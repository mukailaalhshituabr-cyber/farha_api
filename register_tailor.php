<?php
// POST /farha_api/register_tailor.php
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
    ->inList('gender', ['female','male','other','prefer_not_to_say'], 'Gender')
    ->required('password')
    ->strongPassword('password')
    ->required('password_confirmation', 'Password confirmation')
    ->confirmed('password')
    ->required('language')
    ->inList('language', ['en','fr'], 'Language')
    ->required('shop_name', 'Shop/Business name')
    ->minLength('shop_name', 2, 'Shop name')
    ->maxLength('shop_name', 200, 'Shop name')
    ->required('shop_location', 'Shop location')
    ->required('years_experience', 'Years of experience')
    ->numeric('years_experience', 'Years of experience')
    ->min('years_experience', 0, 'Years of experience')
    ->required('experience_level', 'Experience level')
    ->inList('experience_level', ['apprentice','intermediate','master','grandmaster'], 'Experience level');

if ($v->fails()) Response::validationError($v->errors());

if (empty($body['terms_accepted']) || $body['terms_accepted'] !== true) {
    Response::validationError(['terms_accepted' => 'You must accept the Terms of Service and Privacy Policy.']);
}

try {
    $db->beginTransaction();

    $userId = generateUuid();
    $db->prepare('
        INSERT INTO users (id, email, phone, password_hash, user_type, first_name, last_name, language, is_verified)
        VALUES (?, ?, ?, ?, "tailor", ?, ?, ?, 0)
    ')->execute([
        $userId,
        strtolower($v->get('email')),
        $v->get('phone'),
        Auth::hashPassword($v->get('password')),
        sanitizeString($v->get('first_name')),
        sanitizeString($v->get('last_name')),
        $v->get('language'),
    ]);

    $tailorId = generateUuid();
    $db->prepare('
        INSERT INTO tailors (id, user_id, shop_name, gender, shop_location, years_experience, experience_level, bio)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ')->execute([
        $tailorId,
        $userId,
        sanitizeString($v->get('shop_name')),
        $v->get('gender'),
        sanitizeString($v->get('shop_location')),
        (int)$v->get('years_experience'),
        $v->get('experience_level'),
        isset($body['bio']) ? sanitizeString($body['bio']) : null,
    ]);

    $token     = Auth::generateSecureToken(32);
    $expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFY_EXPIRY * 3600);
    $db->prepare('INSERT INTO email_verifications (id, user_id, token, expires_at) VALUES (?, ?, ?, ?)')
       ->execute([generateUuid(), $userId, $token, $expiresAt]);

    $db->commit();
} catch (PDOException $e) {
    $db->rollBack();
    error_log('Register tailor error: ' . $e->getMessage());
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
    'Tailor account created! Please verify your email to start listing your work.',
    201
);
