<?php
/**
 * AJAX API Endpoint
 *
 * This file is the single entry point for all asynchronous (fetch)
 * requests from the client-side JavaScript.
 *
 * It handles:
 * - Security: Validates sessions and CSRF tokens.
 * - Routing: Uses a 'type' parameter to determine which action to perform.
 * - Data Fetching: Calls functions from 'ajax_query_helpers.php'.
 * - HTML Rendering: Calls functions from 'ajax_render_helpers.php'.
 * - Error Handling: Catches all errors/exceptions and returns a clean JSON error.
 * - JSON Responses: All output is JSON.
 *
 * @global PDO $pdo The database connection object (from db.php).
 */

// Set content type to JSON immediately.
// All responses, including errors, will be JSON.
header('Content-Type: application/json');

/**
 * Custom Error Handler
 *
 * This function catches all PHP Notices, Warnings, and Errors
 * and converts them into an Exception. This allows our main try/catch
 * block to handle them gracefully and return a clean JSON error response,
 * instead of outputting messy HTML-formatted errors that break the JSON.
 */
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error is not one we're reporting (e.g., @-suppressed).
        return;
    }
    // Throw an ErrorException that our 'catch' block can handle.
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Main execution block. All logic is wrapped in a try/catch.
try {
    session_start();

    // --- 1. Include Dependencies ---
    require 'db.php';
    require 'csrf.php';
    require 'log_helper.php';
    require 'ajax_query_helpers.php';
    require 'ajax_render_helpers.php';

    // --- 2. Security & Authentication ---
    
    // For POST requests (like 'generate-reset-token'), we MUST validate the CSRF token.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // This will throw an Exception if it fails, which is caught below.
        validate_csrf_token();
    }

    // ALL API endpoints require a logged-in user.
    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized'); // Send 401 status
        throw new Exception('Access Denied. Please log in.');
    }

    // --- 3. Request Parameters ---
    $type = $_GET['type'] ?? '';      // The main "route" (e.g., 'computers', 'categories')
    $role = $_SESSION['role']; // For correct button rendering in helpers
    $params = $_GET;           // Pass all GET params to query functions

    // Base query params for pagination links (we remove 'type')
    $query_params = $_GET;
    unset($query_params['type']);

    // --- 4. Response Variables ---
    $data = null;
    $tableBodyHtml = '';
    $paginationHtml = '';

    // --- 5. API Routing (based on 'type' parameter) ---
    switch ($type) {
        // --- Route: 'computers' ---
        case 'computers':
            if (!defined('UPLOAD_DIR')) {
                define('UPLOAD_DIR', 'uploads/'); // Define for render helper
            }
            // Fetch the data
            $data = fetchComputersData($pdo, $params);
            // Render the HTML for the response
            $tableBodyHtml = renderComputersTableBody($data['results'], $role, csrf_input());
            break;

        // --- Route: 'categories' ---
        case 'categories':
            // Security: Only Admins can see this
            if ($role === 'User') {
                header('HTTP/1.1 403 Forbidden');
                throw new Exception('Access Denied.');
            }
            $data = fetchCategoriesData($pdo, $params);
            $tableBodyHtml = renderCategoriesTableBody($data['results'], csrf_input());
            break;

        // --- Route: 'suppliers' ---
        case 'suppliers':
            // Security: Only Admins can see this
            if ($role === 'User') {
                header('HTTP/1.1 403 Forbidden');
                throw new Exception('Access Denied.');
            }
            $data = fetchSuppliersData($pdo, $params);
            $tableBodyHtml = renderSuppliersTableBody($data['results'], csrf_input());
            break;

        // --- Route: 'generate-reset-token' (POST) ---
        case 'generate-reset-token':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('HTTP/1.1 405 Method Not Allowed');
                throw new Exception('POST method required.');
            }

            // Security: Only Super Admins can do this
            if ($role !== 'Super Admin') {
                header('HTTP/1.1 403 Forbidden');
                throw new Exception('Access Denied.');
            }

            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                header('HTTP/1.1 400 Bad Request');
                throw new Exception('Invalid user ID.');
            }

            // 1. Generate a secure token
            $token = bin2hex(random_bytes(32)); // 64-character hex string

            // 2. Save the token and set a 30-minute expiry in the DB
            $stmt = $pdo->prepare('
                UPDATE users SET 
                    password_reset_token = ?, 
                    reset_expiry = NOW() + INTERVAL 30 MINUTE 
                WHERE id = ?
            ');
            $stmt->execute([$token, $user_id]);

            // 3. Log this security action
            $stmt_user = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $stmt_user->execute([$user_id]);
            $username = $stmt_user->fetchColumn();
            log_system_change($pdo, $_SESSION['user_id'], 'Security', "Generated password reset link for user $username (ID: $user_id).");

            // 4. Send back *only* the token and exit
            echo json_encode(['token' => $token]);
            exit;

        // --- Default: Invalid Route ---
        default:
            header('HTTP/1.1 400 Bad Request');
            throw new Exception('Invalid request type.');
    }

    // --- 6. Common Rendering for List Routes ---
    // (This code only runs if the 'switch' didn't exit)
    
    // Get pagination data
    $total_pages = $data['total_pages'] ?? 0;
    $current_page = $data['current_page'] ?? 1;

    // Render pagination HTML
    $paginationHtml = renderPagination($current_page, $total_pages, $query_params);

    // --- 7. Send Successful JSON Response ---
    echo json_encode([
        'tableBody' => $tableBodyHtml,
        'pagination' => $paginationHtml
    ]);

} catch (PDOException $e) {
    // --- Error Handling: Database ---
    header('HTTP/1.1 500 Internal Server Error');
    error_log('API PDOException: ' . $e->getMessage()); // Log full error
    echo json_encode(['error' => 'A database error occurred.']); // Send generic error

} catch (ErrorException $e) {
    // --- Error Handling: PHP Errors --- (from set_error_handler)
    header('HTTP/1.1 500 Internal Server Error');
    error_log('API ErrorException: ' . $e->getMessage());
    echo json_encode([
        'error' => 'An internal server error occurred.',
        'details' => $e->getMessage() // Show the actual PHP error in the response
    ]);

} catch (Exception $e) {
    // --- Error Handling: All Other Errors --- (CSRF, Access Denied, etc.)
    if (!headers_sent()) {
        if (http_response_code() === 200) {
            // Set a generic 500 error if no specific one (like 401/403) was set
            header('HTTP/1.1 500 Internal Server Error');
        }
    }
    error_log('API Exception: ' . $e->getMessage());
    echo json_encode([
        'error' => 'An error occurred.',
        'details' => $e->getMessage() // Shows "Invalid CSRF token." etc.
    ]);
}
?>