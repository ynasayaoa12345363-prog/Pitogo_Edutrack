<?php
session_start();
require '../db.php';

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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
        $stmt->execute([$table]);
        return (int)$stmt->fetchColumn() > 0;
    } catch (Exception $e) {
        return false;
    }
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?");
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
        if (preg_match('/^https?:\/\//i', $profile)) return $profile;
        return '../uploads/profiles/' . ltrim($profile, '/');
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'Student') . '&background=0056B3&color=fff';
}

function logAudit(PDO $pdo, int $userId, string $action, string $targetTable = 'users', ?int $targetId = null): void {
    if (!tableExists($pdo, 'audit_logs')) return;
    try {
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id,user_role,action,target_table,target_id,ip_address,created_at) VALUES (?, 'student', ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $targetTable, $targetId, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {}
}

$stmtStudent = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1");
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
?>
<?php
if (!tableExists($pdo, 'attendance')) {
    $pdo->exec("CREATE TABLE attendance (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NOT NULL, attendance_date DATE NOT NULL, time_in TIME NULL, status VARCHAR(30) DEFAULT 'present', scanned_by INT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, UNIQUE KEY unique_student_date (student_id, attendance_date))");
}

if (!tableExists($pdo, 'document_requests')) {
    $pdo->exec("CREATE TABLE document_requests (id INT AUTO_INCREMENT PRIMARY KEY, student_id INT NULL, requested_by INT NULL, tracking_id VARCHAR(80) NULL, document_type VARCHAR(150) NOT NULL, purpose TEXT NULL, status VARCHAR(30) DEFAULT 'pending', release_method VARCHAR(50) NULL, file_path VARCHAR(255) NULL, access_otp VARCHAR(10) NULL, otp_used TINYINT(1) DEFAULT 0, otp_verified TINYINT(1) DEFAULT 0, pickup_date DATE NULL, instructions TEXT NULL, remarks TEXT NULL, processed_by INT NULL, processed_at DATETIME NULL, archived_at DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP)");
}

$presentThisMonth = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id = ? AND MONTH(attendance_date)=MONTH(CURDATE()) AND YEAR(attendance_date)=YEAR(CURDATE())", [$currentUserId]);
$presentToday = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id = ? AND attendance_date = CURDATE()", [$currentUserId]);
$totalRequests = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE student_id = ? OR requested_by = ?", [$currentUserId, $currentUserId]);
$pendingRequests = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE (student_id = ? OR requested_by = ?) AND status='pending'", [$currentUserId, $currentUserId]);
$releasedRequests = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE (student_id = ? OR requested_by = ?) AND status='released'", [$currentUserId, $currentUserId]);

$monthDays = (int)date('t');
$currentDay = (int)date('j');
$attendanceRate = $currentDay > 0 ? round(($presentThisMonth / $currentDay) * 100) : 0;

$weekRows = safeFetchAll($pdo, "SELECT attendance_date, COUNT(*) AS total FROM attendance WHERE student_id=? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY attendance_date", [$currentUserId]);
$weekMap = [];
foreach ($weekRows as $r) $weekMap[$r['attendance_date']] = (int)$r['total'];
$weekLabels=[]; $weekCounts=[];
for($i=6;$i>=0;$i--){ $d=date('Y-m-d', strtotime("-$i days")); $weekLabels[]=date('D', strtotime($d)); $weekCounts[]=$weekMap[$d]??0; }

$docRows = safeFetchAll($pdo, "SELECT status, COUNT(*) AS total FROM document_requests WHERE student_id=? OR requested_by=? GROUP BY status", [$currentUserId, $currentUserId]);
$docLabels=[]; $docCounts=[];
if($docRows){ foreach($docRows as $r){$docLabels[]=ucfirst($r['status']);$docCounts[]=(int)$r['total'];}} else {$docLabels=['No Requests'];$docCounts=[0];}

$monthlyRows = safeFetchAll($pdo, "SELECT DATE_FORMAT(attendance_date, '%b') AS month_name, MONTH(attendance_date) AS month_no, COUNT(*) AS total FROM attendance WHERE student_id=? AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH) GROUP BY MONTH(attendance_date), DATE_FORMAT(attendance_date, '%b') ORDER BY month_no ASC", [$currentUserId]);
$monthLabels=[]; $monthCounts=[];
foreach($monthlyRows as $r){$monthLabels[]=$r['month_name'];$monthCounts[]=(int)$r['total'];}
if(!$monthLabels){$monthLabels=[date('M')];$monthCounts=[0];}

$recentAttendance = safeFetchAll($pdo, "SELECT * FROM attendance WHERE student_id=? ORDER BY created_at DESC LIMIT 5", [$currentUserId]);
$recentDocs = safeFetchAll($pdo, "SELECT * FROM document_requests WHERE student_id=? OR requested_by=? ORDER BY created_at DESC LIMIT 5", [$currentUserId, $currentUserId]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Student Dashboard | Pitogo EduTrack</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css"><script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

*{margin:0;padding:0;box-sizing:border-box}
body{
    font-family:'Inter',sans-serif;
    background:var(--bg-main);
    color:var(--text-dark);
    display:flex;
    min-height:100vh;
    overflow-x:hidden;
}
h1,h2,h3,h4{font-family:'Montserrat',sans-serif}

.sidebar{
    width:82px;height:100vh;background:var(--surface-white);
    border-right:1px solid var(--border-color);
    position:fixed;left:0;top:0;z-index:100;
    transition:width .35s ease;overflow-x:hidden;display:flex;flex-direction:column;
}
.sidebar:hover{width:285px;box-shadow:10px 0 30px rgba(15,23,42,0.08)}
.sidebar-brand{padding:24px 17px;display:flex;align-items:center;gap:15px;border-bottom:1px solid var(--border-color)}
.logo-box{min-width:48px;height:48px;border-radius:14px;background:#fff;border:1px solid var(--border-color);overflow:hidden;display:flex;align-items:center;justify-content:center}
.logo-box img{width:100%;height:100%;object-fit:contain}
.brand-text,.nav-label,.nav-item span{opacity:0;visibility:hidden;transition:opacity .2s ease}
.sidebar:hover .brand-text,.sidebar:hover .nav-label,.sidebar:hover .nav-item span{opacity:1;visibility:visible}
.brand-text h2{font-size:1.05rem;color:var(--primary-navy);font-weight:800}
.brand-text p{font-size:.72rem;color:var(--primary-blue);font-weight:800;text-transform:uppercase;letter-spacing:.8px}
.nav-menu{padding:22px 14px;display:flex;flex-direction:column;gap:5px;flex:1}
.nav-label{margin:14px 0 8px 14px;font-size:.72rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}
.nav-item{padding:14px 10px;border-radius:13px;display:flex;align-items:center;gap:20px;text-decoration:none;color:var(--text-muted);font-size:.94rem;font-weight:750;transition:var(--transition)}
.nav-item i{min-width:30px;text-align:center;font-size:1.17rem}
.nav-item:hover{background:var(--bg-main);color:var(--primary-blue);transform:translateX(5px)}
.nav-item.active{background:rgba(0,86,179,.08);color:var(--primary-blue)}
.logout-link{margin-top:auto;color:var(--danger)}

.main-wrapper{margin-left:82px;flex:1;min-width:0}
.top-header{height:88px;background:var(--surface-white);border-bottom:1px solid var(--border-color);padding:0 38px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}
.header-title h1{font-size:1.35rem;color:var(--primary-navy)}
.header-title p{font-size:.85rem;color:var(--text-muted);margin-top:4px}
.user-profile{display:flex;align-items:center;gap:14px}
.user-info{text-align:right}
.user-info h4{font-size:.9rem;color:var(--primary-navy)}
.user-info p{font-size:.75rem;color:var(--text-muted)}
.avatar{width:46px;height:46px;object-fit:cover;border-radius:50%;border:2px solid var(--primary-blue);padding:2px}

.content-area{padding:38px;max-width:1500px;margin:0 auto}
.hero-card{background:linear-gradient(135deg,rgba(11,28,61,.96),rgba(0,86,179,.78)),url('../school-bg.jpg');background-size:cover;background-position:center;color:white;border-radius:24px;padding:34px;margin-bottom:28px;display:flex;justify-content:space-between;gap:25px;align-items:center;flex-wrap:wrap;box-shadow:var(--shadow-md)}
.hero-card h1{font-size:2rem;font-weight:800;margin-bottom:8px}
.hero-card p{color:rgba(255,255,255,.86);line-height:1.6;max-width:850px}
.hero-actions{display:flex;gap:10px;flex-wrap:wrap}
.btn-white,.btn-outline{padding:12px 16px;border-radius:12px;text-decoration:none;font-weight:900;display:inline-flex;align-items:center;gap:8px;border:none;cursor:pointer;font-family:'Montserrat',sans-serif}
.btn-white{background:#fff;color:var(--primary-blue)}
.btn-outline{border:1px solid rgba(255,255,255,.3);color:#fff;background:rgba(255,255,255,.12)}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:18px;margin-bottom:24px}
.stat-card{background:var(--surface-white);padding:22px;border-radius:18px;border:1px solid var(--border-color);display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-sm);transition:var(--transition)}
.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md)}
.stat-icon{width:56px;height:56px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:1.3rem}
.stat-info h3{font-size:1.65rem;font-weight:800;color:var(--primary-navy)}
.stat-info p{font-size:.78rem;color:var(--text-muted);font-weight:800;text-transform:uppercase}
.dashboard-grid{display:grid;grid-template-columns:1.2fr .8fr;gap:22px;margin-bottom:24px}
.panel{background:var(--surface-white);border:1px solid var(--border-color);border-radius:22px;padding:24px;box-shadow:var(--shadow-sm)}
.panel-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}
.panel-header h3{color:var(--primary-navy);font-size:1.05rem}
.panel-header a{color:var(--primary-blue);font-size:.82rem;font-weight:900;text-decoration:none}
.chart-box{height:310px}
.list{display:flex;flex-direction:column;gap:12px}
.list-item{padding:14px;border:1px solid var(--border-color);border-radius:15px;background:#F8FAFC;display:flex;justify-content:space-between;gap:12px;align-items:center}
.list-item h4{color:var(--primary-navy);font-size:.92rem}
.list-item p{color:var(--text-muted);font-size:.78rem;margin-top:3px}
.badge{padding:7px 10px;border-radius:999px;font-size:.72rem;font-weight:900;background:#DCFCE7;color:#166534;text-transform:capitalize;white-space:nowrap}
.badge.pending{background:#FEF3C7;color:#92400E}.badge.approved{background:#DBEAFE;color:#1D4ED8}.badge.released,.badge.present{background:#DCFCE7;color:#166534}.badge.rejected,.badge.absent{background:#FEE2E2;color:#991B1B}
.empty-state{text-align:center;color:var(--text-muted);padding:28px 10px}
.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.full-width{grid-column:span 2}
.form-group label{display:block;margin-bottom:7px;font-size:.82rem;font-weight:900;color:var(--primary-navy)}
.form-control{width:100%;padding:12px 14px;border:1px solid var(--border-color);border-radius:10px;background:#F8FAFC;outline:none;font-family:'Inter',sans-serif}
textarea.form-control{min-height:100px;resize:vertical}
.btn-main{border:none;border-radius:12px;background:var(--primary-blue);color:white;padding:13px 18px;font-weight:900;cursor:pointer;font-family:'Montserrat',sans-serif;display:inline-flex;align-items:center;gap:8px;text-decoration:none}
.table-container{background:#fff;border:1px solid var(--border-color);border-radius:18px;overflow:auto;box-shadow:var(--shadow-sm)}
table{width:100%;border-collapse:collapse;min-width:900px}
th{background:#F8FAFC;padding:16px 18px;font-size:.78rem;font-weight:900;color:var(--text-muted);text-transform:uppercase;border-bottom:2px solid var(--border-color);text-align:left}
td{padding:15px 18px;border-bottom:1px solid var(--border-color);vertical-align:middle}

@media(max-width:1150px){.stats-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}}
@media(max-width:760px){.sidebar{display:none}.main-wrapper{margin-left:0}.top-header{padding:0 20px}.content-area{padding:24px 18px}.stats-grid{grid-template-columns:1fr}.user-info{display:none}.form-grid{grid-template-columns:1fr}.full-width{grid-column:span 1}}
</style>
</head><body>

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
        <a href="dashboard.php" class="nav-item active"><i class="fa fa-chart-line"></i><span>Dashboard</span></a>
<a href="attendance.php" class="nav-item"><i class="fa fa-calendar-check"></i><span>Attendance</span></a>
<a href="document-request.php" class="nav-item"><i class="fa fa-file-lines"></i><span>Document Request</span></a>
<a href="parent-access.php" class="nav-item"><i class="fa fa-key"></i><span>Parent Access</span></a>
<a href="profile.php" class="nav-item"><i class="fa fa-user"></i><span>Profile</span></a>
<a href="settings.php" class="nav-item"><i class="fa fa-gear"></i><span>Settings</span></a>

        <div class="nav-label">Account</div>
        <a href="../logout.php" class="nav-item logout-link"><i class="fa fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>
</aside>
<main class="main-wrapper">
<header class="top-header">
    <div class="header-title">
        <h1>Student Dashboard</h1>
        <p>Personal analytics • Pitogo EduTrack</p>
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
            <h1>Welcome, <?= e($studentName) ?></h1>
            <p>Track your QR attendance, document request status, and personal school records in one organized dashboard.</p>
        </div>
        <div class="hero-actions">
            <a href="attendance.php" class="btn-white"><i class="fa fa-calendar-check"></i> View Attendance</a>
            <a href="document-request.php" class="btn-outline"><i class="fa fa-file-lines"></i> Request Document</a>
        </div>
    </section>

    <div class="stats-grid">
        <div class="stat-card"><div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success);"><i class="fa fa-user-check"></i></div><div class="stat-info"><h3><?= number_format($presentThisMonth) ?></h3><p>Present This Month</p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(0,86,179,.1);color:var(--primary-blue);"><i class="fa fa-calendar-day"></i></div><div class="stat-info"><h3><?= $presentToday ? 'Yes' : 'No' ?></h3><p>Scanned Today</p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning);"><i class="fa fa-file-lines"></i></div><div class="stat-info"><h3><?= number_format($pendingRequests) ?></h3><p>Pending Documents</p></div></div>
        <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--info);"><i class="fa fa-percent"></i></div><div class="stat-info"><h3><?= e($attendanceRate) ?>%</h3><p>Monthly Rate</p></div></div>
    </div>

    <div class="dashboard-grid">
        <div class="panel"><div class="panel-header"><h3>Attendance Trend in the Last 7 Days</h3></div><div class="chart-box"><canvas id="weekChart"></canvas></div></div>
        <div class="panel"><div class="panel-header"><h3>Document Request Status</h3></div><div class="chart-box"><canvas id="docChart"></canvas></div></div>
    </div>

    <div class="dashboard-grid">
        <div class="panel"><div class="panel-header"><h3>Monthly Attendance Overview</h3></div><div class="chart-box"><canvas id="monthChart"></canvas></div></div>
        <div class="panel">
            <div class="panel-header"><h3>Student Information</h3><a href="profile.php">View Profile</a></div>
            <div class="list">
                <div class="list-item"><div><h4>LRN / ID Number</h4><p><?= e($studentLrn) ?></p></div><span class="badge">Active</span></div>
                <div class="list-item"><div><h4>Email</h4><p><?= e($student['email'] ?? 'N/A') ?></p></div><span class="badge approved">Verified</span></div>
                <div class="list-item"><div><h4>Total Requests</h4><p><?= number_format($totalRequests) ?> document request(s)</p></div><span class="badge pending"><?= number_format($releasedRequests) ?> released</span></div>
            </div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header"><h3>Recent Attendance</h3><a href="attendance.php">View All</a></div>
            <div class="list">
                <?php if($recentAttendance): foreach($recentAttendance as $a): ?>
                    <div class="list-item"><div><h4><?= e(date('M d, Y', strtotime($a['attendance_date']))) ?></h4><p><?= !empty($a['time_in']) ? e(date('h:i A', strtotime($a['time_in']))) : 'No time recorded' ?></p></div><span class="badge <?= e($a['status']) ?>"><?= e($a['status']) ?></span></div>
                <?php endforeach; else: ?><div class="empty-state">No attendance records yet.</div><?php endif; ?>
            </div>
        </div>
        <div class="panel">
            <div class="panel-header"><h3>Recent Document Requests</h3><a href="document-request.php">View All</a></div>
            <div class="list">
                <?php if($recentDocs): foreach($recentDocs as $d): ?>
                    <div class="list-item"><div><h4><?= e($d['document_type']) ?></h4><p><?= e($d['purpose'] ?: 'No purpose provided') ?></p></div><span class="badge <?= e($d['status']) ?>"><?= e($d['status']) ?></span></div>
                <?php endforeach; else: ?><div class="empty-state">No document requests yet.</div><?php endif; ?>
            </div>
        </div>
    </div>
</section></main>
<script>
function makeChart(id,type,labels,data,label){
    new Chart(document.getElementById(id),{type,data:{labels,datasets:[{label,data,borderWidth:2,tension:.35,fill:type==='line',backgroundColor:type==='doughnut'?['#10B981','#F59E0B','#3B82F6','#EF4444']:'rgba(0,86,179,.15)',borderColor:type==='doughnut'?'#fff':'#0056B3'}]},options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:type==='doughnut'}},scales:type==='doughnut'?{}:{y:{beginAtZero:true,ticks:{precision:0}}}}});
}
makeChart('weekChart','line',<?= json_encode($weekLabels) ?>,<?= json_encode($weekCounts) ?>,'Attendance');
makeChart('docChart','doughnut',<?= json_encode($docLabels) ?>,<?= json_encode($docCounts) ?>,'Documents');
makeChart('monthChart','bar',<?= json_encode($monthLabels) ?>,<?= json_encode($monthCounts) ?>,'Monthly Attendance');
</script></body></html>
