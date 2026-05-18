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

if ($currentRole !== 'parent') {
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

function profilePath(array $row, string $name, string $fallback = 'Parent'): string {
    $profile = $row['profile_picture'] ?? $row['profile_pic'] ?? '';
    if ($profile) {
        if (preg_match('/^https?:\/\//i', $profile)) return $profile;
        return '../uploads/profiles/' . ltrim($profile, '/');
    }
    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: $fallback) . '&background=0056B3&color=fff';
}

try {
    if (!tableExists($pdo, 'parent_access_codes')) {
        $pdo->exec("CREATE TABLE parent_access_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            access_code VARCHAR(20) NOT NULL UNIQUE,
            status VARCHAR(30) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NULL
        )");
    }

    if (!tableExists($pdo, 'parent_student_links')) {
        $pdo->exec("CREATE TABLE parent_student_links (
            id INT AUTO_INCREMENT PRIMARY KEY,
            parent_id INT NOT NULL,
            student_id INT NOT NULL,
            status VARCHAR(30) DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            linked_at DATETIME NULL
        )");
    }

    if (!tableExists($pdo, 'attendance')) {
        $pdo->exec("CREATE TABLE attendance (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NOT NULL,
            attendance_date DATE NOT NULL,
            time_in TIME NULL,
            status VARCHAR(30) DEFAULT 'present',
            scanned_by INT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_student_date (student_id, attendance_date)
        )");
    }

    if (!tableExists($pdo, 'document_requests')) {
        $pdo->exec("CREATE TABLE document_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT NULL,
            requested_by INT NULL,
            tracking_id VARCHAR(80) NULL,
            document_type VARCHAR(150) NOT NULL,
            purpose TEXT NULL,
            status VARCHAR(30) DEFAULT 'pending',
            release_method VARCHAR(50) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )");
    }

    $codeColumns = [
        'status' => "VARCHAR(30) DEFAULT 'active'",
        'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        'expires_at' => "DATETIME NULL"
    ];
    foreach ($codeColumns as $column => $definition) {
        if (!columnExists($pdo, 'parent_access_codes', $column)) {
            $pdo->exec("ALTER TABLE parent_access_codes ADD COLUMN $column $definition");
        }
    }

    $linkColumns = [
        'status' => "VARCHAR(30) DEFAULT 'active'",
        'created_at' => "DATETIME DEFAULT CURRENT_TIMESTAMP",
        'linked_at' => "DATETIME NULL"
    ];
    foreach ($linkColumns as $column => $definition) {
        if (!columnExists($pdo, 'parent_student_links', $column)) {
            $pdo->exec("ALTER TABLE parent_student_links ADD COLUMN $column $definition");
        }
    }
} catch (Exception $e) {
    die("Database setup error: " . e($e->getMessage()));
}

$stmtParent = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'parent' LIMIT 1");
$stmtParent->execute([$currentUserId]);
$parent = $stmtParent->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
    session_destroy();
    header("Location: ../index.html");
    exit();
}

