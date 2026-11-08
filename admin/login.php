<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/../config/database.php";

$error = "";

if (!isset($_SESSION["admin_login_attempts"])) {
    $_SESSION["admin_login_attempts"] = 0;
}

if (!isset($_SESSION["admin_login_blocked_until"])) {
    $_SESSION["admin_login_blocked_until"] = 0;
}

if (
    $_SESSION["admin_login_blocked_until"] > 0 &&
    $_SESSION["admin_login_blocked_until"] <= time()
) {
    $_SESSION["admin_login_attempts"] = 0;
    $_SESSION["admin_login_blocked_until"] = 0;
}

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    $_SESSION["admin_login_blocked_until"] > time()
) {
    $error = "Too many failed attempts. Please try again after 5 minutes.";
} elseif ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"] ?? "";
    $password = $_POST["password"] ?? "";

    if (!is_string($email) || !is_string($password)) {
        $error = "Invalid email or password, or admin access is not permitted.";
    } else {

        $email = trim($email);

        if ($email === "" || $password === "") {
            $error = "Invalid email or password, or admin access is not permitted.";
        } else {

            $stmt = $conn->prepare(
                "SELECT user_id, name, password_hash, role
                 FROM users
                 WHERE email = ?
                 LIMIT 1"
            );

            if (!$stmt) {
                error_log("Admin login prepare failed: " . $conn->error);
                $error = "Unable to process login. Please try again.";
            } else {

                $stmt->bind_param("s", $email);

                if (!$stmt->execute()) {
                    error_log("Admin login query failed: " . $stmt->error);
                    $error = "Unable to process login. Please try again.";
                } else {

                    $result = $stmt->get_result();

                    if ($result) {
                        $user = $result->fetch_assoc();
                    } else {
                        $user = null;
                        error_log("Admin login result retrieval failed.");
                    }

                    if (
                        $user &&
                        $user["role"] === "admin" &&
                        password_verify($password, $user["password_hash"])
                    ) {

                        $_SESSION["admin_login_attempts"] = 0;
                        $_SESSION["admin_login_blocked_until"] = 0;

                        session_regenerate_id(true);

                        $_SESSION["user_id"] = $user["user_id"];
                        $_SESSION["role"] = $user["role"];
                        $_SESSION["name"] = $user["name"];

                        $stmt->close();

                        header("Location: dashboard.php");
                        exit;
                    }
                    
                    $_SESSION["admin_login_attempts"]++;

                    if ($_SESSION["admin_login_attempts"] >= 5) {
                        $_SESSION["admin_login_blocked_until"] = time() + (5 * 60);

                        $error = "Too many failed attempts. Please try again after 5 minutes.";
                    } else {
                        $error = "Invalid email or password, or admin access is not permitted.";
                    }
                }

                $stmt->close();
            }
        }
    }
}
?>

<html>

<head>
    <title>Admin Login | StudyCircle</title>

    <style>
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 25px;
            background: linear-gradient(
                135deg,
                #eef2ff,
                #f8f7ff,
                #ede9fe
            );
            color: #1e293b;
        }

        .login-container {
            width: 100%;
            max-width: 440px;
            padding: 38px;
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 12px 40px rgba(30, 27, 75, 0.12);
        }

        .logo {
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 18px;
            border-radius: 17px;
            background: #ede9fe;
            color: #6d28d9;
            font-size: 30px;
            font-weight: bold;
        }

        h1 {
            text-align: center;
            color: #172554;
            font-size: 28px;
            margin-bottom: 10px;
        }

        .subtitle {
            text-align: center;
            color: #64748b;
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .field {
            margin-bottom: 21px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #cbd5e1;
            border-radius: 9px;
            font-size: 15px;
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
        }

        input:focus {
            border-color: #7c3aed;
            box-shadow: 0 0 0 3px rgba(124, 58, 237, 0.12);
        }

        button {
            background: #16834A;
            color: white;
            border: none;
            padding: 10px 16px;
            border-radius: 7px;
            cursor: pointer;
        }

        button:hover {
            background: #106638;
        }

        .error-message {
            padding: 12px 14px;
            margin-bottom: 20px;
            border-radius: 8px;
            border: 1px solid #fecaca;
            background: #fef2f2;
            color: #b91c1c;
            font-size: 14px;
            line-height: 1.5;
            overflow-wrap: anywhere;
        }

        .footer {
            text-align: center;
            margin-top: 25px;
            color: #94a3b8;
            font-size: 12px;
            line-height: 1.6;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 27px 22px;
            }

            h1 {
                font-size: 24px;
            }
        }

        .password-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            width: 100%;
        }

        .password-wrapper input[type="password"],
        .password-wrapper input[type="text"] {
            width: 100%;
            padding: 10px 40px 10px 10px;
            font-size: 14px;
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

        .password-toggle:checked ~ .toggle-label .eye-closed {
            display: none;
        }

        .password-toggle:checked ~ .toggle-label .eye-open {
            display: block;
        }

    </style>
    
</head>

<body>

    <div class="login-container">

        <div class="logo">S</div>

        <h1>Welcome Back!</h1>

        <p class="subtitle">
            Sign in to the StudyCircle administrator portal
            to manage your academic community.
        </p>

        <?php if ($error !== "") { ?>
            <div class="error-message" role="alert">
                <?php
                echo htmlspecialchars(
                    $error,
                    ENT_QUOTES,
                    "UTF-8"
                );
                ?>
            </div>
        <?php } ?>

        <form method="POST">

            <div class="field">
                <label for="email">Admin Email</label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="Enter your admin email"
                    autocomplete="username"
                    required
                >
            </div>

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
                    <svg class="eye-closed" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
                    </svg>

                    <svg class="eye-open" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                </label>
            </div><br><br>

            <button type="submit">
                Sign In
            </button>

        </form>

        <p class="footer">
            StudyCircle &copy; 2026<br>
            NSBM Academic Community
        </p>

    </div>

<script>
    const passwordInput = document.getElementById("password");
    const passwordToggle = document.getElementById("toggle");

    passwordToggle.addEventListener("change", function () {
        if (this.checked) {
            passwordInput.type = "text";
        } else {
            passwordInput.type = "password";
        }
    });
</script>

</body>

</html>

<?php
$conn->close();
?>