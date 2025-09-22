<?php 
require_once '../config/config.php';
checkAccess(['user']);

$database = new Database();
$db = $database->getConnection();

$service_id = $_GET['id'] ?? 0;
$provider_id = $_GET['provider'] ?? 0;
$user_id = $_SESSION['user_id'];

// Get specific service and provider details
if ($provider_id) {
    $query = "SELECT s.*, ps.price, ps.provider_id, u.full_name as provider_name, 
                     u.phone as provider_phone, u.address as provider_address,
                     COALESCE(AVG(r.rating), 0) as avg_rating,
                     COUNT(r.id) as review_count
              FROM services s 
              JOIN provider_services ps ON s.id = ps.service_id 
              JOIN users u ON ps.provider_id = u.id 
              LEFT JOIN reviews r ON ps.provider_id = r.provider_id
              WHERE s.id = :service_id AND ps.provider_id = :provider_id 
              AND s.status = 'active' AND ps.availability = 'available'
              GROUP BY s.id, ps.provider_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':service_id', $service_id);
    $stmt->bindParam(':provider_id', $provider_id);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $providers = $service ? [$service] : [];
} else {
    // Get all providers for this service
    $query = "SELECT s.*, ps.price, ps.provider_id, u.full_name as provider_name, 
                     u.phone as provider_phone, u.address as provider_address,
                     COALESCE(AVG(r.rating), 0) as avg_rating,
                     COUNT(r.id) as review_count
              FROM services s 
              JOIN provider_services ps ON s.id = ps.service_id 
              JOIN users u ON ps.provider_id = u.id 
              LEFT JOIN reviews r ON ps.provider_id = r.provider_id
              WHERE s.id = :service_id AND s.status = 'active' AND ps.availability = 'available'
              GROUP BY s.id, ps.provider_id
              ORDER BY avg_rating DESC, ps.price ASC";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':service_id', $service_id);
    $stmt->execute();
    $providers = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

if (empty($providers)) {
    redirect(SITE_URL . '/user/services.php');
}

$service = $providers[0]; // Get service info from first provider

$error = '';
$success = '';

if ($_POST) {
    $selected_provider_id = $_POST['provider_id'];
    $booking_date = $_POST['booking_date'];
    $booking_time = $_POST['booking_time'];
    $notes = trim($_POST['notes']);
    
    // Find selected provider's details
    $selected_provider = array_filter($providers, function($p) use ($selected_provider_id) {
        return $p['provider_id'] == $selected_provider_id;
    });
    
    if (empty($selected_provider)) {
        $error = 'Invalid provider selected.';
    } elseif (empty($booking_date) || empty($booking_time)) {
        $error = 'Please select both date and time.';
    } elseif (strtotime($booking_date . ' ' . $booking_time) <= time()) {
        $error = 'Please select a future date and time.';
    } else {
        $selected_provider = reset($selected_provider);
        $total_amount = $selected_provider['price'];
        
        // Check if slot is available
        $query = "SELECT COUNT(*) FROM bookings 
                  WHERE provider_id = :provider_id 
                  AND booking_date = :booking_date 
                  AND booking_time = :booking_time 
                  AND status NOT IN ('cancelled')";
        $stmt = $db->prepare($query);
        $stmt->bindParam(':provider_id', $selected_provider_id);
        $stmt->bindParam(':booking_date', $booking_date);
        $stmt->bindParam(':booking_time', $booking_time);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $error = 'This time slot is already booked. Please choose another time.';
        } else {
            // Create booking
            $query = "INSERT INTO bookings (user_id, provider_id, service_id, booking_date, booking_time, total_amount, notes, status) 
                     VALUES (:user_id, :provider_id, :service_id, :booking_date, :booking_time, :total_amount, :notes, 'pending')";
            
            $stmt = $db->prepare($query);
            $stmt->bindParam(':user_id', $user_id);
            $stmt->bindParam(':provider_id', $selected_provider_id);
            $stmt->bindParam(':service_id', $service_id);
            $stmt->bindParam(':booking_date', $booking_date);
            $stmt->bindParam(':booking_time', $booking_time);
            $stmt->bindParam(':total_amount', $total_amount);
            $stmt->bindParam(':notes', $notes);
            
            if ($stmt->execute()) {
                $booking_id = $db->lastInsertId();
                $success = 'Booking request sent successfully! The provider will confirm shortly.';
                
                // You can add email notification here
                // sendBookingNotification($booking_id);
            } else {
                $error = 'Error creating booking. Please try again.';
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
    <title>Book Service - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">
                <i class="fas fa-user"></i> QuickFix
            </a>
            <ul class="nav-menu">
                <li><a href="dashboard.php" class="nav-link">Dashboard</a></li>
                <li><a href="services.php" class="nav-link">Services</a></li>
                <li><a href="bookings.php" class="nav-link">My Bookings</a></li>
                <li><a href="../auth/logout.php" class="btn btn-danger">Logout</a></li>
            </ul>
            <button class="mobile-menu-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
    </nav>

    <div class="main-content">
        <div class="dashboard-container">
            <div class="dashboard-header">
                <h1 class="dashboard-title">
                    <i class="fas fa-calendar-plus"></i> Book Service
                </h1>
                <p class="dashboard-subtitle">Schedule your service appointment</p>
            </div>

            <div class="glass-container" style="max-width: 900px; margin: 0 auto;">
                <!-- Service Info -->
                <div class="service-booking-header">
                    <div class="service-info-display">
                        <div class="service-icon">
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
                        </div>
                        <div class="service-details">
                            <h2><?php echo htmlspecialchars($service['name']); ?></h2>
                            <p class="service-category">
                                <i class="fas fa-tag"></i> <?php echo htmlspecialchars($service['category']); ?>
                            </p>
                            <p class="service-description">
                                <?php echo htmlspecialchars($service['description']); ?>
                            </p>
                        </div>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="notification error show" style="position: relative; top: 0; right: 0; margin-bottom: 1rem; transform: none;">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
                    </div>
                <?php endif; ?>

                <?php if ($success): ?>
                    <div class="notification success show" style="position: relative; top: 0; right: 0; margin-bottom: 1rem; transform: none;">
                        <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                        <div style="margin-top: 1rem; text-align: center;">
                            <a href="dashboard.php" class="btn btn-secondary">Go to Dashboard</a>
                            <a href="bookings.php" class="btn btn-primary">View Bookings</a>
                        </div>
                    </div>
                <?php else: ?>
                
                <form method="POST" class="booking-form">
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-user-tie"></i> Select Provider
                        </h3>
                        
                        <div class="providers-grid">
                            <?php foreach ($providers as $provider): ?>
                            <label class="provider-option">
                                <input type="radio" name="provider_id" value="<?php echo $provider['provider_id']; ?>" 
                                       <?php echo count($providers) === 1 ? 'checked' : ''; ?> required>
                                <div class="provider-card">
                                    <div class="provider-header">
                                        <div class="provider-avatar">
                                            <i class="fas fa-user-circle"></i>
                                        </div>
                                        <div class="provider-info">
                                            <h4><?php echo htmlspecialchars($provider['provider_name']); ?></h4>
                                            <div class="provider-rating">
                                                <?php 
                                                $rating = round($provider['avg_rating']);
                                                for ($i = 1; $i <= 5; $i++): 
                                                ?>
                                                <i class="fas fa-star <?php echo $i <= $rating ? 'active' : ''; ?>"></i>
                                                <?php endfor; ?>
                                                <span>(<?php echo number_format($provider['avg_rating'], 1); ?>)</span>
                                            </div>
                                            <p class="provider-contact">
                                                <i class="fas fa-phone"></i> <?php echo htmlspecialchars($provider['provider_phone']); ?>
                                            </p>
                                        </div>
                                        <div class="provider-price">
                                            ₱<?php echo number_format($provider['price'], 2); ?>
                                        </div>
                                    </div>
                                    <div class="provider-stats">
                                        <span><i class="fas fa-star"></i> <?php echo $provider['review_count']; ?> reviews</span>
                                        <span><i class="fas fa-map-marker-alt"></i> Available in your area</span>
                                    </div>
                                </div>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-calendar-alt"></i> Schedule Appointment
                        </h3>
                        
                        <div class="datetime-selection">
                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-calendar"></i> Select Date
                                </label>
                                <input type="date" name="booking_date" class="form-input" required 
                                       min="<?php echo date('Y-m-d'); ?>" 
                                       max="<?php echo date('Y-m-d', strtotime('+3 months')); ?>">
                            </div>

                            <div class="form-group">
                                <label class="form-label">
                                    <i class="fas fa-clock"></i> Select Time
                                </label>
                                <select name="booking_time" class="form-input" required>
                                    <option value="">Choose time...</option>
                                    <?php 
                                    for ($hour = 8; $hour <= 18; $hour++) {
                                        for ($minute = 0; $minute < 60; $minute += 30) {
                                            $time = sprintf("%02d:%02d", $hour, $minute);
                                            $display_time = date('g:i A', strtotime($time));
                                            echo "<option value=\"$time\">$display_time</option>";
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="fas fa-sticky-note"></i> Additional Information
                        </h3>
                        
                        <div class="form-group">
                            <label class="form-label">Special Requests or Notes (Optional)</label>
                            <textarea name="notes" class="form-input" rows="4" 
                                      placeholder="Please describe any specific requirements, access instructions, or special requests..."></textarea>
                        </div>
                    </div>

                    <div class="booking-summary">
                        <div class="summary-content">
                            <h3><i class="fas fa-receipt"></i> Booking Summary</h3>
                            <div class="summary-details">
                                <div class="summary-row">
                                    <span>Service:</span>
                                    <span><?php echo htmlspecialchars($service['name']); ?></span>
                                </div>
                                <div class="summary-row" id="selected-provider">
                                    <span>Provider:</span>
                                    <span>Select a provider above</span>
                                </div>
                                <div class="summary-row" id="selected-datetime">
                                    <span>Date & Time:</span>
                                    <span>Select date and time above</span>
                                </div>
                                <div class="summary-row total" id="total-amount">
                                    <span>Total Amount:</span>
                                    <span>₱0.00</span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary btn-large">
                            <i class="fas fa-calendar-check"></i> Confirm Booking
                        </button>
                        <a href="services.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Services
                        </a>
                    </div>
                </form>

                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="../assets/js/main.js"></script>
    <script>
        // Provider selection functionality
        const providerRadios = document.querySelectorAll('input[name="provider_id"]');
        const providers = <?php echo json_encode($providers); ?>;
        
        providerRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // Update visual selection
                document.querySelectorAll('.provider-option').forEach(option => {
                    option.classList.remove('selected');
                });
                this.closest('.provider-option').classList.add('selected');
                
                // Update summary
                const selectedProvider = providers.find(p => p.provider_id == this.value);
                if (selectedProvider) {
                    document.getElementById('selected-provider').innerHTML = `
                        <span>Provider:</span>
                        <span>${selectedProvider.provider_name}</span>
                    `;
                    document.getElementById('total-amount').innerHTML = `
                        <span>Total Amount:</span>
                        <span>₱${parseFloat(selectedProvider.price).toFixed(2)}</span>
                    `;
                }
            });
        });
        
        // Date and time selection
        document.querySelector('input[name="booking_date"]').addEventListener('change', updateSummary);
        document.querySelector('select[name="booking_time"]').addEventListener('change', updateSummary);
        
        function updateSummary() {
            const date = document.querySelector('input[name="booking_date"]').value;
            const time = document.querySelector('select[name="booking_time"]').value;
            
            if (date && time) {
                const dateObj = new Date(date + 'T' + time);
                const formattedDate = dateObj.toLocaleDateString('en-US', { 
                    weekday: 'long', 
                    year: 'numeric', 
                    month: 'long', 
                    day: 'numeric' 
                });
                const formattedTime = dateObj.toLocaleTimeString('en-US', { 
                    hour: 'numeric', 
                    minute: '2-digit',
                    hour12: true 
                });
                
                document.getElementById('selected-datetime').innerHTML = `
                    <span>Date & Time:</span>
                    <span>${formattedDate} at ${formattedTime}</span>
                `;
            }
        }
        
        // Initialize if only one provider
        if (providerRadios.length === 1) {
            providerRadios[0].closest('.provider-option').classList.add('selected');
            providerRadios[0].dispatchEvent(new Event('change'));
        }
    </script>
</body>
</html>