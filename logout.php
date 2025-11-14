<?php
require 'csrf.php'; // This will also start the session

// *** HTTP SECURITY HEADERS ***
// These headers are good practice even for redirect-only pages.
// This CSP blocks all content from loading, which is what we want here.
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Strict-Transport-Security (HSTS) - Uncomment ONLY if your site uses HTTPS
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
// *** END HTTP SECURITY HEADERS ***

// Only log out if it's a POST request with a valid token
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf_token();

    // Unset all session variables
    $_SESSION = [];

    // Destroy the session
    session_destroy();
}

// Redirect to the login page
// If it wasn't a valid POST, they just get redirected.
header('Location: login.php');
exit;