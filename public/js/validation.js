document.addEventListener('DOMContentLoaded', function() {
    // Get all form inputs
    const inputs = document.querySelectorAll('input, textarea');
    
    // Add blur event listeners to all inputs
    inputs.forEach(input => {
        input.addEventListener('blur', function() {
            validateField(this);
        });
        
        // Remove error styling when user starts typing
        input.addEventListener('input', function() {
            this.classList.remove('error');
            const errorDiv = this.parentNode.querySelector('.field-error');
            if (errorDiv && !errorDiv.hasAttribute('data-server-error')) {
                errorDiv.remove();
            }
        });
    });
    
    // Validation function
    function validateField(field) {
        const fieldName = field.name;
        const fieldValue = field.value.trim();
        let errorMessage = '';
        
        // Remove previous client-side error
        const existingError = field.parentNode.querySelector('.field-error:not([data-server-error])');
        if (existingError) {
            existingError.remove();
        }
        field.classList.remove('error');
        
        // Validate based on field name
        switch(fieldName) {
            case 'email':
                if (fieldValue === '') {
                    errorMessage = 'Email is required';
                } else if (fieldValue.indexOf('@') === -1) {
                    errorMessage = 'Email must contain @ symbol';
                } else if (!isValidEmail(fieldValue)) {
                    errorMessage = 'Please enter a valid email address';
                }
                break;
                
            case 'password':
                if (fieldValue === '') {
                    errorMessage = 'Password is required';
                } else if (fieldValue.length < 6) {
                    errorMessage = 'Password must be at least 6 characters';
                }
                break;
        }
        
        // Show error if exists
        if (errorMessage) {
            field.classList.add('error');
            const errorDiv = document.createElement('div');
            errorDiv.className = 'field-error';
            errorDiv.textContent = errorMessage;
            field.parentNode.appendChild(errorDiv);
        }
    }
    
    // Email validation helper
    function isValidEmail(email) {
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
    }
    
    // Optional: Add loading state to submit button
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function() {
            const submitBtn = this.querySelector('.btn-primary');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.textContent = 'Please wait...';
            }
        });
    });
});
