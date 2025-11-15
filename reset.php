<?php
/**
 * Password Reset Page
 *
 * This standalone page handles the password reset flow.
 * 1. Includes minimal dependencies (DB, CSRF, Log).
 * 2. Sets strict HTTP Security Headers.
 * 3. Validates the reset token from the URL (?token=...).
 * - Checks if token exists.
 * - Checks if token has expired (expiry > NOW()).
 * 4. If token is valid, displays the password reset form.
 * 5. On POST:
 * - Validates CSRF token.
 * - Validates new password (match, length).
 * - On success: Hashes new password, updates user, and *invalidates*
 * the token by setting it to NULL in the DB.
 * - Logs the successful reset.
 * 6. Displays success or error messages.
 */

// Include minimal dependencies
require 'db.php';
require 'csrf.php'; // This also starts the session
require 'log_helper.php'; // For logging the successful reset

// *** HTTP SECURITY HEADERS (Copied from login.php) ***
// A strict CSP is vital for a password reset page.
header("Content-Security-Policy: default-src 'self'; " .
       "script-src 'self'; " .
       "style-src 'self'; " .
       "object-src 'none'; ");
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

define('MIN_PASSWORD_LENGTH', 8);
$error = '';
$success = '';
$token = $_GET['token'] ?? null;
$user = null; // This variable will hold user data if the token is valid

if (!$token) {
    $error = 'No reset token provided.';
} else {
    // --- Token Validation ---
    try {
        // 1. Find the user by the token AND check that it's not expired
        $stmt = $pdo->prepare('SELECT * FROM users WHERE password_reset_token = ? AND reset_expiry > NOW()');
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            // Token not found OR it has expired
            $error = 'This reset link is invalid or has expired. Please request a new one.';
        }

    } catch (PDOException $e) {
        $error = 'A database error occurred. Please try again later.';
        error_log('Reset Page PDOException: ' . $e->getMessage());
    }
}

// 2. Handle the form submission
// Only proceed if the form was POSTed AND our $user object is valid (from GET token)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $user) {
    // Validate CSRF token from the form
    validate_csrf_token();

    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $posted_token = $_POST['token']; // Get token from hidden form field

    // Extra check: ensure the token from the form matches the user we found
    // This protects against a theoretical case where a user opens two reset
    // links in different tabs.
    if (!hash_equals($user['password_reset_token'], $posted_token)) {
        $error = 'Token mismatch. Please refresh and try again.';
    } elseif (empty($password) || empty($confirm_password)) {
        $error = 'All fields are required.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    } else {
        // --- 3. Success! ---
        try {
            // Hash the new password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Store the user ID before we nullify the $user object
            $user_id_to_log = $user['id'];

            // Update the password AND invalidate the token
            // Setting token and expiry to NULL ensures it cannot be used again.
            $stmt = $pdo->prepare('
                UPDATE users SET 
                    password = ?, 
                    password_reset_token = NULL, 
                    reset_expiry = NULL 
                WHERE id = ?
            ');
            $stmt->execute([$hashed_password, $user_id_to_log]);

            $success = 'Your password has been reset successfully!';
            $error = ''; // Clear any previous errors

            // Log this security event
            log_system_change($pdo, $user_id_to_log, 'Security', 'User reset their password via token.');

            // Set $user to null to hide the form and show the success message
            $user = null;

        } catch (PDOException $e) {
            $error = 'A database error occurred. Please try again later.';
            error_log('Reset Page POST PDOException: ' . $e->getMessage());
        }
    }
}

?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Reset Password - Inventory System</title>
    <link href="assets/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body { background-color: #f8f9fa; }
        .reset-card { max-width: 450px; margin-top: 100px; }
    </style>
</head>
<body class="d-flex align-items-center justify-content-center min-vh-100">

    <div class="card reset-card shadow-sm rounded-3">
        <div class="card-body p-4 p-md-5">
            <h2 class="text-center mb-4">Reset Password</h2>
            
            <?php if ($success): ?>
                <!-- Show Success Message -->
                <div class="alert alert-success"><?php echo $success; ?></div>
                <div class="d-grid">
                    <a href="login.php" class="btn btn-primary">Go to Login</a>
                </div>
            <?php elseif ($error): ?>
                <!-- Show Error Message (Invalid token, expired, etc.) -->
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <div class="d-grid">
                    <a href="login.php" class="btn btn-secondary">Go to Login</a>
                </div>
            <?php elseif ($user): ?>
                <!-- Show Reset Form (Token was valid) -->
                <form method="POST">
                    <?php echo csrf_input(); ?>
                    <!-- Pass the token along in the POST request -->
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                    
                    <p>Enter a new password for <strong><?php echo htmlspecialchars($user['full_name']); ?></strong> (<?php echo htmlspecialchars($user['username']); ?>).</p>

                    <div class="mb-3">
                        <label for="password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                    </div>
                    <div class="d-grid">
                        <button type="submit" class="btn btn-primary">Set New Password</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
</body>
</html>