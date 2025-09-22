-- Add system settings table to database
USE quickfix_db;

-- Create system_settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(50) UNIQUE NOT NULL,
    setting_value TEXT,
    setting_type ENUM('boolean', 'string', 'number') DEFAULT 'string',
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default settings
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
('admin_email', 'admin@quickfix.com', 'string', 'Primary admin email address')
ON DUPLICATE KEY UPDATE setting_value = setting_value;