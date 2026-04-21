<?php
session_start();
// ... your config load ...

if (
    empty($_POST['class_id']) ||
    empty($_POST['class_name']) ||
    empty($_POST['scheduled_start_time'])
) {
    die("Missing required fields");
}

$classData = [
    "class_id"             => (int) $_POST['class_id'],
    "class_name"           => trim($_POST['class_name']),
    "scheduled_start_time" => trim($_POST['scheduled_start_time']), // e.g. "09:30:00"
    "professor_id"         => !empty($_POST['professor_id']) ? (int) $_POST['professor_id'] : null,
];