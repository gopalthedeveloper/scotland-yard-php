<?php
require_once 'model/config.php';
require_once 'model/Database.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userModel = new User();

    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($username) || empty($email) || empty($password)) {
        $error = 'All fields are required.';
    } elseif (strlen($username) < 3) {
        $error = 'Username must be at least 3 characters long.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $result = $userModel->createUser($username, $email, $password);
        if ($result['response_status']) {
            $_SESSION['success_message'] = $result['message'];
            header('Location: register.php');
            exit;
        } else {
            $error = $result['message'];
        }
    }
}
if (isset($_SESSION['success_message'])) {
    $success = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Set page variables for header
$pageTitle = 'Register - Scotland Yard';
$pageClass = 'page-register';
$includeGameCSS = false;

// Include header
include 'views/layouts/header.php';
?>

<div class="register-container">
    <div class="card register-card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h2 class="card-title">Join Scotland Yard</h2>
                <p class="text-muted">Create your detective account</p>
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
                    <input type="text" class="form-control" id="username" name="username" 
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>" required>
                    <div class="form-text">Must be at least 3 characters long.</div>
                </div>
                
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" class="form-control" id="email" name="email" 
                           value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                </div>
                
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                    <div class="form-text">Must be at least 6 characters long.</div>
                </div>
                
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Create Account</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p class="mb-0">Already have an account? <a href="login.php">Sign in here</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'views/layouts/footer.php';
?> 