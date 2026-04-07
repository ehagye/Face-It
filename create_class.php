<?php


echo "CREATE CLASS FILE LOADED";
exit;
session_start();

// SERVER SIDE ONLY safe place for secrets
$config = require 'config.php';

$SUPABASE_URL = $config['SUPABASE_URL'];
$SUPABASE_KEY = $config['SUPABASE_KEY'];

// Auth check
// add l8r

// 1. Validate input
if (
    empty($_POST['crn']) ||
    empty($_POST['class_id']) ||
    empty($_POST['class_name'])
) {
    die("Missing required fields");
}

$crn        = trim($_POST['crn']);
$class_id   = trim($_POST['class_id']);
$class_name = trim($_POST['class_name']);

// 2. Prepare class data
$classData = [
    "crn"        => $crn,
    "class_id"   => $class_id,
    "class_name" => $class_name
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
    echo "<h3>Class creation failed</h3>";
    echo "<pre>$dbResponse</pre>";
    exit;
}

// 5. Redirect back
$_SESSION['success'] = "Class created successfully!";
header("Location: manage_classes.php");
exit;