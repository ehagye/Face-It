<?php
// update_attendance.php

$supabase_url = 'https://evoqwkezqahsvctmopld.supabase.co';
$supabase_key = 'YOUR_SUPABASE_KEY_HERE'; // <-- replace

$data = json_decode(file_get_contents("php://input"), true);

$student_id = $data['student_id'];
$status = strtolower($data['status']);

// MAP VALUES
if ($status === "present") {
    $status = "on_time";
} else {
    $status = "absent";
}

$url = "$supabase_url/rest/v1/attendance_logs";

$payload = json_encode([
    "student_id" => $student_id,
    "status" => $status,
    "detected_at" => date("c")
]);

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $supabase_key",
    "Authorization: Bearer $supabase_key",
    "Content-Type: application/json",
    "Prefer: return=minimal"
]);

curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

$response = curl_exec($ch);
curl_close($ch);

echo json_encode(["success" => true]);