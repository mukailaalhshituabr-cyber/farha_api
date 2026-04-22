<?php
// GET /farha_api/payments_history.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$db      = Database::connect();

if ($payload['user_type'] === 'customer') {
    $custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
    $custStmt->execute([$payload['user_id']]);
    $customer = $custStmt->fetch();
    if (!$customer) Response::notFound();

    $stmt = $db->prepare('
        SELECT p.id, p.amount, p.currency, p.payment_method, p.transaction_id, p.status, p.created_at,
               o.reference_number
        FROM payments p
        JOIN orders o ON o.id = p.order_id
        JOIN customers c ON c.id = o.customer_id
        WHERE c.id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ');
    $stmt->execute([$customer['id']]);
} else {
    $tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
    $tailorStmt->execute([$payload['user_id']]);
    $tailor = $tailorStmt->fetch();
    if (!$tailor) Response::notFound();

    $stmt = $db->prepare('
        SELECT p.id, p.amount, p.currency, p.payment_method, p.transaction_id, p.status, p.created_at,
               o.reference_number
        FROM payments p
        JOIN orders o ON o.id = p.order_id
        WHERE o.tailor_id = ?
        ORDER BY p.created_at DESC
        LIMIT 50
    ');
    $stmt->execute([$tailor['id']]);
}

$payments = $stmt->fetchAll();
foreach ($payments as &$p) $p['amount'] = (float)$p['amount'];

Response::success(['payments' => $payments]);
