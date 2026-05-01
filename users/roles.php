<?php
// roles.php - User Role Management
include_once '../functions.php';
// Example: List roles and assign to users (pseudo code)
$roles = ['admin', 'student', 'department', 'student_affair'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_POST['user_id'] ?? '';
    $role = $_POST['role'] ?? '';
    if ($user_id && $role) {
        // assign_role_to_user($user_id, $role);
        echo 'Role assigned!';
    }
}
?>
<form method="post">
    User ID: <input type="text" name="user_id"><br>
    Role: <select name="role">
        <?php foreach ($roles as $role) { echo "<option value='$role'>$role</option>"; } ?>
    </select><br>
    <input type="submit" value="Assign Role">
</form>
