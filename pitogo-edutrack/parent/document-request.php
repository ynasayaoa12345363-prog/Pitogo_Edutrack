<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Parent Document Request
|--------------------------------------------------------------------------
| Save as:
| parent/document-request.php
|
| Features:
| - Restored sidebar and unified Pitogo EduTrack design
| - Soft Copy or Printed Copy selection
| - Report Card = Printed Copy only
| - Soft copy reminder: no dry seal / no CTC
| - OTP protected soft copy access
| - Parent-compatible linked child access
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if ($currentRole !== 'parent') {
    header("Location: ../index.html?error=" . urlencode("Unauthorized parent access only."));
    exit();
}

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

function safeFetchAll(PDO $pdo, string $sql, array $params = []): array {
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
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

function fullName(array $row): string {
    return trim(
        ($row['first_name'] ?? '') . ' ' .
        ($row['middle_name'] ?? '') . ' ' .
        ($row['last_name'] ?? '')
    );
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

function ensureAuditLogs(PDO $pdo): void {
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
}

function logAudit(PDO $pdo, int $userId, string $role, string $action, string $targetTable = 'document_requests', ?int $targetId = null): void {
    ensureAuditLogs($pdo);

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
| Ensure tables and columns
|--------------------------------------------------------------------------
*/

if (!tableExists($pdo, 'document_requests')) {
    $pdo->exec("
        CREATE TABLE document_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NULL,
            requested_by INT NULL,
            tracking_id VARCHAR(80) NULL,
            document_type VARCHAR(150) NOT NULL,
            request_copy_type VARCHAR(50) DEFAULT 'printed',
            purpose TEXT NULL,
            status VARCHAR(30) DEFAULT 'pending',
            release_method VARCHAR(50) NULL,
            file_path VARCHAR(255) NULL,
            access_otp VARCHAR(10) NULL,
            otp_verified TINYINT(1) DEFAULT 0,
            otp_used TINYINT(1) DEFAULT 0,
            pickup_date DATE NULL,
            instructions TEXT NULL,
            remarks TEXT NULL,
            processed_by INT NULL,
            processed_at DATETIME NULL,
            archived_at DATETIME NULL,
            file_expires_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
}

$neededColumns = [
    'student_id' => 'INT NULL',
    'requested_by' => 'INT NULL',
    'tracking_id' => 'VARCHAR(80) NULL',
    'document_type' => 'VARCHAR(150) NOT NULL',
    'request_copy_type' => "VARCHAR(50) DEFAULT 'printed'",
    'purpose' => 'TEXT NULL',
    'status' => "VARCHAR(30) DEFAULT 'pending'",
    'release_method' => 'VARCHAR(50) NULL',
    'file_path' => 'VARCHAR(255) NULL',
    'access_otp' => 'VARCHAR(10) NULL',
    'otp_verified' => 'TINYINT(1) DEFAULT 0',
    'otp_used' => 'TINYINT(1) DEFAULT 0',
    'pickup_date' => 'DATE NULL',
    'instructions' => 'TEXT NULL',
    'remarks' => 'TEXT NULL',
    'processed_by' => 'INT NULL',
    'processed_at' => 'DATETIME NULL',
    'archived_at' => 'DATETIME NULL',
    'file_expires_at' => 'DATETIME NULL',
    'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
    'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
];

foreach ($neededColumns as $column => $definition) {
    if (!columnExists($pdo, 'document_requests', $column)) {
        $pdo->exec("ALTER TABLE document_requests ADD COLUMN $column $definition");
    }
}

if (!tableExists($pdo, 'parent_student_links')) {
    $pdo->exec("
        CREATE TABLE parent_student_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            student_id INT NOT NULL,
            status VARCHAR(30) DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_parent_student (parent_id, student_id)
        )
    ");
}

/*
|--------------------------------------------------------------------------
| Current user
|--------------------------------------------------------------------------
*/

$stmtUser = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    LIMIT 1
");
$stmtUser->execute([$currentUserId]);
$user = $stmtUser->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../index.html");
    exit();
}

$userName = fullName($user) ?: 'User';
$userAvatar = profilePath($user, $userName);
$roleLabel = 'Parent Portal';

/*
|--------------------------------------------------------------------------
| Determine selected student target
|--------------------------------------------------------------------------
*/

$studentId = $currentUserId;
$linkedStudents = [];

if ($currentRole === 'parent') {
    $linkedStudents = safeFetchAll($pdo, "
        SELECT 
            s.id,
            s.first_name,
            s.middle_name,
            s.last_name,
            s.email,
            s.id_num
        FROM parent_student_links psl
        JOIN users s ON s.id = psl.student_id
        WHERE psl.parent_id = ?
        AND psl.status = 'active'
        AND s.role = 'student'
        ORDER BY s.last_name ASC
    ", [$currentUserId]);

    if (!$linkedStudents && columnExists($pdo, 'users', 'linked_student_id')) {
        $fallback = safeFetchAll($pdo, "
            SELECT id, first_name, middle_name, last_name, email, id_num
            FROM users
            WHERE id = (
                SELECT linked_student_id
                FROM users
                WHERE id = ?
                LIMIT 1
            )
            AND role = 'student'
            LIMIT 1
        ", [$currentUserId]);

        $linkedStudents = $fallback;
    }

    $requestedStudentId = (int)($_GET['student_id'] ?? ($_POST['student_id'] ?? 0));

    if ($requestedStudentId > 0) {
        foreach ($linkedStudents as $linked) {
            if ((int)$linked['id'] === $requestedStudentId) {
                $studentId = $requestedStudentId;
                break;
            }
        }
    } elseif ($linkedStudents) {
        $studentId = (int)$linkedStudents[0]['id'];
    }
}

$stmtStudent = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    AND role = 'student'
    LIMIT 1
");
$stmtStudent->execute([$studentId]);
$student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

$studentName = $student ? fullName($student) : 'No linked student';
$studentLrn = $student['id_num'] ?? $student['lrn'] ?? 'N/A';

/*
|--------------------------------------------------------------------------
| Submit request
|--------------------------------------------------------------------------
*/

$message = '';
$messageType = 'danger';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $documentType = trim($_POST['document_type'] ?? '');
    $copyType = trim($_POST['copy_type'] ?? '');
    $purpose = trim($_POST['purpose'] ?? '');
    $postStudentId = (int)($_POST['student_id'] ?? $studentId);

    if ($currentRole === 'parent') {
        $allowed = false;

        foreach ($linkedStudents as $linked) {
            if ((int)$linked['id'] === $postStudentId) {
                $allowed = true;
                break;
            }
        }

        if (!$allowed) {
            $message = 'Invalid linked student selected.';
        } else {
            $studentId = $postStudentId;
        }
    }

    if ($message === '') {
        if ($documentType === '') {
            $message = 'Please select a document type.';
        } else {
            if (strtolower($documentType) === 'report card') {
                $copyType = 'printed';
            }

            if (!in_array($copyType, ['softcopy', 'printed'], true)) {
                $copyType = 'printed';
            }

            $trackingId = 'PET-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
            $otp = (string)random_int(100000, 999999);

            $stmtInsert = $pdo->prepare("
                INSERT INTO document_requests (
                    student_id,
                    requested_by,
                    tracking_id,
                    document_type,
                    request_copy_type,
                    purpose,
                    access_otp,
                    status,
                    created_at
                ) VALUES (
                    ?, ?, ?, ?, ?, ?, ?, 'pending', NOW()
                )
            ");

            $stmtInsert->execute([
                $studentId,
                $currentUserId,
                $trackingId,
                $documentType,
                $copyType,
                $purpose,
                $otp
            ]);

            $newId = (int)$pdo->lastInsertId();

            logAudit($pdo, $currentUserId, $currentRole, 'Submitted document request', 'document_requests', $newId);

            header("Location: document-request.php?success=1" . ($currentRole === 'parent' ? '&student_id=' . $studentId : ''));
            exit();
        }
    }
}

/*
|--------------------------------------------------------------------------
| OTP Verification
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $requestId = (int)($_POST['request_id'] ?? 0);
    $enteredOtp = trim($_POST['entered_otp'] ?? '');

    $stmtCheck = $pdo->prepare("
        SELECT *
        FROM document_requests
        WHERE id = ?
        AND access_otp = ?
        AND student_id = ?
        AND status = 'released'
        AND file_path IS NOT NULL
        AND file_path != ''
        LIMIT 1
    ");
    $stmtCheck->execute([$requestId, $enteredOtp, $studentId]);
    $requestData = $stmtCheck->fetch(PDO::FETCH_ASSOC);

    if ($requestData) {

        if (tableExists($pdo, 'document_access_logs')) {
            try {
                $stmtLog = $pdo->prepare("
                    INSERT INTO document_access_logs (
                        document_request_id,
                        user_id,
                        access_type,
                        created_at
                    ) VALUES (
                        ?, ?, 'otp_verified', NOW()
                    )
                ");
                $stmtLog->execute([$requestId, $currentUserId]);
            } catch (Exception $e) {}
        }

        logAudit(
            $pdo,
            $currentUserId,
            $currentRole,
            'Parent opened secure document using OTP',
            'document_requests',
            $requestId
        );

        $filePath = ltrim((string)$requestData['file_path'], '/');

        header("Location: ../" . $filePath);
        exit();

    } else {
        $message = 'Invalid OTP code or file is unavailable.';
        $messageType = 'danger';
    }
}

/*
|--------------------------------------------------------------------------
| Fetch requests
|--------------------------------------------------------------------------
*/

$requests = [];

if ($student) {
    $requests = safeFetchAll($pdo, "
        SELECT 
            dr.*,
            req.first_name AS requester_first_name,
            req.middle_name AS requester_middle_name,
            req.last_name AS requester_last_name,
            req.role AS requester_role
        FROM document_requests dr
        LEFT JOIN users req ON req.id = dr.requested_by
        WHERE dr.student_id = ?
        ORDER BY dr.created_at DESC
    ", [$studentId]);
}

$pendingCount = $student ? safeCount($pdo, "
    SELECT COUNT(*)
    FROM document_requests
    WHERE student_id = ?
    AND status = 'pending'
", [$studentId]) : 0;

$releasedCount = $student ? safeCount($pdo, "
    SELECT COUNT(*)
    FROM document_requests
    WHERE student_id = ?
    AND status = 'released'
", [$studentId]) : 0;

$softCopyCount = $student ? safeCount($pdo, "
    SELECT COUNT(*)
    FROM document_requests
    WHERE student_id = ?
    AND request_copy_type = 'softcopy'
", [$studentId]) : 0;

$printedCopyCount = $student ? safeCount($pdo, "
    SELECT COUNT(*)
    FROM document_requests
    WHERE student_id = ?
    AND request_copy_type = 'printed'
", [$studentId]) : 0;

if (isset($_GET['success'])) {
    $message = 'Document request submitted successfully.';
    $messageType = 'success';
}

if (isset($_GET['verified'])) {
    $message = 'OTP verified successfully. You may now view the released file.';
    $messageType = 'success';
}

/*
|--------------------------------------------------------------------------
| Sidebar links
|--------------------------------------------------------------------------
*/

if ($currentRole === 'parent') {
    $dashboardLink = 'dashboard.php';
    $attendanceLink = 'attendance.php';
    $documentLink = 'document-request.php';
    $profileLink = 'profile.php';
    $settingsLink = 'settings.php';
} else {
    $dashboardLink = 'dashboard.php';
    $attendanceLink = 'attendance.php';
    $documentLink = 'document-request.php';
    $parentAccessLink = 'parent-access.php';
    $profileLink = 'profile.php';
    $settingsLink = 'settings.php';
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Document Request | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --primary-navy:#0B1C3D;
    --primary-blue:#0056B3;
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

.hero-card {
    background:
        linear-gradient(135deg,rgba(11,28,61,.96),rgba(0,86,179,.78)),
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
    box-shadow:var(--shadow-md);
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

.hero-pill {
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.25);
    color:white;
    padding:12px 16px;
    border-radius:999px;
    font-weight:900;
    display:flex;
    align-items:center;
    gap:8px;
}

.alert {
    margin-bottom:20px;
    padding:15px 18px;
    border-radius:14px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:10px;
}

.alert.success {
    background:#DCFCE7;
    color:#166534;
    border:1px solid #A7F3D0;
}

.alert.danger {
    background:#FEE2E2;
    color:#991B1B;
    border:1px solid #FECACA;
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

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(180px,1fr));
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
    transition:var(--transition);
}

.stat-card:hover {
    transform:translateY(-4px);
    box-shadow:var(--shadow-md);
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
    font-size:1.45rem;
    font-weight:800;
    color:var(--primary-navy);
}

.stat-info p {
    font-size:.78rem;
    color:var(--text-muted);
    font-weight:800;
    text-transform:uppercase;
}

.dashboard-grid {
    display:grid;
    grid-template-columns:1fr .85fr;
    gap:22px;
    margin-bottom:24px;
}

.panel {
    background:var(--surface-white);
    border:1px solid var(--border-color);
    border-radius:22px;
    padding:24px;
    box-shadow:var(--shadow-sm);
}

.panel-header {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
    margin-bottom:18px;
}

.panel-header h3 {
    color:var(--primary-navy);
    font-size:1.05rem;
}

.form-group {
    margin-bottom:16px;
}

.form-group label {
    display:block;
    margin-bottom:7px;
    font-size:.82rem;
    font-weight:900;
    color:var(--primary-navy);
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

textarea.form-control {
    min-height:105px;
    resize:vertical;
}

.btn-main {
    border:none;
    border-radius:12px;
    background:var(--primary-blue);
    color:white;
    padding:13px 18px;
    font-weight:900;
    cursor:pointer;
    font-family:'Montserrat',sans-serif;
    display:inline-flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
}

.btn-main.green {
    background:var(--success);
}

.btn-main.small {
    padding:9px 12px;
    font-size:.78rem;
}

.table-container {
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:18px;
    overflow:auto;
    box-shadow:var(--shadow-sm);
}

table {
    width:100%;
    border-collapse:collapse;
    min-width:1120px;
}

th {
    background:#F8FAFC;
    padding:16px 18px;
    font-size:.78rem;
    font-weight:900;
    color:var(--text-muted);
    text-transform:uppercase;
    border-bottom:2px solid var(--border-color);
    text-align:left;
}

td {
    padding:15px 18px;
    border-bottom:1px solid var(--border-color);
    vertical-align:middle;
}

.badge {
    padding:7px 10px;
    border-radius:999px;
    font-size:.72rem;
    font-weight:900;
    background:#DCFCE7;
    color:#166534;
    text-transform:capitalize;
    white-space:nowrap;
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.badge.pending {
    background:#FEF3C7;
    color:#92400E;
}

.badge.approved {
    background:#DBEAFE;
    color:#1D4ED8;
}

.badge.released,
.badge.present,
.badge.active {
    background:#DCFCE7;
    color:#166534;
}

.badge.rejected {
    background:#FEE2E2;
    color:#991B1B;
}

.badge.printed {
    background:#EDE9FE;
    color:#6D28D9;
}

.badge.softcopy {
    background:#DBEAFE;
    color:#1D4ED8;
}

.otp-box {
    display:flex;
    gap:8px;
    flex-wrap:wrap;
    align-items:center;
}

.otp-box input {
    max-width:150px;
}

.empty-state {
    text-align:center;
    color:var(--text-muted);
    padding:38px 10px;
}

.list {
    display:flex;
    flex-direction:column;
    gap:12px;
}

.list-item {
    padding:14px;
    border:1px solid var(--border-color);
    border-radius:15px;
    background:#F8FAFC;
    display:flex;
    justify-content:space-between;
    gap:12px;
    align-items:center;
}

.list-item h4 {
    color:var(--primary-navy);
    font-size:.92rem;
}

.list-item p {
    color:var(--text-muted);
    font-size:.78rem;
    margin-top:3px;
}


.child-switcher {
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:22px;
    padding:20px;
    margin-bottom:24px;
    box-shadow:var(--shadow-sm);
}

.child-switcher-head {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    margin-bottom:15px;
    flex-wrap:wrap;
}

.child-switcher-head h3 {
    color:var(--primary-navy);
    font-size:1.05rem;
    font-weight:900;
}

.child-switcher-head p {
    color:var(--text-muted);
    font-size:.86rem;
    margin-top:3px;
}

.child-tabs {
    display:grid;
    grid-template-columns:repeat(auto-fit,minmax(230px,1fr));
    gap:12px;
}

.child-tab {
    border:1px solid var(--border-color);
    background:#F8FAFC;
    border-radius:18px;
    padding:14px;
    text-decoration:none;
    color:var(--text-dark);
    display:flex;
    align-items:center;
    gap:12px;
    transition:var(--transition);
}

.child-tab:hover {
    transform:translateY(-2px);
    border-color:rgba(0,86,179,.35);
    background:#fff;
    box-shadow:var(--shadow-sm);
}

.child-tab.active {
    background:linear-gradient(135deg,rgba(0,86,179,.1),rgba(11,28,61,.05));
    border-color:var(--primary-blue);
    box-shadow:0 10px 24px rgba(0,86,179,.12);
}

.child-tab-avatar {
    width:44px;
    height:44px;
    border-radius:14px;
    background:var(--primary-blue);
    color:#fff;
    display:flex;
    align-items:center;
    justify-content:center;
    font-weight:900;
    flex-shrink:0;
}

.child-tab strong {
    display:block;
    color:var(--primary-navy);
    font-size:.92rem;
    margin-bottom:3px;
}

.child-tab span {
    color:var(--text-muted);
    font-size:.78rem;
    font-weight:700;
}

.selected-child-card {
    background:linear-gradient(135deg,#F8FAFC,#EFF6FF);
    border:1px solid #BFDBFE;
    border-radius:16px;
    padding:14px;
    margin-bottom:16px;
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:12px;
}

.selected-child-card h4 {
    color:var(--primary-navy);
    margin-bottom:3px;
}

.selected-child-card p {
    color:var(--text-muted);
    font-size:.82rem;
}

@media(max-width:1150px) {
    .stats-grid {
        grid-template-columns:repeat(2,1fr);
    }

    .dashboard-grid {
        grid-template-columns:1fr;
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

    table {
        min-width:980px;
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

        <a href="<?= e($dashboardLink) ?>" class="nav-item">
            <i class="fa fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="<?= e($attendanceLink) ?>" class="nav-item">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <a href="<?= e($documentLink) ?>" class="nav-item active">
            <i class="fa fa-file-lines"></i>
            <span>Document Request</span>
        </a>

        <?php if ($currentRole === 'student'): ?>
            <a href="<?= e($parentAccessLink) ?>" class="nav-item">
                <i class="fa fa-key"></i>
                <span>Parent Access</span>
            </a>
        <?php endif; ?>

        <a href="<?= e($profileLink) ?>" class="nav-item">
            <i class="fa fa-user"></i>
            <span>Profile</span>
        </a>

        <a href="<?= e($settingsLink) ?>" class="nav-item">
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
        <h1>Document Request</h1>
        <p><?= e($roleLabel) ?> • Pitogo EduTrack</p>
    </div>

    <div class="user-profile">
        <div class="user-info">
            <h4><?= e($userName) ?></h4>
            <p><?= e(ucfirst($currentRole)) ?></p>
        </div>

        <img src="<?= e($userAvatar) ?>" class="avatar" alt="Profile">
    </div>
</header>

<section class="content-area">

    <section class="hero-card">
        <div>
            <h1>Document Request Portal</h1>
            <p>
                Request official school documents as soft copy or printed copy.
                Report Card requests are available as printed copy only.
            </p>
        </div>

        <div class="hero-pill">
            <i class="fa fa-user-graduate"></i>
            <?= e($studentName) ?>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert <?= e($messageType) ?>">
            <i class="fa <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="notice">
        <strong>Important:</strong>
        Soft copy documents are for viewing/reference purposes only and will
        <strong>not contain the official dry seal or Certified True Copy (CTC)</strong>.
        For sealed or official copies, please request the printed version.
    </div>

    <?php if (!$student): ?>
        <div class="alert danger">
            <i class="fa fa-triangle-exclamation"></i>
            No linked student account found. Please link your child first from the Parent Dashboard.
        </div>
    <?php endif; ?>

    <?php if ($linkedStudents): ?>
        <div class="child-switcher">
            <div class="child-switcher-head">
                <div>
                    <h3><i class="fa fa-children" style="color:var(--primary-blue);margin-right:8px;"></i>Switch Child View</h3>
                    <p>Select which linked child you want to view or request documents for.</p>
                </div>
                <span class="badge active"><?= count($linkedStudents) ?> linked child<?= count($linkedStudents) > 1 ? 'ren' : '' ?></span>
            </div>

            <div class="child-tabs">
                <?php foreach ($linkedStudents as $linked): ?>
                    <?php
                        $linkedName = fullName($linked) ?: 'Student';
                        $initials = strtoupper(substr(trim($linked['first_name'] ?? 'S'), 0, 1) . substr(trim($linked['last_name'] ?? ''), 0, 1));
                        $isActiveChild = (int)$linked['id'] === (int)$studentId;
                    ?>
                    <a href="document-request.php?student_id=<?= (int)$linked['id'] ?>" class="child-tab <?= $isActiveChild ? 'active' : '' ?>">
                        <div class="child-tab-avatar"><?= e($initials ?: 'S') ?></div>
                        <div>
                            <strong><?= e($linkedName) ?></strong>
                            <span><?= !empty($linked['id_num']) ? 'LRN / ID: ' . e($linked['id_num']) : 'Student Account' ?></span>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning);">
                <i class="fa fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($pendingCount) ?></h3>
                <p>Pending Requests</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success);">
                <i class="fa fa-file-circle-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($releasedCount) ?></h3>
                <p>Released Requests</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--info);">
                <i class="fa fa-cloud-arrow-down"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($softCopyCount) ?></h3>
                <p>Soft Copies</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(124,58,237,.1);color:#7C3AED;">
                <i class="fa fa-print"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($printedCopyCount) ?></h3>
                <p>Printed Copies</p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h3>Submit New Request</h3>
            </div>

            <form method="POST">

                <input type="hidden" name="student_id" value="<?= e($studentId) ?>">

                <div class="selected-child-card">
                    <div>
                        <h4><?= e($studentName) ?></h4>
                        <p>Selected child for this document request<?= $studentLrn !== 'N/A' ? ' • LRN / ID: ' . e($studentLrn) : '' ?></p>
                    </div>
                    <i class="fa fa-user-graduate" style="color:var(--primary-blue);font-size:1.4rem;"></i>
                </div>

                <div class="form-group">
                    <label>Document Type</label>
                    <select name="document_type" class="form-control" required id="documentType" <?= !$student ? 'disabled' : '' ?>>
                        <option value="">Select document</option>
                        <option>Certificate of Enrollment</option>
                        <option>Good Moral Certificate</option>
                        <option>Form 137</option>
                        <option>Report Card</option>
                        <option>Certificate of Graduation</option>
                        <option>Transcript of Records</option>
                        <option>Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Copy Type</label>
                    <select name="copy_type" class="form-control" required id="copyType" <?= !$student ? 'disabled' : '' ?>>
                        <option value="">Select option</option>
                        <option value="softcopy">Soft Copy</option>
                        <option value="printed">Printed Copy</option>
                    </select>
                    <small style="display:block;margin-top:7px;color:var(--text-muted);line-height:1.5;">
                        Soft copy is for viewing/reference only and does not include dry seal or CTC. Report Card is printed copy only.
                    </small>
                </div>

                <div class="form-group">
                    <label>Purpose</label>
                    <textarea name="purpose" class="form-control" placeholder="State your reason or purpose..." <?= !$student ? 'disabled' : '' ?>></textarea>
                </div>

                <button type="submit" name="submit_request" class="btn-main" <?= !$student ? 'disabled' : '' ?>>
                    <i class="fa fa-paper-plane"></i>
                    Submit Request
                </button>
            </form>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Release & OTP Information</h3>
            </div>

            <div class="list">
                <div class="list-item">
                    <div>
                        <h4>Soft copy access</h4>
                        <p>Soft copy files require OTP verification before viewing.</p>
                    </div>
                    <span class="badge softcopy">OTP</span>
                </div>

                <div class="list-item">
                    <div>
                        <h4>Printed copy release</h4>
                        <p>Printed copies may include office claiming instructions and pickup date.</p>
                    </div>
                    <span class="badge printed">Office</span>
                </div>

                <div class="list-item">
                    <div>
                        <h4>Report Card</h4>
                        <p>Report Card is only available as printed copy.</p>
                    </div>
                    <span class="badge printed">Printed Only</span>
                </div>
            </div>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Request History</h3>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Tracking ID</th>
                        <th>Document</th>
                        <th>Copy Type</th>
                        <th>Status</th>
                        <th>OTP Protection</th>
                        <th>Release</th>
                        <th>Requested By</th>
                        <th>Date Requested</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($requests): ?>
                        <?php foreach ($requests as $request): ?>
                            <?php
                                $requesterName = trim(
                                    ($request['requester_first_name'] ?? '') . ' ' .
                                    ($request['requester_middle_name'] ?? '') . ' ' .
                                    ($request['requester_last_name'] ?? '')
                                );

                                if ($requesterName === '') {
                                    $requesterName = 'System';
                                }

                                $copyType = strtolower($request['request_copy_type'] ?? 'printed');
                                $status = strtolower($request['status'] ?? 'pending');
                                $isSoftCopy = $copyType === 'softcopy';
                                $hasFile = !empty($request['file_path']);
                                $isVerified = false; // OTP required every time
                            ?>
                            <tr>
                                <td>
                                    <strong><?= e($request['tracking_id'] ?: 'REQ-' . $request['id']) ?></strong>
                                </td>

                                <td><?= e($request['document_type']) ?></td>

                                <td>
                                    <span class="badge <?= e($copyType) ?>">
                                        <i class="fa <?= $copyType === 'softcopy' ? 'fa-cloud-arrow-down' : 'fa-print' ?>"></i>
                                        <?= e($copyType === 'softcopy' ? 'Soft Copy' : 'Printed Copy') ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="badge <?= e($status) ?>">
                                        <?= e($status) ?>
                                    </span>
                                </td>

                                <td>
                                    <?php if ($isSoftCopy): ?>
                                        <?php if ($isVerified): ?>
                                            <span class="badge released">
                                                <i class="fa fa-check"></i>
                                                Verified
                                            </span>
                                        <?php else: ?>
                                            <form method="POST" class="otp-box">
                                                <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">
                                                <?php if ($currentRole === 'parent'): ?>
                                                    <input type="hidden" name="student_id" value="<?= e($studentId) ?>">
                                                <?php endif; ?>

                                                <input
                                                    type="text"
                                                    name="entered_otp"
                                                    class="form-control"
                                                    placeholder="Enter OTP"
                                                    maxlength="6"
                                                    required
                                                >

                                                <button type="submit" name="verify_otp" class="btn-main small">
                                                    Verify
                                                </button>
                                            </form>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="badge printed">
                                            <i class="fa fa-building"></i>
                                            Office Claim
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <?php if ($isSoftCopy && $hasFile && $status === 'released'): ?>
                                        <form method="POST" class="otp-box" target="_blank">
                                            <input type="hidden" name="request_id" value="<?= (int)$request['id'] ?>">

                                            <?php if ($currentRole === 'parent'): ?>
                                                <input type="hidden" name="student_id" value="<?= e($studentId) ?>">
                                            <?php endif; ?>

                                            <input
                                                type="password"
                                                name="entered_otp"
                                                class="form-control"
                                                placeholder="Enter OTP"
                                                maxlength="6"
                                                required
                                            >

                                            <button type="submit" name="verify_otp" class="btn-main small green">
                                                <i class="fa fa-lock-open"></i>
                                                Open File
                                            </button>
                                        </form>

                                        <small style="display:block;margin-top:6px;color:var(--text-muted);font-weight:700;">
                                            OTP is required every time you open this file.
                                        </small>
                                    <?php elseif (!$isSoftCopy && !empty($request['pickup_date'])): ?>
                                        <strong>Pickup:</strong>
                                        <?= e(date('M d, Y', strtotime($request['pickup_date']))) ?>
                                        <?php if (!empty($request['instructions'])): ?>
                                            <br>
                                            <span style="font-size:.8rem;color:var(--text-muted);">
                                                <?= e($request['instructions']) ?>
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span style="color:var(--text-muted);">Not available</span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <strong><?= e($requesterName) ?></strong>
                                    <br>
                                    <span style="font-size:.78rem;color:var(--text-muted);">
                                        <?= e($request['requester_role'] ?? 'user') ?>
                                    </span>
                                </td>

                                <td>
                                    <?= !empty($request['created_at']) ? e(date('M d, Y', strtotime($request['created_at']))) : 'N/A' ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">
                                <div class="empty-state">
                                    <i class="fa fa-folder-open" style="font-size:2rem;margin-bottom:10px;"></i>
                                    <br>
                                    No document requests yet.
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>

            </table>
        </div>
    </div>

</section>

</main>

<script>
const documentType = document.getElementById('documentType');
const copyType = document.getElementById('copyType');

if (documentType && copyType) {
    documentType.addEventListener('change', function () {
        const softCopyOption = copyType.querySelector('option[value="softcopy"]');

        if (this.value.toLowerCase() === 'report card') {
            copyType.value = 'printed';

            if (softCopyOption) {
                softCopyOption.disabled = true;
            }

            alert('Report Card is only available as printed copy.');
        } else {
            if (softCopyOption) {
                softCopyOption.disabled = false;
            }
        }
    });
}
</script>

</body>
</html>
