<?php

class MailTemplates {
    private static $appName = 'RobiCodes';

    /**
     * Premium base template — table-based, 100% inline styles, mobile responsive.
     * Works in Gmail, Outlook 2007+, Apple Mail, Yahoo, and all mobile clients.
     */
    private static function baseTemplate(string $title, string $content, string $accentColor = '#8b5cf6', string $accentLight = '#6366f1'): string {
        $year = date('Y');
        $appName = self::$appName;
        return <<<HTML
<!DOCTYPE html>
<html lang="en" xmlns="http://www.w3.org/1999/xhtml">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="format-detection" content="telephone=no,date=no,address=no,email=no">
  <title>{$title}</title>
  <!--[if mso]>
  <noscript><xml><o:OfficeDocumentSettings><o:PixelsPerInch>96</o:PixelsPerInch></o:OfficeDocumentSettings></xml></noscript>
  <![endif]-->
  <style type="text/css">
    /* Reset */
    body, table, td, a { -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
    table, td { mso-table-lspace: 0pt; mso-table-rspace: 0pt; }
    img { -ms-interpolation-mode: bicubic; border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
    /* Outlook fixes */
    table { border-collapse: collapse !important; }
    body { height: 100% !important; margin: 0 !important; padding: 0 !important; width: 100% !important; }
    /* Mobile */
    @media only screen and (max-width: 600px) {
      .email-container { width: 100% !important; max-width: 100% !important; }
      .fluid { max-width: 100% !important; width: 100% !important; }
      .stack-column, .stack-column-center { display: block !important; width: 100% !important; max-width: 100% !important; }
      .hero-title { font-size: 22px !important; line-height: 1.3 !important; }
      .body-padding { padding: 24px 20px !important; }
      .info-table td { font-size: 13px !important; }
      .cta-btn { font-size: 14px !important; padding: 14px 28px !important; }
    }
  </style>
</head>
<body style="margin:0;padding:0;background-color:#f0f2f5;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'Helvetica Neue',Arial,sans-serif;">

<!-- Preheader (hidden) -->
<div style="display:none;font-size:1px;color:#f0f2f5;line-height:1px;max-height:0px;max-width:0px;opacity:0;overflow:hidden;">{$title} &mdash; {$appName}</div>

<!-- Email wrapper -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background-color:#f0f2f5;">
<tr><td align="center" style="padding:32px 12px;">

  <!-- Email container -->
  <table class="email-container" role="presentation" cellspacing="0" cellpadding="0" border="0" width="600" style="max-width:600px;width:100%;background-color:#ffffff;border-radius:20px;overflow:hidden;box-shadow:0 8px 40px rgba(0,0,0,0.10);">

    <!-- ═══ HEADER ═══ -->
    <tr>
      <td style="background:linear-gradient(135deg,#0f172a 0%,#1e1b4b 50%,#1a0533 100%);padding:36px 40px;text-align:center;">
        <!-- Logo area -->
        <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
          <tr>
            <td align="center">
              <!-- App icon -->
              <div style="display:inline-block;width:52px;height:52px;background:linear-gradient(135deg,{$accentColor},{$accentLight});border-radius:16px;margin-bottom:14px;line-height:52px;text-align:center;font-size:24px;">&#9734;</div>
              <h1 class="hero-title" style="margin:0;font-size:26px;font-weight:800;color:#ffffff;letter-spacing:-0.5px;line-height:1.2;">{$appName}</h1>
              <p style="margin:6px 0 0;font-size:13px;color:#94a3b8;letter-spacing:0.5px;text-transform:uppercase;">{$title}</p>
            </td>
          </tr>
        </table>
      </td>
    </tr>

    <!-- ═══ BODY ═══ -->
    <tr>
      <td class="body-padding" style="padding:36px 40px;">
        {$content}
      </td>
    </tr>

    <!-- ═══ DIVIDER ═══ -->
    <tr>
      <td style="padding:0 40px;">
        <div style="height:1px;background:linear-gradient(90deg,transparent,#e5e7eb,transparent);"></div>
      </td>
    </tr>

    <!-- ═══ FOOTER ═══ -->
    <tr>
      <td style="padding:24px 40px 32px;text-align:center;background-color:#f9fafb;">
        <p style="margin:0 0 8px;font-size:13px;color:#6b7280;line-height:1.6;">
          Questions? <a href="mailto:support@robicodes.xyz" style="color:{$accentColor};text-decoration:none;font-weight:600;">support@robicodes.xyz</a>
        </p>
        <p style="margin:0 0 12px;font-size:12px;color:#9ca3af;line-height:1.6;">
          This email was sent to you because you have an account on <strong style="color:#6b7280;">{$appName}</strong>.<br>
          If you didn't request this, please ignore this email.
        </p>
        <p style="margin:0;font-size:11px;color:#d1d5db;">&copy; {$year} {$appName}. All rights reserved.</p>
      </td>
    </tr>

  </table>
  <!-- END Email container -->

</td></tr>
</table>
<!-- END Email wrapper -->

</body>
</html>
HTML;
    }

    /** Reusable: greeting line */
    private static function greeting(string $username): string {
        return '<p style="margin:0 0 20px;font-size:16px;color:#374151;line-height:1.6;">Hi <strong style="color:#111827;">' . htmlspecialchars($username) . '</strong>,</p>';
    }

    /** Reusable: CTA button */
    private static function ctaButton(string $label, string $url, string $bg = '#8b5cf6'): string {
        return <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="margin:24px 0 8px;">
  <tr>
    <td style="border-radius:14px;background:{$bg};" align="center">
      <a class="cta-btn" href="{$url}" target="_blank" style="display:inline-block;padding:15px 34px;font-size:15px;font-weight:700;color:#ffffff;text-decoration:none;border-radius:14px;letter-spacing:0.2px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,sans-serif;">{$label}</a>
    </td>
  </tr>
</table>
HTML;
    }

    /** Reusable: info/detail box */
    private static function infoBox(array $rows, string $bg = '#f8fafc', string $border = '#e5e7eb'): string {
        $html = '<table class="info-table" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:' . $bg . ';border:1px solid ' . $border . ';border-radius:14px;overflow:hidden;margin:20px 0;">';
        foreach ($rows as $i => $row) {
            $isLast = $i === array_key_last($rows);
            $borderBottom = $isLast ? '' : 'border-bottom:1px solid ' . $border . ';';
            $html .= '<tr>';
            $html .= '<td style="padding:12px 18px;' . $borderBottom . 'font-size:13px;color:#6b7280;font-weight:500;width:40%;">' . $row[0] . '</td>';
            $html .= '<td style="padding:12px 18px;' . $borderBottom . 'font-size:14px;color:#111827;font-weight:700;text-align:right;">' . $row[1] . '</td>';
            $html .= '</tr>';
        }
        $html .= '</table>';
        return $html;
    }

    /** Reusable: alert/highlight box */
    private static function alertBox(string $text, string $bg = '#f0fdf4', string $border = '#bbf7d0', string $textColor = '#065f46', string $icon = '✓'): string {
        return <<<HTML
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:20px 0;">
  <tr>
    <td style="background:{$bg};border:1.5px solid {$border};border-radius:14px;padding:18px 20px;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
          <td width="32" valign="top" style="padding-right:12px;">
            <div style="width:28px;height:28px;border-radius:50%;background:{$border};display:flex;align-items:center;justify-content:center;font-size:14px;font-weight:800;color:{$textColor};text-align:center;line-height:28px;">{$icon}</div>
          </td>
          <td style="font-size:14px;color:{$textColor};font-weight:600;line-height:1.6;">{$text}</td>
        </tr>
      </table>
    </td>
  </tr>
</table>
HTML;
    }

    // ─── PUBLIC TEMPLATE METHODS ───────────────────────────────────────────

    public static function verifyEmail(string $username, string $verifyUrl): string {
        $greeting = self::greeting($username);
        $btn = self::ctaButton('&#10003; Verify Email Address', $verifyUrl, 'linear-gradient(135deg,#3b82f6,#8b5cf6)');
        $content = $greeting . <<<HTML
<p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.7;">
  Welcome to <strong>RobiCodes</strong>! One quick step — please verify your email address to unlock all features and secure your account.
</p>
{$btn}
<p style="margin:20px 0 8px;font-size:13px;color:#9ca3af;">Or copy and paste this URL into your browser:</p>
<p style="margin:0 0 24px;font-size:13px;color:#3b82f6;word-break:break-all;background:#f0f9ff;padding:12px 14px;border-radius:10px;border:1px solid #bfdbfe;">{$verifyUrl}</p>
<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">&#128274; This link expires in <strong>24 hours</strong>. If you did not create an account, no action is needed.</p>
HTML;
        return self::baseTemplate('Verify Your Email', $content, '#3b82f6', '#8b5cf6');
    }

    public static function resetPasswordOtp(string $username, string $otp): string {
        $greeting = self::greeting($username);
        $content = $greeting . <<<HTML
<p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.7;">
  We received a request to reset your password. Use the code below — it expires in <strong>10 minutes</strong>.
</p>
<!-- OTP box -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td align="center" style="background:linear-gradient(135deg,#f0f9ff,#eff6ff);border:2px dashed #93c5fd;border-radius:16px;padding:28px 20px;">
      <p style="margin:0 0 8px;font-size:12px;font-weight:700;letter-spacing:2px;text-transform:uppercase;color:#6b7280;">One-Time Password</p>
      <p style="margin:0;font-size:42px;font-weight:800;letter-spacing:12px;color:#1d4ed8;font-family:'Courier New',Courier,monospace;line-height:1;">{$otp}</p>
      <p style="margin:10px 0 0;font-size:12px;color:#9ca3af;">Valid for 10 minutes only</p>
    </td>
  </tr>
</table>
<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">&#128272; Enter this code on the password reset page. If you did not request a reset, please secure your account immediately.</p>
HTML;
        return self::baseTemplate('Password Reset OTP', $content, '#3b82f6', '#6366f1');
    }

    public static function passwordChanged(string $username, string $ip, string $device): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $greeting = self::greeting($username);
        $infoBox = self::infoBox([
            ['Device', htmlspecialchars($device)],
            ['IP Address', htmlspecialchars($ip)],
            ['Time', date('d M Y, h:i A')],
        ]);
        $alertBox = self::alertBox(
            'Your password has been updated successfully.',
            '#f0fdf4', '#86efac', '#15803d', '✓'
        );
        $content = $greeting . $alertBox . <<<HTML
<p style="margin:0 0 16px;font-size:15px;color:#4b5563;line-height:1.7;">Here are the details of this change:</p>
{$infoBox}
<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.7;">&#128680; If you did not make this change, <a href="{$url}/index.php?page=login" style="color:#dc2626;font-weight:700;">reset your password immediately</a> or contact our support team.</p>
HTML;
        return self::baseTemplate('Password Changed', $content, '#8b5cf6', '#6366f1');
    }

    public static function orderPlaced(string $username, string $orderType, string $coinType, int $qty, float $total, int $tradeId): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $isBuy = $orderType === 'buy';
        $typeLabel = $isBuy ? '🟢 Buy Order' : '🔴 Sell Order';
        $accentColor = $isBuy ? '#059669' : '#dc2626';
        $accentLight = $isBuy ? '#10b981' : '#ef4444';
        $instruction = $isBuy
            ? 'Please pay the merchant using the payment details shown in your <strong>P2P Dashboard → Orders</strong>. Your order will be completed once the merchant releases the coins.'
            : 'Your coins are now held in <strong>escrow</strong>. Wait for the buyer to send payment, then log in to verify and release the coins.';
        $coinLabel = ucfirst($coinType) . ' Coin';
        $totalFmt = number_format($total, 2);
        $greeting = self::greeting($username);
        $infoBox = self::infoBox([
            ['Trade ID', '#' . $tradeId],
            ['Order Type', $typeLabel],
            ['Coin Type', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Total Amount', '<span style="font-size:18px;color:' . $accentColor . ';">৳' . $totalFmt . '</span>'],
        ]);
        $btn = self::ctaButton('&#128202; View in P2P Dashboard', $url . '/index.php?page=p2p', $accentColor);
        $instBox = self::alertBox($instruction, $isBuy ? '#f0fdf4' : '#fff7ed', $isBuy ? '#86efac' : '#fdba74', $isBuy ? '#15803d' : '#c2410c', $isBuy ? '💳' : '🔒');
        $content = $greeting . <<<HTML
<p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
  Your P2P {$orderType} order has been <strong>placed successfully</strong>! Here's a summary:
</p>
{$infoBox}
{$instBox}
{$btn}
HTML;
        return self::baseTemplate('Order Placed — #' . $tradeId, $content, $accentColor, $accentLight);
    }

    public static function paymentConfirmed(string $username, int $tradeId, int $qty, string $coinType): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $coinLabel = ucfirst($coinType);
        $greeting = self::greeting($username);
        $infoBox = self::infoBox([
            ['Trade ID', '#' . $tradeId],
            ['Coin Type', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Status', '<span style="background:#dbeafe;color:#1e40af;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700;">Payment Received &#128197;</span>'],
        ]);
        $alertBox = self::alertBox(
            'The buyer has confirmed payment. Please log in, verify you received the payment, then click <strong>"Release Coins"</strong> to complete the trade.',
            '#fff7ed', '#fed7aa', '#c2410c', '⚡'
        );
        $btn = self::ctaButton('&#10003; Go to P2P Dashboard', $url . '/index.php?page=p2p', 'linear-gradient(135deg,#f59e0b,#d97706)');
        $content = $greeting . <<<HTML
<p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
  Good news! Payment has been marked as confirmed for <strong>Trade #{$tradeId}</strong>. Action required on your part:
</p>
{$infoBox}
{$alertBox}
{$btn}
<p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.6;">&#128275; Only release coins after you have verified the payment in your bank/mobile wallet app.</p>
HTML;
        return self::baseTemplate('Payment Confirmed — Trade #' . $tradeId, $content, '#f59e0b', '#d97706');
    }

    public static function tradeCompleted(string $username, string $side, int $tradeId, int $qty, string $coinType): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $coinLabel = ucfirst($coinType);
        $isBuyer = $side === 'buyer';
        $summaryMsg = $isBuyer
            ? "<strong>{$qty} {$coinLabel} coins</strong> have been added to your wallet. Enjoy!"
            : "You have successfully sold <strong>{$qty} {$coinLabel} coins</strong>. Payment should be in your account.";
        $sideLabel = $isBuyer ? '🛒 Buyer' : '💰 Seller';
        $greeting = self::greeting($username);
        $infoBox = self::infoBox([
            ['Trade ID', '#' . $tradeId],
            ['Your Role', $sideLabel],
            ['Coin Type', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Status', '<span style="background:#d1fae5;color:#065f46;padding:2px 10px;border-radius:999px;font-size:12px;font-weight:700;">&#10003; Completed</span>'],
        ]);
        $alertBox = self::alertBox($summaryMsg, '#f0fdf4', '#86efac', '#065f46', '🎉');
        $btn = self::ctaButton('&#128200; View Trade History', $url . '/index.php?page=p2p', 'linear-gradient(135deg,#059669,#10b981)');
        $content = $greeting . <<<HTML
<p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
  <strong>Trade #{$tradeId} has been completed!</strong> Thank you for trading on RobiCodes P2P.
</p>
{$infoBox}
{$alertBox}
{$btn}
<p style="margin:16px 0 0;font-size:13px;color:#6b7280;line-height:1.6;">&#11088; Was your experience good? Consider leaving a review for your trade partner from the P2P Orders tab.</p>
HTML;
        return self::baseTemplate('Trade Completed — #' . $tradeId, $content, '#059669', '#10b981');
    }

    public static function paymentMethodUpdated(string $username, string $action, array $methods): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $actionLabel = $action === 'added' ? 'added' : 'updated';
        $greeting = self::greeting($username);

        // Build method rows
        $methodRows = '';
        $methodMeta = [
            'bkash'  => ['label' => 'bKash',  'color' => '#E2136E', 'bg' => '#fce7f3', 'icon' => '📱'],
            'nagad'  => ['label' => 'Nagad',  'color' => '#F37124', 'bg' => '#fff7ed', 'icon' => '💳'],
            'rocket' => ['label' => 'Rocket', 'color' => '#8B1FA8', 'bg' => '#f5f3ff', 'icon' => '🚀'],
        ];
        foreach ($methods as $m) {
            $key = strtolower(trim($m['method'] ?? ''));
            if (!isset($methodMeta[$key])) continue;
            $meta = $methodMeta[$key];
            $instLabel = ($m['instruction'] ?? '') === 'send_money' ? 'Send Money' : 'Cash Out';
            $instBg = ($m['instruction'] ?? '') === 'send_money' ? '#d1fae5' : '#fef2f2';
            $instColor = ($m['instruction'] ?? '') === 'send_money' ? '#065f46' : '#991b1b';
            $number = htmlspecialchars($m['number'] ?? '');
            $methodRows .= <<<ROW
<tr>
  <td style="padding:14px 18px;border-bottom:1px solid #f3f4f6;">
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
      <tr>
        <td width="44" valign="middle" style="padding-right:14px;">
          <div style="width:44px;height:44px;border-radius:12px;background:{$meta['bg']};display:block;text-align:center;line-height:44px;font-size:22px;">{$meta['icon']}</div>
        </td>
        <td valign="middle">
          <p style="margin:0 0 2px;font-size:15px;font-weight:700;color:{$meta['color']};">{$meta['label']}</p>
          <p style="margin:0;font-size:13px;color:#4b5563;font-family:'Courier New',Courier,monospace;letter-spacing:0.5px;">{$number}</p>
        </td>
        <td align="right" valign="middle">
          <span style="display:inline-block;padding:4px 12px;border-radius:999px;font-size:11px;font-weight:700;background:{$instBg};color:{$instColor};">{$instLabel}</span>
        </td>
      </tr>
    </table>
  </td>
</tr>
ROW;
        }

        $alertBox = self::alertBox(
            'If you did not make this change, please <a href="' . $url . '/index.php?page=p2p" style="color:#dc2626;font-weight:700;">contact support</a> immediately to secure your account.',
            '#fef2f2', '#fecaca', '#991b1b', '⚠'
        );
        $btn = self::ctaButton('&#9881; Manage Payment Methods', $url . '/index.php?page=p2p', '#8b5cf6');

        $content = $greeting . <<<HTML
<p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
  Your P2P payment method(s) have been <strong>{$actionLabel}</strong> successfully. Here are the current details on your account:
</p>
<!-- Method list -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;margin:0 0 20px;">
  {$methodRows}
</table>
{$alertBox}
{$btn}
HTML;
        return self::baseTemplate('Payment Method ' . ucfirst($actionLabel), $content, '#8b5cf6', '#6366f1');
    }

