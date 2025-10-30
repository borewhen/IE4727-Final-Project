<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'PHPMailer/src/Exception.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';

// Gmail Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'shirshosinha9@gmail.com');           // ← UPDATE THIS
define('SMTP_PASSWORD', 'iuau hiat hqwb tpnu');              // ← UPDATE THIS
define('SMTP_FROM_EMAIL', 'shirshosinha9@gmail.com');         // ← UPDATE THIS
define('SMTP_FROM_NAME', 'Stirling Store');

function sendEmail($to, $subject, $body) {
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        
        // Disable SSL certificate verification (for XAMPP/localhost)
        $mail->SMTPOptions = array(
            'ssl' => array(
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true
            )
        );
        
        // Recipients
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $body;
        
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email Error: {$mail->ErrorInfo}");
        return false;
    }
}

function sendOTP($email, $otp) {
    $subject = "Your OTP for Registration - Stirling Store";
    $body = "
    <html>
    <head>
        <style>
            body { 
                font-family: Arial, sans-serif; 
                margin: 0;
                padding: 0;
                background-color: #f4f4f4;
            }
            .container { 
                max-width: 600px; 
                margin: 20px auto; 
                background: #ffffff;
                border-radius: 10px;
                overflow: hidden;
                box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            }
            .header { 
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
                color: white; 
                padding: 30px 20px; 
                text-align: center; 
            }
            .header h1 {
                margin: 0;
                font-size: 28px;
            }
            .content { 
                padding: 40px 30px; 
            }
            .content h2 {
                color: #333;
                margin-top: 0;
            }
            .otp-box { 
                background: #f5f5f5; 
                padding: 25px; 
                border-radius: 8px; 
                text-align: center; 
                margin: 30px 0; 
                border: 2px dashed #667eea; 
            }
            .otp-code { 
                font-size: 42px; 
                font-weight: bold; 
                color: #667eea; 
                letter-spacing: 10px;
                font-family: 'Courier New', monospace;
            }
            .info-box {
                background: #fff3cd;
                border-left: 4px solid #ffc107;
                padding: 15px;
                margin: 20px 0;
            }
            .footer { 
                text-align: center; 
                padding: 20px;
                background: #f8f9fa;
                color: #666; 
                font-size: 12px; 
            }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>🎩 Stirling Store</h1>
                <p style='margin: 5px 0 0 0;'>Email Verification</p>
            </div>
            <div class='content'>
                <h2>Welcome!</h2>
                <p>Thank you for registering with Stirling Store!</p>
                <p>To complete your registration, please use the following One-Time Password (OTP):</p>
                
                <div class='otp-box'>
                    <div class='otp-code'>{$otp}</div>
                </div>
                
                <div class='info-box'>
                    <strong>⚠️ Important:</strong> This OTP is valid for <strong>10 minutes</strong> only.
                </div>
                
                <p>Enter this code on the registration page to verify your email address.</p>
                <p>If you didn't request this registration, please ignore this email.</p>
            </div>
            <div class='footer'>
                <p>&copy; 2024 Menswear Store. All rights reserved.</p>
                <p>This is an automated email, please do not reply.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $body);
}
?>