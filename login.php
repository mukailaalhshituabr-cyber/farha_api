<?php
// POST /farha_api/login.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

Auth::checkRateLimit('login', RATE_LIMIT_LOGIN, RATE_LIMIT_WINDOW);

$body = getBody();
$v    = (new Validator($body))
    ->required('identifier', 'Email or phone')
    ->required('password');

if ($v->fails()) Response::validationError($v->errors());

$identifier = trim($v->get('identifier'));
$password   = $v->get('password');
$db         = Database::connect();

// Normalise the identifier for phone lookup
$emailId  = strtolower(trim($identifier));
$rawPhone = preg_replace('/[\s\-\(\)\.\/]+/', '', $identifier);

// Convert 00-prefix to + (e.g. 00233... → +233...)
if (preg_match('/^00[1-9]/', $rawPhone)) {
    $rawPhone = '+' . substr($rawPhone, 2);
}

// Detect local (leading-zero) format: 0XXXXXXX — valid in many countries
$isLocalPhone = (bool)preg_match('/^0\d{7,11}$/', $rawPhone);
$phoneSuffix  = $isLocalPhone ? substr($rawPhone, 1) : null;

if ($isLocalPhone && $phoneSuffix !== null) {
    $suffixLen = strlen($phoneSuffix);
    // Match exact OR match stored number whose last N digits equal the local suffix
    $stmt = $db->prepare('
        SELECT id, first_name, last_name, email, phone, password_hash,
               user_type, is_verified, is_active, language, profile_photo
        FROM users
        WHERE LOWER(TRIM(email)) = ?
           OR REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "(", ""), ")", "") = ?
           OR RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "(", ""), ")", ""), ?) = ?
        LIMIT 1
    ');
    $stmt->execute([$emailId, $rawPhone, $suffixLen, $phoneSuffix]);
} else {
    $stmt = $db->prepare('
        SELECT id, first_name, last_name, email, phone, password_hash,
               user_type, is_verified, is_active, language, profile_photo
        FROM users
        WHERE LOWER(TRIM(email)) = ?
           OR REPLACE(REPLACE(REPLACE(REPLACE(phone, " ", ""), "-", ""), "(", ""), ")", "") = ?
        LIMIT 1
    ');
    $stmt->execute([$emailId, $rawPhone]);
}
$user = $stmt->fetch();

$dummyHash = '$2y$12$invaliddummyhashtopreventuserenum';

if (!$user) {
    password_verify($password, $dummyHash);
    Response::error('Incorrect email/phone or password.', 401);
}

if (!Auth::verifyPassword($password, $user['password_hash'] ?? $dummyHash)) {
    Response::error('Incorrect email/phone or password.', 401);
}

if (!$user['is_active']) {
    Response::error('Your account has been deactivated. Please contact support.', 403);
}

// Auto-verify for development — remove before final production
if (!$user['is_verified']) {
    $db->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$user['id']]);
    $user['is_verified'] = 1;
}

$tokenPayload = [
    'user_id'   => $user['id'],
    'user_type' => $user['user_type'],
    'email'     => $user['email'],
];

$accessToken  = JWT::generateAccessToken($tokenPayload);
$refreshToken = JWT::generateRefreshToken($tokenPayload);
JWT::storeRefreshToken($user['id'], $refreshToken, $db);

$db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);

if (!empty($body['fcm_token'])) {
    $db->prepare('UPDATE users SET fcm_token = ? WHERE id = ?')
       ->execute([sanitizeString($body['fcm_token']), $user['id']]);
}

$profile = null;
if ($user['user_type'] === 'customer') {
    $stmt = $db->prepare('SELECT id, gender FROM customers WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
} elseif ($user['user_type'] === 'tailor') {
    $stmt = $db->prepare('SELECT id, shop_name, experience_level, rating, is_verified_tailor FROM tailors WHERE user_id = ?');
    $stmt->execute([$user['id']]);
    $profile = $stmt->fetch();
}

Response::success([
    'access_token'  => $accessToken,
    'refresh_token' => $refreshToken,
    'token_type'    => 'Bearer',
    'expires_in'    => JWT_ACCESS_EXPIRY,
    'user' => [
        'id'            => $user['id'],
        'first_name'    => $user['first_name'],
        'last_name'     => $user['last_name'],
        'full_name'     => $user['first_name'] . ' ' . $user['last_name'],
        'email'         => $user['email'],
        'phone'         => $user['phone'],
        'user_type'     => $user['user_type'],
        'language'      => $user['language'],
        'profile_photo' => $user['profile_photo'] ? UPLOAD_URL . 'profiles/' . $user['profile_photo'] : null,
        'profile'       => $profile,
    ],
], 'Login successful. Welcome back to Farha!');
