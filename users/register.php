<?php
// register.php - User Registration
include_once '../functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    // Add validation and sanitization here
    if ($username && $email && $password) {
        $hashed = password_hash($password, PASSWORD_DEFAULT);
        // Save to database (pseudo code)
        // register_user($username, $email, $hashed);
        echo 'Registration successful!';
    } else {
        echo 'Please fill all fields.';
    }
}
?>

