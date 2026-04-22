<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

ob_start();
try {
    require_once __DIR__ . '/config.php';

    // Step 1: DB connect
    $db = Database::connect();
    echo json_encode(['step' => 1, 'db' => 'ok']) . "\n";

    // Step 2: auth_attempts table
    $db->query('SELECT 1 FROM auth_attempts LIMIT 1');
    echo json_encode(['step' => 2, 'auth_attempts' => 'ok']) . "\n";

    // Step 3: rate limit check
    Auth::checkRateLimit('test', 100, 60);
    echo json_encode(['step' => 3, 'rate_limit' => 'ok']) . "\n";

    // Step 4: users table
    $stmt = $db->prepare('SELECT id FROM users LIMIT 1');
    $stmt->execute();
    echo json_encode(['step' => 4, 'users' => 'ok']) . "\n";

} catch (Throwable $e) {
    ob_end_flush();
    echo json_encode([
        'failed_at' => $e->getMessage(),
        'file' => basename($e->getFile()),
        'line' => $e->getLine(),
    ]);
    exit;
}
ob_end_flush();
