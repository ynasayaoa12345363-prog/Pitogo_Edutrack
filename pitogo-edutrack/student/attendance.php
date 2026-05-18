<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Student Attendance
|--------------------------------------------------------------------------
| Save as:
| student/attendance.php
|
| Notes:
| - This page displays the student's QR code and attendance history.
| - Parent accounts can see the same records because attendance is stored
|   in the shared attendance table.
| - Staff scanning is handled by staff/mark-attendance.php.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if ($currentRole !== 'student') {
    header("Location: ../index.html?error=" . urlencode("Unauthorized access."));
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
    return trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function profilePath(array $row, string $name): string {
    $profile = $row['profile_picture'] ?? $row['profile_pic'] ?? '';

    if ($profile) {
        if (preg_match('/^https?:\/\//i', $profile)) {
            return $profile;
        }

        return '../uploads/profiles/' . ltrim($profile, '/');
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'Student') . '&background=0056B3&color=fff';
}

/*
|--------------------------------------------------------------------------
| Database safety
|--------------------------------------------------------------------------
*/

if (!tableExists($pdo, 'attendance')) {
    $pdo->exec("
        CREATE TABLE attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME NULL,
            status VARCHAR(30) DEFAULT 'present',
            scanned_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_date (student_id, attendance_date)
        )
    ");
}

if (!columnExists($pdo, 'attendance', 'student_id')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN student_id INT NOT NULL");
}

if (!columnExists($pdo, 'attendance', 'attendance_date')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN attendance_date DATE NOT NULL");
}

if (!columnExists($pdo, 'attendance', 'time_in')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN time_in TIME NULL");
}

if (!columnExists($pdo, 'attendance', 'status')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN status VARCHAR(30) DEFAULT 'present'");
}

if (!columnExists($pdo, 'attendance', 'scanned_by')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN scanned_by INT NULL");
}

if (!columnExists($pdo, 'attendance', 'created_at')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/*
|--------------------------------------------------------------------------
| Fetch student
|--------------------------------------------------------------------------
*/

$stmtStudent = $pdo->prepare("
    SELECT *
    FROM users
    WHERE id = ?
    AND role = 'student'
    LIMIT 1
");
$stmtStudent->execute([$currentUserId]);
$student = $stmtStudent->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: ../index.html");
    exit();
}

$studentName = fullName($student) ?: 'Student';
$studentAvatar = profilePath($student, $studentName);
$studentLrn = $student['id_num'] ?? $student['lrn'] ?? 'N/A';

/*
|--------------------------------------------------------------------------
| QR token
|--------------------------------------------------------------------------
*/

if (empty($student['qr_token'])) {
    $newToken = bin2hex(random_bytes(24));

    if (!columnExists($pdo, 'users', 'qr_token')) {
        $pdo->exec("ALTER TABLE users ADD COLUMN qr_token VARCHAR(255) NULL");
    }

    $stmt = $pdo->prepare("
        UPDATE users
        SET qr_token = ?
        WHERE id = ?
    ");
    $stmt->execute([$newToken, $currentUserId]);

    $student['qr_token'] = $newToken;
}

/*
|--------------------------------------------------------------------------
| Attendance data
|--------------------------------------------------------------------------
*/

$records = safeFetchAll($pdo, "
    SELECT 
        a.*,
        st.first_name AS staff_first_name,
        st.last_name AS staff_last_name
    FROM attendance a
    LEFT JOIN users st ON st.id = a.scanned_by
    WHERE a.student_id = ?
    ORDER BY a.attendance_date DESC, a.created_at DESC
", [$currentUserId]);

$totalPresent = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE student_id = ?
    AND status = 'present'
", [$currentUserId]);

$thisMonth = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE student_id = ?
    AND MONTH(attendance_date) = MONTH(CURDATE())
    AND YEAR(attendance_date) = YEAR(CURDATE())
", [$currentUserId]);

$presentToday = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE student_id = ?
    AND attendance_date = CURDATE()
", [$currentUserId]);

$lastScan = safeFetchAll($pdo, "
    SELECT *
    FROM attendance
    WHERE student_id = ?
    ORDER BY created_at DESC
    LIMIT 1
", [$currentUserId]);

$lastScanText = $lastScan && !empty($lastScan[0]['created_at'])
    ? date('M d, Y h:i A', strtotime($lastScan[0]['created_at']))
    : 'No scan yet';

$currentDay = (int)date('j');
$monthlyRate = $currentDay > 0
    ? round(($thisMonth / $currentDay) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| Parent connection status
|--------------------------------------------------------------------------
*/

$linkedParents = 0;

if (tableExists($pdo, 'parent_student_links')) {
    $linkedParents = safeCount($pdo, "
        SELECT COUNT(*)
        FROM parent_student_links
        WHERE student_id = ?
        AND status = 'active'
    ", [$currentUserId]);
}

$parentVisibilityText = $linkedParents > 0
    ? $linkedParents . ' linked parent account(s)'
    : 'No linked parent yet';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Attendance | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

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

.hero-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.btn-white,
.btn-outline {
    padding:12px 16px;
    border-radius:12px;
    text-decoration:none;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    gap:8px;
    border:none;
    cursor:pointer;
    font-family:'Montserrat',sans-serif;
}

.btn-white {
    background:#fff;
    color:var(--primary-blue);
}

.btn-outline {
    border:1px solid rgba(255,255,255,.3);
    color:#fff;
    background:rgba(255,255,255,.12);
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
    font-size:1.35rem;
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

.qr-box {
    text-align:center;
    background:#F8FAFC;
    border:1px dashed var(--primary-blue);
    border-radius:18px;
    padding:22px;
}

#studentQR {
    display:flex;
    justify-content:center;
    margin:18px auto;
}

.qr-token {
    font-size:.75rem;
    word-break:break-all;
    color:var(--text-muted);
    background:white;
    border:1px solid var(--border-color);
    border-radius:10px;
    padding:10px;
    margin-top:10px;
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

.badge {
    padding:7px 10px;
    border-radius:999px;
    font-size:.72rem;
    font-weight:900;
    background:#DCFCE7;
    color:#166534;
    text-transform:capitalize;
    white-space:nowrap;
}

.badge.present {
    background:#DCFCE7;
    color:#166534;
}

.badge.absent {
    background:#FEE2E2;
    color:#991B1B;
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
    min-width:900px;
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

.empty-state {
    text-align:center;
    color:var(--text-muted);
    padding:28px 10px;
}

.print-header,
.print-meta,
.print-signature {
    display:none;
}

/* =========================
   CLEAN LANDSCAPE PRINT
========================= */
@media print {
    * {
        -webkit-print-color-adjust:exact !important;
        print-color-adjust:exact !important;
    }

    body {
        background:#fff !important;
        color:#111827 !important;
        display:block !important;
        font-family:Arial, sans-serif !important;
        margin:0 !important;
        padding:0 !important;
    }

    .sidebar,
    .top-header,
    .hero-card,
    .stats-grid,
    .dashboard-grid,
    .no-print {
        display:none !important;
    }

    .main-wrapper {
        margin-left:0 !important;
        width:100% !important;
    }

    .content-area {
        padding:0 !important;
        margin:0 !important;
        max-width:none !important;
        width:100% !important;
    }

    .print-header {
        display:flex !important;
        align-items:center !important;
        justify-content:center !important;
        gap:18px !important;
        text-align:center !important;
        padding-bottom:14px !important;
        border-bottom:2px solid #0B1C3D !important;
        margin-bottom:14px !important;
    }

    .print-header img {
        width:78px !important;
        height:78px !important;
        object-fit:contain !important;
    }

    .print-header h2 {
        margin:0 !important;
        font-size:22px !important;
        line-height:1.1 !important;
        font-weight:800 !important;
        color:#0B1C3D !important;
        letter-spacing:.4px !important;
    }

    .print-header p {
        margin:3px 0 !important;
        font-size:12px !important;
        color:#334155 !important;
    }

    .print-header h3 {
        margin-top:6px !important;
        font-size:13px !important;
        font-weight:800 !important;
        color:#111827 !important;
        text-transform:uppercase !important;
        letter-spacing:.6px !important;
    }

    .print-meta {
        display:grid !important;
        grid-template-columns:repeat(4,1fr) !important;
        gap:8px !important;
        margin-bottom:12px !important;
    }

    .print-meta div {
        border:1px solid #CBD5E1 !important;
        background:#F8FAFC !important;
        padding:8px 10px !important;
    }

    .print-meta span {
        display:block !important;
        font-size:9px !important;
        font-weight:700 !important;
        color:#64748B !important;
        text-transform:uppercase !important;
        margin-bottom:2px !important;
    }

    .print-meta strong {
        display:block !important;
        font-size:11px !important;
        color:#111827 !important;
    }

    .panel {
        border:none !important;
        box-shadow:none !important;
        padding:0 !important;
        border-radius:0 !important;
        background:#fff !important;
    }

    .panel-header {
        display:none !important;
    }

    .table-container {
        border:none !important;
        border-radius:0 !important;
        box-shadow:none !important;
        overflow:visible !important;
        background:#fff !important;
    }

    table {
        width:100% !important;
        min-width:0 !important;
        border-collapse:collapse !important;
        table-layout:fixed !important;
        font-size:10px !important;
    }

    thead {
        display:table-header-group !important;
    }

    tr {
        page-break-inside:avoid !important;
    }

    th {
        background:#E5E7EB !important;
        color:#111827 !important;
        border:1px solid #94A3B8 !important;
        padding:7px 6px !important;
        font-size:9px !important;
        font-weight:800 !important;
        text-transform:uppercase !important;
    }

    td {
        border:1px solid #CBD5E1 !important;
        padding:7px 6px !important;
        vertical-align:top !important;
        word-wrap:break-word !important;
        overflow-wrap:anywhere !important;
        color:#111827 !important;
    }

    .badge {
        background:transparent !important;
        color:#111827 !important;
        padding:0 !important;
        border-radius:0 !important;
        font-size:9px !important;
        font-weight:700 !important;
    }

    .empty-state {
        padding:18px !important;
        color:#64748B !important;
    }

    .print-signature {
        display:flex !important;
        justify-content:flex-end !important;
        margin-top:30px !important;
        page-break-inside:avoid !important;
    }

    .signature-box {
        width:240px !important;
        text-align:center !important;
        font-size:11px !important;
        color:#111827 !important;
    }

    .signature-line {
        border-top:1px solid #111827 !important;
        margin-top:34px !important;
        padding-top:5px !important;
        font-weight:800 !important;
    }

    @page {
        size:landscape;
        margin:12mm;
    }
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
            <p>Student Portal</p>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">Main</div>

        <a href="dashboard.php" class="nav-item">
            <i class="fa fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="attendance.php" class="nav-item active">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <a href="document-request.php" class="nav-item">
            <i class="fa fa-file-lines"></i>
            <span>Document Request</span>
        </a>

        <a href="parent-access.php" class="nav-item">
            <i class="fa fa-key"></i>
            <span>Parent Access</span>
        </a>

        <a href="profile.php" class="nav-item">
            <i class="fa fa-user"></i>
            <span>Profile</span>
        </a>

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
        <h1>Attendance</h1>
        <p>QR attendance records • Pitogo EduTrack</p>
    </div>

    <div class="user-profile">
        <div class="user-info">
            <h4><?= e($studentName) ?></h4>
            <p>Student</p>
        </div>

        <img src="<?= e($studentAvatar) ?>" class="avatar" alt="Student Profile">
    </div>
</header>

<section class="content-area">

    <section class="hero-card">
        <div>
            <h1>My QR Attendance</h1>
            <p>
                Show this QR code to the entrance staff scanner to mark your attendance.
                Linked parent accounts can view the same attendance record after every successful scan.
            </p>
        </div>

        <div class="hero-actions">
            <button onclick="window.print()" class="btn-white">
                <i class="fa fa-print"></i>
                Print Attendance History
            </button>
        </div>
    </section>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success);">
                <i class="fa fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalPresent) ?></h3>
                <p>Total Present</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(0,86,179,.1);color:var(--primary-blue);">
                <i class="fa fa-calendar"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($thisMonth) ?></h3>
                <p>This Month</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning);">
                <i class="fa fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= e($lastScanText) ?></h3>
                <p>Last Scan</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--info);">
                <i class="fa fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= e($parentVisibilityText) ?></h3>
                <p>Parent Visibility</p>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h3>Your Student QR Code</h3>
            </div>

            <div class="qr-box">
                <h3><?= e($studentName) ?></h3>
                <p style="color:var(--text-muted);margin-top:5px;">LRN: <?= e($studentLrn) ?></p>
                <div id="studentQR"></div>
                <div class="qr-token"><?= e($student['qr_token']) ?></div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Attendance Notes</h3>
            </div>

            <div class="list">
                <div class="list-item">
                    <div>
                        <h4>Real-time parent access</h4>
                        <p>Linked parents can see attendance records from the same attendance table.</p>
                    </div>
                    <span class="badge present">Shared</span>
                </div>

                <div class="list-item">
                    <div>
                        <h4>One scan per day</h4>
                        <p>The system prevents duplicate scans on the same date.</p>
                    </div>
                    <span class="badge">Daily</span>
                </div>

                <div class="list-item">
                    <div>
                        <h4>Monthly attendance rate</h4>
                        <p><?= e($monthlyRate) ?>% based on the current day of the month.</p>
                    </div>
                    <span class="badge present"><?= e($monthlyRate) ?>%</span>
                </div>
            </div>
        </div>
    </div>

    <div class="print-header">
        <img src="../school-logo.jpg" alt="Pitogo High School Logo">
        <div>
            <h2>PITOGO HIGH SCHOOL</h2>
            <p>Pitogo EduTrack Management System</p>
            <p>Luzon St, Pitogo, Taguig, 1630 Metro Manila, Philippines</p>
            <h3>Official Student Attendance History Report</h3>
        </div>
    </div>

    <div class="print-meta">
        <div>
            <span>Student Name</span>
            <strong><?= e($studentName) ?></strong>
        </div>

        <div>
            <span>LRN / ID Number</span>
            <strong><?= e($studentLrn) ?></strong>
        </div>

        <div>
            <span>Date Generated</span>
            <strong><?= e(date('F d, Y h:i A')) ?></strong>
        </div>

        <div>
            <span>Total Records</span>
            <strong><?= e(number_format(count($records))) ?> record(s)</strong>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Attendance History</h3>
        </div>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th style="width:70px;">No.</th>
                        <th>Attendance Date</th>
                        <th>Time In</th>
                        <th>Status</th>
                        <th>Scanned By</th>
                        <th>Recorded At</th>
                    </tr>
                </thead>

                <tbody>
                    <?php if ($records): ?>
                        <?php $i = 1; foreach ($records as $record): ?>
                            <?php
                                $staffName = trim(($record['staff_first_name'] ?? '') . ' ' . ($record['staff_last_name'] ?? ''));
                                if ($staffName === '') {
                                    $staffName = 'Staff / System';
                                }
                            ?>
                            <tr>
                                <td><?= $i++ ?></td>
                                <td><?= e(date('F d, Y', strtotime($record['attendance_date']))) ?></td>
                                <td><?= !empty($record['time_in']) ? e(date('h:i A', strtotime($record['time_in']))) : 'N/A' ?></td>
                                <td>
                                    <span class="badge <?= e(strtolower($record['status'] ?? 'present')) ?>">
                                        <?= e($record['status'] ?? 'present') ?>
                                    </span>
                                </td>
                                <td><?= e($staffName) ?></td>
                                <td><?= !empty($record['created_at']) ? e(date('M d, Y h:i A', strtotime($record['created_at']))) : 'N/A' ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">No attendance records yet.</div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="print-signature">
        <div class="signature-box">
            <div class="signature-line"><?= e($studentName) ?></div>
            <div>Student</div>
        </div>
    </div>

</section>

</main>

<script>
new QRCode(document.getElementById("studentQR"), {
    text: <?= json_encode($student['qr_token']) ?>,
    width: 220,
    height: 220,
    correctLevel: QRCode.CorrectLevel.H
});
</script>

</body>
</html>
