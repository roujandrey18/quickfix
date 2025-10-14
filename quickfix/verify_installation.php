<?php
/**
 * QuickFix Database Verification Script
 * Run this file after importing the database to verify everything is set up correctly
 */

// Include configuration
require_once 'config/config.php';

// Start output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QuickFix - Database Verification</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .verification-container {
            max-width: 900px;
            margin: 3rem auto;
            padding: 2rem;
        }
        .test-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .test-success {
            color: #2ecc71;
            border-left: 4px solid #2ecc71;
        }
        .test-error {
            color: #e74c3c;
            border-left: 4px solid #e74c3c;
        }
        .test-title {
            font-size: 1.2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .test-details {
            margin-left: 2rem;
            color: #2c3e50;
            font-size: 0.9rem;
        }
        .test-details ul {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        .test-details li {
            margin: 0.25rem 0;
        }
        .summary-card {
            text-align: center;
            padding: 2rem;
            font-size: 1.3rem;
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="glass-container">
            <h1 style="text-align: center; color: #2c3e50; margin-bottom: 2rem;">
                <i class="fas fa-check-circle"></i> QuickFix Database Verification
            </h1>
            
            <?php
            $tests = [];
            $passed = 0;
            $failed = 0;
            
            try {
                $database = new Database();
                $db = $database->getConnection();
                
                if (!$db) {
                    throw new Exception("Database connection failed");
                }
                
                // Test 1: Database Connection
                $tests[] = [
                    'name' => 'Database Connection',
                    'status' => 'success',
                    'message' => 'Successfully connected to database: ' . DB_NAME,
                    'details' => []
                ];
                $passed++;
                
                // Test 2: Check Tables
                $required_tables = ['users', 'services', 'provider_services', 'bookings', 'reviews', 'user_favorites', 'system_settings'];
                $query = "SHOW TABLES";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $existing_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $missing_tables = array_diff($required_tables, $existing_tables);
                
                if (empty($missing_tables)) {
                    $tests[] = [
                        'name' => 'Database Tables',
                        'status' => 'success',
                        'message' => 'All required tables exist',
                        'details' => $required_tables
                    ];
                    $passed++;
                } else {
                    $tests[] = [
                        'name' => 'Database Tables',
                        'status' => 'error',
                        'message' => 'Missing tables: ' . implode(', ', $missing_tables),
                        'details' => ['Found: ' . implode(', ', $existing_tables)]
                    ];
                    $failed++;
                }
                
                // Test 3: Check Admin User
                $query = "SELECT COUNT(*) FROM users WHERE user_type = 'admin'";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $admin_count = $stmt->fetchColumn();
                
                if ($admin_count > 0) {
                    $query = "SELECT username, email, full_name FROM users WHERE user_type = 'admin' LIMIT 1";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $admin = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $tests[] = [
                        'name' => 'Admin Account',
                        'status' => 'success',
                        'message' => "$admin_count admin account(s) found",
                        'details' => [
                            "Username: {$admin['username']}",
                            "Email: {$admin['email']}",
                            "Name: {$admin['full_name']}"
                        ]
                    ];
                    $passed++;
                } else {
                    $tests[] = [
                        'name' => 'Admin Account',
                        'status' => 'error',
                        'message' => 'No admin account found',
                        'details' => ['Please import the SQL file with sample data']
                    ];
                    $failed++;
                }
                
                // Test 4: Check Users
                $query = "SELECT user_type, COUNT(*) as count FROM users GROUP BY user_type";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $user_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $stats_details = [];
                foreach ($user_stats as $stat) {
                    $stats_details[] = ucfirst($stat['user_type']) . ": {$stat['count']}";
                }
                
                $tests[] = [
                    'name' => 'User Accounts',
                    'status' => 'success',
                    'message' => 'User accounts loaded successfully',
                    'details' => $stats_details
                ];
                $passed++;
                
                // Test 5: Check Services
                $query = "SELECT COUNT(*) FROM services";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $service_count = $stmt->fetchColumn();
                
                if ($service_count > 0) {
                    $query = "SELECT category, COUNT(*) as count FROM services GROUP BY category";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $category_details = [];
                    foreach ($categories as $cat) {
                        $category_details[] = "{$cat['category']}: {$cat['count']} service(s)";
                    }
                    
                    $tests[] = [
                        'name' => 'Services',
                        'status' => 'success',
                        'message' => "$service_count service(s) available",
                        'details' => $category_details
                    ];
                    $passed++;
                } else {
                    $tests[] = [
                        'name' => 'Services',
                        'status' => 'error',
                        'message' => 'No services found',
                        'details' => ['Services table is empty']
                    ];
                    $failed++;
                }
                
                // Test 6: Check Provider Services
                $query = "SELECT COUNT(*) FROM provider_services";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $provider_service_count = $stmt->fetchColumn();
                
                $tests[] = [
                    'name' => 'Provider Services',
                    'status' => $provider_service_count > 0 ? 'success' : 'error',
                    'message' => $provider_service_count > 0 
                        ? "$provider_service_count provider-service relationship(s) configured"
                        : 'No provider services configured',
                    'details' => []
                ];
                
                if ($provider_service_count > 0) {
                    $passed++;
                } else {
                    $failed++;
                }
                
                // Test 7: Check Bookings
                $query = "SELECT COUNT(*) FROM bookings";
                $stmt = $db->prepare($query);
                $stmt->execute();
                $booking_count = $stmt->fetchColumn();
                
                if ($booking_count > 0) {
                    $query = "SELECT status, COUNT(*) as count FROM bookings GROUP BY status";
                    $stmt = $db->prepare($query);
                    $stmt->execute();
                    $booking_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $booking_details = [];
                    foreach ($booking_stats as $stat) {
                        $booking_details[] = ucfirst($stat['status']) . ": {$stat['count']}";
                    }
                    
                    $tests[] = [
                        'name' => 'Bookings',
                        'status' => 'success',
                        'message' => "$booking_count sample booking(s) found",
                        'details' => $booking_details
                    ];
                } else {
                    $tests[] = [
                        'name' => 'Bookings',
                        'status' => 'success',
                        'message' => 'No bookings yet (this is normal for fresh install)',
                        'details' => []
                    ];
                }
                $passed++;
                
                // Test 8: Check Configuration
                $config_tests = [
                    'SITE_URL' => SITE_URL,
                    'SITE_NAME' => SITE_NAME,
                    'DB_NAME' => DB_NAME
                ];
                
                $tests[] = [
                    'name' => 'Configuration',
                    'status' => 'success',
                    'message' => 'Configuration loaded successfully',
                    'details' => [
                        "Site URL: " . SITE_URL,
                        "Site Name: " . SITE_NAME,
                        "Database: " . DB_NAME
                    ]
                ];
                $passed++;
                
            } catch (Exception $e) {
                $tests[] = [
                    'name' => 'Critical Error',
                    'status' => 'error',
                    'message' => $e->getMessage(),
                    'details' => [
                        'Check your database credentials in config/database.php',
                        'Ensure MySQL is running in XAMPP',
                        'Verify database ' . DB_NAME . ' exists'
                    ]
                ];
                $failed++;
            }
            
            // Display results
            foreach ($tests as $test) {
                $class = $test['status'] === 'success' ? 'test-success' : 'test-error';
                $icon = $test['status'] === 'success' ? 'fa-check-circle' : 'fa-times-circle';
                
                echo "<div class='test-card $class'>";
                echo "<div class='test-title'>";
                echo "<i class='fas $icon'></i> {$test['name']}";
                echo "</div>";
                echo "<div class='test-details'>";
                echo "<p>{$test['message']}</p>";
                if (!empty($test['details'])) {
                    echo "<ul>";
                    foreach ($test['details'] as $detail) {
                        echo "<li>$detail</li>";
                    }
                    echo "</ul>";
                }
                echo "</div>";
                echo "</div>";
            }
            
            // Summary
            $total = $passed + $failed;
            $summary_class = $failed === 0 ? 'test-success' : 'test-error';
            echo "<div class='test-card summary-card $summary_class'>";
            if ($failed === 0) {
                echo "<i class='fas fa-check-circle' style='font-size: 3rem; margin-bottom: 1rem; display: block;'></i>";
                echo "<strong>All Tests Passed!</strong><br>";
                echo "Your QuickFix installation is ready to use.<br><br>";
                echo "<a href='index.php' class='btn btn-primary' style='margin-right: 1rem;'>Go to Homepage</a>";
                echo "<a href='auth/login.php' class='btn btn-secondary'>Login</a>";
            } else {
                echo "<i class='fas fa-exclamation-triangle' style='font-size: 3rem; margin-bottom: 1rem; display: block;'></i>";
                echo "<strong>$failed Test(s) Failed</strong><br>";
                echo "Please check the errors above and fix them.<br><br>";
                echo "<a href='SETUP_GUIDE.md' class='btn btn-primary'>View Setup Guide</a>";
            }
            echo "</div>";
            ?>
            
            <div style="text-align: center; margin-top: 2rem; color: #2c3e50; opacity: 0.7;">
                <p>
                    <i class="fas fa-info-circle"></i> 
                    For help, refer to 
                    <a href="SETUP_GUIDE.md" style="color: #4facfe;">SETUP_GUIDE.md</a> 
                    or 
                    <a href="quickfix/README.md" style="color: #4facfe;">README.md</a>
                </p>
            </div>
        </div>
    </div>
</body>
</html>
