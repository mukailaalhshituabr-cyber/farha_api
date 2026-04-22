<?php
// GET  /farha_api/admin_users.php?type=customer|tailor&search=&page=  — list users
// POST /farha_api/admin_users.php  { user_id, action: suspend|unsuspend }
require_once __DIR__ . '/config.php';

Auth::requireAdmin();
$db = Database::connect();

// ── GET: list users ──────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $type   = $_GET['type']   ?? 'all';      // all | customer | tailor
    $search = trim($_GET['search'] ?? '');
    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if (in_array($type, ['customer', 'tailor'])) {
        $where[]  = 'u.user_type = ?';
        $params[] = $type;
    }

    if ($search !== '') {
        $where[]  = '(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like, $like]);
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM users u WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT u.id, u.first_name, u.last_name, u.email, u.phone,
               u.user_type, u.is_active, u.is_verified, u.created_at, u.last_login,
               u.profile_photo,
               CASE WHEN u.user_type='tailor' THEN t.shop_name ELSE NULL END AS shop_name,
               CASE WHEN u.user_type='tailor' THEN t.status    ELSE NULL END AS tailor_status,
               CASE WHEN u.user_type='tailor' THEN t.rating    ELSE NULL END AS rating,
               CASE WHEN u.user_type='tailor' THEN t.total_orders ELSE NULL END AS total_orders
        FROM   users u
        LEFT   JOIN tailors   t ON t.user_id = u.id
        WHERE  $whereClause
        ORDER  BY u.created_at DESC
        LIMIT  ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $users = $stmt->fetchAll();

    foreach ($users as &$u) {
        $u['is_active']   = (bool)$u['is_active'];
        $u['is_verified'] = (bool)$u['is_verified'];
        if ($u['rating'] !== null) $u['rating'] = (float)$u['rating'];
        if ($u['total_orders'] !== null) $u['total_orders'] = (int)$u['total_orders'];
    }

    Response::success([
        'users'      => $users,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

// ── POST: suspend / unsuspend ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();
    $v    = (new Validator($body))
        ->required('user_id', 'User')
        ->required('action', 'Action')
        ->inList('action', ['suspend', 'unsuspend'], 'Action');
    if ($v->fails()) Response::validationError($v->errors());

    $stmt = $db->prepare('SELECT id, user_type FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$v->get('user_id')]);
    $user = $stmt->fetch();
    if (!$user) Response::notFound('User not found.');

    $isActive = $v->get('action') === 'unsuspend' ? 1 : 0;
    $db->prepare('UPDATE users SET is_active = ? WHERE id = ?')
       ->execute([$isActive, $user['id']]);

    // Also update tailor status if applicable
    if ($user['user_type'] === 'tailor' && $v->get('action') === 'suspend') {
        $db->prepare("UPDATE tailors SET status = 'suspended' WHERE user_id = ?")
           ->execute([$user['id']]);
    } elseif ($user['user_type'] === 'tailor' && $v->get('action') === 'unsuspend') {
        $db->prepare("UPDATE tailors SET status = 'approved' WHERE user_id = ? AND status = 'suspended'")
           ->execute([$user['id']]);
    }

    $msg = $v->get('action') === 'suspend' ? 'User suspended.' : 'User unsuspended.';
    Response::success(null, $msg);
}

Response::error('Method not allowed.', 405);
