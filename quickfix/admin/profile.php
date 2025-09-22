<?php
require_once '../config/config.php';
checkAccess(['admin']);

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get admin data
$user_query = "SELECT * FROM users WHERE id = :admin_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':admin_id', $admin_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Initialize action variable
$action = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_profile') {
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $username = trim($_POST['username']);
        
        // Enhanced validation for admin
        if (empty($full_name) || empty($email) || empty($username)) {
            $error = 'Full name, email, and username are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
            $error = 'Username must be 3-20 characters long and contain only letters, numbers, and underscores.';
        } elseif ($phone && !preg_match('/^[0-9+\-\s()]+$/', $phone)) {
            $error = 'Please enter a valid phone number.';
        } else {
            // Check if email is already taken by another user
            $email_check = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :user_id");
            $email_check->execute([':email' => $email, ':user_id' => $admin_id]);
            
            // Check if username is already taken by another user
            $username_check = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $username_check->execute([':username' => $username, ':user_id' => $admin_id]);
            
            if ($email_check->fetch()) {
                $error = 'Email address is already in use by another account.';
            } elseif ($username_check->fetch()) {
                $error = 'Username is already taken. Please choose a different one.';
            } else {
                // Track what's being changed for detailed feedback
                $changes = [];
                if ($user['full_name'] !== $full_name) $changes[] = 'name';
                if ($user['email'] !== $email) $changes[] = 'email';
                if ($user['username'] !== $username) $changes[] = 'username';
                if ($user['phone'] !== $phone) $changes[] = 'phone';
                if ($user['address'] !== $address) $changes[] = 'address';
                
                $update_query = "UPDATE users SET 
                        full_name = :full_name, 
                        email = :email, 
                        phone = :phone, 
                        address = :address,
                        username = :username,
                        updated_at = CURRENT_TIMESTAMP
                    WHERE id = :user_id";
                    
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([
                    ':full_name' => $full_name,
                    ':email' => $email,
                    ':phone' => $phone,
                    ':address' => $address,
                    ':username' => $username,
                    ':user_id' => $admin_id
                ])) {
                    // Update session variables
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $_SESSION['username'] = $username;
                    
                    // Create detailed success message
                    if (empty($changes)) {
                        $success = 'Profile information verified - no changes were needed.';
                    } else {
                        $success = 'Profile updated successfully! Updated: ' . implode(', ', $changes) . '.';
                    }
                    
                    // Refresh user data to reflect changes
                    $user_stmt->execute();
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Log the profile update for admin audit
                    try {
                        $log_query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (:admin_id, 'profile_update', :details, NOW())";
                        $log_stmt = $db->prepare($log_query);
                        $log_stmt->execute([
                            ':admin_id' => $admin_id,
                            ':details' => 'Updated: ' . implode(', ', $changes)
                        ]);
                    } catch (PDOException $e) {
                        // If admin_logs table doesn't exist, create it
                        if ($e->getCode() == '42S02') {
                            try {
                                $create_log_table = "CREATE TABLE admin_logs (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    admin_id INT NOT NULL,
                                    action VARCHAR(50) NOT NULL,
                                    details TEXT,
                                    created_at DATETIME NOT NULL,
                                    INDEX idx_admin_id (admin_id),
                                    INDEX idx_action (action),
                                    INDEX idx_created_at (created_at)
                                )";
                                $db->exec($create_log_table);
                                
                                // Try logging again
                                $log_stmt->execute([
                                    ':admin_id' => $admin_id,
                                    ':details' => 'Updated: ' . implode(', ', $changes)
                                ]);
                            } catch (PDOException $e2) {
                                // Silently continue if logging fails
                            }
                        }
                    }
                } else {
                    $error = 'Failed to update profile. Please try again.';
                }
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Enhanced password validation for admin
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 10) {
            $error = 'Admin password must be at least 10 characters long.';
        } elseif (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*])/', $new_password)) {
            $error = 'Password must contain uppercase, lowercase, number, and special character.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_update = $db->prepare("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
            
            if ($password_update->execute([':password' => $hashed_password, ':user_id' => $admin_id])) {
                $success = 'Password changed successfully! Your account security has been updated.';
                
                // Log the password change for admin audit
                try {
                    $log_query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (:admin_id, 'password_change', 'Admin password updated', NOW())";
                    $log_stmt = $db->prepare($log_query);
                    $log_stmt->execute([':admin_id' => $admin_id]);
                } catch (PDOException $e) {
                    // If admin_logs table doesn't exist, create it and try again
                    if ($e->getCode() == '42S02') {
                        try {
                            $create_log_table = "CREATE TABLE admin_logs (
                                id INT AUTO_INCREMENT PRIMARY KEY,
                                admin_id INT NOT NULL,
                                action VARCHAR(50) NOT NULL,
                                details TEXT,
                                created_at DATETIME NOT NULL,
                                INDEX idx_admin_id (admin_id),
                                INDEX idx_action (action),
                                INDEX idx_created_at (created_at)
                            )";
                            $db->exec($create_log_table);
                            
                            // Try logging again
                            $log_stmt->execute([':admin_id' => $admin_id]);
                        } catch (PDOException $e2) {
                            // Silently continue if logging fails
                        }
                    }
                }
            } else {
                $error = 'Failed to change password. Please try again.';
            }
        }
    } elseif ($action === 'upload_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === 0) {
            $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
            $max_size = 5 * 1024 * 1024; // 5MB
            
            $file = $_FILES['profile_photo'];
            
            if (!in_array($file['type'], $allowed_types)) {
                $error = 'Only JPEG, PNG, and GIF images are allowed.';
            } elseif ($file['size'] > $max_size) {
                $error = 'File size must be less than 5MB.';
            } else {
                // Create uploads directory if it doesn't exist
                $upload_dir = '../uploads/profile_photos/';
                if (!file_exists($upload_dir)) {
                    mkdir($upload_dir, 0777, true);
                }
                
                // Generate unique filename
                $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = 'profile_' . $admin_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Delete old profile photo if it exists and is not default
                    if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists($upload_dir . $user['profile_image'])) {
                        unlink($upload_dir . $user['profile_image']);
                    }
                    
                    // Update database
                    $photo_update = $db->prepare("UPDATE users SET profile_image = :filename, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
                    
                    if ($photo_update->execute([':filename' => $filename, ':user_id' => $admin_id])) {
                        $success = 'Profile photo updated successfully! Your new photo is now displayed.';
                        
                        // Log the photo update for admin audit
                        try {
                            $log_query = "INSERT INTO admin_logs (admin_id, action, details, created_at) VALUES (:admin_id, 'photo_update', :details, NOW())";
                            $log_stmt = $db->prepare($log_query);
                            $log_stmt->execute([
                                ':admin_id' => $admin_id,
                                ':details' => 'Profile photo updated: ' . $filename
                            ]);
                        } catch (PDOException $e) {
                            // Silently continue if logging fails
                        }
                        
                        // Refresh user data to show new photo immediately
                        $user_stmt->execute();
                        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = 'Failed to update profile photo in database. Please try again.';
                        unlink($filepath); // Remove uploaded file
                    }
                } else {
                    $error = 'Failed to upload file.';
                }
            }
        } else {
            $error = 'Please select a photo to upload.';
        }
    }

