<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - User Management
|--------------------------------------------------------------------------
| Save as:
| admin/user-management.php
|
| Uses:
| ../manage_user_action.php
|
| Supported roles:
| superadmin, admin, staff, student, parent
|
| Removed:
| teacher, classes, grades, excuse letters
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if (!in_array($currentRole, ['admin', 'superadmin'], true)) {
    header("Location: ../index.html?error=" . urlencode("Unauthorized access."));
    exit();
}

$isSuperAdmin = $currentRole === 'superadmin';
$themeColor = $isSuperAdmin ? '#7C3AED' : '#0056B3';
$roleLabel = $isSuperAdmin ? 'Super Administrator' : 'Administrator';

function e($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
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

function safeCount(PDO $pdo, string $sql, array $params = []): int {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return (int)$stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function safeFetchAll(PDO $pdo, string $sql, array $params = []): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

function profilePath(array $row, string $name): string {
    $profile = $row['profile_picture'] ?? $row['profile_pic'] ?? '';

    if ($profile) {
        if (preg_match('/^https?:\/\//i', $profile)) {
            return $profile;
        }

        return '../uploads/profiles/' . ltrim($profile, '/');
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'User') . '&background=0056B3&color=fff';
}

function getFullName(array $row): string {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function getIdNumber(array $row): string {
    return $row['id_num'] ?? $row['role_id_number'] ?? $row['lrn'] ?? 'N/A';
}

function getEmergencyPerson(array $row): string {
    return $row['emergency_contact_person']
        ?? $row['emergency_contact_name']
        ?? $row['guardian_name']
        ?? $row['contact_person']
        ?? 'N/A';
}

function getEmergencyNumber(array $row): string {
    return $row['emergency_contact_number']
        ?? $row['emergency_contact_no']
        ?? $row['guardian_contact']
        ?? $row['phone']
        ?? 'N/A';
}

/*
|--------------------------------------------------------------------------
| Current admin info
|--------------------------------------------------------------------------
*/

$stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtAdmin->execute([$currentUserId]);
$currentUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];

$adminNameRaw = getFullName($currentUser) ?: 'System Admin';
$adminAvatar = profilePath($currentUser, $adminNameRaw);

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$allowedTabs = ['active', 'pending', 'archive', 'deleted'];
$activeTab = $_GET['tab'] ?? 'active';

if (!in_array($activeTab, $allowedTabs, true)) {
    $activeTab = 'active';
}

$search = trim($_GET['search'] ?? '');
$roleFilter = strtolower(trim($_GET['role'] ?? 'all'));
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');

$allowedRoles = ['all', 'student', 'parent', 'staff', 'admin', 'superadmin'];

if (!in_array($roleFilter, $allowedRoles, true)) {
    $roleFilter = 'all';
}


/*
|--------------------------------------------------------------------------
| Bulk account actions
|--------------------------------------------------------------------------
| Admin can approve, disapprove, and archive.
| Superadmin can approve, disapprove, archive, and delete.
| Archive and delete actions create a snapshot in separate tables first.
|--------------------------------------------------------------------------
*/

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS archived_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_user_id INT NOT NULL,
            first_name VARCHAR(100) NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(190) NULL,
            role VARCHAR(50) NULL,
            id_num VARCHAR(100) NULL,
            status_before VARCHAR(50) NULL,
            snapshot_json LONGTEXT NULL,
            archived_by INT NULL,
            archived_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS deleted_users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_user_id INT NOT NULL,
            first_name VARCHAR(100) NULL,
            middle_name VARCHAR(100) NULL,
            last_name VARCHAR(100) NULL,
            email VARCHAR(190) NULL,
            role VARCHAR(50) NULL,
            id_num VARCHAR(100) NULL,
            status_before VARCHAR(50) NULL,
            snapshot_json LONGTEXT NULL,
            deleted_by INT NULL,
            deleted_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

function redirectWithBulkMessage(string $tab, string $message): void {
    header("Location: user-management.php?tab=" . urlencode($tab) . "&msg=" . urlencode($message));
    exit();
}

function updateUserStatus(PDO $pdo, int $userId, array $fields): void {
    $sets = [];
    $values = [];

    foreach ($fields as $column => $value) {
        if (columnExists($pdo, 'users', $column)) {
            $sets[] = "`$column` = ?";
            $values[] = $value;
        }
    }

    if (columnExists($pdo, 'users', 'updated_at')) {
        $sets[] = "`updated_at` = NOW()";
    }

    if (!$sets) return;

    $values[] = $userId;
    $stmt = $pdo->prepare("UPDATE users SET " . implode(', ', $sets) . " WHERE id = ?");
    $stmt->execute($values);
}

function insertUserSnapshot(PDO $pdo, string $table, array $user, int $actorId): void {
    $isArchiveTable = $table === 'archived_users';

    $dateColumn = $isArchiveTable ? 'archived_at' : 'deleted_at';
    $actorColumn = $isArchiveTable ? 'archived_by' : 'deleted_by';

    $stmt = $pdo->prepare("
        INSERT INTO `$table`
        (
            original_user_id,
            first_name,
            middle_name,
            last_name,
            email,
            role,
            id_num,
            status_before,
            snapshot_json,
            `$actorColumn`,
            `$dateColumn`
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
    ");

    $stmt->execute([
        (int)($user['id'] ?? 0),
        $user['first_name'] ?? null,
        $user['middle_name'] ?? null,
        $user['last_name'] ?? null,
        $user['email'] ?? null,
        $user['role'] ?? null,
        $user['id_num'] ?? ($user['lrn'] ?? null),
        $user['status'] ?? null,
        json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        $actorId
    ]);
}

function addAuditLogIfAvailable(PDO $pdo, int $actorId, string $action): void {
    try {
        if (!tableExists($pdo, 'audit_logs')) return;

        $columns = [];
        $values = [];

        if (columnExists($pdo, 'audit_logs', 'user_id')) {
            $columns[] = 'user_id';
            $values[] = $actorId;
        }

        if (columnExists($pdo, 'audit_logs', 'action')) {
            $columns[] = 'action';
            $values[] = $action;
        }

        if (columnExists($pdo, 'audit_logs', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
        }

        if (!$columns) return;

        $stmt = $pdo->prepare("INSERT INTO audit_logs (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")");
        $stmt->execute($values);
    } catch (Exception $e) {}
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_action'])) {
    $bulkAction = strtolower(trim($_POST['bulk_action'] ?? ''));
    $selectedIds = $_POST['selected_users'] ?? [];
    $returnTab = $_POST['current_tab'] ?? $activeTab;

    if (!is_array($selectedIds)) {
        $selectedIds = [];
    }

    $selectedIds = array_values(array_unique(array_filter(array_map('intval', $selectedIds), fn($id) => $id > 0)));

    $allowedBulkActions = ['approve', 'disapprove', 'archive'];

    if ($isSuperAdmin) {
        $allowedBulkActions[] = 'delete';
    }

    if (!in_array($bulkAction, $allowedBulkActions, true)) {
        redirectWithBulkMessage($returnTab, 'Bulk action is not allowed for your account.');
    }

    if (empty($selectedIds)) {
        redirectWithBulkMessage($returnTab, 'Please select at least one account first.');
    }

    $placeholders = implode(',', array_fill(0, count($selectedIds), '?'));
    $stmtSelected = $pdo->prepare("SELECT * FROM users WHERE id IN ($placeholders)");
    $stmtSelected->execute($selectedIds);
    $selectedUsers = $stmtSelected->fetchAll(PDO::FETCH_ASSOC);

    $processed = 0;
    $skipped = 0;

    try {
        $pdo->beginTransaction();

        foreach ($selectedUsers as $targetUser) {
            $targetId = (int)($targetUser['id'] ?? 0);
            $targetRole = strtolower(trim($targetUser['role'] ?? ''));

            if ($targetId <= 0 || $targetId === $currentUserId) {
                $skipped++;
                continue;
            }

            if (!$isSuperAdmin && in_array($targetRole, ['admin', 'superadmin'], true)) {
                $skipped++;
                continue;
            }

            if ($bulkAction === 'approve') {
                updateUserStatus($pdo, $targetId, [
                    'status' => 'active',
                    'is_archived' => 0,
                    'is_deleted' => 0
                ]);
                $processed++;
                continue;
            }

            if ($bulkAction === 'disapprove') {
                updateUserStatus($pdo, $targetId, [
                    'status' => 'disapproved',
                    'is_archived' => 0,
                    'is_deleted' => 0
                ]);
                $processed++;
                continue;
            }

            if ($bulkAction === 'archive') {
                insertUserSnapshot($pdo, 'archived_users', $targetUser, $currentUserId);

                updateUserStatus($pdo, $targetId, [
                    'status' => 'archived',
                    'is_archived' => 1,
                    'is_deleted' => 0
                ]);

                $processed++;
                continue;
            }

            if ($bulkAction === 'delete' && $isSuperAdmin) {
                insertUserSnapshot($pdo, 'deleted_users', $targetUser, $currentUserId);

                updateUserStatus($pdo, $targetId, [
                    'status' => 'deleted',
                    'is_archived' => 0,
                    'is_deleted' => 1
                ]);

                $processed++;
                continue;
            }

            $skipped++;
        }

        addAuditLogIfAvailable(
            $pdo,
            $currentUserId,
            'Bulk user action: ' . $bulkAction . ' | Processed: ' . $processed . ' | Skipped: ' . $skipped
        );

        $pdo->commit();

        redirectWithBulkMessage(
            $returnTab,
            ucfirst($bulkAction) . " completed. Processed: {$processed}. Skipped: {$skipped}."
        );
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        redirectWithBulkMessage($returnTab, 'Bulk action failed: ' . $e->getMessage());
    }
}


/*
|--------------------------------------------------------------------------
| Counts
|--------------------------------------------------------------------------
*/

$adminRestriction = $isSuperAdmin ? "" : " AND role NOT IN ('admin', 'superadmin')";

$totalActive = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE status = 'active'
    AND is_archived = 0
    AND is_deleted = 0
    $adminRestriction
");

$totalPending = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE status = 'pending'
    AND is_archived = 0
    AND is_deleted = 0
    $adminRestriction
");

$totalArchived = tableExists($pdo, 'archived_users')
    ? safeCount($pdo, "SELECT COUNT(*) FROM archived_users")
    : safeCount($pdo, "SELECT COUNT(*) FROM users WHERE is_archived = 1 $adminRestriction");

$totalDeleted = tableExists($pdo, 'deleted_users')
    ? safeCount($pdo, "SELECT COUNT(*) FROM deleted_users")
    : safeCount($pdo, "SELECT COUNT(*) FROM users WHERE is_deleted = 1 $adminRestriction");

/*
|--------------------------------------------------------------------------
| Main query
|--------------------------------------------------------------------------
*/

$params = [];

if ($activeTab === 'archive') {
    if (tableExists($pdo, 'archived_users')) {
        $query = "
            SELECT
                id AS archive_row_id,
                original_user_id AS id,
                first_name,
                middle_name,
                last_name,
                email,
                role,
                id_num,
                'archived' AS status,
                archived_at AS created_at,
                archived_by
            FROM archived_users
            WHERE 1=1
        ";
        $dateColumn = 'archived_at';
    } else {
        $query = "
            SELECT *
            FROM users
            WHERE is_archived = 1
            AND is_deleted = 0
        ";
        $dateColumn = 'updated_at';
    }

} elseif ($activeTab === 'deleted') {
    if (tableExists($pdo, 'deleted_users')) {
        $query = "
            SELECT
                id AS deleted_row_id,
                original_user_id AS id,
                first_name,
                middle_name,
                last_name,
                email,
                role,
                id_num,
                'deleted' AS status,
                deleted_at AS created_at,
                deleted_by
            FROM deleted_users
            WHERE 1=1
        ";
        $dateColumn = 'deleted_at';
    } else {
        $query = "
            SELECT *
            FROM users
            WHERE is_deleted = 1
        ";
        $dateColumn = 'updated_at';
    }

} elseif ($activeTab === 'pending') {
    $query = "
        SELECT *
        FROM users
        WHERE status = 'pending'
        AND is_archived = 0
        AND is_deleted = 0
    ";
    $dateColumn = 'created_at';

} else {
    $query = "
        SELECT *
        FROM users
        WHERE status = 'active'
        AND is_archived = 0
        AND is_deleted = 0
    ";
    $dateColumn = 'created_at';
}

if (!$isSuperAdmin && $activeTab !== 'deleted') {
    $query .= " AND role NOT IN ('admin', 'superadmin')";
}

if ($search !== '') {
    $query .= "
        AND (
            first_name LIKE ?
            OR middle_name LIKE ?
            OR last_name LIKE ?
            OR email LIKE ?
            OR id_num LIKE ?
            OR id LIKE ?
        )
    ";

    $searchParam = "%{$search}%";
    array_push($params, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam, $searchParam);
}

if ($roleFilter !== 'all') {
    $query .= " AND role = ?";
    $params[] = $roleFilter;
}

if ($dateFrom !== '') {
    $query .= " AND DATE($dateColumn) >= ?";
    $params[] = $dateFrom;
}

if ($dateTo !== '') {
    $query .= " AND DATE($dateColumn) <= ?";
    $params[] = $dateTo;
}

$query .= " ORDER BY $dateColumn DESC";

$resultData = safeFetchAll($pdo, $query, $params);

/*
|--------------------------------------------------------------------------
| Parent linked children
|--------------------------------------------------------------------------
*/

$parentIds = [];

foreach ($resultData as $row) {
    if (($row['role'] ?? '') === 'parent' && !empty($row['id']) && $activeTab !== 'archive' && $activeTab !== 'deleted') {
        $parentIds[] = (int)$row['id'];
    }
}

$parentLinks = [];

if ($parentIds && tableExists($pdo, 'parent_student_links')) {
    $placeholders = implode(',', array_fill(0, count($parentIds), '?'));

    $links = safeFetchAll($pdo, "
        SELECT
            psl.parent_id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.id_num
        FROM parent_student_links psl
        JOIN users s ON s.id = psl.student_id
        WHERE psl.parent_id IN ($placeholders)
        AND psl.status = 'active'
    ", $parentIds);

    foreach ($links as $link) {
        $childName = trim(($link['first_name'] ?? '') . ' ' . ($link['middle_name'] ?? '') . ' ' . ($link['last_name'] ?? ''));
        $childId = $link['id_num'] ?? 'N/A';
        $parentLinks[(int)$link['parent_id']][] = $childName . ' (LRN: ' . $childId . ')';
    }
}

/*
|--------------------------------------------------------------------------
| Export CSV
|--------------------------------------------------------------------------
*/

if (isset($_GET['export']) && $_GET['export'] === 'true') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Pitogo_EduTrack_User_Report_' . ucfirst($activeTab) . '_' . date('Y-m-d') . '.csv');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Pitogo EduTrack User Management Report']);
    fputcsv($output, ['Generated By', $adminNameRaw]);
    fputcsv($output, ['Generated On', date('Y-m-d h:i A')]);
    fputcsv($output, ['Report Type', ucfirst($activeTab)]);
    fputcsv($output, []);

    fputcsv($output, [
        'No.',
        'User ID',
        'ID Number',
        'Full Name',
        'Email',
        'Role',
        'Status',
        'Date'
    ]);

    $counter = 1;

    foreach ($resultData as $row) {
        fputcsv($output, [
            $counter++,
            $row['id'] ?? '',
            getIdNumber($row),
            getFullName($row),
            $row['email'] ?? '',
            ucfirst($row['role'] ?? ''),
            ucfirst($row['status'] ?? ''),
            !empty($row['created_at']) ? date('Y-m-d h:i A', strtotime($row['created_at'])) : ''
        ]);
    }

    fclose($output);
    exit();
}

$msg = '';
if (isset($_GET['msg'])) {
    $msgText = trim($_GET['msg']);

    $msg = "
        <div class='alert alert-success'>
            <i class='fa fa-check-circle'></i>
            " . e($msgText) . "
        </div>
    ";
}

$filteredTotal = count($resultData);
$filteredStudents = 0;
$filteredParents = 0;
$filteredStaff = 0;
$filteredAdmins = 0;

foreach ($resultData as $row) {
    $role = strtolower($row['role'] ?? '');

    if ($role === 'student') {
        $filteredStudents++;
    } elseif ($role === 'parent') {
        $filteredParents++;
    } elseif ($role === 'staff') {
        $filteredStaff++;
    } elseif (in_array($role, ['admin', 'superadmin'], true)) {
        $filteredAdmins++;
    }
}

$reportGenerated = date('F d, Y h:i A');
$reportTitle = ucfirst($activeTab) . ' User Report';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>User Management | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --primary-navy: #0B1C3D;
    --primary-blue: <?= e($themeColor) ?>;
    --bg-main: #F4F7F9;
    --surface-white: #FFFFFF;
    --text-dark: #1E293B;
    --text-muted: #64748B;
    --border-color: #E2E8F0;
    --success: #10B981;
    --warning: #F59E0B;
    --danger: #EF4444;
    --info: #3B82F6;
    --purple: #8B5CF6;
    --shadow-sm: 0 4px 8px rgba(15,23,42,0.04);
    --shadow-md: 0 14px 35px rgba(15,23,42,0.08);
    --transition: all .3s ease;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: 'Inter', sans-serif;
    background: var(--bg-main);
    color: var(--text-dark);
    display: flex;
    min-height: 100vh;
    overflow-x: hidden;
}

h1, h2, h3, h4 {
    font-family: 'Montserrat', sans-serif;
}

.sidebar {
    width: 82px;
    height: 100vh;
    background: var(--surface-white);
    border-right: 1px solid var(--border-color);
    position: fixed;
    left: 0;
    top: 0;
    z-index: 100;
    transition: width .35s ease;
    overflow-x: hidden;
    display: flex;
    flex-direction: column;
}

.sidebar:hover {
    width: 285px;
    box-shadow: 10px 0 30px rgba(15,23,42,0.08);
}

.sidebar-brand {
    padding: 24px 17px;
    display: flex;
    align-items: center;
    gap: 15px;
    border-bottom: 1px solid var(--border-color);
}

.logo-box {
    min-width: 48px;
    height: 48px;
    border-radius: 14px;
    background: #fff;
    border: 1px solid var(--border-color);
    overflow: hidden;
    display: flex;
    align-items: center;
    justify-content: center;
}

.logo-box img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}

.brand-text,
.nav-label,
.nav-item span {
    opacity: 0;
    visibility: hidden;
    transition: opacity .2s ease;
}

.sidebar:hover .brand-text,
.sidebar:hover .nav-label,
.sidebar:hover .nav-item span {
    opacity: 1;
    visibility: visible;
}

.brand-text h2 {
    font-size: 1.05rem;
    color: var(--primary-navy);
    font-weight: 800;
}

.brand-text p {
    font-size: .72rem;
    color: var(--primary-blue);
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: .8px;
}

.nav-menu {
    padding: 22px 14px;
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex: 1;
}

.nav-label {
    margin: 14px 0 8px 14px;
    font-size: .72rem;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 1px;
}

.nav-item {
    padding: 14px 10px;
    border-radius: 13px;
    display: flex;
    align-items: center;
    gap: 20px;
    text-decoration: none;
    color: var(--text-muted);
    font-size: .94rem;
    font-weight: 750;
    transition: var(--transition);
}

.nav-item i {
    min-width: 30px;
    text-align: center;
    font-size: 1.17rem;
}

.nav-item:hover {
    background: var(--bg-main);
    color: var(--primary-blue);
    transform: translateX(5px);
}

.nav-item.active {
    background: rgba(0,86,179,.08);
    color: var(--primary-blue);
}

.logout-link {
    margin-top: auto;
    color: var(--danger);
}

.main-wrapper {
    margin-left: 82px;
    flex: 1;
    min-width: 0;
}

.top-header {
    height: 88px;
    background: var(--surface-white);
    border-bottom: 1px solid var(--border-color);
    padding: 0 38px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    position: sticky;
    top: 0;
    z-index: 50;
}

.header-title h1 {
    font-size: 1.35rem;
    color: var(--primary-navy);
}

.header-title p {
    font-size: .85rem;
    color: var(--text-muted);
    margin-top: 4px;
}

.user-profile {
    display: flex;
    align-items: center;
    gap: 14px;
}

.user-info {
    text-align: right;
}

.user-info h4 {
    font-size: .9rem;
    color: var(--primary-navy);
}

.user-info p {
    font-size: .75rem;
    color: var(--text-muted);
}

.avatar {
    width: 46px;
    height: 46px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid var(--primary-blue);
    padding: 2px;
}

.content-area {
    padding: 38px;
    max-width: 1500px;
    margin: 0 auto;
}

.alert {
    padding: 15px 18px;
    border-radius: 14px;
    margin-bottom: 20px;
    font-weight: 800;
    display: flex;
    align-items: center;
    gap: 10px;
}

.alert-success {
    background: #DCFCE7;
    color: #166534;
    border: 1px solid #A7F3D0;
}

.report-hero {
    background:
        linear-gradient(135deg, rgba(11,28,61,.96), rgba(0,86,179,.78)),
        url('../school-bg.jpg');
    background-size: cover;
    background-position: center;
    color: white;
    border-radius: 24px;
    padding: 34px;
    margin-bottom: 28px;
    display: flex;
    justify-content: space-between;
    gap: 25px;
    align-items: center;
    flex-wrap: wrap;
}

.report-hero h1 {
    font-size: 2rem;
    font-weight: 800;
    margin-bottom: 8px;
}

.report-hero p {
    color: rgba(255,255,255,.86);
    line-height: 1.6;
}

.report-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.btn-primary,
.btn-outline {
    padding: 12px 18px;
    border-radius: 12px;
    border: none;
    font-weight: 800;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    gap: 8px;
    align-items: center;
    font-family: 'Montserrat', sans-serif;
    font-size: .9rem;
}

.btn-primary {
    background: white;
    color: var(--primary-blue);
}

.btn-outline {
    background: rgba(255,255,255,.14);
    color: white;
    border: 1px solid rgba(255,255,255,.25);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(170px, 1fr));
    gap: 18px;
    margin-bottom: 24px;
}

