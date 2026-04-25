<?php

function supabase_request(string $method, string $path, array $queryParams = []): array
{
    $config = require __DIR__ . '/../config.php';

    $supabaseUrl = rtrim($config['SUPABASE_URL'], '/');
    $supabaseKey = $config['SUPABASE_KEY'];

    $url = $supabaseUrl . '/rest/v1/' . ltrim($path, '/');

    if (!empty($queryParams)) {
        $url .= '?' . http_build_query($queryParams);
    }

    $ch = curl_init($url);

    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $method,
        CURLOPT_HTTPHEADER     => [
            "Authorization: Bearer $supabaseKey",
            "apikey: $supabaseKey",
            "Content-Type: application/json"
        ]
    ]);

    $responseBody = curl_exec($ch);
    $statusCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    if ($responseBody === false) {
        $error = curl_error($ch);
        curl_close($ch);
        throw new Exception("cURL request failed: " . $error);
    }

    curl_close($ch);

    $decoded = json_decode($responseBody, true);

    if ($statusCode < 200 || $statusCode >= 300) {
        throw new Exception("Supabase request failed (HTTP $statusCode): " . $responseBody);
    }

    return is_array($decoded) ? $decoded : [];
}

function build_attendance_query_params(
    string $classId,                 // CHANGED: int -> string
    ?string $startDate = null,
    ?string $endDate = null,
    array $studentIds = [],
    string $sortOrder = 'desc'
): array {
    $params = [
        'select'   => 'student_id,class_id,detected_at,status,confidence_score',
        'class_id' => 'eq.' . $classId,
        'order'    => 'detected_at.' . ($sortOrder === 'asc' ? 'asc' : 'desc')
    ];

    if (!empty($startDate) && !empty($endDate)) {
        $params['and'] = '(detected_at.gte.' . $startDate . 'T00:00:00,detected_at.lte.' . $endDate . 'T23:59:59)';
    } elseif (!empty($startDate)) {
        $params['detected_at'] = 'gte.' . $startDate . 'T00:00:00';
    } elseif (!empty($endDate)) {
        $params['detected_at'] = 'lte.' . $endDate . 'T23:59:59';
    }

    if (!empty($studentIds)) {
        $safeIds = array_map('intval', $studentIds);
        $params['student_id'] = 'in.(' . implode(',', $safeIds) . ')';
    }

    return $params;
}

function get_students_by_ids(array $studentIds): array
{
    if (empty($studentIds)) {
        return [];
    }

    $safeIds = array_map('intval', $studentIds);

    $rows = supabase_request(
        'GET',
        'students',
        [
            'select'     => 'student_id,first_name,last_name,class_id,created_at',
            'student_id' => 'in.(' . implode(',', $safeIds) . ')'
        ]
    );

    $studentsById = [];

    foreach ($rows as $row) {
        $studentsById[(string)$row['student_id']] = [
            'first_name' => $row['first_name'] ?? '',
            'last_name'  => $row['last_name'] ?? '',
            'class_id'   => $row['class_id'] ?? null
        ];
    }

    return $studentsById;
}

function get_students_for_class(string $classId): array     // CHANGED: int -> string
{
    $attendanceRows = supabase_request(
        'GET',
        'attendance_logs',
        [
            'select'   => 'student_id',
            'class_id' => 'eq.' . $classId,
            'order'    => 'student_id.asc'
        ]
    );

    if (empty($attendanceRows)) {
        return [];
    }

    $studentIds = [];
    foreach ($attendanceRows as $row) {
        if (isset($row['student_id'])) {
            $studentIds[] = (int) $row['student_id'];
        }
    }

    $studentIds = array_values(array_unique($studentIds));

    if (empty($studentIds)) {
        return [];
    }

    $students = supabase_request(
        'GET',
        'students',
        [
            'select'     => 'student_id,first_name,last_name,class_id,created_at',
            'student_id' => 'in.(' . implode(',', $studentIds) . ')'
        ]
    );

    usort($students, function ($a, $b) {
        $nameA = trim(($a['last_name'] ?? '') . ' ' . ($a['first_name'] ?? ''));
        $nameB = trim(($b['last_name'] ?? '') . ' ' . ($b['first_name'] ?? ''));
        return strcasecmp($nameA, $nameB);
    });

    return $students;
}

function get_class_by_id(string $classId): ?array     // CHANGED: int -> string
{
    $rows = supabase_request(
        'GET',
        'classes',
        [
            'select'   => 'class_id,class_name,scheduled_start_time,professor_id',
            'class_id' => 'eq.' . $classId,
            'limit'    => 1
        ]
    );

    if (empty($rows)) {
        return null;
    }

    return $rows[0];
}

function get_all_classes(): array
{
    return supabase_request(
        'GET',
        'classes',
        [
            'select' => 'class_id,class_name,scheduled_start_time,professor_id',
            'order'  => 'class_id.asc'
        ]
    );
}

function format_status(string $status): string
{
    return match ($status) {
        'early'   => 'Early',
        'late'    => 'Late',
        'on_time' => 'On time',
        default   => ucwords(str_replace('_', ' ', $status)),
    };
}

function format_sort_order(string $sortOrder): string
{
    return $sortOrder === 'asc' ? 'Ascending' : 'Descending';
}

function format_detected_at(string $timestamp): string
{
    try {
        $dt = new DateTime($timestamp);
        return $dt->format('F j, Y g:i A');
    } catch (Exception $e) {
        return $timestamp;
    }
}

function format_detected_day_key(string $timestamp): string
{
    try {
        $dt = new DateTime($timestamp);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return $timestamp;
    }
}

function sanitize_filename_part(string $value): string
{
    $value = trim($value);
    $value = preg_replace('/[^A-Za-z0-9_-]+/', '_', $value);
    $value = trim($value, '_');

    return $value !== '' ? $value : 'report';
}

function get_available_report_columns(): array
{
    return [
        'student_id'            => 'Student ID',
        'first_name'            => 'First Name',
        'last_name'             => 'Last Name',
        'detected_at_formatted' => 'Detected At',
        'status_formatted'      => 'Status',
        'confidence_score'      => 'Confidence Score',
    ];
}