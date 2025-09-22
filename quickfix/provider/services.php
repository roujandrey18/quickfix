<?php 
require_once '../config/config.php';
checkAccess(['provider']);

$database = new Database();
$db = $database->getConnection();

$provider_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get provider data including profile image
$user_query = "SELECT * FROM users WHERE id = :provider_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':provider_id', $provider_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Handle quick status toggle from AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'toggle_status') {
    $service_id = (int)$_POST['service_id'];
    
    // Get current status
    $status_query = "SELECT availability FROM provider_services WHERE provider_id = :provider_id AND service_id = :service_id";
    $status_stmt = $db->prepare($status_query);
    $status_stmt->bindParam(':provider_id', $provider_id);
    $status_stmt->bindParam(':service_id', $service_id);
    $status_stmt->execute();
    $current = $status_stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($current) {
        $new_status = $current['availability'] === 'available' ? 'unavailable' : 'available';
        
        $update_query = "UPDATE provider_services SET availability = :status WHERE provider_id = :provider_id AND service_id = :service_id";
        $update_stmt = $db->prepare($update_query);
        $update_stmt->bindParam(':status', $new_status);
        $update_stmt->bindParam(':provider_id', $provider_id);
        $update_stmt->bindParam(':service_id', $service_id);
        
        if ($update_stmt->execute()) {
            echo json_encode(['success' => true, 'new_status' => $new_status]);
        } else {
            echo json_encode(['success' => false]);
        }
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}

// Get filter from URL
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$category_filter = isset($_GET['category']) ? $_GET['category'] : 'all';

// Build query based on filters
$where_clause = "WHERE ps.provider_id = :provider_id";
$params = [':provider_id' => $provider_id];

if ($filter !== 'all') {
    $where_clause .= " AND ps.availability = :availability";
    $params[':availability'] = $filter;
}

if ($category_filter !== 'all') {
    $where_clause .= " AND s.category = :category";
    $params[':category'] = $category_filter;
}

// Get provider's services with booking stats
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
$where_clause
GROUP BY s.id, ps.price, ps.availability
ORDER BY s.name ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$my_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get service counts for filter badges
$count_query = "SELECT 
    ps.availability,
    s.category,
    COUNT(*) as count 
FROM provider_services ps
JOIN services s ON ps.service_id = s.id
WHERE ps.provider_id = :provider_id 
GROUP BY ps.availability, s.category";
$count_stmt = $db->prepare($count_query);
$count_stmt->bindParam(':provider_id', $provider_id);
$count_stmt->execute();
$counts = $count_stmt->fetchAll(PDO::FETCH_ASSOC);

// Process counts
$status_counts = ['available' => 0, 'unavailable' => 0];
$category_counts = [];

foreach ($counts as $count) {
    $status_counts[$count['availability']] += $count['count'];
    if (!isset($category_counts[$count['category']])) {
        $category_counts[$count['category']] = 0;
    }
    $category_counts[$count['category']] += $count['count'];
}

$total_services = array_sum($status_counts);

