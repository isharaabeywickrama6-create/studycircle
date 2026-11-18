<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . "/config/database.php";

if (
    !isset($_SESSION["user_id"]) ||
    !isset($_SESSION["role"]) ||
    $_SESSION["role"] !== "student"
) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION["user_id"];

$errors = [];
$title = "";
$description = "";
$subjectId = "";
$newSubjectName = "";

if (
    !isset($_SESSION["upload_csrf"]) ||
    !is_string($_SESSION["upload_csrf"])
) {
    $_SESSION["upload_csrf"] = bin2hex(random_bytes(32));
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $titleInput = $_POST["title"] ?? "";
    $descriptionInput = $_POST["description"] ?? "";
    $subjectInput = $_POST["subject_id"] ?? "";
    $newSubjectInput = $_POST["new_subject_name"] ?? "";

    if (
        !is_string($titleInput) ||
        !is_string($descriptionInput) ||
        !is_string($subjectInput) ||
        !is_string($newSubjectInput)
    ) {
        $errors[] = "Invalid form data. Please try again.";
    } else {
        $title = trim($titleInput);
        $description = trim($descriptionInput);
        $subjectId = trim($subjectInput);
        $newSubjectName = trim($newSubjectInput);
    }

    $submittedToken = $_POST["csrf_token"] ?? null;

    if (
        !is_string($submittedToken) ||
        !isset($_SESSION["upload_csrf"]) ||
        !hash_equals($_SESSION["upload_csrf"], $submittedToken)
    ) {
        $errors[] = "Invalid form request. Please refresh the page.";
    }

    if ($title === "") {
        $errors[] = "Please enter a material title.";
    } elseif (strlen($title) > 200) {
        $errors[] = "The title must not exceed 200 characters.";
    }

    if (strlen($description) > 5000) {
        $errors[] = "The description must not exceed 5000 characters.";
    }

    if ($subjectId === "new") {

        if ($newSubjectName === "") {
            $errors[] = "Please enter the new subject name.";
        } elseif (strlen($newSubjectName) > 100) {
            $errors[] = "The subject name must not exceed 100 characters.";
        }

    } elseif (
        $subjectId === "" ||
        !ctype_digit($subjectId) ||
        (int) $subjectId <= 0
    ) {
        $errors[] = "Please select a subject or add a new one.";

    } else {

        $subjectCheck = $conn->prepare(
            "SELECT subject_id
             FROM subjects
             WHERE subject_id = ?
             LIMIT 1"
        );

        if (!$subjectCheck) {
            error_log("Subject validation preparation failed: " . $conn->error);
            $errors[] = "Unable to validate the selected subject.";

        } else {

            $selectedSubjectId = (int) $subjectId;

            $subjectCheck->bind_param("i", $selectedSubjectId);

            if (!$subjectCheck->execute()) {
                error_log("Subject validation failed: " . $subjectCheck->error);
                $errors[] = "Unable to validate the selected subject.";

            } else {

                $subjectResult = $subjectCheck->get_result();

                if ($subjectResult->num_rows === 0) {
                    $errors[] = "Please select a valid subject.";
                }
            }

            $subjectCheck->close();
        }
    }

    $file = null;

    if (
        !isset($_FILES["material_file"]) ||
        !is_array($_FILES["material_file"]) ||
        !isset(
            $_FILES["material_file"]["error"],
            $_FILES["material_file"]["name"],
            $_FILES["material_file"]["tmp_name"],
            $_FILES["material_file"]["size"]
        )
    ) {
        $errors[] = "Please select a PDF file to upload.";

    } else {

        $file = $_FILES["material_file"];

        if ($file["error"] !== UPLOAD_ERR_OK) {

            $errors[] = "Please select a PDF file to upload.";

        } elseif (
            !is_uploaded_file($file["tmp_name"]) ||
            !is_file($file["tmp_name"])
        ) {

            $errors[] = "Invalid file upload.";

        } else {

            if ($file["size"] > 10 * 1024 * 1024 || $file["size"] <= 0) {
                $errors[] = "The PDF must be between 1 byte and 10 MB.";
            }

            $extension = strtolower(
                pathinfo($file["name"], PATHINFO_EXTENSION)
            );

            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file["tmp_name"]);

            if (
                $extension !== "pdf" ||
                $mimeType !== "application/pdf"
            ) {
                $errors[] = "Only valid PDF files are accepted.";
            }

            $fileHandle = fopen($file["tmp_name"], "rb");

            if ($fileHandle === false) {

                $errors[] = "Unable to read the uploaded file.";

            } else {

                $fileSignature = fread($fileHandle, 5);
                fclose($fileHandle);

                if ($fileSignature !== "%PDF-") {
                    $errors[] = "The uploaded file is not a valid PDF.";
                }
            }
        }
    }

    if (empty($errors)) {

        $uploadDirectory = __DIR__
            . DIRECTORY_SEPARATOR . "uploads"
            . DIRECTORY_SEPARATOR . "materials";

        if (!is_dir($uploadDirectory) || !is_writable($uploadDirectory)) {

            $errors[] = "The materials upload folder is unavailable.";

        } else {

            $newFileName = bin2hex(random_bytes(16)) . ".pdf";

            $destination = $uploadDirectory
                . DIRECTORY_SEPARATOR . $newFileName;

            $relativePath = "uploads/materials/" . $newFileName;

            if (!move_uploaded_file(
                $file["tmp_name"],
                $destination
            )) {

                $errors[] = "The file could not be saved. Please try again.";

            } else {

                $transactionStarted = false;

                try {

                    if (!$conn->begin_transaction()) {
                        throw new RuntimeException("Unable to start transaction.");
                    }

                    $transactionStarted = true;

                    if ($subjectId === "new") {

                        $findSubject = $conn->prepare(
                            "SELECT subject_id
                             FROM subjects
                             WHERE LOWER(TRIM(subject_name)) =
                                   LOWER(TRIM(?))
                             LIMIT 1"
                        );

                        if (!$findSubject) {
                            throw new RuntimeException(
                                "Unable to prepare subject search."
                            );
                        }

                        $findSubject->bind_param("s", $newSubjectName);

                        if (!$findSubject->execute()) {
                            throw new RuntimeException(
                                "Unable to search for the subject."
                            );
                        }

                        $existingSubject = $findSubject
                            ->get_result()
                            ->fetch_assoc();

                        $findSubject->close();

                        if ($existingSubject) {

                            $selectedSubjectId =
                                (int) $existingSubject["subject_id"];

                        } else {

                            $addSubject = $conn->prepare(
                                "INSERT INTO subjects
                                 (subject_name, category)
                                 VALUES (?, 'General')"
                            );

                            if (!$addSubject) {
                                throw new RuntimeException(
                                    "Unable to prepare subject insertion."
                                );
                            }

                            $addSubject->bind_param("s", $newSubjectName);

                            if (!$addSubject->execute()) {
                                throw new RuntimeException(
                                    "Unable to save the new subject."
                                );
                            }

                            $selectedSubjectId = (int) $conn->insert_id;

                            $addSubject->close();
                        }

                    } else {

                        $selectedSubjectId = (int) $subjectId;
                    }

                    $stmt = $conn->prepare(
                        "INSERT INTO materials
                         (
                            user_id,
                            subject_id,
                            title,
                            description,
                            file_path,
                            status,
                            created_at
                         )
                         VALUES (?, ?, ?, ?, ?, 'pending', NOW())"
                    );

                    if (!$stmt) {
                        throw new RuntimeException(
                            "Unable to prepare material insertion."
                        );
                    }

                    $stmt->bind_param(
                        "iisss",
                        $userId,
                        $selectedSubjectId,
                        $title,
                        $description,
                        $relativePath
                    );

                    if (!$stmt->execute()) {
                        throw new RuntimeException(
                            "Unable to save the material."
                        );
                    }

                    $stmt->close();

                    if (!$conn->commit()) {
                        throw new RuntimeException(
                            "Unable to complete the database transaction."
                        );
                    }

                    $transactionStarted = false;

                    $_SESSION["upload_csrf"] = bin2hex(random_bytes(32));

                    header("Location: upload_material.php?success=1");
                    exit;

                } catch (Throwable $e) {

                    if ($transactionStarted) {
                        try {
                            $conn->rollback();
                        } catch (Throwable $rollbackError) {
                            error_log(
                                "Upload rollback failed: " .
                                $rollbackError->getMessage()
                            );
                        }
                    }

                    if (is_file($destination)) {
                        unlink($destination);
                    }

                    error_log(
                        "Material upload failed: " . $e->getMessage()
                    );

                    $errors[] =
                        "The material could not be saved. Please try again.";
                }
            }
        }
    }
}

