<?php
// GET  /farha_api/admin_payouts.php?status=pending  — list payout requests
// POST /farha_api/admin_payouts.php  { payout_id, action: approve|reject, notes? }
require_once __DIR__ . '/config.php';

$adminPayload = Auth::requireAdmin();
$db = Database::connect();

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? 'pending';
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];
    if ($status !== 'all') {
        $where[]  = 'py.status = ?';
        $params[] = $status;
    }
    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM payouts py WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT py.id, py.amount, py.payout_method, py.account, py.status,
               py.notes, py.reviewed_at, py.created_at,
               CONCAT(u.first_name,' ',u.last_name) AS tailor_name,
               u.email AS tailor_email, u.phone AS tailor_phone,
               t.shop_name,
               COALESCE((
                 SELECT SUM(p2.tailor_amount) FROM payments p2
                 JOIN orders o2 ON o2.id=p2.order_id
                 WHERE o2.tailor_id=t.id AND p2.status='completed'
               ),0) AS total_earned,
               COALESCE((
                 SELECT SUM(py2.amount) FROM payouts py2
                 WHERE py2.tailor_id=t.id AND py2.status IN ('approved','processing','completed')
               ),0) AS total_paid_out
        FROM   payouts py
        JOIN   tailors t ON t.id = py.tailor_id
        JOIN   users   u ON u.id = t.user_id
        WHERE  $whereClause
        ORDER  BY py.created_at DESC
        LIMIT  ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $payouts = $stmt->fetchAll();

    foreach ($payouts as &$py) {
        $py['amount']       = (float)$py['amount'];
        $py['total_earned'] = (float)$py['total_earned'];
        $py['total_paid_out'] = (float)$py['total_paid_out'];
        $py['available_after_this'] = max(0, $py['total_earned'] - $py['total_paid_out'] - $py['amount']);
    }

    Response::success([
        'payouts'    => $payouts,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

// ── POST: approve / reject ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();
    $v    = (new Validator($body))
        ->required('payout_id', 'Payout')
        ->required('action', 'Action')
        ->inList('action', ['approve','reject'], 'Action');
    if ($v->fails()) Response::validationError($v->errors());

    $stmt = $db->prepare('
        SELECT py.*, t.user_id AS tailor_user_id
        FROM   payouts py JOIN tailors t ON t.id = py.tailor_id
        WHERE  py.id = ? LIMIT 1
    ');
    $stmt->execute([$v->get('payout_id')]);
    $payout = $stmt->fetch();
    if (!$payout)                        Response::notFound('Payout request not found.');
    if ($payout['status'] !== 'pending') Response::error('This payout has already been reviewed.', 409);

    $newStatus = $v->get('action') === 'approve' ? 'approved' : 'rejected';
    $notes     = sanitizeString($v->get('notes', '') ?? '');

    $db->prepare('
        UPDATE payouts
        SET    status = ?, notes = ?, reviewed_by = ?, reviewed_at = NOW()
        WHERE  id = ?
    ')->execute([$newStatus, $notes ?: null, $adminPayload['admin_id'], $payout['id']]);

    // Notify tailor
    if ($v->get('action') === 'approve') {
        $notifBody = "Your payout request of {$payout['amount']} CFA via {$payout['payout_method']} has been approved and is being processed.";
        $notifTitle = 'Payout Approved';
    } else {
        $reason     = $notes ?: 'Please contact support for more information.';
        $notifBody  = "Your payout request was not approved. Reason: $reason";
        $notifTitle = 'Payout Request Update';
    }

    $db->prepare("
        INSERT INTO notifications (id, user_id, title, body, type)
        VALUES (?, ?, ?, ?, 'payout_update')
    ")->execute([generateUuid(), $payout['tailor_user_id'], $notifTitle, $notifBody]);

    $msg = $v->get('action') === 'approve' ? 'Payout approved.' : 'Payout rejected.';
    Response::success(null, $msg);
}

Response::error('Method not allowed.', 405);
