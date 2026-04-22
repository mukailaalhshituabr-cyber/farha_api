<?php
// GET  /farha_api/admin_payments.php?status=&page=  — all platform payments
// POST /farha_api/admin_payments.php  { payment_id, action: refund }
require_once __DIR__ . '/config.php';

Auth::requireAdmin();
$db = Database::connect();

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($status !== '') {
        $where[]  = 'p.status = ?';
        $params[] = $status;
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM payments p WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT p.id, p.order_id, p.amount, p.platform_fee, p.tailor_amount,
               p.currency, p.payment_method, p.transaction_id, p.status, p.created_at,
               o.reference_number,
               CONCAT(cu.first_name,' ',cu.last_name) AS customer_name,
               CONCAT(tu.first_name,' ',tu.last_name) AS tailor_name,
               t.shop_name
        FROM   payments p
        JOIN   orders   o  ON o.id  = p.order_id
        JOIN   customers c ON c.id  = o.customer_id  JOIN users cu ON cu.id = c.user_id
        JOIN   tailors   t ON t.id  = o.tailor_id    JOIN users tu ON tu.id = t.user_id
        WHERE  $whereClause
        ORDER  BY p.created_at DESC
        LIMIT  ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $payments = $stmt->fetchAll();

    foreach ($payments as &$pmt) {
        $pmt['amount']        = (float)$pmt['amount'];
        $pmt['platform_fee']  = (float)$pmt['platform_fee'];
        $pmt['tailor_amount'] = (float)$pmt['tailor_amount'];
    }

    // Summary totals
    $summaryStmt = $db->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN status='completed' THEN amount        END),0) AS total_collected,
            COALESCE(SUM(CASE WHEN status='completed' THEN platform_fee  END),0) AS total_commission,
            COALESCE(SUM(CASE WHEN status='completed' THEN tailor_amount END),0) AS total_tailor_earnings,
            COALESCE(SUM(CASE WHEN status='refunded'  THEN amount        END),0) AS total_refunded
        FROM payments
    ");
    $summaryStmt->execute();
    $summary = $summaryStmt->fetch();
    foreach ($summary as &$v) $v = (float)$v;

    Response::success([
        'payments'   => $payments,
        'summary'    => $summary,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

// ── POST: refund ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();
    $v    = (new Validator($body))
        ->required('payment_id', 'Payment')
        ->required('action', 'Action')
        ->inList('action', ['refund'], 'Action');
    if ($v->fails()) Response::validationError($v->errors());

    $stmt = $db->prepare('SELECT id, order_id, amount, status FROM payments WHERE id = ? LIMIT 1');
    $stmt->execute([$v->get('payment_id')]);
    $payment = $stmt->fetch();
    if (!$payment) Response::notFound('Payment not found.');
    if ($payment['status'] !== 'completed') Response::error('Only completed payments can be refunded.', 422);

    $db->prepare('UPDATE payments SET status = "refunded" WHERE id = ?')
       ->execute([$payment['id']]);

    // Reverse the paid_amount on the order
    $db->prepare('UPDATE orders SET paid_amount = GREATEST(0, paid_amount - ?) WHERE id = ?')
       ->execute([(float)$payment['amount'], $payment['order_id']]);

    Response::success(null, 'Payment refunded successfully.');
}

Response::error('Method not allowed.', 405);
