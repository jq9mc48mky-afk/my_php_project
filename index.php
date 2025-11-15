<?php

/**
 * Main Application Controller (Front Controller)
 *
 * This is the single entry point for the entire logged-in application.
 * All requests are routed through this file.
 *
 * It handles:
 * 1. Session Initialization and Security.
 * 2. Generating a Content Security Policy (CSP) nonce.
 * 3. Sending HTTP Security Headers.
 * 4. Authentication: Redirects to login.php if user is not logged in.
 * 5. Dependency Inclusion (DB, CSRF, Logging).
 * 6. CSV Export Handling (must run before headers are sent).
 * 7. Global POST request CSRF validation.
 * 8. Routing: Maps the '?page=' parameter to a file in the 'pages/' directory.
 * 9. Role-Based Access Control (RBAC): Blocks access to pages based on user role.
 * 10. Page Buffering: Includes the requested page, captures its output, and
 * injects it between the global header.php and footer.php.
 * 11. Global Error Handling.
 */

session_start();

// Generate a random 16-byte nonce and convert to hex (32 chars)
// This nonce is used in the Content Security Policy.
$csp_nonce = bin2hex(random_bytes(16));

// *** HTTP SECURITY HEADERS ***
// These headers help protect against XSS, clickjacking, and other attacks.
$nonce_string = "'nonce-" . $csp_nonce . "'";
header("Content-Security-Policy: default-src 'self'; " .
       // script-src: Allow 'self' (our domain) and scripts with our nonce.
       "script-src 'self' " . $nonce_string . "; " .
       // style-src: Allow 'self' and inline styles with our nonce.
       "style-src 'self' " . $nonce_string . "; " .
       "font-src 'self'; " . // Allow fonts from our domain.
       "connect-src 'self'; " . // Allow AJAX (fetch) to our domain.
       "img-src 'self' data:; " . // Allow images from our domain and 'data:' (for placeholders/inline).
       "object-src 'none'; " . // Disallow plugins (Flash, etc.).
       "frame-ancestors 'none';"); // Prevent clickjacking (disallow embedding in iframes).
header("X-Content-Type-Options: nosniff"); // Prevents browser from guessing MIME types.
// header("X-Frame-Options: DENY"); // Made redundant by CSP frame-ancestors
header("Referrer-Policy: strict-origin-when-cross-origin"); // Controls referrer info.
header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // Enforce HTTPS.
// *** END HTTP SECURITY HEADERS ***

// --- 4. Authentication ---
// Check if the user is logged in. If not, redirect to the login page.
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

try {
    // --- 5. DEPENDENCIES ---
    require 'db.php';
    require 'csrf.php';
    require 'log_helper.php';

    // Get session data for use in sub-pages
    $role = $_SESSION['role'];
    $page = $_GET['page'] ?? 'dashboard';

    // --- 6. CSV EXPORT LOGIC ---
    // This must run *before* any HTML (like header.php) is included,
    // as it needs to send its own HTTP headers for the file download.
    if (isset($_GET['export'])) {
        // Security: Block 'User' role from all exports
        if ($role == 'User') {
            $_SESSION['error'] = 'Access Denied: You do not have permission for this action.';
            header('Location: index.php?page=dashboard');
            exit;
        }
        // Route to the correct page file, which contains its own export logic.
        if ($page == 'computers') {
            include "pages/{$page}.php"; // The file will handle export and exit
        }
        if ($page == 'reports') {
            include "pages/{$page}.php"; // The file will handle export and exit
        }
    }

    // --- 7. Global CSRF VALIDATION ---
    // For *all* POST requests (e.g., add, edit, delete, checkout),
    // validate the CSRF token.
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        // This function throws an Exception if validation fails.
        validate_csrf_token();
    }

    // --- 8. Routing ---
    // Define an 'allow-list' of valid pages for security.
    // This prevents "directory traversal" attacks (e.g., ?page=../config).
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
        $page = 'dashboard'; // Default to dashboard if page is invalid
    }

    // --- 9. Role-Based Access Control (RBAC) ---
    // Enforce page-level permissions based on user role.

    // 'Admin' role cannot access 'users' or 'system_log'
    if ($role == 'Admin' && ($page == 'users' || $page == 'system_log')) {
        $_SESSION['error'] = 'Access Denied: You do not have permission to access that page.';
        $page = 'dashboard';
    }
    // 'User' role can only access a very limited set of pages
    if ($role == 'User') {
        $user_allowed = ['dashboard', 'computers', 'profile'];
        if (!in_array($page, $user_allowed)) {
            $_SESSION['error'] = 'Access Denied: You do not have permission to access that page.';
            $page = 'dashboard';
        }
    }
    // --- End RBAC ---

    // --- 10. Page Buffering and Inclusion ---
    // Start output buffering. This "catches" all HTML/output from the included
    // page file instead of sending it directly to the browser.
    ob_start();

    $page_file = "pages/{$page}.php";

    if (file_exists($page_file)) {
        // Include the requested page (e.g., pages/dashboard.php)
        // Its output is now in the buffer.
        include $page_file;
    } else {
        // 404 behavior if file is in allow-list but doesn't exist
        header("HTTP/1.1 404 Not Found");
        echo '<h1>404 Not Found</h1>';
        echo '<div class="alert alert-danger">The page you requested does not exist.</div>';
    }

    // Get the contents of the buffer (all the HTML from the page file)
    $page_content = ob_get_clean();

    // Now, include the header (which sends <html>, <head>, and nav)
    include 'partials/header.php';

    // Echo the captured page content into the main content area
    echo $page_content;

    // Finally, include the footer (which closes tags and adds JS)
    include 'partials/footer.php';

} catch (PDOException $e) {
    // --- 11. Global Error Handling ---
    // This catches critical database errors (e.g., connection failure).
    if (ob_get_level()) {
        ob_end_clean(); // Clear any partial output
    }

    // Log the full error to the server log
    error_log('Global PDOException: ' . $e->getMessage());

    // Show a user-friendly error page (we must include header/footer manually)
    // Note: We can't be sure $csp_nonce is set, so this is simple HTML.
    echo '<!doctype html><html><head><title>Error</title><link href="assets/css/bootstrap.min.css" rel="stylesheet"></head><body>';
    echo '<div class="container mt-5"><div class="alert alert-danger">
            <h1>Application Error</h1>
            <p>A critical database error occurred. Please contact support.</p>
          </div></div>';
    echo '<script src="assets/js/bootstrap.bundle.min.js"></script></body></html>';
}
