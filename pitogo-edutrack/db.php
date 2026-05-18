<?php
$host = getenv("MYSQLHOST") ?: "localhost";
$username = getenv("MYSQLUSER") ?: "root";
$password = getenv("MYSQLPASSWORD") ?: "";
$database = getenv("MYSQLDATABASE") ?: "pitogo_edutrack";
$port = getenv("MYSQLPORT") ?: "3306";

try {
    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

} catch (PDOException $e) {
    $isAjax =
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if (
        $isAjax ||
        str_contains($_SERVER['REQUEST_URI'] ?? '', 'process_') ||
        str_contains($_SERVER['REQUEST_URI'] ?? '', 'process-')
    ) {
        http_response_code(500);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed. Please check MySQL and database settings.'
        ], JSON_UNESCAPED_UNICODE);
    } else {
        die("Database Connection Failed: " . $e->getMessage());
    }

    exit();
}
?>
