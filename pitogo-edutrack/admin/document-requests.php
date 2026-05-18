<?php
session_start();
require '../db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../PHPMailer/Exception.php';
require '../PHPMailer/PHPMailer.php';
require '../PHPMailer/SMTP.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Admin Document Requests
|--------------------------------------------------------------------------
| Save as:
| admin/document-requests.php
|
| Supports:
| - student/parent document requests
| - admin/superadmin processing
| - PDF upload for digital release
| - OTP protected document access
| - hard copy pickup instructions
| - archive, approve, reject, release
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    header("Location: ../index.html");
    exit();
}

$currentUserId = (int)$_SESSION['user_id'];
$currentRole = strtolower(trim($_SESSION['role']));

if (!in_array($currentRole, ['admin', 'superadmin'], true)) {
    header("Location: ../index.html?error=" . urlencode("Unauthorized access."));
    exit();
}

$isSuperAdmin = $currentRole === 'superadmin';
$themeColor = $isSuperAdmin ? '#7C3AED' : '#0056B3';
$roleLabel = $isSuperAdmin ? 'Super Administrator' : 'Administrator';

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

function logAudit(PDO $pdo, int $userId, string $role, string $action, string $targetTable = 'document_requests', ?int $targetId = null): void {
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
                ?, ?, ?, ?, ?, ?, NOW()
            )
        ");

        $stmt->execute([
            $userId,
            $role,
            $action,
            $targetTable,
            $targetId,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    } catch (Exception $e) {
        return;
    }
}

function getFullName(array $row, string $prefix = ''): string {
    $first = $row[$prefix . 'first_name'] ?? '';
    $middle = $row[$prefix . 'middle_name'] ?? '';
    $last = $row[$prefix . 'last_name'] ?? '';

    return trim($first . ' ' . $middle . ' ' . $last);
}

function profilePath(array $row, string $name): string {
    $profile = $row['profile_picture'] ?? $row['profile_pic'] ?? '';

    if ($profile) {
        if (preg_match('/^https?:\/\//i', $profile)) {
            return $profile;
        }

        return '../uploads/profiles/' . ltrim($profile, '/');
    }

    return 'https://ui-avatars.com/api/?name=' . urlencode($name ?: 'User') . '&background=0056B3&color=fff';
}

function redirectWithMsg(string $type, string $message): void {
    header("Location: document-requests.php?msg_type=" . urlencode($type) . "&msg=" . urlencode($message));
    exit();
}


