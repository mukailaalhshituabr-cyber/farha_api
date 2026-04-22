<?php
// PUT /farha_api/orders_cancel.php?id=ORDER_ID
require_once __DIR__ . '/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$orderId = $_GET['id'] ?? $_GET['order_id'] ?? '';
if (empty($orderId)) Response::error('Order ID is required.', 400);

$body = getBody();
$db   = Database::connect();

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$chk = $db->prepare('SELECT id, status FROM orders WHERE id = ? AND customer_id = ? LIMIT 1');
$chk->execute([$orderId, $customer['id']]);
$order = $chk->fetch();
if (!$order) Response::notFound('Order not found or not yours.');

if ($order['status'] === 'cancelled') Response::error('Order is already cancelled.', 422);
if ($order['status'] === 'delivered') Response::error('Cannot cancel a delivered order.', 422);
if (in_array($order['status'], ['cutting','sewing','ready'], true)) {
    Response::error('Order is already in progress and cannot be cancelled. Please contact the tailor.', 422);
}

$reason = !empty($body['cancel_reason']) ? sanitizeString($body['cancel_reason']) : null;
$db->prepare('UPDATE orders SET status = "cancelled", cancel_reason = ? WHERE id = ?')
   ->execute([$reason, $orderId]);

Response::success(null, 'Order cancelled successfully.');
