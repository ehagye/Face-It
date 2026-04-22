<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$config = require __DIR__ . '/config.php';
$SUPABASE_URL = $config['SUPABASE_URL'];
$SUPABASE_KEY = $config['SUPABASE_KEY'];

// 1. Validate input
if (
    !isset($_POST['class_id']) || $_POST['class_id'] === '' ||
    empty($_POST['class_name']) ||
    empty($_POST['scheduled_start_time'])
) {
    die("Missing required fields");
}

// 2. Prepare class data
$classData = [
    "class_id"             => trim($_POST['class_id']),
    "class_name"           => trim($_POST['class_name']),
    "scheduled_start_time" => trim($_POST['scheduled_start_time']),
    "professor_id"         => !empty($_POST['professor_id']) ? (int) $_POST['professor_id'] : null,
];

// 3. Insert into Supabase
$ch = curl_init("$SUPABASE_URL/rest/v1/classes");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $SUPABASE_KEY",
        "apikey: $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ],
    CURLOPT_POSTFIELDS => json_encode($classData)
]);

$dbResponse = curl_exec($ch);
$dbStatus   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. Handle result
if ($dbStatus !== 201) {
    echo "<h3>Class creation failed (status: $dbStatus)</h3>";
    echo "<pre>" . htmlspecialchars($dbResponse) . "</pre>";
    exit;
}

// 5. Redirect back
$_SESSION['success'] = "Class created successfully!";
header("Location: manage_classes.php");
exit;