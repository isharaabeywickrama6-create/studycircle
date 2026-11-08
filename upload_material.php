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
    <title>Upload Study Material | StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <style>

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, sans-serif;
            background: #F3F6F5;
            color: #263445;
            line-height: 1.6;
        }

        .container {
            max-width: 760px;
            width: 90%;
            margin: 40px auto;
        }

        .heading {
            margin-bottom: 22px;
        }

        .heading h1 {
            margin-bottom: 6px;
            color: #174A8B;
        }

        .heading p {
            color: #657386;
        }

        .form-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 4px 18px #2634450D;
        }

        .form-group {
            margin-bottom: 20px;
        }

        input[type="file"] {
            display: block;
            width: 100%;
            padding: 12px;
            margin-top: 8px;
            background: #F3F6F5;
            border: 1px solid #CCD4D9;
            border-radius: 8px;
            color: #263445;
            font-size: 14px;
            cursor: pointer;
        }

        input[type="file"]::file-selector-button {
            background: #16834A;
            color: #FFFFFF;
            border: none;
            padding: 10px 16px;
            margin-right: 12px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: bold;
            cursor: pointer;
        }

        input[type="file"]::file-selector-button:hover {
            background: #106638;
        }

        input[type="file"]:focus-visible {
            outline: 2px solid #174A8B;
            outline-offset: 3px;
        }

        label {
            display: block;
            font-weight: bold;
            margin-bottom: 7px;
        }

        input[type="text"],
        textarea,
        select,
        input[type="file"] {
            width: 100%;
            padding: 12px;
            border: 1px solid #D7E0E5;
            border-radius: 7px;
            font-size: 15px;
            background: white;
        }

        textarea {
            min-height: 120px;
            resize: vertical;
        }

        input:focus,
        textarea:focus,
        select:focus {
            outline: 2px solid #16834A;
            border-color: #16834A;
        }

        .hint {
            color: #657386;
            font-size: 13px;
            margin-top: 5px;
        }

        button {
            border: none;
            border-radius: 7px;
            padding: 13px 20px;
            background: #16834A;
            color: white;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
        }

        button:hover {
            background: #106638;
        }

        .alert {
            padding: 14px;
            margin-bottom: 20px;
            border-radius: 7px;
        }

        .error {
            background: #FDECEC;
            color: #9B2226;
        }

        .success {
            background: #E8F5EC;
            color: #106638;
        }

        .notice {
            background: #E8F0FF;
            color: #174A8B;
            padding: 14px;
            margin-bottom: 22px;
            border-radius: 7px;
            font-size: 14px;
        }

        @media (max-width: 600px) {
            .form-card {
                padding: 20px;
            }
        }

        .container {
            max-width: 800px;
            width: 92%;
            margin: 40px auto;
        }

        .heading {
            margin-bottom: 25px;
        }

        .heading h1 {
            color: var(--sc-blue);
            font-size: 30px;
        }

        .heading p {
            color: var(--sc-muted);
        }

        .form-card {
            padding: 32px;
            border: 1px solid var(--sc-border);
        }

        label {
            color: var(--sc-text);
        }

        input[type="text"],
        textarea,
        select {
            border: 1px solid var(--sc-border);
            border-radius: 8px;
        }

        input[type="text"]:focus,
        textarea:focus,
        select:focus {
            outline: 2px solid var(--sc-green);
            border-color: var(--sc-green);
        }

        button {
            background: var(--sc-green);
            border-radius: 8px;
            transition: background 0.2s ease;
        }

        button:hover {
            background: var(--sc-green-dark);
        }

        .notice {
            background: var(--sc-green-soft);
            color: var(--sc-green-dark);
            border: 1px solid var(--sc-border);
        }

        .alert {
            border-radius: 9px;
        }

        @media (max-width: 600px) {
            .form-card {
                padding: 20px;
            }

            .heading h1 {
                font-size: 25px;
            }
        }

    </style>

</head>

<body>

<?php include "includes/header.php"; ?>

<main class="container">

    <div class="heading">

        <h1>Share Study Materials</h1>

        <p>
            Help your fellow students by sharing useful academic resources.
        </p>

    </div>

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
                    <li><?= htmlspecialchars($error) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        
    <?php endif; ?>

    <div class="notice">
        Your upload will not appear in the public materials list
        until an administrator approves it. Upload PDF files only,
        up to 10 MB.
    </div>

    <div class="form-card sc-card">

        <form method="POST"
              action="upload_material.php"
              enctype="multipart/form-data">

            <input type="hidden" name="csrf_token"
                   value="<?= htmlspecialchars($_SESSION["upload_csrf"]) ?>">

            <div class="form-group">

                <label for="title">Material Title *</label>

                <input
                    type="text"
                    id="title"
                    name="title"
                    maxlength="200"
                    value="<?= htmlspecialchars($title) ?>"
                    placeholder="e.g. Introduction to Programming Notes"
                    required
                >

            </div>


            <div class="form-group">

                <label for="subject_id">Subject *</label>

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
                                <?= htmlspecialchars($subject["subject_name"]) ?>
                            </option>

                        <?php endwhile; ?>

                    <?php endif; ?>

                    <option value="new"
                        <?= $subjectId === "new" ? "selected" : "" ?>>
                        + Add a new subject
                    </option>

                </select>

                <div id="new-subject-box"
                    style="display: <?= $subjectId === "new" ? "block" : "none" ?>;
                            margin-top: 12px;">

                    <label for="new_subject_name">New Subject Name *</label>

                    <input
                        type="text"
                        id="new_subject_name"
                        name="new_subject_name"
                        maxlength="100"
                        value="<?= htmlspecialchars($newSubjectName) ?>"
                        placeholder="e.g. Object-Oriented Programming"
                    >

                    <p class="hint">
                        Add a subject only if it is not already in the list.
                    </p>

                </div>
                
            </div>

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


            <div class="form-group">

                <label for="description">Description</label>

                <textarea
                    id="description"
                    name="description"
                    maxlength="5000"
                    placeholder="Briefly describe the material..."
                ><?= htmlspecialchars($description) ?></textarea>

            </div>

            <div class="form-group">

                <label for="material_file">Choose PDF File *</label>

                <input
                    type="file"
                    id="material_file"
                    name="material_file"
                    accept=".pdf,application/pdf"
                    required
                >

                <p class="hint">PDF format only. Maximum file size: 10 MB.</p>
                
            </div>

            <button type="submit">Submit for Approval</button>

        </form>

    </div>

</main>

<?php include "includes/footer.php"; ?>

<?php if (isset($_GET["success"]) && $_GET["success"] == "1"): ?>

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
        html: <?= json_encode(implode("<br>", $errors)) ?>,
        confirmButtonText: "Try Again",
        confirmButtonColor: "#174A8B"
    });
</script>

<?php endif; ?>

</body>
</html>