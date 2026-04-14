<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: home.html");
    exit;
}

// SUPABASE CONFIG
$supabase_url = 'https://evoqwkezqahsvctmopld.supabase.co';
$supabase_key = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImV2b3F3a2V6cWFoc3ZjdG1vcGxkIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NzA4NDEyNzUsImV4cCI6MjA4NjQxNzI3NX0.2lxmqC6l7GxAMLQxxZ1qSLfniPuKWk4b2WsQSGO1v3o';

// FUNCTION TO CALL SUPABASE
function supabase_get($url, $key) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "apikey: $key",
        "Authorization: Bearer $key",
        "Content-Type: application/json"
    ]);
    $response = curl_exec($ch);
    curl_close($ch);
    return json_decode($response, true);
}

// GET SELECTED CLASS NAME (default to Data Science 101)
$selected_class_name = isset($_GET['class_name']) ? $_GET['class_name'] : 'Data Science 101';

// FETCH CLASSES FOR DROPDOWN
$classes = supabase_get("$supabase_url/rest/v1/classes?select=class_id,class_name&order=class_id", $supabase_key);

// FETCH STUDENTS FOR SELECTED CLASS
$students = supabase_get("$supabase_url/rest/v1/students?select=first_name,last_name,student_id&class_name=eq." . urlencode($selected_class_name), $supabase_key);

// FETCH ACTIVITY LOG
$logs = supabase_get("$supabase_url/rest/v1/attendance_logs?select=*&order=detected_at.desc&limit=10", $supabase_key);


// FETCH ATTENDANCE COUNTS FOR SELECTED CLASS
$total_students = $students ? count($students) : 0;
$attendance_today = supabase_get("$supabase_url/rest/v1/attendance_logs?select=student_id,status&order=detected_at.desc", $supabase_key);

$present = 0;
$absent = 0;

