<?php
// process_enroll.php
// SERVER SIDE ONLY — safe place for secrets

$SUPABASE_URL = "https://evoqwkezqahsvctmopld.supabase.co";
$SUPABASE_KEY = "sb_secret_vchsmlPS_bl5qcyzCixSFQ_eGnCm4WL";

// 1. validate input
if (
    empty($_POST['student_id']) ||
    empty($_POST['first_name']) ||
    empty($_POST['last_name'])
) {
    die("Missing required fields");
}

// 2. prepare data
$data = [
    "student_id" => (int) $_POST['student_id'],
    "first_name" => trim($_POST['first_name']),
    "last_name"  => trim($_POST['last_name']),
    "face_encoding" => null,
    "photo_path" => null
];

// 3. send to Supabase REST API
$ch = curl_init("$SUPABASE_URL/rest/v1/students");

curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "apikey: $SUPABASE_KEY",
        "Authorization: Bearer $SUPABASE_KEY",
        "Content-Type: application/json",
        "Prefer: return=minimal"
    ],
    CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// 4. handle response
if ($http_code !== 201) {
    echo "<h3>Supabase insert failed</h3>";
    echo "<pre>$response</pre>";
    exit;
}

echo "<h2>Student enrolled successfully </h2>";