.stat-card {
    background: var(--surface-white);
    padding: 22px;
    border-radius: 18px;
    border: 1px solid var(--border-color);
    display: flex;
    align-items: center;
    gap: 16px;
    box-shadow: var(--shadow-sm);
}

.stat-icon {
    width: 56px;
    height: 56px;
    border-radius: 15px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.3rem;
}

.stat-info h3 {
    font-size: 1.65rem;
    font-weight: 800;
    color: var(--primary-navy);
}

.stat-info p {
    font-size: .78rem;
    color: var(--text-muted);
    font-weight: 800;
    text-transform: uppercase;
}

.tab-menu {
    display: flex;
    gap: 12px;
    margin-bottom: 22px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 12px 18px;
    font-family: 'Montserrat', sans-serif;
    font-weight: 800;
    font-size: .9rem;
    color: var(--text-muted);
    text-decoration: none;
    border-radius: 12px;
    background: white;
    border: 1px solid var(--border-color);
    transition: var(--transition);
}

.tab-btn i {
    margin-right: 8px;
}

.tab-btn:hover {
    color: var(--primary-blue);
    border-color: var(--primary-blue);
}

.tab-btn.active {
    background: var(--primary-blue);
    color: white;
    border-color: var(--primary-blue);
}

.toolbar-card {
    background: var(--surface-white);
    padding: 20px 24px;
    border-radius: 18px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 22px;
    flex-wrap: wrap;
    gap: 15px;
}

