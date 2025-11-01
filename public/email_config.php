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
?>