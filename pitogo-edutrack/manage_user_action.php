<?php
session_start();
require 'db.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - manage_user_action.php
|--------------------------------------------------------------------------
| Final database flow:
|
| ARCHIVE:
| users -> archived_users
| then remove from users
|
| RESTORE:
| archived_users -> users
| then remove from archived_users
|
| DELETE FROM USERS:
| users -> deleted_users
| then remove from users
|
| DELETE FROM ARCHIVE:
| archived_users -> deleted_users
| then remove from archived_users
|
| Roles:
| - admin: approve, disapprove/reject, archive
| - superadmin: approve, disapprove/reject, archive, restore, delete
|--------------------------------------------------------------------------
*/

function redirectBack(string $tab = 'active', string $msg = ''): void {
    if ($tab === 'users') {
        $tab = 'active';
    }

    $url = "admin/user-management.php?tab=" . urlencode($tab);

    if ($msg !== '') {
        $url .= "&msg=" . urlencode($msg);
    }

    header("Location: " . $url);
    exit();
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
        ");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        if (!columnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    } catch (Exception $e) {
        // Keep running. Real action errors will still be shown.
    }
}

function getTableColumns(PDO $pdo, string $table): array {
    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `$table`");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        return array_column($rows, 'Field');
    } catch (Exception $e) {
        return [];
    }
}

function ensureSystemTables(PDO $pdo): void {
    ensureColumn($pdo, 'users', 'is_archived', "TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'users', 'is_deleted', "TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'users', 'updated_at', "DATETIME NULL");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archived_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_user_id INT NULL,
            first_name VARCHAR(100) NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(150) NULL,
            role VARCHAR(50) NULL,
            id_num VARCHAR(100) NULL,
            status_before VARCHAR(50) NULL,
            status_after VARCHAR(50) DEFAULT 'archived',
            archive_reason VARCHAR(255) NULL,
            archived_by INT NULL,
            archived_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            snapshot_json LONGTEXT NULL
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deleted_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_user_id INT NULL,
            first_name VARCHAR(100) NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(150) NULL,
            role VARCHAR(50) NULL,
            id_num VARCHAR(100) NULL,
            status_before VARCHAR(50) NULL,
            status_after VARCHAR(50) DEFAULT 'deleted',
            delete_reason VARCHAR(255) NULL,
            deleted_by INT NULL,
            deleted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            snapshot_json LONGTEXT NULL
        )
    ");

    $archiveCols = [
        'original_user_id' => 'INT NULL',
        'first_name' => 'VARCHAR(100) NULL',
        'middle_name' => 'VARCHAR(100) NULL',
        'last_name' => 'VARCHAR(100) NULL',
        'email' => 'VARCHAR(150) NULL',
        'role' => 'VARCHAR(50) NULL',
        'id_num' => 'VARCHAR(100) NULL',
        'status_before' => 'VARCHAR(50) NULL',
        'status_after' => "VARCHAR(50) DEFAULT 'archived'",
        'archive_reason' => 'VARCHAR(255) NULL',
        'archived_by' => 'INT NULL',
        'archived_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'snapshot_json' => 'LONGTEXT NULL'
    ];

    foreach ($archiveCols as $col => $def) {
        ensureColumn($pdo, 'archived_users', $col, $def);
    }

    $deletedCols = [
        'original_user_id' => 'INT NULL',
        'first_name' => 'VARCHAR(100) NULL',
        'middle_name' => 'VARCHAR(100) NULL',
        'last_name' => 'VARCHAR(100) NULL',
        'email' => 'VARCHAR(150) NULL',
        'role' => 'VARCHAR(50) NULL',
        'id_num' => 'VARCHAR(100) NULL',
        'status_before' => 'VARCHAR(50) NULL',
        'status_after' => "VARCHAR(50) DEFAULT 'deleted'",
        'delete_reason' => 'VARCHAR(255) NULL',
        'deleted_by' => 'INT NULL',
        'deleted_at' => 'DATETIME DEFAULT CURRENT_TIMESTAMP',
        'snapshot_json' => 'LONGTEXT NULL'
    ];

    foreach ($deletedCols as $col => $def) {
        ensureColumn($pdo, 'deleted_users', $col, $def);
    }
}

