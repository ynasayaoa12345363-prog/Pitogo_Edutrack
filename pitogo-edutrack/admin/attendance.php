<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Admin Attendance
|--------------------------------------------------------------------------
| Save as:
| admin/attendance.php
|
| Regular admin and superadmin can view attendance.
| Filters:
| - Grade
| - Section
| - Status
| - Single date
| - Month
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

function fullName(array $row): string {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['middle_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

function profilePath(array $row, string $name): string {
    $profile = $row['profile_picture'] ?? $row['profile_pic'] ?? $row['profile_photo'] ?? '';

    if ($profile) {
        if (preg_match('/^https?:\/\//i', $profile)) {
            return $profile;
        }

        return '../uploads/profiles/' . ltrim($profile, '/');
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'User') . '&background=0056B3&color=fff';
}

/*
|--------------------------------------------------------------------------
| Current admin info
|--------------------------------------------------------------------------
*/

$stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtAdmin->execute([$currentUserId]);
$currentUser = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];

$adminName = fullName($currentUser) ?: 'System Admin';
$adminAvatar = profilePath($currentUser, $adminName);

/*
|--------------------------------------------------------------------------
| Attendance table safety
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

if (!columnExists($pdo, 'attendance', 'scanned_by')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN scanned_by INT NULL AFTER remarks");
}

if (!columnExists($pdo, 'attendance', 'created_at')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$gradeFilter = trim($_GET['grade'] ?? 'all');
$sectionFilter = trim($_GET['section'] ?? 'all');
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$dateFilter = trim($_GET['date'] ?? '');
$monthFilter = trim($_GET['month'] ?? date('Y-m'));
$search = trim($_GET['search'] ?? '');

$validStatus = ['all', 'present', 'absent', 'late', 'excused'];
if (!in_array($statusFilter, $validStatus, true)) {
    $statusFilter = 'all';
}

if (!preg_match('/^\d{4}-\d{2}$/', $monthFilter)) {
    $monthFilter = date('Y-m');
}

$monthStart = $monthFilter . '-01';
$monthEnd = date('Y-m-t', strtotime($monthStart));

$gradeColumn = columnExists($pdo, 'users', 'grade_level') ? 'grade_level' : (columnExists($pdo, 'users', 'grade') ? 'grade' : null);

$grades = [];
if ($gradeColumn) {
    $grades = safeFetchAll($pdo, "
        SELECT DISTINCT `$gradeColumn` AS grade_value
        FROM users
        WHERE LOWER(role) = 'student'
          AND `$gradeColumn` IS NOT NULL
          AND `$gradeColumn` != ''
        ORDER BY `$gradeColumn` ASC
    ");
}

$sections = safeFetchAll($pdo, "
    SELECT DISTINCT section
    FROM users
    WHERE LOWER(role) = 'student'
      AND section IS NOT NULL
      AND section != ''
    ORDER BY section ASC
");

/*
|--------------------------------------------------------------------------
| Query builder
|--------------------------------------------------------------------------
*/

$where = ["LOWER(s.role) = 'student'"];
$params = [];

if ($dateFilter !== '') {
    $where[] = "a.attendance_date = ?";
    $params[] = $dateFilter;
} else {
    $where[] = "a.attendance_date BETWEEN ? AND ?";
    $params[] = $monthStart;
    $params[] = $monthEnd;
}

if ($gradeColumn && $gradeFilter !== 'all') {
    $where[] = "s.`$gradeColumn` = ?";
    $params[] = $gradeFilter;
}

if ($sectionFilter !== 'all') {
    $where[] = "s.section = ?";
    $params[] = $sectionFilter;
}

if ($statusFilter !== 'all') {
    $where[] = "LOWER(a.status) = ?";
    $params[] = $statusFilter;
}

if ($search !== '') {
    $where[] = "(
        s.first_name LIKE ?
        OR s.middle_name LIKE ?
        OR s.last_name LIKE ?
        OR s.email LIKE ?
        OR s.id_num LIKE ?
    )";
    $sp = "%{$search}%";
    array_push($params, $sp, $sp, $sp, $sp, $sp);
}

