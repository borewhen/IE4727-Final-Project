document.addEventListener('DOMContentLoaded', function() {
    const checkoutForm = document.getElementById('checkoutForm');
    const paymentOptions = document.querySelectorAll('input[name="payment_method"]');
    const paymentMethodError = document.getElementById('paymentMethodError');
    const paymentMethodsContainer = document.getElementById('paymentMethodsContainer');
    
    console.log('Checkout JS loaded');
    console.log('Form found:', checkoutForm !== null);
    console.log('Payment options found:', paymentOptions.length);
    
    // Add visual feedback to payment options
    paymentOptions.forEach(function(option) {
        option.addEventListener('change', function() {
            console.log('Payment method changed to:', this.value);
            
            // Remove selected class from all options
            document.querySelectorAll('.payment-option').forEach(function(label) {
                label.classList.remove('selected');
            });
            
            // Add selected class to chosen option
            if (this.checked) {
                this.closest('.payment-option').classList.add('selected');
            }
            
            // Hide error message when payment method is selected
            if (paymentMethodError) {
                paymentMethodError.style.display = 'none';
            }
            if (paymentMethodsContainer) {
                paymentMethodsContainer.style.border = '';
                paymentMethodsContainer.style.padding = '';
            }
        });
    });
    
    // Form validation
    if (checkoutForm) {
        checkoutForm.addEventListener('submit', function(e) {
            console.log('Form submit event triggered');
            
            let isValid = true;
            const errors = [];
            
            // Hide previous payment method error
            if (paymentMethodError) {
                paymentMethodError.style.display = 'none';
            }
            if (paymentMethodsContainer) {
                paymentMethodsContainer.style.border = '';
                paymentMethodsContainer.style.padding = '';
            }
            
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
            
            // Validate payment method
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            console.log('Payment method selected:', paymentMethod ? paymentMethod.value : 'none');
            if (!paymentMethod) {
                errors.push('Please select a payment method');
                isValid = false;
                
                // Show inline error message
                if (paymentMethodError) {
                    paymentMethodError.style.display = 'block';
                }
                
                // Add visual border to payment methods container
                if (paymentMethodsContainer) {
                    paymentMethodsContainer.style.border = '2px solid #dc3545';
                    paymentMethodsContainer.style.padding = '10px';
                    paymentMethodsContainer.style.borderRadius = '4px';
                }
                
                // Scroll to payment method section
                if (paymentMethodsContainer) {
                    paymentMethodsContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
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