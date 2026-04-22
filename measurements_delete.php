<?php
// DELETE /farha_api/measurements_delete.php?id=MEASUREMENT_ID
require_once __DIR__ . '/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) Response::error('Method not allowed.', 405);

$payload       = Auth::requireCustomer();
$body          = $_SERVER['REQUEST_METHOD'] === 'POST' ? getBody() : [];
$measurementId = $_GET['id'] ?? $body['id'] ?? '';
if (empty($measurementId)) Response::error('Measurement ID is required.', 400);

$db      = Database::connect();
$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$chk = $db->prepare('SELECT id FROM measurements WHERE id = ? AND customer_id = ? LIMIT 1');
$chk->execute([$measurementId, $customer['id']]);
if (!$chk->fetch()) Response::notFound('Measurement not found or not yours.');

$db->prepare('DELETE FROM measurements WHERE id = ?')->execute([$measurementId]);

Response::success(null, 'Measurement deleted.');
