<?php
// reset_password.php - Password Reset
include_once '../functions.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    if ($email) {
        // Generate reset token and send email (pseudo code)
        // $token = generate_reset_token($email);
        // send_reset_email($email, $token);
        echo 'Password reset link sent!';
    } else {
        echo 'Please enter your email.';
    }
}
?>
<form method="post">
    Enter your email: <input type="email" name="email"><br>
    <input type="submit" value="Reset Password">
</form>
