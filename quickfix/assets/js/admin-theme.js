// Admin Theme Enhancement Script
// This file contains all the admin-specific JavaScript functionality

// Admin Theme Initialization Functions
function initializeAdminTheme() {
    // Apply admin-specific styling enhancements
    document.body.classList.add('admin-theme');
    
    // Initialize glassmorphism effects
    const glassContainers = document.querySelectorAll('.glass-container');
    glassContainers.forEach(container => {
        container.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-5px)';
            this.style.transition = 'all 0.3s ease';
        });
        
        container.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
        });
    });
    
    // Enhanced stat cards with hover effects
    const statCards = document.querySelectorAll('.stat-card');
    statCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05) translateY(-5px)';
            this.style.transition = 'all 0.3s cubic-bezier(0.4, 0, 0.2, 1)';
            this.style.boxShadow = '0 20px 40px rgba(0,0,0,0.1)';
        });
        
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1) translateY(0)';
            this.style.boxShadow = '';
        });
    });
}

function initializeAdminDropdowns() {
    // Enhanced dropdown functionality for admin interface
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Close other dropdowns
            document.querySelectorAll('.nav-dropdown.active').forEach(dropdown => {
                if (dropdown !== this.parentElement) {
                    dropdown.classList.remove('active');
                }
            });
            
            // Toggle current dropdown
            this.parentElement.classList.toggle('active');
        });
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
    
    // Keyboard navigation for dropdowns
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            document.querySelectorAll('.nav-dropdown.active').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
}

function initializeMobileNavigation() {
    const mobileMenuBtn = document.querySelector('.mobile-menu-btn');
    const navMenu = document.querySelector('.nav-menu');
    
    if (mobileMenuBtn && navMenu) {
        mobileMenuBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            navMenu.classList.toggle('active');
            this.classList.toggle('active');
            
            // Animate hamburger icon
            const icon = this.querySelector('i');
            if (icon) {
                icon.classList.toggle('fa-bars');
                icon.classList.toggle('fa-times');
            }
        });
        
        // Close mobile menu when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-container')) {
                navMenu.classList.remove('active');
                mobileMenuBtn.classList.remove('active');
                const icon = mobileMenuBtn.querySelector('i');
                if (icon) {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                }
            }
        });
    }
}

function initializeDashboardFeatures() {
    // Real-time dashboard updates
    const refreshBtn = document.querySelector('.refresh-dashboard');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';
            setTimeout(() => {
                location.reload();
            }, 1000);
        });
    }
    
    // Interactive charts and graphs
    initializeCharts();
    
    // Admin quick actions
    const quickActions = document.querySelectorAll('.quick-action');
    quickActions.forEach(action => {
        action.addEventListener('click', function() {
            this.style.transform = 'scale(0.95)';
            setTimeout(() => {
                this.style.transform = 'scale(1)';
            }, 150);
        });
    });
}

function initializeNotifications() {
    // Enhanced notification system
    const notifications = document.querySelectorAll('.notification');
    notifications.forEach(notification => {
        // Show animation
        setTimeout(() => {
            notification.classList.add('show');
        }, 100);
        
        // Auto-hide with progress bar
        const progressBar = document.createElement('div');
        progressBar.className = 'notification-progress';
        progressBar.style.cssText = `
            position: absolute;
            bottom: 0;
            left: 0;
            height: 3px;
            background: rgba(255,255,255,0.3);
            animation: notificationProgress 5s linear forwards;
        `;
        notification.style.position = 'relative';
        notification.appendChild(progressBar);
        
        setTimeout(() => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        }, 5000);
        
        // Close button functionality
        const closeBtn = document.createElement('button');
        closeBtn.innerHTML = '<i class="fas fa-times"></i>';
        closeBtn.className = 'notification-close';
        closeBtn.style.cssText = `
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            color: inherit;
            cursor: pointer;
            opacity: 0.7;
            z-index: 1;
        `;
        closeBtn.addEventListener('click', () => {
            notification.classList.remove('show');
            setTimeout(() => {
                if (notification.parentElement) {
                    notification.remove();
                }
            }, 300);
        });
        notification.appendChild(closeBtn);
    });
}

function initializeTableSorting() {
    const tableHeaders = document.querySelectorAll('th[data-sort], .sortable th');
    tableHeaders.forEach(header => {
        header.style.cursor = 'pointer';
        header.style.userSelect = 'none';
        
        // Add sort indicator
        const sortIcon = document.createElement('i');
        sortIcon.className = 'fas fa-sort sort-icon';
        sortIcon.style.marginLeft = '5px';
        sortIcon.style.opacity = '0.5';
        header.appendChild(sortIcon);
        
        header.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            if (!tbody) return;
            
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const columnIndex = Array.from(this.parentElement.children).indexOf(this);
            
            // Remove active state from other headers
            tableHeaders.forEach(h => {
                if (h !== this && h.closest('table') === table) {
                    h.classList.remove('sort-asc', 'sort-desc');
                    const icon = h.querySelector('.sort-icon');
                    if (icon) icon.className = 'fas fa-sort sort-icon';
                }
            });
            
            // Toggle sort direction
            const isAsc = this.classList.contains('sort-asc');
            this.classList.toggle('sort-asc', !isAsc);
            this.classList.toggle('sort-desc', isAsc);
            
            // Update sort icon
            const icon = this.querySelector('.sort-icon');
            if (icon) {
                icon.className = isAsc ? 'fas fa-sort-up sort-icon' : 'fas fa-sort-down sort-icon';
            }
            
            rows.sort((a, b) => {
                const aCell = a.cells[columnIndex];
                const bCell = b.cells[columnIndex];
                if (!aCell || !bCell) return 0;
                
                const aValue = aCell.textContent.trim();
                const bValue = bCell.textContent.trim();
                
                let result = 0;
                if (!isNaN(aValue) && !isNaN(bValue)) {
                    result = parseFloat(aValue) - parseFloat(bValue);
                } else {
                    result = aValue.localeCompare(bValue);
                }
                
                return isAsc ? -result : result;
            });
            
            rows.forEach(row => tbody.appendChild(row));
        });
    });
}

