<?php
// ══════════════════════════════════════════════════════════════════════════════
// config.example.php  —  Copy this file to config.php and fill in your values.
// config.php is in .gitignore and must NEVER be committed to the repository.
// ══════════════════════════════════════════════════════════════════════════════

// ── Database ──────────────────────────────────────────────────────────────────
define('DB_HOST',   'localhost');
define('DB_NAME',   'your_database_name');
define('DB_USER',   'your_database_user');
define('DB_PASS',   'your_database_password');

// ── JWT ───────────────────────────────────────────────────────────────────────
// Generate a strong random string: openssl rand -hex 40
define('JWT_SECRET',         'nnckvjnededtrtrgygty');
define('JWT_ACCESS_EXPIRY',  60 * 60 * 24);       // 24 hours
define('JWT_REFRESH_EXPIRY', 60 * 60 * 24 * 30);  // 30 days

// ── App ───────────────────────────────────────────────────────────────────────
define('APP_NAME',    'Farha');
define('APP_URL',     'http://your-server/farha_api');
define('UPLOAD_PATH', __DIR__ . '/uploads/');
define('UPLOAD_URL',  APP_URL . '/uploads/');

// ── Email (Gmail SMTP via PHPMailer) ─────────────────────────────────────────
// Use a Gmail App Password (not your real password):
// Google Account → Security → 2-Step Verification → App Passwords
define('MAIL_HOST',       'smtp.gmail.com');
define('MAIL_PORT',       587);
define('MAIL_USERNAME',   'your_gmail@gmail.com');
define('MAIL_PASSWORD',   'your_gmail_app_password');
define('MAIL_FROM_EMAIL', 'your_gmail@gmail.com');
define('MAIL_FROM_NAME',  'Farha - The Digital Atelier');

// ── Security ──────────────────────────────────────────────────────────────────
define('BCRYPT_COST',          12);
define('EMAIL_VERIFY_EXPIRY',  24);
define('OTP_EXPIRY_MINUTES',   15);
define('OTP_MAX_ATTEMPTS',     5);
define('RATE_LIMIT_LOGIN',     5);
define('RATE_LIMIT_WINDOW',    15 * 60);
define('RATE_LIMIT_REGISTER',  3);

// ── Upload limits ─────────────────────────────────────────────────────────────
define('MAX_IMAGE_SIZE',    5 * 1024 * 1024);
define('ALLOWED_IMG_TYPES', ['image/jpeg', 'image/png', 'image/webp']);

// ── Platform commission ───────────────────────────────────────────────────────
define('PLATFORM_COMMISSION_RATE', 0.10);   // 10%
define('MIN_PAYOUT_AMOUNT',        5000);   // CFA

// ══════════════════════════════════════════════════════════════════════════════
// Do NOT add the CORS headers, class definitions, or any logic here.
// This file is documentation only. The real config.php contains everything.
// ══════════════════════════════════════════════════════════════════════════════
