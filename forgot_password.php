<?php
require_once 'model/config.php';
require_once 'model/User.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userModel = new User();
    $gameEngine = new GameEngine();
    $email = $_POST['email'] ?? '';
    
    if (empty($email)) {
        $error = 'Please enter Email.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $response = $userModel->generatePasswordLink($email);
        if($response['response_status']) {
            // Send reset email if token is present
            if (!empty($response['data']['reset_token'])) {
                // Prepare HTML email
                $subject = "Scotland Yard Password Reset";
                $message = $gameEngine->renderHtmlTemplate('email/reset_pwd_link', $response['data'], true);
                Helper::sendEmail($email, $subject, $message);
                $_SESSION['reset_email'] = ['success_message'  => $response['message']];
                header('Location: forgot_password.php');
                exit;
            }
        } else {
            $error = $response['message'];
        }
    }
} 
if (isset($_SESSION['reset_email'])) {
    $success = isset($_SESSION['reset_email']['success_message']) ? $_SESSION['reset_email']['success_message'] : '';
    unset($_SESSION['reset_email']);
}

// Set page variables for header
$pageTitle = 'Forgot password - Scotland Yard';
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
                <h2 class="card-title">Reset your password</h2>
                <p class="text-muted">Enter your email to reset your password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>
            <?php if ($success): ?>
                <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="text" class="form-control" id="email" name="email" required>
                </div>
                
                
                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">Send recovery email</button>
                </div>
            </form>
            
            <div class="text-center mt-3">
                <p class="mb-0">Go to <a href="login.php">Sign in</a></p>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'views/layouts/footer.php';
?> 