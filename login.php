<?php
require_once 'model/config.php';
require_once 'model/Database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = new Database();
    
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        $user = $db->authenticateUser($username, $password);
        if ($user) {
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            if (isset($_GET['redirect']) && !empty($_GET['redirect'])) {
                header('Location: ' . $_GET['redirect']);
                exit();
            } 
            header('Location: /');
            exit();
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
if (isset($_SESSION['reset_email'])) {
    if(isset($_SESSION['reset_email']['success_message']) && !empty($_SESSION['reset_email']['success_message'])) {
        $success = $_SESSION['reset_email']['success_message'];
    } else {
        $error = isset($_SESSION['reset_email']['failure_message']) && !empty($_SESSION['reset_email']['failure_message']) ? $_SESSION['reset_email']['failure_message'] : '';
    }
    unset($_SESSION['reset_email']);
} 

// Set page variables for header
$pageTitle = 'Login - Scotland Yard';
$pageClass = 'page-login';
$includeGameCSS = false;

// Include header
include 'views/layouts/header.php';
?>

<div class="login-container">
    <div class="card login-card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h2 class="card-title">Welcome Back</h2>
                <p class="text-muted">Sign in to your Scotland Yard account</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <p class=""><span>&nbsp;</span><a href="forgot_password.php" class="float-end">Forgot Password?</a></p>

                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Sign In</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p class="mb-0">Don't have an account? <a href="register.php">Register here</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'views/layouts/footer.php';
?> 