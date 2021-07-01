<?php

namespace App\Helpers;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use Pusher\Pusher;

class NotificationHelper 
{

    static public function sendEmail ($to, $subject, $body) {

        $mail = new PHPMailer(true);
        try {
            //Server settings
            $mail->SMTPDebug = 0;                                         // Enable verbose debug output
            $mail->isSMTP();                                              // Set mailer to use SMTP
            $mail->Host       = empty($_ENV['EMAIL_SMTP']) ? getenv('EMAIL_SMTP') : $_ENV['EMAIL_SMTP'];  // Specify main and backup SMTP servers
            $mail->SMTPAuth   = true;                                     // Enable SMTP authentication
            $mail->Username   = empty($_ENV['EMAIL_USER']) ? getenv('EMAIL_USER') : $_ENV['EMAIL_USER'];  // SMTP username
            $mail->Password   = empty($_ENV['EMAIL_PASS']) ? getenv('EMAIL_PASS') : $_ENV['EMAIL_PASS'];  // SMTP password
            $mail->SMTPSecure = empty($_ENV['EMAIL_SECURITY']) ? getenv('EMAIL_SECURITY') : $_ENV['EMAIL_SECURITY'];  // Enable TLS encryption, `ssl` also accepted
            $mail->Port       = empty($_ENV['EMAIL_PORT']) ? getenv('EMAIL_PORT') : $_ENV['EMAIL_PORT'];  // TCP port to connect to

            //Recipients
            $mail->setFrom("no-reply@diabafrica.com", 'Diabafrica');
            $mail->addAddress($to);     // Add a recipient

            // Content
            $mail->isHTML(true);      // Set email format to HTML
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            // echo 'Message has been sent';
        } catch (Exception $e) {
            // echo "Message could not be sent. Mailer Error: {$mail->ErrorInfo}";
        }

    } 
    
    static public function sendRealTimeNotification ($channel_name, $event_name, $data) {

        $pusher = new Pusher(
            getenv('PUSHER_APP_KEY'),
            getenv('PUSHER_APP_SECRET'),
            getenv('PUSHER_APP_ID'),
            [
                'cluster' => getenv('PUSHER_CLUSTER'),
                'useTLS'  => true
            ]
        );

        $data['message'] = 'hello world';
        $pusher->trigger($channel_name, $event_name, $data);
    }
 
}
