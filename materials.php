<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}

$isStudent = isset($_SESSION["user_id"])
    && ($_SESSION["role"] ?? "") === "student";

$isAdmin = isset($_SESSION["user_id"])
    && ($_SESSION["role"] ?? "") === "admin";

$userId = $isStudent ? (int)$_SESSION["user_id"] : 0;

if (empty($_SESSION["materials_csrf"])) {
    $_SESSION["materials_csrf"] = bin2hex(random_bytes(32));
}

$materialsMessage = $_SESSION["materials_message"] ?? "";
$materialsMessageType = $_SESSION["materials_message_type"] ?? "success";

unset(
    $_SESSION["materials_message"],
    $_SESSION["materials_message_type"]
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$isStudent && !$isAdmin) {
        header("Location: login.php");
        exit;
    }

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["materials_csrf"], $token)
    ) {
        $_SESSION["materials_message"] = "Invalid request. Please refresh the page.";
        $_SESSION["materials_message_type"] = "error";

    } else {

        $actionInput = $_POST["action"] ?? "";
        $action = is_string($actionInput) ? $actionInput : "";

        $materialIdInput = $_POST["material_id"] ?? "";

        $materialId = is_string($materialIdInput)
            ? filter_var($materialIdInput, FILTER_VALIDATE_INT)
            : false;

        if ($materialId === false || $materialId < 1) {
            $_SESSION["materials_message"] = "Invalid material.";
            $_SESSION["materials_message_type"] = "error";

        } else {

            $materialExists = false;

            $check = $conn->prepare(
                "SELECT material_id FROM materials
                 WHERE material_id = ? AND status = 'approved'"
            );

            if (!$check) {

                error_log("Material verification preparation failed: " . $conn->error);

            } else {

                $check->bind_param("i", $materialId);

                if (!$check->execute()) {

                    error_log("Material verification execution failed: " . $check->error);

                } else {

                    $checkResult = $check->get_result();

                    if ($checkResult) {
                        $materialExists = $checkResult->num_rows > 0;
                    }
                }

                $check->close();
            }

            if (!$materialExists) {
                $_SESSION["materials_message"] = "This material is not available.";
                $_SESSION["materials_message_type"] = "error";

            } elseif ($action === "delete_comment") {

                $commentIdInput = $_POST["comment_id"] ?? "";

                $commentId = is_string($commentIdInput)
                    ? filter_var($commentIdInput, FILTER_VALIDATE_INT)
                    : false;

                if ($commentId === false || $commentId < 1) {

                    $_SESSION["materials_message"] = "Invalid comment.";
                    $_SESSION["materials_message_type"] = "error";

                } else {

                    if ($isAdmin) {

                        $stmt = $conn->prepare(
                            "DELETE FROM resource_comments
                             WHERE comment_id = ? AND material_id = ?"
                        );

                        if ($stmt) {
                            $stmt->bind_param("ii", $commentId, $materialId);

                        }

                    } else {

                        $stmt = $conn->prepare(
                            "DELETE FROM resource_comments
                             WHERE comment_id = ?
                             AND material_id = ?
                             AND user_id = ?"
                        );

                        if ($stmt) {
                            $stmt->bind_param("iii", $commentId, $materialId, $userId);
                        }
                    }

                    if (!$stmt) {

                        error_log("Comment deletion preparation failed: " . $conn->error);

                        $_SESSION["materials_message"] = "Could not process comment deletion.";
                        $_SESSION["materials_message_type"] = "error";

                    } else {

                        if ($stmt->execute()) {

                            if ($stmt->affected_rows > 0) {
                                $_SESSION["materials_message"] = "Comment deleted successfully!";
                                $_SESSION["materials_message_type"] = "success";

                            } else {
                                $_SESSION["materials_message"] = "You cannot delete this comment, or it no longer exists.";
                                $_SESSION["materials_message_type"] = "error";
                            }

                        } else {

                            error_log("Comment deletion failed: " . $stmt->error);

                            $_SESSION["materials_message"] = "Could not delete the comment.";
                            $_SESSION["materials_message_type"] = "error";
                        }

                        $stmt->close();
                    }
                }

            } elseif ($action === "rate") {

                    if (!$isStudent) {
                        $_SESSION["materials_message"] = "Only students can submit ratings.";
                        $_SESSION["materials_message_type"] = "error";

                    } else {

                $ratingInput = $_POST["rating"] ?? "";

                $rating = is_string($ratingInput)
                    ? filter_var($ratingInput, FILTER_VALIDATE_INT)
                    : false;

                if ($rating === false || $rating < 1 || $rating > 5) {
                    $_SESSION["materials_message"] = "Please select a rating from 1 to 5.";
                    $_SESSION["materials_message_type"] = "error";

                } else {

                    $stmt = $conn->prepare(
                        "INSERT INTO resource_ratings
                         (material_id, user_id, rating)
                         VALUES (?, ?, ?)
                         ON DUPLICATE KEY UPDATE
                         rating = VALUES(rating),
                         created_at = CURRENT_TIMESTAMP"
                    );

                    if ($stmt) {
                        $stmt->bind_param(
                            "iii",
                            $materialId,
                            $userId,
                            $rating
                        );

                        if ($stmt->execute()) {
                            $_SESSION["materials_message"] = "Your rating has been saved!";
                            $_SESSION["materials_message_type"] = "success";

                        } else {

                            error_log("Rating insertion failed: " . $stmt->error);

                            $_SESSION["materials_message"] = "Could not save your rating.";
                            $_SESSION["materials_message_type"] = "error";
                        }

                        $stmt->close();

                    } else {
                        error_log("Rating preparation failed: " . $conn->error);

                        $_SESSION["materials_message"] = "Could not process your rating.";
                        $_SESSION["materials_message_type"] = "error";
                    }
                }
            }

            } elseif ($action === "comment") {

                if (!$isStudent) {
                    $_SESSION["materials_message"] = "Only students can post comments.";
                    $_SESSION["materials_message_type"] = "error";

                } else {

                    $commentInput = $_POST["comment"] ?? "";

                    $comment = is_string($commentInput)
                        ? trim($commentInput)
                        : "";

                    if ($comment === "" || mb_strlen($comment) > 2000) {
                        $_SESSION["materials_message"] = "Enter a comment of up to 2000 characters.";
                        $_SESSION["materials_message_type"] = "error";

                    } else {

                        $stmt = $conn->prepare(
                            "INSERT INTO resource_comments
                             (material_id, user_id, comment)
                             VALUES (?, ?, ?)"
                        );

                        if ($stmt) {
                            $stmt->bind_param(
                                "iis",
                                $materialId,
                                $userId,
                                $comment
                            );

                            if ($stmt->execute()) {
                                $_SESSION["materials_message"] = "Your comment has been added!";
                                $_SESSION["materials_message_type"] = "success";

                            } else {

                                error_log("Comment insertion failed: " . $stmt->error);

                                $_SESSION["materials_message"] = "Could not add your comment.";
                                $_SESSION["materials_message_type"] = "error";
                            }

                            $stmt->close();

                        } else {
                            error_log("Comment preparation failed: " . $conn->error);

                            $_SESSION["materials_message"] = "Could not process your comment.";
                            $_SESSION["materials_message_type"] = "error";
                        }
                    }
                }

            } else {
                $_SESSION["materials_message"] = "Invalid action.";
                $_SESSION["materials_message_type"] = "error";
            }
        }
    }

    $_SESSION["materials_csrf"] = bin2hex(random_bytes(32));

    header("Location: materials.php");
    exit;
}

