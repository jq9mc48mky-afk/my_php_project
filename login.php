<?php
// Include CSRF functions first
require 'csrf.php';
// session_start() is called inside csrf.php if not already started

// *** HTTP SECURITY HEADERS ***
header("Content-Security-Policy: default-src 'self'; " .
       "script-src 'self'; " .
       "style-src 'self'; " .
       "connect-src 'self'; " .
       "img-src 'self' data:; " .
       "object-src 'none'; " .
       "frame-ancestors 'none'; ");
header("X-Content-Type-Options: nosniff");
//header("X-Frame-Options: DENY");
header("Referrer-Policy: strict-origin-when-cross-origin");
header("Strict-Transport-Security: max-age=31536000; includeSubDomains");

require 'db.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

define('MIN_PASSWORD_LENGTH', 8);
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    validate_csrf_token();

    $username = $_POST['username'];
    $password = $_POST['password'];

    if (strlen($password) < MIN_PASSWORD_LENGTH) {
        $error = 'Invalid username or password.';
    } else {
        try {
            // Find user in the database
            $stmt = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password'])) {
                session_regenerate_id(true);

                // *** UPDATED: Set session variables ***
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['full_name'];

                header('Location: index.php?page=dashboard');
                exit;
            } else {
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
    <meta name="viewport" content="width=device-width, initial-scale=1">
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
                <?php echo csrf_input(); ?>
                
                <div class="mb-3">
                    <label for="username" class="form-label">Username / Employee Number</label> <input type="text" class="form-control" id="username" name="username" 
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