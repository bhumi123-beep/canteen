<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/EsewaController.php';

requireCashierPayments();
$database = new Database();
$controller = new EsewaController($database->connect());
$result = $controller->fail($_GET['data'] ?? $_POST['data'] ?? '');
if ($result['success']) {
	redirect('views/staff/payment_receipt.php?uuid=' . rawurlencode($result['transaction']['transaction_uuid']));
}
$_SESSION['payment_error'] = 'The eSewa payment was cancelled or failed. No order amount was recorded as paid.';
redirect('views/staff/cashier.php');
