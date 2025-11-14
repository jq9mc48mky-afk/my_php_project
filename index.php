<?php
session_start();

// Generate a random nonce for CSP
$csp_nonce = bin2hex(random_bytes(16));

// *** HTTP SECURITY HEADERS ***
$nonce_string = "'nonce-" . $csp_nonce . "'";
header("Content-Security-Policy: default-src 'self'; " .
       "script-src 'self' " .
           "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js 'sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz' " .
           "https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/js/tom-select.complete.min.js 'sha256-KNeF6xW5o/tW1oae5XlS4JCNADoM+RHqrnoUqL6pvHY=' " .
           "https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js 'sha256-Huqxy3eUcaCwqqk92RwusapTfWlvAasF6p2rxV6FJaE=' " .
           $nonce_string . "; " .
       "style-src 'self' " .
           "https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css 'sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH' " .
           "https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css 'sha384-dpuaTHEBJeSjVBZkE9FNEPcbL2GfNlYtBW/aFG1TLcIcP1rT/5o8NFv/sUbOPfO/' " .
           "https://cdn.jsdelivr.net/npm/tom-select@2.3.1/dist/css/tom-select.bootstrap5.min.css 'sha256-4MwGlgBoHJALXjs2YKZb4sMqhSw7+yMymHAoa0cwJGE=' " .
           "https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css 'sha256-GzSkJVLJbxDk36qko2cnawOGiqz/Y8GsQv/jMTUrx1Q=' " .
           $nonce_string . "; " .
       "font-src 'self' https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/fonts/; " .
       "connect-src 'self' https://cdn.jsdelivr.net; " .
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
    if ($role == 'Admin' && ($page == 'users' || $page == 'system_log')) { // <-- MODIFIED
        $_SESSION['error'] = 'Access Denied: You do not have permission to access that page.'; // <-- MODIFIED
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