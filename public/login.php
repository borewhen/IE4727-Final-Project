<?php
require_once 'config.php';

// Redirect if already logged in
if (isset($_SESSION['customer_id'])) {
    header('Location: index.php');
    exit();
}

// Check for registration success message
$registration_success = '';
if (isset($_SESSION['registration_success'])) {
    $registration_success = $_SESSION['registration_success'];
    unset($_SESSION['registration_success']); // Clear the message after displaying
}

$error_message = '';
$field_errors = [];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Server-side validation
    if (empty($email)) {
        $field_errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['email'] = 'Please enter a valid email address';
    }
    
    if (empty($password)) {
        $field_errors['password'] = 'Password is required';
    } elseif (strlen($password) < 6) {
        $field_errors['password'] = 'Password must be at least 6 characters';
    }
    
    // If no validation errors, proceed with login
    if (empty($field_errors)) {
        $conn = getDBConnection();
        
        // Prepare statement to prevent SQL injection
        $stmt = $conn->prepare("SELECT id, email, password_hash, first_name, last_name, is_admin FROM customers WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password_hash'])) {
                // Password is correct, create session
                $_SESSION['customer_id'] = $user['id'];
                $_SESSION['customer_email'] = $user['email'];
                $_SESSION['customer_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['is_admin'] = $user['is_admin'];
                
                // Update last login
                $update_stmt = $conn->prepare("UPDATE customers SET last_login = NOW() WHERE id = ?");
                $update_stmt->bind_param("i", $user['id']);
                $update_stmt->execute();
                $update_stmt->close();
                
                // Redirect based on user role
                if ($user['is_admin'] == 1) {
                    header('Location: admin_dashboard.php');
                } else {
                    header('Location: index.php');
                }
                exit();
            } else {
                $error_message = 'Invalid email or password';
            }
        } else {
            $error_message = 'Invalid email or password';
        }
        
        $stmt->close();
        $conn->close();
    }
}
?>
<?php require __DIR__ . '/partials/header.php'; ?>
<main id="main">
    <div class="login-container">
        <div class="login-box">
            <h1>Welcome Back</h1>
            <p class="subtitle" style="text-align: left;">Log in to your account</p>
            
            <?php if (!empty($registration_success)): ?>
                <div class="success-message">
                    <?php echo htmlspecialchars($registration_success); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($error_message)): ?>
                <div class="error-message">
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="login.php" id="loginForm" novalidate>
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="<?php echo isset($field_errors['email']) ? 'error' : ''; ?>"
                        value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
                    >
                    <?php if (isset($field_errors['email'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['email']); ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="<?php echo isset($field_errors['password']) ? 'error' : ''; ?>"
                    >
                    <?php if (isset($field_errors['password'])): ?>
                        <div class="field-error" data-server-error><?php echo htmlspecialchars($field_errors['password']); ?></div>
                    <?php endif; ?>
                </div>
                
                <button type="submit" class="btn-primary">Login</button>
            </form>
            
            <div class="form-footer">
                <p>Don't have an account? <a href="register.php">Register here</a></p>
                <p><a href="index.php">Continue as guest</a></p>
            </div>
        </div>
    </div>
</main>

<script src="js/validation.js"></script>
<?php require __DIR__ . '/partials/footer.php'; ?>