<?php
session_start();
require_once "config/database.php";

$isStudent = isset($_SESSION["user_id"])
    && isset($_SESSION["role"])
    && $_SESSION["role"] === "student";

$message = "";
$messageType = "success";

if (empty($_SESSION["groups_csrf"])) {
    $_SESSION["groups_csrf"] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    if (!$isStudent) {
        header("Location: login.php");
        exit;
    }

    $token = $_POST["csrf_token"] ?? "";

    if (!hash_equals($_SESSION["groups_csrf"], $token)) {
        $message = "Invalid request. Please refresh the page.";
        $messageType = "error";
    } else {
        $action = $_POST["action"] ?? "";
        $userId = (int)$_SESSION["user_id"];

        if ($action === "create") {
            $groupName = trim($_POST["group_name"] ?? "");
            $description = trim($_POST["description"] ?? "");
            $subjectRaw = $_POST["subject_id"] ?? "";

            if ($groupName === "" || mb_strlen($groupName) > 150) {
                $message = "Enter a group name of up to 150 characters.";
                $messageType = "error";
            } else {
                $subjectId = null;

                if ($subjectRaw !== "") {
                    $subjectId = filter_var(
                        $subjectRaw,
                        FILTER_VALIDATE_INT
                    );

                    if ($subjectId === false || $subjectId < 1) {
                        $message = "Please select a valid subject.";
                        $messageType = "error";
                    } else {
                        $check = $conn->prepare(
                            "SELECT subject_id FROM subjects WHERE subject_id = ?"
                        );
                        $check->bind_param("i", $subjectId);
                        $check->execute();

                        if ($check->get_result()->num_rows === 0) {
                            $message = "The selected subject was not found.";
                            $messageType = "error";
                        }

                        $check->close();
                    }
                }

                if ($message === "") {
                    $stmt = $conn->prepare(
                        "INSERT INTO study_groups
                         (creator_id, subject_id, group_name, description)
                         VALUES (?, ?, ?, ?)"
                    );

                    if (!$stmt) {
                        $message = "Could not prepare the group. Please try again.";
                        $messageType = "error";
                    } else {
                        $stmt->bind_param(
                            "iiss",
                            $userId,
                            $subjectId,
                            $groupName,
                            $description
                        );

                        if ($stmt->execute()) {
                            $groupId = $conn->insert_id;
                            $stmt->close();

                            $join = $conn->prepare(
                                "INSERT INTO group_members (group_id, user_id)
                                 VALUES (?, ?)"
                            );

                            if ($join) {
                                $join->bind_param("ii", $groupId, $userId);
                                $join->execute();
                                $join->close();
                            }

                            $_SESSION["groups_csrf"] = bin2hex(random_bytes(32));
                            header("Location: study_groups.php?created=1");
                            exit;
                        } else {
                            $message = "Could not create the group. Please try again.";
                            $messageType = "error";
                        }

                        if ($stmt) {
                            $stmt->close();
                        }
                    }
                }
            }
        } elseif ($action === "request_subject") {

            $subjectName = trim($_POST["subject_name"] ?? "");
            $category = trim($_POST["category"] ?? "");

            if (
                $subjectName === "" ||
                $category === "" ||
                mb_strlen($subjectName) > 100 ||
                mb_strlen($category) > 100
            ) {
                $message = "Enter a valid subject name and category.";
                $messageType = "error";

            } else {

                $check = $conn->prepare(
                    "SELECT subject_id FROM subjects
                     WHERE subject_name = ? LIMIT 1"
                );

                $check->bind_param("s", $subjectName);
                $check->execute();

                $subjectExists = $check->get_result()->num_rows > 0;
                $check->close();

                if ($subjectExists) {

                    $message = "This subject already exists. Please select it from the dropdown.";
                    $messageType = "error";

                } else {

                    $check = $conn->prepare(
                        "SELECT request_id FROM subject_requests
                         WHERE subject_name = ?
                         AND status = 'pending'
                         LIMIT 1"
                    );

                    $check->bind_param("s", $subjectName);
                    $check->execute();

                    $requestExists = $check->get_result()->num_rows > 0;
                    $check->close();

                    if ($requestExists) {

                        $message = "This subject has already been requested and is waiting for admin approval.";
                        $messageType = "error";

                    } else {

                        $stmt = $conn->prepare(
                            "INSERT INTO subject_requests
                             (student_id, subject_name, category)
                             VALUES (?, ?, ?)"
                        );

                        if ($stmt) {
                            $stmt->bind_param(
                                "iss",
                                $userId,
                                $subjectName,
                                $category
                            );

                            if ($stmt->execute()) {
                                $message = "Subject request submitted successfully. Please wait for admin approval.";
                                $messageType = "success";
                            } else {
                                $message = "Could not submit your subject request.";
                                $messageType = "error";
                            }

                            $stmt->close();

                        } else {
                            $message = "Could not prepare the subject request.";
                            $messageType = "error";
                        }
                    }
                }
            }
            } elseif ($action === "join") {
            $groupId = filter_var(
                $_POST["group_id"] ?? "",
                FILTER_VALIDATE_INT
            );

            if ($groupId === false || $groupId < 1) {
                $message = "Invalid study group.";
                $messageType = "error";
            } else {
                $check = $conn->prepare(
                    "SELECT group_id FROM study_groups WHERE group_id = ?"
                );
                $check->bind_param("i", $groupId);
                $check->execute();
                $groupExists = $check->get_result()->num_rows > 0;
                $check->close();

                if (!$groupExists) {
                    $message = "This study group was not found.";
                    $messageType = "error";
                } else {
                    $join = $conn->prepare(
                        "INSERT IGNORE INTO group_members (group_id, user_id)
                         VALUES (?, ?)"
                    );

                    if ($join) {
                        $join->bind_param("ii", $groupId, $userId);

                        if ($join->execute()) {
                            $message = $join->affected_rows === 1
                                ? "You joined the study group successfully!"
                                : "You are already a member of this group.";
                        } else {
                            $message = "Could not join the group. Please try again.";
                            $messageType = "error";
                        }

                        $join->close();
                    } else {
                        $message = "Could not prepare the join request.";
                        $messageType = "error";
                    }
                }
            }
            
            } elseif ($action === "delete_session") {

                $sessionId = filter_var(
                    $_POST["session_id"] ?? "",
                    FILTER_VALIDATE_INT
                );

                if (!$sessionId || $sessionId < 1) {
                    $message = "Invalid study session.";
                    $messageType = "error";

                } else {

                    $deleteStmt = $conn->prepare(
                        "DELETE FROM group_sessions
                         WHERE session_id = ? AND created_by = ?"
                    );

                    if ($deleteStmt) {
                        $deleteStmt->bind_param("ii", $sessionId, $userId);

                        if ($deleteStmt->execute() && $deleteStmt->affected_rows === 1) {
                            $deleteStmt->close();

                            header("Location: study_groups.php?session_deleted=1");
                            exit;

                        } else {
                            $message = "You cannot delete this session, or it no longer exists.";
                            $messageType = "error";
                            $deleteStmt->close();
                        }

                    } else {
                        $message = "Could not prepare the delete request.";
                        $messageType = "error";
                    }
                }

            } elseif ($action === "edit_session") {

                $sessionId = filter_var(
                    $_POST["session_id"] ?? "",
                    FILTER_VALIDATE_INT
                );

                $sessionTitle = trim($_POST["session_title"] ?? "");
                $sessionDescription = trim($_POST["session_description"] ?? "");
                $sessionDateRaw = trim($_POST["session_date"] ?? "");
                $startTime = trim($_POST["start_time"] ?? "");
                $endTime = trim($_POST["end_time"] ?? "");
                $sessionType = $_POST["session_type"] ?? "";
                $meetingLink = trim($_POST["meeting_link"] ?? "");
                $location = trim($_POST["location"] ?? "");

                $date = DateTime::createFromFormat("!Y-m-d", $sessionDateRaw);
                $dateErrors = DateTime::getLastErrors();

                $validDate = $date &&
                    ($dateErrors === false ||
                    ($dateErrors["warning_count"] === 0 &&
                     $dateErrors["error_count"] === 0)) &&
                    $date->format("Y-m-d") === $sessionDateRaw;

                $validStartTime = preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $startTime
                );

                $validEndTime = preg_match(
                    '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                    $endTime
                );

                $validMeetingLink = false;

                if (filter_var($meetingLink, FILTER_VALIDATE_URL)) {
                    $scheme = strtolower(
                        parse_url($meetingLink, PHP_URL_SCHEME) ?? ""
                    );

                    $validMeetingLink = in_array(
                        $scheme,
                        ["http", "https"],
                        true
                    );
                }

                if (!$sessionId || $sessionId < 1) {
                    $message = "Invalid study session.";

                } elseif (
                    $sessionTitle === "" ||
                    mb_strlen($sessionTitle) > 150
                ) {
                    $message = "Please enter a valid session title.";

                } elseif (!$validDate || !$validStartTime || !$validEndTime) {
                    $message = "Please enter a valid date and time.";

                } elseif (
                    $sessionDateRaw < date("Y-m-d") ||
                    ($sessionDateRaw === date("Y-m-d") &&
                     $startTime <= date("H:i"))
                ) {
                    $message = "Please select a future session date and time.";

                } elseif ($endTime <= $startTime) {
                    $message = "End time must be later than start time.";

                } elseif (!in_array($sessionType, ["online", "physical"], true)) {
                    $message = "Please select a valid session type.";

                } elseif ($sessionType === "online" && !$validMeetingLink) {
                    $message = "Please enter a valid online meeting link.";

                } elseif (
                    $sessionType === "physical" &&
                    ($location === "" || mb_strlen($location) > 255)
                ) {
                    $message = "Please enter a valid physical location.";

                } elseif (mb_strlen($sessionDescription) > 5000) {
                    $message = "Session description must not exceed 5000 characters.";

                } else {

                    $updateStmt = $conn->prepare(
                        "UPDATE group_sessions
                         SET session_title = ?,
                             session_description = ?,
                             session_date = ?,
                             start_time = ?,
                             end_time = ?,
                             session_type = ?,
                             meeting_link = ?,
                             location = ?
                         WHERE session_id = ? AND created_by = ?"
                    );
            
                    if ($updateStmt) {
                        $updateStmt->bind_param(
                            "ssssssssii",
                            $sessionTitle,
                            $sessionDescription,
                            $sessionDateRaw,
                            $startTime,
                            $endTime,
                            $sessionType,
                            $meetingLink,
                            $location,
                            $sessionId,
                            $userId
                        );
            
                        if ($updateStmt->execute()) {
                            $updateStmt->close();
            
                            header("Location: study_groups.php?session_updated=1");
                            exit;
            
                        } else {
                            $message = "Could not update the study session.";
                            $messageType = "error";
                            $updateStmt->close();
                        }

                    } else {
                        $message = "Could not prepare the session update.";
                        $messageType = "error";
                    }
                }

            } elseif ($action === "create_session") {


                        $messageType = "error";

                            $groupId = filter_var(
                                $_POST["group_id"] ?? null,
                                FILTER_VALIDATE_INT
                            );

                            $sessionTitle = trim($_POST["session_title"] ?? "");
                            $sessionDateRaw = trim($_POST["session_date"] ?? "");
                            $startTime = trim($_POST["start_time"] ?? "");
                            $endTime = trim($_POST["end_time"] ?? "");
                            $sessionType = $_POST["session_type"] ?? "";
                            $meetingLink = trim($_POST["meeting_link"] ?? "");
                            $location = trim($_POST["location"] ?? "");
                            $sessionDescription = trim($_POST["session_description"] ?? "");

                            $date = DateTime::createFromFormat("!Y-m-d", $sessionDateRaw);
                            $dateErrors = DateTime::getLastErrors();

                            $validDate = $date &&
                                ($dateErrors === false ||
                                ($dateErrors["warning_count"] === 0 &&
                                 $dateErrors["error_count"] === 0)) &&
                                $date->format("Y-m-d") === $sessionDateRaw;

                            $validStartTime = preg_match(
                                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                                $startTime
                            );

                            $validEndTime = preg_match(
                                '/^(?:[01]\d|2[0-3]):[0-5]\d$/',
                                $endTime
                            );

                            $validMeetingLink = false;

                            if (filter_var($meetingLink, FILTER_VALIDATE_URL)) {
                                $scheme = strtolower(
                                    parse_url($meetingLink, PHP_URL_SCHEME) ?? ""
                                );

                                $validMeetingLink = in_array(
                                    $scheme,
                                    ["http", "https"],
                                        true
                                );
                            }

                            if (!$groupId || $groupId <= 0) {
                                $message = "Invalid study group.";

                            } elseif ($sessionTitle === "" ||
                                mb_strlen($sessionTitle) > 150) {
                                $message = "Please enter a valid session title.";

                            } elseif (!$validDate || !$validStartTime || !$validEndTime) {
                                $message = "Please enter a valid date and time.";

                            } elseif (
                                $sessionDateRaw < date("Y-m-d") ||
                                ($sessionDateRaw === date("Y-m-d") &&
                                 $startTime <= date("H:i"))
                            ) {
                                $message = "Please select a future session date and time.";

                            } elseif ($endTime <= $startTime) {
                                $message = "End time must be later than start time.";

                            } elseif (!in_array($sessionType, ["online", "physical"], true)) {
                                $message = "Please select a valid session type.";

                            } elseif ($sessionType === "online" && !$validMeetingLink) {
                                $message = "Please enter a valid online meeting link.";

                            } elseif ($sessionType === "physical" &&
                                ($location === "" || mb_strlen($location) > 255)) {
                                $message = "Please enter a valid physical session location.";

                            } elseif (mb_strlen($sessionDescription) > 5000) {
                                $message = "Session description must not exceed 5000 characters.";

                            } else {

                                $memberStmt = $conn->prepare(
                                    "SELECT group_id
                                     FROM group_members
                                     WHERE group_id = ? AND user_id = ?"
                                );

                                if (!$memberStmt) {
                                    $message = "Unable to verify group membership.";

                                    } else {
                                    $memberStmt->bind_param("ii", $groupId, $userId);
                                    $memberStmt->execute();

                                    $isMember = $memberStmt->get_result()->num_rows > 0;
                                    $memberStmt->close();

                                    if (!$isMember) {
                                        $message = "You must join this group before creating a session.";

                                    } else {

                                        $insertStmt = $conn->prepare(
                                            "INSERT INTO group_sessions
                                            (
                                                group_id,
                                                session_title,
                                                session_description,
                                                session_date,
                                                start_time,
                                                end_time,
                                                session_type,
                                                meeting_link,
                                                location,
                                                created_by
                                            )
                                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
                                        );

                                        if ($insertStmt) {

                                            $insertStmt->bind_param(
                                                "issssssssi",
                                                $groupId,
                                                $sessionTitle,
                                                $sessionDescription,
                                                $sessionDateRaw,
                                                $startTime,
                                                $endTime,
                                                $sessionType,
                                                $meetingLink,
                                                $location,
                                                $userId
                                            );

                                            if ($insertStmt->execute()) {
                                                $insertStmt->close();

                                                header("Location: study_groups.php?session_created=1");
                                                exit;
            
                                            } else {
                                                $message = "Unable to create the study session.";
                                                $insertStmt->close();
                                            }

                                        } else {
                                            $message = "Unable to prepare the session creation request.";
                                        }
                                    }
                                }
                            }
                        }
        
        }
}

