<?php
/**
 * Main Router/Controller for the Computers Page.
 *
 * This file is the single entry point for all requests to '?page=computers'.
 * It follows a Command/Query Responsibility Segregation (CQRS) pattern:
 *
 * 1. [Command] It first includes 'computers_actions.php' to handle all
 * state-changing POST requests (Add, Edit, Delete, Check-in/out, Bulk).
 * If a POST request is handled, that script will redirect and exit.
 *
 * 2. [Query] If the script continues, it's a GET request. This file handles:
 * a) Non-HTML GET requests (e.g., CSV Export).
 * b) HTML GET requests (views) by preparing data and including the
 * appropriate partial view file from 'partials/'.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 * @global string $csp_nonce The Content Security Policy nonce.
 * @global string $action The current view action (e.g., 'list', 'add').
 */

// $pdo, $role, $csp_nonce are available from index.php
$action = $_GET['action'] ?? 'list';
$admin_user_id = $_SESSION['user_id'];

// --- 1. COMMANDS (Handle all POST actions first) ---
// This file contains all POST logic (save, delete, check-in, etc.)
// and will `exit;` if a POST request is processed.
require 'computers_actions.php';


// --- 2. QUERIES (Handle all GET requests) ---

// --- Constants (needed for views and helpers) ---
define('UPLOAD_DIR', 'uploads/'); // Directory for full-size images
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif']); // Allowed image types

// --- Helper Files (needed for views) ---
require 'ajax_query_helpers.php';
require 'ajax_render_helpers.php';
require 'image_helper.php';


// --- GET Request: CSV EXPORT ---
// This block intercepts the 'list' action if 'export=csv' is in the URL.
if ($action == 'list' && isset($_GET['export']) && $_GET['export'] == 'csv') {

    // We use the *exact same* query helper as the main list.
    // This ensures the export matches the filters applied on the page.
    $data = fetchComputersData($pdo, $_GET);
    $results = $data['results'];

    // Set HTTP headers to force a file download
    $filename = 'computers_export_' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    // Open 'php://output' as a file stream
    $output = fopen('php://output', 'w');

    // Write the CSV header row
    fputcsv($output, [
        'Asset Tag', 'Category', 'Model', 'Serial Number', 'Status',
        'Assigned To (Name)', 'Assigned To (Username)',
        'Supplier', 'Purchase Date', 'Warranty Expiry'
    ]);

    // Loop through the query results and write each row to the CSV
    foreach ($results as $row) {
        $csv_row = [
            $row['asset_tag'],
            $row['category_name'],
            $row['model'],
            $row['serial_number'],
            $row['status'],
            $row['assigned_to_full_name'],
            $row['assigned_to_username'],
            $row['supplier_name'],
            $row['purchase_date'],
            $row['warranty_expiry']
        ];
        fputcsv($output, $csv_row);
    }

    fclose($output);
    exit; // Stop script execution after generating the CSV
}


// --- GET Request: HTML VIEWS ---
// This switch statement controls which view (list, add, edit, checkout) is displayed.

switch ($action) {
    case 'add':
    case 'edit':
        // --- PREPARE DATA for Add/Edit Form ---
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied.';
            header('Location: index.php?page=computers');
            exit;
        }
        $computer = null;
        if ($action == 'edit' && isset($_GET['id'])) {
            // If 'edit', fetch the computer's data to pre-fill the form
            $stmt = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
            $stmt->execute([$_GET['id']]);
            $computer = $stmt->fetch();
            if (!$computer) {
                $_SESSION['error'] = 'Computer not found.';
                header('Location: index.php?page=computers');
                exit;
            }
        }

        // Fetch data for dropdown menus
        $categories = $pdo->query('SELECT id, name FROM categories')->fetchAll();
        $suppliers = $pdo->query('SELECT id, name FROM suppliers')->fetchAll();
        $users = $pdo->query('SELECT id, username, full_name FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();
        $statuses = ['In Stock', 'Assigned', 'In Repair', 'Retired'];

        // --- RENDER VIEW ---
        include 'partials/computers_form.php';
        break;

    case 'checkout':
        // --- PREPARE DATA for Check-out Form ---
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied.';
            header('Location: index.php?page=computers');
            exit;
        }
        $computer_id = $_GET['id'] ?? null;
        if (!$computer_id) {
            $_SESSION['error'] = 'Invalid computer ID.';
            header('Location: index.php?page=computers');
            exit;
        }

        // 1. Fetch computer details
        $stmt = $pdo->prepare('SELECT * FROM computers WHERE id = ?');
        $stmt->execute([$computer_id]);
        $computer = $stmt->fetch();
        if (!$computer) {
            $_SESSION['error'] = 'Computer not found.';
            header('Location: index.php?page=computers');
            exit;
        }

        // 2. Business Logic: Can only check out items that are 'In Stock'
        if ($computer['status'] != 'In Stock') {
            $_SESSION['error'] = 'This asset is not "In Stock" and cannot be checked out.';
            header('Location: index.php?page=computers');
            exit;
        }

        // 3. Fetch users for the dropdown
        $users = $pdo->query('SELECT id, username, full_name FROM users ORDER BY full_name')->fetchAll();
        
        // --- RENDER VIEW ---
        include 'partials/computers_checkout.php';
        break;

    case 'list':
    default:
        // --- PREPARE DATA for List View ---

        // Get filter values from URL
        $search_term = $_GET['search'] ?? '';
        $status_filter = $_GET['status_filter'] ?? '';
        $category_filter = $_GET['category_filter'] ?? '';
        $assigned_user_filter = $_GET['assigned_user_id'] ?? '';

        // Fetch data for filter dropdowns
        $categories = $pdo->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
        $statuses = ['In Stock', 'Assigned', 'In Repair', 'Retired'];
        $users = $pdo->query('SELECT id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name')->fetchAll();

        // Initial data load using the helper function from 'ajax_query_helpers.php'
        $data = fetchComputersData($pdo, $_GET);
        $computers = $data['results'];
        $total_pages = $data['total_pages'];
        $current_page = $data['current_page'];

        // --- RENDER VIEW ---
        include 'partials/computers_list.php';
        break;
}
?>