<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Staff QR Scanner
|--------------------------------------------------------------------------
| Save as:
| staff/qr-scanner.php
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

function fullName(array $row): string {
    return trim(($row['first_name'] ?? '') . ' ' . ($row['last_name'] ?? ''));
}

/* Database safety */
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

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmt->execute([$currentUserId]);
$staff = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

$staffName = fullName($staff) ?: 'Staff User';

$staffAvatar = !empty($staff['profile_picture'])
    ? '../uploads/profiles/' . $staff['profile_picture']
    : 'https://ui-avatars.com/api/?name=' . urlencode($staffName) . '&background=0056B3&color=fff';

$totalScansToday = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE attendance_date = CURDATE()
    AND scanned_by = ?
", [$currentUserId]);

$totalStudentsToday = safeCount($pdo, "
    SELECT COUNT(DISTINCT student_id)
    FROM attendance
    WHERE attendance_date = CURDATE()
");

$totalScansMonth = safeCount($pdo, "
    SELECT COUNT(*)
    FROM attendance
    WHERE MONTH(attendance_date) = MONTH(CURDATE())
    AND YEAR(attendance_date) = YEAR(CURDATE())
    AND scanned_by = ?
", [$currentUserId]);

$recentScans = [];
try {
    $recent = $pdo->prepare("
        SELECT 
            a.*,
            u.first_name,
            u.last_name,
            u.id_num
        FROM attendance a
        JOIN users u ON u.id = a.student_id
        WHERE a.scanned_by = ?
        ORDER BY a.created_at DESC
        LIMIT 8
    ");
    $recent->execute([$currentUserId]);
    $recentScans = $recent->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $recentScans = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>QR Scanner | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>

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

.live-clock {
    background:rgba(255,255,255,.14);
    border:1px solid rgba(255,255,255,.25);
    padding:14px 18px;
    border-radius:16px;
    font-weight:900;
    font-size:1rem;
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(3, minmax(180px, 1fr));
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

.scanner-grid {
    display:grid;
    grid-template-columns:1fr .8fr;
    gap:22px;
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
    font-size:1.15rem;
}

.scanner-box {
    background:#F8FAFC;
    border:2px dashed var(--border-color);
    border-radius:18px;
    padding:18px;
    min-height:420px;
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
}

#reader {
    width:100%;
    max-width:520px;
}

.scan-controls {
    display:flex;
    gap:12px;
    flex-wrap:wrap;
    margin-top:18px;
}

.btn-main {
    border:none;
    border-radius:12px;
    padding:13px 18px;
    font-weight:900;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:8px;
    font-family:'Montserrat',sans-serif;
}

.btn-start {
    background:var(--primary-blue);
    color:white;
}

.btn-stop {
    background:var(--danger);
    color:white;
}

.btn-history {
    background:#fff;
    color:var(--primary-blue);
    border:1px solid var(--border-color);
    text-decoration:none;
}

.result-box {
    margin-top:18px;
    border-radius:16px;
    padding:18px;
    display:none;
    border:1px solid transparent;
}

.result-box.success {
    display:block;
    background:#DCFCE7;
    border-color:#BBF7D0;
    color:#166534;
}

.result-box.warning {
    display:block;
    background:#FEF3C7;
    border-color:#FDE68A;
    color:#92400E;
}

.result-box.danger {
    display:block;
    background:#FEE2E2;
    border-color:#FECACA;
    color:#991B1B;
}

.result-box h4 {
    margin-bottom:6px;
}

.manual-box {
    margin-top:18px;
    padding-top:18px;
    border-top:1px solid var(--border-color);
}

.manual-box label {
    display:block;
    font-weight:900;
    color:var(--primary-navy);
    margin-bottom:8px;
    font-size:.85rem;
}

.manual-row {
    display:flex;
    gap:10px;
}

.manual-row input {
    flex:1;
    padding:12px 14px;
    border:1px solid var(--border-color);
    border-radius:10px;
    background:#F8FAFC;
    outline:none;
}

.manual-row button {
    padding:12px 16px;
    border:none;
    border-radius:10px;
    background:var(--primary-blue);
    color:white;
    font-weight:900;
    cursor:pointer;
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

@media(max-width:1100px) {
    .scanner-grid {
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

    .manual-row {
        flex-direction:column;
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
        <div class="nav-label">Scanner</div>

        <a href="qr-scanner.php" class="nav-item active">
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
        <h1>QR Attendance Scanner</h1>
        <p>Staff Access • Pitogo EduTrack</p>
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
            <h1>Entrance QR Scanner</h1>
            <p>Scan the student QR code at the entrance to automatically mark attendance for today.</p>
        </div>

        <div class="live-clock" id="liveClock">
            Loading time...
        </div>
    </section>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(0,86,179,.1); color:var(--primary-blue);">
                <i class="fa fa-qrcode"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalScansToday) ?></h3>
                <p>Your Scans Today</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1); color:var(--success);">
                <i class="fa fa-user-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalStudentsToday) ?></h3>
                <p>Students Present Today</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1); color:var(--warning);">
                <i class="fa fa-calendar"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalScansMonth) ?></h3>
                <p>Your Scans This Month</p>
            </div>
        </div>
    </div>

    <div class="scanner-grid">
        <div class="panel">
            <div class="panel-header">
                <h3><i class="fa fa-camera"></i> Live QR Scanner</h3>
            </div>

            <div class="scanner-box">
                <div id="reader"></div>
            </div>

            <div class="scan-controls">
                <button class="btn-main btn-start" onclick="startScanner()">
                    <i class="fa fa-play"></i>
                    Start Scanner
                </button>

                <button class="btn-main btn-stop" onclick="stopScanner()">
                    <i class="fa fa-stop"></i>
                    Stop Scanner
                </button>

                <a href="scanner-history.php" class="btn-main btn-history">
                    <i class="fa fa-clock-rotate-left"></i>
                    View History
                </a>
            </div>

            <div id="scanResult" class="result-box"></div>

            <div class="manual-box">
                <label>Manual QR Token Input</label>
                <div class="manual-row">
                    <input type="text" id="manualToken" placeholder="Paste QR token here">
                    <button type="button" onclick="manualMarkAttendance()">
                        Submit
                    </button>
                </div>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h3><i class="fa fa-list"></i> Recent Scans</h3>
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
                        No scans yet.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

</section>

</main>

<script>
let html5QrCode = null;
let isProcessing = false;
let scannerRunning = false;
let lastScannedToken = "";
let lastScannedTime = 0;

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

function showResult(type, title, message) {
    const box = document.getElementById('scanResult');
    box.className = 'result-box ' + type;

    box.innerHTML = `
        <h4>${title}</h4>
        <p>${message}</p>
    `;
}

async function markAttendance(qrToken) {
    const now = Date.now();

    if (isProcessing) return;

    if (qrToken === lastScannedToken && now - lastScannedTime < 3000) {
        return;
    }

    lastScannedToken = qrToken;
    lastScannedTime = now;
    isProcessing = true;

    showResult('warning', 'Processing...', 'Checking QR code and marking attendance.');

    try {
        const response = await fetch('mark-attendance.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'qr_token=' + encodeURIComponent(qrToken)
        });

        const result = await response.json();

        if (result.success) {
            showResult(
                'success',
                result.title || 'Attendance Marked',
                result.message || 'Student attendance was recorded successfully.'
            );
        } else {
            showResult(
                result.type || 'danger',
                result.title || 'Scan Failed',
                result.message || 'Invalid QR code.'
            );
        }

    } catch (error) {
        showResult('danger', 'Network Error', 'Unable to process QR scan. Please try again.');
    } finally {
        setTimeout(() => {
            isProcessing = false;
        }, 1500);
    }
}

function onScanSuccess(decodedText) {
    markAttendance(decodedText);
}

function onScanFailure(error) {
    // Silent scan failure.
}

function startScanner() {
    if (scannerRunning) {
        showResult('warning', 'Scanner Already Running', 'The QR scanner is already open.');
        return;
    }

    html5QrCode = new Html5Qrcode("reader");

    Html5Qrcode.getCameras().then(cameras => {
        if (!cameras || cameras.length === 0) {
            showResult('danger', 'Camera Not Found', 'No camera was detected on this device.');
            html5QrCode = null;
            return;
        }

        const cameraId = cameras[0].id;

        html5QrCode.start(
            cameraId,
            {
                fps: 10,
                qrbox: {
                    width: 260,
                    height: 260
                }
            },
            onScanSuccess,
            onScanFailure
        ).then(() => {
            scannerRunning = true;
            showResult('warning', 'Scanner Started', 'Scanner will stay open until you click Stop Scanner.');
        }).catch(() => {
            scannerRunning = false;
            html5QrCode = null;
            showResult('danger', 'Camera Error', 'Please allow camera access and try again.');
        });

    }).catch(() => {
        scannerRunning = false;
        html5QrCode = null;
        showResult('danger', 'Camera Error', 'Please allow camera access and try again.');
    });
}

function stopScanner() {
    if (!html5QrCode || !scannerRunning) {
        showResult('warning', 'Scanner Not Running', 'The scanner is currently not active.');
        return;
    }

    html5QrCode.stop().then(() => {
        html5QrCode.clear();
        html5QrCode = null;
        scannerRunning = false;
        showResult('warning', 'Scanner Stopped', 'QR scanner has been stopped.');
    }).catch(() => {
        showResult('danger', 'Stop Failed', 'Unable to stop scanner.');
    });
}

function manualMarkAttendance() {
    const token = document.getElementById('manualToken').value.trim();

    if (!token) {
        showResult('danger', 'Missing Token', 'Please paste or type a QR token first.');
        return;
    }

    markAttendance(token);
}
</script>

</body>
</html>
