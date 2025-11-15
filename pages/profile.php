<?php
// $pdo and $role are available from index.php

// Define minimum password length (mirroring login.php)
define('MIN_PASSWORD_LENGTH', 8);

// Handle the password change POST request
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $user_id = $_SESSION['user_id'];

    // 1. Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $_SESSION['error'] = 'All fields are required.';
    } elseif ($new_password !== $confirm_password) {
        $_SESSION['error'] = 'New password and confirmation do not match.';
    } elseif (strlen($new_password) < MIN_PASSWORD_LENGTH) {
        $_SESSION['error'] = 'New password must be at least ' . MIN_PASSWORD_LENGTH . ' characters long.';
    } else {
        try {
            // 2. Get current user's hash
            $stmt = $pdo->prepare('SELECT password FROM users WHERE id = ?');
            $stmt->execute([$user_id]);
            $user = $stmt->fetch();

            // 3. Verify current password
            if ($user && password_verify($current_password, $user['password'])) {

                // 4. Hash and update new password
                $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare('UPDATE users SET password = ? WHERE id = ?');
                $update_stmt->execute([$new_hashed_password, $user_id]);

                $_SESSION['success'] = 'Your password has been updated successfully.';

                // --- Log Action ---
                $details = "User (ID: $user_id, Username: {$_SESSION['username']}) changed their own password.";
                log_system_change($pdo, $user_id, 'Security', $details);
                // --- End Log ---

            } else {
                $_SESSION['error'] = 'Your current password was incorrect.';
            }

        } catch (PDOException $e) {
            $_SESSION['error'] = 'A database error occurred. Please try again.';
            error_log('Profile password change error: ' . $e->getMessage());
        }
    }

    // Redirect back to dashboard page to show message
    header('Location: index.php?page=dashboard');
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
                <form method="POST" action="index.php?page=profile">
                    <!-- *** CSRF TOKEN INPUT *** -->
                    <?php echo csrf_input(); ?>

                    <div class="mb-3">
                        <label for="current_password" class="form-label">Current Password</label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>
                    <div class="mb-3">
                        <label for="new_password" class="form-label">New Password</label>
                        <input type="password" class="form-control" id="new_password" name="new_password" required>
                        <div class="form-text">Must be at least <?php echo MIN_PASSWORD_LENGTH; ?> characters long.</div>
                    </div>
                    <div class="mb-3">
                        <label for="confirm_password" class="form-label">Confirm New Password</label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
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