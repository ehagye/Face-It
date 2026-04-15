<?php
/**
 * get_students_by_class.php
 * 
 * Fetch all students in a given class
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

// Suppress config output
ob_start();
require 'config.php';
ob_end_clean();

// Fetch all students in this class
$url = $config['SUPABASE_URL'] . "/rest/v1/students";
$url .= "?class_id=eq." . $class_id;
$url .= "&select=student_id,first_name,last_name";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 5,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$config['SUPABASE_KEY']}",
        "apikey: {$config['SUPABASE_KEY']}"
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

header('Content-Type: application/json');

if ($http_code === 200) {
    $students = json_decode($response, true) ?? [];
    echo json_encode([
        'success' => true,
        'students' => $students,
        'count' => count($students)
    ]);
} else {
    http_response_code($http_code ?: 500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to fetch students',
        'http_code' => $http_code,
        'curl_error' => $curl_error
    ]);
}