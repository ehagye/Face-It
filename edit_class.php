<?php
session_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (empty($_SESSION['user'])) {
    header("Location: home.php");
    exit;
}

$config = require __DIR__ . '/config.php';
$SUPABASE_URL = $config['SUPABASE_URL'];
$SUPABASE_KEY = $config['SUPABASE_KEY'];

$baseHeaders = [
    "Authorization: Bearer $SUPABASE_KEY",
    "apikey: $SUPABASE_KEY",
    "Content-Type: application/json",
];

// Handle POST: save edits
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['class_id']) || $_POST['class_id'] === '') {
        die("Missing class_id");
    }

    $class_id = trim($_POST['class_id']);

    $updateData = [
        "class_name"           => trim($_POST['class_name']),
        "scheduled_start_time" => trim($_POST['scheduled_start_time']),
    ];

    $ch = curl_init("$SUPABASE_URL/rest/v1/classes?class_id=eq." . urlencode($class_id));
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PATCH",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_merge($baseHeaders, ["Prefer: return=minimal"]),
        CURLOPT_POSTFIELDS => json_encode($updateData),
    ]);
    $response = curl_exec($ch);
    $status   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($status !== 204) {
        echo "<h3>Update failed (status: $status)</h3>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        exit;
    }

    $_SESSION['success'] = "Class updated!";
    header("Location: manage_classes.php");
    exit;
}

// Handle GET: load class and show form
if (!isset($_GET['class_id']) || $_GET['class_id'] === '') {
    die("Missing class_id");
}
$class_id = trim($_GET['class_id']);

$ch = curl_init("$SUPABASE_URL/rest/v1/classes?class_id=eq." . urlencode($class_id) . "&select=*");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $baseHeaders,
]);
$response = curl_exec($ch);
curl_close($ch);

$rows = json_decode($response, true);
if (empty($rows)) die("Class not found");
$class = $rows[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Edit Class - Face-IT</title>
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
  <h2 class="gradient-text">Edit Class</h2>
  <p class="subtitle">Update class details</p>

  <section class="glass-card">
    <form method="POST" action="edit_class.php">
      <input type="hidden" name="class_id" value="<?= htmlspecialchars($class['class_id']) ?>">

      <div class="form-inline">
        <div>
          <label class="subtitle">Class ID (read-only)</label>
          <input type="text" value="<?= htmlspecialchars($class['class_id']) ?>" disabled>
        </div>

        <div>
          <label class="subtitle">Class Name</label>
          <input type="text" name="class_name"
                 value="<?= htmlspecialchars($class['class_name']) ?>" required>
        </div>

        <div>
          <label class="subtitle">Start Time</label>
          <input type="time" name="scheduled_start_time"
                 value="<?= htmlspecialchars($class['scheduled_start_time']) ?>" required>
        </div>

        <button type="submit">Save</button>
        <a href="manage_classes.php" class="nav-btn">Cancel</a>
      </div>
    </form>
  </section>
</main>

</body>
</html>