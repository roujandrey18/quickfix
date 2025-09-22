<?php
require_once '../config/config.php';
checkAccess(['provider', 'admin']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $booking_id = $input['booking_id'] ?? 0;
    $status = $input['status'] ?? '';
    
    $database = new Database();
    $db = $database->getConnection();
    
    $query = "UPDATE bookings SET status = :status WHERE id = :booking_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':status', $status);
    $stmt->bindParam(':booking_id', $booking_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>