    public static function welcomeVerified(string $username): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $greeting = self::greeting($username);
        $alertBox = self::alertBox('Your email has been verified. You now have full access to RobiCodes!', '#f0fdf4', '#86efac', '#065f46', '✓');
        $btn = self::ctaButton('&#127968; Go to Homepage', $url . '/index.php?page=home', 'linear-gradient(135deg,#3b82f6,#8b5cf6)');
        $content = $greeting . <<<HTML
<p style="margin:0 0 20px;font-size:15px;color:#4b5563;line-height:1.7;">
  Welcome aboard! Your account is now <strong>fully verified and active</strong>. Here's what you can do now:
</p>
<!-- Feature highlights -->
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
        <td style="font-size:18px;padding-right:12px;width:32px;">🏆</td>
        <td><p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Join Tournaments</p><p style="margin:2px 0 0;font-size:12px;color:#6b7280;">Compete and win exciting prizes</p></td>
      </tr></table>
    </td>
  </tr>
  <tr>
    <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
        <td style="font-size:18px;padding-right:12px;width:32px;">🪙</td>
        <td><p style="margin:0;font-size:14px;color:#374151;font-weight:600;">P2P Coin Trading</p><p style="margin:2px 0 0;font-size:12px;color:#6b7280;">Buy and sell coins with verified merchants</p></td>
      </tr></table>
    </td>
  </tr>
  <tr>
    <td style="padding:10px 0;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
        <td style="font-size:18px;padding-right:12px;width:32px;">👥</td>
        <td><p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Community</p><p style="margin:2px 0 0;font-size:12px;color:#6b7280;">Connect with friends and fellow gamers</p></td>
      </tr></table>
    </td>
  </tr>
</table>
{$alertBox}
{$btn}
HTML;
        return self::baseTemplate('Welcome to RobiCodes! 🎉', $content, '#3b82f6', '#8b5cf6');
    }
}
