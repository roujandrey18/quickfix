-- ============================================
-- QuickFix Database Setup
-- Complete database structure with sample data
-- ============================================

CREATE DATABASE IF NOT EXISTS quickfix_db;
USE quickfix_db;

-- ============================================
-- TABLE STRUCTURES
-- ============================================

-- Users table (for all user types: admin, customer/user, provider)
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    phone VARCHAR(20),
    address TEXT,
    user_type ENUM('admin', 'user', 'provider') NOT NULL,
    profile_image VARCHAR(255) DEFAULT 'default.jpg',
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Services table
CREATE TABLE IF NOT EXISTS services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    category VARCHAR(50) NOT NULL,
    base_price DECIMAL(10,2) NOT NULL,
    image VARCHAR(255) DEFAULT 'service-default.jpg',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Provider Services table (many-to-many relationship)
CREATE TABLE IF NOT EXISTS provider_services (
    id INT PRIMARY KEY AUTO_INCREMENT,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    availability ENUM('available', 'unavailable') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    UNIQUE KEY unique_provider_service (provider_id, service_id)
);

-- Bookings table
CREATE TABLE IF NOT EXISTS bookings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    provider_id INT NOT NULL,
    service_id INT NOT NULL,
    booking_date DATE NOT NULL,
    booking_time TIME NOT NULL,
    status ENUM('pending', 'confirmed', 'in_progress', 'completed', 'cancelled') DEFAULT 'pending',
    total_amount DECIMAL(10,2) NOT NULL,
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE
);

