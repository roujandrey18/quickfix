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
    
    if ($action === 'add_service') {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $category = trim($_POST['category']);
        $base_price = floatval($_POST['base_price']);
        
        if (empty($name) || empty($category)) {
            $error = "Service name and category are required.";
        } elseif ($base_price < 0) {
            $error = "Base price must be a positive number.";
        } else {
            $insert_query = "INSERT INTO services (name, description, category, base_price, status, created_at) 
                            VALUES (:name, :description, :category, :base_price, 'active', NOW())";
            $insert_stmt = $db->prepare($insert_query);
            
            if ($insert_stmt->execute([
                ':name' => $name,
                ':description' => $description,
                ':category' => $category,
                ':base_price' => $base_price
            ])) {
                $success = "New service added successfully!";
            } else {
                $error = "Failed to add service.";
            }
        }
    }
    
    elseif ($action === 'update_service_status') {
        $service_id = $_POST['service_id'];
        $status = $_POST['status'];
        
        $update_query = "UPDATE services SET status = :status WHERE id = :service_id";
        $update_stmt = $db->prepare($update_query);
        
        if ($update_stmt->execute([':status' => $status, ':service_id' => $service_id])) {
            $success = "Service status updated successfully!";
        } else {
            $error = "Failed to update service status.";
        }
    }
    
    elseif ($action === 'delete_service') {
        $service_id = $_POST['service_id'];
        
        // Check if service has active bookings
        $booking_check = $db->prepare("SELECT COUNT(*) FROM bookings WHERE service_id = :service_id AND status IN ('pending', 'confirmed')");
        $booking_check->execute([':service_id' => $service_id]);
        $active_bookings = $booking_check->fetchColumn();
        
        if ($active_bookings > 0) {
            $error = "Cannot delete service with active bookings. Please complete or cancel active bookings first.";
        } else {
            // Delete service bookings first
            $db->prepare("DELETE FROM bookings WHERE service_id = :service_id")->execute([':service_id' => $service_id]);
            
            // Delete service
            $delete_query = "DELETE FROM services WHERE id = :service_id";
            $delete_stmt = $db->prepare($delete_query);
            
            if ($delete_stmt->execute([':service_id' => $service_id])) {
                $success = "Service deleted successfully!";
            } else {
                $error = "Failed to delete service.";
            }
        }
    }
}

// Get filter parameters
$category_filter = $_GET['category'] ?? '';
$status_filter = $_GET['status'] ?? '';
$provider_filter = $_GET['provider'] ?? '';
$search = $_GET['search'] ?? '';

// Build query with filters
$where_conditions = [];
$params = [];

if ($category_filter) {
    $where_conditions[] = "s.category = :category";
    $params[':category'] = $category_filter;
}

if ($status_filter) {
    $where_conditions[] = "s.status = :status";
    $params[':status'] = $status_filter;
}

if ($provider_filter) {
    $where_conditions[] = "ps.provider_id = :provider_id";
    $params[':provider_id'] = $provider_filter;
}

