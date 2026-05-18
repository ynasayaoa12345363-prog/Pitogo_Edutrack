<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Audit Logs
|--------------------------------------------------------------------------
| Save as:
| admin/audit-logs.php
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if ($currentRole !== 'superadmin') {
    header("Location: admin-dashboard.php?error=" . urlencode("Audit logs are only available to the Super Administrator."));
    exit();
}

$isSuperAdmin = true;
$themeColor = '#7C3AED';
$roleLabel = 'Super Administrator';

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
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
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

$stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtAdmin->execute([$currentUserId]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];

$adminName = fullName($admin) ?: 'System Admin';

$adminAvatar = !empty($admin['profile_picture'])
    ? '../uploads/profiles/' . $admin['profile_picture']
    : 'https://ui-avatars.com/api/?name=' . urlencode($adminName) . '&background=0056B3&color=fff';

$search = trim($_GET['search'] ?? '');
$roleFilter = strtolower(trim($_GET['role'] ?? 'all'));
$dateFilter = trim($_GET['date'] ?? '');

$params = [];

$query = "
    SELECT
        al.*,
        u.first_name,
        u.last_name,
        u.email
    FROM audit_logs al
    LEFT JOIN users u ON u.id = al.user_id
    WHERE 1=1
";

if ($search !== '') {
    $query .= "
        AND (
            al.action LIKE ?
            OR al.target_table LIKE ?
            OR u.first_name LIKE ?
            OR u.last_name LIKE ?
            OR u.email LIKE ?
            OR al.ip_address LIKE ?
        )
    ";

    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s, $s, $s);
}

if ($roleFilter !== 'all') {
    $query .= " AND al.user_role = ?";
    $params[] = $roleFilter;
}

if ($dateFilter !== '') {
    $query .= " AND DATE(al.created_at) = ?";
    $params[] = $dateFilter;
}

$query .= " ORDER BY al.created_at DESC";

$logs = safeFetchAll($pdo, $query, $params);

$totalLogs = safeCount($pdo, "SELECT COUNT(*) FROM audit_logs");
$totalToday = safeCount($pdo, "SELECT COUNT(*) FROM audit_logs WHERE DATE(created_at)=CURDATE()");
$totalAdmins = safeCount($pdo, "SELECT COUNT(*) FROM audit_logs WHERE user_role IN ('admin','superadmin')");
$totalFiltered = count($logs);

$generatedDate = date('F d, Y h:i A');

$msg = '';

if (isset($_GET['msg'])) {
    $msg = "
        <div class='alert'>
            <i class='fa fa-circle-check'></i>
            " . e($_GET['msg']) . "
        </div>
    ";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Audit Logs | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root{
    --primary-navy:#0B1C3D;
    --primary-blue:<?= e($themeColor) ?>;
    --bg-main:#F4F7F9;
    --surface-white:#FFFFFF;
    --text-dark:#1E293B;
    --text-muted:#64748B;
    --border:#E2E8F0;
    --success:#10B981;
    --warning:#F59E0B;
    --danger:#EF4444;
    --info:#3B82F6;
    --shadow-sm:0 4px 10px rgba(15,23,42,.04);
    --shadow-md:0 12px 28px rgba(15,23,42,.08);
}

*{
    margin:0;
    padding:0;
    box-sizing:border-box;
}

body{
    font-family:'Inter',sans-serif;
    background:var(--bg-main);
    display:flex;
    min-height:100vh;
    color:var(--text-dark);
}

h1,h2,h3,h4{
    font-family:'Montserrat',sans-serif;
}

.sidebar{
    width:82px;
    height:100vh;
    background:#fff;
    border-right:1px solid var(--border);
    position:fixed;
    top:0;
    left:0;
    transition:.3s;
    overflow:hidden;
    z-index:100;
}

.sidebar:hover{
    width:280px;
    box-shadow:10px 0 30px rgba(15,23,42,.08);
}

.brand{
    padding:22px 16px;
    display:flex;
    align-items:center;
    gap:15px;
    border-bottom:1px solid var(--border);
}

.brand img{
    min-width:48px;
    width:48px;
    height:48px;
    object-fit:contain;
}

.brand-text{
    opacity:0;
    transition:.2s;
    white-space:nowrap;
}

.sidebar:hover .brand-text{
    opacity:1;
}

.brand-text h2{
    font-size:1rem;
    font-weight:800;
    color:var(--primary-navy);
}

.brand-text p{
    font-size:.72rem;
    color:var(--primary-blue);
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.8px;
}

.nav{
    padding:20px 14px;
    display:flex;
    flex-direction:column;
    gap:6px;
}