.filter-group {
    display: flex;
    gap: 12px;
    align-items: center;
    flex-wrap: wrap;
}

.select-control {
    padding: 11px 14px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
    background: var(--bg-main);
    font-family: 'Inter', sans-serif;
    font-size: .9rem;
    font-weight: 600;
    color: var(--primary-navy);
    outline: none;
}

.btn-filter,
.btn-export,
.btn-print {
    padding: 11px 16px;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 800;
    text-decoration: none;
    border: none;
    display: inline-flex;
    gap: 7px;
    align-items: center;
}

.btn-filter {
    background: var(--primary-blue);
    color: white;
}

.btn-export {
    background: white;
    border: 1px solid var(--border-color);
    color: var(--text-dark);
}

.btn-print {
    background: var(--primary-navy);
    color: white;
}

.report-paper {
    background: white;
    border-radius: 20px;
    border: 1px solid var(--border-color);
    box-shadow: var(--shadow-sm);
    padding: 28px;
    margin-bottom: 25px;
}

.report-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 20px;
    border-bottom: 2px solid var(--border-color);
    padding-bottom: 20px;
    margin-bottom: 22px;
}

.report-brand {
    display: flex;
    align-items: center;
    gap: 16px;
}

.report-brand img {
    width: 64px;
    height: 64px;
    object-fit: contain;
    border: 1px solid var(--border-color);
    border-radius: 14px;
    padding: 5px;
}

