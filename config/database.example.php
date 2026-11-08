<?php

mysqli_report(MYSQLI_REPORT_OFF);

$host = "YOUR_DATABASE_HOST";
$port = 3306;
$dbname = "YOUR_DATABASE_NAME";
$username = "YOUR_DATABASE_USERNAME";
$password = "YOUR_DATABASE_PASSWORD";

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