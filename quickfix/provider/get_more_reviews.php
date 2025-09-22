<?php
require_once '../config/config.php';
checkAccess(['provider']);

header('Content-Type: application/json');

$provider_id = $_SESSION['user_id'];
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = 5; // Load 5 more reviews at a time

$database = new Database();
$db = $database->getConnection();

try {
    // Get more reviews with offset
    $reviews_query = "SELECT r.*, u.full_name as user_name, u.profile_image as user_image,
                             b.booking_date, b.total_amount,
                             s.name as service_name, s.category as service_category
                      FROM reviews r
                      JOIN users u ON r.user_id = u.id
                      JOIN bookings b ON r.booking_id = b.id
                      JOIN services s ON b.service_id = s.id
                      WHERE r.provider_id = :provider_id
                      ORDER BY r.created_at DESC
                      LIMIT :limit OFFSET :offset";
    
    $reviews_stmt = $db->prepare($reviews_query);
    $reviews_stmt->bindParam(':provider_id', $provider_id);
    $reviews_stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $reviews_stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $reviews_stmt->execute();
    
    $reviews = $reviews_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if there are more reviews
    $total_query = "SELECT COUNT(*) as total FROM reviews WHERE provider_id = :provider_id";
    $total_stmt = $db->prepare($total_query);
    $total_stmt->bindParam(':provider_id', $provider_id);
    $total_stmt->execute();
    $total_count = $total_stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    $has_more = ($offset + $limit) < $total_count;
    
    // Generate HTML for new reviews
    $html = '';
    foreach ($reviews as $review) {
        $html .= '<div class="review-item">
                    <div class="review-header">
                        <div class="reviewer-info">
                            <div class="reviewer-avatar">';
        
        if ($review['user_image']) {
            $html .= '<img src="../uploads/profile_photos/' . htmlspecialchars($review['user_image']) . '" 
                         alt="Reviewer" class="avatar-img">';
        } else {
            $html .= '<i class="fas fa-user-circle"></i>';
        }
        
        $html .= '</div>
                    <div class="reviewer-details">
                        <h5>' . htmlspecialchars($review['user_name']) . '</h5>
                        <div class="service-info">
                            <span class="service-name">' . htmlspecialchars($review['service_name']) . '</span>
                            <span class="service-date">' . date('M j, Y', strtotime($review['booking_date'])) . '</span>
                        </div>
                    </div>
                </div>
                <div class="review-meta">
                    <div class="review-rating">';
        
        for ($i = 1; $i <= 5; $i++) {
            $html .= '<i class="fas fa-star' . ($i <= $review['rating'] ? ' active' : '') . '"></i>';
        }
        
        $html .= '</div>
                    <div class="review-date">' . date('M j, Y', strtotime($review['created_at'])) . '</div>
                </div>
            </div>';
        
        if (!empty($review['comment'])) {
            $html .= '<div class="review-content">
                        <p>"' . htmlspecialchars($review['comment']) . '"</p>
                    </div>';
        }
        
        $html .= '<div class="review-footer">
                    <div class="booking-amount">
                        Service Amount: ₱' . number_format($review['total_amount'], 2) . '
                    </div>
                </div>
            </div>';
    }
    
    echo json_encode([
        'success' => true,
        'reviews' => $reviews,
        'html' => $html,
        'has_more' => $has_more,
        'total' => $total_count,
        'loaded' => count($reviews)
    ]);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error loading reviews',
        'details' => $e->getMessage()
    ]);
}
?>