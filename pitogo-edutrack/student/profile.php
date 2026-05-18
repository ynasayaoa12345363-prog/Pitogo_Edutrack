<?php
session_start();
require '../db.php';

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int) $_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if ($currentRole !== 'student') {
    header("Location: ../index.html?error=" . urlencode("Unauthorized access."));
    exit();
}

/* =========================
   HELPER FUNCTIONS
========================= */

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

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    try {
        if (!columnExists($pdo, $table, $column)) {
            $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
        }
    } catch (Exception $e) {
        // If ALTER TABLE is blocked by hosting, manually add the column in phpMyAdmin.
    }
}

function fullName(array $user): string {
    $first = trim($user['first_name'] ?? '');
    $middle = trim($user['middle_name'] ?? '');
    $last = trim($user['last_name'] ?? '');

    $name = trim($first . ' ' . $middle . ' ' . $last);

    return $name !== '' ? $name : 'Student';
}

function profileImageSrc(array $user, string $name): string {
    $photo = trim($user['profile_pic'] ?? '');

    if ($photo !== '') {
        if (preg_match('/^https?:\/\//i', $photo)) {
            return $photo;
        }

        return '../uploads/profiles/' . ltrim($photo, '/');
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0056B3&color=fff&size=256';
}

function logAudit(PDO $pdo, int $userId, string $action): void {
    try {
        if (!tableExists($pdo, 'audit_logs')) return;

        $columns = [];
        $values = [];

        if (columnExists($pdo, 'audit_logs', 'user_id')) {
            $columns[] = 'user_id';
            $values[] = $userId;
        }

        if (columnExists($pdo, 'audit_logs', 'action')) {
            $columns[] = 'action';
            $values[] = $action;
        }

        if (columnExists($pdo, 'audit_logs', 'created_at')) {
            $columns[] = 'created_at';
            $values[] = date('Y-m-d H:i:s');
        }

        if (empty($columns)) return;

        $placeholders = array_fill(0, count($columns), '?');
        $sql = "INSERT INTO audit_logs (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', $placeholders) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    } catch (Exception $e) {}
}

/* =========================
   DATABASE SAFETY

   This code is based on your database column names:
   phone_number
   profile_pic
   grade_level
   section
   emergency_contact_name
   emergency_contact_number

   If some columns are missing, it will try to add them.
========================= */

ensureColumn($pdo, 'users', 'profile_pic', 'VARCHAR(255) NULL');
ensureColumn($pdo, 'users', 'grade_level', 'VARCHAR(100) NULL');
ensureColumn($pdo, 'users', 'section', 'VARCHAR(100) NULL');
ensureColumn($pdo, 'users', 'emergency_contact_name', 'VARCHAR(150) NULL');
ensureColumn($pdo, 'users', 'emergency_contact_number', 'VARCHAR(50) NULL');

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS profile_photo_deletion_queue (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            file_name VARCHAR(255) NOT NULL,
            delete_after DATETIME NOT NULL,
            deleted_at DATETIME NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {}

/* =========================
   UPLOAD FOLDER
========================= */

$uploadDir = __DIR__ . '/../uploads/profiles/';
$uploadUrl = '../uploads/profiles/';

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

/* =========================
   AUTO DELETE OLD PROFILE PHOTOS AFTER 1 WEEK
========================= */

try {
    $stmtOld = $pdo->prepare("
        SELECT id, file_name
        FROM profile_photo_deletion_queue
        WHERE deleted_at IS NULL
        AND delete_after <= NOW()
    ");
    $stmtOld->execute();
    $oldPhotos = $stmtOld->fetchAll(PDO::FETCH_ASSOC);

    foreach ($oldPhotos as $old) {
        $fileName = basename($old['file_name']);
        $filePath = $uploadDir . $fileName;

        if (is_file($filePath)) {
            unlink($filePath);
        }

        $stmtMark = $pdo->prepare("
            UPDATE profile_photo_deletion_queue
            SET deleted_at = NOW()
            WHERE id = ?
        ");
        $stmtMark->execute([(int)$old['id']]);
    }
} catch (Exception $e) {}

/* =========================
   FETCH STUDENT ACCOUNT
========================= */

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1");
$stmt->execute([$currentUserId]);
$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {
    session_destroy();
    header("Location: ../index.html");
    exit();
}

$message = '';
$messageType = '';

/* =========================
   SAVE PROFILE
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {
    $firstName = trim($_POST['first_name'] ?? '');
    $middleName = trim($_POST['middle_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone_number'] ?? '');
    $grade = trim($_POST['grade_level'] ?? '');
    $section = trim($_POST['section'] ?? '');
    $emergencyPerson = trim($_POST['emergency_contact_name'] ?? '');
    $emergencyNumber = trim($_POST['emergency_contact_number'] ?? '');

    $currentPhoto = $student['profile_pic'] ?? null;
    $newPhoto = $currentPhoto;

    $removePhoto = isset($_POST['remove_profile_photo']) && $_POST['remove_profile_photo'] === '1';

    if ($removePhoto && !empty($currentPhoto)) {
        try {
            $stmtQueue = $pdo->prepare("
                INSERT INTO profile_photo_deletion_queue
                (user_id, file_name, delete_after)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmtQueue->execute([$currentUserId, basename($currentPhoto)]);
        } catch (Exception $e) {}

        $newPhoto = null;
    }

    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $tmpName = $_FILES['profile_photo']['tmp_name'];
            $originalName = $_FILES['profile_photo']['name'];
            $fileSize = (int) $_FILES['profile_photo']['size'];
            $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

            $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
            $imageInfo = @getimagesize($tmpName);

            if (!in_array($ext, $allowedExt, true)) {
                $message = 'Only JPG, JPEG, PNG, and WEBP profile photos are allowed.';
                $messageType = 'danger';
            } elseif ($fileSize > 5 * 1024 * 1024) {
                $message = 'Profile photo must not be larger than 5MB.';
                $messageType = 'danger';
            } elseif ($imageInfo === false) {
                $message = 'The uploaded file is not a valid image.';
                $messageType = 'danger';
            } else {
                $fileName = 'student_' . $currentUserId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    if (!empty($currentPhoto) && basename($currentPhoto) !== $fileName) {
                        try {
                            $stmtQueue = $pdo->prepare("
                                INSERT INTO profile_photo_deletion_queue
                                (user_id, file_name, delete_after)
                                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                            ");
                            $stmtQueue->execute([$currentUserId, basename($currentPhoto)]);
                        } catch (Exception $e) {}
                    }

                    $newPhoto = $fileName;
                } else {
                    $message = 'Failed to upload profile photo. Please check if uploads/profiles folder exists and has permission.';
                    $messageType = 'danger';
                }
            }
        } else {
            $message = 'There was an error uploading your profile photo.';
            $messageType = 'danger';
        }
    }

    if ($messageType !== 'danger') {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE users SET
                    first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    phone_number = ?,
                    grade_level = ?,
                    section = ?,
                    emergency_contact_name = ?,
                    emergency_contact_number = ?,
                    profile_pic = ?
                WHERE id = ?
            ");

            $stmtUpdate->execute([
                $firstName,
                $middleName,
                $lastName,
                $phone,
                $grade,
                $section,
                $emergencyPerson,
                $emergencyNumber,
                $newPhoto,
                $currentUserId
            ]);

            logAudit($pdo, $currentUserId, 'Updated student profile');

            $message = 'Profile updated successfully.';
            $messageType = 'success';

            $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1");
            $stmt->execute([$currentUserId]);
            $student = $stmt->fetch(PDO::FETCH_ASSOC);

        } catch (Exception $e) {
            $message = 'Failed to update profile: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

/* =========================
   DISPLAY VALUES
========================= */

$studentName = fullName($student);
$studentAvatar = profileImageSrc($student, $studentName);

$studentIdNumber = $student['id_num'] ?? $student['lrn'] ?? 'N/A';
$email = $student['email'] ?? 'N/A';
$phone = $student['phone_number'] ?? '';
$grade = $student['grade_level'] ?? '';
$section = $student['section'] ?? '';
$emergencyPerson = $student['emergency_contact_name'] ?? '';
$emergencyNumber = $student['emergency_contact_number'] ?? '';

$firstName = $student['first_name'] ?? '';
$middleName = $student['middle_name'] ?? '';
$lastName = $student['last_name'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Profile | Pitogo EduTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        :root{
            --primary-navy:#0B1C3D;
            --primary-blue:#0056B3;
            --bg-main:#F4F7F9;
            --surface-white:#FFFFFF;
            --text-dark:#1E293B;
            --text-muted:#64748B;
            --border-color:#E2E8F0;
            --success:#10B981;
            --danger:#EF4444;
            --shadow-sm:0 4px 10px rgba(15,23,42,0.05);
            --shadow-md:0 16px 40px rgba(15,23,42,0.10);
            --transition:all .25s ease;
        }

        *{margin:0;padding:0;box-sizing:border-box}

        body{
            font-family:'Inter',sans-serif;
            background:var(--bg-main);
            color:var(--text-dark);
            min-height:100vh;
            display:flex;
            overflow-x:hidden;
        }

        h1,h2,h3,h4{
            font-family:'Montserrat',sans-serif;
            color:var(--primary-navy);
        }

        .sidebar{
            width:82px;
            height:100vh;
            background:#fff;
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

        .sidebar:hover{
            width:285px;
            box-shadow:10px 0 30px rgba(15,23,42,.08);
        }

        .sidebar-brand{
            padding:24px 17px;
            display:flex;
            align-items:center;
            gap:15px;
            border-bottom:1px solid var(--border-color);
        }

        .logo-box{
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

        .logo-box img{
            width:100%;
            height:100%;
            object-fit:contain;
        }

        .brand-text,
        .nav-label,
        .nav-item span{
            opacity:0;
            visibility:hidden;
            transition:opacity .2s ease;
        }

        .sidebar:hover .brand-text,
        .sidebar:hover .nav-label,
        .sidebar:hover .nav-item span{
            opacity:1;
            visibility:visible;
        }

        .brand-text h2{
            font-size:1.05rem;
            font-weight:800;
        }

        .brand-text p{
            font-size:.72rem;
            color:var(--primary-blue);
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.8px;
        }

        .nav-menu{
            padding:22px 14px;
            display:flex;
            flex-direction:column;
            gap:5px;
            flex:1;
        }

        .nav-label{
            margin:14px 0 8px 14px;
            font-size:.72rem;
            font-weight:800;
            color:var(--text-muted);
            text-transform:uppercase;
            letter-spacing:1px;
        }

        .nav-item{
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
            white-space:nowrap;
        }

        .nav-item i{
            min-width:30px;
            text-align:center;
            font-size:1.17rem;
        }

        .nav-item:hover{
            background:var(--bg-main);
            color:var(--primary-blue);
            transform:translateX(5px);
        }

        .nav-item.active{
            background:rgba(0,86,179,.08);
            color:var(--primary-blue);
        }

        .logout-link{
            margin-top:auto;
            color:var(--danger);
        }

        .main-wrapper{
            margin-left:82px;
            flex:1;
            min-width:0;
        }

        .top-header{
            height:88px;
            background:rgba(255,255,255,.92);
            backdrop-filter:blur(16px);
            border-bottom:1px solid var(--border-color);
            display:flex;
            justify-content:space-between;
            align-items:center;
            padding:0 36px;
            position:sticky;
            top:0;
            z-index:50;
        }

        .page-title h1{
            font-size:1.55rem;
            font-weight:800;
        }

        .page-title p{
            color:var(--text-muted);
            margin-top:3px;
            font-size:.92rem;
        }

        .header-profile{
            display:flex;
            align-items:center;
            gap:12px;
            background:#fff;
            border:1px solid var(--border-color);
            padding:8px 14px;
            border-radius:999px;
            box-shadow:var(--shadow-sm);
        }

        .header-profile img{
            width:42px;
            height:42px;
            border-radius:50%;
            object-fit:cover;
            border:2px solid var(--primary-blue);
        }

        .header-name strong{
            display:block;
            font-size:.9rem;
            color:var(--primary-navy);
        }

        .header-name span{
            display:block;
            color:var(--text-muted);
            font-size:.78rem;
        }

        .content-area{
            padding:32px 38px;
        }

        .alert{
            padding:14px 16px;
            border-radius:16px;
            margin-bottom:18px;
            font-weight:700;
            border:1px solid;
        }

        .alert.success{
            background:#DCFCE7;
            color:#166534;
            border-color:#A7F3D0;
        }

        .alert.danger{
            background:#FEE2E2;
            color:#991B1B;
            border-color:#FECACA;
        }

        .profile-hero{
            background:linear-gradient(135deg,#0B1C3D,#0056B3);
            border-radius:30px;
            padding:30px;
            color:#fff;
            display:grid;
            grid-template-columns:auto 1fr auto;
            gap:24px;
            align-items:center;
            box-shadow:var(--shadow-md);
            margin-bottom:26px;
            position:relative;
            overflow:hidden;
        }

        .profile-hero:before{
            content:"";
            position:absolute;
            width:240px;
            height:240px;
            border-radius:50%;
            background:rgba(255,255,255,.10);
            right:-80px;
            top:-90px;
        }

        .profile-photo-wrap{
            position:relative;
            z-index:1;
        }

        .profile-photo{
            width:132px;
            height:132px;
            border-radius:34px;
            object-fit:cover;
            border:5px solid rgba(255,255,255,.75);
            box-shadow:0 18px 38px rgba(0,0,0,.20);
            background:#fff;
        }

        .hero-info{
            position:relative;
            z-index:1;
        }

        .hero-info h2{
            font-size:2rem;
            color:#fff;
            font-weight:800;
            margin-bottom:8px;
        }

        .hero-info p{
            color:rgba(255,255,255,.82);
            font-size:.98rem;
            line-height:1.6;
        }

        .hero-pills{
            margin-top:16px;
            display:flex;
            gap:10px;
            flex-wrap:wrap;
        }

        .pill{
            padding:8px 12px;
            background:rgba(255,255,255,.14);
            border:1px solid rgba(255,255,255,.20);
            border-radius:999px;
            font-weight:700;
            font-size:.85rem;
            color:#fff;
        }

        .btn-edit{
            position:relative;
            z-index:1;
            border:none;
            border-radius:16px;
            background:#fff;
            color:var(--primary-blue);
            padding:14px 18px;
            font-weight:800;
            cursor:pointer;
            box-shadow:var(--shadow-sm);
            transition:var(--transition);
            display:flex;
            align-items:center;
            gap:10px;
            white-space:nowrap;
        }

        .btn-edit:hover{
            transform:translateY(-2px);
            box-shadow:0 14px 30px rgba(0,0,0,.14);
        }

        .profile-grid{
            display:grid;
            grid-template-columns:1.1fr .9fr;
            gap:24px;
        }

        .card{
            background:#fff;
            border:1px solid var(--border-color);
            border-radius:24px;
            padding:24px;
            box-shadow:var(--shadow-sm);
        }

        .card-title{
            display:flex;
            justify-content:space-between;
            align-items:center;
            margin-bottom:18px;
        }

        .card-title h3{
            font-size:1.05rem;
            font-weight:800;
        }

        .info-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:14px;
        }

        .info-box{
            background:#F8FAFC;
            border:1px solid var(--border-color);
            border-radius:18px;
            padding:16px;
        }

        .info-box label{
            display:block;
            color:var(--text-muted);
            font-size:.78rem;
            font-weight:800;
            text-transform:uppercase;
            letter-spacing:.5px;
            margin-bottom:7px;
        }

        .info-box strong{
            display:block;
            color:var(--primary-navy);
            font-size:.97rem;
            word-break:break-word;
        }

        .emergency-card{
            background:linear-gradient(135deg,#FFF7ED,#FFEDD5);
            border-color:#FED7AA;
        }

        .emergency-card .icon-badge{
            width:52px;
            height:52px;
            border-radius:18px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:#FB923C;
            color:#fff;
            font-size:1.3rem;
            margin-bottom:16px;
        }

        .modal{
            position:fixed;
            inset:0;
            background:rgba(15,23,42,.58);
            backdrop-filter:blur(8px);
            display:none;
            align-items:center;
            justify-content:center;
            padding:20px;
            z-index:999;
        }

        .modal.show{
            display:flex;
        }

        .modal-box{
            width:min(920px,100%);
            max-height:92vh;
            overflow:auto;
            background:#fff;
            border-radius:28px;
            box-shadow:0 30px 80px rgba(0,0,0,.25);
            border:1px solid var(--border-color);
        }

        .modal-header{
            padding:24px 26px;
            border-bottom:1px solid var(--border-color);
            display:flex;
            justify-content:space-between;
            align-items:center;
            position:sticky;
            top:0;
            background:#fff;
            z-index:2;
        }

        .modal-header h3{
            font-size:1.25rem;
            font-weight:800;
        }

        .close-btn{
            border:none;
            background:#F1F5F9;
            width:42px;
            height:42px;
            border-radius:14px;
            cursor:pointer;
            color:var(--text-dark);
            font-size:1rem;
        }

        .modal-body{
            padding:26px;
        }

        .edit-layout{
            display:grid;
            grid-template-columns:260px 1fr;
            gap:24px;
        }

        .photo-panel{
            background:#F8FAFC;
            border:1px solid var(--border-color);
            border-radius:22px;
            padding:20px;
            text-align:center;
            height:max-content;
        }

        .photo-preview{
            width:150px;
            height:150px;
            border-radius:36px;
            object-fit:cover;
            border:4px solid #fff;
            box-shadow:var(--shadow-md);
            margin-bottom:14px;
        }

        .file-input{
            display:none;
        }

        .upload-btn,
        .remove-btn,
        .save-btn{
            width:100%;
            border:none;
            border-radius:14px;
            padding:12px 14px;
            font-weight:800;
            cursor:pointer;
            margin-top:10px;
            transition:var(--transition);
        }

        .upload-btn{
            background:var(--primary-blue);
            color:#fff;
            display:block;
        }

        .remove-btn{
            background:#FEE2E2;
            color:#B91C1C;
        }

        .save-btn{
            background:var(--primary-navy);
            color:#fff;
            font-size:1rem;
            margin-top:18px;
        }

        .upload-note{
            font-size:.78rem;
            color:var(--text-muted);
            line-height:1.5;
            margin-top:12px;
        }

        .form-grid{
            display:grid;
            grid-template-columns:repeat(2,minmax(0,1fr));
            gap:16px;
        }

        .form-group{
            display:flex;
            flex-direction:column;
            gap:8px;
        }

        .form-group label{
            font-size:.83rem;
            font-weight:800;
            color:var(--primary-navy);
        }

        .form-group input{
            width:100%;
            border:1px solid var(--border-color);
            background:#F8FAFC;
            border-radius:15px;
            padding:13px 14px;
            font:inherit;
            outline:none;
            transition:var(--transition);
        }

        .form-group input:focus{
            border-color:var(--primary-blue);
            background:#fff;
            box-shadow:0 0 0 4px rgba(0,86,179,.10);
        }

        @media(max-width:900px){
            .profile-hero{
                grid-template-columns:1fr;
                text-align:center;
            }

            .profile-photo-wrap{
                margin:auto;
            }

            .btn-edit{
                justify-content:center;
            }

            .profile-grid,
            .edit-layout{
                grid-template-columns:1fr;
            }

            .info-grid,
            .form-grid{
                grid-template-columns:1fr;
            }

            .content-area{
                padding:22px;
            }

            .header-name{
                display:none;
            }
        }
    </style>
</head>

<body>

<aside class="sidebar">
    <div class="sidebar-brand">
        <div class="logo-box">
            <img src="../school-logo.jpg" alt="Pitogo Logo" onerror="this.src='../assets/logo.png'">
        </div>
        <div class="brand-text">
            <h2>Pitogo</h2>
            <p>EduTrack</p>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">Menu</div>

        <a href="dashboard.php" class="nav-item">
            <i class="fa-solid fa-chart-line"></i>
            <span>Dashboard</span>
        </a>

        <a href="attendance.php" class="nav-item">
            <i class="fa-solid fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <a href="document-request.php" class="nav-item">
            <i class="fa-solid fa-file-lines"></i>
            <span>Documents</span>
        </a>

        <a href="parent-access.php" class="nav-item">
            <i class="fa-solid fa-key"></i>
            <span>Parent Access</span>
        </a>

        <a href="profile.php" class="nav-item active">
            <i class="fa-solid fa-user"></i>
            <span>Profile</span>
        </a>
<a href="settings.php" class="nav-item"><i class="fa fa-gear"></i><span>Settings</span></a>


        <a href="../logout.php" class="nav-item logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main-wrapper">

    <header class="top-header">
        <div class="page-title">
            <h1>Profile</h1>
            <p>Manage your student information and profile photo.</p>
        </div>

        <div class="header-profile">
            <img src="<?= e($studentAvatar) ?>" alt="Profile Photo">
            <div class="header-name">
                <strong><?= e($studentName) ?></strong>
                <span>Student</span>
            </div>
        </div>
    </header>

    <section class="content-area">

        <?php if (!empty($message)): ?>
            <div class="alert <?= e($messageType) ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <section class="profile-hero">
            <div class="profile-photo-wrap">
                <img src="<?= e($studentAvatar) ?>" class="profile-photo" alt="Student Profile Photo">
            </div>

            <div class="hero-info">
                <h2><?= e($studentName) ?></h2>
                <p>
                    Welcome to your EduTrack profile. Keep your account details updated so your school records,
                    class information, and emergency contact details remain accurate.
                </p>

                <div class="hero-pills">
                    <span class="pill"><i class="fa-solid fa-id-card"></i> <?= e($studentIdNumber) ?></span>
                    <span class="pill"><i class="fa-solid fa-graduation-cap"></i> <?= e($grade ?: 'Grade not set') ?></span>
                    <span class="pill"><i class="fa-solid fa-users"></i> <?= e($section ?: 'Section not set') ?></span>
                </div>
            </div>

            <button class="btn-edit" type="button" onclick="openModal()">
                <i class="fa-solid fa-pen-to-square"></i>
                Edit Profile
            </button>
        </section>

        <section class="profile-grid">

            <div class="card">
                <div class="card-title">
                    <h3>Account Details</h3>
                </div>

                <div class="info-grid">
                    <div class="info-box">
                        <label>LRN / ID Number</label>
                        <strong><?= e($studentIdNumber) ?></strong>
                    </div>

                    <div class="info-box">
                        <label>Email Address</label>
                        <strong><?= e($email) ?></strong>
                    </div>

                    <div class="info-box">
                        <label>Phone Number</label>
                        <strong><?= e($phone ?: 'Not set') ?></strong>
                    </div>

                    <div class="info-box">
                        <label>Role</label>
                        <strong>Student</strong>
                    </div>

                    <div class="info-box">
                        <label>Grade</label>
                        <strong><?= e($grade ?: 'Not set') ?></strong>
                    </div>

                    <div class="info-box">
                        <label>Section</label>
                        <strong><?= e($section ?: 'Not set') ?></strong>
                    </div>
                </div>
            </div>

            <div class="card emergency-card">
                <div class="icon-badge">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>

                <div class="card-title">
                    <h3>Emergency Contact</h3>
                </div>

                <div class="info-grid" style="grid-template-columns:1fr;">
                    <div class="info-box">
                        <label>Contact Person</label>
                        <strong><?= e($emergencyPerson ?: 'Not set') ?></strong>
                    </div>

                    <div class="info-box">
                        <label>Contact Number</label>
                        <strong><?= e($emergencyNumber ?: 'Not set') ?></strong>
                    </div>
                </div>
            </div>

        </section>

    </section>

</main>

<div class="modal" id="editModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Edit Profile</h3>
            <button class="close-btn" type="button" onclick="closeModal()">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" enctype="multipart/form-data">
            <div class="modal-body">
                <div class="edit-layout">

                    <div class="photo-panel">
                        <img src="<?= e($studentAvatar) ?>" class="photo-preview" id="photoPreview" alt="Profile Preview">

                        <input type="file" name="profile_photo" id="profilePhoto" class="file-input" accept="image/jpeg,image/png,image/webp">

                        <label for="profilePhoto" class="upload-btn">
                            <i class="fa-solid fa-camera"></i>
                            Upload Photo
                        </label>

                        <?php if (!empty($student['profile_pic'])): ?>
                            <button type="button" class="remove-btn" onclick="removePhoto()">
                                <i class="fa-solid fa-trash"></i>
                                Remove Photo
                            </button>
                        <?php endif; ?>

                        <input type="hidden" name="remove_profile_photo" id="removeProfilePhoto" value="0">

                        <p class="upload-note">
                            Accepted formats: JPG, PNG, WEBP. Maximum size: 5MB.
                            When you change or remove your photo, the old photo will be deleted automatically after 7 days.
                        </p>
                    </div>

                    <div>
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" value="<?= e($firstName) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" value="<?= e($middleName) ?>">
                            </div>

                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" value="<?= e($lastName) ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" value="<?= e($phone) ?>" placeholder="09XXXXXXXXX">
                            </div>

                            <div class="form-group">
                                <label>Grade</label>
                                <input type="text" name="grade_level" value="<?= e($grade) ?>" placeholder="Example: Grade 10">
                            </div>

                            <div class="form-group">
                                <label>Section</label>
                                <input type="text" name="section" value="<?= e($section) ?>" placeholder="Example: Rizal">
                            </div>

                            <div class="form-group">
                                <label>Emergency Contact Person</label>
                                <input type="text" name="emergency_contact_name" value="<?= e($emergencyPerson) ?>" placeholder="Parent / Guardian name">
                            </div>

                            <div class="form-group">
                                <label>Emergency Contact Number</label>
                                <input type="text" name="emergency_contact_number" value="<?= e($emergencyNumber) ?>" placeholder="09XXXXXXXXX">
                            </div>
                        </div>

                        <button type="submit" name="save_profile" class="save-btn">
                            <i class="fa-solid fa-floppy-disk"></i>
                            Save Profile
                        </button>
                    </div>

                </div>
            </div>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById('editModal');
    const profilePhoto = document.getElementById('profilePhoto');
    const photoPreview = document.getElementById('photoPreview');
    const removeProfilePhoto = document.getElementById('removeProfilePhoto');

    function openModal() {
        modal.classList.add('show');
    }

    function closeModal() {
        modal.classList.remove('show');
    }

    function removePhoto() {
        removeProfilePhoto.value = '1';
        profilePhoto.value = '';
        photoPreview.src = 'https://ui-avatars.com/api/?name=<?= urlencode($studentName) ?>&background=0056B3&color=fff&size=256';
    }

    profilePhoto.addEventListener('change', function () {
        removeProfilePhoto.value = '0';

        const file = this.files[0];

        if (file) {
            photoPreview.src = URL.createObjectURL(file);
        }
    });

    window.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeModal();
        }
    });
</script>

</body>
</html>
