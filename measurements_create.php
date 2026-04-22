<?php
// POST /farha_api/measurements_create.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$body    = getBody();
$db      = Database::connect();

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$measurementId = generateUuid();
$numFields     = ['chest','waist','hips','shoulder_width','sleeve_length','total_length','neck','armhole'];

$db->prepare('
    INSERT INTO measurements (id, customer_id, profile_name, garment_type, chest, waist, hips,
                               shoulder_width, sleeve_length, total_length, neck, armhole, unit, notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
')->execute([
    $measurementId,
    $customer['id'],
    !empty($body['profile_name']) ? sanitizeString($body['profile_name']) : 'My Measurements',
    !empty($body['garment_type']) ? sanitizeString($body['garment_type']) : 'general',
    ...array_map(fn($f) => isset($body[$f]) && is_numeric($body[$f]) ? (float)$body[$f] : null, $numFields),
    !empty($body['unit']) && in_array($body['unit'], ['cm','inches'], true) ? $body['unit'] : 'cm',
    !empty($body['notes']) ? sanitizeString($body['notes']) : null,
]);

Response::success(['measurement_id' => $measurementId], 'Measurements saved successfully.', 201);
