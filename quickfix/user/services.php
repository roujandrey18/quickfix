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

// Get search and filter parameters
$search = $_GET['search'] ?? '';
$category = $_GET['category'] ?? '';
$min_price = $_GET['min_price'] ?? '';
$max_price = $_GET['max_price'] ?? '';
$sort_by = $_GET['sort_by'] ?? 'name';

// Build query with filters
$where_conditions = ["s.status = 'active'", "ps.availability = 'available'"];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(s.name LIKE :search OR s.description LIKE :search OR u.full_name LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

if (!empty($category)) {
    $where_conditions[] = "s.category = :category";
    $params[':category'] = $category;
}

if (!empty($min_price) && is_numeric($min_price)) {
    $where_conditions[] = "ps.price >= :min_price";
    $params[':min_price'] = $min_price;
}

if (!empty($max_price) && is_numeric($max_price)) {
    $where_conditions[] = "ps.price <= :max_price";
    $params[':max_price'] = $max_price;
}

// Sort options
$sort_options = [
    'name' => 's.name ASC',
    'price_low' => 'ps.price ASC',
    'price_high' => 'ps.price DESC',
    'rating' => 'avg_rating DESC',
    'newest' => 's.created_at DESC'
];

$order_by = $sort_options[$sort_by] ?? 's.name ASC';

// Main query with ratings
$query = "SELECT s.*, ps.price, ps.provider_id, u.full_name as provider_name, 
                 u.phone as provider_phone, u.address as provider_address,
                 COALESCE(AVG(r.rating), 0) as avg_rating,
                 COUNT(r.id) as review_count,
                 COUNT(DISTINCT b.id) as booking_count
          FROM services s 
          JOIN provider_services ps ON s.id = ps.service_id 
          JOIN users u ON ps.provider_id = u.id 
          LEFT JOIN reviews r ON ps.provider_id = r.provider_id
          LEFT JOIN bookings b ON ps.provider_id = b.provider_id AND b.status = 'completed'
          WHERE " . implode(' AND ', $where_conditions) . "
          GROUP BY s.id, ps.provider_id, ps.price
          ORDER BY " . $order_by;

$stmt = $db->prepare($query);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter
$cat_query = "SELECT DISTINCT category FROM services WHERE status = 'active' ORDER BY category";
$cat_stmt = $db->prepare($cat_query);
$cat_stmt->execute();
$categories = $cat_stmt->fetchAll(PDO::FETCH_COLUMN);

// Get price range
$price_query = "SELECT MIN(ps.price) as min_price, MAX(ps.price) as max_price 
                FROM provider_services ps 
                JOIN services s ON ps.service_id = s.id 
                WHERE s.status = 'active' AND ps.availability = 'available'";
$price_stmt = $db->prepare($price_query);
$price_stmt->execute();
$price_range = $price_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browse Services - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-wrench"></i> QuickFix
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">
                    <i class="fas fa-home"></i> Dashboard
                </a></li>
                <li><a href="services.php" class="nav-link active">
                    <i class="fas fa-search"></i> Browse Services
                </a></li>
                <li><a href="bookings.php" class="nav-link">
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
                        <a href="bookings.php" class="dropdown-item">
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
                    <i class="fas fa-search"></i> Browse Services
                </h1>
                <p class="dashboard-subtitle">
                    Find the perfect service provider for your needs
                </p>
            </div>

            <!-- Search and Filters -->
            <div class="services-filters-container">
                <form class="services-filters" method="GET">
                    <div class="filters-row">
                        <div class="search-box">
                            <i class="fas fa-search"></i>
                            <input type="text" name="search" placeholder="Search services, providers..." 
                                   value="<?php echo htmlspecialchars($search); ?>" class="search-input">
                        </div>
                        
                        <div class="filter-group">
                            <select name="category" class="filter-select">
                                <option value="">All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>" 
                                        <?php echo $category === $cat ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cat); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="price-range-group">
                            <input type="number" name="min_price" placeholder="Min $" 
                                   value="<?php echo htmlspecialchars($min_price); ?>" 
                                   class="price-input" min="0" step="0.01">
                            <span class="price-separator">-</span>
                            <input type="number" name="max_price" placeholder="Max $" 
                                   value="<?php echo htmlspecialchars($max_price); ?>" 
                                   class="price-input" min="0" step="0.01">
                        </div>

                        <div class="filter-group">
                            <select name="sort_by" class="filter-select">
                                <option value="name" <?php echo $sort_by === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                                <option value="price_low" <?php echo $sort_by === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort_by === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                                <option value="rating" <?php echo $sort_by === 'rating' ? 'selected' : ''; ?>>Highest Rated</option>
                                <option value="newest" <?php echo $sort_by === 'newest' ? 'selected' : ''; ?>>Newest</option>
                            </select>
                        </div>

                        <button type="submit" class="btn btn-primary filter-btn">
                            <i class="fas fa-filter"></i> Filter
                        </button>
                        
                        <a href="services.php" class="btn btn-secondary clear-btn">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>

            <!-- Results Summary -->
            <div class="results-summary">
                <div class="results-info">
                    <i class="fas fa-list"></i>
                    <span><?php echo count($services); ?> services found</span>
                    <?php if (!empty($search)): ?>
                        <span class="search-term">for "<?php echo htmlspecialchars($search); ?>"</span>
                    <?php endif; ?>
                </div>
                
                <?php if (!empty($category) || !empty($min_price) || !empty($max_price)): ?>
                <div class="active-filters">
                    <span class="filter-label">Active filters:</span>
                    <?php if (!empty($category)): ?>
                    <span class="filter-tag">
                        Category: <?php echo htmlspecialchars($category); ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['category' => ''])); ?>" class="remove-filter">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                    <?php endif; ?>
                    
                    <?php if (!empty($min_price) || !empty($max_price)): ?>
                    <span class="filter-tag">
                        Price: ₱<?php echo $min_price ?: '0'; ?> - ₱<?php echo $max_price ?: '∞'; ?>
                        <a href="?<?php echo http_build_query(array_merge($_GET, ['min_price' => '', 'max_price' => ''])); ?>" class="remove-filter">
                            <i class="fas fa-times"></i>
                        </a>
                    </span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- Services Grid -->
            <?php if (count($services) > 0): ?>
            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                <div class="service-card enhanced">
                    <div class="service-image">
                        <i class="fas fa-<?php 
                            echo match(strtolower($service['category'])) {
                                'cleaning' => 'broom',
                                'maintenance' => 'wrench',
                                'outdoor' => 'leaf',
                                'electrical' => 'bolt',
                                'plumbing' => 'water',
                                default => 'tools'
                            };
                        ?>"></i>
                        <div class="service-category-badge">
                            <?php echo htmlspecialchars($service['category']); ?>
                        </div>
                    </div>
                    
                    <div class="service-content">
                        <div class="service-header">
                            <h3 class="service-title"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <div class="service-rating">
                                <?php 
                                $rating = round($service['avg_rating']);
                                for ($i = 1; $i <= 5; $i++): 
                                ?>
                                <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                <?php endfor; ?>
                                <span class="rating-text">
                                    (<?php echo number_format($service['avg_rating'], 1); ?>/5)
                                </span>
                            </div>
                        </div>
                        
                        <p class="service-description">
                            <?php echo htmlspecialchars($service['description']); ?>
                        </p>
                        
                        <div class="provider-info">
                            <div class="provider-details">
                                <i class="fas fa-user-tie"></i>
                                <div>
                                    <strong><?php echo htmlspecialchars($service['provider_name']); ?></strong>
                                    <div class="provider-stats">
                                        <span><i class="fas fa-check-circle"></i> <?php echo $service['booking_count']; ?> completed jobs</span>
                                        <span><i class="fas fa-star"></i> <?php echo $service['review_count']; ?> reviews</span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="contact-info">
                                <a href="tel:<?php echo htmlspecialchars($service['provider_phone']); ?>" class="contact-btn">
                                    <i class="fas fa-phone"></i>
                                </a>
                                <a href="#" onclick="showProviderDetails(<?php echo $service['provider_id']; ?>)" class="contact-btn">
                                    <i class="fas fa-info-circle"></i>
                                </a>
                            </div>
                        </div>
                        
                        <div class="service-footer">
                            <div class="service-price">
                                ₱<?php echo number_format($service['price'], 2); ?>
                                <span class="price-label">starting from</span>
                            </div>
                            
                            <div class="service-actions">
                                <a href="book_service.php?id=<?php echo $service['id']; ?>&provider=<?php echo $service['provider_id']; ?>" 
                                   class="btn btn-primary service-book-btn">
                                    <i class="fas fa-calendar-plus"></i> Book Now
                                </a>
                                <button onclick="toggleFavorite(<?php echo $service['id']; ?>, <?php echo $service['provider_id']; ?>)" 
                                        class="btn btn-secondary favorite-btn" 
                                        data-service="<?php echo $service['id']; ?>" 
                                        data-provider="<?php echo $service['provider_id']; ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">
                    <i class="fas fa-search-minus"></i>
                </div>
                <h3>No services found</h3>
                <p>Try adjusting your search criteria or browse all available services.</p>
                <a href="services.php" class="btn btn-primary">
                    <i class="fas fa-refresh"></i> View All Services
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Provider Details Modal -->
    <div id="providerModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-user-tie"></i> Provider Details</h3>
                <button class="modal-close" onclick="closeModal()">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="modal-body" id="providerDetails">
                <div class="loading-spinner">
                    <div class="loading"></div>
                    <p>Loading provider information...</p>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Provider details modal
        function showProviderDetails(providerId) {
            const modal = document.getElementById('providerModal');
            const details = document.getElementById('providerDetails');
            
            modal.style.display = 'flex';
            details.innerHTML = `
                <div class="loading-spinner">
                    <div class="loading"></div>
                    <p>Loading provider information...</p>
                </div>
            `;
            
            fetch(`get_provider_details.php?id=${providerId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        details.innerHTML = `
                            <div class="provider-profile">
                                <div class="provider-avatar">
                                    <i class="fas fa-user-circle"></i>
                                </div>
                                <div class="provider-info-detailed">
                                    <h4>${data.provider.full_name}</h4>
                                    <p><i class="fas fa-phone"></i> ${data.provider.phone}</p>
                                    <p><i class="fas fa-envelope"></i> ${data.provider.email}</p>
                                    <p><i class="fas fa-map-marker-alt"></i> ${data.provider.address}</p>
                                </div>
                            </div>
                            <div class="provider-stats-detailed">
                                <div class="stat-item">
                                    <div class="stat-number">${data.stats.total_bookings}</div>
                                    <div class="stat-label">Total Jobs</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">${data.stats.avg_rating}</div>
                                    <div class="stat-label">Average Rating</div>
                                </div>
                                <div class="stat-item">
                                    <div class="stat-number">${data.stats.total_reviews}</div>
                                    <div class="stat-label">Reviews</div>
                                </div>
                            </div>
                            <div class="provider-services-list">
                                <h5>Available Services:</h5>
                                <div class="services-list">
                                    ${data.services.map(service => `
                                        <div class="service-item">
                                            <span class="service-name">${service.name}</span>
                                            <span class="service-price">₱${parseFloat(service.price).toFixed(2)}</span>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                        `;
                    } else {
                        details.innerHTML = '<p>Error loading provider details.</p>';
                    }
                })
                .catch(error => {
                    details.innerHTML = '<p>Error loading provider details.</p>';
                });
        }

        function closeModal() {
            document.getElementById('providerModal').style.display = 'none';
        }

        // Favorite functionality
        function toggleFavorite(serviceId, providerId) {
            const btn = document.querySelector(`[data-service="${serviceId}"][data-provider="${providerId}"]`);
            const icon = btn.querySelector('i');
            
            fetch('toggle_favorite.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    service_id: serviceId,
                    provider_id: providerId
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    if (data.favorited) {
                        icon.className = 'fas fa-heart';
                        btn.classList.add('favorited');
                    } else {
                        icon.className = 'far fa-heart';
                        btn.classList.remove('favorited');
                    }
                    showNotification(data.message, 'success');
                }
            });
        }

        // Auto-submit form on filter change
        document.querySelectorAll('.filter-select').forEach(select => {
            select.addEventListener('change', function() {
                this.form.submit();
            });
        });

        // Mobile filter toggle
        function toggleMobileFilters() {
            const filters = document.querySelector('.services-filters');
            filters.classList.toggle('mobile-open');
        }
    </script>
</body>
</html>