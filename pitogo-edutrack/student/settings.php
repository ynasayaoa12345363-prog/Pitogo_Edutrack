<?php
session_start();
require '../db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

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

/*
|--------------------------------------------------------------------------
| SMTP SETTINGS
|--------------------------------------------------------------------------
| Replace these with your real Gmail app password credentials.
*/
$smtpEmail = 'margeauxcosmetics16@gmail.com';
$smtpAppPassword = 'piagntijndkisiko';
$smtpFromName = 'Pitogo EduTrack Security';

/* =========================
   HELPERS
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

function fullName(array $user): string {
    $first = trim($user['first_name'] ?? '');
    $middle = trim($user['middle_name'] ?? '');
    $last = trim($user['last_name'] ?? '');
    $name = trim($first . ' ' . $middle . ' ' . $last);

    return $name !== '' ? $name : 'Student';
}

function getAvatar(array $user, string $name): string {
    $photo = trim($user['profile_pic'] ?? '');

    if ($photo !== '') {
        $serverPath = __DIR__ . '/../uploads/profiles/' . basename($photo);
        $urlPath = '../uploads/profiles/' . basename($photo);

        if (file_exists($serverPath)) {
            return $urlPath . '?v=' . filemtime($serverPath);
        }

        return $urlPath;
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name) . '&background=0056B3&color=fff&size=256';
}

function strongPassword(string $password): bool {
    return strlen($password) >= 8
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password)
        && preg_match('/[^A-Za-z0-9]/', $password);
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

        $sql = "INSERT INTO audit_logs (`" . implode('`,`', $columns) . "`) VALUES (" . implode(',', array_fill(0, count($columns), '?')) . ")";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($values);
    } catch (Exception $e) {}
}

function sendOtpEmail($toEmail, $studentName, $otp, $smtpEmail, $smtpAppPassword, $smtpFromName): bool {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();

/*
|--------------------------------------------------------------------------
| BREVO SMTP
|--------------------------------------------------------------------------
*/

$mail->Host = 'smtp-relay.brevo.com';

$mail->SMTPAuth = true;

/*
|--------------------------------------------------------------------------
| YOUR VERIFIED BREVO EMAIL
|--------------------------------------------------------------------------
*/

$mail->Username = $smtpEmail;

/*
|--------------------------------------------------------------------------
| YOUR BREVO SMTP KEY
|--------------------------------------------------------------------------
*/

$mail->Password = 'xsmtpsib-fc2697de645fd2b577bb01628bc6041ef583a23b153f0a64a90d0f2269b30fc3-SGpPylV7Pdcq6xcy';

$mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;

$mail->Port = 587;

/*
|--------------------------------------------------------------------------
| OPTIONAL RAILWAY FIX
|--------------------------------------------------------------------------
*/

$mail->SMTPOptions = [
    'ssl' => [
        'verify_peer' => false,
        'verify_peer_name' => false,
        'allow_self_signed' => true
    ]
];

$mail->Timeout = 60;

        $mail->setFrom($smtpEmail, $smtpFromName);
        $mail->addAddress($toEmail, $studentName);

        $mail->isHTML(true);
        $mail->Subject = 'Your EduTrack Password OTP';
        $mail->Body = "
            <div style='font-family:Arial,sans-serif;background:#f4f7f9;padding:30px;'>
                <div style='max-width:540px;margin:auto;background:white;padding:32px;border-radius:18px;text-align:center;border:1px solid #e2e8f0;'>
                    <h2 style='color:#0B1C3D;margin-bottom:10px;'>Pitogo EduTrack Password Verification</h2>
                    <p style='color:#64748B;'>Hello <b>" . htmlspecialchars($studentName) . "</b>, use this OTP to verify your password change.</p>
                    <div style='font-size:34px;font-weight:bold;letter-spacing:8px;color:#0056B3;background:#F4F7F9;padding:18px;border-radius:14px;margin:25px 0;'>
                        {$otp}
                    </div>
                    <p style='color:#64748B;font-size:13px;'>This code expires in 10 minutes. Do not share it with anyone.</p>
                </div>
            </div>
        ";
        $mail->AltBody = "Your Pitogo EduTrack password verification OTP is: {$otp}. It expires in 10 minutes.";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("PHPMailer Error: " . $mail->ErrorInfo);
        return false;
    }
}

