<?php
session_start();

require_once "config/database.php";
require_once "includes/alerts.php";

if (isset($_SESSION["user_id"]) && isset($_SESSION["role"])) {

    if ($_SESSION["role"] === "admin") {
        header("Location: admin/dashboard.php");
    } else {
        header("Location: index.php");
    }
    exit;
}

if (isset($_GET["registered"])) {
    setAlert(
        "success",
        "Registration Successful!",
        "Your account has been created. You can now log in."
    );

    header("Location: login.php");
    exit;
}

if (isset($_GET["session_expired"])) {
    setAlert(
        "warning",
        "Session Expired",
        "Your session has ended. Please log in again to continue."
    );

    header("Location: login.php");
    exit;
}

$error = "";
$email = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        $error = "Please enter both email and password.";
    } else {
        $stmt = $conn->prepare(
            "SELECT user_id, name, email, password_hash, role
             FROM users
             WHERE email = ?
             LIMIT 1"
        );

        if ($stmt) {
            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $user = $result->fetch_assoc();

        if (
            $user &&
            in_array($user["role"], ["student", "admin"], true) &&
            password_verify($password, $user["password_hash"])
        ) {
            session_regenerate_id(true);

            $_SESSION["user_id"] = (int) $user["user_id"];
            $_SESSION["name"] = $user["name"];
            $_SESSION["role"] = $user["role"];

            if ($user["role"] === "admin") {
                header("Location: admin/dashboard.php");
            } else {
                header("Location: index.php");
            }
            exit;

        } else {
            $error = "Invalid email or password.";
        }

            $stmt->close();
        } else {
            $error = "Unable to process login. Please try again.";
        }
    }

    if ($error !== "") {
        setAlert("error", "Login Failed", $error);
    }
}
?>

<html>

<head>
    <title>Student Login | StudyCircle</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #F3F6F5;
            color: #263445;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            width: 100%;
            max-width: 420px;
            background: #FFFFFF;
            padding: 35px;
            border-radius: 14px;
            box-shadow: 0 5px 22px rgba(0, 0, 0, 0.08);
        }

        .logo {
            color: #16834A;
            font-size: 27px;
            font-weight: bold;
            text-align: center;
            margin-bottom: 10px;
        }

        h1 {
            text-align: center;
            font-size: 25px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #657386;
            line-height: 1.5;
            margin-bottom: 25px;
        }

        label {
            display: block;
            font-weight: bold;
            margin: 16px 0 7px;
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
            color: white;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }

        button:hover {
            background: #106638;
        }

        .links {
            text-align: center;
            margin-top: 20px;
            line-height: 1.8;
        }

        a {
            color: #174A8B;
            text-decoration: none;
        }

        a:hover {
            text-decoration: underline;
        }

        .password-wrapper {
            position: relative;
            display: inline-flex;
            align-items: center;
            width: 350px;
        }

        .password-wrapper input[type="text"] {
            width: 100%;
            padding: 10px 40px 10px 10px;
            font-size: 14px;
            -webkit-text-security: disc;
        }

        .password-toggle {
            display: none;
        }

        .toggle-label {
            position: absolute;
            right: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 15px;
            height: 10px;
        }

        .toggle-label .eye-open {
            display: none;
        }

        .password-toggle:checked ~ input[type="text"] {
            -webkit-text-security: none;
        }

        .password-toggle:checked ~ .toggle-label .eye-closed {
            display: none;
        }

        .password-toggle:checked ~ .toggle-label .eye-open {
            display: block;
        }

    </style>

</head>

<body>

    <div class="login-card">

        <div class="logo">StudyCircle</div>

        <h1>Student Login</h1>

        <p class="subtitle">
            Welcome back! Sign in to access your academic resources.
        </p>

        <form method="POST" action="login.php">

            <label for="email">Student Email</label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= htmlspecialchars($email, ENT_QUOTES, "UTF-8") ?>"
                autocomplete="username"
            >

            <label for="password">Password</label>

            <div class="password-wrapper">

                <input type="checkbox" id="toggle" class="password-toggle">

                <input
                    type="password"
                    id="password"
                    placeholder="Enter Password"
                    name="password"
                    autocomplete="current-password"
                    required
                >

                <label for="toggle" class="toggle-label">

                    <svg class="eye-closed" xmlns="http://w3.org" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>

                    <svg class="eye-open" xmlns="http://w3.org" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>

                </label>

            </div><br><br>

            <button type="submit">Sign In</button>

        </form>

        <div class="links">

            <a href="index.php">Back to Home</a>
            <br>
            <a href="admin/login.php">Administrator Login</a>

        </div>
        
    </div>

    <?php showAlert(); ?>

    <script>
    function togglePassword(inputId, button) {
        const passwordInput = document.getElementById(inputId);

        if (passwordInput.type === "password") {
            passwordInput.type = "text";
            button.textContent = "🙈";
            button.setAttribute("aria-label", "Hide password");
        } else {
            passwordInput.type = "password";
            button.textContent = "👁";
            button.setAttribute("aria-label", "Show password");
        }
    }
    </script>

</body>
</html>