if ($search) {
    $where_conditions[] = "(s.name LIKE :search OR s.description LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = "%$search%";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get services with pagination
$page = $_GET['page'] ?? 1;
$per_page = 12;
$offset = ($page - 1) * $per_page;

$services_query = "SELECT s.*, u.full_name as provider_name, u.profile_image as provider_image,
                   (SELECT COUNT(*) FROM bookings WHERE service_id = s.id) as total_bookings,
                   (SELECT COUNT(*) FROM bookings WHERE service_id = s.id AND status = 'completed') as completed_bookings,
                   (SELECT AVG(r.rating) FROM reviews r 
                    JOIN bookings b ON r.booking_id = b.id 
                    WHERE b.service_id = s.id AND r.rating > 0) as avg_rating,
                   ps.price as service_price,
                   ps.availability,
                   s.base_price
                   FROM services s 
                   INNER JOIN provider_services ps ON s.id = ps.service_id
                   INNER JOIN users u ON ps.provider_id = u.id 
                   $where_clause 
                   ORDER BY s.created_at DESC 
                   LIMIT :limit OFFSET :offset";

$services_stmt = $db->prepare($services_query);
foreach ($params as $key => $value) {
    $services_stmt->bindValue($key, $value);
}
$services_stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$services_stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

if (!$services_stmt->execute()) {
    $error = "Error loading services: " . implode(", ", $services_stmt->errorInfo());
    $services = [];
} else {
    $services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Get total count for pagination
$count_query = "SELECT COUNT(*) FROM services s 
                INNER JOIN provider_services ps ON s.id = ps.service_id
                INNER JOIN users u ON ps.provider_id = u.id $where_clause";
$count_stmt = $db->prepare($count_query);
foreach ($params as $key => $value) {
    $count_stmt->bindValue($key, $value);
}

if (!$count_stmt->execute()) {
    $total_services = 0;
} else {
    $total_services = $count_stmt->fetchColumn();
}
$total_pages = ceil($total_services / $per_page);

// Get categories for filter
$categories_query = "SELECT DISTINCT category FROM services ORDER BY category";
$categories_stmt = $db->prepare($categories_query);
$categories_stmt->execute();
$categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get providers for filter
$providers_query = "SELECT DISTINCT u.id, u.full_name 
                    FROM users u 
                    JOIN provider_services ps ON u.id = ps.provider_id 
                    WHERE u.user_type = 'provider' 
                    ORDER BY u.full_name";
$providers_stmt = $db->prepare($providers_query);
$providers_stmt->execute();
$providers = $providers_stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Services - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        /* Optimized Admin Services Layout */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(380px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
            padding: 0;
        }
        
        .service-card {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
            height: fit-content;
            min-height: 450px;
        }
        
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-hover);
        }
        
        .service-header {
            position: relative;
            height: 140px;
        }
        
        .service-image {
            width: 100%;
            height: 100%;
            background: var(--secondary-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
        }
        
        .service-placeholder {
            font-size: 2.5rem;
            color: white;
            opacity: 0.9;
        }
        
        .service-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .service-status {
            position: absolute;
            top: 1rem;
            right: 1rem;
            z-index: 3;
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            backdrop-filter: blur(10px);
        }
        
        .status-badge.active {
            background: rgba(39, 174, 96, 0.9);
            color: white;
        }
        
        .status-badge.inactive {
            background: rgba(231, 76, 60, 0.9);
            color: white;
        }
        
        .service-info {
            padding: 1.5rem;
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }
        
        .service-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--text-dark);
            margin: 0;
            line-height: 1.3;
        }
        
        .service-category {
            color: var(--text-dark);
            opacity: 0.7;
            font-size: 0.9rem;
            margin: 0;
        }
        
        .service-description {
            color: var(--text-dark);
            opacity: 0.8;
            font-size: 0.9rem;
            line-height: 1.5;
            margin: 0;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .service-provider {
            display: flex;
            align-items: center;
            gap: 0.8rem;
            padding: 0.8rem;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 15px;
            margin: 0.5rem 0;
        }
        
        .provider-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--accent-gradient);
            flex-shrink: 0;
        }
        
        .provider-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        
        .provider-icon {
            color: white;
            font-size: 1.2rem;
        }
        
        .provider-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.2rem;
        }
        
        .provider-name {
            font-weight: 600;
            color: var(--text-dark);
            font-size: 0.95rem;
        }
        
        .provider-label {
            font-size: 0.8rem;
            color: var(--text-dark);
            opacity: 0.6;
        }
        
        .service-stats {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin: 0.5rem 0;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.4rem;
            font-size: 0.85rem;
            color: var(--text-dark);
            opacity: 0.8;
        }
        
        .stat-item i {
            color: #4facfe;
            font-size: 0.9rem;
        }
        
        .service-meta {
            margin-top: auto;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .created-date {
            font-size: 0.8rem;
            color: var(--text-dark);
            opacity: 0.6;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        
        .service-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: space-between;
            padding: 1rem 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: rgba(255, 255, 255, 0.05);
        }
        
        .status-form select {
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 10px;
            padding: 0.4rem 0.8rem;
            font-size: 0.8rem;
            color: var(--text-dark);
            backdrop-filter: blur(10px);
            outline: none;
        }
        
        .delete-service-btn {
            background: rgba(231, 76, 60, 0.8);
            color: white;
            border: none;
            border-radius: 10px;
            padding: 0.5rem 0.8rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }
        
        .delete-service-btn:hover {
            background: rgba(231, 76, 60, 1);
            transform: translateY(-2px);
        }
        
        /* Empty state improvements */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-dark);
            opacity: 0.7;
        }
        
        .empty-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #4facfe;
        }
        
        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
        }
    </style>
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
                <li><a href="services.php" class="nav-link active">
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
                    <i class="fas fa-cogs"></i> Manage Services
                </h1>
                <p class="dashboard-subtitle">
                    View and manage all services offered by providers
                </p>
                <div class="header-actions">
                    <button type="button" class="btn btn-primary" onclick="openAddServiceModal()">
                        <i class="fas fa-plus"></i> Add New Service
                    </button>
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
                                <label for="search">Search Services</label>
                                <div class="search-input-wrapper">
                                    <i class="fas fa-search search-icon"></i>
                                    <input type="text" 
                                           name="search" 
                                           id="search" 
                                           class="form-control search-input"
                                           placeholder="Search by service name, description, or provider..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="category">Category</label>
                                <select name="category" id="category" class="form-control">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?php echo htmlspecialchars($category); ?>" 
                                                <?php echo $category_filter === $category ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select name="status" id="status" class="form-control">
                                    <option value="">All Status</option>
                                    <option value="active" <?php echo $status_filter === 'active' ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo $status_filter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="provider">Provider</label>
                                <select name="provider" id="provider" class="form-control">
                                    <option value="">All Providers</option>
                                    <?php foreach ($providers as $provider): ?>
                                        <option value="<?php echo $provider['id']; ?>" 
                                                <?php echo $provider_filter == $provider['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($provider['full_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
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

            <!-- Services List -->
            <div class="glass-container">
                <div class="services-header">
                    <h3><i class="fas fa-list"></i> Services List</h3>
                    <div class="services-stats">
                        <span class="stats-item">
                            <i class="fas fa-cogs"></i> Total: <?php echo $total_services; ?>
                        </span>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if (count($services) > 0): ?>
                    <div class="services-grid">
                        <?php foreach ($services as $service): ?>
                            <div class="service-card">
                                <div class="service-header">
                                    <div class="service-image">
                                        <?php if (isset($service['image']) && $service['image'] && file_exists('../uploads/services/' . $service['image'])): ?>
                                            <img src="../uploads/services/<?php echo htmlspecialchars($service['image']); ?>" 
                                                 alt="Service Image" class="service-img">
                                        <?php else: ?>
                                            <div class="service-placeholder">
                                                <i class="fas fa-cog"></i>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="service-status">
                                        <span class="status-badge <?php echo $service['status']; ?>">
                                            <i class="fas fa-circle"></i>
                                            <?php echo ucfirst($service['status']); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="service-info">
                                    <h4 class="service-name"><?php echo htmlspecialchars($service['name']); ?></h4>
                                    <p class="service-category">
                                        <i class="fas fa-tag"></i> <?php echo htmlspecialchars($service['category']); ?>
                                    </p>
                                    <p class="service-description">
                                        <?php echo htmlspecialchars(substr($service['description'], 0, 100)) . (strlen($service['description']) > 100 ? '...' : ''); ?>
                                    </p>
                                    
                                    <div class="service-provider">
                                        <div class="provider-avatar">
                                            <?php if ($service['provider_image'] && $service['provider_image'] !== 'default.jpg' && file_exists('../uploads/profile_photos/' . $service['provider_image'])): ?>
                                                <img src="../uploads/profile_photos/<?php echo htmlspecialchars($service['provider_image']); ?>?v=<?php echo time(); ?>" 
                                                     alt="Provider" class="provider-img">
                                            <?php else: ?>
                                                <i class="fas fa-user provider-icon"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="provider-info">
                                            <span class="provider-name"><?php echo htmlspecialchars($service['provider_name']); ?></span>
                                            <span class="provider-label">Service Provider</span>
                                        </div>
                                    </div>
                                    
                                    <div class="service-stats">
                                        <div class="stat-item">
                                            <i class="fas fa-dollar-sign"></i>
                                            <span>₱<?php echo number_format($service['service_price'], 2); ?></span>
                                        </div>
                                        <div class="stat-item">
                                            <i class="fas fa-calendar-check"></i>
                                            <span><?php echo $service['total_bookings']; ?> bookings</span>
                                        </div>
                                        <?php if ($service['avg_rating'] && $service['avg_rating'] > 0): ?>
                                            <div class="stat-item">
                                                <i class="fas fa-star"></i>
                                                <span><?php echo number_format($service['avg_rating'], 1); ?> rating</span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="service-meta">
                                        <span class="created-date">
                                            <i class="fas fa-clock"></i>
                                            Created <?php echo date('M j, Y', strtotime($service['created_at'])); ?>
                                        </span>
                                    </div>
                                </div>
                                
                                <div class="service-actions">
                                    <div class="action-group">
                                        <form method="POST" class="status-form" style="display: inline;">
                                            <input type="hidden" name="action" value="update_service_status">
                                            <input type="hidden" name="service_id" value="<?php echo $service['id']; ?>">
                                            <select name="status" class="status-select" onchange="this.form.submit()">
                                                <option value="active" <?php echo $service['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                <option value="inactive" <?php echo $service['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                            </select>
                                        </form>
                                    </div>
                                    
                                    <button type="button" 
                                            class="btn btn-danger btn-sm delete-service-btn" 
                                            onclick="confirmDelete(<?php echo $service['id']; ?>, '<?php echo htmlspecialchars($service['name']); ?>')">
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
                                <a href="?page=<?php echo $page - 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&provider=<?php echo $provider_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-btn">
                                    <i class="fas fa-chevron-left"></i> Previous
                                </a>
                            <?php endif; ?>
                            
                            <span class="pagination-info">
                                Page <?php echo $page; ?> of <?php echo $total_pages; ?>
                            </span>
                            
                            <?php if ($page < $total_pages): ?>
                                <a href="?page=<?php echo $page + 1; ?>&category=<?php echo $category_filter; ?>&status=<?php echo $status_filter; ?>&provider=<?php echo $provider_filter; ?>&search=<?php echo urlencode($search); ?>" 
                                   class="pagination-btn">
                                    Next <i class="fas fa-chevron-right"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-cogs"></i>
                        </div>
                        <h3>No Services Found</h3>
                        <p>No services match your current filters. Try adjusting your search criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Service Modal -->
    <div class="modal" id="addServiceModal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Add New Service</h3>
                <button type="button" class="modal-close" onclick="closeAddServiceModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add_service">
                    
                    <div class="form-group">
                        <label for="serviceName" class="form-label">
                            <i class="fas fa-tag"></i> Service Name *
                        </label>
                        <input type="text" 
                               name="name" 
                               id="serviceName" 
                               class="form-control"
                               placeholder="e.g., House Cleaning, Plumbing Repair"
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="serviceCategory" class="form-label">
                            <i class="fas fa-list"></i> Category *
                        </label>
                        <select name="category" id="serviceCategory" class="form-control" required>
                            <option value="">Select Category</option>
                            <option value="Cleaning">Cleaning</option>
                            <option value="Plumbing">Plumbing</option>
                            <option value="Electrical">Electrical</option>
                            <option value="Carpentry">Carpentry</option>
                            <option value="Painting">Painting</option>
                            <option value="Appliance Repair">Appliance Repair</option>
                            <option value="Landscaping">Landscaping</option>
                            <option value="Moving">Moving</option>
                            <option value="Automotive">Automotive</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="basePrice" class="form-label">
                            <i class="fas fa-peso-sign"></i> Base Price
                        </label>
                        <input type="number" 
                               name="base_price" 
                               id="basePrice" 
                               class="form-control"
                               placeholder="0.00"
                               min="0"
                               step="0.01">
                        <small class="form-help">Starting price for this service (providers can set their own prices)</small>
                    </div>
                    
                    <div class="form-group">
                        <label for="serviceDescription" class="form-label">
                            <i class="fas fa-info-circle"></i> Description
                        </label>
                        <textarea name="description" 
                                  id="serviceDescription" 
                                  class="form-control"
                                  rows="3"
                                  placeholder="Brief description of the service..."></textarea>
                    </div>
                </div>
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Service
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeAddServiceModal()">
                        Cancel
                    </button>
                </div>
            </form>
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
                <p>Are you sure you want to delete service <strong id="deleteServiceName"></strong>?</p>
                <p class="warning-text">
                    <i class="fas fa-warning"></i>
                    This action cannot be undone and will remove all service data including bookings.
                </p>
            </div>
            <div class="modal-actions">
                <form method="POST" id="deleteForm">
                    <input type="hidden" name="action" value="delete_service">
                    <input type="hidden" name="service_id" id="deleteServiceId">
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Delete Service
                    </button>
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">
                        Cancel
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        // Add service modal functions
        function openAddServiceModal() {
            document.getElementById('addServiceModal').style.display = 'flex';
            document.body.style.overflow = 'hidden';
        }

        function closeAddServiceModal() {
            document.getElementById('addServiceModal').style.display = 'none';
            document.body.style.overflow = '';
        }

        // Delete confirmation
        function confirmDelete(serviceId, serviceName) {
            document.getElementById('deleteServiceId').value = serviceId;
            document.getElementById('deleteServiceName').textContent = serviceName;
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
