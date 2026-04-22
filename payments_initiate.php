<?php
// POST /farha_api/payments_initiate.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$body    = getBody();
$db      = Database::connect();

$allowed_methods = ['mtn_momo', 'telecel', 'orange_money', 'mynita', 'amana', 'cash_on_pickup'];

$v = (new Validator($body))
    ->required('order_id', 'Order')
    ->required('amount', 'Amount')
    ->numeric('amount', 'Amount')
    ->min('amount', 0.01, 'Amount')
    ->required('payment_method', 'Payment method')
    ->inList('payment_method', $allowed_methods, 'Payment method');
if ($v->fails()) Response::validationError($v->errors());

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$orderStmt = $db->prepare('
    SELECT id, total_amount, paid_amount, currency, status
    FROM   orders
    WHERE  id = ? AND customer_id = ? LIMIT 1
');
$orderStmt->execute([$v->get('order_id'), $customer['id']]);
$order = $orderStmt->fetch();

if (!$order) Response::notFound('Order not found.');
if ($order['status'] === 'cancelled') Response::error('Cannot pay for a cancelled order.', 422);

$requestedAmount = (float)$v->get('amount');
$balanceDue      = (float)$order['total_amount'] - (float)$order['paid_amount'];
if ($requestedAmount > $balanceDue + 0.01) {
    Response::error("Amount exceeds remaining balance of {$balanceDue} {$order['currency']}.", 422);
}

// Calculate platform commission
$platformFee  = round($requestedAmount * PLATFORM_COMMISSION_RATE, 2);
$tailorAmount = round($requestedAmount - $platformFee, 2);

$paymentId = generateUuid();
$phone     = sanitizeString($v->get('phone', '') ?? '');

$db->prepare('
    INSERT INTO payments (id, order_id, amount, platform_fee, tailor_amount, currency, payment_method, phone, status)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, "pending")
')->execute([
    $paymentId,
    $order['id'],
    $requestedAmount,
    $platformFee,
    $tailorAmount,
    $order['currency'],
    sanitizeString($v->get('payment_method')),
    $phone ?: null,
]);

// Auto-confirm (production: integrate CinetPay / Wave / MTN MoMo API here)
$txId = 'DEMO-' . strtoupper(bin2hex(random_bytes(4)));
$db->prepare('UPDATE payments SET status = "completed", transaction_id = ? WHERE id = ?')
   ->execute([$txId, $paymentId]);

$db->prepare('UPDATE orders SET paid_amount = paid_amount + ? WHERE id = ?')
   ->execute([$requestedAmount, $order['id']]);

Response::success([
    'payment_id'     => $paymentId,
    'transaction_id' => $txId,
    'status'         => 'completed',
    'amount'         => $requestedAmount,
    'platform_fee'   => $platformFee,
    'tailor_amount'  => $tailorAmount,
    'currency'       => $order['currency'],
], 'Payment recorded successfully.');
