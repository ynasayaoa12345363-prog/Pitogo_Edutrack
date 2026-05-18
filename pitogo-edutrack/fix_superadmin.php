<?php
require 'db.php';

$email = 'superadmin@edutrack.com';
$password = password_hash('admin123', PASSWORD_DEFAULT);

$stmt = $pdo->prepare("
    INSERT INTO users (
        first_name, last_name, email, password, role, status, email_verified_at, is_deleted, is_archived
    ) VALUES (
        'Super', 'Admin', ?, ?, 'superadmin', 'active', NOW(), 0, 0
    )
    ON DUPLICATE KEY UPDATE
        password = VALUES(password),
        role = 'superadmin',
        status = 'active',
        email_verified_at = NOW(),
        is_deleted = 0,
        is_archived = 0
");

$stmt->execute([$email, $password]);

echo "Superadmin fixed. Email: superadmin@edutrack.com Password: admin123";
?>