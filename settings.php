
<?php
session_start();

// If user is NOT logged in, send them to home page
if (!isset($_SESSION['user'])) {
    header("Location: home.html");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT · Settings</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-bg">

<header class="top-bar">
    <h1>Face-IT</h1>

    <nav class="nav-actions">
        <a href="main.php" class="nav-btn">Dashboard</a>
        <a href="manage_classes.php" class="nav-btn">Manage Classes</a>
        <a href="Attendance_Report/report_filters.php" class="nav-btn">Attendance Reports</a>
        <a href="alerts.php" class="nav-btn">Alerts</a>
        <a href="settings.php" class="nav-btn">Settings</a>
        <a href="logout.php" class="nav-btn">Log Out</a>
    </nav>
</header>

<main class="dashboard">

    <section class="glass-card">
        <h2>Settings</h2>

        <label class="setting">
            <input type="checkbox" checked>
            Enable alerts
        </label>

        <label class="setting">
            <input type="checkbox">
            Dark mode (always on 😌)
        </label>

    </section>

</main>

</body>
</html>