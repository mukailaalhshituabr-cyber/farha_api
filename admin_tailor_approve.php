<?php
// POST /farha_api/admin_tailor_approve.php
// { tailor_id, action: approve|reject|feature|unfeature, reason? }
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$adminPayload = Auth::requireAdmin();
$body = getBody();
$db   = Database::connect();

$v = (new Validator($body))
    ->required('tailor_id', 'Tailor')
    ->required('action', 'Action')
    ->inList('action', ['approve','reject','feature','unfeature'], 'Action');
if ($v->fails()) Response::validationError($v->errors());

$stmt = $db->prepare('
    SELECT t.id, t.user_id, t.status, u.first_name, u.last_name, u.email
    FROM   tailors t JOIN users u ON u.id = t.user_id
    WHERE  t.id = ? LIMIT 1
');
$stmt->execute([$v->get('tailor_id')]);
$tailor = $stmt->fetch();
if (!$tailor) Response::notFound('Tailor not found.');

$action = $v->get('action');

if ($action === 'approve') {
    $db->prepare("UPDATE tailors SET status = 'approved', is_verified_tailor = 1 WHERE id = ?")
       ->execute([$tailor['id']]);
    $db->prepare("UPDATE users SET is_active = 1 WHERE id = ?")
       ->execute([$tailor['user_id']]);
    // Notify the tailor
    $db->prepare("
        INSERT INTO notifications (id, user_id, title, body, type)
        VALUES (?, ?, 'Account Approved!', 'Congratulations! Your tailor account has been approved. You can now list products and receive orders.', 'account_approved')
    ")->execute([generateUuid(), $tailor['user_id']]);
    Response::success(null, 'Tailor approved successfully.');
}

if ($action === 'reject') {
    $reason = sanitizeString($v->get('reason', 'Your application did not meet our requirements.'));
    $db->prepare("UPDATE tailors SET status = 'rejected' WHERE id = ?")
       ->execute([$tailor['id']]);
    $db->prepare("
        INSERT INTO notifications (id, user_id, title, body, type)
        VALUES (?, ?, 'Application Update', ?, 'account_rejected')
    ")->execute([generateUuid(), $tailor['user_id'], "Your tailor application was not approved. Reason: $reason"]);
    Response::success(null, 'Tailor rejected.');
}

if ($action === 'feature') {
    $db->prepare("UPDATE tailors SET is_featured = 1 WHERE id = ?")
       ->execute([$tailor['id']]);
    Response::success(null, 'Tailor marked as featured.');
}

if ($action === 'unfeature') {
    $db->prepare("UPDATE tailors SET is_featured = 0 WHERE id = ?")
       ->execute([$tailor['id']]);
    Response::success(null, 'Tailor removed from featured.');
}
