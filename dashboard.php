<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: login.php");
    exit;
}

$user_id = (int) $_SESSION["user_id"];
$student_name = $_SESSION["name"] ?? "Student";
$profile_image = "";

$stmt = $conn->prepare(
    "SELECT profile_image FROM users WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $profile_result = $stmt->get_result();
        $profile_data = $profile_result->fetch_assoc();

        $profile_image = $profile_data["profile_image"] ?? "";
    }

    $stmt->close();
}

$profile_image = is_string($profile_image)
    ? basename($profile_image)
    : "";

$profile_image_path = __DIR__ . "/uploads/profile_pictures/" . $profile_image;

$has_profile_image = (
    $profile_image !== "" &&
    is_file($profile_image_path)
);

$material_counts = [
    "total" => 0,
    "pending" => 0,
    "approved" => 0,
    "rejected" => 0
];

$stmt = $conn->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(status = 'pending'), 0) AS pending,
        COALESCE(SUM(status = 'approved'), 0) AS approved,
        COALESCE(SUM(status = 'rejected'), 0) AS rejected
     FROM materials
     WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($result) {
            $data = $result->fetch_assoc();

            if ($data) {
                foreach ($material_counts as $key => $value) {
                    $material_counts[$key] = (int) $data[$key];
                }
            }
        }
    } else {
        error_log("Student material count query failed: " . $stmt->error);
    }

    $stmt->close();
} else {
    error_log("Student material count prepare failed: " . $conn->error);
}

$total_downloads = 0;

$stmt = $conn->prepare(
    "SELECT COUNT(*) AS total
     FROM resource_downloads
     WHERE user_id = ?"
);

if ($stmt) {
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($result) {
            $download_data = $result->fetch_assoc();
            $total_downloads = (int) ($download_data["total"] ?? 0);
        }
    } else {
        error_log("Student download count query failed: " . $stmt->error);
    }

    $stmt->close();
} else {
    error_log("Student download count prepare failed: " . $conn->error);
}

$recent_uploads = [];

$stmt = $conn->prepare(
    "SELECT m.title, m.status, m.created_at,
            s.subject_name
     FROM materials m
     LEFT JOIN subjects s
        ON m.subject_id = s.subject_id
     WHERE m.user_id = ?
     ORDER BY m.created_at DESC
     LIMIT 5"
);

if ($stmt) {
    $stmt->bind_param("i", $user_id);

    if ($stmt->execute()) {
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $recent_uploads[] = $row;
            }
        }
    } else {
        error_log("Student recent uploads query failed: " . $stmt->error);
    }

    $stmt->close();
} else {
    error_log("Student recent uploads prepare failed: " . $conn->error);
}

?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Student Dashboard | StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">
</head>

<body>

<?php include "includes/header.php"; ?>

