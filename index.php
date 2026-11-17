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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="StudyCircle is an NSBM student learning community for sharing study materials, discussing academic questions and collaborating in study groups.">
    
    <title>StudyCircle | NSBM Learning Community</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>
    body {
        background-color: #F3F6F5;
        background-image:
            linear-gradient(rgba(243, 246, 245, 0.8), rgba(243, 246, 245, 0.8)),
            url("assets/studycircle1.jpg");
        background-size: cover;
        background-position: center;
        background-attachment: fixed;
    }

    .hero {
        position: relative;
        isolation: isolate;
        min-height: 440px;
        padding: 90px 6%;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background:
            radial-gradient(circle at 15% 15%, rgba(255,255,255,0.13), transparent 28%),
            radial-gradient(circle at 85% 85%, rgba(255,255,255,0.12), transparent 28%),
            linear-gradient(125deg, #105A35, #16834A 55%, #21965B);
        box-shadow: 0 12px 30px rgba(16, 102, 56, 0.15);
    }

    .hero::before,
    .hero::after {
        content: "";
        position: absolute;
        z-index: -1;
        border: 2px solid rgba(255,255,255,0.12);
        border-radius: 50%;
    }

    .hero::before {
        width: 340px;
        height: 340px;
        top: -170px;
        right: -70px;
    }

    .hero::after {
        width: 230px;
        height: 230px;
        bottom: -130px;
        left: -60px;
    }

    .hero h1 {
        max-width: 900px;
        margin-bottom: 20px;
        font-size: clamp(34px, 5vw, 56px);
        font-weight: 800;
        letter-spacing: -1.5px;
        text-shadow: 0 3px 12px rgba(0,0,0,0.12);
    }

    .hero p {
        max-width: 690px;
        font-size: 17px;
        line-height: 1.8;
    }

    .hero .button {
        min-width: 155px;
        padding: 14px 24px;
        border-radius: 10px;
        transition: transform 0.25s ease, box-shadow 0.25s ease, background 0.25s ease;
        box-shadow: 0 5px 15px rgba(0,0,0,0.10);
    }

    .hero .button:hover {
        transform: translateY(-4px);
        box-shadow: 0 9px 20px rgba(0,0,0,0.16);
    }

    .hero .button-outline {
        box-shadow: none;
    }

    .container {
        max-width: 1200px;
        padding: 65px 22px;
    }

    .section-heading {
        max-width: 750px;
        margin: 0 auto 32px;
        padding: 28px 22px;
        border: 1px solid #E3EBE5;
        border-radius: 18px;
        box-shadow: 0 5px 20px rgba(28,57,41,0.05);
    }

    .section-heading h2 {
        margin-top: 0;
        font-size: clamp(25px, 3vw, 32px);
        letter-spacing: -0.6px;
        font-weight: 700;
    }

    .section-heading p {
        font-size: 15px;
        font-weight: 700;
    }

    .features {
        gap: 22px;
    }

    .feature {
        position: relative;
        min-height: 245px;
        padding: 28px 24px;
        border: 1px solid #E3EBE5;
        border-top: 4px solid #16834A;
        border-radius: 17px;
        background: rgba(255,255,255,0.97);
        box-shadow: 0 5px 18px rgba(28,57,41,0.055);
        transition: transform 0.25s ease, box-shadow 0.25s ease, border-color 0.25s ease;
    }

    .feature:hover {
        transform: translateY(-7px);
        border-color: #B8DCC4;
        box-shadow: 0 14px 28px rgba(28,57,41,0.11);
    }

    .feature-icon {
        display: grid;
        place-items: center;
        width: 58px;
        height: 58px;
        border-radius: 16px;
        background: linear-gradient(145deg, #E8F5EC, #D8EFDF);
        font-size: 29px;
        transition: transform 0.25s ease;
    }

    .feature:hover .feature-icon {
        transform: scale(1.08) rotate(-4deg);
    }

    .feature h3 {
        margin: 18px 0 9px;
        font-size: 19px;
        color: #174A8B;
    }

    .feature p {
        min-height: 70px;
        font-size: 14px;
        line-height: 1.7;
    }

    .feature a {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        margin-top: 8px;
        font-size: 14px;
        transition: gap 0.2s ease;
    }

    .feature a:hover {
        gap: 10px;
        color: #106638;
    }

    .join-section {
        position: relative;
        overflow: hidden;
        padding: 65px 20px;
        background:
            radial-gradient(circle at 90% 10%, rgba(255,255,255,0.13), transparent 25%),
            linear-gradient(120deg, #E8F5EC, #DDF0E3);
        border-top: 1px solid #D4E8D9;
        border-bottom: 1px solid #D4E8D9;
    }

    .join-section h2 {
        margin-top: 0;
        font-size: clamp(25px, 3vw, 34px);
        letter-spacing: -0.5px;
    }

    .join-section p {
        color: #53675B;
        margin-bottom: 22px;
    }

    .join-button {
        padding: 14px 26px;
        border-radius: 10px;
        box-shadow: 0 5px 14px rgba(16,102,56,0.16);
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .join-button:hover {
        transform: translateY(-3px);
        box-shadow: 0 9px 20px rgba(16,102,56,0.20);
    }

    @media (max-width: 700px) {
        .hero {
            min-height: 390px;
            padding: 65px 20px;
        }

        .hero h1 {
            font-size: 34px;
            letter-spacing: -0.8px;
        }

        .hero p {
            font-size: 14px;
        }

        .container {
            padding: 40px 17px;
        }

        .section-heading {
            padding: 23px 16px;
        }
    
        .feature {
            min-height: auto;
            padding: 23px;
        }

        .feature p {
            min-height: 0;
        }

        .join-section {
            padding: 45px 18px;
        }
    }

    @media (prefers-reduced-motion: reduce) {
        .feature,
        .feature-icon,
        .feature a,
        .hero .button,
        .join-button {
            transition: none;
        }
    }

    body {
        background-color: #F3F6F5;
        color: #263445;
    }

    .hero .button {
        transition: transform 0.25s ease,
                    box-shadow 0.25s ease,
                    background 0.25s ease;
    }

    .container {
        width: 100%;
        max-width: 1240px;
        margin: 0 auto;
        padding: 55px 24px;
    }

    .section-heading {
        width: 100%;
        max-width: auto;
        margin: 0 auto 30px;
        padding: 40px 20px 10px;
        border: none;
        border-radius: 0;
        box-shadow: none;
        text-align: center;
    }

    .features {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        gap: 24px;
        align-items: stretch;
    }

    .feature {
        display: flex;
        flex-direction: column;
        min-width: 0;
        min-height: 270px;
        padding: 26px 23px;
        background: #FFFFFF;
        border: 1px solid #DCE5DF;
        border-top: 4px solid #16834A;
        border-radius: 16px;
        box-shadow: 0 4px 14px rgba(38, 52, 69, 0.06);
    }

    .feature:hover {
        border-color: #B8DCC4;
        border-top-color: #16834A;
    }

    .feature-icon {
        flex-shrink: 0;
        background: #E8F5EC;
    }

    .feature h3 {
        color: #174A8B;
    }

    .feature p {
        flex-grow: 1;
        min-height: 0;
        color: #657386;
    }

    .feature a {
        align-self: flex-start;
        margin-top: 15px;
        color: #16834A;
        font-weight: 600;
    }

    @media (max-width: 1000px) {
        .features {
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 22px;
        }
    }

    @media (max-width: 600px) {
        .container {
            padding: 38px 18px;
        }

        .features {
            grid-template-columns: 1fr;
            gap: 18px;
        }

        .feature {
            min-height: auto;
            padding: 23px;
        }

        .section-heading {
            margin-bottom: 22px;
        }
    }

    .hero {
        text-align: center;
        align-items: center;
        justify-content: center;
        padding: 85px 24px;
        gap: 0;
    }

    .hero h1 {
        width: 100%;
        max-width: 900px;
        margin: 0 auto 20px;
        color: #FFFFFF;
        text-align: center;
        font-size: clamp(36px, 5vw, 54px);
        font-weight: 800;
        line-height: 1.2;
    }

    .hero p {
        width: 100%;
        max-width: 720px;
        margin: 0 auto 30px;
        color: #F0F5F1;
        text-align: center;
        font-size: 17px;
        line-height: 1.8;
    }

    .hero a.button {
        display: inline-flex;
        width: auto;
        align-items: center;
        justify-content: center;
        min-width: 175px;
        min-height: 48px;
        margin: 5px 7px;
        padding: 13px 24px;
        border: 2px solid #FFFFFF;
        border-radius: 10px;
        background: #FFFFFF;
        color: #106638;
        font-size: 15px;
        font-weight: 700;
        text-align: center;
        text-decoration: none;
        white-space: nowrap;
        box-shadow: 0 5px 15px rgba(0,0,0,0.12);
        transition: all 0.25s ease;
    }

    .hero a.button:hover {
        background: #E8F5EC;
        border-color: #E8F5EC;
        color: #106638;
        transform: translateY(-3px);
    }

    .hero a.button-outline {
        background: transparent;
        color: #FFFFFF;
        border: 2px solid rgba(255,255,255,0.9);
        box-shadow: none;
    }

    .hero a.button-outline:hover {
        background: rgba(255,255,255,0.15);
        color: #FFFFFF;
        border-color: #FFFFFF;
    }

    @media (max-width: 600px) {
        .hero {
            padding: 65px 18px;
        }

        .hero h1 {
            font-size: 34px;
        }

        .hero p {
            font-size: 15px;
            margin-bottom: 24px;
        }

        .hero a.button {
            width: 100%;
            max-width: 240px;
            margin: 6px auto;
        }
    }

        .hero-actions {
        display: flex;
        flex-direction: row;
        justify-content: center;
        align-items: center;
        flex-wrap: nowrap;
        gap: 14px;
        width: 100%;
    }

    .hero-actions a.button {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: auto;
        min-width: 175px;
        margin: 0;
    }

    @media (max-width: 420px) {
        .hero-actions {
            gap: 8px;
        }

        .hero-actions a.button {
            min-width: 0;
            padding: 12px 13px;
            font-size: 12px;
        }
    }

    .section-heading .sc-dashboard-label {
        display: inline-block;
        margin-bottom: 12px;
        padding: 6px 12px;
        font-size: 14px;
        font-weight: 700;
        letter-spacing: 1.7px;
        text-transform: uppercase;
        color: #16834A;
        background: #E8F5EC;
        border: 1px solid #C8E5D0;
        border-radius: 30px;
    }

    .sc-feature-label {
        display: inline-block;
        align-self: flex-start;
        margin-top: 15px;
        margin-bottom: 2px;
        padding: 5px 11px;
        color: #174A8B;
        background: #eff1f5;
        border: 1px solid #C8E5D0;
        border-radius: 30px;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 1.2px;
        text-transform: uppercase;
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

    <div class="hero-actions">

        <?php if (!$isLoggedIn): ?>
            <a href="register.php" class="button">Join StudyCircle</a>
        <?php else: ?>
            <a href="materials.php" class="button">Explore Materials</a>
        <?php endif; ?>

        <a href="#features" class="button button-outline">Explore Features</a>

    </div>

</section>

<main>

    <section class="container" id="features">

        <div class="section-heading">
            <span class="sc-dashboard-label">EXPLORE OUR FEATURES</span>
            <h2>Explore StudyCircle</h2>
            <p>Everything you need to support your academic journey.</p>
        </div>

        <div class="features">

            <div class="feature">
                <div class="feature-icon">📚</div>
                <span class="sc-feature-label">RESOURCES</span>
                <p>
                    Discover lecture notes, revision resources,
                    and other academic materials shared by students.
                </p><br>
                <a href="materials.php">Explore Materials →</a>
            </div>

            <div class="feature">
                <div class="feature-icon">💬</div>
                <span class="sc-feature-label">DISCUSSIONS</span>
                <p>
                    Ask academic questions, share ideas, and
                    learn from discussions with other students.
                </p>
                <a href="forum.php">Visit Forum →</a>
            </div>

            <div class="feature">
                <div class="feature-icon">🎓</div>
                <span class="sc-feature-label">COLLABORATION</span>
                <p>
                    Discover study groups, connect with classmates,
                    and collaborate on your learning goals.
                </p>
                <a href="study_groups.php">Explore Groups →</a>
            </div>

            <div class="feature">
                <div class="feature-icon">🤝</div>
                <span class="sc-feature-label">COMMUNITY</span>
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