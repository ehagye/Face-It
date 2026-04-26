<?php
session_start();

// Optional: Only allow logged-in professors to enroll
if (empty($_SESSION['user'])) {
    // You can remove this if enrollment is public
    // header("Location: home.php");
    // exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Face-IT · Student Enrollment</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-bg">

<main class="dashboard centered-content">
    <section class="glass-card enroll-card">
        <h2>Student Enrollment</h2>
        <p class="subtitle">Register a student for facial recognition attendance</p>

        <form class="enroll-form" method="POST" action="process_enroll.php" enctype="multipart/form-data">
            <div class="form-row">
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
            </div>

            <input type="text" name="student_id" placeholder="Student ID" required>

            <label>Professor</label>
            <input list="professors" name="professor" placeholder="Search professor..." required>
            <datalist id="professors">
                <option value="Dr. William G. Johnson">
                <option value="Dr. Sarah Nguyen">
                <option value="Dr. Michael Chen">
            </datalist>

            <label>Class</label>
            <input list="classes" name="class" placeholder="Search class..." required>
            <datalist id="classes">
                <option value="Data Science 101">
                <option value="Capstone ICTW 004">
                <option value="Machine Learning 301">
            </datalist>

            <h3>Live Face Capture</h3>
            <div class="camera-wrapper">
                <video id="video" autoplay playsinline></video>
                <canvas id="canvas" style="display:none;"></canvas>
                <div id="successOverlay" class="success-overlay">✓</div>
            </div>

            <div class="camera-actions">
                <button type="button" onclick="startCamera()">Start Camera</button>
                <button type="button" onclick="capturePhoto()">Capture</button>
            </div>

            <p id="camera-status" class="subtitle"></p>

            <button class="primary-btn full-width" type="submit">Enroll Student</button>
        </form>
    </section>
</main>

<script src="script.js"></script>
</body>
</html>