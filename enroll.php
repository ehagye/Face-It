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
    <title>Face-IT · Student Enrollment</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="dark-bg">

<main class="dashboard centered-content">

    <section class="glass-card enroll-card">
        <h2>Student Enrollment</h2>
        <p class="subtitle">Register a student for facial recognition attendance</p>

        <form class="enroll-form"
              method="POST"
              action="process_enroll.php"
              enctype="multipart/form-data">

            <!-- NAME -->
            <div class="form-row">
                <input type="text" name="first_name" placeholder="First Name" required>
                <input type="text" name="last_name" placeholder="Last Name" required>
            </div>

            <!-- STUDENT ID -->
            <input type="text" name="student_id" placeholder="Student ID" required>

            <!-- PROFESSOR -->
            <label>Professor</label>
            <input list="professors" name="professor" placeholder="Search professor..." required>

            <datalist id="professors">
                <option value="Dr. William G. Johnson">
                <option value="Dr. Sarah Nguyen">
                <option value="Dr. Michael Chen">
            </datalist>

            <!-- CLASS -->
            <label>Class</label>
            <input list="classes" name="class" placeholder="Search class..." required>

            <datalist id="classes">
                <option value="Data Science 101">
                <option value="Capstone ICTW 004">
                <option value="Machine Learning 301">
            </datalist>

            <!-- IMAGE UPLOAD -->
            <label>Upload Face Images</label>
            <input type="file" name="faces[]" multiple accept="image/*" required>

            <!-- SUBMIT -->
            <button class="primary-btn full-width" type="submit">
                Enroll Student
            </button>

            <p class="hint">
                * Images will be used to train the recognition model
            </p>
        </form>
    </section>

</main>

<script src="script.js"></script>
</body>
</html>