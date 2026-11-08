<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

$adminName = $_SESSION["name"] ?? "Administrator";

function getCount($conn, $table, $condition = "") {
    $sql = "SELECT COUNT(*) AS total FROM " . $table;

    if ($condition !== "") {
        $sql .= " WHERE " . $condition;
    }

    $result = $conn->query($sql);
    $row = $result->fetch_assoc();

    return $row["total"];
}

$totalUsers = getCount($conn, "users");
$totalMaterials = getCount($conn, "materials");
$pendingMaterials = getCount(
    $conn,
    "materials",
    "status = 'pending'"
);
$totalDiscussions = getCount($conn, "discussions");
$totalGroups = getCount($conn, "study_groups");

$sql = "SELECT
            m.material_id,
            m.title,
            m.created_at,
            u.name AS student_name,
            s.subject_name
        FROM materials m
        LEFT JOIN users u
            ON m.user_id = u.user_id
        LEFT JOIN subjects s
            ON m.subject_id = s.subject_id
        WHERE m.status = 'pending'
        ORDER BY m.created_at DESC
        LIMIT 10";

$pendingResult = $conn->query($sql);
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Dashboard | StudyCircle</title>
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

        .admin-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin: 22px 0 35px;
        }

        .admin-card {
            display: block;
            padding: 23px;
            background: white;
            border: 1px solid var(--sc-border);
            border-top: 4px solid var(--sc-green);
            border-radius: 12px;
            text-decoration: none;
            color: var(--sc-text);
            box-shadow: var(--sc-shadow);
            transition: transform .2s, box-shadow .2s;
        }

        .admin-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 9px 22px rgba(22, 131, 74, .15);
            border-top-color: var(--sc-blue);
        }

        .admin-icon {
            width: 50px;
            height: 50px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--sc-green-soft);
            border-radius: 12px;
            font-size: 26px;
            margin-bottom: 15px;
        }

        .admin-card h3 {
            margin: 0 0 9px;
            color: var(--sc-green-dark);
            font-size: 18px;
        }

        .admin-card p {
            color: var(--sc-muted);
            font-size: 14px;
            line-height: 1.6;
            margin: 0 0 15px;
        }

        .admin-open {
            color: var(--sc-green-dark);
            font-size: 14px;
            font-weight: bold;
        }

        .section-heading {
            margin-bottom: 18px;
        }

        .section-heading h2 {
            color: var(--sc-green-dark);
            margin-bottom: 5px;
        }

        .section-heading p {
            color: var(--sc-muted);
            margin-top: 0;
        }

        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
            margin: 22px 0 30px;
        }

        .stat-card {
            background: white;
            padding: 22px;
            border: 1px solid var(--sc-border);
            border-left: 5px solid var(--sc-green);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .stat-card p {
            margin: 0 0 12px;
            color: var(--sc-muted);
            font-weight: 600;
        }

        .stat-card h2 {
            margin: 0;
            color: var(--sc-blue);
            font-size: 30px;
        }

        .section {
            background: white;
            padding: 22px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
            margin-bottom: 25px;
        }

        .section h2 {
            color: var(--sc-green-dark);
            margin-top: 0;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 650px;
            border-collapse: collapse;
        }

        th, td {
            padding: 13px;
            text-align: left;
            border-bottom: 1px solid var(--sc-border);
            overflow-wrap: anywhere;
        }

        th {
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
        }

        tbody tr:hover {
            background: #FAFCFA;
        }

        .empty {
            padding: 18px 0;
            color: var(--sc-muted);
        }

        @media (max-width: 600px) {
            .admin-container {
                padding: 20px 12px;
            }

            .section, .stat-card, .admin-card {
                padding: 17px;
            }
        }

    </style>

</head>

<body>

<?php include "../includes/header.php"; ?>

<main class="admin-container">
    
<style>

    .admin-menu {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 22px;
        margin-top: 25px;
    }

    .admin-card {
        display: block;
        background: #ffffff;
        padding: 25px;
        border-radius: 16px;
        border: 1px solid #e8e8f2;
        border-top: 4px solid #16834A;
        text-decoration: none;
        color: #0c4d2b;
        box-shadow: 0 5px 18px rgba(32, 35, 75, 0.06);
        transition: transform 0.25s, box-shadow 0.25s;
    }

    .admin-card:hover {
        transform: translateY(-6px);
        box-shadow: 0 12px 25px #035028;
        border-top-color: #0c4d2b;
    }

    .admin-icon {
        width: 52px;
        height: 52px;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #f0edff;
        border-radius: 13px;
        font-size: 27px;
        margin-bottom: 18px;
    }

    .admin-card h3 {
        margin: 0 0 10px;
        color: #0c4d2b;
        font-size: 19px;
    }

    .admin-card p {
        color: gray;
        font-size: 14px;
        line-height: 1.6;
        margin: 0 0 18px;
    }

    .admin-open {
        color: #16834A;
        font-size: 14px;
        font-weight: bold;
    }

    .admin-container {
        flex: 1;
        width: 100%;
        max-width: 1200px;
        margin: 0 auto;
        padding: 30px 20px;
    }

</style>

<div class="page-heading">
    <h1>Administration</h1>
    <p>Manage and monitor your StudyCircle platform.</p>
</div>

<div class="admin-menu">

    <a href="users.php" class="admin-card">
        <div class="admin-icon">👥</div>
        <h3>Manage Users</h3>
        <p>View registered students and manage user information.</p>
        <span class="admin-open">Open Management →</span>
    </a>

    <a href="materials.php" class="admin-card">
        <div class="admin-icon">📚</div>
        <h3>Study Materials</h3>
        <p>Review submitted resources and approve or reject materials.</p>
        <span class="admin-open">Review Materials →</span>
    </a>

    <a href="subjects.php" class="admin-card">
        <div class="admin-icon">📖</div>
        <h3>Manage Subjects</h3>
        <p>Add, update, and organize academic subjects.</p>
        <span class="admin-open">Manage Subjects →</span>
    </a>

    <a href="discussions.php" class="admin-card">
        <div class="admin-icon">💬</div>
        <h3>Forum Moderation</h3>
        <p>Review student discussions and moderate inappropriate posts.</p>
        <span class="admin-open">View Discussions →</span>
    </a>

    <a href="groups.php" class="admin-card">
        <div class="admin-icon">🎓</div>
        <h3>Study Groups</h3>
        <p>View study groups, creators, subjects, and member counts.</p>
        <span class="admin-open">Manage Groups →</span>
    </a>

    <a href="reports.php" class="admin-card">
        <div class="admin-icon">📊</div>
        <h3>Admin Reports</h3>
        <p>Monitor platform statistics, registrations, and downloads.</p>
        <span class="admin-open">View Reports →</span>
    </a>

</div>
    
<div class="section-heading">
    <h2>Dashboard Overview</h2>
    <p>Monitor students and academic activities.</p>
</div>
    
<div class="cards">
    <div class="stat-card">
        <p>👥 Total Users</p>
        <h2><?= (int) $totalUsers ?></h2>
    </div>

    <div class="stat-card">
        <p>📚 Total Materials</p>
        <h2><?= (int) $totalMaterials ?></h2>
    </div>

    <div class="stat-card">
        <p>⏳ Pending Approvals</p>
        <h2><?= (int) $pendingMaterials ?></h2>
    </div>

    <div class="stat-card">
        <p>💬 Discussions</p>
        <h2><?= (int) $totalDiscussions ?></h2>
    </div>

    <div class="stat-card">
        <p>🎓 Study Groups</p>
        <h2><?= (int) $totalGroups ?></h2>
    </div>
</div>


<div class="section">
    <h2>Materials Waiting for Approval</h2>

    <?php if ($pendingResult->num_rows > 0) { ?>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Material Title</th>
                    <th>Student</th>
                    <th>Subject</th>
                    <th>Date Uploaded</th>
                </tr>
            </thead>

            <tbody>
                <?php while ($material =
                    $pendingResult->fetch_assoc()) { ?>
                    <tr>
                        <td>
                            <?php echo (int) $material["material_id"]; ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars(
                                $material["title"] ?? ""
                            ); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars(
                                $material["student_name"] ?? "Unknown"
                            ); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars(
                                $material["subject_name"] ?? "Unassigned"
                            ); ?>
                        </td>

                        <td>
                            <?php echo htmlspecialchars(
                                $material["created_at"] ?? ""
                            ); ?>
                        </td>
                    </tr>
                <?php } ?>
            </tbody>
        </table>
    </div>

    <?php } else { ?>

        <p class="empty">
            There are no materials waiting for approval.
        </p>

    <?php } ?>
</div>

</main>

<?php include "../includes/footer.php"; ?>

</body>

</html>

<?php
$conn->close();
?>