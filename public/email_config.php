<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Detect PHPMailer availability (optional in local dev)
// Prefer non-public libs path; fallback to public path if present
$__mailerBaseLibs  = realpath(__DIR__ . '/../libs/PHPMailer/src/PHPMailer.php');
$__mailerBasePublic = realpath(__DIR__ . '/PHPMailer/src/PHPMailer.php');
$__mailerPath = $__mailerBaseLibs ?: $__mailerBasePublic;
$__hasMailer = $__mailerPath && file_exists($__mailerPath);
if ($__hasMailer) {
    $baseDir = dirname($__mailerPath);
    require $baseDir . '/Exception.php';
    require $baseDir . '/PHPMailer.php';
    require $baseDir . '/SMTP.php';
}

define('SMTP_HOST', '127.0.0.1');
define('SMTP_PORT', 1025);
define('SMTP_FROM_EMAIL', 'no-reply@stirlings.com');
define('SMTP_FROM_NAME', "Stirling's");
define('DEV_OTP_RECIPIENT', 'bshen002@e.ntu.edu.sg');
define('DEV_MODE', false);

function sendEmail($to, $subject, $body) {
    global $__hasMailer;

    // Prefer PHPMailer if available
    if ($__hasMailer) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->Port       = SMTP_PORT;
            $mail->SMTPAuth   = false;
            $mail->SMTPSecure = '';
            // For Mailpit: keep plain SMTP and avoid auto STARTTLS
            $mail->SMTPAutoTLS = false;
            // Enable debug output to PHP error log for troubleshooting
            $mail->SMTPDebug = 2; // set to 0 to silence after confirming
            $mail->Debugoutput = function($str, $level) {
                error_log("SMTP debug (level {$level}): " . $str);
            };

            $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
            $mail->addAddress($to);

            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $body;

            $mail->send();
            return true;
        } catch (Exception $e) {
            error_log("Email Error: {$mail->ErrorInfo}");
            // fall through to dev fallback below
        }
    }

    // Dev fallback: log the email locally and report success so flows continue
    if (DEV_MODE) {
        $logDir = __DIR__ . '/assets';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/otp_dev.log';
        $log = "[" . date('Y-m-d H:i:s') . "] TO: {$to}\nSUBJECT: {$subject}\nBODY:\n{$body}\n\n";
        @file_put_contents($logFile, $log, FILE_APPEND);
        return true;
    }

    return false;
}

