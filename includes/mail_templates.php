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

    public static function orderPlaced(string $username, string $orderType, string $coinType, int $qty, float $total, int $tradeId): string {
        if ($orderType === 'buy') {
            $inst = 'Pay the merchant using the payment details in your P2P dashboard to complete the trade.';
            $msg = 'Your order to buy coins has been placed successfully!';
        } else {
            $inst = 'Your coins are held in escrow. Wait for the merchant to send you payment, then release the coins.';
            $msg = 'Your order to sell coins has been placed successfully!';
        }
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">{$msg}</p>
<div style="background:#f9fafb;border-radius:12px;padding:20px 24px;margin:0 0 24px;border:1px solid #e5e7eb">
<table width="100%" cellpadding="0" cellspacing="0" style="font-size:14px;color:#374151">
<tr><td style="padding:4px 0;color:#6b7280">Trade ID</td><td style="padding:4px 0;font-weight:700;text-align:right">#{$tradeId}</td></tr>
<tr><td style="padding:4px 0;color:#6b7280">Order Type</td><td style="padding:4px 0;font-weight:700;text-align:right;text-transform:capitalize">{$orderType}</td></tr>
<tr><td style="padding:4px 0;color:#6b7280">Coin</td><td style="padding:4px 0;font-weight:700;text-align:right;text-transform:capitalize">{$coinType}</td></tr>
<tr><td style="padding:4px 0;color:#6b7280">Quantity</td><td style="padding:4px 0;font-weight:700;text-align:right">{$qty}</td></tr>
<tr><td style="padding:4px 0;color:#6b7280;border-top:1px solid #e5e7eb;padding-top:8px">Total</td><td style="padding:4px 0;font-weight:800;text-align:right;border-top:1px solid #e5e7eb;padding-top:8px;font-size:18px">৳{$total}</td></tr>
</table>
</div>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">{$inst}</p>
<p style="font-size:14px;color:#6b7280;margin:0;line-height:1.6">
<a href="http://localhost/Dream/index.php?page=p2p" style="color:#8b5cf6;font-weight:600">View in P2P Dashboard</a>
</p>
HTML;
        return self::baseTemplate('Order Placed - #' . $tradeId, $content);
    }

    public static function paymentConfirmed(string $username, int $tradeId, int $qty, string $coinType): string {
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
The buyer has confirmed payment for Trade #{$tradeId}. Please verify and release the coins.
</p>
<div style="background:#f9fafb;border-radius:12px;padding:20px 24px;margin:0 0 24px;border:1px solid #e5e7eb;text-align:center">
<p style="font-size:14px;color:#6b7280;margin:0 0 4px">Trade #{$tradeId} — {$qty} {$coinType}</p>
<p style="font-size:13px;color:#6b7280;margin:0">Release the coins to the buyer once payment is verified.</p>
</div>
<p style="font-size:14px;color:#6b7280;margin:0;line-height:1.6">
<a href="http://localhost/Dream/index.php?page=p2p" style="color:#8b5cf6;font-weight:600">Go to P2P Dashboard</a> to release coins.
</p>
HTML;
        return self::baseTemplate('Payment Confirmed - Trade #' . $tradeId, $content);
    }

    public static function tradeCompleted(string $username, string $side, int $tradeId, int $qty, string $coinType): string {
        $msg = $side === 'buyer' ? "Your purchased {$qty} {$coinType} coins have been released to your wallet." : "You have successfully sold {$qty} {$coinType} coins. The buyer has received them.";
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
Trade #{$tradeId} has been completed successfully!
</p>
<div style="background:#d1fae5;border-radius:12px;padding:20px 24px;margin:0 0 24px;border:1px solid #a7f3d0;text-align:center">
<p style="font-size:16px;color:#065f46;font-weight:700;margin:0">{$msg}</p>
</div>
<p style="font-size:14px;color:#6b7280;margin:0 0 24px;line-height:1.6">
Thank you for trading on RobiCodes P2P!
</p>
<p style="font-size:14px;color:#6b7280;margin:0;line-height:1.6">
<a href="http://localhost/Dream/index.php?page=p2p" style="color:#8b5cf6;font-weight:600">View Trade History</a>
</p>
HTML;
        return self::baseTemplate('Trade Completed - #' . $tradeId, $content);
    }

    public static function paymentMethodUpdated(string $username, string $action, array $methods): string {
        $rows = '';
        foreach ($methods as $m) {
            $label = ['bkash'=>'bKash','nagad'=>'Nagad','rocket'=>'Rocket'][$m['method']] ?? ucfirst($m['method']);
            $color = ['bkash'=>'#E2136E','nagad'=>'#F37124','rocket'=>'#CF2027'][$m['method']] ?? '#6b7280';
            $cid = $m['method'] === 'bkash' ? 'bkash-logo' : ($m['method'] === 'nagad' ? 'nagad-logo' : 'rocket-logo');
            $instLabel = $m['instruction'] === 'send_money' ? 'Send Money' : 'Cash Out';
            $instColor = $m['instruction'] === 'send_money' ? '#059669' : '#dc2626';
            $instIcon = $m['instruction'] === 'send_money' ? '↑' : '↓';
            $rows .= <<<ROW
<tr>
  <td style="padding:12px 0;border-bottom:1px solid #e5e7eb">
  <table width="100%" cellpadding="0" cellspacing="0">
  <tr>
    <td width="44" valign="middle" style="padding-right:14px">
      <table cellpadding="0" cellspacing="0" style="width:44px;height:44px;border-radius:10px;background:#ffffff">
        <tr><td align="center" valign="middle" style="font-size:0;line-height:0">
          <img src="cid:{$cid}" alt="{$label}" width="44" height="44" style="display:block;width:44px;height:44px;border-radius:10px;border:0;outline:none">
        </td></tr>
      </table>
    </td>
    <td valign="middle">
      <p style="font-size:16px;font-weight:700;color:#111827;margin:0 0 2px">{$label}</p>
      <p style="font-size:14px;color:#4b5563;margin:0;font-family:monospace">{$m['number']}</p>
    </td>
    <td width="120" align="right" valign="middle">
      <span style="display:inline-block;padding:4px 14px;border-radius:20px;font-size:12px;font-weight:700;color:#ffffff;background:{$instColor}">{$instIcon} {$instLabel}</span>
    </td>
  </tr>
  </table>
  </td>
</tr>
ROW;
        }
        $content = <<<HTML
<p style="font-size:16px;color:#374151;margin:0 0 24px">Hi <strong>{$username}</strong>,</p>
<p style="font-size:15px;color:#4b5563;margin:0 0 24px;line-height:1.6">
Your P2P payment method(s) have been <strong>{$action}</strong>. Here are the details:
</p>
<div style="background:#f9fafb;border-radius:12px;padding:0 24px;margin:0 0 24px;border:1px solid #e5e7eb">
<table width="100%" cellpadding="0" cellspacing="0">
{$rows}
</table>
</div>
<p style="font-size:14px;color:#6b7280;margin:0 0 24px;line-height:1.6">
You can manage your payment methods anytime from your P2P Payment Settings.
</p>
<p style="font-size:14px;color:#6b7280;margin:0;line-height:1.6">
If you didn't make this change, please contact support immediately.
</p>
HTML;
        return self::baseTemplate('Payment Method ' . ucfirst($action), $content);
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
