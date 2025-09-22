<?php 
require_once '../config/config.php';
checkAccess(['admin']);

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

// Get admin data
$user_query = "SELECT * FROM users WHERE id = :admin_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':admin_id', $admin_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Function to get setting value
function getSetting($db, $key, $default = '') {
    $stmt = $db->prepare("SELECT setting_value, setting_type FROM system_settings WHERE setting_key = :key");
    $stmt->execute([':key' => $key]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        $value = $result['setting_value'];
        if ($result['setting_type'] === 'boolean') {
            return $value === '1' || $value === 'true';
        }
        return $value;
    }
    return $default;
}

// Function to update setting
function updateSetting($db, $key, $value, $type = 'string') {
    // Convert boolean to string for storage
    if ($type === 'boolean') {
        $value = $value ? '1' : '0';
    }
    
    $stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type) 
                         VALUES (:key, :value, :type) 
                         ON DUPLICATE KEY UPDATE setting_value = :value");
    return $stmt->execute([':key' => $key, ':value' => $value, ':type' => $type]);
}

// Create settings table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        setting_key VARCHAR(50) UNIQUE NOT NULL,
        setting_value TEXT,
        setting_type ENUM('boolean', 'string', 'number') DEFAULT 'string',
        description TEXT,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    $db->exec($create_table);
    
    // Insert default settings if table is empty
    $count_stmt = $db->query("SELECT COUNT(*) FROM system_settings");
    if ($count_stmt->fetchColumn() == 0) {
        $defaults = [
            ['maintenance_mode', '0', 'boolean', 'Put the site in maintenance mode'],
            ['user_registration', '1', 'boolean', 'Allow new users to register'],
            ['provider_registration', '1', 'boolean', 'Allow new service providers to register'],
            ['email_notifications', '1', 'boolean', 'Send email notifications for bookings'],
            ['booking_approval', '0', 'boolean', 'Require admin approval for bookings'],
            ['site_name', 'QuickFix', 'string', 'The name of the application'],
            ['max_upload_size', '5', 'number', 'Maximum file upload size in MB'],
            ['session_timeout', '30', 'number', 'Session timeout in minutes'],
            ['admin_email', 'admin@quickfix.com', 'string', 'Primary admin email address']
        ];
        
        $insert_stmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_type, description) VALUES (?, ?, ?, ?)");
        foreach ($defaults as $default) {
            $insert_stmt->execute($default);
        }
    }
} catch (PDOException $e) {
    error_log("Settings table creation failed: " . $e->getMessage());
}

