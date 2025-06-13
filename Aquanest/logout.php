<?php
// Start session
session_start();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Regenerate session ID for security reasons in case of session fixation attempts
session_regenerate_id(true);

// Destroy the session
session_destroy();

// Redirect to the home page (index.php) instead of login page
header("location: index.php");
exit;
?>