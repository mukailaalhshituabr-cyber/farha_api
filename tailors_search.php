<?php
// GET /farha_api/tailors_search.php?q=name&page=1
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
    SELECT COUNT(*) FROM tailors t JOIN users u ON u.id = t.user_id
    WHERE u.is_active = 1 AND u.is_verified = 1
      AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR t.shop_name LIKE ? OR t.shop_location LIKE ?)
");
$count->execute([$search, $search, $search]);
$total = (int)$count->fetchColumn();

$stmt = $db->prepare("
    SELECT t.id, t.shop_name, t.shop_location, t.experience_level,
           t.is_available, t.rating, t.total_reviews,
           CONCAT(u.first_name, ' ', u.last_name) AS full_name,
           u.profile_photo
    FROM tailors t
    JOIN users u ON u.id = t.user_id
    WHERE u.is_active = 1 AND u.is_verified = 1
      AND (CONCAT(u.first_name, ' ', u.last_name) LIKE ? OR t.shop_name LIKE ? OR t.shop_location LIKE ?)
    ORDER BY t.rating DESC
    LIMIT ? OFFSET ?
");
$stmt->execute([$search, $search, $search, $limit, $offset]);
$tailors = $stmt->fetchAll();

foreach ($tailors as &$t) {
    $t['rating']       = (float)$t['rating'];
    $t['is_available'] = (bool)$t['is_available'];
    $t['profile_photo'] = $t['profile_photo'] ? UPLOAD_URL . 'profiles/' . $t['profile_photo'] : null;
}

Response::success(['query' => $q, 'tailors' => $tailors, 'pagination' => ['total' => $total, 'page' => $page, 'limit' => $limit]]);
