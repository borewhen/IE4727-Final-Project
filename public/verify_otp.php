<?php
require_once 'config.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp']);
    
    // Check if OTP exists in session
    if (!isset($_SESSION['otp']) || !isset($_SESSION['otp_time'])) {
        echo json_encode(['success' => false, 'message' => 'No OTP found. Please request a new one.']);
        exit();
    }
    
    // Check if OTP is expired (10 minutes)
    if (time() - $_SESSION['otp_time'] > 600) {
        unset($_SESSION['otp']);
        unset($_SESSION['otp_time']);
        unset($_SESSION['otp_email']);
        echo json_encode(['success' => false, 'message' => 'OTP expired. Please request a new one.']);
        exit();
    }
    
    // Verify OTP
    if ($entered_otp === $_SESSION['otp']) {
        $_SESSION['otp_verified'] = true;
        echo json_encode(['success' => true, 'message' => 'OTP verified successfully']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid OTP. Please try again.']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
}
?>