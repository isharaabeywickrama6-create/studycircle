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
        !isset($_SESSION["subjects_csrf"]) ||
        !is_string($token) ||
        !hash_equals($_SESSION["subjects_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";

    } else {

        $action = $_POST["action"] ?? "";

        if ($action === "approve_request" || $action === "reject_request") {

            $requestId = filter_input(
                INPUT_POST,
                "request_id",
                FILTER_VALIDATE_INT
            );

            if (!$requestId || $requestId < 1) {
                $message = "Invalid subject request.";
                $messageType = "error";

            } else {

                $conn->begin_transaction();

                try {
                    $check = $conn->prepare(
                        "SELECT subject_name, category
                         FROM subject_requests
                         WHERE request_id = ? AND status = 'pending'
                         FOR UPDATE"
                    );

                    if (!$check) {
                        throw new Exception("Could not prepare request lookup.");
                    }

                    $check->bind_param("i", $requestId);

                    if (!$check->execute()) {
                        throw new Exception("Could not check subject request.");
                    }

                    $request = $check->get_result()->fetch_assoc();
                    $check->close();

                    if (!$request) {
                        throw new Exception("This request is no longer pending.");
                    }

                    if ($action === "approve_request") {

                        $add = $conn->prepare(
                            "INSERT INTO subjects (subject_name, category)
                             VALUES (?, ?)"
                        );

                        if (!$add) {
                            throw new Exception("Could not prepare subject insertion.");
                        }

                        $add->bind_param(
                            "ss",
                            $request["subject_name"],
                            $request["category"]
                        );

                        if (!$add->execute()) {
                            throw new Exception("Could not add the approved subject.");
                        }

                        $add->close();

                        $newStatus = "approved";

                    } else {
                        $newStatus = "rejected";
                    }

                    $adminId = (int)$_SESSION["user_id"];

                    $update = $conn->prepare(
                        "UPDATE subject_requests
                         SET status = ?, reviewed_by = ?, reviewed_at = NOW()
                         WHERE request_id = ? AND status = 'pending'"
                    );

                    if (!$update) {
                        throw new Exception("Could not prepare request update.");
                    }

                    $update->bind_param(
                        "sii",
                        $newStatus,
                        $adminId,
                        $requestId
                    );

                    if (!$update->execute() || $update->affected_rows !== 1) {
                        throw new Exception("Could not update request status.");
                    }

                    $update->close();

                    $conn->commit();

                    $message = $action === "approve_request"
                        ? "Subject approved and added successfully."
                        : "Subject request rejected successfully.";

                    $messageType = "success";

                } catch (Exception $e) {

                    $conn->rollback();

                    error_log("Subject request error: " . $e->getMessage());

                    $message = "Could not process the subject request. Please try again.";
                    $messageType = "error";
                }
            }

        } elseif ($action === "add") {

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
                } else {
                    $message = "Could not prepare the subject insertion.";
                    $messageType = "error";
                }
            }

        } elseif ($action === "edit") {

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
                } else {
                    $message = "Could not prepare the subject update.";
                    $messageType = "error";
                }
            }

        } elseif ($action === "delete") {

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
                } else {
                    $message = "Could not prepare the subject deletion.";
                    $messageType = "error";
                }
            }

        } else {
            $message = "Unknown action.";
            $messageType = "error";
        }
    }
}

$pendingRequests = $conn->query(
    "SELECT sr.request_id, sr.subject_name, sr.category,
            sr.created_at, u.name AS student_name
     FROM subject_requests sr
     LEFT JOIN users u ON u.user_id = sr.student_id
     WHERE sr.status = 'pending'
     ORDER BY sr.created_at ASC"
);

$result = $conn->query(
    "SELECT subject_id, subject_name, category, created_at
     FROM subjects
     ORDER BY subject_id DESC"
);

if (!$result) {
    die("Could not load subjects. Check your database connection.");
}

$summaryResult = $conn->query(
    "SELECT
        (SELECT COUNT(*) FROM subjects) AS total_subjects,
        (SELECT COUNT(*) FROM subject_requests WHERE status = 'pending') AS pending_requests,
        (SELECT COUNT(*) FROM subject_requests WHERE status = 'approved') AS approved_requests"
);

$summary = [
    "total_subjects" => 0,
    "pending_requests" => 0,
    "approved_requests" => 0
];

if ($summaryResult) {
    $summary = $summaryResult->fetch_assoc();
}

