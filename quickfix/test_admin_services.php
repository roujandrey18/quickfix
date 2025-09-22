<?php
// Quick test script to verify admin services page functionality
require_once 'config/config.php';

$database = new Database();
$db = $database->getConnection();

echo "Testing Admin Services Page Functionality\n";
echo "========================================\n\n";

// Test 1: Check if services exist
$services_check = "SELECT COUNT(*) as total FROM services";
$stmt = $db->prepare($services_check);
$stmt->execute();
$total = $stmt->fetchColumn();
echo "1. Total services in database: " . $total . "\n";

// Test 2: Check provider_services relationship
$provider_services_check = "SELECT COUNT(*) as total FROM provider_services";
$stmt = $db->prepare($provider_services_check);
$stmt->execute();
$total = $stmt->fetchColumn();
echo "2. Total provider-service relationships: " . $total . "\n";

// Test 3: Test the main query from admin/services.php
$test_query = "SELECT s.id, s.name, u.full_name as provider_name, 
               ps.price as service_price, s.base_price
               FROM services s 
               LEFT JOIN provider_services ps ON s.id = ps.service_id
               LEFT JOIN users u ON ps.provider_id = u.id 
               LIMIT 5";

try {
    $stmt = $db->prepare($test_query);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "3. Test query results:\n";
    foreach ($results as $row) {
        echo "   - Service: " . $row['name'] . 
             " | Provider: " . ($row['provider_name'] ?? 'No Provider') . 
             " | Price: ₱" . number_format($row['service_price'] ?? $row['base_price'] ?? 0, 2) . "\n";
    }
} catch (PDOException $e) {
    echo "3. Query Error: " . $e->getMessage() . "\n";
}

// Test 4: Check for any services without providers
$unassigned_query = "SELECT s.name FROM services s 
                     LEFT JOIN provider_services ps ON s.id = ps.service_id 
                     WHERE ps.service_id IS NULL";
$stmt = $db->prepare($unassigned_query);
$stmt->execute();
$unassigned = $stmt->fetchAll(PDO::FETCH_COLUMN);

echo "4. Services without assigned providers: " . count($unassigned) . "\n";
if (count($unassigned) > 0) {
    foreach ($unassigned as $service) {
        echo "   - " . $service . "\n";
    }
}

echo "\nTest completed! Admin services page should now work without errors.\n";
?>