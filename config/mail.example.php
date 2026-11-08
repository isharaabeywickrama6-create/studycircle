<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . "/../vendor/autoload.php";

function sendStudyCircleEmail(
    $to,
    $subject,
    $message,
    $isHtml = false
) {
    $mail = new PHPMailer(true);

    try {

        $mail->isSMTP();

        $mail->Host = "smtp.gmail.com";
        $mail->SMTPAuth = true;

        $mail->Username = "YOUR_GMAIL@gmail.com";

        $mail->Password = "YOUR_APP_PASSWORD";

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom(
            "studycircle.finalproject@gmail.com",
            "NSBM StudyCircle"
        );

        $mail->addAddress($to);

        $mail->isHTML($isHtml);
        $mail->Subject = $subject;
        $mail->Body = $message;

        if ($isHtml) {
            $mail->AltBody = strip_tags($message);
        }

        $mail->send();

        return true;

    } catch (Exception $e) {

        error_log(
            "StudyCircle Email Error: " . $mail->ErrorInfo
        );

        return false;
    }
}