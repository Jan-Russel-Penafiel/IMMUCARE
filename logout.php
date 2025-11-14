<?php
// Test logout functionality
require 'config.php';

echo "<h2>Logout Test</h2>";

if (isset($_SESSION['test_counter'])) {
    echo "<p>Before logout - test_counter: " . $_SESSION['test_counter'] . "</p>";
} else {
    echo "<p>No session data found before logout</p>";
}

// Destroy session properly
secure_session_destroy();

echo "<p>Session destroyed successfully!</p>";
echo "<a href='test_session.php'>Test Session Again</a>";
?>