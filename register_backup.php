<?php
session_start();

require_once "config/database.php";
require_once "includes/alerts.php";

$errors = [];
$name = "";
$email = "";

if (empty($_SESSION["register_csrf"])) {
    $_SESSION["register_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name = trim($_POST["name"] ?? "");
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $confirmPassword = $_POST["confirm_password"] ?? "";
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["register_csrf"], $csrfToken)
    ) {
        $errors[] = "Invalid request. Please refresh the page and try again.";
    }

    if ($name === "") {
        $errors[] = "Please enter your full name.";
    } elseif (strlen($name) > 100) {
        $errors[] = "Your name is too long.";
    }

    if ($email === "") {
        $errors[] = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Please enter a valid email address.";
    } elseif (strlen($email) > 255) {
        $errors[] = "Your email address is too long.";
    }

    if (strlen($password) < 8) {
        $errors[] = "Your password must contain at least 8 characters.";
    }

    if ($password !== $confirmPassword) {
        $errors[] = "The passwords do not match.";
    }

    if (empty($errors)) {
        $check = $conn->prepare(
            "SELECT user_id FROM users WHERE email = ? LIMIT 1"
        );

        if (!$check) {
            $errors[] = "Unable to check the email. Please try again.";
        } else {
            $check->bind_param("s", $email);
            $check->execute();

            $checkResult = $check->get_result();
            $emailExists = $checkResult->num_rows > 0;

            $check->close();

            if ($emailExists) {
                $errors[] =
                    "This email is already registered. Please log in.";
            } else {
                $passwordHash = password_hash(
                    $password,
                    PASSWORD_DEFAULT
                );

                $role = "student";

                $stmt = $conn->prepare(
                    "INSERT INTO users (name, email, password_hash, role)
                     VALUES (?, ?, ?, ?)"
                );

                if (!$stmt) {
                    $errors[] =
                        "Unable to create your account. Please try again.";
                } else {
                    $stmt->bind_param(
                        "ssss",
                        $name,
                        $email,
                        $passwordHash,
                        $role
                    );

                    if ($stmt->execute()) {
                        $stmt->close();

                        unset(
                            $_SESSION["user_id"],
                            $_SESSION["name"],
                            $_SESSION["role"]
                        );

                        session_regenerate_id(true);

                        $_SESSION["register_csrf"] =
                            bin2hex(random_bytes(32));
                            
                            setAlert(
                                "success",
                                "Registration Successful!",
                                "Your account has been created. You can now log in."
                            );

                            header("Location: login.php?registered=1");
                            exit;
                            
                    } else {

                        if ($conn->errno === 1062) {
                            $errors[] =
                                "This email is already registered. Please log in.";
                        } else {
                            $errors[] =
                                "Registration failed. Please try again.";
                        }

                        $stmt->close();
                    }
                }
            }
        }
    }

    if (!empty($errors)) {
        setAlert(
            "error",
            "Registration Failed",
            implode("\n", $errors)
        );
    }
}

$csrfToken = $_SESSION["register_csrf"];
?>

<html>

<head>
    <title>Student Registration | StudyCircle</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            min-height: 100vh;
            padding: 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            font-family: Arial, sans-serif;
            background: #F3F6F5;
            color: #263445;
        }

        .register-card {
            width: 100%;
            max-width: 460px;
            padding: 32px;
            background: #FFFFFF;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(0, 0, 0, 0.08);
        }

        .logo {
            color: #16834A;
            text-align: center;
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        h1 {
            text-align: center;
            font-size: 25px;
            margin-bottom: 10px;
        }

        .subtitle {
            color: #657386;
            text-align: center;
            line-height: 1.5;
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin: 16px 0 7px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #CCD4D9;
            border-radius: 7px;
            font-size: 15px;
        }

        input:focus {
            outline: 2px solid #16834A;
            border-color: #16834A;
        }

        button {
            width: 100%;
            padding: 13px;
            margin-top: 22px;
            border: none;
            border-radius: 7px;
            background: #16834A;
            color: #FFFFFF;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #106638;
        }

        .links {
            margin-top: 20px;
            text-align: center;
            line-height: 1.9;
        }

        a {
            color: #174A8B;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

    </style>

</head>

<body>

    <main class="register-card">

        <div class="logo">StudyCircle</div>

        <h1>Create Your Account</h1>

        <p class="subtitle">
            Join your academic community and share learning resources.
        </p>

        <form action="register.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label for="name">Full Name</label>

            <input
                type="text"
                id="name"
                name="name"
                value="<?= htmlspecialchars(
                    $name,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
                autocomplete="name"
            >

            <label for="email">Email Address</label>

            <input
                type="text"
                id="email"
                name="email"
                inputmode="email"
                value="<?= htmlspecialchars(
                    $email,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
                autocomplete="email"
            >

            <label for="password">Password</label>

            <input
                type="password"
                id="password"
                name="password"
                autocomplete="new-password"
            >

            <label for="confirm_password">Confirm Password</label>

            <input
                type="password"
                id="confirm_password"
                name="confirm_password"
                autocomplete="new-password"
            >

            <button type="submit">Create Account</button>

        </form>

        <div class="links">
            Already have an account?
            <a href="login.php">Sign in</a>
            <br>
            <a href="index.php">Back to Home</a>
        </div>

    </main>

    <?php showAlert(); ?>
    
</body>
</html>