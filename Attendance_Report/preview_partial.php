<?php

require __DIR__ . '/report_helpers.php';

try {
    $classId = isset($_GET['class_id']) && $_GET['class_id'] !== ''
        ? trim($_GET['class_id'])
        : null;

    $startDate = $_GET['start_date'] ?? '';
    $endDate = $_GET['end_date'] ?? '';
    $studentIdsRaw = $_GET['student_ids'] ?? '';
    $sortOrder = $_GET['sort_order'] ?? 'desc';

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

    $studentIds = [];
    if (!empty($studentIdsRaw)) {
        $studentIds = array_map('intval', explode(',', $studentIdsRaw));
    }

    $rows = [];
    $classTitle = '';

    if ($classId !== null) {
        $rows = get_attendance_report_rows(
            classId: $classId,
            startDate: $startDate !== '' ? $startDate : null,
            endDate: $endDate !== '' ? $endDate : null,
            studentIds: $studentIds,
            sortOrder: $sortOrder
        );

        if (!empty($rows[0]['class_name'])) {
            $classTitle = $rows[0]['class_name'];
        } else {
            $classInfo = get_class_by_id($classId);
            $classTitle = $classInfo['class_name'] ?? ('Class ' . $classId);
        }
    }

} catch (Exception $e) {
    echo '<div class="empty">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<?php if ($classId === null): ?>
    <div class="empty">Choose a class to see the report preview.</div>

<?php elseif (empty($rows)): ?>
    <div class="empty">No attendance records found for the selected filters.</div>

<?php else: ?>
    <?php $previewRows = array_slice($rows, 0, 15); ?>

    <h3><?= htmlspecialchars($classTitle) ?></h3>

    <table>
        <thead>
            <tr>
                <?php foreach ($selectedColumns as $columnKey): ?>
                    <th><?= htmlspecialchars($availableColumns[$columnKey]) ?></th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $previousDayKey = null;
            foreach ($previewRows as $row):
                $currentDayKey = $row['detected_day_key'] ?? '';
                $separatorClass = ($previousDayKey !== null && $currentDayKey !== $previousDayKey) ? 'day-separator' : '';
            ?>
                <tr class="<?= htmlspecialchars($separatorClass) ?>">
                    <?php foreach ($selectedColumns as $columnKey): ?>
                        <td><?= htmlspecialchars((string)($row[$columnKey] ?? '')) ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php
                $previousDayKey = $currentDayKey;
            endforeach;
            ?>
        </tbody>
    </table>
<?php endif; ?>