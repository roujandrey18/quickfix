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

// Get all available services for suggestions
$services_stmt = $db->prepare("SELECT * FROM services WHERE status = 'active' ORDER BY category, name");
$services_stmt->execute();
$services = $services_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get distinct categories for suggestions
$categories_stmt = $db->prepare("SELECT DISTINCT category FROM services WHERE status = 'active' ORDER BY category");
$categories_stmt->execute();
$existing_categories = $categories_stmt->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $service_type = $_POST['service_type']; // 'existing' or 'custom'
    $price = (float)$_POST['price'];
    $availability = $_POST['availability'] === 'unavailable' ? 'unavailable' : 'available';
    
    if ($service_type === 'existing') {
        // Using existing service
        $service_id = (int)$_POST['existing_service_id'];
        
        // Check if already added
        $check_stmt = $db->prepare("SELECT id FROM provider_services WHERE provider_id = :provider_id AND service_id = :service_id");
        $check_stmt->execute([':provider_id' => $provider_id, ':service_id' => $service_id]);
        if ($check_stmt->fetch()) {
            $error = 'You have already added this service.';
        } else {
            $insert_stmt = $db->prepare("INSERT INTO provider_services (provider_id, service_id, price, availability) VALUES (:provider_id, :service_id, :price, :availability)");
            $result = $insert_stmt->execute([
                ':provider_id' => $provider_id,
                ':service_id' => $service_id,
                ':price' => $price,
                ':availability' => $availability
            ]);
            if ($result) {
                $_SESSION['success'] = 'Service added successfully!';
                header('Location: services.php');
                exit;
            } else {
                $error = 'Failed to add service.';
            }
        }
    } else {
        // Creating custom service
        $service_name = trim($_POST['custom_service_name']);
        $service_description = trim($_POST['custom_service_description']);
        $service_category = trim($_POST['custom_service_category']);
        
        if (empty($service_name) || empty($service_category)) {
            $error = 'Service name and category are required.';
        } else {
            try {
                $db->beginTransaction();
                
                // First, create the new service
                $create_service_stmt = $db->prepare("INSERT INTO services (name, description, category, base_price, status) VALUES (:name, :description, :category, :base_price, 'active')");
                $result1 = $create_service_stmt->execute([
                    ':name' => $service_name,
                    ':description' => $service_description,
                    ':category' => $service_category,
                    ':base_price' => $price
                ]);
                
                if ($result1) {
                    $new_service_id = $db->lastInsertId();
                    
                    // Then add it to provider's services
                    $add_provider_service_stmt = $db->prepare("INSERT INTO provider_services (provider_id, service_id, price, availability) VALUES (:provider_id, :service_id, :price, :availability)");
                    $result2 = $add_provider_service_stmt->execute([
                        ':provider_id' => $provider_id,
                        ':service_id' => $new_service_id,
                        ':price' => $price,
                        ':availability' => $availability
                    ]);
                    
                    if ($result2) {
                        $db->commit();
                        $_SESSION['success'] = 'Custom service created and added successfully!';
                        header('Location: services.php');
                        exit;
                    } else {
                        throw new Exception('Failed to add service to your portfolio.');
                    }
                } else {
                    throw new Exception('Failed to create custom service.');
                }
            } catch (Exception $e) {
                $db->rollBack();
                $error = $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Service - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
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
                <li><a href="add_service.php" class="nav-link active">
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
                    <i class="fas fa-plus-circle"></i> Add New Service
                </h1>
                <p class="dashboard-subtitle">
                    Expand your service portfolio by adding a new service
                </p>
                <div class="header-actions">
                    <a href="services.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Services
                    </a>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="notification error show" style="position: relative; top: 0; right: 0; margin-bottom: 2rem; transform: none;">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Add Service Form -->
            <div class="glass-container">
                <div class="form-container">
                    <div class="form-header">
                        <h2>
                            <i class="fas fa-magic"></i> Add Service to Your Portfolio
                        </h2>
                        <p>Choose to add an existing service or create your own custom service</p>
                    </div>
                    
                    <!-- Service Type Selection -->
                    <div class="service-type-tabs">
                        <button type="button" class="tab-btn active" data-tab="existing">
                            <i class="fas fa-list"></i>
                            <span>Choose Existing</span>
                            <small>Select from available services</small>
                        </button>
                        <button type="button" class="tab-btn" data-tab="custom">
                            <i class="fas fa-plus-circle"></i>
                            <span>Create Custom</span>
                            <small>Design your own service</small>
                        </button>
                    </div>
                    
                    <form method="post" class="form-grid" id="serviceForm">
                        <!-- Hidden field for service type -->
                        <input type="hidden" name="service_type" id="serviceType" value="existing">
                        
                        <!-- Existing Service Tab -->
                        <div class="tab-content active" id="existing-tab">
                            <div class="form-group">
                                <label for="existing_service_id">
                                    <i class="fas fa-search"></i> Search & Select Service
                                </label>
                                <div class="service-search-container">
                                    <input type="text" id="serviceSearch" placeholder="Type to search services..." class="form-control search-input">
                                    <div class="search-results" id="searchResults"></div>
                                </div>
                                <select name="existing_service_id" id="existing_service_id" class="form-control" style="display: none;">
                                    <option value="">Choose a service...</option>
                                    <?php 
                                    $categories = [];
                                    foreach ($services as $service) {
                                        $categories[$service['category']][] = $service;
                                    }
                                    foreach ($categories as $category => $categoryServices): ?>
                                        <optgroup label="<?php echo htmlspecialchars($category); ?>">
                                            <?php foreach ($categoryServices as $service): ?>
                                                <option value="<?php echo $service['id']; ?>" 
                                                        data-base-price="<?php echo $service['base_price']; ?>"
                                                        data-category="<?php echo htmlspecialchars($service['category']); ?>"
                                                        data-description="<?php echo htmlspecialchars($service['description']); ?>">
                                                    <?php echo htmlspecialchars($service['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </optgroup>
                                    <?php endforeach; ?>
                                </select>
                                <div class="selected-service-preview" id="selectedServicePreview" style="display: none;">
                                    <div class="service-card">
                                        <div class="service-info">
                                            <h4 class="service-name"></h4>
                                            <p class="service-category"></p>
                                            <p class="service-description"></p>
                                            <div class="service-base-price">
                                                Base Price: <span class="price-amount"></span>
                                            </div>
                                        </div>
                                        <button type="button" class="btn-clear" onclick="clearSelection()">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Custom Service Tab -->
                        <div class="tab-content" id="custom-tab">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="custom_service_name">
                                        <i class="fas fa-tag"></i> Service Name *
                                        <span class="field-status" id="nameStatus"></span>
                                    </label>
                                    <div class="enhanced-input-container">
                                        <div class="input-wrapper">
                                            <input type="text" 
                                                   name="custom_service_name" 
                                                   id="custom_service_name" 
                                                   class="form-control enhanced-input"
                                                   placeholder="Enter your service name..."
                                                   maxlength="100"
                                                   autocomplete="off">
                                            <div class="input-indicators">
                                                <div class="input-counter">
                                                    <span id="nameCounter">0</span>/100
                                                </div>
                                                <div class="validation-icon" id="nameValidation">
                                                    <i class="fas fa-check-circle"></i>
                                                </div>
                                            </div>
                                            <div class="input-strength-bar">
                                                <div class="strength-fill" id="nameStrengthFill"></div>
                                            </div>
                                        </div>
                                        
                                        <div class="name-suggestions enhanced-suggestions" id="nameSuggestions">
                                            <div class="suggestions-header">
                                                <i class="fas fa-magic"></i> 
                                                <span>Smart Suggestions</span>
                                                <button type="button" class="suggestions-close" onclick="hideSuggestions('name')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="suggestion-categories">
                                                <div class="suggestion-category">
                                                    <h4>Professional Variants</h4>
                                                    <div class="suggestion-tags" id="nameSuggestionTags"></div>
                                                </div>
                                                <div class="suggestion-category">
                                                    <h4>Popular Keywords</h4>
                                                    <div class="keyword-tags" id="keywordTags"></div>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <div class="input-analytics" id="nameAnalytics">
                                            <div class="analytics-row">
                                                <span class="analytics-label">
                                                    <i class="fas fa-search"></i> SEO Score:
                                                </span>
                                                <div class="score-bar">
                                                    <div class="score-fill" id="seoScoreFill"></div>
                                                    <span class="score-text" id="seoScoreText">0%</span>
                                                </div>
                                            </div>
                                            <div class="analytics-row">
                                                <span class="analytics-label">
                                                    <i class="fas fa-eye"></i> Clarity:
                                                </span>
                                                <div class="clarity-indicators" id="clarityIndicators">
                                                    <span class="clarity-dot"></span>
                                                    <span class="clarity-dot"></span>
                                                    <span class="clarity-dot"></span>
                                                    <span class="clarity-dot"></span>
                                                    <span class="clarity-dot"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="form-help dynamic-help" id="nameHelp">
                                        <i class="fas fa-lightbulb"></i> Use descriptive keywords that customers would search for
                                    </small>
                                </div>
                                
                                <div class="form-group">
                                    <label for="custom_service_category">
                                        <i class="fas fa-folder"></i> Category *
                                        <span class="field-status" id="categoryStatus"></span>
                                    </label>
                                    <div class="category-input-container">
                                        <div class="input-wrapper">
                                            <input type="text" 
                                                   name="custom_service_category" 
                                                   id="custom_service_category" 
                                                   class="form-control enhanced-input"
                                                   placeholder="Choose or create category..."
                                                   list="categoryList"
                                                   autocomplete="off">
                                            <div class="input-indicators">
                                                <div class="category-type" id="categoryType">New</div>
                                                <div class="validation-icon" id="categoryValidation">
                                                    <i class="fas fa-plus-circle"></i>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <datalist id="categoryList">
                                            <?php foreach ($existing_categories as $category): ?>
                                                <option value="<?php echo htmlspecialchars($category); ?>">
                                            <?php endforeach; ?>
                                        </datalist>
                                        
                                        <div class="category-suggestions enhanced-suggestions" id="categorySuggestions">
                                            <div class="suggestions-header">
                                                <i class="fas fa-tags"></i> 
                                                <span>Category Options</span>
                                                <button type="button" class="suggestions-close" onclick="hideSuggestions('category')">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <div class="suggestion-categories">
                                                <div class="suggestion-category">
                                                    <h4>Popular Categories</h4>
                                                    <div class="category-tags">
                                                        <?php foreach ($existing_categories as $index => $category): ?>
                                                            <button type="button" 
                                                                    class="category-tag <?php echo $index < 3 ? 'popular' : ''; ?>" 
                                                                    onclick="selectCategory('<?php echo htmlspecialchars($category); ?>')">
                                                                <i class="fas fa-tag"></i>
                                                                <span><?php echo htmlspecialchars($category); ?></span>
                                                                <?php if ($index < 3): ?>
                                                                    <span class="popular-badge">Popular</span>
                                                                <?php endif; ?>
                                                            </button>
                                                        <?php endforeach; ?>
                                                    </div>
                                                </div>
                                                <div class="suggestion-category">
                                                    <h4>Create New Category</h4>
                                                    <div class="new-category-suggestions" id="newCategorySuggestions">
                                                        <div class="suggestion-tags">
                                                            <button type="button" class="suggestion-tag new-category" onclick="suggestNewCategory('Home Services')">
                                                                <i class="fas fa-home"></i> Home Services
                                                            </button>
                                                            <button type="button" class="suggestion-tag new-category" onclick="suggestNewCategory('Personal Care')">
                                                                <i class="fas fa-user"></i> Personal Care
                                                            </button>
                                                            <button type="button" class="suggestion-tag new-category" onclick="suggestNewCategory('Business Services')">
                                                                <i class="fas fa-briefcase"></i> Business Services
                                                            </button>
                                                            <button type="button" class="suggestion-tag new-category" onclick="suggestNewCategory('Creative Services')">
                                                                <i class="fas fa-paint-brush"></i> Creative Services
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <small class="form-help dynamic-help" id="categoryHelp">
                                        <i class="fas fa-info-circle"></i> Choose existing category for better visibility or create your own
                                    </small>
                                </div>
                            </div>
                            
                            <div class="form-group">
                                <label for="custom_service_description">
                                    <i class="fas fa-align-left"></i> Service Description
                                    <span class="optional-badge">Optional</span>
                                    <span class="field-status" id="descriptionStatus"></span>
                                </label>
                                <div class="enhanced-textarea-container">
                                    <div class="textarea-wrapper">
                                        <textarea name="custom_service_description" 
                                                  id="custom_service_description" 
                                                  rows="4" 
                                                  class="form-control enhanced-textarea"
                                                  placeholder="Describe what makes your service special..."
                                                  maxlength="500"
                                                  autocomplete="off"></textarea>
                                        <div class="textarea-indicators">
                                            <div class="textarea-counter">
                                                <span id="descCounter">0</span>/500
                                            </div>
                                            <div class="validation-icon" id="descriptionValidation">
                                                <i class="fas fa-edit"></i>
                                            </div>
                                        </div>
                                        <div class="textarea-strength-bar">
                                            <div class="strength-fill" id="descriptionStrengthFill"></div>
                                        </div>
                                    </div>
                                    
                                    <!-- Description Analytics -->
                                    <div class="description-analytics" id="descriptionAnalytics">
                                        <div class="analytics-grid">
                                            <div class="analytics-item">
                                                <div class="analytics-label">
                                                    <i class="fas fa-eye"></i> Readability
                                                </div>
                                                <div class="readability-score" id="readabilityScore">
                                                    <div class="score-circle">
                                                        <span id="readabilityText">-</span>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="analytics-item">
                                                <div class="analytics-label">
                                                    <i class="fas fa-heart"></i> Appeal
                                                </div>
                                                <div class="appeal-rating" id="appealRating">
                                                    <div class="rating-stars">
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                        <i class="fas fa-star"></i>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="analytics-item">
                                                <div class="analytics-label">
                                                    <i class="fas fa-bullseye"></i> Keywords
                                                </div>
                                                <div class="keyword-count" id="keywordCount">
                                                    <span class="count-number">0</span>
                                                    <small>found</small>
                                                </div>
                                            </div>
                                            <div class="analytics-item">
                                                <div class="analytics-label">
                                                    <i class="fas fa-clock"></i> Read Time
                                                </div>
                                                <div class="read-time" id="readTime">
                                                    <span class="time-number">0</span>
                                                    <small>sec</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Enhanced Templates -->
                                    <div class="description-templates enhanced-templates" id="descriptionTemplates">
                                        <div class="template-header">
                                            <i class="fas fa-magic"></i> 
                                            <span>Smart Templates</span>
                                            <button type="button" class="suggestions-close" onclick="hideTemplates()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div class="template-categories">
                                            <div class="template-category">
                                                <h4>Popular Templates</h4>
                                                <div class="template-grid">
                                                    <button type="button" class="template-btn popular" onclick="useTemplate('cleaning')">
                                                        <i class="fas fa-broom"></i>
                                                        <span>Cleaning Service</span>
                                                        <div class="template-preview">Professional cleaning with eco-friendly products...</div>
                                                    </button>
                                                    <button type="button" class="template-btn popular" onclick="useTemplate('repair')">
                                                        <i class="fas fa-wrench"></i>
                                                        <span>Repair Service</span>
                                                        <div class="template-preview">Expert repairs with quality parts and warranty...</div>
                                                    </button>
                                                    <button type="button" class="template-btn popular" onclick="useTemplate('installation')">
                                                        <i class="fas fa-cogs"></i>
                                                        <span>Installation</span>
                                                        <div class="template-preview">Professional setup with testing and warranty...</div>
                                                    </button>
                                                </div>
                                            </div>
                                            <div class="template-category">
                                                <h4>More Templates</h4>
                                                <div class="template-grid">
                                                    <button type="button" class="template-btn" onclick="useTemplate('maintenance')">
                                                        <i class="fas fa-tools"></i>
                                                        <span>Maintenance</span>
                                                    </button>
                                                    <button type="button" class="template-btn" onclick="useTemplate('consultation')">
                                                        <i class="fas fa-comments"></i>
                                                        <span>Consultation</span>
                                                    </button>
                                                    <button type="button" class="template-btn" onclick="useTemplate('custom')">
                                                        <i class="fas fa-edit"></i>
                                                        <span>Custom</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- AI Writing Assistant -->
                                    <div class="writing-assistant" id="writingAssistant">
                                        <div class="assistant-header">
                                            <i class="fas fa-robot"></i> 
                                            <span>Writing Assistant</span>
                                            <button type="button" class="suggestions-close" onclick="hideAssistant()">
                                                <i class="fas fa-times"></i>
                                            </button>
                                        </div>
                                        <div class="assistant-content">
                                            <div class="assistant-suggestions" id="assistantSuggestions">
                                                <div class="suggestion-item">
                                                    <i class="fas fa-lightbulb"></i>
                                                    <span>Start with what makes your service unique</span>
                                                </div>
                                                <div class="suggestion-item">
                                                    <i class="fas fa-award"></i>
                                                    <span>Mention your experience or certifications</span>
                                                </div>
                                                <div class="suggestion-item">
                                                    <i class="fas fa-shield-alt"></i>
                                                    <span>Include guarantees or warranties</span>
                                                </div>
                                            </div>
                                            <div class="power-phrases" id="powerPhrases">
                                                <div class="phrases-header">Power Phrases</div>
                                                <div class="phrases-grid">
                                                    <button type="button" class="phrase-btn" onclick="addPhrase('Professional and reliable service')">
                                                        Professional & reliable
                                                    </button>
                                                    <button type="button" class="phrase-btn" onclick="addPhrase('Satisfaction guaranteed')">
                                                        Satisfaction guaranteed
                                                    </button>
                                                    <button type="button" class="phrase-btn" onclick="addPhrase('Years of experience')">
                                                        Years of experience
                                                    </button>
                                                    <button type="button" class="phrase-btn" onclick="addPhrase('Quality materials used')">
                                                        Quality materials
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <small class="form-help dynamic-help" id="descriptionHelp">
                                    <i class="fas fa-info-circle"></i> A good description increases customer confidence and bookings
                                </small>
                            </div>
                        </div>

                        <!-- Common Fields -->
                        <div class="form-group">
                            <label for="price">
                                <i class="fas fa-peso-sign"></i> Your Price *
                            </label>
                            <div class="price-input-container">
                                <div class="input-group">
                                    <span class="input-prefix">₱</span>
                                    <input type="number" 
                                           step="0.01" 
                                           min="0" 
                                           name="price" 
                                           id="price" 
                                           required 
                                           class="form-control price-input"
                                           placeholder="0.00">
                                    <div class="price-actions">
                                        <button type="button" class="price-btn" id="roundUpBtn" title="Round up to nearest 50">
                                            <i class="fas fa-arrow-up"></i>
                                        </button>
                                        <button type="button" class="price-btn" id="roundDownBtn" title="Round down to nearest 50">
                                            <i class="fas fa-arrow-down"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Price Suggestions -->
                                <div class="price-suggestions-container" id="priceSuggestionsContainer">
                                    <div class="price-suggestions" id="priceSuggestions">
                                        <div class="suggestions-header">
                                            <i class="fas fa-lightbulb"></i> Smart Price Suggestions
                                        </div>
                                        <div class="suggestions-grid" id="suggestionsGrid">
                                            <!-- Dynamic suggestions will be added here -->
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Price Analysis -->
                                <div class="price-analysis" id="priceAnalysis">
                                    <div class="analysis-row">
                                        <span class="analysis-label">
                                            <i class="fas fa-chart-line"></i> Market Position:
                                        </span>
                                        <span class="analysis-value" id="marketPosition">-</span>
                                    </div>
                                    <div class="analysis-row">
                                        <span class="analysis-label">
                                            <i class="fas fa-star"></i> Competitiveness:
                                        </span>
                                        <div class="competitiveness-bar">
                                            <div class="competitiveness-fill" id="competitivenessFill"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <small class="form-help dynamic-help" id="priceHelp">
                                <i class="fas fa-info-circle"></i> Enter your price to see market analysis and suggestions
                            </small>
                        </div>

                        <div class="form-group">
                            <label for="availability">
                                <i class="fas fa-toggle-on"></i> Initial Status
                            </label>
                            <div class="status-options">
                                <label class="status-option">
                                    <input type="radio" name="availability" value="available" checked>
                                    <div class="status-card available">
                                        <i class="fas fa-check-circle"></i>
                                        <strong>Available</strong>
                                        <small>Ready to accept bookings</small>
                                    </div>
                                </label>
                                <label class="status-option">
                                    <input type="radio" name="availability" value="unavailable">
                                    <div class="status-card unavailable">
                                        <i class="fas fa-pause-circle"></i>
                                        <strong>Unavailable</strong>
                                        <small>Not accepting bookings yet</small>
                                    </div>
                                </label>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-plus-circle"></i> Add Service to Portfolio
                            </button>
                            <a href="services.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Back to Services
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Service data for JavaScript
        const services = <?php echo json_encode($services); ?>;
        
        // Tab switching functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tabName = this.getAttribute('data-tab');
                
                // Update tab buttons
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Update tab content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.remove('active');
                });
                document.getElementById(tabName + '-tab').classList.add('active');
                
                // Update hidden field
                document.getElementById('serviceType').value = tabName;
                
                // Clear form when switching tabs
                clearForm();
            });
        });

        // Service search functionality
        const serviceSearch = document.getElementById('serviceSearch');
        const searchResults = document.getElementById('searchResults');
        const selectedServicePreview = document.getElementById('selectedServicePreview');
        
        serviceSearch.addEventListener('input', function() {
            const query = this.value.toLowerCase().trim();
            
            if (query.length < 2) {
                searchResults.innerHTML = '';
                searchResults.style.display = 'none';
                return;
            }
            
            const filteredServices = services.filter(service => 
                service.name.toLowerCase().includes(query) ||
                service.category.toLowerCase().includes(query) ||
                (service.description && service.description.toLowerCase().includes(query))
            );
            
            displaySearchResults(filteredServices);
        });

        function displaySearchResults(results) {
            if (results.length === 0) {
                searchResults.innerHTML = '<div class="no-results">No services found</div>';
            } else {
                searchResults.innerHTML = results.map(service => `
                    <div class="search-result-item" onclick="selectService(${service.id})">
                        <div class="result-info">
                            <div class="result-name">${service.name}</div>
                            <div class="result-category">${service.category}</div>
                            ${service.description ? `<div class="result-description">${service.description}</div>` : ''}
                        </div>
                        <div class="result-price">₱${parseFloat(service.base_price).toFixed(2)}</div>
                    </div>
                `).join('');
            }
            searchResults.style.display = 'block';
        }

        function selectService(serviceId) {
            const service = services.find(s => s.id == serviceId);
            if (!service) return;
            
            // Hide search results
            searchResults.style.display = 'none';
            serviceSearch.value = '';
            
            // Update hidden select
            document.getElementById('existing_service_id').value = serviceId;
            
            // Show service preview
            const preview = selectedServicePreview;
            preview.querySelector('.service-name').textContent = service.name;
            preview.querySelector('.service-category').textContent = service.category;
            preview.querySelector('.service-description').textContent = service.description || 'No description available';
            preview.querySelector('.price-amount').textContent = '₱' + parseFloat(service.base_price).toFixed(2);
            preview.style.display = 'block';
            
            // Auto-fill price
            document.getElementById('price').value = service.base_price;
            
            // Update price help
            updatePriceHelp(service.base_price, service.category);
        }

        function clearSelection() {
            selectedServicePreview.style.display = 'none';
            document.getElementById('existing_service_id').value = '';
            document.getElementById('price').value = '';
            document.getElementById('priceHelp').innerHTML = '<i class="fas fa-chart-line"></i> Set a competitive price for your service';
        }

        function clearForm() {
            // Clear all form inputs
            document.getElementById('serviceForm').reset();
            
            // Hide previews
            selectedServicePreview.style.display = 'none';
            searchResults.style.display = 'none';
            
            // Clear search
            serviceSearch.value = '';
            
            // Reset price help
            document.getElementById('priceHelp').innerHTML = '<i class="fas fa-chart-line"></i> Set a competitive price for your service';
        }

        function updatePriceHelp(basePrice, category) {
            const price = parseFloat(basePrice);
            const suggested = {
                low: Math.max(price * 0.8, price - 20),
                high: price * 1.3,
                competitive: price * 1.1,
                budget: price * 0.9
            };
            
            // Update suggestions
            updatePriceSuggestions(suggested, category);
            
            document.getElementById('priceHelp').innerHTML = `
                <i class="fas fa-chart-line"></i> Base price: ₱${price.toFixed(2)} • Suggested range: ₱${suggested.low.toFixed(2)} - ₱${suggested.high.toFixed(2)}
            `;
        }

        function updatePriceSuggestions(suggestions, category = '') {
            const suggestionsGrid = document.getElementById('suggestionsGrid');
            const container = document.getElementById('priceSuggestionsContainer');
            
            if (!suggestions) {
                container.style.display = 'none';
                return;
            }
            
            const suggestionButtons = [
                { 
                    price: suggestions.budget, 
                    label: 'Budget Friendly', 
                    icon: 'fas fa-tag', 
                    color: '#56ab2f',
                    description: 'Attract price-conscious customers'
                },
                { 
                    price: suggestions.competitive, 
                    label: 'Competitive', 
                    icon: 'fas fa-balance-scale', 
                    color: '#4facfe',
                    description: 'Balanced pricing strategy'
                },
                { 
                    price: suggestions.high, 
                    label: 'Premium', 
                    icon: 'fas fa-crown', 
                    color: '#f093fb',
                    description: 'Premium service positioning'
                }
            ];
            
            suggestionsGrid.innerHTML = suggestionButtons.map(btn => `
                <button type="button" class="suggestion-btn" onclick="setPriceFromSuggestion(${btn.price.toFixed(2)})" style="--suggestion-color: ${btn.color}">
                    <div class="suggestion-header">
                        <i class="${btn.icon}"></i>
                        <span class="suggestion-label">${btn.label}</span>
                    </div>
                    <div class="suggestion-price">₱${btn.price.toFixed(2)}</div>
                    <div class="suggestion-description">${btn.description}</div>
                </button>
            `).join('');
            
            container.style.display = 'block';
        }

        function setPriceFromSuggestion(price) {
            document.getElementById('price').value = price;
            analyzePrice(price);
            
            // Add visual feedback
            const priceInput = document.getElementById('price');
            priceInput.classList.add('price-updated');
            setTimeout(() => priceInput.classList.remove('price-updated'), 600);
        }

        function analyzePrice(inputPrice) {
            const price = parseFloat(inputPrice);
            const analysis = document.getElementById('priceAnalysis');
            const marketPosition = document.getElementById('marketPosition');
            const competitivenessFill = document.getElementById('competitivenessFill');
            
            if (!price || price <= 0) {
                analysis.style.display = 'none';
                return;
            }
            
            // Get base price for comparison (if available)
            const serviceSelect = document.getElementById('existing_service_id');
            let basePrice = 0;
            
            if (serviceSelect && serviceSelect.value) {
                const selectedOption = serviceSelect.options[serviceSelect.selectedIndex];
                basePrice = parseFloat(selectedOption.getAttribute('data-base-price')) || 0;
            }
            
            let position = 'Custom Pricing';
            let competitiveness = 50; // Default 50%
            
            if (basePrice > 0) {
                const ratio = price / basePrice;
                if (ratio < 0.8) {
                    position = 'Budget Friendly';
                    competitiveness = 85;
                } else if (ratio < 1.1) {
                    position = 'Competitive';
                    competitiveness = 95;
                } else if (ratio < 1.3) {
                    position = 'Premium';
                    competitiveness = 75;
                } else {
                    position = 'High-End';
                    competitiveness = 45;
                }
            } else {
                // For custom services, analyze based on price ranges
                if (price < 100) {
                    position = 'Affordable';
                    competitiveness = 80;
                } else if (price < 300) {
                    position = 'Mid-Range';
                    competitiveness = 90;
                } else if (price < 500) {
                    position = 'Premium';
                    competitiveness = 70;
                } else {
                    position = 'Luxury';
                    competitiveness = 50;
                }
            }
            
            marketPosition.textContent = position;
            competitivenessFill.style.width = competitiveness + '%';
            
            // Color code competitiveness
            if (competitiveness >= 80) {
                competitivenessFill.style.background = 'linear-gradient(90deg, #56ab2f, #a8e6cf)';
            } else if (competitiveness >= 60) {
                competitivenessFill.style.background = 'linear-gradient(90deg, #4facfe, #00f2fe)';
            } else {
                competitivenessFill.style.background = 'linear-gradient(90deg, #f093fb, #f5576c)';
            }
            
            analysis.style.display = 'block';
        }

        function roundPrice(direction) {
            const priceInput = document.getElementById('price');
            let currentPrice = parseFloat(priceInput.value) || 0;
            
            if (currentPrice === 0) return;
            
            let newPrice;
            if (direction === 'up') {
                newPrice = Math.ceil(currentPrice / 50) * 50;
            } else {
                newPrice = Math.floor(currentPrice / 50) * 50;
            }
            
            if (newPrice < 0) newPrice = 50;
            
            priceInput.value = newPrice.toFixed(2);
            analyzePrice(newPrice);
            
            // Visual feedback
            priceInput.classList.add('price-updated');
            setTimeout(() => priceInput.classList.remove('price-updated'), 600);
        }

        // Price input enhancements
        document.getElementById('price').addEventListener('input', function() {
            const value = parseFloat(this.value);
            analyzePrice(value);
            
            if (value && value > 0) {
                // Update help text dynamically
                const helpText = document.getElementById('priceHelp');
                if (value < 50) {
                    helpText.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Consider if this price covers your costs and time';
                    helpText.className = 'form-help dynamic-help warning';
                } else if (value > 1000) {
                    helpText.innerHTML = '<i class="fas fa-info-circle"></i> High-end pricing may limit customer reach';
                    helpText.className = 'form-help dynamic-help info';
                } else {
                    helpText.innerHTML = '<i class="fas fa-check-circle"></i> Good pricing range for most customers';
                    helpText.className = 'form-help dynamic-help success';
                }
            } else {
                document.getElementById('priceHelp').innerHTML = '<i class="fas fa-info-circle"></i> Enter your price to see market analysis and suggestions';
                document.getElementById('priceHelp').className = 'form-help dynamic-help';
            }
        });

        // Round up/down buttons
        document.getElementById('roundUpBtn').addEventListener('click', () => roundPrice('up'));
        document.getElementById('roundDownBtn').addEventListener('click', () => roundPrice('down'));

        // Enhanced service name input
        const serviceNameInput = document.getElementById('custom_service_name');
        const nameCounter = document.getElementById('nameCounter');
        const nameHelp = document.getElementById('nameHelp');
        const nameSuggestions = document.getElementById('nameSuggestions');
        const nameStatus = document.getElementById('nameStatus');
        const nameValidation = document.getElementById('nameValidation');
        const nameStrengthFill = document.getElementById('nameStrengthFill');
        const nameAnalytics = document.getElementById('nameAnalytics');

        if (serviceNameInput) {
            serviceNameInput.addEventListener('input', function() {
                const value = this.value.trim();
                const length = value.length;
                
                // Update counter
                nameCounter.textContent = length;
                
                // Calculate name strength and SEO score
                const analysis = analyzeServiceName(value);
                updateNameAnalytics(analysis);
                
                // Update validation status
                updateNameValidation(analysis.strength);
                
                // Update help text and suggestions
                if (length === 0) {
                    nameHelp.innerHTML = '<i class="fas fa-lightbulb"></i> Use descriptive keywords that customers would search for';
                    nameHelp.className = 'form-help dynamic-help';
                    nameSuggestions.style.display = 'none';
                    nameAnalytics.style.display = 'none';
                    nameStatus.innerHTML = '';
                } else if (length < 10) {
                    nameHelp.innerHTML = '<i class="fas fa-info-circle"></i> Try to be more descriptive (10+ characters recommended)';
                    nameHelp.className = 'form-help dynamic-help info';
                    generateNameSuggestions(value);
                    nameAnalytics.style.display = 'block';
                } else if (length > 80) {
                    nameHelp.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Keep it concise - shorter names are easier to remember';
                    nameHelp.className = 'form-help dynamic-help warning';
                } else {
                    nameHelp.innerHTML = '<i class="fas fa-check-circle"></i> Great! Clear and descriptive name';
                    nameHelp.className = 'form-help dynamic-help success';
                    generateNameSuggestions(value);
                    nameAnalytics.style.display = 'block';
                }
            });

            serviceNameInput.addEventListener('focus', function() {
                if (this.value.length > 0) {
                    generateNameSuggestions(this.value);
                    nameAnalytics.style.display = 'block';
                }
            });

            serviceNameInput.addEventListener('blur', function() {
                setTimeout(() => {
                    if (!document.querySelector('.enhanced-suggestions:hover')) {
                        // Keep analytics visible but hide suggestions after delay
                        setTimeout(() => {
                            if (!serviceNameInput.matches(':focus')) {
                                nameSuggestions.style.display = 'none';
                            }
                        }, 300);
                    }
                }, 100);
            });
        }

        function analyzeServiceName(name) {
            const words = name.toLowerCase().split(/\s+/).filter(w => w.length > 0);
            const keywordScore = calculateKeywordScore(words);
            const clarityScore = calculateClarityScore(name);
            const lengthScore = calculateLengthScore(name.length);
            
            const overallStrength = Math.round((keywordScore + clarityScore + lengthScore) / 3);
            const seoScore = Math.round((keywordScore * 0.4 + clarityScore * 0.3 + lengthScore * 0.3));
            
            return {
                strength: overallStrength,
                seoScore: seoScore,
                clarity: Math.round(clarityScore / 20), // Convert to 1-5 scale
                keywords: words.filter(w => w.length > 3),
                suggestions: generateKeywordSuggestions(words)
            };
        }

        function calculateKeywordScore(words) {
            const serviceKeywords = ['service', 'professional', 'expert', 'quality', 'premium', 'reliable', 'quick', 'affordable', 'certified', 'experienced'];
            const actionWords = ['repair', 'install', 'clean', 'fix', 'maintain', 'design', 'build', 'create', 'provide'];
            
            let score = 0;
            words.forEach(word => {
                if (serviceKeywords.includes(word)) score += 20;
                if (actionWords.includes(word)) score += 15;
                if (word.length > 5) score += 5;
            });
            
            return Math.min(score, 100);
        }

        function calculateClarityScore(name) {
            let score = 50; // Base score
            
            // Bonus for descriptive length
            if (name.length >= 15 && name.length <= 60) score += 20;
            
            // Bonus for multiple relevant words
            const wordCount = name.split(/\s+/).length;
            if (wordCount >= 2 && wordCount <= 5) score += 15;
            
            // Penalty for special characters
            if (/[^a-zA-Z0-9\s-]/.test(name)) score -= 10;
            
            // Bonus for proper capitalization
            if (/^[A-Z]/.test(name)) score += 10;
            
            return Math.max(0, Math.min(score, 100));
        }

        function calculateLengthScore(length) {
            if (length === 0) return 0;
            if (length < 10) return Math.max(20, length * 5);
            if (length <= 50) return 100;
            if (length <= 80) return Math.max(50, 100 - (length - 50) * 2);
            return 30;
        }

        function updateNameAnalytics(analysis) {
            // Update strength bar
            nameStrengthFill.style.width = analysis.strength + '%';
            
            // Color code strength
            if (analysis.strength >= 80) {
                nameStrengthFill.style.background = 'linear-gradient(90deg, #56ab2f, #a8e6cf)';
            } else if (analysis.strength >= 60) {
                nameStrengthFill.style.background = 'linear-gradient(90deg, #4facfe, #00f2fe)';
            } else {
                nameStrengthFill.style.background = 'linear-gradient(90deg, #f093fb, #f5576c)';
            }
            
            // Update SEO score
            const seoScoreFill = document.getElementById('seoScoreFill');
            const seoScoreText = document.getElementById('seoScoreText');
            seoScoreFill.style.width = analysis.seoScore + '%';
            seoScoreText.textContent = analysis.seoScore + '%';
            
            // Update clarity indicators
            const clarityDots = document.querySelectorAll('.clarity-dot');
            clarityDots.forEach((dot, index) => {
                if (index < analysis.clarity) {
                    dot.classList.add('active');
                } else {
                    dot.classList.remove('active');
                }
            });
            
            // Update keyword suggestions
            updateKeywordTags(analysis.suggestions);
        }

        function updateNameValidation(strength) {
            const icon = nameValidation.querySelector('i');
            
            if (strength >= 80) {
                icon.className = 'fas fa-check-circle';
                nameValidation.className = 'validation-icon success';
                nameStatus.innerHTML = '<span class="status-success"><i class="fas fa-check"></i> Excellent</span>';
            } else if (strength >= 60) {
                icon.className = 'fas fa-check-circle';
                nameValidation.className = 'validation-icon good';
                nameStatus.innerHTML = '<span class="status-good"><i class="fas fa-thumbs-up"></i> Good</span>';
            } else if (strength >= 40) {
                icon.className = 'fas fa-exclamation-circle';
                nameValidation.className = 'validation-icon warning';
                nameStatus.innerHTML = '<span class="status-warning"><i class="fas fa-exclamation"></i> Needs work</span>';
            } else if (strength > 0) {
                icon.className = 'fas fa-times-circle';
                nameValidation.className = 'validation-icon error';
                nameStatus.innerHTML = '<span class="status-error"><i class="fas fa-times"></i> Weak</span>';
            } else {
                icon.className = 'fas fa-circle';
                nameValidation.className = 'validation-icon';
                nameStatus.innerHTML = '';
            }
        }

        function generateKeywordSuggestions(words) {
            const serviceTypes = ['Professional', 'Expert', 'Quality', 'Premium', 'Reliable', 'Quick', 'Certified'];
            const actionWords = ['Service', 'Solutions', 'Care', 'Support', 'Specialist', 'Pro', 'Expert'];
            
            return [...serviceTypes.slice(0, 4), ...actionWords.slice(0, 3)];
        }

        function updateKeywordTags(keywords) {
            const keywordContainer = document.getElementById('keywordTags');
            if (keywordContainer && keywords.length > 0) {
                keywordContainer.innerHTML = keywords.map(keyword => 
                    `<button type="button" class="suggestion-tag keyword" onclick="addKeywordToName('${keyword}')">
                        <i class="fas fa-plus"></i> ${keyword}
                    </button>`
                ).join('');
            }
        }

        function addKeywordToName(keyword) {
            const currentName = serviceNameInput.value.trim();
            if (currentName && !currentName.toLowerCase().includes(keyword.toLowerCase())) {
                serviceNameInput.value = keyword + ' ' + currentName;
                serviceNameInput.dispatchEvent(new Event('input'));
                
                // Visual feedback
                serviceNameInput.classList.add('keyword-added');
                setTimeout(() => serviceNameInput.classList.remove('keyword-added'), 600);
            }
        }

        function hideSuggestions(type) {
            if (type === 'name') {
                nameSuggestions.style.display = 'none';
            } else if (type === 'category') {
                document.getElementById('categorySuggestions').style.display = 'none';
            }
        }

        function generateNameSuggestions(input) {
            if (input.length < 3) {
                nameSuggestions.style.display = 'none';
                return;
            }

            const suggestions = [];
            const words = input.toLowerCase().split(' ').filter(word => word.length > 0);
            
            // Generate suggestions based on input
            const prefixes = ['Professional', 'Expert', 'Quality', 'Premium', 'Reliable', 'Quick', 'Affordable'];
            const suffixes = ['Service', 'Solutions', 'Specialist', 'Expert', 'Pro', 'Care', 'Support'];
            
            if (words.length === 1) {
                // Single word input - suggest variations
                const word = words[0];
                suggestions.push(
                    `Professional ${capitalize(word)} Service`,
                    `Expert ${capitalize(word)} Solutions`,
                    `Quality ${capitalize(word)} Care`,
                    `${capitalize(word)} Specialist`
                );
            } else {
                // Multiple words - suggest enhancements
                const basePhrase = words.map(capitalize).join(' ');
                suggestions.push(
                    `Professional ${basePhrase}`,
                    `${basePhrase} Service`,
                    `Expert ${basePhrase}`,
                    `${basePhrase} Solutions`
                );
            }

            displayNameSuggestions(suggestions.slice(0, 4));
        }

        function displayNameSuggestions(suggestions) {
            const tagsContainer = document.getElementById('nameSuggestionTags');
            tagsContainer.innerHTML = suggestions.map(suggestion => 
                `<button type="button" class="suggestion-tag" onclick="applySuggestion('name', '${suggestion}')">
                    ${suggestion}
                </button>`
            ).join('');
            nameSuggestions.style.display = 'block';
        }

        function applySuggestion(type, value) {
            if (type === 'name') {
                document.getElementById('custom_service_name').value = value;
                document.getElementById('nameCounter').textContent = value.length;
                nameSuggestions.style.display = 'none';
                
                // Update help
                nameHelp.innerHTML = '<i class="fas fa-check-circle"></i> Great! Clear and descriptive name';
                nameHelp.className = 'form-help dynamic-help success';
            }
        }

        function capitalize(word) {
            return word.charAt(0).toUpperCase() + word.slice(1);
        }

        // Category selection with enhancements
        const categoryInput = document.getElementById('custom_service_category');
        const categoryStatus = document.getElementById('categoryStatus');
        const categoryValidation = document.getElementById('categoryValidation');
        const categoryType = document.getElementById('categoryType');
        const existingCategories = <?php echo json_encode($existing_categories); ?>;

        if (categoryInput) {
            categoryInput.addEventListener('input', function() {
                const value = this.value.trim();
                updateCategoryStatus(value);
            });

            categoryInput.addEventListener('focus', function() {
                document.getElementById('categorySuggestions').style.display = 'block';
            });
        }

        function updateCategoryStatus(category) {
            const isExisting = existingCategories.includes(category);
            const icon = categoryValidation.querySelector('i');
            
            if (category.length === 0) {
                categoryType.textContent = 'Required';
                categoryType.className = 'category-type required';
                icon.className = 'fas fa-circle';
                categoryValidation.className = 'validation-icon';
                categoryStatus.innerHTML = '';
            } else if (isExisting) {
                categoryType.textContent = 'Existing';
                categoryType.className = 'category-type existing';
                icon.className = 'fas fa-check-circle';
                categoryValidation.className = 'validation-icon success';
                categoryStatus.innerHTML = '<span class="status-success"><i class="fas fa-check"></i> Popular category</span>';
                
                // Update help
                document.getElementById('categoryHelp').innerHTML = `<i class="fas fa-check-circle"></i> Selected: ${category} - Great choice for visibility!`;
                document.getElementById('categoryHelp').className = 'form-help dynamic-help success';
            } else {
                categoryType.textContent = 'New';
                categoryType.className = 'category-type new';
                icon.className = 'fas fa-plus-circle';
                categoryValidation.className = 'validation-icon new';
                categoryStatus.innerHTML = '<span class="status-new"><i class="fas fa-plus"></i> Creating new</span>';
                
                // Update help
                document.getElementById('categoryHelp').innerHTML = `<i class="fas fa-lightbulb"></i> Creating new category: ${category} - Help expand our service offerings!`;
                document.getElementById('categoryHelp').className = 'form-help dynamic-help info';
            }
        }

        function selectCategory(category) {
            categoryInput.value = category;
            updateCategoryStatus(category);
            document.getElementById('categorySuggestions').style.display = 'none';
            
            // Visual feedback
            categoryInput.classList.add('category-selected');
            setTimeout(() => categoryInput.classList.remove('category-selected'), 600);
        }

        function suggestNewCategory(category) {
            categoryInput.value = category;
            updateCategoryStatus(category);
            document.getElementById('categorySuggestions').style.display = 'none';
            
            // Visual feedback
            categoryInput.classList.add('category-created');
            setTimeout(() => categoryInput.classList.remove('category-created'), 600);
        }

        // Enhanced description textarea
        const descriptionTextarea = document.getElementById('custom_service_description');
        const descCounter = document.getElementById('descCounter');
        const descriptionHelp = document.getElementById('descriptionHelp');
        const descriptionStatus = document.getElementById('descriptionStatus');
        const descriptionValidation = document.getElementById('descriptionValidation');
        const descriptionStrengthFill = document.getElementById('descriptionStrengthFill');
        const descriptionAnalytics = document.getElementById('descriptionAnalytics');

        if (descriptionTextarea) {
            descriptionTextarea.addEventListener('input', function() {
                const value = this.value.trim();
                const length = value.length;
                
                // Update counter
                descCounter.textContent = length;
                
                // Analyze description
                const analysis = analyzeDescription(value);
                updateDescriptionAnalytics(analysis);
                updateDescriptionValidation(analysis);
                
                // Update help text and status
                if (length === 0) {
                    descriptionHelp.innerHTML = '<i class="fas fa-info-circle"></i> A good description increases customer confidence and bookings';
                    descriptionHelp.className = 'form-help dynamic-help';
                    descriptionAnalytics.style.display = 'none';
                    descriptionStatus.innerHTML = '';
                } else if (length < 50) {
                    descriptionHelp.innerHTML = '<i class="fas fa-info-circle"></i> Add more details to help customers understand your service';
                    descriptionHelp.className = 'form-help dynamic-help info';
                    descriptionAnalytics.style.display = 'block';
                } else if (length > 400) {
                    descriptionHelp.innerHTML = '<i class="fas fa-exclamation-triangle"></i> Consider keeping it more concise for better readability';
                    descriptionHelp.className = 'form-help dynamic-help warning';
                    descriptionAnalytics.style.display = 'block';
                } else {
                    descriptionHelp.innerHTML = '<i class="fas fa-check-circle"></i> Excellent! Detailed and informative description';
                    descriptionHelp.className = 'form-help dynamic-help success';
                    descriptionAnalytics.style.display = 'block';
                }
            });

            descriptionTextarea.addEventListener('focus', function() {
                if (this.value.length > 0) {
                    descriptionAnalytics.style.display = 'block';
                }
                // Show templates and assistant
                document.getElementById('descriptionTemplates').style.display = 'block';
                document.getElementById('writingAssistant').style.display = 'block';
            });

            descriptionTextarea.addEventListener('blur', function() {
                setTimeout(() => {
                    if (!document.querySelector('.enhanced-templates:hover, .writing-assistant:hover')) {
                        setTimeout(() => {
                            if (!descriptionTextarea.matches(':focus')) {
                                document.getElementById('descriptionTemplates').style.display = 'none';
                                document.getElementById('writingAssistant').style.display = 'none';
                            }
                        }, 300);
                    }
                }, 100);
            });
        }

        function analyzeDescription(text) {
            const words = text.toLowerCase().split(/\s+/).filter(w => w.length > 0);
            const sentences = text.split(/[.!?]+/).filter(s => s.trim().length > 0);
            
            // Calculate metrics
            const readability = calculateReadability(words, sentences);
            const appeal = calculateAppeal(text);
            const keywordScore = calculateDescriptionKeywords(words);
            const readTime = Math.max(1, Math.ceil(words.length / 200 * 60)); // seconds
            
            const overallStrength = Math.round((readability + appeal + keywordScore) / 3);
            
            return {
                strength: overallStrength,
                readability: readability,
                appeal: appeal,
                keywords: keywordScore,
                readTime: readTime,
                wordCount: words.length,
                sentenceCount: sentences.length
            };
        }

        function calculateReadability(words, sentences) {
            if (words.length === 0) return 0;
            
            let score = 50; // Base score
            
            // Ideal word count (50-200 words)
            if (words.length >= 30 && words.length <= 150) score += 20;
            else if (words.length >= 150 && words.length <= 200) score += 10;
            
            // Average sentence length (8-15 words)
            const avgSentenceLength = sentences.length > 0 ? words.length / sentences.length : 0;
            if (avgSentenceLength >= 8 && avgSentenceLength <= 15) score += 15;
            
            // Complex words penalty
            const complexWords = words.filter(word => word.length > 7).length;
            if (complexWords / words.length > 0.3) score -= 10;
            
            return Math.max(0, Math.min(score, 100));
        }

        function calculateAppeal(text) {
            let score = 40; // Base score
            
            // Positive words
            const positiveWords = ['professional', 'quality', 'experienced', 'reliable', 'guaranteed', 'expert', 'certified', 'satisfaction', 'premium', 'excellent'];
            const foundPositive = positiveWords.filter(word => text.toLowerCase().includes(word)).length;
            score += foundPositive * 8;
            
            // Action words
            const actionWords = ['provide', 'ensure', 'deliver', 'guarantee', 'specialize', 'offer', 'include', 'feature'];
            const foundActions = actionWords.filter(word => text.toLowerCase().includes(word)).length;
            score += foundActions * 5;
            
            // Personal touch
            if (/\b(I|my|our|we)\b/i.test(text)) score += 10;
            
            // Benefits mentioned
            if (/\b(benefit|advantage|value|save|improve)\b/i.test(text)) score += 8;
            
            return Math.max(0, Math.min(score, 100));
        }

        function calculateDescriptionKeywords(words) {
            const serviceKeywords = ['service', 'professional', 'quality', 'experience', 'reliable', 'guarantee', 'certified', 'expert', 'satisfaction', 'years'];
            const found = words.filter(word => serviceKeywords.includes(word)).length;
            return Math.min(found * 15, 100);
        }

        function updateDescriptionAnalytics(analysis) {
            // Update strength bar
            descriptionStrengthFill.style.width = analysis.strength + '%';
            
            // Color code strength
            if (analysis.strength >= 80) {
                descriptionStrengthFill.style.background = 'linear-gradient(90deg, #56ab2f, #a8e6cf)';
            } else if (analysis.strength >= 60) {
                descriptionStrengthFill.style.background = 'linear-gradient(90deg, #4facfe, #00f2fe)';
            } else {
                descriptionStrengthFill.style.background = 'linear-gradient(90deg, #f093fb, #f5576c)';
            }
            
            // Update readability score
            const readabilityText = document.getElementById('readabilityText');
            const readabilityScore = Math.round(analysis.readability);
            readabilityText.textContent = readabilityScore;
            
            // Color code readability circle
            const scoreCircle = document.querySelector('.score-circle');
            if (readabilityScore >= 80) {
                scoreCircle.style.borderColor = '#56ab2f';
                scoreCircle.style.color = '#56ab2f';
            } else if (readabilityScore >= 60) {
                scoreCircle.style.borderColor = '#4facfe';
                scoreCircle.style.color = '#4facfe';
            } else {
                scoreCircle.style.borderColor = '#f093fb';
                scoreCircle.style.color = '#f093fb';
            }
            
            // Update appeal stars
            const stars = document.querySelectorAll('.rating-stars i');
            const appealStars = Math.round(analysis.appeal / 20); // Convert to 1-5 scale
            stars.forEach((star, index) => {
                if (index < appealStars) {
                    star.classList.add('active');
                } else {
                    star.classList.remove('active');
                }
            });
            
            // Update keyword count
            document.querySelector('.count-number').textContent = Math.round(analysis.keywords / 15);
            
            // Update read time
            document.querySelector('.time-number').textContent = analysis.readTime;
        }

        function updateDescriptionValidation(analysis) {
            const icon = descriptionValidation.querySelector('i');
            
            if (analysis.strength >= 80) {
                icon.className = 'fas fa-check-circle';
                descriptionValidation.className = 'validation-icon success';
                descriptionStatus.innerHTML = '<span class="status-success"><i class="fas fa-star"></i> Excellent</span>';
            } else if (analysis.strength >= 60) {
                icon.className = 'fas fa-thumbs-up';
                descriptionValidation.className = 'validation-icon good';
                descriptionStatus.innerHTML = '<span class="status-good"><i class="fas fa-check"></i> Good</span>';
            } else if (analysis.strength >= 40) {
                icon.className = 'fas fa-edit';
                descriptionValidation.className = 'validation-icon warning';
                descriptionStatus.innerHTML = '<span class="status-warning"><i class="fas fa-pencil-alt"></i> Improve</span>';
            } else if (analysis.strength > 0) {
                icon.className = 'fas fa-exclamation-circle';
                descriptionValidation.className = 'validation-icon error';
                descriptionStatus.innerHTML = '<span class="status-error"><i class="fas fa-times"></i> Needs work</span>';
            } else {
                icon.className = 'fas fa-edit';
                descriptionValidation.className = 'validation-icon';
                descriptionStatus.innerHTML = '';
            }
        }

        function addPhrase(phrase) {
            const currentText = descriptionTextarea.value.trim();
            const newText = currentText ? currentText + '. ' + phrase : phrase;
            descriptionTextarea.value = newText;
            descriptionTextarea.dispatchEvent(new Event('input'));
            
            // Visual feedback
            descriptionTextarea.classList.add('phrase-added');
            setTimeout(() => descriptionTextarea.classList.remove('phrase-added'), 600);
        }

        function hideTemplates() {
            document.getElementById('descriptionTemplates').style.display = 'none';
        }

        function hideAssistant() {
            document.getElementById('writingAssistant').style.display = 'none';
        }

        // Description templates
        const templates = {
            cleaning: "Professional cleaning service with attention to detail. I use eco-friendly products and ensure thorough cleaning of all areas. Service includes deep cleaning, sanitization, and organization. Satisfaction guaranteed with quality results every time.",
            
            repair: "Expert repair service with years of experience. I diagnose issues quickly and provide reliable, long-lasting solutions. Using quality parts and tools, I ensure your equipment functions properly. Same-day service available for urgent repairs.",
            
            installation: "Professional installation service with proper setup and testing. I handle all aspects of installation including preparation, mounting, connection, and final testing. Clean, efficient work with warranty on installation quality.",
            
            maintenance: "Comprehensive maintenance service to keep your systems running smoothly. Regular inspections, preventive care, and prompt issue resolution. I help extend equipment life and prevent costly breakdowns through proactive maintenance.",
            
            consultation: "Expert consultation service providing professional advice and solutions. I assess your needs, provide detailed recommendations, and create action plans. Get the guidance you need to make informed decisions for your project.",
            
            custom: "Professional service tailored to your specific needs. I bring expertise, reliability, and attention to detail to every job. Committed to delivering quality results that exceed your expectations. Contact me to discuss your requirements."
        };

        function useTemplate(type) {
            const template = templates[type];
            if (template && descriptionTextarea) {
                descriptionTextarea.value = template;
                descCounter.textContent = template.length;
                
                // Update help
                descriptionHelp.innerHTML = '<i class="fas fa-check-circle"></i> Template applied! Feel free to customize it';
                descriptionHelp.className = 'form-help dynamic-help success';
                
                // Highlight the textarea briefly
                descriptionTextarea.classList.add('template-applied');
                setTimeout(() => descriptionTextarea.classList.remove('template-applied'), 1000);
            }
        }

        // Show/hide category suggestions on focus
        document.getElementById('custom_service_category')?.addEventListener('focus', function() {
            document.getElementById('categorySuggestions').style.display = 'block';
        });

        // Hide suggestions when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.category-input-container')) {
                const suggestions = document.getElementById('categorySuggestions');
                if (suggestions) suggestions.style.display = 'none';
            }
            
            if (!e.target.closest('.enhanced-input-container')) {
                if (nameSuggestions) nameSuggestions.style.display = 'none';
            }
        });

        // Form validation
        document.getElementById('serviceForm').addEventListener('submit', function(e) {
            const serviceType = document.getElementById('serviceType').value;
            
            if (serviceType === 'existing') {
                const selectedService = document.getElementById('existing_service_id').value;
                if (!selectedService) {
                    e.preventDefault();
                    alert('Please select a service first.');
                    return;
                }
            } else {
                const serviceName = document.getElementById('custom_service_name').value.trim();
                const serviceCategory = document.getElementById('custom_service_category').value.trim();
                
                if (!serviceName || !serviceCategory) {
                    e.preventDefault();
                    alert('Please fill in the service name and category.');
                    return;
                }
            }
            
            const price = document.getElementById('price').value;
            if (!price || parseFloat(price) <= 0) {
                e.preventDefault();
                alert('Please enter a valid price.');
                return;
            }
        });

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
            
            if (!e.target.closest('.service-search-container')) {
                searchResults.style.display = 'none';
            }
        });
    </script>
</body>
</html>