function e($value) {
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Subjects | StudyCircle</title>
    <link rel="stylesheet" href="../assets/css/studycircle.css">

    <style>

        html, body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
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
            margin-bottom: 5px;
            color: var(--sc-green-dark);
        }

        .page-heading p {
            color: var(--sc-muted);
            margin-top: 0;
        }

        .card {
            background: white;
            padding: 24px;
            margin: 22px 0;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .card h2 {
            margin-top: 0;
            color: var(--sc-green-dark);
        }

        label {
            display: block;
            margin: 12px 0 6px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 11px;
            border: 1px solid var(--sc-border);
            border-radius: 7px;
            margin-bottom: 8px;
        }

        input:focus {
            outline: 2px solid var(--sc-green-soft);
            border-color: var(--sc-green);
        }

        button.primary {
            background: var(--sc-green);
        }

        button.primary:hover {
            background: var(--sc-green-dark);
        }

        button.danger {
            background: #B91C1C;
        }

        button.danger:hover {
            background: #991B1B;
        }

        .notice {
            padding: 13px 16px;
            margin: 18px 0;
            border-radius: 8px;
            background: #E8F0FE;
        }

        .notice.success {
            background: #DCFCE7;
            color: #166534;
        }

        .notice.error {
            background: #FEE2E2;
            color: #991B1B;
        }

        .table-wrapper {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 650px;
        }

        th, td {
            padding: 13px;
            border-bottom: 1px solid var(--sc-border);
            text-align: left;
            vertical-align: top;
        }

        th {
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
        }

        tr:hover td {
            background: #FAFCFA;
        }

        .edit-form {
            min-width: 190px;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .empty-row {
            text-align: center;
            padding: 20px;
            color: var(--sc-muted);
        }

        .subject-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .subject-summary-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid var(--sc-border);
            box-shadow: var(--sc-shadow);
            border-left: 5px solid;
            border-left-color: #174A8B;
        }

        .subject-summary-card.pending {
            border-left: 5px solid;
            border-left-color: #E5A000;
        }

        .subject-summary-card.approved {
            border-left: 5px solid var(--sc-green);
        }

        .subject-summary-card h3 {
            margin: 0 0 8px;
            color: var(--sc-muted);
            font-size: 15px;
        }

        .subject-summary-card p {
            margin: 0;
            font-size: 30px;
            font-weight: bold;
            color: var(--sc-text);
        }

        .card {
            margin: 25px 0;
        }

        .card h2 {
            margin-bottom: 20px;
        }

        table tbody tr:nth-child(even) {
            background: #F8FAF9;
        }

        table tbody tr:hover {
            background: #EAF5EE;
        }

        table th {
            padding: 16px 13px;
            white-space: nowrap;
        }

        table td {
            padding: 15px 13px;
        }

        .action-buttons button,
        .card button {
            padding: 9px 15px;
            border: none;
            border-radius: 7px;
            font-weight: bold;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .action-buttons button:hover,
        .card button:hover {
            transform: translateY(-2px);
        }

        .empty-row {
            text-align: center;
            padding: 25px !important;
            color: var(--sc-muted);
        }

        @media (max-width: 700px) {
            .subject-summary {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                padding: 20px 12px;
            }

            .card {
                padding: 16px;
            }
        }

    </style>

</head>


<body>

<?php include "../includes/header.php"; ?>

<div class="admin-container">

    <div class="page-heading">
        <h1>Manage Subjects</h1>
        <p>Add, edit, and manage subjects and student subject requests.</p>
    </div>

    <div class="subject-summary">

    <div class="subject-summary-card">
        <h3>Total Subjects</h3>
        <p><?= (int)$summary["total_subjects"] ?></p>
    </div>

    <div class="subject-summary-card pending">
        <h3>Pending Requests</h3>
        <p><?= (int)$summary["pending_requests"] ?></p>
    </div>

    <div class="subject-summary-card approved">
        <h3>Approved Requests</h3>
        <p><?= (int)$summary["approved_requests"] ?></p>
    </div>

</div>

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

        <h2>Pending Subject Requests</h2>

        <div class="table-wrapper">

            <table>

                <thead>

                    <tr>
                        <th>Subject</th>
                        <th>Category</th>
                        <th>Requested By</th>
                        <th>Date</th>
                        <th>Actions</th>
                    </tr>

                </thead>

                <tbody>

                <?php if ($pendingRequests && $pendingRequests->num_rows > 0): ?>

                    <?php while ($request = $pendingRequests->fetch_assoc()): ?>

                        <tr>
                            <td><?= e($request["subject_name"]) ?></td>
                            <td><?= e($request["category"]) ?></td>
                            <td><?= e($request["student_name"] ?? "Student") ?></td>
                            <td>
                                <?= !empty($request["created_at"])
                                    ? e(date("d M Y", strtotime($request["created_at"])))
                                    : "—" ?>
                            </td>

                            <td>
                                 <div class="action-buttons">
                                <form method="POST" style="display:inline-block;">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($_SESSION["subjects_csrf"]) ?>">

                                    <input type="hidden" name="action"
                                           value="approve_request">

                                    <input type="hidden" name="request_id"
                                           value="<?= (int)$request["request_id"] ?>">

                                    <button class="primary" type="submit">
                                        Approve
                                    </button>
                                </form>

                                <form method="POST" style="display:inline-block;"
                                      onsubmit="return confirm('Reject this subject request?');">

                                    <input type="hidden" name="csrf_token"
                                           value="<?= e($_SESSION["subjects_csrf"]) ?>">

                                    <input type="hidden" name="action"
                                           value="reject_request">

                                    <input type="hidden" name="request_id"
                                           value="<?= (int)$request["request_id"] ?>">

                                    <button class="danger" type="submit">
                                        Reject
                                    </button>

                                </form>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>
                    <tr>
                        <td colspan="5">No pending subject requests.</td>
                    </tr>
                <?php endif; ?>

                </tbody>

            </table>

        </div>

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

                            <td>
                                <?= !empty($subject["created_at"])
                                    ? e(date("d M Y", strtotime($subject["created_at"])))
                                    : "—" ?>
                            </td>

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

<?php include "../includes/footer.php"; ?>

</body>
</html>