<?php
session_start();

//$SUPABASE_URL = "https://evoqwkezqahsvctmopld.supabase.co
if (
    empty($_POST['first_name']) ||
    empty($_POST['last_name']) ||
    empty($_POST['student_id']) ||
    empty($_POST['professor']) ||
    empty($_POST['class']) ||
    empty($_FILES['faces']['name'][0])
) {
    $_SESSION['error'] = "All fields are required.";
    header("Location: enroll.php");
    exit();
}

$firstName = trim($_POST['first_name']);
$lastName  = trim($_POST['last_name']);
$studentId = trim($_POST['student_id']);
$professor = trim($_POST['professor']);
$class     = trim($_POST['class']);

// create upload directory
$uploadDir = "uploads/students/$studentId/";
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// handle file uploads
foreach ($_FILES['faces']['tmp_name'] as $index => $tmpName) {
    if ($_FILES['faces']['error'][$index] !== UPLOAD_ERR_OK) {
        continue;
    }

    $fileType = mime_content_type($tmpName);
    if (!str_starts_with($fileType, "image/")) {
        continue;
    }

    $fileName = basename($_FILES['faces']['name'][$index]);
    move_uploaded_file($tmpName, $uploadDir . $fileName);
}

/*
 TODO:
 - I]insert student into MySQL
 - associate student with professor + class
 - send images to AI training pipeline
*/

$_SESSION['success'] = "Student enrolled successfully!";
header("Location: enroll.php");
exit();