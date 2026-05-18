<?php
session_start();
require '../db.php';

/*
|--------------------------------------------------------------------------
| Pitogo EduTrack - Mark Attendance
|--------------------------------------------------------------------------
| Save as:
| staff/mark-attendance.php
|
| This version:
| - Marks student attendance from QR scan
| - Prevents duplicate scan per day
| - Creates in-system notification for linked parent accounts
|--------------------------------------------------------------------------
*/

date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');

function respond(bool $success, string $title, string $message, string $type = 'success'): void {
    echo json_encode([
        'success' => $success,
        'title' => $title,
        'message' => $message,
        'type' => $type
    ]);
    exit();
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

function ensureNotificationsTable(PDO $pdo): void {
    if (!tableExists($pdo, 'notifications')) {
        $pdo->exec("
            CREATE TABLE notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                title VARCHAR(150) NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(30) DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            )
        ");
    }
}

function logAudit(PDO $pdo, int $userId, string $role, string $action, string $targetTable = 'attendance', ?int $targetId = null): void {
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

function notifyLinkedParents(PDO $pdo, int $studentId, string $studentName, string $timeIn): void {
    ensureNotificationsTable($pdo);

    $parentIds = [];

    if (tableExists($pdo, 'parent_student_links')) {
        $stmt = $pdo->prepare("
            SELECT parent_id
            FROM parent_student_links
            WHERE student_id = ?
            AND status = 'active'
        ");
        $stmt->execute([$studentId]);
        $parentIds = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'parent_id');
    }

    $parentIds = array_unique(array_filter(array_map('intval', $parentIds)));

    if (!$parentIds) {
        return;
    }

    $title = 'Child Attendance Recorded';
    $message = $studentName . ' entered school and was marked present at ' . date('h:i A', strtotime($timeIn)) . '.';

    $insert = $pdo->prepare("
        INSERT INTO notifications (
            user_id,
            title,
            message,
            type,
            is_read,
            created_at
        ) VALUES (
            ?, ?, ?, 'attendance', 0, NOW()
        )
    ");

    foreach ($parentIds as $parentId) {
        $insert->execute([
            $parentId,
            $title,
            $message
        ]);
    }
}

if (!isset($_SESSION['user_id'], $_SESSION['role'])) {
    respond(false, 'Unauthorized', 'Please login first.', 'danger');
}

$staffId = (int)$_SESSION['user_id'];
$role = strtolower(trim($_SESSION['role']));

if ($role !== 'staff') {
    respond(false, 'Unauthorized', 'Only staff accounts can scan QR attendance.', 'danger');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Invalid Request', 'Invalid request method.', 'danger');
}

$qrToken = trim($_POST['qr_token'] ?? '');

if ($qrToken === '') {
    respond(false, 'Missing QR Token', 'No QR token was received.', 'danger');
}

try {
    if (!tableExists($pdo, 'attendance')) {
        $pdo->exec("
            CREATE TABLE attendance (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                attendance_date DATE NOT NULL,
                time_in TIME NULL,
                status VARCHAR(30) DEFAULT 'present',
                scanned_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_student_date (student_id, attendance_date)
            )
        ");
    }

    if (!columnExists($pdo, 'attendance', 'student_id')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN student_id INT NOT NULL");
    }

    if (!columnExists($pdo, 'attendance', 'attendance_date')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN attendance_date DATE NOT NULL");
    }

    if (!columnExists($pdo, 'attendance', 'time_in')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN time_in TIME NULL");
    }

   if (!columnExists($pdo, 'attendance', 'status')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN status ENUM('Present','Late','Absent') NOT NULL DEFAULT 'Present'");
}

if (!columnExists($pdo, 'attendance', 'attendance_type')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN attendance_type ENUM('qr','manual') NOT NULL DEFAULT 'qr'");
}

if (!columnExists($pdo, 'attendance', 'time_out')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN time_out TIME NULL");
}

if (!columnExists($pdo, 'attendance', 'remarks')) {
    $pdo->exec("ALTER TABLE attendance ADD COLUMN remarks VARCHAR(255) NULL");
}

    if (!columnExists($pdo, 'attendance', 'scanned_by')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN scanned_by INT NULL");
    }

    if (!columnExists($pdo, 'attendance', 'created_at')) {
        $pdo->exec("ALTER TABLE attendance ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP");
    }

    $stmt = $pdo->prepare("
        SELECT id, first_name, middle_name, last_name, email, id_num, role, status, is_deleted, is_archived
        FROM users
        WHERE qr_token = ?
        AND role = 'student'
        LIMIT 1
    ");
    $stmt->execute([$qrToken]);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$student) {
        respond(false, 'Invalid QR Code', 'No active student account matches this QR code.', 'danger');
    }

    if ((int)($student['is_deleted'] ?? 0) === 1 || (int)($student['is_archived'] ?? 0) === 1) {
        respond(false, 'Inactive Student', 'This student account is archived or deleted.', 'danger');
    }

    if (($student['status'] ?? '') !== 'active') {
        respond(false, 'Inactive Account', 'This student account is not active yet.', 'danger');
    }

    $studentId = (int)$student['id'];
    $today = date('Y-m-d');
    $timeNow = date('H:i:s');

    $check = $pdo->prepare("
        SELECT id, time_in, status
        FROM attendance
        WHERE student_id = ?
        AND attendance_date = ?
        LIMIT 1
    ");
    $check->execute([$studentId, $today]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    $studentName = trim(
        ($student['first_name'] ?? '') . ' ' .
        ($student['middle_name'] ?? '') . ' ' .
        ($student['last_name'] ?? '')
    );

    if ($existing) {
        respond(
            false,
            'Already Scanned',
            $studentName . ' was already marked present today at ' . date('h:i A', strtotime($existing['time_in'])) . '.',
            'warning'
        );
    }

    $status = 'Present';
$attendanceType = 'qr';

$insert = $pdo->prepare("
    INSERT INTO attendance (
        student_id,
        attendance_date,
        time_in,
        time_out,
        status,
        attendance_type,
        scanned_by,
        remarks,
        created_at
    ) VALUES (
        ?, ?, ?, NULL, ?, ?, ?, NULL, NOW()
    )
");

$insert->execute([
    $studentId,
    $today,
    $timeNow,
    $status,
    $attendanceType,
    $staffId
]);

    $attendanceId = (int)$pdo->lastInsertId();

    notifyLinkedParents($pdo, $studentId, $studentName, $timeNow);

    logAudit(
        $pdo,
        $staffId,
        'staff',
        'Marked QR attendance for ' . $studentName,
        'attendance',
        $attendanceId
    );

    respond(
        true,
        'Attendance Marked',
        $studentName . ' has been marked present at ' . date('h:i A', strtotime($timeNow)) . '. Linked parent accounts can now see the attendance update.',
        'success'
    );

} catch (Exception $e) {
    respond(false, 'System Error', 'Failed to mark attendance: ' . $e->getMessage(), 'danger');
}
?>
