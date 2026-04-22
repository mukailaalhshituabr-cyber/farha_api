<?php
// GET  /farha_api/admin_orders.php?status=&search=&page=  — all platform orders
// POST /farha_api/admin_orders.php  { order_id, status }  — override status
require_once __DIR__ . '/config.php';

Auth::requireAdmin();
$db = Database::connect();

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $status = $_GET['status'] ?? '';
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($status !== '') {
        $where[]  = 'o.status = ?';
        $params[] = $status;
    }

    if ($search !== '') {
        $where[]  = '(o.reference_number LIKE ? OR cu.first_name LIKE ? OR cu.last_name LIKE ? OR tu.first_name LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like, $like]);
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("
        SELECT COUNT(*) FROM orders o
        JOIN customers c ON c.id = o.customer_id JOIN users cu ON cu.id = c.user_id
        JOIN tailors   t ON t.id = o.tailor_id   JOIN users tu ON tu.id = t.user_id
        WHERE $whereClause
    ");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT o.id, o.reference_number, o.order_type, o.status,
               o.total_amount, o.paid_amount, o.currency, o.created_at,
               CONCAT(cu.first_name,' ',cu.last_name) AS customer_name,
               cu.profile_photo                        AS customer_photo,
               CONCAT(tu.first_name,' ',tu.last_name) AS tailor_name,
               t.shop_name,
               COALESCE(SUM(p.platform_fee),0)         AS platform_fee_collected,
               COALESCE(SUM(p.amount),0)               AS total_paid
        FROM   orders o
        JOIN   customers c  ON c.id  = o.customer_id
        JOIN   users     cu ON cu.id = c.user_id
        JOIN   tailors   t  ON t.id  = o.tailor_id
        JOIN   users     tu ON tu.id = t.user_id
        LEFT   JOIN payments p ON p.order_id = o.id AND p.status = 'completed'
        WHERE  $whereClause
        GROUP  BY o.id
        ORDER  BY o.created_at DESC
        LIMIT  ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $orders = $stmt->fetchAll();

    foreach ($orders as &$ord) {
        $ord['total_amount']          = (float)$ord['total_amount'];
        $ord['paid_amount']           = (float)$ord['paid_amount'];
        $ord['platform_fee_collected'] = (float)$ord['platform_fee_collected'];
        $ord['total_paid']             = (float)$ord['total_paid'];
    }

    Response::success([
        'orders'     => $orders,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

// ── POST: override status ────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();
    $v    = (new Validator($body))
        ->required('order_id', 'Order')
        ->required('status',   'Status')
        ->inList('status', ['pending','confirmed','cutting','sewing','ready','delivered','cancelled'], 'Status');
    if ($v->fails()) Response::validationError($v->errors());

    $stmt = $db->prepare('SELECT id FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$v->get('order_id')]);
    if (!$stmt->fetch()) Response::notFound('Order not found.');

    $db->prepare('UPDATE orders SET status = ? WHERE id = ?')
       ->execute([$v->get('status'), $v->get('order_id')]);

    Response::success(null, 'Order status updated.');
}

Response::error('Method not allowed.', 405);
