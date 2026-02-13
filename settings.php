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
        <button onclick="goToDashboard()">Dashboard</button>
        <button onclick="goToAlerts()">Alerts</button>
        <button class="active-nav">Settings</button>
        <button onclick="logout()">Log Out</button>
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

<script src="script.js"></script>
<script>protectPage();</script>
</body>
</html>