.nav a{
    display:flex;
    align-items:center;
    gap:18px;
    padding:14px 12px;
    border-radius:12px;
    text-decoration:none;
    color:#64748B;
    font-weight:700;
    transition:.2s;
}

.nav a:hover,
.nav a.active{
    background:rgba(0,86,179,.08);
    color:var(--primary-blue);
}
.logout-link {
    margin-top: auto;
    color: var(--danger);
}

.nav i{
    width:24px;
    text-align:center;
    font-size:1.1rem;
}

.nav span{
    opacity:0;
    transition:.2s;
    white-space:nowrap;
}

.sidebar:hover .nav span{
    opacity:1;
}

.main{
    margin-left:82px;
    flex:1;
    min-width:0;
}

.topbar{
    height:88px;
    background:#fff;
    border-bottom:1px solid var(--border);
    display:flex;
    justify-content:space-between;
    align-items:center;
    padding:0 35px;
    position:sticky;
    top:0;
    z-index:50;
}

.topbar h1{
    font-size:1.4rem;
    color:var(--primary-navy);
}

.topbar p{
    color:#64748B;
    margin-top:4px;
    font-size:.85rem;
}

.user{
    display:flex;
    align-items:center;
    gap:12px;
}

.user img{
    width:46px;
    height:46px;
    border-radius:50%;
    object-fit:cover;
    border:2px solid var(--primary-blue);
    padding:2px;
}

.content{
    padding:35px;
    max-width:1500px;
    margin:0 auto;
}

.alert{
    background:#DCFCE7;
    border:1px solid #A7F3D0;
    color:#166534;
    padding:15px 18px;
    border-radius:14px;
    margin-bottom:20px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:10px;
}

.hero{
    background:
        linear-gradient(135deg, rgba(11,28,61,.96), rgba(0,86,179,.82)),
        url('../school-bg.jpg');
    background-size:cover;
    background-position:center;
    border-radius:24px;
    color:#fff;
    padding:34px;
    margin-bottom:24px;
    box-shadow:var(--shadow-md);
}

.hero h1{
    font-size:2rem;
    margin-bottom:8px;
}

.hero p{
    opacity:.9;
    line-height:1.6;
    max-width:850px;
}

.stats{
    display:grid;
    grid-template-columns:repeat(4,1fr);
    gap:18px;
    margin-bottom:24px;
}

.card{
    background:#fff;
    border:1px solid var(--border);
    border-radius:18px;
    padding:22px;
    box-shadow:var(--shadow-sm);
    transition:.2s;
}

.card:hover{
    transform:translateY(-3px);
    box-shadow:var(--shadow-md);
}

.card-icon{
    width:52px;
    height:52px;
    border-radius:15px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.35rem;
    margin-bottom:14px;
}

.card h2{
    font-size:1.9rem;
    color:var(--primary-navy);
}

.card p{
    color:#64748B;
    font-weight:800;
    text-transform:uppercase;
    font-size:.78rem;
    margin-top:4px;
}

.filters{
    background:#fff;
    border:1px solid var(--border);
    border-radius:18px;
    padding:20px;
    display:flex;
    flex-wrap:wrap;
    gap:12px;
    margin-bottom:24px;
    box-shadow:var(--shadow-sm);
}

.filters input,
.filters select{
    padding:11px 14px;
    border-radius:10px;
    border:1px solid var(--border);
    background:#F8FAFC;
    font-family:'Inter',sans-serif;
    min-width:180px;
}

.btn{
    background:var(--primary-blue);
    color:#fff;
    border:none;
    border-radius:10px;
    padding:11px 16px;
    font-weight:800;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:8px;
    text-decoration:none;
}

.btn.dark{
    background:var(--primary-navy);
}

.table-wrap{
    background:#fff;
    border:1px solid var(--border);
    border-radius:18px;
    overflow:auto;
    box-shadow:var(--shadow-sm);
}

table{
    width:100%;
    border-collapse:collapse;
    min-width:1100px;
}

th{
    background:#F8FAFC;
    padding:16px;
    text-align:left;
    font-size:.76rem;
    text-transform:uppercase;
    color:#64748B;
    border-bottom:2px solid var(--border);
}

td{
    padding:16px;
    border-top:1px solid var(--border);
    vertical-align:top;
}

tbody tr:hover td{
    background:#F8FAFC;
}

.badge{
    display:inline-block;
    padding:7px 12px;
    border-radius:999px;
    font-size:.75rem;
    font-weight:800;
    background:#DBEAFE;
    color:#1D4ED8;
    text-transform:capitalize;
}