if (isset($_GET["created"]) && $_GET["created"] === "1") {
    $message = "Study group created successfully!";
}

if (isset($_GET["session_created"]) && $_GET["session_created"] === "1") {
    $message = "Study session created successfully!";
}

$subjects = $conn->query(
    "SELECT subject_id, subject_name
     FROM subjects
     ORDER BY subject_name"
);

$groups = $conn->query(
    "SELECT g.group_id, g.group_name, g.description, g.created_at,
            u.name AS creator_name, s.subject_name,
            (SELECT COUNT(*)
             FROM group_members gm
             WHERE gm.group_id = g.group_id) AS member_count,
            (SELECT COUNT(*)
             FROM group_members gm
             WHERE gm.group_id = g.group_id
               AND gm.user_id = " . (int)($_SESSION["user_id"] ?? 0) . "
            ) AS is_member
     FROM study_groups g
     LEFT JOIN users u ON u.user_id = g.creator_id
     LEFT JOIN subjects s ON s.subject_id = g.subject_id
     ORDER BY g.created_at DESC"
);

$mySessions = false;

if ($isStudent) {
    $userId = (int)$_SESSION["user_id"];

    $sessionStmt = $conn->prepare(
        "SELECT            
            ss.session_id,
            ss.group_id,
            ss.created_by,
            ss.session_title,
            ss.session_description,
            ss.session_date,
            ss.start_time,
            ss.end_time,
            ss.session_type,
            ss.meeting_link,
            ss.location,
            g.group_name,
            u.name AS creator_name
         FROM group_sessions ss
         INNER JOIN study_groups g
             ON g.group_id = ss.group_id
         INNER JOIN group_members gm
             ON gm.group_id = g.group_id
         LEFT JOIN users u
             ON u.user_id = ss.created_by
         WHERE gm.user_id = ?
         ORDER BY ss.session_date ASC, ss.start_time ASC"
    );

    if ($sessionStmt) {
        $sessionStmt->bind_param("i", $userId);
        $sessionStmt->execute();
        $mySessions = $sessionStmt->get_result();
        $sessionStmt->close();
    }
}

