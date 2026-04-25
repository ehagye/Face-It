<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: ../home.html");
    exit;
}

require __DIR__ . '/report_helpers.php';

try {
    $classes = get_all_classes();
    $availableColumns = get_available_report_columns();
    $defaultColumns = get_default_report_columns();
} catch (Exception $e) {
    die("Error loading report page: " . htmlspecialchars($e->getMessage()));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT · Attendance Reports</title>
    <link rel="stylesheet" href="../styles.css">
</head>
<body class="dark-bg">

<header class="top-bar">
    <h1>Face-IT</h1>

    <nav class="nav-actions">
        <a href="../main.php" class="nav-btn">Dashboard</a>
        <a href="../manage_classes.php" class="nav-btn">Manage Classes</a>
        <a href="../alerts.php" class="nav-btn">Alerts</a>
        <a href="../settings.php" class="nav-btn">Settings</a>
        <a href="../logout.php" class="nav-btn">Log Out</a>
    </nav>
</header>

<main class="dashboard">

    <section class="glass-card report-hero-card">
        <h2 class="gradient-text">Attendance Reports</h2>
        <p class="subtitle">
            Filter attendance records, preview the report live, and export it as CSV or PDF.
        </p>
    </section>

    <section class="dashboard-grid report-page-grid">

        <!-- LEFT COLUMN: FILTERS -->
        <div class="column">

            <section class="glass-card report-filter-card">
                <div class="card-header">
                    <h3>Report Filters</h3>
                </div>

                <form id="reportForm" class="report-form" onsubmit="return false;">

                    <div class="field">
                        <label for="class_id" class="subtitle">Class</label>
                        <select name="class_id" id="class_id" required>
                            <option value="">Select a class</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?= htmlspecialchars((string)$class['class_id']) ?>">
                                    <?= htmlspecialchars((string)$class['class_id']) ?> - <?= htmlspecialchars($class['class_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="field">
                        <label for="start_date" class="subtitle">Start Date</label>
                        <input type="date" name="start_date" id="start_date">
                    </div>

                    <div class="field">
                        <label for="end_date" class="subtitle">End Date</label>
                        <input type="date" name="end_date" id="end_date">
                    </div>

                    <div class="field">
                        <label class="subtitle">Students</label>
                        <div id="studentPicker" class="report-student-picker disabled">
                            <input
                                type="text"
                                id="studentSearch"
                                class="report-student-search"
                                placeholder="Select a class first"
                                disabled
                            >

                            <div class="report-student-actions">
                                <button type="button" id="clearStudentsBtn" class="manage-btn" disabled>Clear all</button>
                            </div>

                            <div id="studentList" class="report-student-list">
                                <div class="report-student-empty">Select a class first.</div>
                            </div>

                            <div id="selectedStudentsText" class="subtitle report-selected-students">
                                No students selected.
                            </div>
                        </div>
                        <p class="subtitle report-field-note">
                            Click student names to toggle them on or off. Use the search box to narrow the list.
                        </p>
                    </div>

                    <div class="field">
                        <label for="sort_order" class="subtitle">Sort Order</label>
                        <select name="sort_order" id="sort_order">
                            <option value="desc">Newest to oldest</option>
                            <option value="asc">Oldest to newest</option>
                        </select>
                    </div>

                    <div class="field">
                        <label class="subtitle">Columns to Include</label>
                        <div class="report-checkbox-grid">
                            <?php foreach ($availableColumns as $columnKey => $columnLabel): ?>
                                <label class="report-checkbox-item">
                                    <input
                                        type="checkbox"
                                        name="columns[]"
                                        value="<?= htmlspecialchars($columnKey) ?>"
                                        <?= in_array($columnKey, $defaultColumns, true) ? 'checked' : '' ?>
                                    >
                                    <span><?= htmlspecialchars($columnLabel) ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="report-button-row">
                        <a id="downloadCsvBtn" class="primary-btn report-download-btn is-disabled" href="export_csv.php">
                            Download CSV
                        </a>

                        <a id="downloadPdfBtn" class="secondary-btn report-download-btn is-disabled" href="export_pdf.php">
                            Download PDF
                        </a>
                    </div>
                </form>
            </section>

        </div>

        <!-- RIGHT COLUMN: PREVIEW -->
        <div class="column">

            <section class="glass-card report-preview-card">
                <div class="card-header">
                    <h3>Live Report Preview</h3>
                </div>

                <div id="previewContainer" class="report-preview-container">
                    <div class="report-preview-empty">
                        Choose a class to see the report preview.
                    </div>
                </div>
            </section>

        </div>

    </section>

</main>

<script>
    const previewContainer = document.getElementById('previewContainer');
    const downloadCsvBtn = document.getElementById('downloadCsvBtn');
    const downloadPdfBtn = document.getElementById('downloadPdfBtn');

    const classIdInput = document.getElementById('class_id');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const sortOrderInput = document.getElementById('sort_order');

    const studentPicker = document.getElementById('studentPicker');
    const studentSearch = document.getElementById('studentSearch');
    const studentList = document.getElementById('studentList');
    const selectedStudentsText = document.getElementById('selectedStudentsText');
    const clearStudentsBtn = document.getElementById('clearStudentsBtn');

    let debounceTimer = null;
    let allStudents = [];
    let selectedStudentIds = new Set();

    function getSelectedStudentIds() {
        return Array.from(selectedStudentIds);
    }

    function setDownloadButtonsEnabled(enabled) {
        if (enabled) {
            downloadCsvBtn.classList.remove('is-disabled');
            downloadPdfBtn.classList.remove('is-disabled');
        } else {
            downloadCsvBtn.classList.add('is-disabled');
            downloadPdfBtn.classList.add('is-disabled');
        }
    }

    function updateSelectedStudentsText() {
        const selectedStudents = allStudents.filter(student =>
            selectedStudentIds.has(String(student.student_id))
        );

        if (selectedStudents.length === 0) {
            selectedStudentsText.textContent = 'No students selected.';
            return;
        }

        const names = selectedStudents.map(student => student.display);
        selectedStudentsText.textContent = 'Selected: ' + names.join(', ');
    }

    function renderStudentList(filterText = '') {
        const normalizedFilter = filterText.trim().toLowerCase();
        studentList.innerHTML = '';

        const filteredStudents = allStudents.filter(student => {
            if (normalizedFilter === '') {
                return true;
            }

            return student.display.toLowerCase().includes(normalizedFilter);
        });

        if (filteredStudents.length === 0) {
            const emptyDiv = document.createElement('div');
            emptyDiv.className = 'report-student-empty';
            emptyDiv.textContent = 'No matching students found.';
            studentList.appendChild(emptyDiv);
            updateSelectedStudentsText();
            return;
        }

        filteredStudents.forEach(student => {
            const item = document.createElement('div');
            item.className = 'report-student-item';
            item.dataset.studentId = String(student.student_id);
            item.textContent = student.display;

            if (selectedStudentIds.has(String(student.student_id))) {
                item.classList.add('selected');
            }

            item.addEventListener('click', () => {
                const id = String(student.student_id);

                if (selectedStudentIds.has(id)) {
                    selectedStudentIds.delete(id);
                    item.classList.remove('selected');
                } else {
                    selectedStudentIds.add(id);
                    item.classList.add('selected');
                }

                updateSelectedStudentsText();
                updatePreview();
            });

            studentList.appendChild(item);
        });

        updateSelectedStudentsText();
    }

    async function loadStudentsForClass() {
        const classId = classIdInput.value;

        allStudents = [];
        selectedStudentIds = new Set();
        studentSearch.value = '';
        updateSelectedStudentsText();

        if (classId === '') {
            studentPicker.classList.add('disabled');
            studentSearch.disabled = true;
            clearStudentsBtn.disabled = true;
            studentSearch.placeholder = 'Select a class first';
            studentList.innerHTML = '<div class="report-student-empty">Select a class first.</div>';
            return;
        }

        studentPicker.classList.remove('disabled');
        studentSearch.disabled = false;
        clearStudentsBtn.disabled = false;
        studentSearch.placeholder = 'Type to search student names';

        try {
            const response = await fetch('students_by_class.php?class_id=' + encodeURIComponent(classId));
            const students = await response.json();

            allStudents = Array.isArray(students) ? students : [];
            renderStudentList();
        } catch (error) {
            allStudents = [];
            selectedStudentIds = new Set();
            studentList.innerHTML = '<div class="report-student-empty">Failed to load students.</div>';
            updateSelectedStudentsText();
        }
    }

    function buildQueryString() {
        const params = new URLSearchParams();

        if (classIdInput.value !== '') {
            params.set('class_id', classIdInput.value);
        }

        if (startDateInput.value !== '') {
            params.set('start_date', startDateInput.value);
        }

        if (endDateInput.value !== '') {
            params.set('end_date', endDateInput.value);
        }

        const selectedIds = getSelectedStudentIds();
        if (selectedIds.length > 0) {
            params.set('student_ids', selectedIds.join(','));
        }

        if (sortOrderInput.value !== '') {
            params.set('sort_order', sortOrderInput.value);
        }

        const selectedColumns = document.querySelectorAll('input[name="columns[]"]:checked');
        selectedColumns.forEach((checkbox) => {
            params.append('columns[]', checkbox.value);
        });

        return params.toString();
    }

    async function updatePreview() {
        const queryString = buildQueryString();

        if (classIdInput.value === '') {
            previewContainer.innerHTML = '<div class="report-preview-empty">Choose a class to see the report preview.</div>';
            downloadCsvBtn.href = 'export_csv.php';
            downloadPdfBtn.href = 'export_pdf.php';
            setDownloadButtonsEnabled(false);
            return;
        }

        previewContainer.innerHTML = '<div class="report-preview-loading">Loading preview...</div>';

        try {
            const response = await fetch('preview_partial.php?' + queryString);
            const html = await response.text();
            previewContainer.innerHTML = html;

            downloadCsvBtn.href = 'export_csv.php?' + queryString;
            downloadPdfBtn.href = 'export_pdf.php?' + queryString;
            setDownloadButtonsEnabled(true);
        } catch (error) {
            previewContainer.innerHTML = '<div class="report-preview-empty">Failed to load preview.</div>';
        }
    }

    function debouncedUpdatePreview() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(updatePreview, 300);
    }

    classIdInput.addEventListener('change', async () => {
        await loadStudentsForClass();
        updatePreview();
    });

    startDateInput.addEventListener('change', updatePreview);
    endDateInput.addEventListener('change', updatePreview);
    sortOrderInput.addEventListener('change', updatePreview);

    studentSearch.addEventListener('input', () => {
        renderStudentList(studentSearch.value);
    });

    clearStudentsBtn.addEventListener('click', () => {
        selectedStudentIds = new Set();
        renderStudentList(studentSearch.value);
        updatePreview();
    });

    document.querySelectorAll('input[name="columns[]"]').forEach((checkbox) => {
        checkbox.addEventListener('change', updatePreview);
    });

    window.addEventListener('DOMContentLoaded', () => {
        setDownloadButtonsEnabled(false);
        updatePreview();
    });
</script>

</body>
</html>