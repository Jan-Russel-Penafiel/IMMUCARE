<?php
// Test session functionality
require 'config.php';

echo "<h2>Session Test</h2>";

// Check session status
echo "<h3>Session Status:</h3>";
echo "Session Status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";
echo "Session Name: " . session_name() . "<br>";

// Test session variables
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
    echo "<p>Session variable initialized: test_counter = 1</p>";
} else {
    $_SESSION['test_counter']++;
    echo "<p>Session variable updated: test_counter = " . $_SESSION['test_counter'] . "</p>";
}

// Show session data
echo "<h3>Session Data:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Test timeout function
echo "<h3>Session Management:</h3>";
if (check_session_timeout()) {
    echo "Session timeout check: PASSED<br>";
} else {
    echo "Session timeout check: FAILED<br>";
}

// Show session configuration
echo "<h3>Session Configuration:</h3>";
echo "GC Max Lifetime: " . ini_get('session.gc_maxlifetime') . " seconds<br>";
echo "Cookie Lifetime: " . ini_get('session.cookie_lifetime') . " seconds<br>";
echo "Use Strict Mode: " . ini_get('session.use_strict_mode') . "<br>";
echo "Use Only Cookies: " . ini_get('session.use_only_cookies') . "<br>";
echo "Cookie HTTPOnly: " . ini_get('session.cookie_httponly') . "<br>";
echo "Cookie Secure: " . ini_get('session.cookie_secure') . "<br>";

echo "<br><a href='test_session.php'>Refresh Page</a> | <a href='logout.php'>Logout</a>";
?>