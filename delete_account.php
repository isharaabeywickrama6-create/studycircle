<?php
session_start();

require_once "config/database.php";
require_once "includes/alerts.php";

if (
    !isset($_SESSION["user_id"]) ||
    ($_SESSION["role"] ?? "") !== "student"
) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

if (empty($_SESSION["delete_account_csrf"])) {
    $_SESSION["delete_account_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["csrf_token"] ?? "";
    $password = $_POST["password"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["delete_account_csrf"], $csrfToken)
    ) {
        setAlert("error", "Security Error", "Please refresh the page and try again.");
        header("Location: delete_account.php");
        exit;
    }

    if (!is_string($password) || $password === "") {
        setAlert("error", "Password Required", "Enter your current password to continue.");
        header("Location: delete_account.php");
        exit;
    }

    $conn->begin_transaction();

    try {
        $stmt = $conn->prepare(
            "SELECT password_hash FROM users
             WHERE user_id = ? AND role = 'student'
             FOR UPDATE"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        $stmt->close();

        if (!$user || !password_verify($password, $user["password_hash"])) {
            throw new RuntimeException("Your password is incorrect.");
        }

        $stmt = $conn->prepare(
            "UPDATE materials SET user_id = NULL WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE discussions SET user_id = NULL WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "UPDATE answers SET user_id = NULL WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "SELECT group_id FROM study_groups WHERE creator_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $groupsResult = $stmt->get_result();

        $ownedGroups = [];
        while ($group = $groupsResult->fetch_assoc()) {
            $ownedGroups[] = (int) $group["group_id"];
        }
        $stmt->close();

        foreach ($ownedGroups as $groupId) {
            $stmt = $conn->prepare(
                "SELECT user_id FROM group_members
                 WHERE group_id = ? AND user_id <> ?
                 ORDER BY joined_at ASC, user_id ASC
                 LIMIT 1"
            );
            $stmt->bind_param("ii", $groupId, $userId);
            $stmt->execute();
            $memberResult = $stmt->get_result();
            $member = $memberResult->fetch_assoc();
            $stmt->close();

            if ($member) {
                $newCreatorId = (int) $member["user_id"];

                $stmt = $conn->prepare(
                    "UPDATE study_groups
                     SET creator_id = ?
                     WHERE group_id = ? AND creator_id = ?"
                );
                $stmt->bind_param(
                    "iii",
                    $newCreatorId,
                    $groupId,
                    $userId
                );
            } else {
                $stmt = $conn->prepare(
                    "UPDATE study_groups
                     SET creator_id = NULL
                     WHERE group_id = ? AND creator_id = ?"
                );
                $stmt->bind_param("ii", $groupId, $userId);
            }

            $stmt->execute();
            $stmt->close();
        }

        $stmt = $conn->prepare(
            "UPDATE study_sessions SET created_by = NULL WHERE created_by = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM resource_ratings WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM resource_comments WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM resource_downloads WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM group_members WHERE user_id = ?"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare(
            "DELETE FROM users WHERE user_id = ? AND role = 'student'"
        );
        $stmt->bind_param("i", $userId);
        $stmt->execute();

        if ($stmt->affected_rows !== 1) {
            $stmt->close();
            throw new RuntimeException("The student account could not be deleted.");
        }

        $stmt->close();

        $conn->commit();

        $_SESSION = [];
        session_regenerate_id(true);

        setAlert(
            "success",
            "Account Deleted",
            "Your account and personal activity have been permanently deleted."
        );

        header("Location: login.php");
        exit;

    } catch (Throwable $e) {
        $conn->rollback();

        $message = $e instanceof RuntimeException
            ? $e->getMessage()
            : "Your account could not be deleted. No changes were saved.";

        setAlert("error", "Account Deletion Failed", $message);

        header("Location: delete_account.php");
        exit;
    }
}
?>

<html>

<head>
    <title>Delete Account | NSBM StudyCircle</title>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            padding: 24px;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #F3F6F5;
            color: #263445;
            font-family: Arial, sans-serif;
        }

        .delete-card {
            width: 100%;
            max-width: 480px;
            padding: 32px;
            background: #FFFFFF;
            border-radius: 14px;
            box-shadow: 0 5px 24px rgba(0, 0, 0, 0.08);
        }

        h1 {
            margin-top: 0;
            color: #123665;
            font-size: 26px;
        }

        .warning {
            padding: 14px;
            margin: 20px 0;
            background: #FFF0F0;
            border-left: 4px solid #C62828;
            border-radius: 6px;
            line-height: 1.6;
        }

        label {
            display: block;
            margin: 18px 0 8px;
            font-weight: bold;
        }

        input {
            width: 100%;
            padding: 12px;
            border: 1px solid #CBD3DC;
            border-radius: 6px;
            font-size: 16px;
        }

        button, .cancel-link {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 16px;
            border: none;
            border-radius: 6px;
            font-size: 15px;
            text-align: center;
            text-decoration: none;
            cursor: pointer;
        }

        .delete-button {
            background: #C62828;
            color: #FFFFFF;
        }

        .delete-button:hover {
            background: #A61F1F;
        }

        .cancel-link {
            background: #E9EDF0;
            color: #263445;
        }

        .small-note {
            margin-top: 16px;
            color: #657386;
            font-size: 13px;
            line-height: 1.5;
        }

    </style>

</head>

<body>

<div class="delete-card">

    <h1>Delete Your Account</h1>

    <p>
        You are about to permanently delete your NSBM StudyCircle student account.
    </p>

    <div class="warning">
        <strong>This action cannot be undone.</strong>
        Your personal details, ratings, comments, download history and
        group memberships will be deleted. Shared academic content may
        remain without your name.
    </div>

    <form method="POST" id="deleteAccountForm">
        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo htmlspecialchars(
                $_SESSION["delete_account_csrf"],
                ENT_QUOTES,
                "UTF-8"
            ); ?>"
        >

        <label for="password">Enter your current password</label>

        <input
            type="password"
            id="password"
            name="password"
            autocomplete="current-password"
            placeholder="Your current password"
        >

        <button type="submit" class="delete-button">
            Permanently Delete My Account
        </button>

    </form>

    <a href="index.php" class="cancel-link">Cancel and Return</a>

    <p class="small-note">
        Shared study materials, discussions, answers and study groups
        may be retained for other students.
    </p>

</div>

<?php showAlert(); ?>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
document.getElementById("deleteAccountForm").addEventListener("submit", function (event) {
    event.preventDefault();

    const form = this;
    const password = document.getElementById("password").value;

    if (password.trim() === "") {
        Swal.fire({
            icon: "warning",
            title: "Password Required",
            text: "Enter your current password before continuing.",
            confirmButtonColor: "#16834A"
        });
        return;
    }

    Swal.fire({
        icon: "warning",
        title: "Delete Your Account Permanently?",
        text: "Your personal account and activity will be deleted. This cannot be undone.",
        showCancelButton: true,
        confirmButtonText: "Yes, delete my account",
        cancelButtonText: "No, keep my account",
        confirmButtonColor: "#C62828",
        cancelButtonColor: "#657386",
        focusCancel: true
    }).then(function (result) {
        if (result.isConfirmed) {
            form.submit();
        }
    });
});
</script>

</body>
</html>