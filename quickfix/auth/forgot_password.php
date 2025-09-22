<?php 
require_once '../config/config.php';

$success = '';
$error = '';
$step = 'email'; // email, verification, reset

// Check if we have a reset token in the URL
if (isset($_GET['token'])) {
    $step = 'reset';
    $reset_token = $_GET['token'];
}

// Handle form submissions
if ($_POST) {
    $database = new Database();
    $db = $database->getConnection();
    
    if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // Step 1: Send reset email
        if ($action === 'send_reset_email') {
            $email = trim($_POST['email']);
            
            if (empty($email)) {
                $error = 'Please enter your email address.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';
            } else {
                // Check if email exists
                $user_query = "SELECT id, full_name, email FROM users WHERE email = :email";
                $user_stmt = $db->prepare($user_query);
                $user_stmt->execute([':email' => $email]);
                
                if ($user_stmt->rowCount() > 0) {
                    $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Generate reset token
                    $reset_token = bin2hex(random_bytes(32));
                    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
                    
                    // Store reset token in database (you'll need to create this table)
                    try {
                        $token_query = "INSERT INTO password_resets (user_id, email, token, expires_at, created_at) 
                                       VALUES (:user_id, :email, :token, :expires_at, NOW())
                                       ON DUPLICATE KEY UPDATE 
                                       token = :token, expires_at = :expires_at, created_at = NOW()";
                        $token_stmt = $db->prepare($token_query);
                        $token_stmt->execute([
                            ':user_id' => $user['id'],
                            ':email' => $email,
                            ':token' => $reset_token,
                            ':expires_at' => $expires_at
                        ]);
                        
                        // In a real application, you would send an actual email here
                        // For demo purposes, we'll just show the reset link
                        $reset_link = SITE_URL . '/auth/forgot_password.php?token=' . $reset_token;
                        
                        $success = "Password reset instructions have been sent to your email. For demo purposes, here's your reset link: <br><br>
                                   <a href='" . $reset_link . "' style='color: #4facfe; word-break: break-all;'>" . $reset_link . "</a>";
                        
                    } catch (PDOException $e) {
                        // If password_resets table doesn't exist, create it
                        if ($e->getCode() == '42S02') {
                            try {
                                $create_table = "CREATE TABLE password_resets (
                                    id INT AUTO_INCREMENT PRIMARY KEY,
                                    user_id INT NOT NULL,
                                    email VARCHAR(255) NOT NULL,
                                    token VARCHAR(255) NOT NULL UNIQUE,
                                    expires_at DATETIME NOT NULL,
                                    created_at DATETIME NOT NULL,
                                    used_at DATETIME NULL,
                                    UNIQUE KEY unique_email (email),
                                    INDEX idx_token (token),
                                    INDEX idx_expires (expires_at)
                                )";
                                $db->exec($create_table);
                                
                                // Try inserting the token again
                                $token_stmt->execute([
                                    ':user_id' => $user['id'],
                                    ':email' => $email,
                                    ':token' => $reset_token,
                                    ':expires_at' => $expires_at
                                ]);
                                
                                $reset_link = SITE_URL . '/auth/forgot_password.php?token=' . $reset_token;
                                $success = "Password reset instructions have been sent to your email. For demo purposes, here's your reset link: <br><br>
                                           <a href='" . $reset_link . "' style='color: #4facfe; word-break: break-all;'>" . $reset_link . "</a>";
                                
                            } catch (PDOException $e2) {
                                $error = 'Database error occurred. Please try again later.';
                            }
                        } else {
                            $error = 'An error occurred. Please try again later.';
                        }
                    }
                } else {
                    // Don't reveal whether email exists or not for security
                    $success = "If an account with that email exists, you will receive password reset instructions shortly.";
                }
            }
        }
        
        // Step 2: Reset password with token
        elseif ($action === 'reset_password') {
            $token = $_POST['token'];
            $new_password = $_POST['new_password'];
            $confirm_password = $_POST['confirm_password'];
            
            if (empty($new_password) || empty($confirm_password)) {
                $error = 'Please fill in all password fields.';
            } elseif ($new_password !== $confirm_password) {
                $error = 'Passwords do not match.';
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
            } else {
                // Verify token is valid and not expired
                $token_query = "SELECT pr.*, u.id as user_id, u.email, u.full_name 
                               FROM password_resets pr 
                               JOIN users u ON pr.user_id = u.id 
                               WHERE pr.token = :token AND pr.expires_at > NOW() AND pr.used_at IS NULL";
                $token_stmt = $db->prepare($token_query);
                $token_stmt->execute([':token' => $token]);
                
                if ($token_stmt->rowCount() > 0) {
                    $reset_data = $token_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Update user password
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $update_query = "UPDATE users SET password = :password, updated_at = NOW() WHERE id = :user_id";
                    $update_stmt = $db->prepare($update_query);
                    
                    if ($update_stmt->execute([':password' => $hashed_password, ':user_id' => $reset_data['user_id']])) {
                        // Mark token as used
                        $mark_used = "UPDATE password_resets SET used_at = NOW() WHERE token = :token";
                        $mark_stmt = $db->prepare($mark_used);
                        $mark_stmt->execute([':token' => $token]);
                        
                        $success = 'Your password has been reset successfully! You can now log in with your new password.';
                        $step = 'success';
                    } else {
                        $error = 'Failed to update password. Please try again.';
                    }
                } else {
                    $error = 'Invalid or expired reset token. Please request a new password reset.';
                    $step = 'email';
                }
            }
        }
    }
}

