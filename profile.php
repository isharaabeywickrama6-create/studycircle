<?php
session_start();

require_once "config/database.php";

if (
    empty($_SESSION['user_id']) ||
    !in_array($_SESSION['role'] ?? '', ['student', 'admin'], true)
) {
    header("Location: login.php");
    exit;
}

$userId = (int) $_SESSION['user_id'];
$message = "";
$messageType = "error";

if (empty($_SESSION['profile_csrf'])) {
    $_SESSION['profile_csrf'] = bin2hex(random_bytes(32));
}

$uploadDir = __DIR__ . "/uploads/profile_pictures/";

if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

$stmt = $conn->prepare(
    "SELECT name, email, profile_image FROM users WHERE user_id = ?"
);
$stmt->bind_param("i", $userId);
$stmt->execute();

$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user) {
    exit("User account not found.");
}

$_SESSION['profile_image'] = $user['profile_image'] ?? '';

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $csrfToken = $_POST["profile_csrf"] ?? "";

    if (
        !is_string($csrfToken) ||
        !hash_equals($_SESSION["profile_csrf"], $csrfToken)
    ) {
        $message = "Invalid request. Please refresh and try again.";

    } elseif (isset($_POST["remove_picture"])) {

        if (!empty($user["profile_image"])) {

            $oldImage = $uploadDir . basename($user["profile_image"]);

            $stmt = $conn->prepare(
                "UPDATE users SET profile_image = NULL WHERE user_id = ?"
            );
            $stmt->bind_param("i", $userId);

            if ($stmt->execute()) {
                if (is_file($oldImage)) {
                    unlink($oldImage);
                }

                $message = "Profile picture removed successfully.";
                $messageType = "success";
                $user["profile_image"] = null;
                $_SESSION['profile_image'] = '';
            } else {
                $message = "Unable to remove profile picture.";
            }

            $stmt->close();

        } else {
            $message = "You do not have a profile picture.";
        }

    } elseif (isset($_FILES["profile_image"])) {

        $file = $_FILES["profile_image"];

        if ($file["error"] !== UPLOAD_ERR_OK) {
            $message = "Please select a valid image.";

        } elseif ($file["size"] > 2 * 1024 * 1024) {
            $message = "Image size must be 2 MB or less.";

        } else {

            $imageInfo = @getimagesize($file["tmp_name"]);

            $allowedTypes = [
                IMAGETYPE_JPEG => "jpg",
                IMAGETYPE_PNG => "png",
                IMAGETYPE_WEBP => "webp"
            ];

            if (
                $imageInfo === false ||
                !isset($allowedTypes[$imageInfo[2]])
            ) {
                $message = "Only JPG, PNG and WebP images are allowed.";

            } else {

                $extension = $allowedTypes[$imageInfo[2]];
                $newFilename = bin2hex(random_bytes(24)) . "." . $extension;

                $destination = $uploadDir . $newFilename;

                if (move_uploaded_file($file["tmp_name"], $destination)) {

                    $stmt = $conn->prepare(
                        "UPDATE users SET profile_image = ? WHERE user_id = ?"
                    );
                    $stmt->bind_param("si", $newFilename, $userId);

                    if ($stmt->execute()) {

                        if (!empty($user["profile_image"])) {
                            $oldImage = $uploadDir . basename($user["profile_image"]);

                            if (is_file($oldImage)) {
                                unlink($oldImage);
                            }
                        }

                        $user["profile_image"] = $newFilename;
                        $_SESSION['profile_image'] = $newFilename;
                        $message = "Profile picture updated successfully.";
                        $messageType = "success";

                    } else {
                        unlink($destination);
                        $message = "Unable to save your profile picture.";
                    }

                    $stmt->close();

                } else {
                    $message = "Unable to upload the image. Please try again.";
                }
            }
        }
    }

    $_SESSION["profile_csrf"] = bin2hex(random_bytes(32));
}

$profileImageUrl = !empty($user["profile_image"])
    ? "uploads/profile_pictures/" . rawurlencode($user["profile_image"])
    : "";

$pageTitle = "My Profile";
?>

