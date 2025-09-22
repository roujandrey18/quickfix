<?php
require_once '../config/config.php';
checkAccess(['user']);

$database = new Database();
$db = $database->getConnection();
$user_id = $_SESSION['user_id'];

// Get user data
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle remove favorite action
if (isset($_POST['remove_favorite']) && isset($_POST['service_id']) && isset($_POST['provider_id'])) {
    $service_id = (int)$_POST['service_id'];
    $provider_id = (int)$_POST['provider_id'];
    
    $delete_query = "DELETE FROM user_favorites 
                     WHERE user_id = :user_id AND service_id = :service_id AND provider_id = :provider_id";
    $delete_stmt = $db->prepare($delete_query);
    $delete_stmt->bindParam(':user_id', $user_id);
    $delete_stmt->bindParam(':service_id', $service_id);
    $delete_stmt->bindParam(':provider_id', $provider_id);
    
    if ($delete_stmt->execute()) {
        $success_message = "Service removed from favorites successfully!";
    } else {
        $error_message = "Failed to remove service from favorites.";
    }
}

// Create user_favorites table if it doesn't exist
try {
    $create_table = "CREATE TABLE IF NOT EXISTS user_favorites (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        provider_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favorite (user_id, service_id, provider_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($create_table);
} catch (Exception $e) {
    // If foreign key constraints fail, create table without them
    $create_table_simple = "CREATE TABLE IF NOT EXISTS user_favorites (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        provider_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favorite (user_id, service_id, provider_id)
    )";
    $db->exec($create_table_simple);
}

// Get user's favorite services with full details
$favorites_query = "
    SELECT 
        uf.id as favorite_id,
        uf.created_at as favorited_at,
        s.id as service_id,
        s.name as service_name,
        s.description as service_description,
        ps.price,
        s.category,
        s.image as image_url,
        u.id as provider_id,
        u.full_name as provider_name,
        u.profile_image as provider_image,
        u.email as provider_email,
        u.phone as provider_phone,
        COALESCE(AVG(r.rating), 0) as avg_rating,
        COUNT(DISTINCT r.id) as review_count,
        (SELECT COUNT(*) FROM bookings b WHERE b.provider_id = u.id AND b.status = 'completed') as completed_jobs
    FROM user_favorites uf
    INNER JOIN services s ON uf.service_id = s.id
    INNER JOIN provider_services ps ON s.id = ps.service_id AND uf.provider_id = ps.provider_id
    INNER JOIN users u ON uf.provider_id = u.id
    LEFT JOIN bookings b ON b.provider_id = u.id AND b.service_id = s.id
    LEFT JOIN reviews r ON b.id = r.booking_id
    WHERE uf.user_id = :user_id
    GROUP BY uf.id, s.id, u.id, ps.id
    ORDER BY uf.created_at DESC
";

