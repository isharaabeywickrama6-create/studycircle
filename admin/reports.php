<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

function getCount($conn, $sql) {
    $result = $conn->query($sql);

    if (!$result) {
        error_log("Report count query failed: " . $conn->error);
        return 0;
    }

    $row = $result->fetch_assoc();

    return (int) ($row["total"] ?? 0);
}

$totalUsers = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM users"
);

$totalStudents = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM users WHERE role = 'student'"
);

$totalMaterials = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM materials"
);

$pendingMaterials = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM materials WHERE status = 'pending'"
);

$approvedMaterials = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM materials WHERE status = 'approved'"
);

$rejectedMaterials = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM materials WHERE status = 'rejected'"
);

$totalDiscussions = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM discussions"
);

$totalGroups = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM study_groups"
);

$totalSubjects = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM subjects"
);

$totalDownloads = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM resource_downloads"
);

$totalAnswers = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM answers"
);

$totalMemberships = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM group_members"
);

$totalSessions = getCount(
    $conn,
    "SELECT COUNT(*) AS total FROM study_sessions"
);

$recentUsers = $conn->query(
    "SELECT name, email, created_at
     FROM users
     WHERE role = 'student'
     ORDER BY created_at DESC
     LIMIT 5"
);

if (!$recentUsers) {
    error_log("Recent users query failed: " . $conn->error);
    die("Unable to load the reports. Please try again later.");
}

$popularMaterials = $conn->query(
    "SELECT m.title, COUNT(rd.download_id) AS download_count
     FROM materials m
     LEFT JOIN resource_downloads rd
        ON m.material_id = rd.material_id
     GROUP BY m.material_id, m.title
     ORDER BY download_count DESC, m.title ASC
     LIMIT 5"
);

if (!$popularMaterials) {
    error_log("Popular materials query failed: " . $conn->error);
    die("Unable to load the reports. Please try again later.");
}
?>

<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Admin Reports | StudyCircle</title>
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

        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
            gap: 18px;
            margin: 25px 0;
        }

        .card, .section {
            background: white;
            padding: 22px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .card {
            border-top: 4px solid var(--sc-green);
        }

        .card h3 {
            color: var(--sc-muted);
            font-size: 14px;
            margin-top: 0;
            margin-bottom: 12px;
        }

        .number {
            color: var(--sc-green-dark);
            font-size: 30px;
            font-weight: bold;
        }

        .section {
            margin-bottom: 24px;
        }

        .section h2 {
            color: var(--sc-green-dark);
            margin-top: 0;
            margin-bottom: 18px;
        }

        .table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            min-width: 550px;
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

        tr:hover td {
            background: #FAFCFA;
        }

        .empty {
            color: var(--sc-muted);
            text-align: center;
            padding: 20px;
        }

        @media (max-width: 600px) {
            .admin-container {
                padding: 20px 12px;
            }

            .card, .section {
                padding: 17px;
            }
        }

        .card {
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .card:hover {
            transform: translateY(-3px);
        }

        .number {
            overflow-wrap: anywhere;
        }

        .stats {
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
        }

        .section {
            overflow: hidden;
        }

        .section h2 {
            font-size: 20px;
        }

        table th {
            font-size: 13px;
            font-weight: 600;
        }

        table td {
            font-size: 14px;
        }

        table tr:last-child td {
            border-bottom: none;
        }

        .report-date {
            color: var(--sc-muted);
            font-size: 13px;
            margin-top: 8px;
        }

    </style>
</head>

<body>

<?php include "../includes/header.php"; ?>

<div class="admin-container">

    <div class="page-heading">

        <h1>StudyCircle Admin Reports</h1>

        <p>Overview of users, study materials, discussions, and study groups.</p>

        <p class="report-date">
            Report generated: <?= date("d M Y, h:i A") ?>
        </p>

    </div>

    <div class="stats">

        <div class="card">
            <h3>Total Users</h3>
            <div class="number"><?= $totalUsers ?></div>
        </div>
        <div class="card">
            <h3>Registered Students</h3>
            <div class="number"><?= $totalStudents ?></div>
        </div>
        <div class="card">
            <h3>Total Study Materials</h3>
            <div class="number"><?= $totalMaterials ?></div>
        </div>
        <div class="card">
            <h3>Pending Materials</h3>
            <div class="number"><?= $pendingMaterials ?></div>
        </div>
        <div class="card">
            <h3>Approved Materials</h3>
            <div class="number"><?= $approvedMaterials ?></div>
        </div>
        <div class="card">
            <h3>Rejected Materials</h3>
            <div class="number"><?= $rejectedMaterials ?></div>
        </div>
        <div class="card">
            <h3>Total Discussions</h3>
            <div class="number"><?= $totalDiscussions ?></div>
        </div>
        <div class="card">
            <h3>Total Study Groups</h3>
            <div class="number"><?= $totalGroups ?></div>
        </div>
        <div class="card">
            <h3>Total Subjects</h3>
            <div class="number"><?= $totalSubjects ?></div>
        </div>
        <div class="card">
            <h3>Material Downloads</h3>
            <div class="number"><?= $totalDownloads ?></div>
        </div>
        <div class="card">
            <h3>Total Discussion Answers</h3>
            <div class="number"><?= $totalAnswers ?></div>
        </div>
        <div class="card">
            <h3>Group Memberships</h3>
            <div class="number"><?= $totalMemberships ?></div>
        </div>
        <div class="card">
            <h3>Scheduled Study Sessions</h3>
            <div class="number"><?= $totalSessions ?></div>
        </div>

    </div>

    <div class="section">

        <h2>Recent Student Registrations</h2>

        <div class="table-wrap">

        <table>

            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Registration Date</th>
            </tr>

            <?php if ($recentUsers && $recentUsers->num_rows > 0): ?>

                <?php while ($user = $recentUsers->fetch_assoc()): ?>

                    <tr>
                        <td><?= htmlspecialchars($user["name"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= htmlspecialchars($user["email"], ENT_QUOTES, "UTF-8") ?></td>
                        <td>
                            <?= !empty($user["created_at"])
                                ? date("d M Y", strtotime($user["created_at"]))
                                : "Unknown" ?>
                        </td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="3" class="empty">No student registrations found.</td>
                </tr>

            <?php endif; ?>

        </table>

        </div>

    </div>

    <div class="section">

        <h2>Most Downloaded Materials</h2>

        <div class="table-wrap">

        <table>

            <tr>
                <th>Material Title</th>
                <th>Download Count</th>
            </tr>

            <?php if ($popularMaterials && $popularMaterials->num_rows > 0): ?>

                <?php while ($material = $popularMaterials->fetch_assoc()): ?>

                    <tr>
                        <td><?= htmlspecialchars($material["title"], ENT_QUOTES, "UTF-8") ?></td>
                        <td><?= (int) $material["download_count"] ?></td>
                    </tr>

                <?php endwhile; ?>

            <?php else: ?>

                <tr>
                    <td colspan="2" class="empty">No study materials found.</td>
                </tr>

            <?php endif; ?>

        </table>

        </div>
        
    </div>

</div>

<?php include "../includes/footer.php"; ?>

</body>
</html>