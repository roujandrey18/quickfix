<?php
require_once '../config/config.php';
checkAccess(['user']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['booking_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing booking ID']);
    exit;
}

$booking_id = (int)$input['booking_id'];
$user_id = $_SESSION['user_id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Verify booking belongs to user and can be cancelled
    $check_query = "SELECT status, booking_date, booking_time FROM bookings 
                    WHERE id = :booking_id AND user_id = :user_id";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':booking_id', $booking_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $booking = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$booking) {
        echo json_encode(['success' => false, 'error' => 'Booking not found']);
        exit;
    }
    
    if ($booking['status'] !== 'pending' && $booking['status'] !== 'confirmed') {
        echo json_encode(['success' => false, 'error' => 'Cannot cancel this booking']);
        exit;
    }
    
    // Check if booking is at least 2 hours in the future
    $booking_datetime = strtotime($booking['booking_date'] . ' ' . $booking['booking_time']);
    $current_time = time();
    $time_difference = $booking_datetime - $current_time;
    
    if ($time_difference < 7200) { // Less than 2 hours
        echo json_encode(['success' => false, 'error' => 'Cannot cancel booking less than 2 hours before appointment']);
        exit;
    }
    
    // Update booking status to cancelled
    $update_query = "UPDATE bookings SET status = 'cancelled' WHERE id = :booking_id";
    $stmt = $db->prepare($update_query);
    $stmt->bindParam(':booking_id', $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Booking cancelled successfully']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to cancel booking']);
    }

} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error occurred']);
}
?>