<?php

require __DIR__ . '/report_helpers.php';
require __DIR__ . '/../vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

try {
    $classId = isset($_GET['class_id']) ? (int) $_GET['class_id'] : 1;
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
    $className = $classInfo['class_name'] ?? ('Class ' . $classId);
    $safeClassName = sanitize_filename_part($className);
    $filename = 'attendance_report_' . $safeClassName . '.pdf';

    $tz = new DateTimeZone('America/New_York');
    $generatedAt = (new DateTime('now', $tz))->format('F j, Y g:i A');

    $filterParts = [];
    if (!empty($startDate)) {
        $filterParts[] = 'Start Date: ' . htmlspecialchars($startDate);
    }
    if (!empty($endDate)) {
        $filterParts[] = 'End Date: ' . htmlspecialchars($endDate);
    }
    if (!empty($studentIds)) {
        $filterParts[] = 'Selected Students: ' . count($studentIds);
    }

    $filterSummary = implode(' | ', $filterParts);

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            @page {
                margin: 32px 28px;
            }

            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                font-size: 11px;
                color: #222;
            }

            h1 {
                margin: 0 0 6px 0;
                font-size: 20px;
            }

            .subtitle {
                margin: 0 0 18px 0;
                font-size: 11px;
                color: #555;
            }

            .filters {
                margin-bottom: 14px;
                font-size: 10px;
                color: #444;
            }

            table {
                width: 100%;
                border-collapse: collapse;
                table-layout: fixed;
            }

            th, td {
                border: 1px solid #cfcfcf;
                padding: 7px 6px;
                text-align: left;
                vertical-align: top;
                word-wrap: break-word;
            }

            th {
                background: #ececec;
                font-weight: bold;
            }

            .day-separator td {
                border-top: 3px solid #444 !important;
            }

            .footer-note {
                margin-top: 14px;
                font-size: 10px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <h1><?php echo htmlspecialchars($className); ?> Attendance Report</h1>
        <div class="subtitle">Generated on <?php echo htmlspecialchars($generatedAt); ?></div>

        <?php if ($filterSummary !== ''): ?>
            <div class="filters"><?php echo $filterSummary; ?></div>
        <?php endif; ?>

        <?php if (empty($rows)): ?>
            <p>No attendance records found for the selected filters.</p>
        <?php else: ?>
            <table>
                <thead>
                    <tr>
                        <?php foreach ($selectedColumns as $columnKey): ?>
                            <th><?php echo htmlspecialchars($availableColumns[$columnKey]); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $previousDayKey = null;
                    foreach ($rows as $row):
                        $currentDayKey = $row['detected_day_key'] ?? '';
                        $separatorClass = ($previousDayKey !== null && $currentDayKey !== $previousDayKey) ? 'day-separator' : '';
                    ?>
                        <tr class="<?php echo $separatorClass; ?>">
                            <?php foreach ($selectedColumns as $columnKey): ?>
                                <td><?php echo htmlspecialchars((string)($row[$columnKey] ?? '')); ?></td>
                            <?php endforeach; ?>
                        </tr>
                    <?php
                        $previousDayKey = $currentDayKey;
                    endforeach;
                    ?>
                </tbody>
            </table>
        <?php endif; ?>

        <div class="footer-note">
            Face-It Attendance System
        </div>
    </body>
    </html>
    <?php
    $html = ob_get_clean();

    $options = new Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'DejaVu Sans');

    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('letter', 'portrait');
    $dompdf->render();
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;

} catch (Exception $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}