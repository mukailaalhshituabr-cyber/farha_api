<?php
// GET /farha_api/orders_list.php?status=&page=1&limit=20
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload  = Auth::require();
$db       = Database::connect();
$userType = $payload['user_type'];
$userId   = $payload['user_id'];

$page   = max(1, (int)($_GET['page']   ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;
$status = $_GET['status'] ?? null;

// Build role-based WHERE
if ($userType === 'customer') {
    $profileStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch();
    if (!$profile) Response::notFound('Customer profile not found.');
    $roleClause = 'o.customer_id = ?';
    $roleParam  = $profile['id'];
} else {
    $profileStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch();
    if (!$profile) Response::notFound('Tailor profile not found.');
    $roleClause = 'o.tailor_id = ?';
    $roleParam  = $profile['id'];
}

$where  = [$roleClause];
$params = [$roleParam];

if ($status === 'active') {
    $active = ['pending','confirmed','cutting','sewing','ready'];
    $placeholders = implode(',', array_fill(0, count($active), '?'));
    $where[]  = "o.status IN ($placeholders)";
    $params   = array_merge($params, $active);
} elseif ($status) {
    $where[] = 'o.status = ?';
    $params[] = $status;
}

$whereClause = implode(' AND ', $where);

$count = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $whereClause");
$count->execute($params);
$total = (int)$count->fetchColumn();

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare("
    SELECT o.id, o.customer_id, o.tailor_id, o.product_id,
           o.reference_number, o.order_type, o.status, o.size,
           o.quantity, o.total_amount, o.deposit_amount, o.paid_amount,
           o.currency, o.special_instructions, o.estimated_completion,
           o.cancel_reason, o.created_at, o.updated_at,
           CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
           CONCAT(tu.first_name, ' ', tu.last_name) AS tailor_name,
           t.shop_name,
           p.name AS product_name,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = o.product_id AND pi.is_main = 1 LIMIT 1) AS product_image_url
    FROM orders o
    JOIN customers c  ON c.id  = o.customer_id
    JOIN users cu     ON cu.id = c.user_id
    JOIN tailors t    ON t.id  = o.tailor_id
    JOIN users tu     ON tu.id = t.user_id
    LEFT JOIN products p ON p.id = o.product_id
    WHERE $whereClause
    ORDER BY o.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$orders = $stmt->fetchAll();

foreach ($orders as &$o) {
    $o['total_amount']   = (float)$o['total_amount'];
    $o['deposit_amount'] = (float)$o['deposit_amount'];
    $o['paid_amount']    = (float)$o['paid_amount'];
}

Response::success([
    'orders'     => $orders,
    'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit, 'total_pages' => (int)ceil($total / $limit)],
]);
