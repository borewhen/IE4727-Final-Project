<?php
require_once 'config.php';
require_once 'email_config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit();
    }
    
    if (strpos($email, '@') === false) {
        echo json_encode(['success' => false, 'message' => 'Email must contain @ symbol']);
        exit();
    }
    
    // Check if email already exists
    $conn = getDBConnection();
    $stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        echo json_encode(['success' => false, 'message' => 'Email already registered']);
        $stmt->close();
        $conn->close();
        exit();
    }
    $stmt->close();
    $conn->close();
    
    // Generate 6-digit OTP
    $otp = str_pad(rand(0, 999999), 6, '0', STR_PAD_LEFT);
    
    // Store OTP in session with timestamp
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_email'] = $email;
    $_SESSION['otp_time'] = time();
    
    // Send OTP only to local dev recipient, regardless of entered email
    if (sendOTP(DEV_OTP_RECIPIENT, $otp)) {
        echo json_encode(['success' => true, 'message' => 'OTP sent to your email']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>