<?php
require_once __DIR__ . "/config/database.php";

$result = $conn->query("SHOW TABLES");

if ($result) {
    echo "<h2>StudyCircle Database Connected Successfully!</h2>";
    echo "<p>Database: studycircle_db</p>";
    echo "<h3>Available tables:</h3><ul>";

    while ($row = $result->fetch_array()) {
        echo "<li>" . htmlspecialchars($row[0]) . "</li>";
    }

    echo "</ul>";
} else {
    echo "Unable to retrieve database tables.";
}

$conn->close();
?>