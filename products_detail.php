<?php
// GET /farha_api/products_detail.php?id=PRODUCT_ID
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') Response::error('Method not allowed.', 405);

Auth::require();

$productId = $_GET['product_id'] ?? $_GET['id'] ?? '';
if (empty($productId)) Response::error('Product ID is required.', 400);

$db   = Database::connect();
$stmt = $db->prepare("
    SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) AS tailor_name,
           c.name_en AS category_name
    FROM products p
    JOIN tailors t ON t.id = p.tailor_id
    JOIN users u   ON u.id = t.user_id
    JOIN categories c ON c.id = p.category_id
    WHERE p.id = ?
    LIMIT 1
");
$stmt->execute([$productId]);
$product = $stmt->fetch();

if (!$product) Response::notFound('Product not found.');

// Load images
$imgStmt = $db->prepare('SELECT id, image_url, is_main FROM product_images WHERE product_id = ? ORDER BY is_main DESC, sort_order ASC');
$imgStmt->execute([$productId]);
$images = $imgStmt->fetchAll();
foreach ($images as &$img) $img['is_main'] = (bool)$img['is_main'];

$product['images']          = $images;
$product['available_sizes'] = $product['available_sizes'] ? json_decode($product['available_sizes'], true) : [];
$product['base_price']      = (float)$product['base_price'];
$product['rating']          = (float)$product['rating'];
$product['allows_custom']   = (bool)$product['allows_custom'];
$product['is_available']    = (bool)$product['is_available'];
$product['is_draft']        = (bool)$product['is_draft'];

Response::success($product);
