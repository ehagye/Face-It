<?php
session_start();

if (!isset($_SESSION['logged_in'])) {
    header("Location: login.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT Dashboard</title>
    <link rel="stylesheet" href="styles.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="dark-bg">

<header class="top-bar">
    <h1>Face-IT</h1>
    <nav class="nav-actions">
        <button onclick="goToDashboard()">Dashboard</button>
        <button onclick="goToAlerts()">Alerts</button>
        <button onclick="goToSettings()">Settings</button>
        <button onclick="window.location.href='logout.php'">Log Out</button>
    </nav>
</header>

<main class="dashboard">

    <!-- PROFESSOR -->
    <section class="glass-card professor-card">
        <img src="https://i.imgur.com/8Km9tLL.png">
        <div>
            <h2>
                Dr. William G. Johnson
                <button class="inline-alerts" onclick="goToAlerts()">View Alerts</button>
            </h2>
            <p>Capstone ICTW · Section 004</p>
        </div>
    </section>

    <section class="dashboard-grid">

        <!-- LEFT COLUMN -->
        <div class="column">
            <div class="glass-card">
                <h3>Class Selection</h3>
                <select>
                    <option>Data Science 101</option>
                    <option>Capstone ICTW 004</option>
                </select>
            </div>

            <div class="glass-card">
                <h3>Class Roster</h3>
                <div class="roster">
                    <div class="row">
                        <span>Jordan Kim</span><span>002481923</span>
                        <select><option>Present</option><option>Absent</option></select>
                    </div>
                    <div class="row">
                        <span>Maya Patel</span><span>002735642</span>
                        <select><option>Present</option><option>Absent</option></select>
                    </div>
                    <div class="row">
                        <span>Ethan Williams</span><span>002497658</span>
                        <select><option>Absent</option><option>Present</option></select>
                    </div>
                </div>
            </div>

            <div class="glass-card">
                <h3>Activity Log</h3>
                <div class="log">
                    <p>[9:01] Attendance — Jordan Kim (0.96)</p>
                    <p class="warn">[9:07] Low confidence — Marcus Johnson</p>
                </div>
            </div>
        </div>

        <!-- RIGHT COLUMN -->
        <div class="column">
            <div class="glass-card">
                <h3>Attendance Overview</h3>
                <canvas id="attendanceChart"></canvas>
            </div>

            <div class="glass-card status">
                <h3>Camera Status</h3>
                <p class="active">● Active</p>
            </div>
        </div>

    </section>
</main>

<script src="script.js"></script>
<script>
new Chart(document.getElementById("attendanceChart"), {
    type: "doughnut",
    data: {
        labels: ["Present", "Absent"],
        datasets: [{
            data: [26, 3],
            backgroundColor: ["#4fc3ff", "#ff6b6b"]
        }]
    },
    options: {
        plugins: {
            legend: {
                labels: { color: "white" }
            }
        }
    }
});
</script>
</body>
</html>