<?php
// POST /farha_api/verify_otp.php  —  verifies OTP and returns a reset token
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

Auth::checkRateLimit('verify_otp', 10, 900);

$body = getBody();
$v    = (new Validator($body))
    ->required('email')->email('email')
    ->required('otp', 'Reset code')->otp('otp');

if ($v->fails()) Response::validationError($v->errors());

$email = strtolower($v->get('email'));
$otp   = $v->get('otp');
$db    = Database::connect();

$stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) Response::error('Invalid or expired reset code.', 400);

$stmt = $db->prepare('
    SELECT id, attempts, expires_at FROM password_resets
    WHERE user_id = ? AND otp_code = ? AND used_at IS NULL
    ORDER BY created_at DESC LIMIT 1
');
$stmt->execute([$user['id'], $otp]);
$reset = $stmt->fetch();

if (!$reset) Response::error('Invalid reset code. Please check the code or request a new one.', 400);
if ((int)$reset['attempts'] >= OTP_MAX_ATTEMPTS) Response::error('Maximum attempts reached. Please request a new reset code.', 429);
if (strtotime($reset['expires_at']) < time()) Response::error('This reset code has expired. Please request a new one.', 410);

$db->prepare('UPDATE password_resets SET attempts = attempts + 1 WHERE id = ?')->execute([$reset['id']]);

$resetToken = Auth::generateSecureToken(32);
$db->prepare('UPDATE password_resets SET reset_token = ? WHERE id = ?')->execute([$resetToken, $reset['id']]);

Response::success(
    ['reset_token' => $resetToken, 'expires_in_minutes' => 10],
    'Code verified. You may now set a new password.'
);
