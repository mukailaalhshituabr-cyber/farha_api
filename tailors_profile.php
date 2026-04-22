<?php
// GET /farha_api/tailors_profile.php?id=TAILOR_ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

Auth::require();

$tailorId = $_GET['id'] ?? '';
if (empty($tailorId)) Response::error('Tailor ID is required.', 400);

$db   = Database::connect();
$stmt = $db->prepare('
    SELECT t.id, t.user_id, t.shop_name, t.bio, t.shop_location,
           t.latitude, t.longitude, t.years_experience, t.experience_level,
           t.is_available, t.is_verified_tailor, t.rating, t.total_reviews, t.total_orders,
           CONCAT(u.first_name, \' \', u.last_name) AS full_name,
           u.profile_photo, u.email, u.phone
    FROM tailors t
    JOIN users u ON u.id = t.user_id
    WHERE t.id = ? AND u.is_active = 1
    LIMIT 1
');
$stmt->execute([$tailorId]);
$tailor = $stmt->fetch();

if (!$tailor) Response::notFound('Tailor not found.');

$tailor['rating']            = (float)$tailor['rating'];
$tailor['is_available']      = (bool)$tailor['is_available'];
$tailor['is_verified_tailor'] = (bool)$tailor['is_verified_tailor'];
$tailor['profile_photo']     = $tailor['profile_photo'] ? UPLOAD_URL . 'profiles/' . $tailor['profile_photo'] : null;

Response::success($tailor);
