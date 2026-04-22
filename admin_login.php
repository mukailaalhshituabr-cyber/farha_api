<?php
// POST /farha_api/admin_login.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

Auth::checkRateLimit('admin_login', 5, 15 * 60);

$body = getBody();
$v    = (new Validator($body))
    ->required('email',    'Email')
    ->email('email')
    ->required('password', 'Password');
if ($v->fails()) Response::validationError($v->errors());

$db   = Database::connect();
$stmt = $db->prepare('SELECT id, name, email, password_hash, role, is_active FROM admins WHERE email = ? LIMIT 1');
$stmt->execute([strtolower(trim($v->get('email')))]);
$admin = $stmt->fetch();

if (!$admin || !Auth::verifyPassword($v->get('password'), $admin['password_hash'])) {
    Response::error('Invalid email or password.', 401);
}
if (!$admin['is_active']) {
    Response::error('This admin account has been deactivated.', 403);
}

$db->prepare('UPDATE admins SET last_login = NOW() WHERE id = ?')->execute([$admin['id']]);

$token = JWT::generateAccessToken([
    'admin_id'  => $admin['id'],
    'user_type' => 'admin',
    'role'      => $admin['role'],
]);

Response::success([
    'token' => $token,
    'admin' => [
        'id'    => $admin['id'],
        'name'  => $admin['name'],
        'email' => $admin['email'],
        'role'  => $admin['role'],
    ],
], 'Login successful.');