function sendOTP($email, $otp) {
    $subject = "Stirling's - Email Verification";
    $body = <<<HTML
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="x-apple-disable-message-reformatting">
  <style>
    body { margin:0; padding:0; background:#eeeeee; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:#222222; }
    .container { max-width:600px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.08); }
    .header { background:#7A553D; color:#ffffff; padding:24px 20px; text-align:center; }
    .brand { margin:0; font-size:24px; font-weight:700; letter-spacing:.2px; }
    .sub { margin:6px 0 0; opacity:.9; }
    .content { padding:32px 28px; }
    .h2 { margin:0 0 8px 0; font-size:20px; }
    .muted { color:#7e5d3f; margin:0 0 16px 0; }
    .otp-box { background:#f7f2ec; padding:24px; text-align:center; border-radius:10px; margin:24px 0; }
    .otp { font-size:32px; font-weight:800; color:#FA6000; letter-spacing:10px;}
    .notice { border: 1px solid #7a553d; border-left:4px solid #7a553d; padding:12px 14px; margin:18px 0; border-radius:6px; color:#7a553d; }
    .footer { background:#f8f9fa; color:#7e5d3f; text-align:center; font-size:12px; padding:16px; }
  </style>
  <title>Stirling OTP</title>
  </head>
  <body>
    <div class="container">
      <div class="header">
      <img src="http://localhost/IE4727-Final-Project/public/assets/images/logo.svg" alt="Stirling's Logo" width="140" style="display:block;margin:0 auto;filter:brightness(0) invert(1);">
        <div class="sub">Email Verification</div>
      </div>
      <div class="content">
        <h2 class="h2">Welcome!</h2>
        <p class="muted">Use the verification code below to verify your email.</p>
        <div class="otp-box"><div class="otp">{$otp}</div></div>
        <div class="notice"><em>This OTP is only valid for <strong>10 minutes</strong>.</em></div>
        <p class="muted">If you didn&apos;t request this, you can safely ignore this email.</p>
      </div>
      <div class="footer">&copy; 2025 Stirling&apos;s, Shen Bowen &amp; Shirsho Sinha.</div>
    </div>
  </body>
</html>
HTML;

    return sendEmail($email, $subject, $body);
}

// Send Order Confirmation with 2-minute editable window
function sendOrderConfirmationEmail($orderId) {
    require_once __DIR__ . '/config.php';

    $conn = getDBConnection();
    // Load order + items
    $stmt = $conn->prepare("SELECT o.id, o.order_number, o.customer_email, o.customer_name, o.order_total, o.created_at FROM orders o WHERE o.id = ? LIMIT 1");
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $order = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$order) { $conn->close(); return false; }

    $itemsStmt = $conn->prepare("SELECT product_name, quantity, unit_price, line_total FROM order_items WHERE order_id = ?");
    $itemsStmt->bind_param('i', $orderId);
    $itemsStmt->execute();
    $items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $itemsStmt->close();

    $conn->close();

    // Build email body with review link
    $baseUrl = 'http://localhost/IE4727-Final-Project/public/';
    // Provide a simple, login-protected link using order number (no token needed)
    $reviewUrl = $baseUrl . 'order_review.php?order=' . urlencode($order['order_number']);
    $cancelUrl = $reviewUrl . '&action=cancel';

    $itemsRows = '';
    foreach ($items as $it) {
        $itemsRows .= '<tr><td style="padding:6px 8px; border-bottom:1px solid #eee;">' . htmlspecialchars($it['product_name']) . '</td>';
        $itemsRows .= '<td style="padding:6px 8px; text-align:center; border-bottom:1px solid #eee;">' . (int)$it['quantity'] . '</td>';
        $itemsRows .= '<td style="padding:6px 8px; text-align:right; border-bottom:1px solid #eee;">$' . number_format((float)$it['line_total'], 2) . '</td></tr>';
    }

    // Totals breakdown
    $subtotal = 0.0;
    foreach ($items as $it) { $subtotal += (float)($it['line_total'] ?? 0); }
    $taxRate = 0.09; // match checkout
    $taxAmount = $subtotal * $taxRate;
    $shippingFee = $subtotal > 100 ? 0.00 : 10.00;
    $fmtSubtotal = '$' . number_format($subtotal, 2);
    $fmtTax = '$' . number_format($taxAmount, 2);
    $fmtShipping = $shippingFee > 0 ? ('$' . number_format($shippingFee, 2)) : 'FREE';
    $fmtTotal = '$' . number_format((float)$order['order_total'], 2);

    $subject = "Your Order " . $order['order_number'] . " is Confirmed";
    $body = <<<HTML
<!doctype html>
<html>
    <head>
        <meta charset="utf-8">
        <title>Order Confirmation</title>
        <style>
            body { margin:0; padding:0; background:#eeeeee; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif; color:#222222; }
            .container { max-width:600px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.08); }
            .header { background:#7A553D; color:#ffffff; padding:24px 20px; text-align:center; }
            .brand { margin:0; font-size:24px; font-weight:700; letter-spacing:.2px; }
            .sub { margin:6px 0 0; opacity:.9; }
            .content { padding:32px 28px; }
            .h2 { margin:0 0 8px 0; font-size:20px; }
            .muted { color:#7e5d3f; margin:0 0 16px 0; }
            .otp-box { background:#f7f2ec; padding:24px; text-align:center; border-radius:10px; margin:24px 0; }
            .otp { font-size:32px; font-weight:800; color:#FA6000; letter-spacing:10px;}
            .notice { border: 1px solid #7a553d; border-left:4px solid #7a553d; padding:12px 14px; margin:18px 0; border-radius:6px; color:#7a553d; }
            .footer { background:#f8f9fa; color:#7e5d3f; text-align:center; font-size:12px; padding:16px; }
        </style>
    </head>
<body style="background:#f5f5f5; font-family: -apple-system, Segoe UI, Roboto, Arial, sans-serif;">
  <div style="max-width:680px; margin:24px auto; background:#ffffff; border-radius:12px; overflow:hidden; box-shadow:0 4px 16px rgba(0,0,0,.06)">
    <div style="background:#7A553D; color:#fff; padding:22px 20px;">
      <div class="header" style="align-items:center;">
        <img src="http://localhost/IE4727-Final-Project/public/assets/images/logo.svg" alt="Stirling's Logo" width="140" style="display:block;margin:0 auto;filter:brightness(0) invert(1);">
        <div class="sub">Order Confirmed</div>
        <div style="opacity:.9;">Order #{$order['order_number']}</div>
      </div>
    </div>
    <div style="padding:24px 22px; color:#222;">
      <p>Hi {$order['customer_name']},</p>
      <p>Thanks for your purchase. Your order has been received.</p>
      <table style="width:100%; border-collapse:collapse; margin:12px 0;">
        <thead>
          <tr><th style="text-align:left; padding:6px 8px; border-bottom:2px solid #ddd;">Item</th>
              <th style="text-align:center; padding:6px 8px; border-bottom:2px solid #ddd;">Qty</th>
              <th style="text-align:right; padding:6px 8px; border-bottom:2px solid #ddd;">Total</th></tr>
        </thead>
        <tbody>{$itemsRows}</tbody>
        <tfoot>
          <tr><td></td><td style="text-align:right; padding:8px;">Subtotal</td><td style="text-align:right; padding:8px;">{$fmtSubtotal}</td></tr>
          <tr><td></td><td style="text-align:right; padding:8px;">Tax (9%)</td><td style="text-align:right; padding:8px;">{$fmtTax}</td></tr>
          <tr><td></td><td style="text-align:right; padding:8px;">Shipping</td><td style="text-align:right; padding:8px;">{$fmtShipping}</td></tr>
          <tr><td></td><td style="text-align:right; padding:8px;"><strong>Order Total</strong></td><td style="text-align:right; padding:8px;"><strong>{$fmtTotal}</strong></td></tr>
        </tfoot>
      </table>
      <div class="notice">
        You can review or change your order for the next <strong>2 minutes</strong>.
      </div>
      <p>
        <a href="{$reviewUrl}" style="display:inline-block; background:#7A553D; color:#fff; padding:10px 16px; border-radius:8px; text-decoration:none;">Review / Edit Order</a>
        <a href="{$cancelUrl}" style="display:inline-block; margin-left:10px; background:#b3261e; color:#fff; padding:10px 16px; border-radius:8px; text-decoration:none;">Cancel Order</a>
      </p>
    </div>
    <div class="footer">&copy; 2025 Stirling&apos;s, Shen Bowen &amp; Shirsho Sinha.</div>
  </div>
</body></html>
HTML;

    return sendEmail($order['customer_email'], $subject, $body);
}
?>