// Get current system settings
$system_settings = [
    'site_name' => getSetting($db, 'site_name', SITE_NAME),
    'maintenance_mode' => getSetting($db, 'maintenance_mode', false),
    'user_registration' => getSetting($db, 'user_registration', true),
    'provider_registration' => getSetting($db, 'provider_registration', true),
    'email_notifications' => getSetting($db, 'email_notifications', true),
    'booking_approval' => getSetting($db, 'booking_approval', false),
    'max_upload_size' => getSetting($db, 'max_upload_size', '5'),
    'session_timeout' => getSetting($db, 'session_timeout', '30'),
    'admin_email' => getSetting($db, 'admin_email', 'admin@quickfix.com')
];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_setting') {
        $setting_key = $_POST['setting_key'] ?? '';
        $setting_value = $_POST['setting_value'] ?? '';
        $setting_type = $_POST['setting_type'] ?? 'string';
        
        if ($setting_key) {
            if (updateSetting($db, $setting_key, $setting_value, $setting_type)) {
                $success = "Setting updated successfully!";
                // Refresh settings
                $system_settings[$setting_key] = ($setting_type === 'boolean') ? ($setting_value === '1') : $setting_value;
            } else {
                $error = "Failed to update setting.";
            }
        }
    }
    
    elseif ($action === 'backup_database') {
        // Real database backup functionality
        $backup_dir = realpath(__DIR__ . '/../backups/');
        if (!$backup_dir) {
            $backup_dir = __DIR__ . '/../backups/';
            if (!file_exists($backup_dir)) {
                if (!mkdir($backup_dir, 0755, true)) {
                    $error = "Failed to create backup directory. Please check permissions.";
                } else {
                    $backup_dir = realpath($backup_dir);
                }
            }
        }
        
        // Ensure directory is writable
        if (!$error && (!is_writable($backup_dir))) {
            $error = "Backup directory is not writable. Please check permissions.";
        }
        
        if (!$error) {
            $backup_file = 'quickfix_backup_' . date('Y-m-d_H-i-s') . '.sql';
            $backup_path = $backup_dir . DIRECTORY_SEPARATOR . $backup_file;
        
        // Get database configuration
        $host = DB_HOST;
        $database = DB_NAME;
        $username = DB_USER;
        $password = DB_PASS;
        
        // Try mysqldump command first (if available)
        $mysqldump_available = false;
        $command = "";
        
        // Check if mysqldump is available
        if (function_exists('exec')) {
            // For Windows with XAMPP
            $mysqldump_paths = [
                'C:\\xampp\\mysql\\bin\\mysqldump.exe',
                'mysqldump' // If it's in PATH
            ];
            
            foreach ($mysqldump_paths as $mysqldump_path) {
                $test_command = "\"{$mysqldump_path}\" --version 2>&1";
                $test_output = [];
                $test_return = 0;
                
                @exec($test_command, $test_output, $test_return);
                if ($test_return === 0) {
                    $mysqldump_available = true;
                    $escaped_path = escapeshellarg($backup_path);
                    if ($password) {
                        $command = "\"{$mysqldump_path}\" --host={$host} --user={$username} --password={$password} {$database} > {$escaped_path} 2>&1";
                    } else {
                        $command = "\"{$mysqldump_path}\" --host={$host} --user={$username} {$database} > {$escaped_path} 2>&1";
                    }
                    break;
                }
            }
        }
        
        $backup_success = false;
        
        if ($mysqldump_available && $command) {
            $output = [];
            $return_var = 0;
            
            exec($command, $output, $return_var);
            
            if ($return_var === 0 && file_exists($backup_path) && filesize($backup_path) > 0) {
                $file_size = round(filesize($backup_path) / 1024, 2);
                $success = "Database backup created successfully using mysqldump: {$backup_file} ({$file_size} KB)";
                $backup_success = true;
            }
        }
        
        if (!$backup_success) {
            // Fallback to PHP-based backup
            try {
                $backup_content = "-- QuickFix Database Backup (PHP Generated)\n";
                $backup_content .= "-- Generated on: " . date('Y-m-d H:i:s') . "\n";
                $backup_content .= "-- Database: {$database}\n\n";
                $backup_content .= "SET FOREIGN_KEY_CHECKS=0;\n\n";
                
                // Get all tables
                $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
                
                if (empty($tables)) {
                    throw new Exception("No tables found in database");
                }
                
                foreach ($tables as $table) {
                    $backup_content .= "-- --------------------------------------------------------\n";
                    $backup_content .= "-- Table structure for `{$table}`\n";
                    $backup_content .= "-- --------------------------------------------------------\n\n";
                    
                    // Drop table if exists
                    $backup_content .= "DROP TABLE IF EXISTS `{$table}`;\n";
                    
                    // Get table structure
                    $create_table = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                    $backup_content .= $create_table['Create Table'] . ";\n\n";
                    
                    // Get table data
                    $backup_content .= "-- Data for table `{$table}`\n\n";
                    $stmt = $db->query("SELECT * FROM `{$table}`");
                    $row_count = 0;
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        if ($row_count === 0) {
                            $columns = array_keys($row);
                            $backup_content .= "INSERT INTO `{$table}` (`" . implode('`, `', $columns) . "`) VALUES\n";
                        }
                        
                        $values = array_map(function($val) use ($db) {
                            return $val === null ? 'NULL' : $db->quote($val);
                        }, array_values($row));
                        
                        $backup_content .= "(" . implode(', ', $values) . ")";
                        $backup_content .= ($row_count > 0 && $stmt->rowCount() > $row_count + 1) ? ",\n" : ";\n";
                        $row_count++;
                    }
                    
                    if ($row_count === 0) {
                        $backup_content .= "-- No data found in table `{$table}`\n";
                    }
                    $backup_content .= "\n";
                }
                
                $backup_content .= "SET FOREIGN_KEY_CHECKS=1;\n";
                
                if (file_put_contents($backup_path, $backup_content) === false) {
                    throw new Exception("Failed to write backup file");
                }
                
                if (!file_exists($backup_path) || filesize($backup_path) === 0) {
                    throw new Exception("Backup file was not created properly");
                }
                
                $file_size = round(filesize($backup_path) / 1024, 2);
                $success = "Database backup created successfully using PHP: {$backup_file} ({$file_size} KB)";
                
            } catch (Exception $e) {
                // Clean up failed backup file
                if (file_exists($backup_path)) {
                    unlink($backup_path);
                }
                $error = "PHP backup failed: " . $e->getMessage();
            }
        }
        } // Close the if (!$error) block
    }
    
    elseif ($action === 'clear_cache') {
        // Enhanced cache clearing functionality
        $cache_cleared = 0;
        $errors = [];
        
        try {
            // Clear PHP OPcache if available
            if (function_exists('opcache_reset')) {
                if (opcache_reset()) {
                    $cache_cleared++;
                } else {
                    $errors[] = "Failed to reset OPcache";
                }
            }
            
            // Clear APCu cache if available
            if (function_exists('apcu_clear_cache')) {
                if (apcu_clear_cache()) {
                    $cache_cleared++;
                } else {
                    $errors[] = "Failed to clear APCu cache";
                }
            }
            
            // Clear old session files (only old ones for safety)
            $session_path = session_save_path() ?: sys_get_temp_dir();
            if (is_dir($session_path) && is_readable($session_path)) {
                $session_files = glob($session_path . '/sess_*');
                $session_cleared = 0;
                foreach ($session_files as $file) {
                    if (is_file($file) && filemtime($file) < time() - 7200) { // 2 hours old
                        if (@unlink($file)) {
                            $session_cleared++;
                        }
                    }
                }
                if ($session_cleared > 0) {
                    $cache_cleared += $session_cleared;
                }
            } else {
                $errors[] = "Session directory not accessible";
            }
            
            // Clear temporary files in various directories
            $temp_dirs = [
                __DIR__ . '/../uploads/temp/',
                __DIR__ . '/../cache/',
                __DIR__ . '/../tmp/'
            ];
            
            foreach ($temp_dirs as $temp_dir) {
                if (is_dir($temp_dir) && is_writable($temp_dir)) {
                    $files = glob($temp_dir . '*');
                    $temp_cleared = 0;
                    foreach ($files as $file) {
                        if (is_file($file)) {
                            // Only delete files older than 1 hour
                            if (filemtime($file) < time() - 3600) {
                                if (@unlink($file)) {
                                    $temp_cleared++;
                                }
                            }
                        }
                    }
                    $cache_cleared += $temp_cleared;
                }
            }
            
            // Clear browser cache headers (for future requests)
            if (!headers_sent()) {
                header("Cache-Control: no-cache, no-store, must-revalidate");
                header("Pragma: no-cache");
                header("Expires: 0");
            }
            
            if (empty($errors)) {
                $success = "System cache cleared successfully! ({$cache_cleared} items processed)";
            } else {
                $success = "Cache clearing completed with some warnings ({$cache_cleared} items processed)";
                $error = "Warnings: " . implode(", ", $errors);
            }
            
        } catch (Exception $e) {
            $error = "Cache clearing failed: " . $e->getMessage();
        }
    }
    
    elseif ($action === 'send_test_email') {
        $test_email = trim($_POST['test_email'] ?? '');
        
        // Validate email address
        if (!filter_var($test_email, FILTER_VALIDATE_EMAIL)) {
            $error = "Invalid email address format.";
        }
        // Check if admin email is configured
        elseif (empty($system_settings['admin_email']) || $system_settings['admin_email'] === 'admin@quickfix.com') {
            $error = "Admin email is not configured. Please set up the admin email in general settings first.";
        }
        // Validate admin email
        elseif (!filter_var($system_settings['admin_email'], FILTER_VALIDATE_EMAIL)) {
            $error = "Admin email address is invalid. Please update it in general settings.";
        }
        else {
            try {
                // Prepare email content
                $subject = "QuickFix - Test Email Notification";
                $message = "Hello,\n\n";
                $message .= "This is a test email sent from the QuickFix administration system.\n\n";
                $message .= "System Information:\n";
                $message .= "- Timestamp: " . date('Y-m-d H:i:s T') . "\n";
                $message .= "- Server: " . $_SERVER['HTTP_HOST'] . "\n";
                $message .= "- From Email: " . $system_settings['admin_email'] . "\n";
                $message .= "- Site Name: " . $system_settings['site_name'] . "\n";
                $message .= "- PHP Version: " . phpversion() . "\n";
                $message .= "- Test Recipient: " . $test_email . "\n\n";
                $message .= "If you received this email, your email system is configured correctly.\n\n";
                $message .= "Best regards,\n";
                $message .= $system_settings['site_name'] . " System";
                
                // Prepare headers
                $headers = [];
                $headers[] = "From: " . $system_settings['site_name'] . " <" . $system_settings['admin_email'] . ">";
                $headers[] = "Reply-To: " . $system_settings['admin_email'];
                $headers[] = "X-Mailer: QuickFix-PHP/" . phpversion();
                $headers[] = "Content-Type: text/plain; charset=UTF-8";
                $headers[] = "X-Priority: 3";
                
                $headers_string = implode("\r\n", $headers);
                
                // Attempt to send email
                $mail_sent = @mail($test_email, $subject, $message, $headers_string);
                
                if ($mail_sent) {
                    $success = "Test email sent successfully to: {$test_email}. Please check your inbox (and spam folder).";
                    
                    // Log the successful email test
                    error_log("QuickFix: Test email sent successfully to {$test_email} from admin panel");
                } else {
                    $php_error = error_get_last();
                    $error_details = $php_error ? $php_error['message'] : 'Unknown mail function error';
                    $error = "Failed to send test email. Error: {$error_details}. Please check your server's mail configuration.";
                    
                    // Log the failed email attempt
                    error_log("QuickFix: Failed to send test email to {$test_email}. Error: {$error_details}");
                }
                
            } catch (Exception $e) {
                $error = "Email sending failed with exception: " . $e->getMessage();
                error_log("QuickFix: Email test exception - " . $e->getMessage());
            }
        }
    }
    
    elseif ($action === 'view_logs') {
        // Get log type from request
        $log_type = $_POST['log_type'] ?? 'error';
        $lines = intval($_POST['lines'] ?? 50);
        
        $logs = [];
        $log_files = [];
        
        // Define log file locations
        switch ($log_type) {
            case 'error':
                $log_files = [
                    ini_get('error_log'),
                    '../logs/error.log',
                    '../logs/php_errors.log'
                ];
                break;
            case 'access':
                $log_files = [
                    '../logs/access.log',
                    '../logs/system.log'
                ];
                break;
            case 'system':
                $log_files = [
                    '../logs/system.log',
                    '../logs/quickfix.log'
                ];
                break;
        }
        
        // Read log files
        foreach ($log_files as $log_file) {
            if ($log_file && file_exists($log_file) && is_readable($log_file)) {
                $file_lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                if ($file_lines) {
                    $recent_lines = array_slice($file_lines, -$lines);
                    foreach ($recent_lines as $line) {
                        $logs[] = [
                            'file' => basename($log_file),
                            'content' => $line,
                            'timestamp' => time()
                        ];
                    }
                }
                break; // Use first available log file
            }
        }
        
        // If no log files found, create a sample entry
        if (empty($logs)) {
            $logs[] = [
                'file' => 'system',
                'content' => '[' . date('Y-m-d H:i:s') . '] No log entries found or log files are not accessible',
                'timestamp' => time()
            ];
        }
        
        // Return logs as JSON for AJAX request
        if (isset($_POST['ajax'])) {
            header('Content-Type: application/json');
            echo json_encode(['logs' => $logs]);
            exit;
        }
    }
    
    elseif ($action === 'update_general_settings') {
        $site_name = trim($_POST['site_name'] ?? '');
        $admin_email = trim($_POST['admin_email'] ?? '');
        $max_upload_size = intval($_POST['max_upload_size'] ?? 5);
        $session_timeout = intval($_POST['session_timeout'] ?? 30);
        
        if ($site_name && $admin_email) {
            $updated = true;
            $updated &= updateSetting($db, 'site_name', $site_name, 'string');
            $updated &= updateSetting($db, 'admin_email', $admin_email, 'string');
            $updated &= updateSetting($db, 'max_upload_size', $max_upload_size, 'number');
            $updated &= updateSetting($db, 'session_timeout', $session_timeout, 'number');
            
            if ($updated) {
                $success = "General settings updated successfully!";
                $system_settings['site_name'] = $site_name;
                $system_settings['admin_email'] = $admin_email;
                $system_settings['max_upload_size'] = $max_upload_size;
                $system_settings['session_timeout'] = $session_timeout;
            } else {
                $error = "Failed to update general settings.";
            }
        } else {
            $error = "Site name and admin email are required.";
        }
    }
}

