<?php
// POST /farha_api/upload_photo.php  —  upload profile photo (multipart/form-data)
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') Response::error('Method not allowed.', 405);

$payload = Auth::require();
$userId  = $payload['user_id'];

// Check PHP's own upload error FIRST — this catches server-side limits
if (!isset($_FILES['photo'])) {
    Response::error('No photo received. Check PHP upload_max_filesize and post_max_size settings.', 400);
}
 
$file      = $_FILES['photo'];
$phpErrors = [
    UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit (upload_max_filesize).',
    UPLOAD_ERR_FORM_SIZE  => 'File exceeds form size limit.',
    UPLOAD_ERR_PARTIAL    => 'File was only partially uploaded. Please try again.',
    UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
    UPLOAD_ERR_NO_TMP_DIR => 'Server missing temporary folder. Contact your server admin.',
    UPLOAD_ERR_CANT_WRITE => 'Server failed to write file to disk. Check server permissions.',
    UPLOAD_ERR_EXTENSION  => 'A PHP extension stopped the file upload.',
];

if ($file['error'] !== UPLOAD_ERR_OK) {
    $msg = $phpErrors[$file['error']] ?? 'Upload error code: ' . $file['error'];
    Response::error($msg, 422);
}

if ($file['size'] === 0) {
    Response::error('Uploaded file is empty.', 422);
}

// Detect MIME from actual file content (not extension)
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mime  = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($mime, ALLOWED_IMG_TYPES, true)) {
    Response::error('Invalid file type (' . $mime . '). Allowed: JPEG, PNG, WebP.', 422);
}
if ($file['size'] > MAX_IMAGE_SIZE) {
    Response::error('File too large (' . round($file['size'] / 1024 / 1024, 1) . ' MB). Maximum is 5 MB.', 422);
}

$uploadDir = UPLOAD_PATH . 'profiles/';
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        Response::error('Upload directory could not be created. Please create farha_api/uploads/profiles/ on the server.', 500);
    }
}
if (!is_writable($uploadDir)) {
    Response::error('Upload directory is not writable. Run: chmod 755 farha_api/uploads/profiles on the server.', 500);
}

$ext      = match($mime) {
    'image/png'  => 'png',
    'image/webp' => 'webp',
    default      => 'jpg',
};
$filename = $userId . '_' . time() . '.' . $ext;
$dest     = $uploadDir . $filename;

if (!move_uploaded_file($file['tmp_name'], $dest)) {
    $err = error_get_last();
    Response::error('Failed to save file: ' . ($err['message'] ?? 'unknown error'), 500);
}

// Delete old photo if different
$db   = Database::connect();
$stmt = $db->prepare('SELECT profile_photo FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$old  = $stmt->fetchColumn();
if ($old && $old !== $filename && file_exists($uploadDir . $old)) {
    unlink($uploadDir . $old);
}

$db->prepare('UPDATE users SET profile_photo = ? WHERE id = ?')->execute([$filename, $userId]);

Response::success(
    [
        'photo_url'     => UPLOAD_URL . 'profiles/' . $filename,
        'profile_photo' => UPLOAD_URL . 'profiles/' . $filename,
    ],
    'Profile photo updated successfully.'
);