<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?> - StudyCircle</title>
    <link rel="stylesheet" href="assets/css/studycircle.css">

</head>

<body>

<?php require_once "includes/header.php"; ?>

<main class="sc-profile-page">

    <section class="sc-profile-card">

        <h1>My Profile</h1>
        <p>Manage your StudyCircle account and profile picture.</p>

        <?php if ($message): ?>
            <div class="sc-profile-message <?= $messageType === 'success' ? 'success' : 'error' ?>">
                <?= htmlspecialchars($message, ENT_QUOTES, 'UTF-8') ?>
            </div>
        <?php endif; ?>

        <div class="sc-profile-avatar">

            <?php if ($profileImageUrl): ?>

                <img
                    src="<?= htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                    alt="Profile picture"
                >

            <?php else: ?>

                <div class="sc-profile-placeholder">
                    <?= strtoupper(substr($user["name"], 0, 1)) ?>
                </div>

            <?php endif; ?>

        </div>

        <h2>
            <?= htmlspecialchars($user["name"], ENT_QUOTES, 'UTF-8') ?>
        </h2>

        <p class="sc-profile-email">
            <?= htmlspecialchars($user["email"], ENT_QUOTES, 'UTF-8') ?>
        </p>

        <form method="POST" enctype="multipart/form-data">

            <input
                type="hidden"
                name="profile_csrf"
                value="<?= htmlspecialchars($_SESSION["profile_csrf"], ENT_QUOTES, 'UTF-8') ?>"
            >

            <label for="profile_image">Choose a profile picture</label>

            <input
                type="file"
                id="profile_image"
                name="profile_image"
                accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                required
            >
                      
            <div class="sc-photo-editor" id="photoEditor" hidden>

                <h3>Adjust Your Profile Picture</h3>

                <p>Drag the image to position it inside the circle.</p>

                <div class="sc-simple-editor">
                    <div class="sc-circle-preview" id="circlePreview">
                        <img id="imageToCrop" src="" alt="Profile picture preview">
                    </div>
                </div>

            </div>

            <small>JPG, PNG or WebP. Maximum size: 2 MB.</small>

            <button type="submit">
                Upload / Change Picture
            </button>

        </form>

        <?php if (!empty($user["profile_image"])): ?>

            <form method="POST"
                  onsubmit="return confirm('Are you sure you want to remove your profile picture?');">

                <input
                    type="hidden"
                    name="profile_csrf"
                    value="<?= htmlspecialchars($_SESSION["profile_csrf"], ENT_QUOTES, 'UTF-8') ?>"
                >

                <button type="submit"
                        name="remove_picture"
                        class="sc-profile-remove">
                    Remove Picture
                </button>

            </form>

        <?php endif; ?>

        <a href="<?= ($_SESSION['role'] === 'admin') ? 'admin/dashboard.php' : 'index.php' ?>"
           class="sc-profile-back">
            Back to Dashboard
        </a>

    </section>

</main>

