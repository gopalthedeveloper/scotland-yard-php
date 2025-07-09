<?php
require_once 'model/config.php';
require_once 'model/User.php';

$error = '';
$success = '';

$token = $_GET['token'] ?? '';

if (!empty($token)) {
    $userModel = new User();
    $result = $userModel->userActivateToken($token);
    if (!$result['response_status']) {
        $error = $result['message'];
    } else {
        $success = $result['message'];
    }
}

if( $error ) {
     $error;
} elseif ($success) {
    $_SESSION['reset_email']['success_message'] = $success;
    
} else {
    $_SESSION['reset_email']['failure_message'] =  $error?: 'Link is invalid or expired. Try resetting your password to activate your account';
}

header('Location: login.php');
exit;