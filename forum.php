<?php
session_start();

require_once "config/database.php";

$isStudent = isset($_SESSION["user_id"])
    && isset($_SESSION["role"])
    && $_SESSION["role"] === "student";

if (
    !isset($_SESSION["forum_csrf"]) ||
    !is_string($_SESSION["forum_csrf"])
) {
    $_SESSION["forum_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "success";

function validForumToken(): bool
{
    $token = $_POST["csrf_token"] ?? "";

    return is_string($token)
        && hash_equals($_SESSION["forum_csrf"], $token);
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["reply_submit"])
) {
    if (!$isStudent) {
        header("Location: login.php");
        exit;
    }

    if (!validForumToken()) {
        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";
    } else {
        $discussionInput = $_POST["discussion_id"] ?? "";
        $replyInput = $_POST["reply_body"] ?? "";

        $discussionId = is_string($discussionInput)
            ? filter_var($discussionInput, FILTER_VALIDATE_INT)
            : false;

        $replyBody = is_string($replyInput)
            ? trim($replyInput)
            : "";

        if ($discussionId === false || $discussionId < 1) {
            $message = "Invalid discussion.";
            $messageType = "error";
        } elseif ($replyBody === "") {
            $message = "Please enter a reply.";
            $messageType = "error";
        } elseif (mb_strlen($replyBody) > 5000) {
            $message = "Your reply cannot exceed 5000 characters.";
            $messageType = "error";
        } else {
            $check = $conn->prepare(
                "SELECT discussion_id FROM discussions WHERE discussion_id = ?"
            );

            if (!$check) {
                error_log("Forum discussion check preparation failed: " . $conn->error);
                $message = "Unable to process your reply. Please try again.";
                $messageType = "error";
            } else {
                $check->bind_param("i", $discussionId);

                if (!$check->execute()) {
                    error_log("Forum discussion check failed: " . $check->error);
                    $message = "Unable to process your reply. Please try again.";
                    $messageType = "error";
                    $exists = false;
                } else {
                    $exists = $check->get_result()->num_rows > 0;
                }

                $check->close();

                if ($message === "") {
                    if (!$exists) {
                        $message = "The discussion could not be found.";
                        $messageType = "error";
                    } else {
                        $userId = (int) $_SESSION["user_id"];

                        $stmt = $conn->prepare(
                            "INSERT INTO answers (discussion_id, user_id, body)
                             VALUES (?, ?, ?)"
                        );

                        if (!$stmt) {
                            error_log("Forum reply preparation failed: " . $conn->error);
                            $message = "Could not save your reply. Please try again.";
                            $messageType = "error";
                        } else {
                            $stmt->bind_param(
                                "iis",
                                $discussionId,
                                $userId,
                                $replyBody
                            );

                            if ($stmt->execute()) {
                                $stmt->close();

                                $_SESSION["forum_csrf"] = bin2hex(random_bytes(32));

                                header("Location: forum.php?reply_posted=1");
                                exit;
                            } else {
                                error_log("Forum reply insertion failed: " . $stmt->error);
                                $message = "Could not save your reply. Please try again.";
                                $messageType = "error";
                            }

                            $stmt->close();
                        }
                    }
                }
            }
        }
    }
}

if (
    isset($_GET["reply_posted"]) &&
    $_GET["reply_posted"] === "1"
) {
    $message = "Your reply was posted successfully!";
    $messageType = "success";
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    !isset($_POST["reply_submit"])
) {
    if (!$isStudent) {
        header("Location: login.php");
        exit;
    }

    if (!validForumToken()) {
        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";
    } else {
        $titleInput = $_POST["title"] ?? "";
        $bodyInput = $_POST["body"] ?? "";
        $subjectInput = $_POST["subject_id"] ?? "";

        $title = is_string($titleInput) ? trim($titleInput) : "";
        $body = is_string($bodyInput) ? trim($bodyInput) : "";

        if (!is_string($subjectInput)) {
            $subjectInput = "";
        }

        if ($title === "" || $body === "") {
            $message = "Please enter both a discussion title and message.";
            $messageType = "error";
        } elseif (mb_strlen($title) > 200) {
            $message = "The title cannot exceed 200 characters.";
            $messageType = "error";
        } elseif (mb_strlen($body) > 10000) {
            $message = "Your discussion message cannot exceed 10000 characters.";
            $messageType = "error";
        } else {
            $subjectValue = null;

            if ($subjectInput !== "") {
                $subjectValue = filter_var(
                    $subjectInput,
                    FILTER_VALIDATE_INT
                );

                if ($subjectValue === false || $subjectValue < 1) {
                    $message = "Please select a valid subject.";
                    $messageType = "error";
                } else {
                    $check = $conn->prepare(
                        "SELECT subject_id FROM subjects WHERE subject_id = ?"
                    );

                    if (!$check) {
                        error_log("Forum subject check preparation failed: " . $conn->error);
                        $message = "Unable to validate the selected subject.";
                        $messageType = "error";
                    } else {
                        $check->bind_param("i", $subjectValue);

                        if (!$check->execute()) {
                            error_log("Forum subject check failed: " . $check->error);
                            $message = "Unable to validate the selected subject.";
                            $messageType = "error";
                        } else {
                            $subjectResult = $check->get_result();

                            if ($subjectResult->num_rows === 0) {
                                $message = "The selected subject does not exist.";
                                $messageType = "error";
                            }
                        }

                        $check->close();
                    }
                }
            }

            if ($message === "") {
                $userId = (int) $_SESSION["user_id"];

                $stmt = $conn->prepare(
                    "INSERT INTO discussions
                    (user_id, subject_id, title, body)
                    VALUES (?, ?, ?, ?)"
                );

                if (!$stmt) {
                    error_log("Forum discussion preparation failed: " . $conn->error);
                    $message = "Could not publish your discussion. Please try again.";
                    $messageType = "error";
                } else {
                    $stmt->bind_param(
                        "iiss",
                        $userId,
                        $subjectValue,
                        $title,
                        $body
                    );

                    if ($stmt->execute()) {
                        $stmt->close();

                        $_SESSION["forum_csrf"] = bin2hex(random_bytes(32));

                        header("Location: forum.php?posted=1");
                        exit;
                    } else {
                        error_log("Forum discussion insertion failed: " . $stmt->error);
                        $message = "Could not publish your discussion. Please try again.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }
    }
}

if (
    isset($_GET["posted"]) &&
    $_GET["posted"] === "1"
) {
    $message = "Your discussion was published successfully!";
    $messageType = "success";
}

$subjects = $conn->query(
    "SELECT subject_id, subject_name
     FROM subjects
     ORDER BY subject_name"
);

if (!$subjects) {
    error_log("Forum subjects query failed: " . $conn->error);
}

$discussions = $conn->query(
    "SELECT d.discussion_id, d.title, d.body, d.created_at,
            u.name AS author_name,
            s.subject_name,
            (SELECT COUNT(*)
             FROM answers a
             WHERE a.discussion_id = d.discussion_id) AS answer_count
     FROM discussions d
     LEFT JOIN users u ON d.user_id = u.user_id
     LEFT JOIN subjects s ON d.subject_id = s.subject_id
     ORDER BY d.created_at DESC"
);

if (!$discussions) {
    error_log("Forum discussions query failed: " . $conn->error);
}

function e($value): string
{
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES | ENT_SUBSTITUTE,
        "UTF-8"
    );
}
?>

<html>

<head>
    <title>Academic Forum | NSBM StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #F3F6F5;
            color: #263445;
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
            width: 90%;
            max-width: 1000px;
            margin: 30px auto;
        }

        .panel {
            background: white;
            padding: 24px;
            margin-bottom: 24px;
            border-radius: 12px;
            box-shadow: 0 3px 12px rgba(0, 0, 0, 0.06);
        }

        h2 {
            color: #106638;
            margin-top: 0;
        }

        label {
            display: block;
            font-weight: bold;
            margin: 15px 0 7px;
        }

        input, textarea, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #CBD5D1;
            border-radius: 7px;
            font: inherit;
        }

        textarea {
            min-height: 130px;
            resize: vertical;
        }

        button, .button {
            display: inline-block;
            background: #16834A;
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 7px;
            text-decoration: none;
            font-weight: bold;
            cursor: pointer;
            margin-top: 16px;
        }

        button:hover, .button:hover {
            background: #106638;
        }

        .discussion {
            border: 1px solid #E1E8E4;
            padding: 20px;
            border-radius: 10px;
            margin-top: 16px;
            overflow-wrap: anywhere;
        }

        .discussion h3 {
            margin-top: 0;
            color: #174A8B;
        }

        .meta {
            color: #657386;
            font-size: 14px;
            margin-bottom: 14px;
        }

        .notice {
            padding: 13px;
            border-radius: 7px;
            margin-bottom: 18px;
            background: #E8F5EC;
        }

        .notice.error {
            background: #FCE8E6;
            color: #8A1C13;
        }

        @media (max-width: 600px) {
            .container {
                width: 94%;
                margin: 18px auto;
            }

            .panel {
                padding: 17px;
            }

            nav a {
                display: inline-block;
                margin: 5px 12px 5px 0;
            }
        }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>

<section class="hero">

    <h1>Discussion Forum</h1>
    <p>
        Welcome to the NSBM StudyCircle Academic Forum! This is a place where 
        students can ask questions, share ideas, and discuss academic subjects 
        with their peers. Whether you're looking for help with a specific topic 
        or want to engage in meaningful discussions, you've come to the right place.
    </p>

</section>

<main class="container">

    <section class="panel sc-card">

        <h2>Welcome to the Academic Forum</h2>

        <p>Ask questions, share ideas and discuss your academic subjects
           with other students.</p>

        <?php if ($message !== ""): ?>
            <div class="notice <?= $messageType === "error" ? "error" : "" ?>">
                <?= e($message) ?>
            </div>
        <?php endif; ?>

        <?php if ($isStudent): ?>

            <h2>Start a Discussion</h2>

            <form method="POST" action="forum.php">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION["forum_csrf"]) ?>"
                >

                <label for="title">Discussion Title</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="200"
                    required
                    placeholder="Enter your question or topic"
                >

                <label for="subject_id">Subject (optional)</label>

                <select id="subject_id" name="subject_id">

                    <option value="">General Discussion</option>

                    <?php if ($subjects): ?>
                        <?php while ($subject = $subjects->fetch_assoc()): ?>
                            <option value="<?= (int) $subject["subject_id"] ?>">
                                <?= e($subject["subject_name"]) ?>
                            </option>
                        <?php endwhile; ?>
                    <?php endif; ?>

                </select>

                <label for="body">Your Message</label>

                <textarea
                    id="body"
                    name="body"
                    required
                    placeholder="Write your question or discussion here..."
                ></textarea>

                <button type="submit">Publish Discussion</button>

            </form>

        <?php else: ?>

            <p>Please log in with a student account to publish a discussion.</p>
            <a class="button" href="login.php">Student Login</a>

        <?php endif; ?>

    </section>

    <section class="panel sc-card">

        <h2>Recent Discussions</h2>

        <?php if (!$discussions): ?>

            <p>We could not load discussions. Please check the database configuration.</p>

        <?php elseif ($discussions->num_rows === 0): ?>

            <p>No discussions have been posted yet. Be the first to start one!</p>

        <?php else: ?>

            <?php while ($discussion = $discussions->fetch_assoc()): ?>

                <article class="discussion">

                    <h3><?= e($discussion["title"]) ?></h3>

                    <div class="meta">
                        Posted by <?= e($discussion["author_name"] ?? "Student") ?>
                        · <?= e($discussion["subject_name"] ?? "General Discussion") ?>
                        · <?= e($discussion["created_at"]) ?>
                    </div>

                    <p><?= nl2br(e($discussion["body"])) ?></p>

                    <div class="meta">
                        Replies: <?= (int) $discussion["answer_count"] ?>
                    </div>

                    <?php
                    $replyStmt = $conn->prepare(
                        "SELECT a.body, a.created_at, u.name AS author_name
                         FROM answers a
                         LEFT JOIN users u ON a.user_id = u.user_id
                         WHERE a.discussion_id = ?
                         ORDER BY a.created_at ASC"
                    );
                    $replyStmt->bind_param("i", $discussion["discussion_id"]);
                    $replyStmt->execute();
                    $replies = $replyStmt->get_result();
                    ?>

                    <?php while ($reply = $replies->fetch_assoc()): ?>
                        <div class="discussion" style="margin-left: 15px; background: #F7FAF8;">
                            <strong><?= e($reply["author_name"] ?? "Student") ?></strong>
                            <div class="meta"><?= e($reply["created_at"]) ?></div>
                            <p><?= nl2br(e($reply["body"])) ?></p>
                        </div>
                    <?php endwhile; ?>

                    <?php $replyStmt->close(); ?>

                    <?php if ($isStudent): ?>
                        <form method="POST" action="forum.php">
                            <input type="hidden" name="reply_submit" value="1">
                            <input type="hidden" name="csrf_token"
                                value="<?= e($_SESSION["forum_csrf"]) ?>">
                            <input type="hidden" name="discussion_id"
                                value="<?= (int) $discussion["discussion_id"] ?>">

                            <label for="reply_<?= (int) $discussion["discussion_id"] ?>">
                                Write a reply
                            </label>
                            <textarea
                                id="reply_<?= (int) $discussion["discussion_id"] ?>"
                                name="reply_body"
                                required
                                placeholder="Write your reply here..."
                            ></textarea>

                            <button type="submit">Post Reply</button>

                        </form>

                    <?php endif; ?>

                </article>

            <?php endwhile; 
            ?>

        <?php endif; ?>
        
    </section>

</main>

<?php include "includes/footer.php"; ?>

</body>
</html>