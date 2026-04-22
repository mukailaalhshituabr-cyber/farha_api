<?php
// POST /farha_api/cart_add.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$body    = getBody();
$db      = Database::connect();

$v = (new Validator($body))
    ->required('product_id', 'Product')
    ->required('quantity', 'Quantity')
    ->numeric('quantity', 'Quantity')
    ->min('quantity', 1, 'Quantity');
if ($v->fails()) Response::validationError($v->errors());

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$prodStmt = $db->prepare('SELECT id, stock_quantity, is_available FROM products WHERE id = ? LIMIT 1');
$prodStmt->execute([$v->get('product_id')]);
$product = $prodStmt->fetch();
if (!$product || !$product['is_available']) Response::error('Product not available.', 422);

$size = $body['size'] ?? null;

// Upsert: if same product+size already in cart, increment quantity
$existing = $db->prepare('SELECT id, quantity FROM cart_items WHERE customer_id = ? AND product_id = ? AND (size = ? OR (size IS NULL AND ? IS NULL)) LIMIT 1');
$existing->execute([$customer['id'], $v->get('product_id'), $size, $size]);
$cartItem = $existing->fetch();

if ($cartItem) {
    $db->prepare('UPDATE cart_items SET quantity = quantity + ? WHERE id = ?')
       ->execute([(int)$v->get('quantity'), $cartItem['id']]);
    $cartId = $cartItem['id'];
} else {
    $cartId = generateUuid();
    $db->prepare('INSERT INTO cart_items (id, customer_id, product_id, quantity, size) VALUES (?, ?, ?, ?, ?)')
       ->execute([$cartId, $customer['id'], $v->get('product_id'), (int)$v->get('quantity'), $size]);
}

Response::success(['cart_item_id' => $cartId], 'Item added to cart.', 201);
