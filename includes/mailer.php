<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class Mailer {
    private static $instance = null;
    private $from;
    private $fromName;

    private function __construct() {
        $this->from = env('SMTP_FROM', 'noreply@robicodes.xyz');
        $this->fromName = env('SMTP_FROM_NAME', 'RobiCodes Support');
    }

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $fromName = null): array {
        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();
            $mail->Host = DatabaseConfig::getSmtpHost();
            $mail->SMTPAuth = true;
            $mail->Username = DatabaseConfig::getSmtpUser();
            $mail->Password = DatabaseConfig::getSmtpPass();

            $port = (int) DatabaseConfig::getSmtpPort();
            $mail->Port = $port;

            if ($port === 465) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($port === 587) {
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } else {
                $mail->SMTPSecure = false;
                $mail->SMTPAutoTLS = false;
            }

            $mail->setFrom($from ?? $this->from, $fromName ?? $this->fromName);
            $mail->addAddress($to);
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $body;
            $mail->AltBody = strip_tags(str_replace(['<br>', '<br/>', '<br />'], "\n", $body));

            $mail->send();
            return ['success' => true, 'message' => 'Email sent successfully'];
        } catch (Exception $e) {
            error_log("Mailer Error: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
