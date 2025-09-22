<?php 
require_once '../config/config.php';
checkAccess(['user']);

$database = new Database();
$db = $database->getConnection();

$user_id = $_SESSION['user_id'];

// Get user data including profile image
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get user's bookings
$query = "SELECT b.*, s.name as service_name, u.full_name as provider_name 
          FROM bookings b 
          JOIN services s ON b.service_id = s.id 
          JOIN users u ON b.provider_id = u.id 
          WHERE b.user_id = :user_id 
          ORDER BY b.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get available services
$query = "SELECT DISTINCT s.*, u.full_name as provider_name, ps.price 
          FROM services s 
          JOIN provider_services ps ON s.id = ps.service_id 
          JOIN users u ON ps.provider_id = u.id 
          WHERE s.status = 'active' AND ps.availability = 'available'
          ORDER BY s.name";
$stmt = $db->prepare($query);
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - <?php echo SITE_NAME; ?></title>
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
                <li><a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-search"></i> Browse Services
                </a></li>
                <li><a href="bookings.php" class="nav-link">
                    <i class="fas fa-calendar-alt"></i> My Bookings
                </a></li>
                <li><a href="favorites.php" class="nav-link">
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
                                    <div class="user-name-full"><?php echo htmlspecialchars($user['full_name']); ?></div>
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
                        <a href="services.php?favorites=1" class="dropdown-item">
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

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <i class="fas fa-home"></i> Welcome Back!
            </h1>
            <p class="dashboard-subtitle">
                Hello <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Ready to book a service?
            </p>
        </div>

        <!-- Quick Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-calendar-check" style="color: #4facfe;"></i>
                    <?php echo count($bookings); ?>
                </div>
                <div class="stat-label">Total Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-clock" style="color: #f093fb;"></i>
                    <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'pending'; })); ?>
                </div>
                <div class="stat-label">Pending Bookings</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-check-circle" style="color: #56ab2f;"></i>
                    <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'completed'; })); ?>
                </div>
                <div class="stat-label">Completed Services</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-cogs" style="color: #667eea;"></i>
                    <?php echo count($services); ?>
                </div>
                <div class="stat-label">Available Services</div>
            </div>
        </div>

        <!-- Quick Book Service -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">
                <i class="fas fa-plus-circle"></i> Book a New Service
            </h2>
            <div class="services-grid">
                <?php 
                $featured_services = array_slice($services, 0, 3);
                foreach ($featured_services as $service): 
                ?>
                <div class="service-card">
                    <div class="service-image" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; height: 150px;">
                        <i class="fas fa-tools" style="font-size: 2.5rem; color: white;"></i>
                    </div>
                    <div class="service-content">
                        <h3 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                        <p style="color: #2c3e50; opacity: 0.8; margin-bottom: 0.5rem;">
                            Provider: <?php echo htmlspecialchars($service['provider_name']); ?>
                        </p>
                        <div class="service-price">₱<?php echo number_format($service['price'], 2); ?></div>
                        <a href="book_service.php?id=<?php echo $service['id']; ?>" class="btn btn-primary">
                            <i class="fas fa-calendar-plus"></i> Book Now
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div style="text-align: center; margin-top: 2rem;">
                <a href="services.php" class="btn btn-secondary">
                    <i class="fas fa-eye"></i> View All Services
                </a>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">
                <i class="fas fa-history"></i> Recent Bookings
            </h2>
            
            <?php if (count($bookings) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Provider</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Status</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recent_bookings = array_slice($bookings, 0, 5);
                        foreach ($recent_bookings as $booking): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['provider_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></td>
                            <td>
                                <span style="padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; font-weight: 600; 
                                      background: <?php 
                                          switch($booking['status']) {
                                              case 'pending': echo 'linear-gradient(135deg, #f7971e 0%, #ffd200 100%)';
                                                  break;
                                              case 'confirmed': echo 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)';
                                                  break;
                                              case 'completed': echo 'linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%)';
                                                  break;
                                              case 'cancelled': echo 'linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%)';
                                                  break;
                                              default: echo 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                                          }
                                      ?>; color: white;">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </td>
                            <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                            <td>
                                <a href="booking_details.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary" style="font-size: 0.8rem; padding: 0.5rem 1rem;">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if ($booking['status'] == 'completed'): ?>
                                <a href="review.php?id=<?php echo $booking['id']; ?>" class="btn btn-primary" style="font-size: 0.8rem; padding: 0.5rem 1rem; margin-left: 0.5rem;">
                                    <i class="fas fa-star"></i> Review
                                </a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #2c3e50; opacity: 0.6;">
                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>No bookings yet. Book your first service!</p>
                <a href="services.php" class="btn btn-primary" style="margin-top: 1rem;">
                    <i class="fas fa-search"></i> Browse Services
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>