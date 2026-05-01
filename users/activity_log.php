<?php
// activity_log.php - User Activity Log
include_once '../functions.php';
// Example: Display user activity log (pseudo code)
// $logs = get_user_activity_logs();
$logs = [
    ['user' => 'alice', 'action' => 'login', 'time' => '2026-04-30 10:00'],
    ['user' => 'bob', 'action' => 'logout', 'time' => '2026-04-30 10:05'],
];
?>
<h2>User Activity Log</h2>
<table border="1">
    <tr><th>User</th><th>Action</th><th>Time</th></tr>
    <?php foreach ($logs as $log) {
        echo "<tr><td>{$log['user']}</td><td>{$log['action']}</td><td>{$log['time']}</td></tr>";
    } ?>
</table>
