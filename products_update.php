<?php
// PUT /farha_api/products_update.php?id=PRODUCT_ID
require_once __DIR__ . '/config.php';

if (!in_array($_SERVER['REQUEST_METHOD'], ['PUT', 'POST'], true)) Response::error('Method not allowed.', 405);

$payload = Auth::requireTailor();
$body    = getBody();
// Accept product id from query string (preferred) or request body
$productId = $_GET['id'] ?? $_GET['product_id'] ?? $body['product_id'] ?? '';
if (empty($productId)) Response::error('Product ID is required.', 400);

$db = Database::connect();

// Confirm ownership
$stmt = $db->prepare('
    SELECT p.id FROM products p
    JOIN tailors t ON t.id = p.tailor_id
    WHERE p.id = ? AND t.user_id = ? LIMIT 1
');
$stmt->execute([$productId, $payload['user_id']]);
if (!$stmt->fetch()) Response::notFound('Product not found or not yours.');

$fields = [];
$params = [];

$allowed = ['name', 'description', 'base_price', 'stock_quantity', 'currency',
            'allows_custom', 'is_available', 'is_draft', 'category_id', 'available_sizes'];

foreach ($allowed as $key) {
    if (!isset($body[$key])) continue;
    switch ($key) {
        case 'name':
            $fields[] = 'name = ?';
            $params[] = sanitizeString($body[$key]);
            break;
        case 'description':
            $fields[] = 'description = ?';
            $params[] = sanitizeString($body[$key]);
            break;
        case 'base_price':
        case 'stock_quantity':
            $fields[] = "$key = ?";
            $params[] = (float)$body[$key];
            break;
        case 'allows_custom':
        case 'is_available':
        case 'is_draft':
            $fields[] = "$key = ?";
            $params[] = (int)(bool)$body[$key];
            break;
        case 'available_sizes':
            $fields[] = 'available_sizes = ?';
            $params[] = is_array($body[$key]) ? json_encode($body[$key]) : null;
            break;
        default:
            $fields[] = "$key = ?";
            $params[] = $body[$key];
    }
}

if (!empty($fields)) {
    $params[] = $productId;
    $db->prepare('UPDATE products SET ' . implode(', ', $fields) . ' WHERE id = ?')->execute($params);
}

// Replace all product images if an images array is provided
if (array_key_exists('images', $body) && is_array($body['images'])) {
    $db->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
    if (!empty($body['images'])) {
        $imgStmt = $db->prepare(
            'INSERT INTO product_images (id, product_id, image_url, is_main, sort_order) VALUES (?, ?, ?, ?, ?)'
        );
        foreach (array_values($body['images']) as $i => $url) {
            if (!empty($url)) {
                $imgStmt->execute([generateUuid(), $productId, sanitizeString($url), $i === 0 ? 1 : 0, $i]);
            }
        }
    }
}

if (empty($fields) && !array_key_exists('images', $body)) {
    Response::error('No fields to update.', 400);
}

Response::success(null, 'Product updated successfully.');
