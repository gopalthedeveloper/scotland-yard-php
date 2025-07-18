<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Get page variables (set by individual pages)
$pageTitle = $pageTitle ?? 'Scotland Yard';
$includeGameCSS = $includeGameCSS ?? false;
$includeCustomJS = $includeCustomJS ?? '';
$user = $user ?? null;

$profilePic = 'assets/images/dummy-user.svg';


// Get current user if not provided
if (!$user && isset($_SESSION['user_id'])) {
    require_once 'model/Database.php';
    $db = new Database();
    $user = $db->getUserById($_SESSION['user_id']);
}
if($user) {
    // Use user's profile image if available
    if (!empty($user['profile_image']) && file_exists($user['profile_image'])) {
        $profilePic = $user['profile_image'];
    }
}

// Get error message from session
$errorMessage = $_SESSION['game_error'] ?? '';
unset($_SESSION['game_error']); // Clear the error after displaying

// Get success message from session
$successMessage = $_SESSION['game_success'] ?? '';
unset($_SESSION['game_success']); // Clear the success after displaying

require_once 'model/config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/custom.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="assets/images/favicon.svg">
    <?php if ($includeGameCSS): ?>
    <link rel="stylesheet" href="assets/css/game.css">
    <?php endif; ?>
    <style>
        /* Sticky Footer Layout */
        html, body {
            height: 100%;
        }
        
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        
        .main-content {
            flex: 1 0 auto;
        }
        
        .footer {
            flex-shrink: 0;
            margin-top: auto;
        }
        
      
    </style>
</head>
<body class="<?= $pageClass ?? '' ?>">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark header">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
            <img src="assets/images/logo.svg" alt="Scotland Yard" height="30" class="d-inline-block align-text-top me-2">
            </a>
            <div class="navbar-nav ms-auto">
                <?php if ($user): ?>
                <div class="dropdown">
                    <a class="nav-link dropdown-toggle d-flex align-items-center " href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <img src="<?= htmlspecialchars($profilePic) ?>" alt="Scotland Yard"  class="me-2 rounded-circle  header-profile-image">
                        <span><?= htmlspecialchars($user['username']) ?></span>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                        <?php if (basename($_SERVER['PHP_SELF']) !== 'index.php'): ?>
                        <li><a class="dropdown-item" href="index.php">Home</a></li>
                        <?php endif; ?>
                        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="logout.php">Logout</a></li>
                    </ul>
                </div>
                <?php else: ?>
                <a class="nav-link" href="login.php">Login</a>
                <a class="nav-link" href="register.php">Register</a>
                <?php endif; ?>
            </div>
        </div>
    </nav>
    <div class="container-fluid mt-4">
        <div class="board-container alert-container">
    
        <?php if ($errorMessage): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($errorMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if ($successMessage): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($successMessage) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>