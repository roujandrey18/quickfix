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
    } elseif ($password !== $confirm_password) {
        $error = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
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
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $query = "INSERT INTO users (username, email, password, full_name, phone, address, user_type) 
                     VALUES (:username, :email, :password, :full_name, :phone, :address, :user_type)";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':password', $hashed_password);
            $stmt->bindParam(':full_name', $full_name);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':user_type', $selected_type);
            
            if ($stmt->execute()) {
                $success = 'Account created successfully! You can now login.';
            } else {
                $error = 'Error creating account. Please try again.';
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

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 1rem;">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password *
                        </label>
                        <input type="password" name="password" class="form-input" placeholder="At least 6 characters" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Confirm Password *
                        </label>
                        <input type="password" name="confirm_password" class="form-input" placeholder="Confirm your password" required>
                    </div>
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
</body>
</html>