<?php
session_start();
require 'db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Fetch active user
    $stmt = $pdo->prepare("
        SELECT * 
        FROM users 
        WHERE email = ? 
        AND is_deleted = 0 
        AND is_archived = 0
    ");

    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Check if account exists
    if ($user) {

        // Verify password
        if (
            password_verify($password, $user['password']) ||
            $password === $user['password']
        ) {

            // Pending account check
            if ($user['status'] == 'pending') {

                echo '
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1.0">
                    <title>Account Pending | Pitogo EduTrack</title>

                    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

                    <style>
                        body{
                            font-family: "Inter", sans-serif;
                            background:#f1f5f9;
                            display:flex;
                            justify-content:center;
                            align-items:center;
                            height:100vh;
                            margin:0;
                        }

                        .pending-modal{
                            background:white;
                            padding:50px 40px;
                            border-radius:18px;
                            width:100%;
                            max-width:420px;
                            text-align:center;
                            box-shadow:0 15px 35px rgba(0,0,0,0.08);
                        }

                        .pending-icon{
                            width:85px;
                            height:85px;
                            background:#fff7ed;
                            color:#ea580c;
                            border-radius:50%;
                            display:flex;
                            align-items:center;
                            justify-content:center;
                            margin:0 auto 20px;
                        }

                        .pending-icon svg{
                            width:42px;
                            height:42px;
                        }

                        h2{
                            color:#0f172a;
                            margin-bottom:15px;
                        }

                        p{
                            color:#64748b;
                            line-height:1.6;
                            margin-bottom:25px;
                        }

                        .btn-back{
                            width:100%;
                            padding:15px;
                            border:none;
                            border-radius:12px;
                            background:#0b2240;
                            color:white;
                            font-size:15px;
                            font-weight:600;
                            cursor:pointer;
                            transition:0.3s ease;
                        }

                        .btn-back:hover{
                            background:#173661;
                        }
                    </style>
                </head>

                <body>

                    <div class="pending-modal">

                        <div class="pending-icon">
                            <svg fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round"
                                d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                        </div>

                        <h2>Account Pending</h2>

                        <p>
                            Your account has been verified but is still awaiting
                            approval from the administrator.
                        </p>

                        <button class="btn-back"
                        onclick="window.location.href=\'index.html\'">
                            Back to Login
                        </button>

                    </div>

                </body>
                </html>
                ';

                exit();
            }

            // Clean role
            $dbRole = strtolower(trim($user['role'] ?? 'student'));

            // Store sessions
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['role'] = $dbRole;
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['email'] = $user['email'];

            // Optional student session info
            $_SESSION['student_name'] =
                $user['first_name'] . ' ' . ($user['last_name'] ?? '');

            // Redirect based on role
            if ($dbRole === 'admin' || $dbRole === 'superadmin') {

                header("Location: admin/admin-dashboard.php");

            } elseif ($dbRole === 'staff') {

                header("Location: staff/qr-scanner.php");

            } elseif ($dbRole === 'parent') {

                header("Location: parent/dashboard.php");

            } elseif ($dbRole === 'student') {

                header("Location: student/dashboard.php");

            } else {

                // Invalid role
                header("Location: index.html?error=invalid_role");
            }

            exit();

        } else {

            // Wrong password
            header("Location: index.html?error=wrong_password&email=" . urlencode($email));
            exit();
        }

    } else {

        // No account
        header("Location: index.html?error=no_account");
        exit();
    }
}
?>
