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

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build query with filters
$where_conditions = ["b.user_id = :user_id"];
$params = [':user_id' => $user_id];

if (!empty($status_filter)) {
    $where_conditions[] = "b.status = :status";
    $params[':status'] = $status_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "b.booking_date >= :date_from";
    $params[':date_from'] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "b.booking_date <= :date_to";
    $params[':date_to'] = $date_to;
}

// Get bookings with related data
$query = "SELECT b.*, s.name as service_name, s.category as service_category,
                 u.full_name as provider_name, u.phone as provider_phone,
                 r.rating, r.comment as review_comment
          FROM bookings b 
          JOIN services s ON b.service_id = s.id 
          JOIN users u ON b.provider_id = u.id 
          LEFT JOIN reviews r ON b.id = r.booking_id
          WHERE " . implode(' AND ', $where_conditions) . "
          ORDER BY b.created_at DESC";

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get booking statistics
$stats_query = "SELECT 
                    COUNT(*) as total_bookings,
                    COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_bookings,
                    COUNT(CASE WHEN status = 'confirmed' THEN 1 END) as confirmed_bookings,
                    COUNT(CASE WHEN status = 'completed' THEN 1 END) as completed_bookings,
                    COUNT(CASE WHEN status = 'cancelled' THEN 1 END) as cancelled_bookings,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN total_amount END), 0) as total_spent
                FROM bookings 
                WHERE user_id = :user_id";