if ($attendance_today && $students) {
    $enrolled_ids = array_column($students, 'student_id');
    $seen = [];
    foreach ($attendance_today as $record) {
        if (in_array($record['student_id'], $enrolled_ids) && !in_array($record['student_id'], $seen)) {
            $seen[] = $record['student_id'];
            if ($record['status'] === 'on_time' || $record['status'] === 'early') {
                $present++;
            } else {
                $absent++;
            }
        }
    }
    $absent += ($total_students - count($seen));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <script src="chart.js"></script>
</head>
<body class="dark-bg">

<header class="top-bar">
    <h1>Face-IT</h1>
    <nav class="nav-actions">
        <a href="main.php" class="nav-btn">Dashboard</a>
        <a href="manage_classes.php" class="nav-btn">Manage Classes</a>
        <a href="alerts.php" class="nav-btn">Alerts</a>
        <a href="settings.php" class="nav-btn">Settings</a>
        <a href="logout.php" class="nav-btn">Log Out</a>
    </nav>
</header>

<main class="dashboard">

    <!-- PROFESSOR -->
    <section class="glass-card professor-card">
        <div>
            <h2>
                Dr. William G. Johnson
                <a href="alerts.php" class="inline-alerts">View Alerts</a>
            </h2>
        </div>
    </section>

    <section class="dashboard-grid">

        <!-- LEFT COLUMN -->
        <div class="column">

            <!-- CLASS SELECTION -->
            <div class="glass-card">
                <div class="card-header">
                    <h3>Class Selection</h3>
                    <a href="manage_classes.php" class="manage-btn">Manage Classes</a>
                </div>

                <select onchange="window.location.href='main.php?class_name=' + encodeURIComponent(this.value)">
                    <?php if ($classes): foreach ($classes as $class): ?>
                        <option value="<?= htmlspecialchars($class['class_name']) ?>" <?= $class['class_name'] == $selected_class_name ? 'selected' : '' ?>>
                            <?= htmlspecialchars($class['class_name']) ?>
                        </option>
                    <?php endforeach; endif; ?>
                </select>
            </div>

            <!-- ROSTER -->
            <div class="glass-card">
                <h3>Class Roster</h3>
                <div class="roster">
                    <?php if ($students && count($students) > 0): ?>
                        <?php foreach ($students as $student): ?>
                            <div class="row">
                                <span><?= htmlspecialchars($student['first_name'] . ' ' . $student['last_name']) ?></span>
                                <span><?= htmlspecialchars($student['student_id']) ?></span>
                                <select class="attendance-select" data-id="<?= $student['student_id'] ?>">
                                <option>Present</option>
                                <option>Absent</option>
                            </select>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No students enrolled in this class.</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ACTIVITY LOG -->
            <div class="glass-card">
                <h3>Activity Log</h3>
                <div class="log">
                    <?php if ($logs && count($logs) > 0): ?>
                        <?php foreach ($logs as $entry): ?>
                            <p class="<?= $entry['confidence_score'] < 0.8 ? 'warn' : '' ?>">
                                [<?= htmlspecialchars($entry['detected_at']) ?>]
                                Student <?= htmlspecialchars($entry['student_id']) ?>
                                (<?= htmlspecialchars($entry['status']) ?>)
                            </p>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p>No activity yet.</p>
                    <?php endif; ?>
                </div>
            </div>

        </div>

        <!-- RIGHT COLUMN -->
        <div class="column">

            <!-- LIVE CAMERA FEED -->
            <!-- Browser-side camera preview so the professor can see
                what the camera sees. The actual face detection and
                matching runs in a separate Python process. -->
            <div class="glass-card">
                <div class="card-header">
                    <h3>Live Camera</h3>
                    <div class="camera-controls">
                        <select id="cameraSelect"></select>
                        <button id="camToggle" class="manage-btn" onclick="toggleCamera()">
                            Start
                        </button>
                    </div>
                </div>
                <div class="camera-feed-wrapper">
                    <video id="dashCam" autoplay playsinline></video>
                    <p id="camStatus" class="subtitle">Camera off</p>
                </div>
            </div>

            <!-- CHART -->
            <div class="glass-card">
                <h3>Attendance Overview</h3>
                <canvas id="attendanceChart"></canvas>
            </div>

            <!-- CAMERA STATUS -->
            <div class="glass-card status">
                <h3>Camera Status</h3>
                <p id="camIndicator" class="inactive">● Inactive</p>
            </div>

        </div>

    </section>

</main>
<script>
    window.present = <?= $present ?>;
    window.absent = <?= $absent ?>;
document.addEventListener("DOMContentLoaded", () => {

    const ctx = document.getElementById("attendanceChart").getContext("2d");

    new Chart(ctx, {
        type: "doughnut",
        data: {
            labels: ["Present", "Absent"],
            datasets: [{
                data: [26, 3],
                backgroundColor: ["#4fc3ff", "#ff6b6b"]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    labels: { color: "white" }
                }
            }
        }
    });

});

// Dashboard camera feed
// Shows a live preview from any connected camera so the
// professor can monitor the room. Camera selector lets them
// pick which device to use (e.g. built-in webcam vs USB cam)

let dashStream = null;

// Populate the camera dropdown with all available video devices
async function loadCameraList() {
    const select = document.getElementById("cameraSelect");

    // Need a temporary stream first so the browser reveals device labels
    try {
        const tempStream = await navigator.mediaDevices.getUserMedia({ video: true });
        tempStream.getTracks().forEach(t => t.stop());
    } catch (e) {
        console.error("Camera permission denied:", e);
        return;
    }

    const devices = await navigator.mediaDevices.enumerateDevices();
    const videoDevices = devices.filter(d => d.kind === "videoinput");

    select.innerHTML = "";
    videoDevices.forEach((device, i) => {
        const option = document.createElement("option");
        option.value = device.deviceId;
        option.textContent = device.label || `Camera ${i + 1}`;
        select.appendChild(option);
    });
}

// Start or stop the camera preview
async function toggleCamera() {
    const video  = document.getElementById("dashCam");
    const btn    = document.getElementById("camToggle");
    const status = document.getElementById("camStatus");
    const indicator = document.getElementById("camIndicator");

    if (dashStream) {
        // Stop the camera
        dashStream.getTracks().forEach(t => t.stop());
        dashStream = null;
        video.style.display = "none";
        btn.textContent = "Start";
        status.textContent = "Camera off";
        indicator.textContent = "● Inactive";
        indicator.className = "inactive";
        return;
    }

    // Start the selected camera
    const deviceId = document.getElementById("cameraSelect").value;
    try {
        dashStream = await navigator.mediaDevices.getUserMedia({
            video: { deviceId: { exact: deviceId } }
        });
        video.srcObject = dashStream;
        video.style.display = "block";
        btn.textContent = "Stop";
        status.textContent = "";
        indicator.textContent = "● Active";
        indicator.className = "active";
    } catch (e) {
        console.error("Failed to start camera:", e);
        status.textContent = "Failed to access camera";
    }
}

// Load camera list when the page loads
document.addEventListener("DOMContentLoaded", loadCameraList);
</script>
<script src="script.js"></script>

</body>
</html>