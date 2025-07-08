
<?php 
require_once 'email_header.php';
$reset_link = ACTUAL_HOST_URL."/reset_password.php?token=" . $reset_token;
?>

<h2 style="text-align: center; color: #2c3e50; margin: 0;">Scotland Yard Password Reset</h2>
<p>Hello <?=$name?>,</p>
<p>We received a request to reset your password. Please click the button below to reset your password:</p>
<div style="text-align: center; margin: 30px 0;">
    <a href="<?=$reset_link?>" style="background: #d33b2c; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 4px; font-size: 16px; display: inline-block;">Reset Password</a>
</div>
<p>If the button above does not work, copy and paste this link into your browser:</p>
<p style="word-break: break-all;"><a href="<?=$reset_link?>"><?=$reset_link?></a></p>
<hr style="margin: 32px 0;" />
<p style="font-size: 13px; color: #888;">If you did not request this, please ignore this email.</p>
<?php 
require_once 'email_footer.php';
?>
        