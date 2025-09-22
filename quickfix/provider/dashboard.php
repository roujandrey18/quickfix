<?php 
require_once '../config/config.php';
checkAccess(['provider']);

$database = new Database();
$db = $database->getConnection();

$provider_id = $_SESSION['user_id'];

// Get provider data including profile image
$user_query = "SELECT * FROM users WHERE id = :provider_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':provider_id', $provider_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get provider's bookings
$query = "SELECT b.*, s.name as service_name, u.full_name as user_name 
          FROM bookings b 
          JOIN services s ON b.service_id = s.id 
          JOIN users u ON b.user_id = u.id 
          WHERE b.provider_id = :provider_id 
          ORDER BY b.created_at DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':provider_id', $provider_id);
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get provider's services with booking stats and statistics
$query = "SELECT 
    s.*,
    ps.price,
    ps.availability,
    COUNT(b.id) as total_bookings,
    COUNT(CASE WHEN b.status = 'completed' THEN 1 END) as completed_bookings,
    COUNT(CASE WHEN b.status = 'pending' THEN 1 END) as pending_bookings,
    SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as total_earnings
FROM provider_services ps
JOIN services s ON ps.service_id = s.id
LEFT JOIN bookings b ON b.service_id = s.id AND b.provider_id = ps.provider_id
WHERE ps.provider_id = :provider_id
GROUP BY s.id, ps.price, ps.availability
ORDER BY s.name ASC
LIMIT 6";
$stmt = $db->prepare($query);
$stmt->bindParam(':provider_id', $provider_id);
$stmt->execute();
$my_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate earnings
$total_earnings = 0;
foreach ($bookings as $booking) {
    if ($booking['status'] == 'completed') {
        $total_earnings += $booking['total_amount'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Provider Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-wrench"></i> QuickFix Pro
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link active">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="bookings.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Bookings
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-cogs"></i> My Services
                </a></li>
                <li><a href="earnings.php" class="nav-link">
                    <i class="fas fa-peso-sign"></i> Earnings
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
                        <a href="services.php" class="dropdown-item">
                            <i class="fas fa-cogs"></i> Manage Services
                        </a>
                        <a href="earnings.php" class="dropdown-item">
                            <i class="fas fa-chart-bar"></i> View Earnings
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
                <i class="fas fa-chart-line"></i> Provider Dashboard
            </h1>
            <p class="dashboard-subtitle">
                Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! Here's your business overview.
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <a href="bookings.php" class="stat-card clickable">
                <div class="stat-number">
                    <i class="fas fa-calendar-alt" style="color: #4facfe;"></i>
                    <?php echo count($bookings); ?>
                </div>
                <div class="stat-label">Total Bookings</div>
                <div class="stat-action">
                    <i class="fas fa-arrow-right"></i> View All
                </div>
            </a>
            <a href="bookings.php?filter=pending" class="stat-card clickable">
                <div class="stat-number">
                    <i class="fas fa-clock" style="color: #f093fb;"></i>
                    <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'pending'; })); ?>
                </div>
                <div class="stat-label">Pending Requests</div>
                <div class="stat-action">
                    <i class="fas fa-arrow-right"></i> Review Now
                </div>
            </a>
            <a href="bookings.php?filter=completed" class="stat-card clickable">
                <div class="stat-number">
                    <i class="fas fa-check-circle" style="color: #56ab2f;"></i>
                    <?php echo count(array_filter($bookings, function($b) { return $b['status'] == 'completed'; })); ?>
                </div>
                <div class="stat-label">Completed Jobs</div>
                <div class="stat-action">
                    <i class="fas fa-arrow-right"></i> View History
                </div>
            </a>
            <a href="earnings.php" class="stat-card clickable">
                <div class="stat-number">
                    <i class="fas fa-peso-sign" style="color: #667eea;"></i>
                    ₱<?php echo number_format($total_earnings, 2); ?>
                </div>
                <div class="stat-label">Total Earnings</div>
                <div class="stat-action">
                    <i class="fas fa-arrow-right"></i> View Details
                </div>
            </a>
        </div>

        <!-- Pending Bookings -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">
                <i class="fas fa-clock"></i> Pending Bookings
                <?php 
                $pending_count = count(array_filter($bookings, function($b) { return $b['status'] == 'pending'; }));
                if ($pending_count > 0): ?>
                <span style="background: linear-gradient(135deg, #f7971e 0%, #ffd200 100%); color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.8rem; margin-left: 1rem;">
                    <?php echo $pending_count; ?> New
                </span>
                <?php endif; ?>
            </h2>
            
            <?php 
            $pending_bookings = array_filter($bookings, function($b) { return $b['status'] == 'pending'; });
            if (count($pending_bookings) > 0): 
            ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Amount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pending_bookings as $booking): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                            <td><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></td>
                            <td>₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                            <td>
                                <div class="action-buttons">
                                    <a href="update_booking.php?id=<?php echo $booking['id']; ?>&status=confirmed" 
                                       class="btn btn-primary btn-sm action-btn" 
                                       onclick="return confirm('Accept this booking request?')">
                                        <i class="fas fa-check"></i> Accept
                                    </a>
                                    <a href="update_booking.php?id=<?php echo $booking['id']; ?>&status=cancelled" 
                                       class="btn btn-danger btn-sm action-btn" 
                                       onclick="return confirm('Decline this booking request?')">
                                        <i class="fas fa-times"></i> Decline
                                    </a>
                                    <a href="view_booking.php?id=<?php echo $booking['id']; ?>" 
                                       class="btn btn-secondary btn-sm action-btn">
                                        <i class="fas fa-eye"></i> Details
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #2c3e50; opacity: 0.6;">
                <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>No pending bookings at the moment!</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- My Services -->
        <div class="glass-container">
            <div class="section-header">
                <h2 class="section-title">
                    <i class="fas fa-cogs"></i> My Services
                </h2>
                <a href="add_service.php" class="btn btn-primary btn-sm">
                    <i class="fas fa-plus"></i> Add Service
                </a>
            </div>
            
            <?php if (count($my_services) > 0): ?>
            <div class="services-grid">
                <?php foreach ($my_services as $service): ?>
                    <div class="service-card provider-service <?php echo $service['availability']; ?>">
                        <!-- Service Header -->
                        <div class="service-header">
                            <div class="service-category-badge">
                                <i class="fas fa-tag"></i>
                                <?php echo htmlspecialchars($service['category']); ?>
                            </div>
                            <div class="service-status">
                                <span class="status-indicator status-<?php echo $service['availability']; ?>">
                                    <i class="fas fa-<?php echo $service['availability'] === 'available' ? 'check-circle' : 'pause-circle'; ?>"></i>
                                    <?php echo ucfirst($service['availability']); ?>
                                </span>
                            </div>
                        </div>

                        <!-- Service Image/Icon -->
                        <div class="service-image">
                            <i class="fas fa-tools service-icon"></i>
                            <?php if ($service['availability'] === 'unavailable'): ?>
                                <div class="service-overlay">
                                    <i class="fas fa-pause"></i>
                                    <span>Inactive</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Service Content -->
                        <div class="service-content">
                            <h3 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="service-description"><?php echo htmlspecialchars(substr($service['description'], 0, 80)); ?><?php echo strlen($service['description']) > 80 ? '...' : ''; ?></p>
                            
                            <div class="service-pricing">
                                <div class="price-main">₱<?php echo number_format($service['price'], 2); ?></div>
                                <?php if ($service['base_price'] != $service['price']): ?>
                                    <div class="price-base">Base: ₱<?php echo number_format($service['base_price'], 2); ?></div>
                                <?php endif; ?>
                            </div>

                            <!-- Service Statistics -->
                            <div class="service-stats">
                                <div class="stat-item">
                                    <i class="fas fa-calendar-check"></i>
                                    <span><?php echo $service['total_bookings']; ?> bookings</span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-clock"></i>
                                    <span><?php echo $service['pending_bookings']; ?> pending</span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-peso-sign"></i>
                                    <span>₱<?php echo number_format($service['total_earnings'] ?? 0, 2); ?></span>
                                </div>
                                <div class="stat-item">
                                    <i class="fas fa-check-circle"></i>
                                    <span><?php echo $service['completed_bookings']; ?> done</span>
                                </div>
                            </div>

                            <!-- Service Actions -->
                            <div class="service-actions">
                                <button onclick="toggleServiceStatus(<?php echo $service['id']; ?>)" 
                                        class="btn btn-sm <?php echo $service['availability'] === 'available' ? 'btn-warning' : 'btn-success'; ?>">
                                    <i class="fas fa-<?php echo $service['availability'] === 'available' ? 'pause' : 'play'; ?>"></i>
                                    <?php echo $service['availability'] === 'available' ? 'Pause' : 'Activate'; ?>
                                </button>
                                
                                <a href="services.php" class="btn btn-outline-primary btn-sm">
                                    <i class="fas fa-cogs"></i> Manage
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <div class="section-footer">
                <a href="services.php" class="btn btn-outline-primary">
                    <i class="fas fa-cogs"></i> View All Services
                </a>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-cogs" style="font-size: 4rem; color: #4facfe; margin-bottom: 1rem;"></i>
                <h3>No services added yet</h3>
                <p>Start building your service portfolio to attract customers and grow your business.</p>
                <div class="empty-actions">
                    <a href="add_service.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Your First Service
                    </a>
                    <a href="services.php" class="btn btn-secondary">
                        <i class="fas fa-cogs"></i> Browse Services
                    </a>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Recent Activity -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">
                <i class="fas fa-history"></i> Recent Activity
            </h2>
            
            <?php if (count($bookings) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Customer</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $recent_bookings = array_slice($bookings, 0, 8);
                        foreach ($recent_bookings as $booking): 
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                            <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
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
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div style="text-align: center; padding: 2rem; color: #2c3e50; opacity: 0.6;">
                <i class="fas fa-calendar-times" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>No bookings yet. Customers will find you soon!</p>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
    // Service status toggle functionality
    function toggleServiceStatus(serviceId) {
        const button = event.target.closest('button');
        const originalContent = button.innerHTML;
        
        // Show loading state
        button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        button.disabled = true;
        
        fetch('services.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=toggle_status&service_id=${serviceId}`
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Show success feedback
                button.innerHTML = '<i class="fas fa-check"></i> Updated!';
                button.className = 'btn btn-sm btn-success';
                
                // Reload page after short delay to show updated status
                setTimeout(() => {
                    location.reload();
                }, 1000);
            } else {
                // Show error and restore button
                button.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Error';
                button.className = 'btn btn-sm btn-danger';
                
                setTimeout(() => {
                    button.innerHTML = originalContent;
                    button.disabled = false;
                    button.className = button.getAttribute('data-original-class') || 'btn btn-sm btn-warning';
                }, 2000);
                
                alert('Error updating service status. Please try again.');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            button.innerHTML = originalContent;
            button.disabled = false;
            alert('Network error. Please try again.');
        });
    }
    
    // Store original button classes for error handling
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.service-actions button[onclick*="toggleServiceStatus"]').forEach(btn => {
            btn.setAttribute('data-original-class', btn.className);
        });
    });
    </script>
</body>
</html>