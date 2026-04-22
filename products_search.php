<?php
// GET /farha_api/products_search.php?q=term&page=1&limit=20
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

Auth::require();

$q      = trim($_GET['q'] ?? '');
$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

if (strlen($q) < 2) Response::error('Search query must be at least 2 characters.', 400);

$db     = Database::connect();
$search = "%$q%";

$count = $db->prepare("
    SELECT COUNT(*) FROM products p
    WHERE p.is_available = 1 AND p.is_draft = 0
      AND (p.name LIKE ? OR p.description LIKE ?)
");
$count->execute([$search, $search]);
$total = (int)$count->fetchColumn();

$stmt = $db->prepare("
    SELECT p.id, p.tailor_id, p.category_id, p.name, p.description,
           p.base_price, p.currency, p.stock_quantity, p.allows_custom,
           p.rating, p.total_reviews, p.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS tailor_name,
           c.name_en AS category_name,
           (SELECT image_url FROM product_images WHERE product_id = p.id AND is_main = 1 LIMIT 1) AS main_image_url
    FROM products p
    JOIN tailors t ON t.id = p.tailor_id
    JOIN users u   ON u.id = t.user_id
    JOIN categories c ON c.id = p.category_id
    WHERE p.is_available = 1 AND p.is_draft = 0
      AND (p.name LIKE ? OR p.description LIKE ?)
    ORDER BY p.rating DESC, p.total_reviews DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$search, $search, $limit, $offset]);
$products = $stmt->fetchAll();

foreach ($products as &$p) {
    $p['base_price']    = (float)$p['base_price'];
    $p['rating']        = (float)$p['rating'];
    $p['allows_custom'] = (bool)$p['allows_custom'];
}

Response::success([
    'query'      => $q,
    'products'   => $products,
    'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit],
]);