.report-brand h2 {
    color: var(--primary-navy);
    font-size: 1.25rem;
    font-weight: 800;
}

.report-brand p,
.report-meta p {
    color: var(--text-muted);
    font-size: .88rem;
    margin-top: 4px;
}

.report-meta {
    text-align: right;
}

.report-meta strong {
    color: var(--primary-navy);
}

.summary-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
    gap: 14px;
    margin-bottom: 22px;
}

.summary-card {
    background: #F8FAFC;
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 16px;
}

.summary-card span {
    display: block;
    color: var(--text-muted);
    font-size: .75rem;
    text-transform: uppercase;
    font-weight: 800;
    margin-bottom: 7px;
}

.summary-card strong {
    color: var(--primary-navy);
    font-size: 1.45rem;
    font-family: 'Montserrat', sans-serif;
}

.table-container {
    background: var(--surface-white);
    border-radius: 18px;
    border: 1px solid var(--border-color);
    overflow-x: auto;
    box-shadow: var(--shadow-sm);
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    min-width: 1050px;
}

.data-table th {
    background: #F8FAFC;
    padding: 16px 18px;
    font-size: .78rem;
    font-weight: 800;
    color: var(--text-muted);
    text-transform: uppercase;
    border-bottom: 2px solid var(--border-color);
    text-align: left;
}

.data-table td {
    padding: 15px 18px;
    border-bottom: 1px solid var(--border-color);
    vertical-align: middle;
}

.data-table tbody tr:hover td {
    background: rgba(0,86,179,.025);
}

.user-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-table-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border-color);
    background: white;
    flex-shrink: 0;
}

.badge-role,
.badge-status {
    padding: 7px 12px;
    border-radius: 20px;
    font-size: .75rem;
    font-weight: 800;
    display: inline-block;
}

.role-student {
    background: rgba(59,130,246,.1);
    color: var(--info);
}

.role-parent {
    background: rgba(245,158,11,.1);
    color: var(--warning);
}

.role-staff {
    background: rgba(16,185,129,.1);
    color: var(--success);
}

.role-admin {
    background: rgba(11,28,61,.1);
    color: var(--primary-navy);
}

.role-superadmin {
    background: rgba(124,58,237,.1);
    color: #7C3AED;
}

.status-active {
    background: rgba(16,185,129,.1);
    color: var(--success);
}

.status-pending {
    background: rgba(245,158,11,.1);
    color: var(--warning);
}

.status-inactive,
.status-archived {
    background: rgba(100,116,139,.1);
    color: var(--text-muted);
}

.status-deleted {
    background: rgba(239,68,68,.1);
    color: var(--danger);
}

.action-group {
    display: flex;
    gap: 8px;
    align-items: center;
}

.action-form {
    display: inline-flex;
    margin: 0;
}

.btn-icon {
    width: 34px;
    height: 34px;
    border-radius: 9px;
    border: 1px solid var(--border-color);
    background: white;
    cursor: pointer;
    transition: .2s;
    display: flex;
    align-items: center;
    justify-content: center;
}


.bulk-actions-card {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:18px;
    padding:16px 18px;
    margin-bottom:18px;
    box-shadow:var(--shadow-sm);
}

.bulk-left {
    display:flex;
    align-items:center;
    gap:12px;
    flex-wrap:wrap;
}

.bulk-title {
    font-weight:900;
    color:var(--primary-navy);
    font-family:'Montserrat',sans-serif;
}

.bulk-hint {
    color:var(--text-muted);
    font-size:.85rem;
    font-weight:700;
}

.bulk-select {
    padding:11px 14px;
    border-radius:12px;
    border:1px solid var(--border-color);
    background:#F8FAFC;
    color:var(--primary-navy);
    font-weight:800;
    outline:none;
}

