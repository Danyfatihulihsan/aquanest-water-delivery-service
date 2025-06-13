<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'aquanest');
define('DB_USER', 'root'); 
define('DB_PASS', '');

// Application configuration
define('UPLOAD_PATH', 'uploads/');
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/png', 'image/jpg', 'application/pdf']);
define('MAX_FILE_SIZE', 2 * 1024 * 1024); // 2MB

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
