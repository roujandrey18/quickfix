<?php 
require_once '../config/config.php';

$error = '';

if ($_POST) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    
    if (empty($username) || empty($password)) {
        $error = 'Please fill in all fields.';
    } else {
        $database = new Database();
        $db = $database->getConnection();
        
        $query = "SELECT * FROM users WHERE username = :username OR email = :username";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password'])) {
                // Check if email is verified (handle cases where column might not exist yet)
                $email_verified = isset($user['email_verified']) ? $user['email_verified'] : 1;
                
                if ($email_verified == 0) {
                    $error = 'Please verify your email address before logging in. Check your email for the verification link or <a href="verify_email.php" style="color: #4facfe;">request a new one</a>.';
                } else {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['user_type'] = $user['user_type'];
                    
                    // Redirect based on user type
                    switch ($user['user_type']) {
                        case 'admin':
                            redirect(SITE_URL . '/admin/dashboard.php');
                            break;
                        case 'provider':
                            redirect(SITE_URL . '/provider/dashboard.php');
                            break;
                        case 'user':
                            redirect(SITE_URL . '/user/dashboard.php');
                            break;
                    }
                }
            } else {
                $error = 'Invalid username or password.';
            }
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="dashboard-container">
        <div class="glass-container" style="max-width: 500px; margin: 5rem auto;">
            <div style="text-align: center; margin-bottom: 2rem;">
                <h1 style="font-size: 2.5rem; margin-bottom: 1rem; color: #2c3e50;">
                    <i class="fas fa-sign-in-alt"></i> Welcome Back
                </h1>
                <p style="color: #2c3e50; opacity: 0.8;">Sign in to your QuickFix account</p>
            </div>

            <?php if ($error): ?>
                <div style="background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%); color: white; padding: 1rem; border-radius: 10px; margin-bottom: 1rem; text-align: center;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-user"></i> Username or Email
                    </label>
                    <input type="text" name="username" class="form-input" placeholder="Enter your username or email" required>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-lock"></i> Password
                    </label>
                    <input type="password" name="password" class="form-input" placeholder="Enter your password" required>
                </div>

                <button type="submit" class="btn btn-primary" style="width: 100%; margin-bottom: 1rem;">
                    <i class="fas fa-sign-in-alt"></i> Sign In
                </button>

                <div style="text-align: center; margin-bottom: 1rem;">
                    <a href="forgot_password.php" style="color: #4facfe; text-decoration: none; font-size: 0.9rem;">
                        <i class="fas fa-question-circle"></i> Forgot your password?
                    </a>
                </div>
            </form>

            <div style="text-align: center; margin-top: 2rem;">
                <p style="color: #2c3e50;">Don't have an account? 
                    <a href="register.php" style="color: #4facfe; text-decoration: none; font-weight: 600;">
                        Sign up here
                    </a>
                </p>
                <a href="../index.php" style="color: #2c3e50; text-decoration: none; margin-top: 1rem; display: inline-block;">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </a>
            </div>
        </div>
    </div>
</body>
</html>