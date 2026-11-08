<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

$message = "";
$error = "";

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST"
    && isset($_POST["delete_group"])) {

    if (
        !isset($_POST["csrf_token"]) ||
        !hash_equals($_SESSION["csrf_token"], $_POST["csrf_token"])
    ) {
        $error = "Invalid request. Please try again.";
    } else {
        $group_id = filter_input(
            INPUT_POST,
            "group_id",
            FILTER_VALIDATE_INT
        );

        if (!$group_id || $group_id < 1) {
            $error = "Invalid group ID.";
        } else {
            try {
                $conn->begin_transaction();

                $stmt = $conn->prepare(
                    "DELETE FROM group_sessions WHERE group_id = ?"
                );
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "DELETE FROM group_members WHERE group_id = ?"
                );
                $stmt->bind_param("i", $group_id);
                $stmt->execute();
                $stmt->close();

                $stmt = $conn->prepare(
                    "DELETE FROM study_groups WHERE group_id = ?"
                );
                $stmt->bind_param("i", $group_id);
                $stmt->execute();

                $deleted = $stmt->affected_rows;
                $stmt->close();

                $conn->commit();

                $message = $deleted > 0
                    ? "Study group deleted successfully."
                    : "The study group was not found.";

            } catch (Throwable $e) {
                $conn->rollback();
                $error = "Could not delete this group. Check related database records.";
            }
        }
    }
}

$groupSummaryResult = $conn->query("
    SELECT
        (SELECT COUNT(*) FROM study_groups) AS total_groups,
        (SELECT COUNT(*) FROM group_members) AS total_members,
        (SELECT COUNT(*) FROM group_sessions) AS total_sessions
");

$groupSummary = [
    "total_groups" => 0,
    "total_members" => 0,
    "total_sessions" => 0
];

if ($groupSummaryResult) {
    $groupSummary = $groupSummaryResult->fetch_assoc();
}

$search = trim($_GET["search"] ?? "");

$sql = "
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
        ) AS member_count
    FROM study_groups g
    LEFT JOIN users u ON g.creator_id = u.user_id
    LEFT JOIN subjects s ON g.subject_id = s.subject_id
";

if ($search !== "") {
    $sql .= "
        WHERE g.group_name LIKE ?
           OR g.description LIKE ?
           OR u.name LIKE ?
           OR s.subject_name LIKE ?
    ";
}

$sql .= " ORDER BY g.created_at DESC";

$stmt = $conn->prepare($sql);

if ($search !== "") {
    $search_term = "%" . $search . "%";
    $stmt->bind_param(
        "ssss",
        $search_term,
        $search_term,
        $search_term,
        $search_term
    );
}

$stmt->execute();
$result = $stmt->get_result();
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Study Groups | StudyCircle</title>
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

        .group-card {
            background: white;
            padding: 22px;
            margin-bottom: 18px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .group-card h2 {
            margin-top: 0;
            color: var(--sc-green-dark);
            font-size: 21px;
        }

        .description {
            white-space: pre-wrap;
            overflow-wrap: anywhere;
            color: var(--sc-text);
        }

        .details {
            color: var(--sc-muted);
            font-size: 14px;
            line-height: 1.9;
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

        .empty-message {
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

            .group-card {
                padding: 17px;
            }

            .search-box {
                align-items: stretch;
            }

            .search-box input {
                width: 100%;
            }
        }

        .group-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 25px;
        }

        .group-summary-card {
            background: white;
            padding: 20px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .group-summary-card h3 {
            margin: 0 0 10px;
            color: var(--sc-muted);
            font-size: 14px;
            font-weight: 600;
        }

        .group-summary-card p {
            margin: 0;
            color: var(--sc-green-dark);
            font-size: 28px;
            font-weight: bold;
        }

        .group-card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .group-card:hover {
            transform: translateY(-2px);
        }

        @media (max-width: 700px) {
            .group-summary {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        .group-card h2 {
            overflow-wrap: anywhere;
        }

        .group-card form {
            margin-top: 15px;
        }

        .details strong {
            color: var(--sc-text);
        }

        .delete-button {
            padding: 10px 18px;
            border: none;
            border-radius: 7px;
            color: white;
            font-weight: 600;
            cursor: pointer;
        }

        .delete-button:hover {
            background: #991B1B;
        }

    </style>

</head>

<body>

<?php include "../includes/header.php"; ?>

<div class="admin-container">

    <div class="page-heading">
        <h1>Manage Study Groups</h1>
        <p>View and manage study groups, creators, subjects, and members.</p>
    </div>

    <div class="group-summary">

        <div class="group-summary-card">
            <h3>Total Study Groups</h3>
            <p><?= (int) $groupSummary["total_groups"] ?></p>
        </div>
        <div class="group-summary-card">
            <h3>Total Memberships</h3>
            <p><?= (int) $groupSummary["total_members"] ?></p>
        </div>
        <div class="group-summary-card">
            <h3>Scheduled Sessions</h3>
            <p><?= (int) $groupSummary["total_sessions"] ?></p>
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
            placeholder="Search group, creator, or subject..."
            value="<?= htmlspecialchars($search, ENT_QUOTES, "UTF-8") ?>"
        >

        <button class="search-button" type="submit">
            Search
        </button>

        <a class="show-all" href="groups.php">Show All</a>
    </form>

    <?php if ($result->num_rows > 0): ?>

        <?php while ($group = $result->fetch_assoc()): ?>
            <div class="group-card">

                <h2>
                    <?= htmlspecialchars(
                        $group["group_name"] ?? "",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </h2>

                <p class="description">
                    <?= htmlspecialchars(
                        $group["description"] ?? "No description provided.",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </p>

                <p class="details">
                    <strong>Creator:</strong>
                    <?= htmlspecialchars(
                        $group["creator_name"] ?? "Unknown",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                    <br>

                    <strong>Subject:</strong>
                    <?= htmlspecialchars(
                        $group["subject_name"] ?? "No subject",
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                    <br>

                    <strong>Members:</strong>
                    <?= (int) $group["member_count"] ?>
                    <br>

                    <strong>Created:</strong>
                    <?= !empty($group["created_at"])
                        ? date("d M Y", strtotime($group["created_at"]))
                        : "Unknown" ?>
                </p>

                <form method="POST"
                    onsubmit="return confirm('Delete this study group, its memberships, and its scheduled sessions? This cannot be undone.');">

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= htmlspecialchars(
                            $_SESSION["csrf_token"],
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                    >

                    <input
                        type="hidden"
                        name="group_id"
                        value="<?= (int) $group["group_id"] ?>"
                    >

                    <button
                        type="submit"
                        name="delete_group"
                        class="delete-button"
                    >
                        Delete Group
                    </button>
                </form>
            </div>
        <?php endwhile; ?>

    <?php else: ?>

        <div class="empty-message">
            No study groups found.
        </div>
        
    <?php endif; ?>

</div>

<?php include "../includes/footer.php"; ?>

</body>

</html>

<?php
$stmt->close();
?>