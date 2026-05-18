<?php
session_start();
require_once '../db.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../../index.html");
    exit();
}

$user_id = (int)$_SESSION['user_id'];

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function tableExists(PDO $pdo, string $table): bool {
    try {
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        return (bool)$stmt->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE ?");
        $stmt->execute([$column]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

if (!tableExists($pdo, 'attendance')) {
    $pdo->exec("
        CREATE TABLE attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME NULL,
            status VARCHAR(30) DEFAULT 'present',
            remarks TEXT NULL,
            scanned_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_date (student_id, attendance_date)
        )
    ");
}

if (!columnExists($pdo, 'attendance', 'attendance_date')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN attendance_date DATE NULL AFTER student_id");
}

if (!columnExists($pdo, 'attendance', 'time_in')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN time_in TIME NULL AFTER attendance_date");
}

if (!columnExists($pdo, 'attendance', 'status')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN status VARCHAR(30) DEFAULT 'present' AFTER time_in");
}

if (!columnExists($pdo, 'attendance', 'remarks')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN remarks TEXT NULL AFTER status");
}

if (!columnExists($pdo, 'attendance', 'created_at')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND LOWER(role) = 'parent' LIMIT 1");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../../index.html");
    exit();
}

$parentNameRaw = trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? ''));
$parentNameRaw = $parentNameRaw !== '' ? $parentNameRaw : 'Parent';
$fullName = e($parentNameRaw);

$avatarUrl = !empty($user['profile_pic'])
    ? '../../uploads/profiles/' . e($user['profile_pic'])
    : "https://ui-avatars.com/api/?name=" . urlencode($parentNameRaw) . "&background=0056B3&color=fff";

$childrenStmt = $pdo->prepare("
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.id_num,
        u.grade_level,
        u.grade,
        u.section,
        u.profile_pic,
        u.profile_photo
    FROM parent_student_links psl
    INNER JOIN users u ON u.id = psl.student_id
    WHERE psl.parent_id = ?
      AND LOWER(u.role) = 'student'
      AND (psl.status IS NULL OR LOWER(psl.status) = 'active')
    ORDER BY u.last_name ASC, u.first_name ASC
");
$childrenStmt->execute([$user_id]);
$children = $childrenStmt->fetchAll(PDO::FETCH_ASSOC);

$selected_student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : (int)($children[0]['id'] ?? 0);

$child = null;
foreach ($children as $c) {
    if ((int)$c['id'] === $selected_student_id) {
        $child = $c;
        break;
    }
}

if (!$child && !empty($children)) {
    $child = $children[0];
    $selected_student_id = (int)$child['id'];
}

$currentMonth = date('Y-m');
$selectedMonth = isset($_GET['month']) && preg_match('/^\d{4}-\d{2}$/', $_GET['month'])
    ? $_GET['month']
    : $currentMonth;

$monthStart = $selectedMonth . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));
$selectedMonthLabel = date('F Y', strtotime($monthStart));

$totalDays = 0;
$presentDays = 0;
$absentDays = 0;
$lateDays = 0;
$excusedDays = 0;
$attendanceRate = "0%";
$attendanceRecords = [];
$availableMonths = [];
$chartLabels = [];
$presentChartData = [];
$lateChartData = [];
$absentChartData = [];
$excusedChartData = [];

