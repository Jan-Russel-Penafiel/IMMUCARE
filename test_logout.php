<?php
// Test logout functionality
require 'config.php';

echo "<h2>Logout Test</h2>";

// Show session data before logout
echo "<h3>Before Logout:</h3>";
if (is_user_logged_in()) {
    echo "User logged in: YES<br>";
    echo "User ID: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'Not set') . "<br>";
    echo "Username: " . (isset($_SESSION['username']) ? $_SESSION['username'] : 'Not set') . "<br>";
} else {
    echo "User logged in: NO<br>";
}

// Perform logout
secure_session_destroy();

// Try to start new session to show after logout state
secure_session_start();

echo "<h3>After Logout:</h3>";
if (is_user_logged_in()) {
    echo "User logged in: YES (This should not happen!)<br>";
} else {
    echo "User logged in: NO (Correct!)<br>";
}

echo "Session ID: " . session_id() . "<br>";
echo "Session Data: ";
if (empty($_SESSION)) {
    echo "Empty (Correct!)";
} else {
    echo "<pre>";
    print_r($_SESSION);
    echo "</pre>";
}

echo "<br><br><a href='test_comprehensive.php'>Test Login Again</a>";
?>