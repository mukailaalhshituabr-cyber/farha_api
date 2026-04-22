<?php
// POST /farha_api/products_create.php  —  tailor creates a product
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireTailor();
$body    = getBody();
$db      = Database::connect();

$v = (new Validator($body))
    ->required('name', 'Product name')
    ->maxLength('name', 255, 'Product name')
    ->required('base_price', 'Base price')
    ->numeric('base_price', 'Base price')
    ->min('base_price', 0.01, 'Base price')
    ->required('stock_quantity', 'Stock quantity')
    ->numeric('stock_quantity', 'Stock quantity')
    ->min('stock_quantity', 0, 'Stock quantity');

if ($v->fails()) Response::validationError($v->errors());

// Get tailor record
$tailorStmt = $db->prepare('SELECT id FROM tailors WHERE user_id = ? LIMIT 1');
$tailorStmt->execute([$payload['user_id']]);
$tailor = $tailorStmt->fetch();
if (!$tailor) Response::forbidden('Tailor profile not found.');

// Resolve category — use provided id, or fall back to first available category
$categoryId = $body['category_id'] ?? null;
if ($categoryId) {
    $catStmt = $db->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $catStmt->execute([$categoryId]);
    if (!$catStmt->fetch()) $categoryId = null;
}
if (!$categoryId) {
    $catStmt = $db->prepare('SELECT id FROM categories ORDER BY sort_order ASC LIMIT 1');
    $catStmt->execute();
    $cat = $catStmt->fetch();
    $categoryId = $cat ? $cat['id'] : null;
}
if (!$categoryId) Response::error('No categories found. Please seed the database.', 500);

$productId      = generateUuid();
$availableSizes = !empty($body['available_sizes']) && is_array($body['available_sizes'])
    ? json_encode($body['available_sizes'])
    : null;

$db->prepare('
    INSERT INTO products (id, tailor_id, category_id, name, description, base_price,
                          currency, stock_quantity, allows_custom, is_available, is_draft, available_sizes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
')->execute([
    $productId,
    $tailor['id'],
    $categoryId,
    sanitizeString($v->get('name')),
    isset($body['description']) ? sanitizeString($body['description']) : null,
    (float)$v->get('base_price'),
    $body['currency'] ?? 'CFA',
    (int)$v->get('stock_quantity'),
    isset($body['allows_custom']) ? (int)(bool)$body['allows_custom'] : 0,
    isset($body['is_available'])  ? (int)(bool)$body['is_available']  : 1,
    isset($body['is_draft'])      ? (int)(bool)$body['is_draft']      : 0,
    $availableSizes,
]);

// Insert product images
if (!empty($body['images']) && is_array($body['images'])) {
    $imgStmt = $db->prepare(
        'INSERT INTO product_images (id, product_id, image_url, is_main, sort_order) VALUES (?, ?, ?, ?, ?)'
    );
    foreach (array_values($body['images']) as $i => $url) {
        if (!empty($url)) {
            $imgStmt->execute([generateUuid(), $productId, sanitizeString($url), $i === 0 ? 1 : 0, $i]);
        }
    }
}

Response::success(
    ['product_id' => $productId, 'category_id' => $categoryId],
    'Product created successfully.',
    201
);
