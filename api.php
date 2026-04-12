<?php
// Supabase config
$SUPABASE_URL = "https://evoqwkezqahsvctmopld.supabase.co/rest/v1/";
$SUPABASE_KEY = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV2b3F3a2V6cWFoc3ZjdG1vcGxkIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzA4NDEyNzUsImV4cCI6MjA4NjQxNzI3NX0.2lxmqC6l7GxAMLQxxZ1qSLfniPuKWk4b2WsQSGO1v3o"; 

// Get request info
$endpoint = $_GET['endpoint']; // e.g. "classes" or "attendance_logs"
$query = $_GET['query'] ?? ""; // e.g. "?select=*"

// Build full URL
$url = $SUPABASE_URL . $endpoint . $query;

// cURL request
$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: $SUPABASE_KEY",
    "Authorization: Bearer $SUPABASE_KEY",
    "Content-Type: application/json"
]);

$response = curl_exec($ch);
$http_status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Return response
http_response_code($http_status);
echo $response;
?>