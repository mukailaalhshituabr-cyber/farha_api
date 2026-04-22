<?php
// GET /farha_api/payments_verify.php?payment_id=ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload   = Auth::require();
$paymentId = $_GET['payment_id'] ?? '';
if (empty($paymentId)) Response::error('Payment ID is required.', 400);

$db   = Database::connect();
$stmt = $db->prepare('SELECT id, order_id, amount, currency, payment_method, transaction_id, status, created_at FROM payments WHERE id = ? LIMIT 1');
$stmt->execute([$paymentId]);
$payment = $stmt->fetch();

if (!$payment) Response::notFound('Payment not found.');

$payment['amount'] = (float)$payment['amount'];
Response::success($payment);
