<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/AuthController.php';

$database = new Database();
$db = $database->connect();
$auth = new AuthController($db);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCsrf('logout_csrf')) {
	redirect('login.php');
}
$auth->logout();
redirect('login.php');
