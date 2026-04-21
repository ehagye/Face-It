<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: home.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Manage Classes - Face-IT</title>
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

<main class="manage-container">

  <h2 class="gradient-text">Manage Classes</h2>
  <p class="subtitle">Create, edit, or remove courses</p>

  <section class="manage-grid">

    <div class="glass-card">
      <h3>Enroll New Class</h3>

      <form method="POST" action="/face-it/create_class.php">
        <div class="form-inline">

          <div>
            <label class="subtitle">CRN</label>
            <input type="text" name="crn" placeholder="12345" required>
          </div>

          <div>
            <label class="subtitle">Class ID</label>
            <input type="text" name="class_id" placeholder="ICTW004" required>
          </div>

          <div>
            <label class="subtitle">Class Name</label>
            <input type="text" name="class_name" placeholder="Capstone ICTW" required>
          </div>

          <button type="submit">Enroll</button>

        </div>
      </form>
    </div>

    <div class="glass-card">
      <h3>Delete Class</h3>

      <form class="manage-form" method="POST" action="delete_class.php">
        <div class="form-inline small">

          <div>
            <label class="subtitle">Class ID</label>
            <input type="text" name="class_id" placeholder="ICTW004" required>
          </div>

          <button type="submit" class="danger-btn">Delete</button>

        </div>
      </form>
    </div>

  </section>

  <section class="glass-card table-wrap">
    <h3>Current Classes</h3>

    <table class="class-table">
      <thead>
        <tr>
          <th>CRN</th>
          <th>Class ID</th>
          <th>Class Name</th>
        </tr>
      </thead>

      <tbody>
        <tr>
          <td>12345</td>
          <td>ICTW004</td>
          <td>Capstone ICTW</td>
        </tr>
      </tbody>
    </table>
  </section>

</main>

</body>
</html>