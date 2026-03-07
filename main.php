<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: home.html");
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
            <p>Capstone ICTW · Section 004</p>
        </div>
    </section>

    <section class="dashboard-grid">

        <!-- LEFT COLUMN -->
        <div class="column">

            <!-- CLASS SELECTION -->
            <div class="glass-card">

                <div class="card-header">
                    <h3>Class Selection</h3>

                    <a href="manage_classes.php" class="manage-btn">
                        Manage Classes
                    </a>
                </div>

                <select>
                    <option>Data Science 101</option>
                    <option>Capstone ICTW 004</option>
                </select>

            </div>

            <!-- ROSTER -->
            <div class="glass-card">
                <h3>Class Roster</h3>

                <div class="roster">

                    <div class="row">
                        <span>Jordan Kim</span>
                        <span>002481923</span>

                        <select>
                            <option>Present</option>
                            <option>Absent</option>
                        </select>
                    </div>

                    <div class="row">
                        <span>Maya Patel</span>
                        <span>002735642</span>

                        <select>
                            <option>Present</option>
                            <option>Absent</option>
                        </select>
                    </div>

                    <div class="row">
                        <span>Ethan Williams</span>
                        <span>002497658</span>

                        <select>
                            <option>Absent</option>
                            <option>Present</option>
                        </select>
                    </div>

                </div>
            </div>

            <!-- ACTIVITY LOG -->
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

            <!-- CHART -->
            <div class="glass-card">
                <h3>Attendance Overview</h3>
                <canvas id="attendanceChart" width="514" height="514" style="display: block; box-sizing: border-box; height: 343.2px; width: 343.2px;"></canvas>
            </div>

            <!-- CAMERA STATUS -->
            <div class="glass-card status">
                <h3>Camera Status</h3>
                <p class="active">● Active</p>
            </div>

        </div>

    </section>

</main>

<script>
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
</script>

</body>
</html>