.bulk-select:focus {
    border-color:var(--primary-blue);
    box-shadow:0 0 0 4px rgba(0,86,179,.08);
    background:white;
}

.bulk-submit {
    border:none;
    border-radius:12px;
    padding:11px 15px;
    background:var(--primary-blue);
    color:white;
    font-family:'Montserrat',sans-serif;
    font-weight:900;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:8px;
    transition:var(--transition);
}

.bulk-submit:hover {
    transform:translateY(-2px);
    box-shadow:var(--shadow-md);
}

.select-col {
    width:48px;
    text-align:center !important;
}

.row-check,
#selectAllUsers {
    width:17px;
    height:17px;
    accent-color:var(--primary-blue);
    cursor:pointer;
}

.selected-count {
    font-weight:900;
    color:var(--primary-blue);
}

@media print {
    .bulk-actions-card,
    .select-col {
        display:none !important;
    }
}

.btn-view {
    background: #EFF6FF;
    color: #3B82F6;
    border-color: #BFDBFE;
}

.btn-approve {
    background: #DCFCE7;
    color: #16A34A;
    border-color: #BBF7D0;
}

.btn-reject,
.btn-delete {
    background: #FEE2E2;
    color: #DC2626;
    border-color: #FCA5A5;
}

.btn-archive {
    background: #FEF3C7;
    color: #D97706;
    border-color: #FDE68A;
}

.btn-restore {
    background: #E0F2FE;
    color: #0284C7;
    border-color: #BAE6FD;
}

.restricted-text {
    font-size: .75rem;
    color: var(--text-muted);
    font-weight: 800;
}

