<?php
// GET /farha_api/reviews_list.php?tailor_id=&product_id=&page=1
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

Auth::require();

$db       = Database::connect();
$tailorId = $_GET['tailor_id']  ?? null;
$productId= $_GET['product_id'] ?? null;
$page     = max(1, (int)($_GET['page']  ?? 1));
$limit    = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset   = ($page - 1) * $limit;

$where  = [];
$params = [];
if ($tailorId)  { $where[] = 'r.tailor_id = ?';  $params[] = $tailorId; }
if ($productId) { $where[] = 'r.product_id = ?'; $params[] = $productId; }
$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$count = $db->prepare("SELECT COUNT(*) FROM reviews r $whereClause");
$count->execute($params);
$total = (int)$count->fetchColumn();

$params[] = $limit;
$params[] = $offset;
$stmt = $db->prepare("
    SELECT r.id, r.rating, r.comment, r.created_at,
           CONCAT(u.first_name, ' ', u.last_name) AS customer_name,
           u.profile_photo, r.product_id
    FROM reviews r
    JOIN customers c ON c.id = r.customer_id
    JOIN users u     ON u.id = c.user_id
    $whereClause
    ORDER BY r.created_at DESC
    LIMIT ? OFFSET ?
");
$stmt->execute($params);
$reviews = $stmt->fetchAll();

foreach ($reviews as &$rv) {
    $rv['rating']       = (int)$rv['rating'];
    $rv['profile_photo'] = $rv['profile_photo'] ? UPLOAD_URL . 'profiles/' . $rv['profile_photo'] : null;
}

Response::success(['reviews' => $reviews, 'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit]]);
