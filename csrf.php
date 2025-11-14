<?php
/**
 * CSRF Protection
 * Call session_start() if not already started.
 */
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Generates or retrieves the CSRF token from the session.
 *
 * @return string The CSRF token.
 */
function get_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Validates the CSRF token from a POST request.
 * Dies with an error message if validation fails.
 */
function validate_csrf_token() {
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        // Token is missing or invalid.
        // Clear token to be safe and die.
        unset($_SESSION['csrf_token']);
        die('CSRF token validation failed. Please go back and try again.');
    }
}

/**
 * Generates a hidden HTML input field with the CSRF token.
 *
 * @return string HTML input field.
 */
function csrf_input() {
    return '<input type="hidden" name="csrf_token" value="' . get_csrf_token() . '">';
}
?>