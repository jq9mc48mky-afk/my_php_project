<?php

/**
 * User Logout Script
 *
 * This file securely logs out a user.
 * 1. Requires CSRF helper (which starts the session).
 * 2. Sets strict HTTP Security Headers.
 * 3. Checks for POST request method.
 * 4. Validates the CSRF token to prevent "logout CSRF" attacks.
 * 5. If valid, destroys the user's session.
 * 6. Redirects to login.php regardless of success (no info is leaked).
 */

require 'csrf.php'; // This will also start the session

// *** HTTP SECURITY HEADERS ***
// These headers are good practice even for redirect-only pages.
// This CSP blocks all content from loading, which is what we want here.
header("Content-Security-Policy: default-src 'none'; frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
// Strict-Transport-Security (HSTS)
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
// *** END HTTP SECURITY HEADERS ***

// --- Logout Logic ---
// We MUST check that this is a POST request and has a valid token.
// This prevents a malicious site from logging a user out via a simple <img> tag.
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Throws an exception if token is invalid
    validate_csrf_token();

    // --- Token is valid, proceed with logout ---

    // Unset all of the session variables.
    $_SESSION = [];

    // Finally, destroy the session.
    session_destroy();
}

// --- Redirect ---
// Always redirect to the login page.
// If the request was a GET, or a POST with an invalid token,
// the session is *not* destroyed, but the user is still redirected.
header('Location: login.php');
exit;
