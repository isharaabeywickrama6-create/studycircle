<?php
session_start();

require_once "config/database.php";
require_once "includes/alerts.php";

$errors = [];

if (empty($_SESSION["pending_registration_email"])) {

    setAlert(
        "error",
        "Verification Required",
        "Please register first to receive your verification code."
    );

    header("Location: register.php");
    exit;
}

$email = $_SESSION["pending_registration_email"];

if (empty($_SESSION["verify_csrf"])) {
    $_SESSION["verify_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $otp = trim($_POST["otp"] ?? "");
    $csrfToken = $_POST["csrf_token"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["verify_csrf"], $csrfToken)
    ) {
        $errors[] = "Invalid request. Please refresh the page.";
    }

    if (!preg_match('/^[0-9]{6}$/', $otp)) {
        $errors[] = "Please enter a valid 6-digit OTP.";
    }

    if (empty($errors)) {

        $stmt = $conn->prepare(
            "SELECT pending_id, name, email, password_hash,
                    otp_hash, expires_at, attempts
             FROM pending_registrations
             WHERE email = ?
             LIMIT 1"
        );

        if (!$stmt) {

            $errors[] = "Unable to verify your OTP. Please try again.";

        } else {

            $stmt->bind_param("s", $email);
            $stmt->execute();

            $result = $stmt->get_result();
            $pending = $result->fetch_assoc();

            $stmt->close();

            if (!$pending) {

                $errors[] =
                    "Registration details not found. Please register again.";

            } elseif (strtotime($pending["expires_at"]) < time()) {

                $errors[] =
                    "Your OTP has expired. Please register again.";

            } elseif ((int)$pending["attempts"] >= 5) {

                $errors[] =
                    "Too many incorrect attempts. Please register again.";

            } else {

                if (!password_verify($otp, $pending["otp_hash"])) {

                    $update = $conn->prepare(
                        "UPDATE pending_registrations
                         SET attempts = attempts + 1
                         WHERE email = ?"
                    );

                    if ($update) {
                        $update->bind_param("s", $email);
                        $update->execute();
                        $update->close();
                    }

                    $errors[] =
                        "Incorrect OTP. Please check your email and try again.";

                } else {

                    $check = $conn->prepare(
                        "SELECT user_id FROM users WHERE email = ? LIMIT 1"
                    );

                    if (!$check) {

                        $errors[] =
                            "Unable to complete registration. Please try again.";

                    } else {

                        $check->bind_param("s", $email);
                        $check->execute();

                        $existing = $check->get_result();
                        $emailExists = $existing->num_rows > 0;

                        $check->close();

                        if ($emailExists) {

                            $errors[] =
                                "This email is already registered. Please log in.";

                        } else {

                            $role = "student";

                            $insert = $conn->prepare(
                                "INSERT INTO users
                                (name, email, password_hash, role)
                                VALUES (?, ?, ?, ?)"
                            );

                            if (!$insert) {

                                $errors[] =
                                    "Unable to create your account. Please try again.";

                            } else {

                                $insert->bind_param(
                                    "ssss",
                                    $pending["name"],
                                    $pending["email"],
                                    $pending["password_hash"],
                                    $role
                                );

                                if ($insert->execute()) {

                                    $insert->close();

                                    $delete = $conn->prepare(
                                        "DELETE FROM pending_registrations
                                         WHERE email = ?"
                                    );

                                    if ($delete) {
                                        $delete->bind_param("s", $email);
                                        $delete->execute();
                                        $delete->close();
                                    }

                                    unset(
                                        $_SESSION["pending_registration_email"],
                                        $_SESSION["verify_csrf"],
                                        $_SESSION["user_id"],
                                        $_SESSION["name"],
                                        $_SESSION["role"]
                                    );

                                    session_regenerate_id(true);

                                    setAlert(
                                        "success",
                                        "Email Verified Successfully!",
                                        "Your StudyCircle account has been created. You can now log in."
                                    );

                                    header("Location: login.php?registered=1");
                                    exit;

                                } else {

                                    if ($conn->errno === 1062) {

                                        $errors[] =
                                            "This email is already registered. Please log in.";

                                    } else {

                                        $errors[] =
                                            "Account creation failed. Please try again.";
                                    }

                                    $insert->close();
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    if (!empty($errors)) {

        setAlert(
            "error",
            "Verification Failed",
            implode("\n", $errors)
        );
    }
}

$csrfToken = $_SESSION["verify_csrf"];
?>

<html>

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Email Verification | StudyCircle</title>

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

        .verify-card {
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
            margin-bottom: 20px;
        }

        h1 {
            text-align: center;
            font-size: 25px;
        }

        .subtitle {
            color: #657386;
            text-align: center;
            line-height: 1.6;
            margin-bottom: 24px;
        }

        label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 14px;
            border: 1px solid #CCD4D9;
            border-radius: 7px;
            font-size: 22px;
            text-align: center;
            letter-spacing: 8px;
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

    <main class="verify-card">

        <div class="logo">StudyCircle</div>

        <h1>Verify Your Email</h1>

        <p class="subtitle">
            We have sent a 6-digit verification code to your email address.
            Enter the code below to complete your registration.
        </p>

        <form action="verify_email.php" method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                    $csrfToken,
                    ENT_QUOTES,
                    "UTF-8"
                ) ?>"
            >

            <label for="otp">Enter Verification Code</label>

            <input
                type="text"
                id="otp"
                name="otp"
                inputmode="numeric"
                pattern="[0-9]{6}"
                maxlength="6"
                minlength="6"
                placeholder="000000"
                autocomplete="one-time-code"
                required
            >

            <button type="submit">Verify Email</button>

        </form>

        <div class="links">
            <a href="register.php">Back to Registration</a>
        </div>

    </main>

    <?php showAlert(); ?>

</body>
</html>