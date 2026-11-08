<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$sessionToken = $_SESSION["logout_csrf"] ?? "";
$submittedToken = $_POST["logout_csrf"] ?? "";

if (
    $_SERVER["REQUEST_METHOD"] !== "POST" ||
    !is_string($sessionToken) ||
    !is_string($submittedToken) ||
    !hash_equals($sessionToken, $submittedToken)
) {
    http_response_code(403);
    exit("Invalid logout request. Please return to StudyCircle and try again.");
}

$role = $_SESSION["role"] ?? "";
$isStudent = $role === "student";
$isAdmin = $role === "admin";

$isSwitchingAccount = (
    isset($_POST["switch"]) &&
    is_string($_POST["switch"]) &&
    $_POST["switch"] === "1"
);

$_SESSION = [];

if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        "",
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

session_destroy();

session_start();

require_once "includes/alerts.php";

if ($isSwitchingAccount) {

    if ($isStudent) {
        setAlert(
            "success",
            "Account Logged Out",
            "You have safely logged out. Please log in with another student account."
        );

        header("Location: login.php");
        exit;
    }

    if ($isAdmin) {
        setAlert(
            "success",
            "Account Logged Out",
            "You have safely logged out. Please log in with another admin account."
        );

        header("Location: admin/login.php");
        exit;
    }
}

if ($isStudent) {
    setAlert(
        "success",
        "Logged Out Successfully",
        "You have safely logged out of your StudyCircle account."
    );

    header("Location: index.php");
    exit;
}

if ($isAdmin) {
    setAlert(
        "success",
        "Logged Out Successfully",
        "You have safely logged out of the admin account."
    );

    header("Location: admin/login.php");
    exit;
}

header("Location: index.php");
exit;
?>