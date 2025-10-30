<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OTP Email Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-container {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #667eea;
            border-bottom: 3px solid #667eea;
            padding-bottom: 10px;
        }
        .form-group {
            margin: 20px 0;
        }
        label {
            display: block;
            font-weight: bold;
            margin-bottom: 5px;
            color: #333;
        }
        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 16px;
            box-sizing: border-box;
        }
        button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            cursor: pointer;
            width: 100%;
            font-weight: bold;
        }
        button:hover {
            opacity: 0.9;
        }
        .result {
            margin-top: 20px;
            padding: 15px;
            border-radius: 5px;
        }
        .success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .debug {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 5px;
            font-family: monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
        }
        .instructions {
            background: #fff3cd;
            padding: 15px;
            border-left: 4px solid #ffc107;
            margin-bottom: 20px;
        }
        .instructions h3 {
            margin-top: 0;
            color: #856404;
        }
        .instructions ol {
            margin: 10px 0;
            padding-left: 20px;
        }
        .instructions li {
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>📧 OTP Email Test</h1>
        
        <div class="instructions">
            <h3>⚠️ Before Testing:</h3>
            <ol>
                <li>Make sure you've updated <code>email_config.php</code> with your Gmail credentials</li>
                <li>Ensure 2-Factor Authentication is enabled on your Gmail account</li>
                <li>Use an App Password (not your regular Gmail password)</li>
                <li>Check your spam folder if email doesn't arrive in inbox</li>
            </ol>
        </div>

        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            require_once 'email_config.php';
            
            $test_email = trim($_POST['test_email']);
            $test_otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
            
            echo "<div class='result'>";
            echo "<h3>🔄 Sending test email...</h3>";
            echo "<p><strong>To:</strong> " . htmlspecialchars($test_email) . "</p>";
            echo "<p><strong>OTP:</strong> <code style='font-size: 18px; background: #f5f5f5; padding: 5px 10px; border-radius: 3px;'>{$test_otp}</code></p>";
            echo "</div>";
            
            echo "<div class='debug'>";
            echo "<strong>Debug Output:</strong><br><br>";
            
            ob_start();
            $result = sendOTP($test_email, $test_otp);
            $debug_output = ob_get_clean();
            
            echo $debug_output;
            echo "</div>";
            
            if ($result) {
                echo "<div class='result success'>";
                echo "<h3>✅ Success!</h3>";
                echo "<p>Email sent successfully!</p>";
                echo "<p><strong>Next steps:</strong></p>";
                echo "<ul>";
                echo "<li>Check your email inbox: <strong>" . htmlspecialchars($test_email) . "</strong></li>";
                echo "<li>If not in inbox, check your <strong>Spam/Junk</strong> folder</li>";
                echo "<li>Look for email from: <strong>" . SMTP_FROM_EMAIL . "</strong></li>";
                echo "<li>The OTP in the email should be: <strong>{$test_otp}</strong></li>";
                echo "</ul>";
                echo "</div>";
            } else {
                echo "<div class='result error'>";
                echo "<h3>❌ Failed to send email</h3>";
                echo "<p>Please check the debug output above for error details.</p>";
                echo "<p><strong>Common issues:</strong></p>";
                echo "<ul>";
                echo "<li>Incorrect Gmail address or App Password</li>";
                echo "<li>2-Factor Authentication not enabled</li>";
                echo "<li>Using regular password instead of App Password</li>";
                echo "<li>Firewall blocking SMTP connection</li>";
                echo "</ul>";
                echo "</div>";
            }
        }
        ?>

        <form method="POST" action="">
            <div class="form-group">
                <label for="test_email">Enter Your Email Address:</label>
                <input 
                    type="email" 
                    id="test_email" 
                    name="test_email" 
                    placeholder="your_email@gmail.com" 
                    required
                    value="<?php echo isset($_POST['test_email']) ? htmlspecialchars($_POST['test_email']) : ''; ?>"
                >
                <small style="color: #666; display: block; margin-top: 5px;">
                    Enter the email where you want to receive the test OTP
                </small>
            </div>
            
            <button type="submit">📨 Send Test OTP</button>
        </form>
    </div>
</body>
</html>