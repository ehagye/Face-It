<?php
session_start();

if (empty($_SESSION['user'])) {
    header("Location: home.php");
    exit;
}

$config = require __DIR__ . '/config.php';
$SUPABASE_URL = $config['SUPABASE_URL'];
$SUPABASE_KEY = $config['SUPABASE_KEY'];

// Grab and clear any success message from a redirect
$successMsg = $_SESSION['success'] ?? null;
unset($_SESSION['success']);

// Fetch all classes
$ch = curl_init("$SUPABASE_URL/rest/v1/classes?select=*&order=class_id");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer $SUPABASE_KEY",
        "apikey: $SUPABASE_KEY",
    ],
]);
$classesJson = curl_exec($ch);
curl_close($ch);

$classes = json_decode($classesJson, true) ?: [];
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

  <?php if ($successMsg): ?>
    <div class="glass-card success-banner">
      <?= htmlspecialchars($successMsg) ?>
    </div>
  <?php endif; ?>

  <section class="manage-grid">

    <div class="glass-card">
      <h3>Enroll New Class</h3>

      <form method="POST" action="create_class.php">
        <div class="form-inline">

          <div>
            <label class="subtitle">Class ID</label>
            <input type="text" name="class_id" placeholder="ICTW004" required>
          </div>

          <div>
            <label class="subtitle">Class Name</label>
            <input type="text" name="class_name" placeholder="Capstone ICTW" required>
          </div>

          <div>
            <label class="subtitle">Start Time</label>
            <input type="time" name="scheduled_start_time" required>
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
          <th>Class ID</th>
          <th>Class Name</th>
          <th>Start Time</th>
          <th>Actions</th>
        </tr>
      </thead>

      <tbody>
        <?php if (empty($classes)): ?>
          <tr><td colspan="4">No classes yet.</td></tr>
        <?php else: ?>
          <?php foreach ($classes as $c): ?>
            <tr>
              <td><?= htmlspecialchars($c['class_id']) ?></td>
              <td><?= htmlspecialchars($c['class_name']) ?></td>
              <td><?= htmlspecialchars($c['scheduled_start_time']) ?></td>
              <td>
                <a href="edit_class.php?class_id=<?= urlencode($c['class_id']) ?>"
                   class="nav-btn">Edit</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

</main>

</body>
</html>
