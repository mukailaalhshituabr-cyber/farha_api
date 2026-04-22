<?php
// POST /farha_api/reset_password.php  —  sets new password using reset_token from verify_otp
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$body = getBody();
$v    = (new Validator($body))
    ->required('reset_token', 'Reset token')
    ->required('password', 'New password')
    ->strongPassword('password')
    ->required('password_confirmation', 'Password confirmation')
    ->confirmed('password');

if ($v->fails()) Response::validationError($v->errors());

$db    = Database::connect();
$token = $v->get('reset_token');

$stmt = $db->prepare('
    SELECT id, user_id, expires_at
    FROM password_resets
    WHERE reset_token = ? AND used_at IS NULL
    LIMIT 1
');
$stmt->execute([$token]);
$reset = $stmt->fetch();

if (!$reset) Response::error('Invalid or expired reset token. Please start the password reset process again.', 400);
if (strtotime($reset['expires_at']) < time()) Response::error('This reset token has expired. Please request a new code.', 410);

try {
    $db->beginTransaction();

    $db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')
       ->execute([Auth::hashPassword($v->get('password')), $reset['user_id']]);

    // Revoke all sessions so user must log in fresh everywhere
    $db->prepare('UPDATE refresh_tokens SET revoked = 1 WHERE user_id = ?')
       ->execute([$reset['user_id']]);

    $db->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')
       ->execute([$reset['id']]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    error_log('Reset password error: ' . $e->getMessage());
    Response::error('Password reset failed. Please try again.', 500);
}

Response::success(null, 'Password reset successfully. Please log in with your new password.');
