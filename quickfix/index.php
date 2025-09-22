<?php 
require_once 'config/config.php';

// If user is logged in, redirect to appropriate dashboard
if (isLoggedIn()) {
    $userType = getUserType();
    switch ($userType) {
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

// Get featured services
$database = new Database();
$db = $database->getConnection();

$query = "SELECT * FROM services WHERE status = 'active' ORDER BY created_at DESC LIMIT 6";
$stmt = $db->prepare($query);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - Local Service Provider Platform</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="#" class="logo">
                <i class="fas fa-tools"></i> QuickFix
            </a>
            <ul class="nav-menu">
                <li><a href="#home" class="nav-link">Home</a></li>
                <li><a href="#services" class="nav-link">Services</a></li>
                <li><a href="#about" class="nav-link">About</a></li>
                <li><a href="#contact" class="nav-link">Contact</a></li>
                <li><a href="auth/login.php" class="nav-link">Login</a></li>
                <li><a href="auth/register.php" class="btn btn-primary">Sign Up</a></li>
            </ul>
        </div>
    </nav>

    <!-- Hero Section -->
    <section id="home" class="hero">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-wrench"></i> QuickFix
                </h1>
                <p class="dashboard-subtitle">
                    Your trusted local service provider platform. Connect with skilled professionals for all your home and business needs.
                </p>
                <div style="margin-top: 2rem;">
                    <a href="auth/register.php?type=user" class="btn btn-primary" style="margin-right: 1rem;">
                        <i class="fas fa-user"></i> Find Services
                    </a>
                    <a href="auth/register.php?type=provider" class="btn btn-secondary">
                        <i class="fas fa-briefcase"></i> Become a Provider
                    </a>
                </div>
            </div>

            <!-- Features -->
            <div class="stats-grid" style="margin-top: 4rem;">
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-shield-alt" style="color: #4facfe;"></i>
                    </div>
                    <div class="stat-label">Trusted & Verified</div>
                    <p style="margin-top: 1rem; opacity: 0.8;">All service providers are thoroughly vetted and verified</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-clock" style="color: #4facfe;"></i>
                    </div>
                    <div class="stat-label">24/7 Support</div>
                    <p style="margin-top: 1rem; opacity: 0.8;">Round-the-clock customer support for all your needs</p>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-star" style="color: #4facfe;"></i>
                    </div>
                    <div class="stat-label">Quality Service</div>
                    <p style="margin-top: 1rem; opacity: 0.8;">Guaranteed satisfaction with every service booking</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section id="services" style="padding: 4rem 0;">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2 class="dashboard-title">Our Services</h2>
                <p class="dashboard-subtitle">Professional services for your home and business</p>
            </div>

            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                <div class="service-card">
                    <div class="service-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-tools" style="font-size: 3rem; color: white;"></i>
                    </div>
                    <div class="service-content">
                        <h3 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                        <div class="service-price">$<?php echo number_format($service['base_price'], 2); ?></div>
                        <a href="auth/login.php" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section id="about" style="padding: 4rem 0;">
        <div class="dashboard-container">
            <div class="glass-container" style="padding: 3rem; text-align: center;">
                <h2 style="font-size: 2.5rem; margin-bottom: 2rem; color: #2c3e50;">About QuickFix</h2>
                <p style="font-size: 1.2rem; line-height: 1.8; color: #2c3e50; margin-bottom: 2rem;">
                    QuickFix is your go-to platform for connecting with trusted local service providers. 
                    Whether you need home cleaning, repairs, maintenance, or any other professional service, 
                    we've got you covered. Our platform ensures quality, reliability, and convenience for all your service needs.
                </p>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number">500+</div>
                        <div class="stat-label">Verified Providers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">10k+</div>
                        <div class="stat-label">Happy Customers</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number">50+</div>
                        <div class="stat-label">Service Categories</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section id="contact" style="padding: 4rem 0;">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h2 class="dashboard-title">Get In Touch</h2>
                <p class="dashboard-subtitle">Have questions? We're here to help!</p>
            </div>
            
            <div class="glass-container" style="max-width: 600px; margin: 0 auto;">
                <form action="#" method="POST">
                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-input" placeholder="Your full name" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" placeholder="your@email.com" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Message</label>
                        <textarea class="form-input" rows="5" placeholder="How can we help you?" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary" style="width: 100%;">
                        <i class="fas fa-paper-plane"></i> Send Message
                    </button>
                </form>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer style="background: rgba(0,0,0,0.1); backdrop-filter: blur(10px); padding: 2rem 0; margin-top: 4rem;">
        <div class="dashboard-container">
            <div style="text-align: center; color: white;">
                <p>&copy; 2024 QuickFix. All rights reserved. | Built with ❤️ for local communities</p>
            </div>
        </div>
    </footer>

    <script src="assets/js/main.js"></script>
</body>
</html>