// Get all categories for filter
$categories = array_keys($category_counts);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Services - <?php echo SITE_NAME; ?></title>
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
                <li><a href="services.php" class="nav-link active">
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
                    <i class="fas fa-cogs"></i> My Services
                </h1>
                <p class="dashboard-subtitle">
                    Manage your service portfolio and track performance
                </p>
                <div class="header-actions">
                    <a href="add_service.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add New Service
                    </a>
                </div>
            </div>

            <?php if (isset($_SESSION['success'])): ?>
                <div class="notification success show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-check-circle"></i> <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['error'])): ?>
                <div class="notification error show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Service Statistics -->
            <div class="stats-grid">
                <a href="?filter=all" class="stat-card clickable <?php echo $filter === 'all' ? 'active' : ''; ?>">
                    <div class="stat-number">
                        <i class="fas fa-cogs" style="color: #667eea;"></i>
                        <?php echo $total_services; ?>
                    </div>
                    <div class="stat-label">Total Services</div>
                    <div class="stat-action">
                        <i class="fas fa-eye"></i> View All
                    </div>
                </a>
                <a href="?filter=available" class="stat-card clickable <?php echo $filter === 'available' ? 'active' : ''; ?>">
                    <div class="stat-number">
                        <i class="fas fa-check-circle" style="color: #56ab2f;"></i>
                        <?php echo $status_counts['available']; ?>
                    </div>
                    <div class="stat-label">Active Services</div>
                    <div class="stat-action">
                        <i class="fas fa-eye"></i> View Active
                    </div>
                </a>
                <a href="?filter=unavailable" class="stat-card clickable <?php echo $filter === 'unavailable' ? 'active' : ''; ?>">
                    <div class="stat-number">
                        <i class="fas fa-pause-circle" style="color: #f093fb;"></i>
                        <?php echo $status_counts['unavailable']; ?>
                    </div>
                    <div class="stat-label">Inactive Services</div>
                    <div class="stat-action">
                        <i class="fas fa-eye"></i> View Inactive
                    </div>
                </a>
                <a href="earnings.php" class="stat-card clickable">
                    <div class="stat-number">
                        <i class="fas fa-peso-sign" style="color: #4facfe;"></i>
                        ₱<?php echo number_format(array_sum(array_column($my_services, 'total_earnings')), 2); ?>
                    </div>
                    <div class="stat-label">Total Earnings</div>
                    <div class="stat-action">
                        <i class="fas fa-chart-line"></i> View Details
                    </div>
                </a>
            </div>

            <!-- Filter Tabs -->
            <?php if (count($categories) > 1): ?>
            <div class="glass-container">
                <div class="filter-tabs">
                    <a href="?filter=<?php echo $filter; ?>&category=all" class="filter-tab <?php echo $category_filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-th"></i> All Categories
                        <span class="tab-count"><?php echo $total_services; ?></span>
                    </a>
                    <?php foreach ($categories as $category): ?>
                        <a href="?filter=<?php echo $filter; ?>&category=<?php echo urlencode($category); ?>" 
                           class="filter-tab <?php echo $category_filter === $category ? 'active' : ''; ?>">
                            <i class="fas fa-tag"></i> <?php echo htmlspecialchars($category); ?>
                            <span class="tab-count"><?php echo $category_counts[$category]; ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Services Grid -->
            <div class="glass-container">
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
                                    <p class="service-description"><?php echo htmlspecialchars($service['description']); ?></p>
                                    
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
                                            <span>₱<?php echo number_format($service['total_earnings'], 2); ?> earned</span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="fas fa-check-circle"></i>
                                            <span><?php echo $service['completed_bookings']; ?> completed</span>
                                        </div>
                                    </div>

                                    <!-- Service Actions -->
                                    <div class="service-actions">
                                        <button onclick="toggleServiceStatus(<?php echo $service['id']; ?>)" 
                                                class="btn btn-sm <?php echo $service['availability'] === 'available' ? 'btn-warning' : 'btn-success'; ?>">
                                            <i class="fas fa-<?php echo $service['availability'] === 'available' ? 'pause' : 'play'; ?>"></i>
                                            <?php echo $service['availability'] === 'available' ? 'Deactivate' : 'Activate'; ?>
                                        </button>
                                        
                                        <a href="edit_service.php?id=<?php echo $service['id']; ?>" class="btn btn-secondary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        
                                        <a href="service_analytics.php?id=<?php echo $service['id']; ?>" class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-chart-bar"></i> Analytics
                                        </a>
                                        
                                        <button onclick="removeService(<?php echo $service['id']; ?>)" 
                                                class="btn btn-danger btn-sm" 
                                                title="Remove service from portfolio">
                                            <i class="fas fa-trash"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-cogs" style="font-size: 4rem; color: #4facfe; margin-bottom: 1rem;"></i>
                        <h3>
                            <?php if ($filter === 'all' && $category_filter === 'all'): ?>
                                No services added yet
                            <?php else: ?>
                                No <?php echo $filter !== 'all' ? $filter : ''; ?> 
                                <?php echo $category_filter !== 'all' ? $category_filter : ''; ?> services found
                            <?php endif; ?>
                        </h3>
                        <p>
                            <?php if ($filter === 'all' && $category_filter === 'all'): ?>
                                Start building your service portfolio to attract customers and grow your business.
                            <?php else: ?>
                                Try adjusting your filters or add more services to your portfolio.
                            <?php endif; ?>
                        </p>
                        <div class="empty-actions">
                            <a href="add_service.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add New Service
                            </a>
                            <?php if ($filter !== 'all' || $category_filter !== 'all'): ?>
                                <a href="services.php" class="btn btn-secondary">
                                    <i class="fas fa-eye"></i> View All Services
                                </a>
                            <?php endif; ?>
                            <a href="../user/services.php" class="btn btn-outline-primary">
                                <i class="fas fa-search"></i> Browse Available Services
                            </a>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        function toggleServiceStatus(serviceId) {
            if (confirm('Are you sure you want to change the status of this service?')) {
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
                        location.reload();
                    } else {
                        alert('Error updating service status. Please try again.');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error updating service status. Please try again.');
                });
            }
        }
        
        function removeService(serviceId) {
            if (confirm('Are you sure you want to remove this service from your portfolio? This action cannot be undone.')) {
                // Redirect to remove service handler
                window.location.href = `remove_service.php?id=${serviceId}`;
            }
        }
        
        // Auto-hide notifications
        setTimeout(() => {
            const notifications = document.querySelectorAll('.notification');
            notifications.forEach(notification => {
                notification.classList.remove('show');
            });
        }, 5000);
    </script>
</body>
</html>
