<?php
require_once __DIR__ . "/auth_check.php";
require_once __DIR__ . "/../config/database.php";

$search = $_GET["search"] ?? "";

if (!is_string($search)) {
    $search = "";
} else {
    $search = trim($search);
}

if ($search !== "") {
    $sql = "SELECT user_id, name, email, role, created_at
            FROM users
            WHERE name LIKE ? OR email LIKE ?
            ORDER BY user_id DESC";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        die("Database query preparation failed.");
    }

    $searchValue = "%" . $search . "%";
    $stmt->bind_param("ss", $searchValue, $searchValue);

    if (!$stmt->execute()) {
        die("Could not retrieve users.");
    }

    $result = $stmt->get_result();

    if (!$result) {
        die("Could not retrieve users.");
    }
    } else {
    $sql = "SELECT user_id, name, email, role, created_at
            FROM users
            ORDER BY user_id DESC";

    $result = $conn->query($sql);

    if (!$result) {
        die("Could not retrieve users.");
    }
}

$users = [];

while ($row = $result->fetch_assoc()) {
    $users[] = $row;
}

$statsResult = $conn->query(
    "SELECT
        COUNT(*) AS total_users,
        SUM(role = 'student') AS total_students,
        SUM(role = 'admin') AS total_admins
     FROM users"
);

$stats = [
    "total_users" => 0,
    "total_students" => 0,
    "total_admins" => 0
];

if ($statsResult) {
    $stats = $statsResult->fetch_assoc();
}

function e($value) {
    return htmlspecialchars(
        (string) $value,
        ENT_QUOTES,
        "UTF-8"
    );
}
?>

<html>
    
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manage Users | StudyCircle</title>
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
            max-width: 1200px;
            width: 100%;
            margin: 35px auto;
            padding: 0 20px;
            flex: 1;
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

        .search-box {
            display: flex;
            gap: 10px;
            margin: 20px 0;
        }

        .search-box input {
            flex: 1;
            min-width: 0;
            padding: 12px;
            border: 1px solid var(--sc-border);
            border-radius: 8px;
        }

        .search-box button {
            padding: 10px 20px;
        }

        .clear-search {
            display: inline-flex;
            align-items: center;
            color: var(--sc-green-dark);
            font-size: 14px;
            text-decoration: none;
        }

        .users-table-wrapper {
            overflow-x: auto;
            background: white;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .users-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 700px;
        }

        .users-table th,
        .users-table td {
            padding: 15px;
            text-align: left;
            border-bottom: 1px solid var(--sc-border);
        }

        .users-table th {
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
            font-size: 13px;
        }

        .users-table td {
            font-size: 14px;
        }

        .users-table tr:last-child td {
            border-bottom: none;
        }

        .role-badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
            text-transform: capitalize;
        }

        .role-student {
            background: #E8F5EC;
            color: #16733B;
        }

        .role-admin {
            background: #E6EFFB;
            color: #174A8B;
        }

        .users-count {
            margin-top: 18px;
            color: var(--sc-muted);
            font-size: 14px;
        }

        .empty-row {
            padding: 30px !important;
            text-align: center !important;
            color: var(--sc-muted);
        }

        .user-summary {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 20px;
            margin: 25px 0;
        }

        .user-summary-card {
            background: white;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid var(--sc-border);
            border-left: 5px solid var(--sc-green);
            box-shadow: var(--sc-shadow);
            transition: transform 0.2s ease;
        }

        .user-summary-card.students {
            border-left-color: #2563EB;
        }

        .user-summary-card.admins {
            border-left-color: #64748B;
        }

        .user-summary-card:hover {
            transform: translateY(-3px);
        }

        .user-summary-card h3 {
            margin: 0 0 8px;
            color: var(--sc-muted);
            font-size: 15px;
        }

        .user-summary-card p {
            margin: 0;
            color: var(--sc-text);
            font-size: 30px;
            font-weight: bold;
        }

        .search-box {
            align-items: center;
            background: white;
            padding: 15px;
            border: 1px solid var(--sc-border);
            border-radius: 12px;
            box-shadow: var(--sc-shadow);
        }

        .search-box input {
            margin: 0;
            outline: none;
        }

        .search-box input:focus {
            border-color: var(--sc-green);
            box-shadow: 0 0 0 3px var(--sc-green-soft);
        }

        .search-box button {
            border: none;
            border-radius: 7px;
            background: var(--sc-green);
            color: white;
            font-weight: bold;
            cursor: pointer;
        }

        .search-box button:hover {
            background: var(--sc-green-dark);
        }

        .users-table tbody tr {
            transition: background 0.2s ease;
        }

        .users-table tbody tr:nth-child(even) {
            background: #F8FAF9;
        }

        .users-table tbody tr:hover {
            background: #EAF5EE;
        }

        .users-table th {
            padding: 17px 15px;
            white-space: nowrap;
        }

        .users-table td {
            line-height: 1.6;
        }

        @media (max-width: 700px) {
            .user-summary {
                grid-template-columns: 1fr;
                gap: 12px;
            }
        }

        @media (max-width: 600px) {
            .admin-container {
                margin: 22px auto;
                padding: 0 12px;
            }

            .search-box {
                flex-wrap: wrap;
            }

            .search-box button {
                width: 100%;
            }
        }

        .role-badge {
            min-width: 65px;
            text-align: center;
        }

        .users-table td {
            overflow-wrap: anywhere;
        }

    </style>

</head>

<body>

<?php include __DIR__ . "/../includes/header.php"; ?>

<div class="admin-container">

    <div class="page-heading">
        <h1>Manage Users</h1>
        <p>View registered StudyCircle users and search their details.</p>
    </div>

    <div class="user-summary">

        <div class="user-summary-card">
            <h3>Total Users</h3>
            <p><?= (int)$stats["total_users"] ?></p>
        </div>
        <div class="user-summary-card students">
            <h3>Students</h3>
            <p><?= (int)$stats["total_students"] ?></p>
        </div>
        <div class="user-summary-card admins">
            <h3>Administrators</h3>
            <p><?= (int)$stats["total_admins"] ?></p>
        </div>

    </div>

</div>

<form class="search-box" method="GET">

    <input
        type="text"
        name="search"
        placeholder="Search users by name or email..."
        value="<?= e($search) ?>"
    >

    <button type="submit">Search</button>

    <?php if ($search !== ""): ?>
        <a class="clear-search" href="users.php">
            Clear Search
        </a>
    <?php endif; ?>

</form>

<div class="users-table-wrapper">

    <table class="users-table">

        <thead>

            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Registered</th>
            </tr>

        </thead>

        <tbody>

        <?php if (count($users) > 0): ?>

            <?php foreach ($users as $user): ?>

                <tr>

                    <td><?= e($user["user_id"]) ?></td>
                    <td><?= e($user["name"]) ?></td>
                    <td><?= e($user["email"]) ?></td>
                    <td>
                        <span class="role-badge role-<?= e($user["role"]) ?>">
                            <?= e($user["role"]) ?>
                        </span>
                    </td>
                    <td>
                        <?= !empty($user["created_at"])
                            ? e(date("d M Y", strtotime($user["created_at"])))
                        : "—" ?>
                    </td>

                </tr>

            <?php endforeach; ?>

        <?php else: ?>

            <tr>

                <td colspan="5" class="empty-row">
                    No users found.
                </td>

            </tr>

        <?php endif; ?>

        </tbody>

    </table>
    
</div>

<p class="users-count">
    Total users displayed: <strong><?= count($users) ?></strong>
</p>

<?php include __DIR__ . "/../includes/footer.php"; ?>

</body>

</html>