$sql = "SELECT
            materials.material_id,
            materials.title,
            materials.description,
            materials.file_path,
            materials.created_at,
            subjects.subject_name,
            users.name AS uploader_name,

            COALESCE(
                (SELECT AVG(r.rating)
                 FROM resource_ratings r
                 WHERE r.material_id = materials.material_id),
                0
            ) AS average_rating,

            (SELECT COUNT(*)
             FROM resource_ratings r
             WHERE r.material_id = materials.material_id
            ) AS rating_count,

            COALESCE(
                (SELECT r.rating
                 FROM resource_ratings r
                 WHERE r.material_id = materials.material_id
                   AND r.user_id = $userId
                 LIMIT 1),
                0
            ) AS my_rating

        FROM materials
        LEFT JOIN subjects
            ON materials.subject_id = subjects.subject_id
        LEFT JOIN users
            ON materials.user_id = users.user_id
        WHERE materials.status = 'approved'
        ORDER BY materials.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    error_log("Materials loading query failed: " . $conn->error);

    http_response_code(500);
    exit("Unable to load study materials. Please try again later.");
}
?>

<html>
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta
        name="description"
        content="Explore academic notes, lecture materials and learning resources shared by the NSBM StudyCircle community."
    >

    <title>Study Materials | NSBM StudyCircle</title>

    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>

    * {
        box-sizing: border-box;
    }

    body {
        background-color: #F3F6F5;
        background-image:
            linear-gradient(rgba(243, 246, 245, 0.5), rgba(243, 246, 245, 0.5)),
            url("assets/studycircle2.jpg");
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }

    .hero {
        position: relative;
        isolation: isolate;
        overflow: hidden;

        min-height: 260px;
        padding: 42px 24px;

        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;

        text-align: center;

        background:
            radial-gradient(circle at 12% 15%, rgba(255,255,255,0.13), transparent 28%),
            radial-gradient(circle at 88% 85%, rgba(255,255,255,0.12), transparent 28%),
            linear-gradient(120deg, #106638, #16834A);

        box-shadow: 0 10px 25px rgba(16,102,56,0.12);
    }

    .hero::before,
    .hero::after {
        content: "";
        position: absolute;
        z-index: -1;

        border: 3px solid rgba(255,255,255,0.13);
        border-radius: 50%;

        pointer-events: none;
    }

    .hero::before {
        width: 300px;
        height: 300px;
        top: -50px;
        right: -60px;
    }

    .hero::after {
        width: 220px;
        height: 220px;

        bottom: -125px;
        left: -55px;
    }

    .sc-materials-label {
        display: inline-block;
        margin-bottom: 12px;
        padding: 6px 12px;

        color: #D8F3E2;
        background: rgba(255,255,255,0.12);

        border: 1px solid rgba(255,255,255,0.25);
        border-radius: 30px;

        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1.7px;
        text-transform: uppercase;
    }

    .hero h1 {
        width: 100%;
        max-width: 800px;

        margin: 0 auto 12px;

        color: #FFFFFF;

        font-weight: 600;
        line-height: 1.3;
        letter-spacing: -0.5px;
    }

    .hero p {
        width: 100%;
        max-width: 700px;

        margin: 0 auto;

        color: #EAF4ED;

        font-size: 14px;
        font-weight: 400;
        line-height: 1.7;
    }

    .container {
        width: 92%;
        max-width: 1200px;
        margin-top: 38px auto 60px;
        margin: 38px auto 60px;
    }

    .materials-panel {
        padding: 28px;
        margin-bottom: 26px;

        background: #FFFFFF;

        border: 1px solid #DCE5DF;
        border-radius: 16px;

        box-shadow: 0 5px 18px rgba(38,52,69,0.055);
    }

    .section-label {
        display: inline-block;

        margin-bottom: 12px;
        padding: 6px 12px;

        color: #106638;
        background: #F1F5F2;

        border: 1px solid #DCE5DF;
        border-radius: 30px;

        font-size: 13px;
        font-weight: 700;
        letter-spacing: 1.2px;
    }

    .section-heading {
        margin-bottom: 20px;
    }

    .section-heading h2 {
        margin: 0 0 5px;

        color: #106638;

        font-size: 22px;
        font-weight: 800;
    }

    .section-heading p {
        margin: 0;

        color: #657386;
        font-size: 14px;
    }

    .review-notice {
        padding: 14px 16px;
        margin-bottom: 22px;

        color: #106638;
        background: #E8F5EC;

        border: 1px solid #C8E5D0;
        border-radius: 9px;

        font-size: 14px;
    }

    .review-notice.error {
        color: #8A1C13;
        background: #FCE8E6;
        border-color: #F3C6C2;
    }

    .search-box {
        display: flex;
        align-items: center;

        gap: 14px;

        width: 100%;
        margin: 22px 0 28px;
    }

    .search-box input[type="search"],
    .search-box select {
        height: 50px;
        padding: 0 16px;

        border: 1px solid #DCE5DF;
        border-radius: 9px;

        background: #FFFFFF;
        color: #263445;

        font-family: inherit;
        font-size: 14px;

        outline: none;

        transition: border-color 0.2s ease,
                    box-shadow 0.2s ease;
    }

    .search-box input[type="search"] {
        flex: 1;
        min-width: 0;
    }

    .search-box select {
        flex: 0 0 230px;
        cursor: pointer;
    }

    .search-box input:focus,
    .search-box select:focus {
        border-color: #16834A;
        box-shadow: 0 0 0 3px rgba(22,131,74,0.10);
    }

    .materials-grid {
        display: grid;

        grid-template-columns: repeat(
            auto-fit,
            minmax(270px, 1fr)
        );

        gap: 22px;
        align-items: stretch;
    }

    .material-card {
        min-width: 0;

        display: flex;
        flex-direction: column;

        padding: 23px;

        background: #FFFFFF;

        border: 1px solid #DCE5DF;
        border-radius: 15px;

        box-shadow: 0 3px 14px rgba(38,52,69,0.05);

        overflow-wrap: anywhere;

        transition: transform 0.2s ease,
                    box-shadow 0.2s ease,
                    border-color 0.2s ease;
    }

    .material-card:hover {
        transform: translateY(-4px);

        border-color: #B8DCC4;

        box-shadow: 0 9px 24px rgba(38,52,69,0.09);
    }

    .file-icon {
        width: 52px;
        height: 52px;

        display: grid;
        place-items: center;

        margin-bottom: 17px;

        border-radius: 13px;

        background: #E8F5EC;
        color: #16834A;

        font-size: 26px;
    }

    .subject-tag {
        display: inline-block;
        align-self: flex-start;

        max-width: 100%;

        margin-bottom: 12px;
        padding: 5px 11px;

        border-radius: 20px;

        background: #E8F5EC;
        color: #106638;

        font-size: 12px;
        font-weight: 700;

        overflow-wrap: anywhere;
    }

    .material-card h3 {
        margin: 0 0 9px;

        color: #174A8B;

        font-size: 19px;
        font-weight: 700;
        line-height: 1.4;

        overflow-wrap: anywhere;
    }

    .description {
        margin: 0 0 18px;

        color: #455568;

        font-size: 14px;
        line-height: 1.7;

        overflow-wrap: anywhere;
    }

    .material-meta {
        margin-top: auto;
        padding-top: 13px;

        border-top: 1px solid #E5ECE7;

        color: #657386;
        font-size: 13px;
    }

    .material-meta p {
        margin: 4px 0;
    }

    .material-meta strong {
        color: #263445;
    }

    .download-btn {
        display: flex;
        align-items: center;
        justify-content: center;

        gap: 8px;

        margin-top: 17px;
        padding: 12px;

        border-radius: 9px;

        background: #16834A;
        color: #FFFFFF;

        font-size: 14px;
        font-weight: 700;

        text-decoration: none;

        transition: background 0.2s ease,
                    transform 0.2s ease,
                    box-shadow 0.2s ease;
    }

    .download-btn:hover {
        background: #106638;
        color: #FFFFFF;

        transform: translateY(-2px);

        box-shadow: 0 5px 12px rgba(16,102,56,0.15);
    }

    .unavailable {
        margin-top: 17px;
        padding: 11px;

        border-radius: 9px;

        background: #F1F3F5;
        color: #657386;

        text-align: center;
        font-size: 13px;
    }

    .empty-state {
        grid-column: 1 / -1;

        padding: 45px 20px;

        border: 1px dashed #B8C9BD;
        border-radius: 14px;

        background: #FFFFFF;

        text-align: center;
    }

    .empty-state h3 {
        margin: 0 0 8px;

        color: #174A8B;
        font-size: 19px;
    }

    .empty-state p {
        margin: 0;

        color: #657386;
        font-size: 14px;
    }

    .no-search-results {
        display: none;
        margin-top: 20px;
    }

    .review-section {
        margin-top: 21px;
        padding-top: 17px;

        border-top: 1px solid #DCE5DF;
    }

    .review-section h4 {
        margin: 0 0 11px;

        color: #174A8B;

        font-size: 15px;
        font-weight: 700;
    }

    .rating-summary {
        margin-bottom: 12px;

        color: #263445;
        font-size: 14px;
    }

    .stars {
        color: #E5A000;
        font-size: 20px;
    }

    .review-count,
    .review-login {
        color: #657386;
        font-size: 13px;
    }

    .review-login {
        line-height: 1.7;
    }

    .review-login a {
        color: #16834A;
        font-weight: 700;
        text-decoration: none;
    }

    .review-login a:hover {
        color: #106638;
        text-decoration: underline;
    }

    .review-form {
        margin-top: 13px;
    }

    .review-form label {
        display: block;
        margin-bottom: 7px;

        color: #263445;

        font-size: 13px;
        font-weight: 700;
    }

    .star-rating {
        display: flex;
        gap: 5px;

        margin: 8px 0 12px;
    }

    .rating-form .rating-star {
        padding: 2px;

        border: none;
        background: transparent;

        color: #B8C0C8;

        font-size: 30px;
        line-height: 1;

        cursor: pointer;

        transition: color 0.2s ease,
                    transform 0.2s ease;
    }

    .rating-form .rating-star.is-selected,
    .rating-form .rating-star:hover {
        color: #E5A000;
    }

    .rating-form .rating-star:hover {
        transform: scale(1.12);
    }

    .review-form textarea {
        display: block;

        width: 100%;
        min-height: 85px;

        margin-bottom: 8px;
        padding: 11px;

        border: 1px solid #DCE5DF;
        border-radius: 9px;

        background: #FFFFFF;
        color: #263445;

        font-family: inherit;
        font-size: 14px;
        line-height: 1.6;

        resize: vertical;
        outline: none;

        transition: border-color 0.2s ease,
                    box-shadow 0.2s ease;
    }

    .review-form textarea:focus {
        border-color: #16834A;
        box-shadow: 0 0 0 3px rgba(22,131,74,0.10);
    }

    .review-form button[type="submit"] {
        display: inline-flex;
        align-items: center;
        justify-content: center;

        width: 100%;

        margin-top: 5px;
        padding: 11px 14px;

        border: none;
        border-radius: 9px;

        background: #16834A;
        color: #FFFFFF;

        font-family: inherit;
        font-size: 13px;
        font-weight: 700;

        cursor: pointer;

        transition: background 0.2s ease,
                    transform 0.2s ease,
                    box-shadow 0.2s ease;
    }

    .review-form button[type="submit"]:hover {
        background: #106638;
        transform: translateY(-1px);

        box-shadow: 0 4px 10px rgba(16,102,56,0.15);
    }

    .comments-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .comment-item {
        padding: 13px;

        border: 1px solid #DCE5DF;
        border-radius: 10px;

        background: #F7FAF8;

        overflow-wrap: anywhere;
    }

    .comment-item strong {
        display: block;

        color: #106638;
        font-size: 13px;
    }

    .comment-item p {
        margin: 7px 0;

        color: #263445;

        font-size: 14px;
        line-height: 1.6;
    }

    .comment-item small {
        color: #657386;
        font-size: 11px;
    }

    @media (max-width: 768px) {

        .container {
            width: 94%;
            margin: 25px auto 40px;
        }

        .materials-panel {
            padding: 22px;
        }

        .hero {
            min-height: 250px;
            padding: 38px 20px;
        }

        .hero h1 {
            font-size: 30px;
        }

        .hero p {
            max-width: 560px;
            font-size: 13px;
        }

        .materials-grid {
            grid-template-columns: repeat(
                auto-fit,
                minmax(240px, 1fr)
            );
        }
    }

    @media (max-width: 650px) {

        .container {
            width: 94%;
            margin: 20px auto 35px;
        }

        .hero {
            min-height: 240px;
            padding: 35px 18px;
        }

        .hero h1 {
            font-size: 27px;
        }

        .hero p {
            font-size: 13px;
            line-height: 1.6;
        }

        .hero::after {
            right: 5%;
            font-size: 65px;
        }

        .materials-panel {
            padding: 18px;
            border-radius: 13px;
        }

        .section-heading h2 {
            font-size: 20px;
        }

        .search-box {
            flex-direction: column;
            align-items: stretch;

            gap: 11px;
            margin: 20px 0 25px;
        }

        .search-box input[type="search"],
        .search-box select {
            width: 100%;
            flex: auto;
        }

        .materials-grid {
            grid-template-columns: 1fr;
            gap: 16px;
        }

        .material-card {
            padding: 19px;
        }
    }

    @media (prefers-reduced-motion: reduce) {

        *,
        *::before,
        *::after {
            transition: none !important;
            scroll-behavior: auto !important;
        }
    }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>

<section class="hero">

    <h1>Study Materials</h1>

    <p>
        Explore academic notes, lecture materials and other learning
        resources shared by the StudyCircle community.
    </p>

</section>

<main class="container">

    <section class="materials-panel">

        <div class="section-heading">

            <h2>Explore Resources</h2>

            <p>
                Find learning materials to support your studies.
                Search by title, subject or keyword.
            </p>

        </div>

        <?php if ($materialsMessage !== ""): ?>

            <div class="review-notice <?= $materialsMessageType === "error" ? "error" : "" ?>">
                <?= e($materialsMessage) ?>
            </div>

        <?php endif; ?>

        <div class="search-box">

            <input
                type="search"
                id="materialSearch"
                placeholder="Search by material title, subject or description..."
                aria-label="Search study materials"
            >

            <select
                id="subjectFilter"
                aria-label="Filter materials by subject"
            >

                <option value="">All Subjects</option>

                <?php
                $subjectSql = "SELECT subject_name
                    FROM subjects
                    ORDER BY subject_name ASC";

                $subjectResult = $conn->query($subjectSql);

                if (!$subjectResult) {
                    error_log("Subject loading query failed: " . $conn->error);
                }

                if ($subjectResult) {
                    while ($subject = $subjectResult->fetch_assoc()) {
                        echo '<option value="' .
                            e($subject["subject_name"]) .
                            '">' .
                            e($subject["subject_name"]) .
                            '</option>';
                    }
                }
                ?>

            </select>

        </div>

        <div class="materials-grid" id="materialsGrid">

            <?php if ($result && $result->num_rows > 0): ?>

                <?php while ($material = $result->fetch_assoc()): ?>

                    <?php
                    $filePath = $material["file_path"] ?? "";

                    $uploadDirectory = realpath(
                        __DIR__ . DIRECTORY_SEPARATOR . "uploads"
                        . DIRECTORY_SEPARATOR . "materials"
                    );

                    $fullPath = __DIR__ . DIRECTORY_SEPARATOR
                        . str_replace("/", DIRECTORY_SEPARATOR, $filePath);

                    $realFile = realpath($fullPath);

                    $fileAvailable = (
                        $filePath !== "" &&
                        $uploadDirectory !== false &&
                        $realFile !== false &&
                        strpos(
                            $realFile,
                            $uploadDirectory . DIRECTORY_SEPARATOR
                        ) === 0 &&
                        is_file($realFile) &&
                        is_readable($realFile)
                    );
                    ?>

                    <?php
                    $commentsStmt = $conn->prepare(
                        "SELECT
                            rc.comment_id,
                            rc.user_id,
                            rc.comment,
                            rc.created_at,
                            u.name AS commenter_name
                         FROM resource_comments rc
                         LEFT JOIN users u ON u.user_id = rc.user_id
                         WHERE rc.material_id = ?
                         ORDER BY rc.created_at DESC
                         LIMIT 5"
                    );

                    $commentsResult = false;

                    if (!$commentsStmt) {

                        error_log("Comments query preparation failed: " . $conn->error);

                    } else {

                        $commentsStmt->bind_param("i", $material["material_id"]);

                        if (!$commentsStmt->execute()) {

                            error_log("Comments query execution failed: " . $commentsStmt->error);

                        } else {

                            $commentsResult = $commentsStmt->get_result();

                            if (!$commentsResult) {
                                error_log("Unable to retrieve comments query results.");
                            }
                        }
                    }
                    ?>

                    <article class="material-card">

                        <div class="file-icon" aria-hidden="true">
                            &#128196;
                        </div>

                        <span class="subject-tag material-subject">
                            <?= e($material["subject_name"] ?? "General") ?>
                        </span>

                        <h3>
                            <?= e($material["title"]) ?>
                        </h3>

                        <p class="description">
                            <?= e($material["description"] ?? "No description provided.") ?>
                        </p>

                        <div class="material-meta">

                            <p>
                                <strong>Shared by:</strong>
                                <?= e($material["uploader_name"] ?? "Student") ?>
                            </p>

                            <p>
                                <strong>Date added:</strong>
                                <?= e(date(
                                    "d M Y",
                                    strtotime($material["created_at"])
                                )) ?>
                            </p>

                        </div>

                        <?php if ($fileAvailable): ?>

                            <a
                                class="download-btn"
                                href="download.php?id=<?= (int)$material["material_id"] ?>"
                            >
                                <span aria-hidden="true">&#8681;</span>
                                Download Material
                            </a>

                        <?php else: ?>

                            <div class="unavailable">
                                File currently unavailable
                            </div>

                        <?php endif; ?>

                        <div class="review-section">

                            <h4>Student Ratings</h4>

                            <p class="rating-summary">

                                <span class="stars">★</span>

                                <?= number_format(
                                    (float)$material["average_rating"],
                                    1
                                ) ?>/5

                                <span class="review-count">
                                    (<?= (int)$material["rating_count"] ?> ratings)
                                </span>

                            </p>

                            <?php if ($isStudent): ?>

                                <form
                                    method="POST"
                                    action="materials.php"
                                    class="review-form rating-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION["materials_csrf"]) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="rate"
                                    >

                                    <input
                                        type="hidden"
                                        name="material_id"
                                        value="<?= (int)$material["material_id"] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="rating"
                                        class="rating-value"
                                        value="<?= (int)$material["my_rating"] ?>"
                                    >

                                    <label>Give your rating</label>

                                    <div class="star-rating">

                                        <?php for ($star = 1; $star <= 5; $star++): ?>

                                            <button
                                                type="button"
                                                class="rating-star <?= $star <= (int)$material["my_rating"] ? "is-selected" : "" ?>"
                                                data-value="<?= $star ?>"
                                                aria-label="<?= $star ?> stars"
                                                aria-pressed="<?= $star === (int)$material["my_rating"] ? "true" : "false" ?>"
                                            >★</button>

                                        <?php endfor; ?>

                                    </div>

                                    <button type="submit">
                                        Save Rating
                                    </button>

                                </form>

                            <?php else: ?>

                                <p class="review-login">
                                    <a href="login.php">Log in as a student</a>
                                    to rate this material.
                                </p>

                            <?php endif; ?>

                        </div>

                        <div class="review-section">

                            <h4>Student Comments</h4>

                            <div class="comments-list">

                                <?php if ($commentsResult && $commentsResult->num_rows > 0): ?>

                                    <?php while ($commentRow = $commentsResult->fetch_assoc()): ?>

                                        <div class="comment-item">

                                            <strong>
                                                <?= e($commentRow["commenter_name"] ?? "Student") ?>
                                            </strong>

                                            <p>
                                                <?= nl2br(e($commentRow["comment"])) ?>
                                            </p>

                                            <small>
                                                <?= e(date(
                                                    "d M Y, h:i A",
                                                    strtotime($commentRow["created_at"])
                                                )) ?>
                                            </small>

                                            <?php if (
                                                $isAdmin ||
                                                ($isStudent && (int)$commentRow["user_id"] === $userId)
                                            ): ?>

                                                <form
                                                    method="POST"
                                                    action="materials.php"
                                                    class="comment-delete-form"
                                                    onsubmit="return confirm('Are you sure you want to delete this comment?');"
                                                >

                                                    <input
                                                        type="hidden"
                                                        name="csrf_token"
                                                        value="<?= e($_SESSION["materials_csrf"]) ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="action"
                                                        value="delete_comment"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="material_id"
                                                        value="<?= (int)$material["material_id"] ?>"
                                                    >

                                                    <input
                                                        type="hidden"
                                                        name="comment_id"
                                                        value="<?= (int)$commentRow["comment_id"] ?>"
                                                    >

                                                    <button type="submit" class="comment-delete-button">
                                                        Delete Comment
                                                    </button>

                                                </form>

                                            <?php endif; ?>

                                        </div>

                                    <?php endwhile; ?>

                                <?php else: ?>

                                    <p class="review-login">
                                        No comments yet. Be the first to comment!
                                    </p>

                                <?php endif; ?>

                            </div>

                            <?php if ($isStudent): ?>

                                <form
                                    method="POST"
                                    action="materials.php"
                                    class="review-form"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION["materials_csrf"]) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="action"
                                        value="comment"
                                    >

                                    <input
                                        type="hidden"
                                        name="material_id"
                                        value="<?= (int)$material["material_id"] ?>"
                                    >

                                    <label for="comment_<?= (int)$material["material_id"] ?>">
                                        Write a comment
                                    </label>

                                    <textarea
                                        id="comment_<?= (int)$material["material_id"] ?>"
                                        name="comment"
                                        maxlength="2000"
                                        rows="3"
                                        placeholder="Share your thoughts about this material..."
                                        required
                                    ></textarea>

                                    <button type="submit">
                                        Post Comment
                                    </button>

                                </form>

                            <?php else: ?>

                                <p class="review-login">
                                    <a href="login.php">Log in as a student</a>
                                    to comment.
                                </p>

                            <?php endif; ?>

                        </div>

                        <?php
                        if ($commentsStmt) {
                            $commentsStmt->close();
                        }
                        ?>

                    </article>

                <?php endwhile; ?>

                <!-- NO SEARCH RESULTS MESSAGE -->

                <div
                    class="empty-state no-search-results"
                    id="noSearchResults"
                >

                    <h3>No matching materials found</h3>

                    <p>
                        Try another title, subject or keyword.
                    </p>

                </div>

            <?php else: ?>

                <div class="empty-state">

                    <h3>No study materials available yet</h3>

                    <p>
                        Approved learning resources will appear here
                        when they are shared with the community.
                    </p>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php include "includes/footer.php"; ?>

