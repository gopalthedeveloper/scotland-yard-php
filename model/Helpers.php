<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;
class Helper {
    

    public static function generateRandomString($length = 10) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';

        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[random_int(0, $charactersLength - 1)];
        }

        return $randomString;
    }
    public static function sendEmail($to, $subject, $message) {     
       

        $mail = new PHPMailer(true);
        try {
            // $mail->SMTPDebug = \PHPMailer\PHPMailer\SMTP::DEBUG_SERVER;     //Enable verbose debug output

            //Server settings
            $mail->SMTPDebug = EMAIL_CONFIG['debug_mode'];     //Disable verbose debug output
            $mail->isSMTP();                                    //Send using SMTP
            $mail->Host       = EMAIL_CONFIG['smtp_host'];      //Set the SMTP server to send through
            $mail->SMTPAuth   = true;                           //Enable SMTP authentication
            $mail->Username   = EMAIL_CONFIG['smtp_user'];      //SMTP username
            $mail->Password   = EMAIL_CONFIG['smtp_pass'];      //SMTP password
            $mail->SMTPSecure = EMAIL_CONFIG['smtp_encryption'];//Enable TLS encryption
            $mail->Port       = EMAIL_CONFIG['smtp_port'];      //TCP port to connect to

            //Recipients
            $mail->setFrom(EMAIL_CONFIG['from_email'], EMAIL_CONFIG['from_name']);
            $mail->addAddress($to);                                     //Add a recipient

            //Content
            $mail->isHTML(true);                                        //Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $message;

            $mail->send();
        } catch (Exception $e) {
            error_log("Email could not be sent. Mailer Error: {$mail->ErrorInfo}");
        }
    }
}