<?php
// FILE: includes/notification_config.php
// Konfigurasi untuk sistem notifikasi pembayaran

// Notification Settings
define('NOTIFICATION_SETTINGS', [
    'email_enabled' => true,
    'sms_enabled' => false,
    'auto_refresh_interval' => 30, // seconds
    'notification_expiry' => 3600, // 1 hour
    'admin_email' => 'admin@aquanest.com',
    'payment_verification_timeout' => 86400, // 24 hours
    'max_file_size' => 2097152, // 2MB
    'allowed_file_types' => ['jpg', 'jpeg', 'png', 'pdf'],
    'upload_path' => 'uploads/payment_proofs/',
    'cleanup_days' => 30 // Auto cleanup notifications older than 30 days
]);

// Admin Settings
define('ADMIN_SETTINGS', [
    'default_username' => 'admin',
    'default_password' => 'admin123', // Change this in production!
    'session_timeout' => 7200, // 2 hours
    'require_2fa' => false,
    'max_login_attempts' => 5,
    'lockout_duration' => 900 // 15 minutes
]);

// Email Settings (for notifications)
define('EMAIL_SETTINGS', [
    'smtp_enabled' => false, // Set to true to use SMTP
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => 587,
    'smtp_username' => 'your-email@gmail.com',
    'smtp_password' => 'your-app-password',
    'smtp_encryption' => 'tls',
    'from_email' => 'no-reply@aquanest.com',
    'from_name' => 'Aquanest System',
    'admin_email' => 'admin@aquanest.com'
]);

// SMS Settings (optional)
define('SMS_SETTINGS', [
    'provider' => 'twilio', // twilio, nexmo, etc
    'api_key' => 'your-api-key',
    'api_secret' => 'your-api-secret',
    'from_number' => '+1234567890'
]);

// Database Settings for cleanup
define('CLEANUP_SETTINGS', [
    'auto_cleanup_enabled' => true,
    'cleanup_interval' => 86400, // 24 hours
    'keep_notifications_days' => 30,
    'keep_processed_days' => 90,
    'max_file_age_days' => 365
]);

// Security Settings
define('SECURITY_SETTINGS', [
    'rate_limit_enabled' => true,
    'max_requests_per_minute' => 60,
    'csrf_protection' => true,
    'ip_whitelist' => [], // Empty array = all IPs allowed
    'log_all_actions' => true
]);

// File Upload Settings
define('UPLOAD_SETTINGS', [
    'max_file_size' => NOTIFICATION_SETTINGS['max_file_size'],
    'allowed_types' => NOTIFICATION_SETTINGS['allowed_file_types'],
    'upload_path' => NOTIFICATION_SETTINGS['upload_path'],
    'create_thumbnails' => true,
    'thumbnail_size' => [300, 300],
    'virus_scan_enabled' => false, // Set to true if you have virus scanner
    'watermark_enabled' => false
]);

// Status Messages
define('STATUS_MESSAGES', [
    'payment_uploaded' => 'Bukti pembayaran telah diunggah dan menunggu verifikasi admin',
    'payment_approved' => 'Pembayaran telah disetujui dan pesanan sedang diproses',
    'payment_rejected' => 'Pembayaran ditolak, silakan upload ulang bukti yang valid',
    'order_processing' => 'Pesanan sedang diproses',
    'order_shipped' => 'Pesanan telah dikirim',
    'order_delivered' => 'Pesanan telah sampai'
]);

// Error Messages
define('ERROR_MESSAGES', [
    'file_too_large' => 'Ukuran file terlalu besar. Maksimal 2MB.',
    'invalid_file_type' => 'Tipe file tidak diizinkan. Gunakan JPG, PNG, atau PDF.',
    'upload_failed' => 'Gagal mengunggah file. Silakan coba lagi.',
    'order_not_found' => 'Pesanan tidak ditemukan.',
    'unauthorized' => 'Akses tidak diizinkan.',
    'session_expired' => 'Sesi telah berakhir. Silakan login kembali.',
    'invalid_payment_method' => 'Metode pembayaran tidak valid.'
]);

// Success Messages
define('SUCCESS_MESSAGES', [
    'payment_uploaded' => 'Bukti pembayaran berhasil diunggah. Akan diverifikasi dalam 24 jam.',
    'payment_approved' => 'Pembayaran telah dikonfirmasi. Pesanan sedang diproses.',
    'order_created' => 'Pesanan berhasil dibuat.',
    'profile_updated' => 'Profil berhasil diperbarui.',
    'notification_sent' => 'Notifikasi berhasil dikirim.'
]);

// System Configuration
define('SYSTEM_CONFIG', [
    'timezone' => 'Asia/Jakarta',
    'date_format' => 'd M Y, H:i',
    'currency' => 'IDR',
    'currency_symbol' => 'Rp',
    'company_name' => 'Aquanest',
    'support_phone' => '0812-3456-7890',
    'support_email' => 'support@aquanest.com'
]);

// Set timezone
date_default_timezone_set(SYSTEM_CONFIG['timezone']);

// Helper functions for configuration
function getNotificationSetting($key) {
    return NOTIFICATION_SETTINGS[$key] ?? null;
}

function getAdminSetting($key) {
    return ADMIN_SETTINGS[$key] ?? null;
}

function getEmailSetting($key) {
    return EMAIL_SETTINGS[$key] ?? null;
}

function isFeatureEnabled($feature) {
    switch ($feature) {
        case 'email':
            return getNotificationSetting('email_enabled');
        case 'sms':
            return getNotificationSetting('sms_enabled');
        case 'cleanup':
            return CLEANUP_SETTINGS['auto_cleanup_enabled'];
        case 'rate_limit':
            return SECURITY_SETTINGS['rate_limit_enabled'];
        default:
            return false;
    }
}

function getUploadPath() {
    $path = getNotificationSetting('upload_path');
    if (!file_exists($path)) {
        mkdir($path, 0777, true);
    }
    return $path;
}

function getMaxFileSize() {
    return getNotificationSetting('max_file_size');
}

function getAllowedFileTypes() {
    return getNotificationSetting('allowed_file_types');
}

function formatCurrency($amount) {
    return SYSTEM_CONFIG['currency_symbol'] . ' ' . number_format($amount, 0, ',', '.');
}

function formatDate($date) {
    return date(SYSTEM_CONFIG['date_format'], strtotime($date));
}

// Environment-specific settings
if (getenv('ENVIRONMENT') === 'production') {
    // Production settings
    error_reporting(0);
    ini_set('display_errors', 0);
    
    // Override some settings for production
    define('PROD_ADMIN_SETTINGS', array_merge(ADMIN_SETTINGS, [
        'session_timeout' => 3600, // 1 hour in production
        'require_2fa' => true
    ]));
} else {
    // Development settings
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// Load custom configuration if exists
if (file_exists(__DIR__ . '/custom_config.php')) {
    include_once __DIR__ . '/custom_config.php';
}

?>