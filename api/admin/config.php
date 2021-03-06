<?php
chdir(dirname(__FILE__));
//ini_set("display_errors", 1);
//error_reporting(E_WARNING);

// Import libraries (Slim framework, PHPMailer, etc)
require '../vendor/autoload.php';

// Import models
require '../classes/DB.php';
require '../classes/User.php';
require '../classes/Team.php';
require '../classes/Match.php';
require '../classes/Bet.php';
require '../classes/Stats.php';
require '../classes/Token.php';

date_default_timezone_set('Europe/Lisbon');

define('STATIC_URL', 'https://static.soccerwars.xyz');

$db = new DB();
