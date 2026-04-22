<?php
// DELETE /farha_api/products_delete.php?id=PRODUCT_ID
require_once __DIR__ . '/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['DELETE', 'POST'], true)) Response::error('Method not allowed.', 405);

$payload   = Auth::requireTailor();
$productId = $_GET['id'] ?? $_GET['product_id'] ?? '';
if (empty($productId)) Response::error('Product ID is required.', 400);

$db   = Database::connect();
$stmt = $db->prepare('
    SELECT p.id FROM products p
    JOIN tailors t ON t.id = p.tailor_id
    WHERE p.id = ? AND t.user_id = ? LIMIT 1
');
$stmt->execute([$productId, $payload['user_id']]);
if (!$stmt->fetch()) Response::notFound('Product not found or not yours.');

$db->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);

Response::success(null, 'Product deleted successfully.');
