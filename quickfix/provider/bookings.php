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

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';

// Build query based on filter
$where_clause = "WHERE b.provider_id = :provider_id";
$params = [':provider_id' => $provider_id];

if ($filter !== 'all') {
    $where_clause .= " AND b.status = :status";
    $params[':status'] = $filter;
}

// Get provider's bookings
$query = "SELECT b.*, s.name as service_name, u.full_name as user_name, u.phone, u.email 
          FROM bookings b 
          JOIN services s ON b.service_id = s.id 
          JOIN users u ON b.user_id = u.id 
          $where_clause
          ORDER BY 
            CASE 
                WHEN b.status = 'pending' THEN 1
                WHEN b.status = 'confirmed' THEN 2
                WHEN b.status = 'completed' THEN 3
                WHEN b.status = 'cancelled' THEN 4
                ELSE 5
            END,
            b.booking_date DESC, b.booking_time DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking counts for filter badges
$count_query = "SELECT status, COUNT(*) as count 
                FROM bookings 
                WHERE provider_id = :provider_id 
                GROUP BY status";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':provider_id', $provider_id);
$count_stmt->execute();
$counts = $count_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Set default counts to 0 if not exists
$status_counts = [
    'pending' => $counts['pending'] ?? 0,
    'confirmed' => $counts['confirmed'] ?? 0,
    'completed' => $counts['completed'] ?? 0,
    'cancelled' => $counts['cancelled'] ?? 0
];
$total_count = array_sum($status_counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - <?php echo SITE_NAME; ?></title>
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
                <li><a href="bookings.php" class="nav-link active">
                    <i class="fas fa-calendar-check"></i> Bookings
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-cogs"></i> My Services
                </a></li>
                <li><a href="add_service.php" class="nav-link">
                    <i class="fas fa-plus-circle"></i> Add Service
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

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-calendar-check"></i> My Bookings
                </h1>
                <p class="dashboard-subtitle">
                    Manage your customer bookings and service requests
                </p>
            </div>

            <!-- Filter Tabs -->
            <div class="glass-container">
                <div class="filter-tabs">
                    <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i> All Bookings
                        <span class="tab-count"><?php echo $total_count; ?></span>
                    </a>
                    <a href="?filter=pending" class="filter-tab <?php echo $filter === 'pending' ? 'active' : ''; ?>">
                        <i class="fas fa-clock"></i> Pending
                        <?php if ($status_counts['pending'] > 0): ?>
                            <span class="tab-count pending"><?php echo $status_counts['pending']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=confirmed" class="filter-tab <?php echo $filter === 'confirmed' ? 'active' : ''; ?>">
                        <i class="fas fa-check"></i> Confirmed
                        <?php if ($status_counts['confirmed'] > 0): ?>
                            <span class="tab-count confirmed"><?php echo $status_counts['confirmed']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=completed" class="filter-tab <?php echo $filter === 'completed' ? 'active' : ''; ?>">
                        <i class="fas fa-check-circle"></i> Completed
                        <?php if ($status_counts['completed'] > 0): ?>
                            <span class="tab-count completed"><?php echo $status_counts['completed']; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="?filter=cancelled" class="filter-tab <?php echo $filter === 'cancelled' ? 'active' : ''; ?>">
                        <i class="fas fa-times-circle"></i> Cancelled
                        <?php if ($status_counts['cancelled'] > 0): ?>
                            <span class="tab-count cancelled"><?php echo $status_counts['cancelled']; ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <!-- Bookings List -->
            <div class="glass-container">
                <?php if (count($bookings) > 0): ?>
                    <div class="bookings-list">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-card status-<?php echo $booking['status']; ?>">
                                <div class="booking-header">
                                    <div class="booking-info">
                                        <h3 class="customer-name">
                                            <i class="fas fa-user"></i>
                                            <?php echo htmlspecialchars($booking['user_name']); ?>
                                        </h3>
                                        <div class="booking-service">
                                            <i class="fas fa-tools"></i>
                                            <?php echo htmlspecialchars($booking['service_name']); ?>
                                        </div>
                                    </div>
                                    <div class="booking-status">
                                        <span class="status-badge status-<?php echo $booking['status']; ?>">
                                            <?php 
                                            $status_icons = [
                                                'pending' => 'fas fa-clock',
                                                'confirmed' => 'fas fa-check',
                                                'completed' => 'fas fa-check-circle',
                                                'cancelled' => 'fas fa-times-circle'
                                            ];
                                            ?>
                                            <i class="<?php echo $status_icons[$booking['status']]; ?>"></i>
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="booking-details">
                                    <div class="booking-detail">
                                        <i class="fas fa-calendar"></i>
                                        <span><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                                    </div>
                                    <div class="booking-detail">
                                        <i class="fas fa-clock"></i>
                                        <span><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                                    </div>
                                    <div class="booking-detail">
                                        <i class="fas fa-peso-sign"></i>
                                        <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                                    </div>
                                    <div class="booking-detail">
                                        <i class="fas fa-phone"></i>
                                        <span><?php echo htmlspecialchars($booking['phone']); ?></span>
                                    </div>
                                </div>

                                <?php if (!empty($booking['notes'])): ?>
                                    <div class="booking-notes">
                                        <i class="fas fa-sticky-note"></i>
                                        <strong>Customer Notes:</strong> <?php echo htmlspecialchars($booking['notes']); ?>
                                    </div>
                                <?php endif; ?>

                                <div class="booking-actions">
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <a href="update_booking.php?id=<?php echo $booking['id']; ?>&status=confirmed" 
                                           class="btn btn-primary btn-sm"
                                           onclick="return confirm('Accept this booking request?')">
                                            <i class="fas fa-check"></i> Accept
                                        </a>
                                        <a href="update_booking.php?id=<?php echo $booking['id']; ?>&status=cancelled" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Decline this booking request?')">
                                            <i class="fas fa-times"></i> Decline
                                        </a>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <a href="update_booking.php?id=<?php echo $booking['id']; ?>&status=completed" 
                                           class="btn btn-success btn-sm"
                                           onclick="return confirm('Mark this booking as completed?')">
                                            <i class="fas fa-check-circle"></i> Mark Complete
                                        </a>
                                        <a href="update_booking.php?id=<?php echo $booking['id']; ?>&status=cancelled" 
                                           class="btn btn-danger btn-sm"
                                           onclick="return confirm('Cancel this booking?')">
                                            <i class="fas fa-times"></i> Cancel
                                        </a>
                                    <?php endif; ?>
                                    
                                    <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="btn btn-secondary btn-sm">
                                        <i class="fas fa-eye"></i> View Details
                                    </a>
                                    
                                    <?php if ($booking['status'] === 'completed'): ?>
                                        <a href="invoice.php?id=<?php echo $booking['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-file-invoice"></i> Invoice
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-calendar-times" style="font-size: 4rem; color: #4facfe; margin-bottom: 1rem;"></i>
                        <h3>No <?php echo $filter === 'all' ? '' : $filter; ?> bookings found</h3>
                        <p>
                            <?php if ($filter === 'all'): ?>
                                You don't have any bookings yet. Customers will find and book your services soon!
                            <?php else: ?>
                                No <?php echo $filter; ?> bookings at the moment.
                            <?php endif; ?>
                        </p>
                        <div class="empty-actions">
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-home"></i> Back to Dashboard
                            </a>
                            <?php if ($filter !== 'all'): ?>
                                <a href="bookings.php" class="btn btn-secondary">
                                    <i class="fas fa-list"></i> View All Bookings
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
</body>
</html>
