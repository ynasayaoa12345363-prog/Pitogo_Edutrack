<?php
session_start();
require 'db.php';

date_default_timezone_set('Asia/Manila');

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

header('Content-Type: application/json');

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - process_send_otp.php
|--------------------------------------------------------------------------
| Sends OTP for forgot password
|--------------------------------------------------------------------------
*/

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
        SELECT id,
               first_name,
               last_name,
               email,
               status
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

        respond(
            false,
            'Your account is not active yet. Please contact the administrator.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Generate OTP
    |--------------------------------------------------------------------------
    */

    $otp = (string) random_int(100000, 999999);

    $expiry = date(
        'Y-m-d H:i:s',
        strtotime('+15 minutes')
    );

    /*
    |--------------------------------------------------------------------------
    | Save OTP
    |--------------------------------------------------------------------------
    */

    $update = $pdo->prepare("
        UPDATE users
        SET otp_code = ?,
            otp_expiry = ?
        WHERE id = ?
    ");

    $update->execute([
        $otp,
        $expiry,
        $user['id']
    ]);

    /*
    |--------------------------------------------------------------------------
    | Optional OTP Logging
    |--------------------------------------------------------------------------
    */

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
            VALUES (
                ?, ?, ?, 'forgot_password',
                'pending', ?, NOW()
            )
        ");

        $log->execute([
            $user['id'],
            $email,
            $otp,
            $expiry
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Send Email
    |--------------------------------------------------------------------------
    */

    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = 'smtp.gmail.com';

        $mail->SMTPAuth = true;

        $mail->Username = 'margeauxcosmetics16@gmail.com';

        $mail->Password = 'piagntijndkisiko';

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT FIX FOR RAILWAY
        |--------------------------------------------------------------------------
        */

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

        $mail->Port = 465;

        $mail->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            ]
        ];

        $mail->Timeout = 60;

        /*
        |--------------------------------------------------------------------------
        | Email Content
        |--------------------------------------------------------------------------
        */

        $mail->setFrom(
            'margeauxcosmetics16@gmail.com',
            'Pitogo EduTrack Security'
        );

        $mail->addAddress(
            $email,
            $user['first_name'] . ' ' . $user['last_name']
        );

        $mail->isHTML(true);

        $mail->Subject =
            'Your Password Reset OTP - Pitogo EduTrack';

        $mail->Body = "
            <div style='font-family: Arial, sans-serif;
                        background:#f4f7fb;
                        padding:24px;
                        color:#1e293b;'>

                <div style='max-width:560px;
                            margin:auto;
                            background:#ffffff;
                            border-radius:16px;
                            padding:30px;
                            border:1px solid #e2e8f0;'>

                    <h2 style='margin-top:0;
                               color:#0B1C3D;'>

                        Password Reset Request

                    </h2>

                    <p style='font-size:15px;'>

                        Hello
                        <b>{$user['first_name']}</b>,

                    </p>

                    <p style='font-size:15px;
                              line-height:1.6;'>

                        We received a request to reset your
                        Pitogo EduTrack password.

                    </p>

                    <div style='text-align:center;
                                margin:28px 0;'>

                        <span style='display:inline-block;
                                     background:#0B1C3D;
                                     color:#ffffff;
                                     font-size:30px;
                                     letter-spacing:7px;
                                     padding:16px 28px;
                                     border-radius:12px;
                                     font-weight:bold;'>

                            {$otp}

                        </span>

                    </div>

                    <p style='color:#ef4444;
                              font-size:14px;
                              font-weight:bold;'>

                        This OTP will expire in 15 minutes.

                    </p>

                    <p style='font-size:13px;
                              color:#64748b;'>

                        If you did not request this,
                        please ignore this email.

                    </p>

                </div>

            </div>
        ";

        $mail->AltBody =
            "Your OTP is {$otp}. "
            . "This OTP expires in 15 minutes.";

        $mail->send();

        respond(
            true,
            'OTP sent successfully. Please check your email.'
        );

    } catch (Exception $e) {

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | Do NOT break system if SMTP fails
        |--------------------------------------------------------------------------
        */

        error_log(
            "MAIL ERROR: " . $mail->ErrorInfo
        );

        respond(
            true,
            'OTP generated successfully. '
            . 'Email sending is temporarily unavailable.'
        );
    }

} catch (PDOException $e) {

    respond(
        false,
        'System error. Please try again later.'
    );
}
?>
