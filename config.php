<?php
declare(strict_types=1);

date_default_timezone_set('Africa/Addis_Ababa');

const APP_NAME = 'ASFES';
const APP_BRAND = 'ASTU SFES';
const APP_SUBTITLE = 'Academic Student Feedback and Evaluation System';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/storage.php';

start_session();
