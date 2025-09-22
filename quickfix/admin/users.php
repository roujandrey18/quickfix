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
    
    if ($action === 'update_status') {
        $user_id = $_POST['user_id'];
        $status = $_POST['status'];
        
        $update_query = "UPDATE users SET status = :status WHERE id = :user_id";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([':status' => $status, ':user_id' => $user_id])) {
            $success = "User status updated successfully!";
        } else {
            $error = "Failed to update user status.";
        }
    }
    
    elseif ($action === 'delete_user') {
        $user_id = $_POST['user_id'];
        
        // Check if user has active bookings
        $booking_check = $db->prepare("SELECT COUNT(*) FROM bookings WHERE (user_id = :user_id OR provider_id = :user_id) AND status IN ('pending', 'confirmed')");
        $booking_check->execute([':user_id' => $user_id]);
        $active_bookings = $booking_check->fetchColumn();
        
        if ($active_bookings > 0) {
            $error = "Cannot delete user with active bookings. Please complete or cancel active bookings first.";
        } else {
            // Delete user's bookings and provider services first
            $db->prepare("DELETE FROM bookings WHERE user_id = :user_id OR provider_id = :user_id")->execute([':user_id' => $user_id]);
            $db->prepare("DELETE FROM provider_services WHERE provider_id = :user_id")->execute([':user_id' => $user_id]);
            
            // Delete user
            $delete_query = "DELETE FROM users WHERE id = :user_id AND id != :admin_id";
            $delete_stmt = $db->prepare($delete_query);
            
            if ($delete_stmt->execute([':user_id' => $user_id, ':admin_id' => $admin_id])) {
                $success = "User deleted successfully!";
            } else {
                $error = "Failed to delete user.";
            }
        }
    }
}

// Get filter parameters
$user_type_filter = $_GET['type'] ?? '';
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = ["id != :admin_id"];
$params = [':admin_id' => $admin_id];

if ($user_type_filter) {
    $where_conditions[] = "user_type = :user_type";
    $params[':user_type'] = $user_type_filter;
}

if ($status_filter) {
    $where_conditions[] = "status = :status";
    $params[':status'] = $status_filter;
}

