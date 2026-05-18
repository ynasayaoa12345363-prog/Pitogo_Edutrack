<?php
$mysqlUrl = getenv("MYSQL_URL");

if ($mysqlUrl) {

    $url = parse_url($mysqlUrl);

    $host = $url["host"] ?? "";
    $port = $url["port"] ?? "3306";
    $username = $url["user"] ?? "";
    $password = $url["pass"] ?? "";
    $database = isset($url["path"]) ? ltrim($url["path"], "/") : "";

} else {

    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "pitogo_edutrack";
    $port = "3306";
}

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

    die("Database Connection Failed: " . $e->getMessage());

}
?>
