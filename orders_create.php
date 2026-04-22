<?php
// POST /farha_api/orders_create.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$body    = getBody();
$db      = Database::connect();

$v = (new Validator($body))
    ->required('tailor_id', 'Tailor')
    ->required('order_type', 'Order type')
    ->inList('order_type', ['ready_made','custom'], 'Order type')
    ->required('total_amount', 'Total amount')
    ->numeric('total_amount', 'Total amount')
    ->min('total_amount', 0.01, 'Total amount')
    ->required('quantity', 'Quantity')
    ->numeric('quantity', 'Quantity')
    ->min('quantity', 1, 'Quantity');

if ($v->fails()) Response::validationError($v->errors());

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$tailorStmt = $db->prepare('SELECT id FROM tailors WHERE id = ? LIMIT 1');
$tailorStmt->execute([$v->get('tailor_id')]);
if (!$tailorStmt->fetch()) Response::error('Tailor not found.', 422);

$orderId   = generateUuid();
$reference = 'FAR-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

$db->prepare('
    INSERT INTO orders (id, reference_number, customer_id, tailor_id, product_id, order_type,
                        quantity, total_amount, deposit_amount, currency, size,
                        special_instructions, design_reference_url, estimated_completion)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
')->execute([
    $orderId,
    $reference,
    $customer['id'],
    $v->get('tailor_id'),
    $body['product_id'] ?? null,
    $v->get('order_type'),
    (int)$v->get('quantity'),
    (float)$v->get('total_amount'),
    isset($body['deposit_amount']) ? (float)$body['deposit_amount'] : 0,
    $body['currency'] ?? 'CFA',
    $body['size'] ?? null,
    isset($body['special_instructions']) ? sanitizeString($body['special_instructions']) : null,
    $body['design_reference_url'] ?? null,
    $body['estimated_completion'] ?? null,
]);

Response::success(
    ['order_id' => $orderId, 'reference_number' => $reference],
    'Order placed successfully.',
    201
);