// Get real system statistics
$stats = [];

// Count files in uploads directory
$upload_dir = '../uploads/profile_photos/';
$stats['profile_photos'] = 0;
if (is_dir($upload_dir)) {
    $files = glob($upload_dir . '*');
    $stats['profile_photos'] = count(array_filter($files, 'is_file'));
}

$service_upload_dir = '../uploads/services/';
$stats['service_images'] = 0;
if (is_dir($service_upload_dir)) {
    $files = glob($service_upload_dir . '*');
    $stats['service_images'] = count(array_filter($files, 'is_file'));
}

// Get real database size
try {
    $size_query = "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) AS 'size_mb' 
                   FROM information_schema.tables 
                   WHERE table_schema = :db_name";
    $size_stmt = $db->prepare($size_query);
    $size_stmt->execute([':db_name' => DB_NAME]);
    $db_size = $size_stmt->fetchColumn();
    $stats['database_size'] = $db_size ? $db_size . ' MB' : 'Unknown';
} catch (Exception $e) {
    $stats['database_size'] = 'Unknown';
}

// Get system info
$stats['php_version'] = phpversion();
$stats['server_software'] = $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown';
$stats['memory_limit'] = ini_get('memory_limit');
$stats['max_execution_time'] = ini_get('max_execution_time') . 's';
$stats['upload_max_filesize'] = ini_get('upload_max_filesize');
$stats['total_uploads'] = $stats['profile_photos'] + $stats['service_images'];

