<?php
require_once 'model/config.php';
require_once 'model/User.php';

$error = '';
$success = '';

$token = $_GET['token'] ?? '';
$showResetForm = true;
if (empty($token)) {
    $error = 'Link is invalid or expired.';
    $showResetForm = false;
} else {
    $userModel = new User();
    
    // Handle password reset form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        if (empty($password) || empty($confirmPassword)) {
            $error = 'Please enter and confirm your new password.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            $result = $userModel->resetPasswordWithToken($token, $password);
            // print_r($result);die;
            if ($result['response_status']) {
                $success = $result['message'];
            } else {
                $error = $result['message'];
                $showResetForm = (isset($result['valid_token']) && ($result['valid_token'] === true)) ? true : false;
            }
        }
    } else {
        // Validate the token
        $result = $userModel->validatePasswordResetToken($token);
        if (!$result['response_status']) {
            $error = $result['message'];
            $showResetForm = false;
        }
    }
}

// Set page variables for header
$pageTitle = 'Reset Password - Scotland Yard';
$pageClass = 'page-login';
$includeGameCSS = false;
$showNavbar = false;

// Include header
include 'views/layouts/header.php';
?>

<div class="login-container">
    <div class="card login-card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                    
                <h2 class="card-title">
                    <?= ($showResetForm) ? 'Set a new password' : 'Generate New link'; ?>
                </h2>

            </div>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            <?php if (!$success && $showResetForm): ?>
            <p class="text-muted">Enter your new password below</p>
            <form method="POST">
                <div class="mb-3">
                    <label for="password" class="form-label">New Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Reset Password</button>
                </div>
            </form>
            <?php endif; ?>
            <div class="text-center mt-3">
                <?php if ($showResetForm): ?>
                    <p class="mb-0">Go to <a href="login.php">Sign in</a></p>
                <?php else: ?>
                    <p class="mb-0">Go to <a href="forgot_password.php">Forgot Password?</a></p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'views/layouts/footer.php';
?>
