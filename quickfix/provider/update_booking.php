<?php 
require_once '../config/config.php';
checkAccess(['provider']);

$database = new Database();
$db = $database->getConnection();

$provider_id = $_SESSION['user_id'];

if (!isset($_GET['id']) || !isset($_GET['status'])) {
    $_SESSION['error'] = 'Invalid booking update request.';
    header('Location: bookings.php');
    exit;
}

$booking_id = (int)$_GET['id'];
$new_status = $_GET['status'];

// Validate status
$allowed_statuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($new_status, $allowed_statuses)) {
    $_SESSION['error'] = 'Invalid booking status.';
    header('Location: bookings.php');
    exit;
}

// Verify booking belongs to this provider
$verify_query = "SELECT b.*, s.name as service_name, u.full_name as customer_name, u.email as customer_email 
                FROM bookings b 
                JOIN services s ON b.service_id = s.id 
                JOIN users u ON b.user_id = u.id 
                WHERE b.id = :booking_id AND b.provider_id = :provider_id";
$verify_stmt = $db->prepare($verify_query);
$verify_stmt->bindParam(':booking_id', $booking_id);
$verify_stmt->bindParam(':provider_id', $provider_id);
$verify_stmt->execute();

$booking = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    $_SESSION['error'] = 'Booking not found or access denied.';
    header('Location: bookings.php');
    exit;
}

// Validate status transition
$current_status = $booking['status'];
$valid_transitions = [
    'pending' => ['confirmed', 'cancelled'],
    'confirmed' => ['completed', 'cancelled'],
    'completed' => [], // Cannot change from completed
    'cancelled' => []  // Cannot change from cancelled
];

if (!in_array($new_status, $valid_transitions[$current_status])) {
    $_SESSION['error'] = 'Invalid status transition from ' . $current_status . ' to ' . $new_status . '.';
    header('Location: bookings.php');
    exit;
}

try {
    // Update booking status
    $update_query = "UPDATE bookings SET status = :status WHERE id = :booking_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':status', $new_status);
    $update_stmt->bindParam(':booking_id', $booking_id);
    
    if ($update_stmt->execute()) {
        $rows_affected = $update_stmt->rowCount();
        error_log("Booking update successful. Rows affected: " . $rows_affected);
        
        if ($rows_affected > 0) {
            // Set success message
            $status_messages = [
                'confirmed' => 'Booking confirmed successfully! The customer has been notified.',
                'completed' => 'Booking marked as completed! Great job finishing the service.',
                'cancelled' => 'Booking cancelled. The customer has been notified.'
            ];

            $_SESSION['success'] = $status_messages[$new_status] ?? 'Booking status updated successfully.';
        } else {
            error_log("No rows were affected during booking update. Booking ID: " . $booking_id);
            $_SESSION['error'] = 'No changes were made. Please try again.';
        }
    } else {
        error_log("Failed to execute booking update query.");
        $_SESSION['error'] = 'Failed to update booking status. Please try again.';
    }

    // TODO: Send email/SMS notification to customer
    // This would be implemented with a notification service

} catch (Exception $e) {
    error_log("Booking update error: " . $e->getMessage());
    $_SESSION['error'] = 'Error updating booking status. Please try again.';
}

// Redirect back to bookings
$redirect_url = 'bookings.php';
if (isset($_GET['from']) && $_GET['from'] === 'dashboard') {
    $redirect_url = 'dashboard.php';
}

header('Location: ' . $redirect_url);
exit;
?>
