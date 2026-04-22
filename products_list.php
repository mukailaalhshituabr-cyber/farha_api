<?php
// GET /farha_api/products_list.php?category_id=&tailor_id=&page=1&limit=20
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload    = Auth::require();
$db         = Database::connect();
$page       = max(1, (int)($_GET['page']   ?? 1));
$limit      = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset     = ($page - 1) * $limit;
$categoryId = $_GET['category_id'] ?? null;
$tailorId   = $_GET['tailor_id']   ?? null;
$search     = trim($_GET['search']  ?? '');
$sort       = $_GET['sort']         ?? 'newest';

// Tailors viewing their own products bypass visibility filters to see drafts/unavailable
$ownProducts = false;
if ($payload['user_type'] === 'tailor') {
    // ?own=1 lets the authenticated tailor fetch their products without knowing their tailor UUID
    if (($_GET['own'] ?? '') === '1') {
        $ownStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
        $ownStmt->execute([$payload['user_id']]);
        $ownRow = $ownStmt->fetch();
        if ($ownRow) {
            $tailorId    = $ownRow['id'];
            $ownProducts = true;
        }
    } elseif ($tailorId) {
        $chk = $db->prepare('SELECT id FROM tailors WHERE id = ? AND user_id = ? LIMIT 1');
        $chk->execute([$tailorId, $payload['user_id']]);
        $ownProducts = (bool)$chk->fetch();
    }
}

$where  = $ownProducts ? [] : ['p.is_available = 1', 'p.is_draft = 0'];
$params = [];

if ($categoryId) { $where[] = 'p.category_id = ?'; $params[] = $categoryId; }
if ($tailorId)   { $where[] = 'p.tailor_id = ?';   $params[] = $tailorId; }
if ($search !== '') {
    $where[]  = '(p.name LIKE ? OR p.description LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ?)';
    $like     = '%' . $search . '%';
    $params[] = $like; $params[] = $like; $params[] = $like; $params[] = $like;
}

$orderBy = match($sort) {
    'price_asc'  => 'p.base_price ASC',
    'price_desc' => 'p.base_price DESC',
    'rating'     => 'p.rating DESC, p.total_reviews DESC',
    default      => 'p.created_at DESC',
};

$whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$countStmt = $db->prepare("SELECT COUNT(*) FROM products p JOIN tailors t ON t.id = p.tailor_id JOIN users u ON u.id = t.user_id JOIN categories c ON c.id = p.category_id $whereClause");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

$params[] = $limit;
$params[] = $offset;

$stmt = $db->prepare("
    SELECT p.id, p.tailor_id, p.category_id, p.name, p.description,
           p.base_price, p.currency, p.stock_quantity, p.allows_custom,
           p.is_available, p.is_draft, p.available_sizes, p.rating,
           p.total_reviews, p.total_sales, p.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS tailor_name,
           c.name_en AS category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image_url
    FROM products p
    JOIN tailors t ON t.id = p.tailor_id
    JOIN users u   ON u.id = t.user_id
    JOIN categories c ON c.id = p.category_id
    $whereClause
    ORDER BY $orderBy
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$products = $stmt->fetchAll();

// Collect all product IDs so we can batch-fetch images in one query
$productIds = array_column($products, 'id');

$imageMap = [];
if (!empty($productIds)) {
    $placeholders = implode(',', array_fill(0, count($productIds), '?'));
    $imgStmt = $db->prepare(
        "SELECT product_id, id, image_url, is_main FROM product_images WHERE product_id IN ($placeholders) ORDER BY is_main DESC, sort_order ASC"
    );
    $imgStmt->execute($productIds);
    foreach ($imgStmt->fetchAll() as $img) {
        $imageMap[$img['product_id']][] = [
            'id'       => $img['id'],
            'image_url'=> $img['image_url'],
            'is_main'  => (bool)$img['is_main'],
        ];
    }
}

foreach ($products as &$p) {
    $p['available_sizes'] = $p['available_sizes'] ? json_decode($p['available_sizes'], true) : [];
    $p['base_price']      = (float)$p['base_price'];
    $p['rating']          = (float)$p['rating'];
    $p['allows_custom']   = (bool)$p['allows_custom'];
    $p['is_available']    = (bool)$p['is_available'];
    $p['is_draft']        = (bool)$p['is_draft'];
    $p['images']          = $imageMap[$p['id']] ?? [];
}

Response::success([
    'items'    => $products,
    'total'    => $total,
    'has_more' => ($page * $limit) < $total,
    'page'     => $page,
    'limit'    => $limit,
]);
