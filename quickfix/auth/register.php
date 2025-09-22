<?php 
require_once '../config/config.php';

$error = '';
$success = '';
$user_type = $_GET['type'] ?? 'user';

if (!in_array($user_type, ['user', 'provider'])) {
    $user_type = 'user';
}

if ($_POST) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $selected_type = $_POST['user_type'];
    
    // Validation
    if (empty($username) || empty($email) || empty($password) || empty($full_name)) {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 10) {
        $error = 'Password must be at least 10 characters long.';
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = 'Password must contain at least one uppercase letter.';
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = 'Password must contain at least one lowercase letter.';
    } elseif (!preg_match('/\d/', $password)) {
        $error = 'Password must contain at least one number.';
    } elseif (!preg_match('/[!@#$%^&*()_+\-=\[\]{};\':"\\|,.<>\/?]/', $password)) {
        $error = 'Password must contain at least one special character.';
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        // Check if username or email already exists
        $query = "SELECT COUNT(*) FROM users WHERE username = :username OR email = :email";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'Username or email already exists.';
        } else {
            // Create user with email verification
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $verification_token = bin2hex(random_bytes(32));
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            try {
                $query = "INSERT INTO users (username, email, password, full_name, phone, address, user_type, email_verified, verification_token, verification_expires) 
                         VALUES (:username, :email, :password, :full_name, :phone, :address, :user_type, 0, :verification_token, :verification_expires)";
                
                $stmt = $db->prepare($query);
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->bindParam(':full_name', $full_name);
                $stmt->bindParam(':phone', $phone);
                $stmt->bindParam(':address', $address);
                $stmt->bindParam(':user_type', $selected_type);
                $stmt->bindParam(':verification_token', $verification_token);
                $stmt->bindParam(':verification_expires', $verification_expires);
                
                if ($stmt->execute()) {
                    // In production, send actual email. For demo, show verification link
                    $verification_link = SITE_URL . '/auth/verify_email.php?token=' . $verification_token;
                    
                    $success = 'Account created successfully! Please verify your email address to complete registration.<br><br>
                               <strong>Demo verification link:</strong><br>
                               <a href="' . $verification_link . '" style="color: #4facfe; word-break: break-all;">' . $verification_link . '</a><br><br>
                               <small>In production, this link would be sent to your email address.</small>';
                } else {
                    $error = 'Error creating account. Please try again.';
                }
                
            } catch (PDOException $e) {
                // Check if columns don't exist and add them
                if (strpos($e->getMessage(), 'email_verified') !== false || strpos($e->getMessage(), 'verification_token') !== false) {
                    try {
                        // Add email verification columns
                        $db->exec("ALTER TABLE users ADD COLUMN email_verified TINYINT(1) DEFAULT 0");
                        $db->exec("ALTER TABLE users ADD COLUMN verification_token VARCHAR(255) NULL");
                        $db->exec("ALTER TABLE users ADD COLUMN verification_expires DATETIME NULL");
                        $db->exec("ALTER TABLE users ADD INDEX idx_verification_token (verification_token)");
                        
                        // Try the insert again
                        if ($stmt->execute()) {
                            $verification_link = SITE_URL . '/auth/verify_email.php?token=' . $verification_token;
                            
                            $success = 'Account created successfully! Please verify your email address to complete registration.<br><br>
                                       <strong>Demo verification link:</strong><br>
                                       <a href="' . $verification_link . '" style="color: #4facfe; word-break: break-all;">' . $verification_link . '</a><br><br>
                                       <small>In production, this link would be sent to your email address.</small>';
                        } else {
                            $error = 'Error creating account. Please try again.';
                        }
                    } catch (PDOException $e2) {
                        $error = 'Database error occurred. Please contact support.';
                    }
                } else {
                    $error = 'Error creating account. Please try again.';
                }
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="glass-container" style="max-width: 600px; margin: 3rem auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                    <i class="fas fa-user-plus"></i> Join QuickFix
                </h1>
                <p style="color: #2c3e50; opacity: 0.8;">
                    Create your account as a 
                    <span style="color: #4facfe; font-weight: 600;">
                        <?php echo ucfirst($user_type); ?>
                    </span>
                </p>
            </div>

            <?php if ($error): ?>
                <div style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div style="background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    <div style="margin-top: 1rem;">
                        <a href="login.php" class="btn btn-secondary">Go to Login</a>
                    </div>
                </div>
            <?php else: ?>
            
            <form method="POST">
                <input type="hidden" name="user_type" value="<?php echo $user_type; ?>">
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Username *
                        </label>
                        <input type="text" name="username" class="form-input" placeholder="Choose a username" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email *
                        </label>
                        <input type="email" name="email" class="form-input" placeholder="your@email.com" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-id-card"></i> Full Name *
                    </label>
                    <input type="text" name="full_name" class="form-input" placeholder="Enter your full name" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password *
                    </label>
                    <input type="password" name="password" id="password" class="form-input" placeholder="Strong password required" required minlength="10">
                    
                    <div class="password-requirements" style="margin-top: 1rem; display: none;">
                        <div class="requirement" data-requirement="length" style="display: flex; align-items: center; margin-bottom: 0.5rem; color: #2c3e50; font-size: 0.9rem;">
                            <i class="fas fa-times" style="color: #ff4b2b; margin-right: 0.5rem; width: 16px;"></i> At least 10 characters
                        </div>
                        <div class="requirement" data-requirement="uppercase" style="display: flex; align-items: center; margin-bottom: 0.5rem; color: #2c3e50; font-size: 0.9rem;">
                            <i class="fas fa-times" style="color: #ff4b2b; margin-right: 0.5rem; width: 16px;"></i> One uppercase letter
                        </div>
                        <div class="requirement" data-requirement="lowercase" style="display: flex; align-items: center; margin-bottom: 0.5rem; color: #2c3e50; font-size: 0.9rem;">
                            <i class="fas fa-times" style="color: #ff4b2b; margin-right: 0.5rem; width: 16px;"></i> One lowercase letter
                        </div>
                        <div class="requirement" data-requirement="number" style="display: flex; align-items: center; margin-bottom: 0.5rem; color: #2c3e50; font-size: 0.9rem;">
                            <i class="fas fa-times" style="color: #ff4b2b; margin-right: 0.5rem; width: 16px;"></i> One number
                        </div>
                        <div class="requirement" data-requirement="special" style="display: flex; align-items: center; margin-bottom: 0.5rem; color: #2c3e50; font-size: 0.9rem;">
                            <i class="fas fa-times" style="color: #ff4b2b; margin-right: 0.5rem; width: 16px;"></i> One special character
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Confirm Password *
                    </label>
                    <input type="password" name="confirm_password" class="form-input" placeholder="Confirm your password" required minlength="10">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-phone"></i> Phone Number
                    </label>
                    <input type="text" name="phone" class="form-input" placeholder="Your phone number">
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-map-marker-alt"></i> Address
                    </label>
                    <textarea name="address" class="form-input" rows="3" placeholder="Your address"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>

            <?php endif; ?>

            <div style="text-align: center; margin-top: 2rem;">
                <p style="color: #2c3e50;">Already have an account? 
                    <a href="login.php" style="color: #4facfe; text-decoration: none; font-weight: 600;">
                        Sign in here
                    </a>
                </p>
                <a href="../index.php" style="color: #2c3e50; text-decoration: none; margin-top: 1rem; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>

            <div style="margin-top: 2rem; text-align: center;">
                <p style="color: #2c3e50; margin-bottom: 1rem;">Choose your account type:</p>
                <div style="display: flex; gap: 1rem; justify-content: center;">
                    <a href="?type=user" class="btn <?php echo $user_type === 'user' ? 'btn-primary' : 'btn-secondary'; ?>">
                        <i class="fas fa-user"></i> Customer
                    </a>
                    <a href="?type=provider" class="btn <?php echo $user_type === 'provider' ? 'btn-primary' : 'btn-secondary'; ?>">
                        <i class="fas fa-briefcase"></i> Service Provider
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Password validation
            const passwordInput = document.getElementById('password');
            const requirements = document.querySelectorAll('.requirement');
            const requirementsContainer = document.querySelector('.password-requirements');
            
            if (passwordInput && requirements.length > 0) {
                // Show requirements when password field is focused
                passwordInput.addEventListener('focus', function() {
                    requirementsContainer.style.display = 'block';
                });
                
                // Hide requirements when password field is blurred and empty
                passwordInput.addEventListener('blur', function() {
                    if (this.value === '') {
                        requirementsContainer.style.display = 'none';
                    }
                });
                
                // Real-time password validation
                passwordInput.addEventListener('input', function() {
                    const password = this.value;
                    
                    if (password === '') {
                        requirementsContainer.style.display = 'none';
                        return;
                    }
                    
                    requirementsContainer.style.display = 'block';
                    
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
                            req.style.color = '#28a745';
                            icon.className = 'fas fa-check';
                            icon.style.color = '#28a745';
                        } else {
                            req.style.color = '#2c3e50';
                            icon.className = 'fas fa-times';
                            icon.style.color = '#ff4b2b';
                        }
                    });
                });
            }

            // Email validation (basic)
            const emailInput = document.querySelector('input[name="email"]');
            if (emailInput) {
                emailInput.addEventListener('blur', function() {
                    const email = this.value;
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    
                    if (email && !emailRegex.test(email)) {
                        this.style.borderColor = '#ff4b2b';
                    } else {
                        this.style.borderColor = '';
                    }
                });
            }
        });
    </script>
</body>
</html>