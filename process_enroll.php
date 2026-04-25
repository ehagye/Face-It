<?php
// Receives student info + captured face images
// generate_embeddings.py runs later to compute embeddings

$config = require __DIR__ . '/config.php';
$SUPABASE_URL = $config['SUPABASE_URL'];
$SUPABASE_KEY = $config['SUPABASE_KEY'];

// --- Validate required fields ---
if (empty($_POST['student_id']) || empty($_POST['first_name']) || empty($_POST['last_name'])) {
    die("Missing required fields.");
}

$student_id = trim($_POST['student_id']);
$first_name = trim($_POST['first_name']);
$last_name  = trim($_POST['last_name']);
$faces      = $_POST['faces'] ?? [];

if (count($faces) < 3) {
    die("At least 3 face captures are required.");
}

$studentData = [
    "student_id"    => $student_id,
    "first_name"    => $first_name,
    "last_name"     => $last_name,
    "face_encoding" => null
];

$ch = curl_init("$SUPABASE_URL/rest/v1/students");
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        "Authorization: Bearer $SUPABASE_KEY",
        "apikey: $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: resolution=merge-duplicates,return=minimal"
    ],
    CURLOPT_POSTFIELDS => json_encode($studentData)
]);
$dbResponse = curl_exec($ch);
$dbStatus   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($dbStatus !== 200 && $dbStatus !== 201) {
    die("Student insert failed (HTTP $dbStatus): $dbResponse");
}

foreach ($faces as $index => $dataUrl) {
    // Strip the data:image/jpeg;base64, prefix to get raw base64
    $base64    = preg_replace('/^data:image\/\w+;base64,/', '', $dataUrl);
    $imageData = base64_decode($base64);
    if (!$imageData) continue;

    $fileName    = $student_id . "_" . time() . "_" . $index . ".jpg";
    $storagePath = "students/" . $student_id . "/" . $fileName;
    $uploadUrl   = "$SUPABASE_URL/storage/v1/object/faces/$storagePath";

    // Upload raw JPEG bytes to Supabase Storage
    $ch = curl_init($uploadUrl);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $SUPABASE_KEY",
            "apikey: $SUPABASE_KEY",
            "Content-Type: image/jpeg"
        ],
        CURLOPT_POSTFIELDS => $imageData
    ]);
    $uploadStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    // Log the photo path so Python can find it later
    $ch = curl_init("$SUPABASE_URL/rest/v1/student_faces");
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $SUPABASE_KEY",
            "apikey: $SUPABASE_KEY",
            "Content-Type: application/json",
            "Prefer: return=minimal"
        ],
        CURLOPT_POSTFIELDS => json_encode([
            "student_id" => $student_id,
            "photo_path" => $storagePath
        ])
    ]);
    curl_exec($ch);
    curl_close($ch);
}

// Redirect back to enrollment page with success message
header("Location: enroll.php?success=1");
exit;