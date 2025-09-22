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

// Get date range for reports
$start_date = $_GET['start_date'] ?? date('Y-m-01'); // First day of current month
$end_date = $_GET['end_date'] ?? date('Y-m-t'); // Last day of current month

// Overall Statistics
$overall_stats = [];

// Total users
$query = "SELECT COUNT(*) as total, 
          SUM(CASE WHEN user_type = 'user' THEN 1 ELSE 0 END) as customers,
          SUM(CASE WHEN user_type = 'provider' THEN 1 ELSE 0 END) as providers,
          SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_users
          FROM users WHERE user_type != 'admin'";
$stmt = $db->prepare($query);
$stmt->execute();
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Total services
$query = "SELECT COUNT(*) as total_services,
          COUNT(DISTINCT category) as categories,
          SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_services
          FROM services";
$stmt = $db->prepare($query);
$stmt->execute();
$service_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Booking statistics for date range
$query = "SELECT COUNT(*) as total_bookings,
          SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
          SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
          SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
          SUM(total_amount) as total_revenue,
          AVG(total_amount) as avg_booking_value
          FROM bookings 
          WHERE DATE(created_at) BETWEEN :start_date AND :end_date";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$booking_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Top services
$query = "SELECT s.name, s.category, COUNT(b.id) as booking_count, 
          SUM(b.total_amount) as revenue, u.full_name as provider_name
          FROM services s 
          LEFT JOIN provider_services ps ON s.id = ps.service_id
          LEFT JOIN users u ON ps.provider_id = u.id
          LEFT JOIN bookings b ON s.id = b.service_id 
          WHERE DATE(b.created_at) BETWEEN :start_date AND :end_date OR b.created_at IS NULL
          GROUP BY s.id, u.id 
          ORDER BY booking_count DESC, revenue DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$top_services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top providers
$query = "SELECT u.full_name, u.email, COUNT(b.id) as booking_count,
          SUM(b.total_amount) as revenue, AVG(r.rating) as avg_rating,
          COUNT(DISTINCT ps.service_id) as service_count
          FROM users u 
          JOIN provider_services ps ON u.id = ps.provider_id
          LEFT JOIN bookings b ON ps.service_id = b.service_id AND ps.provider_id = b.provider_id
          LEFT JOIN reviews r ON b.id = r.booking_id
          WHERE u.user_type = 'provider' 
          AND (DATE(b.created_at) BETWEEN :start_date AND :end_date OR b.created_at IS NULL)
          GROUP BY u.id 
          ORDER BY booking_count DESC, revenue DESC 
          LIMIT 10";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$top_providers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Revenue by category
$query = "SELECT s.category, COUNT(b.id) as booking_count,
          SUM(b.total_amount) as revenue
          FROM services s 
          LEFT JOIN bookings b ON s.id = b.service_id 
          WHERE DATE(b.created_at) BETWEEN :start_date AND :end_date
          GROUP BY s.category 
          ORDER BY revenue DESC";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$category_revenue = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Daily bookings for chart
$query = "SELECT DATE(created_at) as date, COUNT(*) as bookings,
          SUM(total_amount) as revenue
          FROM bookings 
          WHERE DATE(created_at) BETWEEN :start_date AND :end_date
          GROUP BY DATE(created_at) 
          ORDER BY date";
