<?php

mysqli_report(MYSQLI_REPORT_OFF);

$host = "localhost";
$port = 3309;
$dbname = "studycircle_db";
$username = "root";
$password = "";

$conn = new mysqli(
    $host,
    $username,
    $password,
    $dbname,
    $port
);

if ($conn->connect_errno) {
    error_log("Database connection failed: " . $conn->connect_error);

    http_response_code(500);
    exit("Unable to connect to the database. Please try again later.");
}

if (!$conn->set_charset("utf8mb4")) {
    error_log("Database character encoding setup failed: " . $conn->error);

    http_response_code(500);
    exit("A database configuration error occurred.");
}

?>