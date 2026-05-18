<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Settings
|--------------------------------------------------------------------------
| Save as:
| admin/settings.php
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

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

function fullName(array $row): string {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function generateCode(string $prefix): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $random = '';

    for ($i = 0; $i < 8; $i++) {
        $random .= $chars[random_int(0, strlen($chars) - 1)];
    }

    return $prefix . '-' . date('Y') . '-' . $random;
}

function getSetting(PDO $pdo, string $key, string $default = ''): string {
    $stmt = $pdo->prepare("
        SELECT setting_value
        FROM system_settings
        WHERE setting_key = ?
        LIMIT 1
    ");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();

    return $value !== false ? (string)$value : $default;
}

function setSetting(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare("
        INSERT INTO system_settings (setting_key, setting_value, updated_at)
        VALUES (?, ?, NOW())
        ON DUPLICATE KEY UPDATE
            setting_value = VALUES(setting_value),
            updated_at = NOW()
    ");
    $stmt->execute([$key, $value]);
}

function logAudit(PDO $pdo, int $userId, string $role, string $action, string $targetTable = 'system_settings', ?int $targetId = null): void {
    if (!tableExists($pdo, 'audit_logs')) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id,
                user_role,
                action,
                target_table,
                target_id,
                ip_address,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $userId,
            $role,
            $action,
            $targetTable,
            $targetId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        return;
    }
}

/*
|--------------------------------------------------------------------------
| Database safety
|--------------------------------------------------------------------------
*/

try {
    if (!tableExists($pdo, 'system_settings')) {
        $pdo->exec("
            CREATE TABLE system_settings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                setting_key VARCHAR(100) UNIQUE NOT NULL,
                setting_value TEXT NULL,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    if (!tableExists($pdo, 'registration_codes')) {
        $pdo->exec("
            CREATE TABLE registration_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) UNIQUE NOT NULL,
                code_type VARCHAR(30) NOT NULL,
                status VARCHAR(30) DEFAULT 'active',
                notes TEXT NULL,
                created_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                replaced_at DATETIME NULL,
                revoked_at DATETIME NULL
            )
        ");
    }

    if (!tableExists($pdo, 'audit_logs')) {
        $pdo->exec("
            CREATE TABLE audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NULL,
                user_role VARCHAR(50) NULL,
                action VARCHAR(255) NOT NULL,
                target_table VARCHAR(100) NULL,
                target_id INT NULL,
                ip_address VARCHAR(100) NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    if (getSetting($pdo, 'admin_code') === '') {
        setSetting($pdo, 'admin_code', 'EDUTRACK-ADMIN-2026');
    }

    if (getSetting($pdo, 'staff_code') === '') {
        setSetting($pdo, 'staff_code', 'EDUTRACK-STAFF-2026');
    }

} catch (Exception $e) {
    die("Settings setup failed: " . e($e->getMessage()));
}

/*
|--------------------------------------------------------------------------
| Current admin
|--------------------------------------------------------------------------
*/

$stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtAdmin->execute([$currentUserId]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];

$adminName = fullName($admin) ?: 'System Admin';

$adminAvatar = !empty($admin['profile_picture'])
    ? '../uploads/profiles/' . e($admin['profile_picture'])
    : 'https://ui-avatars.com/api/?name=' . urlencode($adminName) . '&background=0056B3&color=fff';

$msg = '';

/*
|--------------------------------------------------------------------------
| POST actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim($_POST['action'] ?? ''));

    if (!$isSuperAdmin) {
        $msg = "
            <div class='alert alert-danger'>
                <i class='fa fa-triangle-exclamation'></i>
                Only the superadmin can update system registration codes.
            </div>
        ";
    } else {
        try {
            if ($action === 'generate_code') {
                $codeType = strtolower(trim($_POST['code_type'] ?? ''));
                $notes = trim($_POST['notes'] ?? '');

                if (!in_array($codeType, ['admin', 'staff'], true)) {
                    throw new Exception('Invalid code type.');
                }

                $settingKey = $codeType === 'admin' ? 'admin_code' : 'staff_code';
                $prefix = $codeType === 'admin' ? 'EDUTRACK-ADMIN' : 'EDUTRACK-STAFF';
                $newCode = generateCode($prefix);

                $pdo->beginTransaction();

                $old = $pdo->prepare("
                    UPDATE registration_codes
                    SET status = 'replaced',
                        replaced_at = NOW()
                    WHERE code_type = ?
                    AND status = 'active'
                ");
                $old->execute([$codeType]);

                setSetting($pdo, $settingKey, $newCode);

                $save = $pdo->prepare("
                    INSERT INTO registration_codes (
                        code,
                        code_type,
                        status,
                        notes,
                        created_by,
                        created_at
                    ) VALUES (
                        ?, ?, 'active', ?, ?, NOW()
                    )
                ");
                $save->execute([$newCode, $codeType, $notes, $currentUserId]);

                logAudit($pdo, $currentUserId, $currentRole, "Generated new {$codeType} registration code", 'registration_codes', (int)$pdo->lastInsertId());

                $pdo->commit();

                $msg = "
                    <div class='alert alert-success'>
                        <i class='fa fa-check-circle'></i>
                        New " . e(ucfirst($codeType)) . " registration code generated successfully.
                    </div>
                ";
            }

            if ($action === 'manual_update') {
                $adminCode = strtoupper(trim($_POST['admin_code'] ?? ''));
                $staffCode = strtoupper(trim($_POST['staff_code'] ?? ''));

                if ($adminCode === '' || $staffCode === '') {
                    throw new Exception('Admin code and staff code are required.');
                }

                setSetting($pdo, 'admin_code', $adminCode);
                setSetting($pdo, 'staff_code', $staffCode);

                logAudit($pdo, $currentUserId, $currentRole, 'Manually updated registration codes', 'system_settings', null);

                $msg = "
                    <div class='alert alert-success'>
                        <i class='fa fa-check-circle'></i>
                        Registration codes updated successfully.
                    </div>
                ";
            }

            if ($action === 'revoke_code') {
                $codeId = (int)($_POST['code_id'] ?? 0);

                if ($codeId <= 0) {
                    throw new Exception('Invalid code record.');
                }

                $stmt = $pdo->prepare("
                    UPDATE registration_codes
                    SET status = 'revoked',
                        revoked_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$codeId]);

                logAudit($pdo, $currentUserId, $currentRole, 'Revoked a registration code record', 'registration_codes', $codeId);

                $msg = "
                    <div class='alert alert-warning'>
                        <i class='fa fa-ban'></i>
                        Code record revoked successfully.
                    </div>
                ";
            }

        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $msg = "
                <div class='alert alert-danger'>
                    <i class='fa fa-triangle-exclamation'></i>
                    " . e($e->getMessage()) . "
                </div>
            ";
        }
    }
}

/*
|--------------------------------------------------------------------------
| Fetch data
|--------------------------------------------------------------------------
*/

$currentAdminCode = getSetting($pdo, 'admin_code', 'EDUTRACK-ADMIN-2026');
$currentStaffCode = getSetting($pdo, 'staff_code', 'EDUTRACK-STAFF-2026');

$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$typeFilter = strtolower(trim($_GET['type'] ?? 'all'));
$search = trim($_GET['search'] ?? '');

$params = [];

$query = "
    SELECT
        rc.*,
        u.first_name,
        u.last_name,
        u.email
    FROM registration_codes rc
    LEFT JOIN users u ON u.id = rc.created_by
    WHERE 1=1
";

if (in_array($statusFilter, ['active', 'replaced', 'revoked'], true)) {
    $query .= " AND rc.status = ?";
    $params[] = $statusFilter;
}

if (in_array($typeFilter, ['admin', 'staff'], true)) {
    $query .= " AND rc.code_type = ?";
    $params[] = $typeFilter;
}

if ($search !== '') {
    $query .= "
        AND (
            rc.code LIKE ?
            OR rc.notes LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
        )
    ";

    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s, $s);
}

$query .= " ORDER BY rc.created_at DESC";

$codes = safeFetchAll($pdo, $query, $params);

$totalCodes = safeCount($pdo, "SELECT COUNT(*) FROM registration_codes");
$activeCodes = safeCount($pdo, "SELECT COUNT(*) FROM registration_codes WHERE status='active'");
$adminCodes = safeCount($pdo, "SELECT COUNT(*) FROM registration_codes WHERE code_type='admin'");
$staffCodes = safeCount($pdo, "SELECT COUNT(*) FROM registration_codes WHERE code_type='staff'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Settings | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --primary-navy:#0B1C3D;
    --primary-blue:<?= e($themeColor) ?>;
    --bg-main:#F4F7F9;
    --surface-white:#FFFFFF;
    --text-dark:#1E293B;
    --text-muted:#64748B;
    --border-color:#E2E8F0;
    --success:#10B981;
    --warning:#F59E0B;
    --danger:#EF4444;
    --info:#3B82F6;
    --purple:#8B5CF6;
    --shadow-sm:0 4px 8px rgba(15,23,42,0.04);
    --shadow-md:0 14px 35px rgba(15,23,42,0.08);
    --transition:all .3s ease;
}

* {
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body {
    font-family:'Inter',sans-serif;
    background:var(--bg-main);
    color:var(--text-dark);
    display:flex;
    min-height:100vh;
    overflow-x:hidden;
}

h1,h2,h3,h4 {
    font-family:'Montserrat',sans-serif;
}

.sidebar {
    width:82px;
    height:100vh;
    background:var(--surface-white);
    border-right:1px solid var(--border-color);
    position:fixed;
    left:0;
    top:0;
    z-index:100;
    transition:width .35s ease;
    overflow-x:hidden;
    display:flex;
    flex-direction:column;
}

.sidebar:hover {
    width:285px;
    box-shadow:10px 0 30px rgba(15,23,42,0.08);
}

.sidebar-brand {
    padding:24px 17px;
    display:flex;
    align-items:center;
    gap:15px;
    border-bottom:1px solid var(--border-color);
}

.logo-box {
    min-width:48px;
    height:48px;
    border-radius:14px;
    background:#fff;
    border:1px solid var(--border-color);
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
}

.logo-box img {
    width:100%;
    height:100%;
    object-fit:contain;
}

.brand-text,
.nav-label,
.nav-item span {
    opacity:0;
    visibility:hidden;
    transition:opacity .2s ease;
}

.sidebar:hover .brand-text,
.sidebar:hover .nav-label,
.sidebar:hover .nav-item span {
    opacity:1;
    visibility:visible;
}

.brand-text h2 {
    font-size:1.05rem;
    color:var(--primary-navy);
    font-weight:800;
}

.brand-text p {
    font-size:.72rem;
    color:var(--primary-blue);
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.8px;
}

.nav-menu {
    padding:22px 14px;
    display:flex;
    flex-direction:column;
    gap:5px;
    flex:1;
}

.nav-label {
    margin:14px 0 8px 14px;
    font-size:.72rem;
    font-weight:800;
    color:var(--text-muted);
    text-transform:uppercase;
    letter-spacing:1px;
}

.nav-item {
    padding:14px 10px;
    border-radius:13px;
    display:flex;
    align-items:center;
    gap:20px;
    text-decoration:none;
    color:var(--text-muted);
    font-size:.94rem;
    font-weight:750;
    transition:var(--transition);
}

.nav-item i {
    min-width:30px;
    text-align:center;
    font-size:1.17rem;
}

.nav-item:hover {
    background:var(--bg-main);
    color:var(--primary-blue);
    transform:translateX(5px);
}

.nav-item.active {
    background:rgba(0,86,179,.08);
    color:var(--primary-blue);
}

.logout-link {
    margin-top:auto;
    color:var(--danger);
}

.main-wrapper {
    margin-left:82px;
    flex:1;
    min-width:0;
}

.top-header {
    height:88px;
    background:var(--surface-white);
    border-bottom:1px solid var(--border-color);
    padding:0 38px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:50;
}

.header-title h1 {
    font-size:1.35rem;
    color:var(--primary-navy);
}

.header-title p {
    font-size:.85rem;
    color:var(--text-muted);
    margin-top:4px;
}

.user-profile {
    display:flex;
    align-items:center;
    gap:14px;
}

.user-info {
    text-align:right;
}

.user-info h4 {
    font-size:.9rem;
    color:var(--primary-navy);
}

.user-info p {
    font-size:.75rem;
    color:var(--text-muted);
}

.avatar {
    width:46px;
    height:46px;
    object-fit:cover;
    border-radius:50%;
    border:2px solid var(--primary-blue);
    padding:2px;
}

.content-area {
    padding:38px;
    max-width:1500px;
    margin:0 auto;
}

.alert {
    padding:15px 18px;
    border-radius:14px;
    margin-bottom:20px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:10px;
}

.alert-success {
    background:#DCFCE7;
    color:#166534;
    border:1px solid #A7F3D0;
}

.alert-danger {
    background:#FEE2E2;
    color:#991B1B;
    border:1px solid #FECACA;
}

.alert-warning {
    background:#FEF3C7;
    color:#92400E;
    border:1px solid #FDE68A;
}

.hero-card {
    background:
        linear-gradient(135deg, rgba(11,28,61,.96), rgba(0,86,179,.78)),
        url('../school-bg.jpg');
    background-size:cover;
    background-position:center;
    color:white;
    border-radius:24px;
    padding:34px;
    margin-bottom:28px;
    display:flex;
    justify-content:space-between;
    gap:25px;
    align-items:center;
    flex-wrap:wrap;
}

.hero-card h1 {
    font-size:2rem;
    font-weight:800;
    margin-bottom:8px;
}

.hero-card p {
    color:rgba(255,255,255,.86);
    line-height:1.6;
    max-width:850px;
}

.role-pill {
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.25);
    padding:12px 18px;
    border-radius:999px;
    font-weight:800;
    display:inline-flex;
    gap:8px;
    align-items:center;
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4, minmax(170px, 1fr));
    gap:18px;
    margin-bottom:24px;
}

.stat-card {
    background:var(--surface-white);
    padding:22px;
    border-radius:18px;
    border:1px solid var(--border-color);
    display:flex;
    align-items:center;
    gap:16px;
    box-shadow:var(--shadow-sm);
}

.stat-icon {
    width:56px;
    height:56px;
    border-radius:15px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.3rem;
}

.stat-info h3 {
    font-size:1.65rem;
    font-weight:800;
    color:var(--primary-navy);
}

.stat-info p {
    font-size:.78rem;
    color:var(--text-muted);
    font-weight:800;
    text-transform:uppercase;
}

.settings-grid {
    display:grid;
    grid-template-columns:1fr 1fr;
    gap:22px;
    margin-bottom:24px;
}

.panel {
    background:var(--surface-white);
    border:1px solid var(--border-color);
    border-radius:20px;
    padding:24px;
    box-shadow:var(--shadow-sm);
}

.panel h3 {
    color:var(--primary-navy);
    font-size:1.1rem;
    margin-bottom:7px;
}

.panel-desc {
    color:var(--text-muted);
    line-height:1.6;
    font-size:.9rem;
    margin-bottom:20px;
}

.current-code-box {
    background:#F8FAFC;
    border:1px dashed var(--primary-blue);
    padding:16px;
    border-radius:14px;
    margin-bottom:18px;
}

.current-code-box label {
    display:block;
    font-size:.72rem;
    font-weight:900;
    color:var(--text-muted);
    text-transform:uppercase;
    margin-bottom:7px;
}

.code-line {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}

.code-text {
    color:var(--primary-navy);
    font-size:1rem;
    font-weight:900;
    letter-spacing:1px;
    word-break:break-all;
}

.copy-btn {
    min-width:38px;
    height:38px;
    border:none;
    border-radius:10px;
    background:rgba(0,86,179,.1);
    color:var(--primary-blue);
    cursor:pointer;
}

.form-group {
    margin-bottom:16px;
}

.form-group label {
    display:block;
    font-size:.82rem;
    font-weight:900;
    color:var(--primary-navy);
    margin-bottom:8px;
}

.form-control {
    width:100%;
    padding:12px 14px;
    border:1px solid var(--border-color);
    border-radius:10px;
    background:#F8FAFC;
    outline:none;
    font-family:'Inter',sans-serif;
}

.form-control:focus {
    border-color:var(--primary-blue);
    background:white;
}

.btn-main {
    width:100%;
    border:none;
    border-radius:12px;
    background:var(--primary-blue);
    color:white;
    padding:13px 16px;
    font-weight:900;
    cursor:pointer;
    font-family:'Montserrat',sans-serif;
    display:flex;
    justify-content:center;
    align-items:center;
    gap:8px;
}

.btn-main.green {
    background:var(--success);
}

.btn-main:disabled {
    opacity:.55;
    cursor:not-allowed;
}

.toolbar-card {
    background:var(--surface-white);
    padding:20px 24px;
    border-radius:18px;
    border:1px solid var(--border-color);
    box-shadow:var(--shadow-sm);
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:22px;
    flex-wrap:wrap;
    gap:15px;
}

.filter-group {
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}

.filter-control {
    padding:11px 14px;
    border-radius:10px;
    border:1px solid var(--border-color);
    background:var(--bg-main);
    font-family:'Inter',sans-serif;
    font-size:.9rem;
    font-weight:600;
    color:var(--primary-navy);
    outline:none;
}

.btn-filter {
    padding:11px 16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:900;
    border:none;
    background:var(--primary-blue);
    color:white;
    display:inline-flex;
    gap:7px;
    align-items:center;
}

.table-container {
    background:var(--surface-white);
    border-radius:18px;
    border:1px solid var(--border-color);
    overflow-x:auto;
    box-shadow:var(--shadow-sm);
}

.data-table {
    width:100%;
    border-collapse:collapse;
    min-width:1050px;
}

.data-table th {
    background:#F8FAFC;
    padding:16px 18px;
    font-size:.78rem;
    font-weight:900;
    color:var(--text-muted);
    text-transform:uppercase;
    border-bottom:2px solid var(--border-color);
    text-align:left;
}

.data-table td {
    padding:15px 18px;
    border-bottom:1px solid var(--border-color);
    vertical-align:middle;
}

.data-table tbody tr:hover td {
    background:rgba(0,86,179,.025);
}

.badge-status,
.badge-type {
    padding:7px 12px;
    border-radius:20px;
    font-size:.75rem;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    gap:6px;
    text-transform:capitalize;
}

.status-active {
    background:rgba(16,185,129,.1);
    color:var(--success);
}

.status-replaced {
    background:rgba(59,130,246,.1);
    color:var(--info);
}

.status-revoked {
    background:rgba(239,68,68,.1);
    color:var(--danger);
}

.type-admin {
    background:rgba(124,58,237,.1);
    color:#7C3AED;
}

.type-staff {
    background:rgba(0,86,179,.1);
    color:#0056B3;
}

.action-group {
    display:flex;
    gap:8px;
    align-items:center;
}

.btn-small {
    border:none;
    border-radius:9px;
    padding:8px 12px;
    cursor:pointer;
    font-weight:900;
    font-size:.78rem;
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.btn-revoke {
    background:#FEF3C7;
    color:#B45309;
}

.empty-state {
    padding:45px;
    text-align:center;
    color:var(--text-muted);
}

.notice {
    background:#EFF6FF;
    border:1px solid #BFDBFE;
    color:#1E40AF;
    border-radius:14px;
    padding:16px;
    line-height:1.6;
    font-size:.9rem;
    margin-bottom:24px;
}

.notice strong {
    color:#1E3A8A;
}

@media(max-width:1100px) {
    .settings-grid {
        grid-template-columns:1fr;
    }

    .stats-grid {
        grid-template-columns:repeat(2, 1fr);
    }
}

@media(max-width:760px) {
    .sidebar {
        display:none;
    }

    .main-wrapper {
        margin-left:0;
    }

    .top-header {
        padding:0 20px;
    }

    .content-area {
        padding:24px 18px;
    }

    .stats-grid {
        grid-template-columns:1fr;
    }

    .user-info {
        display:none;
    }

    .toolbar-card {
        align-items:flex-start;
    }

    .filter-control,
    .btn-filter {
        width:100%;
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

        <a href="user-management.php" class="nav-item">
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

        <a href="settings.php" class="nav-item active">
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
        <h1>System Settings</h1>
        <p><?= e($roleLabel) ?> Access • Pitogo EduTrack</p>
    </div>

    <div class="user-profile">
        <div class="user-info">
            <h4><?= e($adminName) ?></h4>
            <p><?= e($roleLabel) ?></p>
        </div>

        <img src="<?= e($adminAvatar) ?>" class="avatar" alt="Profile">
    </div>
</header>

<div class="content-area">

    <?= $msg ?>

<?php if ($isSuperAdmin): ?>
    <section class="hero-card">
        <div>
            <h1>Registration Code Settings</h1>
            <p>
                Manage secure registration codes for administrators and staff members.
                Admin codes allow admin registration, while staff codes allow QR scanner staff registration.
            </p>
        </div>

        <div class="role-pill">
            <i class="fa fa-shield-halved"></i>
            <?= e($roleLabel) ?>
        </div>
    </section>

    <div class="notice">
        <strong>Important:</strong>
        The current <strong>Admin Code</strong> and <strong>Staff Code</strong> are used by the registration page.
        Only the superadmin can generate or update these codes.
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(124,58,237,.1); color:#7C3AED;">
                <i class="fa fa-key"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalCodes) ?></h3>
                <p>Total Codes</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1); color:var(--success);">
                <i class="fa fa-circle-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($activeCodes) ?></h3>
                <p>Active Codes</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1); color:var(--info);">
                <i class="fa fa-user-shield"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($adminCodes) ?></h3>
                <p>Admin Codes</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1); color:var(--warning);">
                <i class="fa fa-qrcode"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($staffCodes) ?></h3>
                <p>Staff Codes</p>
            </div>
        </div>
    </div>

    <div class="settings-grid">
        <div class="panel">
            <h3>Current Active Codes</h3>
            <p class="panel-desc">
                These are the codes currently accepted by the registration system.
            </p>

            <div class="current-code-box">
                <label>Current Admin Code</label>
                <div class="code-line">
                    <div class="code-text" id="adminCodeText"><?= e($currentAdminCode) ?></div>
                    <button type="button" class="copy-btn" onclick="copyCode('adminCodeText')" title="Copy">
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
            </div>

            <div class="current-code-box">
                <label>Current Staff Code</label>
                <div class="code-line">
                    <div class="code-text" id="staffCodeText"><?= e($currentStaffCode) ?></div>
                    <button type="button" class="copy-btn" onclick="copyCode('staffCodeText')" title="Copy">
                        <i class="fa fa-copy"></i>
                    </button>
                </div>
            </div>

            <form method="POST">
                <input type="hidden" name="action" value="manual_update">

                <div class="form-group">
                    <label>Update Admin Code</label>
                    <input type="text" name="admin_code" class="form-control" value="<?= e($currentAdminCode) ?>" <?= !$isSuperAdmin ? 'readonly' : '' ?>>
                </div>

                <div class="form-group">
                    <label>Update Staff Code</label>
                    <input type="text" name="staff_code" class="form-control" value="<?= e($currentStaffCode) ?>" <?= !$isSuperAdmin ? 'readonly' : '' ?>>
                </div>

                <button type="submit" class="btn-main green" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                    <i class="fa fa-save"></i>
                    Save Current Codes
                </button>
            </form>
        </div>

        <div class="panel">
            <h3>Generate New Code</h3>
            <p class="panel-desc">
                Generating a new code will replace the current active code of the selected type.
            </p>

            <form method="POST">
                <input type="hidden" name="action" value="generate_code">

                <div class="form-group">
                    <label>Code Type</label>
                    <select name="code_type" class="form-control" required <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                        <option value="admin">Admin Registration Code</option>
                        <option value="staff">Staff Registration Code</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="5" placeholder="Example: Generated for new registrar admin or entrance scanner staff." <?= !$isSuperAdmin ? 'readonly' : '' ?>></textarea>
                </div>

                <button type="submit" class="btn-main" <?= !$isSuperAdmin ? 'disabled' : '' ?>>
                    <i class="fa fa-key"></i>
                    Generate New Code
                </button>
            </form>
        </div>
    </div>
<?php endif; ?>
<?php if (!$isSuperAdmin): ?>

<section class="hero-card">
    <div>
        <h1>Account Settings</h1>
        <p>
            Regular administrators can only manage their own account security settings.
            Registration code management is restricted to the Super Administrator.
        </p>
    </div>

    <div class="role-pill">
        <i class="fa fa-lock"></i>
        Limited Access
    </div>
</section>

<div class="notice" style="background:#F8FAFC;border:1px dashed #CBD5E1;color:#475569;">
    <strong>Restricted:</strong>
    Registration code generation and system access code management are only available for the Super Administrator account.
</div>

<div class="settings-card" style="opacity:.82;">
    <div style="padding:30px;text-align:center;">
        <i class="fa fa-lock" style="font-size:3rem;color:#94A3B8;margin-bottom:15px;"></i>

        <h2 style="color:#475569;margin-bottom:10px;">
            Access Code Management Locked
        </h2>

        <p style="color:#64748B;line-height:1.7;max-width:700px;margin:auto;">
            Your administrator account can still access profile settings and password security,
            but only the Super Administrator can create, replace, revoke, and manage registration access codes.
        </p>
    </div>
</div>

<?php endif; ?>
<?php if ($isSuperAdmin): ?>
    <form method="GET" class="toolbar-card">
        <div class="filter-group">
            <input
                type="text"
                name="search"
                class="filter-control"
                style="width:260px;"
                placeholder="Search code, notes, creator..."
                value="<?= e($search) ?>"
            >

            <select name="type" class="filter-control">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Types</option>
                <option value="admin" <?= $typeFilter === 'admin' ? 'selected' : '' ?>>Admin</option>
                <option value="staff" <?= $typeFilter === 'staff' ? 'selected' : '' ?>>Staff</option>
            </select>

            <select name="status" class="filter-control">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Active</option>
                <option value="replaced" <?= $statusFilter === 'replaced' ? 'selected' : '' ?>>Replaced</option>
                <option value="revoked" <?= $statusFilter === 'revoked' ? 'selected' : '' ?>>Revoked</option>
            </select>

            <button type="submit" class="btn-filter">
                <i class="fa fa-filter"></i>
                Filter
            </button>

            <?php if ($search !== '' || $typeFilter !== 'all' || $statusFilter !== 'all'): ?>
                <a href="settings.php" style="color:var(--danger); text-decoration:none; font-weight:900;">
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Type</th>
                    <th>Status</th>
                    <th>Notes</th>
                    <th>Created By</th>
                    <th>Created</th>
                    <th>Replaced / Revoked</th>
                    <th>Action</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$codes): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fa fa-folder-open" style="font-size:2rem; margin-bottom:10px;"></i>
                                <br>
                                No registration code history yet.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($codes as $code): ?>
                        <?php
                            $creator = trim(($code['first_name'] ?? '') . ' ' . ($code['last_name'] ?? ''));
                            if ($creator === '') {
                                $creator = 'System';
                            }

                            $status = strtolower($code['status'] ?? 'active');
                            $type = strtolower($code['code_type'] ?? 'admin');
                            $endedAt = $code['revoked_at'] ?: ($code['replaced_at'] ?? null);
                        ?>
                        <tr>
                            <td>
                                <strong id="code-<?= e($code['id']) ?>"><?= e($code['code']) ?></strong>
                                <button type="button" class="copy-btn" onclick="copyCode('code-<?= e($code['id']) ?>')" title="Copy">
                                    <i class="fa fa-copy"></i>
                                </button>
                            </td>

                            <td>
                                <span class="badge-type type-<?= e($type) ?>">
                                    <i class="fa <?= $type === 'admin' ? 'fa-user-shield' : 'fa-qrcode' ?>"></i>
                                    <?= e($type) ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge-status status-<?= e($status) ?>">
                                    <i class="fa <?= $status === 'active' ? 'fa-circle-check' : ($status === 'revoked' ? 'fa-ban' : 'fa-repeat') ?>"></i>
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <td><?= e($code['notes'] ?: 'No notes') ?></td>

                            <td>
                                <strong><?= e($creator) ?></strong>
                                <br>
                                <span style="font-size:.78rem;color:var(--text-muted);">
                                    <?= e($code['email'] ?? '') ?>
                                </span>
                            </td>

                            <td><?= !empty($code['created_at']) ? e(date('M d, Y h:i A', strtotime($code['created_at']))) : 'N/A' ?></td>

                            <td><?= !empty($endedAt) ? e(date('M d, Y h:i A', strtotime($endedAt))) : 'Still active' ?></td>

                            <td>
                                <div class="action-group">
                                    <?php if ($isSuperAdmin && $status === 'active'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="action" value="revoke_code">
                                            <input type="hidden" name="code_id" value="<?= e($code['id']) ?>">
                                            <button type="submit" class="btn-small btn-revoke" onclick="return confirm('Revoke this code record? This will only update the history record. Generate a new code if this is the current active code.');">
                                                <i class="fa fa-ban"></i>
                                                Revoke
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span style="font-size:.78rem;color:var(--text-muted);font-weight:900;">
                                            No action
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

</div>

</main>

<script>
function copyCode(elementId) {
    const el = document.getElementById(elementId);

    if (!el) {
        return;
    }

    const text = el.innerText.trim();

    navigator.clipboard.writeText(text).then(() => {
        alert('Code copied: ' + text);
    }).catch(() => {
        alert('Copy failed. Please copy manually.');
    });
}
</script>

</body>
</html>
