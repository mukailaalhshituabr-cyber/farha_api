<?php
// GET  /farha_api/conversations.php   — list my conversations
// POST /farha_api/conversations.php   — start or get a conversation
require_once __DIR__ . '/config.php';

$payload = Auth::require();
$db      = Database::connect();
$userId  = $payload['user_id'];

// Helper: return a photo URL as-is if it's already absolute,
// otherwise prepend the local upload base path.
function resolvePhoto(?string $url): ?string {
    if (!$url) return null;
    return str_starts_with($url, 'http') ? $url : UPLOAD_URL . 'profiles/' . $url;
}

// ── GET ──────────────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    if ($payload['user_type'] === 'customer') {
        $custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
        $custStmt->execute([$userId]);
        $profile = $custStmt->fetch();
        if (!$profile) Response::notFound();

        $stmt = $db->prepare("
            SELECT
                cv.id,
                cv.customer_id,
                cv.tailor_id,
                cv.order_id,
                CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
                cu.profile_photo                          AS customer_photo,
                CONCAT(tu.first_name, ' ', tu.last_name) AS tailor_name,
                t.shop_name,
                tu.profile_photo                          AS tailor_photo,
                o.reference_number                        AS order_ref,
                cv.last_message_at,
                (SELECT m.message_text
                 FROM   messages m
                 WHERE  m.conversation_id = cv.id
                 ORDER  BY m.created_at DESC
                 LIMIT  1)                                AS last_message,
                (SELECT COUNT(*)
                 FROM   messages m
                 WHERE  m.conversation_id = cv.id
                   AND  m.is_read   = 0
                   AND  m.sender_id != ?)                 AS customer_unread
            FROM  conversations cv
            JOIN  tailors   t   ON t.id   = cv.tailor_id
            JOIN  users     tu  ON tu.id  = t.user_id
            JOIN  customers c   ON c.id   = cv.customer_id
            JOIN  users     cu  ON cu.id  = c.user_id
            LEFT  JOIN orders o ON o.id   = cv.order_id
            WHERE cv.customer_id = ?
            ORDER BY COALESCE(cv.last_message_at, cv.created_at) DESC
        ");
        $stmt->execute([$userId, $profile['id']]);

    } else {
        $tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
        $tailorStmt->execute([$userId]);
        $profile = $tailorStmt->fetch();
        if (!$profile) Response::notFound();

        $stmt = $db->prepare("
            SELECT
                cv.id,
                cv.customer_id,
                cv.tailor_id,
                cv.order_id,
                CONCAT(cu.first_name, ' ', cu.last_name) AS customer_name,
                cu.profile_photo                          AS customer_photo,
                CONCAT(tu.first_name, ' ', tu.last_name) AS tailor_name,
                t.shop_name,
                tu.profile_photo                          AS tailor_photo,
                o.reference_number                        AS order_ref,
                cv.last_message_at,
                (SELECT m.message_text
                 FROM   messages m
                 WHERE  m.conversation_id = cv.id
                 ORDER  BY m.created_at DESC
                 LIMIT  1)                                AS last_message,
                (SELECT COUNT(*)
                 FROM   messages m
                 WHERE  m.conversation_id = cv.id
                   AND  m.is_read   = 0
                   AND  m.sender_id != ?)                 AS tailor_unread
            FROM  conversations cv
            JOIN  tailors   t   ON t.id   = cv.tailor_id
            JOIN  users     tu  ON tu.id  = t.user_id
            JOIN  customers c   ON c.id   = cv.customer_id
            JOIN  users     cu  ON cu.id  = c.user_id
            LEFT  JOIN orders o ON o.id   = cv.order_id
            WHERE cv.tailor_id = ?
            ORDER BY COALESCE(cv.last_message_at, cv.created_at) DESC
        ");
        $stmt->execute([$userId, $profile['id']]);
    }

    $convs = $stmt->fetchAll();
    foreach ($convs as &$c) {
        $c['customer_photo'] = resolvePhoto($c['customer_photo']);
        $c['tailor_photo']   = resolvePhoto($c['tailor_photo']);
        $c['customer_unread'] = (int)($c['customer_unread'] ?? 0);
        $c['tailor_unread']   = (int)($c['tailor_unread']   ?? 0);
    }
    Response::success(['conversations' => $convs]);
}

// ── POST: start or return existing conversation ───────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $body = getBody();

    $customerId = null;
    $tailorId   = null;

    if ($payload['user_type'] === 'customer') {
        // Customer provides the tailor's tailors.id
        $v = (new Validator($body))->required('tailor_id', 'Tailor');
        if ($v->fails()) Response::validationError($v->errors());

        $custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
        $custStmt->execute([$userId]);
        $customer = $custStmt->fetch();
        if (!$customer) Response::forbidden('Customer profile not found.');

        $customerId = $customer['id'];
        $tailorId   = $v->get('tailor_id');

    } else {
        // Tailor provides the customer's customers.id
        $v = (new Validator($body))->required('customer_id', 'Customer');
        if ($v->fails()) Response::validationError($v->errors());

        $tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
        $tailorStmt->execute([$userId]);
        $tailor = $tailorStmt->fetch();
        if (!$tailor) Response::forbidden('Tailor profile not found.');

        $tailorId   = $tailor['id'];
        $customerId = $v->get('customer_id');
    }

    $existing = $db->prepare(
        'SELECT id FROM conversations WHERE customer_id = ? AND tailor_id = ? LIMIT 1');
    $existing->execute([$customerId, $tailorId]);
    $conv = $existing->fetch();

    if (!$conv) {
        $convId = generateUuid();
        $db->prepare('INSERT INTO conversations (id, customer_id, tailor_id) VALUES (?, ?, ?)')
           ->execute([$convId, $customerId, $tailorId]);
    } else {
        $convId = $conv['id'];
    }

    Response::success(['conversation_id' => $convId], 'Conversation ready.');
}

Response::error('Method not allowed.', 405);
