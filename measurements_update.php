<?php
// POST /farha_api/measurements_update.php?id=MEASUREMENT_ID
require_once __DIR__ . '/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT'], true)) Response::error('Method not allowed.', 405);

$payload       = Auth::requireCustomer();
$measurementId = $_GET['id'] ?? '';
if (empty($measurementId)) Response::error('Measurement ID is required.', 400);

$db       = Database::connect();
$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$chk = $db->prepare('SELECT id FROM measurements WHERE id = ? AND customer_id = ? LIMIT 1');
$chk->execute([$measurementId, $customer['id']]);
if (!$chk->fetch()) Response::notFound('Measurement not found or not yours.');

$body      = getBody();
$fields    = [];
$params    = [];
$numFields = ['chest','waist','hips','shoulder_width','sleeve_length','total_length','neck','armhole'];

if (!empty($body['profile_name']))  { $fields[] = 'profile_name = ?'; $params[] = sanitizeString($body['profile_name']); }
if (!empty($body['garment_type']))  { $fields[] = 'garment_type = ?'; $params[] = sanitizeString($body['garment_type']); }
if (!empty($body['unit']) && in_array($body['unit'], ['cm','inches'], true)) { $fields[] = 'unit = ?'; $params[] = $body['unit']; }
if (isset($body['notes']))          { $fields[] = 'notes = ?'; $params[] = sanitizeString($body['notes']); }

foreach ($numFields as $f) {
    if (isset($body[$f])) {
        $fields[] = "$f = ?";
        $params[] = is_numeric($body[$f]) ? (float)$body[$f] : null;
    }
}

if (empty($fields)) Response::error('No fields to update.', 400);

$params[] = $measurementId;
$db->prepare('UPDATE measurements SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);

Response::success(null, 'Measurements updated successfully.');
