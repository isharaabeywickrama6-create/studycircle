<?php
session_start();

$isLoggedIn = isset($_SESSION["user_id"])
    && ($_SESSION["role"] ?? "") === "student";

$userName = $isLoggedIn
    ? ($_SESSION["name"] ?? "Student")
    : "";

$userName = htmlspecialchars($userName, ENT_QUOTES, "UTF-8");
?>

<html>

<head>
    <title>StudyCircle | NSBM Learning Community</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>

        :root {
            --green: #16834A;
            --dark-green: #106638;
            --blue: #174A8B;
            --dark-blue: #123665;
            --light-green: #E8F5EC;
            --background: #F3F6F5;
            --text: #263445;
            --muted: #657386;
            --white: #FFFFFF;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            color: var(--text);
            background: var(--background);
            line-height: 1.6;
            background-image: url("nsbm_jpg.jpg");
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
        }

        nav {
            background: var(--white);
            padding: 8px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            flex-wrap: wrap;
            box-shadow: 0 2px 10px #26344510;
            position: relative;
            z-index: 100;
        }

        .logo {
            color: var(--dark-green);
            font-size: 25px;
            font-weight: bold;
            text-decoration: none;
            white-space: nowrap;
        }

        .nav-right {
            display: flex;
            align-items: center;
            gap: 22px;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .nav-links a {
            text-decoration: none;
            color: var(--text);
            font-weight: 600;
        }

        .nav-links a:hover {
            color: var(--green);
        }

        .more-options {
            position: relative;
        }

        .more-button {
            width: 44px;
            height: 44px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid #E0E6E2;
            border-radius: 50%;
            background: var(--white);
            color: var(--dark-blue);
            font-size: 27px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
        }

        .more-button:hover,
        .more-button:focus-visible {
            background: var(--light-green);
            border-color: var(--green);
            outline: none;
        }

        .more-button:focus-visible {
            box-shadow: 0 0 0 3px #16834A30;
        }

        .more-menu {
            position: absolute;
            top: calc(100% + 12px);
            right: 0;
            width: 255px;
            background: var(--white);
            border: 1px solid #E2E8E4;
            border-radius: 12px;
            box-shadow: 0 10px 30px #26344520;
            padding: 8px;
            display: none;
            z-index: 200;
        }

        .more-menu.show {
            display: block;
        }

        .menu-heading {
            padding: 10px 12px;
            color: var(--muted);
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .more-menu a {
            display: flex;
            align-items: center;
            gap: 11px;
            padding: 11px 12px;
            border-radius: 7px;
            text-decoration: none;
            color: var(--text);
            font-size: 14px;
            font-weight: 600;
        }

        .more-menu a:hover {
            background: var(--light-green);
            color: var(--dark-green);
        }

        .menu-icon {
            width: 22px;
            text-align: center;
            font-size: 18px;
        }

        .menu-divider {
            border: 0;
            border-top: 1px solid #E9EDF0;
            margin: 6px 4px;
        }

        .more-menu a.danger {
            color: #C62828;
        }

        .more-menu a.danger:hover {
            background: #FFF0F0;
            color: #A61F1F;
        }

        .welcome-text {
            color: var(--dark-green);
            font-size: 14px;
            font-weight: bold;
            max-width: 180px;
            overflow-wrap: anywhere;
        }

        .hero {
            padding: 75px 6%;
            text-align: center;
            background: linear-gradient(
                var(--dark-green),
                var(--green),
                var(--dark-green)
            );
            color: var(--white);
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
        }

        .hero h1 {
            font-size: clamp(32px, 5vw, 52px);
            line-height: 1.2;
            margin: 0 auto 20px;
            max-width: 850px;
        }

        .hero p {
            max-width: 650px;
            margin: 0 auto 28px;
            color: #E8F5EC;
            font-size: 18px;
        }

        .button {
            display: inline-block;
            padding: 13px 22px;
            background: var(--white);
            color: var(--dark-green);
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            margin: 5px;
        }

        .button:hover {
            background: var(--light-green);
        }

        .button-outline {
            border: 1px solid var(--white);
            background: transparent;
            color: var(--white);
        }

        .button-outline:hover {
            background: #FFFFFF20;
            color: var(--white);
        }

        .container {
            max-width: 1150px;
            margin: auto;
            padding: 50px 22px;
        }

        .section-heading {
            text-align: center;
            margin-bottom: 30px;
            background-color: #FFFFFF;
            border-radius: 20px;
            padding: 30px 22px;
        }

        .section-heading h2 {
            color: var(--dark-blue);
            font-size: 30px;
            margin-bottom: 8px;
        }

        .section-heading p {
            color: var(--muted);
            margin-top: 0;
        }

        .features {
            display: grid;
            grid-template-columns: repeat(
                auto-fit, minmax(220px, 1fr)
            );
            gap: 22px;
        }

        .feature {
            background: var(--white);
            padding: 25px;
            border-radius: 14px;
            border-top: 4px solid var(--green);
            box-shadow: 0 4px 15px #2634450D;
        }

        .feature-icon {
            font-size: 32px;
        }

        .feature h3 {
            color: var(--dark-blue);
            margin: 12px 0 8px;
        }

        .feature p {
            color: var(--muted);
            font-size: 15px;
        }

        .feature a {
            color: var(--dark-green);
            font-weight: bold;
            text-decoration: none;
        }

        .join-section {
            background: var(--light-green);
            text-align: center;
            padding: 45px 20px;
        }

        .join-section h2 {
            color: var(--dark-blue);
        }

        .join-button {
            display: inline-block;
            background: var(--green);
            color: var(--white);
            padding: 12px 22px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
        }

        .join-button:hover {
            background: var(--dark-green);
        }

        @media (max-width: 900px) {
            nav {
                flex-wrap: wrap;
            }

            .nav-right {
                flex: 1;
                justify-content: flex-end;
                gap: 12px;
            }

            .nav-links {
                gap: 12px;
            }
        }

        @media (max-width: 600px) {
            nav {
                padding: 13px 5%;
                gap: 12px;
            }

            .logo {
                font-size: 22px;
            }

            .nav-right {
                flex: 0 0 auto;
                margin-left: auto;
            }

            .nav-links {
                display: none;
            }

            .more-menu {
                width: min(255px, calc(100vw - 30px));
            }

            .hero {
                padding: 55px 20px;
            }

            .container {
                padding: 35px 18px;
            }

            .section-heading h2 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>

<section class="hero">

    <h1>Learn Together. Grow Together.</h1>

    <p>
        Welcome to StudyCircle, a student learning community
        designed to share knowledge, discover study resources,
        discuss academic questions, and learn together.
    </p>

    <?php if (!$isLoggedIn): ?>
        <a href="register.php" class="button">Join StudyCircle</a>
    <?php else: ?>
        <a href="materials.php" class="button">Explore Materials</a>
    <?php endif; ?>

    <a href="#features" class="button button-outline">
        Explore Features
    </a>

</section>

<main>

    <section class="container" id="features">

        <div class="section-heading">
            <h2>Explore StudyCircle</h2>
            <p>Everything you need to support your academic journey.</p>
        </div>

        <div class="features">

            <div class="feature">
                <div class="feature-icon">📚</div>
                <h3>Study Materials</h3>
                <p>
                    Discover lecture notes, revision resources,
                    and other academic materials shared by students.
                </p><br>
                <a href="materials.php">Explore Materials →</a>
            </div>

            <div class="feature">
                <div class="feature-icon">💬</div>
                <h3>Academic Forum</h3>
                <p>
                    Ask academic questions, share ideas, and
                    learn from discussions with other students.
                </p>
                <a href="forum.php">Visit Forum →</a>
            </div>

            <div class="feature">
                <div class="feature-icon">🎓</div>
                <h3>Study Groups</h3>
                <p>
                    Discover study groups, connect with classmates,
                    and collaborate on your learning goals.
                </p>
                <a href="study_groups.php">Explore Groups →</a>
            </div>

            <div class="feature">
                <div class="feature-icon">🤝</div>
                <h3>Student Community</h3>
                <p>
                    Share knowledge, support fellow students,
                    and build a collaborative learning community.
                </p>
                <a href="register.php">Join the Community →</a>
            </div>

        </div>

    </section>

    <?php if (!$isLoggedIn): ?>

        <section class="join-section">

            <h2>Your Learning Journey Starts Here</h2>
            <p>Become part of a community that learns and grows together.</p>
            <a href="register.php" class="join-button">
                Get Started
            </a>
            
        </section>

    <?php endif; ?>

</main>

<?php include "includes/footer.php"; ?>

<script>
const moreButton = document.getElementById("moreButton");
const moreMenu = document.getElementById("moreMenu");

moreButton.addEventListener("click", function (event) {
    event.stopPropagation();

    const isOpen = moreMenu.classList.toggle("show");
    moreButton.setAttribute("aria-expanded", String(isOpen));
});

document.addEventListener("click", function (event) {
    if (!event.target.closest(".more-options")) {
        moreMenu.classList.remove("show");
        moreButton.setAttribute("aria-expanded", "false");
    }
});

document.addEventListener("keydown", function (event) {
    if (event.key === "Escape") {
        moreMenu.classList.remove("show");
        moreButton.setAttribute("aria-expanded", "false");
        moreButton.focus();
    }
});

const logoutLink = document.getElementById("logoutLink");

if (logoutLink) {
    logoutLink.addEventListener("click", function (event) {
        event.preventDefault();

        if (confirm("Are you sure you want to log out?")) {
            window.location.href = logoutLink.href;
        }
    });
}

const deleteAccountLink = document.getElementById("deleteAccountLink");

if (deleteAccountLink) {
    deleteAccountLink.addEventListener("click", function (event) {
        event.preventDefault();

        if (confirm("Do you want to continue to the permanent account deletion page?")) {
            window.location.href = deleteAccountLink.href;
        }
    });
}
</script>

</body>
</html>