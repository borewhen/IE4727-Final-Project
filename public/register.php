<?php
require_once 'config.php';

// Hardcoded admin PIN
define('ADMIN_PIN', '9999'); // Change this to your desired admin PIN

// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit();
}

$success_message = '';
$error_message = '';
$field_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if all required POST variables exist first
    $first_name = isset($_POST['first_name']) ? trim($_POST['first_name']) : '';
    $middle_name = isset($_POST['middle_name']) ? trim($_POST['middle_name']) : '';
    $last_name = isset($_POST['last_name']) ? trim($_POST['last_name']) : '';
    
    // Use verified_email if it exists, otherwise use email
    $email = isset($_POST['verified_email']) && !empty($_POST['verified_email']) 
        ? trim($_POST['verified_email']) 
        : (isset($_POST['email']) ? trim($_POST['email']) : '');
    
    $country_code = isset($_POST['country_code']) ? trim($_POST['country_code']) : '';
    $phone = isset($_POST['phone']) ? trim($_POST['phone']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $confirm_password = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    $shipping_address = isset($_POST['shipping_address']) ? trim($_POST['shipping_address']) : '';
    $pincode = isset($_POST['pincode']) ? trim($_POST['pincode']) : '';
    
    // Role and admin PIN
    $user_role = isset($_POST['user_role']) ? $_POST['user_role'] : 'customer';
    $admin_pin = isset($_POST['admin_pin']) ? trim($_POST['admin_pin']) : '';
    
    // Server-side validation
    
    // First name validation
    if (empty($first_name)) {
        $field_errors['first_name'] = 'First name is required';
    } elseif (strlen($first_name) < 2) {
        $field_errors['first_name'] = 'First name must be at least 2 characters';
    } elseif (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $first_name)) {
        $field_errors['first_name'] = 'First name must start with a capital letter and contain only letters';
    }
    
    // Middle name validation (optional)
    if (!empty($middle_name)) {
        if (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $middle_name)) {
            $field_errors['middle_name'] = 'Middle name must start with a capital letter and contain only letters';
        }
    }
    
    // Last name validation
    if (empty($last_name)) {
        $field_errors['last_name'] = 'Last name is required';
    } elseif (strlen($last_name) < 2) {
        $field_errors['last_name'] = 'Last name must be at least 2 characters';
    } elseif (!preg_match('/^[A-Z][a-zA-Z\s]*$/', $last_name)) {
        $field_errors['last_name'] = 'Last name must start with a capital letter and contain only letters';
    }
    
    // Email validation
    if (empty($email)) {
        $field_errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['email'] = 'Please enter a valid email address';
    } elseif (strpos($email, '@') === false) {
        $field_errors['email'] = 'Email must contain @ symbol';
    }
    
    // Phone validation
    if (empty($phone)) {
        $field_errors['phone'] = 'Phone number is required';
    } elseif (!preg_match('/^[0-9]{8,12}$/', $phone)) {
        $field_errors['phone'] = 'Phone number must be 8-12 digits';
    }
    
    // Password validation
    if (empty($password)) {
        $field_errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8 || strlen($password) > 15) {
        $field_errors['password'] = 'Password must be 8-15 characters';
    } elseif (!preg_match('/[0-9]/', $password)) {
        $field_errors['password'] = 'Password must contain at least one digit';
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $field_errors['password'] = 'Password must contain at least one special character';
    }
    
    // Confirm password validation
    if (empty($confirm_password)) {
        $field_errors['confirm_password'] = 'Please confirm your password';
    } elseif ($password !== $confirm_password) {
        $field_errors['confirm_password'] = 'Passwords do not match';
    }
    
    // Address validation
    if (empty($shipping_address)) {
        $field_errors['shipping_address'] = 'Shipping address is required';
    } elseif (strlen($shipping_address) < 10) {
        $field_errors['shipping_address'] = 'Please enter a complete address (at least 10 characters)';
    }
    
    // Pincode validation
    if (empty($pincode)) {
        $field_errors['pincode'] = 'Pincode is required';
    } elseif (!preg_match('/^[0-9]{5,10}$/', $pincode)) {
        $field_errors['pincode'] = 'Pincode must be 5-10 digits';
    }
    
    // Admin PIN validation
    if ($user_role === 'admin') {
        if (empty($admin_pin)) {
            $field_errors['admin_pin'] = 'Admin PIN is required for admin registration';
        } elseif ($admin_pin !== ADMIN_PIN) {
            $field_errors['admin_pin'] = 'Invalid admin PIN';
        }
    }
    
    // OTP verification check
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true) {
        $field_errors['otp'] = 'Please verify your email with OTP';
    } elseif (isset($_SESSION['otp_email']) && $_SESSION['otp_email'] !== $email) {
        $field_errors['otp'] = 'OTP was verified for a different email address';
    }
    
    // If no validation errors, proceed with registration
    if (empty($field_errors)) {
        $conn = getDBConnection();
        
        // Check if email already exists (double check)
        $check_stmt = $conn->prepare("SELECT id FROM customers WHERE email = ?");
        $check_stmt->bind_param("s", $email);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = 'Email already registered. Please login or use a different email.';
            $check_stmt->close();
            $conn->close();
        } else {
            $check_stmt->close();
            
            // Hash the password
            $password_hash = password_hash($password, PASSWORD_DEFAULT);
            
            // Combine first, middle, and last name
            $full_first_name = $middle_name ? $first_name . ' ' . $middle_name : $first_name;
            
            // Combine country code and phone
            $full_phone = $country_code . $phone;
            
            // Combine address with pincode
            $full_address = $shipping_address . ', Pincode: ' . $pincode;
            
            // Set is_admin flag based on role
            $is_admin = ($user_role === 'admin') ? 1 : 0;
            
            // Insert new customer
            $stmt = $conn->prepare("INSERT INTO customers (email, password_hash, first_name, last_name, phone, shipping_address, is_admin) VALUES (?, ?, ?, ?, ?, ?, ?)");
            
            if ($stmt === false) {
                $error_message = 'Database error: ' . $conn->error;
            } else {
                $stmt->bind_param("ssssssi", $email, $password_hash, $full_first_name, $last_name, $full_phone, $full_address, $is_admin);
                
                if ($stmt->execute()) {
                    // Clear OTP session data
                    unset($_SESSION['otp']);
                    unset($_SESSION['otp_time']);
                    unset($_SESSION['otp_email']);
                    unset($_SESSION['otp_verified']);
                    
                    // Set success message in session to display on login page
                    if ($user_role === 'admin') {
                        $_SESSION['registration_success'] = 'Admin account created successfully! Please login with your credentials.';
                    } else {
                        $_SESSION['registration_success'] = 'Registration successful! Please login with your credentials.';
                    }
                    
                    // Redirect to login page
                    header('Location: login.php');
                    exit();
                } else {
                    $error_message = 'Registration failed: ' . $stmt->error;
                }
                
                $stmt->close();
            }
            
            $conn->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Menswear Store</title>
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box register-box">
            <h1>Create Account</h1>
            <p class="subtitle">Register for a new account</p>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($field_errors)): ?>
                <div class="error-message">
                    <strong>Please fix the following errors:</strong>
                    <ul style="margin: 10px 0 0 20px; padding: 0;">
                        <?php foreach ($field_errors as $error): ?>
                            <li><?php echo htmlspecialchars($error); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="register.php" id="registerForm" novalidate>
                <!-- Role Toggle -->
                <div class="form-group role-toggle-container">
                    <label class="role-toggle-label">
                        <span class="role-text">Register as:</span>
                        <div class="toggle-switch">
                            <input type="checkbox" id="roleToggle" name="user_role" value="admin">
                            <span class="toggle-slider"></span>
                            <span class="toggle-label-customer">Customer</span>
                            <span class="toggle-label-admin">Admin</span>
                        </div>
                    </label>
                </div>
                
                <!-- Hidden input to store actual role value -->
                <input type="hidden" id="user_role_input" name="user_role" value="customer">
                
                <!-- Admin PIN Field (Hidden by default) -->
                <div class="form-group" id="adminPinGroup" style="display: none;">
                    <label for="admin_pin">Admin PIN <span class="required">*</span></label>
                    <input 
                        type="password" 
                        id="admin_pin" 
                        name="admin_pin" 
                        class="<?php echo isset($field_errors['admin_pin']) ? 'error' : ''; ?>"
                        placeholder="Enter 4-digit admin PIN"
                        maxlength="4"
                    >
                    <small class="password-hint">Contact administrator for PIN</small>
                    <?php if (isset($field_errors['admin_pin'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['admin_pin']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input 
                            type="text" 
                            id="first_name" 
                            name="first_name" 
                            class="<?php echo isset($field_errors['first_name']) ? 'error' : ''; ?>"
                            value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>"
                            placeholder="John"
                        >
                        <?php if (isset($field_errors['first_name'])): ?>
                            <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['first_name']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="middle_name">Middle Name</label>
                        <input 
                            type="text" 
                            id="middle_name" 
                            name="middle_name" 
                            class="<?php echo isset($field_errors['middle_name']) ? 'error' : ''; ?>"
                            value="<?php echo isset($_POST['middle_name']) ? htmlspecialchars($_POST['middle_name']) : ''; ?>"
                            placeholder="Optional"
                        >
                        <?php if (isset($field_errors['middle_name'])): ?>
                            <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['middle_name']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="last_name">Last Name <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="last_name" 
                        name="last_name" 
                        class="<?php echo isset($field_errors['last_name']) ? 'error' : ''; ?>"
                        value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>"
                        placeholder="Doe"
                    >
                    <?php if (isset($field_errors['last_name'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['last_name']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <div class="email-otp-container">
                        <input 
                            type="email" 
                            id="email" 
                            name="email" 
                            class="<?php echo isset($field_errors['email']) ? 'error' : ''; ?>"
                            value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                            placeholder="john@example.com"
                        >
                        <button type="button" id="sendOtpBtn" class="btn-otp">Send OTP</button>
                    </div>
                    <!-- Hidden field to store verified email -->
                    <input type="hidden" id="verified_email" name="verified_email" value="<?php echo isset($_POST['verified_email']) ? htmlspecialchars($_POST['verified_email']) : ''; ?>">
                    
                    <?php if (isset($field_errors['email'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['email']); ?></div>
                    <?php endif; ?>
                    <div id="otpMessage" class="otp-message"></div>
                </div>
                
                <div class="form-group" id="otpFieldGroup" style="display: none;">
                    <label for="otp">Enter OTP <span class="required">*</span></label>
                    <div class="email-otp-container">
                        <input 
                            type="text" 
                            id="otp" 
                            name="otp_input" 
                            maxlength="6"
                            placeholder="Enter 6-digit OTP"
                        >
                        <button type="button" id="verifyOtpBtn" class="btn-otp">Verify</button>
                    </div>
                    <?php if (isset($field_errors['otp'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['otp']); ?></div>
                    <?php endif; ?>
                    <div id="otpVerifyMessage" class="otp-message"></div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <div class="phone-container">
                        <select id="country_code" name="country_code" class="country-code">
                            <option value="+65" <?php echo (!isset($_POST['country_code']) || $_POST['country_code'] === '+65') ? 'selected' : ''; ?>>+65 (SG)</option>
                            <option value="+91" <?php echo (isset($_POST['country_code']) && $_POST['country_code'] === '+91') ? 'selected' : ''; ?>>+91 (IN)</option>
                            <option value="+1" <?php echo (isset($_POST['country_code']) && $_POST['country_code'] === '+1') ? 'selected' : ''; ?>>+1 (US)</option>
                            <option value="+44" <?php echo (isset($_POST['country_code']) && $_POST['country_code'] === '+44') ? 'selected' : ''; ?>>+44 (UK)</option>
                            <option value="+61" <?php echo (isset($_POST['country_code']) && $_POST['country_code'] === '+61') ? 'selected' : ''; ?>>+61 (AU)</option>
                            <option value="+81" <?php echo (isset($_POST['country_code']) && $_POST['country_code'] === '+81') ? 'selected' : ''; ?>>+81 (JP)</option>
                            <option value="+86" <?php echo (isset($_POST['country_code']) && $_POST['country_code'] === '+86') ? 'selected' : ''; ?>>+86 (CN)</option>
                        </select>
                        <input 
                            type="tel" 
                            id="phone" 
                            name="phone" 
                            class="<?php echo isset($field_errors['phone']) ? 'error' : ''; ?>"
                            value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                            placeholder="91234567"
                        >
                    </div>
                    <?php if (isset($field_errors['phone'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['phone']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="shipping_address">Shipping Address <span class="required">*</span></label>
                    <textarea 
                        id="shipping_address" 
                        name="shipping_address" 
                        rows="3"
                        class="<?php echo isset($field_errors['shipping_address']) ? 'error' : ''; ?>"
                        placeholder="123 Main Street, Apartment 4B"
                    ><?php echo isset($_POST['shipping_address']) ? htmlspecialchars($_POST['shipping_address']) : ''; ?></textarea>
                    <?php if (isset($field_errors['shipping_address'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['shipping_address']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="pincode">Pincode <span class="required">*</span></label>
                    <input 
                        type="text" 
                        id="pincode" 
                        name="pincode" 
                        class="<?php echo isset($field_errors['pincode']) ? 'error' : ''; ?>"
                        value="<?php echo isset($_POST['pincode']) ? htmlspecialchars($_POST['pincode']) : ''; ?>"
                        placeholder="123456"
                        maxlength="10"
                    >
                    <?php if (isset($field_errors['pincode'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['pincode']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label for="password">Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="<?php echo isset($field_errors['password']) ? 'error' : ''; ?>"
                        >
                        <small class="password-hint">8-15 characters, 1 digit, 1 special character</small>
                        <?php if (isset($field_errors['password'])): ?>
                            <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['password']); ?></div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                        <input 
                            type="password" 
                            id="confirm_password" 
                            name="confirm_password" 
                            class="<?php echo isset($field_errors['confirm_password']) ? 'error' : ''; ?>"
                        >
                        <?php if (isset($field_errors['confirm_password'])): ?>
                            <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['confirm_password']); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <button type="submit" class="btn-primary" id="submitBtn" disabled>Register</button>
                <p class="form-note">* Required fields</p>
            </form>
            
            <div class="form-footer">
                <p>Already have an account? <a href="login.php">Login here</a></p>
                <p><a href="index.php">Continue as guest</a></p>
            </div>
        </div>
    </div>
    
    <script src="js/register_validation.js"></script>
</body>
</html>