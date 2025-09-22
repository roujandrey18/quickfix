<?php 
require_once '../config/config.php';

$success = '';
$error = '';
$verified = false;

// Check if we have a verification token
if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    if (empty($token)) {
        $error = 'Invalid verification link.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // Check if token exists and is valid
            $query = "SELECT id, username, email, full_name, email_verified, verification_expires 
                     FROM users 
                     WHERE verification_token = :token";
            $stmt = $db->prepare($query);
            $stmt->execute([':token' => $token]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Check if already verified
                if ($user['email_verified'] == 1) {
                    $success = 'Your email has already been verified! You can now log in to your account.';
                    $verified = true;
                } 
                // Check if token has expired
                elseif (strtotime($user['verification_expires']) < time()) {
                    $error = 'Verification link has expired. Please request a new verification email.';
                } 
                // Verify the email
                else {
                    $update_query = "UPDATE users 
                                   SET email_verified = 1, 
                                       verification_token = NULL, 
                                       verification_expires = NULL,
                                       updated_at = NOW()
                                   WHERE verification_token = :token";
                    $update_stmt = $db->prepare($update_query);
                    
                    if ($update_stmt->execute([':token' => $token])) {
                        $success = 'Email verified successfully! Your account is now active and you can log in.';
                        $verified = true;
                    } else {
                        $error = 'Failed to verify email. Please try again or contact support.';
                    }
                }
            } else {
                $error = 'Invalid verification link. Please check the link or request a new verification email.';
            }
            
        } catch (PDOException $e) {
            // Handle case where verification columns don't exist yet
            if (strpos($e->getMessage(), 'email_verified') !== false) {
                $error = 'Email verification system is not set up yet. Please contact support.';
            } else {
                $error = 'Database error occurred. Please try again later.';
            }
        }
    }
} else {
    $error = 'No verification token provided.';
}

// Handle resend verification email
if ($_POST && isset($_POST['resend_email'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        try {
            // Find user with this email who is not verified
            $user_query = "SELECT id, username, email, full_name, email_verified 
                          FROM users 
                          WHERE email = :email AND email_verified = 0";
            $user_stmt = $db->prepare($user_query);
            $user_stmt->execute([':email' => $email]);
            
            if ($user_stmt->rowCount() > 0) {
                $user = $user_stmt->fetch(PDO::FETCH_ASSOC);
                
                // Generate new verification token
                $new_token = bin2hex(random_bytes(32));
                $new_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
                
                $update_query = "UPDATE users 
                               SET verification_token = :token, 
                                   verification_expires = :expires,
                                   updated_at = NOW()
                               WHERE email = :email";
                $update_stmt = $db->prepare($update_query);
                
                if ($update_stmt->execute([
                    ':token' => $new_token, 
                    ':expires' => $new_expires,
                    ':email' => $email
                ])) {
                    $verification_link = SITE_URL . '/auth/verify_email.php?token=' . $new_token;
                    $success = 'A new verification email has been sent! For demo purposes, here\'s your verification link:<br><br>
                               <a href="' . $verification_link . '" style="color: #4facfe; word-break: break-all;">' . $verification_link . '</a>';
                } else {
                    $error = 'Failed to send verification email. Please try again.';
                }
            } else {
                // Don't reveal if email exists or not
                $success = 'If an unverified account with that email exists, a new verification email has been sent.';
            }
        } catch (PDOException $e) {
            $error = 'Database error occurred. Please try again later.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Verification - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="glass-container" style="max-width: 500px; margin: 5rem auto;">
            
            <?php if ($verified): ?>
                <!-- Email Successfully Verified -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                        <i class="fas fa-check-circle" style="color: #28a745;"></i> Email Verified!
                    </h1>
                    <p style="color: #2c3e50; opacity: 0.8;">Your account has been successfully activated</p>
                </div>

                <div style="background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 2rem; text-align: center;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>

                <div style="text-align: center;">
                    <a href="login.php" class="btn btn-primary" style="display: inline-block; text-decoration: none; margin-bottom: 1rem;">
                        <i class="fas fa-sign-in-alt"></i> Log In to Your Account
                    </a>
                </div>

            <?php else: ?>
                <!-- Verification Failed or Resend Form -->
                <div style="text-align: center; margin-bottom: 2rem;">
                    <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                        <i class="fas fa-envelope-open-text"></i> Email Verification
                    </h1>
                    <p style="color: #2c3e50; opacity: 0.8;">
                        <?php if ($error): ?>
                            There was an issue with your verification link
                        <?php else: ?>
                            Verify your email to activate your account
                        <?php endif; ?>
                    </p>
                </div>

                <?php if ($error): ?>
                    <div style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>

                    <!-- Resend Verification Email Form -->
                    <div style="background: rgba(76, 172, 254, 0.1); border: 1px solid rgba(76, 172, 254, 0.3); border-radius: 10px; padding: 1.5rem; margin-bottom: 1rem;">
                        <h3 style="color: #2c3e50; margin-bottom: 1rem; text-align: center;">
                            <i class="fas fa-paper-plane"></i> Resend Verification Email
                        </h3>
                        <p style="color: #2c3e50; opacity: 0.8; text-align: center; margin-bottom: 1rem; font-size: 0.9rem;">
                            Enter your email address to receive a new verification link
                        </p>

                        <form method="POST">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-envelope"></i> Email Address
                                </label>
                                <input type="email" name="email" class="form-input" placeholder="Enter your email address" required>
                            </div>

                            <button type="submit" name="resend_email" class="btn btn-primary" style="width: 100%;">
                                <i class="fas fa-paper-plane"></i> Resend Verification Email
                            </button>
                        </form>
                    </div>

                <?php endif; ?>

                <?php if ($success && !$verified): ?>
                    <div style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center; line-height: 1.6;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                    </div>
                <?php endif; ?>

            <?php endif; ?>

            <!-- Navigation Links -->
            <div style="text-align: center; margin-top: 2rem; padding-top: 1rem; border-top: 1px solid rgba(44, 62, 80, 0.1);">
                <p style="color: #2c3e50; margin-bottom: 1rem;">
                    <a href="login.php" style="color: #4facfe; text-decoration: none; font-weight: 600; margin-right: 1rem;">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </a>
                    <a href="register.php" style="color: #4facfe; text-decoration: none; font-weight: 600;">
                        <i class="fas fa-user-plus"></i> Register
                    </a>
                </p>
                <a href="../index.php" style="color: #2c3e50; text-decoration: none;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>