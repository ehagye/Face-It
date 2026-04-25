<?php

require __DIR__ . '/report_helpers.php';

try {
    $rows = get_attendance_report_rows(
        classId: 1,   // replace with a real class_id from your DB
        startDate: null,
        endDate: null,
        studentIds: [],
        sortOrder: 'desc'
    );

    echo "<pre>";
    print_r($rows);
    echo "</pre>";

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}