// If we have a token in URL, verify it's valid
if ($step === 'reset' && isset($reset_token)) {
    $database = new Database();
    $db = $database->getConnection();
    
    $token_query = "SELECT * FROM password_resets WHERE token = :token AND expires_at > NOW() AND used_at IS NULL";
    $token_stmt = $db->prepare($token_query);
    $token_stmt->execute([':token' => $reset_token]);
    
    if ($token_stmt->rowCount() === 0) {
        $error = 'Invalid or expired reset token. Please request a new password reset.';
        $step = 'email';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="glass-container" style="max-width: 500px; margin: 5rem auto;">
            
            <?php if ($step === 'email'): ?>
                <!-- Step 1: Email Input -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                        <i class="fas fa-key"></i> Forgot Password
                    </h1>
                    <p style="color: #2c3e50; opacity: 0.8;">Enter your email address and we'll send you instructions to reset your password</p>
                </div>

                <?php if ($error): ?>
                    <div style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center; line-height: 1.6;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="send_reset_email">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                        <i class="fas fa-paper-plane"></i> Send Reset Instructions
                    </button>
                </form>

            <?php elseif ($step === 'reset'): ?>
                <!-- Step 2: Password Reset Form -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                        <i class="fas fa-shield-alt"></i> Reset Password
                    </h1>
                    <p style="color: #2c3e50; opacity: 0.8;">Enter your new password below</p>
                </div>

                <?php if ($error): ?>
                    <div style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <form method="POST">
                    <input type="hidden" name="action" value="reset_password">
                    <input type="hidden" name="token" value="<?php echo htmlspecialchars($reset_token); ?>">
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> New Password
                        </label>
                        <input type="password" name="new_password" id="new_password" class="form-input" placeholder="Enter your new password" required minlength="10">
                        
                        <div class="password-requirements" style="margin-top: 1rem;">
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
                            <i class="fas fa-lock"></i> Confirm New Password
                        </label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Confirm your new password" required minlength="10">
                    </div>

                    <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                        <i class="fas fa-shield-alt"></i> Reset Password
                    </button>
                </form>

            <?php elseif ($step === 'success'): ?>
                <!-- Step 3: Success Message -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i> Password Reset
                    </h1>
                    <p style="color: #2c3e50; opacity: 0.8;">Your password has been successfully reset!</p>
                </div>

                <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>

                <div style="text-align: center;">
                    <a href="login.php" class="btn btn-primary" style="display: inline-block; text-decoration: none;">
                        <i class="fas fa-sign-in-alt"></i> Go to Login
                    </a>
                </div>

            <?php endif; ?>

            <!-- Navigation Links -->
            <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(44, 62, 80, 0.1);">
                <p style="color: #2c3e50; margin-bottom: 1rem;">Remember your password? 
                    <a href="login.php" style="color: #4facfe; text-decoration: none; font-weight: 600;">
                        Sign in here
                    </a>
                </p>
                <a href="../index.php" style="color: #2c3e50; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
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
        });
    </script>
</body>
</html>