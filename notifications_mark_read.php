<?php
// PUT /farha_api/notifications_mark_read.php
// Body: { "notification_id": "..." } or {} to mark ALL as read
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$body    = getBody();
$db      = Database::connect();

if (!empty($body['notification_id'])) {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')
       ->execute([$body['notification_id'], $payload['user_id']]);
} else {
    $db->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')
       ->execute([$payload['user_id']]);
}

Response::success(null, 'Notification(s) marked as read.');
