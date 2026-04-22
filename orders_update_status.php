<?php
// PUT /farha_api/orders_update_status.php?id=ORDER_ID
require_once __DIR__ . '/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) Response::error('Method not allowed.', 405);

$payload = Auth::requireTailor();
$orderId = $_GET['id'] ?? $_GET['order_id'] ?? '';
if (empty($orderId)) Response::error('Order ID is required.', 400);

$body = getBody();
$v    = (new Validator($body))
    ->required('status', 'Status')
    ->inList('status', ['confirmed','cutting','sewing','ready','delivered'], 'Status');
if ($v->fails()) Response::validationError($v->errors());

$db       = Database::connect();
$tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
$tailorStmt->execute([$payload['user_id']]);
$tailor = $tailorStmt->fetch();
if (!$tailor) Response::notFound('Tailor profile not found.');

$chk = $db->prepare('SELECT id, status FROM orders WHERE id = ? AND tailor_id = ? LIMIT 1');
$chk->execute([$orderId, $tailor['id']]);
$order = $chk->fetch();
if (!$order) Response::notFound('Order not found or not yours.');

if ($order['status'] === 'cancelled') Response::error('Cannot update a cancelled order.', 422);
if ($order['status'] === 'delivered') Response::error('Order is already delivered.', 422);

$params = [$v->get('status')];
$sql    = 'UPDATE orders SET status = ?';

if (!empty($body['estimated_completion'])) {
    $sql      .= ', estimated_completion = ?';
    $params[]  = $body['estimated_completion'];
}

$params[] = $orderId;
$db->prepare($sql . ' WHERE id = ?')->execute($params);

// Notify the customer about the status change
$newStatus = $v->get('status');
$statusMessages = [
    'confirmed' => ['Order Confirmed',       'Your order has been confirmed and work will begin shortly.'],
    'cutting'   => ['Cutting in Progress',   'The tailor has started cutting the fabric for your order.'],
    'sewing'    => ['Sewing in Progress',    'Your order is currently being sewn.'],
    'ready'     => ['Ready for Pickup',      'Your order is ready! Please arrange pickup or delivery.'],
    'delivered' => ['Order Delivered',       'Your order has been marked as delivered. Enjoy!'],
];
if (isset($statusMessages[$newStatus])) {
    [$notifTitle, $notifBody] = $statusMessages[$newStatus];
    $custStmt = $db->prepare('
        SELECT c.user_id FROM customers c
        JOIN orders o ON o.customer_id = c.id
        WHERE o.id = ? LIMIT 1
    ');
    $custStmt->execute([$orderId]);
    $custUserId = $custStmt->fetchColumn();
    if ($custUserId) {
        $db->prepare("
            INSERT INTO notifications (id, user_id, title, body, type, reference_id, is_read)
            VALUES (?, ?, ?, ?, 'order_status', ?, 0)
        ")->execute([generateUuid(), $custUserId, $notifTitle, $notifBody, $orderId]);
    }
}

Response::success(['status' => $newStatus], 'Order status updated.');
