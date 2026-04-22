<?php
// POST /farha_api/forgot_password.php  —  sends OTP to user's email
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

Auth::checkRateLimit('forgot_password', 3, 3600);

$body = getBody();
$v    = (new Validator($body))->required('email')->email('email');
if ($v->fails()) Response::validationError($v->errors());

$email = strtolower(trim($v->get('email')));
$db    = Database::connect();

$stmt = $db->prepare('SELECT id, first_name, last_name, is_active, language FROM users WHERE LOWER(TRIM(email)) = ? LIMIT 1');
$stmt->execute([$email]);
$user = $stmt->fetch();

// Never reveal whether an email exists
if (!$user || !$user['is_active']) {
    Response::success(null, 'If that email address exists in our system, you will receive a reset code shortly.');
}

// Invalidate any existing OTPs
$db->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
   ->execute([$user['id']]);

$otp       = Auth::generateOTP();
$expiresAt = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
$db->prepare('INSERT INTO password_resets (user_id, otp_code, expires_at) VALUES (?, ?, ?)')
   ->execute([$user['id'], $otp, $expiresAt]);

$fullName = $user['first_name'] . ' ' . $user['last_name'];

// Send OTP email BEFORE responding — Response::success() calls exit() so anything after never runs
$emailSent = Mailer::sendOTP($email, $fullName, $otp, $user['language'] ?? 'en');

Response::success(
    ['email' => $email, 'expires_in_minutes' => OTP_EXPIRY_MINUTES],
    'A 6-digit reset code has been sent to your email. It expires in ' . OTP_EXPIRY_MINUTES . ' minutes.'
);