// Get database record counts
try {
    $stats['total_users'] = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $stats['total_services'] = $db->query("SELECT COUNT(*) FROM services")->fetchColumn();
    $stats['total_bookings'] = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $stats['total_providers'] = $db->query("SELECT COUNT(*) FROM users WHERE user_type = 'provider'")->fetchColumn();
} catch (Exception $e) {
    $stats['total_users'] = 'Unknown';
    $stats['total_services'] = 'Unknown';
    $stats['total_bookings'] = 'Unknown';
    $stats['total_providers'] = 'Unknown';
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-wrench"></i> QuickFix Admin
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i> Users
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-cogs"></i> Services
                </a></li>
                <li><a href="bookings.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> Bookings
                </a></li>
                
                <!-- User Avatar Dropdown -->
                <li class="nav-dropdown user-dropdown">
                    <a href="#" class="nav-link dropdown-toggle user-profile-link">
                        <div class="nav-avatar">
                            <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user['profile_image'])): ?>
                                <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" 
                                     alt="Profile" class="nav-avatar-img">
                            <?php else: ?>
                                <i class="fas fa-user-shield nav-avatar-icon"></i>
                            <?php endif; ?>
                        </div>
                        <span class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></span>
                        <i class="fas fa-chevron-down dropdown-arrow"></i>
                    </a>
                    <div class="dropdown-menu user-dropdown-menu">
                        <div class="dropdown-header">
                            <div class="user-info">
                                <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user['profile_image'])): ?>
                                    <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" 
                                         alt="Profile" class="dropdown-avatar">
                                <?php else: ?>
                                    <i class="fas fa-user-shield dropdown-avatar-icon"></i>
                                <?php endif; ?>
                                <div class="user-details">
                                    <div class="user-name-full"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Admin Profile
                        </a>
                        <a href="users.php" class="dropdown-item">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                        <a href="settings.php" class="dropdown-item active">
                            <i class="fas fa-cogs"></i> System Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a href="../auth/logout.php" class="dropdown-item logout-item">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </li>
            </ul>
            <button class="mobile-menu-btn" id="mobileMenuBtn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-cogs"></i> System Settings
                </h1>
                <p class="dashboard-subtitle">
                    Configure and manage system-wide settings
                </p>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="notification success show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="notification error show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- System Information -->
            <div class="glass-container">
                <h3><i class="fas fa-info-circle"></i> System Information</h3>
                <div class="system-info-grid">
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-server"></i> PHP Version
                        </div>
                        <div class="info-value"><?php echo $stats['php_version']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-database"></i> Database Size
                        </div>
                        <div class="info-value"><?php echo $stats['database_size']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-memory"></i> Memory Limit
                        </div>
                        <div class="info-value"><?php echo $stats['memory_limit']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-upload"></i> Max Upload Size
                        </div>
                        <div class="info-value"><?php echo $stats['upload_max_filesize']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-users"></i> Total Users
                        </div>
                        <div class="info-value"><?php echo $stats['total_users']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-user-cog"></i> Providers
                        </div>
                        <div class="info-value"><?php echo $stats['total_providers']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-cogs"></i> Services
                        </div>
                        <div class="info-value"><?php echo $stats['total_services']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-calendar-alt"></i> Total Bookings
                        </div>
                        <div class="info-value"><?php echo $stats['total_bookings']; ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-images"></i> Profile Photos
                        </div>
                        <div class="info-value"><?php echo $stats['profile_photos']; ?> files</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-file-image"></i> Service Images
                        </div>
                        <div class="info-value"><?php echo $stats['service_images']; ?> files</div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-clock"></i> Server Time
                        </div>
                        <div class="info-value"><?php echo date('Y-m-d H:i:s'); ?></div>
                    </div>
                    
                    <div class="info-item">
                        <div class="info-label">
                            <i class="fas fa-hdd"></i> Total Uploads
                        </div>
                        <div class="info-value"><?php echo $stats['total_uploads']; ?> files</div>
                    </div>
                </div>
            </div>

            <!-- General Settings Form -->
            <div class="glass-container">
                <h3><i class="fas fa-cogs"></i> General Settings</h3>
                <form method="POST" class="settings-form">
                    <input type="hidden" name="action" value="update_general_settings">
                    
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="site_name" class="form-label">
                                <i class="fas fa-globe"></i> Site Name *
                            </label>
                            <input type="text" 
                                   name="site_name" 
                                   id="site_name" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($system_settings['site_name']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="admin_email" class="form-label">
                                <i class="fas fa-envelope"></i> Admin Email *
                            </label>
                            <input type="email" 
                                   name="admin_email" 
                                   id="admin_email" 
                                   class="form-control"
                                   value="<?php echo htmlspecialchars($system_settings['admin_email']); ?>" 
                                   required>
                        </div>
                        
                        <div class="form-group">
                            <label for="max_upload_size" class="form-label">
                                <i class="fas fa-upload"></i> Max Upload Size (MB)
                            </label>
                            <input type="number" 
                                   name="max_upload_size" 
                                   id="max_upload_size" 
                                   class="form-control"
                                   value="<?php echo $system_settings['max_upload_size']; ?>" 
                                   min="1" 
                                   max="100">
                        </div>
                        
                        <div class="form-group">
                            <label for="session_timeout" class="form-label">
                                <i class="fas fa-clock"></i> Session Timeout (minutes)
                            </label>
                            <input type="number" 
                                   name="session_timeout" 
                                   id="session_timeout" 
                                   class="form-control"
                                   value="<?php echo $system_settings['session_timeout']; ?>" 
                                   min="5" 
                                   max="120">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save General Settings
                        </button>
                    </div>
                </form>
            </div>

            <!-- System Settings -->
            <div class="glass-container">
                <h3><i class="fas fa-sliders-h"></i> Application Settings</h3>
                <div class="settings-grid">
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Maintenance Mode</h4>
                            <p>Put the site in maintenance mode</p>
                        </div>
                        <div class="setting-value">
                            <label class="switch">
                                <input type="checkbox" 
                                       data-setting="maintenance_mode" 
                                       data-type="boolean"
                                       <?php echo $system_settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>User Registration</h4>
                            <p>Allow new users to register</p>
                        </div>
                        <div class="setting-value">
                            <label class="switch">
                                <input type="checkbox" 
                                       data-setting="user_registration" 
                                       data-type="boolean"
                                       <?php echo $system_settings['user_registration'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Provider Registration</h4>
                            <p>Allow new service providers to register</p>
                        </div>
                        <div class="setting-value">
                            <label class="switch">
                                <input type="checkbox" 
                                       data-setting="provider_registration" 
                                       data-type="boolean"
                                       <?php echo $system_settings['provider_registration'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Email Notifications</h4>
                            <p>Send email notifications for bookings</p>
                        </div>
                        <div class="setting-value">
                            <label class="switch">
                                <input type="checkbox" 
                                       data-setting="email_notifications" 
                                       data-type="boolean"
                                       <?php echo $system_settings['email_notifications'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="setting-item">
                        <div class="setting-info">
                            <h4>Booking Approval</h4>
                            <p>Require admin approval for bookings</p>
                        </div>
                        <div class="setting-value">
                            <label class="switch">
                                <input type="checkbox" 
                                       data-setting="booking_approval" 
                                       data-type="boolean"
                                       <?php echo $system_settings['booking_approval'] ? 'checked' : ''; ?>>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Tools -->
            <div class="glass-container">
                <h3><i class="fas fa-tools"></i> System Tools</h3>
                <div class="tools-grid">
                    <!-- Database Backup -->
                    <div class="tool-item">
                        <div class="tool-icon">
                            <i class="fas fa-database"></i>
                        </div>
                        <div class="tool-info">
                            <h4>Database Backup</h4>
                            <p>Create a backup of the database</p>
                        </div>
                        <div class="tool-action">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="backup_database">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-download"></i> Backup Now
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Clear Cache -->
                    <div class="tool-item">
                        <div class="tool-icon">
                            <i class="fas fa-broom"></i>
                        </div>
                        <div class="tool-info">
                            <h4>Clear Cache</h4>
                            <p>Clear system cache and temporary files</p>
                        </div>
                        <div class="tool-action">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="clear_cache">
                                <button type="submit" class="btn btn-warning">
                                    <i class="fas fa-trash"></i> Clear Cache
                                </button>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Test Email -->
                    <div class="tool-item">
                        <div class="tool-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="tool-info">
                            <h4>Test Email</h4>
                            <p>Send a test email to verify email functionality</p>
                        </div>
                        <div class="tool-action">
                            <form method="POST" class="test-email-form">
                                <input type="hidden" name="action" value="send_test_email">
                                <div class="input-group">
                                    <input type="email" 
                                           name="test_email" 
                                           placeholder="Enter email address..."
                                           class="form-control"
                                           required>
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-paper-plane"></i> Send
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- View Logs -->
                    <div class="tool-item">
                        <div class="tool-icon">
                            <i class="fas fa-file-alt"></i>
                        </div>
                        <div class="tool-info">
                            <h4>System Logs</h4>
                            <p>View application and error logs</p>
                        </div>
                        <div class="tool-action">
                            <button type="button" class="btn btn-secondary" onclick="openLogsModal()">
                                <i class="fas fa-eye"></i> View Logs
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="glass-container">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="quick-actions-grid">
                    <a href="users.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="action-info">
                            <h4>Manage Users</h4>
                            <p>View and manage user accounts</p>
                        </div>
                    </a>
                    
                    <a href="services.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <div class="action-info">
                            <h4>Manage Services</h4>
                            <p>Oversee all service offerings</p>
                        </div>
                    </a>
                    
                    <a href="bookings.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <div class="action-info">
                            <h4>View Bookings</h4>
                            <p>Monitor all booking activities</p>
                        </div>
                    </a>
                    
                    <a href="reports.php" class="action-card">
                        <div class="action-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="action-info">
                            <h4>Generate Reports</h4>
                            <p>View analytics and reports</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Mobile menu functionality
        document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
        });

        // Dropdown functionality
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = this.parentElement;
                dropdown.classList.toggle('active');
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-dropdown')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
        });

        // Auto-hide success/error messages
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification.show');
            notifications.forEach(notification => {
                notification.classList.remove('show');
            });
        }, 5000);

        // Settings switches with AJAX functionality
        document.querySelectorAll('.switch input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const settingKey = this.getAttribute('data-setting');
                const settingType = this.getAttribute('data-type') || 'string';
                const settingValue = this.checked ? '1' : '0';
                
                // Visual feedback
                const switchElement = this.closest('.switch');
                switchElement.style.opacity = '0.6';
                switchElement.style.pointerEvents = 'none';
                
                // Send AJAX request
                const formData = new FormData();
                formData.append('action', 'update_setting');
                formData.append('setting_key', settingKey);
                formData.append('setting_value', settingValue);
                formData.append('setting_type', settingType);
                
                fetch('settings.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.text())
                .then(data => {
                    // Check if the response indicates success
                    if (data.includes('Setting updated successfully')) {
                        // Show success notification
                        showNotification('Setting updated successfully!', 'success');
                    } else {
                        // Revert the checkbox state on error
                        this.checked = !this.checked;
                        showNotification('Failed to update setting', 'error');
                    }
                })
                .catch(error => {
                    // Revert the checkbox state on error
                    this.checked = !this.checked;
                    showNotification('Network error occurred', 'error');
                    console.error('Error:', error);
                })
                .finally(() => {
                    // Restore visual state
                    switchElement.style.opacity = '';
                    switchElement.style.pointerEvents = '';
                });
            });
        });
        
        // Function to show notifications
        function showNotification(message, type = 'info') {
            // Remove existing notifications
            const existingNotifications = document.querySelectorAll('.notification');
            existingNotifications.forEach(notification => {
                notification.remove();
            });
            
            // Create new notification
            const notification = document.createElement('div');
            notification.className = `notification ${type} show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            
            const icon = type === 'success' ? 'fas fa-check-circle' : 
                        type === 'error' ? 'fas fa-exclamation-triangle' : 
                        'fas fa-info-circle';
            
            notification.innerHTML = `<i class="${icon}"></i> ${message}`;
            document.body.appendChild(notification);
            
            // Auto-hide after 3 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
        }
        
        // Enhanced form validation
        document.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', function(e) {
                const submitButton = form.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                    
                    // Re-enable button after form submission
                    setTimeout(() => {
                        submitButton.disabled = false;
                        submitButton.innerHTML = submitButton.innerHTML.replace(
                            '<i class="fas fa-spinner fa-spin"></i> Processing...', 
                            submitButton.innerHTML.includes('Save') ? '<i class="fas fa-save"></i> Save' : 
                            submitButton.innerHTML
                        );
                    }, 2000);
                }
            });
        });
    </script>

    <!-- Logs Modal -->
    <div id="logsModal" class="modal" style="display: none;">
        <div class="modal-content" style="max-width: 800px; max-height: 80vh;">
            <div class="modal-header">
                <h3><i class="fas fa-file-alt"></i> System Logs</h3>
                <button type="button" class="modal-close" onclick="closeLogsModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <div class="logs-controls">
                    <select id="logType" onchange="loadLogs()">
                        <option value="error">Error Logs</option>
                        <option value="access">Access Logs</option>
                        <option value="system">System Logs</option>
                    </select>
                    <select id="logLines" onchange="loadLogs()">
                        <option value="25">Last 25 lines</option>
                        <option value="50" selected>Last 50 lines</option>
                        <option value="100">Last 100 lines</option>
                        <option value="200">Last 200 lines</option>
                    </select>
                    <button type="button" class="btn btn-primary btn-sm" onclick="loadLogs()">
                        <i class="fas fa-refresh"></i> Refresh
                    </button>
                </div>
                <div id="logsContent" class="logs-display">
                    <div class="loading">
                        <i class="fas fa-spinner fa-spin"></i> Loading logs...
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Logs modal functions
        function openLogsModal() {
            document.getElementById('logsModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
            loadLogs();
        }

        function closeLogsModal() {
            document.getElementById('logsModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        function loadLogs() {
            const logType = document.getElementById('logType').value;
            const lines = document.getElementById('logLines').value;
            const logsContent = document.getElementById('logsContent');
            
            logsContent.innerHTML = '<div class="loading"><i class="fas fa-spinner fa-spin"></i> Loading logs...</div>';
            
            const formData = new FormData();
            formData.append('action', 'view_logs');
            formData.append('log_type', logType);
            formData.append('lines', lines);
            formData.append('ajax', '1');
            
            fetch('settings.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.logs && data.logs.length > 0) {
                    let html = '<div class="logs-list">';
                    data.logs.forEach(log => {
                        html += `<div class="log-entry">
                            <span class="log-file">[${log.file}]</span>
                            <span class="log-content">${log.content}</span>
                        </div>`;
                    });
                    html += '</div>';
                    logsContent.innerHTML = html;
                } else {
                    logsContent.innerHTML = '<div class="no-logs">No log entries found.</div>';
                }
            })
            .catch(error => {
                logsContent.innerHTML = '<div class="error">Error loading logs: ' + error.message + '</div>';
            });
        }

        // Close modal on outside click
        document.getElementById('logsModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLogsModal();
            }
        });
    </script>
    
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow: hidden;
            box-shadow: var(--shadow);
        }
        
        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--glass-border);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            color: var(--text-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .modal-close {
            background: none;
            border: none;
            font-size: 1.2rem;
            color: var(--text-dark);
            cursor: pointer;
            opacity: 0.7;
            transition: opacity 0.3s;
        }
        
        .modal-close:hover {
            opacity: 1;
        }
        
        .modal-body {
            padding: 1.5rem;
            overflow-y: auto;
            max-height: calc(80vh - 120px);
        }
        
        .logs-controls {
            display: flex;
            gap: 1rem;
            margin-bottom: 1rem;
            flex-wrap: wrap;
        }
        
        .logs-controls select {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid var(--glass-border);
            border-radius: 8px;
            padding: 0.5rem;
            color: var(--text-dark);
            outline: none;
        }
        
        .logs-display {
            background: rgba(0, 0, 0, 0.1);
            border-radius: 10px;
            padding: 1rem;
            min-height: 300px;
            font-family: monospace;
            font-size: 0.85rem;
            overflow-y: auto;
        }
        
        .log-entry {
            margin-bottom: 0.5rem;
            padding: 0.3rem;
            border-left: 3px solid #4facfe;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 3px;
        }
        
        .log-file {
            color: #4facfe;
            font-weight: bold;
            margin-right: 0.5rem;
        }
        
        .log-content {
            color: var(--text-dark);
            word-wrap: break-word;
        }
        
        .loading, .no-logs, .error {
            text-align: center;
            color: var(--text-dark);
            opacity: 0.7;
            padding: 2rem;
        }
        
        .error {
            color: #e74c3c;
        }
    </style>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin-theme.js"></script>
</body>
</html>
