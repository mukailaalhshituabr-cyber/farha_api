<?php
// GET /farha_api/cart_get.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload  = Auth::requireCustomer();
$db       = Database::connect();

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$stmt = $db->prepare("
    SELECT ci.id, ci.product_id, ci.customer_id, ci.quantity, ci.size, ci.added_at,
           p.name, p.base_price, p.currency, p.stock_quantity,
           t.id AS tailor_id,
           CONCAT(u.first_name, ' ', u.last_name) AS tailor_name,
           t.shop_name,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = ci.product_id AND pi.is_main = 1 LIMIT 1) AS main_image_url
    FROM cart_items ci
    JOIN products p ON p.id = ci.product_id
    JOIN tailors t  ON t.id = p.tailor_id
    JOIN users u    ON u.id = t.user_id
    WHERE ci.customer_id = ?
    ORDER BY ci.added_at DESC
");
$stmt->execute([$customer['id']]);
$items = $stmt->fetchAll();

$total = 0;
foreach ($items as &$item) {
    $item['base_price'] = (float)$item['base_price'];
    $total += $item['base_price'] * $item['quantity'];
}

Response::success(['items' => $items, 'total' => $total, 'count' => count($items)]);
