<?php
// PUT /farha_api/cart_update.php?id=CART_ITEM_ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') Response::error('Method not allowed.', 405);

$payload    = Auth::requireCustomer();
$cartItemId = $_GET['id'] ?? '';
if (empty($cartItemId)) Response::error('Cart item ID is required.', 400);

$body = getBody();
$v    = (new Validator($body))->required('quantity')->numeric('quantity')->min('quantity', 1, 'Quantity');
if ($v->fails()) Response::validationError($v->errors());

$db      = Database::connect();
$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$chk = $db->prepare('SELECT id FROM cart_items WHERE id = ? AND customer_id = ? LIMIT 1');
$chk->execute([$cartItemId, $customer['id']]);
if (!$chk->fetch()) Response::notFound('Cart item not found.');

$db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')
   ->execute([(int)$v->get('quantity'), $cartItemId]);

Response::success(null, 'Cart updated.');