$favorites_stmt = $db->prepare($favorites_query);
$favorites_stmt->bindParam(':user_id', $user_id);
$favorites_stmt->execute();
$favorites = $favorites_stmt->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-wrench"></i> QuickFix
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-search"></i> Browse Services
                </a></li>
                <li><a href="bookings.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> My Bookings
                </a></li>
                <li><a href="favorites.php" class="nav-link active">
                    <i class="fas fa-heart"></i> Favorites
                </a></li>
                
                <!-- User Avatar Dropdown -->
                <li class="nav-dropdown user-dropdown">
                    <a href="#" class="nav-link dropdown-toggle user-profile-link">
                        <div class="nav-avatar">
                            <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user['profile_image'])): ?>
                                <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" 
                                     alt="Profile" class="nav-avatar-img">
                            <?php else: ?>
                                <i class="fas fa-user-circle nav-avatar-icon"></i>
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
                                    <i class="fas fa-user-circle dropdown-avatar-icon"></i>
                                <?php endif; ?>
                                <div class="user-details">
                                    <div class="user-name-large"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Profile Settings
                        </a>
                        <a href="bookings.php" class="dropdown-item">
                            <i class="fas fa-history"></i> Booking History
                        </a>
                        <a href="favorites.php" class="dropdown-item active">
                            <i class="fas fa-heart"></i> My Favorites
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

    <div class="main-container">
        <div class="page-header">
            <div class="page-title">
                <h1><i class="fas fa-heart"></i> My Favorites</h1>
                <p>Your saved services and preferred providers</p>
            </div>
            <div class="page-actions">
                <a href="services.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Browse Services
                </a>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                <i class="fas fa-check-circle"></i>
                <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (count($favorites) > 0): ?>
            <!-- Favorites Count -->
            <div class="stats-banner">
                <div class="stats-item">
                    <i class="fas fa-heart"></i>
                    <span class="stats-number"><?php echo count($favorites); ?></span>
                    <span class="stats-label">Favorite Service<?php echo count($favorites) !== 1 ? 's' : ''; ?></span>
                </div>
            </div>

            <!-- Favorites Grid -->
            <div class="services-grid">
                <?php foreach ($favorites as $favorite): ?>
                    <div class="service-card favorite-service-card">
                        <div class="service-image-container">
                            <?php if ($favorite['image_url'] && $favorite['image_url'] !== 'service-default.jpg'): ?>
                                <img src="../uploads/services/<?php echo htmlspecialchars($favorite['image_url']); ?>" 
                                     alt="Service" class="service-image">
                            <?php else: ?>
                                <div class="service-image service-placeholder">
                                    <i class="fas fa-tools"></i>
                                </div>
                            <?php endif; ?>
                            <div class="favorite-badge">
                                <i class="fas fa-heart"></i>
                            </div>
                            <div class="favorite-date">
                                <?php echo date('M j, Y', strtotime($favorite['favorited_at'])); ?>
                            </div>
                        </div>
                        
                        <div class="service-content">
                            <div class="service-header">
                                <h3 class="service-title"><?php echo htmlspecialchars($favorite['service_name']); ?></h3>
                                <div class="service-category"><?php echo htmlspecialchars($favorite['category']); ?></div>
                            </div>
                            
                            <?php if ($favorite['service_description']): ?>
                                <p class="service-description">
                                    <?php echo htmlspecialchars(substr($favorite['service_description'], 0, 120)); ?>
                                    <?php echo strlen($favorite['service_description']) > 120 ? '...' : ''; ?>
                                </p>
                            <?php endif; ?>
                            
                            <div class="provider-info">
                                <div class="provider-avatar">
                                    <img src="../uploads/profile_photos/<?php echo $favorite['provider_image'] ? htmlspecialchars($favorite['provider_image']) : 'default.jpg'; ?>" 
                                         alt="Provider">
                                </div>
                                <div class="provider-details">
                                    <div class="provider-name"><?php echo htmlspecialchars($favorite['provider_name']); ?></div>
                                    <div class="provider-stats">
                                        <span class="rating">
                                            <i class="fas fa-star"></i>
                                            <?php echo $favorite['avg_rating'] > 0 ? number_format($favorite['avg_rating'], 1) : 'New'; ?>
                                        </span>
                                        <span class="jobs">
                                            <?php echo $favorite['completed_jobs']; ?> jobs
                                        </span>
                                        <?php if ($favorite['review_count'] > 0): ?>
                                            <span class="reviews">
                                                <?php echo $favorite['review_count']; ?> reviews
                                            </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="service-footer">
                                <div class="service-price">
                                    ₱<?php echo number_format($favorite['price'], 0); ?>
                                </div>
                                <div class="service-actions">
                                    <a href="book_service.php?service_id=<?php echo $favorite['service_id']; ?>&provider_id=<?php echo $favorite['provider_id']; ?>" 
                                       class="btn btn-primary btn-sm">
                                        <i class="fas fa-calendar-plus"></i> Book
                                    </a>
                                    <form method="POST" style="display: inline;" 
                                          onsubmit="return confirm('Remove this service from favorites?');">
                                        <input type="hidden" name="service_id" value="<?php echo $favorite['service_id']; ?>">
                                        <input type="hidden" name="provider_id" value="<?php echo $favorite['provider_id']; ?>">
                                        <button type="submit" name="remove_favorite" class="btn btn-outline btn-sm" 
                                                title="Remove from favorites">
                                            <i class="fas fa-heart-broken"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <div class="empty-icon">
                    <i class="fas fa-heart-crack"></i>
                </div>
                <h3>No Favorites Yet</h3>
                <p>You haven't saved any services as favorites yet.<br>Start browsing our services and save the ones you love!</p>
                <a href="services.php" class="btn btn-primary">
                    <i class="fas fa-search"></i> Browse Services
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- JavaScript -->
    <script src="../assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize mobile menu
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const navMenu = document.querySelector('.nav-menu');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                    this.classList.toggle('active');
                });
            }

            // Animate service cards on load
            const cards = document.querySelectorAll('.service-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.6s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>