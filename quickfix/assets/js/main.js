// QuickFix - Main JavaScript Functions

document.addEventListener('DOMContentLoaded', function() {
    // Initialize admin theme functionality
    initializeAdminTheme();
    
    // Smooth scrolling for navigation links
    const navLinks = document.querySelectorAll('a[href^="#"]');
    navLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetSection = document.querySelector(targetId);
            if (targetSection) {
                targetSection.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });

    // Enhanced Admin Dropdown Functionality
    initializeAdminDropdowns();
    
    // Enhanced Mobile Navigation
    initializeMobileNavigation();
    
    // Admin Dashboard Enhancements
    initializeDashboardFeatures();
    
    // Form validation and enhancement
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.innerHTML = '<div class="loading"></div> Processing...';
                submitBtn.disabled = true;
            }
        });
    });

    // Auto-hide notifications with improved animation
    initializeNotifications();

    // Enhanced form inputs with floating labels
    const inputs = document.querySelectorAll('.form-input, .form-control');
    inputs.forEach(input => {
        input.addEventListener('focus', function() {
            this.parentElement.classList.add('focused');
        });
        
        input.addEventListener('blur', function() {
            if (!this.value) {
                this.parentElement.classList.remove('focused');
            }
        });
        
        if (input.value) {
            input.parentElement.classList.add('focused');
        }
    });

    // Table sorting functionality with admin enhancements
    initializeTableSorting();

    // Image upload preview with admin styling
    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(input => {
        input.addEventListener('change', function() {
            const file = this.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    let preview = input.parentElement.querySelector('.image-preview');
                    if (!preview) {
                        preview = document.createElement('img');
                        preview.className = 'image-preview';
                        preview.style.cssText = 'max-width: 200px; max-height: 200px; margin-top: 10px; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2);';
                        input.parentElement.appendChild(preview);
                    }
                    preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // Real-time search functionality
    const searchInputs = document.querySelectorAll('input[data-search]');
    searchInputs.forEach(searchInput => {
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const targetTable = document.querySelector(this.getAttribute('data-search'));
            
            if (targetTable) {
                const rows = targetTable.querySelectorAll('tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchTerm) ? '' : 'none';
                });
            }
        });
    });

    // Status update functionality
    const statusSelects = document.querySelectorAll('select[data-update-status]');
    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            const bookingId = this.getAttribute('data-booking-id');
            const newStatus = this.value;
            
            fetch('update_status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    booking_id: bookingId,
                    status: newStatus
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('Status updated successfully!', 'success');
                } else {
                    showNotification('Error updating status', 'error');
                }
            })
            .catch(error => {
                showNotification('Network error', 'error');
            });
        });
    });
});

// Observe elements for animation
document.querySelectorAll('.card, .service-card, .stat-card').forEach(el => {
    el.style.opacity = '0';
    el.style.transform = 'translateY(20px)';
    el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
});

// Intersection Observer for animations
const observerOptions = {
    threshold: 0.1,
    rootMargin: '0px 0px -50px 0px'
};

const observer = new IntersectionObserver(function(entries) {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.opacity = '1';
            entry.target.style.transform = 'translateY(0)';
        }
    });
}, observerOptions);

document.querySelectorAll('.card, .service-card, .stat-card').forEach(el => {
    observer.observe(el);
});

// Utility functions
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `<i class="fas fa-info-circle"></i> ${message}`;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => notification.remove(), 300);
    }, 3000);
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Service worker registration for PWA features
if ('serviceWorker' in navigator) {
    window.addEventListener('load', function() {
        navigator.serviceWorker.register('/quickfix/sw.js')
            .then(registration => {
                console.log('ServiceWorker registration successful');
            })
            .catch(error => {
                console.log('ServiceWorker registration failed');
            });
    });
}

// Calendar functionality for booking dates
function initDatePicker() {
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(input => {
        const today = new Date().toISOString().split('T')[0];
        input.min = today;
    });
}

// Initialize date picker on page load
initDatePicker();

// Star rating functionality
function initStarRating() {
    const starContainers = document.querySelectorAll('.star-rating');
    
    starContainers.forEach(container => {
        const stars = container.querySelectorAll('.star');
        const hiddenInput = container.querySelector('input[type="hidden"]');
        
        stars.forEach((star, index) => {
            star.addEventListener('click', function() {
                const rating = index + 1;
                hiddenInput.value = rating;
                
                stars.forEach((s, i) => {
                    s.classList.toggle('active', i < rating);
                });
            });
            
            star.addEventListener('mouseenter', function() {
                const rating = index + 1;
                stars.forEach((s, i) => {
                    s.classList.toggle('hover', i < rating);
                });
            });
        });
        
        container.addEventListener('mouseleave', function() {
            stars.forEach(s => s.classList.remove('hover'));
        });
    });
}