-- Reviews table
CREATE TABLE IF NOT EXISTS reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    booking_id INT NOT NULL,
    user_id INT NOT NULL,
    provider_id INT NOT NULL,
    rating INT CHECK (rating >= 1 AND rating <= 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (booking_id) REFERENCES bookings(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User Favorites table
CREATE TABLE IF NOT EXISTS user_favorites (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    service_id INT NOT NULL,
    provider_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_favorite (user_id, service_id, provider_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System Settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('boolean', 'string', 'number') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- ============================================
-- SAMPLE DATA
-- ============================================

-- Insert default admin user (password: password)
INSERT INTO users (username, email, password, full_name, phone, address, user_type, status) VALUES
('admin', 'admin@quickfix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', '+1234567890', '123 Admin Street, City', 'admin', 'active');

-- Insert sample customer users (password: password)
INSERT INTO users (username, email, password, full_name, phone, address, user_type, status) VALUES
('john_doe', 'john.doe@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'John Doe', '+1234567891', '456 Customer Lane, City', 'user', 'active'),
('jane_smith', 'jane.smith@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Jane Smith', '+1234567892', '789 Client Road, City', 'user', 'active'),
('mike_wilson', 'mike.wilson@email.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mike Wilson', '+1234567893', '321 Buyer Avenue, City', 'user', 'active');

-- Insert sample service provider users (password: password)
INSERT INTO users (username, email, password, full_name, phone, address, user_type, status) VALUES
('bob_cleaner', 'bob.cleaner@quickfix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bob the Cleaner', '+1234567894', '111 Service Street, City', 'provider', 'active'),
('alice_plumber', 'alice.plumber@quickfix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Alice the Plumber', '+1234567895', '222 Repair Road, City', 'provider', 'active'),
('charlie_electric', 'charlie.electric@quickfix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Charlie Electrician', '+1234567896', '333 Power Avenue, City', 'provider', 'active'),
('david_gardener', 'david.gardener@quickfix.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'David the Gardener', '+1234567897', '444 Green Lane, City', 'provider', 'active');

-- Insert sample services with different categories
INSERT INTO services (name, description, category, base_price, status) VALUES
('Home Cleaning', 'Professional home cleaning service including dusting, mopping, and sanitizing', 'Cleaning', 50.00, 'active'),
('Deep Cleaning', 'Comprehensive deep cleaning for homes and offices', 'Cleaning', 100.00, 'active'),
('Plumbing Repair', 'Fix leaks, unclog drains, pipe installations and repairs', 'Maintenance', 75.00, 'active'),
('Emergency Plumbing', '24/7 emergency plumbing services', 'Maintenance', 150.00, 'active'),
('Electrical Work', 'Electrical installations, repairs, and maintenance', 'Maintenance', 80.00, 'active'),
('Electrical Inspection', 'Complete electrical system inspection and certification', 'Maintenance', 120.00, 'active'),
('Gardening', 'Garden maintenance, landscaping, and lawn care', 'Outdoor', 60.00, 'active'),
('Tree Trimming', 'Professional tree trimming and pruning services', 'Outdoor', 90.00, 'active'),
('AC Repair', 'Air conditioning service, repair, and maintenance', 'Maintenance', 90.00, 'active'),
('AC Installation', 'Professional air conditioning installation services', 'Maintenance', 200.00, 'active'),
('Carpet Cleaning', 'Deep carpet and upholstery cleaning', 'Cleaning', 70.00, 'active'),
('Window Cleaning', 'Interior and exterior window cleaning services', 'Cleaning', 45.00, 'active');

-- Link providers to services with their custom pricing
-- Bob the Cleaner offers cleaning services
INSERT INTO provider_services (provider_id, service_id, price, availability) VALUES
(5, 1, 50.00, 'available'),  -- Home Cleaning
(5, 2, 95.00, 'available'),  -- Deep Cleaning
(5, 11, 70.00, 'available'), -- Carpet Cleaning
(5, 12, 45.00, 'available'); -- Window Cleaning

-- Alice the Plumber offers plumbing services
INSERT INTO provider_services (provider_id, service_id, price, availability) VALUES
(6, 3, 75.00, 'available'),  -- Plumbing Repair
(6, 4, 150.00, 'available'); -- Emergency Plumbing

-- Charlie Electrician offers electrical services
INSERT INTO provider_services (provider_id, service_id, price, availability) VALUES
(7, 5, 80.00, 'available'),  -- Electrical Work
(7, 6, 120.00, 'available'); -- Electrical Inspection

-- David the Gardener offers outdoor services
INSERT INTO provider_services (provider_id, service_id, price, availability) VALUES
(8, 7, 60.00, 'available'),  -- Gardening
(8, 8, 90.00, 'available');  -- Tree Trimming

-- Insert sample bookings
INSERT INTO bookings (user_id, provider_id, service_id, booking_date, booking_time, status, total_amount, notes) VALUES
(2, 5, 1, '2025-10-20', '10:00:00', 'confirmed', 50.00, 'Please bring cleaning supplies'),
(2, 6, 3, '2025-10-22', '14:00:00', 'pending', 75.00, 'Kitchen sink is leaking'),
(3, 7, 5, '2025-10-18', '09:00:00', 'completed', 80.00, 'Install new light fixtures in living room'),
(3, 5, 2, '2025-10-25', '11:00:00', 'confirmed', 95.00, 'Full house deep cleaning needed'),
(4, 8, 7, '2025-10-21', '08:00:00', 'confirmed', 60.00, 'Weekly lawn maintenance'),
(4, 6, 4, '2025-10-15', '20:00:00', 'completed', 150.00, 'Emergency - Water pipe burst');

-- Insert sample reviews
INSERT INTO reviews (booking_id, user_id, provider_id, rating, comment) VALUES
(3, 3, 7, 5, 'Excellent work! Very professional and completed the job quickly.'),
(6, 4, 6, 5, 'Responded immediately to the emergency. Great service!');

-- Insert system settings
INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES
('maintenance_mode', '0', 'boolean', 'Put the site in maintenance mode'),
('user_registration', '1', 'boolean', 'Allow new users to register'),
('provider_registration', '1', 'boolean', 'Allow new service providers to register'),
('email_notifications', '1', 'boolean', 'Send email notifications for bookings'),
('booking_approval', '0', 'boolean', 'Require admin approval for bookings'),
('site_name', 'QuickFix', 'string', 'The name of the application'),
('max_upload_size', '5', 'number', 'Maximum file upload size in MB'),
('session_timeout', '30', 'number', 'Session timeout in minutes'),
('default_currency', 'PHP', 'string', 'Default currency for the platform'),
('admin_email', 'admin@quickfix.com', 'string', 'Primary admin email address');

-- ============================================
-- END OF DATABASE SETUP
-- ============================================
