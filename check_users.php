<?php
require_once 'config.php';

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

echo "Available Users:\n";
$result = $conn->query('
    SELECT u.id, u.name, u.email, p.phone_number 
    FROM users u 
    LEFT JOIN patients p ON u.id = p.user_id 
    ORDER BY u.id LIMIT 10
');

while($row = $result->fetch_assoc()) {
    echo 'ID: ' . $row['id'] . ', Name: ' . $row['name'] . ', Email: ' . $row['email'] . ', Phone: ' . ($row['phone_number'] ?? 'N/A') . "\n";
}

$conn->close();
?>