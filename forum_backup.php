<?php
session_start();

require_once "config/database.php";

$isStudent = isset($_SESSION["user_id"])
    && isset($_SESSION["role"])
    && $_SESSION["role"] === "student";

if (empty($_SESSION["forum_csrf"])) {
    $_SESSION["forum_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "success";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!$isStudent) {
        header("Location: login.php");
        exit;
    }

    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["forum_csrf"], $csrfToken)) {
        $message = "Invalid request. Please refresh the page and try again.";
        $messageType = "error";
    } else {
        $title = trim($_POST["title"] ?? "");
        $body = trim($_POST["body"] ?? "");
        $subjectId = $_POST["subject_id"] ?? "";

        if ($title === "" || $body === "") {
            $message = "Please enter both a discussion title and message.";
            $messageType = "error";
        } elseif (mb_strlen($title) > 200) {
            $message = "The title cannot exceed 200 characters.";
            $messageType = "error";
        } else {
            $subjectValue = null;

            if ($subjectId !== "") {
                $subjectValue = filter_var(
                    $subjectId,
                    FILTER_VALIDATE_INT
                );

                if ($subjectValue === false || $subjectValue < 1) {
                    $message = "Please select a valid subject.";
                    $messageType = "error";
                } else {
                    $check = $conn->prepare(
                        "SELECT subject_id FROM subjects WHERE subject_id = ?"
                    );
                    $check->bind_param("i", $subjectValue);
                    $check->execute();
                    $subjectResult = $check->get_result();

                    if ($subjectResult->num_rows === 0) {
                        $message = "The selected subject does not exist.";
                        $messageType = "error";
                    }

                    $check->close();
                }
            }

            if ($message === "") {
                $userId = (int) $_SESSION["user_id"];

                $stmt = $conn->prepare(
                    "INSERT INTO discussions
                    (user_id, subject_id, title, body)
                    VALUES (?, ?, ?, ?)"
                );

                $stmt->bind_param(
                    "iiss",
                    $userId,
                    $subjectValue,
                    $title,
                    $body
                );

                if ($stmt->execute()) {
                    $_SESSION["forum_csrf"] = bin2hex(random_bytes(32));
                    header("Location: forum.php?posted=1");
                    exit;
                } else {
                    $message = "Database error: " . $stmt->error;
                    $messageType = "error";
                }

                $stmt->close();
            }
        }
    }
}

if (isset($_GET["posted"]) && $_GET["posted"] === "1") {
    $message = "Your discussion was published successfully!";
    $messageType = "success";
}

$subjects = $conn->query(
    "SELECT subject_id, subject_name
     FROM subjects
     ORDER BY subject_name"
);

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

function e($value) {
    return htmlspecialchars((string) $value, ENT_QUOTES, "UTF-8");
}
?>

<html>

<head>
    <title>Academic Forum | NSBM StudyCircle</title>

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

        header {
            background: #16834A;
            color: white;
            padding: 22px 6%;
        }

        header h1 {
            margin: 0 0 8px;
        }

        header p {
            margin: 0;
        }

        nav {
            background: #106638;
            padding: 13px 6%;
        }

        nav a {
            color: white;
            text-decoration: none;
            margin-right: 22px;
            font-weight: bold;
        }

        nav a:hover {
            text-decoration: underline;
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

        footer {
            text-align: center;
            padding: 22px;
            color: #657386;
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

<header>
    <h1>NSBM StudyCircle</h1>
    <p>Academic Forum — Learn, Share and Discuss</p>
</header>

<nav>
    <a href="index.php">Home</a>
    <a href="materials.php">Study Materials</a>
    <a href="forum.php">Forum</a>
    <a href="index.php">Study Groups</a>

    <?php if ($isStudent): ?>
        <a href="dashboard.php">Dashboard</a>
        <a href="logout.php">Logout</a>
    <?php else: ?>
        <a href="login.php">Login</a>
        <a href="register.php">Register</a>
    <?php endif; ?>
</nav>

<main class="container">

    <section class="panel">

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

    <section class="panel">

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

                </article>

            <?php endwhile; ?>

        <?php endif; ?>
        
    </section>

</main>

<footer>
    NSBM StudyCircle | Learn Together, Grow Together
</footer>

</body>
</html>