.empty{
    text-align:center;
    padding:45px;
    color:#64748B;
}

.print-report-header,
.print-meta-grid,
.print-signature{
    display:none;
}

@media(max-width:900px){
    .stats{
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width:760px){
    .sidebar{
        display:none;
    }

    .main{
        margin-left:0;
    }

    .topbar{
        padding:0 20px;
    }

    .user div{
        display:none;
    }

    .content{
        padding:20px;
    }

    .stats{
        grid-template-columns:1fr;
    }

    .filters{
        flex-direction:column;
        align-items:stretch;
    }

    .filters input,
    .filters select,
    .btn{
        width:100%;
    }
}

/* =========================
   CLEAN PRINT FORMAT
========================= */
@media print{
    *{
        -webkit-print-color-adjust:exact !important;
        print-color-adjust:exact !important;
    }

    body{
        background:#fff !important;
        color:#111827 !important;
        display:block !important;
        font-family:Arial, sans-serif !important;
        margin:0 !important;
        padding:0 !important;
    }

    .sidebar,
    .topbar,
    .filters,
    .btn,
    .no-print{
        display:none !important;
    }

    .main{
        margin:0 !important;
        width:100% !important;
    }

    .content{
        padding:0 !important;
        margin:0 !important;
        max-width:none !important;
        width:100% !important;
    }

    .print-report-header{
        display:flex !important;
        align-items:center !important;
        justify-content:center !important;
        gap:18px !important;
        text-align:center !important;
        padding-bottom:14px !important;
        border-bottom:2px solid #0B1C3D !important;
        margin-bottom:14px !important;
    }

    .print-report-header img{
        width:78px !important;
        height:78px !important;
        object-fit:contain !important;
    }

    .print-report-header h2{
        margin:0 !important;
        font-size:22px !important;
        line-height:1.1 !important;
        font-weight:800 !important;
        color:#0B1C3D !important;
        letter-spacing:.4px !important;
    }

    .print-report-header p{
        margin:3px 0 !important;
        font-size:12px !important;
        color:#334155 !important;
    }

    .print-report-header h3{
        margin-top:6px !important;
        font-size:13px !important;
        font-weight:800 !important;
        color:#111827 !important;
        text-transform:uppercase !important;
        letter-spacing:.6px !important;
    }

    .hero{
        background:#fff !important;
        color:#111827 !important;
        border:1px solid #CBD5E1 !important;
        border-left:5px solid #0B1C3D !important;
        box-shadow:none !important;
        border-radius:0 !important;
        padding:12px 14px !important;
        margin:0 0 12px 0 !important;
    }

    .hero h1{
        font-size:18px !important;
        margin:0 0 4px 0 !important;
        color:#0B1C3D !important;
    }

    .hero p{
        font-size:11px !important;
        line-height:1.4 !important;
        color:#475569 !important;
        margin:0 !important;
        max-width:none !important;
    }

    .print-meta-grid{
        display:grid !important;
        grid-template-columns:repeat(4, 1fr) !important;
        gap:8px !important;
        margin-bottom:12px !important;
    }

    .print-meta-item{
        border:1px solid #CBD5E1 !important;
        padding:7px 9px !important;
        background:#F8FAFC !important;
    }

    .print-meta-item span{
        display:block !important;
        font-size:9px !important;
        color:#64748B !important;
        text-transform:uppercase !important;
        font-weight:700 !important;
        margin-bottom:2px !important;
    }

    .print-meta-item strong{
        display:block !important;
        font-size:11px !important;
        color:#0F172A !important;
        font-weight:800 !important;
    }

    .stats{
        grid-template-columns:repeat(4,1fr) !important;
        gap:8px !important;
        margin:0 0 12px 0 !important;
    }

    .card{
        border:1px solid #CBD5E1 !important;
        border-radius:0 !important;
        box-shadow:none !important;
        padding:9px 10px !important;
        background:#fff !important;
        page-break-inside:avoid !important;
    }

    .card-icon{
        display:none !important;
    }

    .card h2{
        font-size:17px !important;
        color:#0B1C3D !important;
        margin:0 0 2px 0 !important;
    }

    .card p{
        font-size:9px !important;
        color:#475569 !important;
        margin:0 !important;
    }

    .table-wrap{
        border:none !important;
        border-radius:0 !important;
        overflow:visible !important;
        box-shadow:none !important;
        background:#fff !important;
    }

    table{
        width:100% !important;
        min-width:0 !important;
        border-collapse:collapse !important;
        table-layout:fixed !important;
        font-size:10px !important;
    }

    thead{
        display:table-header-group !important;
    }

    tr{
        page-break-inside:avoid !important;
    }

    th{
        background:#E5E7EB !important;
        color:#111827 !important;
        border:1px solid #94A3B8 !important;
        padding:6px 5px !important;
        font-size:8.5px !important;
        font-weight:800 !important;
        text-transform:uppercase !important;
    }

    td{
        border:1px solid #CBD5E1 !important;
        padding:6px 5px !important;
        vertical-align:top !important;
        word-wrap:break-word !important;
        overflow-wrap:anywhere !important;
        color:#111827 !important;
    }

    td:nth-child(1){
        width:4% !important;
        text-align:center !important;
    }

    td:nth-child(2){
        width:18% !important;
    }

    td:nth-child(3){
        width:10% !important;
    }

    td:nth-child(4){
        width:28% !important;
    }

    td:nth-child(5){
        width:12% !important;
    }

    td:nth-child(6){
        width:8% !important;
    }

    td:nth-child(7){
        width:10% !important;
    }

    td:nth-child(8){
        width:10% !important;
    }

    .badge{
        background:transparent !important;
        color:#111827 !important;
        padding:0 !important;
        border-radius:0 !important;
        font-size:9px !important;
        font-weight:700 !important;
    }

    .empty{
        padding:18px !important;
        color:#64748B !important;
    }

    .print-signature{
        display:flex !important;
        justify-content:flex-end !important;
        margin-top:28px !important;
        page-break-inside:avoid !important;
    }

    .signature-box{
        width:230px !important;
        text-align:center !important;
        font-size:11px !important;
        color:#111827 !important;
    }

    .signature-line{
        border-top:1px solid #111827 !important;
        margin-top:35px !important;
        padding-top:5px !important;
        font-weight:800 !important;
    }

    @page{
        size:landscape;
        margin:12mm;
    }
}
.disabled-nav {
    opacity: .45;
    cursor: not-allowed;
    pointer-events: none;
    background: #f1f5f9;
}

.disabled-nav i,
.disabled-nav span {
    color: #94a3b8 !important;
}
</style>
</head>

<body>

<aside class="sidebar">
    <div class="brand">
        <img src="../school-logo.jpg" alt="Pitogo High School Logo" onerror="this.style.display='none';">

        <div class="brand-text">
            <h2>Pitogo EduTrack</h2>
            <p><?= e($roleLabel) ?></p>
        </div>
    </div>

    <div class="nav">
        <a href="admin-dashboard.php">
            <i class="fa fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <a href="user-management.php">
            <i class="fa fa-users"></i>
            <span>User Management</span>
        </a>

        <a href="document-requests.php">
            <i class="fa fa-file-lines"></i>
            <span>Document Requests</span>
        </a>

        <a class="nav-item" href="attendance.php">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <?php if ($isSuperAdmin): ?>
    <a href="audit-logs.php" class="nav-item">
        <i class="fa fa-clock-rotate-left"></i>
        <span>Audit Logs</span>
    </a>
<?php else: ?>
    <div class="nav-item disabled-nav" title="Only Super Administrator can access Audit Logs">
        <i class="fa fa-lock"></i>
        <span>Audit Logs</span>
    </div>
<?php endif; ?>

        <a href="settings.php">
            <i class="fa fa-gear"></i>
            <span>Settings</span>
        </a>

          <a href="../logout.php" class="nav-item logout-link">
            <i class="fa fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </div>
</aside>

<main class="main">

<header class="topbar">
    <div>
        <h1>Audit Logs</h1>
        <p><?= e($roleLabel) ?> Access • Pitogo EduTrack</p>
    </div>

    <div class="user">
        <div>
            <strong><?= e($adminName) ?></strong>
            <p style="font-size:.8rem;color:#64748B;"><?= e($roleLabel) ?></p>
        </div>

        <img src="<?= e($adminAvatar) ?>" alt="Admin Profile">
    </div>
</header>

<div class="content">

<?= $msg ?>

<div class="print-report-header">
    <img src="../school-logo.jpg" alt="Pitogo High School Logo">
    <div>
        <h2>PITOGO HIGH SCHOOL</h2>
        <p>Pitogo EduTrack Management System</p>
        <p>Luzon St, Pitogo, Taguig, 1630 Metro Manila, Philippines</p>
        <h3>Official Audit Log Report</h3>
    </div>
</div>

<section class="hero">
    <h1>System Audit Logs</h1>
    <p>Track account actions, approvals, document releases, OTP activities, QR attendance scans, and system records.</p>
</section>

<div class="print-meta-grid">
    <div class="print-meta-item">
        <span>Generated By</span>
        <strong><?= e($adminName) ?></strong>
    </div>

    <div class="print-meta-item">
        <span>Role</span>
        <strong><?= e($roleLabel) ?></strong>
    </div>

    <div class="print-meta-item">
        <span>Date Generated</span>
        <strong><?= e($generatedDate) ?></strong>
    </div>

    <div class="print-meta-item">
        <span>Report Filter</span>
        <strong><?= e($roleFilter === 'all' ? 'All Roles' : ucfirst($roleFilter)) ?></strong>
    </div>
</div>

<div class="stats">
    <div class="card">
        <div class="card-icon" style="background:rgba(0,86,179,.1);color:#0056B3;">
            <i class="fa fa-database"></i>
        </div>
        <h2><?= number_format($totalLogs) ?></h2>
        <p>Total Logs</p>
    </div>

    <div class="card">
        <div class="card-icon" style="background:rgba(16,185,129,.1);color:#10B981;">
            <i class="fa fa-calendar-day"></i>
        </div>
        <h2><?= number_format($totalToday) ?></h2>
        <p>Today’s Logs</p>
    </div>

    <div class="card">
        <div class="card-icon" style="background:rgba(124,58,237,.1);color:#7C3AED;">
            <i class="fa fa-user-shield"></i>
        </div>
        <h2><?= number_format($totalAdmins) ?></h2>
        <p>Admin Actions</p>
    </div>

    <div class="card">
        <div class="card-icon" style="background:rgba(245,158,11,.1);color:#F59E0B;">
            <i class="fa fa-list-check"></i>
        </div>
        <h2><?= number_format($totalFiltered) ?></h2>
        <p>Filtered Results</p>
    </div>
</div>

<form method="GET" class="filters">
    <input type="text" name="search" placeholder="Search logs..." value="<?= e($search) ?>">

    <select name="role">
        <option value="all" <?= $roleFilter==='all'?'selected':'' ?>>All Roles</option>
        <option value="admin" <?= $roleFilter==='admin'?'selected':'' ?>>Admin</option>
        <option value="superadmin" <?= $roleFilter==='superadmin'?'selected':'' ?>>Superadmin</option>
        <option value="staff" <?= $roleFilter==='staff'?'selected':'' ?>>Staff</option>
        <option value="student" <?= $roleFilter==='student'?'selected':'' ?>>Student</option>
        <option value="parent" <?= $roleFilter==='parent'?'selected':'' ?>>Parent</option>
    </select>

    <input type="date" name="date" value="<?= e($dateFilter) ?>">

    <button class="btn" type="submit">
        <i class="fa fa-filter"></i>
        Filter
    </button>

    <button class="btn dark" type="button" onclick="window.print()">
        <i class="fa fa-print"></i>
        Print Report
    </button>
</form>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th style="width:4%;">#</th>
                <th style="width:18%;">User</th>
                <th style="width:10%;">Role</th>
                <th style="width:28%;">Action</th>
                <th style="width:12%;">Target Table</th>
                <th style="width:8%;">Target ID</th>
                <th style="width:10%;">IP Address</th>
                <th style="width:10%;">Date & Time</th>
            </tr>
        </thead>

        <tbody>
            <?php if (!$logs): ?>
                <tr>
                    <td colspan="8">
                        <div class="empty">
                            <i class="fa fa-folder-open" style="font-size:1.5rem;margin-bottom:8px;"></i>
                            <br>
                            No audit logs found.
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php $count = 1; ?>
                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= $count++ ?></td>

                        <td>
                            <strong><?= e(fullName($log) ?: 'System') ?></strong>
                            <br>
                            <span style="font-size:.78rem;color:#64748B;">
                                <?= e($log['email'] ?? 'No email') ?>
                            </span>
                        </td>

                        <td>
                            <span class="badge">
                                <?= e(ucfirst($log['user_role'] ?? 'Unknown')) ?>
                            </span>
                        </td>

                        <td><?= e($log['action']) ?></td>

                        <td><?= e($log['target_table'] ?? 'N/A') ?></td>

                        <td><?= e($log['target_id'] ?? 'N/A') ?></td>

                        <td><?= e($log['ip_address'] ?? 'N/A') ?></td>

                        <td>
                            <?= !empty($log['created_at']) ? date('M d, Y h:i A', strtotime($log['created_at'])) : 'N/A' ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="print-signature">
    <div class="signature-box">
        <div class="signature-line"><?= e($adminName) ?></div>
        <div><?= e($roleLabel) ?></div>
    </div>
</div>

</div>

</main>

</body>
</html>