if ($search) {
    $where_conditions[] = "(full_name LIKE :search OR email LIKE :search OR username LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = "WHERE " . implode(" AND ", $where_conditions);

// Get users with pagination
$page = $_GET['page'] ?? 1;
$per_page = 20;
$offset = ($page - 1) * $per_page;

$users_query = "SELECT *, 
                (SELECT COUNT(*) FROM bookings WHERE user_id = users.id) as user_bookings,
                (SELECT COUNT(*) FROM bookings WHERE provider_id = users.id) as provider_bookings,
                (SELECT COUNT(*) FROM provider_services WHERE provider_id = users.id) as total_services
                FROM users $where_clause 
                ORDER BY created_at DESC 
                LIMIT :limit OFFSET :offset";

$users_stmt = $db->prepare($users_query);
foreach ($params as $key => $value) {
    $users_stmt->bindValue($key, $value);
}
$users_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$users_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$users_stmt->execute();
$users = $users_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM users $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}
$count_stmt->execute();
$total_users = $count_stmt->fetchColumn();
$total_pages = ceil($total_users / $per_page);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - <?php echo SITE_NAME; ?></title>
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
                <li><a href="users.php" class="nav-link active">
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
                        <a href="users.php" class="dropdown-item active">
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
                    <i class="fas fa-users"></i> Manage Users
                </h1>
                <p class="dashboard-subtitle">
                    View and manage all registered users in the system
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

            <!-- Filters and Search -->
            <div class="glass-container">
                <div class="filters-section">
                    <h3><i class="fas fa-filter"></i> Filters & Search</h3>
                    <form method="GET" class="filters-form">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="search">Search Users</label>
                                <div class="search-input-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" 
                                           name="search" 
                                           id="search" 
                                           class="form-control search-input"
                                           placeholder="Search by name, email, or username..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="type">User Type</label>
                                <select name="type" id="type" class="form-control">
                                    <option value="">All Types</option>
                                    <option value="user" <?php echo $user_type_filter === 'user' ? 'selected' : ''; ?>>Customers</option>
                                    <option value="provider" <?php echo $user_type_filter === 'provider' ? 'selected' : ''; ?>>Service Providers</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                    <option value="banned" <?php echo $status_filter === 'banned' ? 'selected' : ''; ?>>Banned</option>
                                </select>
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

            <!-- Users List -->
            <div class="glass-container">
                <div class="users-header">
                    <h3><i class="fas fa-list"></i> Users List</h3>
                    <div class="users-stats">
                        <span class="stats-item">
                            <i class="fas fa-users"></i> Total: <?php echo $total_users; ?>
                        </span>
                    </div>
                </div>

                <?php if (count($users) > 0): ?>
                    <div class="users-grid">
                        <?php foreach ($users as $user_item): ?>
                            <div class="user-card">
                                <div class="user-avatar">
                                    <?php if ($user_item['profile_image'] && $user_item['profile_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $user_item['profile_image'])): ?>
                                        <img src="../uploads/profile_photos/<?php echo htmlspecialchars($user_item['profile_image']); ?>?v=<?php echo time(); ?>" 
                                             alt="Profile" class="avatar-img">
                                    <?php else: ?>
                                        <i class="fas fa-user avatar-icon"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="user-info">
                                    <h4 class="user-name"><?php echo htmlspecialchars($user_item['full_name']); ?></h4>
                                    <p class="user-email"><?php echo htmlspecialchars($user_item['email']); ?></p>
                                    <p class="user-username">@<?php echo htmlspecialchars($user_item['username']); ?></p>
                                    
                                    <div class="user-badges">
                                        <span class="user-type-badge <?php echo $user_item['user_type']; ?>">
                                            <i class="fas fa-<?php echo $user_item['user_type'] === 'provider' ? 'briefcase' : 'user'; ?>"></i>
                                            <?php echo ucfirst($user_item['user_type']); ?>
                                        </span>
                                        <span class="status-badge <?php echo $user_item['status']; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($user_item['status']); ?>
                                        </span>
                                    </div>
                                    
                                    <div class="user-stats">
                                        <?php if ($user_item['user_type'] === 'provider'): ?>
                                            <span class="stat">
                                                <i class="fas fa-cogs"></i> 
                                                <?php echo $user_item['total_services']; ?> Services
                                            </span>
                                            <span class="stat">
                                                <i class="fas fa-calendar-check"></i> 
                                                <?php echo $user_item['provider_bookings']; ?> Bookings
                                            </span>
                                        <?php else: ?>
                                            <span class="stat">
                                                <i class="fas fa-calendar-alt"></i> 
                                                <?php echo $user_item['user_bookings']; ?> Bookings
                                            </span>
                                        <?php endif; ?>
                                        <span class="stat">
                                            <i class="fas fa-calendar"></i> 
                                            Joined <?php echo date('M Y', strtotime($user_item['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="user-actions">
                                    <div class="action-group">
                                        <form method="POST" class="status-form" style="display: inline;">
                                            <input type="hidden" name="action" value="update_status">
                                            <input type="hidden" name="user_id" value="<?php echo $user_item['id']; ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <option value="active" <?php echo $user_item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $user_item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                <option value="banned" <?php echo $user_item['status'] === 'banned' ? 'selected' : ''; ?>>Banned</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <button type="button" 
                                            class="btn btn-danger btn-sm delete-user-btn" 
                                            onclick="confirmDelete(<?php echo $user_item['id']; ?>, '<?php echo htmlspecialchars($user_item['full_name']); ?>')">
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
                                <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $user_type_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $user_type_filter; ?>&status=<?php echo $status_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <h3>No Users Found</h3>
                        <p>No users match your current filters. Try adjusting your search criteria.</p>
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
                <p>Are you sure you want to delete user <strong id="deleteUserName"></strong>?</p>
                <p class="warning-text">
                    <i class="fas fa-warning"></i>
                    This action cannot be undone and will remove all user data including bookings and services.
                </p>
            </div>
            <div class="modal-actions">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_user">
                    <input type="hidden" name="user_id" id="deleteUserId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete User
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
        function confirmDelete(userId, userName) {
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
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
