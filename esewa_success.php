<?php
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/controllers/EsewaController.php';

requireCashierPayments();
$database = new Database();
$controller = new EsewaController($database->connect());
$result = $controller->complete($_GET['data'] ?? $_POST['data'] ?? '');
if (!$result['success']) {
    $_SESSION['payment_error'] = $result['message'];
    redirect('views/staff/cashier.php');
}
redirect('views/staff/payment_receipt.php?uuid=' . rawurlencode($result['transaction']['transaction_uuid']));