function logAudit(PDO $pdo, ?int $actorId, ?string $actorRole, string $action, string $targetTable, int $targetId): void {
    try {
        if (!tableExists($pdo, 'audit_logs')) {
            return;
        }

        $cols = [];
        $vals = [];

        if (columnExists($pdo, 'audit_logs', 'user_id')) {
            $cols[] = 'user_id';
            $vals[] = $actorId;
        }

        if (columnExists($pdo, 'audit_logs', 'user_role')) {
            $cols[] = 'user_role';
            $vals[] = $actorRole;
        }

        if (columnExists($pdo, 'audit_logs', 'action')) {
            $cols[] = 'action';
            $vals[] = $action;
        }

        if (columnExists($pdo, 'audit_logs', 'target_table')) {
            $cols[] = 'target_table';
            $vals[] = $targetTable;
        }

        if (columnExists($pdo, 'audit_logs', 'target_id')) {
            $cols[] = 'target_id';
            $vals[] = $targetId;
        }

        if (columnExists($pdo, 'audit_logs', 'ip_address')) {
            $cols[] = 'ip_address';
            $vals[] = $_SERVER['REMOTE_ADDR'] ?? null;
        }

        if (columnExists($pdo, 'audit_logs', 'created_at')) {
            $cols[] = 'created_at';
            $vals[] = date('Y-m-d H:i:s');
        }

        if (!$cols) {
            return;
        }

        $placeholders = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO audit_logs (`" . implode('`,`', $cols) . "`) VALUES ($placeholders)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($vals);
    } catch (Exception $e) {}
}