initStarRating();

// Add to existing main.js file

// Navbar dropdown functionality
document.addEventListener('DOMContentLoaded', function() {
    // Mobile menu toggle
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn && navMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            navMenu.classList.toggle('show');
            
            // Toggle icon
            const icon = this.querySelector('i');
            if (navMenu.classList.contains('show')) {
                icon.classList.remove('fa-bars');
                icon.classList.add('fa-times');
            } else {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        });
    }
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(e) {
        if (!navMenu?.contains(e.target) && !mobileMenuBtn?.contains(e.target)) {
            navMenu?.classList.remove('show');
            const icon = mobileMenuBtn?.querySelector('i');
            if (icon) {
                icon.classList.remove('fa-times');
                icon.classList.add('fa-bars');
            }
        }
    });
    
    // Handle dropdown menus on mobile
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const dropdown = this.nextElementSibling;
                const isOpen = dropdown.style.display === 'block';
                
                // Close all other dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(menu => {
                    menu.style.display = 'none';
                });
                
                // Toggle current dropdown
                dropdown.style.display = isOpen ? 'none' : 'block';
            }
        });
    });

    // User dropdown functionality
    const userDropdowns = document.querySelectorAll('.user-dropdown');
    userDropdowns.forEach(dropdown => {
        const trigger = dropdown.querySelector('.user-profile-link');
        const menu = dropdown.querySelector('.user-dropdown-menu');
        
        if (trigger && menu) {
            // Handle hover for desktop
            let hoverTimeout;
            
            dropdown.addEventListener('mouseenter', function() {
                clearTimeout(hoverTimeout);
                menu.style.display = 'block';
                setTimeout(() => {
                    menu.style.opacity = '1';
                    menu.style.visibility = 'visible';
                    menu.style.transform = 'translateY(0) scale(1)';
                }, 10);
            });
            
            dropdown.addEventListener('mouseleave', function() {
                hoverTimeout = setTimeout(() => {
                    menu.style.opacity = '0';
                    menu.style.visibility = 'hidden';
                    menu.style.transform = 'translateY(-10px) scale(0.95)';
                    setTimeout(() => {
                        if (menu.style.opacity === '0') {
                            menu.style.display = 'none';
                        }
                    }, 300);
                }, 100);
            });
            
            // Handle click for mobile
            trigger.addEventListener('click', function(e) {
                if (window.innerWidth <= 768) {
                    e.preventDefault();
                    const isVisible = menu.style.opacity === '1';
                    
                    if (isVisible) {
                        menu.style.opacity = '0';
                        menu.style.visibility = 'hidden';
                        menu.style.transform = 'translateY(-10px) scale(0.95)';
                    } else {
                        menu.style.display = 'block';
                        setTimeout(() => {
                            menu.style.opacity = '1';
                            menu.style.visibility = 'visible';
                            menu.style.transform = 'translateY(0) scale(1)';
                        }, 10);
                    }
                }
            });
        }
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        const userDropdowns = document.querySelectorAll('.user-dropdown');
        userDropdowns.forEach(dropdown => {
            const menu = dropdown.querySelector('.user-dropdown-menu');
            if (menu && !dropdown.contains(e.target)) {
                menu.style.opacity = '0';
                menu.style.visibility = 'hidden';
                menu.style.transform = 'translateY(-10px) scale(0.95)';
                setTimeout(() => {
                    if (menu.style.opacity === '0') {
                        menu.style.display = 'none';
                    }
                }, 300);
            }
        });
    });
});

// Navbar scroll effect
window.addEventListener('scroll', function() {
    const navbar = document.getElementById('navbar');
    if (navbar) {
        if (window.scrollY > 50) {
            navbar.classList.add('scrolled');
        } else {
            navbar.classList.remove('scrolled');
        }
    }
});

// Mobile menu functionality
document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuBtn = document.getElementById('mobileMenuBtn');
    const navMenu = document.getElementById('navMenu');
    
    if (mobileMenuBtn && navMenu) {
        mobileMenuBtn.addEventListener('click', function() {
            navMenu.classList.toggle('active');
            mobileMenuBtn.classList.toggle('active');
        });
    }
});
