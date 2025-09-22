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

// Get earnings data
$earnings_query = "SELECT 
    DATE(b.booking_date) as date,
    COUNT(*) as bookings_count,
    SUM(b.total_amount) as daily_earnings,
    s.name as service_name,
    b.total_amount,
    b.booking_date,
    b.status,
    u.full_name as customer_name
FROM bookings b 
JOIN services s ON b.service_id = s.id 
JOIN users u ON b.user_id = u.id 
WHERE b.provider_id = :provider_id AND b.status = 'completed'
GROUP BY DATE(b.booking_date)
ORDER BY b.booking_date DESC";

$earnings_stmt = $db->prepare($earnings_query);
$earnings_stmt->bindParam(':provider_id', $provider_id);
$earnings_stmt->execute();
$daily_earnings = $earnings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get individual completed bookings for detailed view
$bookings_query = "SELECT 
    b.*,
    s.name as service_name,
    u.full_name as customer_name
FROM bookings b 
JOIN services s ON b.service_id = s.id 
JOIN users u ON b.user_id = u.id 
WHERE b.provider_id = :provider_id AND b.status = 'completed'
ORDER BY b.booking_date DESC";

$bookings_stmt = $db->prepare($bookings_query);
$bookings_stmt->bindParam(':provider_id', $provider_id);
$bookings_stmt->execute();
$completed_bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate statistics
$total_earnings = 0;
$this_month_earnings = 0;
$this_week_earnings = 0;
$today_earnings = 0;

$current_date = new DateTime();
$first_day_of_month = new DateTime('first day of this month');
$first_day_of_week = new DateTime('last Monday');
if ($first_day_of_week > $current_date) {
    $first_day_of_week->modify('-7 days');
}

foreach ($completed_bookings as $booking) {
    $booking_date = new DateTime($booking['booking_date']);
    $amount = floatval($booking['total_amount']);
    
    $total_earnings += $amount;
    
    if ($booking_date >= $first_day_of_month) {
        $this_month_earnings += $amount;
    }
    
    if ($booking_date >= $first_day_of_week) {
        $this_week_earnings += $amount;
    }
    
    if ($booking_date->format('Y-m-d') === $current_date->format('Y-m-d')) {
        $today_earnings += $amount;
    }
}

$total_bookings = count($completed_bookings);
$average_earning = $total_bookings > 0 ? $total_earnings / $total_bookings : 0;

// Get top services by earnings
$top_services_query = "SELECT 
    s.name,
    COUNT(b.id) as booking_count,
    SUM(b.total_amount) as total_earned,
    AVG(b.total_amount) as avg_price
FROM bookings b 
JOIN services s ON b.service_id = s.id 
WHERE b.provider_id = :provider_id AND b.status = 'completed'
GROUP BY s.id, s.name
ORDER BY total_earned DESC
LIMIT 5";

