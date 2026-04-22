<?php
// POST /farha_api/logout.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$body    = getBody();
$db      = Database::connect();

if (!empty($body['refresh_token'])) {
    JWT::revokeRefreshToken($body['refresh_token'], $db);
}

$db->prepare('UPDATE users SET fcm_token = NULL WHERE id = ?')->execute([$payload['user_id']]);

Response::success(null, 'Logged out successfully.');
