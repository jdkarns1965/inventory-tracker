<?php
require_once 'config/config.php';
require_once 'config/auth.php';

// Clear remember me cookie if it exists
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Log the logout action
if (isLoggedIn()) {
    $username = $_SESSION['username'] ?? 'unknown';
    error_log("User logout: $username");
}

// Destroy session and redirect
logout();
?>