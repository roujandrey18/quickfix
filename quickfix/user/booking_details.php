<?php 
require_once '../config/config.php';
checkAccess(['user']);

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: bookings.php');
    exit;
}

$booking_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

// Get user data including profile image
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

try {
    // Get detailed booking information
    $query = "SELECT b.*, s.name as service_name, s.description as service_description,
                     s.category as service_category, s.price as service_price, u.full_name as provider_name,
                     u.phone as provider_phone, u.email as provider_email,
                     u.address as provider_address, u.profile_image as provider_image,
                     r.rating, r.comment as review_comment, r.created_at as review_date
              FROM bookings b 
              JOIN services s ON b.service_id = s.id 
              JOIN users u ON b.provider_id = u.id 
              LEFT JOIN reviews r ON b.id = r.booking_id
              WHERE b.id = :booking_id AND b.user_id = :user_id";
    
    $stmt = $db->prepare($query);
    $stmt->bindParam(':booking_id', $booking_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: bookings.php?error=booking_not_found');
        exit;
    }
    
} catch (Exception $e) {
    header('Location: bookings.php?error=database_error');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - QuickFix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');

        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --secondary-gradient: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            --accent-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --dark-gradient: linear-gradient(135deg, #2c3e50 0%, #3498db 100%);
            --glass-bg: rgba(255, 255, 255, 0.25);
            --glass-border: rgba(255, 255, 255, 0.18);
            --text-dark: #2c3e50;
            --text-light: #ecf0f1;
            --shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.37);
            --shadow-hover: 0 12px 40px 0 rgba(31, 38, 135, 0.5);
            --navbar-height: 65px;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background: var(--primary-gradient);
            min-height: 100vh;
            color: var(--text-dark);
            overflow-x: hidden;
            line-height: 1.6;
            position: relative;
            padding-top: var(--navbar-height);
        }

        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                radial-gradient(circle at 20% 50%, rgba(120, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, rgba(255, 119, 198, 0.3) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(120, 219, 255, 0.3) 0%, transparent 50%);
            z-index: -2;
            animation: backgroundShift 20s ease-in-out infinite;
            pointer-events: none;
        }

        @keyframes backgroundShift {
            0%, 100% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
        }

        .navbar {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-bottom: 1px solid var(--glass-border);
            padding: 0.75rem 0;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            width: 100%;
            height: var(--navbar-height);
            z-index: 1000;
            display: flex;
            align-items: center;
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
            height: 100%;
        }

        .nav-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .logo {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--accent-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-decoration: none;
            z-index: 1001;
            white-space: nowrap;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .nav-links {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .nav-link {
            padding: 0.5rem 1rem;
            border-radius: 10px;
            text-decoration: none;
            color: var(--text-dark);
            font-weight: 500;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-dark);
        }

        .nav-link.active {
            background: var(--accent-gradient);
            color: white;
        }

        .nav-profile {
            position: relative;
        }

        .profile-dropdown .profile-btn {
            background: none;
            border: none;
            display: flex;
            align-items: center;
            gap: 0.4rem;
            padding: 0.4rem;
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.8rem;
        }

        .profile-dropdown .profile-btn:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        .profile-img {
            width: 28px;
            height: 28px;
            border-radius: 50%;
            object-fit: cover;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .dropdown-menu {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--glass-border);
            border-radius: 15px;
            box-shadow: var(--shadow);
            margin-top: 0.5rem;
            min-width: 200px;
            max-width: 250px;
            z-index: 1000;
        }

        .dropdown-item {
            color: var(--text-dark);
            padding: 0.75rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s ease;
        }

        .dropdown-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: var(--text-dark);
        }

        .dropdown-item i {
            width: 16px;
            text-align: center;
            opacity: 0.8;
        }

        .main-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            position: relative;
            z-index: 1;
        }

        .page-header {
            margin-bottom: 2rem;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .back-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            border: 1px solid var(--glass-border);
            border-radius: 50%;
            width: 45px;
            height: 45px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: var(--shadow);
        }

        .back-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: var(--text-dark);
        }

        .header-icon {
            width: 60px;
            height: 60px;
            background: var(--accent-gradient);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            box-shadow: var(--shadow);
        }

        .header-text .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
            line-height: 1.2;
        }

        .header-text .page-subtitle {
            color: var(--text-dark);
            opacity: 0.8;
            margin: 0.5rem 0 0 0;
            font-size: 1rem;
        }

        .booking-details-container {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 2rem;
            align-items: start;
        }

        .glass-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 2rem;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 2;
        }

        .booking-header-card {
            background: var(--accent-gradient);
            color: white;
            border: none;
            margin-bottom: 2rem;
        }

        .booking-header-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .service-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .service-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .service-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .service-category {
            opacity: 0.9;
            font-size: 1rem;
        }

        .booking-status {
            text-align: right;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 15px;
            font-size: 0.9rem;
            font-weight: 600;
            text-transform: uppercase;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            margin-bottom: 0.5rem;
        }

        .booking-amount {
            font-size: 1.8rem;
            font-weight: 700;
            margin-top: 0.5rem;
        }

        .detail-section {
            margin-bottom: 2rem;
            padding-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .detail-section:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
            border-bottom: none;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .section-title i {
            color: #4facfe;
            width: 20px;
        }

        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
        }

        .detail-item {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .detail-item label {
            font-size: 0.85rem;
            color: var(--text-dark);
            opacity: 0.7;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .detail-item span {
            font-size: 1rem;
            color: var(--text-dark);
            font-weight: 500;
        }

        .provider-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .provider-info-header {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .provider-avatar {
            width: 60px;
            height: 60px;
            border-radius: 15px;
            background: var(--accent-gradient);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            object-fit: cover;
        }

        .provider-details h4 {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 0.25rem;
        }

        .provider-details .provider-rating {
            color: #ffc107;
            font-size: 0.9rem;
        }

        .provider-contact {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .contact-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            color: var(--text-dark);
            font-size: 0.95rem;
        }

        .contact-item i {
            color: #4facfe;
            width: 18px;
            font-size: 0.9rem;
        }

        .service-description {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            line-height: 1.7;
        }

        .notes-display {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-left: 4px solid #4facfe;
            font-style: italic;
            line-height: 1.6;
        }

        .review-display {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .review-rating {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }

        .review-rating i {
            color: #ffc107;
            font-size: 1rem;
        }

        .review-rating i:not(.active) {
            color: rgba(255, 193, 7, 0.3);
        }

        .review-comment {
            line-height: 1.6;
            font-style: italic;
            margin-bottom: 1rem;
        }

        .review-date {
            font-size: 0.85rem;
            opacity: 0.7;
            color: var(--text-dark);
        }

        .actions-sidebar {
            position: sticky;
            top: calc(var(--navbar-height) + 2rem);
        }

        .action-card {
            margin-bottom: 1.5rem;
        }

        .action-card h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .action-card h4 i {
            color: #4facfe;
            font-size: 0.9rem;
        }

        .action-buttons {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 12px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 0.95rem;
            text-align: center;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .btn-secondary {
            background: rgba(108, 117, 125, 0.8);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-secondary:hover {
            background: rgba(108, 117, 125, 1);
            transform: translateY(-2px);
            color: white;
        }

        .btn-danger {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #26de81 0%, #20bf6b 100%);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .btn-outline-primary {
            background: transparent;
            color: #4facfe;
            border: 2px solid #4facfe;
        }

        .btn-outline-primary:hover {
            background: #4facfe;
            color: white;
        }

        .quick-info-card {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
        }

        .info-item:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .info-item .label {
            color: var(--text-dark);
            opacity: 0.7;
            font-size: 0.9rem;
        }

        .info-item .value {
            color: var(--text-dark);
            font-weight: 600;
        }

        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .status-pending {
            background: rgba(255, 193, 7, 0.2);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }

        .status-confirmed {
            background: rgba(23, 162, 184, 0.2);
            color: #17a2b8;
            border: 1px solid rgba(23, 162, 184, 0.3);
        }

        .status-in-progress {
            background: rgba(255, 87, 34, 0.2);
            color: #ff5722;
            border: 1px solid rgba(255, 87, 34, 0.3);
        }

        .status-completed {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .status-cancelled {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        @media (max-width: 992px) {
            .booking-details-container {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }

            .actions-sidebar {
                position: static;
            }

            .main-container {
                padding: 1rem;
            }
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .booking-header-content {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .booking-status {
                text-align: left;
                margin-top: 1rem;
            }

            .detail-grid {
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            .provider-info-header {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .nav-links span {
                display: none;
            }

            .page-title {
                font-size: 1.5rem !important;
            }
        }
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-left">
                <a href="../index.php" class="logo">QuickFix</a>
            </div>
            <div class="nav-right">
                <div class="nav-links">
                    <a href="dashboard.php" class="nav-link">
                        <i class="fas fa-tachometer-alt"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="services.php" class="nav-link">
                        <i class="fas fa-search"></i>
                        <span>Services</span>
                    </a>
                    <a href="bookings.php" class="nav-link active">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Bookings</span>
                    </a>
                    <a href="favorites.php" class="nav-link">
                        <i class="fas fa-heart"></i>
                        <span>Favorites</span>
                    </a>
                </div>
                <div class="nav-profile">
                    <div class="dropdown">
                        <button class="profile-btn" type="button" data-bs-toggle="dropdown">
                            <?php if ($user && $user['profile_image']): ?>
                                <img src="../uploads/profile_photos/<?php echo $user['profile_image']; ?>" 
                                     alt="Profile" class="profile-img">
                            <?php else: ?>
                                <img src="../uploads/profile_photos/default.svg" alt="Profile" class="profile-img">
                            <?php endif; ?>
                            <i class="fas fa-chevron-down" style="font-size: 0.8rem; opacity: 0.7;"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="profile.php">
                                <i class="fas fa-user"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <!-- Page Header -->
        <div class="page-header">
            <div class="header-content">
                <div class="header-left">
                    <a href="bookings.php" class="back-btn" title="Back to Bookings">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="header-icon">
                        <i class="fas fa-file-invoice"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="page-title">Booking Details</h1>
                        <p class="page-subtitle">View complete booking information and manage your service</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Details -->
        <div class="booking-details-container">
            <div class="main-content">
                <!-- Booking Header -->
                <div class="glass-container booking-header-card">
                    <div class="booking-header-content">
                        <div class="service-info">
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
                                <h3><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                                <div class="service-category"><?php echo htmlspecialchars($booking['service_category']); ?></div>
                            </div>
                        </div>
                        <div class="booking-status">
                            <div class="status-badge status-<?php echo $booking['status']; ?>">
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
                            </div>
                            <div class="booking-amount">₱<?php echo number_format($booking['total_amount'], 2); ?></div>
                        </div>
                    </div>
                </div>

                <!-- Booking Information -->
                <div class="glass-container">
                    <div class="detail-section">
                        <h4 class="section-title">
                            <i class="fas fa-info-circle"></i>
                            Booking Information
                        </h4>
                        <div class="detail-grid">
                            <div class="detail-item">
                                <label>Booking ID</label>
                                <span>#<?php echo $booking['id']; ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Date & Time</label>
                                <span><?php echo date('M j, Y \a\t g:i A', strtotime($booking['booking_date'] . ' ' . $booking['booking_time'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Service Price</label>
                                <span>₱<?php echo number_format($booking['service_price'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Total Amount</label>
                                <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Booked On</label>
                                <span><?php echo date('M j, Y \a\t g:i A', strtotime($booking['created_at'])); ?></span>
                            </div>
                            <div class="detail-item">
                                <label>Last Updated</label>
                                <span><?php echo date('M j, Y \a\t g:i A', strtotime($booking['updated_at'])); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Provider Information -->
                    <div class="detail-section">
                        <h4 class="section-title">
                            <i class="fas fa-user-tie"></i>
                            Provider Information
                        </h4>
                        <div class="provider-card">
                            <div class="provider-info-header">
                                <?php if ($booking['provider_image']): ?>
                                    <img src="../uploads/profile_photos/<?php echo $booking['provider_image']; ?>" 
                                         alt="Provider" class="provider-avatar">
                                <?php else: ?>
                                    <div class="provider-avatar">
                                        <i class="fas fa-user-circle"></i>
                                    </div>
                                <?php endif; ?>
                                <div class="provider-details">
                                    <h4><?php echo htmlspecialchars($booking['provider_name']); ?></h4>
                                    <?php if ($booking['rating']): ?>
                                        <div class="provider-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star<?php echo $i <= $booking['rating'] ? '' : '-o'; ?>"></i>
                                            <?php endfor; ?>
                                            <span>(<?php echo $booking['rating']; ?>/5)</span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="provider-contact">
                                <div class="contact-item">
                                    <i class="fas fa-phone"></i>
                                    <span><?php echo htmlspecialchars($booking['provider_phone']); ?></span>
                                </div>
                                <div class="contact-item">
                                    <i class="fas fa-envelope"></i>
                                    <span><?php echo htmlspecialchars($booking['provider_email']); ?></span>
                                </div>
                                <?php if (!empty($booking['provider_address'])): ?>
                                    <div class="contact-item">
                                        <i class="fas fa-map-marker-alt"></i>
                                        <span><?php echo htmlspecialchars($booking['provider_address']); ?></span>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Service Description -->
                    <?php if (!empty($booking['service_description'])): ?>
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-clipboard-list"></i>
                                Service Description
                            </h4>
                            <div class="service-description">
                                <?php echo htmlspecialchars($booking['service_description']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Special Notes -->
                    <?php if (!empty($booking['notes'])): ?>
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-sticky-note"></i>
                                Special Notes
                            </h4>
                            <div class="notes-display">
                                <?php echo htmlspecialchars($booking['notes']); ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Review Section -->
                    <?php if ($booking['rating']): ?>
                        <div class="detail-section">
                            <h4 class="section-title">
                                <i class="fas fa-star"></i>
                                Your Review
                            </h4>
                            <div class="review-display">
                                <div class="review-rating">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <i class="fas fa-star<?php echo $i <= $booking['rating'] ? ' active' : ''; ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if (!empty($booking['review_comment'])): ?>
                                    <div class="review-comment">
                                        "<?php echo htmlspecialchars($booking['review_comment']); ?>"
                                    </div>
                                <?php endif; ?>
                                <div class="review-date">
                                    Reviewed on <?php echo date('M j, Y', strtotime($booking['review_date'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Actions Sidebar -->
            <div class="actions-sidebar">
                <!-- Quick Info -->
                <div class="glass-container action-card">
                    <h4><i class="fas fa-info-circle"></i> Quick Info</h4>
                    <div class="quick-info-card">
                        <div class="info-item">
                            <span class="label">Status</span>
                            <span class="value">
                                <span class="status-indicator status-<?php echo $booking['status']; ?>">
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
                            </span>
                        </div>
                        <div class="info-item">
                            <span class="label">Service Date</span>
                            <span class="value"><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Service Time</span>
                            <span class="value"><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                        </div>
                        <div class="info-item">
                            <span class="label">Amount Paid</span>
                            <span class="value">₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Actions -->
                <div class="glass-container action-card">
                    <h4><i class="fas fa-cog"></i> Actions</h4>
                    <div class="action-buttons">
                        <?php if ($booking['status'] === 'pending'): ?>
                            <button onclick="cancelBooking(<?php echo $booking['id']; ?>)" class="btn btn-danger">
                                <i class="fas fa-times"></i> Cancel Booking
                            </button>
                        <?php endif; ?>

                        <?php if ($booking['status'] === 'completed' && !$booking['rating']): ?>
                            <a href="review.php?booking_id=<?php echo $booking['id']; ?>" class="btn btn-primary">
                                <i class="fas fa-star"></i> Leave Review
                            </a>
                        <?php endif; ?>

                        <a href="tel:<?php echo htmlspecialchars($booking['provider_phone']); ?>" class="btn btn-success">
                            <i class="fas fa-phone"></i> Call Provider
                        </a>

                        <a href="mailto:<?php echo htmlspecialchars($booking['provider_email']); ?>" class="btn btn-outline-primary">
                            <i class="fas fa-envelope"></i> Email Provider
                        </a>

                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Bookings
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function cancelBooking(bookingId) {
            if (confirm('Are you sure you want to cancel this booking?')) {
                fetch('cancel_booking.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `booking_id=${bookingId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification('Booking cancelled successfully', 'success');
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showNotification(data.message || 'Error cancelling booking', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showNotification('Network error occurred', 'error');
                });
            }
        }

        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `notification-toast alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.remove();
                }, 5000);
            });
        });
    </script>
</body>
</html>