<?php
echo "NEW VERSION LOADED";
exit;


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

// 2. insert student data
// ]);

// $uploadResponse = curl_exec($ch);
// $uploadStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
// curl_close($ch);

// if ($uploadStatus !== 200 && $uploadStatus !== 201) {
//     echo "<h3>File upload failed</h3>";
//     echo "<pre>$uploadResponse</pre>";
//     exit;
// }

// 3. insert student data
$studentData = [
    "student_id"    => $student_id,
    "first_name"    => $first_name,
    "last_name"     => $last_name,
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
    CURLOPT_POSTFIELDS => json_encode($studentData)
]);

$dbResponse = curl_exec($ch);
$dbStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($dbStatus !== 201) {
    echo "<h3>Supabase insert failed</h3>";
    echo "<pre>$dbResponse</pre>";
    exit;
}


foreach ($_FILES['faces']['tmp_name'] as $index => $tmp_name) {

    if (!file_exists($tmp_name)) continue;

    $fileExt = pathinfo($_FILES['faces']['name'][$index], PATHINFO_EXTENSION);

    // Optional file type validation
    $allowed = ['jpg', 'jpeg', 'png'];
    if (!in_array(strtolower($fileExt), $allowed)) {
        continue;
    }

    $fileName = $student_id . "_" . time() . "_" . $index . "." . $fileExt;
    $storagePath = "students/" . $fileName;

    $uploadUrl = "$SUPABASE_URL/storage/v1/object/faces/$storagePath";
    $fileData = file_get_contents($tmp_name);

    // Upload to Supabase Storage
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

        $faceData = [
            "student_id" => $student_id,
            "photo_path" => $storagePath
        ];
    
        $ch = curl_init("$SUPABASE_URL/rest/v1/student_faces");
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer $SUPABASE_KEY",
                "apikey: $SUPABASE_KEY",
                "Content-Type: application/json",
                "Prefer: return=minimal"
            ],
            CURLOPT_POSTFIELDS => json_encode($faceData)
        ]);
    
        curl_exec($ch);
        curl_close($ch);
    }
    
    echo "<h2>Student enrolled successfully</h2>";

    