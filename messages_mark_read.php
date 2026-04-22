<?php
// PUT /farha_api/messages_mark_read.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$body    = getBody();
$v       = (new Validator($body))->required('conversation_id', 'Conversation');
if ($v->fails()) Response::validationError($v->errors());

$db     = Database::connect();
$userId = $payload['user_id'];
$convId = $v->get('conversation_id');

$chk = $db->prepare('
    SELECT cv.id FROM conversations cv
    LEFT JOIN customers c ON c.id = cv.customer_id
    LEFT JOIN tailors t   ON t.id = cv.tailor_id
    WHERE cv.id = ? AND (c.user_id = ? OR t.user_id = ?)
    LIMIT 1
');
$chk->execute([$convId, $userId, $userId]);
if (!$chk->fetch()) Response::forbidden('Not part of this conversation.');

$db->prepare('UPDATE messages SET is_read = 1 WHERE conversation_id = ? AND sender_id != ?')
   ->execute([$convId, $userId]);

Response::success(null, 'Messages marked as read.');
