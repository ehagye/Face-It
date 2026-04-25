<?php

require __DIR__ . '/report_helpers.php';

header('Content-Type: application/json; charset=utf-8');

try {
    $classId = isset($_GET['class_id']) && $_GET['class_id'] !== ''
        ? trim($_GET['class_id'])
        : null;

    if ($classId === null) {
        echo json_encode([]);
        exit;
    }

    $students = get_students_for_class($classId);

    $result = [];

    foreach ($students as $student) {
        $firstName = $student['first_name'] ?? '';
        $lastName = $student['last_name'] ?? '';
        $studentId = $student['student_id'] ?? '';

        $result[] = [
            'student_id' => $studentId,
            'first_name' => $firstName,
            'last_name'  => $lastName,
            'display'    => trim($firstName . ' ' . $lastName) . ' (' . $studentId . ')'
        ];
    }

    echo json_encode($result);
    exit;

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => $e->getMessage()
    ]);
    exit;
}