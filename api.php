<?php
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf_token();
}
// Start session and check authentication
session_start();

if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.1 401 Unauthorized');
    echo json_encode(['error' => 'Access Denied. Please log in.']);
    exit;
}

// Set content type to JSON
header('Content-Type: application/json');

// Include dependencies
try {
    require 'db.php';
    require 'csrf.php';
    require 'log_helper.php';
    require 'ajax_query_helpers.php';
    require 'ajax_render_helpers.php';
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    echo json_encode(['error' => 'Failed to load dependencies.']);
    exit;
}

// Get request parameters
$type = $_GET['type'] ?? '';
$role = $_SESSION['role']; // For correct button rendering
$csrf_token = get_csrf_token(); // For rendering forms

// Get all GET params for query functions
$params = $_GET; 

// Base query params for pagination links
$query_params = $_GET;
unset($query_params['type']); // This is not needed in the pagination links

$data = null;
$results = [];
$total_pages = 0;
$current_page = 1;
$tableBodyHtml = '';
$paginationHtml = '';

try {
    switch ($type) {
        case 'computers':
            if (!defined('UPLOAD_DIR')) {
                define('UPLOAD_DIR', 'uploads/'); // Define for render helper
            }
            $data = fetchComputersData($pdo, $params);
            // *** MODIFIED: Pass csrf_token() string, not the function
            $tableBodyHtml = renderComputersTableBody($data['results'], $role, csrf_input());
            break;

        case 'categories':
            if ($role == 'User') { // Or whatever role CANNOT see categories
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['error' => 'Access Denied.']);
                exit;
            }
            $data = fetchCategoriesData($pdo, $params);
            // *** MODIFIED: Pass csrf_token() string, not the function
            $tableBodyHtml = renderCategoriesTableBody($data['results'], csrf_input());
            break;

        case 'suppliers':
            if ($role == 'User') {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['error' => 'Access Denied.']);
                exit;
            }
            $data = fetchSuppliersData($pdo, $params);
            // *** MODIFIED: Pass csrf_token() string, not the function
            $tableBodyHtml = renderSuppliersTableBody($data['results'], csrf_input());
            break;

        case 'generate-reset-token':
            if ($_SERVER['REQUEST_METHOD'] != 'POST') {
                header('HTTP/1.1 405 Method Not Allowed');
                echo json_encode(['error' => 'POST method required.']);
                exit;
            }

            // Only Super Admins can do this
            if ($role != 'Super Admin') {
                header('HTTP/1.1 403 Forbidden');
                echo json_encode(['error' => 'Access Denied.']);
                exit;
            }

            $user_id = (int)($_POST['user_id'] ?? 0);
            if ($user_id <= 0) {
                header('HTTP/1.1 400 Bad Request');
                echo json_encode(['token' => $token]);
                exit;
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

            // 4. Send back the token
            echo json_encode(['token' => $token]);
            exit; // Exit here, we don't need the table/pagination logic

        default:
            header('HTTP/1.1 400 Bad Request');
            echo json_encode(['error' => 'Invalid request type.']);
            exit;
    }

    // Common data extraction and pagination rendering
    $results = $data['results'];
    $total_pages = $data['total_pages'];
    $current_page = $data['current_page'];
    
    $paginationHtml = renderPagination($current_page, $total_pages, $query_params);

    // Send the successful response
    echo json_encode([
        'tableBody' => $tableBodyHtml,
        'pagination' => $paginationHtml
    ]);

} catch (PDOException $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log('API PDOException: ' . $e->getMessage());
    echo json_encode(['error' => 'A database error occurred.']);
} catch (Exception $e) {
    header('HTTP/1.1 500 Internal Server Error');
    error_log('API Exception: ' . $e->getMessage());
    echo json_encode(['error' => 'An unexpected error occurred.']);
}

?>