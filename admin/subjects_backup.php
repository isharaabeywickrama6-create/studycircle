<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

if (empty($_SESSION["subjects_csrf"])) {
    $_SESSION["subjects_csrf"] = bin2hex(random_bytes(32));
}

$message = "";
$messageType = "info";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !is_string($token) ||
        !hash_equals($_SESSION["subjects_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";
    } else {

        $action = $_POST["action"] ?? "";

        if ($action === "add") {

            $subjectName = trim($_POST["subject_name"] ?? "");
            $category = trim($_POST["category"] ?? "");

            if (
                $subjectName === "" ||
                strlen($subjectName) > 100 ||
                strlen($category) > 100
            ) {
                $message = "Enter a subject name and a category of up to 100 characters.";
                $messageType = "error";
            } else {

                $stmt = $conn->prepare(
                    "INSERT INTO subjects (subject_name, category)
                     VALUES (?, ?)"
                );

                if ($stmt) {
                    $stmt->bind_param("ss", $subjectName, $category);

                    if ($stmt->execute()) {
                        $message = "Subject added successfully.";
                        $messageType = "success";
                    } else {
                        $message = "Could not add the subject.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }

        elseif ($action === "edit") {

            $subjectId = filter_input(
                INPUT_POST,
                "subject_id",
                FILTER_VALIDATE_INT
            );

            $subjectName = trim($_POST["subject_name"] ?? "");
            $category = trim($_POST["category"] ?? "");

            if (
                !$subjectId ||
                $subjectName === "" ||
                strlen($subjectName) > 100 ||
                strlen($category) > 100
            ) {
                $message = "Please enter valid subject details.";
                $messageType = "error";
            } else {

                $stmt = $conn->prepare(
                    "UPDATE subjects
                     SET subject_name = ?, category = ?
                     WHERE subject_id = ?"
                );

                if ($stmt) {
                    $stmt->bind_param(
                        "ssi",
                        $subjectName,
                        $category,
                        $subjectId
                    );

                    if ($stmt->execute()) {
                        $message = $stmt->affected_rows > 0
                            ? "Subject updated successfully."
                            : "No changes made, or the subject was not found.";

                        $messageType = "success";
                    } else {
                        $message = "Could not update the subject.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }

        elseif ($action === "delete") {

            $subjectId = filter_input(
                INPUT_POST,
                "subject_id",
                FILTER_VALIDATE_INT
            );

            if (!$subjectId) {
                $message = "Invalid subject ID.";
                $messageType = "error";
            } else {

                $stmt = $conn->prepare(
                    "DELETE FROM subjects WHERE subject_id = ?"
                );

                if ($stmt) {
                    $stmt->bind_param("i", $subjectId);

                    if ($stmt->execute()) {
                        $message = $stmt->affected_rows > 0
                            ? "Subject deleted successfully."
                            : "Subject not found.";

                        $messageType = "success";
                    } else {
                        $message = "This subject may be linked to existing materials, discussions, or groups. Remove those links before deleting it.";
                        $messageType = "error";
                    }

                    $stmt->close();
                }
            }
        }

        else {
            $message = "Unknown action.";
            $messageType = "error";
        }
    }
}

$result = $conn->query(
    "SELECT subject_id, subject_name, category, created_at
     FROM subjects
     ORDER BY subject_id DESC"
);

if (!$result) {
    die("Could not load subjects. Check your database connection.");
}

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects | StudyCircle</title>

    <style>
        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 25px;
            font-family: Arial, sans-serif;
            background: #f5f6fc;
            color: #202747;
        }

        .container {
            max-width: 1050px;
            margin: auto;
        }

        h1 {
            color: #172554;
        }

        a {
            color: #5141a5;
            text-decoration: none;
        }

        .card {
            background: white;
            padding: 22px;
            margin: 22px 0;
            border-radius: 12px;
            box-shadow: 0 3px 12px #17255412;
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid #d7d9e8;
            border-radius: 7px;
        }

        button {
            background: #16834A;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        button:hover {
            background: #106638;
        }

        .primary {
            background: #5141a5;
            color: white;
        }

        .danger {
            background: #b91c1c;
            color: white;
        }

        .notice {
            padding: 12px;
            margin: 18px 0;
            border-radius: 8px;
            background: #eae8fb;
        }

        .success {
            background: #dcfce7;
            color: #166534;
        }

        .error {
            background: #fee2e2;
            color: #991b1b;
        }

        .table-wrapper {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 12px;
            border-bottom: 1px solid #ececf4;
            text-align: left;
            vertical-align: top;
        }

        th {
            background: #eae8fb;
        }

        .edit-form {
            min-width: 170px;
        }

        @media (max-width: 600px) {
            body {
                padding: 14px;
            }
        }
    </style>
</head>

<body>
<div class="container">

    <a href="dashboard.php">&larr; Back to Dashboard</a>

    <h1>Manage Subjects</h1>
    <p>Add and maintain the subjects available on StudyCircle.</p>

    <?php if ($message !== ""): ?>
        <div class="notice <?= e($messageType) ?>" role="status">
            <?= e($message) ?>
        </div>
    <?php endif; ?>

    <div class="card">
        <h2>Add a Subject</h2>

        <form method="POST">
            <input type="hidden" name="csrf_token"
                   value="<?= e($_SESSION["subjects_csrf"]) ?>">

            <input type="hidden" name="action" value="add">

            <label for="subject_name">Subject Name</label>
            <input type="text" id="subject_name"
                   name="subject_name" maxlength="100" required>

            <label for="category">Category</label>
            <input type="text" id="category"
                   name="category" maxlength="100"
                   placeholder="e.g. Programming, Mathematics" required>

            <button class="primary" type="submit">Add Subject</button>
        </form>
    </div>

    <div class="card">
        <h2>Existing Subjects</h2>

        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Subject and Category</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($subject = $result->fetch_assoc()): ?>
                        <tr>
                            <td><?= e($subject["subject_id"]) ?></td>

                            <td>
                                <form method="POST" class="edit-form">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($_SESSION["subjects_csrf"]) ?>">
                                    <input type="hidden" name="action" value="edit">
                                    <input type="hidden" name="subject_id"
                                           value="<?= e($subject["subject_id"]) ?>">

                                    <label>Subject Name</label>
                                    <input type="text" name="subject_name"
                                           maxlength="100"
                                           value="<?= e($subject["subject_name"]) ?>"
                                           required>

                                    <label>Category</label>
                                    <input type="text" name="category"
                                           maxlength="100"
                                           value="<?= e($subject["category"]) ?>"
                                           required>

                                    <button class="primary" type="submit">
                                        Save Changes
                                    </button>
                                </form>
                            </td>

                            <td><?= e($subject["created_at"]) ?></td>

                            <td>
                                <form method="POST"
                                      onsubmit="return confirm('Delete this subject? This may fail if other records use it.');">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($_SESSION["subjects_csrf"]) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="subject_id"
                                           value="<?= e($subject["subject_id"]) ?>">

                                    <button class="danger" type="submit">
                                        Delete
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">No subjects added yet.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</div>
</body>
</html>