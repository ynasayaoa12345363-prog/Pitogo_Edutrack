<?php
session_start();
require 'db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - verify-otp.php
|--------------------------------------------------------------------------
| Purpose:
| Verifies registration OTP.
|
| Important:
| After successful OTP verification, account remains "pending"
| until approved by admin/superadmin in User Management.
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

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

function logAudit(PDO $pdo, ?int $userId, string $role, string $action): void {
    if (!tableExists($pdo, 'audit_logs')) {
        return;
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO audit_logs (
                user_id,
                user_role,
                action,
                target_table,
                target_id,
                ip_address,
                created_at
            ) VALUES (
                ?, ?, ?, 'users', ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $userId,
            $role,
            $action,
            $userId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        return;
    }
}

if (!isset($_SESSION['verify_email'])) {
    header("Location: index.html");
    exit();
}

$email = trim($_SESSION['verify_email']);
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $userOtp = trim($_POST['otp'] ?? '');

    if (!preg_match('/^[0-9]{6}$/', $userOtp)) {
        $error = "Please enter a valid 6-digit OTP code.";
    } else {
        try {
            $stmt = $pdo->prepare("
                SELECT id, first_name, last_name, email, role, otp_code, otp_expiry, status
                FROM users
                WHERE email = ?
                AND is_deleted = 0
                AND is_archived = 0
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                $error = "Account not found. Please register again.";
            } elseif (empty($user['otp_code']) || !hash_equals((string)$user['otp_code'], (string)$userOtp)) {
                $error = "Invalid OTP code. Please check your email and try again.";
            } elseif (empty($user['otp_expiry']) || strtotime($user['otp_expiry']) < time()) {
                $error = "OTP expired. Please register again or request a new code.";
            } else {
                /*
                |--------------------------------------------------------------------------
                | OTP verified
                |--------------------------------------------------------------------------
                | Keep account status as pending.
                | Admin/superadmin approval is still required before login.
                |--------------------------------------------------------------------------
                */

                $update = $pdo->prepare("
                    UPDATE users
                    SET otp_code = NULL,
                        otp_expiry = NULL,
                        email_verified_at = NOW(),
                        status = 'pending'
                    WHERE id = ?
                ");
                $update->execute([$user['id']]);

                if (tableExists($pdo, 'otp_logs')) {
                    $otpLog = $pdo->prepare("
                        UPDATE otp_logs
                        SET status = 'verified'
                        WHERE email = ?
                        AND otp_code = ?
                        AND purpose = 'registration'
                        AND status = 'pending'
                    ");
                    $otpLog->execute([$email, $userOtp]);
                }

                logAudit(
                    $pdo,
                    (int)$user['id'],
                    strtolower($user['role'] ?? 'user'),
                    'Verified registration OTP and awaiting admin approval'
                );

                unset($_SESSION['verify_email']);

                echo '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Verification Successful | Pitogo EduTrack</title>

                    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

                    <style>
                        :root {
                            --primary-navy:#0B1C3D;
                            --primary-blue:#0056B3;
                            --bg-main:#F4F7F9;
                            --text-muted:#64748B;
                            --success:#10B981;
                        }

                        * {
                            box-sizing:border-box;
                        }

                        body {
                            font-family:"Inter", sans-serif;
                            background:
                                linear-gradient(rgba(11,28,61,.88), rgba(11,28,61,.92)),
                                url("school-bg.jpg");
                            background-size:cover;
                            background-position:center;
                            min-height:100vh;
                            margin:0;
                            display:flex;
                            justify-content:center;
                            align-items:center;
                            padding:24px;
                        }

                        .success-modal {
                            background:white;
                            padding:48px 40px;
                            border-radius:22px;
                            width:100%;
                            max-width:460px;
                            text-align:center;
                            box-shadow:0 25px 60px rgba(0,0,0,.18);
                            animation:popIn .35s ease forwards;
                        }

                        @keyframes popIn {
                            from {
                                opacity:0;
                                transform:scale(.92) translateY(12px);
                            }
                            to {
                                opacity:1;
                                transform:scale(1) translateY(0);
                            }
                        }

                        .logo {
                            width:82px;
                            height:82px;
                            border-radius:50%;
                            background:#fff;
                            border:1px solid #E2E8F0;
                            padding:7px;
                            margin:0 auto 18px;
                            object-fit:contain;
                        }

                        .success-icon {
                            background:#DCFCE7;
                            color:#16A34A;
                            width:78px;
                            height:78px;
                            border-radius:50%;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            margin:0 auto 20px;
                        }

                        .success-icon svg {
                            width:40px;
                            height:40px;
                        }

                        h2 {
                            font-family:"Montserrat", sans-serif;
                            color:var(--primary-navy);
                            margin:0 0 14px;
                            font-size:1.55rem;
                            font-weight:800;
                        }

                        p {
                            color:var(--text-muted);
                            font-size:15px;
                            line-height:1.7;
                            margin:0 0 28px;
                        }

                        .status-box {
                            background:#EFF6FF;
                            border:1px solid #BFDBFE;
                            border-radius:14px;
                            color:#1E40AF;
                            padding:14px;
                            line-height:1.6;
                            font-size:14px;
                            margin-bottom:24px;
                        }

                        .btn-ok {
                            background:var(--primary-navy);
                            color:white;
                            border:none;
                            padding:15px;
                            width:100%;
                            font-size:15px;
                            font-weight:800;
                            border-radius:12px;
                            cursor:pointer;
                            transition:.3s ease;
                            font-family:"Montserrat", sans-serif;
                        }

                        .btn-ok:hover {
                            background:var(--primary-blue);
                            transform:translateY(-2px);
                        }
                    </style>
                </head>

                <body>
                    <div class="success-modal">
                        <img src="school-logo.jpg" class="logo" alt="Pitogo High School Logo" onerror="this.style.display=\'none\';">

                        <div class="success-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="3">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                            </svg>
                        </div>

                        <h2>Verification Successful!</h2>

                        <p>
                            Your email has been verified successfully.
                        </p>

                        <div class="status-box">
                            Your account is now <b>pending admin approval</b>.
                            You can log in once the school administrator approves your registration.
                        </div>

                        <button class="btn-ok" onclick="window.location.href=\'index.html\'">
                            Back to Login
                        </button>
                    </div>
                </body>
                </html>
                ';
                exit();
            }

        } catch (PDOException $e) {
            $error = "System error. Please try again.";
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Verify Account | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

<style>
:root {
    --primary-navy:#0B1C3D;
    --primary-blue:#0056B3;
    --bg-main:#F4F7F9;
    --surface-white:#FFFFFF;
    --text-dark:#1E293B;
    --text-muted:#64748B;
    --border-color:#E2E8F0;
    --danger:#EF4444;
}

* {
    box-sizing:border-box;
}

body {
    font-family:"Inter", sans-serif;
    background:
        linear-gradient(rgba(11,28,61,.88), rgba(11,28,61,.92)),
        url("school-bg.jpg");
    background-size:cover;
    background-position:center;
    min-height:100vh;
    margin:0;
    color:var(--text-dark);
    display:flex;
    justify-content:center;
    align-items:center;
    padding:24px;
}

.verify-container {
    background:var(--surface-white);
    padding:46px 40px;
    border-radius:22px;
    box-shadow:0 25px 60px rgba(0,0,0,.18);
    text-align:center;
    max-width:460px;
    width:100%;
}

.logo {
    width:86px;
    height:86px;
    border-radius:50%;
    background:white;
    border:1px solid var(--border-color);
    padding:7px;
    margin:0 auto 18px;
    object-fit:contain;
}

.icon-wrapper {
    background:#E0F2FE;
    width:72px;
    height:72px;
    border-radius:50%;
    display:flex;
    align-items:center;
    justify-content:center;
    margin:0 auto 20px;
}

.icon-wrapper svg {
    width:36px;
    height:36px;
    color:var(--primary-blue);
}

.verify-container h2 {
    font-family:"Montserrat", sans-serif;
    color:var(--primary-navy);
    margin:0 0 10px;
    font-size:1.6rem;
    font-weight:800;
}

.verify-container p {
    color:var(--text-muted);
    font-size:15px;
    line-height:1.7;
    margin-bottom:28px;
}

.email-highlight {
    font-weight:800;
    color:var(--primary-navy);
}

.otp-input {
    width:100%;
    padding:18px;
    font-size:28px;
    font-weight:800;
    text-align:center;
    letter-spacing:12px;
    border:2px solid #CBD5E1;
    border-radius:12px;
    outline:none;
    margin-bottom:20px;
    transition:.3s ease;
    color:#0F172A;
}

.otp-input:focus {
    border-color:var(--primary-blue);
    box-shadow:0 0 0 4px rgba(0,86,179,.12);
}

.otp-input::placeholder {
    color:#94A3B8;
    font-weight:400;
}

.btn-verify {
    background:var(--primary-navy);
    color:white;
    border:none;
    padding:15px;
    width:100%;
    font-size:15px;
    font-weight:800;
    border-radius:12px;
    cursor:pointer;
    transition:.3s ease;
    font-family:"Montserrat", sans-serif;
}

.btn-verify:hover {
    background:var(--primary-blue);
    transform:translateY(-2px);
}

.error-msg {
    color:#991B1B;
    background:#FEE2E2;
    border:1px solid #FECACA;
    padding:13px;
    border-radius:12px;
    margin-bottom:18px;
    font-size:14px;
    font-weight:700;
}

.back-link {
    display:inline-block;
    margin-top:18px;
    color:var(--text-muted);
    text-decoration:none;
    font-size:.9rem;
    font-weight:700;
}

.back-link:hover {
    color:var(--primary-blue);
}

@media(max-width:520px) {
    .verify-container {
        padding:34px 24px;
    }

    .otp-input {
        letter-spacing:9px;
        font-size:24px;
    }
}
</style>
</head>

<body>

<div class="verify-container">
    <img src="school-logo.jpg" class="logo" alt="Pitogo High School Logo" onerror="this.style.display='none';">

    <div class="icon-wrapper">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round"
            d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
        </svg>
    </div>

    <h2>Verify Your Email</h2>

    <p>
        We sent a 6-digit OTP code to<br>
        <span class="email-highlight"><?= e($email) ?></span>
    </p>

    <?php if ($error): ?>
        <div class="error-msg"><?= e($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <input
            type="text"
            name="otp"
            class="otp-input"
            required
            maxlength="6"
            inputmode="numeric"
            pattern="[0-9]{6}"
            placeholder="••••••"
            autocomplete="one-time-code"
            oninput="this.value=this.value.replace(/[^0-9]/g,'').slice(0,6);"
        >

        <button type="submit" class="btn-verify">
            Confirm Code
        </button>
    </form>

    <a href="index.html" class="back-link">
        Back to Login
    </a>
</div>

</body>
</html>
