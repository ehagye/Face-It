<?php
/**
 * get_students_by_class.php
 *
 * AJAX endpoint — returns JSON list of students for a given class_id.
 * Called by main.php when the professor selects a class.
 *
 * GET /get_students_by_class.php?class_id=2
 * → { success: true, students: [{student_id, first_name, last_name}, ...], count: N }
 */

session_start();

if (empty($_SESSION['user'])) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$class_id = intval($_GET['class_id'] ?? 0);

if (!$class_id) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Missing class_id']);
    exit;
}

$config = require 'config.php';

$url = $config['SUPABASE_URL'] . "/rest/v1/students"
     . "?class_id=eq." . $class_id
     . "&select=student_id,first_name,last_name"
     . "&order=last_name.asc,first_name.asc";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$config['SUPABASE_SERVICE_ROLE_KEY']}",
        "apikey: {$config['SUPABASE_SERVICE_ROLE_KEY']}",
    ],
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

header('Content-Type: application/json');

if ($http_code === 200) {
    $students = json_decode($response, true) ?? [];
    echo json_encode(['success' => true, 'students' => $students, 'count' => count($students)]);
} else {
    http_response_code($http_code);
    echo json_encode(['success' => false, 'error' => "Supabase returned HTTP $http_code"]);
}