<?php require_once "includes/footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {

    const fileInput = document.getElementById("profile_image");
    const editor = document.getElementById("photoEditor");
    const preview = document.getElementById("circlePreview");
    const image = document.getElementById("imageToCrop");

    if (!fileInput || !editor || !preview || !image) {
        return;
    }

    const uploadForm = fileInput.closest("form");

    if (!uploadForm) {
        return;
    }

    let imageReady = false;
    let isDragging = false;
    let isPreparingUpload = false;

    let imageLeft = 0;
    let imageTop = 0;
    let imageWidth = 0;
    let imageHeight = 0;

    let startX = 0;
    let startY = 0;
    let startLeft = 0;
    let startTop = 0;

    let currentImageUrl = null;

    function updateImagePosition() {
        image.style.width = imageWidth + "px";
        image.style.height = imageHeight + "px";
        image.style.left = imageLeft + "px";
        image.style.top = imageTop + "px";
    }

    function limitImagePosition() {
        const previewWidth = preview.clientWidth;
        const previewHeight = preview.clientHeight;

        imageLeft = Math.min(
            0,
            Math.max(previewWidth - imageWidth, imageLeft)
        );

        imageTop = Math.min(
            0,
            Math.max(previewHeight - imageHeight, imageTop)
        );
    }

    function prepareImage() {
        const previewWidth = preview.clientWidth;
        const previewHeight = preview.clientHeight;

        const scale = Math.max(
            previewWidth / image.naturalWidth,
            previewHeight / image.naturalHeight
        );

        imageWidth = image.naturalWidth * scale;
        imageHeight = image.naturalHeight * scale;

        imageLeft = (previewWidth - imageWidth) / 2;
        imageTop = (previewHeight - imageHeight) / 2;

        limitImagePosition();
        updateImagePosition();

        imageReady = true;
    }

    fileInput.addEventListener("change", function () {
        const file = this.files[0];

        imageReady = false;
        isPreparingUpload = false;

        if (!file) {
            editor.hidden = true;
            return;
        }

        if (!["image/jpeg", "image/png", "image/webp"].includes(file.type)) {
            alert("Please select a JPG, PNG or WebP image.");
            this.value = "";
            editor.hidden = true;
            return;
        }

        if (currentImageUrl) {
            URL.revokeObjectURL(currentImageUrl);
        }

        editor.hidden = false;
        currentImageUrl = URL.createObjectURL(file);

        image.onload = function () {
            prepareImage();

            URL.revokeObjectURL(currentImageUrl);
            currentImageUrl = null;
        };

        image.onerror = function () {
            alert("Unable to load this image. Please select another picture.");

            fileInput.value = "";
            editor.hidden = true;
            imageReady = false;

            if (currentImageUrl) {
                URL.revokeObjectURL(currentImageUrl);
                currentImageUrl = null;
            }
        };

        image.src = currentImageUrl;
    });

    preview.addEventListener("pointerdown", function (event) {
        if (!imageReady) return;

        event.preventDefault();

        isDragging = true;

        startX = event.clientX;
        startY = event.clientY;

        startLeft = imageLeft;
        startTop = imageTop;

        preview.setPointerCapture(event.pointerId);
    });

    preview.addEventListener("pointermove", function (event) {
        if (!isDragging) return;

        imageLeft = startLeft + (event.clientX - startX);
        imageTop = startTop + (event.clientY - startY);

        limitImagePosition();
        updateImagePosition();
    });

    function stopDragging() {
        isDragging = false;
    }

    preview.addEventListener("pointerup", stopDragging);
    preview.addEventListener("pointercancel", stopDragging);
    preview.addEventListener("lostpointercapture", stopDragging);

    uploadForm.addEventListener("submit", function (event) {
        if (isPreparingUpload) return;
        if (!fileInput.files.length) return;

        event.preventDefault();

        if (!imageReady) {
            alert("Please wait until the image preview is ready.");
            return;
        }

        const outputSize = 512;

        const scaleX = outputSize / preview.clientWidth;
        const scaleY = outputSize / preview.clientHeight;

        const canvas = document.createElement("canvas");
        canvas.width = outputSize;
        canvas.height = outputSize;

        const context = canvas.getContext("2d");

        if (!context) {
            alert("Unable to prepare your profile picture.");
            return;
        }

        context.fillStyle = "#FFFFFF";
        context.fillRect(0, 0, outputSize, outputSize);

        context.drawImage(
            image,
            imageLeft * scaleX,
            imageTop * scaleY,
            imageWidth * scaleX,
            imageHeight * scaleY
        );

        isPreparingUpload = true;

        canvas.toBlob(function (blob) {
            if (!blob) {
                isPreparingUpload = false;
                alert("Unable to prepare the image. Please try again.");
                return;
            }

            const croppedFile = new File(
                [blob],
                "profile-picture.jpg",
                {
                    type: "image/jpeg",
                    lastModified: Date.now()
                }
            );

            const dataTransfer = new DataTransfer();
            dataTransfer.items.add(croppedFile);

            fileInput.files = dataTransfer.files;

            uploadForm.requestSubmit();

        }, "image/jpeg", 0.90);
    });

});
</script>

</body>
</html>