if ($child) {
    $student_id = (int)$child['id'];

    $monthsStmt = $pdo->prepare("
        SELECT DISTINCT DATE_FORMAT(attendance_date, '%Y-%m') AS month_value
        FROM attendance
        WHERE student_id = ?
          AND attendance_date IS NOT NULL
        ORDER BY month_value DESC
    ");
    $monthsStmt->execute([$student_id]);
    $availableMonths = $monthsStmt->fetchAll(PDO::FETCH_COLUMN);

    if (!in_array($currentMonth, $availableMonths, true)) {
        array_unshift($availableMonths, $currentMonth);
    }

    if (!in_array($selectedMonth, $availableMonths, true)) {
        $selectedMonth = $availableMonths[0] ?? $currentMonth;
        $monthStart = $selectedMonth . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart));
        $selectedMonthLabel = date('F Y', strtotime($monthStart));
    }

    $statStmt = $pdo->prepare("
        SELECT 
            COUNT(*) AS total_days,
            COALESCE(SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END), 0) AS present_days,
            COALESCE(SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END), 0) AS absent_days,
            COALESCE(SUM(CASE WHEN LOWER(status) = 'late' THEN 1 ELSE 0 END), 0) AS late_days,
            COALESCE(SUM(CASE WHEN LOWER(status) = 'excused' THEN 1 ELSE 0 END), 0) AS excused_days
        FROM attendance
        WHERE student_id = ?
          AND attendance_date BETWEEN ? AND ?
    ");
    $statStmt->execute([$student_id, $monthStart, $monthEnd]);
    $stats = $statStmt->fetch(PDO::FETCH_ASSOC);

    if ($stats) {
        $totalDays = (int)($stats['total_days'] ?? 0);
        $presentDays = (int)($stats['present_days'] ?? 0);
        $absentDays = (int)($stats['absent_days'] ?? 0);
        $lateDays = (int)($stats['late_days'] ?? 0);
        $excusedDays = (int)($stats['excused_days'] ?? 0);

        if ($totalDays > 0) {
            $attended = $presentDays + $lateDays + $excusedDays;
            $attendanceRate = round(($attended / $totalDays) * 100) . "%";
        }
    }

    $recStmt = $pdo->prepare("
        SELECT *
        FROM attendance
        WHERE student_id = ?
          AND attendance_date BETWEEN ? AND ?
        ORDER BY attendance_date DESC, created_at DESC
    ");
    $recStmt->execute([$student_id, $monthStart, $monthEnd]);
    $attendanceRecords = $recStmt->fetchAll(PDO::FETCH_ASSOC);

    $chartStmt = $pdo->prepare("
        SELECT 
            attendance_date,
            SUM(CASE WHEN LOWER(status) = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN LOWER(status) = 'late' THEN 1 ELSE 0 END) AS late_count,
            SUM(CASE WHEN LOWER(status) = 'absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN LOWER(status) = 'excused' THEN 1 ELSE 0 END) AS excused_count
        FROM attendance
        WHERE student_id = ?
          AND attendance_date BETWEEN ? AND ?
        GROUP BY attendance_date
        ORDER BY attendance_date ASC
    ");
    $chartStmt->execute([$student_id, $monthStart, $monthEnd]);
    $dailyRows = $chartStmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($dailyRows as $row) {
        $chartLabels[] = date('M j', strtotime($row['attendance_date']));
        $presentChartData[] = (int)$row['present_count'];
        $lateChartData[] = (int)$row['late_count'];
        $absentChartData[] = (int)$row['absent_count'];
        $excusedChartData[] = (int)$row['excused_count'];
    }
}

$childNameRaw = $child ? trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')) : 'No Child Linked';
$childName = e($childNameRaw);
$gradeText = $child ? (($child['grade_level'] ?? $child['grade'] ?? 'N/A')) : 'N/A';
$sectionText = $child ? ($child['section'] ?? 'N/A') : 'N/A';
$childInfo = $child ? e("Grade " . $gradeText . " - " . $sectionText) : "N/A";
$childIdNum = $child ? e($child['id_num'] ?? 'N/A') : 'N/A';
$printedDate = date('F j, Y h:i A');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Attendance | Pitogo EduTrack Parent Portal</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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

.logo-box i {
    color:var(--primary-blue);
}

.brand-text,
.nav-label,
.nav-item span {
    opacity:0;
    visibility:hidden;
    transition:.25s ease;
}

.sidebar:hover .brand-text,
.sidebar:hover .nav-label,
.sidebar:hover .nav-item span {
    opacity:1;
    visibility:visible;
}

.brand-text h2 {
    font-size:1.08rem;
    font-weight:900;
    color:var(--primary-navy);
    line-height:1.1;
}

.brand-text p {
    font-size:.72rem;
    color:var(--primary-blue);
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:1px;
}

.nav-menu {
    padding:24px 15px;
    display:flex;
    flex-direction:column;
    gap:6px;
    overflow-y:auto;
    flex:1;
}

.nav-label {
    font-size:.72rem;
    color:var(--text-muted);
    font-weight:900;
    text-transform:uppercase;
    letter-spacing:1px;
    margin:14px 0 8px 15px;
}

.nav-item {
    padding:14px 10px;
    display:flex;
    align-items:center;
    gap:20px;
    border-radius:14px;
    color:var(--text-muted);
    text-decoration:none;
    font-weight:800;
    font-size:.92rem;
    transition:var(--transition);
}

.nav-item i {
    min-width:28px;
    text-align:center;
    font-size:1.1rem;
}

.nav-item:hover {
    background:var(--bg-main);
    color:var(--primary-blue);
    transform:translateX(5px);
}

.nav-item.active {
    background:rgba(0,86,179,0.09);
    color:var(--primary-blue);
}

.logout-link {
    color:var(--danger) !important;
    margin-top:20px;
}

.main-wrapper {
    margin-left:82px;
    width:calc(100% - 82px);
}

.top-header {
    height:88px;
    background:#fff;
    border-bottom:1px solid var(--border-color);
    padding:0 38px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:20px;
    position:sticky;
    top:0;
    z-index:90;
}

.header-title h1 {
    color:var(--primary-navy);
    font-size:1.35rem;
    font-weight:900;
}

.header-title p {
    color:var(--text-muted);
    margin-top:4px;
    font-size:.88rem;
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
    font-size:.92rem;
    color:var(--primary-navy);
    font-weight:900;
}

.user-info p {
    font-size:.76rem;
    color:var(--text-muted);
}

.avatar {
    width:46px;
    height:46px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid var(--primary-blue);
    padding:2px;
}

.content-area {
    padding:34px 38px 48px;
    max-width:1280px;
    margin:0 auto;
}

.hero-card {
    background:linear-gradient(135deg,var(--primary-navy),var(--primary-blue));
    color:#fff;
    border-radius:24px;
    padding:32px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:22px;
    box-shadow:var(--shadow-md);
    margin-bottom:22px;
    overflow:hidden;
    position:relative;
}

.hero-card:after {
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:50%;
    background:rgba(255,255,255,.08);
    right:-70px;
    top:-90px;
}

.hero-card > * {
    position:relative;
    z-index:1;
}

.hero-card h1 {
    font-size:2rem;
    font-weight:900;
    margin-bottom:8px;
}

.hero-card p {
    max-width:760px;
    color:rgba(255,255,255,.86);
    line-height:1.65;
}

.btn-main,
.btn-white,
.btn-dark {
    border:none;
    border-radius:14px;
    padding:12px 16px;
    font-family:'Montserrat',sans-serif;
    font-weight:900;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    text-decoration:none;
    transition:var(--transition);
    white-space:nowrap;
}

.btn-main {
    background:var(--primary-blue);
    color:white;
}

.btn-white {
    background:#fff;
    color:var(--primary-blue);
}

.btn-dark {
    background:var(--primary-navy);
    color:#fff;
}

.btn-main:hover,
.btn-white:hover,
.btn-dark:hover {
    transform:translateY(-2px);
    box-shadow:var(--shadow-md);
}

.filters-card {
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:20px;
    box-shadow:var(--shadow-sm);
    padding:18px;
    display:flex;
    align-items:end;
    justify-content:space-between;
    gap:16px;
    flex-wrap:wrap;
    margin-bottom:22px;
}

.filter-grid {
    display:grid;
    grid-template-columns:repeat(2,minmax(220px,1fr));
    gap:14px;
    flex:1;
}

.form-group label {
    display:block;
    font-size:.74rem;
    font-weight:900;
    color:var(--primary-navy);
    text-transform:uppercase;
    letter-spacing:.6px;
    margin-bottom:7px;
}

.select-control,
.search-control {
    width:100%;
    padding:12px 14px;
    border:1px solid var(--border-color);
    background:#F8FAFC;
    border-radius:13px;
    color:var(--primary-navy);
    outline:none;
    font-family:'Inter',sans-serif;
    font-weight:800;
}

.select-control:focus,
.search-control:focus {
    background:#fff;
    border-color:var(--primary-blue);
    box-shadow:0 0 0 4px rgba(0,86,179,.08);
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:22px;
}

.stat-card {
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:20px;
    padding:21px;
    display:flex;
    align-items:center;
    gap:15px;
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
    border-radius:17px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.32rem;
    flex-shrink:0;
}

.stat-details h3 {
    color:var(--text-muted);
    font-size:.74rem;
    text-transform:uppercase;
    font-weight:900;
    letter-spacing:.5px;
    margin-bottom:6px;
}

.stat-details .value {
    font-size:1.72rem;
    font-weight:900;
    line-height:1;
}

.grid-2 {
    display:grid;
    grid-template-columns:1.1fr .9fr;
    gap:22px;
    margin-bottom:22px;
}

.panel,
.section-card {
    background:#fff;
    border:1px solid var(--border-color);
    border-radius:22px;
    box-shadow:var(--shadow-sm);
    overflow:hidden;
}

.panel-header,
.section-header {
    padding:20px 22px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    border-bottom:1px solid var(--border-color);
    flex-wrap:wrap;
}

.panel-header h2,
.section-header h2 {
    color:var(--primary-navy);
    font-size:1.12rem;
    font-weight:900;
}

.panel-body {
    padding:22px;
}

.chart-box {
    height:300px;
}

.summary-list {
    display:grid;
    gap:12px;
}

.summary-item {
    border:1px solid var(--border-color);
    background:#F8FAFC;
    border-radius:16px;
    padding:15px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
}

.summary-item h4 {
    color:var(--primary-navy);
    font-size:.9rem;
    margin-bottom:4px;
}

.summary-item p {
    color:var(--text-muted);
    font-size:.86rem;
}

.section-subtitle {
    color:var(--text-muted);
    font-size:.9rem;
    margin-top:4px;
}

.table-wrap {
    overflow-x:auto;
}

table {
    width:100%;
    min-width:860px;
    border-collapse:collapse;
}

th {
    text-align:left;
    padding:15px;
    background:#F8FAFC;
    color:var(--text-muted);
    font-size:.76rem;
    font-weight:900;
    text-transform:uppercase;
    border-bottom:2px solid var(--border-color);
}

td {
    padding:15px;
    border-bottom:1px solid var(--border-color);
    color:var(--text-dark);
    font-weight:600;
    font-size:.92rem;
}

tr:hover td {
    background:#F8FAFC;
}

.status-badge {
    padding:7px 13px;
    border-radius:999px;
    font-size:.77rem;
    font-weight:900;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    min-width:92px;
}

.status-present {
    background:rgba(16,185,129,.1);
    color:var(--success);
}

.status-absent {
    background:rgba(239,68,68,.1);
    color:var(--danger);
}

.status-late {
    background:rgba(245,158,11,.12);
    color:var(--warning);
}

.status-excused {
    background:rgba(0,86,179,.1);
    color:var(--primary-blue);
}

.remarks-text {
    color:var(--text-muted);
    font-size:.86rem;
    font-style:italic;
}

.empty-state {
    text-align:center;
    padding:52px 20px;
    color:var(--text-muted);
}

.empty-state i {
    font-size:3rem;
    opacity:.45;
    margin-bottom:15px;
}

.print-report {
    display:none;
}

@media(max-width:1100px) {
    .stats-grid {
        grid-template-columns:repeat(2,1fr);
    }

    .grid-2 {
        grid-template-columns:1fr;
    }
}

@media(max-width:768px) {
    .sidebar {
        display:none;
    }

    .main-wrapper {
        margin-left:0;
        width:100%;
    }

    .top-header {
        height:auto;
        padding:18px 20px;
        align-items:flex-start;
        flex-direction:column;
    }

    .user-profile {
        display:none;
    }

    .content-area {
        padding:24px 18px;
    }

    .hero-card {
        padding:26px;
        align-items:flex-start;
        flex-direction:column;
    }

    .hero-card h1 {
        font-size:1.55rem;
    }

    .filter-grid,
    .stats-grid {
        grid-template-columns:1fr;
    }
}

@media print {
    body {
        background:white !important;
        display:block !important;
        color:#111827 !important;
        font-size:12px;
    }

    .sidebar,
    .top-header,
    .hero-card,
    .filters-card,
    .stats-grid,
    .grid-2,
    .no-print,
    .search-control {
        display:none !important;
    }

    .main-wrapper {
        margin-left:0 !important;
        width:100% !important;
    }

    .content-area {
        padding:0 !important;
        max-width:none !important;
        width:100% !important;
    }

    .print-report {
        display:block !important;
        padding:18px 12px 8px;
        border-bottom:2px solid #111827;
        margin-bottom:18px;
    }

    .print-report-top {
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:15px;
    }

    .print-school {
        display:flex;
        align-items:center;
        gap:12px;
    }

    .print-logo {
        width:58px;
        height:58px;
        object-fit:contain;
    }

    .print-report h1 {
        font-size:18px;
        margin:0 0 3px;
        color:#111827;
    }

    .print-report p {
        margin:2px 0;
        color:#374151;
        font-size:11px;
    }

    .print-title {
        text-align:right;
    }

    .print-title h2 {
        font-size:16px;
        margin-bottom:4px;
        color:#111827;
    }

    .print-meta {
        margin-top:14px;
        display:grid;
        grid-template-columns:repeat(4,1fr);
        gap:8px;
    }

    .print-meta div {
        border:1px solid #D1D5DB;
        padding:8px;
        border-radius:6px;
    }

    .print-meta strong {
        display:block;
        font-size:9px;
        color:#6B7280;
        text-transform:uppercase;
        margin-bottom:3px;
    }

    .section-card {
        border:none !important;
        box-shadow:none !important;
        border-radius:0 !important;
    }

    .section-header {
        display:block !important;
        padding:0 0 10px !important;
        border-bottom:none !important;
    }

    .section-header h2 {
        font-size:14px !important;
        color:#111827 !important;
    }

    .section-subtitle {
        font-size:11px !important;
        color:#374151 !important;
    }

    table {
        min-width:0 !important;
        font-size:11px;
    }

    th {
        background:#E5E7EB !important;
        color:#111827 !important;
        border:1px solid #9CA3AF !important;
        padding:8px !important;
        font-size:10px !important;
    }

    td {
        border:1px solid #D1D5DB !important;
        padding:8px !important;
        font-size:10.5px !important;
    }

    tr:hover td {
        background:white !important;
    }

    .status-badge {
        border:1px solid #9CA3AF !important;
        color:#111827 !important;
        background:white !important;
        padding:3px 6px !important;
        min-width:0 !important;
        font-size:10px !important;
    }

    .status-badge i {
        display:none !important;
    }

    .empty-state {
        border:1px solid #D1D5DB;
        padding:25px;
    }

    @page {
        size:A4 portrait;
        margin:12mm;
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
            <p>Parent Portal</p>
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
            <h1>Child Attendance</h1>
            <p>Monthly attendance preview • Real-time parent monitoring</p>
        </div>

        <div class="user-profile">
            <div class="user-info">
                <h4><?= $fullName ?></h4>
                <p>Parent Account</p>
            </div>

            <img src="<?= $avatarUrl ?>" alt="Profile" class="avatar">
        </div>
    </header>

    <div class="content-area">
        <?php if (!$child): ?>
            <div class="empty-state" style="border:1px dashed var(--border-color);border-radius:20px;background:white;">
                <i class="fa fa-user-slash"></i>
                <h2 style="color:var(--primary-navy);margin-bottom:10px;">No Child Linked Yet</h2>
                <p style="margin-bottom:20px;">You need to link your child's account first from the Parent Dashboard.</p>
                <a href="dashboard.php" class="btn-main">
                    <i class="fa fa-link"></i>
                    Go to Dashboard
                </a>
            </div>
        <?php else: ?>

            <section class="hero-card">
                <div>
                    <h1><?= $childName ?>’s Attendance</h1>
                    <p>
                        Preview attendance by month, switch between linked children, review previous months,
                        and print a clean official-style attendance report.
                    </p>
                </div>

                <button type="button" class="btn-white" onclick="window.print()">
                    <i class="fa fa-print"></i>
                    Print Report
                </button>
            </section>

            <form method="GET" class="filters-card no-print">
                <div class="filter-grid">
                    <div class="form-group">
                        <label>Select Child</label>
                        <select name="student_id" class="select-control" onchange="this.form.submit()">
                            <?php foreach ($children as $c): ?>
                                <option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$selected_student_id ? 'selected' : '' ?>>
                                    <?= e(trim(($c['first_name'] ?? '') . ' ' . ($c['last_name'] ?? ''))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label>Attendance Month</label>
                        <select name="month" class="select-control" onchange="this.form.submit()">
                            <?php foreach ($availableMonths as $monthValue): ?>
                                <option value="<?= e($monthValue) ?>" <?= $monthValue === $selectedMonth ? 'selected' : '' ?>>
                                    <?= e(date('F Y', strtotime($monthValue . '-01'))) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <button type="button" class="btn-dark" onclick="window.print()">
                    <i class="fa fa-print"></i>
                    Print Attendance
                </button>
            </form>

            <div class="print-report">
                <div class="print-report-top">
                    <div class="print-school">
                        <img class="print-logo" src="../school-logo.jpg" alt="School Logo">
                        <div>
                            <h1>Pitogo EduTrack</h1>
                            <p>Pitogo High School</p>
                            <p>Parent Portal Attendance Monitoring Report</p>
                        </div>
                    </div>

                    <div class="print-title">
                        <h2>Attendance Report</h2>
                        <p>Month: <strong><?= e($selectedMonthLabel) ?></strong></p>
                        <p>Generated: <?= e($printedDate) ?></p>
                    </div>
                </div>

                <div class="print-meta">
                    <div>
                        <strong>Student Name</strong>
                        <?= $childName ?>
                    </div>
                    <div>
                        <strong>LRN / ID Number</strong>
                        <?= $childIdNum ?>
                    </div>
                    <div>
                        <strong>Grade & Section</strong>
                        <?= $childInfo ?>
                    </div>
                    <div>
                        <strong>Attendance Rate</strong>
                        <?= e($attendanceRate) ?>
                    </div>
                </div>
            </div>

            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(0,86,179,0.1);color:var(--primary-blue);">
                        <i class="fa fa-chart-line"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Monthly Rate</h3>
                        <div class="value" style="color:var(--primary-blue);"><?= e($attendanceRate) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(16,185,129,0.1);color:var(--success);">
                        <i class="fa fa-user-check"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Present</h3>
                        <div class="value" style="color:var(--success);"><?= number_format($presentDays) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(239,68,68,0.1);color:var(--danger);">
                        <i class="fa fa-user-times"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Absent</h3>
                        <div class="value" style="color:var(--danger);"><?= number_format($absentDays) ?></div>
                    </div>
                </div>

                <div class="stat-card">
                    <div class="stat-icon" style="background:rgba(245,158,11,0.12);color:var(--warning);">
                        <i class="fa fa-user-clock"></i>
                    </div>
                    <div class="stat-details">
                        <h3>Late</h3>
                        <div class="value" style="color:var(--warning);"><?= number_format($lateDays) ?></div>
                    </div>
                </div>
            </div>

            <div class="grid-2 no-print">
                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fa fa-chart-column" style="color:var(--primary-blue);margin-right:8px;"></i>Attendance Graph</h2>
                        <span style="font-weight:900;color:var(--text-muted);font-size:.85rem;"><?= e($selectedMonthLabel) ?></span>
                    </div>
                    <div class="panel-body">
                        <?php if (empty($chartLabels)): ?>
                            <div class="empty-state" style="padding:35px 15px;">
                                <i class="fa fa-chart-simple"></i>
                                <p>No graph data available for this month.</p>
                            </div>
                        <?php else: ?>
                            <div class="chart-box">
                                <canvas id="attendanceChart"></canvas>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2><i class="fa fa-circle-info" style="color:var(--primary-blue);margin-right:8px;"></i>Report Summary</h2>
                    </div>
                    <div class="panel-body">
                        <div class="summary-list">
                            <div class="summary-item">
                                <div>
                                    <h4>Student</h4>
                                    <p><?= $childName ?> • <?= $childInfo ?></p>
                                </div>
                                <i class="fa fa-user-graduate" style="color:var(--primary-blue);"></i>
                            </div>

                            <div class="summary-item">
                                <div>
                                    <h4>Preview Month</h4>
                                    <p><?= e($selectedMonthLabel) ?> attendance history</p>
                                </div>
                                <i class="fa fa-calendar-days" style="color:var(--primary-blue);"></i>
                            </div>

                            <div class="summary-item">
                                <div>
                                    <h4>Total Records</h4>
                                    <p><?= number_format($totalDays) ?> attendance record(s) found</p>
                                </div>
                                <i class="fa fa-list-check" style="color:var(--primary-blue);"></i>
                            </div>

                            <div class="summary-item">
                                <div>
                                    <h4>Excused</h4>
                                    <p><?= number_format($excusedDays) ?> excused record(s)</p>
                                </div>
                                <i class="fa fa-envelope-open-text" style="color:var(--primary-blue);"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="section-card">
                <div class="section-header">
                    <div>
                        <h2><i class="fa fa-list-alt" style="color:var(--primary-blue);margin-right:8px;"></i>Attendance History Preview</h2>
                        <p class="section-subtitle">
                            Showing records for <strong><?= e($selectedMonthLabel) ?></strong>. Use the month dropdown above to view previous months.
                        </p>
                    </div>

                    <div class="no-print" style="display:flex;gap:10px;align-items:center;flex-wrap:wrap;">
                        <input type="text" id="searchInput" class="search-control" placeholder="Search records..." style="width:230px;">
                        <button type="button" class="btn-main" onclick="window.print()">
                            <i class="fa fa-print"></i>
                            Print
                        </button>
                    </div>
                </div>

                <?php if (empty($attendanceRecords)): ?>
                    <div class="empty-state">
                        <i class="fa fa-folder-open"></i>
                        <h3 style="color:var(--primary-navy);margin-bottom:8px;">No Attendance Records</h3>
                        <p>No attendance records found for <?= e($selectedMonthLabel) ?>.</p>
                    </div>
                <?php else: ?>
                    <div class="table-wrap">
                        <table id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Day</th>
                                    <th>Time Logged</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <?php
                                    $dateValue = $record['attendance_date'] ?? null;
                                    $timeValue = $record['time_in'] ?? $record['created_at'] ?? null;
                                    $status = strtolower($record['status'] ?? 'absent');

                                    $dateText = $dateValue ? date('F j, Y', strtotime($dateValue)) : 'N/A';
                                    $dayText = $dateValue ? date('l', strtotime($dateValue)) : 'N/A';
                                    $timeText = $timeValue ? date('h:i A', strtotime($timeValue)) : 'N/A';

                                    $icon = 'fa-times';
                                    if ($status === 'present') $icon = 'fa-check';
                                    if ($status === 'late') $icon = 'fa-clock';
                                    if ($status === 'excused') $icon = 'fa-envelope-open-text';

                                    $safeStatusClass = in_array($status, ['present', 'absent', 'late', 'excused'], true) ? $status : 'absent';
                                    ?>
                                    <tr>
                                        <td><strong><?= e($dateText) ?></strong></td>
                                        <td><?= e($dayText) ?></td>
                                        <td><?= e($timeText) ?></td>
                                        <td>
                                            <span class="status-badge status-<?= e($safeStatusClass) ?>">
                                                <i class="fa <?= e($icon) ?>"></i>
                                                <?= e(ucfirst($status)) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (!empty($record['remarks'])): ?>
                                                <span class="remarks-text"><?= e($record['remarks']) ?></span>
                                            <?php else: ?>
                                                <span style="color:#CBD5E1;">No remarks</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </div>
</main>

<script>
const searchInput = document.getElementById('searchInput');
const table = document.getElementById('attendanceTable');

if (searchInput && table) {
    searchInput.addEventListener('input', function () {
        const query = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');

        rows.forEach(row => {
            row.style.display = row.innerText.toLowerCase().includes(query) ? '' : 'none';
        });
    });
}

<?php if (!empty($chartLabels)): ?>
const ctx = document.getElementById('attendanceChart');
if (ctx) {
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Present',
                    data: <?= json_encode($presentChartData) ?>,
                    borderWidth: 2,
                    borderRadius: 8,
                    backgroundColor: 'rgba(16, 185, 129, 0.18)',
                    borderColor: '#10B981'
                },
                {
                    label: 'Late',
                    data: <?= json_encode($lateChartData) ?>,
                    borderWidth: 2,
                    borderRadius: 8,
                    backgroundColor: 'rgba(245, 158, 11, 0.18)',
                    borderColor: '#F59E0B'
                },
                {
                    label: 'Absent',
                    data: <?= json_encode($absentChartData) ?>,
                    borderWidth: 2,
                    borderRadius: 8,
                    backgroundColor: 'rgba(239, 68, 68, 0.16)',
                    borderColor: '#EF4444'
                },
                {
                    label: 'Excused',
                    data: <?= json_encode($excusedChartData) ?>,
                    borderWidth: 2,
                    borderRadius: 8,
                    backgroundColor: 'rgba(0, 86, 179, 0.14)',
                    borderColor: '#0056B3'
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        boxWidth: 8,
                        font: { weight: 'bold' }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0 }
                },
                x: {
                    grid: { display: false }
                }
            }
        }
    });
}
<?php endif; ?>
</script>

</body>
</html>
