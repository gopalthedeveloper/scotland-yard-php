<?php
require_once 'model/config.php';

// Set page variables for header
$pageTitle = '404 Not Found - Scotland Yard';
$pageClass = 'page-404';
$includeGameCSS = false;

// Include header
include 'views/layouts/header.php';
?>

<div class="login-container">
    <div class="card login-card">
        <div class="card-body p-5">
            <div class="text-center mb-4">
                <h2 class="card-title">Invalid Url</h2>
                <p class="text-muted">The URL you are trying to access is invalid.</p>
            </div>
            <div class="d-flex justify-content-center">
                <a href="index.php" class="btn btn-primary">Go to Home</a>
            </div>
        </div>
    </div>
</div>

<?php
// Include footer
include 'views/layouts/footer.php';
?> 