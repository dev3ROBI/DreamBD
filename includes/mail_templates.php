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
  <!--[if !mso]><!-->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" media="none" onload="if(media!='all')media='all'">
  <!--<![endif]-->
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
        $html = '<table class="info-table" role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:' . $bg . ';border:1px solid ' . $border . ';border-radius:14px;margin:20px 0;">';
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
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="width:28px;height:28px;"><tr><td style="background:{$border};border-radius:50%;width:28px;height:28px;text-align:center;vertical-align:middle;font-size:14px;font-weight:800;color:{$textColor};line-height:28px;">{$icon}</td></tr></table>
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
  Welcome to <strong>RobiCodes</strong>! One quick step &mdash; please verify your email address to unlock all features and secure your account.
</p>
{$btn}
<p style="margin:20px 0 8px;font-size:13px;color:#9ca3af;">Or copy and paste this URL into your browser:</p>
<p style="margin:0 0 24px;font-size:13px;color:#3b82f6;word-break:break-all;background:#f0f9ff;padding:12px 14px;border-radius:10px;border:1px solid #bfdbfe;">{$verifyUrl}</p>
<p style="margin:0;font-size:13px;color:#6b7280;line-height:1.6;">&#x1F512; This link expires in <strong>24 hours</strong>. If you did not create an account, no action is needed.</p>
HTML;
        return self::baseTemplate('Verify Your Email', $content, '#3b82f6', '#8b5cf6');
    }

    public static function resetPasswordOtp(string $username, string $otp): string {
        $greeting = self::greeting($username);
        $content = $greeting . <<<HTML
<p style="margin:0 0 24px;font-size:15px;color:#4b5563;line-height:1.7;">
  We received a request to reset your password. Use the code below &mdash; it expires in <strong>10 minutes</strong>.
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

    private static function statusBadge(string $label, string $bg, string $color): string {
        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="display:inline-block;"><tr><td style="background:' . $bg . ';border-radius:999px;padding:5px 16px;"><span style="font-size:12px;font-weight:700;color:' . $color . ';text-decoration:none;">' . $label . '</span></td></tr></table>';
    }

    private static function iconCircle(string $text, string $bg): string {
        return '<table role="presentation" cellspacing="0" cellpadding="0" border="0" style="display:inline-block;"><tr><td style="background:' . $bg . ';border-radius:50%;width:54px;height:54px;text-align:center;vertical-align:middle;"><span style="font-family:Arial,Helvetica,sans-serif;font-size:20px;font-weight:900;color:#ffffff;line-height:54px;letter-spacing:0.5px;">' . $text . '</span></td></tr></table>';
    }

    public static function orderPlaced(string $username, string $orderType, string $coinType, int $qty, float $total, int $tradeId): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $isBuy = $orderType === 'buy';
        $accentColor = $isBuy ? '#059669' : '#dc2626';
        $accentLight = $isBuy ? '#34d399' : '#f87171';
        $accentBg = $isBuy ? '#f0fdf4' : '#fef2f2';
        $accentBorder = $isBuy ? '#a7f3d0' : '#fecaca';
        $accentBadge = $isBuy ? '#d1fae5' : '#fee2e2';
        $typeLabel = $isBuy ? 'Buy Order' : 'Sell Order';
        $circleLabel = $isBuy ? 'B' : 'S';
        $circleBg = $isBuy ? '#059669' : '#dc2626';
        $instruction = $isBuy
            ? 'Send <strong>BDT ' . number_format($total, 2) . '</strong> to the merchant using the payment details shown in your dashboard. Once the merchant confirms receipt, the coins will be released to your wallet automatically.'
            : 'Your <strong>' . $qty . ' ' . ucfirst($coinType) . '</strong> coins are now held in <strong>escrow</strong> &mdash; a secure hold. Wait for the buyer to send payment, then log in to verify and release the coins.';
        $coinLabel = ucfirst($coinType) . ' Coin';
        $totalFmt = number_format($total, 2);
        $greeting = self::greeting($username);

        $hero = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background:' . $accentBg . ';border:1px solid ' . $accentBorder . ';border-radius:16px;padding:24px;text-align:center;">
      ' . self::iconCircle($circleLabel, $circleBg) . '
      <h2 style="margin:12px 0 4px;font-size:20px;font-weight:800;color:#1f2937;">Order Placed Successfully</h2>
      <p style="margin:0;font-size:14px;color:#6b7280;">Trade <strong>#' . $tradeId . '</strong> &middot; ' . $typeLabel . '</p>
    </td>
  </tr>
</table>';

        $info = self::infoBox([
            ['Trade ID', '<span style="font-weight:800;">#' . $tradeId . '</span>'],
            ['Type', '<span style="color:' . $accentColor . ';font-weight:700;">' . $typeLabel . '</span>'],
            ['Coin', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Total', '<span style="font-size:18px;font-weight:900;color:' . $accentColor . ';">BDT ' . $totalFmt . '</span>'],
        ], '#ffffff', $accentBorder);

        $instBox = self::alertBox($instruction, $accentBg, $accentBorder, $isBuy ? '#065f46' : '#991b1b', 'i');
        $btn = self::ctaButton('View in P2P Dashboard', $url . '/index.php?page=p2p', $circleBg);

        return self::baseTemplate($typeLabel . ' Placed &mdash; #' . $tradeId, $greeting . $hero . $info . $instBox . $btn, $accentColor, $accentLight);
    }

    public static function paymentConfirmed(string $username, int $tradeId, int $qty, string $coinType): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $coinLabel = ucfirst($coinType);
        $greeting = self::greeting($username);

        $hero = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background:#fefce8;border:1px solid #fde68a;border-radius:16px;padding:24px;text-align:center;">
      ' . self::iconCircle('$', '#d97706') . '
      <h2 style="margin:12px 0 4px;font-size:20px;font-weight:800;color:#92400e;">Payment Confirmed</h2>
      <p style="margin:0;font-size:14px;color:#a16207;">Buyer has sent payment for Trade <strong>#' . $tradeId . '</strong></p>
    </td>
  </tr>
</table>';

        $info = self::infoBox([
            ['Trade ID', '<span style="font-weight:800;">#' . $tradeId . '</span>'],
            ['Coin', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Status', self::statusBadge('Payment Received', '#dbeafe', '#1e40af')],
        ], '#ffffff', '#fde68a');

        $alertBox = self::alertBox(
            'Verify the payment in your mobile wallet, then click <strong>"Release Coins"</strong> in your P2P dashboard to complete the trade. The buyer is waiting for you.',
            '#fefce8', '#fde68a', '#92400e', '!'
        );
        $btn = self::ctaButton('Release Coins Now', $url . '/index.php?page=p2p', '#d97706');

        $note = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:20px 0 0;">
  <tr>
    <td style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:12px;padding:14px 18px;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
          <td width="20" valign="top" style="padding-right:10px;"><span style="color:#dc2626;font-size:16px;font-weight:700;">!</span></td>
          <td><span style="font-size:13px;color:#6b7280;line-height:1.6;">Only release coins after you have <strong>verified the payment</strong> in your bKash / Nagad / Rocket account.</span></td>
        </tr>
      </table>
    </td>
  </tr>
</table>';

        return self::baseTemplate('Payment Confirmed &mdash; Trade #' . $tradeId, $greeting . $hero . $info . $alertBox . $btn . $note, '#d97706', '#f59e0b');
    }

    public static function tradeCompleted(string $username, string $side, int $tradeId, int $qty, string $coinType): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $coinLabel = ucfirst($coinType);
        $isBuyer = $side === 'buyer';
        $summaryMsg = $isBuyer
            ? '<strong>' . $qty . ' ' . $coinLabel . '</strong> coins have been credited to your wallet.'
            : 'You have successfully sold <strong>' . $qty . ' ' . $coinLabel . '</strong> coins. The payment has been sent to your account.';
        $sideLabel = $isBuyer ? 'Buyer' : 'Seller';
        $greeting = self::greeting($username);

        $hero = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background:#f0fdf4;border:1px solid #86efac;border-radius:16px;padding:24px;text-align:center;">
      ' . self::iconCircle('OK', '#059669') . '
      <h2 style="margin:12px 0 4px;font-size:20px;font-weight:800;color:#065f46;">Trade Completed</h2>
      <p style="margin:0;font-size:14px;color:#047857;">Trade <strong>#' . $tradeId . '</strong> &middot; ' . $sideLabel . '</p>
    </td>
  </tr>
</table>';

        $info = self::infoBox([
            ['Trade ID', '<span style="font-weight:800;">#' . $tradeId . '</span>'],
            ['Role', '<span style="font-weight:700;">' . $sideLabel . '</span>'],
            ['Coin', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Status', self::statusBadge('Completed', '#d1fae5', '#065f46')],
        ], '#ffffff', '#86efac');

        $resultBox = self::alertBox($summaryMsg, '#f0fdf4', '#86efac', '#065f46', 'V');
        $btn = self::ctaButton('View Trade History', $url . '/index.php?page=p2p', '#059669');

        $reviewNote = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:20px 0 0;">
  <tr>
    <td style="background:#fffbeb;border:1px solid #fde68a;border-radius:12px;padding:14px 18px;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
          <td width="20" valign="top" style="padding-right:10px;"><span style="color:#d97706;font-size:16px;font-weight:700;">*</span></td>
          <td><span style="font-size:13px;color:#92400e;line-height:1.6;">Was your experience good? <a href="' . $url . '/index.php?page=p2p" style="color:#d97706;font-weight:700;text-decoration:underline;">Leave a review</a> for your trade partner.</span></td>
        </tr>
      </table>
    </td>
  </tr>
</table>';

        return self::baseTemplate('Trade Completed &mdash; #' . $tradeId, $greeting . $hero . $info . $resultBox . $btn . $reviewNote, '#059669', '#10b981');
    }

    public static function orderCancelled(string $username, int $tradeId, int $qty, string $coinType, string $reason): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $coinLabel = ucfirst($coinType);
        $greeting = self::greeting($username);

        if ($reason === 'user_cancelled') {
            $reasonLabel = 'Cancelled by you';
            $bg = '#fef2f2'; $border = '#fecaca'; $color = '#991b1b'; $accent = '#dc2626'; $clrLabel = 'X';
        } elseif ($reason === 'merchant_cancelled') {
            $reasonLabel = 'Cancelled by merchant';
            $bg = '#fef2f2'; $border = '#fecaca'; $color = '#991b1b'; $accent = '#dc2626'; $clrLabel = 'X';
        } else {
            $reasonLabel = 'Auto-cancelled (15 min timeout)';
            $bg = '#fff7ed'; $border = '#fed7aa'; $color = '#9a3412'; $accent = '#d97706'; $clrLabel = '!';
        }

        $hero = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background:' . $bg . ';border:1px solid ' . $border . ';border-radius:16px;padding:24px;text-align:center;">
      ' . self::iconCircle($clrLabel, $accent) . '
      <h2 style="margin:12px 0 4px;font-size:20px;font-weight:800;color:' . $color . ';">Order Cancelled</h2>
      <p style="margin:0;font-size:14px;color:' . $color . ';">' . $reasonLabel . ' &middot; Trade <strong>#' . $tradeId . '</strong></p>
    </td>
  </tr>
</table>';

        $info = self::infoBox([
            ['Trade ID', '<span style="font-weight:800;">#' . $tradeId . '</span>'],
            ['Coin', $coinLabel],
            ['Quantity', $qty . ' coins'],
            ['Reason', self::statusBadge($reasonLabel, $bg, $color)],
        ], '#ffffff', $border);

        $safetyBox = self::alertBox(
            'No coins have been transferred or lost. All funds are safe in your wallet. You may place a new order at any time.',
            '#f0fdf4', '#bbf7d0', '#065f46', 'V'
        );
        $btn = self::ctaButton('View Orders', $url . '/index.php?page=p2p', $accent);

        $subject = $reason === 'auto_timeout' ? 'Order Auto-Cancelled &mdash; #' . $tradeId : 'Order Cancelled &mdash; #' . $tradeId;
        return self::baseTemplate($subject, $greeting . $hero . $info . $safetyBox . $btn, $accent, '#fbbf24');
    }

    public static function paymentMethodUpdated(string $username, string $action, array $methods): string {
        $url = env('APP_URL', 'http://localhost/Dream');
        $actionLabel = $action === 'added' ? 'Added' : 'Updated';
        $greeting = self::greeting($username);

        $methodRows = '';
        $methodMeta = [
            'bkash'  => ['label' => 'bKash',  'color' => '#E2136E', 'bg' => '#fdf2f8', 'badgeBg' => '#fce7f3', 'short' => 'BK'],
            'nagad'  => ['label' => 'Nagad',  'color' => '#F37124', 'bg' => '#fff7ed', 'badgeBg' => '#ffedd5', 'short' => 'NG'],
            'rocket' => ['label' => 'Rocket', 'color' => '#8B1FA8', 'bg' => '#f5f3ff', 'badgeBg' => '#ede9fe', 'short' => 'RK'],
        ];
        foreach ($methods as $m) {
            $key = strtolower(trim($m['method'] ?? ''));
            if (!isset($methodMeta[$key])) continue;
            $meta = $methodMeta[$key];
            $instLabel = ($m['instruction'] ?? '') === 'send_money' ? 'Send Money' : 'Cash Out';
            $instBg = ($m['instruction'] ?? '') === 'send_money' ? '#d1fae5' : '#fef2f2';
            $instColor = ($m['instruction'] ?? '') === 'send_money' ? '#065f46' : '#991b1b';
            $number = htmlspecialchars($m['number'] ?? '');
            $methodRows .= '
  <tr>
    <td style="padding:0 0 0 0;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="background:' . $meta['bg'] . ';border:1px solid ' . $meta['badgeBg'] . ';border-radius:12px;margin-bottom:10px;">
        <tr>
          <td width="50" valign="middle" style="padding:12px 0 12px 14px;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0">
              <tr><td style="background:' . $meta['badgeBg'] . ';border-radius:10px;width:38px;height:38px;text-align:center;"><span style="font-size:12px;font-weight:800;color:' . $meta['color'] . ';line-height:38px;">' . $meta['short'] . '</span></td></tr>
            </table>
          </td>
          <td valign="middle" style="padding:12px 10px;">
            <p style="margin:0 0 2px;font-size:14px;font-weight:800;color:' . $meta['color'] . ';">' . $meta['label'] . '</p>
            <p style="margin:0;font-size:13px;color:#374151;font-family:Courier,monospace;font-weight:600;">' . $number . '</p>
          </td>
          <td align="right" valign="middle" style="padding:12px 14px 12px 6px;white-space:nowrap;">
            <table role="presentation" cellspacing="0" cellpadding="0" border="0" style="display:inline-block;">
              <tr><td style="background:' . $instBg . ';border-radius:999px;padding:3px 12px;"><span style="font-size:11px;font-weight:700;color:' . $instColor . ';">' . $instLabel . '</span></td></tr>
            </table>
          </td>
        </tr>
      </table>
    </td>
  </tr>';
        }

        $hero = '
<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 24px;">
  <tr>
    <td style="background:#f5f3ff;border:1px solid #c4b5fd;border-radius:16px;padding:24px;text-align:center;">
      ' . self::iconCircle('P', '#7c3aed') . '
      <h2 style="margin:12px 0 4px;font-size:20px;font-weight:800;color:#5b21b6;">Payment Methods ' . $actionLabel . '</h2>
      <p style="margin:0;font-size:14px;color:#7c3aed;">Your P2P payment details have been ' . strtolower($actionLabel) . ' successfully</p>
    </td>
  </tr>
</table>';

        $alertBox = self::alertBox(
            'If you did not make this change, please <a href="' . $url . '/index.php?page=p2p" style="color:#dc2626;font-weight:700;text-decoration:underline;">contact support</a> immediately.',
            '#fef2f2', '#fecaca', '#991b1b', '!'
        );
        $btn = self::ctaButton('Manage Payment Methods', $url . '/index.php?page=p2p', '#7c3aed');

        $content = $greeting . $hero .
            '<p style="margin:0 0 16px;font-size:15px;color:#4b5563;">Here are your current payment methods:</p>' .
            '<table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin:0 0 20px;">' . $methodRows . '</table>' .
            $alertBox . $btn;
        return self::baseTemplate('Payment Methods ' . $actionLabel, $content, '#7c3aed', '#a78bfa');
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
        <td style="font-size:16px;padding-right:12px;width:32px;font-weight:700;color:#8b5cf6;">[1]</td>
        <td><p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Join Tournaments</p><p style="margin:2px 0 0;font-size:12px;color:#6b7280;">Compete and win exciting prizes</p></td>
      </tr></table>
    </td>
  </tr>
  <tr>
    <td style="padding:10px 0;border-bottom:1px solid #f3f4f6;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
        <td style="font-size:16px;padding-right:12px;width:32px;font-weight:700;color:#059669;">[2]</td>
        <td><p style="margin:0;font-size:14px;color:#374151;font-weight:600;">P2P Coin Trading</p><p style="margin:2px 0 0;font-size:12px;color:#6b7280;">Buy and sell coins with verified merchants</p></td>
      </tr></table>
    </td>
  </tr>
  <tr>
    <td style="padding:10px 0;">
      <table role="presentation" cellspacing="0" cellpadding="0" border="0"><tr>
        <td style="font-size:16px;padding-right:12px;width:32px;font-weight:700;color:#3b82f6;">[3]</td>
        <td><p style="margin:0;font-size:14px;color:#374151;font-weight:600;">Community</p><p style="margin:2px 0 0;font-size:12px;color:#6b7280;">Connect with friends and fellow gamers</p></td>
      </tr></table>
    </td>
  </tr>
</table>
{$alertBox}
{$btn}
HTML;
        return self::baseTemplate('Welcome to RobiCodes!', $content, '#3b82f6', '#8b5cf6');
    }
}
