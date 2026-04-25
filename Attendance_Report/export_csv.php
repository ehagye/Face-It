<?php
require __DIR__ . '/report_helpers.php';
try {
    $classId = isset($_GET['class_id']) ? trim($_GET['class_id']) : '';
    if ($classId === '') {
        throw new Exception('Missing class_id');
    }

    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $sortOrder = $_GET['sort_order'] ?? 'desc';
    $studentIds = [];
    if (!empty($_GET['student_ids'])) {
        $studentIds = array_map('intval', explode(',', $_GET['student_ids']));
    }
    $availableColumns = get_available_report_columns();
    $defaultColumns = get_default_report_columns();
    $selectedColumns = $_GET['columns'] ?? $defaultColumns;
    if (!is_array($selectedColumns) || empty($selectedColumns)) {
        $selectedColumns = $defaultColumns;
    }
    $selectedColumns = array_values(array_filter(
        $selectedColumns,
        fn($col) => array_key_exists($col, $availableColumns)
    ));
    $rows = get_attendance_report_rows(
        classId: $classId,
        startDate: $startDate,
        endDate: $endDate,
        studentIds: $studentIds,
        sortOrder: $sortOrder
    );
    $classInfo = get_class_by_id($classId);
    $className = $classInfo['class_name'] ?? ('class_' . $classId);
    $safeClassName = sanitize_filename_part($className);
    $filename = 'attendance_report_' . $safeClassName . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $output = fopen('php://output', 'w');
    $headerRow = [];
    foreach ($selectedColumns as $columnKey) {
        $headerRow[] = $availableColumns[$columnKey];
    }
    fputcsv($output, $headerRow);
    foreach ($rows as $row) {
        $csvRow = [];
        foreach ($selectedColumns as $columnKey) {
            $csvRow[] = $row[$columnKey] ?? '';
        }
        fputcsv($output, $csvRow);
    }
    fclose($output);
    exit;
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}