$editSession = null;

if (
    $isStudent &&
    isset($_GET["edit_session"])
) {
    $editSessionId = filter_var(
        $_GET["edit_session"],
        FILTER_VALIDATE_INT
    );

    if ($editSessionId && $editSessionId > 0) {

        $editStmt = $conn->prepare(
            "SELECT
                session_id,
                session_title,
                session_description,
                session_date,
                start_time,
                end_time,
                session_type,
                meeting_link,
                location
             FROM group_sessions
             WHERE session_id = ? AND created_by = ?"
        );

        if ($editStmt) {
            $editStmt->bind_param(
                "ii",
                $editSessionId,
                $userId
            );

            $editStmt->execute();

            $editSession = $editStmt->get_result()->fetch_assoc();

            $editStmt->close();
        }
    }

    if (!$editSession) {
        $message = "You cannot edit this session, or it no longer exists.";
        $messageType = "error";
    }
}

?>

<html>

<head>
    <title>Study Groups | NSBM StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <style>
    
    * { 
        box-sizing: border-box; 
    }

    body { 
        margin: 0; 
        font-family: Arial, sans-serif; 
        background: #F3F6F5; color: #263445;
    }

    .hero {
        background: linear-gradient(#106638, #16834A, #106638);
        color: white;
        padding: 55px 7%;
        }

    .hero p {
        margin-top: 10px;
        max-width: 650px;
        color: #F0F5FF;
    }

    .hero h1 {
        font-size: 34px;
        margin-bottom: 5px;
    }

    .container { 
        width: 90%; 
        max-width: 1000px; 
        margin: 30px auto; 
    }
    
    .panel, .group { 
        background: white; 
        padding: 24px; 
        border-radius: 12px; 
        margin-bottom: 22px; 
        box-shadow: 0 3px 12px #0000000A; 
    }

    h2 { 
        color: #106638; 
        margin-top: 0; 
    }

    label { 
        display: block; 
        font-weight: bold; 
        margin: 14px 0 7px; 
    }

    input, textarea, select { 
        width: 100%; 
        padding: 12px; 
        border: 1px solid #CBD5D1; 
        border-radius: 7px; 
        font: inherit; 
    }

    textarea { 
        min-height: 90px; 
    }

    button, .button { 
        display: inline-block; 
        margin-top: 14px; 
        padding: 11px 18px; 
        background: #16834A; 
        color: white; 
        border: 0; 
        border-radius: 7px; 
        text-decoration: none; 
        font-weight: bold; 
        cursor: pointer; 
    }

    button:hover, .button:hover { 
        background: #106638; 
    }

    .meta { 
        color: #657386; 
        font-size: 14px; 
        margin: 10px 0; 
    }

    .notice { 
        padding: 13px; 
        background: #E8F5EC; 
        border-radius: 7px; 
        margin-bottom: 18px; 
    }

    .notice.error { 
        background: #FCE8E6; 
        color: #8A1C13; 
    }
    
    @media(max-width:600px) { 
        .container { 
            width: 94%; 
        } 
        .panel, .group { 
            padding: 17px; 
        } 
        nav a { 
            display: inline-block; 
            margin: 5px 12px 5px 0; 
        } 
    }

    .group {
        border: 1px solid #DCE5DF;
        transition: transform 0.2s ease, box-shadow 0.2s ease;
    }

    .group:hover {
        transform: translateY(-3px);
        box-shadow: 0 6px 18px rgba(28, 57, 41, 0.10);
    }

    .group h3 {
        color: #174A8B;
        margin-top: 0;
    }

    hr {
        border: 0;
        border-top: 1px solid #DCE5DF;
    }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>

<section class="hero">

    <h1>Study Groups</h1>

    <p>
        Welcome to the NSBM StudyCircle Study Groups! This is a place where 
        students can create or join groups to study together and collaborate on academic projects.
    </p>

</section>

<main class="container">

    <?php if ($editSession): ?>

        <section class="panel sc-card">

            <h2>Edit Study Session</h2>

            <p>Update your study session details below.</p>

            <form method="POST" action="study_groups.php">

                <input type="hidden" name="csrf_token"
                       value="<?= h($_SESSION["groups_csrf"]) ?>">

                <input type="hidden" name="action"
                       value="edit_session">

                <input type="hidden" name="session_id"
                       value="<?= (int)$editSession["session_id"] ?>">

                <label for="edit_session_title">Session Title</label>

                <input
                    type="text"
                    id="edit_session_title"
                    name="session_title"
                    maxlength="150"
                    value="<?= h($editSession["session_title"]) ?>"
                    required
                >

                <label for="edit_session_date">Session Date</label>

                <input
                    type="date"
                    id="edit_session_date"
                    name="session_date"
                    min="<?= date('Y-m-d') ?>"
                    value="<?= h($editSession["session_date"]) ?>"
                    required
                >

                <label for="edit_start_time">Start Time</label>

                <input
                    type="time"
                    id="edit_start_time"
                    name="start_time"
                    value="<?= h(substr($editSession["start_time"], 0, 5)) ?>"
                    required
                >

                <label for="edit_end_time">End Time</label>

                <input
                    type="time"
                    id="edit_end_time"
                    name="end_time"
                    value="<?= h(substr($editSession["end_time"], 0, 5)) ?>"
                    required
                >

                <label for="edit_session_type">Session Type</label>

                <select
                    id="edit_session_type"
                    name="session_type"
                    required
                >
                    <option value="online"
                        <?= $editSession["session_type"] === "online" ? "selected" : "" ?>>
                        Online
                    </option>

                    <option value="physical"
                        <?= $editSession["session_type"] === "physical" ? "selected" : "" ?>>
                        Physical
                    </option>
                </select>

                <label for="edit_meeting_link">Online Meeting Link</label>

                <input
                    type="url"
                    id="edit_meeting_link"
                    name="meeting_link"
                    maxlength="500"
                    value="<?= h($editSession["meeting_link"] ?? "") ?>"
                    placeholder="https://meet.google.com/..."
                >

                <label for="edit_location">Physical Location</label>

                <input
                    type="text"
                    id="edit_location"
                    name="location"
                    maxlength="255"
                    value="<?= h($editSession["location"] ?? "") ?>"
                    placeholder="Example: NSBM Library"
                >

                <label for="edit_session_description">
                    Session Description
                </label>

                <textarea
                    id="edit_session_description"
                    name="session_description"
                    maxlength="5000"
                ><?= h($editSession["session_description"] ?? "") ?></textarea>

                <button type="submit">Save Changes</button>

                <a class="button"
                   href="study_groups.php"
                   style="background: #657386;">
                    Cancel
                </a>

            </form>

        </section>

    <?php endif; ?>


    <section class="panel sc-card">

        <h2>Study Groups</h2>

        <p>Create a group for your subject or join a group created by another student.</p>

        <?php if ($message !== ""): ?>

            <div class="notice <?= $messageType === "error" ? "error" : "" ?>">
                <?= h($message) ?>
            </div>

        <?php endif; ?>

        <?php if ($isStudent): ?>

        <h2>Create a Study Group</h2>

        <form method="POST" action="study_groups.php">

            <input type="hidden" name="csrf_token" value="<?= h($_SESSION["groups_csrf"]) ?>">

            <input type="hidden" name="action" value="create">

            <label for="group_name">Group Name</label>

            <input id="group_name" name="group_name" maxlength="150" required
                   placeholder="Example: Programming Study Group">

            <label for="subject_id">Subject (optional)</label>

            <select id="subject_id" name="subject_id">

                <option value="">General Study Group</option>

                <?php if ($subjects): ?>

                    <?php while ($subject = $subjects->fetch_assoc()): ?>

                        <option value="<?= (int)$subject["subject_id"] ?>">
                            <?= h($subject["subject_name"]) ?>
                        </option>

                    <?php endwhile; ?>

                <?php endif; ?>

            </select>

            <label for="description">Description</label>

            <textarea id="description" name="description"
                      placeholder="What will your group study?"></textarea>

            <button type="submit">Create Group</button>

        </form>

        <hr style="margin: 30px 0;">

        <h2>Request a New Subject</h2>

        <p>
            Can't find your subject in the dropdown?
            Submit a request for admin approval.
        </p>

        <form method="POST" action="study_groups.php">

            <input type="hidden" name="csrf_token"
                   value="<?= h($_SESSION["groups_csrf"]) ?>">

            <input type="hidden" name="action"
                   value="request_subject">

            <label for="request_subject_name">Subject Name</label>

            <input
                type="text"
                id="request_subject_name"
                name="subject_name"
                maxlength="100"
                placeholder="Example: Artificial Intelligence"
                required
            >

            <label for="request_category">Category</label>

            <input
                type="text"
                id="request_category"
                name="category"
                maxlength="100"
                placeholder="Example: Computer Science"
                required
            >

            <button type="submit">Submit Subject Request</button>

        </form>
        
        <?php else: ?>

            <p>Please log in as a student to create or join a study group.</p>
            <a class="button" href="login.php">Student Login</a>

        <?php endif; ?>

    </section>

    <section class="panel sc-card">

        <h2>Available Study Groups</h2>

        <?php if (!$groups): ?>

            <p>Could not load study groups. Please check the database configuration.</p>

        <?php elseif ($groups->num_rows === 0): ?>

            <p>No study groups yet. Log in and create the first one!</p>

        <?php else: ?>

            <?php while ($group = $groups->fetch_assoc()): ?>

            <article class="group">

                <h3><?= h($group["group_name"]) ?></h3>

                <p><?= nl2br(h($group["description"] ?? "")) ?></p>

                <div class="meta">
                    Members: <?= (int)$group["member_count"] ?>
                </div>

                <?php if ($isStudent): ?>
                    
                    <?php if ((int)$group["is_member"] === 1): ?>

                        <p><strong>You are a member of this group.</strong></p>

                        <hr style="margin: 20px 0;">
                        
                        <h4>Create a Study Session</h4>

                        <form method="POST" action="study_groups.php">

                            <input type="hidden" name="csrf_token"
                                value="<?= h($_SESSION["groups_csrf"]) ?>">

                            <input type="hidden" name="action"
                                value="create_session">

                            <input type="hidden" name="group_id"
                                value="<?= (int)$group["group_id"] ?>">

                            <label for="session_title_<?= (int)$group["group_id"] ?>">
                                Session Title
                            </label>

                            <input
                                type="text"
                                id="session_title_<?= (int)$group["group_id"] ?>"
                                name="session_title"
                                maxlength="150"
                                placeholder="Example: Java Programming Study Session"
                                required
                            >

                            <label for="session_date_<?= (int)$group["group_id"] ?>">
                                Session Date
                            </label>

                            <input
                                type="date"
                                id="session_date_<?= (int)$group["group_id"] ?>"
                                name="session_date"
                                min="<?= date('Y-m-d') ?>"
                                required
                            >

                            <label for="start_time_<?= (int)$group["group_id"] ?>">
                                Start Time
                            </label>

                            <input
                                type="time"
                                id="start_time_<?= (int)$group["group_id"] ?>"
                                name="start_time"
                                required
                            >

                            <label for="end_time_<?= (int)$group["group_id"] ?>">
                                End Time
                            </label>

                            <input
                                type="time"
                                id="end_time_<?= (int)$group["group_id"] ?>"
                                name="end_time"
                                required
                            >

                            <label for="session_type_<?= (int)$group["group_id"] ?>">
                                Session Type
                            </label>

                            <select
                                id="session_type_<?= (int)$group["group_id"] ?>"
                                name="session_type"
                                required
                            >
                                <option value="">Select Session Type</option>
                                <option value="online">Online</option>
                                <option value="physical">Physical</option>
                            </select>

                            <label for="meeting_link_<?= (int)$group["group_id"] ?>">
                                Online Meeting Link (Required for online sessions)
                            </label>

                            <input
                                type="url"
                                id="meeting_link_<?= (int)$group["group_id"] ?>"
                                name="meeting_link"
                                maxlength="500"
                                placeholder="https://meet.google.com/..."
                            >

                            <label for="location_<?= (int)$group["group_id"] ?>">
                                Physical Location (Required for physical sessions)
                            </label>

                            <input
                                type="text"
                                id="location_<?= (int)$group["group_id"] ?>"
                                name="location"
                                maxlength="255"
                                placeholder="Example: NSBM Library"
                            >

                            <label for="session_description_<?= (int)$group["group_id"] ?>">
                                Session Description
                            </label>

                            <textarea
                                id="session_description_<?= (int)$group["group_id"] ?>"
                                name="session_description"
                                maxlength="5000"
                                placeholder="Describe what you plan to study."
                            ></textarea>

                            <button type="submit">Create Study Session</button>

                        </form>


                        <?php else: ?>
                        
                        <form method="POST" action="study_groups.php">
                            <input type="hidden" name="csrf_token" value="<?= h($_SESSION["groups_csrf"]) ?>">
                            <input type="hidden" name="action" value="join">
                            <input type="hidden" name="group_id" value="<?= (int)$group["group_id"] ?>">
                            <button type="submit">Join Group</button>
                        </form>

                    <?php endif; ?>

                <?php endif; ?>

            </article>

            <?php endwhile; ?>

        <?php endif; ?>

    </section>

        <section class="panel sc-card">

        <h2>My Study Sessions</h2>

        <p>Study sessions created in groups you have joined.</p>

        <?php if (!$isStudent): ?>

            <p>Please log in as a student to view study sessions.</p>

        <?php elseif (!$mySessions || $mySessions->num_rows === 0): ?>

            <p>No study sessions have been created for your groups yet.</p>

        <?php else: ?>

            <?php while ($session = $mySessions->fetch_assoc()): ?>

                <article class="group">

                    <h3><?= h($session["group_name"]) ?></h3>

                    <h4><?= h($session["session_title"]) ?></h4>

                    <div class="meta">
                        <strong>Date:</strong>
                        <?= date("d M Y", strtotime($session["session_date"])) ?>
                    </div>

                    <div class="meta">
                        <strong>Time:</strong>
                        <?= date("h:i A", strtotime($session["start_time"])) ?>
        -
                        <?= date("h:i A", strtotime($session["end_time"])) ?>
                    </div>

                    <p>
                        <strong>Session Type:</strong>
                        <?= h(ucfirst($session["session_type"])) ?>
                    </p>

                    <?php if ($session["session_type"] === "online"): ?>

                        <?php if (!empty($session["meeting_link"])): ?>
                            <p>
                                <strong>Meeting Link:</strong>
                                <a
                                    href="<?= h($session["meeting_link"]) ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                >
                                    Join Online Meeting
                                </a>
                            </p>
                        <?php endif; ?>

                    <?php elseif ($session["session_type"] === "physical"): ?>

                        <?php if (!empty($session["location"])): ?>
                            <p>
                                <strong>Location:</strong>
                                <?= h($session["location"]) ?>
                            </p>
                        <?php endif; ?>

                    <?php endif; ?>

                    <?php if (!empty($session["session_description"])): ?>
                        <p>
                            <?= nl2br(h($session["session_description"])) ?>
                        </p>
                    <?php endif; ?>

                    <div class="meta">
                        Created by <?= h($session["creator_name"] ?? "Student") ?>
                    </div>

                    <?php if ((int)$session["created_by"] === (int)$_SESSION["user_id"]): ?>

                <div class="session-actions">

                    <a
                        class="button"
                        href="study_groups.php?edit_session=<?= (int)$session["session_id"] ?>"
                    >
                        Edit Session
                    </a>

                    <form
                        method="POST"
                        action="study_groups.php"
                        onsubmit="return confirm('Are you sure you want to delete this study session?');"
                        style="display: inline-block;"
                    >
                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= h($_SESSION["groups_csrf"]) ?>"
                        >

                        <input type="hidden" name="action" value="delete_session">

                        <input
                            type="hidden"
                            name="session_id"
                            value="<?= (int)$session["session_id"] ?>"
                        >

                        <button type="submit" style="background: #C0392B;">
                            Delete Session
                        </button>
                    </form>

                </div>

                <?php endif; ?>

                </article>

            <?php endwhile; ?>

        <?php endif; ?>

    </section>
    
</main>

<?php include "includes/footer.php"; ?>

</body>
</html>