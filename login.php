<?php
/**
 * User Login Page
 *
 * This file handles user authentication.
 * 1. Includes CSRF and DB helpers.
 * 2. Sets strict HTTP Security Headers.
 * 3. If user is already logged in, redirects to dashboard.
 * 4. On POST:
 * - Validates CSRF token.
 * - Fetches the user from the DB by username (must be 'is_active = 1').
 * - Verifies the password using `password_verify()`.
 * - On success: Regenerates session ID, sets session data, redirects to dashboard.
 * - On failure: Shows a generic error message.
 * 5. Displays the HTML login form.
 */

// Include CSRF functions first
require 'csrf.php';
// session_start() is called inside csrf.php if not already started

// *** HTTP SECURITY HEADERS ***
// These are crucial for a login page to prevent attacks.
header("Content-Security-Policy: default-src 'self'; " .
       "script-src 'self'; " . // Only allow scripts from our domain
       "style-src 'self'; " .  // Only allow CSS from our domain
       "connect-src 'self'; " . // Allow fetch/XHR to our domain
       "img-src 'self' data:; " . // Allow images from our domain or data:
       "object-src 'none'; " . // Block plugins
       "frame-ancestors 'none'; "); // Block clickjacking
header("X-Content-Type-Options: nosniff");
//header("X-Frame-Options: DENY"); // Redundant with CSP
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains"); // Enforce HTTPS

require 'db.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

define('MIN_PASSWORD_LENGTH', 8);
$error = '';

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // 1. Validate CSRF token
    validate_csrf_token();

    $username = $_POST['username'];
    $password = $_POST['password'];

    // 2. Simple password length check (fail-fast)
    // We use the *exact same* generic error for all failures
    // to prevent "username enumeration" attacks.
    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Invalid username or password.';
    } else {
        try {
            // 3. Find user in the database
            // IMPORTANT: We only select users who are 'is_active = 1'
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            // 4. Verify password
            // `password_verify` securely compares the plain-text password
            // against the hashed password from the database.
            // This check will fail if $user is false (not found) OR password doesn't match.
            if ($user && password_verify($password, $user['password'])) {

                // --- 5. SUCCESS ---

                // Regenerate session ID to prevent session fixation attacks
                session_regenerate_id(true);

                // Store all necessary user data in the session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                // Redirect to the main application
                header('Location: index.php?page=dashboard');
                exit;
            } else {
                // --- 6. FAILURE ---
                // Generic error message
                $error = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            error_log('Login PDOException: ' . $e->getMessage());
            $error = 'A database error occurred. Please try again later.';
        }
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale-1">
    <title>Login - Inventory System</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .login-card { max-width: 400px; margin-top: 100px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

    <div class="card login-card shadow-sm rounded-3">
        <div class="card-body p-4 p-md-5">
            <h2 class="text-center mb-4">Inventory System Login</h2>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <form method="POST">
                <!-- CSRF Token Input -->
                <?php echo csrf_input(); ?>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username / Employee Number</label> 
                    <!-- Pattern matches 3 digits OR 3-20 alphanumeric chars -->
                    <input type="text" class="form-control" id="username" name="username" 
                           required 
                           pattern="(\d{3}|[a-zA-Z0-9]{3,20})" 
                           title="Enter your username or 3-digit employee number."
                           value="<?php echo isset($username) ? htmlspecialchars($username) : ''; ?>">
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>