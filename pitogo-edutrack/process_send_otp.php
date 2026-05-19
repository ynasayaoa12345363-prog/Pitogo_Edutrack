<?php
session_start();
require 'db.php';

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

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

function respond(bool $success, string $message): void {
    echo json_encode([
        'success' => $success,
        'message' => $message
    ]);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid request method.');
}

$email = trim($_POST['email'] ?? '');

if ($email === '') {
    respond(false, 'Please enter your registered email address.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(false, 'Invalid email format.');
}

try {
    $stmt = $pdo->prepare("
        SELECT id, first_name, last_name, email, status
        FROM users
        WHERE email = ?
        AND is_deleted = 0
        AND is_archived = 0
        LIMIT 1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        respond(false, 'This email is not registered in Pitogo EduTrack.');
    }

    if ($user['status'] !== 'active') {
        respond(false, 'Your account is not active yet. Please contact the administrator.');
    }

    $otp = (string) random_int(100000, 999999);
    $expiry = date('Y-m-d H:i:s', strtotime('+15 minutes'));

    $update = $pdo->prepare("
        UPDATE users
        SET otp_code = ?,
            otp_expiry = ?
        WHERE id = ?
    ");
    $update->execute([$otp, $expiry, $user['id']]);

    if (tableExists($pdo, 'otp_logs')) {
        $log = $pdo->prepare("
            INSERT INTO otp_logs (
                user_id,
                email,
                otp_code,
                purpose,
                status,
                expires_at,
                created_at
            )
            VALUES (?, ?, ?, 'forgot_password', 'pending', ?, NOW())
        ");
        $log->execute([$user['id'], $email, $otp, $expiry]);
    }

    $brevoApiKey = getenv('BREVO_API_KEY');
    $senderEmail = getenv('BREVO_SENDER_EMAIL') ?: 'margeauxcosmetics16@gmail.com';
    $senderName = getenv('BREVO_SENDER_NAME') ?: 'Pitogo EduTrack Security';

    if (!$brevoApiKey) {
        respond(false, 'Brevo API key is missing in Railway Variables.');
    }

    $emailData = [
        "sender" => [
            "name" => $senderName,
            "email" => $senderEmail
        ],
        "to" => [
            [
                "email" => $email,
                "name" => trim($user['first_name'] . ' ' . $user['last_name'])
            ]
        ],
        "subject" => "Your Password Reset OTP - Pitogo EduTrack",
        "htmlContent" => "
            <div style='font-family:Arial,sans-serif;background:#f4f7fb;padding:24px;'>
                <div style='max-width:560px;margin:auto;background:#ffffff;border-radius:16px;padding:30px;border:1px solid #e2e8f0;'>
                    <h2 style='color:#0B1C3D;'>Password Reset Request</h2>

                    <p>Hello <b>" . htmlspecialchars($user['first_name'], ENT_QUOTES, 'UTF-8') . "</b>,</p>

                    <p>We received a request to reset your Pitogo EduTrack password.</p>

                    <p>Your OTP is:</p>

                    <h1 style='letter-spacing:7px;color:#0B1C3D;text-align:center;'>{$otp}</h1>

                    <p style='color:#ef4444;font-weight:bold;'>This OTP will expire in 15 minutes.</p>

                    <p style='font-size:13px;color:#64748b;'>If you did not request this, please ignore this email.</p>
                </div>
            </div>
        "
    ];

    $ch = curl_init("https://api.brevo.com/v3/smtp/email");

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => [
            "accept: application/json",
            "api-key: {$brevoApiKey}",
            "content-type: application/json"
        ],
        CURLOPT_POSTFIELDS => json_encode($emailData),
        CURLOPT_TIMEOUT => 60
    ]);

    $response = curl_exec($ch);
    $error = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    if ($error) {
        respond(false, "Brevo API error: " . $error);
    }

    if ($httpCode >= 200 && $httpCode < 300) {
        respond(true, "OTP sent successfully. Please check your email.");
    }

    respond(false, "Brevo failed. HTTP {$httpCode}: {$response}");

} catch (PDOException $e) {
    respond(false, 'System error. Please try again later.');
}
?>
