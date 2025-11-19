<?php
/**
 * Page for managing the currently logged-in user's profile.
 *
 * This file handles:
 * - Security: All actions are tied to the logged-in user's session ID.
 * - Password Change: Provides a form for the user to change their own password.
 * - Validation: Checks current password, new password match, and minimum length.
 * - Logging: Logs successful password changes to the system log.
 *
 * @global PDO $pdo The database connection object.
 * @global string $role The role of the currently logged-in user.
 */

// $pdo and $role are available from index.php

// Define minimum password length (mirroring login.php)
define('MIN_PASSWORD_LENGTH', 8);

// Handle the password change POST request
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    // Security: Get the user ID from the session, not from the form.
    $user_id = $_SESSION['user_id'];

    // 1. Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < MIN_PASSWORD_LENGTH) {
        $_SESSION['error'] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    } else {
        // Validation passed, proceed with database checks
        try {
            // 2. Get current user's hashed password from the DB
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // 3. Verify current password
            // We use password_verify() to safely compare the plain-text input
            // with the hashed password stored in the database.
            if ($user && password_verify($current_password, $user['password'])) {

                // 4. Hash and update new password
                // The current password is correct, so we can proceed.
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update_stmt->execute([$new_hashed_password, $user_id]);

                $_SESSION['success'] = 'Your password has been updated successfully.';

                // --- Log Action ---
                // Log this security event to the global system log.
                $details = "User (ID: $user_id, Username: {$_SESSION['username']}) changed their own password.";
                log_system_change($pdo, $user_id, 'Security', $details);
                // --- End Log ---

            } else {
                // Password verification failed
                $_SESSION['error'] = 'Your current password was incorrect.';
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = 'A database error occurred. Please try again.';
            // Log the detailed error for the admin, but don't show it to the user.
            error_log('Profile password change error: ' . $e->getMessage());
        }
    }

    // Redirect back to dashboard page to show the success/error message
    // Using a redirect clears the POST data and prevents resubmission on refresh.
    header('Location: index.php?page=profile');
    exit;
}

?>

<h1 class="mb-4">My Profile</h1>
<div class="row">
    <div class="col-lg-6">
        <div class="card shadow-sm rounded-3">
            <div class="card-header">
                <h5 class="mb-0">Change Password</h5>
            </div>
            <div class="card-body">
                <!-- The form submits to this same page, handled by the block above -->
                <form method="POST" action="index.php?page=profile">
                    <!-- Security: CSRF TOKEN INPUT -->
                    <?php echo csrf_input(); ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                            <button class="btn btn-outline-secondary toggle-password-btn" type="button">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <button class="btn btn-outline-secondary toggle-password-btn" type="button">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                        <div class="form-text">Must be at least <?php echo MIN_PASSWORD_LENGTH; ?> characters long.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                            <button class="btn btn-outline-secondary toggle-password-btn" type="button">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                    </div>
                    <hr class="my-4">
                    <div class="d-flex justify-content-end">
                        <button type="submit" name="change_password" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php
?>