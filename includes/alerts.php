<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function setAlert($type, $title, $message)
{
    $allowedTypes = ['success', 'error', 'warning', 'info'];

    if (!in_array($type, $allowedTypes, true)) {
        $type = 'info';
    }

    $_SESSION["site_alert"] = [
        "type" => $type,
        "title" => $title,
        "message" => $message
    ];
}

function showAlert()
{
    if (empty($_SESSION["site_alert"])) {
        return;
    }

    $alert = $_SESSION["site_alert"];

    unset($_SESSION["site_alert"]);

    $type = json_encode(
        $alert["type"],
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_HEX_AMP
    );

    $title = json_encode(
        $alert["title"],
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_HEX_AMP
    );

    $message = json_encode(
        $alert["message"],
        JSON_HEX_TAG | JSON_HEX_APOS |
        JSON_HEX_QUOT | JSON_HEX_AMP
    );

    echo '<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>';

    echo "
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            Swal.fire({
                icon: $type,
                title: $title,
                text: $message,
                confirmButtonText: 'OK',
                confirmButtonColor: '#16834A'
            });
        });
    </script>
    ";
}
?>