$stmt = $db->prepare($query);
$stmt->bindParam(':start_date', $start_date);
$stmt->bindParam(':end_date', $end_date);
$stmt->execute();
$daily_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports & Analytics - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
                <li><a href="bookings.php" class="nav-link">
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
                    <i class="fas fa-chart-bar"></i> Reports & Analytics
                </h1>
                <p class="dashboard-subtitle">
                    Comprehensive insights and analytics for your business
                </p>
                <div class="header-actions">
                    <a href="dashboard.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>

            <!-- Date Range Filter -->
            <div class="glass-container">
                <div class="date-filter-section">
                    <h3><i class="fas fa-calendar-alt"></i> Report Period</h3>
                    <form method="GET" class="date-filter-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="start_date">Start Date</label>
                                <input type="date" 
                                       name="start_date" 
                                       id="start_date" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label for="end_date">End Date</label>
                                <input type="date" 
                                       name="end_date" 
                                       id="end_date" 
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                            
                            <div class="form-group">
                                <label>&nbsp;</label>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-sync-alt"></i> Update Report
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Overall Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-users" style="color: #4facfe;"></i>
                        <?php echo $user_stats['total']; ?>
                    </div>
                    <div class="stat-label">Total Users</div>
                    <div class="stat-detail">
                        <?php echo $user_stats['customers']; ?> Customers, <?php echo $user_stats['providers']; ?> Providers
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-cogs" style="color: #f093fb;"></i>
                        <?php echo $service_stats['total_services']; ?>
                    </div>
                    <div class="stat-label">Total Services</div>
                    <div class="stat-detail">
                        <?php echo $service_stats['active_services']; ?> Active, <?php echo $service_stats['categories']; ?> Categories
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-calendar-check" style="color: #56ab2f;"></i>
                        <?php echo $booking_stats['total_bookings'] ?: 0; ?>
                    </div>
                    <div class="stat-label">Period Bookings</div>
                    <div class="stat-detail">
                        <?php echo $booking_stats['completed_bookings'] ?: 0; ?> Completed
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-number">
                        <i class="fas fa-dollar-sign" style="color: #667eea;"></i>
                        ₱<?php echo number_format($booking_stats['total_revenue'] ?: 0, 0); ?>
                    </div>
                    <div class="stat-label">Period Revenue</div>
                    <div class="stat-detail">
                        Avg: ₱<?php echo number_format($booking_stats['avg_booking_value'] ?: 0, 0); ?>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="reports-grid">
                <!-- Daily Bookings Chart -->
                <div class="glass-container chart-container">
                    <h3><i class="fas fa-line-chart"></i> Daily Bookings & Revenue</h3>
                    <div class="chart-wrapper">
                        <canvas id="dailyChart"></canvas>
                    </div>
                </div>

                <!-- Category Revenue Chart -->
                <div class="glass-container chart-container">
                    <h3><i class="fas fa-pie-chart"></i> Revenue by Category</h3>
                    <div class="chart-wrapper">
                        <canvas id="categoryChart"></canvas>
                    </div>
                </div>
            </div>

            <!-- Top Services -->
            <div class="glass-container">
                <h3><i class="fas fa-trophy"></i> Top Performing Services</h3>
                
                <?php if (count($top_services) > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Service Name</th>
                                    <th>Category</th>
                                    <th>Provider</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_services as $index => $service): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                            #<?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($service['name']); ?></td>
                                    <td><?php echo htmlspecialchars($service['category']); ?></td>
                                    <td><?php echo htmlspecialchars($service['provider_name']); ?></td>
                                    <td><?php echo $service['booking_count'] ?: 0; ?></td>
                                    <td>₱<?php echo number_format($service['revenue'] ?: 0, 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <h3>No Service Data</h3>
                        <p>No service bookings found for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Top Providers -->
            <div class="glass-container">
                <h3><i class="fas fa-star"></i> Top Performing Providers</h3>
                
                <?php if (count($top_providers) > 0): ?>
                    <div class="table-container">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Provider Name</th>
                                    <th>Email</th>
                                    <th>Services</th>
                                    <th>Bookings</th>
                                    <th>Revenue</th>
                                    <th>Rating</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($top_providers as $index => $provider): ?>
                                <tr>
                                    <td>
                                        <span class="rank-badge rank-<?php echo min($index + 1, 3); ?>">
                                            #<?php echo $index + 1; ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($provider['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($provider['email']); ?></td>
                                    <td><?php echo $provider['service_count']; ?></td>
                                    <td><?php echo $provider['booking_count'] ?: 0; ?></td>
                                    <td>₱<?php echo number_format($provider['revenue'] ?: 0, 2); ?></td>
                                    <td>
                                        <?php if ($provider['avg_rating']): ?>
                                            <div class="rating-display">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?php echo $i <= round($provider['avg_rating']) ? 'rated' : 'unrated'; ?>"></i>
                                                <?php endfor; ?>
                                                <span><?php echo number_format($provider['avg_rating'], 1); ?></span>
                                            </div>
                                        <?php else: ?>
                                            <span class="no-rating">No ratings</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>No Provider Data</h3>
                        <p>No provider bookings found for the selected period.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
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
        });

        // Daily Chart
        const dailyData = <?php echo json_encode($daily_stats); ?>;
        const dailyLabels = dailyData.map(item => new Date(item.date).toLocaleDateString());
        const dailyBookings = dailyData.map(item => item.bookings);
        const dailyRevenue = dailyData.map(item => item.revenue);

        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [{
                    label: 'Bookings',
                    data: dailyBookings,
                    borderColor: '#4facfe',
                    backgroundColor: 'rgba(79, 172, 254, 0.1)',
                    yAxisID: 'y'
                }, {
                    label: 'Revenue (₱)',
                    data: dailyRevenue,
                    borderColor: '#667eea',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    yAxisID: 'y1'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        type: 'linear',
                        display: true,
                        position: 'left',
                    },
                    y1: {
                        type: 'linear',
                        display: true,
                        position: 'right',
                        grid: {
                            drawOnChartArea: false,
                        },
                    }
                }
            }
        });

        // Category Chart
        const categoryData = <?php echo json_encode($category_revenue); ?>;
        const categoryLabels = categoryData.map(item => item.category);
        const categoryRevenues = categoryData.map(item => item.revenue);

        const categoryCtx = document.getElementById('categoryChart').getContext('2d');
        new Chart(categoryCtx, {
            type: 'doughnut',
            data: {
                labels: categoryLabels,
                datasets: [{
                    data: categoryRevenues,
                    backgroundColor: [
                        '#4facfe',
                        '#f093fb',
                        '#667eea',
                        '#56ab2f',
                        '#f7971e',
                        '#ff416c',
                        '#00f2fe',
                        '#a8e6cf'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    </script>
    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin-theme.js"></script>
</body>
</html>
