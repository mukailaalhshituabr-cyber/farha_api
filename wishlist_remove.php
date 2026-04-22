<?php
// DELETE /farha_api/wishlist_remove.php?product_id=PRODUCT_ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') Response::error('Method not allowed.', 405);

$payload   = Auth::requireCustomer();
$productId = $_GET['product_id'] ?? '';
if (empty($productId)) Response::error('Product ID is required.', 400);

$db      = Database::connect();
$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$db->prepare('DELETE FROM wishlist WHERE customer_id = ? AND product_id = ?')
   ->execute([$customer['id'], $productId]);

Response::success(null, 'Removed from wishlist.');
