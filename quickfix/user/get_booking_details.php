<?php
require_once '../config/config.php';
checkAccess(['user']);

header('Content-Type: application/json');

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid booking ID']);
    exit;
}

$booking_id = (int)$_GET['id'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Get detailed booking information
    $query = "SELECT b.*, s.name as service_name, s.description as service_description,
                     s.category as service_category, u.full_name as provider_name,
                     u.phone as provider_phone, u.email as provider_email,
                     u.address as provider_address, r.rating, r.comment as review_comment
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
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    
    // Generate HTML for modal
    $status_class = 'status-' . $booking['status'];
    $status_icon = match($booking['status']) {
        'pending' => 'clock',
        'confirmed' => 'check-circle',
        'in_progress' => 'spinner',
        'completed' => 'check-double',
        'cancelled' => 'times-circle',
        default => 'question-circle'
    };
    
    $html = '
    <div class="booking-details-modal">
        <div class="detail-header">
            <div class="service-info-modal">
                <div class="service-icon">
                    <i class="fas fa-' . match(strtolower($booking['service_category'])) {
                        'cleaning' => 'broom',
                        'maintenance' => 'wrench',
                        'outdoor' => 'leaf',
                        'electrical' => 'bolt',
                        'plumbing' => 'water',
                        default => 'tools'
                    } . '"></i>
                </div>
                <div>
                    <h4>' . htmlspecialchars($booking['service_name']) . '</h4>
                    <p>' . htmlspecialchars($booking['service_category']) . '</p>
                </div>
            </div>
            <span class="status-badge ' . $status_class . '">
                <i class="fas fa-' . $status_icon . '"></i>
                ' . ucfirst(str_replace('_', ' ', $booking['status'])) . '
            </span>
        </div>
        
        <div class="detail-body">
            <div class="detail-section">
                <h5><i class="fas fa-info-circle"></i> Booking Information</h5>
                <div class="detail-grid">
                    <div class="detail-item">
                        <label>Booking ID</label>
                        <span>#' . $booking['id'] . '</span>
                    </div>
                    <div class="detail-item">
                        <label>Date & Time</label>
                        <span>' . date('M j, Y \a\t g:i A', strtotime($booking['booking_date'] . ' ' . $booking['booking_time'])) . '</span>
                    </div>
                    <div class="detail-item">
                        <label>Amount</label>
                        <span>₱' . number_format($booking['total_amount'], 2) . '</span>
                    </div>
                    <div class="detail-item">
                        <label>Booked On</label>
                        <span>' . date('M j, Y \a\t g:i A', strtotime($booking['created_at'])) . '</span>
                    </div>
                </div>
            </div>
            
            <div class="detail-section">
                <h5><i class="fas fa-user-tie"></i> Provider Information</h5>
                <div class="provider-detail-card">
                    <div class="provider-avatar-small">
                        <i class="fas fa-user-circle"></i>
                    </div>
                    <div class="provider-info-modal">
                        <h6>' . htmlspecialchars($booking['provider_name']) . '</h6>
                        <p><i class="fas fa-phone"></i> ' . htmlspecialchars($booking['provider_phone']) . '</p>
                        <p><i class="fas fa-envelope"></i> ' . htmlspecialchars($booking['provider_email']) . '</p>
                        ' . (!empty($booking['provider_address']) ? '<p><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($booking['provider_address']) . '</p>' : '') . '
                    </div>
                </div>
            </div>';
    
    if (!empty($booking['service_description'])) {
        $html .= '
            <div class="detail-section">
                <h5><i class="fas fa-clipboard-list"></i> Service Description</h5>
                <p>' . htmlspecialchars($booking['service_description']) . '</p>
            </div>';
    }
    
    if (!empty($booking['notes'])) {
        $html .= '
            <div class="detail-section">
                <h5><i class="fas fa-sticky-note"></i> Special Notes</h5>
                <div class="notes-display">
                    <p>' . htmlspecialchars($booking['notes']) . '</p>
                </div>
            </div>';
    }
    
    if ($booking['rating']) {
        $html .= '
            <div class="detail-section">
                <h5><i class="fas fa-star"></i> Your Review</h5>
                <div class="review-display">
                    <div class="review-rating-modal">';
        
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<i class="fas fa-star' . ($i <= $booking['rating'] ? ' active' : '') . '"></i>';
        }
        
        $html .= '
                    </div>
                    ' . (!empty($booking['review_comment']) ? '<p>' . htmlspecialchars($booking['review_comment']) . '</p>' : '') . '
                </div>
            </div>';
    }
    
    $html .= '
        </div>
        
        <div class="detail-footer">
            <div class="detail-actions">';
    
    if ($booking['status'] === 'pending') {
        $html .= '<button onclick="cancelBooking(' . $booking['id'] . '); closeBookingModal();" class="btn btn-danger">
                    <i class="fas fa-times"></i> Cancel Booking
                  </button>';
    }
    
    if ($booking['status'] === 'completed' && !$booking['rating']) {
        $html .= '<a href="review.php?booking_id=' . $booking['id'] . '" class="btn btn-primary">
                    <i class="fas fa-star"></i> Leave Review
                  </a>';
    }
    
    $html .= '
                <a href="tel:' . htmlspecialchars($booking['provider_phone']) . '" class="btn btn-secondary">
                    <i class="fas fa-phone"></i> Contact Provider
                </a>
            </div>
        </div>
    </div>';
    
    echo json_encode([
        'success' => true,
        'html' => $html
    ]);

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>