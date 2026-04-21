<?php

require __DIR__ . '/report_helpers.php';

try {
    $classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 1;
    $startDate = $_GET['start_date'] ?? null;
    $endDate = $_GET['end_date'] ?? null;
    $sortOrder = $_GET['sort_order'] ?? 'desc';

    $studentIds = [];
    if (!empty($_GET['student_ids'])) {
        $studentIds = array_map('intval', explode(',', $_GET['student_ids']));
    }

    $rows = get_attendance_report_rows(
        classId: $classId,
        startDate: $startDate,
        endDate: $endDate,
        studentIds: $studentIds,
        sortOrder: $sortOrder
    );

    $csvUrl = 'export_csv.php?class_id=' . urlencode((string)$classId);

    if (!empty($startDate)) {
        $csvUrl .= '&start_date=' . urlencode($startDate);
    }

    if (!empty($endDate)) {
        $csvUrl .= '&end_date=' . urlencode($endDate);
    }

    if (!empty($studentIds)) {
        $csvUrl .= '&student_ids=' . urlencode(implode(',', $studentIds));
    }

    if (!empty($sortOrder)) {
        $csvUrl .= '&sort_order=' . urlencode($sortOrder);
    }

} catch (Exception $e) {
    die("Error: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Attendance Report Preview</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 30px;
            background: #f7f7f7;
            color: #222;
        }

        h1 {
            margin-bottom: 10px;
        }

        .meta {
            margin-bottom: 20px;
        }

        .actions {
            margin-bottom: 20px;
        }

        .btn {
            display: inline-block;
            padding: 10px 16px;
            background: #2563eb;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            margin-right: 10px;
        }

        .btn:hover {
            background: #1d4ed8;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: white;
        }

        th, td {
            border: 1px solid #ddd;
            padding: 10px;
            text-align: left;
            font-size: 14px;
        }

        th {
            background: #f0f0f0;
        }

        tr:nth-child(even) {
            background: #fafafa;
        }

        .empty {
            padding: 20px;
            background: white;
            border: 1px solid #ddd;
        }
    </style>
</head>
<body>

    <h1>Attendance Report Preview</h1>

    <div class="meta">
        <p><strong>Class ID:</strong> <?= htmlspecialchars((string)$classId) ?></p>
        <p><strong>Start Date:</strong> <?= htmlspecialchars($startDate ?? 'None') ?></p>
        <p><strong>End Date:</strong> <?= htmlspecialchars($endDate ?? 'None') ?></p>
        <p><strong>Sort Order:</strong> <?= htmlspecialchars($sortOrder) ?></p>
    </div>

    <div class="actions">
        <a class="btn" href="<?= htmlspecialchars($csvUrl) ?>">Download CSV</a>
    </div>

    <?php if (empty($rows)): ?>
        <div class="empty">No attendance records found for the selected filters.</div>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Log ID</th>
                    <th>Student ID</th>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Class Name</th>
                    <th>Professor</th>
                    <th>Detected At</th>
                    <th>Status</th>
                    <th>Confidence</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= htmlspecialchars((string)$row['log_id']) ?></td>
                        <td><?= htmlspecialchars((string)$row['student_id']) ?></td>
                        <td><?= htmlspecialchars($row['first_name']) ?></td>
                        <td><?= htmlspecialchars($row['last_name']) ?></td>
                        <td><?= htmlspecialchars($row['class_name']) ?></td>
                        <td><?= htmlspecialchars($row['professor_name']) ?></td>
                        <td><?= htmlspecialchars($row['detected_at']) ?></td>
                        <td><?= htmlspecialchars($row['status']) ?></td>
                        <td><?= htmlspecialchars((string)$row['confidence_score']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</body>
</html>