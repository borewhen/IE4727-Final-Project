document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('registerForm');
    const emailInput = document.getElementById('email');
    const verifiedEmailInput = document.getElementById('verified_email');
    const sendOtpBtn = document.getElementById('sendOtpBtn');
    const otpFieldGroup = document.getElementById('otpFieldGroup');
    const otpInput = document.getElementById('otp');
    const verifyOtpBtn = document.getElementById('verifyOtpBtn');
    const submitBtn = document.getElementById('submitBtn');
    const otpMessage = document.getElementById('otpMessage');
    const otpVerifyMessage = document.getElementById('otpVerifyMessage');
    
    // Role toggle elements
    const roleToggle = document.getElementById('roleToggle');
    const userRoleInput = document.getElementById('user_role_input');
    const adminPinGroup = document.getElementById('adminPinGroup');
    const adminPinInput = document.getElementById('admin_pin');
    
    let otpVerified = false;
    // Centralize enabling/disabling submit button
    function updateSubmitState() {
        submitBtn.disabled = !otpVerified;
    }

    let otpSent = false;
    let verifiedEmail = '';
    let isAdminRole = false;
    
    // Handle role toggle
    roleToggle.addEventListener('change', function() {
        isAdminRole = this.checked;
        userRoleInput.value = isAdminRole ? 'admin' : 'customer';
        
        if (isAdminRole) {
            adminPinGroup.style.display = 'block';
            adminPinInput.required = true;
        } else {
            adminPinGroup.style.display = 'none';
            adminPinInput.required = false;
            adminPinInput.value = '';
            // Remove any admin PIN errors
            const adminPinError = adminPinInput.parentNode.querySelector('.field-error:not([data-server-error])');
            if (adminPinError) {
                adminPinError.remove();
            }
            adminPinInput.classList.remove('error');
        }
    });
    
    // Validate admin PIN on input
    adminPinInput.addEventListener('input', function() {
        // Only allow numbers
        this.value = this.value.replace(/[^0-9]/g, '');
        
        // Remove error when typing
        this.classList.remove('error');
        const errorDiv = this.parentNode.querySelector('.field-error:not([data-server-error])');
        if (errorDiv) {
            errorDiv.remove();
        }
    });
    
    // Get all form inputs
    const inputs = document.querySelectorAll('input, textarea, select');
    
    // Add blur event listeners to all inputs
    inputs.forEach(input => {
        if (input.id !== 'otp' && input.id !== 'otp_input' && input.id !== 'verified_email' && input.id !== 'user_role_input' && input.id !== 'roleToggle') {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        }
        
        // Remove error styling when user starts typing
        input.addEventListener('input', function() {
            this.classList.remove('error');
            const errorDiv = this.parentNode.querySelector('.field-error');
            if (errorDiv && !errorDiv.hasAttribute('data-server-error')) {
                errorDiv.remove();
            }
        });
    });
    
    // Prevent email change after OTP is sent
    emailInput.addEventListener('input', function() {
        if (otpSent) {
            otpVerified = false;
            otpSent = false;
            updateSubmitState();
            otpFieldGroup.style.display = 'none';
            sendOtpBtn.textContent = 'Send OTP';
            sendOtpBtn.disabled = false;
            verifiedEmailInput.value = '';
            showMessage(otpMessage, 'Email changed. Please send OTP again.', 'error');
        }
    });
    
    // Send OTP
    sendOtpBtn.addEventListener('click', function() {
        const email = emailInput.value.trim();
        
        if (!isValidEmail(email)) {
            showMessage(otpMessage, 'Please enter a valid email address with @', 'error');
            return;
        }
        
        sendOtpBtn.disabled = true;
        sendOtpBtn.textContent = 'Sending...';
        
        fetch('send_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'email=' + encodeURIComponent(email)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(otpMessage, data.message + ' ✓', 'success');
                otpFieldGroup.style.display = 'block';
                otpSent = true;
                verifiedEmail = email;
                sendOtpBtn.textContent = 'Resend OTP';
                setTimeout(() => {
                    sendOtpBtn.disabled = false;
                }, 60000);
            } else {
                showMessage(otpMessage, data.message, 'error');
                sendOtpBtn.disabled = false;
                sendOtpBtn.textContent = 'Send OTP';
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showMessage(otpMessage, 'Error sending OTP. Please try again.', 'error');
            sendOtpBtn.disabled = false;
            sendOtpBtn.textContent = 'Send OTP';
        });
    });
    
    // Verify OTP
    verifyOtpBtn.addEventListener('click', function() {
        const otp = otpInput.value.trim();
        const currentEmail = emailInput.value.trim();
        
        if (currentEmail !== verifiedEmail) {
            showMessage(otpVerifyMessage, 'Email changed! Please send OTP again to the new email.', 'error');
            otpFieldGroup.style.display = 'none';
            otpSent = false;
            sendOtpBtn.textContent = 'Send OTP';
            sendOtpBtn.disabled = false;
            return;
        }
        
        if (otp.length !== 6) {
            showMessage(otpVerifyMessage, 'Please enter 6-digit OTP', 'error');
            return;
        }
        
        verifyOtpBtn.disabled = true;
        verifyOtpBtn.textContent = 'Verifying...';
        
        fetch('verify_otp.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'otp=' + encodeURIComponent(otp) + '&email=' + encodeURIComponent(currentEmail)
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                showMessage(otpVerifyMessage, data.message + ' ✓', 'success');
                otpVerified = true;
                updateSubmitState();
                verifyOtpBtn.disabled = true;
                verifyOtpBtn.textContent = 'Verified ✓';
                verifyOtpBtn.style.background = '#28a745';
                otpInput.disabled = true;
                emailInput.disabled = true;
                emailInput.style.backgroundColor = '#f5f5f5';
                emailInput.style.cursor = 'not-allowed';
                sendOtpBtn.disabled = true;
                
                // Store verified email in hidden field
                verifiedEmailInput.value = verifiedEmail;
            } else {
                showMessage(otpVerifyMessage, data.message, 'error');
                verifyOtpBtn.disabled = false;
                verifyOtpBtn.textContent = 'Verify';
                otpVerified = false;
                updateSubmitState();
            }
        })
        .catch(error => {
            console.error('Fetch error:', error);
            showMessage(otpVerifyMessage, 'Error verifying OTP. Please try again.', 'error');
            verifyOtpBtn.disabled = false;
            verifyOtpBtn.textContent = 'Verify';
            otpVerified = false;
            updateSubmitState();
        });
    });
    
    // Validation function
    function validateField(field) {
        const fieldName = field.name;
        const fieldValue = field.value.trim();
        let errorMessage = '';
        
        const existingError = field.parentNode.querySelector('.field-error:not([data-server-error])');
        if (existingError) {
            existingError.remove();
        }
        field.classList.remove('error');
        
        switch(fieldName) {
            case 'first_name':
                if (fieldValue === '') {
                    errorMessage = 'First name is required';
                } else if (fieldValue.length < 2) {
                    errorMessage = 'Must be at least 2 characters';
                } else if (!/^[A-Z]/.test(fieldValue)) {
                    errorMessage = 'Must start with capital letter';
                } else if (!/^[A-Z][a-zA-Z\s]*$/.test(fieldValue)) {
                    errorMessage = 'Can only contain letters';
                }
                break;
            case 'middle_name':
                if (fieldValue !== '') {
                    if (!/^[A-Z]/.test(fieldValue)) {
                        errorMessage = 'Must start with capital letter';
                    } else if (!/^[A-Z][a-zA-Z\s]*$/.test(fieldValue)) {
                        errorMessage = 'Can only contain letters';
                    }
                }
                break;
            case 'last_name':
                if (fieldValue === '') {
                    errorMessage = 'Last name is required';
                } else if (fieldValue.length < 2) {
                    errorMessage = 'Must be at least 2 characters';
                } else if (!/^[A-Z]/.test(fieldValue)) {
                    errorMessage = 'Must start with capital letter';
                } else if (!/^[A-Z][a-zA-Z\s]*$/.test(fieldValue)) {
                    errorMessage = 'Can only contain letters';
                }
                break;
            case 'email':
                if (fieldValue === '') {
                    errorMessage = 'Email is required';
                } else if (fieldValue.indexOf('@') === -1) {
                    errorMessage = 'Email must contain @ symbol';
                } else if (!isValidEmail(fieldValue)) {
                    errorMessage = 'Please enter a valid email address';
                }
                break;
            case 'phone':
                if (fieldValue === '') {
                    errorMessage = 'Phone number is required';
                } else if (!/^[0-9]{8,12}$/.test(fieldValue)) {
                    errorMessage = 'Phone number must be 8-12 digits';
                }
                break;
            case 'password':
                if (fieldValue === '') {
                    errorMessage = 'Password is required';
                } else if (fieldValue.length < 8) {
                    errorMessage = 'Password must be at least 8 characters';
                } else if (fieldValue.length > 15) {
                    errorMessage = 'Password must not exceed 15 characters';
                } else if (!/[0-9]/.test(fieldValue)) {
                    errorMessage = 'Must contain at least one digit';
                } else if (!/[!@#$%^&*(),.?":{}|<>]/.test(fieldValue)) {
                    errorMessage = 'Must contain at least one special character';
                }
                break;
            case 'confirm_password':
                const password = document.getElementById('password').value;
                if (fieldValue === '') {
                    errorMessage = 'Please confirm your password';
                } else if (fieldValue !== password) {
                    errorMessage = 'Passwords do not match';
                }
                break;
            case 'shipping_address':
                if (fieldValue === '') {
                    errorMessage = 'Shipping address is required';
                } else if (fieldValue.length < 10) {
                    errorMessage = 'Please enter a complete address (at least 10 characters)';
                }
                break;
            case 'pincode':
                if (fieldValue === '') {
                    errorMessage = 'Pincode is required';
                } else if (!/^[0-9]{5,10}$/.test(fieldValue)) {
                    errorMessage = 'Pincode must be 5-10 digits';
                }
                break;
            case 'admin_pin':
                if (isAdminRole) {
                    if (fieldValue === '') {
                        errorMessage = 'Admin PIN is required';
                    } else if (!/^[0-9]{4}$/.test(fieldValue)) {
                        errorMessage = 'PIN must be exactly 4 digits';
                    }
                }
                break;
        }
        
        if (errorMessage) {
            field.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = errorMessage;
            field.parentNode.appendChild(errorDiv);
        }
    }
    
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    function showMessage(element, message, type) {
        element.textContent = message;
        element.className = 'otp-message ' + type;
    }
    
    // Form submission
    registerForm.addEventListener('submit', function(e) {
        if (!otpVerified) {
            e.preventDefault();
            alert('Please verify your email with OTP before submitting');
            return false;
        }
        
        const currentEmail = emailInput.value.trim();
        const storedVerifiedEmail = verifiedEmailInput.value.trim();
        
        if (!storedVerifiedEmail || currentEmail !== storedVerifiedEmail) {
            e.preventDefault();
            alert('Email verification issue. Please verify your email again.');
            return false;
        }
        
        // Check admin PIN if admin role selected
        if (isAdminRole) {
            const adminPin = adminPinInput.value.trim();
            if (!adminPin || adminPin.length !== 4) {
                e.preventDefault();
                alert('Please enter a valid 4-digit admin PIN');
                adminPinInput.focus();
                return false;
            }
        }
        
        submitBtn.disabled = true;
        submitBtn.textContent = 'Registering...';
    });
});

