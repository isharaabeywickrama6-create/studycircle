<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$materialId = filter_input(
    INPUT_GET,
    "id",
    FILTER_VALIDATE_INT
);

if (!$materialId || $materialId <= 0) {
    http_response_code(400);
    exit("Invalid material.");
}

$stmt = $conn->prepare(
    "SELECT title, file_path
     FROM materials
     WHERE material_id = ? AND status = 'approved'
     LIMIT 1"
);

if (!$stmt) {
    error_log("Download query preparation failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to process download.");
}

$stmt->bind_param("i", $materialId);

if (!$stmt->execute()) {
    error_log("Download query execution failed: " . $stmt->error);
    http_response_code(500);
    exit("Unable to process download.");
}

$result = $stmt->get_result();
$material = $result->fetch_assoc();
$stmt->close();

if (!$material) {
    http_response_code(404);
    exit("Material not found or not approved.");
}

$filePath = $material["file_path"] ?? "";

$uploadDirectory = realpath(
    __DIR__ . DIRECTORY_SEPARATOR . "uploads"
    . DIRECTORY_SEPARATOR . "materials"
);

$fullPath = __DIR__ . DIRECTORY_SEPARATOR
    . str_replace("/", DIRECTORY_SEPARATOR, $filePath);

$realFile = realpath($fullPath);

if (
    $filePath === "" ||
    $uploadDirectory === false ||
    $realFile === false ||
    strpos(
        $realFile,
        $uploadDirectory . DIRECTORY_SEPARATOR
    ) !== 0 ||
    !is_file($realFile) ||
    !is_readable($realFile)
) {
    http_response_code(404);
    exit("The file is currently unavailable.");
}

$downloadStmt = $conn->prepare(
    "INSERT INTO resource_downloads (material_id, user_id)
     VALUES (?, ?)"
);

if (!$downloadStmt) {
    error_log("Download record preparation failed: " . $conn->error);
    http_response_code(500);
    exit("Unable to record download.");
}

$downloadStmt->bind_param("ii", $materialId, $userId);

if (!$downloadStmt->execute()) {
    error_log("Download record insertion failed: " . $downloadStmt->error);
    $downloadStmt->close();
    http_response_code(500);
    exit("Unable to record download.");
}

$downloadStmt->close();

$extension = pathinfo($realFile, PATHINFO_EXTENSION);
$title = preg_replace(
    '/[^A-Za-z0-9_-]/',
    '_',
    $material["title"]
);

$downloadName = $title . "." . $extension;

if (function_exists("mime_content_type")) {
    $mimeType = mime_content_type($realFile);
} else {
    $mimeType = "application/octet-stream";
}

header("Content-Type: " . $mimeType);
header("Content-Disposition: attachment; filename=\"" . $downloadName . "\"");
header("Content-Length: " . filesize($realFile));
header("X-Content-Type-Options: nosniff");

header("Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");
header("Expires: 0");

readfile($realFile);
exit;