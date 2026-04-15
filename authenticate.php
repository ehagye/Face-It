<?php
session_start();

// Basic safety check so this page can't be opened directly
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

// temp credentials
if (
    isset($_POST['email'], $_POST['password']) &&
    $_POST['email'] === 'professor@faceit.edu' &&
    $_POST['password'] === 'faceit123'
) {
    $_SESSION['user'] = [
        'email' => $_POST['email'],
        'role'  => 'professor'
    ];

    header("Location: main.php");
    exit;
}

// login failed
$_SESSION['error'] = "Invalid email or password";
header("Location: login.php");
exit;