function fetchUser(PDO $pdo, int $userId): ?array {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$userId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function fetchArchivedUser(PDO $pdo, int $originalUserId): ?array {
    $stmt = $pdo->prepare("
        SELECT *
        FROM archived_users
        WHERE original_user_id = ?
        ORDER BY archived_at DESC, id DESC
        LIMIT 1
    ");
    $stmt->execute([$originalUserId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    return $row ?: null;
}

function userCanBeManaged(array $user, int $currentUserId, string $currentRole, string $action): array {
    $targetId = (int)($user['id'] ?? 0);
    $targetRole = strtolower(trim($user['role'] ?? ''));

    if ($targetId === $currentUserId && in_array($action, ['archive', 'delete', 'reject'], true)) {
        return [false, 'You cannot perform this action on your own account.'];
    }

    if ($currentRole !== 'superadmin' && in_array($targetRole, ['admin', 'superadmin'], true)) {
        return [false, 'Only superadmin can manage admin accounts.'];
    }

    if (in_array($action, ['delete', 'restore'], true) && $currentRole !== 'superadmin') {
        return [false, 'Only superadmin can perform this action.'];
    }

    return [true, ''];
}

function insertArchivedUser(PDO $pdo, array $user, int $currentUserId, string $reason = 'Archived by admin'): void {
    $stmt = $pdo->prepare("
        INSERT INTO archived_users (
            original_user_id,
            first_name,
            middle_name,
            last_name,
            email,
            role,
            id_num,
            status_before,
            status_after,
            archive_reason,
            archived_by,
            archived_at,
            snapshot_json
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, 'archived', ?, ?, NOW(), ?
        )
    ");

    $stmt->execute([
        $user['id'] ?? null,
        $user['first_name'] ?? null,
        $user['middle_name'] ?? null,
        $user['last_name'] ?? null,
        $user['email'] ?? null,
        $user['role'] ?? null,
        $user['id_num'] ?? null,
        $user['status'] ?? 'active',
        $reason,
        $currentUserId,
        json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
}

function insertDeletedUser(PDO $pdo, array $user, int $currentUserId, string $reason = 'Deleted by superadmin'): void {
    $originalId = $user['id'] ?? $user['original_user_id'] ?? null;

    $stmt = $pdo->prepare("
        INSERT INTO deleted_users (
            original_user_id,
            first_name,
            middle_name,
            last_name,
            email,
            role,
            id_num,
            status_before,
            status_after,
            delete_reason,
            deleted_by,
            deleted_at,
            snapshot_json
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, 'deleted', ?, ?, NOW(), ?
        )
    ");

    $stmt->execute([
        $originalId,
        $user['first_name'] ?? null,
        $user['middle_name'] ?? null,
        $user['last_name'] ?? null,
        $user['email'] ?? null,
        $user['role'] ?? null,
        $user['id_num'] ?? null,
        $user['status'] ?? $user['status_before'] ?? 'active',
        $reason,
        $currentUserId,
        json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
    ]);
}

function restoreSnapshotToUsers(PDO $pdo, array $archiveRow): void {
    $originalUserId = (int)($archiveRow['original_user_id'] ?? 0);

    if ($originalUserId <= 0) {
        throw new Exception('Invalid archived user ID.');
    }

    $existing = fetchUser($pdo, $originalUserId);

    $oldStatus = trim((string)($archiveRow['status_before'] ?? ''));
    if ($oldStatus === '' || strtolower($oldStatus) === 'archived') {
        $oldStatus = 'active';
    }

    if ($existing) {
        $stmt = $pdo->prepare("
            UPDATE users
            SET status = ?,
                is_archived = 0,
                is_deleted = 0,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$oldStatus, $originalUserId]);
        return;
    }

    $snapshot = json_decode($archiveRow['snapshot_json'] ?? '', true);

    if (!is_array($snapshot)) {
        $snapshot = [
            'id' => $originalUserId,
            'first_name' => $archiveRow['first_name'] ?? null,
            'middle_name' => $archiveRow['middle_name'] ?? null,
            'last_name' => $archiveRow['last_name'] ?? null,
            'email' => $archiveRow['email'] ?? null,
            'role' => $archiveRow['role'] ?? null,
            'id_num' => $archiveRow['id_num'] ?? null,
            'status' => $oldStatus
        ];
    }

    $userColumns = getTableColumns($pdo, 'users');
    $insertData = [];

    foreach ($snapshot as $column => $value) {
        if (in_array($column, $userColumns, true)) {
            $insertData[$column] = $value;
        }
    }

    if (in_array('id', $userColumns, true)) {
        $insertData['id'] = $originalUserId;
    }

    if (in_array('status', $userColumns, true)) {
        $insertData['status'] = $oldStatus;
    }

    if (in_array('is_archived', $userColumns, true)) {
        $insertData['is_archived'] = 0;
    }

    if (in_array('is_deleted', $userColumns, true)) {
        $insertData['is_deleted'] = 0;
    }

    if (in_array('updated_at', $userColumns, true)) {
        $insertData['updated_at'] = date('Y-m-d H:i:s');
    }

    if (empty($insertData)) {
        throw new Exception('No matching user columns found for restore.');
    }

    $columns = array_keys($insertData);
    $placeholders = implode(',', array_fill(0, count($columns), '?'));

    $sql = "INSERT INTO users (`" . implode('`,`', $columns) . "`) VALUES ($placeholders)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_values($insertData));
}

function approveUser(PDO $pdo, int $userId, int $currentUserId, string $currentRole): array {
    $user = fetchUser($pdo, $userId);

    if (!$user) {
        return [false, 'User not found.'];
    }

    [$allowed, $reason] = userCanBeManaged($user, $currentUserId, $currentRole, 'approve');
    if (!$allowed) {
        return [false, $reason];
    }

    $parts = [];
    $params = [];

    $parts[] = "status = 'active'";

    if (columnExists($pdo, 'users', 'email_verified_at')) {
        $parts[] = "email_verified_at = COALESCE(email_verified_at, NOW())";
    }

    if (columnExists($pdo, 'users', 'otp_code')) {
        $parts[] = "otp_code = NULL";
    }

    if (columnExists($pdo, 'users', 'otp_expiry')) {
        $parts[] = "otp_expiry = NULL";
    }

    if (columnExists($pdo, 'users', 'is_archived')) {
        $parts[] = "is_archived = 0";
    }

    if (columnExists($pdo, 'users', 'is_deleted')) {
        $parts[] = "is_deleted = 0";
    }

    if (columnExists($pdo, 'users', 'updated_at')) {
        $parts[] = "updated_at = NOW()";
    }

    $params[] = $userId;

    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $parts) . " WHERE id = ?");
    $stmt->execute($params);

    logAudit($pdo, $currentUserId, $currentRole, 'Approved user account', 'users', $userId);

    return [true, 'Approved'];
}

function rejectUser(PDO $pdo, int $userId, int $currentUserId, string $currentRole): array {
    $user = fetchUser($pdo, $userId);

    if (!$user) {
        return [false, 'User not found.'];
    }

    [$allowed, $reason] = userCanBeManaged($user, $currentUserId, $currentRole, 'reject');
    if (!$allowed) {
        return [false, $reason];
    }

    insertDeletedUser($pdo, $user, $currentUserId, 'Pending account disapproved/rejected');

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    logAudit($pdo, $currentUserId, $currentRole, 'Disapproved/rejected user account and moved to deleted_users', 'users', $userId);

    return [true, 'Disapproved'];
}

function archiveUser(PDO $pdo, int $userId, int $currentUserId, string $currentRole): array {
    $user = fetchUser($pdo, $userId);

    if (!$user) {
        return [false, 'User not found in users table.'];
    }

    [$allowed, $reason] = userCanBeManaged($user, $currentUserId, $currentRole, 'archive');
    if (!$allowed) {
        return [false, $reason];
    }

    insertArchivedUser($pdo, $user, $currentUserId, 'Archived by admin');

    /*
    | Required behavior:
    | Once archived, remove from users table.
    */
    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    logAudit($pdo, $currentUserId, $currentRole, 'Moved user from users to archived_users', 'archived_users', $userId);

    return [true, 'Archived'];
}

function restoreUser(PDO $pdo, int $userId, int $currentUserId, string $currentRole): array {
    if ($currentRole !== 'superadmin') {
        return [false, 'Only superadmin can restore archived accounts.'];
    }

    $archiveRow = fetchArchivedUser($pdo, $userId);

    if (!$archiveRow) {
        return [false, 'Archived record not found.'];
    }

    restoreSnapshotToUsers($pdo, $archiveRow);

    /*
    | Required behavior:
    | Once restored, remove from archived_users table.
    */
    $stmt = $pdo->prepare("DELETE FROM archived_users WHERE original_user_id = ?");
    $stmt->execute([$userId]);

    logAudit($pdo, $currentUserId, $currentRole, 'Restored user from archived_users to users', 'users', $userId);

    return [true, 'Restored'];
}

function deleteUser(PDO $pdo, int $userId, int $currentUserId, string $currentRole, string $currentTab): array {
    if ($currentRole !== 'superadmin') {
        return [false, 'Only superadmin can delete accounts.'];
    }

    /*
    |--------------------------------------------------------------------------
    | Delete from archive tab:
    | archived_users -> deleted_users
    | then remove from archived_users
    |--------------------------------------------------------------------------
    */
    if ($currentTab === 'archive') {
        $archiveRow = fetchArchivedUser($pdo, $userId);

        if (!$archiveRow) {
            return [false, 'Archived record not found.'];
        }

        insertDeletedUser($pdo, $archiveRow, $currentUserId, 'Deleted from archive by superadmin');

        $stmt = $pdo->prepare("DELETE FROM archived_users WHERE original_user_id = ?");
        $stmt->execute([$userId]);

        logAudit($pdo, $currentUserId, $currentRole, 'Moved archived user to deleted_users and removed from archived_users', 'deleted_users', $userId);

        return [true, 'Deleted from archive'];
    }

    /*
    |--------------------------------------------------------------------------
    | Delete from active/pending users:
    | users -> deleted_users
    | then remove from users
    |--------------------------------------------------------------------------
    */
    $user = fetchUser($pdo, $userId);

    if (!$user) {
        return [false, 'User not found in users table.'];
    }

    [$allowed, $reason] = userCanBeManaged($user, $currentUserId, $currentRole, 'delete');
    if (!$allowed) {
        return [false, $reason];
    }

    insertDeletedUser($pdo, $user, $currentUserId, 'Deleted by superadmin');

    $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
    $stmt->execute([$userId]);

    /*
    | Clean possible duplicate archive copy too.
    */
    $stmtArchive = $pdo->prepare("DELETE FROM archived_users WHERE original_user_id = ?");
    $stmtArchive->execute([$userId]);

    logAudit($pdo, $currentUserId, $currentRole, 'Moved user from users to deleted_users', 'deleted_users', $userId);

    return [true, 'Deleted'];
}

/*
|--------------------------------------------------------------------------
| Security
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: index.html?error=" . urlencode("Please login first."));
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if (!in_array($currentRole, ['admin', 'superadmin'], true)) {
    header("Location: index.html?error=" . urlencode("Unauthorized access."));
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirectBack('active');
}

ensureSystemTables($pdo);

/*
|--------------------------------------------------------------------------
| Inputs
|--------------------------------------------------------------------------
*/

$action = strtolower(trim($_POST['action'] ?? $_POST['bulk_action'] ?? ''));
$currentTab = strtolower(trim($_POST['current_tab'] ?? $_POST['tab'] ?? 'active'));

if ($currentTab === 'users') {
    $currentTab = 'active';
}

if ($action === 'disapprove') {
    $action = 'reject';
}

$allowedActions = ['approve', 'reject', 'archive', 'restore', 'delete'];

if (!in_array($action, $allowedActions, true)) {
    redirectBack($currentTab, 'Invalid action.');
}

$userIds = [];

if (isset($_POST['user_id']) && (int)$_POST['user_id'] > 0) {
    $userIds[] = (int)$_POST['user_id'];
}

if (!empty($_POST['selected_users']) && is_array($_POST['selected_users'])) {
    foreach ($_POST['selected_users'] as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $userIds[] = $id;
        }
    }
}

if (!empty($_POST['user_ids']) && is_array($_POST['user_ids'])) {
    foreach ($_POST['user_ids'] as $id) {
        $id = (int)$id;
        if ($id > 0) {
            $userIds[] = $id;
        }
    }
}

$userIds = array_values(array_unique($userIds));

if (!$userIds) {
    redirectBack($currentTab, 'Please select at least one account.');
}

if (in_array($action, ['delete', 'restore'], true) && $currentRole !== 'superadmin') {
    redirectBack($currentTab, 'Only superadmin can perform this action.');
}

/*
|--------------------------------------------------------------------------
| Process
|--------------------------------------------------------------------------
*/

$successCount = 0;
$failedCount = 0;
$firstError = '';

try {
    $pdo->beginTransaction();

    foreach ($userIds as $userId) {
        if ($action === 'approve') {
            [$ok, $message] = approveUser($pdo, $userId, $currentUserId, $currentRole);
        } elseif ($action === 'reject') {
            [$ok, $message] = rejectUser($pdo, $userId, $currentUserId, $currentRole);
        } elseif ($action === 'archive') {
            [$ok, $message] = archiveUser($pdo, $userId, $currentUserId, $currentRole);
        } elseif ($action === 'restore') {
            [$ok, $message] = restoreUser($pdo, $userId, $currentUserId, $currentRole);
        } elseif ($action === 'delete') {
            [$ok, $message] = deleteUser($pdo, $userId, $currentUserId, $currentRole, $currentTab);
        } else {
            [$ok, $message] = [false, 'Invalid action.'];
        }

        if ($ok) {
            $successCount++;
        } else {
            $failedCount++;
            if ($firstError === '') {
                $firstError = $message;
            }
        }
    }

    $pdo->commit();

    $labels = [
        'approve' => 'approved',
        'reject' => 'disapproved',
        'archive' => 'archived',
        'restore' => 'restored',
        'delete' => 'deleted'
    ];

    $msg = "{$successCount} account(s) {$labels[$action]} successfully.";

    if ($failedCount > 0) {
        $msg .= " {$failedCount} failed.";
        if ($firstError !== '') {
            $msg .= " {$firstError}";
        }
    }

    if ($action === 'approve' || $action === 'reject') {
        redirectBack('pending', $msg);
    }

    if ($action === 'archive') {
        redirectBack('active', $msg);
    }

    if ($action === 'restore') {
        redirectBack('archive', $msg);
    }

    if ($action === 'delete') {
        redirectBack($currentTab === 'archive' ? 'archive' : 'active', $msg);
    }

    redirectBack($currentTab, $msg);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    redirectBack($currentTab, 'Bulk action failed: ' . $e->getMessage());
}
