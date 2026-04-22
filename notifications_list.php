<?php
// GET /farha_api/notifications_list.php?page=1&limit=30
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$db      = Database::connect();

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(100, max(1, (int)($_GET['limit'] ?? 30)));
$offset = ($page - 1) * $limit;

$count = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ?');
$count->execute([$payload['user_id']]);
$total = (int)$count->fetchColumn();

$stmt = $db->prepare('
    SELECT id, title, body, type, reference_id, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT ? OFFSET ?
');
$stmt->execute([$payload['user_id'], $limit, $offset]);
$notifications = $stmt->fetchAll();

foreach ($notifications as &$n) $n['is_read'] = (bool)$n['is_read'];

$unread = $db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
$unread->execute([$payload['user_id']]);

Response::success([
    'notifications' => $notifications,
    'unread_count'  => (int)$unread->fetchColumn(),
    'pagination'    => ['total' => $total, 'page' => $page, 'limit' => $limit],
]);
