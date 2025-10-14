<?php 
require_once '../config/config.php';
checkAccess(['admin']);

$database = new Database();
$db = $database->getConnection();

$admin_id = $_SESSION['user_id'];

// Get admin data including profile image
$user_query = "SELECT * FROM users WHERE id = :admin_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':admin_id', $admin_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get statistics
$stats = [
    'users' => 0,
    'providers' => 0,
    'services' => 0,
    'bookings' => 0
];

// Count users
$query = "SELECT COUNT(*) FROM users WHERE user_type = 'user'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['users'] = $stmt->fetchColumn();

// Count providers
$query = "SELECT COUNT(*) FROM users WHERE user_type = 'provider'";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['providers'] = $stmt->fetchColumn();

// Count services
$query = "SELECT COUNT(*) FROM services";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['services'] = $stmt->fetchColumn();

// Count bookings
$query = "SELECT COUNT(*) FROM bookings";
$stmt = $db->prepare($query);
$stmt->execute();
$stats['bookings'] = $stmt->fetchColumn();

// Get recent activities
$query = "SELECT b.*, u1.full_name as user_name, u2.full_name as provider_name, s.name as service_name 
          FROM bookings b 
          JOIN users u1 ON b.user_id = u1.id 
          JOIN users u2 ON b.provider_id = u2.id 
          JOIN services s ON b.service_id = s.id 
          ORDER BY b.created_at DESC LIMIT 10";
$stmt = $db->prepare($query);
$stmt->execute();
$recent_bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
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
                <li><a href="dashboard.php" class="nav-link active">
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

    <div class="dashboard-container">
        <div class="dashboard-header">
            <h1 class="dashboard-title">
                <i class="fas fa-tachometer-alt"></i> Admin Dashboard
            </h1>
            <p class="dashboard-subtitle">
                Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!
            </p>
        </div>

        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-users" style="color: #4facfe;"></i>
                    <?php echo $stats['users']; ?>
                </div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-briefcase" style="color: #f093fb;"></i>
                    <?php echo $stats['providers']; ?>
                </div>
                <div class="stat-label">Service Providers</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-cogs" style="color: #667eea;"></i>
                    <?php echo $stats['services']; ?>
                </div>
                <div class="stat-label">Total Services</div>
            </div>
            <div class="stat-card">
                <div class="stat-number">
                    <i class="fas fa-calendar-check" style="color: #56ab2f;"></i>
                    <?php echo $stats['bookings']; ?>
                </div>
                <div class="stat-label">Total Bookings</div>
            </div>
        </div>

        <!-- Recent Bookings -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">
                <i class="fas fa-clock"></i> Recent Bookings
            </h2>
            
            <?php if (count($recent_bookings) > 0): ?>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Provider</th>
                            <th>Service</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_bookings as $booking): ?>
                        <tr>
                            <td>#<?php echo $booking['id']; ?></td>
                            <td><?php echo htmlspecialchars($booking['user_name']); ?></td>
                            <td><?php echo htmlspecialchars($booking['provider_name']); ?></td>
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
                <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 1rem;"></i>
                <p>No bookings found</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Quick Actions -->
        <div class="card">
            <h2 style="margin-bottom: 1.5rem; color: #2c3e50;">
                <i class="fas fa-bolt"></i> Quick Actions
            </h2>
            <div class="stats-grid">
                <a href="services.php?action=add" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-number">
                        <i class="fas fa-plus" style="color: #4facfe;"></i>
                    </div>
                    <div class="stat-label">Add Service</div>
                </a>
                <a href="users.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-number">
                        <i class="fas fa-user-cog" style="color: #f093fb;"></i>
                    </div>
                    <div class="stat-label">Manage Users</div>
                </a>
                <a href="bookings.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-number">
                        <i class="fas fa-calendar-alt" style="color: #667eea;"></i>
                    </div>
                    <div class="stat-label">View All Bookings</div>
                </a>
                <a href="reports.php" class="stat-card" style="text-decoration: none; cursor: pointer;">
                    <div class="stat-number">
                        <i class="fas fa-chart-bar" style="color: #56ab2f;"></i>
                    </div>
                    <div class="stat-label">Generate Reports</div>
                </a>
            </div>
        </div>
    </div>

    <?php include '../includes/footer.php'; ?>

    <script src="../assets/js/main.js"></script>
    <script src="../assets/js/admin-theme.js"></script>
</body>
</html>