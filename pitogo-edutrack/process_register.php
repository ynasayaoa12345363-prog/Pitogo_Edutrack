<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

date_default_timezone_set('Asia/Manila');

require 'db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/Exception.php';
require 'PHPMailer/PHPMailer.php';
require 'PHPMailer/SMTP.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - process_register.php
|--------------------------------------------------------------------------
| Supported roles:
| student, parent, admin, staff
|
| Admin/staff registration codes:
| - Must exist in registration_codes table
| - Must be active
| - Must not be used yet
| - Automatically becomes used after successful account creation
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

function ensureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (!columnExists($pdo, $table, $column)) {
        $pdo->exec("ALTER TABLE `$table` ADD COLUMN `$column` $definition");
    }
}

function goBack(string $message): void {
    $safeMessage = addslashes($message);
    echo "<script>alert('{$safeMessage}'); window.history.back();</script>";
    exit();
}

function generateCode(int $length = 10): string {
    $characters = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $code = '';

    for ($i = 0; $i < $length; $i++) {
        $code .= $characters[random_int(0, strlen($characters) - 1)];
    }

    return $code;
}

function generateUniqueUserCode(PDO $pdo, string $column, int $length = 10): string {
    do {
        $code = generateCode($length);
        $stmt = $pdo->prepare("SELECT id FROM users WHERE {$column} = ? LIMIT 1");
        $stmt->execute([$code]);
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
    } while ($exists);

    return $code;
}

