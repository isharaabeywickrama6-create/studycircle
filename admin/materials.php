<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

$message = "";

if (empty($_SESSION["admin_materials_csrf"])) {
    $_SESSION["admin_materials_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $token = $_POST["csrf_token"] ?? "";

    if (
        !isset($_SESSION["admin_materials_csrf"]) ||
        !hash_equals($_SESSION["admin_materials_csrf"], $token)
    ) {
        $message = "Invalid request. Please refresh the page.";

    } else {

        $materialId = filter_input(
            INPUT_POST,
            "material_id",
            FILTER_VALIDATE_INT
        );

        $newStatus = $_POST["status"] ?? "";

        if (
            $materialId &&
            in_array($newStatus, ["approved", "rejected"], true)
        ) {

            $stmt = $conn->prepare(
                "UPDATE materials
                 SET status = ?
                 WHERE material_id = ? AND status = 'pending'"
            );

            if ($stmt) {

                $stmt->bind_param("si", $newStatus, $materialId);
                if ($stmt->execute()) {

                    if ($stmt->affected_rows > 0) {
                        $message = "Material status updated successfully.";
                    } else {
                        $message = "No pending material was updated.";
                    }

            } else {
                $message = "Could not update material status.";
            }

            $stmt->close();

            } else {
                $message = "Could not prepare the update.";
            }

        } else {
            $message = "Invalid material details.";
        }
    }
}

$sql = "SELECT
            m.material_id,
            m.title,
            m.description,
            m.file_path,
            m.status,
            m.created_at,
            u.name AS uploader,
            s.subject_name
        FROM materials m
        LEFT JOIN users u ON m.user_id = u.user_id
        LEFT JOIN subjects s ON m.subject_id = s.subject_id
        ORDER BY
            CASE WHEN m.status = 'pending' THEN 0 ELSE 1 END,
            m.created_at DESC";

$result = $conn->query($sql);

if (!$result) {
    die("Could not load materials. Please check the database tables.");
}

$materialCounts = [
    "pending" => 0,
    "approved" => 0,
    "rejected" => 0
];

$countResult = $conn->query(
    "SELECT status, COUNT(*) AS total
     FROM materials
     GROUP BY status"
);

if ($countResult) {
    while ($count = $countResult->fetch_assoc()) {
        $status = $count["status"];

        if (isset($materialCounts[$status])) {
            $materialCounts[$status] = (int)$count["total"];
        }
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}
?>

<html>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Material Approval | StudyCircle</title>
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
            max-width: 1250px;
            margin: 35px auto;
            padding: 0 20px;
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
        }

        .materials-table-wrapper {
            overflow-x: auto;
            background: white;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .materials-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 900px;
        }

        .materials-table th,
        .materials-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--sc-border);
            vertical-align: top;
        }

        .materials-table th {
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
            font-size: 13px;
        }

        .materials-table td {
            font-size: 14px;
        }

        .materials-table tr:last-child td {
            border-bottom: none;
        }

        .materials-table tbody tr {
            transition: background 0.2s ease;
        }

        .materials-table tbody tr:nth-child(even) {
            background: #F8FAF9;
        }

        .materials-table tbody tr:hover {
            background: #EAF5EE;
        }

        .materials-table th {
            padding: 17px 15px;
            white-space: nowrap;
        }

        .materials-table td {
            line-height: 1.6;
        }

        .material-description {
            color: var(--sc-muted);
            margin: 6px 0 0;
            max-width: 300px;
            overflow-wrap: anywhere;
        }

        .status-badge {
            display: inline-block;
            padding: 5px 11px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .status-pending {
            background: #FFF4D6;
            color: #946200;
        }

        .status-approved {
            background: #E1F5E8;
            color: #16733B;
        }

        .status-rejected {
            background: #FCE8E6;
            color: #B42318;
        }

        .action-buttons {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .action-buttons form {
            margin: 0;
        }

        .action-buttons button {
            padding: 9px 15px;
            font-size: 12px;
            font-weight: bold;
            border: none;
            border-radius: 7px;
            color: white;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .action-buttons button:hover {
            transform: translateY(-2px);
        }

        .approve-button:hover {
            background: #106638;
        }

        .approve-button {
            background: #16834A;
        }

        .reject-button {
            background: #C0392B;
        }

        .reject-button:hover {
            background: #922B21;
        }

        .no-action {
            color: var(--sc-muted);
            font-size: 13px;
        }

        .empty-row {
            padding: 30px !important;
            text-align: center !important;
            color: var(--sc-muted);
        }

        .admin-notice {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
        }

        .material-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin-bottom: 25px;
        }

        .summary-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid var(--sc-border);
            box-shadow: var(--sc-shadow);
            border-left: 5px solid var(--sc-green);
        }

        .summary-card.pending {
            border-left-color: #E5A000;
        }

        .summary-card.approved {
            border-left-color: #16834A;
        }

        .summary-card.rejected {
            border-left-color: #C0392B;
        }

        .summary-card h3 {
            margin: 0 0 8px;
            color: var(--sc-muted);
            font-size: 15px;
        }

        .summary-card p {
            margin: 0;
            font-size: 30px;
            font-weight: bold;
            color: var(--sc-text);
        }

        @media (max-width: 700px) {
            .material-summary {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                margin: 22px auto;
                padding: 0 12px;
            }
        }

    </style>

</head>

<body>
    
<?php include "../includes/header.php"; ?>

<div class="admin-container">

    <div class="page-heading">
        <h1>Study Material Approval</h1>
        <p>Review student-submitted materials and manage their approval status.</p>
    </div>

    <div class="material-summary">

    <div class="summary-card pending">
        <h3>Pending Materials</h3>
        <p><?= $materialCounts["pending"] ?></p>
    </div>
    <div class="summary-card approved">
        <h3>Approved Materials</h3>
        <p><?= $materialCounts["approved"] ?></p>
    </div>
    <div class="summary-card rejected">
        <h3>Rejected Materials</h3>
        <p><?= $materialCounts["rejected"] ?></p>
    </div>

</div>

<?php if ($message !== ""): ?>
    <div class="admin-notice"><?= e($message) ?></div>
<?php endif; ?>

<div class="materials-table-wrapper">

    <table class="materials-table">

        <thead>

            <tr>
                <th>ID</th>
                <th>Title and Description</th>
                <th>Uploaded By</th>
                <th>Subject</th>
                <th>Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>

        </thead>

        <tbody>

        <?php if ($result->num_rows > 0): ?>

            <?php while ($material = $result->fetch_assoc()): ?>

                <tr>
                    <td>
                        <?= e($material["material_id"]) ?>
                    </td>

                    <td>
                        <strong><?= e($material["title"]) ?></strong>
                        <p class="material-description">
                            <?= e($material["description"]) ?>
                        </p>
                    </td>

                    <td>
                        <?= e($material["uploader"] ?? "Unknown") ?>
                    </td>

                    <td>
                        <?= e($material["subject_name"] ?? "Not assigned") ?>
                    </td>

                    <td>
                        <?= !empty($material["created_at"])
                            ? e(date("d M Y", strtotime($material["created_at"])))
                            : "—" ?>
                    </td>

                    <td>
                        <span class="status-badge status-<?= e($material["status"]) ?>">
                            <?= e($material["status"]) ?>
                        </span>
                    </td>

                    <td>
                        <?php if ($material["status"] === "pending"): ?>

                            <div class="action-buttons">

                                <form method="POST"
                                    onsubmit="return confirm('Approve this material?');">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION["admin_materials_csrf"]) ?>">

                                    <input type="hidden" name="material_id"
                                        value="<?= e($material["material_id"]) ?>">

                                    <input type="hidden" name="status" value="approved">

                                    <button class="approve-button" type="submit">
                                        Approve
                                    </button>

                                </form>

                                <form method="POST"
                                    onsubmit="return confirm('Reject this material?');">

                                    <input type="hidden" name="csrf_token"
                                        value="<?= e($_SESSION["admin_materials_csrf"]) ?>">

                                    <input type="hidden" name="material_id"
                                        value="<?= e($material["material_id"]) ?>">

                                    <input type="hidden" name="status" value="rejected">

                                    <button class="reject-button" type="submit">
                                        Reject
                                    </button>

                                </form>

                            </div>

                        <?php else: ?>

                            <span class="no-action">No action needed</span>

                        <?php endif; ?>

                    </td>

                </tr>

            <?php endwhile; ?>

        <?php else: ?>

            <tr>
                <td colspan="7" class="empty-row">
                    No study materials have been submitted yet.
                </td>
            </tr>

        <?php endif; ?>
        
        </tbody>

    </table>
    
</div>

</div>

<?php include __DIR__ . "/../includes/footer.php"; ?>

</body>
</html>