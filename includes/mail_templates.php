<?php

class MailTemplates {
    private static $appName = 'RobiCodes';
    private static $appUrl = '';

    private static function baseTemplate(string $title, string $content): string {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"></head>
<body style="margin:0;padding:0;background:#f3f4f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f3f4f6;padding:40px 20px">
<tr><td align="center">
<table width="560" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,0.08)">
<tr><td style="background:linear-gradient(135deg,#3b82f6,#8b5cf6);padding:40px 32px;text-align:center">
<h1 style="color:#ffffff;font-size:24px;font-weight:700;margin:0">{$title}</h1>
</td></tr>
<tr><td style="padding:32px">
{$content}
</td></tr>
<tr><td style="background:#f9fafb;padding:24px 32px;text-align:center;border-top:1px solid #e5e7eb">
<p style="margin:0 0 8px;font-size:13px;color:#6b7280">Need help? Contact us at <a href="mailto:support@robicodes.xyz" style="color:#3b82f6;text-decoration:none">support@robicodes.xyz</a></p>
<p style="margin:0;font-size:12px;color:#9ca3af">&copy; 2026 RobiCodes. All rights reserved.</p>
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
    }

    public static function verifyEmail(string $username, string $verifyUrl): string {
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
Welcome to RobiCodes! Please verify your email address by clicking the button below.
</p>
<table cellpadding="0" cellspacing="0" style="margin:0 0 24px"><tr><td style="background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:12px;padding:0">
<a href="{$verifyUrl}" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none">Verify Email Address</a>
</td></tr></table>
<p style="font-size:14px;color:#6b7280;margin:0 0 8px">Or copy this link:</p>
<p style="font-size:13px;color:#3b82f6;margin:0;word-break:break-all">{$verifyUrl}</p>
<p style="font-size:14px;color:#6b7280;margin:24px 0 0;line-height:1.6">This link expires in 24 hours. If you didn't create an account, you can safely ignore this email.</p>
HTML;
        return self::baseTemplate('Verify Your Email', $content);
    }

    public static function resetPasswordOtp(string $username, string $otp): string {
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
We received a request to reset your password. Use the OTP below to set a new one.
</p>
<div style="background:#f3f4f6;border-radius:12px;padding:24px;text-align:center;margin:0 0 24px">
<span style="font-size:36px;font-weight:700;letter-spacing:8px;color:#3b82f6;font-family:monospace">{$otp}</span>
</div>
<p style="font-size:14px;color:#6b7280;margin:0 0 24px;line-height:1.6">
Enter this code on the password reset page. It expires in 10 minutes.
</p>
<p style="font-size:14px;color:#6b7280;margin:0;line-height:1.6">If you didn't request this, please ignore this email.</p>
HTML;
        return self::baseTemplate('Password Reset OTP', $content);
    }

    public static function passwordChanged(string $username, string $ip, string $device): string {
        $url = self::$appUrl ?: env('APP_URL', 'http://localhost/Dream');
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
Your password has been changed successfully.
</p>
<div style="background:#f3f4f6;border-radius:12px;padding:20px 24px;margin:0 0 24px;font-size:14px;color:#4b5563;line-height:1.8">
    <strong style="color:#374151">Device:</strong> {$device}<br>
    <strong style="color:#374151">IP Address:</strong> {$ip}
</div>
<p style="font-size:14px;color:#6b7280;margin:0 0 24px;line-height:1.6">
If you did not make this change, please <a href="{$url}/index.php?page=login" style="color:#3b82f6">reset your password</a> immediately or contact support.
</p>
HTML;
        return self::baseTemplate('Password Changed', $content);
    }

    public static function welcomeVerified(string $username): string {
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
Your email has been verified successfully! You now have full access to all RobiCodes features.
</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
Connect with friends, join tournaments, and explore the community.</p>
<table cellpadding="0" cellspacing="0" style="margin:0 0 24px"><tr><td style="background:linear-gradient(135deg,#3b82f6,#8b5cf6);border-radius:12px;padding:0">
<a href="http://localhost/Dream/index.php?page=home" style="display:inline-block;padding:14px 32px;font-size:15px;font-weight:600;color:#ffffff;text-decoration:none">Go to Homepage</a>
</td></tr></table>
HTML;
        return self::baseTemplate('Email Verified!', $content);
    }
}
