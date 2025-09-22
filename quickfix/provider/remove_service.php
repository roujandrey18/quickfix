<?php 
require_once '../config/config.php';
checkAccess(['provider']);

$database = new Database();
$db = $database->getConnection();

$provider_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    $_SESSION['error'] = 'Invalid service removal request.';
    header('Location: services.php');
    exit;
}

$service_id = (int)$_GET['id'];

// Verify service belongs to this provider
$verify_query = "SELECT ps.*, s.name as service_name 
                FROM provider_services ps
                JOIN services s ON ps.service_id = s.id
                WHERE ps.provider_id = :provider_id AND ps.service_id = :service_id";
$verify_stmt = $db->prepare($verify_query);
$verify_stmt->bindParam(':provider_id', $provider_id);
$verify_stmt->bindParam(':service_id', $service_id);
$verify_stmt->execute();

$service = $verify_stmt->fetch(PDO::FETCH_ASSOC);

if (!$service) {
    $_SESSION['error'] = 'Service not found or access denied.';
    header('Location: services.php');
    exit;
}

// Check for active bookings
$active_bookings_query = "SELECT COUNT(*) as count 
                         FROM bookings 
                         WHERE provider_id = :provider_id 
                         AND service_id = :service_id 
                         AND status IN ('pending', 'confirmed')";
$bookings_stmt = $db->prepare($active_bookings_query);
$bookings_stmt->bindParam(':provider_id', $provider_id);
$bookings_stmt->bindParam(':service_id', $service_id);
$bookings_stmt->execute();

$active_bookings = $bookings_stmt->fetchColumn();

if ($active_bookings > 0) {
    $_SESSION['error'] = 'Cannot remove service "' . $service['service_name'] . '" because it has active bookings. Please complete or cancel all pending bookings first.';
    header('Location: services.php');
    exit;
}

try {
    // Begin transaction
    $db->beginTransaction();

    // Remove service from provider's portfolio
    $remove_query = "DELETE FROM provider_services 
                    WHERE provider_id = :provider_id AND service_id = :service_id";
    $remove_stmt = $db->prepare($remove_query);
    $remove_stmt->bindParam(':provider_id', $provider_id);
    $remove_stmt->bindParam(':service_id', $service_id);
    $remove_stmt->execute();

    // Log the removal (optional - create log table if needed)
    $log_query = "INSERT INTO service_removal_log (provider_id, service_id, service_name, removed_at) 
                  VALUES (:provider_id, :service_id, :service_name, NOW())";
    $log_stmt = $db->prepare($log_query);
    $log_stmt->bindParam(':provider_id', $provider_id);
    $log_stmt->bindParam(':service_id', $service_id);
    $log_stmt->bindParam(':service_name', $service['service_name']);
    $log_stmt->execute();

    // Commit transaction
    $db->commit();

    $_SESSION['success'] = 'Service "' . htmlspecialchars($service['service_name']) . '" has been removed from your portfolio successfully.';

} catch (Exception $e) {
    // Rollback transaction
    $db->rollBack();
    
    error_log("Service removal error: " . $e->getMessage());
    $_SESSION['error'] = 'Error removing service. Please try again.';
}

header('Location: services.php');
exit;
?>