$stmt = $db->prepare($stats_query);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
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
    <nav class="navbar navbar-transparent" id="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-wrench"></i> QuickFix
            </a>
            <ul class="nav-menu" id="navMenu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-search"></i> Browse Services
                </a></li>
                <li><a href="bookings.php" class="nav-link active">
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
                        <a href="bookings.php" class="dropdown-item active">
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

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-calendar-alt"></i> My Bookings
                </h1>
                <p class="dashboard-subtitle">
                    Manage and track all your service appointments
                </p>
            </div>

            <!-- Booking Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-calendar-check" style="color: #4facfe;"></i>
                        <?php echo $stats['total_bookings']; ?>
                    </div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-clock" style="color: #f7971e;"></i>
                        <?php echo $stats['pending_bookings']; ?>
                    </div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-check-circle" style="color: #56ab2f;"></i>
                        <?php echo $stats['completed_bookings']; ?>
                    </div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-dollar-sign" style="color: #667eea;"></i>
                        ₱<?php echo number_format($stats['total_spent'], 2); ?>
                    </div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>

            <!-- Filters -->
            <div class="bookings-filters-container">
                <form class="bookings-filters" method="GET">
                    <div class="filters-row">
                        <div class="filter-group">
                            <label class="filter-label">Status</label>
                            <select name="status" class="filter-select">
                                <option value="">All Status</option>
                                <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                <option value="in_progress" <?php echo $status_filter === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">From Date</label>
                            <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" class="filter-input">
                        </div>
                        
                        <div class="filter-group">
                            <label class="filter-label">To Date</label>
                            <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" class="filter-input">
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filter
                            </button>
                            <a href="bookings.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> Clear
                            </a>
                        </div>
                    </div>
                </form>
            </div>

            <!-- Bookings List -->
            <?php if (count($bookings) > 0): ?>
            <div class="bookings-grid">
                <?php foreach ($bookings as $booking): ?>
                <div class="booking-card">
                    <div class="booking-header">
                        <div class="booking-service">
                            <div class="service-icon">
                                <i class="fas fa-<?php 
                                    echo match(strtolower($booking['service_category'])) {
                                        'cleaning' => 'broom',
                                        'maintenance' => 'wrench',
                                        'outdoor' => 'leaf',
                                        'electrical' => 'bolt',
                                        'plumbing' => 'water',
                                        default => 'tools'
                                    };
                                ?>"></i>
                            </div>
                            <div class="service-details">
                                <h3 class="service-name"><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                                <p class="service-category"><?php echo htmlspecialchars($booking['service_category']); ?></p>
                            </div>
                        </div>
                        
                        <div class="booking-status">
                            <span class="status-badge status-<?php echo $booking['status']; ?>">
                                <i class="fas fa-<?php 
                                    echo match($booking['status']) {
                                        'pending' => 'clock',
                                        'confirmed' => 'check-circle',
                                        'in_progress' => 'spinner',
                                        'completed' => 'check-double',
                                        'cancelled' => 'times-circle',
                                        default => 'question-circle'
                                    };
                                ?>"></i>
                                <?php echo ucfirst(str_replace('_', ' ', $booking['status'])); ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="booking-body">
                        <div class="booking-info-grid">
                            <div class="info-item">
                                <i class="fas fa-user-tie"></i>
                                <div>
                                    <label>Provider</label>
                                    <span><?php echo htmlspecialchars($booking['provider_name']); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-calendar"></i>
                                <div>
                                    <label>Date</label>
                                    <span><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-clock"></i>
                                <div>
                                    <label>Time</label>
                                    <span><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="fas fa-dollar-sign"></i>
                                <div>
                                    <label>Amount</label>
                                    <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (!empty($booking['notes'])): ?>
                        <div class="booking-notes">
                            <i class="fas fa-sticky-note"></i>
                            <span><?php echo htmlspecialchars($booking['notes']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="booking-footer">
                        <div class="booking-actions">
                            <?php if ($booking['status'] === 'pending'): ?>
                            <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn btn-danger btn-sm">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <?php endif; ?>
                            
                            <?php if ($booking['status'] === 'completed' && !$booking['rating']): ?>
                            <a href="review.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary btn-sm">
                                <i class="fas fa-star"></i> Leave Review
                            </a>
                            <?php endif; ?>
                            
                            <a href="tel:<?php echo htmlspecialchars($booking['provider_phone']); ?>" class="btn btn-secondary btn-sm">
                                <i class="fas fa-phone"></i> Contact
                            </a>
                            
                            <button onclick="showBookingDetails(<?php echo $booking['id']; ?>)" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eye"></i> Details
                            </button>
                        </div>
                        
                        <div class="booking-meta">
                            <small>Booked on <?php echo date('M j, Y', strtotime($booking['created_at'])); ?></small>
                        </div>
                    </div>
                    
                    <?php if ($booking['rating']): ?>
                    <div class="booking-review">
                        <div class="review-header">
                            <span class="review-label">Your Review:</span>
                            <div class="review-rating">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star <?php echo $i <= $booking['rating'] ? 'active' : ''; ?>"></i>
                                <?php endfor; ?>
                            </div>
                        </div>
                        <?php if (!empty($booking['review_comment'])): ?>
                        <p class="review-comment"><?php echo htmlspecialchars($booking['review_comment']); ?></p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-calendar-times"></i>
                </div>
                <h3>No bookings found</h3>
                <p>You haven't made any bookings yet, or no bookings match your current filters.</p>
                <div class="no-results-actions">
                    <a href="services.php" class="btn btn-primary">
                        <i class="fas fa-search"></i> Browse Services
                    </a>
                    <?php if (!empty($status_filter) || !empty($date_from) || !empty($date_to)): ?>
                    <a href="bookings.php" class="btn btn-secondary">
                        <i class="fas fa-times"></i> Clear Filters
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking Details Modal -->
    <div id="bookingModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-info-circle"></i> Booking Details</h3>
                <button class="modal-close" onclick="closeBookingModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="bookingDetails">
                <!-- Details will be loaded here -->
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Initialize navbar transparency
        window.addEventListener('scroll', function() {
            const navbar = document.getElementById('navbar');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });

        // Cancel booking function
        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({ booking_id: bookingId })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Booking cancelled successfully', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification('Failed to cancel booking', 'error');
                    }
                });
            }
        }

        // Show booking details modal
        function showBookingDetails(bookingId) {
            const modal = document.getElementById('bookingModal');
            const details = document.getElementById('bookingDetails');
            
            modal.style.display = 'flex';
            details.innerHTML = '<div class="loading-spinner"><div class="loading"></div><p>Loading...</p></div>';
            
            fetch(`get_booking_details.php?id=${bookingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        details.innerHTML = data.html;
                    } else {
                        details.innerHTML = '<p>Error loading booking details.</p>';
                    }
                });
        }

        function closeBookingModal() {
            document.getElementById('bookingModal').style.display = 'none';
        }

        // Auto-submit filters
        document.querySelectorAll('.filter-select, .filter-input').forEach(input => {
            input.addEventListener('change', function() {
                this.form.submit();
            });
        });
    </script>
</body>
</html>