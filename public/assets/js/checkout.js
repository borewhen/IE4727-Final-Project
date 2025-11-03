document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            
            let isValid = true;
            const errors = [];
            
            // Validate shipping address
            const shippingAddress = document.getElementById('shipping_address');
            if (shippingAddress) {
                const addressValue = shippingAddress.value.trim();
                console.log('Shipping address length:', addressValue.length);
                if (addressValue.length < 10) {
                    errors.push('Please enter a complete shipping address (at least 10 characters)');
                    isValid = false;
                }
            } else {
                console.error('Shipping address field not found');
            }
            
            // Validate phone
            const phone = document.getElementById('phone');
            if (phone) {
                const phoneValue = phone.value.trim();
                console.log('Phone length:', phoneValue.length);
                if (phoneValue.length < 8) {
                    errors.push('Please enter a valid phone number');
                    isValid = false;
                }
            } else {
                console.error('Phone field not found');
            }
            
            if (!isValid) {
                console.log('Validation failed:', errors);
                e.preventDefault();
                
                // Show alert with clearer message
                let alertMessage = '⚠️ Please fix the following to continue:\n\n';
                alertMessage += errors.join('\n');
                alert(alertMessage);
                
                return false;
            }
            
            console.log('Validation passed, submitting form');
            
            // Disable submit button to prevent double submission
            const submitBtn = checkoutForm.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Processing Order...';
                console.log('Submit button disabled');
            }
            
            // Allow form to submit naturally
            return true;
        });
    } else {
        console.error('Checkout form not found!');
    }
    
    // Auto-hide messages
    const messages = document.querySelectorAll('.message');
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s, transform 0.5s';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 5000);
    });
});