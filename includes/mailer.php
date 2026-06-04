<?php
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private static $instance = null;
    private $config;

    private function __construct() {
        $this->config = [
            'host' => DatabaseConfig::getSmtpHost(),
            'port' => DatabaseConfig::getSmtpPort(),
            'user' => DatabaseConfig::getSmtpUser(),
            'pass' => DatabaseConfig::getSmtpPass(),
            'from' => getenv('SMTP_FROM') ?: 'noreply@dreambd.com',
            'from_name' => getenv('SMTP_FROM_NAME') ?: 'DreamBD',
        ];
    }

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function send(string $to, string $subject, string $body): array {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host = $this->config['host'];
            $mail->SMTPAuth = true;
            $mail->Username = $this->config['user'];
            $mail->Password = $this->config['pass'];
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = (int) $this->config['port'];
            $mail->CharSet = 'UTF-8';
            $mail->SMTPDebug = 0;

            $mail->setFrom($this->config['from'], $this->config['from_name']);
            $mail->addAddress($to);
            $mail->addReplyTo($this->config['from'], $this->config['from_name']);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>'], "\n", $body));

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log("Mailer Error: " . $mail->ErrorInfo);
            return ['success' => false, 'message' => $mail->ErrorInfo];
        }
    }
}