/* =========================
   ATTEMPT LIMITER
========================= */

if (!isset($_SESSION['settings_failed_attempts'])) {
    $_SESSION['settings_failed_attempts'] = 0;
}

if (!isset($_SESSION['settings_lockout_until'])) {
    $_SESSION['settings_lockout_until'] = 0;
}

/* =========================
   AJAX PASSWORD OTP REQUESTS
========================= */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    if (time() < ($_SESSION['settings_lockout_until'] ?? 0)) {
        $remaining = $_SESSION['settings_lockout_until'] - time();
        echo json_encode([
            'success' => false,
            'message' => "Too many failed attempts. Try again in {$remaining} seconds."
        ]);
        exit();
    }

    $action = $_POST['action'];

    if ($action === 'request_password_otp') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword) {
            echo json_encode([
                'success' => false,
                'message' => 'New password and confirm password do not match.'
            ]);
            exit();
        }

        if (!strongPassword($newPassword)) {
            echo json_encode([
                'success' => false,
                'message' => 'Password is too weak. Please follow all password requirements.'
            ]);
            exit();
        }

        $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, email, password FROM users WHERE id = ? AND role = 'student' LIMIT 1");
        $stmt->execute([$currentUserId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($currentPassword, $user['password'])) {
            $_SESSION['settings_failed_attempts']++;

            if ($_SESSION['settings_failed_attempts'] >= 5) {
                $_SESSION['settings_lockout_until'] = time() + 300;
            }

            echo json_encode([
                'success' => false,
                'message' => 'Incorrect current password.'
            ]);
            exit();
        }

        $otp = random_int(100000, 999999);

        $_SESSION['password_change_otp'] = (string)$otp;
        $_SESSION['password_change_otp_expires'] = time() + 600;
        $_SESSION['pending_new_password'] = password_hash($newPassword, PASSWORD_DEFAULT);

        $studentName = fullName($user);

        if (sendOtpEmail($user['email'], $studentName, $otp, $smtpEmail, $smtpAppPassword, $smtpFromName)) {
            echo json_encode([
                'success' => true,
                'message' => 'OTP sent to your registered email.'
            ]);
            exit();
        }

        echo json_encode([
            'success' => false,
            'message' => 'Failed to send OTP. Check your PHPMailer SMTP settings.'
        ]);
        exit();
    }

    if ($action === 'verify_password_otp') {
        $enteredOtp = trim($_POST['otp'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $enteredOtp)) {
            echo json_encode([
                'success' => false,
                'message' => 'Please enter a valid 6-digit OTP.'
            ]);
            exit();
        }

        if (!isset($_SESSION['password_change_otp'], $_SESSION['pending_new_password'])) {
            echo json_encode([
                'success' => false,
                'message' => 'No active OTP request found.'
            ]);
            exit();
        }

        if (time() > ($_SESSION['password_change_otp_expires'] ?? 0)) {
            unset($_SESSION['password_change_otp'], $_SESSION['password_change_otp_expires'], $_SESSION['pending_new_password']);

            echo json_encode([
                'success' => false,
                'message' => 'OTP expired. Please request a new one.'
            ]);
            exit();
        }

        if (!hash_equals((string)$_SESSION['password_change_otp'], (string)$enteredOtp)) {
            $_SESSION['settings_failed_attempts']++;

            if ($_SESSION['settings_failed_attempts'] >= 5) {
                $_SESSION['settings_lockout_until'] = time() + 300;
            }

            echo json_encode([
                'success' => false,
                'message' => 'Invalid OTP.'
            ]);
            exit();
        }

        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->execute([$_SESSION['pending_new_password'], $currentUserId]);

        unset($_SESSION['password_change_otp'], $_SESSION['password_change_otp_expires'], $_SESSION['pending_new_password']);

        $_SESSION['settings_failed_attempts'] = 0;
        $_SESSION['settings_lockout_until'] = 0;

        logAudit($pdo, $currentUserId, 'Changed password from student settings');

        echo json_encode([
            'success' => true,
            'message' => 'Password updated successfully.'
        ]);
        exit();
    }

    echo json_encode([
        'success' => false,
        'message' => 'Invalid action.'
    ]);
    exit();
}

