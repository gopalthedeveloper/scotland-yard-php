<?php
require_once 'model/config.php';
require_once 'model/Database.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

$userModel = new User();
$uploadError = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $result = $userModel->updateProfileImage($_SESSION['user_id']);

    if ($result['response_status']) {
        $user['profile_image'] = $result['data']['profile_image'];
        $_SESSION['game_success'] = 'Profile picture updated successfully.';
        header('Location: profile.php');
        exit();
    } else {
        $uploadError = $result['message'];
    }
}

$pageTitle = 'Profile - Scotland Yard';
$pageClass = 'page-profile';
$includeGameCSS = false;

// Include header
include 'views/layouts/header.php';
?>
<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Your Profile</h4>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3">
                        <!-- Profile Pic Clickable for Modal -->
                        <div class="position-relative d-inline-block profile-image me-3">
                            <img src="<?= htmlspecialchars($profilePic) ?>" 
                                 alt="Profile picture of <?= htmlspecialchars($user['username']) ?>. Click to change your profile image. Circular avatar, neutral background." 
                                 class="me-3 w-100 h-100 image"
                                 data-bs-toggle="modal" data-bs-target="#profilePicModal">
                            <span class="position-absolute top-50 start-50 translate-middle bg-white rounded-circle d-flex align-items-center justify-content-center uploadIcon" >
                                <image src="assets/images/upload-button.svg" alt="Upload profile image" width="20" height="20" style="pointer-events:none;">
                            </span>
                        </div>
                        <div>
                            <h5 class="mb-0"><?= htmlspecialchars($user['username']) ?></h5>
                            <small class="text-muted">Joined: <?= date('M j, Y', strtotime($user['created_at'])) ?></small>
                        </div>
                    </div>
                    <hr>
                    <p><strong>Email:</strong> <?= htmlspecialchars($user['email']) ?></p>
                    <p><strong>Username:</strong> <?= htmlspecialchars($user['username']) ?></p>
                    <p><strong>Last Login:</strong> <?= $user['last_login'] ? date('M j, Y g:i A', strtotime($user['last_login'])) : 'Never' ?></p>
                    <hr>
                    <a href="logout.php" class="btn btn-danger">Logout</a>
                </div>
            </div>
        </div>
    </div>
</div>
<?php
ob_start();
?>
 <!-- Profile Pic Upload Modal -->
<div class="modal fade" id="profilePicModal" tabindex="-1" aria-labelledby="profilePicModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="profilePicModalLabel">Change Profile Picture</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="file" class="form-control" id="profile_image" name="profile_image" accept="image/jpeg,image/png,image/webp">
                    <?php if ($uploadError): ?>
                    <div class="alert alert-danger mt-2"><?= htmlspecialchars($uploadError) ?></div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
    <?php if ($uploadError): ?>
        let pictureUpload = new bootstrap.Modal(document.getElementById('profilePicModal'));
        pictureUpload.show();
    <?php endif; ?>
</script>
<?php
// Set custom JavaScript for footer
$includeCustomJS = ob_get_contents();
ob_end_clean();
// Include footer
include 'views/layouts/footer.php';
?>
