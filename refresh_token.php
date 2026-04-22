<?php
// POST /farha_api/refresh_token.php  —  issues new access token from valid refresh token
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$body = getBody();
$v    = (new Validator($body))->required('refresh_token', 'Refresh token');
if ($v->fails()) Response::validationError($v->errors());

$refreshToken = $v->get('refresh_token');
$db           = Database::connect();

try {
    $payload = JWT::decode($refreshToken);
} catch (RuntimeException $e) {
    Response::unauthorized('Refresh token is invalid or expired. Please log in again.');
}

if (($payload['type'] ?? '') !== 'refresh') Response::unauthorized('Invalid token type.');

if (!JWT::validateRefreshToken($refreshToken, $db)) {
    Response::unauthorized('Session has been revoked. Please log in again.');
}

$stmt = $db->prepare('SELECT id, user_type, email, is_active, is_verified FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$payload['user_id']]);
$user = $stmt->fetch();

if (!$user || !$user['is_active']) Response::unauthorized('Account not found or deactivated.');

$newPayload  = ['user_id' => $user['id'], 'user_type' => $user['user_type'], 'email' => $user['email']];
$accessToken = JWT::generateAccessToken($newPayload);

Response::success([
    'access_token' => $accessToken,
    'token_type'   => 'Bearer',
    'expires_in'   => JWT_ACCESS_EXPIRY,
], 'Token refreshed successfully.');
