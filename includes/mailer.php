<?php

class Mailer {
    private static $instance = null;
    private $apiKey;
    private $from;
    private $fromName;

    private function __construct() {
        $this->apiKey = DatabaseConfig::getSmtpPass();
        $this->from = getenv('SMTP_FROM') ?: 'noreply@robicodes.xyz';
        $this->fromName = getenv('SMTP_FROM_NAME') ?: 'RobiCodes Support';
    }

    public static function getInstance() {
        if (self::$instance === null) self::$instance = new self();
        return self::$instance;
    }

    public function send(string $to, string $subject, string $body, ?string $from = null, ?string $fromName = null): array {
        $payload = json_encode([
            'sender' => ['name' => $fromName ?? $this->fromName, 'email' => $from ?? $this->from],
            'to' => [['email' => $to]],
            'subject' => $subject,
            'htmlContent' => $body,
        ]);

        $ch = curl_init('https://api.brevo.com/v3/smtp/email');
        $caPath = 'D:\xampp\php\cacert.pem';
        $caOpts = file_exists($caPath)
            ? [CURLOPT_CAINFO => realpath($caPath)]
            : [CURLOPT_SSL_VERIFYPEER => false];
        curl_setopt_array($ch, $caOpts + [
            CURLOPT_HTTPHEADER => [
                'api-key: ' . $this->apiKey,
                'Content-Type: application/json',
                'Accept: application/json',
            ],
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            error_log("Mailer cURL Error: " . $error);
            return ['success' => false, 'message' => 'Network error: ' . $error];
        }

        if ($httpCode >= 200 && $httpCode < 300) {
            return ['success' => true, 'message' => 'Email sent successfully'];
        }

        $data = json_decode($response, true);
        $msg = $data['message'] ?? 'HTTP ' . $httpCode;
        error_log("Mailer API Error: $msg — Response: " . substr($response, 0, 500));
        return ['success' => false, 'message' => $msg];
    }
}