<main class="sc-dashboard">

    <section class="sc-dashboard-welcome">

        <div class="sc-welcome-content">
            <span class="sc-dashboard-label">STUDENT DASHBOARD</span>

            <h1>
                Welcome back,
                <?= htmlspecialchars($student_name, ENT_QUOTES, "UTF-8") ?>!
            </h1>

            <p>
                Your learning journey continues here.
                Explore study resources, share knowledge and connect
                with your university community.
            </p>

            <a href="upload_material.php" class="sc-dashboard-button">
                + Upload Study Material
            </a>
        </div>

        <div class="sc-welcome-decoration">

            <?php if ($has_profile_image): ?>

                <img
                    src="uploads/profile_pictures/<?= rawurlencode($profile_image) ?>"
                    alt="Your profile picture"
                    class="sc-welcome-profile-picture"
                >

            <?php else: ?>

                <div class="sc-welcome-profile-placeholder">
                    <?= htmlspecialchars(
                        strtoupper(substr($student_name, 0, 1)),
                        ENT_QUOTES,
                        "UTF-8"
                    ) ?>
                </div>

            <?php endif; ?>

        </div>

    </section>

    <section class="sc-dashboard-section">

        <div class="sc-dashboard-heading">
            <div>
                <h2>Your Overview</h2>
                <p>A quick summary of your StudyCircle activity.</p>
            </div>
        </div>

        <div class="sc-dashboard-stats">

            <article class="sc-dashboard-stat">
                <div class="sc-stat-icon">📁</div>
                <p>Total Uploads</p>
                <h3><?= (int) $material_counts["total"] ?></h3>
                <span>Materials you shared</span>
            </article>

            <article class="sc-dashboard-stat">
                <div class="sc-stat-icon">⏳</div>
                <p>Pending</p>
                <h3><?= (int) $material_counts["pending"] ?></h3>
                <span>Waiting for approval</span>
            </article>

            <article class="sc-dashboard-stat">
                <div class="sc-stat-icon">✓</div>
                <p>Approved</p>
                <h3><?= (int) $material_counts["approved"] ?></h3>
                <span>Available to students</span>
            </article>

            <article class="sc-dashboard-stat">
                <div class="sc-stat-icon">✕</div>
                <p>Rejected</p>
                <h3><?= (int) $material_counts["rejected"] ?></h3>
                <span>Not approved materials</span>
            </article>

            <article class="sc-dashboard-stat">
                <div class="sc-stat-icon">⬇</div>
                <p>Total Downloads</p>
                <h3><?= (int) $total_downloads ?></h3>
                <span>Your resource downloads</span>
            </article>

        </div>

    </section>

    <section class="sc-dashboard-section">

        <div class="sc-dashboard-heading">
            <div>
                <h2>Quick Access</h2>
                <p>Continue learning with these StudyCircle features.</p>
            </div>
        </div>

        <div class="sc-dashboard-actions">

            <a href="upload_material.php" class="sc-dashboard-action">
                <span class="sc-action-icon">📤</span>
                <div>
                    <h3>Upload Materials</h3>
                    <p>Share your notes and educational resources.</p>
                </div>
                <span class="sc-action-arrow">→</span>
            </a>

            <a href="materials.php" class="sc-dashboard-action">
                <span class="sc-action-icon">📖</span>
                <div>
                    <h3>Browse Materials</h3>
                    <p>Find and download approved study resources.</p>
                </div>
                <span class="sc-action-arrow">→</span>
            </a>

            <a href="forum.php" class="sc-dashboard-action">
                <span class="sc-action-icon">💬</span>
                <div>
                    <h3>Discussion Forum</h3>
                    <p>Ask questions and share academic knowledge.</p>
                </div>
                <span class="sc-action-arrow">→</span>
            </a>

            <a href="study_groups.php" class="sc-dashboard-action">
                <span class="sc-action-icon">👥</span>
                <div>
                    <h3>Study Groups</h3>
                    <p>Connect and learn with other students.</p>
                </div>
                <span class="sc-action-arrow">→</span>
            </a>

        </div>

    </section>

    <section class="sc-dashboard-section">

        <div class="sc-dashboard-heading">
            <div>
                <h2>My Recent Uploads</h2>
                <p>Check the latest materials you have shared.</p>
            </div>

            <a href="upload_material.php" class="sc-view-link">
                Upload New +
            </a>
        </div>

        <div class="sc-dashboard-table">

            <?php if (count($recent_uploads) > 0): ?>

                <div class="sc-table-scroll">
                    <table>
                        <thead>
                            <tr>
                                <th>Material Title</th>
                                <th>Subject</th>
                                <th>Uploaded Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>

                        <tbody>

                            <?php foreach ($recent_uploads as $material): ?>

                                <?php
                                $status = strtolower($material["status"] ?? "pending");
                                $allowed_statuses = ["pending", "approved", "rejected"];

                                if (!in_array($status, $allowed_statuses, true)) {
                                    $status = "pending";
                                }
                                ?>

                                <tr>
                                    <td>
                                        <strong>
                                            <?= htmlspecialchars(
                                                $material["title"] ?? "",
                                                ENT_QUOTES,
                                                "UTF-8"
                                            ) ?>
                                        </strong>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $material["subject_name"] ?? "Not specified",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <?= htmlspecialchars(
                                            $material["created_at"] ?? "",
                                            ENT_QUOTES,
                                            "UTF-8"
                                        ) ?>
                                    </td>

                                    <td>
                                        <span class="sc-status sc-status-<?= $status ?>">
                                            <?= htmlspecialchars($status, ENT_QUOTES, "UTF-8") ?>
                                        </span>
                                    </td>
                                </tr>

                            <?php endforeach; ?>

                        </tbody>
                    </table>
                </div>

            <?php else: ?>

                <div class="sc-dashboard-empty">
                    <span>👤</span>
                    <h3>No uploads yet</h3>
                    <p>
                        You haven't uploaded any study materials.
                        Start sharing your first resource with other students!
                    </p>
                    <a href="upload_material.php" class="sc-dashboard-button">
                        Upload Your First Material
                    </a>
                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<?php include "includes/footer.php"; ?>

</body>
</html>