try {
    /*
    |--------------------------------------------------------------------------
    | Database safety columns
    |--------------------------------------------------------------------------
    */

    ensureColumn($pdo, 'users', 'id_num', "VARCHAR(100) NULL");
    ensureColumn($pdo, 'users', 'middle_name', "VARCHAR(100) NULL");
    ensureColumn($pdo, 'users', 'parent_access_code', "VARCHAR(50) NULL");
    ensureColumn($pdo, 'users', 'admin_code', "VARCHAR(50) NULL");
    ensureColumn($pdo, 'users', 'staff_code', "VARCHAR(50) NULL");
    ensureColumn($pdo, 'users', 'qr_token', "VARCHAR(100) NULL");
    ensureColumn($pdo, 'users', 'information_correct', "TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'users', 'otp_code', "VARCHAR(10) NULL");
    ensureColumn($pdo, 'users', 'otp_expiry', "DATETIME NULL");
    ensureColumn($pdo, 'users', 'is_deleted', "TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'users', 'is_archived', "TINYINT(1) NOT NULL DEFAULT 0");

    if (!tableExists($pdo, 'parent_student_links')) {
        $pdo->exec("
            CREATE TABLE parent_student_links (
                id INT AUTO_INCREMENT PRIMARY KEY,
                parent_id INT NOT NULL,
                student_id INT NOT NULL,
                status VARCHAR(30) DEFAULT 'active',
                linked_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_parent_student (parent_id, student_id)
            )
        ");
    }

    if (!tableExists($pdo, 'parent_access_codes')) {
        $pdo->exec("
            CREATE TABLE parent_access_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                access_code VARCHAR(50) NOT NULL UNIQUE,
                status VARCHAR(30) DEFAULT 'active',
                expires_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }

    /*
    |--------------------------------------------------------------------------
    | Registration codes table
    |--------------------------------------------------------------------------
    | This is used for one-time admin/staff registration codes.
    |--------------------------------------------------------------------------
    */

    if (!tableExists($pdo, 'registration_codes')) {
        $pdo->exec("
            CREATE TABLE registration_codes (
                id INT AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(100) NOT NULL UNIQUE,
                code_type VARCHAR(30) NOT NULL,
                status VARCHAR(30) DEFAULT 'active',
                notes TEXT NULL,
                created_by INT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                replaced_at DATETIME NULL,
                revoked_at DATETIME NULL,
                is_used TINYINT(1) NOT NULL DEFAULT 0,
                used_by INT NULL,
                used_at DATETIME NULL
            )
        ");
    }

    ensureColumn($pdo, 'registration_codes', 'code', "VARCHAR(100) NOT NULL");
    ensureColumn($pdo, 'registration_codes', 'code_type', "VARCHAR(30) NOT NULL");
    ensureColumn($pdo, 'registration_codes', 'status', "VARCHAR(30) DEFAULT 'active'");
    ensureColumn($pdo, 'registration_codes', 'notes', "TEXT NULL");
    ensureColumn($pdo, 'registration_codes', 'created_by', "INT NULL");
    ensureColumn($pdo, 'registration_codes', 'created_at', "DATETIME DEFAULT CURRENT_TIMESTAMP");
    ensureColumn($pdo, 'registration_codes', 'replaced_at', "DATETIME NULL");
    ensureColumn($pdo, 'registration_codes', 'revoked_at', "DATETIME NULL");
    ensureColumn($pdo, 'registration_codes', 'is_used', "TINYINT(1) NOT NULL DEFAULT 0");
    ensureColumn($pdo, 'registration_codes', 'used_by', "INT NULL");
    ensureColumn($pdo, 'registration_codes', 'used_at', "DATETIME NULL");

} catch (PDOException $e) {
    die("Database setup error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.html');
    exit();
}

/*
|--------------------------------------------------------------------------
| Form values
|--------------------------------------------------------------------------
*/

$role = strtolower(trim($_POST['role'] ?? 'student'));
$allowedRoles = ['student', 'parent', 'admin', 'staff'];

if (!in_array($role, $allowedRoles, true)) {
    goBack('Invalid account type selected.');
}

$firstName = trim($_POST['first_name'] ?? '');
$middleName = trim($_POST['middle_name'] ?? '');
$lastName = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$rawPassword = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';
$informationCorrect = isset($_POST['information_correct']) ? 1 : 0;

$idNum = trim($_POST['id_num'] ?? '');
$studentLrn = trim($_POST['student_lrn'] ?? '');
$parentAccessInput = strtoupper(trim($_POST['parent_access_code'] ?? ''));
$adminCodeInput = strtoupper(trim($_POST['admin_code'] ?? ''));
$staffCodeInput = strtoupper(trim($_POST['staff_code'] ?? ''));

$parentAccessCodeForDb = null;
$adminCodeForDb = null;
$staffCodeForDb = null;
$qrToken = null;
$validParentLinks = [];

$adminRegistrationCodeRow = null;
$staffRegistrationCodeRow = null;

/*
|--------------------------------------------------------------------------
| Base validation
|--------------------------------------------------------------------------
*/

if ($firstName === '' || $lastName === '') {
    goBack('First name and last name are required.');
}

if ($informationCorrect !== 1) {
    goBack('Please confirm that all information provided is correct.');
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    goBack('Invalid email format.');
}

$domain = substr(strrchr($email, '@'), 1);
if (!$domain || !checkdnsrr($domain, 'MX')) {
    goBack('The email domain does not exist or cannot receive emails.');
}

if ($rawPassword === '') {
    goBack('Password is required.');
}

if ($rawPassword !== $confirmPassword) {
    goBack('Passwords do not match.');
}

if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/', $rawPassword)) {
    goBack('Password must have at least 8 characters, uppercase, lowercase, number, and special character.');
}

/*
|--------------------------------------------------------------------------
| Role-specific validation
|--------------------------------------------------------------------------
*/

if ($role === 'student') {
    $idNum = $studentLrn !== '' ? $studentLrn : $idNum;

    if ($idNum === '') {
        goBack('Student LRN is required.');
    }

    if (!preg_match('/^[0-9]{12}$/', $idNum)) {
        goBack('Student LRN must be exactly 12 digits.');
    }

    $parentAccessCodeForDb = generateUniqueUserCode($pdo, 'parent_access_code', 10);
    $qrToken = bin2hex(random_bytes(32));

} elseif ($role === 'parent') {
    $idNum = null;

    if ($parentAccessInput === '') {
        goBack('Please enter the parent access code from the student account.');
    }

    $parentAccessCodeForDb = $parentAccessInput;

    $stmtCodeCheck = $pdo->prepare("
        SELECT 
            pac.id AS code_id,
            pac.student_id,
            pac.access_code,
            pac.status,
            pac.expires_at,
            u.first_name,
            u.middle_name,
            u.last_name
        FROM parent_access_codes pac
        JOIN users u ON u.id = pac.student_id
        WHERE pac.access_code = ?
        AND pac.status = 'active'
        AND (pac.expires_at IS NULL OR pac.expires_at > NOW())
        LIMIT 1
    ");
    $stmtCodeCheck->execute([$parentAccessInput]);
    $codeData = $stmtCodeCheck->fetch(PDO::FETCH_ASSOC);

    if (!$codeData) {
        goBack('Invalid or expired parent access code.');
    }

    $validParentLinks[] = $codeData;

} elseif ($role === 'admin') {
    $idNum = null;

    if ($adminCodeInput === '') {
        goBack('Admin code is required.');
    }

    $adminCodeForDb = $adminCodeInput;

    /*
    |--------------------------------------------------------------------------
    | ONE-TIME ADMIN CODE CHECK
    |--------------------------------------------------------------------------
    | The code must be active and unused.
    |--------------------------------------------------------------------------
    */

    $stmtAdminCode = $pdo->prepare("
        SELECT *
        FROM registration_codes
        WHERE UPPER(code) = ?
        AND LOWER(code_type) = 'admin'
        AND LOWER(status) = 'active'
        AND is_used = 0
        LIMIT 1
    ");
    $stmtAdminCode->execute([$adminCodeInput]);
    $adminRegistrationCodeRow = $stmtAdminCode->fetch(PDO::FETCH_ASSOC);

    if (!$adminRegistrationCodeRow) {
        goBack('Invalid, expired, or already used admin code.');
    }

} elseif ($role === 'staff') {
    $idNum = null;

    if ($staffCodeInput === '') {
        goBack('Staff code is required.');
    }

    $staffCodeForDb = $staffCodeInput;

    /*
    |--------------------------------------------------------------------------
    | ONE-TIME STAFF CODE CHECK
    |--------------------------------------------------------------------------
    | The code must be active and unused.
    |--------------------------------------------------------------------------
    */

    $stmtStaffCode = $pdo->prepare("
        SELECT *
        FROM registration_codes
        WHERE UPPER(code) = ?
        AND LOWER(code_type) = 'staff'
        AND LOWER(status) = 'active'
        AND is_used = 0
        LIMIT 1
    ");
    $stmtStaffCode->execute([$staffCodeInput]);
    $staffRegistrationCodeRow = $stmtStaffCode->fetch(PDO::FETCH_ASSOC);

    if (!$staffRegistrationCodeRow) {
        goBack('Invalid, expired, or already used staff code.');
    }
}

/*
|--------------------------------------------------------------------------
| Duplicate checks
|--------------------------------------------------------------------------
*/

$checkEmail = $pdo->prepare("
    SELECT id, status
    FROM users
    WHERE email = ?
    LIMIT 1
");
$checkEmail->execute([$email]);
$existingUser = $checkEmail->fetch(PDO::FETCH_ASSOC);

if ($existingUser) {
    if ($existingUser['status'] !== 'pending') {
        echo "<script>alert('This email is already registered. Please log in.'); window.location.href='index.html';</script>";
        exit();
    }

    $deleteOld = $pdo->prepare("DELETE FROM users WHERE email = ? AND status = 'pending'");
    $deleteOld->execute([$email]);
}

if ($role === 'student') {
    $checkLrn = $pdo->prepare("
        SELECT id
        FROM users
        WHERE id_num = ?
        AND role = 'student'
        LIMIT 1
    ");
    $checkLrn->execute([$idNum]);

    if ($checkLrn->fetch()) {
        goBack('This student LRN is already registered.');
    }
}

/*
|--------------------------------------------------------------------------
| Save account with OTP
|--------------------------------------------------------------------------
*/

$otp = (string)random_int(100000, 999999);
$expiry = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$password = password_hash($rawPassword, PASSWORD_DEFAULT);

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO users (
            first_name,
            middle_name,
            last_name,
            email,
            password,
            role,
            id_num,
            parent_access_code,
            admin_code,
            staff_code,
            qr_token,
            information_correct,
            status,
            otp_code,
            otp_expiry,
            is_deleted,
            is_archived
        ) VALUES (
            ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?,
            'pending',
            ?, ?,
            0,
            0
        )
    ");

    $stmt->execute([
        $firstName,
        $middleName,
        $lastName,
        $email,
        $password,
        $role,
        $idNum,
        $parentAccessCodeForDb,
        $adminCodeForDb,
        $staffCodeForDb,
        $qrToken,
        $informationCorrect,
        $otp,
        $expiry
    ]);

    $newUserId = (int)$pdo->lastInsertId();

    /*
    |--------------------------------------------------------------------------
    | Mark admin/staff registration code as used
    |--------------------------------------------------------------------------
    */

    if ($role === 'admin' && $adminRegistrationCodeRow) {
        $markUsed = $pdo->prepare("
            UPDATE registration_codes
            SET
                is_used = 1,
                used_by = ?,
                used_at = NOW(),
                status = 'used'
            WHERE id = ?
            AND is_used = 0
        ");
        $markUsed->execute([$newUserId, $adminRegistrationCodeRow['id']]);

        if ($markUsed->rowCount() < 1) {
            throw new Exception('This admin code was already used. Please request a new code.');
        }
    }

    if ($role === 'staff' && $staffRegistrationCodeRow) {
        $markUsed = $pdo->prepare("
            UPDATE registration_codes
            SET
                is_used = 1,
                used_by = ?,
                used_at = NOW(),
                status = 'used'
            WHERE id = ?
            AND is_used = 0
        ");
        $markUsed->execute([$newUserId, $staffRegistrationCodeRow['id']]);

        if ($markUsed->rowCount() < 1) {
            throw new Exception('This staff code was already used. Please request a new code.');
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Store student parent access code
    |--------------------------------------------------------------------------
    */

    if ($role === 'student' && $parentAccessCodeForDb) {
        $saveCode = $pdo->prepare("
            INSERT INTO parent_access_codes (
                student_id,
                access_code,
                status,
                expires_at,
                created_at
            ) VALUES (
                ?, ?, 'active', NULL, NOW()
            )
        ");
        $saveCode->execute([$newUserId, $parentAccessCodeForDb]);
    }

    /*
    |--------------------------------------------------------------------------
    | Link parent to student
    |--------------------------------------------------------------------------
    */

    if ($role === 'parent' && !empty($validParentLinks)) {
        foreach ($validParentLinks as $link) {
            $linkStmt = $pdo->prepare("
                INSERT IGNORE INTO parent_student_links (
                    parent_id,
                    student_id,
                    status,
                    linked_at
                ) VALUES (
                    ?, ?, 'active', NOW()
                )
            ");
            $linkStmt->execute([$newUserId, $link['student_id']]);
        }
    }

    $pdo->commit();

    /*
    |--------------------------------------------------------------------------
    | Send OTP email
    |--------------------------------------------------------------------------
    */

    /*
|--------------------------------------------------------------------------
| SEND OTP EMAIL VIA BREVO API
|--------------------------------------------------------------------------
*/

$brevoApiKey = getenv('BREVO_API_KEY');

$senderEmail =
    getenv('BREVO_SENDER_EMAIL')
    ?: 'margeauxcosmetics16@gmail.com';

$senderName =
    getenv('BREVO_SENDER_NAME')
    ?: 'Pitogo EduTrack';

if (!$brevoApiKey) {

    die('Brevo API key missing in Railway Variables.');
}

$safeFirstName = htmlspecialchars(
    $firstName,
    ENT_QUOTES,
    'UTF-8'
);

$emailData = [

    "sender" => [
        "name" => $senderName,
        "email" => $senderEmail
    ],

    "to" => [
        [
            "email" => $email,
            "name" => trim($firstName . ' ' . $lastName)
        ]
    ],

    "subject" =>
        "Pitogo EduTrack Verification Code",

    "htmlContent" => "

        <div style='font-family:Arial,sans-serif;
                    background:#f4f7fb;
                    padding:25px;'>

            <div style='max-width:520px;
                        margin:auto;
                        background:#ffffff;
                        border-radius:14px;
                        padding:30px;
                        border:1px solid #e5e7eb;'>

                <h2 style='color:#0B1C3D;
                           margin-top:0;'>

                    Pitogo EduTrack Verification

                </h2>

                <p style='color:#334155;
                          font-size:15px;'>

                    Hello <b>{$safeFirstName}</b>,

                </p>

                <p style='color:#334155;
                          font-size:15px;
                          line-height:1.6;'>

                    Use the OTP below
                    to verify your registration.
                    This code will expire
                    in 10 minutes.

                </p>

                <div style='text-align:center;
                            margin:28px 0;'>

                    <span style='display:inline-block;
                                 background:#0B1C3D;
                                 color:#ffffff;
                                 font-size:28px;
                                 letter-spacing:6px;
                                 padding:14px 24px;
                                 border-radius:10px;
                                 font-weight:bold;'>

                        {$otp}

                    </span>

                </div>

                <p style='color:#64748b;
                          font-size:13px;
                          line-height:1.6;'>

                    If you did not create
                    this account,
                    you can ignore this email.

                </p>

            </div>

        </div>

    "
];

/*
|--------------------------------------------------------------------------
| SEND REQUEST
|--------------------------------------------------------------------------
*/

$ch = curl_init(
    "https://api.brevo.com/v3/smtp/email"
);

curl_setopt_array($ch, [

    CURLOPT_RETURNTRANSFER => true,

    CURLOPT_POST => true,

    CURLOPT_HTTPHEADER => [

        "accept: application/json",

        "api-key: {$brevoApiKey}",

        "content-type: application/json"

    ],

    CURLOPT_POSTFIELDS =>
        json_encode($emailData),

    CURLOPT_TIMEOUT => 60
]);

$response = curl_exec($ch);

$error = curl_error($ch);

$httpCode = curl_getinfo(
    $ch,
    CURLINFO_HTTP_CODE
);

curl_close($ch);

/*
|--------------------------------------------------------------------------
| RESPONSE
|--------------------------------------------------------------------------
*/

if ($error) {

    die(
        'Brevo API Error: '
        . htmlspecialchars($error)
    );
}

if ($httpCode >= 200 && $httpCode < 300) {

    $_SESSION['verify_email'] = $email;

    header('Location: verify-otp.php');

    exit();
}

die(
    'Brevo API Failed. HTTP '
    . $httpCode
    . ' RESPONSE: '
    . htmlspecialchars($response)
);

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    die('Database saving failed: ' . $e->getMessage());
}
?>
