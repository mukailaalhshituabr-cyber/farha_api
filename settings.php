<?php
// GET /farha_api/settings.php  — get user settings
// PUT /farha_api/settings.php  — update language/notifications
require_once __DIR__ . '/config.php';

$payload = Auth::require();
$db      = Database::connect();
$userId  = $payload['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $stmt = $db->prepare('SELECT language, fcm_token FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $user = $stmt->fetch();
    Response::success([
        'language'      => $user['language'] ?? 'en',
        'notifications' => !empty($user['fcm_token']),
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    $body   = getBody();
    $fields = [];
    $params = [];

    if (!empty($body['language']) && in_array($body['language'], ['en','fr'], true)) {
        $fields[] = 'language = ?';
        $params[] = $body['language'];
    }

    if (!empty($fields)) {
        $params[] = $userId;
        $db->prepare('UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
    }

    Response::success(null, 'Settings updated.');
}

Response::error('Method not allowed.', 405);
