<?php
require_once __DIR__ . '/../config.php';
$user = require_role('admin');
flash_set('info', 'Use the Admin Console to manage roles.');
header('Location: users.php');
exit;
