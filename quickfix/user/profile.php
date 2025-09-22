<?php
require_once '../config/config.php';
checkAccess(['user']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get user data
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
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
        
        // Basic validation for user
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
            $email_check->execute([':email' => $email, ':user_id' => $user_id]);
            
            // Check if username is already taken by another user
            $username_check = $db->prepare("SELECT id FROM users WHERE username = :username AND id != :user_id");
            $username_check->execute([':username' => $username, ':user_id' => $user_id]);
            
            if ($email_check->fetch()) {
                $error = 'Email address is already in use.';
            } elseif ($username_check->fetch()) {
                $error = 'Username is already taken. Please choose a different one.';
            } else {
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
                    ':user_id' => $user_id
                ])) {
                    $_SESSION['full_name'] = $full_name;
                    $_SESSION['email'] = $email;
                    $success = 'Profile updated successfully!';
                    
                    // Refresh user data
                    $user_stmt->execute();
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $error = 'Failed to update profile.';
                }
            }
        }
    } elseif ($action === 'change_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];
        
        // Password validation for user
        if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
            $error = 'All password fields are required.';
        } elseif ($new_password !== $confirm_password) {
            $error = 'New passwords do not match.';
        } elseif (strlen($new_password) < 10) {
            $error = 'Password must be at least 10 characters long.';
        } elseif (!preg_match('/[A-Z]/', $new_password)) {
            $error = 'Password must contain at least one uppercase letter.';
        } elseif (!preg_match('/[a-z]/', $new_password)) {
            $error = 'Password must contain at least one lowercase letter.';
        } elseif (!preg_match('/\d/', $new_password)) {
            $error = 'Password must contain at least one number.';
        } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $new_password)) {
            $error = 'Password must contain at least one special character.';
        } elseif (!password_verify($current_password, $user['password'])) {
            $error = 'Current password is incorrect.';
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $password_update = $db->prepare("UPDATE users SET password = :password, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
            
            if ($password_update->execute([':password' => $hashed_password, ':user_id' => $user_id])) {
                $success = 'Password changed successfully!';
            } else {
                $error = 'Failed to change password.';
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
                $filename = 'profile_' . $user_id . '_' . time() . '.' . $extension;
                $filepath = $upload_dir . $filename;
                
                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    // Delete old profile photo if it exists and is not default
                    if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists($upload_dir . $user['profile_image'])) {
                        unlink($upload_dir . $user['profile_image']);
                    }
                    
                    // Update database
                    $photo_update = $db->prepare("UPDATE users SET profile_image = :filename, updated_at = CURRENT_TIMESTAMP WHERE id = :user_id");
                    
                    if ($photo_update->execute([':filename' => $filename, ':user_id' => $user_id])) {
                        $success = 'Profile photo updated successfully!';
                        
                        // Refresh user data
                        $user_stmt->execute();
                        $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $error = 'Failed to update profile photo in database.';
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
}

// Get user statistics
$stats_query = "SELECT 
    COUNT(DISTINCT CASE WHEN b.status IS NOT NULL THEN b.id END) as total_bookings,
    COUNT(DISTINCT CASE WHEN b.status = 'completed' THEN b.id END) as completed_bookings,
    COUNT(DISTINCT CASE WHEN b.status = 'cancelled' THEN b.id END) as cancelled_bookings,
    COUNT(DISTINCT CASE WHEN b.status = 'pending' THEN b.id END) as pending_bookings
FROM bookings b
WHERE b.user_id = :user_id";

$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute([':user_id' => $user_id]);
$stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Profile Settings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="user">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-home"></i> QuickFix
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a></li>
                
                <!-- User Avatar Dropdown -->
                <li class="nav-dropdown user-dropdown">
                    <a href="#" class="nav-link dropdown-toggle user-profile-link">
                        <div class="nav-avatar">
                            <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user['profile_image'])): ?>
                                <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" 
                                     alt="Profile" class="nav-avatar-img">
                            <?php else: ?>
                                <i class="fas fa-user nav-avatar-icon"></i>
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
                                    <i class="fas fa-user dropdown-avatar-icon"></i>
                                <?php endif; ?>
                                <div class="user-details">
                                    <div class="user-name-full"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                    <div class="user-role">Service User</div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item active">
                            <i class="fas fa-user-edit"></i> Profile Settings
                        </a>
                        <a href="dashboard.php" class="dropdown-item">
                            <i class="fas fa-tachometer-alt"></i> User Dashboard
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
                    <i class="fas fa-user-edit"></i> User Profile
                </h1>
                <p class="dashboard-subtitle">
                    Manage your account settings and preferences
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
                                        <i class="fas fa-user"></i>
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
                                <span class="status-badge user">
                                    <i class="fas fa-home"></i>
                                    Service User
                                </span>
                                <span class="join-date">
                                    <i class="fas fa-calendar"></i>
                                    Member since <?php echo date('F Y', strtotime($user['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        <div class="profile-stats">
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['total_bookings']; ?></div>
                                <div class="stat-label">Bookings</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['completed_bookings']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['cancelled_bookings']; ?></div>
                                <div class="stat-label">Cancelled</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-number"><?php echo $stats['pending_bookings']; ?></div>
                                <div class="stat-label">Pending</div>
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
            </div>

            <!-- General Settings -->
            <div class="glass-container tab-content active" id="general-tab">
                <div class="settings-section">
                    <h3 class="section-title">
                        <i class="fas fa-user-edit"></i> Personal Information
                    </h3>
                    <form method="post" class="settings-form">
                        <input type="hidden" name="action" value="update_profile">
                        
                        <div class="admin-form-section">
                            <h4><i class="fas fa-id-card"></i> Basic Information</h4>
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
                                               required>
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
                                               required>
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
                                               placeholder="+63 912 345 6789">
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
                                        <small class="form-help">Choose a unique username for your account</small>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="enhanced-form-group">
                                <label for="address">
                                    <i class="fas fa-map-marker-alt"></i> Home Address
                                </label>
                                <div class="textarea-container">
                                    <textarea name="address" 
                                              id="address" 
                                              rows="3" 
                                              class="enhanced-textarea"
                                              placeholder="Enter your home address..."><?php echo htmlspecialchars($user['address']); ?></textarea>
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
                                Your password must be at least 10 characters and include uppercase, lowercase, number, and special character.
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
                                               required>
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
                                    </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching
            document.querySelectorAll('.settings-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    const tabName = this.getAttribute('data-tab');
                    
                    document.querySelectorAll('.settings-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    document.querySelectorAll('.tab-content').forEach(content => {
                        content.classList.remove('active');
                    });
                    document.getElementById(tabName + '-tab').classList.add('active');
                });
            });

            // Password validation
            const passwordInput = document.getElementById('new_password');
            const requirements = document.querySelectorAll('.requirement');
            
            if (passwordInput && requirements.length > 0) {
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    const checks = {
                        length: password.length >= 10,
                        uppercase: /[A-Z]/.test(password),
                        lowercase: /[a-z]/.test(password),
                        number: /\d/.test(password),
                        special: /[!@#$%^&*()_+\-=\[\]{};':"\\|,.<>\/?]/.test(password)
                    };
                    
                    requirements.forEach(req => {
                        const requirement = req.getAttribute('data-requirement');
                        const icon = req.querySelector('i');
                        
                        if (checks[requirement]) {
                            req.classList.add('met');
                            icon.className = 'fas fa-check';
                        } else {
                            req.classList.remove('met');
                            icon.className = 'fas fa-times';
                        }
                    });
                });
            }

            // Photo upload modal
            window.openPhotoModal = function() {
                document.getElementById('photoModal').style.display = 'flex';
                document.body.style.overflow = 'hidden';
            };

            window.closePhotoModal = function() {
                document.getElementById('photoModal').style.display = 'none';
                document.body.style.overflow = '';
            };

            // File upload handling
            const uploadArea = document.getElementById('uploadArea');
            const fileInput = document.getElementById('profilePhoto');
            const photoPreview = document.getElementById('photoPreview');
            const previewImage = document.getElementById('previewImage');

            if (uploadArea && fileInput) {
                uploadArea.addEventListener('click', () => fileInput.click());

                fileInput.addEventListener('change', (e) => {
                    if (e.target.files.length > 0) {
                        const file = e.target.files[0];
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
                });
            }

            window.removePhoto = function() {
                fileInput.value = '';
                photoPreview.style.display = 'none';
                uploadArea.style.display = 'flex';
            };

            // Mobile menu functionality
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
        });
    </script>
</body>
</html>