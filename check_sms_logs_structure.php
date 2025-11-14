<?php
$conn = new mysqli('localhost', 'root', '', 'immucare_db');
$result = $conn->query('DESCRIBE sms_logs');
echo "SMS Logs Table Structure:\n";
while ($row = $result->fetch_assoc()) {
    echo $row['Field'] . ' | ' . $row['Type'] . ' | NULL:' . $row['Null'] . ' | Default:' . $row['Default'] . "\n";
}
?>