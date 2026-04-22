<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
header('Content-Type: application/json');

ob_start();
try {
    require_once __DIR__ . '/config.php';
    $output = ob_get_clean();
    echo json_encode(['status' => 'ok', 'early_output' => $output]);
} catch (Throwable $e) {
    $output = ob_get_clean();
    http_response_code(500);
    echo json_encode([
        'status'  => 'error',
        'type'    => get_class($e),
        'message' => $e->getMessage(),
        'file'    => $e->getFile(),
        'line'    => $e->getLine(),
        'early_output' => $output,
    ]);
}
