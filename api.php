<?php
/**
 * AJAX API Endpoint
 *
 * Handles all asynchronous data requests for filtering, pagination,
 * and other dynamic actions (like generating reset tokens).
 *
 * All responses are in JSON format.
 */

// Set content type to JSON immediately.
header('Content-Type: application/json');

/**
 * Custom Error Handler
 * This function catches all PHP Notices, Warnings, and Errors
 * and converts them into an Exception that our main try/catch
 * block can handle, ensuring a clean JSON error response.
 */
set_error_handler(function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error is not one we're reporting.
        return;
    }
    // Throw an ErrorException
    throw new ErrorException($message, 0, $severity, $file, $line);
});

// Main execution block
try {
    session_start();

    // --- Include Dependencies ---
    require 'db.php';
    require 'csrf.php';
    require 'log_helper.php';
    require 'ajax_query_helpers.php';
    require 'ajax_render_helpers.php';

    // --- Security & Authentication ---
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // This will throw an Exception if it fails
        validate_csrf_token();
    }

    if (!isset($_SESSION['user_id'])) {
        header('HTTP/1.1 401 Unauthorized');
        throw new Exception('Access Denied. Please log in.');
    }

    // --- Request Parameters ---
    $type = $_GET['type'] ?? '';
    $role = $_SESSION['role']; // For correct button rendering
    $params = $_GET; // All GET params for query functions

    // Base query params for pagination links
    $query_params = $_GET;
    unset($query_params['type']); // Not needed in pagination links

    // --- Response Variables ---
    $data = null;
    $tableBodyHtml = '';
    $paginationHtml = '';

    // --- API Routing (based on 'type' parameter) ---
    switch ($type) {
        case 'computers':
            if (!defined('UPLOAD_DIR')) {
                define('UPLOAD_DIR', 'uploads/'); // Define for render helper
            }
            $data = fetchComputersData($pdo, $params);
            $tableBodyHtml = renderComputersTableBody($data['results'], $role, csrf_input());
            break;

        case 'categories':
            if ($role === 'User') {
                header('HTTP/1.1 403 Forbidden');
                throw new Exception('Access Denied.');
            }
            $data = fetchCategoriesData($pdo, $params);
            $tableBodyHtml = renderCategoriesTableBody($data['results'], csrf_input());
            break;

        case 'suppliers':
            if ($role === 'User') {
                header('HTTP/1.1 403 Forbidden');
                throw new Exception('Access Denied.');
            }
            $data = fetchSuppliersData($pdo, $params);
            $tableBodyHtml = renderSuppliersTableBody($data['results'], csrf_input());
            break;

        case 'generate-reset-token':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                header('HTTP/1.1 405 Method Not Allowed');
                throw new Exception('POST method required.');
            }

            // Only Super Admins can do this
            if ($role !== 'Super Admin') {
                header('HTTP/1.1 403 Forbidden');
                throw new Exception('Access Denied.');
            }

            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                header('HTTP/1.1 400 Bad Request');
                throw new Exception('Invalid user ID.');
            }

            // 1. Generate secure token and expiry
            $token = bin2hex(random_bytes(32)); // 64-character hex string

            // 2. Save to database
            $stmt = $pdo->prepare('
                UPDATE users SET 
                    password_reset_token = ?, 
                    reset_expiry = NOW() + INTERVAL 30 MINUTE 
                WHERE id = ?
            ');
            $stmt->execute([$token, $user_id]);

            // 3. Log this action
            $stmt_user = $pdo->prepare('SELECT username FROM users WHERE id = ?');
            $stmt_user->execute([$user_id]);
            $username = $stmt_user->fetchColumn();
            log_system_change($pdo, $_SESSION['user_id'], 'Security', "Generated password reset link for user $username (ID: $user_id).");

            // 4. Send back the token (and exit)
            echo json_encode(['token' => $token]);
            exit;

        default:
            header('HTTP/1.1 400 Bad Request');
            throw new Exception('Invalid request type.');
    }

    // --- Common data extraction and pagination rendering for list types ---
    $total_pages = $data['total_pages'] ?? 0;
    $current_page = $data['current_page'] ?? 1;

    $paginationHtml = renderPagination($current_page, $total_pages, $query_params);

    // --- Send the successful response ---
    echo json_encode([
        'tableBody' => $tableBodyHtml,
        'pagination' => $paginationHtml
    ]);

} catch (PDOException $e) {
    // Database exceptions
    header('HTTP/1.1 500 Internal Server Error');
    error_log('API PDOException: ' . $e->getMessage());
    echo json_encode(['error' => 'A database error occurred.']);

} catch (ErrorException $e) {
    // PHP errors (from set_error_handler)
    header('HTTP/1.1 500 Internal Server Error');
    error_log('API ErrorException: ' . $e->getMessage());
    echo json_encode([
        'error' => 'An internal server error occurred.',
        'details' => $e->getMessage() // Shows the actual PHP error
    ]);

} catch (Exception $e) {
    // All other exceptions (CSRF, Access Denied, etc.)
    if (!headers_sent()) {
        if (http_response_code() === 200) {
            // Set a generic 500 error if no specific one was set
            header('HTTP/1.1 500 Internal Server Error');
        }
    }
    error_log('API Exception: ' . $e->getMessage());
    echo json_encode([
        'error' => 'An error occurred.',
        'details' => $e->getMessage() // Shows "Invalid CSRF token." etc.
    ]);
}