<?php
// GET  /farha_api/verify_email.php?token=TOKEN  (from email link)
// POST /farha_api/verify_email.php              (resend verification)
require_once __DIR__ . '/config.php';

$db = Database::connect();

// ── GET: verify from email link ───────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $token = trim($_GET['token'] ?? '');

    if (empty($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
        Response::error('Invalid verification link.', 400);
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

    if (!$record) Response::error('Verification link not found or already used.', 404);
    if ($record['used_at'] !== null || $record['is_verified']) {
        Response::success(null, 'Your email is already verified. You can log in.');
    }
    if (strtotime($record['expires_at']) < time()) {
        Response::error('This verification link has expired. Please request a new one.', 410);
    }

    try {
        $db->beginTransaction();
        $db->prepare('UPDATE users SET is_verified = 1 WHERE id = ?')->execute([$record['user_id']]);
        $db->prepare('UPDATE email_verifications SET used_at = NOW() WHERE id = ?')->execute([$record['id']]);
        $db->commit();
    } catch (PDOException $e) {
        $db->rollBack();
        error_log('Email verify error: ' . $e->getMessage());
        Response::error('Verification failed. Please try again.', 500);
    }

    Response::success(null, 'Email verified successfully! You can now log in to your Farha account.');
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
