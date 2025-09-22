<?php
require_once '../config/config.php';
checkAccess(['provider']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $service_id = $input['service_id'] ?? 0;
    $provider_id = $_SESSION['user_id'];
    
    $database = new Database();
    $db = $database->getConnection();
    
    // Toggle availability
    $query = "UPDATE provider_services 
              SET availability = CASE 
                  WHEN availability = 'available' THEN 'unavailable' 
                  ELSE 'available' 
              END 
              WHERE service_id = :service_id AND provider_id = :provider_id";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':service_id', $service_id);
    $stmt->bindParam(':provider_id', $provider_id);
    
    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
}
?>