$top_services_stmt = $db->prepare($top_services_query);
$top_services_stmt->bindParam(':provider_id', $provider_id);
$top_services_stmt->execute();
$top_services = $top_services_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Earnings - <?php echo SITE_NAME; ?></title>
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
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="bookings.php" class="nav-link">
                    <i class="fas fa-calendar-check"></i> Bookings
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-cogs"></i> My Services
                </a></li>
                <li><a href="add_service.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i> Add Service
                </a></li>
                <li><a href="earnings.php" class="nav-link active">
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

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-chart-line"></i> Earnings Overview
                </h1>
                <p class="dashboard-subtitle">
                    Track your income and analyze your business performance
                </p>
            </div>

            <!-- Earnings Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-peso-sign" style="color: #667eea;"></i>
                        ₱<?php echo number_format($total_earnings, 2); ?>
                    </div>
                    <div class="stat-label">Total Earnings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-calendar-month" style="color: #4facfe;"></i>
                        ₱<?php echo number_format($this_month_earnings, 2); ?>
                    </div>
                    <div class="stat-label">This Month</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-calendar-week" style="color: #f093fb;"></i>
                        ₱<?php echo number_format($this_week_earnings, 2); ?>
                    </div>
                    <div class="stat-label">This Week</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-calendar-day" style="color: #56ab2f;"></i>
                        ₱<?php echo number_format($today_earnings, 2); ?>
                    </div>
                    <div class="stat-label">Today</div>
                </div>
            </div>

            <div class="grid-container">
                <!-- Top Services -->
                <div class="glass-container">
                    <div class="section-header">
                        <h3><i class="fas fa-trophy"></i> Top Earning Services</h3>
                        <p>Your most profitable services</p>
                    </div>

                    <?php if (count($top_services) > 0): ?>
                        <div class="top-services">
                            <?php foreach ($top_services as $index => $service): ?>
                                <div class="service-rank-item">
                                    <div class="rank-badge rank-<?php echo $index + 1; ?>">
                                        #<?php echo $index + 1; ?>
                                    </div>
                                    <div class="service-details">
                                        <h4><?php echo htmlspecialchars($service['name']); ?></h4>
                                        <div class="service-stats">
                                            <span class="stat">
                                                <i class="fas fa-peso-sign"></i>
                                                ₱<?php echo number_format($service['total_earned'], 2); ?> total
                                            </span>
                                            <span class="stat">
                                                <i class="fas fa-calendar-check"></i>
                                                <?php echo $service['booking_count']; ?> bookings
                                            </span>
                                            <span class="stat">
                                                <i class="fas fa-chart-line"></i>
                                                ₱<?php echo number_format($service['avg_price'], 2); ?> avg
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-bar" style="font-size: 3rem; color: #4facfe; margin-bottom: 1rem;"></i>
                            <h4>No earnings data yet</h4>
                            <p>Complete some bookings to see your top services here.</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Additional Stats -->
                <div class="glass-container">
                    <div class="section-header">
                        <h3><i class="fas fa-calculator"></i> Business Insights</h3>
                        <p>Key performance indicators</p>
                    </div>

                    <div class="insights-grid">
                        <div class="insight-card">
                            <div class="insight-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="insight-data">
                                <div class="insight-number"><?php echo $total_bookings; ?></div>
                                <div class="insight-label">Completed Jobs</div>
                            </div>
                        </div>
                        <div class="insight-card">
                            <div class="insight-icon">
                                <i class="fas fa-chart-line"></i>
                            </div>
                            <div class="insight-data">
                                <div class="insight-number">₱<?php echo number_format($average_earning, 2); ?></div>
                                <div class="insight-label">Average per Job</div>
                            </div>
                        </div>
                        <div class="insight-card">
                            <div class="insight-icon">
                                <i class="fas fa-cogs"></i>
                            </div>
                            <div class="insight-data">
                                <div class="insight-number"><?php echo count($top_services); ?></div>
                                <div class="insight-label">Active Services</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent Earnings -->
            <div class="glass-container">
                <div class="section-header">
                    <h3><i class="fas fa-history"></i> Recent Earnings</h3>
                    <p>Your latest completed bookings</p>
                </div>

                <?php if (count($completed_bookings) > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Customer</th>
                                    <th>Service</th>
                                    <th>Amount</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $recent_bookings = array_slice($completed_bookings, 0, 10);
                                foreach ($recent_bookings as $booking): 
                                ?>
                                <tr>
                                    <td><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($booking['customer_name']); ?></td>
                                    <td><?php echo htmlspecialchars($booking['service_name']); ?></td>
                                    <td class="amount">₱<?php echo number_format($booking['total_amount'], 2); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-sm">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                            <a href="invoice.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-primary btn-sm">
                                                <i class="fas fa-file-invoice"></i> Invoice
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($completed_bookings) > 10): ?>
                        <div style="text-align: center; margin-top: 1.5rem;">
                            <a href="bookings.php?filter=completed" class="btn btn-primary">
                                <i class="fas fa-list"></i> View All Completed Bookings
                            </a>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-coins" style="font-size: 4rem; color: #f093fb; margin-bottom: 1rem;"></i>
                        <h3>No earnings yet</h3>
                        <p>Complete your first booking to start earning money!</p>
                        <div class="empty-actions">
                            <a href="bookings.php" class="btn btn-primary">
                                <i class="fas fa-calendar-check"></i> Check Bookings
                            </a>
                            <a href="services.php" class="btn btn-secondary">
                                <i class="fas fa-cogs"></i> Manage Services
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
