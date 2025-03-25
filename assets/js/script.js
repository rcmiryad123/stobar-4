// Main JavaScript file for STOBAR application

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips if any
    initTooltips();
    
    // Charts are initialized directly in index.php
    
    // Add event listeners for modals
    setupModals();
    
    // Add event listeners for form validations
    setupFormValidations();
    
    // Add animation classes to elements
    animateElements();
});

// Initialize tooltips
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-tooltip]');
    
    tooltips.forEach(tooltip => {
        tooltip.addEventListener('mouseenter', function() {
            const tooltipText = this.getAttribute('data-tooltip');
            const tooltipEl = document.createElement('div');
            tooltipEl.className = 'tooltip';
            tooltipEl.textContent = tooltipText;
            
            document.body.appendChild(tooltipEl);
            
            const rect = this.getBoundingClientRect();
            const tooltipRect = tooltipEl.getBoundingClientRect();
            
            tooltipEl.style.top = `${rect.top - tooltipRect.height - 10}px`;
            tooltipEl.style.left = `${rect.left + (rect.width / 2) - (tooltipRect.width / 2)}px`;
            tooltipEl.style.opacity = '1';
        });
        
        tooltip.addEventListener('mouseleave', function() {
            const tooltipEl = document.querySelector('.tooltip');
            if (tooltipEl) {
                tooltipEl.remove();
            }
        });
    });
}

// Setup modal functionality
function setupModals() {
    const modalTriggers = document.querySelectorAll('[data-modal]');
    
    modalTriggers.forEach(trigger => {
        trigger.addEventListener('click', function() {
            const modalId = this.getAttribute('data-modal');
            const modal = document.getElementById(modalId);
            
            if (modal) {
                modal.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
        });
    });
    
    const closeButtons = document.querySelectorAll('.modal-close');
    
    closeButtons.forEach(button => {
        button.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.classList.remove('active');
            document.body.style.overflow = '';
        });
    });
    
    // Close modal when clicking outside content
    document.addEventListener('click', function(event) {
        if (event.target.classList.contains('modal')) {
            event.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    });
}

// Setup form validations
function setupFormValidations() {
    const forms = document.querySelectorAll('form');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(event) {
            let isValid = true;
            
            // Check required fields
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add('error');
                    
                    // Add error message if not exists
                    let errorMessage = field.nextElementSibling;
                    if (!errorMessage || !errorMessage.classList.contains('error-message')) {
                        errorMessage = document.createElement('div');
                        errorMessage.className = 'error-message';
                        errorMessage.textContent = 'This field is required';
                        field.parentNode.insertBefore(errorMessage, field.nextSibling);
                    }
                } else {
                    field.classList.remove('error');
                    
                    // Remove error message if exists
                    const errorMessage = field.nextElementSibling;
                    if (errorMessage && errorMessage.classList.contains('error-message')) {
                        errorMessage.remove();
                    }
                }
            });
            
            // Prevent form submission if validation fails
            if (!isValid) {
                event.preventDefault();
            }
        });
    });
}

// Add animation classes to elements
function animateElements() {
    const elements = document.querySelectorAll('.stat-card, .data-section, .form-section, .chart-container');
    
    elements.forEach((element, index) => {
        setTimeout(() => {
            element.classList.add('fade-in');
        }, index * 100);
    });
    
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach((item, index) => {
        setTimeout(() => {
            item.classList.add('slide-in');
        }, index * 50);
    });
}

// Initialize usage chart
function initUsageChart() {
    const canvas = document.getElementById('usageChart');
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }
    
    // Sample data - this would be replaced with actual data from the backend
    const labels = ['Laptop', 'Printer Paper', 'Ballpoint Pen', 'Stapler', 'Whiteboard Marker'];
    const data = [3, 5, 2, 0, 4];
    
    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Average Monthly Usage',
                data: data,
                backgroundColor: 'rgba(74, 111, 165, 0.7)',
                borderColor: 'rgba(74, 111, 165, 1)',
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Quantity'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Product'
                    }
                }
            }
        }
    });
}

// Initialize prediction chart
function initPredictionChart() {
    const canvas = document.getElementById('predictionChart');
    const ctx = canvas.getContext('2d');
    
    // Destroy existing chart if it exists
    const existingChart = Chart.getChart(canvas);
    if (existingChart) {
        existingChart.destroy();
    }
    
    // Sample data - this would be replaced with actual data from the backend
    const labels = ['Laptop', 'Printer Paper', 'Ballpoint Pen', 'Stapler', 'Whiteboard Marker'];
    const predictionData = [5, 8, 12, 3, 7];
    
    new Chart(ctx, {
        type: 'line',
        data: {
            labels: labels,
            datasets: [{
                label: 'Predicted Stock Level',
                data: data,
                backgroundColor: 'rgba(165, 74, 74, 0.7)',
                borderColor: 'rgba(165, 74, 74, 1)',
                borderWidth: 1,
                tension: 0.4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    title: {
                        display: true,
                        text: 'Predicted Quantity'
                    }
                },
                x: {
                    title: {
                        display: true,
                        text: 'Product'
                    }
                }
            }
        }
    });
    const data = [45, 12, 18, 30, 8]; // Days until depletion
    
    new Chart(ctx, {
        type: 'horizontalBar',
        data: {
            labels: labels,
            datasets: [{
                label: 'Days Until Depletion',
                data: data,
                backgroundColor: [  
                    'rgba(40, 167, 69, 0.7)',   // Green for items with plenty of time
                    'rgba(255, 193, 7, 0.7)',    // Yellow for items with moderate time
                    'rgba(255, 193, 7, 0.7)',    // Yellow for items with moderate time
                    'rgba(40, 167, 69, 0.7)',    // Green for items with plenty of time
                    'rgba(220, 53, 69, 0.7)'     // Red for items running low
                ],
                borderColor: [
                    'rgba(40, 167, 69, 1)',
                    'rgba(255, 193, 7, 1)',
                    'rgba(255, 193, 7, 1)', 
                    'rgba(40, 167, 69, 1)',
                    'rgba(220, 53, 69, 1)'
                ],
                borderWidth: 1
            }]
        }
    });
}