.modal-overlay {
    position: fixed;
    inset: 0;
    background: rgba(11,28,61,.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 1000;
    opacity: 0;
    visibility: hidden;
    transition: var(--transition);
    backdrop-filter: blur(4px);
}

.modal-overlay.active {
    opacity: 1;
    visibility: visible;
}

.modal-card {
    background: white;
    width: 100%;
    max-width: 560px;
    border-radius: 20px;
    padding: 30px;
    transform: translateY(20px);
    transition: var(--transition);
    position: relative;
}

.modal-overlay.active .modal-card {
    transform: translateY(0);
}

.modal-close {
    position: absolute;
    top: 20px;
    right: 20px;
    background: none;
    border: none;
    font-size: 1.2rem;
    color: var(--text-muted);
    cursor: pointer;
}

.modal-header {
    margin-bottom: 20px;
    border-bottom: 1px solid var(--border-color);
    padding-bottom: 15px;
    display: flex;
    align-items: center;
    gap: 14px;
}

.modal-user-avatar {
    width: 64px;
    height: 64px;
    border-radius: 50%;
    object-fit: cover;
    border: 3px solid var(--primary-blue);
    padding: 2px;
    background: white;
    flex-shrink: 0;
}

.modal-header h2 {
    color: var(--primary-navy);
    font-size: 1.5rem;
}

.modal-header p {
    color: var(--text-muted);
    font-size: .9rem;
    margin-top: 5px;
}

.detail-row {
    display: flex;
    justify-content: space-between;
    margin-bottom: 12px;
    gap: 20px;
}

.detail-label {
    font-weight: 700;
    color: var(--text-muted);
}

.detail-value {
    font-weight: 800;
    color: var(--primary-navy);
    text-align: right;
}

.children-list {
    margin-top: 15px;
    background: var(--bg-main);
    padding: 15px;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.children-list h4 {
    font-size: .85rem;
    color: var(--text-muted);
    text-transform: uppercase;
    margin-bottom: 10px;
}

.children-list ul {
    list-style: none;
}

.children-list li {
    font-size: .9rem;
    color: var(--primary-navy);
    font-weight: 600;
    padding: 6px 0;
    border-bottom: 1px dashed #CBD5E1;
}

.children-list li:last-child {
    border-bottom: none;
}

@media(max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media(max-width: 760px) {
    .sidebar {
        display: none;
    }

    .main-wrapper {
        margin-left: 0;
    }

    .top-header {
        padding: 0 20px;
    }

    .user-info {
        display: none;
    }

    .content-area {
        padding: 24px 18px;
    }

    .report-header,
    .report-hero,
    .toolbar-card {
        flex-direction: column;
        align-items: flex-start;
    }

    .report-meta {
        text-align: left;
    }

    .stats-grid {
        grid-template-columns: 1fr;
    }
}

@media print {
    body {
        background: white;
        display: block;
        color: #000;
        font-size: 11px;
    }

    .sidebar,
    .top-header,
    .report-hero,
    .stats-grid,
    .tab-menu,
    .toolbar-card,
    .action-group,
    .modal-overlay,
    .no-print {
        display: none !important;
    }

    .main-wrapper {
        margin-left: 0;
    }

    .content-area {
        padding: 0;
        max-width: none;
    }

    .report-paper,
    .table-container {
        box-shadow: none;
        border: none;
        padding: 0;
        border-radius: 0;
    }

    .report-header {
        display: flex;
        border-bottom: 2px solid #000;
        margin-bottom: 15px;
        padding-bottom: 12px;
    }

    .summary-grid {
        grid-template-columns: repeat(4, 1fr);
        gap: 8px;
        margin-bottom: 12px;
    }

    .summary-card {
        border: 1px solid #999;
        padding: 8px;
        border-radius: 0;
        background: white;
    }

    .data-table {
        min-width: 0;
        width: 100%;
        font-size: 10px;
    }

    .data-table th,
    .data-table td {
        padding: 7px;
        border: 1px solid #999;
    }

    .data-table th {
        background: #eee !important;
        color: #000;
    }

    .badge-role,
    .badge-status {
        background: transparent !important;
        color: #000 !important;
        padding: 0;
        font-size: 10px;
    }

    @page {
        size: landscape;
        margin: 12mm;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-box">
            <img src="../school-logo.jpg" alt="Pitogo High School Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=&quot;fa fa-graduation-cap&quot;></i>';">
        </div>

        <div class="brand-text">
            <h2>Pitogo EduTrack</h2>
            <p><?= e($roleLabel) ?></p>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">Main</div>

        <a href="admin-dashboard.php" class="nav-item">
            <i class="fa fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <a href="user-management.php" class="nav-item active">
            <i class="fa fa-users-gear"></i>
            <span>User Management</span>
        </a>

        <a href="document-requests.php" class="nav-item">
            <i class="fa fa-file-lines"></i>
            <span>Document Requests</span>
        </a>

        <a class="nav-item" href="attendance.php">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <?php if ($isSuperAdmin): ?>
            <a class="nav-item" href="audit-logs.php">
                <i class="fa fa-clock-rotate-left"></i>
                <span>Audit Logs</span>
            </a>
        <?php endif; ?>

        <a href="settings.php" class="nav-item">
            <i class="fa fa-gear"></i>
            <span>Settings</span>
        </a>

        <div class="nav-label">Account</div>

        <a href="../logout.php" class="nav-item logout-link">
            <i class="fa fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main-wrapper">

<header class="top-header">
    <div class="header-title">
        <h1>User Management</h1>
        <p><?= e($roleLabel) ?> Access • Pitogo EduTrack</p>
    </div>

    <div class="user-profile">
        <div class="user-info">
            <h4><?= e($adminNameRaw) ?></h4>
            <p><?= e($roleLabel) ?></p>
        </div>

        <img src="<?= e($adminAvatar) ?>" class="avatar" alt="Profile">
    </div>
</header>

<div class="content-area">

    <?= $msg ?>

    <section class="report-hero">
        <div>
            <h1>User Management & Reports</h1>
            <p>Manage student, parent, staff, and admin accounts. Review pending users, archive records, and print clean reports.</p>
        </div>

        <div class="report-actions">
            <button type="button" class="btn-primary" onclick="window.print()">
                <i class="fa fa-print"></i>
                Print Report
            </button>

            <a href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'true']))) ?>" class="btn-outline">
                <i class="fa fa-file-csv"></i>
                Download CSV
            </a>
        </div>
    </section>

    <div class="stats-grid no-print">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1); color:var(--success);">
                <i class="fa fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalActive) ?></h3>
                <p>Active</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1); color:var(--warning);">
                <i class="fa fa-user-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalPending) ?></h3>
                <p>Pending</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(139,92,246,.1); color:#8B5CF6;">
                <i class="fa fa-box-archive"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalArchived) ?></h3>
                <p>Archived</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1); color:var(--danger);">
                <i class="fa fa-trash"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalDeleted) ?></h3>
                <p>Deleted</p>
            </div>
        </div>
    </div>

    <div class="tab-menu no-print">
        <a href="?tab=active" class="tab-btn <?= $activeTab === 'active' ? 'active' : '' ?>">
            <i class="fa fa-user-check"></i>
            Active
        </a>

        <a href="?tab=pending" class="tab-btn <?= $activeTab === 'pending' ? 'active' : '' ?>">
            <i class="fa fa-user-clock"></i>
            Pending
        </a>

        <a href="?tab=archive" class="tab-btn <?= $activeTab === 'archive' ? 'active' : '' ?>">
            <i class="fa fa-box-archive"></i>
            Archive
        </a>

        <?php if ($isSuperAdmin): ?>
            <a href="?tab=deleted" class="tab-btn <?= $activeTab === 'deleted' ? 'active' : '' ?>">
                <i class="fa fa-trash"></i>
                Deleted
            </a>
        <?php endif; ?>
    </div>

    <form method="GET" action="" class="toolbar-card no-print">
        <input type="hidden" name="tab" value="<?= e($activeTab) ?>">

        <div class="filter-group">
            <input
                type="text"
                name="search"
                class="select-control"
                style="width:240px;"
                placeholder="Search name, email, ID..."
                value="<?= e($search) ?>"
            >

            <select name="role" class="select-control">
                <option value="all" <?= $roleFilter === 'all' ? 'selected' : '' ?>>All Roles</option>
                <option value="student" <?= $roleFilter === 'student' ? 'selected' : '' ?>>Student</option>
                <option value="parent" <?= $roleFilter === 'parent' ? 'selected' : '' ?>>Parent</option>
                <option value="staff" <?= $roleFilter === 'staff' ? 'selected' : '' ?>>Staff</option>

                <?php if ($isSuperAdmin): ?>
                    <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                    <option value="superadmin" <?= $roleFilter === 'superadmin' ? 'selected' : '' ?>>Superadmin</option>
                <?php endif; ?>
            </select>

            <input type="date" name="date_from" class="select-control" value="<?= e($dateFrom) ?>">
            <input type="date" name="date_to" class="select-control" value="<?= e($dateTo) ?>">

            <button type="submit" class="btn-filter">
                <i class="fa fa-filter"></i>
                Filter
            </button>

            <?php if ($search !== '' || $roleFilter !== 'all' || $dateFrom !== '' || $dateTo !== ''): ?>
                <a href="?tab=<?= e($activeTab) ?>" style="color:var(--danger); text-decoration:none; font-weight:800;">
                    Clear
                </a>
            <?php endif; ?>
        </div>

        <div class="filter-group">
            <a href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'true']))) ?>" class="btn-export">
                <i class="fa fa-file-csv" style="color:var(--success);"></i>
                Export CSV
            </a>

            <button type="button" onclick="window.print()" class="btn-print">
                <i class="fa fa-print"></i>
                Print
            </button>
        </div>
    </form>

    <div class="report-paper">
        <div class="report-header">
            <div class="report-brand">
                <img src="../school-logo.jpg" alt="Pitogo High School Logo" onerror="this.style.display='none';">
                <div>
                    <h2>Pitogo EduTrack User Management Report</h2>
                    <p><?= e($reportTitle) ?></p>
                    <p>Prepared by <?= e($adminNameRaw) ?>, <?= e($roleLabel) ?></p>
                </div>
            </div>

            <div class="report-meta">
                <p><strong>Generated:</strong> <?= e($reportGenerated) ?></p>
                <p><strong>Report Type:</strong> <?= e(ucfirst($activeTab)) ?></p>
                <p><strong>Role Filter:</strong> <?= e($roleFilter === 'all' ? 'All Roles' : ucfirst($roleFilter)) ?></p>
                <p><strong>Date Range:</strong> <?= e($dateFrom !== '' ? $dateFrom : 'All') ?> to <?= e($dateTo !== '' ? $dateTo : 'All') ?></p>
            </div>
        </div>

        <div class="summary-grid">
            <div class="summary-card">
                <span>Filtered Records</span>
                <strong><?= number_format($filteredTotal) ?></strong>
            </div>

            <div class="summary-card">
                <span>Students</span>
                <strong><?= number_format($filteredStudents) ?></strong>
            </div>

            <div class="summary-card">
                <span>Parents</span>
                <strong><?= number_format($filteredParents) ?></strong>
            </div>

            <div class="summary-card">
                <span>Staff</span>
                <strong><?= number_format($filteredStaff) ?></strong>
            </div>

            <?php if ($isSuperAdmin): ?>
                <div class="summary-card">
                    <span>Admins</span>
                    <strong><?= number_format($filteredAdmins) ?></strong>
                </div>
            <?php endif; ?>
        </div>


        <form id="bulkActionForm" method="POST" action="user-management.php?<?= e(http_build_query($_GET)) ?>" class="bulk-actions-card no-print">
            <input type="hidden" name="current_tab" value="<?= e($activeTab) ?>">

            <div class="bulk-left">
                <div>
                    <div class="bulk-title">
                        <i class="fa fa-list-check" style="color:var(--primary-blue);margin-right:8px;"></i>
                        Bulk Actions
                    </div>
                    <div class="bulk-hint">
                        Select multiple accounts below. Admin can archive; only superadmin can delete.
                    </div>
                </div>

                <span class="bulk-hint">
                    Selected: <span id="selectedCount" class="selected-count">0</span>
                </span>
            </div>

            <div class="bulk-left">
                <select name="bulk_action" id="bulkActionSelect" class="bulk-select" required>
                    <option value="">Choose action</option>

                    <?php if ($activeTab === 'pending'): ?>
                        <option value="approve">Approve selected</option>
                        <option value="disapprove">Disapprove selected</option>
                    <?php endif; ?>

                    <?php if ($activeTab === 'active'): ?>
                        <option value="archive">Archive selected</option>
                    <?php endif; ?>

                    <?php if ($isSuperAdmin && in_array($activeTab, ['active', 'archive'], true)): ?>
                        <option value="delete">Delete selected</option>
                    <?php endif; ?>
                </select>

                <button type="submit" class="bulk-submit" onclick="return confirmBulkAction();">
                    <i class="fa fa-check-double"></i>
                    Apply
                </button>
            </div>
        </form>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th class="select-col no-print"><input type="checkbox" id="selectAllUsers" title="Select all"></th>
                        <th style="width:60px;">No.</th>
                        <th>User Information</th>
                        <th>Role</th>
                        <th>Status</th>
                        <th>ID Number / Linked Child</th>
                        <th>Date Added</th>
                        <th class="no-print">Actions</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if (empty($resultData)): ?>
                        <tr>
                            <td colspan="8" style="text-align:center; padding:45px; color:var(--text-muted);">
                                <i class="fa fa-folder-open" style="font-size:2rem; margin-bottom:10px;"></i>
                                <br>
                                No records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php $rowNumber = 1; foreach ($resultData as $row): ?>
                            <?php
                            $rowRole = strtolower($row['role'] ?? '');
                            $rowStatus = strtolower($row['status'] ?? $activeTab);
                            $fullNameRaw = getFullName($row) ?: 'Unnamed User';
                            $fullName = e($fullNameRaw);
                            $email = e($row['email'] ?? 'No email');
                            $profilePicture = e(profilePath($row, $fullNameRaw));
                            $idNumber = getIdNumber($row);
                            $emergencyPerson = getEmergencyPerson($row);
                            $emergencyNumber = getEmergencyNumber($row);
                            $createdAt = $row['created_at'] ?? '';
                            $displayDate = !empty($createdAt) ? date('M d, Y', strtotime($createdAt)) : 'N/A';

                            $childrenArray = [];
                            if ($rowRole === 'parent' && isset($parentLinks[(int)($row['id'] ?? 0)])) {
                                $childrenArray = $parentLinks[(int)$row['id']];
                            }

                            $displayId = e($idNumber ?: 'N/A');
                            if ($rowRole === 'parent') {
                                $displayId = $childrenArray ? implode('<br>', array_map('e', $childrenArray)) : 'No linked child found';
                            }

                            $canAct = $activeTab !== 'deleted';

                            if (!$isSuperAdmin && in_array($rowRole, ['admin', 'superadmin'], true)) {
                                $canAct = false;
                            }

                            if ($activeTab !== 'archive' && (int)($row['id'] ?? 0) === $currentUserId) {
                                $canAct = false;
                            }

                            $childrenJson = e(json_encode($childrenArray));
                            ?>
                            <tr>
                                <td class="select-col no-print">
                                    <?php if ($canAct): ?>
                                        <input
                                            type="checkbox"
                                            class="row-check"
                                            name="selected_users[]"
                                            value="<?= e($row['id'] ?? 0) ?>"
                                            form="bulkActionForm"
                                        >
                                    <?php endif; ?>
                                </td>

                                <td style="font-weight:800;color:var(--text-muted);">
                                    <?= $rowNumber++ ?>
                                </td>

                                <td>
                                    <div class="user-cell">
                                        <img src="<?= $profilePicture ?>" class="user-table-avatar" alt="User Profile Picture">
                                        <div>
                                            <strong style="color:var(--primary-navy); font-size:.95rem;">
                                                <?= $fullName ?>
                                            </strong>
                                            <br>
                                            <span style="font-size:.8rem; color:var(--text-muted);">
                                                <?= $email ?>
                                            </span>
                                        </div>
                                    </div>
                                </td>

                                <td>
                                    <span class="badge-role role-<?= e($rowRole) ?>">
                                        <?= e(ucfirst($rowRole ?: 'User')) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge-status status-<?= e($rowStatus) ?>">
                                        <?= e(ucfirst($rowStatus ?: 'N/A')) ?>
                                    </span>
                                </td>

                                <td style="font-weight:700; color:var(--text-dark);">
                                    <?= $displayId ?>
                                </td>

                                <td style="color:var(--text-muted);">
                                    <?= e($displayDate) ?>
                                </td>

                                <td class="no-print">
                                    <div class="action-group">
                                        <button
                                            type="button"
                                            class="btn-icon btn-view"
                                            title="View Details"
                                            data-name="<?= $fullName ?>"
                                            data-email="<?= $email ?>"
                                            data-role="<?= e(ucfirst($rowRole ?: 'User')) ?>"
                                            data-status="<?= e($rowStatus ?: 'N/A') ?>"
                                            data-idnumber="<?= e(strip_tags($displayId) ?: 'N/A') ?>"
                                            data-emergency-person="<?= e($emergencyPerson) ?>"
                                            data-emergency-number="<?= e($emergencyNumber) ?>"
                                            data-children='<?= $childrenJson ?>'
                                            data-date="<?= e($createdAt) ?>"
                                            data-photo="<?= $profilePicture ?>"
                                            onclick="openModalFromButton(this)"
                                        >
                                            <i class="fa fa-eye"></i>
                                        </button>

                                        <?php if ($canAct): ?>
                                            <?php if ($activeTab === 'pending'): ?>
                                                <form class="action-form" method="POST" action="../manage_user_action.php">
                                                    <input type="hidden" name="user_id" value="<?= e($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="current_tab" value="pending">
                                                    <button type="submit" name="action" value="approve" class="btn-icon btn-approve" title="Approve">
                                                        <i class="fa fa-check"></i>
                                                    </button>
                                                </form>

                                                <form class="action-form" method="POST" action="../manage_user_action.php">
                                                    <input type="hidden" name="user_id" value="<?= e($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="current_tab" value="pending">
                                                    <button type="submit" name="action" value="reject" class="btn-icon btn-reject" title="Reject" onclick="return confirm('Reject this pending account?');">
                                                        <i class="fa fa-times"></i>
                                                    </button>
                                                </form>

                                            <?php elseif ($activeTab === 'active'): ?>
                                                <form class="action-form" method="POST" action="../manage_user_action.php">
                                                    <input type="hidden" name="user_id" value="<?= e($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="current_tab" value="active">
                                                    <button type="submit" name="action" value="archive" class="btn-icon btn-archive" title="Archive" onclick="return confirm('Move this account to archive?');">
                                                        <i class="fa fa-box-archive"></i>
                                                    </button>
                                                </form>

                                                <?php if ($isSuperAdmin): ?>
                                                    <form class="action-form" method="POST" action="../manage_user_action.php">
                                                        <input type="hidden" name="user_id" value="<?= e($row['id'] ?? 0) ?>">
                                                        <input type="hidden" name="current_tab" value="active">
                                                        <button type="submit" name="action" value="delete" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Move this account to deleted records?');">
                                                            <i class="fa fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>

                                            <?php elseif ($activeTab === 'archive' && $isSuperAdmin): ?>
                                                <form class="action-form" method="POST" action="../manage_user_action.php">
                                                    <input type="hidden" name="user_id" value="<?= e($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="current_tab" value="archive">
                                                    <button type="submit" name="action" value="restore" class="btn-icon btn-restore" title="Restore">
                                                        <i class="fa fa-rotate-left"></i>
                                                    </button>
                                                </form>

                                                <form class="action-form" method="POST" action="../manage_user_action.php">
                                                    <input type="hidden" name="user_id" value="<?= e($row['id'] ?? 0) ?>">
                                                    <input type="hidden" name="current_tab" value="archive">
                                                    <button type="submit" name="action" value="delete" class="btn-icon btn-delete" title="Delete" onclick="return confirm('Move this archived account to deleted records?');">
                                                        <i class="fa fa-trash"></i>
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="restricted-text">Restricted</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>

