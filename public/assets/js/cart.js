/*
// Increment quantity
function incrementQty(button, maxQty) {
    const input = button.parentElement.querySelector('.qty-input');
    let currentValue = parseInt(input.value);
    
    if (currentValue < maxQty) {
        input.value = currentValue + 1;
        input.form.submit();
    } else {
        alert('Maximum stock quantity reached!');
    }
}

// Decrement quantity
function decrementQty(button) {
    const input = button.parentElement.querySelector('.qty-input');
    let currentValue = parseInt(input.value);
    
    if (currentValue > 1) {
        input.value = currentValue - 1;
        input.form.submit();
    }
}

// Auto-hide success/error messages
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.success-message, .error-message');
    
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s';
            message.style.opacity = '0';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 3000);
    });
});
*/
// Increment quantity
function incrementQty(button, maxQty) {
    const input = button.parentElement.querySelector('.qty-input');
    let currentValue = parseInt(input.value);
    
    if (currentValue < maxQty) {
        input.value = currentValue + 1;
        input.form.submit();
    } else {
        alert('Maximum stock quantity reached!');
    }
}

// Decrement quantity
function decrementQty(button) {
    const input = button.parentElement.querySelector('.qty-input');
    let currentValue = parseInt(input.value);
    
    if (currentValue > 1) {
        input.value = currentValue - 1;
        input.form.submit();
    }
}

// Auto-hide success/error messages
document.addEventListener('DOMContentLoaded', function() {
    const messages = document.querySelectorAll('.message');
    
    messages.forEach(function(message) {
        setTimeout(function() {
            message.style.transition = 'opacity 0.5s, transform 0.5s';
            message.style.opacity = '0';
            message.style.transform = 'translateY(-10px)';
            setTimeout(function() {
                message.remove();
            }, 500);
        }, 4000);
    });
});