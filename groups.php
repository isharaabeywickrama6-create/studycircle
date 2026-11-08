<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

function e($value) {
    return htmlspecialchars((string)($value ?? ""), ENT_QUOTES, "UTF-8");
}

$isStudent = isset($_SESSION["user_id"])
    && ($_SESSION["role"] ?? "") === "student";

if (!$isStudent) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["groups_csrf"])) {
    $_SESSION["groups_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["create_group"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["groups_csrf"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";
    } else {
        $groupName = trim($_POST["group_name"] ?? "");
        $description = trim($_POST["description"] ?? "");
        $subjectId = filter_input(INPUT_POST, "subject_id", FILTER_VALIDATE_INT);

        if ($groupName === "" || strlen($groupName) > 150) {
            $error = "Enter a group name (maximum 150 characters).";
        } elseif (strlen($description) > 5000) {
            $error = "Description is too long.";
        } elseif (!$subjectId || $subjectId < 1) {
            $error = "Please select a subject.";
        } else {
            $subjectCheck = $conn->prepare(
                "SELECT subject_id FROM subjects WHERE subject_id = ?"
            );
            $subjectCheck->bind_param("i", $subjectId);
            $subjectCheck->execute();
            $subjectExists = $subjectCheck->get_result()->num_rows > 0;
            $subjectCheck->close();

            if (!$subjectExists) {
                $error = "Selected subject does not exist.";
            } else {
                try {
                    $conn->begin_transaction();

                    $stmt = $conn->prepare(
                        "INSERT INTO study_groups
                        (creator_id, subject_id, group_name, description)
                        VALUES (?, ?, ?, ?)"
                    );
                    $stmt->bind_param(
                        "iiss",
                        $userId,
                        $subjectId,
                        $groupName,
                        $description
                    );
                    $stmt->execute();

                    $newGroupId = $conn->insert_id;
                    $stmt->close();

                    $memberStmt = $conn->prepare(
                        "INSERT INTO group_members (group_id, user_id)
                         VALUES (?, ?)"
                    );
                    $memberStmt->bind_param("ii", $newGroupId, $userId);
                    $memberStmt->execute();
                    $memberStmt->close();

                    $conn->commit();

                    $message = "Study group created successfully. You are its first member.";

                } catch (Throwable $e) {
                    $conn->rollback();
                    $error = "Could not create the study group. Please try again.";
                }
            }
        }
    }
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["join_group"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["groups_csrf"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";
    } else {
        $groupId = filter_input(INPUT_POST, "group_id", FILTER_VALIDATE_INT);

        if (!$groupId || $groupId < 1) {
            $error = "Invalid study group.";
        } else {
            $groupCheck = $conn->prepare(
                "SELECT group_id FROM study_groups WHERE group_id = ?"
            );
            $groupCheck->bind_param("i", $groupId);
            $groupCheck->execute();
            $groupExists = $groupCheck->get_result()->num_rows > 0;
            $groupCheck->close();

            if (!$groupExists) {
                $error = "Study group not found.";
            } else {
                $memberCheck = $conn->prepare(
                    "SELECT group_id FROM group_members
                     WHERE group_id = ? AND user_id = ?"
                );
                $memberCheck->bind_param("ii", $groupId, $userId);
                $memberCheck->execute();
                $alreadyJoined = $memberCheck->get_result()->num_rows > 0;
                $memberCheck->close();

                if ($alreadyJoined) {
                    $error = "You have already joined this group.";
                } else {
                    $joinStmt = $conn->prepare(
                        "INSERT INTO group_members (group_id, user_id)
                         VALUES (?, ?)"
                    );
                    $joinStmt->bind_param("ii", $groupId, $userId);

                    if ($joinStmt->execute()) {
                        $message = "You joined the study group successfully.";
                    } else {
                        $error = "Could not join the study group.";
                    }

                    $joinStmt->close();
                }
            }
        }
    }
}

$subjects = $conn->query(
    "SELECT subject_id, subject_name FROM subjects ORDER BY subject_name"
);

$stmt = $conn->prepare("
    SELECT
        g.group_id,
        g.group_name,
        g.description,
        g.created_at,
        u.name AS creator_name,
        s.subject_name,
        (
            SELECT COUNT(*)
            FROM group_members gm
            WHERE gm.group_id = g.group_id
        ) AS member_count,
        (
            SELECT COUNT(*)
            FROM group_members mine
            WHERE mine.group_id = g.group_id
              AND mine.user_id = ?
        ) AS joined
    FROM study_groups g
    LEFT JOIN users u ON g.creator_id = u.user_id
    LEFT JOIN subjects s ON g.subject_id = s.subject_id
    ORDER BY g.created_at DESC
");

$stmt->bind_param("i", $userId);
$stmt->execute();
$groups = $stmt->get_result();
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Study Groups | StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>
        .groups-container {
            width: 100%;
            max-width: 1100px;
            margin: 0 auto;
            padding: 30px 20px;
        }

        .groups-heading {
            margin-bottom: 25px;
        }

        .groups-heading h1 {
            color: var(--sc-green-dark);
        }

        .groups-heading p {
            color: var(--sc-muted);
        }

        .create-group-box,
        .group-card {
            background: white;
            padding: 22px;
            margin-bottom: 22px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .create-group-box h2,
        .group-card h2 {
            color: var(--sc-green-dark);
            margin-top: 0;
        }

        .group-form {
            display: grid;
            gap: 13px;
        }

        .group-form label {
            font-weight: 600;
            color: var(--sc-text);
        }

        .group-form input,
        .group-form textarea,
        .group-form select {
            width: 100%;
            box-sizing: border-box;
            padding: 11px;
            border: 1px solid var(--sc-border);
            border-radius: 7px;
            font: inherit;
        }

        .group-form textarea {
            min-height: 100px;
            resize: vertical;
        }

        .group-form button,
        .join-button {
            width: fit-content;
            padding: 11px 20px;
            border: none;
            border-radius: 7px;
            background: var(--sc-green);
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        .group-form button:hover,
        .join-button:hover {
            background: var(--sc-green-dark);
        }

        .group-card {
            transition: transform 0.2s ease;
        }

        .group-card:hover {
            transform: translateY(-2px);
        }

        .group-description {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            color: var(--sc-text);
        }

        .group-details {
            color: var(--sc-muted);
            line-height: 1.9;
            font-size: 14px;
        }

        .joined-label {
            display: inline-block;
            padding: 9px 15px;
            border-radius: 7px;
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
            font-weight: 600;
        }

        .message,
        .error {
            padding: 13px 16px;
            margin-bottom: 18px;
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

        .empty-message {
            padding: 25px;
            background: white;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            text-align: center;
            color: var(--sc-muted);
        }

        @media (max-width: 600px) {
            .groups-container {
                padding: 20px 12px;
            }

            .create-group-box,
            .group-card {
                padding: 17px;
            }
        }
    </style>
</head>

<body>

<?php include __DIR__ . "/includes/header.php"; ?>

<main class="groups-container">

    <div class="groups-heading">
        <h1>Study Groups</h1>
        <p>Create a study group, connect with other students, and learn together.</p>
    </div>

    <?php if ($message !== ""): ?>
        <div class="message"><?= e($message) ?></div>
    <?php endif; ?>

    <?php if ($error !== ""): ?>
        <div class="error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="create-group-box">
        <h2>Create a Study Group</h2>

        <form method="POST" class="group-form">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION["groups_csrf"]) ?>">

            <label for="group_name">Group Name</label>
            <input
                type="text"
                id="group_name"
                name="group_name"
                maxlength="150"
                required
                placeholder="Enter a group name"
            >

            <label for="subject_id">Subject</label>
            <select id="subject_id" name="subject_id" required>
                <option value="">Select a subject</option>

                <?php if ($subjects): ?>
                    <?php while ($subject = $subjects->fetch_assoc()): ?>
                        <option value="<?= (int) $subject["subject_id"] ?>">
                            <?= e($subject["subject_name"]) ?>
                        </option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>

            <label for="description">Description</label>
            <textarea
                id="description"
                name="description"
                maxlength="5000"
                placeholder="What will your group study?"
            ></textarea>

            <button type="submit" name="create_group">
                Create Group
            </button>
        </form>
    </section>

    <h2>Available Study Groups</h2>

    <?php if ($groups->num_rows > 0): ?>

        <?php while ($group = $groups->fetch_assoc()): ?>

            <article class="group-card">

                <h2><?= e($group["group_name"]) ?></h2>

                <p class="group-description">
                    <?= e($group["description"] ?: "No description provided.") ?>
                </p>

                <div class="group-details">
                    <strong>Subject:</strong> <?= e($group["subject_name"] ?: "No subject") ?><br>
                    <strong>Created by:</strong> <?= e($group["creator_name"] ?: "Unknown") ?><br>
                    <strong>Members:</strong> <?= (int) $group["member_count"] ?><br>
                    <strong>Created:</strong> <?= date("d M Y", strtotime($group["created_at"])) ?>
                </div>

                <?php if ((int) $group["joined"] === 1): ?>

                    <span class="joined-label">You are a member</span>

                <?php else: ?>

                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?= e($_SESSION["groups_csrf"]) ?>">
                        <input type="hidden" name="group_id" value="<?= (int) $group["group_id"] ?>">

                        <button
                            type="submit"
                            name="join_group"
                            class="join-button"
                        >
                            Join Group
                        </button>
                    </form>

                <?php endif; ?>

            </article>

        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty-message">
            No study groups have been created yet. Create the first one!
        </div>

    <?php endif; ?>

</main>

<?php include __DIR__ . "/includes/footer.php"; ?>

</body>
</html>

<?php
$stmt->close();
?>