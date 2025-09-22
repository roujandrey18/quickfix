<?php
require_once '../config/config.php';
checkAccess(['provider']);

$database = new Database();
$db = $database->getConnection();
$provider_id = $_SESSION['user_id'];

// Test data update
if ($_GET['test'] === 'update') {
    $test_name = 'Test Name Updated - ' . date('H:i:s');
    $test_email = 'test_' . time() . '@example.com';
    
    $update_query = "UPDATE users SET full_name = :full_name, email = :email WHERE id = :provider_id";
    $update_stmt = $db->prepare($update_query);
    $update_stmt->bindParam(':full_name', $test_name);
    $update_stmt->bindParam(':email', $test_email);
    $update_stmt->bindParam(':provider_id', $provider_id);
    
    if ($update_stmt->execute()) {
        echo "Update successful!<br>";
        echo "New name: " . $test_name . "<br>";
        echo "New email: " . $test_email . "<br>";
    } else {
        echo "Update failed!<br>";
    }
}

// Display current data
$user_query = "SELECT * FROM users WHERE id = :provider_id";
$user_stmt = $db->prepare($user_query);
$user_stmt->bindParam(':provider_id', $provider_id);
$user_stmt->execute();
$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

echo "<h3>Current User Data:</h3>";
echo "<pre>";
print_r($user);
echo "</pre>";

echo '<br><a href="?test=update">Test Update</a>';
echo '<br><a href="profile.php">Back to Profile</a>';
?>