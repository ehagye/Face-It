<?php
session_start();

// TEMP credentials will replace with database later
$VALID_EMAIL = "professor@faceit.edu";
$VALID_PASSWORD = "faceit123";

$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';

if ($email === $VALID_EMAIL && $password === $VALID_PASSWORD) {
    $_SESSION['logged_in'] = true;
    $_SESSION['user_email'] = $email;

    header("Location: index.php");
    exit;
}

// login failed
$_SESSION['error'] = "Invalid email or password";
header("Location: login.php");
exit;
