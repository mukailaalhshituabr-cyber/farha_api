<?php
// GET  /farha_api/verify_email.php?token=TOKEN  (from email link)
// POST /farha_api/verify_email.php              (resend verification)
require_once __DIR__ . '/config.php';

function htmlResponse(string $type, string $title, string $message): never {
    $isSuccess = $type === 'success';
    $color     = $isSuccess ? '#74262b' : '#b91c1c';
    $icon      = $isSuccess ? '✅' : '❌';
    http_response_code($isSuccess ? 200 : 400);
    header('Content-Type: text/html; charset=UTF-8');
    echo <<<HTML
<!DOCTYPE html>
<html lang="en"><head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Farha — {$title}</title>
<style>
  *{box-sizing:border-box;margin:0;padding:0}
  body{font-family:'Segoe UI',Arial,sans-serif;background:#f5f5f5;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
  .card{background:#fff;border-radius:20px;padding:40px 32px;max-width:440px;width:100%;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,.1)}
  .icon{font-size:56px;margin-bottom:20px}
  h1{color:{$color};font-size:22px;margin-bottom:12px}
  p{color:#555;font-size:15px;line-height:1.7;margin-bottom:28px}
  .btn{display:inline-block;background:{$color};color:#fff;text-decoration:none;padding:14px 32px;border-radius:50px;font-size:15px;font-weight:600}
  .footer{margin-top:24px;font-size:12px;color:#aaa}
</style></head>
<body><div class="card">
  <div class="icon">{$icon}</div>
  <h1>{$title}</h1>
  <p>{$message}</p>
  <div class="footer">Farha — The Digital Atelier</div>
</div></body></html>
HTML;
    exit;
}

$db = Database::connect();

// ── GET: verify from email link ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');

    if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        htmlResponse('error', 'Invalid Link', 'This verification link is invalid. Please request a new one from the app.');
    }

    $stmt = $db->prepare('
        SELECT ev.id, ev.user_id, ev.expires_at, ev.used_at, u.is_verified
        FROM email_verifications ev
        JOIN users u ON u.id = ev.user_id
        WHERE ev.token = ?
        LIMIT 1
    ');
    $stmt->execute([$token]);
    $record = $stmt->fetch();

    if (!$record) {
        htmlResponse('error', 'Link Not Found', 'This verification link was not found or has already been used. You can request a new one from the Farha app.');
    }
    if ($record['used_at'] !== null || $record['is_verified']) {
        htmlResponse('success', 'Already Verified', 'Your email is already verified. Open the Farha app and log in.');
    }
    if (strtotime($record['expires_at']) < time()) {
        htmlResponse('error', 'Link Expired', 'This verification link has expired (links are valid for 24 hours). Open the Farha app and tap "Resend Email" to get a new link.');
    }

    try {
        $db->beginTransaction();
        $db->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$record['user_id']]);
        $db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')->execute([$record['id']]);
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Email verify error: ' . $e->getMessage());
        htmlResponse('error', 'Verification Failed', 'Something went wrong on our end. Please try again or contact support.');
    }

    htmlResponse('success', 'Email Verified!', 'Your email has been verified successfully. Open the Farha app and log in to your account.');
}

// ── POST: resend verification email ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    Auth::checkRateLimit('resend_verify', 3, 3600);

    $body = getBody();
    $v    = (new Validator($body))->required('email')->email('email');
    if ($v->fails()) Response::validationError($v->errors());

    $email = strtolower($v->get('email'));

    $stmt = $db->prepare('SELECT id, first_name, last_name, is_verified, language FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || $user['is_verified']) {
        Response::success(null, 'If that email exists and is unverified, a new link has been sent.');
    }

    $db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')
       ->execute([$user['id']]);

    $token     = Auth::generateSecureToken(32);
    $expiresAt = date('Y-m-d H:i:s', time() + EMAIL_VERIFY_EXPIRY * 3600);
    // Include id UUID (required column)
    $db->prepare('INSERT INTO email_verifications (id, user_id, token, expires_at) VALUES (?, ?, ?, ?)')
       ->execute([generateUuid(), $user['id'], $token, $expiresAt]);

    $fullName = $user['first_name'] . ' ' . $user['last_name'];
    Mailer::sendEmailVerification($email, $fullName, $token, $user['language'] ?? 'en');

    Response::success(null, 'A new verification link has been sent to your email.');
}

Response::error('Method not allowed.', 405);
