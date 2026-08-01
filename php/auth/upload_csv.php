<?php
session_start();

// Path to Flask backend's data folder (relative to this auth folder)
$targetDir = __DIR__ . "/../backend/data/";

if (!is_dir($targetDir)) {
    mkdir($targetDir, 0777, true);
}

if (isset($_FILES["csv_file"])) {
    $fileName = basename($_FILES["csv_file"]["name"]);
    $targetFile = $targetDir . $fileName;
    $fileType = strtolower(pathinfo($targetFile, PATHINFO_EXTENSION));

    if ($fileType != "csv") {
        $_SESSION["error"] = "Only CSV files are allowed.";
        header("Location: admin.php");
        exit;
    }

    if (move_uploaded_file($_FILES["csv_file"]["tmp_name"], $targetFile)) {
        $_SESSION["success"] = "File uploaded successfully: $fileName";
    } else {
        $_SESSION["error"] = "Error uploading file.";
    }
} else {
    $_SESSION["error"] = "No file selected.";
}

header("Location: admin.php");
exit;
?>
