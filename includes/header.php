<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION["logout_csrf"])) {
    $_SESSION["logout_csrf"] = bin2hex(random_bytes(32));
}

$scRole = $_SESSION['role'] ?? '';
$scNameInput = $_SESSION['name'] ?? 'Student';

$scName = is_string($scNameInput)
    ? $scNameInput
    : 'Student';

$scIsAdmin = ($scRole === 'admin');
$scIsStudent = ($scRole === 'student');

$scBase = $scIsAdmin ? '../' : '';
$scCurrentPage = basename($_SERVER['PHP_SELF'] ?? '');

$scHomeLink = $scIsAdmin ? 'dashboard.php' : $scBase . 'index.php';
$scLogoutLink = $scBase . 'logout.php';
?>

<header class="sc-site-header">

    <a class="sc-brand" href="<?= htmlspecialchars($scHomeLink, ENT_QUOTES, 'UTF-8') ?>">
        <img src="<?= $scBase ?>assets/studycircle-icon.png"
             alt="StudyCircle Logo"
             class="sc-logo">

        <span>
            <strong>StudyCircle</strong>
            <small>NSBM Learning Community</small>
        </span>
    </a>

    <nav class="sc-nav" aria-label="Main navigation">

        <?php if ($scIsAdmin): ?>

            <a class="<?= $scCurrentPage === 'dashboard.php' ? 'active' : '' ?>"
               href="dashboard.php">Dashboard</a>

            <a class="<?= $scCurrentPage === 'materials.php' ? 'active' : '' ?>"
               href="materials.php">Materials</a>

            <a class="<?= $scCurrentPage === 'subjects.php' ? 'active' : '' ?>"
               href="subjects.php">Subjects</a>

            <a class="<?= $scCurrentPage === 'users.php' ? 'active' : '' ?>"
               href="users.php">Users</a>

            <a class="<?= $scCurrentPage === 'groups.php' ? 'active' : '' ?>"
               href="groups.php">Groups</a>

            <a class="<?= $scCurrentPage === 'discussions.php' ? 'active' : '' ?>"
               href="discussions.php">Discussions</a>

            <a class="<?= $scCurrentPage === 'reports.php' ? 'active' : '' ?>"
               href="reports.php">Reports</a>

        <?php elseif (!$scIsAdmin): ?>

            <a class="<?= $scCurrentPage === 'index.php' ? 'active' : '' ?>"
               href="index.php">Home</a>

            <a class="<?= $scCurrentPage === 'materials.php' ? 'active' : '' ?>"
               href="materials.php">Materials</a>

            <a class="<?= $scCurrentPage === 'forum.php' ? 'active' : '' ?>"
               href="forum.php">Discussion Forum</a>

            <a class="<?= $scCurrentPage === 'study_groups.php' ? 'active' : '' ?>"
               href="study_groups.php">Study Groups</a>

            <?php if ($scIsStudent): ?>

                <a href="dashboard.php">My Dashboard</a>

                <a class="sc-nav-cta" href="upload_material.php">
                    Upload Material
                </a>

            <?php endif; ?>

        <?php endif; ?>

    </nav>

<div class="sc-account">

    <?php if ($scIsStudent || $scIsAdmin): ?>

        <span class="sc-welcome">
            Hi, <?= htmlspecialchars($scName, ENT_QUOTES, 'UTF-8') ?>
        </span>

        <details class="sc-account-menu">

            <summary title="More options" aria-label="More options">
                <span class="sc-menu-dots">⋮</span>
            </summary>

            <div class="sc-dropdown">

                <?php if ($scIsStudent): ?>

                    <a href="dashboard.php">My Account</a>

                    <a href="delete_account.php"
                       class="sc-delete-link">
                        Delete Account
                    </a>

                <?php elseif ($scIsAdmin): ?>

                    <a href="dashboard.php">Admin Account</a>

                <?php endif; ?>

                <form method="POST" action="<?= htmlspecialchars($scLogoutLink, ENT_QUOTES, 'UTF-8') ?>">
                    <input
                        type="hidden"
                        name="logout_csrf"
                        value="<?= htmlspecialchars($_SESSION["logout_csrf"], ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <input type="hidden" name="switch" value="1">

                    <button type="submit" class="sc-switch-link">
                        Login with Another Account
                    </button>
                </form>

                <form method="POST" action="<?= htmlspecialchars($scLogoutLink, ENT_QUOTES, 'UTF-8') ?>">
                    <input
                        type="hidden"
                        name="logout_csrf"
                        value="<?= htmlspecialchars($_SESSION["logout_csrf"], ENT_QUOTES, 'UTF-8') ?>"
                    >

                    <button type="submit" class="sc-logout-link">
                        Log Out
                    </button>
                </form>

            </div>

        </details>

    <?php else: ?>

        <a class="sc-account-link" href="login.php">Log In</a>

        <a class="sc-nav-cta" href="register.php">Join Now</a>

    <?php endif; ?>

</div>

</header>

<script>
document.addEventListener("click", function(event) {

    document.querySelectorAll(".sc-account-menu[open]").forEach(function(menu) {

        if (!menu.contains(event.target)) {
            menu.open = false;
        }

    });

});

document.addEventListener("keydown", function(event) {

    if (event.key === "Escape") {

        document.querySelectorAll(".sc-account-menu[open]").forEach(function(menu) {
            menu.open = false;
        });

    }

});
</script>