$subjects = $conn->query(
    "SELECT subject_id, subject_name
     FROM subjects
     ORDER BY subject_name"
);

if (!$subjects) {
    error_log("Subject loading failed: " . $conn->error);
    $errors[] = "Unable to load subjects. Please refresh the page.";
}

$success = isset($_GET["success"]) && $_GET["success"] === "1";

?>

<html>

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Upload Study Material | StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            background-color: #F3F6F5;
            background-image:
                linear-gradient(rgba(243, 246, 245, 0.5), rgba(243, 246, 245, 0.5)),
                url("assets/studycircle1.jpg");
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
        }

        .hero {
            position: relative;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: center;
            text-justify: center;
            padding: 55px max(7%, calc((100% - 1150px) / 2));
            color: white;
            background: linear-gradient(120deg, #106638, #16834A);
        }

        .hero::after {
            content: "";
            position: absolute;
            width: 260px;
            height: 260px;
            right: 8%;
            top: -100px;
            border: 35px solid rgba(255,255,255,0.07);
            border-radius: 50%;
            pointer-events: none;
        }

        .hero h1 {
            width: 100%;
            max-width: 800px;
            margin: 0 auto 12px;

            color: #FFFFFF;
            font-weight: 600;
            line-height: 1.3;
            letter-spacing: -0.5px;
            text-align: center;
        }

        .hero p {
            max-width: 680px;
            margin: 0;
            color: #E8F5EC;
            font-size: 16px;
            text-justify: center;
        }

        .form-card1 {
            max-width: 1200px;
            width: 92%;
            margin: 38px auto 55px;
            padding: 28px;
            margin-bottom: 1px;

            background: #FFFFFF;
            border: 1px solid #DCE5DF;
            border-radius: 16px;

            box-shadow: 0 5px 18px rgba(38,52,69,0.055);
        }

        .form-card1 h2 {
            margin-top: 0;
            margin-bottom: 12px;

            color: #106638;
            font-size: 22px;
            font-weight: 800;
        }

        .form-card2 {
            max-width: 1200px;
            width: 92%;
            margin: 38px auto 55px;
            padding: 28px;
            margin-bottom: 26px;
            margin-top: 10px;

            background: #FFFFFF;
            border: 1px solid #DCE5DF;
            border-radius: 16px;

            box-shadow: 0 5px 18px rgba(38,52,69,0.055);
        }

        .form-card2 h2 {
            margin-top: 0;
            margin-bottom: 12px;

            color: #106638;
            font-size: 22px;
            font-weight: 800;
        }

        .page-intro h2 {
            margin: 0 0 5px;
            color: #174A8B;
            font-size: 23px;
        }

        .page-intro p {
            margin: 0;
            color: #657386;
            font-size: 14px;
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 13px;
            padding: 17px 19px;
            margin-bottom: 23px;
            background: #E8F5EC;
            color: #106638;
            border: 1px solid #CDE7D5;
            border-radius: 10px;
            font-size: 14px;
        }

        .notice-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            width: 30px;
            height: 30px;
            background: #FFFFFF;
            border-radius: 50%;
            font-size: 16px;
        }

        .notice strong {
            display: block;
            margin-bottom: 3px;
        }

        .form-card {
            padding: 32px;
            background: #FFFFFF;
            border: 1px solid #DCE5DF;
            border-radius: 14px;
            box-shadow: 0 5px 20px rgba(38,52,69,0.06);
        }

        .form-card-header {
            padding-bottom: 20px;
            margin-bottom: 25px;
            border-bottom: 1px solid #E8EEEA;
        }

        .form-card-header h2 {
            margin: 0 0 5px;
            color: #106638;
            font-size: 21px;
        }

        .form-card-header p {
            margin: 0;
            color: #657386;
            font-size: 14px;
        }

        .form-group {
            margin-bottom: 23px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #263445;
            font-size: 14px;
            font-weight: 700;
        }

        .required {
            color: #D13B45;
        }

        input[type="text"],
        textarea,
        select {
            display: block;
            width: 100%;
            padding: 13px 14px;
            color: #263445;
            background: #FFFFFF;
            border: 1px solid #DCE5DF;
            border-radius: 8px;
            font-family: Arial, sans-serif;
            font-size: 14px;
            transition: border-color 0.2s ease, box-shadow 0.2s ease;
        }

        input[type="text"]::placeholder,
        textarea::placeholder {
            color: #9AA6B2;
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #16834A;
            box-shadow: 0 0 0 3px rgba(22,131,74,0.10);
        }

        textarea {
            min-height: 135px;
            resize: vertical;
        }

        select {
            cursor: pointer;
        }

        .hint {
            margin: 7px 0 0;
            color: #657386;
            font-size: 12px;
        }

        #new-subject-box {
            padding: 17px;
            margin-top: 14px;
            background: #F3F8F4;
            border: 1px solid #DCE5DF;
            border-radius: 9px;
        }

        .file-upload-area {
            padding: 24px;
            background: #F8FBF9;
            border: 2px dashed #B8D5C1;
            border-radius: 10px;
            text-align: center;
            transition: background 0.2s ease, border-color 0.2s ease;
        }

        .file-upload-area:hover {
            background: #F0F8F2;
            border-color: #16834A;
        }

        .upload-icon {
            display: block;
            margin-bottom: 9px;
            color: #16834A;
            font-size: 32px;
        }

        .file-upload-area p {
            margin: 0 0 12px;
            color: #657386;
            font-size: 13px;
        }

        input[type="file"] {
            display: block;
            width: 100%;
            padding: 10px;
            margin-top: 10px;
            color: #263445;
            background: #FFFFFF;
            border: 1px solid #DCE5DF;
            border-radius: 8px;
            font-size: 13px;
            cursor: pointer;
        }

        input[type="file"]::file-selector-button {
            padding: 9px 14px;
            margin-right: 12px;
            color: #FFFFFF;
            background: #16834A;
            border: none;
            border-radius: 6px;
            font-size: 13px;
            font-weight: 700;
            cursor: pointer;
        }

        input[type="file"]::file-selector-button:hover {
            background: #106638;
        }

        input[type="file"]:focus-visible {
            outline: 2px solid #174A8B;
            outline-offset: 3px;
        }

        .file-info {
            display: none;
            padding: 10px 13px;
            margin-top: 12px;
            color: #106638;
            background: #E8F5EC;
            border-radius: 7px;
            font-size: 13px;
            text-align: left;
            overflow-wrap: anywhere;
        }

        .submit-button {
            display: block;
            width: 100%;
            padding: 14px 20px;
            margin-top: 27px;
            color: #FFFFFF;
            background: #16834A;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            cursor: pointer;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .submit-button:hover {
            background: #106638;
            transform: translateY(-1px);
        }

        .submit-button:focus-visible {
            outline: 3px solid rgba(22,131,74,0.25);
            outline-offset: 3px;
        }

        .alert {
            padding: 15px 18px;
            margin-bottom: 20px;
            border-radius: 9px;
            font-size: 14px;
        }

        .alert ul {
            padding-left: 22px;
            margin: 8px 0 0;
        }

        .alert li {
            margin-bottom: 4px;
        }

        .error {
            color: #9B2226;
            background: #FDECEC;
            border: 1px solid #F5CACA;
        }

        .success {
            color: #106638;
            background: #E8F5EC;
            border: 1px solid #CDE7D5;
        }

        @media (max-width: 700px) {

            .hero {
                padding: 42px 6%;
            }

            .hero h1 {
                font-size: 30px;
            }

            .hero p {
                font-size: 14px;
            }

            .container {
                margin-top: 28px;
            }

            .form-card {
                padding: 23px;
            }

        }

        @media (max-width: 480px) {

            .hero {
                padding: 35px 5%;
            }

            .hero h1 {
                font-size: 30px;
            }

            .page-intro h2 {
                font-size: 20px;
            }

            .form-card {
                padding: 18px;
            }

            .file-upload-area {
                padding: 17px;
            }

        }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>

