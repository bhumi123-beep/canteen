<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/EsewaController.php';

requireCashierPayments();
$database = new Database();
$controller = new EsewaController($database->connect());
$transaction = $controller->transaction($_GET['uuid'] ?? '');
if (!$transaction || $transaction['status'] !== 'PAID') {
    $_SESSION['payment_error'] = 'This receipt is not available because the payment was not confirmed.';
    redirect('views/staff/cashier.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>eSewa Receipt - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_staff.php'; ?>
<main class="admin-main">
    <header class="admin-topbar row-between">
        <div><p class="muted small">PAYMENT RECEIPT</p><h1>Payment confirmed</h1><p class="muted">eSewa transaction <?= e($transaction['transaction_uuid']) ?></p></div>
        <button type="button" class="btn-secondary btn-small" onclick="window.print()">Print receipt</button>
    </header>
    <section class="card">
        <div class="row-between"><strong>CanteenPro</strong><span class="status-pill status-ready">PAID</span></div>
        <hr>
        <div class="row-between"><span>Order</span><strong>#<?= e($transaction['order_number']) ?></strong></div>
        <div class="row-between"><span>Table</span><strong><?= e($transaction['table_number'] ?: 'Takeaway') ?></strong></div>
        <div class="row-between"><span>Amount</span><strong>$<?= number_format((float)$transaction['amount'], 2) ?></strong></div>
        <div class="row-between"><span>Payment reference</span><strong><?= e($transaction['payment_ref']) ?></strong></div>
        <div class="row-between"><span>Paid at</span><strong><?= e($transaction['created_at']) ?></strong></div>
        <hr>
        <p class="muted small">Payment independently confirmed with eSewa transaction status API.</p>
        <div class="card"><h2>Would you like to rate your experience?</h2><a class="btn-primary" href="<?= BASE_URL ?>/views/customer/review.php?token=<?= rawurlencode($transaction['public_review_token']) ?>&mode=rate">Rate now</a> <a class="btn-secondary" href="<?= BASE_URL ?>/views/customer/review.php?token=<?= rawurlencode($transaction['public_review_token']) ?>&mode=skip">Skip</a></div>
        <a href="<?= BASE_URL ?>/views/staff/cashier.php" class="btn-primary">Back to Cashier workspace</a>
    </section>
</main>
</body>
</html>
