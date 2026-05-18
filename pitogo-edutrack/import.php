<?php

$host = "centerbeam.proxy.rlwy.net";
$port = "21257";
$database = "railway";
$username = "root";
$password = "BjjoLHzockpGUKkRVNkchzQgPVEiKVQm";

try {

    $pdo = new PDO(
        "mysql:host=$host;port=$port;dbname=$database;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]
    );

    echo "<h2>Connected to Railway MySQL ✅</h2>";

    $sql = file_get_contents("pitogo_edutrack.sql");

    $pdo->exec($sql);

    echo "<h2>Database Imported Successfully ✅</h2>";

} catch (PDOException $e) {

    die("ERROR: " . $e->getMessage());

}
?>