function initializeCharts() {
    // Initialize Chart.js charts if available
    if (typeof Chart !== 'undefined') {
        const chartElements = document.querySelectorAll('[data-chart]');
        chartElements.forEach(element => {
            console.log('Initializing chart:', element.getAttribute('data-chart'));
        });
    }
}

// Admin utility functions
function showAdminNotification(message, type = 'info', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check' : type === 'error' ? 'exclamation-triangle' : 'info'}-circle"></i>
        ${message}
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => notification.classList.add('show'), 100);
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 300);
    }, duration);
}

function confirmAdminAction(message, callback) {
    const modal = document.createElement('div');
    modal.className = 'admin-confirm-modal';
    modal.innerHTML = `
        <div class="modal-overlay"></div>
        <div class="modal-content">
            <h3><i class="fas fa-exclamation-triangle"></i> Confirm Action</h3>
            <p>${message}</p>
            <div class="modal-actions">
                <button class="btn btn-danger confirm-yes">Yes, Continue</button>
                <button class="btn btn-secondary confirm-no">Cancel</button>
            </div>
        </div>
    `;
    
    document.body.appendChild(modal);
    setTimeout(() => modal.classList.add('show'), 100);
    
    modal.querySelector('.confirm-yes').addEventListener('click', () => {
        callback(true);
        document.body.removeChild(modal);
    });
    
    modal.querySelector('.confirm-no').addEventListener('click', () => {
        callback(false);
        document.body.removeChild(modal);
    });
    
    modal.querySelector('.modal-overlay').addEventListener('click', () => {
        callback(false);
        document.body.removeChild(modal);
    });
}

// Initialize admin theme when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Check if we're in admin area
    if (document.body.classList.contains('admin') || 
        window.location.pathname.includes('/admin/') ||
        document.querySelector('.admin-nav')) {
        
        initializeAdminTheme();
        initializeAdminDropdowns();
        initializeMobileNavigation();
        initializeDashboardFeatures();
        initializeNotifications();
        initializeTableSorting();
        
        console.log('Admin theme initialized successfully!');
    }
});

// Add CSS animations for admin theme
const adminStyle = document.createElement('style');
adminStyle.textContent = `
    @keyframes notificationProgress {
        from { width: 100%; }
        to { width: 0%; }
    }
    
    .admin-theme .glass-container {
        transition: all 0.3s ease;
    }
    
    .admin-theme .stat-card:hover {
        cursor: pointer;
    }
    
    .notification-close:hover {
        opacity: 1 !important;
    }
    
    .sort-icon {
        transition: all 0.2s ease;
    }
    
    .admin-confirm-modal {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: 10000;
        display: flex;
        align-items: center;
        justify-content: center;
        opacity: 0;
        visibility: hidden;
        transition: all 0.3s ease;
    }
    
    .admin-confirm-modal.show {
        opacity: 1;
        visibility: visible;
    }
    
    .admin-confirm-modal .modal-overlay {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.5);
        backdrop-filter: blur(5px);
    }
    
    .admin-confirm-modal .modal-content {
        background: var(--glass-bg);
        backdrop-filter: blur(20px);
        border: 1px solid var(--glass-border);
        border-radius: 15px;
        padding: 2rem;
        max-width: 400px;
        width: 90%;
        transform: scale(0.9);
        transition: transform 0.3s ease;
        position: relative;
        z-index: 1;
        box-shadow: var(--shadow);
    }
    
    .admin-confirm-modal.show .modal-content {
        transform: scale(1);
    }
    
    .admin-confirm-modal h3 {
        color: var(--text-dark);
        margin-bottom: 1rem;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .admin-confirm-modal p {
        color: var(--text-dark);
        margin-bottom: 1.5rem;
        opacity: 0.8;
    }
    
    .admin-confirm-modal .modal-actions {
        display: flex;
        gap: 1rem;
        justify-content: flex-end;
    }

    /* Enhanced admin navigation styles */
    .admin-theme .nav-dropdown.active .dropdown-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }

    .admin-theme .nav-dropdown .dropdown-menu {
        opacity: 0;
        visibility: hidden;
        transform: translateY(-10px);
        transition: all 0.3s ease;
    }

    /* Enhanced glass morphism for admin */
    .admin-theme .glass-container {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .admin-theme .glass-container:hover {
        background: rgba(255, 255, 255, 0.15);
        border: 1px solid rgba(255, 255, 255, 0.3);
    }

    /* Enhanced stat card animations */
    .admin-theme .stat-card {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .admin-theme .stat-card:active {
        transform: scale(0.98);
    }
`;
document.head.appendChild(adminStyle);