<?php
// Test double session start
session_start(); // This simulates what happens in other files
require 'config.php';

echo "<h2>Double Session Start Test</h2>";
echo "Session Status: " . session_status() . "<br>";
echo "Session ID: " . session_id() . "<br>";

// Test session variables
if (!isset($_SESSION['double_test'])) {
    $_SESSION['double_test'] = "Working";
}

echo "Session working: " . $_SESSION['double_test'] . "<br>";
echo "Session timeout check: " . (check_session_timeout() ? "PASSED" : "FAILED") . "<br>";
?>