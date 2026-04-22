<?php
// POST /farha_api/reviews_create.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$body    = getBody();
$db      = Database::connect();

$v = (new Validator($body))
    ->required('order_id', 'Order')
    ->required('rating', 'Rating')
    ->numeric('rating', 'Rating')
    ->min('rating', 1, 'Rating');
if ($v->fails()) Response::validationError($v->errors());

$rating = min(5, max(1, (int)$v->get('rating')));

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$orderStmt = $db->prepare('SELECT id, tailor_id, product_id, status FROM orders WHERE id = ? AND customer_id = ? LIMIT 1');
$orderStmt->execute([$v->get('order_id'), $customer['id']]);
$order = $orderStmt->fetch();

if (!$order) Response::notFound('Order not found.');
if ($order['status'] !== 'delivered') Response::error('You can only review a delivered order.', 422);

$existing = $db->prepare('SELECT id FROM reviews WHERE order_id = ? AND customer_id = ? LIMIT 1');
$existing->execute([$order['id'], $customer['id']]);
if ($existing->fetch()) Response::error('You have already reviewed this order.', 422);

$reviewId = generateUuid();
$db->prepare('
    INSERT INTO reviews (id, order_id, customer_id, tailor_id, product_id, rating, comment)
    VALUES (?, ?, ?, ?, ?, ?, ?)
')->execute([
    $reviewId,
    $order['id'],
    $customer['id'],
    $order['tailor_id'],
    $order['product_id'],
    $rating,
    !empty($body['comment']) ? sanitizeString($body['comment']) : null,
]);

// Update tailor rating average
$db->prepare('
    UPDATE tailors SET
        total_reviews = total_reviews + 1,
        rating = (SELECT AVG(r.rating) FROM reviews r WHERE r.tailor_id = tailors.id)
    WHERE id = ?
')->execute([$order['tailor_id']]);

// Update product rating if applicable
if ($order['product_id']) {
    $db->prepare('
        UPDATE products SET
            total_reviews = total_reviews + 1,
            rating = (SELECT AVG(r.rating) FROM reviews r WHERE r.product_id = products.id)
        WHERE id = ?
    ')->execute([$order['product_id']]);
}

Response::success(['review_id' => $reviewId], 'Review submitted. Thank you!', 201);
