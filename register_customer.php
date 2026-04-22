<?php
// POST /farha_api/register_customer.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

Auth::checkRateLimit('register', RATE_LIMIT_REGISTER, 3600);

$body = getBody();
$db   = Database::connect();

$v = (new Validator($body))
    ->required('first_name', 'First name')
    ->minLength('first_name', 2, 'First name')
    ->maxLength('first_name', 75, 'First name')
    ->required('last_name', 'Last name')
    ->minLength('last_name', 2, 'Last name')
    ->maxLength('last_name', 75, 'Last name')
    ->required('email')
    ->email('email')
    ->uniqueInDb('email', 'users', 'email', $db)
    ->required('phone', 'Phone number')
    ->phone('phone')
    ->uniqueInDb('phone', 'users', 'phone', $db, 'Phone number')
    ->required('gender')
    ->inList('gender', ['male','female','other','prefer_not_to_say'], 'Gender')
    ->required('password')
    ->strongPassword('password')
    ->required('password_confirmation', 'Password confirmation')
    ->confirmed('password')
    ->required('language')
    ->inList('language', ['en','fr'], 'Language');

if ($v->fails()) Response::validationError($v->errors());

if (empty($body['terms_accepted']) || $body['terms_accepted'] !== true) {
    Response::validationError(['terms_accepted' => 'You must accept the Terms of Service and Privacy Policy.']);
}

try {
    $db->beginTransaction();

    $userId = generateUuid();
    $db->prepare('
        INSERT INTO users (id, email, phone, password_hash, user_type, first_name, last_name, language, is_verified)
        VALUES (?, ?, ?, ?, "customer", ?, ?, ?, 0)
    ')->execute([
        $userId,
        strtolower($v->get('email')),
        $v->get('phone'),
        Auth::hashPassword($v->get('password')),
        sanitizeString($v->get('first_name')),
        sanitizeString($v->get('last_name')),
        $v->get('language'),
    ]);

    $customerId = generateUuid();
    $db->prepare('INSERT INTO customers (id, user_id, gender) VALUES (?, ?, ?)')
       ->execute([$customerId, $userId, $v->get('gender')]);

    $token     = Auth::generateSecureToken(32);
    $expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFY_EXPIRY * 3600);
    $db->prepare('INSERT INTO email_verifications (id, user_id, token, expires_at) VALUES (?, ?, ?, ?)')
       ->execute([generateUuid(), $userId, $token, $expiresAt]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    error_log('Register customer error: ' . $e->getMessage());
    Response::error('Registration failed. Please try again.', 500);
}

// Send email BEFORE responding — Response::success() calls exit() so anything after it never runs
$emailSent = Mailer::sendEmailVerification(
    strtolower($v->get('email')),
    sanitizeString($v->get('first_name')),
    $token,
    $v->get('language')
);

Response::success(
    ['user_id' => $userId, 'email_sent' => $emailSent],
    'Registration successful! Please check your email to verify your account.',
    201
);