$whereSql = implode(' AND ', $where);

$records = safeFetchAll($pdo, "
    SELECT
        a.*,
        s.first_name,
        s.middle_name,
        s.last_name,
        s.email,
        s.id_num,
        " . ($gradeColumn ? "s.`$gradeColumn` AS grade_value," : "NULL AS grade_value,") . "
        s.section,
        st.first_name AS staff_first,
        st.last_name AS staff_last
    FROM attendance a
    INNER JOIN users s ON s.id = a.student_id
    LEFT JOIN users st ON st.id = a.scanned_by
    WHERE $whereSql
    ORDER BY a.attendance_date DESC, a.time_in DESC, a.created_at DESC
", $params);

$summaryParams = $params;

$totalRecords = count($records);
$totalPresent = 0;
$totalAbsent = 0;
$totalLate = 0;
$totalExcused = 0;

foreach ($records as $r) {
    $status = strtolower($r['status'] ?? '');
    if ($status === 'present') $totalPresent++;
    elseif ($status === 'absent') $totalAbsent++;
    elseif ($status === 'late') $totalLate++;
    elseif ($status === 'excused') $totalExcused++;
}

$attendanceRate = $totalRecords > 0 ? round((($totalPresent + $totalLate + $totalExcused) / $totalRecords) * 100) : 0;

$chartRows = safeFetchAll($pdo, "
    SELECT
        a.attendance_date,
        SUM(CASE WHEN LOWER(a.status) = 'present' THEN 1 ELSE 0 END) AS present_count,
        SUM(CASE WHEN LOWER(a.status) = 'late' THEN 1 ELSE 0 END) AS late_count,
        SUM(CASE WHEN LOWER(a.status) = 'absent' THEN 1 ELSE 0 END) AS absent_count,
        SUM(CASE WHEN LOWER(a.status) = 'excused' THEN 1 ELSE 0 END) AS excused_count
    FROM attendance a
    INNER JOIN users s ON s.id = a.student_id
    WHERE $whereSql
    GROUP BY a.attendance_date
    ORDER BY a.attendance_date ASC
", $summaryParams);

$chartLabels = [];
$presentData = [];
$lateData = [];
$absentData = [];
$excusedData = [];

foreach ($chartRows as $row) {
    $chartLabels[] = date('M d', strtotime($row['attendance_date']));
    $presentData[] = (int)$row['present_count'];
    $lateData[] = (int)$row['late_count'];
    $absentData[] = (int)$row['absent_count'];
    $excusedData[] = (int)$row['excused_count'];
}

$reportDateText = $dateFilter !== '' ? date('F d, Y', strtotime($dateFilter)) : date('F Y', strtotime($monthStart));

if (isset($_GET['export']) && $_GET['export'] === 'true') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=Pitogo_EduTrack_Attendance_Report_' . date('Y-m-d') . '.csv');

    $out = fopen('php://output', 'w');

    fputcsv($out, ['Pitogo EduTrack Attendance Report']);
    fputcsv($out, ['Generated By', $adminName]);
    fputcsv($out, ['Generated On', date('Y-m-d h:i A')]);
    fputcsv($out, ['Covered Period', $reportDateText]);
    fputcsv($out, []);

    fputcsv($out, ['No.', 'LRN / ID', 'Student Name', 'Grade', 'Section', 'Date', 'Time In', 'Status', 'Scanned By', 'Remarks']);

    $n = 1;
    foreach ($records as $r) {
        $studentName = fullName($r);
        $scannedBy = trim(($r['staff_first'] ?? '') . ' ' . ($r['staff_last'] ?? ''));
        fputcsv($out, [
            $n++,
            $r['id_num'] ?? '',
            $studentName,
            $r['grade_value'] ?? '',
            $r['section'] ?? '',
            !empty($r['attendance_date']) ? date('Y-m-d', strtotime($r['attendance_date'])) : '',
            !empty($r['time_in']) ? date('h:i A', strtotime($r['time_in'])) : '',
            ucfirst($r['status'] ?? ''),
            $scannedBy ?: 'System',
            $r['remarks'] ?? ''
        ]);
    }

    fclose($out);
    exit();
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Attendance | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    margin-bottom:24px;
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
}

.hero-actions {
    display:flex;
    gap:10px;
    flex-wrap:wrap;
}

.btn-primary,
.btn-outline,
.btn-filter,
.btn-export,
.btn-print {
    padding:12px 18px;
    border-radius:12px;
    border:none;
    font-weight:800;
    cursor:pointer;
    text-decoration:none;
    display:inline-flex;
    gap:8px;
    align-items:center;
    font-family:'Montserrat',sans-serif;
    font-size:.9rem;
}

.btn-primary {
    background:white;
    color:var(--primary-blue);
}

.btn-outline {
    background:rgba(255,255,255,.14);
    color:white;
    border:1px solid rgba(255,255,255,.25);
}

.btn-filter {
    background:var(--primary-blue);
    color:white;
}

.btn-export {
    background:white;
    color:var(--text-dark);
    border:1px solid var(--border-color);
}

.btn-print {
    background:var(--primary-navy);
    color:white;
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4,minmax(160px,1fr));
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

.report-paper {
    background:white;
    border-radius:20px;
    border:1px solid var(--border-color);
    box-shadow:var(--shadow-sm);
    padding:28px;
    margin-bottom:25px;
}

.report-header {
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:20px;
    border-bottom:2px solid var(--border-color);
    padding-bottom:20px;
    margin-bottom:22px;
}

.report-brand {
    display:flex;
    align-items:center;
    gap:16px;
}

.report-brand img {
    width:64px;
    height:64px;
    object-fit:contain;
    border:1px solid var(--border-color);
    border-radius:14px;
    padding:5px;
}

.report-brand h2 {
    color:var(--primary-navy);
    font-size:1.25rem;
    font-weight:800;
}

.report-brand p,
.report-meta p {
    color:var(--text-muted);
    font-size:.88rem;
    margin-top:4px;
}

.report-meta {
    text-align:right;
}

.chart-card,
.table-container {
    background:white;
    border-radius:18px;
    border:1px solid var(--border-color);
    box-shadow:var(--shadow-sm);
    overflow:hidden;
    margin-bottom:24px;
}

.chart-header,
.table-header {
    padding:20px 24px;
    border-bottom:1px solid var(--border-color);
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:14px;
    flex-wrap:wrap;
}

.chart-header h3,
.table-header h3 {
    color:var(--primary-navy);
    font-size:1.1rem;
}

.chart-body {
    padding:24px;
    height:320px;
}

.table-container {
    overflow-x:auto;
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
    font-weight:800;
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

.student-cell {
    display:flex;
    align-items:center;
    gap:12px;
}

.student-avatar {
    width:42px;
    height:42px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid var(--border-color);
}

.badge {
    padding:7px 12px;
    border-radius:20px;
    font-size:.75rem;
    font-weight:800;
    display:inline-flex;
    align-items:center;
    gap:6px;
}

.badge.present {
    background:rgba(16,185,129,.1);
    color:var(--success);
}

.badge.absent {
    background:rgba(239,68,68,.1);
    color:var(--danger);
}

.badge.late {
    background:rgba(245,158,11,.1);
    color:var(--warning);
}

.badge.excused {
    background:rgba(59,130,246,.1);
    color:var(--info);
}

.empty-state {
    text-align:center;
    padding:45px;
    color:var(--text-muted);
}

.print-header {
    display:none;
}

@media(max-width:1100px) {
    .stats-grid {
        grid-template-columns:repeat(2,1fr);
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

    .toolbar-card,
    .report-header {
        flex-direction:column;
        align-items:flex-start;
    }

    .report-meta {
        text-align:left;
    }

    .filter-control,
    .btn-filter {
        width:100%;
    }
}

@media print {
    body {
        background:white;
        display:block;
        color:#000;
        font-size:11px;
    }

    .sidebar,
    .top-header,
    .hero-card,
    .stats-grid,
    .toolbar-card,
    .chart-card,
    .no-print {
        display:none !important;
    }

    .main-wrapper {
        margin-left:0;
    }

    .content-area {
        padding:0;
        max-width:none;
    }

    .report-paper,
    .table-container {
        box-shadow:none;
        border:none;
        padding:0;
        border-radius:0;
    }

    .report-header {
        display:flex;
        border-bottom:2px solid #000;
        margin-bottom:15px;
        padding-bottom:12px;
    }

    .data-table {
        min-width:0;
        width:100%;
        font-size:10px;
    }

    .data-table th,
    .data-table td {
        padding:7px;
        border:1px solid #999;
    }

    .data-table th {
        background:#eee !important;
        color:#000;
    }

    .badge {
        background:transparent !important;
        color:#000 !important;
        padding:0;
        font-size:10px;
    }

    @page {
        size:landscape;
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
            <p><?= e($roleLabel) ?></p>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">Main</div>

        <a class="nav-item" href="admin-dashboard.php">
            <i class="fa fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <a class="nav-item" href="user-management.php">
            <i class="fa fa-users-gear"></i>
            <span>User Management</span>
        </a>

        <a class="nav-item" href="document-requests.php">
            <i class="fa fa-file-lines"></i>
            <span>Document Requests</span>
        </a>

        <a class="nav-item active" href="attendance.php">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <?php if ($isSuperAdmin): ?>
            <a class="nav-item" href="audit-logs.php">
                <i class="fa fa-clock-rotate-left"></i>
                <span>Audit Logs</span>
            </a>
        <?php endif; ?>

        <a class="nav-item" href="settings.php">
            <i class="fa fa-gear"></i>
            <span>Settings</span>
        </a>

        <div class="nav-label">Account</div>

        <a class="nav-item logout-link" href="../logout.php">
            <i class="fa fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main-wrapper">
    <header class="top-header">
        <div class="header-title">
            <h1>Attendance Management</h1>
            <p><?= e($roleLabel) ?> Access • Student attendance monitoring</p>
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
        <section class="hero-card">
            <div>
                <h1>Student Attendance Reports</h1>
                <p>
                    View real-time student attendance records with filters by grade, section, status, specific date, or month.
                    You can also print and export clean attendance reports.
                </p>
            </div>

            <div class="hero-actions">
                <button type="button" class="btn-primary" onclick="window.print()">
                    <i class="fa fa-print"></i>
                    Print Report
                </button>

                <a href="?<?= e(http_build_query(array_merge($_GET, ['export' => 'true']))) ?>" class="btn-outline">
                    <i class="fa fa-file-csv"></i>
                    Export CSV
                </a>
            </div>
        </section>

        <div class="stats-grid no-print">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(0,86,179,.1);color:var(--primary-blue);">
                    <i class="fa fa-chart-line"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($attendanceRate) ?>%</h3>
                    <p>Attendance Rate</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success);">
                    <i class="fa fa-user-check"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($totalPresent) ?></h3>
                    <p>Present</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning);">
                    <i class="fa fa-user-clock"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($totalLate) ?></h3>
                    <p>Late</p>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(239,68,68,.1);color:var(--danger);">
                    <i class="fa fa-user-xmark"></i>
                </div>
                <div class="stat-info">
                    <h3><?= number_format($totalAbsent) ?></h3>
                    <p>Absent</p>
                </div>
            </div>
        </div>

        <form method="GET" class="toolbar-card no-print">
            <div class="filter-group">
                <input type="text" name="search" class="filter-control" style="width:230px;" placeholder="Search student, LRN..." value="<?= e($search) ?>">

                <?php if ($gradeColumn): ?>
                    <select name="grade" class="filter-control">
                        <option value="all">All Grades</option>
                        <?php foreach ($grades as $g): ?>
                            <?php $gradeValue = (string)($g['grade_value'] ?? ''); ?>
                            <option value="<?= e($gradeValue) ?>" <?= $gradeFilter === $gradeValue ? 'selected' : '' ?>>
                                Grade <?= e($gradeValue) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                <?php endif; ?>

                <select name="section" class="filter-control">
                    <option value="all">All Sections</option>
                    <?php foreach ($sections as $s): ?>
                        <?php $sectionValue = (string)($s['section'] ?? ''); ?>
                        <option value="<?= e($sectionValue) ?>" <?= $sectionFilter === $sectionValue ? 'selected' : '' ?>>
                            <?= e($sectionValue) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <select name="status" class="filter-control">
                    <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Status</option>
                    <option value="present" <?= $statusFilter === 'present' ? 'selected' : '' ?>>Present</option>
                    <option value="late" <?= $statusFilter === 'late' ? 'selected' : '' ?>>Late</option>
                    <option value="absent" <?= $statusFilter === 'absent' ? 'selected' : '' ?>>Absent</option>
                    <option value="excused" <?= $statusFilter === 'excused' ? 'selected' : '' ?>>Excused</option>
                </select>

                <input type="month" name="month" class="filter-control" value="<?= e($monthFilter) ?>" title="Filter by month">
                <input type="date" name="date" class="filter-control" value="<?= e($dateFilter) ?>" title="Optional: exact date">

                <button type="submit" class="btn-filter">
                    <i class="fa fa-filter"></i>
                    Filter
                </button>

                <?php if ($search !== '' || $gradeFilter !== 'all' || $sectionFilter !== 'all' || $statusFilter !== 'all' || $dateFilter !== '' || $monthFilter !== date('Y-m')): ?>
                    <a href="attendance.php" style="color:var(--danger);text-decoration:none;font-weight:900;">Clear</a>
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
                        <h2>Pitogo EduTrack Attendance Report</h2>
                        <p>Coverage: <?= e($reportDateText) ?></p>
                        <p>Prepared by <?= e($adminName) ?>, <?= e($roleLabel) ?></p>
                    </div>
                </div>

                <div class="report-meta">
                    <p><strong>Generated:</strong> <?= e(date('F d, Y h:i A')) ?></p>
                    <p><strong>Grade:</strong> <?= e($gradeFilter === 'all' ? 'All Grades' : 'Grade ' . $gradeFilter) ?></p>
                    <p><strong>Section:</strong> <?= e($sectionFilter === 'all' ? 'All Sections' : $sectionFilter) ?></p>
                    <p><strong>Status:</strong> <?= e($statusFilter === 'all' ? 'All Status' : ucfirst($statusFilter)) ?></p>
                </div>
            </div>

            <div class="chart-card no-print">
                <div class="chart-header">
                    <h3><i class="fa fa-chart-column" style="color:var(--primary-blue);margin-right:8px;"></i>Attendance Trend</h3>
                    <span style="color:var(--text-muted);font-weight:800;"><?= e($reportDateText) ?></span>
                </div>
                <div class="chart-body">
                    <canvas id="attendanceChart"></canvas>
                </div>
            </div>

            <div class="table-container">
                <div class="table-header no-print">
                    <h3><i class="fa fa-list" style="color:var(--primary-blue);margin-right:8px;"></i>Attendance Records</h3>
                    <span style="color:var(--text-muted);font-weight:800;"><?= number_format($totalRecords) ?> record(s)</span>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>No.</th>
                            <th>Student</th>
                            <th>LRN / ID</th>
                            <th>Grade</th>
                            <th>Section</th>
                            <th>Date</th>
                            <th>Time In</th>
                            <th>Status</th>
                            <th>Scanned By</th>
                            <th>Remarks</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="10">
                                    <div class="empty-state">
                                        <i class="fa fa-folder-open" style="font-size:2rem;margin-bottom:10px;"></i>
                                        <br>
                                        No attendance records found.
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php $n = 1; foreach ($records as $r): ?>
                                <?php
                                    $studentName = fullName($r) ?: 'Unnamed Student';
                                    $avatar = profilePath($r, $studentName);
                                    $status = strtolower($r['status'] ?? 'present');
                                    $validBadge = in_array($status, ['present', 'absent', 'late', 'excused'], true) ? $status : 'present';
                                    $scannedBy = trim(($r['staff_first'] ?? '') . ' ' . ($r['staff_last'] ?? ''));
                                ?>
                                <tr>
                                    <td style="font-weight:800;color:var(--text-muted);"><?= $n++ ?></td>
                                    <td>
                                        <div class="student-cell">
                                            <img src="<?= e($avatar) ?>" class="student-avatar" alt="Student">
                                            <div>
                                                <strong style="color:var(--primary-navy);"><?= e($studentName) ?></strong>
                                                <br>
                                                <span style="font-size:.8rem;color:var(--text-muted);"><?= e($r['email'] ?? '') ?></span>
                                            </div>
                                        </div>
                                    </td>
                                    <td style="font-weight:800;"><?= e($r['id_num'] ?? 'N/A') ?></td>
                                    <td><?= e($r['grade_value'] ?? 'N/A') ?></td>
                                    <td><?= e($r['section'] ?? 'N/A') ?></td>
                                    <td><?= !empty($r['attendance_date']) ? e(date('M d, Y', strtotime($r['attendance_date']))) : 'N/A' ?></td>
                                    <td><?= !empty($r['time_in']) ? e(date('h:i A', strtotime($r['time_in']))) : 'N/A' ?></td>
                                    <td>
                                        <span class="badge <?= e($validBadge) ?>">
                                            <i class="fa <?= $validBadge === 'present' ? 'fa-check' : ($validBadge === 'late' ? 'fa-clock' : ($validBadge === 'excused' ? 'fa-envelope-open-text' : 'fa-xmark')) ?>"></i>
                                            <?= e(ucfirst($status)) ?>
                                        </span>
                                    </td>
                                    <td><?= e($scannedBy ?: 'System') ?></td>
                                    <td><?= e($r['remarks'] ?? 'No remarks') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</main>

<script>
const chartElement = document.getElementById('attendanceChart');

if (chartElement) {
    new Chart(chartElement, {
        type: 'bar',
        data: {
            labels: <?= json_encode($chartLabels) ?>,
            datasets: [
                {
                    label: 'Present',
                    data: <?= json_encode($presentData) ?>,
                    borderWidth: 2,
                    borderRadius: 7,
                    backgroundColor: 'rgba(16,185,129,.18)',
                    borderColor: '#10B981'
                },
                {
                    label: 'Late',
                    data: <?= json_encode($lateData) ?>,
                    borderWidth: 2,
                    borderRadius: 7,
                    backgroundColor: 'rgba(245,158,11,.18)',
                    borderColor: '#F59E0B'
                },
                {
                    label: 'Absent',
                    data: <?= json_encode($absentData) ?>,
                    borderWidth: 2,
                    borderRadius: 7,
                    backgroundColor: 'rgba(239,68,68,.16)',
                    borderColor: '#EF4444'
                },
                {
                    label: 'Excused',
                    data: <?= json_encode($excusedData) ?>,
                    borderWidth: 2,
                    borderRadius: 7,
                    backgroundColor: 'rgba(59,130,246,.15)',
                    borderColor: '#3B82F6'
                }
            ]
        },
        options: {
            responsive:true,
            maintainAspectRatio:false,
            plugins:{
                legend:{
                    position:'bottom',
                    labels:{
                        usePointStyle:true,
                        font:{weight:'bold'}
                    }
                }
            },
            scales:{
                y:{
                    beginAtZero:true,
                    ticks:{precision:0}
                },
                x:{
                    grid:{display:false}
                }
            }
        }
    });
}
</script>

</body>
</html>
