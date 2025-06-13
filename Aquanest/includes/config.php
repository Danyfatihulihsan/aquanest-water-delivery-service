<?php
// File: includes/config.php

// Application settings
define('APP_NAME', 'Aquanest');
define('APP_VERSION', '1.0.0');
define('APP_URL', 'https://aquanest.id');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'aquanest_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Upload settings
define('UPLOAD_DIR', __DIR__ . '/../uploads/');
define('PAYMENT_PROOF_DIR', UPLOAD_DIR . 'payment_proofs/');
define('MAX_UPLOAD_SIZE', 2 * 1024 * 1024); // 2MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf']);

// Order settings
define('ORDER_PREFIX', 'AQN');
define('DEFAULT_DELIVERY_DAYS', 1); // Default delivery in days
define('COD_MAX_CHANGE', 50000); // Maximum change courier carries

// Payment settings
define('PAYMENT_EXPIRY_HOURS', 24);
define('BANK_ACCOUNT_NUMBER', '1234567890');
define('BANK_ACCOUNT_NAME', 'PT Aquanest Indonesia');
define('BANK_NAME', 'Bank Central Asia (BCA)');

// QRIS settings
define('QRIS_MERCHANT_ID', '93600914300021565');
define('QRIS_MERCHANT_NAME', 'AQUANEST');
define('QRIS_MERCHANT_CITY', 'JAKARTA');

// Email settings
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@aquanest.id');
define('SMTP_PASSWORD', 'your-email-password');
define('SMTP_FROM_NAME', 'Aquanest');
define('SMTP_FROM_EMAIL', 'noreply@aquanest.id');

// SMS settings
define('SMS_API_KEY', 'your-sms-api-key');
define('SMS_API_URL', 'https://api.sms-provider.com/send');
define('SMS_SENDER_ID', 'AQUANEST');

// WhatsApp settings
define('WA_API_KEY', 'your-whatsapp-api-key');
define('WA_API_URL', 'https://api.whatsapp-provider.com/send');
define('WA_BUSINESS_NUMBER', '628123456789');

// Maps API
define('GOOGLE_MAPS_API_KEY', 'your-google-maps-api-key');

// Session settings
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'aquanest_session');

// Security settings
define('SALT', 'your-random-salt-here');
define('PEPPER', 'your-random-pepper-here');

// Timezone
date_default_timezone_set('Asia/Jakarta');

// Error reporting (disable in production)
if ($_SERVER['SERVER_NAME'] == 'localhost') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// Start session with secure settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_secure', 1);
    session_name(SESSION_NAME);
    session_start();
}

// Include required files
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
?>