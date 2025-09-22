<?php 
require_once '../config/config.php';
checkAccess(['user']);

if (!isset($_GET['booking_id']) || !is_numeric($_GET['booking_id'])) {
    header('Location: bookings.php?error=invalid_booking');
    exit;
}

$booking_id = (int)$_GET['booking_id'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

// Handle review submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rating = (int)$_POST['rating'];
    $comment = trim($_POST['comment']);
    
    // Validate rating
    if ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } else {
        try {
            // Check if booking exists and belongs to user and is completed
            $check_query = "SELECT b.*, u.full_name as provider_name, s.name as service_name, s.category
                           FROM bookings b 
                           JOIN users u ON b.provider_id = u.id 
                           JOIN services s ON b.service_id = s.id
                           WHERE b.id = :booking_id AND b.user_id = :user_id AND b.status = 'completed'";
            $check_stmt = $db->prepare($check_query);
            $check_stmt->bindParam(':booking_id', $booking_id);
            $check_stmt->bindParam(':user_id', $user_id);
            $check_stmt->execute();
            
            $booking = $check_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$booking) {
                $error = "Invalid booking or booking is not completed yet.";
            } else {
                // Check if review already exists
                $existing_review_query = "SELECT id FROM reviews WHERE booking_id = :booking_id";
                $existing_review_stmt = $db->prepare($existing_review_query);
                $existing_review_stmt->bindParam(':booking_id', $booking_id);
                $existing_review_stmt->execute();
                
                if ($existing_review_stmt->fetch()) {
                    $error = "You have already reviewed this booking.";
                } else {
                    // Insert review
                    $insert_query = "INSERT INTO reviews (booking_id, user_id, provider_id, rating, comment) 
                                   VALUES (:booking_id, :user_id, :provider_id, :rating, :comment)";
                    $insert_stmt = $db->prepare($insert_query);
                    $insert_stmt->bindParam(':booking_id', $booking_id);
                    $insert_stmt->bindParam(':user_id', $user_id);
                    $insert_stmt->bindParam(':provider_id', $booking['provider_id']);
                    $insert_stmt->bindParam(':rating', $rating);
                    $insert_stmt->bindParam(':comment', $comment);
                    
                    if ($insert_stmt->execute()) {
                        $success = "Review submitted successfully!";
                        // Redirect to booking details or bookings page after a delay
                        header("refresh:2;url=booking_details.php?id=" . $booking_id);
                    } else {
                        $error = "Error submitting review. Please try again.";
                    }
                }
            }
        } catch (Exception $e) {
            $error = "Database error occurred. Please try again.";
        }
    }
}

// Get user data including profile image
$user_query = "SELECT * FROM users WHERE id = :user_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':user_id', $user_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

