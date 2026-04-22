<?php
// GET /farha_api/profile.php   — get own profile
// PUT /farha_api/profile.php   — update profile fields
require_once __DIR__ . '/config.php';

$payload = Auth::require();
$db      = Database::connect();
$userId  = $payload['user_id'];

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('
        SELECT id, first_name, last_name, email, phone, user_type, language,
               profile_photo, is_verified, is_active, created_at
        FROM users WHERE id = ? LIMIT 1
    ');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();

    if (!$user) Response::notFound('User not found.');

    $profile = null;
    if ($user['user_type'] === 'customer') {
        $stmt = $db->prepare('SELECT id, gender FROM customers WHERE user_id = ?');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
    } elseif ($user['user_type'] === 'tailor') {
        $stmt = $db->prepare('
            SELECT id, shop_name, bio, shop_location, years_experience,
                   experience_level, is_available, is_verified_tailor,
                   rating, total_reviews, total_orders, gender
            FROM tailors WHERE user_id = ?
        ');
        $stmt->execute([$userId]);
        $profile = $stmt->fetch();
    }

    Response::success([
        'id'            => $user['id'],
        'first_name'    => $user['first_name'],
        'last_name'     => $user['last_name'],
        'full_name'     => $user['first_name'] . ' ' . $user['last_name'],
        'email'         => $user['email'],
        'phone'         => $user['phone'],
        'user_type'     => $user['user_type'],
        'language'      => $user['language'],
        'profile_photo' => $user['profile_photo'] ? UPLOAD_URL . 'profiles/' . $user['profile_photo'] : null,
        'is_verified'   => (bool)$user['is_verified'],
        'profile'       => $profile,
        'created_at'    => $user['created_at'],
    ]);
}

// ── PUT or POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();

    // Build update for users table
    $fields = [];
    $params = [];

    if (!empty($body['first_name'])) {
        $fields[] = 'first_name = ?';
        $params[] = sanitizeString($body['first_name']);
    }
    if (!empty($body['last_name'])) {
        $fields[] = 'last_name = ?';
        $params[] = sanitizeString($body['last_name']);
    }
    if (!empty($body['phone'])) {
        $v = (new Validator($body))->phone('phone');
        if ($v->fails()) Response::validationError($v->errors());
        // Check uniqueness excluding current user
        $chk = $db->prepare('SELECT id FROM users WHERE phone = ? AND id != ? LIMIT 1');
        $chk->execute([$body['phone'], $userId]);
        if ($chk->fetch()) Response::error('Phone number is already in use.', 422);
        $fields[] = 'phone = ?';
        $params[] = $body['phone'];
    }
    if (!empty($body['language']) && in_array($body['language'], ['en','fr'], true)) {
        $fields[] = 'language = ?';
        $params[] = $body['language'];
    }

    if (!empty($fields)) {
        $params[] = $userId;
        $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }

    // Tailor-specific fields
    if ($payload['user_type'] === 'tailor') {
        $tFields = [];
        $tParams = [];
        if (!empty($body['shop_name'])) { $tFields[] = 'shop_name = ?'; $tParams[] = sanitizeString($body['shop_name']); }
        if (!empty($body['shop_location'])) { $tFields[] = 'shop_location = ?'; $tParams[] = sanitizeString($body['shop_location']); }
        if (isset($body['bio'])) { $tFields[] = 'bio = ?'; $tParams[] = sanitizeString($body['bio']); }
        if (isset($body['is_available'])) { $tFields[] = 'is_available = ?'; $tParams[] = (int)(bool)$body['is_available']; }

        if (!empty($tFields)) {
            $tParams[] = $userId;
            $db->prepare('UPDATE tailors SET ' . implode(', ', $tFields) . ' WHERE user_id = ?')->execute($tParams);
        }
    }

    Response::success(null, 'Profile updated successfully.');
}

Response::error('Method not allowed.', 405);