function getRequesterInfo(PDO $pdo, int $requestId): array {
    $stmt = $pdo->prepare("
        SELECT
            dr.id,
            dr.document_type,
            dr.tracking_id,
            COALESCE(req.id, stu.id) AS recipient_id,
            COALESCE(req.email, stu.email) AS recipient_email,
            COALESCE(
                NULLIF(TRIM(CONCAT(req.first_name, ' ', COALESCE(req.middle_name, ''), ' ', req.last_name)), ''),
                NULLIF(TRIM(CONCAT(stu.first_name, ' ', COALESCE(stu.middle_name, ''), ' ', stu.last_name)), ''),
                'Requester'
            ) AS recipient_name,
            TRIM(CONCAT(stu.first_name, ' ', COALESCE(stu.middle_name, ''), ' ', stu.last_name)) AS student_name
        FROM document_requests dr
        LEFT JOIN users req ON req.id = dr.requested_by
        LEFT JOIN users stu ON stu.id = dr.student_id
        WHERE dr.id = ?
        LIMIT 1
    ");
    $stmt->execute([$requestId]);

    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

function sendDocumentOtpEmail(string $recipientEmail, string $recipientName, string $documentType, string $trackingId, string $otp): bool {
    if ($recipientEmail === '' || !filter_var($recipientEmail, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;

        /*
        |--------------------------------------------------------------------------
        | IMPORTANT:
        | Replace these with your Gmail address and Gmail App Password if needed.
        |--------------------------------------------------------------------------
        */
        $mail->Username = 'margeauxcosmetics16@gmail.com';
        $mail->Password = 'piagntijndkisiko';

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('margeauxcosmetics16@gmail.com', 'Pitogo EduTrack');
        $mail->addAddress($recipientEmail, $recipientName ?: 'Requester');

        $mail->isHTML(true);
        $mail->Subject = 'Pitogo EduTrack Document Access OTP';

        $safeName = htmlspecialchars($recipientName ?: 'Requester', ENT_QUOTES, 'UTF-8');
        $safeDoc = htmlspecialchars($documentType ?: 'Requested Document', ENT_QUOTES, 'UTF-8');
        $safeTracking = htmlspecialchars($trackingId ?: 'N/A', ENT_QUOTES, 'UTF-8');
        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;background:#f4f7fb;padding:25px;'>
                <div style='max-width:560px;margin:auto;background:#ffffff;border-radius:14px;padding:30px;border:1px solid #e5e7eb;'>
                    <h2 style='color:#0B1C3D;margin-top:0;'>Pitogo EduTrack Document Access</h2>

                    <p style='color:#334155;font-size:15px;'>Hello <b>{$safeName}</b>,</p>

                    <p style='color:#334155;font-size:15px;line-height:1.6;'>
                        Your requested soft copy document has been released and is now ready for secure access.
                    </p>

                    <div style='background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;padding:16px;margin:18px 0;'>
                        <p style='margin:0 0 8px;color:#334155;'><b>Document:</b> {$safeDoc}</p>
                        <p style='margin:0;color:#334155;'><b>Tracking ID:</b> {$safeTracking}</p>
                    </div>

                    <p style='color:#334155;font-size:15px;line-height:1.6;'>
                        Use the OTP below to open or download your soft copy document:
                    </p>

                    <div style='text-align:center;margin:26px 0;'>
                        <span style='display:inline-block;background:#0B1C3D;color:#ffffff;font-size:30px;letter-spacing:6px;padding:14px 26px;border-radius:10px;font-weight:bold;'>
                            {$safeOtp}
                        </span>
                    </div>

                    <p style='color:#64748b;font-size:13px;line-height:1.6;'>
                        This OTP is for document access only. Do not share it with anyone.
                    </p>
                </div>
            </div>
        ";

        $mail->AltBody = "Your Pitogo EduTrack document access OTP is: {$otp}";

        $mail->send();
        return true;

    } catch (Exception $e) {
        return false;
    }
}


function sendLinkedParentOtpEmails(PDO $pdo, int $studentId, string $requesterEmail, string $documentType, string $trackingId, string $otp): int {
    if ($studentId <= 0) {
        return 0;
    }

    try {
        $stmtParents = $pdo->prepare("
            SELECT DISTINCT
                p.email,
                p.first_name,
                p.last_name
            FROM parent_student_links psl
            INNER JOIN users p ON p.id = psl.parent_id
            WHERE psl.student_id = ?
              AND LOWER(p.role) = 'parent'
              AND LOWER(p.status) = 'active'
              AND p.email IS NOT NULL
              AND p.email != ''
        ");
        $stmtParents->execute([$studentId]);
        $parents = $stmtParents->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return 0;
    }

    $sent = 0;
    $sentEmails = [];

    if ($requesterEmail !== '') {
        $sentEmails[] = strtolower(trim($requesterEmail));
    }

    foreach ($parents as $parent) {
        $email = trim((string)($parent['email'] ?? ''));

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            continue;
        }

        if (in_array(strtolower($email), $sentEmails, true)) {
            continue;
        }

        $name = trim(($parent['first_name'] ?? '') . ' ' . ($parent['last_name'] ?? ''));

        if (sendDocumentOtpEmail($email, $name ?: 'Parent', $documentType, $trackingId, $otp)) {
            $sent++;
            $sentEmails[] = strtolower($email);
        }
    }

    return $sent;
}


/*
|--------------------------------------------------------------------------
| Database safety
|--------------------------------------------------------------------------
*/

try {
    if (!tableExists($pdo, 'document_requests')) {
        $pdo->exec("
            CREATE TABLE document_requests (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NULL,
                requested_by INT NULL,
                tracking_id VARCHAR(80) NULL,
                document_type VARCHAR(150) NOT NULL,
                purpose TEXT NULL,
                status VARCHAR(30) DEFAULT 'pending',
                release_method VARCHAR(50) NULL,
                file_path VARCHAR(255) NULL,
                access_otp VARCHAR(10) NULL,
                otp_used TINYINT(1) NOT NULL DEFAULT 0,
                otp_verified TINYINT(1) NOT NULL DEFAULT 0,
                pickup_date DATE NULL,
                instructions TEXT NULL,
                remarks TEXT NULL,
                processed_by INT NULL,
                processed_at DATETIME NULL,
                archived_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            )
        ");
    }

    $neededColumns = [
        'student_id' => 'INT NULL',
        'requested_by' => 'INT NULL',
        'tracking_id' => 'VARCHAR(80) NULL',
        'document_type' => 'VARCHAR(150) NOT NULL',
        'purpose' => 'TEXT NULL',
        'status' => "VARCHAR(30) DEFAULT 'pending'",
        'release_method' => 'VARCHAR(50) NULL',
        'file_path' => 'VARCHAR(255) NULL',
        'access_otp' => 'VARCHAR(10) NULL',
        'otp_used' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'otp_verified' => 'TINYINT(1) NOT NULL DEFAULT 0',
        'pickup_date' => 'DATE NULL',
        'instructions' => 'TEXT NULL',
        'remarks' => 'TEXT NULL',
        'processed_by' => 'INT NULL',
        'processed_at' => 'DATETIME NULL',
        'archived_at' => 'DATETIME NULL',
        'file_expires_at' => 'DATETIME NULL',
        'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP',
        'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ];

    foreach ($neededColumns as $column => $definition) {
        if (!columnExists($pdo, 'document_requests', $column)) {
            $pdo->exec("ALTER TABLE document_requests ADD COLUMN $column $definition");
        }
    }

    // Convert old ENUM status to flexible VARCHAR so archived/released works without enum errors.
    try {
        $pdo->exec("ALTER TABLE document_requests MODIFY COLUMN status VARCHAR(30) DEFAULT 'pending'");
    } catch (Exception $e) {}

} catch (Exception $e) {
    die("Document request database setup failed: " . e($e->getMessage()));
}

/*
|--------------------------------------------------------------------------
| Current admin
|--------------------------------------------------------------------------
*/

$stmtAdmin = $pdo->prepare("SELECT * FROM users WHERE id = ? LIMIT 1");
$stmtAdmin->execute([$currentUserId]);
$admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC) ?: [];

$adminNameRaw = getFullName($admin) ?: 'System Admin';
$adminAvatar = profilePath($admin, $adminNameRaw);

/*
|--------------------------------------------------------------------------
| Handle actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = strtolower(trim($_POST['action'] ?? ''));
    $requestId = (int)($_POST['request_id'] ?? 0);

    if ($requestId <= 0) {
        redirectWithMsg('danger', 'Invalid document request.');
    }

    $stmtRequest = $pdo->prepare("SELECT * FROM document_requests WHERE id = ? LIMIT 1");
    $stmtRequest->execute([$requestId]);
    $request = $stmtRequest->fetch(PDO::FETCH_ASSOC);

    if (!$request) {
        redirectWithMsg('danger', 'Document request not found.');
    }

    try {
        if ($action === 'approve') {
            $stmt = $pdo->prepare("
                UPDATE document_requests
                SET status = 'approved',
                    processed_by = ?,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$currentUserId, $requestId]);

            logAudit($pdo, $currentUserId, $currentRole, 'Approved document request', 'document_requests', $requestId);
            redirectWithMsg('success', 'Document request approved.');
        }

        if ($action === 'reject') {
            $remarks = trim($_POST['remarks'] ?? '');

            $stmt = $pdo->prepare("
                UPDATE document_requests
                SET status = 'rejected',
                    remarks = ?,
                    processed_by = ?,
                    processed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$remarks, $currentUserId, $requestId]);

            logAudit($pdo, $currentUserId, $currentRole, 'Rejected document request', 'document_requests', $requestId);
            redirectWithMsg('danger', 'Document request rejected.');
        }

        if ($action === 'release') {
            $releaseMethod = strtolower(trim($_POST['release_method'] ?? 'digital'));

            if ($releaseMethod === 'digital') {
                if (!isset($_FILES['document_file']) || $_FILES['document_file']['error'] !== UPLOAD_ERR_OK) {
                    redirectWithMsg('danger', 'Please upload a valid PDF file.');
                }

                $fileTmp = $_FILES['document_file']['tmp_name'];
                $fileSize = (int)$_FILES['document_file']['size'];
                $originalName = basename($_FILES['document_file']['name']);

                $mime = function_exists('mime_content_type') ? mime_content_type($fileTmp) : '';

                if ($mime !== 'application/pdf') {
                    redirectWithMsg('danger', 'Only PDF files are allowed.');
                }

                if ($fileSize > 10 * 1024 * 1024) {
                    redirectWithMsg('danger', 'PDF file must not exceed 10MB.');
                }

                $uploadDir = '../uploads/documents/';

                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0777, true);
                }

                $safeName = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
                $fileName = 'DOC_' . $requestId . '_' . time() . '_' . $safeName;
                $targetPath = $uploadDir . $fileName;
                $dbPath = 'uploads/documents/' . $fileName;

                if (!move_uploaded_file($fileTmp, $targetPath)) {
                    redirectWithMsg('danger', 'Failed to upload file. Please check folder permission.');
                }

                $otp = (string)random_int(100000, 999999);

                $stmt = $pdo->prepare("
                    UPDATE document_requests
                    SET status = 'released',
                        release_method = 'digital',
                        file_path = ?,
                        access_otp = ?,
                        file_expires_at = DATE_ADD(NOW(), INTERVAL 2 YEAR),
                        otp_used = 0,
                        otp_verified = 0,
                        processed_by = ?,
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$dbPath, $otp, $currentUserId, $requestId]);

                $recipientInfo = getRequesterInfo($pdo, $requestId);
                $recipientId = (int)($recipientInfo['recipient_id'] ?? 0);
                $recipientEmail = trim((string)($recipientInfo['recipient_email'] ?? ''));
                $recipientName = trim((string)($recipientInfo['recipient_name'] ?? 'Requester'));
                $documentType = trim((string)($recipientInfo['document_type'] ?? 'Requested Document'));
                $trackingId = trim((string)($recipientInfo['tracking_id'] ?? ''));

                if ($recipientEmail === '') {
                    logAudit($pdo, $currentUserId, $currentRole, 'Released digital document but requester email was missing', 'document_requests', $requestId);
                    redirectWithMsg('danger', 'Digital document was released, but no requester email was found for sending the OTP.');
                }

                if (tableExists($pdo, 'otp_logs')) {
                    $logOtp = $pdo->prepare("
                        INSERT INTO otp_logs (
                            user_id,
                            email,
                            otp_code,
                            purpose,
                            status,
                            expires_at,
                            created_at
                        ) VALUES (
                            ?, ?, ?, 'document_access', 'pending', DATE_ADD(NOW(), INTERVAL 1 DAY), NOW()
                        )
                    ");
                    $logOtp->execute([$recipientId ?: null, $recipientEmail, $otp]);
                }

                if (tableExists($pdo, 'document_access_logs')) {
                    $accessLog = $pdo->prepare("
                        INSERT INTO document_access_logs (
                            document_request_id,
                            user_id,
                            access_type,
                            created_at
                        ) VALUES (
                            ?, ?, 'otp_sent', NOW()
                        )
                    ");
                    $accessLog->execute([$requestId, $recipientId ?: null]);
                }

                $emailSent = sendDocumentOtpEmail($recipientEmail, $recipientName, $documentType, $trackingId, $otp);

                $parentOtpSentCount = 0;
                if ($emailSent) {
                    $parentOtpSentCount = sendLinkedParentOtpEmails(
                        $pdo,
                        (int)($request['student_id'] ?? 0),
                        $recipientEmail,
                        $documentType,
                        $trackingId,
                        $otp
                    );
                }

                if (!$emailSent) {
                    logAudit($pdo, $currentUserId, $currentRole, 'Released digital document but OTP email failed', 'document_requests', $requestId);
                    redirectWithMsg('danger', 'Digital document was released, but OTP email failed to send. Please check PHPMailer settings.');
                }

                logAudit($pdo, $currentUserId, $currentRole, 'Released digital document and emailed OTP to requester', 'document_requests', $requestId);
                redirectWithMsg('success', 'Digital document released successfully. The OTP was emailed to the requester and linked parent account(s).');
            }

            if ($releaseMethod === 'hard_copy') {
                $pickupDate = trim($_POST['pickup_date'] ?? '');
                $instructions = trim($_POST['instructions'] ?? '');

                if ($pickupDate === '') {
                    redirectWithMsg('danger', 'Pickup date is required for hard copy release.');
                }

                $stmt = $pdo->prepare("
                    UPDATE document_requests
                    SET status = 'approved',
                        release_method = 'hard_copy',
                        pickup_date = ?,
                        instructions = ?,
                        processed_by = ?,
                        processed_at = NOW(),
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([$pickupDate, $instructions, $currentUserId, $requestId]);

                logAudit($pdo, $currentUserId, $currentRole, 'Scheduled hard copy document pickup', 'document_requests', $requestId);
                redirectWithMsg('success', 'Hard copy pickup schedule saved.');
            }
        }

        if ($action === 'archive') {
            if (!$isSuperAdmin) {
                redirectWithMsg('danger', 'Only superadmin can archive document requests.');
            }

            $stmt = $pdo->prepare("
                UPDATE document_requests
                SET status = 'archived',
                    archived_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$requestId]);

            logAudit($pdo, $currentUserId, $currentRole, 'Archived document request', 'document_requests', $requestId);
            redirectWithMsg('warning', 'Document request archived.');
        }

        redirectWithMsg('danger', 'Invalid action selected.');

    } catch (Exception $e) {
        redirectWithMsg('danger', 'Action failed: ' . $e->getMessage());
    }
}

/*
|--------------------------------------------------------------------------
| Filters
|--------------------------------------------------------------------------
*/

$search = trim($_GET['search'] ?? '');
$statusFilter = strtolower(trim($_GET['status'] ?? 'all'));
$typeFilter = trim($_GET['doc_type'] ?? 'all');

$allowedStatuses = ['all', 'pending', 'approved', 'rejected', 'released', 'archived'];

if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'all';
}

/*
|--------------------------------------------------------------------------
| Query requests
|--------------------------------------------------------------------------
*/

$params = [];

$query = "
    SELECT
        dr.*,
        stu.first_name AS student_first_name,
        stu.middle_name AS student_middle_name,
        stu.last_name AS student_last_name,
        stu.email AS student_email,
        stu.id_num AS student_id_num,
        req.first_name AS requester_first_name,
        req.middle_name AS requester_middle_name,
        req.last_name AS requester_last_name,
        req.email AS requester_email,
        req.role AS requester_role
    FROM document_requests dr
    LEFT JOIN users stu ON stu.id = dr.student_id
    LEFT JOIN users req ON req.id = dr.requested_by
    WHERE 1=1
";

if (!$isSuperAdmin) {
    $query .= " AND dr.status != 'archived'";
}

if ($search !== '') {
    $query .= "
        AND (
            dr.tracking_id LIKE ?
            OR dr.document_type LIKE ?
            OR dr.purpose LIKE ?
            OR stu.first_name LIKE ?
            OR stu.last_name LIKE ?
            OR stu.email LIKE ?
            OR stu.id_num LIKE ?
            OR req.first_name LIKE ?
            OR req.last_name LIKE ?
            OR req.email LIKE ?
        )
    ";

    $s = "%{$search}%";
    array_push($params, $s, $s, $s, $s, $s, $s, $s, $s, $s, $s);
}

if ($statusFilter !== 'all') {
    $query .= " AND dr.status = ?";
    $params[] = $statusFilter;
}

if ($typeFilter !== 'all') {
    $query .= " AND dr.document_type = ?";
    $params[] = $typeFilter;
}

$query .= " ORDER BY dr.created_at DESC";

$requests = safeFetchAll($pdo, $query, $params);

$docTypes = safeFetchAll($pdo, "
    SELECT DISTINCT document_type
    FROM document_requests
    WHERE document_type IS NOT NULL
    AND document_type != ''
    ORDER BY document_type ASC
");

$totalPending = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE status = 'pending'");
$totalApproved = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE status = 'approved'");
$totalReleased = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE status = 'released'");
$totalArchived = safeCount($pdo, "SELECT COUNT(*) FROM document_requests WHERE status = 'archived'");

$msg = '';

if (isset($_GET['msg'])) {
    $type = $_GET['msg_type'] ?? 'success';
    $allowedMsgTypes = ['success', 'danger', 'warning', 'info'];

    if (!in_array($type, $allowedMsgTypes, true)) {
        $type = 'success';
    }

    $icon = [
        'success' => 'fa-check-circle',
        'danger' => 'fa-triangle-exclamation',
        'warning' => 'fa-box-archive',
        'info' => 'fa-circle-info'
    ][$type];

    $msg = "
        <div class='alert alert-{$type}'>
            <i class='fa {$icon}'></i>
            " . e($_GET['msg']) . "
        </div>
    ";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Document Requests | Pitogo EduTrack</title>

<link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

<style>
:root {
    --primary-navy:#0B1C3D;
    --primary-blue:<?= e($themeColor) ?>;
    --bg-main:#F4F7F9;
    --surface-white:#FFFFFF;
    --text-dark:#1E293B;
    --text-muted:#64748B;
    --border-color:#E2E8F0;
    --success:#10B981;
    --warning:#F59E0B;
    --danger:#EF4444;
    --info:#3B82F6;
    --purple:#8B5CF6;
    --shadow-sm:0 4px 6px rgba(0,0,0,0.02);
    --shadow-md:0 10px 30px rgba(0,0,0,0.06);
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
    display:flex;
    min-height:100vh;
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
    box-shadow:10px 0 30px rgba(15,23,42,.08);
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
    background:#fff;
    border:1px solid var(--border-color);
    overflow:hidden;
    display:flex;
    align-items:center;
    justify-content:center;
}

.logo-box img {
    width:100%;
    height:100%;
    object-fit:contain;
}

.brand-text,
.nav-label,
.nav-item span {
    opacity:0;
    visibility:hidden;
    transition:opacity .2s ease;
}

.sidebar:hover .brand-text,
.sidebar:hover .nav-label,
.sidebar:hover .nav-item span {
    opacity:1;
    visibility:visible;
}

.brand-text h2 {
    font-size:1.05rem;
    color:var(--primary-navy);
    font-weight:800;
}

.brand-text p {
    font-size:.72rem;
    color:var(--primary-blue);
    font-weight:800;
    text-transform:uppercase;
    letter-spacing:.8px;
}

.nav-menu {
    padding:22px 14px;
    display:flex;
    flex-direction:column;
    gap:5px;
    flex:1;
}

.nav-label {
    margin:14px 0 8px 14px;
    font-size:.72rem;
    font-weight:800;
    color:var(--text-muted);
    text-transform:uppercase;
    letter-spacing:1px;
}

.nav-item {
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
}

.nav-item i {
    min-width:30px;
    text-align:center;
    font-size:1.17rem;
}

.nav-item:hover {
    background:var(--bg-main);
    color:var(--primary-blue);
    transform:translateX(5px);
}

.nav-item.active {
    background:rgba(0,86,179,.08);
    color:var(--primary-blue);
}

.logout-link {
    margin-top:auto;
    color:var(--danger);
}

.main-wrapper {
    margin-left:82px;
    flex:1;
    min-width:0;
}

.top-header {
    height:88px;
    background:var(--surface-white);
    border-bottom:1px solid var(--border-color);
    padding:0 38px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    position:sticky;
    top:0;
    z-index:50;
}

.header-title h1 {
    font-size:1.35rem;
    color:var(--primary-navy);
}

.header-title p {
    font-size:.85rem;
    color:var(--text-muted);
    margin-top:4px;
}

.user-profile {
    display:flex;
    align-items:center;
    gap:14px;
}

.user-info {
    text-align:right;
}

.user-info h4 {
    font-size:.9rem;
    color:var(--primary-navy);
}

.user-info p {
    font-size:.75rem;
    color:var(--text-muted);
}

.avatar {
    width:46px;
    height:46px;
    object-fit:cover;
    border-radius:50%;
    border:2px solid var(--primary-blue);
    padding:2px;
}

.content-area {
    padding:38px;
    max-width:1500px;
    margin:0 auto;
}

.alert {
    padding:15px 18px;
    border-radius:14px;
    margin-bottom:20px;
    font-weight:800;
    display:flex;
    align-items:center;
    gap:10px;
}

.alert-success {
    background:#DCFCE7;
    color:#166534;
    border:1px solid #A7F3D0;
}

.alert-danger {
    background:#FEE2E2;
    color:#991B1B;
    border:1px solid #FECACA;
}

.alert-warning {
    background:#FEF3C7;
    color:#92400E;
    border:1px solid #FDE68A;
}

.alert-info {
    background:#DBEAFE;
    color:#1D4ED8;
    border:1px solid #BFDBFE;
}

.hero-card {
    background:
        linear-gradient(135deg, rgba(11,28,61,.96), rgba(0,86,179,.78)),
        url('../school-bg.jpg');
    background-size:cover;
    background-position:center;
    color:white;
    border-radius:24px;
    padding:34px;
    margin-bottom:28px;
    display:flex;
    justify-content:space-between;
    gap:25px;
    align-items:center;
    flex-wrap:wrap;
}

.hero-card h1 {
    font-size:2rem;
    font-weight:800;
    margin-bottom:8px;
}

.hero-card p {
    color:rgba(255,255,255,.86);
    line-height:1.6;
}

.stats-grid {
    display:grid;
    grid-template-columns:repeat(4, minmax(170px, 1fr));
    gap:18px;
    margin-bottom:24px;
}

.stat-card {
    background:var(--surface-white);
    padding:22px;
    border-radius:18px;
    border:1px solid var(--border-color);
    display:flex;
    align-items:center;
    gap:16px;
    box-shadow:var(--shadow-sm);
}

.stat-icon {
    width:56px;
    height:56px;
    border-radius:15px;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:1.3rem;
}

.stat-info h3 {
    font-size:1.65rem;
    font-weight:800;
    color:var(--primary-navy);
}

.stat-info p {
    font-size:.78rem;
    color:var(--text-muted);
    font-weight:800;
    text-transform:uppercase;
}

.toolbar-card {
    background:var(--surface-white);
    padding:20px 24px;
    border-radius:18px;
    border:1px solid var(--border-color);
    box-shadow:var(--shadow-sm);
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:22px;
    flex-wrap:wrap;
    gap:15px;
}

.filter-group {
    display:flex;
    gap:12px;
    align-items:center;
    flex-wrap:wrap;
}

.select-control {
    padding:11px 14px;
    border-radius:10px;
    border:1px solid var(--border-color);
    background:var(--bg-main);
    font-family:'Inter',sans-serif;
    font-size:.9rem;
    font-weight:600;
    color:var(--primary-navy);
    outline:none;
}

.btn-filter,
.btn-clear {
    padding:11px 16px;
    border-radius:10px;
    cursor:pointer;
    font-weight:800;
    text-decoration:none;
    border:none;
    display:inline-flex;
    gap:7px;
    align-items:center;
}

.btn-filter {
    background:var(--primary-blue);
    color:white;
}

.btn-clear {
    color:var(--danger);
    background:#fff;
    border:1px solid var(--border-color);
}

.table-container {
    background:var(--surface-white);
    border-radius:18px;
    border:1px solid var(--border-color);
    overflow-x:auto;
    box-shadow:var(--shadow-sm);
}

.data-table {
    width:100%;
    border-collapse:collapse;
    min-width:1150px;
}

.data-table th {
    background:#F8FAFC;
    padding:16px 18px;
    font-size:.78rem;
    font-weight:800;
    color:var(--text-muted);
    text-transform:uppercase;
    border-bottom:2px solid var(--border-color);
    text-align:left;
}

.data-table td {
    padding:15px 18px;
    border-bottom:1px solid var(--border-color);
    vertical-align:middle;
}

.data-table tbody tr:hover td {
    background:rgba(0,86,179,.025);
}

.badge-status {
    padding:7px 12px;
    border-radius:20px;
    font-size:.75rem;
    font-weight:800;
    display:inline-block;
    text-transform:capitalize;
}

.status-pending {
    background:rgba(245,158,11,.1);
    color:var(--warning);
}

.status-approved {
    background:rgba(59,130,246,.1);
    color:var(--info);
}

.status-released {
    background:rgba(16,185,129,.1);
    color:var(--success);
}

.status-rejected {
    background:rgba(239,68,68,.1);
    color:var(--danger);
}

.status-archived {
    background:rgba(100,116,139,.1);
    color:var(--text-muted);
}

.action-group {
    display:flex;
    gap:8px;
    align-items:center;
    flex-wrap:wrap;
}

.btn-small {
    border:none;
    border-radius:9px;
    padding:9px 11px;
    font-weight:800;
    font-size:.78rem;
    cursor:pointer;
    display:inline-flex;
    align-items:center;
    gap:6px;
    text-decoration:none;
}

.btn-approve {
    background:#DCFCE7;
    color:#16A34A;
}

.btn-reject {
    background:#FEE2E2;
    color:#DC2626;
}

.btn-release {
    background:#DBEAFE;
    color:#1D4ED8;
}

.btn-archive {
    background:#FEF3C7;
    color:#D97706;
}

.btn-file {
    background:#F1F5F9;
    color:#0F172A;
}

.modal-overlay {
    position:fixed;
    inset:0;
    background:rgba(11,28,61,.6);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index:1000;
    opacity:0;
    visibility:hidden;
    transition:var(--transition);
    backdrop-filter:blur(4px);
}

.modal-overlay.active {
    opacity:1;
    visibility:visible;
}

.modal-card {
    background:white;
    width:100%;
    max-width:560px;
    border-radius:20px;
    padding:30px;
    transform:translateY(20px);
    transition:var(--transition);
    position:relative;
}

.modal-overlay.active .modal-card {
    transform:translateY(0);
}

.modal-close {
    position:absolute;
    top:20px;
    right:20px;
    background:none;
    border:none;
    font-size:1.2rem;
    color:var(--text-muted);
    cursor:pointer;
}

.modal-card h2 {
    color:var(--primary-navy);
    margin-bottom:6px;
}

.modal-card p {
    color:var(--text-muted);
    font-size:.9rem;
    margin-bottom:20px;
}

.form-group {
    margin-bottom:16px;
}

.form-group label {
    display:block;
    font-weight:800;
    color:var(--primary-navy);
    margin-bottom:7px;
    font-size:.85rem;
}

.form-group input,
.form-group textarea,
.form-group select {
    width:100%;
    padding:12px 14px;
    border:1px solid var(--border-color);
    border-radius:10px;
    font-family:'Inter',sans-serif;
    background:#F8FAFC;
    outline:none;
}

.form-group textarea {
    resize:vertical;
    min-height:90px;
}

.submit-btn {
    width:100%;
    padding:13px;
    border:none;
    border-radius:10px;
    background:var(--primary-blue);
    color:white;
    font-weight:800;
    cursor:pointer;
}

.empty-state {
    text-align:center;
    padding:45px;
    color:var(--text-muted);
}

@media(max-width: 1000px) {
    .stats-grid {
        grid-template-columns:repeat(2,1fr);
    }
}

@media(max-width: 760px) {
    .sidebar {
        display:none;
    }

    .main-wrapper {
        margin-left:0;
    }

    .top-header {
        padding:0 20px;
    }

    .user-info {
        display:none;
    }

    .content-area {
        padding:24px 18px;
    }

    .stats-grid {
        grid-template-columns:1fr;
    }

    .hero-card,
    .toolbar-card {
        flex-direction:column;
        align-items:flex-start;
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
            <p><?= e($roleLabel) ?></p>
        </div>
    </div>

    <nav class="nav-menu">
        <div class="nav-label">Main</div>

        <a href="admin-dashboard.php" class="nav-item">
            <i class="fa fa-chart-pie"></i>
            <span>Dashboard</span>
        </a>

        <a href="user-management.php" class="nav-item">
            <i class="fa fa-users-gear"></i>
            <span>User Management</span>
        </a>

        <a href="document-requests.php" class="nav-item active">
            <i class="fa fa-file-lines"></i>
            <span>Document Requests</span>
        </a>

        <a class="nav-item" href="attendance.php">
            <i class="fa fa-calendar-check"></i>
            <span>Attendance</span>
        </a>

        <?php if ($isSuperAdmin): ?>
            <a class="nav-item" href="audit-logs.php">
                <i class="fa fa-clock-rotate-left"></i>
                <span>Audit Logs</span>
            </a>
        <?php endif; ?>

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
        <h1>Document Requests</h1>
        <p><?= e($roleLabel) ?> Access • Pitogo EduTrack</p>
    </div>

    <div class="user-profile">
        <div class="user-info">
            <h4><?= e($adminNameRaw) ?></h4>
            <p><?= e($roleLabel) ?></p>
        </div>

        <img src="<?= e($adminAvatar) ?>" class="avatar" alt="Profile">
    </div>
</header>

<div class="content-area">

    <?= $msg ?>

    <section class="hero-card">
        <div>
            <h1>Document Request Management</h1>
            <p>Review, approve, reject, release, and archive student or parent document requests securely with OTP-based document access.</p>
        </div>
    </section>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(245,158,11,.1); color:var(--warning);">
                <i class="fa fa-clock"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalPending) ?></h3>
                <p>Pending</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(59,130,246,.1); color:var(--info);">
                <i class="fa fa-circle-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalApproved) ?></h3>
                <p>Approved</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(16,185,129,.1); color:var(--success);">
                <i class="fa fa-file-circle-check"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalReleased) ?></h3>
                <p>Released</p>
            </div>
        </div>

        <div class="stat-card">
            <div class="stat-icon" style="background:rgba(100,116,139,.1); color:var(--text-muted);">
                <i class="fa fa-box-archive"></i>
            </div>
            <div class="stat-info">
                <h3><?= number_format($totalArchived) ?></h3>
                <p>Archived</p>
            </div>
        </div>
    </div>

    <form method="GET" class="toolbar-card">
        <div class="filter-group">
            <input
                type="text"
                name="search"
                class="select-control"
                style="width:250px;"
                placeholder="Search tracking ID, student, document..."
                value="<?= e($search) ?>"
            >

            <select name="status" class="select-control">
                <option value="all" <?= $statusFilter === 'all' ? 'selected' : '' ?>>All Statuses</option>
                <option value="pending" <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                <option value="released" <?= $statusFilter === 'released' ? 'selected' : '' ?>>Released</option>
                <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>

                <?php if ($isSuperAdmin): ?>
                    <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>Archived</option>
                <?php endif; ?>
            </select>

            <select name="doc_type" class="select-control">
                <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Documents</option>
                <?php foreach ($docTypes as $type): ?>
                    <option value="<?= e($type['document_type']) ?>" <?= $typeFilter === $type['document_type'] ? 'selected' : '' ?>>
                        <?= e($type['document_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <button type="submit" class="btn-filter">
                <i class="fa fa-filter"></i>
                Filter
            </button>

            <?php if ($search !== '' || $statusFilter !== 'all' || $typeFilter !== 'all'): ?>
                <a href="document-requests.php" class="btn-clear">
                    <i class="fa fa-times"></i>
                    Clear
                </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Tracking ID</th>
                    <th>Student</th>
                    <th>Requested By</th>
                    <th>Document</th>
                    <th>Status</th>
                    <th>Release</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>

            <tbody>
                <?php if (!$requests): ?>
                    <tr>
                        <td colspan="8">
                            <div class="empty-state">
                                <i class="fa fa-folder-open" style="font-size:2rem; margin-bottom:10px;"></i>
                                <br>
                                No document requests found.
                            </div>
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($requests as $request): ?>
                        <?php
                        $studentName = trim(($request['student_first_name'] ?? '') . ' ' . ($request['student_middle_name'] ?? '') . ' ' . ($request['student_last_name'] ?? ''));
                        $requesterName = trim(($request['requester_first_name'] ?? '') . ' ' . ($request['requester_middle_name'] ?? '') . ' ' . ($request['requester_last_name'] ?? ''));

                        if ($studentName === '') {
                            $studentName = 'Unknown Student';
                        }

                        if ($requesterName === '') {
                            $requesterName = 'Unknown Requester';
                        }

                        $trackingId = $request['tracking_id'] ?: ('REQ-' . str_pad((string)$request['id'], 6, '0', STR_PAD_LEFT));
                        $status = strtolower($request['status'] ?? 'pending');
                        $releaseMethod = $request['release_method'] ?? 'Not set';
                        $createdAt = !empty($request['created_at']) ? date('M d, Y h:i A', strtotime($request['created_at'])) : 'N/A';
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($trackingId) ?></strong>
                                <br>
                                <span style="font-size:.78rem;color:var(--text-muted);">
                                    ID: <?= e($request['id']) ?>
                                </span>
                            </td>

                            <td>
                                <strong><?= e($studentName) ?></strong>
                                <br>
                                <span style="font-size:.78rem;color:var(--text-muted);">
                                    LRN: <?= e($request['student_id_num'] ?? 'N/A') ?>
                                </span>
                            </td>

                            <td>
                                <?= e($requesterName) ?>
                                <br>
                                <span style="font-size:.78rem;color:var(--text-muted);">
                                    <?= e($request['requester_role'] ?? 'User') ?>
                                </span>
                            </td>

                            <td>
                                <strong><?= e($request['document_type'] ?? 'Document') ?></strong>
                                <br>
                                <span style="font-size:.78rem;color:var(--text-muted);">
                                    <?= e($request['purpose'] ?? 'No purpose provided') ?>
                                </span>
                            </td>

                            <td>
                                <span class="badge-status status-<?= e($status) ?>">
                                    <?= e($status) ?>
                                </span>
                            </td>

                            <td>
                                <strong><?= e(ucwords(str_replace('_', ' ', $releaseMethod))) ?></strong>

                                <?php if (!empty($request['pickup_date'])): ?>
                                    <br>
                                    <span style="font-size:.78rem;color:var(--text-muted);">
                                        Pickup: <?= e(date('M d, Y', strtotime($request['pickup_date']))) ?>
                                    </span>
                                <?php endif; ?>

                                <?php if (!empty($request['file_path'])): ?>
                                    <br>
                                    <a class="btn-small btn-file" href="../<?= e($request['file_path']) ?>" target="_blank" style="margin-top:6px;">
                                        <i class="fa fa-file-pdf"></i>
                                        View PDF
                                    </a>
                                <?php endif; ?>
                            </td>

                            <td><?= e($createdAt) ?></td>

                            <td>
                                <div class="action-group">
                                    <?php if ($status === 'pending'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?= e($request['id']) ?>">
                                            <button type="submit" name="action" value="approve" class="btn-small btn-approve">
                                                <i class="fa fa-check"></i>
                                                Approve
                                            </button>
                                        </form>

                                        <button type="button" class="btn-small btn-reject" onclick="openRejectModal(<?= e($request['id']) ?>)">
                                            <i class="fa fa-times"></i>
                                            Reject
                                        </button>
                                    <?php endif; ?>

                                    <?php if (in_array($status, ['pending', 'approved'], true)): ?>
                                        <button type="button" class="btn-small btn-release" onclick="openReleaseModal(<?= e($request['id']) ?>)">
                                            <i class="fa fa-upload"></i>
                                            Release
                                        </button>
                                    <?php endif; ?>

                                    <?php if ($isSuperAdmin && $status !== 'archived'): ?>
                                        <form method="POST" style="display:inline;">
                                            <input type="hidden" name="request_id" value="<?= e($request['id']) ?>">
                                            <button type="submit" name="action" value="archive" class="btn-small btn-archive" onclick="return confirm('Archive this document request?');">
                                                <i class="fa fa-box-archive"></i>
                                                Archive
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>

</main>

<div class="modal-overlay" id="releaseModal">
    <div class="modal-card">
        <button class="modal-close" onclick="closeReleaseModal()">
            <i class="fa fa-times"></i>
        </button>

        <h2>Release Document</h2>
        <p>Choose how this requested document will be released.</p>

        <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="request_id" id="releaseRequestId">
            <input type="hidden" name="action" value="release">

            <div class="form-group">
                <label>Release Method</label>
                <select name="release_method" id="releaseMethod" onchange="toggleReleaseFields()" required>
                    <option value="digital">Digital PDF with OTP</option>
                    <option value="hard_copy">Hard Copy Pickup</option>
                </select>
            </div>

            <div id="digitalFields">
                <div class="form-group">
                    <label>Upload PDF File</label>
                    <input type="file" name="document_file" accept="application/pdf">
                </div>
            </div>

            <div id="hardCopyFields" style="display:none;">
                <div class="form-group">
                    <label>Pickup Date</label>
                    <input type="date" name="pickup_date">
                </div>

                <div class="form-group">
                    <label>Instructions</label>
                    <textarea name="instructions" placeholder="Example: Bring school ID and claim at the registrar window."></textarea>
                </div>
            </div>

            <button type="submit" class="submit-btn">
                Save Release Details
            </button>
        </form>
    </div>
</div>

<div class="modal-overlay" id="rejectModal">
    <div class="modal-card">
        <button class="modal-close" onclick="closeRejectModal()">
            <i class="fa fa-times"></i>
        </button>

        <h2>Reject Request</h2>
        <p>Add a reason or remark for rejecting this document request.</p>

        <form method="POST">
            <input type="hidden" name="request_id" id="rejectRequestId">
            <input type="hidden" name="action" value="reject">

            <div class="form-group">
                <label>Remarks</label>
                <textarea name="remarks" placeholder="Reason for rejection..." required></textarea>
            </div>

            <button type="submit" class="submit-btn" style="background:var(--danger);">
                Reject Request
            </button>
        </form>
    </div>
</div>

<script>
function openReleaseModal(requestId) {
    document.getElementById('releaseRequestId').value = requestId;
    document.getElementById('releaseModal').classList.add('active');
}

function closeReleaseModal() {
    document.getElementById('releaseModal').classList.remove('active');
}

function openRejectModal(requestId) {
    document.getElementById('rejectRequestId').value = requestId;
    document.getElementById('rejectModal').classList.add('active');
}

function closeRejectModal() {
    document.getElementById('rejectModal').classList.remove('active');
}

function toggleReleaseFields() {
    const method = document.getElementById('releaseMethod').value;
    const digitalFields = document.getElementById('digitalFields');
    const hardCopyFields = document.getElementById('hardCopyFields');

    if (method === 'digital') {
        digitalFields.style.display = 'block';
        hardCopyFields.style.display = 'none';
    } else {
        digitalFields.style.display = 'none';
        hardCopyFields.style.display = 'block';
    }
}

document.querySelectorAll('.modal-overlay').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === modal) {
            modal.classList.remove('active');
        }
    });
});
</script>

</body>
</html>
