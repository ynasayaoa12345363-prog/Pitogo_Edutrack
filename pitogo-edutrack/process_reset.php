<?php
session_start();
require_once 'db.php';

date_default_timezone_set('Asia/Manila');

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - process_reset.php
|--------------------------------------------------------------------------
| Handles forgot password OTP verification and password reset.
| Form fields expected:
| email, otp, new_password, confirm_new_password
|--------------------------------------------------------------------------
*/

function redirectWithError(string $message, string $email = ''): void {
    $url = "index.html?forgot=1&error=" . urlencode($message);

    if ($email !== '') {
        $url .= "&email=" . urlencode($email);
    }

    header("Location: " . $url);
    exit();
}

function columnExists(PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
        AND COLUMN_NAME = ?
    ");
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function tableExists(PDO $pdo, string $table): bool {
    $stmt = $pdo->prepare("
        SELECT COUNT(*)
        FROM INFORMATION_SCHEMA.TABLES
        WHERE TABLE_SCHEMA = DATABASE()
        AND TABLE_NAME = ?
    ");
    $stmt->execute([$table]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.html");
    exit();
}

$email = trim($_POST['email'] ?? '');
$otp = trim($_POST['otp'] ?? '');
$newPassword = $_POST['new_password'] ?? '';
$confirmPassword = $_POST['confirm_new_password'] ?? '';

if ($email === '' || $otp === '' || $newPassword === '' || $confirmPassword === '') {
    redirectWithError("Please complete all fields.", $email);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    redirectWithError("Invalid email address.", $email);
}

if (!preg_match('/^[0-9]{6}$/', $otp)) {
    redirectWithError("OTP must be a 6-digit code.", $email);
}

if ($newPassword !== $confirmPassword) {
    redirectWithError("Passwords do not match.", $email);
}

$passwordRegex = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^a-zA-Z0-9]).{8,}$/';

if (!preg_match($passwordRegex, $newPassword)) {
    redirectWithError("Password must contain uppercase, lowercase, number, and special character.", $email);
}

try {
    $stmt = $pdo->prepare("
        SELECT id, email, password, otp_code, otp_expiry
        FROM users
        WHERE email = ?
        AND is_deleted = 0
        AND is_archived = 0
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        redirectWithError("Account not found.", $email);
    }

    /*
    |--------------------------------------------------------------------------
    | Check OTP
    |--------------------------------------------------------------------------
    | Primary check is from users.otp_code and users.otp_expiry.
    | If otp_logs exists, it also verifies the latest forgot_password/reset_password OTP.
    |--------------------------------------------------------------------------
    */

    $otpIsValid = false;

    if (!empty($user['otp_code']) && hash_equals((string)$user['otp_code'], (string)$otp)) {
        if (!empty($user['otp_expiry']) && strtotime($user['otp_expiry']) >= time()) {
            $otpIsValid = true;
        }
    }

    if (!$otpIsValid && tableExists($pdo, 'otp_logs')) {
        $otpStmt = $pdo->prepare("
            SELECT id, otp_code, expires_at, status
            FROM otp_logs
            WHERE email = ?
            AND otp_code = ?
            AND purpose IN ('forgot_password', 'reset_password')
            AND status = 'pending'
            ORDER BY id DESC
            LIMIT 1
        ");
        $otpStmt->execute([$email, $otp]);
        $otpRow = $otpStmt->fetch(PDO::FETCH_ASSOC);

        if ($otpRow && strtotime($otpRow['expires_at']) >= time()) {
            $otpIsValid = true;
        }
    }

    if (!$otpIsValid) {
        redirectWithError("Wrong or expired OTP. Please request a new one.", $email);
    }

    if (password_verify($newPassword, $user['password'])) {
        redirectWithError("New password cannot be the same as your old password.", $email);
    }

    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    $update = $pdo->prepare("
        UPDATE users
        SET password = ?,
            otp_code = NULL,
            otp_expiry = NULL
        WHERE id = ?
    ");
    $update->execute([$hashedPassword, $user['id']]);

    if (tableExists($pdo, 'otp_logs')) {
        $logUpdate = $pdo->prepare("
            UPDATE otp_logs
            SET status = 'verified'
            WHERE email = ?
            AND otp_code = ?
            AND purpose IN ('forgot_password', 'reset_password')
            AND status = 'pending'
        ");
        $logUpdate->execute([$email, $otp]);
    }

    if (tableExists($pdo, 'audit_logs')) {
        $audit = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id,
                user_role,
                action,
                target_table,
                target_id,
                ip_address,
                created_at
            ) VALUES (
                ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $audit->execute([
            $user['id'],
            'user',
            'Password reset through OTP verification',
            'users',
            $user['id'],
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }

    header("Location: index.html?success=" . urlencode("Password changed successfully. You may now login."));
    exit();

} catch (PDOException $e) {
    redirectWithError("System error. Please try again.", $email);
}
?>