</main>

<div class="modal-overlay no-print" id="infoModal">
    <div class="modal-card">
        <button class="modal-close" onclick="closeModal()">
            <i class="fa fa-times"></i>
        </button>

        <div class="modal-header">
            <img src="" id="modalProfilePicture" class="modal-user-avatar" alt="User Profile Picture">

            <div>
                <h2 id="modalName">User Name</h2>
                <p id="modalEmail">user@email.com</p>
            </div>
        </div>

        <div class="detail-row">
            <span class="detail-label">Role:</span>
            <span class="detail-value">
                <span id="modalRole" class="badge-role">Role</span>
            </span>
        </div>

        <div class="detail-row">
            <span class="detail-label">Status:</span>
            <span class="detail-value">
                <span id="modalStatus" class="badge-status">Status</span>
            </span>
        </div>

        <div class="detail-row">
            <span class="detail-label" id="idNumberLabel">ID Number:</span>
            <span class="detail-value" id="modalIdNumber">N/A</span>
        </div>

        <div class="detail-row">
            <span class="detail-label">Emergency Contact Person:</span>
            <span class="detail-value" id="modalEmergencyPerson">N/A</span>
        </div>

        <div class="detail-row">
            <span class="detail-label">Emergency Contact Number:</span>
            <span class="detail-value" id="modalEmergencyNumber">N/A</span>
        </div>

        <div class="detail-row">
            <span class="detail-label">Date Registered:</span>
            <span class="detail-value" id="modalDate">N/A</span>
        </div>

        <div id="childrenContainer" class="children-list" style="display:none;">
            <h4>
                <i class="fa fa-users"></i>
                Linked Children
            </h4>

            <ul id="modalChildrenList"></ul>
        </div>
    </div>
