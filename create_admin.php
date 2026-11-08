<?php
session_start();

require_once "config/database.php";

$error = "";
$success = "";

$result = $conn->query(
    "SELECT COUNT(*) AS total FROM users WHERE role = 'admin'"
);

$adminExists = false;

if ($result) {
    $row = $result->fetch_assoc();
    $adminExists = ((int)$row["total"] > 0);
}

if (empty($_SESSION["admin_csrf"])) {
    $_SESSION["admin_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST" && !$adminExists) {

    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["admin_csrf"], $csrfToken)) {
        $error = "Invalid request. Please refresh the page.";

    } elseif ($name === "" || $email === "" ||
              $password === "" || $confirmPassword === "") {
        $error = "Please fill in all fields.";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";

    } elseif (strlen($password) < 8) {
        $error = "Password must contain at least 8 characters.";

    } elseif ($password !== $confirmPassword) {
        $error = "Passwords do not match.";

    } else {

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $conn->prepare(
            "INSERT INTO users (name, email, password_hash, role)
             VALUES (?, ?, ?, 'admin')"
        );

        if ($stmt) {
            $stmt->bind_param(
                "sss",
                $name,
                $email,
                $passwordHash
            );

            if ($stmt->execute()) {
                $success = "Admin account created successfully!";
                unset($_SESSION["admin_csrf"]);
                $adminExists = true;
            } else {
                if ($stmt->errno === 1062) {
                    $error = "This email address is already registered.";
                } else {
                    $error = "Unable to create the account. Please try again.";
                }
            }

            $stmt->close();
        } else {
            $error = "Database error. Please try again.";
        }
    }
}
?>

<html>

<head>

    <title>Create Admin Account | StudyCircle</title>

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            background: #F3F6F5;
            color: #263445;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px 15px;
        }

        .container {
            width: 100%;
            max-width: 440px;
            background: white;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(0, 0, 0, 0.08);
        }

        .logo {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo h2 {
            color: #16834A;
            font-size: 27px;
            margin-bottom: 6px;
        }

        .logo p {
            color: #657386;
            font-size: 14px;
        }

        h1 {
            text-align: center;
            font-size: 23px;
            margin-bottom: 8px;
        }

        .subtitle {
            text-align: center;
            color: #657386;
            font-size: 14px;
            margin-bottom: 25px;
            line-height: 1.5;
        }

        .form-group {
            margin-bottom: 18px;
        }

        label {
            display: block;
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #CCD4D9;
            border-radius: 7px;
            font-size: 14px;
            outline: none;
        }

        input:focus {
            border-color: #16834A;
            box-shadow: 0 0 0 2px rgba(22, 131, 74, 0.12);
        }

        button {
            width: 100%;
            padding: 14px;
            background: #16834A;
            color: white;
            border: none;
            border-radius: 7px;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.3s;
        }

        button:hover {
            background: #106638;
        }

        .message {
            padding: 12px;
            border-radius: 7px;
            margin-bottom: 18px;
            font-size: 14px;
            line-height: 1.5;
        }

        .error {
            background: #FDECEC;
            color: #B42318;
            border: 1px solid #F5C2C0;
        }

        .success {
            background: #EAF7EF;
            color: #16834A;
            border: 1px solid #B9E2C8;
        }

        .notice {
            background: #F3F6F5;
            color: #657386;
            padding: 16px;
            border-radius: 8px;
            text-align: center;
            font-size: 14px;
            line-height: 1.6;
        }

        .footer {
            text-align: center;
            margin-top: 22px;
            font-size: 13px;
            color: #657386;
        }

        .footer a {
            color: #174A8B;
            text-decoration: none;
            font-weight: bold;
        }

        .footer a:hover {
            text-decoration: underline;
        }

        @media (max-width: 480px) {
            .container {
                padding: 25px 20px;
            }
        }

    </style>
    
</head>

<body>

<div class="container">

    <div class="logo">
        <h2>StudyCircle</h2>
        <p>Learn Together. Grow Together.</p>
    </div>

    <h1>Create Admin Account</h1>

    <p class="subtitle">
        Set up your StudyCircle administrator account.
    </p>

    <?php if ($error !== ""): ?>
        <div class="message error">
            <?= htmlspecialchars($error) ?>
        </div>
    <?php endif; ?>

    <?php if ($success !== ""): ?>
        <div class="message success">
            <?= htmlspecialchars($success) ?>
        </div>

        <p class="footer">
            <a href="login.php">Go to Login</a>
        </p>

    <?php elseif ($adminExists): ?>

        <div class="notice">
            An administrator account already exists.
            This setup page can no longer be used.
            <br><br>
            <a href="login.php">Go to Login</a>
        </div>

    <?php else: ?>

        <form method="POST" action="">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["admin_csrf"]) ?>"
            >

            <div class="form-group">
                <label for="name">Admin Name</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Enter your name"
                    maxlength="100"
                    required
                >
            </div>

            <div class="form-group">
                <label for="email">Email Address</label>
                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your email"
                    required
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimum 8 characters"
                    minlength="8"
                    required
                >
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input
                    type="password"
                    id="confirm_password"
                    name="confirm_password"
                    placeholder="Re-enter your password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit">
                Create Admin Account
            </button>

        </form>

    <?php endif; ?>

    <div class="footer">
        StudyCircle | NSBM Green University
    </div>

</div>

</body>
</html>