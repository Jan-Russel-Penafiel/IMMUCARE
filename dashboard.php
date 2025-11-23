<?php
session_start();
require 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Get user information
$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'User';
$user_email = isset($_SESSION['user_email']) ? $_SESSION['user_email'] : '';
$user_type = isset($_SESSION['user_type']) ? $_SESSION['user_type'] : '';

// If user name or email is missing from session, fetch from database
if (empty($user_name) || empty($user_email)) {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if (!$conn->connect_error) {
        $stmt = $conn->prepare("SELECT name, email FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 1) {
            $user_data = $result->fetch_assoc();
            if (empty($user_name)) {
                $user_name = $user_data['name'];
                $_SESSION['user_name'] = $user_name;
            }
            if (empty($user_email)) {
                $user_email = $user_data['email'];
                $_SESSION['user_email'] = $user_email;
            }
        }
        $stmt->close();
        $conn->close();
    }
}

// Redirect users based on their type

if ($user_type === 'admin') {
    header('Location: admin_dashboard.php');
    exit;
} else if ($user_type === 'midwife') {
    header('Location: midwife_dashboard.php');
    exit;
} else if ($user_type === 'nurse') {
    header('Location: nurse_dashboard.php');
} else if ($user_type === 'patient') {
    header('Location: patient_dashboard.php');
    exit;
}

// Process logout
if (isset($_GET['logout'])) {
    // Clear all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header('Location: login.php');
    exit;
}
?>

// Dashboard routing completed - user redirected to appropriate dashboard 