// Get booking details for display
try {
    $booking_query = "SELECT b.*, s.name as service_name, s.category as service_category,
                             u.full_name as provider_name, u.profile_image as provider_image,
                             r.rating as existing_rating, r.comment as existing_comment
                      FROM bookings b 
                      JOIN services s ON b.service_id = s.id 
                      JOIN users u ON b.provider_id = u.id 
                      LEFT JOIN reviews r ON b.id = r.booking_id
                      WHERE b.id = :booking_id AND b.user_id = :user_id";
    
    $booking_stmt = $db->prepare($booking_query);
    $booking_stmt->bindParam(':booking_id', $booking_id);
    $booking_stmt->bindParam(':user_id', $user_id);
    $booking_stmt->execute();
    
    $booking = $booking_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        header('Location: bookings.php?error=booking_not_found');
        exit;
    }
    
    // Check if booking is completed
    if ($booking['status'] !== 'completed') {
        header('Location: bookings.php?error=booking_not_completed');
        exit;
    }
    
    // Check if already reviewed
    $already_reviewed = !empty($booking['existing_rating']);
    
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
    <title>Leave Review - QuickFix</title>
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
            max-width: 800px;
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

        .glass-container {
            background: var(--glass-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            border: 1px solid var(--glass-border);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow);
            position: relative;
            z-index: 2;
        }

        .booking-summary {
            background: var(--accent-gradient);
            color: white;
            border: none;
            margin-bottom: 2rem;
        }

        .booking-info {
            display: flex;
            align-items: center;
            gap: 1.5rem;
            flex-wrap: wrap;
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

        .booking-details h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .booking-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .provider-info {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }

        .provider-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            object-fit: cover;
        }

        .provider-details h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
        }

        .provider-details p {
            opacity: 0.8;
            margin: 0;
            font-size: 0.9rem;
        }

        .review-form {
            text-align: center;
        }

        .form-section {
            margin-bottom: 2rem;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--text-dark);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }

        .section-title i {
            color: #4facfe;
        }

        .rating-input {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .star-input {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .star-input:hover,
        .star-input.active {
            color: #ffc107;
            transform: scale(1.1);
        }

        .star-input:hover ~ .star-input {
            color: #ddd;
            transform: scale(1);
        }

        .rating-label {
            text-align: center;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            font-weight: 500;
            color: var(--text-dark);
            min-height: 1.5rem;
        }

        .form-group {
            text-align: left;
            margin-bottom: 2rem;
        }

        .form-label {
            display: block;
            color: var(--text-dark);
            font-weight: 600;
            margin-bottom: 0.75rem;
            font-size: 1rem;
        }

        .form-control {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            color: var(--text-dark);
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            padding: 1rem;
            width: 100%;
            resize: vertical;
            min-height: 120px;
        }

        .form-control:focus {
            background: rgba(255, 255, 255, 0.15);
            border-color: #4facfe;
            box-shadow: 0 0 0 0.2rem rgba(79, 172, 254, 0.25);
            color: var(--text-dark);
            outline: none;
        }

        .form-control::placeholder {
            color: rgba(44, 62, 80, 0.6);
        }

        .btn {
            padding: 0.75rem 2rem;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            font-size: 1rem;
            min-width: 150px;
        }

        .btn-primary {
            background: var(--accent-gradient);
            color: white;
            box-shadow: var(--shadow);
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: var(--shadow-hover);
            color: white;
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }

        .alert {
            padding: 1rem 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .existing-review {
            text-align: center;
            padding: 2rem;
        }

        .existing-rating {
            display: flex;
            justify-content: center;
            gap: 0.25rem;
            margin-bottom: 1rem;
        }

        .existing-rating i {
            font-size: 2rem;
            color: #ffc107;
        }

        .existing-rating i:not(.active) {
            color: rgba(255, 193, 7, 0.3);
        }

        .existing-comment {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
            font-style: italic;
            line-height: 1.6;
            margin: 1.5rem 0;
        }

        .review-date {
            opacity: 0.7;
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                align-items: flex-start;
            }

            .booking-info {
                flex-direction: column;
                align-items: flex-start;
                text-align: left;
            }

            .booking-meta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .main-container {
                padding: 1rem;
            }

            .star-input {
                font-size: 2rem;
            }

            .form-actions {
                flex-direction: column;
                align-items: center;
            }

            .btn {
                width: 100%;
                max-width: 300px;
            }

            .nav-links span {
                display: none;
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
                    <a href="booking_details.php?id=<?php echo $booking_id; ?>" class="back-btn" title="Back to Booking Details">
                        <i class="fas fa-arrow-left"></i>
                    </a>
                    <div class="header-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="header-text">
                        <h1 class="page-title"><?php echo $already_reviewed ? 'Your Review' : 'Leave a Review'; ?></h1>
                        <p class="page-subtitle">
                            <?php echo $already_reviewed ? 'View your review for this service' : 'Rate your experience and help others make informed decisions'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Booking Summary -->
        <div class="glass-container booking-summary">
            <div class="booking-info">
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
                <div class="booking-details">
                    <h3><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                    <div class="booking-meta">
                        <div class="meta-item">
                            <i class="fas fa-calendar"></i>
                            <span><?php echo date('M j, Y', strtotime($booking['booking_date'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-clock"></i>
                            <span><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-dollar-sign"></i>
                            <span>₱<?php echo number_format($booking['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    <div class="provider-info">
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
                            <p>Service Provider</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Review Content -->
        <?php if ($already_reviewed): ?>
            <!-- Existing Review Display -->
            <div class="glass-container">
                <div class="existing-review">
                    <h3 class="section-title">
                        <i class="fas fa-check-circle"></i>
                        Review Already Submitted
                    </h3>
                    
                    <div class="existing-rating">
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <i class="fas fa-star<?php echo $i <= $booking['existing_rating'] ? ' active' : ''; ?>"></i>
                        <?php endfor; ?>
                    </div>
                    
                    <p class="rating-label">
                        <?php 
                        $rating_text = match($booking['existing_rating']) {
                            1 => 'Poor',
                            2 => 'Fair',
                            3 => 'Good',
                            4 => 'Very Good',
                            5 => 'Excellent',
                            default => 'No Rating'
                        };
                        echo $rating_text . ' (' . $booking['existing_rating'] . '/5)';
                        ?>
                    </p>
                    
                    <?php if (!empty($booking['existing_comment'])): ?>
                        <div class="existing-comment">
                            "<?php echo htmlspecialchars($booking['existing_comment']); ?>"
                        </div>
                    <?php endif; ?>
                    
                    <p class="review-date">
                        Thank you for your review! It helps other users make informed decisions.
                    </p>
                    
                    <div class="form-actions">
                        <a href="booking_details.php?id=<?php echo $booking_id; ?>" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Booking
                        </a>
                        <a href="bookings.php" class="btn btn-secondary">
                            <i class="fas fa-list"></i> View All Bookings
                        </a>
                    </div>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Review Form -->
            <div class="glass-container">
                <?php if (isset($success)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i>
                        <?php echo $success; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i>
                        <?php echo $error; ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" class="review-form" id="reviewForm">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-star"></i>
                            Rate Your Experience
                        </h3>
                        
                        <div class="rating-input" id="ratingInput">
                            <input type="hidden" name="rating" id="selectedRating" value="0">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <i class="fas fa-star star-input" data-rating="<?php echo $i; ?>"></i>
                            <?php endfor; ?>
                        </div>
                        
                        <div class="rating-label" id="ratingLabel">Please select a rating</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="comment" class="form-label">
                            <i class="fas fa-comment"></i> Write Your Review (Optional)
                        </label>
                        <textarea 
                            name="comment" 
                            id="comment" 
                            class="form-control"
                            placeholder="Share your experience with this service provider. What did you like? How was the quality of work? Would you recommend them to others?"
                            maxlength="1000"
                        ><?php echo isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : ''; ?></textarea>
                        <small class="form-text" style="opacity: 0.7; font-size: 0.85rem; margin-top: 0.5rem; display: block;">
                            <i class="fas fa-info-circle"></i> Your review will help other users choose quality service providers.
                        </small>
                    </div>
                    
                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="submitBtn" disabled>
                            <i class="fas fa-star"></i> Submit Review
                        </button>
                        <a href="booking_details.php?id=<?php echo $booking_id; ?>" class="btn btn-secondary">
                            <i class="fas fa-times"></i> Cancel
                        </a>
                    </div>
                </form>
            </div>
        <?php endif; ?>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        <?php if (!$already_reviewed): ?>
        // Rating functionality
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-input');
            const ratingInput = document.getElementById('selectedRating');
            const ratingLabel = document.getElementById('ratingLabel');
            const submitBtn = document.getElementById('submitBtn');
            
            const ratingTexts = {
                0: 'Please select a rating',
                1: 'Poor - Very unsatisfied with the service',
                2: 'Fair - Service was below expectations',
                3: 'Good - Service met expectations',
                4: 'Very Good - Service exceeded expectations',
                5: 'Excellent - Outstanding service!'
            };
            
            stars.forEach((star, index) => {
                star.addEventListener('mouseenter', function() {
                    highlightStars(index + 1);
                });
                
                star.addEventListener('click', function() {
                    const rating = parseInt(this.dataset.rating);
                    selectRating(rating);
                });
            });
            
            document.getElementById('ratingInput').addEventListener('mouseleave', function() {
                const currentRating = parseInt(ratingInput.value);
                highlightStars(currentRating);
            });
            
            function highlightStars(count) {
                stars.forEach((star, index) => {
                    if (index < count) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }
            
            function selectRating(rating) {
                ratingInput.value = rating;
                ratingLabel.textContent = ratingTexts[rating];
                highlightStars(rating);
                
                // Enable submit button if rating is selected
                if (rating > 0) {
                    submitBtn.disabled = false;
                } else {
                    submitBtn.disabled = true;
                }
            }
            
            // Form validation
            document.getElementById('reviewForm').addEventListener('submit', function(e) {
                const rating = parseInt(ratingInput.value);
                if (rating < 1 || rating > 5) {
                    e.preventDefault();
                    alert('Please select a rating before submitting your review.');
                    return false;
                }
            });
        });
        <?php endif; ?>
        
        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        alert.remove();
                    }, 300);
                }, 5000);
            });
        });
    </script>
</body>
</html>