// Get admin statistics
$stats_query = "SELECT 
    COUNT(DISTINCT u.id) as total_users,
    COUNT(DISTINCT CASE WHEN u.user_type = 'provider' THEN u.id END) as total_providers,
    COUNT(DISTINCT b.id) as total_bookings,
    COUNT(DISTINCT ps.id) as total_services
FROM users u
LEFT JOIN bookings b ON 1=1
LEFT JOIN provider_services ps ON 1=1";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Profile Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-shield-alt"></i> QuickFix Admin
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                
                <!-- Admin Avatar Dropdown -->
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
                                    <div class="user-role">System Administrator</div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item active">
                            <i class="fas fa-user-cog"></i> Profile Settings
                        </a>
                        <a href="dashboard.php" class="dropdown-item">
                            <i class="fas fa-tachometer-alt"></i> Admin Dashboard
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
                    <i class="fas fa-user-shield"></i> Administrator Profile
                </h1>
                <p class="dashboard-subtitle">
                    Manage your administrative account settings and preferences
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

            <!-- Profile Overview -->
            <div class="glass-container">
                <div class="profile-overview">
                    <div class="profile-header">
                        <div class="profile-avatar-section">
                            <div class="profile-avatar-container">
                                <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user['profile_image'])): ?>
                                    <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" 
                                         alt="Profile" class="profile-avatar">
                                <?php else: ?>
                                    <div class="profile-avatar-placeholder">
                                        <i class="fas fa-user-shield"></i>
                                    </div>
                                <?php endif; ?>
                                <button type="button" class="avatar-edit-btn" onclick="openPhotoModal()">
                                    <i class="fas fa-camera"></i>
                                </button>
                            </div>
                        </div>
                        <div class="profile-info">
                            <h2 class="profile-name"><?php echo htmlspecialchars($user['full_name']); ?></h2>
                            <p class="profile-email"><?php echo htmlspecialchars($user['email']); ?></p>
                            <div class="profile-status">
                                <span class="status-badge admin">
                                    <i class="fas fa-shield-alt"></i>
                                    System Administrator
                                </span>
                                <span class="join-date">
                                    <i class="fas fa-calendar"></i>
                                    Admin since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                                </span>
                                <?php if ($user['updated_at'] && $user['updated_at'] !== $user['created_at']): ?>
                                <span class="join-date">
                                    <i class="fas fa-clock"></i>
                                    Last updated <?php echo date('M j, Y \a\t g:i A', strtotime($user['updated_at'])); ?>
                                </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_users']; ?></div>
                                <div class="stat-label">Users</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_providers']; ?></div>
                                <div class="stat-label">Providers</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_services']; ?></div>
                                <div class="stat-label">Services</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                                <div class="stat-label">Bookings</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Settings Tabs -->
            <div class="settings-tabs">
                <button type="button" class="settings-tab active" data-tab="general">
                    <i class="fas fa-user"></i> General
                </button>
                <button type="button" class="settings-tab" data-tab="security">
                    <i class="fas fa-shield-alt"></i> Security
                </button>
                <button type="button" class="settings-tab" data-tab="admin">
                    <i class="fas fa-cogs"></i> Admin Settings
                </button>
                <button type="button" class="settings-tab" data-tab="system">
                    <i class="fas fa-server"></i> System
                </button>
            </div>

            <!-- General Settings -->
            <div class="glass-container tab-content active" id="general-tab">
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-edit"></i> Administrative Information
                    </h3>
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="admin-form-section">
                            <h4><i class="fas fa-id-card"></i> Identity Information</h4>
                            <div class="form-row">
                                <div class="enhanced-form-group">
                                    <label for="full_name">
                                        <i class="fas fa-user"></i> Full Name *
                                    </label>
                                    <div class="input-container">
                                        <input type="text" 
                                               name="full_name" 
                                               id="full_name" 
                                               class="enhanced-input"
                                               value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                                               required
                                               data-validate="name">
                                        <div class="input-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="enhanced-form-group">
                                    <label for="email">
                                        <i class="fas fa-envelope"></i> Email Address *
                                    </label>
                                    <div class="input-container">
                                        <input type="email" 
                                               name="email" 
                                               id="email" 
                                               class="enhanced-input"
                                               value="<?php echo htmlspecialchars($user['email']); ?>" 
                                               required
                                               data-validate="email">
                                        <div class="input-feedback"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="admin-form-section">
                            <h4><i class="fas fa-address-card"></i> Contact Information</h4>
                            <div class="form-row">
                                <div class="enhanced-form-group">
                                    <label for="phone">
                                        <i class="fas fa-phone"></i> Phone Number
                                    </label>
                                    <div class="input-container">
                                        <input type="tel" 
                                               name="phone" 
                                               id="phone" 
                                               class="enhanced-input"
                                               value="<?php echo htmlspecialchars($user['phone']); ?>"
                                               data-validate="phone"
                                               placeholder="+63 912 345 6789">
                                        <div class="input-feedback"></div>
                                    </div>
                                </div>
                                
                                <div class="enhanced-form-group">
                                    <label for="username">
                                        <i class="fas fa-at"></i> Username
                                    </label>
                                    <div class="input-container">
                                        <input type="text" 
                                               name="username"
                                               id="username"
                                               class="enhanced-input" 
                                               value="<?php echo htmlspecialchars($user['username']); ?>" 
                                               required>
                                        <small class="form-help">Choose a unique username for admin access</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="enhanced-form-group">
                                <label for="address">
                                    <i class="fas fa-map-marker-alt"></i> Office Address
                                </label>
                                <div class="textarea-container">
                                    <textarea name="address" 
                                              id="address" 
                                              rows="3" 
                                              class="enhanced-textarea"
                                              placeholder="Enter office/work address..."><?php echo htmlspecialchars($user['address']); ?></textarea>
                                    <div class="char-counter">
                                        <span class="current-chars">0</span>/<span class="max-chars">500</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Security Settings -->
            <div class="glass-container tab-content" id="security-tab">
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-shield-alt"></i> Security Settings
                    </h3>
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="change_password">
                        
                        <div class="admin-form-section">
                            <h4><i class="fas fa-lock"></i> Password Management</h4>
                            <p style="color: var(--text-dark); opacity: 0.8; margin-bottom: 2rem;">
                                Admin passwords require enhanced security with minimum 10 characters including uppercase, lowercase, numbers, and special characters.
                            </p>
                            
                            <div class="enhanced-form-group">
                                <label for="current_password">
                                    <i class="fas fa-key"></i> Current Password
                                </label>
                                <div class="password-container">
                                    <input type="password" 
                                           name="current_password" 
                                           id="current_password" 
                                           class="enhanced-input password-input" 
                                           required>
                                    <button type="button" class="password-toggle" data-target="current_password">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-row">
                                <div class="enhanced-form-group">
                                    <label for="new_password">
                                        <i class="fas fa-shield-alt"></i> New Password
                                    </label>
                                    <div class="password-container">
                                        <input type="password" 
                                               name="new_password" 
                                               id="new_password" 
                                               class="enhanced-input password-input" 
                                               minlength="10" 
                                               required
                                               data-validate="password">
                                        <button type="button" class="password-toggle" data-target="new_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="password-strength">
                                        <div class="strength-meter">
                                            <div class="strength-fill"></div>
                                        </div>
                                        <div class="strength-text">Password Strength</div>
                                    </div>
                                    <div class="password-requirements">
                                        <div class="requirement" data-requirement="length">
                                            <i class="fas fa-times"></i> At least 10 characters
                                        </div>
                                        <div class="requirement" data-requirement="uppercase">
                                            <i class="fas fa-times"></i> One uppercase letter
                                        </div>
                                        <div class="requirement" data-requirement="lowercase">
                                            <i class="fas fa-times"></i> One lowercase letter
                                        </div>
                                        <div class="requirement" data-requirement="number">
                                            <i class="fas fa-times"></i> One number
                                        </div>
                                        <div class="requirement" data-requirement="special">
                                            <i class="fas fa-times"></i> One special character
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="enhanced-form-group">
                                    <label for="confirm_password">
                                        <i class="fas fa-shield-alt"></i> Confirm New Password
                                    </label>
                                    <div class="password-container">
                                        <input type="password" 
                                               name="confirm_password" 
                                               id="confirm_password" 
                                               class="enhanced-input password-input" 
                                               minlength="10" 
                                               required>
                                        <button type="button" class="password-toggle" data-target="confirm_password">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <div class="input-feedback" id="password-match-feedback"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-shield-alt"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Admin Settings -->
            <div class="glass-container tab-content" id="admin-tab">
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-cogs"></i> Administrative Preferences
                    </h3>
                    <div class="admin-permission-grid">
                        <div class="permission-item">
                            <span>User Management</span>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="permission-item">
                            <span>Provider Management</span>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="permission-item">
                            <span>Service Management</span>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                        <div class="permission-item">
                            <span>System Configuration</span>
                            <label class="switch">
                                <input type="checkbox" checked>
                                <span class="slider"></span>
                            </label>
                        </div>
                    </div>
                    
                    <div class="admin-form-section">
                        <h4><i class="fas fa-bell"></i> Admin Notifications</h4>
                        <div class="notification-settings">
                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>System Alerts</h4>
                                    <p>Critical system notifications and errors</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>User Reports</h4>
                                    <p>Daily and weekly user activity reports</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                            
                            <div class="notification-item">
                                <div class="notification-info">
                                    <h4>Security Alerts</h4>
                                    <p>Login attempts and security-related notifications</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- System Settings -->
            <div class="glass-container tab-content" id="system-tab">
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-server"></i> System Preferences
                    </h3>
                    <div class="preferences-grid">
                        <div class="preference-card">
                            <div class="preference-icon">
                                <i class="fas fa-palette"></i>
                            </div>
                            <div class="preference-content">
                                <h4>Admin Theme</h4>
                                <p>Choose your admin panel theme</p>
                                <div class="theme-selector">
                                    <label class="theme-option">
                                        <input type="radio" name="admin_theme" value="light" checked>
                                        <span class="theme-preview light"></span>
                                        <span class="theme-name">Light</span>
                                    </label>
                                    <label class="theme-option">
                                        <input type="radio" name="admin_theme" value="dark">
                                        <span class="theme-preview dark"></span>
                                        <span class="theme-name">Dark</span>
                                    </label>
                                    <label class="theme-option">
                                        <input type="radio" name="admin_theme" value="contrast">
                                        <span class="theme-preview auto"></span>
                                        <span class="theme-name">High Contrast</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="preference-card">
                            <div class="preference-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="preference-content">
                                <h4>Timezone & Format</h4>
                                <p>Set timezone and date/time formats</p>
                                <div class="preference-controls">
                                    <select class="enhanced-select">
                                        <option value="Asia/Manila">Asia/Manila (PHP)</option>
                                        <option value="UTC">UTC</option>
                                        <option value="America/New_York">Eastern Time</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="preference-card">
                            <div class="preference-icon">
                                <i class="fas fa-database"></i>
                            </div>
                            <div class="preference-content">
                                <h4>Data Management</h4>
                                <p>Configure data retention and backup settings</p>
                                <div class="preference-controls">
                                    <div class="role-selector">
                                        <button class="role-option active">Auto Backup</button>
                                        <button class="role-option">Manual Only</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Photo Upload Modal -->
    <div class="modal" id="photoModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Update Profile Photo</h3>
                <button type="button" class="modal-close" onclick="closePhotoModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="post" enctype="multipart/form-data" class="photo-upload-form">
                <input type="hidden" name="action" value="upload_photo">
                
                <div class="upload-area" id="uploadArea">
                    <div class="upload-icon">
                        <i class="fas fa-cloud-upload-alt"></i>
                    </div>
                    <div class="upload-text">
                        <p><strong>Click to select</strong> or drag and drop</p>
                        <p class="upload-hint">JPEG, PNG, GIF up to 5MB</p>
                    </div>
                    <input type="file" 
                           name="profile_photo" 
                           id="profilePhoto" 
                           accept="image/*" 
                           required>
                </div>
                
                <div class="photo-preview" id="photoPreview" style="display: none;">
                    <img src="" alt="Preview" id="previewImage">
                    <button type="button" class="remove-photo" onclick="removePhoto()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Upload Photo
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closePhotoModal()">
                        Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Enhanced admin form functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    // Update tab buttons
                    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Update tab content
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(tabName + '-tab').classList.add('active');
                });
            });

            // Enhanced input validation
            const validationPatterns = {
                name: /^[a-zA-Z\s.'-]{2,50}$/,
                email: /^[^\s@]+@[^\s@]+\.[^\s@]+$/,
                phone: /^[+]?[0-9\s\-()]{10,15}$/,
                password: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[!@#$%^&*]).{10,}$/
            };

            // Real-time validation
            document.querySelectorAll('[data-validate]').forEach(input => {
                input.addEventListener('input', function() {
                    const validateType = this.getAttribute('data-validate');
                    const pattern = validationPatterns[validateType];
                    const feedback = this.parentElement.querySelector('.input-feedback');
                    
                    if (pattern && this.value) {
                        if (pattern.test(this.value)) {
                            this.classList.remove('invalid');
                            this.classList.add('valid');
                            if (feedback) {
                                feedback.textContent = '';
                                feedback.className = 'input-feedback';
                            }
                        } else {
                            this.classList.remove('valid');
                            this.classList.add('invalid');
                            if (feedback) {
                                feedback.textContent = getValidationMessage(validateType);
                                feedback.className = 'input-feedback error';
                            }
                        }
                    }
                });
            });

            function getValidationMessage(type) {
                const messages = {
                    name: 'Please enter a valid name (2-50 characters)',
                    email: 'Please enter a valid email address',
                    phone: 'Please enter a valid phone number',
                    password: 'Password must be 10+ characters with uppercase, lowercase, number, and special character'
                };
                return messages[type] || 'Invalid input';
            }

            // Character counters for textareas
            document.querySelectorAll('.enhanced-textarea').forEach(textarea => {
                const container = textarea.closest('.textarea-container');
                const counter = container.querySelector('.char-counter .current-chars');
                const maxChars = parseInt(container.querySelector('.max-chars').textContent);
                
                function updateCounter() {
                    const currentLength = textarea.value.length;
                    counter.textContent = currentLength;
                    
                    if (currentLength > maxChars * 0.9) {
                        counter.style.color = '#ff6b6b';
                    } else if (currentLength > maxChars * 0.7) {
                        counter.style.color = '#ffa726';
                    } else {
                        counter.style.color = '#4facfe';
                    }
                }
                
                textarea.addEventListener('input', updateCounter);
                updateCounter(); // Initial count
            });

            // Admin password strength indicator (enhanced)
            const newPasswordInput = document.getElementById('new_password');
            if (newPasswordInput) {
                newPasswordInput.addEventListener('input', function() {
                    updatePasswordStrength(this.value);
                    checkPasswordRequirements(this.value);
                });
            }

            function updatePasswordStrength(password) {
                const strengthMeter = document.querySelector('.strength-fill');
                const strengthText = document.querySelector('.strength-text');
                
                if (!strengthMeter || !strengthText) return;
                
                let score = 0;
                const checks = [
                    password.length >= 10,
                    /[a-z]/.test(password),
                    /[A-Z]/.test(password),
                    /\d/.test(password),
                    /[!@#$%^&*(),.?":{}|<>]/.test(password)
                ];
                
                score = checks.reduce((sum, check) => sum + (check ? 1 : 0), 0);
                
                const strength = ['Very Weak', 'Weak', 'Fair', 'Good', 'Strong'][Math.min(score, 4)];
                const colors = ['#ff4757', '#ffa726', '#ffeb3b', '#4caf50', '#2e7d32'];
                const widths = ['20%', '40%', '60%', '80%', '100%'];
                
                strengthMeter.style.width = widths[Math.min(score, 4)];
                strengthMeter.style.backgroundColor = colors[Math.min(score, 4)];
                strengthText.textContent = `Password Strength: ${strength}`;
                strengthText.style.color = colors[Math.min(score, 4)];
            }

            function checkPasswordRequirements(password) {
                const requirements = {
                    length: password.length >= 10,
                    uppercase: /[A-Z]/.test(password),
                    lowercase: /[a-z]/.test(password),
                    number: /\d/.test(password),
                    special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
                };
                
                Object.entries(requirements).forEach(([req, met]) => {
                    const element = document.querySelector(`[data-requirement="${req}"]`);
                    if (element) {
                        const icon = element.querySelector('i');
                        if (met) {
                            element.classList.add('met');
                            icon.className = 'fas fa-check';
                        } else {
                            element.classList.remove('met');
                            icon.className = 'fas fa-times';
                        }
                    }
                });
            }

            // Password confirmation matching
            const confirmPasswordInput = document.getElementById('confirm_password');
            if (confirmPasswordInput && newPasswordInput) {
                function checkPasswordMatch() {
                    const feedback = document.getElementById('password-match-feedback');
                    if (!feedback) return;
                    
                    if (confirmPasswordInput.value && newPasswordInput.value !== confirmPasswordInput.value) {
                        feedback.textContent = 'Passwords do not match';
                        feedback.className = 'input-feedback error';
                        confirmPasswordInput.classList.add('invalid');
                    } else if (confirmPasswordInput.value) {
                        feedback.textContent = 'Passwords match';
                        feedback.className = 'input-feedback success';
                        confirmPasswordInput.classList.remove('invalid');
                        confirmPasswordInput.classList.add('valid');
                    } else {
                        feedback.textContent = '';
                        feedback.className = 'input-feedback';
                    }
                }
                
                confirmPasswordInput.addEventListener('input', checkPasswordMatch);
                newPasswordInput.addEventListener('input', checkPasswordMatch);
            }

            // Password toggle functionality
            document.querySelectorAll('.password-toggle').forEach(button => {
                button.addEventListener('click', function() {
                    const targetId = this.getAttribute('data-target');
                    const input = document.getElementById(targetId);
                    const icon = this.querySelector('i');
                    
                    if (input.type === 'password') {
                        input.type = 'text';
                        icon.className = 'fas fa-eye-slash';
                    } else {
                        input.type = 'password';
                        icon.className = 'fas fa-eye';
                    }
                });
            });

            // Role selector
            document.querySelectorAll('.role-option').forEach(option => {
                option.addEventListener('click', function() {
                    const parent = this.parentElement;
                    parent.querySelectorAll('.role-option').forEach(o => o.classList.remove('active'));
                    this.classList.add('active');
                });
            });

            // Photo upload modal functionality
            window.openPhotoModal = function() {
                document.getElementById('photoModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.closePhotoModal = function() {
                document.getElementById('photoModal').style.display = 'none';
                document.body.style.overflow = '';
                removePhoto();
            };

            // File upload handling
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('profilePhoto');
            const photoPreview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');

            if (uploadArea && fileInput) {
                uploadArea.addEventListener('click', () => fileInput.click());

                uploadArea.addEventListener('dragover', (e) => {
                    e.preventDefault();
                    uploadArea.classList.add('dragover');
                });

                uploadArea.addEventListener('dragleave', () => {
                    uploadArea.classList.remove('dragover');
                });

                uploadArea.addEventListener('drop', (e) => {
                    e.preventDefault();
                    uploadArea.classList.remove('dragover');
                    
                    const files = e.dataTransfer.files;
                    if (files.length > 0) {
                        fileInput.files = files;
                        handleFileSelect(files[0]);
                    }
                });

                fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        handleFileSelect(e.target.files[0]);
                    }
                });
            }

            function handleFileSelect(file) {
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        previewImage.src = e.target.result;
                        uploadArea.style.display = 'none';
                        photoPreview.style.display = 'block';
                    };
                    reader.readAsDataURL(file);
                }
            }

            window.removePhoto = function() {
                fileInput.value = '';
                photoPreview.style.display = 'none';
                uploadArea.style.display = 'flex';
            };

            // Mobile menu and dropdown functionality
            document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
                document.querySelector('.nav-menu').classList.toggle('active');
            });

            document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
                toggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    const dropdown = this.parentElement;
                    dropdown.classList.toggle('active');
                });
            });

            // Close dropdowns and modal when clicking outside
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.nav-dropdown')) {
                    document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                        dropdown.classList.remove('active');
                    });
                }
                
                if (e.target.classList.contains('modal')) {
                    closePhotoModal();
                }
            });

            // Form submission feedback and database update confirmation
            const forms = document.querySelectorAll('.settings-form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const submitBtn = this.querySelector('button[type="submit"]');
                    const originalText = submitBtn.innerHTML;
                    
                    // Show loading state
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving Changes...';
                    submitBtn.disabled = true;
                    
                    // Create and show a temporary status message
                    const statusDiv = document.createElement('div');
                    statusDiv.className = 'saving-status';
                    statusDiv.innerHTML = '<i class="fas fa-database"></i> Updating database...';
                    statusDiv.style.cssText = `
                        position: fixed;
                        top: 20px;
                        right: 20px;
                        background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
                        color: white;
                        padding: 1rem 1.5rem;
                        border-radius: 10px;
                        z-index: 10000;
                        box-shadow: 0 4px 20px rgba(79, 172, 254, 0.3);
                        transform: translateX(400px);
                        transition: all 0.3s ease;
                    `;
                    document.body.appendChild(statusDiv);
                    
                    // Animate in
                    setTimeout(() => {
                        statusDiv.style.transform = 'translateX(0)';
                    }, 100);
                    
                    // Remove after form submission
                    setTimeout(() => {
                        statusDiv.style.transform = 'translateX(400px)';
                        setTimeout(() => {
                            if (statusDiv.parentNode) {
                                statusDiv.remove();
                            }
                        }, 300);
                    }, 1000);
                });
            });

            // Auto-hide success/error messages after 7 seconds
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                if (notification.classList.contains('show')) {
                    setTimeout(() => {
                        notification.style.opacity = '0';
                        notification.style.transform = 'translateY(-20px)';
                        setTimeout(() => {
                            notification.style.display = 'none';
                        }, 300);
                    }, 7000);
                }
            });

            // Form change detection for better UX
            const profileForms = document.querySelectorAll('.settings-form');
            profileForms.forEach(form => {
                const inputs = form.querySelectorAll('input, textarea, select');
                let originalValues = {};
                
                // Store original values
                inputs.forEach(input => {
                    originalValues[input.name] = input.value;
                });
                
                inputs.forEach(input => {
                    input.addEventListener('input', function() {
                        checkFormChanges(form, originalValues);
                    });
                });
            });

            function checkFormChanges(form, originalValues) {
                const inputs = form.querySelectorAll('input, textarea, select');
                let hasChanges = false;
                
                inputs.forEach(input => {
                    if (originalValues[input.name] !== input.value) {
                        hasChanges = true;
                    }
                });
                
                const submitBtn = form.querySelector('button[type="submit"]');
                if (hasChanges) {
                    submitBtn.style.background = 'linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%)';
                    submitBtn.innerHTML = '<i class="fas fa-save"></i> Save Changes';
                } else {
                    submitBtn.style.background = '';
                    const originalIcon = submitBtn.getAttribute('data-original-icon') || 'fas fa-save';
                    const originalText = submitBtn.getAttribute('data-original-text') || 'Save Changes';
                    submitBtn.innerHTML = `<i class="${originalIcon}"></i> ${originalText}`;
                }
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin-theme.js"></script>
</body>
</html>