<section class="hero">

    <h1>Upload Study Materials</h1>

    <p>
        Share your knowledge with the StudyCircle community.
        Upload useful notes, lecture materials and academic resources
        to help fellow students learn together.
    </p>

</section>

<main class="container">

    <div class="form-card1">

        <h2>Share Your Knowledge</h2>

        <p>
            Complete the form below to share your academic resources
            with other students.
        </p>

    <?php if ($success): ?>

        <div class="alert success">
            Your material was submitted successfully and is waiting
            for admin approval.
        </div>

    <?php endif; ?>

    <?php if (!empty($errors)): ?>

        <div class="alert error">

            <strong>Please correct the following:</strong>

            <ul>
                <?php foreach ($errors as $error): ?>
                    <li>
                        <?= htmlspecialchars($error, ENT_QUOTES, "UTF-8") ?>
                    </li>
                <?php endforeach; ?>
            </ul>

        </div>

    <?php endif; ?>

    <div class="notice">

        <div class="notice-icon">ℹ</div>

        <div>
            <strong>Important upload information</strong>
            Your upload will not appear in the public materials list
            until an administrator approves it. Only PDF files are
            accepted, with a maximum file size of 10 MB.
        </div>

    </div>

    </div><br>

    <div class="form-card2">

        <div class="form-card-header">

            <h2>Material Details</h2>

            <p>
                Enter the information about the material you want to share.
            </p>

        </div>

        <form method="POST"
              action="upload_material.php"
              enctype="multipart/form-data">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($_SESSION["upload_csrf"], ENT_QUOTES, "UTF-8") ?>"
            >

            <div class="form-group">

                <label for="title">
                    Material Title <span class="required">*</span>
                </label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="200"
                    value="<?= htmlspecialchars($title, ENT_QUOTES, "UTF-8") ?>"
                    placeholder="e.g. Introduction to Programming Notes"
                    required
                >

                <p class="hint">
                    Give your material a clear and meaningful title.
                </p>

            </div>

            <div class="form-group">

                <label for="subject_id">
                    Subject <span class="required">*</span>
                </label>

                <select id="subject_id" name="subject_id" required>

                    <option value="">-- Select a subject --</option>

                    <?php if ($subjects): ?>

                        <?php while ($subject = $subjects->fetch_assoc()): ?>

                            <option
                                value="<?= (int) $subject["subject_id"] ?>"
                                <?= (string) $subjectId ===
                                    (string) $subject["subject_id"]
                                    ? "selected" : "" ?>
                            >
                                <?= htmlspecialchars(
                                    $subject["subject_name"],
                                    ENT_QUOTES,
                                    "UTF-8"
                                ) ?>
                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                    <option
                        value="new"
                        <?= $subjectId === "new" ? "selected" : "" ?>
                    >
                        + Add a new subject
                    </option>

                </select>

                <div
                    id="new-subject-box"
                    style="display: <?= $subjectId === "new" ? "block" : "none" ?>;"
                >

                    <label for="new_subject_name">
                        New Subject Name <span class="required">*</span>
                    </label>

                    <input
                        type="text"
                        id="new_subject_name"
                        name="new_subject_name"
                        maxlength="100"
                        value="<?= htmlspecialchars(
                            $newSubjectName,
                            ENT_QUOTES,
                            "UTF-8"
                        ) ?>"
                        placeholder="e.g. Object-Oriented Programming"
                    >

                    <p class="hint">
                        Add a subject only if it is not already in the list.
                    </p>

                </div>

            </div>

            <div class="form-group">

                <label for="description">
                    Description
                </label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="5000"
                    placeholder="Briefly describe the material and what students can learn from it..."
                ><?= htmlspecialchars($description, ENT_QUOTES, "UTF-8") ?></textarea>

                <p class="hint">
                    Explain what the material contains. This helps other
                    students understand its purpose.
                </p>

            </div>

            <div class="form-group">

                <label for="material_file">
                    Upload PDF File <span class="required">*</span>
                </label>

                <div class="file-upload-area">

                    <span class="upload-icon">📄</span>

                    <p>
                        Choose the PDF document you want to share.
                    </p>

                    <input
                        type="file"
                        id="material_file"
                        name="material_file"
                        accept=".pdf,application/pdf"
                        required
                    >

                    <div class="file-info" id="file-info"></div>

                    <p class="hint">
                        PDF format only. Maximum file size: 10 MB.
                    </p>

                </div>

            </div>

            <button type="submit" class="submit-button">
                Submit for Approval
            </button>

        </form>

    </div>

