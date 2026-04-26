<?php
// POST /farha_api/upload_product_image.php  —  upload a product image (tailor only)
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::requireTailor();

if (empty($_FILES['photo'])) Response::error('No photo file uploaded.', 400);

$file  = $_FILES['photo'];
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);
 
if (!in_array($mime, ALLOWED_IMG_TYPES, true)) {
    Response::error('Invalid file type. Allowed: JPEG, PNG, WebP.', 422);
}
if ($file['size'] > MAX_IMAGE_SIZE) {
    Response::error('File too large. Maximum size is 5 MB.', 422);
}

$uploadDir = UPLOAD_PATH . 'products/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        Response::error('Upload directory could not be created. Please create farha_api/uploads/products/ on the server.', 500);
    }
}
if (!is_writable($uploadDir)) {
    Response::error('Upload directory is not writable. Run: chmod 755 farha_api/uploads/products on the server.', 500);
}

$ext      = match($mime) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$filename = $payload['user_id'] . '_' . uniqid('', true) . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    Response::error('Failed to save image. Please try again.', 500);
}

Response::success(
    ['image_url' => UPLOAD_URL . 'products/' . $filename],
    'Product image uploaded successfully.'
);
