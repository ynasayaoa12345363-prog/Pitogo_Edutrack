<?php
session_start();
require '../db.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int) $_SESSION['user_id'];
$currentRole = strtolower(trim((string) $_SESSION['role']));

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
        $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, user_role, action, target_table, target_id, ip_address, created_at) VALUES (?, 'student', ?, ?, ?, ?, NOW())");
        $stmt->execute([$userId, $action, $targetTable, $targetId, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Exception $e) {}
}

function ensureParentTables(PDO $pdo): void {
    if (!tableExists($pdo, 'parent_access_codes')) {
        $pdo->exec("CREATE TABLE parent_access_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            access_code VARCHAR(50) UNIQUE NOT NULL,
            status VARCHAR(30) DEFAULT 'active',
            expires_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_student_status (student_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    if (!tableExists($pdo, 'parent_student_links')) {
        $pdo->exec("CREATE TABLE parent_student_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            student_id INT NOT NULL,
            access_code VARCHAR(50) NULL,
            status VARCHAR(30) DEFAULT 'active',
            linked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uniq_parent_student (parent_id, student_id),
            INDEX idx_student_status (student_id, status),
            INDEX idx_parent_status (parent_id, status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }
}

ensureParentTables($pdo);

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
$message = '';
$messageType = '';

$linkedParentCount = safeCount($pdo, "
    SELECT COUNT(*)
    FROM parent_student_links
    WHERE student_id = ?
      AND status = 'active'
", [$currentUserId]);

$remainingParentSlots = max(0, 2 - $linkedParentCount);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'generate') {
    if ($linkedParentCount >= 2) {
        $message = 'Maximum limit reached. Only 2 parent accounts can be linked to a student.';
        $messageType = 'danger';
    } else {
        try {
            $pdo->beginTransaction();

            $pdo->prepare("UPDATE parent_access_codes SET status = 'expired' WHERE student_id = ? AND status = 'active'")
                ->execute([$currentUserId]);

            do {
                $code = 'PARENT-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
                $exists = safeCount($pdo, "SELECT COUNT(*) FROM parent_access_codes WHERE access_code = ?", [$code]);
            } while ($exists > 0);

            $stmt = $pdo->prepare("INSERT INTO parent_access_codes (student_id, access_code, status, expires_at, created_at) VALUES (?, ?, 'active', DATE_ADD(NOW(), INTERVAL 30 DAY), NOW())");
            $stmt->execute([$currentUserId, $code]);
            $newCodeId = (int)$pdo->lastInsertId();

            if (columnExists($pdo, 'users', 'parent_access_code')) {
                $pdo->prepare("UPDATE users SET parent_access_code = ? WHERE id = ?")->execute([$code, $currentUserId]);
            }

            logAudit($pdo, $currentUserId, 'Generated parent access code', 'parent_access_codes', $newCodeId);
            $pdo->commit();

            header("Location: parent-access.php?generated=1");
            exit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $message = 'Failed to generate access code. Please try again.';
            $messageType = 'danger';
        }
    }
}

if (isset($_GET['generated'])) {
    $message = 'New parent access code generated.';
    $messageType = 'success';
}

$active = safeFetchAll($pdo, "
    SELECT *
    FROM parent_access_codes
    WHERE student_id = ?
      AND status = 'active'
      AND (expires_at IS NULL OR expires_at >= NOW())
    ORDER BY created_at DESC
    LIMIT 1
", [$currentUserId]);

$history = safeFetchAll($pdo, "
    SELECT *
    FROM parent_access_codes
    WHERE student_id = ?
    ORDER BY created_at DESC
", [$currentUserId]);

$linkedParents = safeFetchAll($pdo, "
    SELECT psl.*, u.first_name, u.middle_name, u.last_name, u.email
    FROM parent_student_links psl
    LEFT JOIN users u ON u.id = psl.parent_id
    WHERE psl.student_id = ?
      AND psl.status = 'active'
    ORDER BY COALESCE(psl.linked_at, psl.created_at) DESC
", [$currentUserId]);

$code = $active[0]['access_code'] ?? ($student['parent_access_code'] ?? 'No active code');
$canGenerate = $linkedParentCount < 2;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Access | Pitogo EduTrack</title>
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

.access-code{font-size:1.6rem;font-weight:900;letter-spacing:2px;color:var(--primary-navy);word-break:break-all;background:#F8FAFC;border:1px dashed var(--primary-blue);padding:18px;border-radius:14px;text-align:center}

.alert{border-radius:16px;padding:15px 18px;margin-bottom:20px;font-weight:800;border:1px solid var(--border-color)}
.alert.success{border-color:#A7F3D0;color:#166534;background:#DCFCE7}.alert.danger{border-color:#FECACA;color:#991B1B;background:#FEE2E2}.btn-main:disabled{opacity:.55;cursor:not-allowed}.mini-note{font-size:.82rem;color:var(--text-muted);margin-top:10px;line-height:1.5}.parent-grid{display:grid;grid-template-columns:repeat(3,minmax(180px,1fr));gap:18px;margin-bottom:24px}@media(max-width:900px){.parent-grid{grid-template-columns:1fr}}
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
        <a href="dashboard.php" class="nav-item"><i class="fa fa-chart-line"></i><span>Dashboard</span></a>
<a href="attendance.php" class="nav-item"><i class="fa fa-calendar-check"></i><span>Attendance</span></a>
<a href="document-request.php" class="nav-item"><i class="fa fa-file-lines"></i><span>Document Request</span></a>
<a href="parent-access.php" class="nav-item active"><i class="fa fa-key"></i><span>Parent Access</span></a>
<a href="profile.php" class="nav-item"><i class="fa fa-user"></i><span>Profile</span></a>
<a href="settings.php" class="nav-item"><i class="fa fa-gear"></i><span>Settings</span></a>

        <div class="nav-label">Account</div>
        <a href="../logout.php" class="nav-item logout-link"><i class="fa fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>
</aside>
<main class="main-wrapper">
<header class="top-header">
    <div class="header-title">
        <h1>Parent Access Code</h1>
        <p>Connect parent account • Pitogo EduTrack</p>
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
            <h1>Parent Access Code</h1>
            <p>Generate a secure code your parent or guardian can use to connect their account to yours. A student can only have a maximum of 2 linked parent accounts.</p>
        </div>
    </section>

    <?php if ($message): ?>
        <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
    <?php endif; ?>

    <div class="parent-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:#DBEAFE;color:#1D4ED8"><i class="fa fa-users"></i></div>
            <div class="stat-info"><h3><?= e($linkedParentCount) ?>/2</h3><p>Linked Parents</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#DCFCE7;color:#166534"><i class="fa fa-user-plus"></i></div>
            <div class="stat-info"><h3><?= e($remainingParentSlots) ?></h3><p>Available Slots</p></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon" style="background:#FEF3C7;color:#92400E"><i class="fa fa-clock"></i></div>
            <div class="stat-info"><h3>30</h3><p>Code Days Valid</p></div>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="panel">
            <div class="panel-header"><h3>Current Active Code</h3></div>
            <div class="access-code" id="accessCode"><?= e($code) ?></div>
            <p class="mini-note">Share this only with your parent/guardian. Once 2 parent accounts are linked, code generation will be locked.</p><br>
            <div style="display:flex;gap:10px;flex-wrap:wrap;">
                <button class="btn-main" onclick="copyCode()" type="button"><i class="fa fa-copy"></i> Copy Code</button>
                <form method="POST">
                    <input type="hidden" name="action" value="generate">
                    <button class="btn-main" type="submit" style="background:var(--success);" <?= !$canGenerate ? 'disabled' : '' ?>>
                        <i class="fa fa-key"></i> Generate New Code
                    </button>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-header"><h3>How it works</h3></div>
            <div class="list">
                <div class="list-item"><div><h4>Step 1</h4><p>Generate or copy your active code.</p></div><span class="badge">Code</span></div>
                <div class="list-item"><div><h4>Step 2</h4><p>Give the code to your parent/guardian.</p></div><span class="badge approved">Share</span></div>
                <div class="list-item"><div><h4>Step 3</h4><p>Parent registers using this code.</p></div><span class="badge pending">Link</span></div>
            </div>
        </div>
    </div>

    <div class="panel" style="margin-bottom:24px;">
        <div class="panel-header"><h3>Linked Parent Accounts</h3></div>
        <div class="table-container">
            <table>
                <thead><tr><th>Parent Name</th><th>Email</th><th>Status</th><th>Linked Date</th></tr></thead>
                <tbody>
                <?php if ($linkedParents): foreach ($linkedParents as $parent): $pName = fullName($parent) ?: 'Parent Account'; ?>
                    <tr>
                        <td><strong><?= e($pName) ?></strong></td>
                        <td><?= e($parent['email'] ?? 'N/A') ?></td>
                        <td><span class="badge"><?= e($parent['status'] ?? 'active') ?></span></td>
                        <td><?= !empty($parent['linked_at']) ? e(date('M d, Y', strtotime($parent['linked_at']))) : (!empty($parent['created_at']) ? e(date('M d, Y', strtotime($parent['created_at']))) : 'N/A') ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4"><div class="empty-state">No linked parent account yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="panel">
        <div class="panel-header"><h3>Code History</h3></div>
        <div class="table-container">
            <table>
                <thead><tr><th>Code</th><th>Status</th><th>Expires</th><th>Created</th></tr></thead>
                <tbody>
                <?php if ($history): foreach ($history as $h): ?>
                    <tr>
                        <td><strong><?= e($h['access_code']) ?></strong></td>
                        <td><span class="badge <?= e($h['status']) ?>"><?= e($h['status']) ?></span></td>
                        <td><?= !empty($h['expires_at']) ? e(date('M d, Y', strtotime($h['expires_at']))) : 'No expiry' ?></td>
                        <td><?= !empty($h['created_at']) ? e(date('M d, Y', strtotime($h['created_at']))) : 'N/A' ?></td>
                    </tr>
                <?php endforeach; else: ?>
                    <tr><td colspan="4"><div class="empty-state">No code history yet.</div></td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</section>
</main>
<script>
function copyCode(){
    const code = document.getElementById('accessCode').innerText.trim();
    if (!code || code === 'No active code') {
        alert('No active code to copy.');
        return;
    }
    navigator.clipboard.writeText(code).then(() => alert('Parent access code copied.'));
}
</script>
</body>
</html>
