<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Staff Dashboard
|--------------------------------------------------------------------------
| Save as:
| staff/dashboard.php
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if ($currentRole !== 'staff') {
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

/*
|--------------------------------------------------------------------------
| Staff info
|--------------------------------------------------------------------------
*/

$stmtStaff = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtStaff->execute([$currentUserId]);
$staff = $stmtStaff->fetch(PDO::FETCH_ASSOC) ?: [];

$staffName = fullName($staff) ?: 'Staff User';

$staffAvatar = !empty($staff['profile_picture'])
    ? '../uploads/profiles/' . $staff['profile_picture']
    : 'https://ui-avatars.com/api/?name=' . urlencode($staffName) . '&background=0056B3&color=fff';

/*
|--------------------------------------------------------------------------
| Dashboard metrics
|--------------------------------------------------------------------------
*/

$totalStudents = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'student'
    AND status = 'active'
    AND is_archived = 0
    AND is_deleted = 0
");

$totalPresentToday = safeCount($pdo, "
    SELECT COUNT(DISTINCT student_id)
    FROM attendance
    WHERE attendance_date = CURDATE()
");

$myScansToday = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE scanned_by = ?
    AND attendance_date = CURDATE()
", [$currentUserId]);

$myScansMonth = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE scanned_by = ?
    AND MONTH(attendance_date) = MONTH(CURDATE())
    AND YEAR(attendance_date) = YEAR(CURDATE())
", [$currentUserId]);

$totalScansToday = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE attendance_date = CURDATE()
");

$estimatedAbsentToday = max($totalStudents - $totalPresentToday, 0);

$attendanceRate = $totalStudents > 0
    ? round(($totalPresentToday / $totalStudents) * 100)
    : 0;

$myContributionRate = $totalScansToday > 0
    ? round(($myScansToday / $totalScansToday) * 100)
    : 0;

/*
|--------------------------------------------------------------------------
| Chart: last 7 days total attendance
|--------------------------------------------------------------------------
*/

$weekRows = safeFetchAll($pdo, "
    SELECT attendance_date, COUNT(DISTINCT student_id) AS total
    FROM attendance
    WHERE attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY attendance_date
    ORDER BY attendance_date ASC
");

$weekMap = [];
foreach ($weekRows as $row) {
    $weekMap[$row['attendance_date']] = (int)$row['total'];
}

$weekLabels = [];
$weekCounts = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $weekLabels[] = date('D', strtotime($date));
    $weekCounts[] = $weekMap[$date] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Chart: my scans per day this week
|--------------------------------------------------------------------------
*/

$myWeekRows = safeFetchAll($pdo, "
    SELECT attendance_date, COUNT(*) AS total
    FROM attendance
    WHERE scanned_by = ?
    AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    GROUP BY attendance_date
    ORDER BY attendance_date ASC
", [$currentUserId]);

$myWeekMap = [];
foreach ($myWeekRows as $row) {
    $myWeekMap[$row['attendance_date']] = (int)$row['total'];
}

$myWeekCounts = [];
foreach ($weekLabels as $index => $label) {
    $date = date('Y-m-d', strtotime('-' . (6 - $index) . ' days'));
    $myWeekCounts[] = $myWeekMap[$date] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Chart: hourly scan pattern today
|--------------------------------------------------------------------------
*/

$hourRows = safeFetchAll($pdo, "
    SELECT HOUR(time_in) AS scan_hour, COUNT(*) AS total
    FROM attendance
    WHERE attendance_date = CURDATE()
    AND time_in IS NOT NULL
    GROUP BY HOUR(time_in)
    ORDER BY scan_hour ASC
");

$hourMap = [];
foreach ($hourRows as $row) {
    $hourMap[(int)$row['scan_hour']] = (int)$row['total'];
}

$hourLabels = [];
$hourCounts = [];

for ($h = 6; $h <= 18; $h++) {
    $hourLabels[] = date('g A', strtotime(sprintf('%02d:00:00', $h)));
    $hourCounts[] = $hourMap[$h] ?? 0;
}

/*
|--------------------------------------------------------------------------
| Chart: status distribution today
|--------------------------------------------------------------------------
*/

$statusRows = safeFetchAll($pdo, "
    SELECT status, COUNT(*) AS total
    FROM attendance
    WHERE attendance_date = CURDATE()
    GROUP BY status
");

$statusLabels = [];
$statusCounts = [];

if ($statusRows) {
    foreach ($statusRows as $row) {
        $statusLabels[] = ucfirst($row['status'] ?? 'Present');
        $statusCounts[] = (int)$row['total'];
    }
} else {
    $statusLabels = ['Present'];
    $statusCounts = [0];
}

/*
|--------------------------------------------------------------------------
| Recent scans and top scanners
|--------------------------------------------------------------------------
*/

$recentScans = safeFetchAll($pdo, "
    SELECT
        a.*,
        s.first_name,
        s.last_name,
        s.id_num,
        s.email
    FROM attendance a
    JOIN users s ON s.id = a.student_id
    WHERE a.scanned_by = ?
    ORDER BY a.created_at DESC
    LIMIT 8
", [$currentUserId]);

$topScanners = safeFetchAll($pdo, "
    SELECT
        u.first_name,
        u.last_name,
        COUNT(a.id) AS total
    FROM attendance a
    JOIN users u ON u.id = a.scanned_by
    WHERE a.attendance_date = CURDATE()
    GROUP BY a.scanned_by
    ORDER BY total DESC
    LIMIT 5
");

$latestScanTime = safeFetchAll($pdo, "
    SELECT created_at
    FROM attendance
    WHERE scanned_by = ?
    ORDER BY created_at DESC
    LIMIT 1
", [$currentUserId]);

$latestScanText = $latestScanTime && !empty($latestScanTime[0]['created_at'])
    ? date('h:i A', strtotime($latestScanTime[0]['created_at']))
    : 'No scan yet';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Staff Dashboard | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
}

.btn-white {
    background:#fff;
    color:var(--primary-blue);
}

.btn-outline {
    border:1px solid rgba(255,255,255,.3);
    color:#fff;
}

.live-clock {
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.25);
    padding:13px 16px;
    border-radius:14px;
    font-weight:900;
    text-align:right;
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4, minmax(180px, 1fr));
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

.insight-card {
    background:linear-gradient(135deg, #0B1C3D, #0056B3);
    color:white;
    border-radius:22px;
    padding:24px;
    margin-bottom:24px;
    box-shadow:var(--shadow-md);
}

.insight-card h2 {
    font-size:1.2rem;
    margin-bottom:8px;
}

.insight-card p {
    color:rgba(255,255,255,.86);
    line-height:1.6;
}

.insight-grid {
    display:grid;
    grid-template-columns:repeat(3, 1fr);
    gap:14px;
    margin-top:18px;
}

.insight-item {
    background:rgba(255,255,255,.12);
    border:1px solid rgba(255,255,255,.16);
    padding:16px;
    border-radius:16px;
}

.insight-item h3 {
    font-size:1.5rem;
}

.insight-item span {
    color:rgba(255,255,255,.78);
    font-size:.78rem;
}

.dashboard-grid {
    display:grid;
    grid-template-columns:1.2fr .8fr;
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

.panel-header a {
    color:var(--primary-blue);
    font-size:.82rem;
    font-weight:900;
    text-decoration:none;
}

.chart-box {
    height:310px;
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

.empty-state {
    text-align:center;
    color:var(--text-muted);
    padding:28px 10px;
}

@media(max-width:1150px) {
    .stats-grid {
        grid-template-columns:repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns:1fr;
    }

    .insight-grid {
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

    .hero-card {
        align-items:flex-start;
    }

    .live-clock {
        text-align:left;
    }
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-box">
            <img src="../school-logo.jpg" alt="Pitogo High School Logo" onerror="this.style.display='none'; this.parentElement.innerHTML='<i class=&quot;fa fa-qrcode&quot;></i>';">
        </div>

        <div class="brand-text">
            <h2>Pitogo EduTrack</h2>
            <p>Staff Scanner</p>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">Main</div>

        <a href="dashboard.php" class="nav-item active">
            <i class="fa fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="qr-scanner.php" class="nav-item">
            <i class="fa fa-qrcode"></i>
            <span>QR Scanner</span>
        </a>

        <a href="scanner-history.php" class="nav-item">
            <i class="fa fa-clock-rotate-left"></i>
            <span>Scanner History</span>
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
        <h1>Staff Dashboard</h1>
        <p>QR Attendance Analytics • Pitogo EduTrack</p>
    </div>

    <div class="user-profile">
        <div class="user-info">
            <h4><?= e($staffName) ?></h4>
            <p>Entrance Staff</p>
        </div>

        <img src="<?= e($staffAvatar) ?>" class="avatar" alt="Staff Profile">
    </div>
</header>

<section class="content-area">

    <section class="hero-card">
        <div>
            <h1>Welcome, <?= e($staffName) ?></h1>
            <p>
                Monitor QR attendance activity, scan performance, student attendance rate,
                and real-time entrance scanning trends.
            </p>
        </div>

        <div class="hero-actions">
            <a href="qr-scanner.php" class="btn-white">
                <i class="fa fa-qrcode"></i>
                Open Scanner
            </a>

            <a href="scanner-history.php" class="btn-outline">
                <i class="fa fa-clock-rotate-left"></i>
                View History
            </a>

            <div class="live-clock" id="liveClock">Loading time...</div>
        </div>
    </section>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(0,86,179,.1); color:var(--primary-blue);">
                <i class="fa fa-users"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalStudents) ?></h3>
                <p>Active Students</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1); color:var(--success);">
                <i class="fa fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalPresentToday) ?></h3>
                <p>Present Today</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1); color:var(--warning);">
                <i class="fa fa-qrcode"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($myScansToday) ?></h3>
                <p>Your Scans Today</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(239,68,68,.1); color:var(--danger);">
                <i class="fa fa-user-xmark"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($estimatedAbsentToday) ?></h3>
                <p>Not Yet Scanned</p>
            </div>
        </div>
    </div>

    <section class="insight-card">
        <h2>Attendance Insight</h2>
        <p>
            Today’s attendance rate is based on active student accounts and successful QR scans.
            Your contribution rate compares your scans against all QR attendance scans today.
        </p>

        <div class="insight-grid">
            <div class="insight-item">
                <h3><?= e($attendanceRate) ?>%</h3>
                <span>Attendance rate today</span>
            </div>

            <div class="insight-item">
                <h3><?= e($myContributionRate) ?>%</h3>
                <span>Your scanning contribution</span>
            </div>

            <div class="insight-item">
                <h3><?= e($latestScanText) ?></h3>
                <span>Your latest scan</span>
            </div>
        </div>
    </section>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h3>Attendance Trend in the Last 7 Days</h3>
            </div>
            <div class="chart-box">
                <canvas id="weekAttendanceChart"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Today’s Attendance Distribution</h3>
            </div>
            <div class="chart-box">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h3>Hourly Scan Pattern Today</h3>
            </div>
            <div class="chart-box">
                <canvas id="hourlyChart"></canvas>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Your Weekly Scan Activity</h3>
            </div>
            <div class="chart-box">
                <canvas id="myScanChart"></canvas>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header">
                <h3>Recent Students You Scanned</h3>
                <a href="scanner-history.php">View All</a>
            </div>

            <div class="list">
                <?php if ($recentScans): ?>
                    <?php foreach ($recentScans as $scan): ?>
                        <div class="list-item">
                            <div>
                                <h4><?= e(trim(($scan['first_name'] ?? '') . ' ' . ($scan['last_name'] ?? ''))) ?></h4>
                                <p>
                                    LRN: <?= e($scan['id_num'] ?? 'N/A') ?>
                                    <br>
                                    <?= e(date('M d, Y', strtotime($scan['attendance_date']))) ?>
                                    <?php if (!empty($scan['time_in'])): ?>
                                        • <?= e(date('h:i A', strtotime($scan['time_in']))) ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <span class="badge"><?= e($scan['status']) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-qrcode" style="font-size:2rem; margin-bottom:10px;"></i>
                        <br>
                        No recent scans yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3>Top Staff Scanners Today</h3>
            </div>

            <div class="list">
                <?php if ($topScanners): ?>
                    <?php foreach ($topScanners as $scanner): ?>
                        <div class="list-item">
                            <div>
                                <h4><?= e(trim(($scanner['first_name'] ?? '') . ' ' . ($scanner['last_name'] ?? ''))) ?></h4>
                                <p>Entrance QR scanning activity</p>
                            </div>
                            <span class="badge"><?= e($scanner['total']) ?> scans</span>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fa fa-user-clock" style="font-size:2rem; margin-bottom:10px;"></i>
                        <br>
                        No scanner activity yet today.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</section>

</main>

<script>
function updateClock() {
    const now = new Date();

    document.getElementById('liveClock').innerHTML =
        now.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'short',
            day: 'numeric',
            year: 'numeric'
        }) + '<br>' +
        now.toLocaleTimeString('en-US');
}

setInterval(updateClock, 1000);
updateClock();

function createChart(id, type, labels, data, label) {
    const ctx = document.getElementById(id);

    if (!ctx) return;

    new Chart(ctx, {
        type: type,
        data: {
            labels: labels,
            datasets: [{
                label: label,
                data: data,
                borderWidth: 2,
                tension: .35,
                fill: type === 'line',
                backgroundColor: type === 'doughnut'
                    ? ['#10B981', '#F59E0B', '#3B82F6', '#EF4444']
                    : 'rgba(0,86,179,.15)',
                borderColor: type === 'doughnut'
                    ? '#ffffff'
                    : '#0056B3'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: type === 'doughnut'
                }
            },
            scales: type === 'doughnut' ? {} : {
                y: {
                    beginAtZero: true,
                    ticks: {
                        precision: 0
                    }
                }
            }
        }
    });
}

createChart(
    'weekAttendanceChart',
    'line',
    <?= json_encode($weekLabels) ?>,
    <?= json_encode($weekCounts) ?>,
    'Students Present'
);

createChart(
    'statusChart',
    'doughnut',
    <?= json_encode($statusLabels) ?>,
    <?= json_encode($statusCounts) ?>,
    'Attendance Status'
);

createChart(
    'hourlyChart',
    'bar',
    <?= json_encode($hourLabels) ?>,
    <?= json_encode($hourCounts) ?>,
    'Scans per Hour'
);

createChart(
    'myScanChart',
    'bar',
    <?= json_encode($weekLabels) ?>,
    <?= json_encode($myWeekCounts) ?>,
    'Your Scans'
);
</script>

</body>
</html>
