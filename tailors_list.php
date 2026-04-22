<?php
// GET /farha_api/tailors_list.php?page=1&limit=20
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

Auth::require();

$db     = Database::connect();
$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(50, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

$count = (int)$db->query('SELECT COUNT(*) FROM tailors t JOIN users u ON u.id = t.user_id WHERE u.is_active = 1 AND u.is_verified = 1')->fetchColumn();

$stmt = $db->prepare('
    SELECT t.id, t.user_id, t.shop_name, t.bio, t.shop_location,
           t.years_experience, t.experience_level, t.is_available,
           t.is_verified_tailor, t.rating, t.total_reviews, t.total_orders,
           CONCAT(u.first_name, \' \', u.last_name) AS full_name,
           u.profile_photo, u.email, u.phone
    FROM tailors t
    JOIN users u ON u.id = t.user_id
    WHERE u.is_active = 1 AND u.is_verified = 1
    ORDER BY t.rating DESC, t.total_reviews DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$limit, $offset]);
$tailors = $stmt->fetchAll();

foreach ($tailors as &$t) {
    $t['rating']          = (float)$t['rating'];
    $t['is_available']    = (bool)$t['is_available'];
    $t['is_verified_tailor'] = (bool)$t['is_verified_tailor'];
    $t['profile_photo']   = $t['profile_photo'] ? UPLOAD_URL . 'profiles/' . $t['profile_photo'] : null;
}

Response::success([
    'tailors'    => $tailors,
    'pagination' => ['total' => $count, 'page' => $page, 'limit' => $limit, 'total_pages' => (int)ceil($count / $limit)],
]);
