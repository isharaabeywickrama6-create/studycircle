<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

if (
    !isset($_SESSION["csrf_token"]) ||
    !is_string($_SESSION["csrf_token"])
) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["delete_discussion"])
) {

    $submittedToken = $_POST["csrf_token"] ?? null;

    if (
        !is_string($submittedToken) ||
        !hash_equals($_SESSION["csrf_token"], $submittedToken)
    ) {
        $error = "Invalid request. Please try again.";

    } else {

        $discussionId = filter_input(
            INPUT_POST,
            "discussion_id",
            FILTER_VALIDATE_INT
        );

        if (!$discussionId || $discussionId < 1) {

            $error = "Invalid discussion ID.";

        } else {

            $transactionStarted = false;

            try {

                if (!$conn->begin_transaction()) {
                    throw new RuntimeException(
                        "Unable to start transaction."
                    );
                }

                $transactionStarted = true;

                $stmt = $conn->prepare(
                    "DELETE FROM answers WHERE discussion_id = ?"
                );

                if (!$stmt) {
                    throw new RuntimeException(
                        "Unable to prepare answer deletion."
                    );
                }

                $stmt->bind_param("i", $discussionId);

                if (!$stmt->execute()) {
                    throw new RuntimeException(
                        "Unable to delete discussion answers."
                    );
                }

                $stmt = $conn->prepare(
                    "DELETE FROM discussions WHERE discussion_id = ?"
                );

                if (!$stmt) {
                    throw new RuntimeException(
                        "Unable to prepare discussion deletion."
                    );
                }

                $stmt->bind_param("i", $discussionId);

                if (!$stmt->execute()) {
                    throw new RuntimeException(
                        "Unable to delete discussion."
                    );
                }

                $deleted = $stmt->affected_rows;

                if (!$conn->commit()) {
                    throw new RuntimeException(
                        "Unable to commit transaction."
                    );
                }

                $transactionStarted = false;

                if ($deleted > 0) {
                    $message = "Discussion deleted successfully.";
                } else {
                    $message = "That discussion no longer exists.";
                }

                $_SESSION["csrf_token"] = bin2hex(random_bytes(32));

            } catch (Throwable $e) {

                if ($transactionStarted) {
                    try {
                        $conn->rollback();
                    } catch (Throwable $rollbackError) {
                        error_log(
                            "Discussion rollback failed: " .
                            $rollbackError->getMessage()
                        );
                    }
                }

                error_log(
                    "Discussion deletion failed: " .
                    $e->getMessage()
                );

                $error = "Unable to delete the discussion. Please try again.";
            }
        }
    }
}

$discussionSummary = [
    "total_discussions" => 0,
    "total_answers" => 0,
    "subjects_used" => 0
];

$discussionSummaryResult = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM discussions) AS total_discussions,
        (SELECT COUNT(*) FROM answers) AS total_answers,
        (SELECT COUNT(DISTINCT subject_id)
         FROM discussions
         WHERE subject_id IS NOT NULL) AS subjects_used
");

if ($discussionSummaryResult) {
    $discussionSummary = $discussionSummaryResult->fetch_assoc();
} else {
    error_log("Discussion summary query failed: " . $conn->error);
    $error = "Unable to load discussion statistics.";
}

$searchInput = $_GET["search"] ?? "";

if (!is_string($searchInput)) {
    $searchInput = "";
}

$search = trim($searchInput);

if (strlen($search) > 200) {
    $search = substr($search, 0, 200);
    $error = "Search text is too long. Only the first 200 characters were used.";
}

$sql = "
    SELECT
        d.discussion_id,
        d.title,
        d.body,
        d.created_at,
        u.name AS author_name,
        s.subject_name
    FROM discussions d
    LEFT JOIN users u ON d.user_id = u.user_id
    LEFT JOIN subjects s ON d.subject_id = s.subject_id
";

if ($search !== "") {
    $sql .= "
        WHERE d.title LIKE ? OR d.body LIKE ? OR u.name LIKE ?
    ";
}

$sql .= " ORDER BY d.created_at DESC";

$stmt = $conn->prepare($sql);

$result = false;

