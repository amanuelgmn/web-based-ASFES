<?php
require_once __DIR__ . '/../config.php';
$user = require_role('admin');
flash_set('info', 'Reset passwords from the Admin Console.');
header('Location: users.php');
exit;