</main>

<?php include "includes/footer.php"; ?>

<script>

    const subjectSelect = document.getElementById("subject_id");
    const newSubjectBox = document.getElementById("new-subject-box");
    const newSubjectInput = document.getElementById("new_subject_name");

    function updateSubjectField() {

        const addingNew = subjectSelect.value === "new";

        newSubjectBox.style.display = addingNew ? "block" : "none";

        newSubjectInput.required = addingNew;
    }

    subjectSelect.addEventListener("change", updateSubjectField);

    updateSubjectField();

</script>

<script>

    const materialFileInput = document.getElementById("material_file");
    const fileInfo = document.getElementById("file-info");

    materialFileInput.addEventListener("change", function () {

        const file = this.files[0];

        if (!file) {
            fileInfo.style.display = "none";
            fileInfo.textContent = "";
            return;
        }

        const fileSize = (file.size / (1024 * 1024)).toFixed(2);

        fileInfo.textContent =
            "Selected file: " + file.name + " (" + fileSize + " MB)";

        fileInfo.style.display = "block";

    });

</script>

<?php if ($success): ?>

<script>

    Swal.fire({

        icon: "success",
        title: "Upload Successful!",
        text: "Your study material has been uploaded successfully and is waiting for admin approval.",
        confirmButtonText: "OK",
        confirmButtonColor: "#16834A"

    });

</script>

<?php endif; ?>

<?php if (!empty($errors)): ?>

<script>

    Swal.fire({

        icon: "error",
        title: "Oops!",
        html: <?= json_encode(
            implode("<br>", $errors),
            JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
        ) ?>,
        confirmButtonText: "Try Again",
        confirmButtonColor: "#174A8B"

    });

</script>

<?php endif; ?>

</body>
</html>