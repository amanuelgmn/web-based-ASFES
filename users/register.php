<?php
require_once __DIR__ . '/../config.php';
$user = require_role('admin');
flash_set('info', 'Use the Admin Console to create new users.');
header('Location: users.php');
exit;
