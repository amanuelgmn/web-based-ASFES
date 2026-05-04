<?php ob_start(); ?>
<?php
require_once __DIR__ . '/../config.php';
$user = require_login();
header('Location: ../profile.php');
exit;