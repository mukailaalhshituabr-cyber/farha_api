<?php
// POST /farha_api/wishlist_add.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$body    = getBody();
$db      = Database::connect();

$v = (new Validator($body))->required('product_id', 'Product');
if ($v->fails()) Response::validationError($v->errors());

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$existing = $db->prepare('SELECT id FROM wishlist WHERE customer_id = ? AND product_id = ? LIMIT 1');
$existing->execute([$customer['id'], $v->get('product_id')]);
if ($existing->fetch()) Response::success(null, 'Already in wishlist.');

$id = generateUuid();
$db->prepare('INSERT INTO wishlist (id, customer_id, product_id) VALUES (?, ?, ?)')
   ->execute([$id, $customer['id'], $v->get('product_id')]);

Response::success(['wishlist_id' => $id], 'Added to wishlist.', 201);