/* =========================
   FETCH STUDENT
========================= */

$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND role = 'student' LIMIT 1");
$stmt->execute([$currentUserId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_destroy();
    header("Location: ../index.html");
    exit();
}

$studentName = fullName($user);
$avatarUrl = getAvatar($user, $studentName);
$idNumber = $user['id_num'] ?? $user['lrn'] ?? 'N/A';
$email = $user['email'] ?? 'N/A';
$phone = $user['phone_number'] ?? 'Not Set';
$gradeLevel = $user['grade_level'] ?? 'Not Set';
$section = $user['section'] ?? 'Not Set';
$emergencyName = $user['emergency_contact_name'] ?? 'Not Set';
$emergencyNumber = $user['emergency_contact_number'] ?? 'Not Set';
$createdAt = !empty($user['created_at']) ? date('F d, Y', strtotime($user['created_at'])) : 'Not Available';
$lastLogin = !empty($user['last_login']) ? date('F d, Y h:i A', strtotime($user['last_login'])) : 'No login record';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Settings | Pitogo EduTrack</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>
        :root{
            --primary-navy:#0B1C3D;
            --primary-blue:#0056B3;
            --blue-soft:#EAF3FF;
            --bg-main:#F4F7F9;
            --surface-white:#FFFFFF;
            --text-dark:#1E293B;
            --text-muted:#64748B;
            --border-color:#E2E8F0;
            --success:#10B981;
            --danger:#EF4444;
            --warning:#F59E0B;
            --shadow-sm:0 4px 10px rgba(15,23,42,0.05);
            --shadow-md:0 18px 45px rgba(15,23,42,0.10);
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

        .settings-hero{
            background:linear-gradient(135deg,#0B1C3D,#0056B3);
            border-radius:30px;
            padding:30px;
            color:#fff;
            display:grid;
            grid-template-columns:auto 1fr;
            gap:24px;
            align-items:center;
            box-shadow:var(--shadow-md);
            margin-bottom:26px;
            position:relative;
            overflow:hidden;
        }

        .settings-hero:before{
            content:"";
            position:absolute;
            width:260px;
            height:260px;
            border-radius:50%;
            background:rgba(255,255,255,.10);
            right:-90px;
            top:-100px;
        }

        .hero-avatar{
            width:130px;
            height:130px;
            border-radius:34px;
            object-fit:cover;
            border:5px solid rgba(255,255,255,.75);
            box-shadow:0 18px 38px rgba(0,0,0,.20);
            position:relative;
            z-index:1;
            background:#fff;
        }

        .hero-info{
            position:relative;
            z-index:1;
        }

        .hero-info h2{
            color:#fff;
            font-size:2rem;
            font-weight:800;
            margin-bottom:8px;
        }

        .hero-info p{
            color:rgba(255,255,255,.82);
            line-height:1.6;
        }

        .hero-pills{
            margin-top:16px;
            display:flex;
            flex-wrap:wrap;
            gap:10px;
        }

        .pill{
            padding:8px 12px;
            border-radius:999px;
            background:rgba(255,255,255,.15);
            border:1px solid rgba(255,255,255,.20);
            font-size:.85rem;
            font-weight:700;
            color:#fff;
        }

        .settings-grid{
            display:grid;
            grid-template-columns:.9fr 1.1fr;
            gap:24px;
            align-items:start;
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
            align-items:center;
            justify-content:space-between;
            gap:12px;
            margin-bottom:18px;
        }

        .card-title h3{
            font-size:1.08rem;
            font-weight:800;
        }

        .badge{
            padding:7px 11px;
            border-radius:999px;
            font-size:.76rem;
            font-weight:800;
            background:#DCFCE7;
            color:#166534;
        }

        .profile-list{
            display:grid;
            gap:12px;
        }

        .info-row{
            display:flex;
            justify-content:space-between;
            gap:16px;
            padding:15px;
            background:#F8FAFC;
            border:1px solid var(--border-color);
            border-radius:17px;
        }

        .info-row span{
            color:var(--text-muted);
            font-weight:700;
            font-size:.85rem;
        }

        .info-row strong{
            color:var(--primary-navy);
            text-align:right;
            word-break:break-word;
        }

        .security-card{
            background:linear-gradient(135deg,#ffffff,#F8FAFC);
        }

        .security-icon{
            width:58px;
            height:58px;
            border-radius:20px;
            display:flex;
            align-items:center;
            justify-content:center;
            background:var(--blue-soft);
            color:var(--primary-blue);
            font-size:1.45rem;
            margin-bottom:14px;
        }

        .form-grid{
            display:grid;
            gap:15px;
        }

        .form-group{
            display:flex;
            flex-direction:column;
            gap:8px;
        }

        .form-group label{
            color:var(--primary-navy);
            font-weight:800;
            font-size:.86rem;
        }

        .input-wrap{
            position:relative;
        }

        .input-wrap input{
            width:100%;
            border:1px solid var(--border-color);
            background:#F8FAFC;
            border-radius:16px;
            padding:14px 46px 14px 14px;
            outline:none;
            font:inherit;
            transition:var(--transition);
        }

        .input-wrap input:focus{
            background:#fff;
            border-color:var(--primary-blue);
            box-shadow:0 0 0 4px rgba(0,86,179,.10);
        }

        .toggle-pass{
            position:absolute;
            right:13px;
            top:50%;
            transform:translateY(-50%);
            border:none;
            background:transparent;
            color:var(--text-muted);
            cursor:pointer;
            font-size:1rem;
        }

        .requirements{
            margin-top:14px;
            padding:16px;
            border-radius:18px;
            border:1px solid var(--border-color);
            background:#F8FAFC;
        }

        .requirements h4{
            font-size:.9rem;
            margin-bottom:10px;
        }

        .req-list{
            display:grid;
            gap:8px;
        }

        .req-item{
            display:flex;
            align-items:center;
            gap:9px;
            color:var(--text-muted);
            font-size:.86rem;
            font-weight:650;
        }

        .req-item i{
            color:#CBD5E1;
        }

        .req-item.valid{
            color:#166534;
        }

        .req-item.valid i{
            color:var(--success);
        }

        .action-btn{
            width:100%;
            border:none;
            margin-top:18px;
            padding:14px 18px;
            border-radius:16px;
            background:var(--primary-blue);
            color:#fff;
            font-weight:800;
            font-size:.98rem;
            cursor:pointer;
            transition:var(--transition);
            display:flex;
            align-items:center;
            justify-content:center;
            gap:10px;
        }

        .action-btn:hover:not(:disabled){
            transform:translateY(-2px);
            box-shadow:0 14px 30px rgba(0,86,179,.20);
        }

        .action-btn:disabled{
            background:#94A3B8;
            cursor:not-allowed;
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
            width:min(460px,100%);
            background:#fff;
            border-radius:26px;
            box-shadow:0 30px 80px rgba(0,0,0,.25);
            border:1px solid var(--border-color);
            overflow:hidden;
        }

        .modal-header{
            padding:24px 26px;
            border-bottom:1px solid var(--border-color);
            display:flex;
            align-items:center;
            justify-content:space-between;
        }

        .modal-header h3{
            font-size:1.18rem;
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

        .otp-box{
            text-align:center;
        }

        .otp-box .shield{
            width:70px;
            height:70px;
            border-radius:24px;
            display:flex;
            align-items:center;
            justify-content:center;
            margin:0 auto 16px;
            background:var(--blue-soft);
            color:var(--primary-blue);
            font-size:1.8rem;
        }

        .otp-box p{
            color:var(--text-muted);
            line-height:1.6;
            margin-bottom:18px;
        }

        .otp-input{
            width:100%;
            text-align:center;
            font-size:1.6rem;
            letter-spacing:8px;
            font-weight:800;
            border:1px solid var(--border-color);
            background:#F8FAFC;
            border-radius:16px;
            padding:16px;
            outline:none;
            margin-bottom:12px;
        }

        .otp-input:focus{
            background:#fff;
            border-color:var(--primary-blue);
            box-shadow:0 0 0 4px rgba(0,86,179,.10);
        }

        .otp-error{
            display:none;
            background:#FEE2E2;
            color:#991B1B;
            border:1px solid #FECACA;
            border-radius:14px;
            padding:11px;
            font-weight:700;
            font-size:.88rem;
            margin-bottom:12px;
        }

        @media(max-width:950px){
            .settings-hero,
            .settings-grid{
                grid-template-columns:1fr;
            }

            .settings-hero{
                text-align:center;
            }

            .hero-avatar{
                margin:auto;
            }

            .hero-pills{
                justify-content:center;
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

        <a href="profile.php" class="nav-item">
            <i class="fa-solid fa-user"></i>
            <span>Profile</span>
        </a>

        <a href="settings.php" class="nav-item active">
            <i class="fa-solid fa-gear"></i>
            <span>Settings</span>
        </a>

        <a href="../logout.php" class="nav-item logout-link">
            <i class="fa-solid fa-right-from-bracket"></i>
            <span>Logout</span>
        </a>
    </nav>
</aside>

<main class="main-wrapper">

    <header class="top-header">
        <div class="page-title">
            <h1>Settings</h1>
            <p>Manage your account security and review your account details.</p>
        </div>

        <div class="header-profile">
            <img src="<?= e($avatarUrl) ?>" alt="Profile">
            <div class="header-name">
                <strong><?= e($studentName) ?></strong>
                <span>Student</span>
            </div>
        </div>
    </header>

    <section class="content-area">

        <section class="settings-hero">
            <img src="<?= e($avatarUrl) ?>" class="hero-avatar" alt="Student Profile">

            <div class="hero-info">
                <h2>Account Settings</h2>
                <p>
                    Keep your EduTrack account protected. You can review your profile details and update your password
                    securely through OTP verification sent to your registered email.
                </p>

                <div class="hero-pills">
                    <span class="pill"><i class="fa-solid fa-id-card"></i> <?= e($idNumber) ?></span>
                    <span class="pill"><i class="fa-solid fa-envelope"></i> <?= e($email) ?></span>
                    <span class="pill"><i class="fa-solid fa-shield-halved"></i> OTP Protected</span>
                </div>
            </div>
        </section>

        <section class="settings-grid">

            <div class="card">
                <div class="card-title">
                    <h3>Account Information</h3>
                    <span class="badge">Active</span>
                </div>

                <div class="profile-list">
                    <div class="info-row">
                        <span>Full Name</span>
                        <strong><?= e($studentName) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>LRN / ID Number</span>
                        <strong><?= e($idNumber) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Email</span>
                        <strong><?= e($email) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Phone Number</span>
                        <strong><?= e($phone ?: 'Not Set') ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Grade & Section</span>
                        <strong><?= e($gradeLevel) ?> - <?= e($section) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Emergency Contact</span>
                        <strong><?= e($emergencyName) ?> / <?= e($emergencyNumber) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Account Created</span>
                        <strong><?= e($createdAt) ?></strong>
                    </div>

                    <div class="info-row">
                        <span>Last Login</span>
                        <strong><?= e($lastLogin) ?></strong>
                    </div>
                </div>
            </div>

            <div class="card security-card">
                <div class="security-icon">
                    <i class="fa-solid fa-lock"></i>
                </div>

                <div class="card-title">
                    <h3>Change Password</h3>
                </div>

                <form id="passwordForm" onsubmit="requestPasswordOtp(event)">
                    <div class="form-grid">

                        <div class="form-group">
                            <label>Current Password</label>
                            <div class="input-wrap">
                                <input type="password" id="currentPassword" required autocomplete="current-password">
                                <button type="button" class="toggle-pass" onclick="togglePassword('currentPassword', this)">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>New Password</label>
                            <div class="input-wrap">
                                <input type="password" id="newPassword" required autocomplete="new-password">
                                <button type="button" class="toggle-pass" onclick="togglePassword('newPassword', this)">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                        <div class="form-group">
                            <label>Confirm New Password</label>
                            <div class="input-wrap">
                                <input type="password" id="confirmPassword" required autocomplete="new-password">
                                <button type="button" class="toggle-pass" onclick="togglePassword('confirmPassword', this)">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                        </div>

                    </div>

                    <div class="requirements">
                        <h4>Password Requirements</h4>

                        <div class="req-list">
                            <div class="req-item" id="reqLength"><i class="fa-solid fa-circle-check"></i> At least 8 characters</div>
                            <div class="req-item" id="reqUpper"><i class="fa-solid fa-circle-check"></i> At least 1 uppercase letter</div>
                            <div class="req-item" id="reqLower"><i class="fa-solid fa-circle-check"></i> At least 1 lowercase letter</div>
                            <div class="req-item" id="reqNumber"><i class="fa-solid fa-circle-check"></i> At least 1 number</div>
                            <div class="req-item" id="reqSpecial"><i class="fa-solid fa-circle-check"></i> At least 1 special character</div>
                            <div class="req-item" id="reqMatch"><i class="fa-solid fa-circle-check"></i> Passwords match</div>
                        </div>
                    </div>

                    <button type="submit" class="action-btn" id="updatePassBtn" disabled>
                        <i class="fa-solid fa-key"></i>
                        Send OTP & Update Password
                    </button>
                </form>
            </div>

        </section>

    </section>

</main>

<div class="modal" id="verifyOtpModal">
    <div class="modal-box">
        <div class="modal-header">
            <h3>Verify OTP</h3>
            <button class="close-btn" type="button" onclick="closeModal('verifyOtpModal')">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <div class="modal-body">
            <form class="otp-box" onsubmit="verifyPasswordOtp(event)">
                <div class="shield">
                    <i class="fa-solid fa-shield-halved"></i>
                </div>

                <p>
                    Enter the 6-digit verification code sent to your registered email address.
                    The code expires in 10 minutes.
                </p>

                <div class="otp-error" id="otpError"></div>

                <input type="text" id="otpInput" class="otp-input" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required>

                <button type="submit" class="action-btn" id="verifyOtpBtn">
                    <i class="fa-solid fa-shield-halved"></i>
                    Verify & Save Password
                </button>
            </form>
        </div>
    </div>
</div>

<script>
const currentPassword = document.getElementById('currentPassword');
const newPassword = document.getElementById('newPassword');
const confirmPassword = document.getElementById('confirmPassword');
const updatePassBtn = document.getElementById('updatePassBtn');

function togglePassword(inputId, button) {
    const input = document.getElementById(inputId);
    const icon = button.querySelector('i');

    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('fa-eye');
        icon.classList.add('fa-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.remove('fa-eye-slash');
        icon.classList.add('fa-eye');
    }
}

function setReq(id, valid) {
    const item = document.getElementById(id);

    if (valid) {
        item.classList.add('valid');
    } else {
        item.classList.remove('valid');
    }
}

function validatePassword() {
    const pass = newPassword.value;
    const confirm = confirmPassword.value;

    const checks = {
        length: pass.length >= 8,
        upper: /[A-Z]/.test(pass),
        lower: /[a-z]/.test(pass),
        number: /[0-9]/.test(pass),
        special: /[^A-Za-z0-9]/.test(pass),
        match: pass !== '' && pass === confirm
    };

    setReq('reqLength', checks.length);
    setReq('reqUpper', checks.upper);
    setReq('reqLower', checks.lower);
    setReq('reqNumber', checks.number);
    setReq('reqSpecial', checks.special);
    setReq('reqMatch', checks.match);

    updatePassBtn.disabled = !(checks.length && checks.upper && checks.lower && checks.number && checks.special && checks.match && currentPassword.value.length > 0);
}

currentPassword.addEventListener('input', validatePassword);
newPassword.addEventListener('input', validatePassword);
confirmPassword.addEventListener('input', validatePassword);

function openModal(id) {
    document.getElementById(id).classList.add('show');
}

function closeModal(id) {
    document.getElementById(id).classList.remove('show');
}

function showOtpError(message) {
    const err = document.getElementById('otpError');
    err.textContent = message;
    err.style.display = 'block';
}

async function requestPasswordOtp(event) {
    event.preventDefault();

    const formData = new FormData();
    formData.append('action', 'request_password_otp');
    formData.append('current_password', currentPassword.value);
    formData.append('new_password', newPassword.value);
    formData.append('confirm_password', confirmPassword.value);

    updatePassBtn.disabled = true;
    updatePassBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Sending OTP...';

    try {
        const response = await fetch('settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'OTP Sent',
                text: data.message,
                confirmButtonColor: '#0056B3'
            }).then(() => {
                openModal('verifyOtpModal');
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Unable to Continue',
                text: data.message,
                confirmButtonColor: '#0B1C3D'
            });
        }
    } catch (e) {
        Swal.fire({
            icon: 'error',
            title: 'Server Error',
            text: 'Unable to process request.',
            confirmButtonColor: '#0B1C3D'
        });
    }

    updatePassBtn.innerHTML = '<i class="fa-solid fa-key"></i> Send OTP & Update Password';
    validatePassword();
}

async function verifyPasswordOtp(event) {
    event.preventDefault();

    const otpInput = document.getElementById('otpInput');
    const verifyBtn = document.getElementById('verifyOtpBtn');
    const otp = otpInput.value.trim();

    const formData = new FormData();
    formData.append('action', 'verify_password_otp');
    formData.append('otp', otp);

    verifyBtn.disabled = true;
    verifyBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Verifying...';

    try {
        const response = await fetch('settings.php', {
            method: 'POST',
            body: formData
        });

        const data = await response.json();

        if (data.success) {
            closeModal('verifyOtpModal');

            Swal.fire({
                icon: 'success',
                title: 'Password Updated',
                text: data.message,
                confirmButtonColor: '#10B981'
            });

            document.getElementById('passwordForm').reset();
            otpInput.value = '';
            document.getElementById('otpError').style.display = 'none';
            validatePassword();
        } else {
            showOtpError(data.message);
        }
    } catch (e) {
        showOtpError('Server error. Please try again.');
    }

    verifyBtn.disabled = false;
    verifyBtn.innerHTML = '<i class="fa-solid fa-shield-halved"></i> Verify & Save Password';
}

document.getElementById('otpInput').addEventListener('input', function () {
    this.value = this.value.replace(/[^0-9]/g, '').slice(0, 6);
});
</script>

</body>
</html>
