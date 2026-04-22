<?php
// GET /farha_api/measurements_list.php
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

$payload = Auth::requireCustomer();
$db      = Database::connect();

$custStmt = $db->prepare('SELECT id FROM customers WHERE user_id = ? LIMIT 1');
$custStmt->execute([$payload['user_id']]);
$customer = $custStmt->fetch();
if (!$customer) Response::notFound('Customer profile not found.');

$stmt = $db->prepare('
    SELECT m.id, ? AS user_id, m.profile_name, m.garment_type,
           m.chest, m.waist, m.hips, m.shoulder_width,
           m.sleeve_length, m.total_length, m.neck, m.armhole,
           m.unit, m.notes, m.created_at, m.updated_at
    FROM measurements m
    WHERE m.customer_id = ?
    ORDER BY m.updated_at DESC
');
$stmt->execute([$payload['user_id'], $customer['id']]);
$measurements = $stmt->fetchAll();

foreach ($measurements as &$m) {
    foreach (['chest','waist','hips','shoulder_width','sleeve_length','total_length','neck','armhole'] as $f) {
        if ($m[$f] !== null) $m[$f] = (float)$m[$f];
    }
}

Response::success(['items' => $measurements, 'total' => count($measurements)]);
