<?php
require_once '../config/config.php';
checkAccess(['user']);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['service_id']) || !isset($input['provider_id'])) {
    echo json_encode(['success' => false, 'error' => 'Missing required parameters']);
    exit;
}

$user_id = $_SESSION['user_id'];
$service_id = (int)$input['service_id'];
$provider_id = (int)$input['provider_id'];

$database = new Database();
$db = $database->getConnection();

try {
    // Create favorites table if it doesn't exist
    $create_table = "CREATE TABLE IF NOT EXISTS user_favorites (
        id INT PRIMARY KEY AUTO_INCREMENT,
        user_id INT NOT NULL,
        service_id INT NOT NULL,
        provider_id INT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_favorite (user_id, service_id, provider_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE,
        FOREIGN KEY (provider_id) REFERENCES users(id) ON DELETE CASCADE
    )";
    $db->exec($create_table);
    
    // Check if already favorited
    $check_query = "SELECT id FROM user_favorites 
                    WHERE user_id = :user_id AND service_id = :service_id AND provider_id = :provider_id";
    $stmt = $db->prepare($check_query);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':service_id', $service_id);
    $stmt->bindParam(':provider_id', $provider_id);
    $stmt->execute();
    
    $is_favorited = $stmt->fetch();
    
    if ($is_favorited) {
        // Remove from favorites
        $delete_query = "DELETE FROM user_favorites 
                        WHERE user_id = :user_id AND service_id = :service_id AND provider_id = :provider_id";
        $stmt = $db->prepare($delete_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':service_id', $service_id);
        $stmt->bindParam(':provider_id', $provider_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'favorited' => false,
            'message' => 'Removed from favorites'
        ]);
    } else {
        // Add to favorites
        $insert_query = "INSERT INTO user_favorites (user_id, service_id, provider_id) 
                        VALUES (:user_id, :service_id, :provider_id)";
        $stmt = $db->prepare($insert_query);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':service_id', $service_id);
        $stmt->bindParam(':provider_id', $provider_id);
        $stmt->execute();
        
        echo json_encode([
            'success' => true,
            'favorited' => true,
            'message' => 'Added to favorites'
        ]);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error occurred'
    ]);
}
?>