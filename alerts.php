<?php
session_start();

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error'] ?? null;

unset($_SESSION['success'], $_SESSION['error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT · Alerts</title>
    <link rel="stylesheet" href="styles.css">
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
    <section class="glass-card">
        <h2>Review Alerts</h2>

        <div class="log">
            <p class="warn">[9:07] Marcus Johnson — Low confidence (0.75)</p>
            <p class="warn">[9:12] Unknown face detected — Camera A</p>
        </div>
    </section>
</main>


</body>
</html>
