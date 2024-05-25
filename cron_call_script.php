<?php
require_once 'vendor/autoload.php';

use Twilio\Rest\Client;

// Database connection settings
$db_host = 'localhost';
$db_username = ''; // Replace with your actual username
$db_password = '';   // Replace with your actual password
$db_name = ' ';

// Establishing database connection
$conn = new mysqli($db_host, $db_username, $db_password, $db_name);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Twilio credentials
$sid = ' ';
$token = ' ';

try {
    // Create Twilio client
    $twilio = new Client($sid, $token);

    // Get current UTC time
    $now = new DateTime('now', new DateTimeZone('UTC'));

    // Prepare SQL to get pending calls scheduled for now or earlier
    $sql = "SELECT * FROM scheduled_calls WHERE utc_datetime <= ? AND call_status = 'pending'";
    $stmt = $conn->prepare($sql);
    $nowString = $now->format('Y-m-d H:i:s');
    $stmt->bind_param("s", $nowString);
    $stmt->execute();
    $result = $stmt->get_result();

    // Iterate through the scheduled calls and make calls
    if ($result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $callText = !empty($row['recognized_text']) ? $row['recognized_text'] : $row['user_text'];

            // Make the call using Twilio
            $call = $twilio->calls->create(
                "", // to
                "", // from
                ["twiml" => "<Response><Say>$callText</Say></Response>"]
            );

            // Update call_status to 'completed'
            $updateSql = "UPDATE scheduled_calls SET call_status = 'completed' WHERE id = ?";
            $updateStmt = $conn->prepare($updateSql);
            $updateStmt->bind_param("i", $row['id']);
            $updateStmt->execute();
            $updateStmt->close();

            echo "Call initiated<br>";
        }
    } else {
        echo "No scheduled call found";
    }
    $stmt->close();
} catch (Exception $e) {
    echo 'Caught exception: ' . $e->getMessage() . "\n";
}

// Close database connection
$conn->close();
?>
