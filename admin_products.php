<?php
// GET    /farha_api/admin_products.php?search=&tailor_id=&page=  — all products
// DELETE /farha_api/admin_products.php  { product_id, reason? }  — remove product
require_once __DIR__ . '/config.php';

Auth::requireAdmin();
$db = Database::connect();

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search   = trim($_GET['search']    ?? '');
    $tailorId = trim($_GET['tailor_id'] ?? '');
    $page     = max(1, (int)($_GET['page']  ?? 1));
    $limit    = min(50, max(1, (int)($_GET['limit'] ?? 20)));
    $offset   = ($page - 1) * $limit;

    $where  = ['1=1'];
    $params = [];

    if ($search !== '') {
        $where[]  = '(pr.name LIKE ? OR pr.description LIKE ?)';
        $like     = '%' . $search . '%';
        $params   = array_merge($params, [$like, $like]);
    }
    if ($tailorId !== '') {
        $where[]  = 'pr.tailor_id = ?';
        $params[] = $tailorId;
    }

    $whereClause = implode(' AND ', $where);

    $countStmt = $db->prepare("SELECT COUNT(*) FROM products pr WHERE $whereClause");
    $countStmt->execute($params);
    $total = (int)$countStmt->fetchColumn();

    $stmt = $db->prepare("
        SELECT pr.id, pr.name, pr.base_price, pr.currency, pr.is_available,
               pr.is_draft, pr.rating, pr.total_reviews, pr.total_sales,
               pr.created_at,
               t.shop_name,
               CONCAT(u.first_name,' ',u.last_name) AS tailor_name,
               pi.image_url AS main_image
        FROM   products pr
        JOIN   tailors  t  ON t.id  = pr.tailor_id
        JOIN   users    u  ON u.id  = t.user_id
        LEFT   JOIN product_images pi ON pi.product_id = pr.id AND pi.is_main = 1
        WHERE  $whereClause
        ORDER  BY pr.created_at DESC
        LIMIT  ? OFFSET ?
    ");
    $stmt->execute(array_merge($params, [$limit, $offset]));
    $products = $stmt->fetchAll();

    foreach ($products as &$p) {
        $p['base_price']    = (float)$p['base_price'];
        $p['is_available']  = (bool)$p['is_available'];
        $p['is_draft']      = (bool)$p['is_draft'];
        $p['rating']        = (float)$p['rating'];
        $p['total_reviews'] = (int)$p['total_reviews'];
        $p['total_sales']   = (int)$p['total_sales'];
    }

    Response::success([
        'products'   => $products,
        'pagination' => [
            'total'       => $total,
            'page'        => $page,
            'limit'       => $limit,
            'total_pages' => (int)ceil($total / $limit),
        ],
    ]);
}

// ── DELETE: remove product ───────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    $body = getBody();
    $v    = (new Validator($body))->required('product_id', 'Product');
    if ($v->fails()) Response::validationError($v->errors());

    $stmt = $db->prepare('SELECT id, tailor_id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$v->get('product_id')]);
    $product = $stmt->fetch();
    if (!$product) Response::notFound('Product not found.');

    $db->prepare('DELETE FROM products WHERE id = ?')->execute([$product['id']]);

    Response::success(null, 'Product removed.');
}

// ── POST: toggle availability ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();
    $v    = (new Validator($body))
        ->required('product_id', 'Product')
        ->required('action', 'Action')
        ->inList('action', ['disable','enable'], 'Action');
    if ($v->fails()) Response::validationError($v->errors());

    $stmt = $db->prepare('SELECT id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$v->get('product_id')]);
    if (!$stmt->fetch()) Response::notFound('Product not found.');

    $avail = $v->get('action') === 'enable' ? 1 : 0;
    $db->prepare('UPDATE products SET is_available = ? WHERE id = ?')
       ->execute([$avail, $v->get('product_id')]);

    Response::success(null, $v->get('action') === 'enable' ? 'Product enabled.' : 'Product disabled.');
}

Response::error('Method not allowed.', 405);
