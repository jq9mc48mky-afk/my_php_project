<?php

/**
 * CSRF (Cross-Site Request Forgery) Protection Library
 *
 * This file provides functions to generate, validate, and output CSRF tokens
 * to protect all state-changing POST requests.
 *
 * 1. `get_csrf_token()`: Generates a token and stores it in the session.
 * 2. `csrf_input()`: Generates an HTML <input> field with the token.
 * 3. `validate_csrf_token()`: Checks the token from a POST request against
 * the one in the session. Throws an Exception if it doesn't match.
 */

// Call session_start() if not already started, as CSRF tokens rely on sessions.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates or retrieves the CSRF token from the session.
 *
 * If a token doesn't exist in the session, a new cryptographically
 * secure one is generated and stored.
 *
 * @return string The CSRF token.
 */
function get_csrf_token()
{
    if (empty($_SESSION['csrf_token'])) {
        // Generate a 32-byte random string and convert it to hex (64 chars)
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token from a POST request.
 *
 * This function MUST be called at the beginning of any script that
 * processes a POST request which modifies data.
 *
 * It uses `hash_equals()` for a timing-attack-safe comparison.
 *
 * @throws Exception if the token is missing or invalid.
 */
function validate_csrf_token()
{
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token is missing or invalid.
        // Clear the invalid token from the session to be safe.
        unset($_SESSION['csrf_token']);
        // Throw an exception, which will be caught by the calling script
        // (e.g., in api.php) and converted to a JSON error.
        throw new Exception('Invalid CSRF token.');
    }
}

/**
 * Generates a hidden HTML input field with the CSRF token.
 *
 * This function should be called inside every <form> that sends POST data.
 *
 * @return string HTML <input> field.
 */
function csrf_input()
{
    return '<input type="hidden" name="csrf_token" value="' . get_csrf_token() . '">';
}
