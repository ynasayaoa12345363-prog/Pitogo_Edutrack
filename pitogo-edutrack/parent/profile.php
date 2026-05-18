<?php
session_start();

$dbPath1 = __DIR__ . '/../db.php';
$dbPath2 = __DIR__ . '/../../db.php';

if (file_exists($dbPath1)) {
    require_once $dbPath1;
} elseif (file_exists($dbPath2)) {
    require_once $dbPath2;
} else {
    die("Database file not found.");
}

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
    } catch (Exception $e) {}
}

function fullName(array $user): string {
    $first = trim($user['first_name'] ?? '');
    $middle = trim($user['middle_name'] ?? '');
    $last = trim($user['last_name'] ?? '');

    $name = trim($first . ' ' . $middle . ' ' . $last);
    return $name !== '' ? $name : 'Parent';
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
========================= */

ensureColumn($pdo, 'users', 'middle_name', 'VARCHAR(100) NULL');
ensureColumn($pdo, 'users', 'phone_number', 'VARCHAR(50) NULL');
ensureColumn($pdo, 'users', 'address', 'TEXT NULL');
ensureColumn($pdo, 'users', 'profile_pic', 'VARCHAR(255) NULL');
ensureColumn($pdo, 'users', 'relationship_to_student', 'VARCHAR(100) NULL');
ensureColumn($pdo, 'users', 'occupation', 'VARCHAR(150) NULL');

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

if (!tableExists($pdo, 'parent_student_links')) {
    try {
        $pdo->exec("
            CREATE TABLE parent_student_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT NOT NULL,
                student_id INT NOT NULL,
                status VARCHAR(30) DEFAULT 'active',
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_parent_student (parent_id, student_id)
            )
        ");
    } catch (Exception $e) {}
}

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
   FETCH PARENT ACCOUNT
========================= */

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND LOWER(role) = 'parent' LIMIT 1");
$stmt->execute([$currentUserId]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$parent) {
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
    $phoneNumber = trim($_POST['phone_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $relationship = trim($_POST['relationship_to_student'] ?? '');
    $occupation = trim($_POST['occupation'] ?? '');

    if ($firstName === '' || $lastName === '') {
        $message = 'First name and last name are required.';
        $messageType = 'danger';
    } else {
        try {
            $stmtUpdate = $pdo->prepare("
                UPDATE users
                SET first_name = ?,
                    middle_name = ?,
                    last_name = ?,
                    phone_number = ?,
                    address = ?,
                    relationship_to_student = ?,
                    occupation = ?
                WHERE id = ?
                AND LOWER(role) = 'parent'
            ");

            $stmtUpdate->execute([
                $firstName,
                $middleName,
                $lastName,
                $phoneNumber,
                $address,
                $relationship,
                $occupation,
                $currentUserId
            ]);

            logAudit($pdo, $currentUserId, 'Updated parent profile information');

            $message = 'Profile information updated successfully.';
            $messageType = 'success';
        } catch (Exception $e) {
            $message = 'Failed to update profile: ' . $e->getMessage();
            $messageType = 'danger';
        }
    }
}

/* =========================
   UPLOAD PROFILE PHOTO
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['upload_photo'])) {
    if (!isset($_FILES['profile_photo']) || $_FILES['profile_photo']['error'] !== UPLOAD_ERR_OK) {
        $message = 'Please choose a valid image file.';
        $messageType = 'danger';
    } else {
        $file = $_FILES['profile_photo'];
        $allowedExt = ['jpg', 'jpeg', 'png', 'webp'];
        $originalName = $file['name'];
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));

        if (!in_array($extension, $allowedExt, true)) {
            $message = 'Only JPG, JPEG, PNG, and WEBP files are allowed.';
            $messageType = 'danger';
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $message = 'Image file must not exceed 5MB.';
            $messageType = 'danger';
        } else {
            $mime = mime_content_type($file['tmp_name']);
            $allowedMime = ['image/jpeg', 'image/png', 'image/webp'];

            if (!in_array($mime, $allowedMime, true)) {
                $message = 'Invalid image type.';
                $messageType = 'danger';
            } else {
                try {
                    $oldPhoto = trim($parent['profile_pic'] ?? '');
                    $newFileName = 'parent_' . $currentUserId . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $extension;
                    $targetPath = $uploadDir . $newFileName;

                    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                        throw new Exception('Upload failed. Please check your uploads/profiles folder permission.');
                    }

                    $stmtPhoto = $pdo->prepare("
                        UPDATE users
                        SET profile_pic = ?
                        WHERE id = ?
                        AND LOWER(role) = 'parent'
                    ");
                    $stmtPhoto->execute([$newFileName, $currentUserId]);

                    if ($oldPhoto !== '' && !preg_match('/^https?:\/\//i', $oldPhoto) && $oldPhoto !== $newFileName) {
                        $stmtQueue = $pdo->prepare("
                            INSERT INTO profile_photo_deletion_queue (user_id, file_name, delete_after)
                            VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
                        ");
                        $stmtQueue->execute([$currentUserId, basename($oldPhoto)]);
                    }

                    logAudit($pdo, $currentUserId, 'Updated parent profile photo');

                    $message = 'Profile photo updated successfully. Old photo will be removed after 1 week.';
                    $messageType = 'success';
                } catch (Exception $e) {
                    $message = 'Failed to upload profile photo: ' . $e->getMessage();
                    $messageType = 'danger';
                }
            }
        }
    }
}

/* =========================
   REMOVE PROFILE PHOTO
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_photo'])) {
    try {
        $oldPhoto = trim($parent['profile_pic'] ?? '');

        $stmtRemove = $pdo->prepare("
            UPDATE users
            SET profile_pic = NULL
            WHERE id = ?
            AND LOWER(role) = 'parent'
        ");
        $stmtRemove->execute([$currentUserId]);

        if ($oldPhoto !== '' && !preg_match('/^https?:\/\//i', $oldPhoto)) {
            $stmtQueue = $pdo->prepare("
                INSERT INTO profile_photo_deletion_queue (user_id, file_name, delete_after)
                VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 7 DAY))
            ");
            $stmtQueue->execute([$currentUserId, basename($oldPhoto)]);
        }

        logAudit($pdo, $currentUserId, 'Removed parent profile photo');

        $message = 'Profile photo removed. The old image file will be deleted after 1 week.';
        $messageType = 'success';
    } catch (Exception $e) {
        $message = 'Failed to remove profile photo: ' . $e->getMessage();
        $messageType = 'danger';
    }
}

/* =========================
   REFRESH PROFILE DATA
========================= */

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND LOWER(role) = 'parent' LIMIT 1");
$stmt->execute([$currentUserId]);
$parent = $stmt->fetch(PDO::FETCH_ASSOC);

$parentName = fullName($parent);
$profileImage = profileImageSrc($parent, $parentName);

/* =========================
   LINKED CHILDREN
========================= */

$linkedChildren = [];

try {
    $stmtChildren = $pdo->prepare("
        SELECT 
            u.id,
            u.first_name,
            u.middle_name,
            u.last_name,
            u.id_num,
            u.grade_level,
            u.grade,
            u.section,
            u.profile_pic,
            u.profile_photo,
            psl.status,
            psl.created_at AS linked_at
        FROM parent_student_links psl
        INNER JOIN users u ON u.id = psl.student_id
        WHERE psl.parent_id = ?
        AND LOWER(u.role) = 'student'
        AND (psl.status IS NULL OR LOWER(psl.status) = 'active')
        ORDER BY u.last_name ASC, u.first_name ASC
    ");
    $stmtChildren->execute([$currentUserId]);
    $linkedChildren = $stmtChildren->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $linkedChildren = [];
}

$linkedCount = count($linkedChildren);

$profileCompleteness = 0;
$requiredFields = [
    $parent['first_name'] ?? '',
    $parent['last_name'] ?? '',
    $parent['email'] ?? '',
    $parent['phone_number'] ?? '',
    $parent['address'] ?? '',
    $parent['relationship_to_student'] ?? ''
];

foreach ($requiredFields as $field) {
    if (trim((string)$field) !== '') {
        $profileCompleteness += round(100 / count($requiredFields));
    }
}

if ($profileCompleteness > 100) {
    $profileCompleteness = 100;
}

$emailValue = $parent['email'] ?? '';
$phoneValue = $parent['phone_number'] ?? '';
$relationshipValue = $parent['relationship_to_student'] ?? '';
$occupationValue = $parent['occupation'] ?? '';
$addressValue = $parent['address'] ?? '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Profile | Pitogo EduTrack Parent Portal</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
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
    min-height:100vh;
    display:flex;
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
    background:white;
    border:1px solid var(--border-color);
    display:flex;
    align-items:center;
    justify-content:center;
    overflow:hidden;
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
    background:white;
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

.header-profile {
    display:flex;
    align-items:center;
    gap:14px;
}

.header-profile .text {
    text-align:right;
}

.header-profile h4 {
    font-size:.92rem;
    color:var(--primary-navy);
    font-weight:900;
}

.header-profile p {
    font-size:.76rem;
    color:var(--text-muted);
}

.header-avatar {
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

.profile-hero {
    background:linear-gradient(135deg,var(--primary-navy),var(--primary-blue));
    color:white;
    border-radius:26px;
    padding:32px;
    display:grid;
    grid-template-columns:auto 1fr auto;
    align-items:center;
    gap:24px;
    box-shadow:var(--shadow-md);
    margin-bottom:22px;
    position:relative;
    overflow:hidden;
}

.profile-hero:after {
    content:"";
    position:absolute;
    width:260px;
    height:260px;
    border-radius:50%;
    background:rgba(255,255,255,.08);
    right:-80px;
    top:-90px;
}

.profile-hero > * {
    position:relative;
    z-index:1;
}

.hero-photo {
    width:118px;
    height:118px;
    border-radius:28px;
    object-fit:cover;
    background:white;
    border:4px solid rgba(255,255,255,.35);
    box-shadow:0 15px 35px rgba(0,0,0,.18);
}

.hero-info h1 {
    font-size:2rem;
    font-weight:900;
    margin-bottom:8px;
}

.hero-info p {
    color:rgba(255,255,255,.85);
    line-height:1.55;
}

.hero-badge {
    background:rgba(255,255,255,.16);
    border:1px solid rgba(255,255,255,.25);
    padding:14px 16px;
    border-radius:18px;
    text-align:center;
}

.hero-badge strong {
    display:block;
    font-size:1.8rem;
    font-family:'Montserrat',sans-serif;
    font-weight:900;
}

.hero-badge span {
    font-size:.78rem;
    text-transform:uppercase;
    letter-spacing:.6px;
    font-weight:900;
    color:rgba(255,255,255,.86);
}

.alert {
    border-radius:16px;
    padding:14px 16px;
    margin-bottom:20px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:10px;
}

.alert-success {
    background:rgba(16,185,129,.12);
    color:#047857;
    border:1px solid rgba(16,185,129,.25);
}

.alert-danger {
    background:rgba(239,68,68,.12);
    color:#B91C1C;
    border:1px solid rgba(239,68,68,.25);
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(3,1fr);
    gap:18px;
    margin-bottom:22px;
}

.stat-card {
    background:white;
    border:1px solid var(--border-color);
    border-radius:20px;
    padding:21px;
    display:flex;
    align-items:center;
    gap:15px;
    box-shadow:var(--shadow-sm);
}

.stat-icon {
    width:56px;
    height:56px;
    border-radius:17px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.32rem;
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
    font-size:1.6rem;
    font-weight:900;
    line-height:1;
    color:var(--primary-navy);
}

.grid-layout {
    display:grid;
    grid-template-columns:.9fr 1.1fr;
    gap:22px;
}

.panel {
    background:white;
    border:1px solid var(--border-color);
    border-radius:22px;
    box-shadow:var(--shadow-sm);
    overflow:hidden;
}

.panel-header {
    padding:20px 22px;
    border-bottom:1px solid var(--border-color);
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:12px;
}

.panel-header h2 {
    color:var(--primary-navy);
    font-size:1.12rem;
    font-weight:900;
}

.panel-body {
    padding:22px;
}

.photo-box {
    text-align:center;
}

.preview-photo {
    width:155px;
    height:155px;
    border-radius:32px;
    object-fit:cover;
    border:4px solid #EEF4FF;
    box-shadow:var(--shadow-md);
    margin-bottom:16px;
}

.upload-actions {
    display:grid;
    gap:10px;
}

.file-input {
    width:100%;
    padding:12px;
    border:1px dashed var(--primary-blue);
    border-radius:14px;
    background:#F8FAFC;
    color:var(--text-muted);
}

.btn-main,
.btn-light,
.btn-danger {
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
}

.btn-main {
    background:var(--primary-blue);
    color:white;
}

.btn-light {
    background:#F8FAFC;
    color:var(--primary-blue);
    border:1px solid var(--border-color);
}

.btn-danger {
    background:rgba(239,68,68,.1);
    color:var(--danger);
    border:1px solid rgba(239,68,68,.2);
}

.btn-main:hover,
.btn-light:hover,
.btn-danger:hover {
    transform:translateY(-2px);
    box-shadow:var(--shadow-md);
}

.form-grid {
    display:grid;
    grid-template-columns:repeat(2,1fr);
    gap:16px;
}

.form-group.full {
    grid-column:1 / -1;
}

.form-group label {
    display:block;
    font-size:.76rem;
    font-weight:900;
    color:var(--primary-navy);
    text-transform:uppercase;
    letter-spacing:.6px;
    margin-bottom:7px;
}

.form-control {
    width:100%;
    padding:13px 14px;
    border:1px solid var(--border-color);
    background:#F8FAFC;
    border-radius:14px;
    color:var(--primary-navy);
    outline:none;
    font-family:'Inter',sans-serif;
    font-weight:700;
}

textarea.form-control {
    min-height:100px;
    resize:vertical;
}

.form-control:focus {
    background:white;
    border-color:var(--primary-blue);
    box-shadow:0 0 0 4px rgba(0,86,179,.08);
}

.readonly-field {
    background:#F1F5F9;
    color:var(--text-muted);
    cursor:not-allowed;
}

.children-grid {
    display:grid;
    gap:14px;
}

.child-card {
    border:1px solid var(--border-color);
    border-radius:18px;
    padding:16px;
    background:#F8FAFC;
    display:flex;
    align-items:center;
    gap:14px;
}

.child-avatar {
    width:54px;
    height:54px;
    border-radius:16px;
    object-fit:cover;
    background:white;
    border:2px solid #EAF2FF;
}

.child-info h4 {
    color:var(--primary-navy);
    font-weight:900;
    margin-bottom:4px;
}

.child-info p {
    color:var(--text-muted);
    font-size:.85rem;
    line-height:1.45;
}

.empty-state {
    text-align:center;
    color:var(--text-muted);
    padding:35px 15px;
}

.empty-state i {
    font-size:2.5rem;
    opacity:.45;
    margin-bottom:12px;
}

.progress-wrap {
    width:100%;
    height:10px;
    background:#E2E8F0;
    border-radius:999px;
    overflow:hidden;
    margin-top:10px;
}

.progress-bar {
    height:100%;
    width:<?= (int)$profileCompleteness ?>%;
    background:linear-gradient(90deg,var(--primary-blue),#38BDF8);
    border-radius:999px;
}

@media(max-width:1100px) {
    .profile-hero {
        grid-template-columns:auto 1fr;
    }

    .hero-badge {
        grid-column:1 / -1;
    }

    .grid-layout {
        grid-template-columns:1fr;
    }
}

@media(max-width:800px) {
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

    .header-profile {
        display:none;
    }

    .content-area {
        padding:24px 18px;
    }

    .profile-hero {
        grid-template-columns:1fr;
        text-align:center;
    }

    .hero-photo {
        margin:auto;
    }

    .stats-grid,
    .form-grid {
        grid-template-columns:1fr;
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

        <a href="attendance.php" class="nav-item">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <a href="document-request.php" class="nav-item">
            <i class="fa fa-file-lines"></i>
            <span>Document Request</span>
        </a>

        <a href="profile.php" class="nav-item active">
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
            <h1>Parent Profile</h1>
            <p>Manage your account details and linked children information</p>
        </div>

        <div class="header-profile">
            <div class="text">
                <h4><?= e($parentName) ?></h4>
                <p>Parent Account</p>
            </div>

            <img src="<?= e($profileImage) ?>" alt="Profile" class="header-avatar">
        </div>
    </header>

    <div class="content-area">
        <?php if ($message): ?>
            <div class="alert alert-<?= e($messageType) ?>">
                <i class="fa <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-triangle-exclamation' ?>"></i>
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <section class="profile-hero">
            <img src="<?= e($profileImage) ?>" alt="Parent Profile Photo" class="hero-photo">

            <div class="hero-info">
                <h1><?= e($parentName) ?></h1>
                <p>
                    Parent account connected to <?= number_format($linkedCount) ?> child<?= $linkedCount === 1 ? '' : 'ren' ?>.
                    Keep your contact details updated so the school can reach you when needed.
                </p>
            </div>

            <div class="hero-badge">
                <strong><?= (int)$profileCompleteness ?>%</strong>
                <span>Profile Complete</span>
                <div class="progress-wrap">
                    <div class="progress-bar"></div>
                </div>
            </div>
        </section>

        <section class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(0,86,179,.1);color:var(--primary-blue);">
                    <i class="fa fa-user-shield"></i>
                </div>
                <div class="stat-details">
                    <h3>Account Role</h3>
                    <div class="value">Parent</div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(16,185,129,.1);color:var(--success);">
                    <i class="fa fa-children"></i>
                </div>
                <div class="stat-details">
                    <h3>Linked Children</h3>
                    <div class="value"><?= number_format($linkedCount) ?></div>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-icon" style="background:rgba(245,158,11,.12);color:var(--warning);">
                    <i class="fa fa-id-card"></i>
                </div>
                <div class="stat-details">
                    <h3>Profile Status</h3>
                    <div class="value"><?= (int)$profileCompleteness ?>%</div>
                </div>
            </div>
        </section>

        <section class="grid-layout">
            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fa fa-camera" style="color:var(--primary-blue);margin-right:8px;"></i>Profile Photo</h2>
                </div>

                <div class="panel-body">
                    <div class="photo-box">
                        <img src="<?= e($profileImage) ?>" alt="Profile Preview" class="preview-photo">

                        <form method="POST" enctype="multipart/form-data" class="upload-actions">
                            <input type="file" name="profile_photo" accept="image/jpeg,image/png,image/webp" class="file-input" required>

                            <button type="submit" name="upload_photo" class="btn-main">
                                <i class="fa fa-upload"></i>
                                Upload New Photo
                            </button>
                        </form>

                        <?php if (!empty($parent['profile_pic'])): ?>
                            <form method="POST" style="margin-top:10px;">
                                <button type="submit" name="remove_photo" class="btn-danger" onclick="return confirm('Remove your profile photo? The old file will be deleted after 1 week.');">
                                    <i class="fa fa-trash"></i>
                                    Remove Photo
                                </button>
                            </form>
                        <?php endif; ?>

                        <p style="margin-top:14px;color:var(--text-muted);font-size:.85rem;line-height:1.5;">
                            Accepted formats: JPG, PNG, WEBP. Maximum size: 5MB.
                        </p>
                    </div>
                </div>

                <div class="panel-header" style="border-top:1px solid var(--border-color);">
                    <h2><i class="fa fa-children" style="color:var(--primary-blue);margin-right:8px;"></i>Linked Children</h2>
                </div>

                <div class="panel-body">
                    <?php if (empty($linkedChildren)): ?>
                        <div class="empty-state">
                            <i class="fa fa-link-slash"></i>
                            <h3 style="color:var(--primary-navy);margin-bottom:8px;">No linked children yet</h3>
                            <p>Link your child from the parent dashboard to view attendance and request documents.</p>
                        </div>
                    <?php else: ?>
                        <div class="children-grid">
                            <?php foreach ($linkedChildren as $child): ?>
                                <?php
                                    $childName = fullName($child);
                                    $childPhoto = profileImageSrc($child, $childName);
                                    $grade = $child['grade_level'] ?? $child['grade'] ?? 'N/A';
                                    $section = $child['section'] ?? 'N/A';
                                ?>
                                <div class="child-card">
                                    <img src="<?= e($childPhoto) ?>" alt="Child Photo" class="child-avatar">
                                    <div class="child-info">
                                        <h4><?= e($childName) ?></h4>
                                        <p>
                                            LRN / ID: <?= e($child['id_num'] ?? 'N/A') ?><br>
                                            Grade <?= e($grade) ?> - <?= e($section) ?>
                                        </p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2><i class="fa fa-pen-to-square" style="color:var(--primary-blue);margin-right:8px;"></i>Edit Parent Information</h2>
                </div>

                <div class="panel-body">
                    <form method="POST">
                        <div class="form-grid">
                            <div class="form-group">
                                <label>First Name</label>
                                <input type="text" name="first_name" class="form-control" value="<?= e($parent['first_name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Middle Name</label>
                                <input type="text" name="middle_name" class="form-control" value="<?= e($parent['middle_name'] ?? '') ?>">
                            </div>

                            <div class="form-group">
                                <label>Last Name</label>
                                <input type="text" name="last_name" class="form-control" value="<?= e($parent['last_name'] ?? '') ?>" required>
                            </div>

                            <div class="form-group">
                                <label>Email Address</label>
                                <input type="email" class="form-control readonly-field" value="<?= e($emailValue) ?>" readonly>
                            </div>

                            <div class="form-group">
                                <label>Phone Number</label>
                                <input type="text" name="phone_number" class="form-control" value="<?= e($phoneValue) ?>" placeholder="09XXXXXXXXX">
                            </div>

                            <div class="form-group">
                                <label>Relationship to Student</label>
                                <select name="relationship_to_student" class="form-control">
                                    <?php
                                        $relationships = ['', 'Mother', 'Father', 'Guardian', 'Grandparent', 'Aunt/Uncle', 'Other'];
                                        foreach ($relationships as $rel):
                                    ?>
                                        <option value="<?= e($rel) ?>" <?= $relationshipValue === $rel ? 'selected' : '' ?>>
                                            <?= $rel === '' ? 'Select relationship' : e($rel) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="form-group full">
                                <label>Occupation</label>
                                <input type="text" name="occupation" class="form-control" value="<?= e($occupationValue) ?>" placeholder="Optional">
                            </div>

                            <div class="form-group full">
                                <label>Home Address</label>
                                <textarea name="address" class="form-control" placeholder="Complete home address"><?= e($addressValue) ?></textarea>
                            </div>
                        </div>

                        <div style="display:flex;justify-content:flex-end;gap:12px;margin-top:20px;flex-wrap:wrap;">
                            <a href="dashboard.php" class="btn-light">
                                <i class="fa fa-arrow-left"></i>
                                Back to Dashboard
                            </a>

                            <button type="submit" name="save_profile" class="btn-main">
                                <i class="fa fa-save"></i>
                                Save Profile
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    </div>
</main>

</body>
</html>
