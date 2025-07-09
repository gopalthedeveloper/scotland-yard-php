
<?php 
require_once 'email_header.php';
$link = ACTUAL_HOST_URL."/user_activate.php?token=" . $token;
?>

<h2 style="text-align: center; color: #2c3e50; margin: 0;">Scotland Yard Account Activation</h2>
<p>Hi <?=$name?>,</p>
<p>Welcome to Scotland Yard! We're thrilled to have a new detective on the case.</p>
<p>Your account has been successfully created, and you're one step away from diving into the thrilling world of mystery and strategy.</p>
<p>To activate your account and start playing, simply click the link below:</p>
<div style="text-align: center; margin: 30px 0;">
    <a href="<?=$link?>" style="background: #d33b2c; color: #fff; padding: 14px 32px; text-decoration: none; border-radius: 4px; font-size: 16px; display: inline-block;">Activate My Account</a>
</div>
<p>If the button above does not work, copy and paste this link into your browser:</p>
<p style="word-break: break-all;"><a href="<?=$link?>"><?=$link?></a></p>
<hr style="margin: 32px 0;" />
<p style="font-size: 13px; color: #888;">If you did not request this, please ignore this email.</p>
<?php 
require_once 'email_footer.php';
?>
        