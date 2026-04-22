<?php
// DELETE /farha_api/cart_remove.php?id=CART_ITEM_ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') Response::error('Method not allowed.', 405);

$payload    = Auth::requireCustomer();
$cartItemId = $_GET['id'] ?? '';
if (empty($cartItemId)) Response::error('Cart item ID is required.', 400);

$db      = Database::connect();
$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$chk = $db->prepare('SELECT id FROM cart_items WHERE id = ? AND customer_id = ? LIMIT 1');
$chk->execute([$cartItemId, $customer['id']]);
if (!$chk->fetch()) Response::notFound('Cart item not found.');

$db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$cartItemId]);

Response::success(null, 'Item removed from cart.');
