<?php
// GET  /farha_api/messages.php?conversation_id=ID  — load messages
// POST /farha_api/messages.php                     — send a message
require_once __DIR__ . '/config.php';

$payload = Auth::require();
$db      = Database::connect();
$userId  = $payload['user_id'];

/** Verify the current user is a participant of the conversation */
function canAccessConversation(PDO $db, string $convId, string $userId): bool {
    $stmt = $db->prepare("
        SELECT cv.id
        FROM   conversations cv
        LEFT   JOIN customers c ON c.id   = cv.customer_id
        LEFT   JOIN tailors   t ON t.id   = cv.tailor_id
        WHERE  cv.id = ?
          AND  (c.user_id = ? OR t.user_id = ?)
        LIMIT  1
    ");
    $stmt->execute([$convId, $userId, $userId]);
    return (bool) $stmt->fetch();
}

// ── GET ───────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $conversationId = $_GET['conversation_id'] ?? '';
    if (empty($conversationId)) Response::error('conversation_id is required.', 400);

    if (!canAccessConversation($db, $conversationId, $userId)) {
        Response::forbidden('Not part of this conversation.');
    }

    $page   = max(1, (int)($_GET['page']  ?? 1));
    $limit  = min(100, max(1, (int)($_GET['limit'] ?? 50)));
    $offset = ($page - 1) * $limit;

    $stmt = $db->prepare("
        SELECT m.id,
               m.conversation_id,
               m.sender_id,
               m.message_text,
               m.is_read,
               m.created_at
        FROM   messages m
        WHERE  m.conversation_id = ?
        ORDER  BY m.created_at ASC
        LIMIT  ? OFFSET ?
    ");
    $stmt->execute([$conversationId, $limit, $offset]);
    $msgs = $stmt->fetchAll();

    foreach ($msgs as &$m) {
        $m['is_read'] = (bool) $m['is_read'];
    }

    $countStmt = $db->prepare("SELECT COUNT(*) FROM messages WHERE conversation_id = ?");
    $countStmt->execute([$conversationId]);
    $total = (int) $countStmt->fetchColumn();

    // Mark messages from the other party as read
    $db->prepare("
        UPDATE messages
        SET    is_read = 1
        WHERE  conversation_id = ?
          AND  sender_id       != ?
          AND  is_read         = 0
    ")->execute([$conversationId, $userId]);

    Response::success([
        'messages' => $msgs,
        'has_more' => ($offset + count($msgs)) < $total,
        'total'    => $total,
        'page'     => $page,
    ]);
}

// ── POST: send message ────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();
    $v    = (new Validator($body))
        ->required('conversation_id', 'Conversation')
        ->required('message_text', 'Message');
    if ($v->fails()) Response::validationError($v->errors());

    $convId = $v->get('conversation_id');

    if (!canAccessConversation($db, $convId, $userId)) {
        Response::forbidden('Not part of this conversation.');
    }

    $msgId = generateUuid();
    $db->prepare('
        INSERT INTO messages (id, conversation_id, sender_id, message_text)
        VALUES (?, ?, ?, ?)
    ')->execute([$msgId, $convId, $userId, sanitizeString($v->get('message_text'))]);

    $db->prepare('
        UPDATE conversations
        SET    last_message_at = NOW()
        WHERE  id = ?
    ')->execute([$convId]);

    // Return the created message so the client can display it immediately
    $newMsg = $db->prepare('
        SELECT id, conversation_id, sender_id, message_text, is_read, created_at
        FROM   messages WHERE id = ? LIMIT 1
    ');
    $newMsg->execute([$msgId]);
    $msg = $newMsg->fetch();
    $msg['is_read'] = (bool) $msg['is_read'];

    Response::success(['message' => $msg], 'Message sent.', 201);
}

Response::error('Method not allowed.', 405);