$parentName = fullName($parent) ?: 'Parent';
$firstName = $parent['first_name'] ?? 'Parent';
$avatarUrl = profilePath($parent, $parentName, 'Parent');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['link_child'])) {
    $firstNameInput = trim($_POST['first_name'] ?? '');
    $lastNameInput = trim($_POST['last_name'] ?? '');
    $accessCode = strtoupper(trim($_POST['access_code'] ?? ''));

    if ($firstNameInput === '' || $lastNameInput === '' || $accessCode === '') {
        header("Location: dashboard.php?msg=" . urlencode("Error: Please complete all fields."));
        exit();
    }

    try {
        $stmtStudent = $pdo->prepare("SELECT u.id, u.first_name, u.last_name
            FROM users u
            JOIN parent_access_codes pac ON pac.student_id = u.id
            WHERE LOWER(u.first_name) = LOWER(?)
            AND LOWER(u.last_name) = LOWER(?)
            AND UPPER(pac.access_code) = ?
            AND pac.status = 'active'
            AND (pac.expires_at IS NULL OR pac.expires_at > NOW())
            AND u.role = 'student'
            LIMIT 1");
        $stmtStudent->execute([$firstNameInput, $lastNameInput, $accessCode]);
        $studentToLink = $stmtStudent->fetch(PDO::FETCH_ASSOC);

        if (!$studentToLink) {
            header("Location: dashboard.php?msg=" . urlencode("Error: Invalid student name or access code."));
            exit();
        }

        $stmtExists = $pdo->prepare("SELECT id FROM parent_student_links WHERE parent_id = ? AND student_id = ? AND status = 'active' LIMIT 1");
        $stmtExists->execute([$currentUserId, $studentToLink['id']]);
        if ($stmtExists->fetch()) {
            header("Location: dashboard.php?msg=" . urlencode($studentToLink['first_name'] . " is already linked to your account."));
            exit();
        }

        $stmtSlots = $pdo->prepare("SELECT COUNT(*) FROM parent_student_links WHERE student_id = ? AND status = 'active'");
        $stmtSlots->execute([$studentToLink['id']]);
        if ((int)$stmtSlots->fetchColumn() >= 2) {
            header("Location: dashboard.php?msg=" . urlencode("Error: This student already has two linked parent accounts."));
            exit();
        }

        $pdo->beginTransaction();
        $stmtInsert = $pdo->prepare("INSERT INTO parent_student_links (parent_id, student_id, status, created_at, linked_at) VALUES (?, ?, 'active', NOW(), NOW())");
        $stmtInsert->execute([$currentUserId, $studentToLink['id']]);

        $stmtCode = $pdo->prepare("UPDATE parent_access_codes SET status = 'used' WHERE student_id = ? AND UPPER(access_code) = ?");
        $stmtCode->execute([$studentToLink['id'], $accessCode]);
        $pdo->commit();

        header("Location: dashboard.php?msg=" . urlencode("Successfully linked " . $studentToLink['first_name'] . " " . $studentToLink['last_name'] . "."));
        exit();
    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        header("Location: dashboard.php?msg=" . urlencode("DB Error: " . $e->getMessage()));
        exit();
    }
}

try {
    if (!empty($parent['parent_access_code'])) {
        $savedAccessCode = strtoupper(trim($parent['parent_access_code']));
        $autoStmt = $pdo->prepare("SELECT pac.student_id FROM parent_access_codes pac JOIN users s ON s.id = pac.student_id WHERE UPPER(pac.access_code) = ? AND pac.status IN ('active','used') AND s.role = 'student' LIMIT 1");
        $autoStmt->execute([$savedAccessCode]);
        $auto = $autoStmt->fetch(PDO::FETCH_ASSOC);
        if ($auto) {
            $exists = safeCount($pdo, "SELECT COUNT(*) FROM parent_student_links WHERE parent_id = ? AND student_id = ? AND status = 'active'", [$currentUserId, $auto['student_id']]);
            if ($exists === 0) {
                $insertAuto = $pdo->prepare("INSERT INTO parent_student_links (parent_id, student_id, status, created_at, linked_at) VALUES (?, ?, 'active', NOW(), NOW())");
                $insertAuto->execute([$currentUserId, $auto['student_id']]);
            }
        }
    }
} catch (Exception $e) {}

$linkedChildren = safeFetchAll($pdo, "SELECT u.*, psl.linked_at
    FROM users u
    JOIN parent_student_links psl ON psl.student_id = u.id
    WHERE psl.parent_id = ? AND psl.status = 'active'
    ORDER BY u.last_name ASC", [$currentUserId]);

$childIds = array_map(fn($child) => (int)$child['id'], $linkedChildren);
$totalChildren = count($linkedChildren);
$displayMessage = isset($_GET['msg']) ? e($_GET['msg']) : '';
$isError = stripos($displayMessage, 'error') !== false || stripos($displayMessage, 'DB Error') !== false;
$isProfileIncomplete = empty(trim($parent['contact_number'] ?? '')) || empty(trim($parent['address'] ?? '')) || empty(trim($parent['profile_pic'] ?? $parent['profile_picture'] ?? ''));

$todayAttendanceLogs = 0;
$presentTotal = 0;
$absentTotal = 0;
$lateTotal = 0;
$pendingDocuments = 0;
$totalRequests = 0;
$weekLabels = [];
$weekCounts = [];
$docLabels = ['Pending','Approved','Released','Rejected'];
$docCounts = [0,0,0,0];
$recentAttendance = [];
$recentDocs = [];

if ($childIds) {
    $placeholders = implode(',', array_fill(0, count($childIds), '?'));
    $todayAttendanceLogs = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id IN ($placeholders) AND attendance_date = CURDATE()", $childIds);
    $presentTotal = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id IN ($placeholders) AND LOWER(status) = 'present' AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)", $childIds);
    $absentTotal = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id IN ($placeholders) AND LOWER(status) = 'absent' AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)", $childIds);
    $lateTotal = safeCount($pdo, "SELECT COUNT(*) FROM attendance WHERE student_id IN ($placeholders) AND LOWER(status) = 'late' AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 29 DAY)", $childIds);
    $pendingDocuments = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE (student_id IN ($placeholders) OR requested_by = ?) AND LOWER(status) = 'pending'", array_merge($childIds, [$currentUserId]));
    $totalRequests = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE student_id IN ($placeholders) OR requested_by = ?", array_merge($childIds, [$currentUserId]));

    $weekRows = safeFetchAll($pdo, "SELECT attendance_date, COUNT(*) AS total FROM attendance WHERE student_id IN ($placeholders) AND attendance_date >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) GROUP BY attendance_date", $childIds);
    $weekMap = [];
    foreach ($weekRows as $row) $weekMap[$row['attendance_date']] = (int)$row['total'];
    for ($i = 6; $i >= 0; $i--) {
        $date = date('Y-m-d', strtotime("-$i days"));
        $weekLabels[] = date('D', strtotime($date));
        $weekCounts[] = $weekMap[$date] ?? 0;
    }

    $docRows = safeFetchAll($pdo, "SELECT LOWER(status) AS status_name, COUNT(*) AS total FROM document_requests WHERE student_id IN ($placeholders) OR requested_by = ? GROUP BY LOWER(status)", array_merge($childIds, [$currentUserId]));
    $docMap = ['pending'=>0,'approved'=>0,'released'=>0,'rejected'=>0];
    foreach ($docRows as $row) {
        if (isset($docMap[$row['status_name']])) $docMap[$row['status_name']] = (int)$row['total'];
    }
    $docCounts = array_values($docMap);

    $recentAttendance = safeFetchAll($pdo, "SELECT a.*, u.first_name, u.last_name FROM attendance a JOIN users u ON u.id = a.student_id WHERE a.student_id IN ($placeholders) ORDER BY a.created_at DESC LIMIT 5", $childIds);
    $recentDocs = safeFetchAll($pdo, "SELECT dr.*, u.first_name, u.last_name FROM document_requests dr LEFT JOIN users u ON u.id = dr.student_id WHERE dr.student_id IN ($placeholders) OR dr.requested_by = ? ORDER BY dr.created_at DESC LIMIT 5", array_merge($childIds, [$currentUserId]));
} else {
    for ($i = 6; $i >= 0; $i--) $weekLabels[] = date('D', strtotime("-$i days"));
    $weekCounts = array_fill(0, 7, 0);
}

