<?php
$host = "localhost";
$username = "root";
$password = "";
$database = "pitogo_edutrack";

try {
    $pdo = new PDO(
        "mysql:host=$host;dbname=$database;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $isAjax =
        !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
        strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    if ($isAjax || str_contains($_SERVER['REQUEST_URI'] ?? '', 'process_') || str_contains($_SERVER['REQUEST_URI'] ?? '', 'process-')) {
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