<?php
// POST /farha_api/admin_manage.php
// Create a new admin account — super_admin only.
require_once __DIR__ . '/config.php';

$payload = Auth::requireAdmin();

if (($payload['role'] ?? '') !== 'super_admin') {
    Response::forbidden('Only super admins can create new admin accounts.');
} 

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$body = getBody();
$v = (new Validator($body))
    ->required('name',     'Name')
    ->required('email',    'Email')
    ->email('email')
    ->required('password', 'Password')
    ->strongPassword('password');
if ($v->fails()) Response::validationError($v->errors());

$role = in_array($body['role'] ?? '', ['super_admin', 'moderator'], true)
    ? $body['role']
    : 'moderator';

$db   = Database::connect();
$stmt = $db->prepare('SELECT id FROM admins WHERE email = ? LIMIT 1');
$stmt->execute([strtolower($v->get('email'))]);
if ($stmt->fetch()) Response::error('An admin with this email already exists.', 409);

$id   = generateUuid();
$hash = Auth::hashPassword($v->get('password'));

$db->prepare(
    'INSERT INTO admins (id, name, email, password_hash, role, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, 1, NOW())'
)->execute([$id, $v->get('name'), strtolower($v->get('email')), $hash, $role]);

Response::success(['id' => $id, 'role' => $role], 'Admin account created successfully.', 201);
