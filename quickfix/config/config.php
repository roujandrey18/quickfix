<?php
session_start();

// Site configuration
define('SITE_URL', 'http://localhost/quickfix');
define('SITE_NAME', 'QuickFix');
define('UPLOAD_PATH', 'assets/uploads/');

// Database configuration
require_once 'database.php';

// Helper functions
function redirect($url) {
    header("Location: " . $url);
    exit();
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function getUserType() {
    return $_SESSION['user_type'] ?? null;
}

function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'email' => $_SESSION['email'],
            'full_name' => $_SESSION['full_name'],
            'user_type' => $_SESSION['user_type']
        ];
    }
    return null;
}

function checkAccess($allowedTypes = []) {
    if (!isLoggedIn()) {
        redirect(SITE_URL . '/auth/login.php');
    }
    
    if (!empty($allowedTypes) && !in_array(getUserType(), $allowedTypes)) {
        redirect(SITE_URL . '/access-denied.php');
    }
}
?>