<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Admin Dashboard
|--------------------------------------------------------------------------
| Save as:
| admin/admin-dashboard.php
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

if (!in_array($_SESSION['role'], ['admin', 'superadmin'], true)) {
    header("Location: ../index.html?error=" . urlencode("Unauthorized access."));
    exit();
}

$userId = (int)$_SESSION['user_id'];
$userRole = strtolower(trim($_SESSION['role']));
$isSuperAdmin = $userRole === 'superadmin';

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

/*
|--------------------------------------------------------------------------
| Current admin info
|--------------------------------------------------------------------------
*/

$currentUser = [];
$adminNameRaw = 'System Admin';
$adminAvatar = 'https://ui-avatars.com/api/?name=System+Admin&background=0056B3&color=fff';

try {
    $stmt = $pdo->prepare("
        SELECT *
        FROM users
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $currentUser = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($currentUser) {
        $adminNameRaw = trim(($currentUser['first_name'] ?? 'System') . ' ' . ($currentUser['last_name'] ?? 'Admin'));
        $profile = $currentUser['profile_picture'] ?? $currentUser['profile_pic'] ?? '';

        if ($profile) {
            $adminAvatar = '../uploads/profiles/' . e($profile);
        } else {
            $adminAvatar = 'https://ui-avatars.com/api/?name=' . urlencode($adminNameRaw) . '&background=0056B3&color=fff';
        }
    }
} catch (Exception $e) {}

/*
|--------------------------------------------------------------------------
| Counts
|--------------------------------------------------------------------------
*/

$totalUsers = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE role != 'superadmin'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalActive = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE status = 'active'
    AND role != 'superadmin'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalPending = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE status = 'pending'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalStudents = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'student'
    AND status = 'active'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalParents = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'parent'
    AND status = 'active'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalStaff = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE role = 'staff'
    AND status = 'active'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalAdmins = safeCount($pdo, "
    SELECT COUNT(*)
    FROM users
    WHERE role IN ('admin', 'superadmin')
    AND status = 'active'
    AND is_deleted = 0
    AND is_archived = 0
");

$totalArchived = tableExists($pdo, 'archived_users')
    ? safeCount($pdo, "SELECT COUNT(*) FROM archived_users")
    : safeCount($pdo, "SELECT COUNT(*) FROM users WHERE is_archived = 1");

$totalDeleted = tableExists($pdo, 'deleted_users')
    ? safeCount($pdo, "SELECT COUNT(*) FROM deleted_users")
    : safeCount($pdo, "SELECT COUNT(*) FROM users WHERE is_deleted = 1");

$totalDocPending = tableExists($pdo, 'document_requests')
    ? safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE status = 'pending'")
    : 0;

$totalDocApproved = tableExists($pdo, 'document_requests')
    ? safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE status = 'approved'")
    : 0;

$totalAttendanceToday = tableExists($pdo, 'attendance')
    ? safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE attendance_date = CURDATE()")
    : 0;

$totalAttendanceMonth = tableExists($pdo, 'attendance')
    ? safeCount($pdo, "
        SELECT COUNT(*)
        FROM attendance
        WHERE MONTH(attendance_date) = MONTH(CURDATE())
        AND YEAR(attendance_date) = YEAR(CURDATE())
    ")
    : 0;

$pendingLoad = $totalPending + $totalDocPending;

/*
|--------------------------------------------------------------------------
| Chart data
|--------------------------------------------------------------------------
*/

$registrationRows = safeFetchAll($pdo, "
    SELECT DATE(created_at) AS reg_date, COUNT(*) AS total
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND is_deleted = 0
    GROUP BY DATE(created_at)
    ORDER BY DATE(created_at) ASC
");

$registrationMap = [];
foreach ($registrationRows as $row) {
    $registrationMap[$row['reg_date']] = (int)$row['total'];
}

$registrationLabels = [];
$registrationCounts = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $registrationLabels[] = date('D', strtotime($date));
    $registrationCounts[] = $registrationMap[$date] ?? 0;
}

$roleLabels = ['Students', 'Parents', 'Staff', 'Admins'];
$roleCounts = [$totalStudents, $totalParents, $totalStaff, $totalAdmins];

$statusLabels = ['Active', 'Pending', 'Archived', 'Deleted'];
$statusCounts = [$totalActive, $totalPending, $totalArchived, $totalDeleted];

/*
|--------------------------------------------------------------------------
| Attendance Analytics
|--------------------------------------------------------------------------
*/

$gradeFilter = trim($_GET['grade_filter'] ?? 'all');

$attendanceAnalyticsLabels = [];
$attendanceAnalyticsCounts = [];

$attendanceWhere = "";

$paramsAttendance = [];

if ($gradeFilter !== 'all') {
    $attendanceWhere = " AND u.grade_level = ? ";
    $paramsAttendance[] = $gradeFilter;
}

$attendanceAnalytics = tableExists($pdo, 'attendance')
    ? safeFetchAll($pdo, "
        SELECT 
            DATE(a.attendance_date) AS attendance_day,
            COUNT(*) AS total
        FROM attendance a
        LEFT JOIN users u ON u.id = a.student_id
        WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        $attendanceWhere
        GROUP BY DATE(a.attendance_date)
        ORDER BY attendance_day ASC
    ", $paramsAttendance)
    : [];

$attendanceMap = [];

foreach ($attendanceAnalytics as $row) {
    $attendanceMap[$row['attendance_day']] = (int)$row['total'];
}

for ($i = 6; $i >= 0; $i--) {

    $date = date('Y-m-d', strtotime("-{$i} days"));

    $attendanceAnalyticsLabels[] = date('D', strtotime($date));

    $attendanceAnalyticsCounts[] = $attendanceMap[$date] ?? 0;
}

$requestLabels = ['Pending Documents', 'Approved Documents', 'Today Attendance'];
$requestCounts = [$totalDocPending, $totalDocApproved, $totalAttendanceToday];

/*
|--------------------------------------------------------------------------
| Attendance Analytics with Grade Filter
|--------------------------------------------------------------------------
*/

$gradeFilter = trim($_GET['grade_filter'] ?? 'all');

$gradeColumn = null;

try {
    $stmtGradeLevel = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = 'users'
        AND COLUMN_NAME = 'grade_level'
    ");
    $stmtGradeLevel->execute();

    if ((int)$stmtGradeLevel->fetchColumn() > 0) {
        $gradeColumn = 'grade_level';
    } else {
        $stmtGrade = $pdo->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'users'
            AND COLUMN_NAME = 'grade'
        ");
        $stmtGrade->execute();

        if ((int)$stmtGrade->fetchColumn() > 0) {
            $gradeColumn = 'grade';
        }
    }
} catch (Exception $e) {
    $gradeColumn = null;
}

$gradeOptions = [];

if ($gradeColumn !== null) {
    $gradeOptions = safeFetchAll($pdo, "
        SELECT DISTINCT `$gradeColumn` AS grade_value
        FROM users
        WHERE role = 'student'
        AND `$gradeColumn` IS NOT NULL
        AND `$gradeColumn` != ''
        ORDER BY `$gradeColumn` ASC
    ");
}

$attendanceWhere = "";
$attendanceParams = [];

if ($gradeFilter !== 'all' && $gradeColumn !== null) {
    $attendanceWhere = " AND u.`$gradeColumn` = ? ";
    $attendanceParams[] = $gradeFilter;
}

$attendanceAnalytics = tableExists($pdo, 'attendance')
    ? safeFetchAll($pdo, "
        SELECT DATE(a.attendance_date) AS attendance_day, COUNT(*) AS total
        FROM attendance a
        LEFT JOIN users u ON u.id = a.student_id
        WHERE a.attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
        $attendanceWhere
        GROUP BY DATE(a.attendance_date)
        ORDER BY attendance_day ASC
    ", $attendanceParams)
    : [];

$attendanceMap = [];

foreach ($attendanceAnalytics as $row) {
    $attendanceMap[$row['attendance_day']] = (int)$row['total'];
}

$attendanceAnalyticsLabels = [];
$attendanceAnalyticsCounts = [];

for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $attendanceAnalyticsLabels[] = date('D', strtotime($date));
    $attendanceAnalyticsCounts[] = $attendanceMap[$date] ?? 0;
}

$attendanceByStatus = tableExists($pdo, 'attendance')
    ? safeFetchAll($pdo, "
        SELECT LOWER(COALESCE(a.status, 'present')) AS status_name, COUNT(*) AS total
        FROM attendance a
        LEFT JOIN users u ON u.id = a.student_id
        WHERE MONTH(a.attendance_date) = MONTH(CURDATE())
        AND YEAR(a.attendance_date) = YEAR(CURDATE())
        $attendanceWhere
        GROUP BY LOWER(COALESCE(a.status, 'present'))
    ", $attendanceParams)
    : [];

$attPresent = 0;
$attLate = 0;
$attAbsent = 0;
$attOther = 0;

foreach ($attendanceByStatus as $row) {
    $statusName = strtolower($row['status_name'] ?? 'present');
    $total = (int)($row['total'] ?? 0);

    if ($statusName === 'present') {
        $attPresent += $total;
    } elseif ($statusName === 'late') {
        $attLate += $total;
    } elseif ($statusName === 'absent') {
        $attAbsent += $total;
    } else {
        $attOther += $total;
    }
}

$attendanceStatusLabels = ['Present', 'Late', 'Absent', 'Other'];
$attendanceStatusCounts = [$attPresent, $attLate, $attAbsent, $attOther];


$monthlyRows = safeFetchAll($pdo, "
    SELECT DATE_FORMAT(created_at, '%b') AS month_name, MONTH(created_at) AS month_no, COUNT(*) AS total
    FROM users
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
    AND is_deleted = 0
    GROUP BY MONTH(created_at), DATE_FORMAT(created_at, '%b')
    ORDER BY month_no ASC
");

$monthlyLabels = [];
$monthlyCounts = [];

foreach ($monthlyRows as $row) {
    $monthlyLabels[] = $row['month_name'];
    $monthlyCounts[] = (int)$row['total'];
}

if (!$monthlyLabels) {
    $monthlyLabels = [date('M')];
    $monthlyCounts = [0];
}

/*
|--------------------------------------------------------------------------
| Recent data
|--------------------------------------------------------------------------
*/

$recentPendingUsers = safeFetchAll($pdo, "
    SELECT id, first_name, last_name, role, created_at
    FROM users
    WHERE status = 'pending'
    AND is_deleted = 0
    AND is_archived = 0
    ORDER BY created_at DESC
    LIMIT 5
");

$recentDocs = tableExists($pdo, 'document_requests')
    ? safeFetchAll($pdo, "
        SELECT 
            dr.id,
            dr.document_type,
            dr.status,
            dr.created_at,
            s.first_name AS student_first,
            s.last_name AS student_last,
            r.first_name AS requester_first,
            r.last_name AS requester_last
        FROM document_requests dr
        LEFT JOIN users s ON s.id = dr.student_id
        LEFT JOIN users r ON r.id = dr.requested_by
        ORDER BY dr.created_at DESC
        LIMIT 5
    ")
    : [];

$recentAttendance = tableExists($pdo, 'attendance')
    ? safeFetchAll($pdo, "
        SELECT
            a.attendance_date,
            a.time_in,
            a.status,
            s.first_name,
            s.last_name,
            st.first_name AS staff_first,
            st.last_name AS staff_last
        FROM attendance a
        LEFT JOIN users s ON s.id = a.student_id
        LEFT JOIN users st ON st.id = a.scanned_by
        ORDER BY a.created_at DESC
        LIMIT 5
    ")
    : [];

$recentLogs = ($isSuperAdmin && tableExists($pdo, 'audit_logs'))
    ? safeFetchAll($pdo, "
        SELECT 
            a.*,
            u.first_name,
            u.last_name
        FROM audit_logs a
        LEFT JOIN users u ON u.id = a.user_id
        ORDER BY a.created_at DESC
        LIMIT 6
    ")
    : [];

$approvalRate = ($totalUsers > 0)
    ? round(($totalActive / max($totalUsers, 1)) * 100)
    : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title><?= e($roleLabel) ?> Dashboard | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

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
    --transition: all 0.3s ease;
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
    padding: 8px 10px;
    border-radius: 14px;
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

.page-title {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 18px;
    margin-bottom: 28px;
}

.page-title h1 {
    font-size: 2rem;
    color: var(--primary-navy);
}

.page-title p {
    color: var(--text-muted);
    margin-top: 7px;
}

.quick-actions {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.quick-btn {
    text-decoration: none;
    background: var(--primary-blue);
    color: #fff;
    padding: 12px 16px;
    border-radius: 12px;
    font-weight: 800;
    font-size: .88rem;
    box-shadow: var(--shadow-sm);
}

.quick-btn.secondary {
    background: #fff;
    color: var(--primary-blue);
    border: 1px solid var(--border-color);
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(190px, 1fr));
    gap: 20px;
    margin-bottom: 25px;
}

.stat-card {
    background: var(--surface-white);
    border: 1px solid var(--border-color);
    border-radius: 22px;
    padding: 22px;
    box-shadow: var(--shadow-sm);
    position: relative;
    overflow: hidden;
    transition: var(--transition);
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: var(--shadow-md);
}

.stat-card::after {
    content: '';
    position: absolute;
    width: 90px;
    height: 90px;
    right: -25px;
    top: -25px;
    background: rgba(0,86,179,.08);
    border-radius: 50%;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 15px;
    background: rgba(0,86,179,.09);
    color: var(--primary-blue);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    margin-bottom: 18px;
}

.stat-card h2 {
    font-size: 2rem;
    color: var(--primary-navy);
}

.stat-card p {
    color: var(--text-muted);
    font-size: .9rem;
    font-weight: 700;
    margin-top: 5px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(430px, 1fr));
    gap: 22px;
    margin-bottom: 24px;
    align-items: stretch;
}

.panel {
    background: var(--surface-white);
    border: 1px solid var(--border-color);
    border-radius: 22px;
    padding: 24px;
    box-shadow: var(--shadow-sm);
}

.panel-header {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    align-items: center;
    margin-bottom: 20px;
}

.panel-header h3 {
    color: var(--primary-navy);
    font-size: 1.05rem;
}

.panel-header a {
    color: var(--primary-blue);
    font-size: .82rem;
    font-weight: 800;
    text-decoration: none;
}

.chart-box {
    position: relative;
    width: 100%;
    height: 320px;
}

.mini-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}

.table-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.list-item {
    border: 1px solid var(--border-color);
    background: #F8FAFC;
    padding: 14px;
    border-radius: 15px;
    display: flex;
    justify-content: space-between;
    gap: 15px;
    align-items: center;
}

.list-item h4 {
    font-size: .9rem;
    color: var(--primary-navy);
}

.list-item p {
    color: var(--text-muted);
    font-size: .78rem;
    margin-top: 3px;
}

.badge {
    padding: 7px 10px;
    border-radius: 999px;
    font-size: .72rem;
    font-weight: 900;
    text-transform: uppercase;
    white-space: nowrap;
}

.badge.pending {
    background: #FEF3C7;
    color: #92400E;
}

.badge.active,
.badge.present,
.badge.approved {
    background: #DCFCE7;
    color: #166534;
}

.badge.released {
    background: #DBEAFE;
    color: #1E40AF;
}

.badge.rejected,
.badge.absent {
    background: #FEE2E2;
    color: #991B1B;
}

.empty-state {
    color: var(--text-muted);
    font-size: .9rem;
    text-align: center;
    padding: 25px 10px;
}

.attendance-filter {
    display: flex;
    gap: 10px;
    align-items: center;
}

.grade-select {
    padding: 10px 14px;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    background: #fff;
    color: var(--primary-navy);
    font-weight: 800;
    outline: none;
    min-width: 150px;
}

.grade-select:focus {
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(0,86,179,.08);
}

.panel-subtitle {
    color: var(--text-muted);
    font-size: .8rem;
    margin-top: 4px;
    font-weight: 600;
}

.health-card {
    background: linear-gradient(135deg, var(--primary-navy), var(--primary-blue));
    color: white;
    border-radius: 22px;
    padding: 26px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-md);
}

.health-card h2 {
    font-size: 1.25rem;
    margin-bottom: 10px;
}

.health-card p {
    color: rgba(255,255,255,.85);
    line-height: 1.6;
}

.health-row {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-top: 20px;
}

.health-item {
    background: rgba(255,255,255,.12);
    border: 1px solid rgba(255,255,255,.16);
    padding: 16px;
    border-radius: 16px;
}

.health-item h3 {
    font-size: 1.5rem;
}

.health-item span {
    font-size: .78rem;
    color: rgba(255,255,255,.8);
}

@media(max-width: 1100px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: 1fr;
    }
}

@media(max-width: 700px) {
    .sidebar {
        width: 0;
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

    .page-title {
        flex-direction: column;
        align-items: flex-start;
    }

    .stats-grid,
    .mini-grid,
    .health-row {
        grid-template-columns: 1fr;
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

        <a class="nav-item active" href="admin-dashboard.php">
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
            <h1><?= e($roleLabel) ?> Dashboard</h1>
            <p><?= date('l, F d, Y') ?> • Pitogo EduTrack Control Panel</p>
        </div>

        <div class="user-profile">
            <div class="user-info">
                <h4><?= e($adminNameRaw) ?></h4>
                <p><?= e($roleLabel) ?></p>
            </div>
            <img src="<?= e($adminAvatar) ?>" alt="Admin Avatar" class="avatar">
        </div>
    </header>

    <section class="content-area">

        <div class="page-title">
            <div>
                <h1>System Overview</h1>
                <p>Monitor registered users, QR attendance, document requests, and account activity.</p>
            </div>

            <div class="quick-actions">
                <a href="user-management.php?tab=pending" class="quick-btn">
                    <i class="fa fa-user-check"></i> Review Pending
                </a>
                <a href="document-requests.php" class="quick-btn secondary">
                    <i class="fa fa-file-circle-check"></i> Documents
                </a>

                <a href="attendance.php" class="quick-btn secondary">
                    <i class="fa fa-calendar-check"></i> Attendance
                </a>
                <a href="attendance.php" class="quick-btn secondary">
                    <i class="fa fa-calendar-check"></i> Attendance
                </a>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-users"></i></div>
                <h2><?= e($totalUsers) ?></h2>
                <p>Total Users</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-user-clock"></i></div>
                <h2><?= e($totalPending) ?></h2>
                <p>Pending Accounts</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-qrcode"></i></div>
                <h2><?= e($totalAttendanceToday) ?></h2>
                <p>QR Attendance Today</p>
            </div>

            <div class="stat-card">
                <div class="stat-icon"><i class="fa fa-file-signature"></i></div>
                <h2><?= e($totalDocPending) ?></h2>
                <p>Pending Documents</p>
            </div>
        </div>

        <div class="health-card">
            <h2>Pitogo EduTrack Status</h2>
            <p>
                This dashboard now focuses on QR-based attendance, parent-student access,
                document requests, and secure account management.
            </p>

            <div class="health-row">
                <div class="health-item">
                    <h3><?= e($approvalRate) ?>%</h3>
                    <span>Active account rate</span>
                </div>

                <div class="health-item">
                    <h3><?= e($pendingLoad) ?></h3>
                    <span>Pending workload</span>
                </div>

                <div class="health-item">
                    <h3><?= e($totalAttendanceMonth) ?></h3>
                    <span>Attendance scans this month</span>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="panel">
                <div class="panel-header">
                    <h3>Registrations in the Last 7 Days</h3>
                </div>
                <div class="chart-box">
                    <canvas id="registrationChart"></canvas>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>User Role Distribution</h3>
                </div>
                <div class="chart-box">
                    <canvas id="roleChart"></canvas>
                </div>
            </div>
        </div>

        <div class="dashboard-grid">

    <div class="panel">
        <div class="panel-header">
            <div>
                <h3>Attendance Analytics</h3>
                <p style="font-size:.8rem;color:var(--text-muted);margin-top:4px;">
                    Attendance records filtered by grade level
                </p>
            </div>

            <form method="GET" style="display:flex;gap:10px;align-items:center;">
                <select 
                    name="grade_filter"
                    onchange="this.form.submit()"
                    style="
                        padding:10px 14px;
                        border-radius:10px;
                        border:1px solid var(--border-color);
                        background:#fff;
                        font-weight:700;
                        color:var(--primary-navy);
                    "
                >
                    <option value="all">All Grades</option>
                    <option value="7" <?= ($_GET['grade_filter'] ?? '') === '7' ? 'selected' : '' ?>>Grade 7</option>
                    <option value="8" <?= ($_GET['grade_filter'] ?? '') === '8' ? 'selected' : '' ?>>Grade 8</option>
                    <option value="9" <?= ($_GET['grade_filter'] ?? '') === '9' ? 'selected' : '' ?>>Grade 9</option>
                    <option value="10" <?= ($_GET['grade_filter'] ?? '') === '10' ? 'selected' : '' ?>>Grade 10</option>
                    <option value="11" <?= ($_GET['grade_filter'] ?? '') === '11' ? 'selected' : '' ?>>Grade 11</option>
                    <option value="12" <?= ($_GET['grade_filter'] ?? '') === '12' ? 'selected' : '' ?>>Grade 12</option>
                </select>
            </form>
        </div>

        <div class="chart-box">
            <canvas id="attendanceAnalyticsChart"></canvas>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header">
            <h3>Account Status</h3>
        </div>

        <div class="chart-box">
            <canvas id="statusChart"></canvas>
        </div>
    </div>

</div>

        <div class="mini-grid">
            <div class="panel">
                <div class="panel-header">
                    <h3>Recent Pending Accounts</h3>
                    <a href="user-management.php?tab=pending">View All</a>
                </div>

                <div class="table-list">
                    <?php if ($recentPendingUsers): ?>
                        <?php foreach ($recentPendingUsers as $user): ?>
                            <div class="list-item">
                                <div>
                                    <h4><?= e(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?></h4>
                                    <p><?= ucfirst(e($user['role'])) ?> • <?= e(date('M d, Y', strtotime($user['created_at']))) ?></p>
                                </div>
                                <span class="badge pending">Pending</span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No pending accounts.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Recent Document Requests</h3>
                    <a href="document-requests.php">View All</a>
                </div>

                <div class="table-list">
                    <?php if ($recentDocs): ?>
                        <?php foreach ($recentDocs as $doc): ?>
                            <div class="list-item">
                                <div>
                                    <h4><?= e($doc['document_type']) ?></h4>
                                    <p>
                                        Student:
                                        <?= e(trim(($doc['student_first'] ?? '') . ' ' . ($doc['student_last'] ?? ''))) ?>
                                    </p>
                                </div>
                                <span class="badge <?= e(strtolower($doc['status'])) ?>"><?= e($doc['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No document requests found.</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h3>Recent QR Attendance</h3>
                </div>

                <div class="table-list">
                    <?php if ($recentAttendance): ?>
                        <?php foreach ($recentAttendance as $att): ?>
                            <div class="list-item">
                                <div>
                                    <h4><?= e(trim(($att['first_name'] ?? '') . ' ' . ($att['last_name'] ?? ''))) ?></h4>
                                    <p>
                                        <?= e(date('M d, Y', strtotime($att['attendance_date']))) ?>
                                        <?php if (!empty($att['time_in'])): ?>
                                            • <?= e(date('h:i A', strtotime($att['time_in']))) ?>
                                        <?php endif; ?>
                                    </p>
                                </div>
                                <span class="badge <?= e(strtolower($att['status'])) ?>"><?= e($att['status']) ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">No attendance scans yet.</div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if ($isSuperAdmin): ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3>Recent Audit Logs</h3>
                        <a href="audit-logs.php">View All</a>
                    </div>

                    <div class="table-list">
                        <?php if ($recentLogs): ?>
                            <?php foreach ($recentLogs as $log): ?>
                                <div class="list-item">
                                    <div>
                                        <h4><?= e($log['action']) ?></h4>
                                        <p>
                                            <?= e(trim(($log['first_name'] ?? 'System') . ' ' . ($log['last_name'] ?? ''))) ?>
                                            • <?= e(date('M d, h:i A', strtotime($log['created_at']))) ?>
                                        </p>
                                    </div>
                                    <span class="badge active">Log</span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">No audit logs yet.</div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php else: ?>
                <div class="panel">
                    <div class="panel-header">
                        <h3>Attendance Management</h3>
                        <a href="attendance.php">View Attendance</a>
                    </div>

                    <div class="table-list">
                        <div class="list-item">
                            <div>
                                <h4>Student Attendance Records</h4>
                                <p>View student attendance by grade, section, date, or month.</p>
                            </div>
                            <span class="badge active">Open</span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>

    </section>

</main>

<script>
const chartColor = '<?= e($themeColor) ?>';

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
                    ? ['#0056B3', '#10B981', '#F59E0B', '#7C3AED']
                    : 'rgba(0,86,179,.15)',
                borderColor: type === 'doughnut'
                    ? '#ffffff'
                    : chartColor
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
    'registrationChart',
    'line',
    <?= json_encode($registrationLabels) ?>,
    <?= json_encode($registrationCounts) ?>,
    'Registrations'
);

createChart(
    'roleChart',
    'doughnut',
    <?= json_encode($roleLabels) ?>,
    <?= json_encode($roleCounts) ?>,
    'Users'
);

createChart(
    'requestChart',
    'bar',
    <?= json_encode($requestLabels) ?>,
    <?= json_encode($requestCounts) ?>,
    'Queue'
);

createChart(
    'statusChart',
    'doughnut',
    <?= json_encode($statusLabels) ?>,
    <?= json_encode($statusCounts) ?>,
    'Status'
);


createChart(
    'attendanceAnalyticsChart',
    'bar',
    <?= json_encode($attendanceAnalyticsLabels) ?>,
    <?= json_encode($attendanceAnalyticsCounts) ?>,
    'Attendance Scans'
);

createChart(
    'attendanceStatusChart',
    'doughnut',
    <?= json_encode($attendanceStatusLabels) ?>,
    <?= json_encode($attendanceStatusCounts) ?>,
    'Attendance Status'
);
</script>

</body>
</html>