if ($stmt) {

    if ($search !== "") {

        $searchTerm = "%" . $search . "%";

        $stmt->bind_param(
            "sss",
            $searchTerm,
            $searchTerm,
            $searchTerm
        );
    }

    if ($stmt->execute()) {

        $result = $stmt->get_result();

    } else {

        error_log("Discussion retrieval failed: " . $stmt->error);
        $error = "Unable to load discussions.";
    }

} else {

    error_log("Discussion query preparation failed: " . $conn->error);
    $error = "Unable to load discussions.";
}
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Discussions | StudyCircle</title>
    <link rel="stylesheet" href="../assets/css/studycircle.css">

    <style>

        html, body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
        }

        .admin-container {
            flex: 1;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .page-heading {
            margin-bottom: 25px;
        }

        .page-heading h1 {
            color: var(--sc-green-dark);
            margin-bottom: 5px;
        }

        .page-heading p {
            color: var(--sc-muted);
            margin-top: 0;
        }

        .search-box {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
            background: white;
            padding: 18px;
            margin-bottom: 24px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .search-box input {
            flex: 1;
            min-width: 220px;
            padding: 11px;
            border: 1px solid var(--sc-border);
            border-radius: 7px;
        }

        .search-box input:focus {
            outline: 2px solid var(--sc-green-soft);
            border-color: var(--sc-green);
        }

        .search-button {
            background: var(--sc-green);
        }

        .search-button:hover {
            background: var(--sc-green-dark);
        }

        .show-all {
            text-decoration: none;
            font-weight: 600;
        }

        .discussion {
            background: white;
            padding: 22px;
            margin-bottom: 18px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .discussion h2 {
            color: var(--sc-green-dark);
            font-size: 21px;
            margin-top: 0;
        }

        .details {
            color: var(--sc-muted);
            font-size: 14px;
            line-height: 1.9;
        }

        .discussion-body {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            color: var(--sc-text);
            line-height: 1.7;
        }

        .delete-button {
            background: #B91C1C;
        }

        .delete-button:hover {
            background: #991B1B;
        }

        .message, .error {
            padding: 13px 16px;
            margin: 18px 0;
            border-radius: 8px;
        }

        .message {
            background: #DCFCE7;
            color: #166534;
        }

        .error {
            background: #FEE2E2;
            color: #991B1B;
        }

        .empty {
            background: white;
            padding: 25px;
            text-align: center;
            color: var(--sc-muted);
            border: 1px solid var(--sc-border);
            border-radius: 12px;
        }

        @media (max-width: 600px) {
            .admin-container {
                padding: 20px 12px;
            }

            .discussion {
                padding: 17px;
            }

            .search-box {
                align-items: stretch;
            }

            .search-box input {
                width: 100%;
            }
        }

        .discussion-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .discussion-summary-card {
            background: white;
            padding: 20px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .discussion-summary-card h3 {
            margin: 0 0 10px;
            color: var(--sc-muted);
            font-size: 14px;
            font-weight: 600;
        }

        .discussion-summary-card p {
            margin: 0;
            color: var(--sc-green-dark);
            font-size: 28px;
            font-weight: bold;
        }

        .discussion {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .discussion:hover {
            transform: translateY(-2px);
        }

        .discussion h2 {
            overflow-wrap: anywhere;
        }

        .discussion-body {
            overflow-wrap: anywhere;
        }

        .discussion form {
            margin-top: 18px;
        }

        .delete-button {
            padding: 10px 18px;
            border: none;
            border-radius: 7px;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        @media (max-width: 700px) {
            .discussion-summary {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

    </style>

</head>

<body>

<?php include "../includes/header.php"; ?>

<div class="admin-container">

    <div class="page-heading">
        <h1>Manage Discussions</h1>
        <p>Review student discussions and manage inappropriate posts.</p>
    </div>

    <div class="discussion-summary">

        <div class="discussion-summary-card">
            <h3>Total Discussions</h3>
            <p><?= (int) $discussionSummary["total_discussions"] ?></p>
        </div>
        <div class="discussion-summary-card">
            <h3>Total Answers</h3>
            <p><?= (int) $discussionSummary["total_answers"] ?></p>
        </div>
        <div class="discussion-summary-card">
            <h3>Subjects Used</h3>
            <p><?= (int) $discussionSummary["subjects_used"] ?></p>
        </div>

    </div>

    <?php if ($message !== ""): ?>

        <p class="message">
            <?= htmlspecialchars($message, ENT_QUOTES, "UTF-8") ?>
        </p>

    <?php endif; ?>

    <?php if ($error !== ""): ?>

        <p class="error">
            <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
        </p>

    <?php endif; ?>

    <form method="GET" class="search-box">

        <input
            type="text"
            name="search"
            placeholder="Search title, content, or author..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, "UTF-8") ?>"
        >

        <button 
            type="submit" 
            class="search-button"
        >
        Search
        </button>

        <a class="show-all" href="discussions.php">Show All</a>

    </form>

    <?php if ($result && $result->num_rows > 0): ?>

        <?php while ($discussion = $result->fetch_assoc()): ?>

            <div class="discussion">

                <h2>
                    <?= htmlspecialchars(
                        $discussion["title"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </h2>

                <p class="details">
                    <strong>Author:</strong>
                    <?= htmlspecialchars(
                        $discussion["author_name"] ?? "Unknown",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    |
                    <strong>Subject:</strong>
                    <?= htmlspecialchars(
                        $discussion["subject_name"] ?? "No subject",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>

                    |
                    <strong>Date:</strong>
                    <?= !empty($discussion["created_at"])
                        ? date("d M Y", strtotime($discussion["created_at"]))
                        : "Unknown" ?>
                </p>

                <p class="discussion-body">
                    <?= htmlspecialchars(
                        $discussion["body"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <form method="POST"
                      onsubmit="return confirm('Are you sure you want to delete this discussion and its answers? This cannot be undone.');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="discussion_id"
                        value="<?= (int) $discussion["discussion_id"] ?>"
                    >

                    <button
                        type="submit"
                        name="delete_discussion"
                        class="delete-button"
                    >
                        Delete Discussion
                    </button>

                </form>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty">
            No discussions found.
        </div>

    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>

</body>

</html>

<?php
$stmt->close();
?>