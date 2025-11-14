<?php
// Comprehensive session test
require 'config.php';

echo "<h2>Comprehensive Session Test</h2>";

// Test 1: Basic session functionality
echo "<h3>Test 1: Basic Session</h3>";
$_SESSION['test'] = 'Session working!';
echo "Session test: " . $_SESSION['test'] . "<br>";

// Test 2: User login simulation
echo "<h3>Test 2: User Login Simulation</h3>";
set_user_session(123, ['username' => 'testuser', 'role' => 'admin']);
echo "User ID: " . $_SESSION['user_id'] . "<br>";
echo "Username: " . $_SESSION['username'] . "<br>";
echo "Role: " . $_SESSION['role'] . "<br>";

// Test 3: Login check
echo "<h3>Test 3: Login Check</h3>";
if (is_user_logged_in()) {
    echo "User is logged in: YES<br>";
} else {
    echo "User is logged in: NO<br>";
}

// Test 4: Session data
echo "<h3>Test 4: All Session Data</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test 5: Session configuration
echo "<h3>Test 5: Session Configuration</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Status: " . session_status() . " (2 = active)<br>";
echo "Session Timeout: " . SESSION_TIMEOUT . " seconds<br>";
echo "Regenerate Time: " . SESSION_REGENERATE_TIME . " seconds<br>";

echo "<br><a href='test_comprehensive.php'>Refresh</a> | <a href='test_logout.php'>Test Logout</a>";
?>