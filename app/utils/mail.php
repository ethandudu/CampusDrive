<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

require __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/config.php';

class Mailer {

    private $mail;

    public function __construct() {
        $this->mail = new PHPMailer(true);
        $this->mail->isSMTP();
        $this->mail->Host = MAIL_HOST;
        $this->mail->SMTPAuth = true;
        $this->mail->Username = MAIL_USERNAME;
        $this->mail->Password = MAIL_PASSWORD;
        $this->mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $this->mail->Port = 587;
        $this->mail->setFrom(MAIL_FROM, MAIL_FROM_NAME);
        $this->mail->setLanguage('fr');
        $this->mail->CharSet = PHPMailer::CHARSET_UTF8;
        $this->mail->Encoding = PHPMailer::ENCODING_BASE64;
    }

    public function sendMail($to, $subject, $body) {
        try {
            $this->mail->addAddress($to);
            $this->mail->isHTML(true);
            $this->mail->Subject = $subject;
            $this->mail->Body    = $body;
            $this->mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Mailer Error: " . $this->mail->ErrorInfo);
            return false;
        }
    }
}