$attendanceTotal = $presentTotal + $absentTotal + $lateTotal;
$attendanceRate = $attendanceTotal > 0 ? round(($presentTotal / $attendanceTotal) * 100) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Parent Dashboard | Pitogo EduTrack</title>
<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<style>
:root{--primary-navy:#0B1C3D;--primary-blue:#0056B3;--bg-main:#F4F7F9;--surface-white:#FFFFFF;--text-dark:#1E293B;--text-muted:#64748B;--border-color:#E2E8F0;--success:#10B981;--warning:#F59E0B;--danger:#EF4444;--info:#3B82F6;--purple:#8B5CF6;--shadow-sm:0 4px 8px rgba(15,23,42,.04);--shadow-md:0 14px 35px rgba(15,23,42,.08);--transition:all .3s ease}
*{margin:0;padding:0;box-sizing:border-box}body{font-family:'Inter',sans-serif;background:var(--bg-main);color:var(--text-dark);display:flex;min-height:100vh;overflow-x:hidden}h1,h2,h3,h4{font-family:'Montserrat',sans-serif}.sidebar{width:82px;height:100vh;background:var(--surface-white);border-right:1px solid var(--border-color);position:fixed;left:0;top:0;z-index:100;transition:width .35s ease;overflow-x:hidden;display:flex;flex-direction:column}.sidebar:hover{width:285px;box-shadow:10px 0 30px rgba(15,23,42,.08)}.sidebar-brand{padding:24px 17px;display:flex;align-items:center;gap:15px;border-bottom:1px solid var(--border-color)}.logo-box{min-width:48px;height:48px;border-radius:14px;background:#fff;border:1px solid var(--border-color);overflow:hidden;display:flex;align-items:center;justify-content:center}.logo-box img{width:100%;height:100%;object-fit:contain}.brand-text,.nav-label,.nav-item span{opacity:0;visibility:hidden;transition:opacity .2s ease}.sidebar:hover .brand-text,.sidebar:hover .nav-label,.sidebar:hover .nav-item span{opacity:1;visibility:visible}.brand-text h2{font-size:1.05rem;color:var(--primary-navy);font-weight:800}.brand-text p{font-size:.72rem;color:var(--primary-blue);font-weight:800;text-transform:uppercase;letter-spacing:.8px}.nav-menu{padding:22px 14px;display:flex;flex-direction:column;gap:5px;flex:1}.nav-label{margin:14px 0 8px 14px;font-size:.72rem;font-weight:800;color:var(--text-muted);text-transform:uppercase;letter-spacing:1px}.nav-item{padding:14px 10px;border-radius:13px;display:flex;align-items:center;gap:20px;text-decoration:none;color:var(--text-muted);font-size:.94rem;font-weight:750;transition:var(--transition)}.nav-item i{min-width:30px;text-align:center;font-size:1.17rem}.nav-item:hover{background:var(--bg-main);color:var(--primary-blue);transform:translateX(5px)}.nav-item.active{background:rgba(0,86,179,.08);color:var(--primary-blue)}.logout-link{margin-top:auto;color:var(--danger)}
.main-wrapper{margin-left:82px;flex:1;min-width:0}.top-header{height:88px;background:var(--surface-white);border-bottom:1px solid var(--border-color);padding:0 38px;display:flex;align-items:center;justify-content:space-between;position:sticky;top:0;z-index:50}.header-title h1{font-size:1.35rem;color:var(--primary-navy)}.header-title p{font-size:.85rem;color:var(--text-muted);margin-top:4px}.user-profile{display:flex;align-items:center;gap:14px;background:#F8FAFC;border:1px solid var(--border-color);padding:8px 12px;border-radius:14px}.user-info{text-align:right}.user-info h4{font-size:.9rem;color:var(--primary-navy)}.user-info p{font-size:.75rem;color:var(--text-muted)}.avatar{width:46px;height:46px;object-fit:cover;border-radius:50%;border:2px solid var(--primary-blue);padding:2px}.content-area{padding:32px;max-width:1320px;margin:0 auto;width:100%}
.alert{padding:14px 18px;border-radius:14px;font-weight:800;margin-bottom:20px;display:flex;gap:10px;align-items:center;border:1px solid}.alert-success{background:#ECFDF5;color:#059669;border-color:#A7F3D0}.alert-danger{background:#FEF2F2;color:#DC2626;border-color:#FECACA}.hero-card{background:linear-gradient(135deg,rgba(11,28,61,.96),rgba(0,86,179,.78)),url('../school-bg.jpg');background-size:cover;background-position:center;color:white;border-radius:24px;padding:32px;margin-bottom:24px;display:grid;grid-template-columns:minmax(0,1fr) auto;gap:28px;align-items:center;box-shadow:var(--shadow-md);position:relative;overflow:hidden}.hero-card:after{content:"";position:absolute;width:260px;height:260px;border-radius:50%;background:rgba(255,255,255,.08);right:-80px;top:-95px}.profile-reminder{position:relative;z-index:2;background:rgba(255,255,255,.12);border:1px solid rgba(255,255,255,.24);border-radius:18px;padding:16px;margin-bottom:18px;display:flex;justify-content:space-between;align-items:center;gap:15px}.profile-reminder strong{font-family:'Montserrat';display:block}.profile-reminder p{font-size:.86rem;margin-top:4px}.hero-content{position:relative;z-index:2}.hero-content h1{font-size:2rem;font-weight:900;margin-bottom:8px}.hero-content p{color:rgba(255,255,255,.88);line-height:1.6;max-width:760px}.hero-actions{position:relative;z-index:2;display:flex;flex-direction:column;gap:12px;min-width:190px}.btn-white,.btn-outline,.btn-main,.btn-soft{padding:12px 16px;border-radius:12px;text-decoration:none;font-weight:900;display:inline-flex;align-items:center;justify-content:center;gap:8px;border:none;cursor:pointer;font-family:'Montserrat',sans-serif;transition:var(--transition)}.btn-white{background:#fff;color:var(--primary-blue)}.btn-outline{border:1px solid rgba(255,255,255,.3);color:#fff;background:rgba(255,255,255,.12)}.btn-main{background:var(--primary-blue);color:white}.btn-soft{background:#EFF6FF;color:var(--primary-blue);border:1px solid #BFDBFE}.btn-white:hover,.btn-outline:hover,.btn-main:hover,.btn-soft:hover{transform:translateY(-2px);box-shadow:var(--shadow-md)}
.stats-grid{display:grid;grid-template-columns:repeat(4,minmax(180px,1fr));gap:18px;margin-bottom:24px}.stat-card{background:var(--surface-white);padding:22px;border-radius:18px;border:1px solid var(--border-color);display:flex;align-items:center;gap:16px;box-shadow:var(--shadow-sm);transition:var(--transition)}.stat-card:hover{transform:translateY(-4px);box-shadow:var(--shadow-md)}.stat-icon{width:56px;height:56px;border-radius:15px;display:flex;align-items:center;justify-content:center;font-size:1.3rem}.stat-info h3{font-size:1.65rem;font-weight:900;color:var(--primary-navy)}.stat-info p{font-size:.78rem;color:var(--text-muted);font-weight:900;text-transform:uppercase}.dashboard-grid{display:grid;grid-template-columns:1.15fr .85fr;gap:22px;margin-bottom:24px}.panel{background:var(--surface-white);border:1px solid var(--border-color);border-radius:22px;padding:24px;box-shadow:var(--shadow-sm);overflow:hidden}.panel-header{display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px}.panel-header h3{color:var(--primary-navy);font-size:1.08rem}.panel-header p{color:var(--text-muted);font-size:.84rem;font-weight:700;margin-top:3px}.panel-header a{color:var(--primary-blue);font-size:.82rem;font-weight:900;text-decoration:none}.chart-box{height:290px;position:relative}.mini-summary{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-bottom:18px}.mini-card{border:1px solid var(--border-color);background:#F8FAFC;border-radius:15px;padding:14px;text-align:center}.mini-card h4{font-size:1.35rem;color:var(--primary-navy)}.mini-card p{font-size:.72rem;font-weight:900;text-transform:uppercase;color:var(--text-muted);margin-top:3px}.children-list{display:flex;flex-direction:column;gap:12px}.child-row{border:1px solid var(--border-color);background:#fff;border-radius:18px;padding:16px;display:grid;grid-template-columns:auto 1fr auto;gap:15px;align-items:center}.child-avatar{width:58px;height:58px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;background:#EFF6FF;color:var(--primary-blue);font-weight:900;font-family:'Montserrat'}.child-avatar img{width:100%;height:100%;object-fit:cover}.child-info h4{font-size:1rem;color:var(--primary-navy)}.child-info p{font-size:.8rem;color:var(--text-muted);margin-top:3px}.child-actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end}.child-actions .btn-soft{padding:9px 11px;font-size:.78rem}.empty-state{text-align:center;padding:38px 20px;border:1px dashed #CBD5E1;border-radius:18px;background:#F8FAFC}.empty-state i{font-size:2rem;color:var(--primary-blue);margin-bottom:12px}.empty-state h3{color:var(--primary-navy);margin-bottom:5px}.empty-state p{color:var(--text-muted);margin-bottom:16px}.list{display:flex;flex-direction:column;gap:12px}.list-item{padding:14px;border:1px solid var(--border-color);border-radius:15px;background:#F8FAFC;display:flex;justify-content:space-between;gap:12px;align-items:center}.list-item h4{color:var(--primary-navy);font-size:.92rem}.list-item p{color:var(--text-muted);font-size:.78rem;margin-top:3px}.badge{padding:7px 10px;border-radius:999px;font-size:.72rem;font-weight:900;background:#DCFCE7;color:#166534;text-transform:capitalize;white-space:nowrap}.badge.pending{background:#FEF3C7;color:#92400E}.badge.approved{background:#DBEAFE;color:#1D4ED8}.badge.released,.badge.present{background:#DCFCE7;color:#166534}.badge.rejected,.badge.absent{background:#FEE2E2;color:#991B1B}.badge.late{background:#FFEDD5;color:#9A3412}
.modal-overlay{position:fixed;inset:0;background:rgba(15,23,42,.55);backdrop-filter:blur(6px);z-index:999;display:none;align-items:center;justify-content:center;padding:22px}.modal-overlay.show{display:flex}.modal-card{width:min(520px,100%);background:#fff;border-radius:24px;padding:26px;box-shadow:0 30px 80px rgba(15,23,42,.28);position:relative}.modal-close{position:absolute;right:18px;top:18px;border:none;background:#F1F5F9;color:var(--primary-navy);width:36px;height:36px;border-radius:12px;cursor:pointer}.modal-card h2{color:var(--primary-navy);margin-bottom:8px}.modal-card>p{color:var(--text-muted);line-height:1.55;margin-bottom:20px}.form-grid{display:grid;grid-template-columns:1fr 1fr;gap:14px}.form-group{margin-bottom:15px}.form-group label{display:block;margin-bottom:7px;font-size:.82rem;font-weight:900;color:var(--primary-navy)}.form-control{width:100%;padding:13px 14px;border:1px solid var(--border-color);border-radius:12px;background:#F8FAFC;outline:none;font-family:'Inter',sans-serif}.form-control:focus{border-color:var(--primary-blue);box-shadow:0 0 0 4px rgba(0,86,179,.08)}.toast{position:fixed;right:26px;bottom:26px;background:var(--success);color:white;padding:15px 20px;border-radius:14px;font-weight:800;box-shadow:var(--shadow-md);z-index:1000}.toast.error{background:var(--danger)}
@media(max-width:1120px){.stats-grid{grid-template-columns:repeat(2,1fr)}.dashboard-grid{grid-template-columns:1fr}.hero-card{grid-template-columns:1fr}.hero-actions{flex-direction:row;min-width:0}.child-row{grid-template-columns:auto 1fr}.child-actions{grid-column:1/-1;justify-content:flex-start}}@media(max-width:760px){.sidebar{display:none}.main-wrapper{margin-left:0}.top-header{padding:0 20px}.content-area{padding:22px 16px}.stats-grid{grid-template-columns:1fr}.user-info{display:none}.hero-card{padding:24px}.hero-content h1{font-size:1.55rem}.hero-actions{flex-direction:column}.form-grid{grid-template-columns:1fr}.mini-summary{grid-template-columns:1fr}}
</style>
</head>
<body>
<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-box"><img src="../school-logo.jpg" alt="Pitogo High School Logo" onerror="this.style.display='none';this.parentElement.innerHTML='<i class=&quot;fa fa-graduation-cap&quot;></i>';"></div>
        <div class="brand-text"><h2>Pitogo EduTrack</h2><p>Parent Portal</p></div>
    </div>
    <nav class="nav-menu">
        <div class="nav-label">Main</div>
        <a href="dashboard.php" class="nav-item active"><i class="fa fa-chart-line"></i><span>Dashboard</span></a>
        <a href="attendance.php" class="nav-item"><i class="fa fa-calendar-check"></i><span>Attendance</span></a>
        <a href="document-request.php" class="nav-item"><i class="fa fa-file-lines"></i><span>Document Request</span></a>
        <a href="profile.php" class="nav-item"><i class="fa fa-user"></i><span>Profile</span></a>
        <a href="settings.php" class="nav-item"><i class="fa fa-gear"></i><span>Settings</span></a>
        <div class="nav-label">Account</div>
        <a href="../logout.php" class="nav-item logout-link"><i class="fa fa-right-from-bracket"></i><span>Logout</span></a>
    </nav>
</aside>

<main class="main-wrapper">
    <header class="top-header">
        <div class="header-title">
            <h1>Parent Dashboard</h1>
            <p>Child monitoring • Pitogo EduTrack</p>
        </div>
        <div class="user-profile">
            <div class="user-info"><h4><?= e($parentName) ?></h4><p>Parent Account</p></div>
            <img src="<?= e($avatarUrl) ?>" class="avatar" alt="Parent Profile">
        </div>
    </header>

    <section class="content-area">
        <?php if ($displayMessage): ?>
            <div class="alert <?= $isError ? 'alert-danger' : 'alert-success' ?>"><i class="fa <?= $isError ? 'fa-circle-xmark' : 'fa-circle-check' ?>"></i><?= $displayMessage ?></div>
        <?php endif; ?>

        <section class="hero-card">
            <div>
                <?php if ($isProfileIncomplete): ?>
                <div class="profile-reminder">
                    <div><strong><i class="fa fa-circle-info"></i> Complete your parent profile</strong><p>Add your contact details and profile photo so school staff can identify your account properly.</p></div>
                    <a href="profile.php" class="btn-white"><i class="fa fa-user-pen"></i> Edit Profile</a>
                </div>
                <?php endif; ?>
                <div class="hero-content">
                    <h1>Welcome back, <?= e($firstName) ?>!</h1>
                    <p>Monitor your linked child’s attendance, document requests, and school updates in one clean parent dashboard.</p>
                </div>
            </div>
            <div class="hero-actions">
                <button type="button" class="btn-white" onclick="openModal()"><i class="fa fa-link"></i> Link Child</button>
                <a href="attendance.php" class="btn-outline"><i class="fa fa-calendar-check"></i> View Attendance</a>
            </div>
        </section>

        <div class="stats-grid">
            <div class="stat-card"><div class="stat-icon" style="background:rgba(0,86,179,.1);color:var(--primary-blue)"><i class="fa fa-user-graduate"></i></div><div class="stat-info"><h3><?= number_format($totalChildren) ?></h3><p>Linked Children</p></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success)"><i class="fa fa-check"></i></div><div class="stat-info"><h3><?= number_format($todayAttendanceLogs) ?></h3><p>Today Logs</p></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:rgba(245,158,11,.1);color:var(--warning)"><i class="fa fa-clock"></i></div><div class="stat-info"><h3><?= number_format($pendingDocuments) ?></h3><p>Pending Documents</p></div></div>
            <div class="stat-card"><div class="stat-icon" style="background:rgba(59,130,246,.1);color:var(--info)"><i class="fa fa-percent"></i></div><div class="stat-info"><h3><?= number_format($attendanceRate) ?>%</h3><p>30-Day Rate</p></div></div>
        </div>

        <div class="dashboard-grid">
            <div class="panel">
                <div class="panel-header"><div><h3>Linked Children</h3><p>Students connected to your parent account</p></div><button type="button" class="btn-soft" onclick="openModal()"><i class="fa fa-plus"></i> Add</button></div>
                <div class="children-list">
                    <?php if ($linkedChildren): ?>
                        <?php foreach ($linkedChildren as $child): ?>
                            <?php
                                $childName = fullName($child) ?: 'Student';
                                $childAvatar = profilePath($child, $childName, 'Student');
                                $grade = $child['grade_level'] ?? $child['grade'] ?? 'Not Set';
                                $section = $child['section'] ?? 'Not Set';
                                $idNum = $child['id_num'] ?? $child['lrn'] ?? 'No ID Number';
                            ?>
                            <div class="child-row">
                                <div class="child-avatar"><img src="<?= e($childAvatar) ?>" alt="Child"></div>
                                <div class="child-info">
                                    <h4><?= e($childName) ?></h4>
                                    <p><?= e($grade) ?> • <?= e($section) ?></p>
                                    <p>ID/LRN: <?= e($idNum) ?> • Linked <?= !empty($child['linked_at']) ? e(date('M d, Y', strtotime($child['linked_at']))) : 'N/A' ?></p>
                                </div>
                                <div class="child-actions">
                                    <a href="attendance.php?student_id=<?= (int)$child['id'] ?>" class="btn-soft"><i class="fa fa-calendar-check"></i> Attendance</a>
                                    <a href="document-request.php?student_id=<?= (int)$child['id'] ?>" class="btn-soft"><i class="fa fa-file-lines"></i> Documents</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state"><i class="fa fa-user-graduate"></i><h3>No child linked yet</h3><p>Ask your child to generate a parent access code from the student portal.</p><button type="button" class="btn-main" onclick="openModal()"><i class="fa fa-link"></i> Link Your First Child</button></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header"><div><h3>Attendance Summary</h3><p>30-day attendance status overview</p></div><a href="attendance.php">View full report</a></div>
                <div class="mini-summary">
                    <div class="mini-card"><h4><?= number_format($presentTotal) ?></h4><p>Present</p></div>
                    <div class="mini-card"><h4><?= number_format($absentTotal) ?></h4><p>Absent</p></div>
                    <div class="mini-card"><h4><?= number_format($lateTotal) ?></h4><p>Late</p></div>
                </div>
                <div class="chart-box"><canvas id="attendanceChart"></canvas></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="panel">
                <div class="panel-header"><div><h3>7-Day Attendance Activity</h3><p>Total logs from linked children</p></div></div>
                <div class="chart-box"><canvas id="weekChart"></canvas></div>
            </div>
            <div class="panel">
                <div class="panel-header"><div><h3>Document Requests</h3><p>Request status overview</p></div><a href="document-request.php">Open requests</a></div>
                <div class="chart-box"><canvas id="docChart"></canvas></div>
            </div>
        </div>

        <div class="dashboard-grid">
            <div class="panel">
                <div class="panel-header"><div><h3>Recent Attendance</h3><p>Latest scanned logs from linked children</p></div></div>
                <div class="list">
                    <?php if ($recentAttendance): foreach ($recentAttendance as $log): ?>
                        <div class="list-item"><div><h4><?= e(trim(($log['first_name'] ?? '') . ' ' . ($log['last_name'] ?? ''))) ?></h4><p><?= e(date('M d, Y', strtotime($log['attendance_date'] ?? 'now'))) ?> <?= !empty($log['time_in']) ? '• ' . e(date('h:i A', strtotime($log['time_in']))) : '' ?></p></div><span class="badge <?= e(strtolower($log['status'] ?? 'present')) ?>"><?= e($log['status'] ?? 'Present') ?></span></div>
                    <?php endforeach; else: ?>
                        <div class="empty-state"><i class="fa fa-calendar-check"></i><h3>No attendance logs yet</h3><p>Attendance records will appear here once your linked child scans in.</p></div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="panel">
                <div class="panel-header"><div><h3>Recent Documents</h3><p>Latest document request activity</p></div></div>
                <div class="list">
                    <?php if ($recentDocs): foreach ($recentDocs as $doc): ?>
                        <div class="list-item"><div><h4><?= e($doc['document_type'] ?? 'Document Request') ?></h4><p><?= e(trim(($doc['first_name'] ?? '') . ' ' . ($doc['last_name'] ?? ''))) ?> • <?= !empty($doc['created_at']) ? e(date('M d, Y', strtotime($doc['created_at']))) : 'N/A' ?></p></div><span class="badge <?= e(strtolower($doc['status'] ?? 'pending')) ?>"><?= e($doc['status'] ?? 'Pending') ?></span></div>
                    <?php endforeach; else: ?>
                        <div class="empty-state"><i class="fa fa-file-lines"></i><h3>No document requests yet</h3><p>Requests submitted by you or your linked child will appear here.</p></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>
</main>

<div class="modal-overlay" id="linkModal" onclick="closeModalOnBackdrop(event)">
    <div class="modal-card">
        <button class="modal-close" type="button" onclick="closeModal()"><i class="fa fa-xmark"></i></button>
        <h2>Link a Child</h2>
        <p>Enter the student’s exact first name, last name, and active parent access code from the student portal.</p>
        <form method="POST">
            <div class="form-grid">
                <div class="form-group"><label>Student First Name</label><input class="form-control" type="text" name="first_name" required placeholder="Example: Juan"></div>
                <div class="form-group"><label>Student Last Name</label><input class="form-control" type="text" name="last_name" required placeholder="Example: Dela Cruz"></div>
            </div>
            <div class="form-group"><label>Parent Access Code</label><input class="form-control" type="text" name="access_code" required maxlength="20" placeholder="ENTER CODE" style="letter-spacing:2px;font-weight:900;text-align:center;text-transform:uppercase"></div>
            <button type="submit" name="link_child" class="btn-main" style="width:100%"><i class="fa fa-circle-check"></i> Verify and Link Child</button>
        </form>
    </div>
</div>

<script>
const chartText = '#64748B';
const gridLine = 'rgba(226,232,240,.8)';
Chart.defaults.font.family = 'Inter';
Chart.defaults.color = chartText;

function openModal(){document.getElementById('linkModal').classList.add('show');}
function closeModal(){document.getElementById('linkModal').classList.remove('show');}
function closeModalOnBackdrop(e){if(e.target.id === 'linkModal') closeModal();}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });

new Chart(document.getElementById('attendanceChart'), {
    type:'doughnut',
    data:{labels:['Present','Absent','Late'],datasets:[{data:[<?= (int)$presentTotal ?>,<?= (int)$absentTotal ?>,<?= (int)$lateTotal ?>],backgroundColor:['#10B981','#EF4444','#F59E0B'],borderWidth:0,hoverOffset:8}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{position:'bottom',labels:{usePointStyle:true,boxWidth:8,font:{weight:'700'}}},tooltip:{padding:12}}}
});

new Chart(document.getElementById('weekChart'), {
    type:'bar',
    data:{labels:<?= json_encode($weekLabels) ?>,datasets:[{label:'Attendance Logs',data:<?= json_encode($weekCounts) ?>,backgroundColor:'rgba(0,86,179,.75)',borderRadius:12,maxBarThickness:42}]},
    options:{responsive:true,maintainAspectRatio:false,plugins:{legend:{display:false}},scales:{y:{beginAtZero:true,ticks:{precision:0},grid:{color:gridLine}},x:{grid:{display:false}}}}
});

new Chart(document.getElementById('docChart'), {
    type:'bar',
    data:{labels:<?= json_encode($docLabels) ?>,datasets:[{label:'Requests',data:<?= json_encode($docCounts) ?>,backgroundColor:['#F59E0B','#3B82F6','#10B981','#EF4444'],borderRadius:12,maxBarThickness:42}]},
    options:{responsive:true,maintainAspectRatio:false,indexAxis:'y',plugins:{legend:{display:false}},scales:{x:{beginAtZero:true,ticks:{precision:0},grid:{color:gridLine}},y:{grid:{display:false}}}}
});
</script>
</body>
</html>
