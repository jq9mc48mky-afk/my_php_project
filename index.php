<?php
session_start();

// Generate a random nonce for CSP
$csp_nonce = bin2hex(random_bytes(16));

// *** HTTP SECURITY HEADERS ***
$nonce_string = "'nonce-" . $csp_nonce . "'";
header("Content-Security-Policy: default-src 'self'; " .
       "script-src 'self' " . $nonce_string . "; " .
       "style-src 'self' " . $nonce_string . "; " .
       "font-src 'self'; " .
       "connect-src 'self'; " .
       "img-src 'self' data:; " .
       "object-src 'none'; " .
       "frame-ancestors 'none';");
header("X-Content-Type-Options: nosniff");
// header("X-Frame-Options: DENY"); // Made redundant by CSP frame-ancestors
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");
// *** END HTTP SECURITY HEADERS ***

// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // --- DEPENDENCIES ---
    require 'db.php';
    require 'csrf.php';
    require 'log_helper.php'; 

    $role = $_SESSION['role'];
    $page = $_GET['page'] ?? 'dashboard';

    // --- CSV EXPORT LOGIC ---
    // (This stays here, as it's a header logic that must run first)
    if (isset($_GET['export'])) {
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied: You do not have permission for this action.';
            header('Location: index.php?page=dashboard');
            exit;
        }
        if ($page == 'computers') {
            include 'pages/computers.php'; // The file will handle export and exit
        }
        if ($page == 'reports') {
            include 'pages/reports.php'; // The file will handle export and exit
        }
    }

    // *** CSRF VALIDATION FOR ALL POST ACTIONS ***
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        validate_csrf_token();
    }

    // Define allowed pages for security
    $allowed_pages = [
        'dashboard', 
        'computers', 
        'suppliers', 
        'categories', 
        'users', 
        'reports',
        'computer_history',
        'profile',
        'maintenance',
        'system_log'
    ];

    if (!in_array($page, $allowed_pages)) {
        $page = 'dashboard';
    }

    // --- Role-Based Access Control (RBAC) ---
    if ($role == 'Admin' && ($page == 'users' || $page == 'system_log')) {
        $_SESSION['error'] = 'Access Denied: You do not have permission to access that page.';
        $page = 'dashboard';
    }
    if ($role == 'User') {
        $user_allowed = ['dashboard', 'computers', 'profile'];
        if (!in_array($page, $user_allowed)) {
            $_SESSION['error'] = 'Access Denied: You do not have permission to access that page.';
            $page = 'dashboard';
        }
    }
    // --- End RBAC ---

    ob_start();

    // --- Include the specific page content ---
    // The page file itself is now responsible for its own logic AND
    // for including the header and footer.
    $page_file = "pages/{$page}.php";

    if (file_exists($page_file)) {
        include $page_file;
    } else {
        // 404 behavior if file not found
        header("HTTP/1.1 404 Not Found");
        echo '<h1>404 Not Found</h1>';
        echo '<div class="alert alert-danger">The page you requested does not exist.</div>';
    }

    $page_content = ob_get_clean();

    include 'partials/header.php';
    echo $page_content; // Inject the specifically captured content here
    include 'partials/footer.php';

} catch (PDOException $e) {
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // A critical database error occurred.
    error_log('Global PDOException: ' . $e->getMessage());
    
    // We must include header/footer here to show the error
    include 'partials/header.php';
    echo '<div class="alert alert-danger">
            A critical database error occurred. Please contact support.
          </div>';
    include 'partials/footer.php';
}