</div>

<script>

const selectAllUsers = document.getElementById('selectAllUsers');
const rowChecks = document.querySelectorAll('.row-check');
const selectedCount = document.getElementById('selectedCount');

function updateSelectedCount() {
    const checked = document.querySelectorAll('.row-check:checked').length;

    if (selectedCount) {
        selectedCount.textContent = checked;
    }

    if (selectAllUsers) {
        selectAllUsers.checked = checked > 0 && checked === rowChecks.length;
        selectAllUsers.indeterminate = checked > 0 && checked < rowChecks.length;
    }
}

if (selectAllUsers) {
    selectAllUsers.addEventListener('change', function() {
        rowChecks.forEach(check => {
            check.checked = this.checked;
        });

        updateSelectedCount();
    });
}

rowChecks.forEach(check => {
    check.addEventListener('change', updateSelectedCount);
});

function confirmBulkAction() {
    const actionSelect = document.getElementById('bulkActionSelect');
    const checked = document.querySelectorAll('.row-check:checked').length;

    if (!actionSelect || actionSelect.value === '') {
        alert('Please choose a bulk action first.');
        return false;
    }

    if (checked === 0) {
        alert('Please select at least one account first.');
        return false;
    }

    let actionText = actionSelect.options[actionSelect.selectedIndex].text;

    if (actionSelect.value === 'delete') {
        return confirm('Only superadmin can delete. The selected account(s) will be copied to deleted_users first, then marked as deleted. Continue?');
    }

    if (actionSelect.value === 'archive') {
        return confirm('The selected account(s) will be copied to archived_users first, then marked as archived. Continue?');
    }

    return confirm('Apply "' + actionText + '" to ' + checked + ' selected account(s)?');
}

updateSelectedCount();



const modal = document.getElementById('infoModal');

function openModalFromButton(btn) {
    const name = btn.dataset.name || 'Unnamed User';
    const email = btn.dataset.email || 'No email';
    const profilePicture = btn.dataset.photo || '';
    const role = btn.dataset.role || 'User';
    const status = btn.dataset.status || 'N/A';
    const idNumber = btn.dataset.idnumber || 'N/A';
    const emergencyPerson = btn.dataset.emergencyPerson || 'N/A';
    const emergencyNumber = btn.dataset.emergencyNumber || 'N/A';
    const childrenJson = btn.dataset.children || '[]';
    const dateRaw = btn.dataset.date || '';

    document.getElementById('modalName').textContent = name;
    document.getElementById('modalEmail').textContent = email;

    const modalProfilePicture = document.getElementById('modalProfilePicture');
    modalProfilePicture.src = profilePicture || 'https://ui-avatars.com/api/?name=' + encodeURIComponent(name || 'User') + '&background=0056B3&color=fff';

    const cleanRole = role.toLowerCase();
    const cleanStatus = status.toLowerCase();

    document.getElementById('modalRole').textContent = role;
    document.getElementById('modalRole').className = 'badge-role role-' + cleanRole;

    document.getElementById('modalStatus').textContent = status.charAt(0).toUpperCase() + status.slice(1);
    document.getElementById('modalStatus').className = 'badge-status status-' + cleanStatus;

    document.getElementById('idNumberLabel').textContent = cleanRole === 'parent' ? 'Linked Child / LRN:' : 'ID Number:';
    document.getElementById('modalIdNumber').textContent = idNumber || 'N/A';

    document.getElementById('modalEmergencyPerson').textContent = emergencyPerson || 'N/A';
    document.getElementById('modalEmergencyNumber').textContent = emergencyNumber || 'N/A';

    const childrenContainer = document.getElementById('childrenContainer');
    const childrenList = document.getElementById('modalChildrenList');

    childrenContainer.style.display = 'none';
    childrenList.innerHTML = '';

    if (cleanRole === 'parent') {
        childrenContainer.style.display = 'block';

        let childrenArray = [];

        try {
            childrenArray = JSON.parse(childrenJson);
        } catch (e) {
            childrenArray = [];
        }

        if (childrenArray.length > 0) {
            document.getElementById('modalIdNumber').textContent = 'See linked child below';

            childrenArray.forEach(child => {
                const li = document.createElement('li');
                li.innerHTML = '<i class="fa fa-user-graduate" style="color:var(--primary-blue); margin-right:8px;"></i>' + child;
                childrenList.appendChild(li);
            });
        } else {
            childrenList.innerHTML = '<li style="color:var(--text-muted);">No linked children found.</li>';
        }
    }

    if (dateRaw) {
        const dateObj = new Date(dateRaw);

        if (!isNaN(dateObj.getTime())) {
            document.getElementById('modalDate').textContent = dateObj.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric'
            });
        } else {
            document.getElementById('modalDate').textContent = 'N/A';
        }
    } else {
        document.getElementById('modalDate').textContent = 'N/A';
    }

    modal.classList.add('active');
}

function closeModal() {
    modal.classList.remove('active');
}

modal.addEventListener('click', function(e) {
    if (e.target === modal) {
        closeModal();
    }
});
</script>

</body>
</html>
