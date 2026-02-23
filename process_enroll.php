<?php
// process_enroll.php
// SERVER SIDE ONLY — safe place for secrets

$SUPABASE_URL = "https://evoqwkezqahsvctmopld.supabase.co";
$SUPABASE_KEY = "sb_secret_vchsmlPS_bl5qcyzCixSFQ_eGnCm4WL";

// 1. validate input
if (
    empty($_POST['student_id']) ||
    empty($_POST['first_name']) ||
    empty($_POST['last_name']) ||
    empty($_FILES['faces']['name'][0])
) {
    die("Missing required fields");
}

$student_id = (int) $_POST['student_id'];
$first_name = trim($_POST['first_name']);
$last_name  = trim($_POST['last_name']);

// 2. handle image upload
$tmp_name = $_FILES['faces']['tmp_name'][0];
$fileExt  = pathinfo($_FILES['faces']['name'][0], PATHINFO_EXTENSION);

$fileName = $student_id . "_" . time() . "." . $fileExt;
$storagePath = "students/" . $fileName;

$uploadUrl = "$SUPABASE_URL/storage/v1/object/faces/$storagePath";
$fileData = file_get_contents($tmp_name);

$ch = curl_init($uploadUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $SUPABASE_KEY",
        "apikey: $SUPABASE_KEY",
        "Content-Type: application/octet-stream"
    ],
    CURLOPT_POSTFIELDS => $fileData
]);

$uploadResponse = curl_exec($ch);
$uploadStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($uploadStatus !== 200 && $uploadStatus !== 201) {
    echo "<h3>File upload failed</h3>";
    echo "<pre>$uploadResponse</pre>";
    exit;
}

// 3. insert database row
$data = [
    "student_id"    => $student_id,
    "first_name"    => $first_name,
    "last_name"     => $last_name,
    "photo_path"    => $storagePath,
    "face_encoding" => null
];

$ch = curl_init("$SUPABASE_URL/rest/v1/students");
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $SUPABASE_KEY",
        "apikey: $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$dbResponse = curl_exec($ch);
$dbStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($dbStatus !== 201) {
    echo "<h3>Supabase insert failed</h3>";
    echo "<pre>$dbResponse</pre>";
    exit;
}

echo "<h2>Student enrolled successfully</h2>";