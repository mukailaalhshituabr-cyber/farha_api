<?php
// POST /farha_api/revenue_payout.php  —  tailor requests a payout
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireTailor();
$body    = getBody();
$db      = Database::connect();

$tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
$tailorStmt->execute([$payload['user_id']]);
$tailor = $tailorStmt->fetch();
if (!$tailor) Response::notFound('Tailor profile not found.');
$tailorId = $tailor['id'];

$allowed_methods = ['mtn_momo', 'telecel', 'orange_money', 'mynita', 'amana'];

$v = (new Validator($body))
    ->required('amount', 'Amount')
    ->numeric('amount', 'Amount')
    ->min('amount', MIN_PAYOUT_AMOUNT, 'Amount')
    ->required('payout_method', 'Payout method')
    ->inList('payout_method', $allowed_methods, 'Payout method')
    ->required('account', 'Account number');
if ($v->fails()) Response::validationError($v->errors());

// Calculate available balance: sum of tailor_amount from completed payments - sum of approved/completed payouts
$earningsStmt = $db->prepare('
    SELECT COALESCE(SUM(p.tailor_amount), 0) AS total_earned
    FROM   payments p
    JOIN   orders   o ON o.id = p.order_id
    WHERE  o.tailor_id = ? AND p.status = "completed"
');
$earningsStmt->execute([$tailorId]);
$totalEarned = (float)$earningsStmt->fetchColumn();

$paidOutStmt = $db->prepare('
    SELECT COALESCE(SUM(amount), 0) AS total_paid_out
    FROM   payouts
    WHERE  tailor_id = ? AND status IN ("approved","processing","completed")
');
$paidOutStmt->execute([$tailorId]);
$totalPaidOut = (float)$paidOutStmt->fetchColumn();

$available = round($totalEarned - $totalPaidOut, 2);
$requested = (float)$v->get('amount');

if ($requested > $available) {
    Response::error("Requested amount exceeds available balance of {$available} CFA.", 422);
}

// Check no pending payout already waiting
$pendingStmt = $db->prepare("SELECT id FROM payouts WHERE tailor_id = ? AND status = 'pending' LIMIT 1");
$pendingStmt->execute([$tailorId]);
if ($pendingStmt->fetch()) {
    Response::error('You already have a pending payout request. Please wait for it to be processed.', 409);
}

$payoutId = generateUuid();
$db->prepare('
    INSERT INTO payouts (id, tailor_id, amount, payout_method, account, status)
    VALUES (?, ?, ?, ?, ?, "pending")
')->execute([
    $payoutId,
    $tailorId,
    $requested,
    sanitizeString($v->get('payout_method')),
    sanitizeString($v->get('account')),
]);

Response::success([
    'payout_id'      => $payoutId,
    'amount'         => $requested,
    'available'      => round($available - $requested, 2),
    'payout_method'  => $v->get('payout_method'),
    'status'         => 'pending',
    'estimated_days' => 3,
], 'Payout request submitted. An admin will review it within 1–3 business days.');
