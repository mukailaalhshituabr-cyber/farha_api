<?php
// Temporary diagnostic — DELETE after fixing
ini_set('display_errors', 1);
error_reporting(E_ALL);

define('DB_HOST', 'localhost');
define('DB_NAME', 'mobileapps_2026B_mukaila_shittu');
define('DB_USER', 'mukaila.shittu');
define('DB_PASS', 'Adf=Tdd3&Wt');

header('Content-Type: application/json');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo json_encode(['status' => 'connected']);

    // Check which tables exist
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "\n" . json_encode(['tables' => $tables]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['status' => 'failed', 'error' => $e->getMessage()]);
}
