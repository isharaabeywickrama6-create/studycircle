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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Join the NSBM StudyCircle Academic Forum to ask questions, share ideas and learn together.">

    <title>Academic Forum | NSBM StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

<style>

* {
    box-sizing: border-box;
}

body {
    background-color: #F3F6F5;
    background-image:
        linear-gradient(rgba(243, 246, 245, 0.5), rgba(243, 246, 245, 0.5)),
        url("assets/studycircle3.jpg");
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
        linear-gradient(125deg, #105A35, #16834A 55%, #21965B);

    box-shadow: 0 10px 25px rgba(16,102,56,0.12);
}

.hero::before,
.hero::after {
    content: "";
    position: absolute;
    z-index: -1;
    border: 2px solid rgba(255,255,255,0.13);
    border-radius: 50%;
    pointer-events: none;
}

.hero::before {
    width: 200px;
    height: 200px;
    top: -150px;
    right: -60px;
}

.hero::after {
    width: 220px;
    height: 220px;
    bottom: -25px;
    left: -55px;
}

.hero h2 {
    width: 100%;
    max-width: 800px;
    margin: 0 auto 12px;

    color: #FFFFFF;
    font-size: clamp(27px, 3.5vw, 36px);
    font-weight: 600;
    line-height: 1.3;
    letter-spacing: -0.5px;
    text-align: center;
}

.hero p {
    width: 100%;
    max-width: 1000px;
    margin: 0 auto;

    color: #EAF4ED;
    font-size: 14px;
    font-weight: 400;
    line-height: 1.7;
    text-align: center;
}

.container {
    width: 92%;
    max-width: 1200px;
    margin: 38px auto;
}

.panel {
    padding: 28px;
    margin-bottom: 26px;

    background: #FFFFFF;
    border: 1px solid #DCE5DF;
    border-radius: 16px;

    box-shadow: 0 5px 18px rgba(38,52,69,0.055);
}

.panel h2 {
    margin-top: 0;
    margin-bottom: 12px;

    color: #106638;
    font-size: 22px;
    font-weight: 800;
}

.panel > p {
    color: #657386;
    font-size: 14px;
}

label {
    display: block;
    margin: 18px 0 8px;

    color: #263445;
    font-size: 14px;
    font-weight: 600;
}

input,
textarea,
select {
    display: block;
    width: 100%;
    padding: 13px 14px;

    color: #263445;
    background: #FFFFFF;

    border: 1px solid #DCE5DF;
    border-radius: 9px;

    font-family: inherit;
    font-size: 14px;

    outline: none;
    transition: border-color 0.2s ease, box-shadow 0.2s ease;
}

input:focus,
textarea:focus,
select:focus {
    border-color: #16834A;
    box-shadow: 0 0 0 3px rgba(22,131,74,0.10);
}

textarea {
    min-height: 130px;
    resize: vertical;
    line-height: 1.6;
}

button,
.button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    padding: 12px 21px;
    margin-top: 16px;

    color: #FFFFFF;
    background: #16834A;

    border: none;
    border-radius: 9px;

    font-family: inherit;
    font-size: 14px;
    font-weight: 600;

    text-decoration: none;
    cursor: pointer;

    transition: background 0.2s ease,
                transform 0.2s ease,
                box-shadow 0.2s ease;
}

button:hover,
.button:hover {
    background: #106638;
    transform: translateY(-2px);
    box-shadow: 0 5px 12px rgba(16,102,56,0.15);
}

.notice {
    padding: 14px 16px;
    margin-bottom: 20px;

    color: #106638;
    background: #E8F5EC;

    border: 1px solid #C8E5D0;
    border-radius: 9px;

    font-size: 14px;
}

.notice.error {
    color: #8A1C13;
    background: #FCE8E6;
    border-color: #F3C6C2;
}

.discussion {
    padding: 23px;
    margin-top: 18px;

    background: #FFFFFF;
    border: 1px solid #DCE5DF;
    border-left: 4px solid #16834A;
    border-radius: 12px;

    overflow-wrap: anywhere;

    transition: box-shadow 0.25s ease,
                border-color 0.25s ease;
}

.discussion:hover {
    border-color: #B8DCC4;
    box-shadow: 0 6px 18px rgba(38,52,69,0.07);
}

.discussion h3 {
    margin: 0 0 10px;

    color: #174A8B;
    font-size: 19px;
    font-weight: 700;
    line-height: 1.4;
}

.discussion p {
    color: #455568;
    font-size: 14px;
    line-height: 1.8;
    overflow-wrap: anywhere;
}

.meta {
    margin-bottom: 13px;

    color: #657386;
    font-size: 12px;
    line-height: 1.7;
}

.discussion .discussion {
    padding: 17px;
    margin: 18px 0 0 15px;

    background: #F3F6F5;
    border: 1px solid #DCE5DF;
    border-left: 3px solid #174A8B;
    border-radius: 10px;

    box-shadow: none;
}

.discussion .discussion:hover {
    background: #EEF4F0;
}

.discussion .discussion strong {
    color: #174A8B;
    font-size: 14px;
}

.discussion .discussion p {
    margin-bottom: 0;
}

.discussion form {
    margin-top: 20px;
    padding-top: 16px;
    border-top: 1px solid #E5ECE7;
}

@media (max-width: 768px) {

    .container {
        width: 94%;
        margin: 25px auto;
    }

    .panel {
        padding: 22px;
    }

    .hero {
        min-height: 250px;
        padding: 38px 20px;
    }

    .hero h2 {
        font-size: 30px;
    }

    .hero p {
        max-width: 560px;
        font-size: 13px;
    }

    .discussion {
        padding: 19px;
    }
}

@media (max-width: 600px) {

    .container {
        width: 94%;
        margin: 20px auto;
    }

    .hero {
        min-height: 240px;
        padding: 35px 18px;
    }

    .hero h2 {
        font-size: 27px;
    }

    .hero p {
        font-size: 13px;
        line-height: 1.6;
    }

    .panel {
        padding: 18px;
        border-radius: 13px;
    }

    .panel h2 {
        font-size: 19px;
    }

    .discussion {
        padding: 17px;
    }

    .discussion h3 {
        font-size: 17px;
    }

    .discussion .discussion {
        margin-left: 8px;
        padding: 14px;
    }

    button,
    .button {
        max-width: 100%;
    }
}

@media (prefers-reduced-motion: reduce) {
    *,
    *::before,
    *::after {
        transition: none !important;
    }
}

.sc-forum-label {
    display: inline-block;
    margin-bottom: 12px;
    padding: 11px 18px;
    color: #106638;
    background: #f1f5f2;
    border: 1px solid #DCE5DF;
    border-radius: 30px;
    font-size: 14px;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform:none;
}
.sc-forum-label1 {
    display: inline-block;
    margin-bottom: 12px;
    padding: 11px 18px;
    color: #263445;
    background: #eceeef;
    border: 1px solid #bfd7f7;
    border-radius: 30px;
    font-size: 18px;
    font-weight: 700;
    letter-spacing: 1.4px;
    text-transform:none;
}

</style>

</head>

<body>

<?php include "includes/header.php"; ?>

<section class="hero">

    <h2>Discussion Forum</h2>
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

            <span class="sc-forum-label">Start a Discussion</span>

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

        <span class="sc-forum-label">Recent Discussions</span>

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