<?php
// GET /farha_api/wishlist_get.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload  = Auth::requireCustomer();
$db       = Database::connect();

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$stmt = $db->prepare("
    SELECT w.id, w.product_id, w.added_at,
           p.name, p.base_price, p.currency, p.is_available,
           CONCAT(u.first_name, ' ', u.last_name) AS tailor_name,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = w.product_id AND pi.is_main = 1 LIMIT 1) AS main_image_url
    FROM wishlist w
    JOIN products p ON p.id = w.product_id
    JOIN tailors t  ON t.id = p.tailor_id
    JOIN users u    ON u.id = t.user_id
    WHERE w.customer_id = ?
    ORDER BY w.added_at DESC
");
$stmt->execute([$customer['id']]);
$items = $stmt->fetchAll();

foreach ($items as &$item) {
    $item['base_price']  = (float)$item['base_price'];
    $item['is_available'] = (bool)$item['is_available'];
}

Response::success(['items' => $items, 'count' => count($items)]);
