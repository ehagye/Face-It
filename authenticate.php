<?php
session_start();

// temp credentials
if (
    $_POST['email'] === 'professor@faceit.edu' &&
    $_POST['password'] === 'faceit123'
) {
    $_SESSION['user'] = [
        'email' => $_POST['email'],
        'role'  => 'professor'
    ];

    header("Location: index.php");
    exit();
}
// log in failed
$_SESSION['error'] = "Invalid email or password";
header("Location: login.php");
exit();