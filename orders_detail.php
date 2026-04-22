<?php
// GET /farha_api/orders_detail.php?id=ORDER_ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$orderId = $_GET['id'] ?? '';
if (empty($orderId)) Response::error('Order ID is required.', 400);

$db   = Database::connect();
$stmt = $db->prepare("
    SELECT o.*,
           CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
           CONCAT(tu.first_name, ' ', tu.last_name) AS tailor_name,
           t.shop_name, t.shop_location, t.latitude, t.longitude,
           p.name AS product_name,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = o.product_id AND pi.is_main = 1 LIMIT 1) AS product_image_url
    FROM orders o
    JOIN customers c  ON c.id  = o.customer_id
    JOIN users cu     ON cu.id = c.user_id
    JOIN tailors t    ON t.id  = o.tailor_id
    JOIN users tu     ON tu.id = t.user_id
    LEFT JOIN products p ON p.id = o.product_id
    WHERE o.id = ?
    LIMIT 1
");
$stmt->execute([$orderId]);
$order = $stmt->fetch();

if (!$order) Response::notFound('Order not found.');

// Verify the requesting user owns this order (as customer or tailor)
if ($payload['user_type'] === 'customer') {
    $chk = $db->prepare('SELECT id FROM customers WHERE id = ? AND user_id = ? LIMIT 1');
    $chk->execute([$order['customer_id'], $payload['user_id']]);
    if (!$chk->fetch()) Response::forbidden();
} else {
    $chk = $db->prepare('SELECT id FROM tailors WHERE id = ? AND user_id = ? LIMIT 1');
    $chk->execute([$order['tailor_id'], $payload['user_id']]);
    if (!$chk->fetch()) Response::forbidden();
}

$order['total_amount']   = (float)$order['total_amount'];
$order['deposit_amount'] = (float)$order['deposit_amount'];
$order['paid_amount']    = (float)$order['paid_amount'];

Response::success($order);
