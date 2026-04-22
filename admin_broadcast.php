<?php
// POST /farha_api/admin_broadcast.php
// { title, body, target: all|customers|tailors|user, target_user_id? }
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$adminPayload = Auth::requireAdmin();
$body = getBody();
$db   = Database::connect();

$v = (new Validator($body))
    ->required('title', 'Title')
    ->required('body',  'Message body')
    ->required('target', 'Target audience')
    ->inList('target', ['all','customers','tailors','user'], 'Target');
if ($v->fails()) Response::validationError($v->errors());

$target       = $v->get('target');
$targetUserId = $v->get('target_user_id');

if ($target === 'user' && empty($targetUserId)) {
    Response::error('target_user_id is required when target is "user".', 422);
}

$title   = sanitizeString($v->get('title'));
$message = sanitizeString($v->get('body'));

// Collect user IDs to notify
$userIds = [];

if ($target === 'user') {
    $stmt = $db->prepare('SELECT id FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$targetUserId]);
    $u = $stmt->fetch();
    if (!$u) Response::notFound('Target user not found.');
    $userIds = [$targetUserId];
} elseif ($target === 'all') {
    $stmt = $db->query('SELECT id FROM users WHERE is_active = 1');
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($target === 'customers') {
    $stmt = $db->query("SELECT id FROM users WHERE user_type='customer' AND is_active=1");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
} elseif ($target === 'tailors') {
    $stmt = $db->query("SELECT id FROM users WHERE user_type='tailor' AND is_active=1");
    $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
}

// Insert notification for each user
$insertStmt = $db->prepare("
    INSERT INTO notifications (id, user_id, title, body, type)
    VALUES (?, ?, ?, ?, 'broadcast')
");

foreach ($userIds as $uid) {
    $insertStmt->execute([generateUuid(), $uid, $title, $message]);
}

// Log the broadcast
$db->prepare('
    INSERT INTO admin_broadcasts (id, title, body, target, target_user_id, sent_by)
    VALUES (?, ?, ?, ?, ?, ?)
')->execute([
    generateUuid(), $title, $message, $target,
    $target === 'user' ? $targetUserId : null,
    $adminPayload['admin_id'],
]);

Response::success([
    'recipients' => count($userIds),
], "Broadcast sent to " . count($userIds) . " users.");