<script>

    const searchInput = document.getElementById("materialSearch");
    const subjectFilter = document.getElementById("subjectFilter");
    const cards = document.querySelectorAll(".material-card");
    const noResults = document.getElementById("noSearchResults");

    function filterMaterials() {

        const keyword = searchInput.value.toLowerCase().trim();

        const selectedSubject = subjectFilter.value.toLowerCase().trim();

        let visibleCount = 0;

        cards.forEach(function (card) {

            const cardText = card.textContent.toLowerCase();

            const subjectElement = card.querySelector(".material-subject");

            const cardSubject = subjectElement
                ? subjectElement.textContent.toLowerCase().trim()
                : "";

            const matchesSearch = cardText.includes(keyword);

            const matchesSubject =
                selectedSubject === "" ||
                cardSubject === selectedSubject;

            const shouldShow = matchesSearch && matchesSubject;

            card.style.display = shouldShow ? "flex" : "none";

            if (shouldShow) {
                visibleCount++;
            }

        });

        if (noResults) {

            noResults.style.display =
                cards.length > 0 && visibleCount === 0
                    ? "block"
                    : "none";

        }

    }

    if (searchInput && subjectFilter) {

        searchInput.addEventListener("input", filterMaterials);

        subjectFilter.addEventListener("change", filterMaterials);

    }

    document.querySelectorAll(".rating-form").forEach(function (form) {

        const stars = form.querySelectorAll(".rating-star");

        const ratingInput = form.querySelector(".rating-value");

        function updateStars(rating) {

            stars.forEach(function (star) {

                const value = Number(star.dataset.value);

                star.classList.toggle(
                    "is-selected",
                    value <= rating
                );

                star.setAttribute(
                    "aria-pressed",
                    value === rating ? "true" : "false"
                );

            });

            ratingInput.value = rating;

        }

        stars.forEach(function (star) {

            star.addEventListener("click", function () {

                const selectedRating = Number(this.dataset.value);

                updateStars(selectedRating);

            });

        });

    });

</script>

</body>
</html>