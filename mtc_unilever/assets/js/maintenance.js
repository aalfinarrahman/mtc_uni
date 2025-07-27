// Maintenance Request JavaScript Functions

// Tab switching functionality
function showTab(tabName) {
    // Hide all tab contents
    const tabContents = document.querySelectorAll('.tab-content');
    tabContents.forEach(tab => {
        tab.classList.remove('active');
    });
    
    // Remove active class from all buttons
    const tabButtons = document.querySelectorAll('.tab-button');
    tabButtons.forEach(button => {
        button.classList.remove('active');
    });
    
    // Show selected tab
    const targetTab = document.getElementById(tabName + '-tab');
    if (targetTab) {
        targetTab.classList.add('active');
    }
    
    // Add active class to clicked button
    event.target.classList.add('active');
}

// Enhanced form validation with better UX
function validateMaintenanceForm() {
    const requiredFields = [
        'requester_name',
        'request_title', 
        'request_date',
        'incident_time',
        'production_line',
        'machine_name',
        'shift_number',
        'damage_type',
        'problem_description'
    ];
    
    let isValid = true;
    let firstInvalidField = null;
    
    // Clear previous error states
    document.querySelectorAll('.form-group input, .form-group select, .form-group textarea').forEach(field => {
        field.style.borderColor = '#e9ecef';
        const errorMsg = field.parentNode.querySelector('.error-message');
        if (errorMsg) {
            errorMsg.remove();
        }
    });
    
    requiredFields.forEach(fieldName => {
        const field = document.getElementById(fieldName);
        if (field && !field.value.trim()) {
            field.style.borderColor = '#dc3545';
            
            // Add error message
            const errorMsg = document.createElement('small');
            errorMsg.className = 'error-message';
            errorMsg.style.color = '#dc3545';
            errorMsg.style.fontSize = '12px';
            errorMsg.style.marginTop = '5px';
            errorMsg.textContent = 'Field ini wajib diisi';
            field.parentNode.appendChild(errorMsg);
            
            if (!firstInvalidField) {
                firstInvalidField = field;
            }
            isValid = false;
        }
    });
    
    if (!isValid && firstInvalidField) {
        firstInvalidField.focus();
        firstInvalidField.scrollIntoView({ behavior: 'smooth', block: 'center' });
        
        // Show toast notification
        showToast('Mohon lengkapi semua field yang wajib diisi', 'error');
    }
    
    return isValid;
}

// Toast notification function
function showToast(message, type = 'info') {
    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
        <i class="fas fa-${type === 'error' ? 'exclamation-circle' : 'check-circle'}"></i>
        <span>${message}</span>
    `;
    
    // Toast styles
    Object.assign(toast.style, {
        position: 'fixed',
        top: '20px',
        right: '20px',
        background: type === 'error' ? '#dc3545' : '#28a745',
        color: 'white',
        padding: '15px 20px',
        borderRadius: '8px',
        boxShadow: '0 4px 20px rgba(0,0,0,0.15)',
        zIndex: '9999',
        display: 'flex',
        alignItems: 'center',
        gap: '10px',
        fontSize: '14px',
        fontWeight: '500',
        transform: 'translateX(100%)',
        transition: 'transform 0.3s ease'
    });
    
    document.body.appendChild(toast);
    
    // Animate in
    setTimeout(() => {
        toast.style.transform = 'translateX(0)';
    }, 100);
    
    // Remove after 3 seconds
    setTimeout(() => {
        toast.style.transform = 'translateX(100%)';
        setTimeout(() => {
            if (toast.parentNode) {
                toast.parentNode.removeChild(toast);
            }
        }, 300);
    }, 3000);
}

// Auto-fill current date and time
function initializeDateTimeFields() {
    const now = new Date();
    
    // Set current date
    const dateField = document.getElementById('request_date');
    if (dateField && !dateField.value) {
        dateField.value = now.toISOString().split('T')[0];
    }
    
    // Set current time
    const timeField = document.getElementById('incident_time');
    if (timeField && !timeField.value) {
        const hours = String(now.getHours()).padStart(2, '0');
        const minutes = String(now.getMinutes()).padStart(2, '0');
        timeField.value = `${hours}:${minutes}`;
    }
}

// Initialize when page loads
document.addEventListener('DOMContentLoaded', function() {
    initializeDateTimeFields();
    
    // Add form validation on submit
    const form = document.getElementById('maintenance-form');
    if (form) {
        form.addEventListener('submit', function(e) {
            if (!validateMaintenanceForm()) {
                e.preventDefault();
            }
        });
    }
    
    // Real-time validation feedback
    const requiredFields = document.querySelectorAll('input[required], select[required], textarea[required]');
    requiredFields.forEach(field => {
        field.addEventListener('blur', function() {
            if (this.value.trim()) {
                this.style.borderColor = '#28a745';
            } else {
                this.style.borderColor = '#dc3545';
            }
        });
        
        field.addEventListener('input', function() {
            if (this.value.trim()) {
                this.style.borderColor = '#ddd';
            }
        });
    });
});