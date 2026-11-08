<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "config/database.php";

function e($value) {
    return htmlspecialchars(
        (string)($value ?? ""),
        ENT_QUOTES,
        "UTF-8"
    );
}

$isStudent = isset($_SESSION["user_id"])
    && ($_SESSION["role"] ?? "") === "student";

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

    if (!$isStudent) {
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

            } elseif ($action === "rate") {

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

                            error_log("Rating statement preparation failed: " . $conn->error);

                            $_SESSION["materials_message"] = "Could not save your rating.";
                            $_SESSION["materials_message_type"] = "error";
                        }

                        $stmt->close();

                    } else {
                        $_SESSION["materials_message"] = "Could not process your rating.";
                        $_SESSION["materials_message_type"] = "error";
                    }
                }

            } elseif ($action === "comment") {

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

                            error_log("Comment statement preparation failed: " . $conn->error);

                            $_SESSION["materials_message"] = "Could not add your comment.";
                            $_SESSION["materials_message_type"] = "error";
                        }

                        $stmt->close();

                    } else {
                        $_SESSION["materials_message"] = "Could not process your comment.";
                        $_SESSION["materials_message_type"] = "error";
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
    <title>Study Materials | StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: Arial, sans-serif;
            background: #F3F6F5;
            color: #263445;
            line-height: 1.6;
        }

        .navbar {
            background: white;
            padding: 16px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px #0000000D;
        }

        .logo {
            color: #16834A;
            font-size: 25px;
            font-weight: bold;
            text-decoration: none;
        }

        .nav-links {
            display: flex;
            flex-wrap: wrap;
            gap: 22px;
        }

        .nav-links a {
            text-decoration: none;
            color: #263445;
            font-size: 14px;
            font-weight: bold;
        }

        .nav-links a:hover,
        .nav-links a.active {
            color: #16834A;
        }

        .hero {
            background: linear-gradient(#106638, #16834A, #106638);
            color: white;
            padding: 55px 7%;
        }

        .hero p {
            margin-top: 10px;
            max-width: 650px;
            color: #F0F5FF;
        }

        .hero h1 {
            font-size: 34px;
            margin-bottom: 5px;
        }

        .container {
            max-width: 1150px;
            width: 86%;
            margin: 35px auto 60px;
        }

        .section-heading {
            margin-bottom: 20px;
        }

        .section-heading h2 {
            font-size: 25px;
            color: #263445;
        }

        .section-heading p {
            color: #657386;
            margin-top: 4px;
        }

        .search-box {
            margin-bottom: 28px;
        }

        .search-box input {
            width: 100%;
            padding: 14px 16px;
            border: 1px solid #D7E0E5;
            border-radius: 9px;
            background: white;
            font-size: 15px;
            outline: none;
        }

        .search-box input:focus {
            border-color: #16834A;
            box-shadow: 0 0 0 3px #16834A18;
        }

        .materials-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
            gap: 22px;
        }

        .material-card {
            background: white;
            border: 1px solid #E4EAE7;
            border-radius: 12px;
            padding: 23px;
            display: flex;
            flex-direction: column;
            box-shadow: 0 4px 14px #2634450A;
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .material-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 22px #26344512;
        }

        .file-icon {
            width: 48px;
            height: 48px;
            background: #E8F5EC;
            color: #16834A;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 23px;
            margin-bottom: 17px;
        }

        .subject-tag {
            display: inline-block;
            align-self: flex-start;
            background: #E8F5EC;
            color: #106638;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 12px;
        }

        .material-card h3 {
            font-size: 19px;
            line-height: 1.4;
            margin-bottom: 10px;
            overflow-wrap: anywhere;
        }

        .description {
            color: #657386;
            font-size: 14px;
            margin-bottom: 18px;
            overflow-wrap: anywhere;
        }

        .material-meta {
            color: #657386;
            font-size: 13px;
            margin-top: auto;
            padding-top: 12px;
            border-top: 1px solid #EDF0F2;
        }

        .download-btn {
            display: block;
            text-align: center;
            background: #16834A;
            color: white;
            padding: 11px;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
            margin-top: 17px;
        }

        .download-btn:hover {
            background: #106638;
        }

        .unavailable {
            background: #F1F3F5;
            color: #657386;
            padding: 10px;
            text-align: center;
            border-radius: 7px;
            margin-top: 17px;
            font-size: 13px;
        }

        .empty-state {
            background: white;
            border: 1px dashed #C9D6CF;
            border-radius: 12px;
            padding: 45px 20px;
            text-align: center;
            grid-column: 1 / -1;
        }

        .empty-state h3 {
            color: #263445;
            margin-bottom: 8px;
        }

        .empty-state p {
            color: #657386;
        }

        .no-search-results {
            display: none;
            color: #657386;
            margin-top: 20px;
        }

        @media (max-width: 600px) {
            .navbar {
                padding: 15px 5%;
            }

            .nav-links {
                gap: 14px;
            }

            .hero {
                padding: 38px 6%;
            }

            .hero h1 {
                font-size: 27px;
            }

            .container {
                width: 90%;
                margin-top: 25px;
            }
        }

        .search-box::selection {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            max-width: 850px;
            margin: 28px auto 35px;
        }

        .search-box input[type="search"],
        .search-box select {
            box-sizing: border-box;
            height: 50px;
            padding: 0 16px;
            border: 1px solid #d9e2ec;
            border-radius: 10px;
            background: #ffffff;
            color: #263445;
            font-family: inherit;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-box input[type="search"] {
            flex: 1;
            min-width: 0;
        }

        .search-box select {
            flex: 0 0 220px;
            cursor: pointer;
        }

        .search-box input[type="search"]:focus,
        .search-box select:focus {
            border-color: #16834a;
            box-shadow: 0 0 0 3px rgba(22, 131, 74, 0.12);
        }

        .search-box input::placeholder {
            color: #8793a2;
        }

        @media (max-width: 600px) {
            .search-box {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .search-box input[type="search"],
            .search-box select {
                width: 100%;
                flex: auto;
            }
        }

        .search-box {
            display: flex;
            align-items: center;
            gap: 14px;
            width: 100%;
            max-width: 850px;
            margin: 28px auto 35px;
            box-sizing: border-box;
        }

        .search-box input[type="search"],
        .search-box select {
            box-sizing: border-box;
            height: 50px;
            padding: 0 16px;
            border: 1px solid #d9e2ec;
            border-radius: 10px;
            background-color: #ffffff;
            color: #263445;
            font-family: inherit;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        .search-box input[type="search"] {
            flex: 1;
            min-width: 0;
        }

        .search-box select {
            flex: 0 0 220px;
            width: 220px;
            cursor: pointer;
            appearance: auto;
        }

        .search-box input[type="search"]:focus,
        .search-box select:focus {
            border-color: #16834a;
            box-shadow: 0 0 0 3px rgba(22, 131, 74, 0.12);
        }

        .search-box input::placeholder {
            color: #8793a2;
        }

        @media (max-width: 600px) {
            .search-box {
                flex-direction: column;
                align-items: stretch;
                gap: 12px;
            }

            .search-box input[type="search"],
            .search-box select {
                width: 100%;
                flex: auto;
            }
        }

        .review-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--sc-border);
        }

        .review-section h4 {
            color: var(--sc-blue);
            font-size: 16px;
            margin-bottom: 10px;
        }

        .rating-summary {
            font-size: 14px;
            margin-bottom: 12px;
        }

        .stars {
            color: #E5A000;
            font-size: 20px;
        }

        .review-count,
        .review-login {
            color: var(--sc-muted);
            font-size: 13px;
        }

        .review-form {
            margin-top: 12px;
        }

        .review-form label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .review-form select,
        .review-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--sc-border);
            border-radius: 7px;
            font: inherit;
            margin-bottom: 8px;
        }

        .review-form textarea {
            resize: vertical;
        }

        .review-form button {
            margin-top: 4px;
            padding: 9px 14px;
        }

        .comment-item {
            background: #F7FAF8;
            border: 1px solid var(--sc-border);
            border-radius: 8px;
            padding: 11px;
            margin: 10px 0;
            overflow-wrap: anywhere;
        }

        .comment-item strong {
            color: var(--sc-green-dark);
            font-size: 13px;
        }

        .comment-item p {
            font-size: 14px;
            margin: 5px 0;
        }

        .comment-item small {
            color: var(--sc-muted);
            font-size: 11px;
        }

        .review-notice {
            padding: 13px;
            margin-bottom: 20px;
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
            border-radius: 8px;
        }

        .review-notice.error {
            background: #FCE8E6;
            color: #8A1C13;
        }

        .star-rating {
            display: flex;
            gap: 5px;
            margin: 8px 0 12px;
        }

        .rating-form .rating-star {
            background: transparent;
            color: #B8C0C8;
            border: none;
            padding: 2px;
            font-size: 30px;
            cursor: pointer;
            line-height: 1;
        }

        .rating-form .rating-star.is-selected {
            color: #E5A000;
        }

        .rating-form .rating-star:hover {
            color: #E5A000;
        }

        .review-section {
            margin-top: 20px;
            padding-top: 16px;
            border-top: 1px solid var(--sc-border);
        }

        .review-section h4 {
            color: var(--sc-blue);
            font-size: 16px;
            margin-bottom: 10px;
        }
            
        .rating-summary {
            font-size: 14px;
            margin-bottom: 12px;
        }
            
        .stars {
            color: #E5A000;
            font-size: 20px;
        }
            
        .review-count,
        .review-login {
            color: var(--sc-muted);
            font-size: 13px;
        }
            
        .comments-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
            
        .comment-item {
            background: #F7FAF8;
            border: 1px solid var(--sc-border);
            border-radius: 9px;
            padding: 13px;
            overflow-wrap: anywhere;
        }
            
        .comment-item strong {
            display: block;
            color: var(--sc-green-dark);
            font-size: 14px;
        }
            
        .comment-item p {
            font-size: 14px;
            margin: 7px 0;
        }
            
        .comment-item small {
            color: var(--sc-muted);
            font-size: 11px;
        }
            
        .review-form {
            margin-top: 12px;
        }
            
        .review-form label {
            display: block;
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 6px;
        }
            
        .review-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--sc-border);
            border-radius: 7px;
            font: inherit;
            margin-bottom: 8px;
            resize: vertical;
        }
            
        .review-form button[type="submit"] {
            margin-top: 4px;
            padding: 9px 14px;
        }
            
        .review-notice {
            padding: 13px;
            margin-bottom: 20px;
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
            border-radius: 8px;
        }
            
        .review-notice.error {
            background: #FCE8E6;
            color: #8A1C13;
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

    <div class="section-heading">
        <h2>Explore Resources</h2>
        <p>Find learning materials to support your studies.</p>
    </div>

<?php if ($materialsMessage !== ""): ?>

    <div class="review-notice <?= $materialsMessageType === "error" ? "error" : "" ?>">
        <?= htmlspecialchars($materialsMessage, ENT_QUOTES, "UTF-8") ?>
    </div>

<?php endif; ?>

    <div class="search-box">

        <input
            type="search"
            id="materialSearch"
            placeholder="Search by material title, subject or description..."
            aria-label="Search study materials"
        >

        <select id="subjectFilter">

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
                        htmlspecialchars($subject["subject_name"], ENT_QUOTES, "UTF-8") .
                        '">' .
                        htmlspecialchars($subject["subject_name"], ENT_QUOTES, "UTF-8") .
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
                    "SELECT rc.comment, rc.created_at, u.name AS commenter_name
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
                        <?= htmlspecialchars(
                            $material["subject_name"] ?? "General"
                        ) ?>
                    </span>

                    <h3>
                        <?= htmlspecialchars($material["title"]) ?>
                    </h3>

                    <p class="description">
                        <?= htmlspecialchars(
                            $material["description"] ?? "No description provided."
                        ) ?>
                    </p>

                    <div class="material-meta">
                        <p>
                            <strong>Shared by:</strong>
                            <?= htmlspecialchars(
                                $material["uploader_name"] ?? "Student"
                            ) ?>
                        </p>

                        <p>
                            <strong>Date added:</strong>
                            <?= htmlspecialchars(
                                date(
                                    "d M Y",
                                    strtotime($material["created_at"])
                                )
                            ) ?>
                        </p>
                    </div>

                    <?php if ($fileAvailable): ?>

                        <a
                            class="download-btn"
                            href="download.php?id=<?= (int) $material['material_id'] ?>"
                        >
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
                            <?= number_format((float)$material["average_rating"], 1) ?>/5

                            <span class="review-count">
                                (<?= (int)$material["rating_count"] ?> ratings)
                            </span>
                        </p>

                        <?php if ($isStudent): ?>

                            <form method="POST" action="materials.php"
                                  class="review-form rating-form">

                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION["materials_csrf"], ENT_QUOTES, "UTF-8") ?>">

                                <input type="hidden" name="action" value="rate">

                                <input type="hidden" name="material_id"
                                    value="<?= (int)$material["material_id"] ?>">

                                <input type="hidden" name="rating"
                                    class="rating-value"
                                    value="<?= (int)$material["my_rating"] ?>">

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

                                <button type="submit">Save Rating</button>

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
                                            <?= htmlspecialchars(
                                                $commentRow["commenter_name"] ?? "Student",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </strong>

                                        <p>
                                            <?= nl2br(htmlspecialchars(
                                                $commentRow["comment"],
                                                ENT_QUOTES,
                                                "UTF-8"
                                            )) ?>
                                        </p>

                                        <small>
                                            <?= date(
                                                "d M Y, h:i A",
                                                strtotime($commentRow["created_at"])
                                            ) ?>
                                        </small>

                                    </div>

                                <?php endwhile; ?>

                            <?php else: ?>

                                <p class="review-login">No comments yet. Be the first to comment!</p>

                            <?php endif; ?>

                        </div>

                        <?php if ($isStudent): ?>

                            <form method="POST" action="materials.php"
                                  class="review-form">

                                <input type="hidden" name="csrf_token"
                                    value="<?= htmlspecialchars($_SESSION["materials_csrf"], ENT_QUOTES, "UTF-8") ?>">

                                <input type="hidden" name="action" value="comment">

                                <input type="hidden" name="material_id"
                                    value="<?= (int)$material["material_id"] ?>">

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

                                <button type="submit">Post Comment</button>

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

            <div class="empty-state" id="noSearchResults"
                 style="display: none;">
                <h3>No matching materials found</h3>
                <p>Try another title, subject or keyword.</p>
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

    searchInput.addEventListener("input", filterMaterials);
    subjectFilter.addEventListener("change", filterMaterials);

    document.querySelectorAll(".rating-form").forEach(function (form) {

        const stars = form.querySelectorAll(".rating-star");
        const ratingInput = form.querySelector(".rating-value");

        function updateStars(rating) {

            stars.forEach(function (star) {

               const value = Number(star.dataset.value);

                star.classList.toggle("is-selected", value <= rating);

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