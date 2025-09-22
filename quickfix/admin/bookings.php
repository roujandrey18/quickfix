<?php 
require_once '../config/config.php';
checkAccess(['admin']);

$database = new Database();
$db = $database->getConnection();
$admin_id = $_SESSION['user_id'];

// Get admin data
$user_query = "SELECT * FROM users WHERE id = :admin_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':admin_id', $admin_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

$success = '';
$error = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'update_booking_status') {
        $booking_id = $_POST['booking_id'];
        $status = $_POST['status'];
        
        $update_query = "UPDATE bookings SET status = :status WHERE id = :booking_id";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([':status' => $status, ':booking_id' => $booking_id])) {
            $success = "Booking status updated successfully!";
        } else {
            $error = "Failed to update booking status.";
        }
    }
    
    elseif ($action === 'delete_booking') {
        $booking_id = $_POST['booking_id'];
        
        $delete_query = "DELETE FROM bookings WHERE id = :booking_id";
        $delete_stmt = $db->prepare($delete_query);
        
        if ($delete_stmt->execute([':booking_id' => $booking_id])) {
            $success = "Booking deleted successfully!";
        } else {
            $error = "Failed to delete booking.";
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$date_filter = $_GET['date'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "b.status = :status";
    $params[':status'] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(b.booking_date) = :date";
    $params[':date'] = $date_filter;
}

if ($search) {
    $where_conditions[] = "(u1.full_name LIKE :search OR u2.full_name LIKE :search OR s.name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get bookings with pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$bookings_query = "SELECT b.*, 
                   u1.full_name as customer_name, u1.email as customer_email, u1.profile_image as customer_image,
                   u2.full_name as provider_name, u2.email as provider_email, u2.profile_image as provider_image,
                   s.name as service_name, s.category as service_category,
                   r.rating as rating
                   FROM bookings b 
                   JOIN users u1 ON b.user_id = u1.id 
                   JOIN users u2 ON b.provider_id = u2.id 
                   JOIN services s ON b.service_id = s.id 
                   LEFT JOIN reviews r ON b.id = r.booking_id
                   $where_clause 
                   ORDER BY b.created_at DESC 
                   LIMIT :limit OFFSET :offset";

$bookings_stmt = $db->prepare($bookings_query);
foreach ($params as $key => $value) {
    $bookings_stmt->bindValue($key, $value);
}
$bookings_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$bookings_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$bookings_stmt->execute();
$bookings = $bookings_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM bookings b 
                JOIN users u1 ON b.user_id = u1.id 
                JOIN users u2 ON b.provider_id = u2.id 
                JOIN services s ON b.service_id = s.id 
                LEFT JOIN reviews r ON b.id = r.booking_id
                $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_bookings = $count_stmt->fetchColumn();
$total_pages = ceil($total_bookings / $per_page);

// Get booking statistics
$stats_query = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
    SUM(total_amount) as total_revenue
    FROM bookings";
$stats_stmt = $db->prepare($stats_query);
$stats_stmt->execute();
$booking_stats = $stats_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Bookings - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="admin">
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-wrench"></i> QuickFix Admin
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i> Users
                </a></li>
                <li><a href="services.php" class="nav-link">
                    <i class="fas fa-cogs"></i> Services
                </a></li>
                <li><a href="bookings.php" class="nav-link active">
                    <i class="fas fa-calendar-alt"></i> Bookings
                </a></li>
                
                <!-- User Avatar Dropdown -->
                <li class="nav-dropdown user-dropdown">
                    <a href="#" class="nav-link dropdown-toggle user-profile-link">
                        <div class="nav-avatar">
                            <?php if ($user['profile_image'] && $user['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user['profile_image'])): ?>
                                <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user['profile_image']); ?>?v=<?php echo time(); ?>" 
                                     alt="Profile" class="nav-avatar-img">
                            <?php else: ?>
                                <i class="fas fa-user-shield nav-avatar-icon"></i>
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
                                    <i class="fas fa-user-shield dropdown-avatar-icon"></i>
                                <?php endif; ?>
                                <div class="user-details">
                                    <div class="user-name-full"><?php echo htmlspecialchars($user['full_name']); ?></div>
                                    <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                </div>
                            </div>
                        </div>
                        <div class="dropdown-divider"></div>
                        <a href="profile.php" class="dropdown-item">
                            <i class="fas fa-user-cog"></i> Admin Profile
                        </a>
                        <a href="users.php" class="dropdown-item">
                            <i class="fas fa-users-cog"></i> Manage Users
                        </a>
                        <a href="settings.php" class="dropdown-item">
                            <i class="fas fa-cogs"></i> System Settings
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
                    <i class="fas fa-calendar-alt"></i> Manage Bookings
                </h1>
                <p class="dashboard-subtitle">
                    View and manage all booking transactions
                </p>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <?php if ($success): ?>
                <div class="notification success show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="notification error show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Booking Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-calendar-alt" style="color: #4facfe;"></i>
                        <?php echo $booking_stats['total']; ?>
                    </div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-clock" style="color: #f7971e;"></i>
                        <?php echo $booking_stats['pending']; ?>
                    </div>
                    <div class="stat-label">Pending</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-check-circle" style="color: #56ab2f;"></i>
                        <?php echo $booking_stats['completed']; ?>
                    </div>
                    <div class="stat-label">Completed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-dollar-sign" style="color: #667eea;"></i>
                        ₱<?php echo number_format($booking_stats['total_revenue'], 0); ?>
                    </div>
                    <div class="stat-label">Total Revenue</div>
                </div>
            </div>

            <!-- Filters and Search -->
            <div class="glass-container">
                <div class="filters-section">
                    <h3><i class="fas fa-filter"></i> Filters & Search</h3>
                    <form method="GET" class="filters-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="search">Search Bookings</label>
                                <div class="search-input-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" 
                                           name="search" 
                                           id="search" 
                                           class="form-control search-input"
                                           placeholder="Search by customer, provider, or service..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="pending" <?php echo $status_filter === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="confirmed" <?php echo $status_filter === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                    <option value="completed" <?php echo $status_filter === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="cancelled" <?php echo $status_filter === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="date">Booking Date</label>
                                <input type="date" 
                                       name="date" 
                                       id="date" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($date_filter); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Apply Filters
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Bookings List -->
            <div class="glass-container">
                <div class="bookings-header">
                    <h3><i class="fas fa-list"></i> Bookings List</h3>
                    <div class="bookings-stats">
                        <span class="stats-item">
                            <i class="fas fa-calendar-alt"></i> Total: <?php echo $total_bookings; ?>
                        </span>
                    </div>
                </div>

                <?php if (count($bookings) > 0): ?>
                    <div class="bookings-list">
                        <?php foreach ($bookings as $booking): ?>
                            <div class="booking-card">
                                <div class="booking-header">
                                    <div class="booking-id">
                                        <span class="id-label">Booking #<?php echo $booking['id']; ?></span>
                                        <span class="booking-date">
                                            <i class="fas fa-calendar"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($booking['booking_date'])); ?>
                                        </span>
                                    </div>
                                    <div class="booking-status">
                                        <span class="status-badge <?php echo $booking['status']; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="booking-content">
                                    <div class="booking-service">
                                        <h4 class="service-name">
                                            <i class="fas fa-cog"></i>
                                            <?php echo htmlspecialchars($booking['service_name']); ?>
                                        </h4>
                                        <p class="service-category"><?php echo htmlspecialchars($booking['service_category']); ?></p>
                                    </div>
                                    
                                    <div class="booking-parties">
                                        <div class="party customer">
                                            <div class="party-avatar">
                                                <?php if ($booking['customer_image'] && $booking['customer_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $booking['customer_image'])): ?>
                                                    <img src="../uploads/profile_photos/<?php echo htmlspecialchars($booking['customer_image']); ?>?v=<?php echo time(); ?>" 
                                                         alt="Customer" class="party-img">
                                                <?php else: ?>
                                                    <i class="fas fa-user party-icon"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="party-info">
                                                <span class="party-name"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                                                <span class="party-label">Customer</span>
                                                <span class="party-email"><?php echo htmlspecialchars($booking['customer_email']); ?></span>
                                            </div>
                                        </div>
                                        
                                        <div class="arrow">
                                            <i class="fas fa-arrow-right"></i>
                                        </div>
                                        
                                        <div class="party provider">
                                            <div class="party-avatar">
                                                <?php if ($booking['provider_image'] && $booking['provider_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $booking['provider_image'])): ?>
                                                    <img src="../uploads/profile_photos/<?php echo htmlspecialchars($booking['provider_image']); ?>?v=<?php echo time(); ?>" 
                                                         alt="Provider" class="party-img">
                                                <?php else: ?>
                                                    <i class="fas fa-briefcase party-icon"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="party-info">
                                                <span class="party-name"><?php echo htmlspecialchars($booking['provider_name']); ?></span>
                                                <span class="party-label">Service Provider</span>
                                                <span class="party-email"><?php echo htmlspecialchars($booking['provider_email']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="booking-details">
                                        <div class="detail-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span class="detail-label">Amount:</span>
                                            <span class="detail-value">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                                        </div>
                                        
                                        <?php if ($booking['notes']): ?>
                                            <div class="detail-item full-width">
                                                <i class="fas fa-sticky-note"></i>
                                                <span class="detail-label">Notes:</span>
                                                <span class="detail-value"><?php echo htmlspecialchars($booking['notes']); ?></span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div class="detail-item">
                                            <i class="fas fa-clock"></i>
                                            <span class="detail-label">Created:</span>
                                            <span class="detail-value"><?php echo date('M j, Y g:i A', strtotime($booking['created_at'])); ?></span>
                                        </div>
                                        
                                        <?php if (isset($booking['rating']) && $booking['rating'] > 0): ?>
                                            <div class="detail-item">
                                                <i class="fas fa-star"></i>
                                                <span class="detail-label">Rating:</span>
                                                <span class="detail-value">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fas fa-star <?php echo $i <= $booking['rating'] ? 'rated' : 'unrated'; ?>"></i>
                                                    <?php endfor; ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="booking-actions">
                                    <form method="POST" class="status-form" style="display: inline;">
                                        <input type="hidden" name="action" value="update_booking_status">
                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                        <select name="status" class="status-select" onchange="this.form.submit()">
                                            <option value="pending" <?php echo $booking['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                            <option value="confirmed" <?php echo $booking['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                            <option value="completed" <?php echo $booking['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                            <option value="cancelled" <?php echo $booking['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                        </select>
                                    </form>
                                    
                                    <button type="button" 
                                            class="btn btn-danger btn-sm delete-booking-btn" 
                                            onclick="confirmDelete(<?php echo $booking['id']; ?>, '<?php echo htmlspecialchars($booking['service_name']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <div class="pagination">
                            <?php if ($page > 1): ?>
                                <a href="?page=<?php echo $page - 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&status=<?php echo $status_filter; ?>&date=<?php echo $date_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-calendar-alt"></i>
                        </div>
                        <h3>No Bookings Found</h3>
                        <p>No bookings match your current filters. Try adjusting your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal" id="deleteModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-exclamation-triangle"></i> Confirm Deletion</h3>
                <button type="button" class="modal-close" onclick="closeDeleteModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete booking for <strong id="deleteBookingName"></strong>?</p>
                <p class="warning-text">
                    <i class="fas fa-warning"></i>
                    This action cannot be undone and will permanently remove the booking record.
                </p>
            </div>
            <div class="modal-actions">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_booking">
                    <input type="hidden" name="booking_id" id="deleteBookingId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Booking
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Delete confirmation
        function confirmDelete(bookingId, serviceName) {
            document.getElementById('deleteBookingId').value = bookingId;
            document.getElementById('deleteBookingName').textContent = serviceName;
            document.getElementById('deleteModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Mobile menu functionality
        document.getElementById('mobileMenuBtn')?.addEventListener('click', function() {
            document.querySelector('.nav-menu').classList.toggle('active');
        });

        // Dropdown functionality
        document.querySelectorAll('.dropdown-toggle').forEach(toggle => {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                const dropdown = this.parentElement;
                dropdown.classList.toggle('active');
            });
        });

        // Close dropdowns when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.nav-dropdown')) {
                document.querySelectorAll('.nav-dropdown').forEach(dropdown => {
                    dropdown.classList.remove('active');
                });
            }
            
            if (e.target.classList.contains('modal')) {
                closeDeleteModal();
            }
        });

        // Auto-hide success/error messages
        setTimeout(function() {
            const notifications = document.querySelectorAll('.notification.show');
            notifications.forEach(notification => {
                notification.classList.remove('show');
            });
        }, 5000);
    </script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin-theme.js"></script>
</body>
</html>
