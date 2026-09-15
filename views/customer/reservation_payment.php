<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/ReservationController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$reservationController = new ReservationController($db);
$reservationId = Validator::positiveInteger($_GET['id'] ?? $_POST['reservation_id'] ?? null);
$reservation = $reservationId === false ? null : $reservationController->reservation($reservationId, $_SESSION['user_id']);
if (!$reservation) {
    redirect('views/customer/reservation.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['pay_deposit'])) {
    $method = $_POST['payment_method'] ?? '';
    if (!in_array($method, ['esewa', 'khalti', 'cash'], true)) {
        $error = 'Select a valid payment method.';
    } else {
        $result = $reservationController->payDeposit($_SESSION['user_id'], $reservationId, $method);
        if ($result === 'pending_counter') {
            flash('success', 'Your deposit is marked for payment at the counter.');
            redirect('views/customer/reservation.php');
        } elseif ($result === 'paid') {
            flash('success', 'Reservation deposit paid successfully.');
            redirect('views/customer/reservation.php');
        }
        $error = 'The deposit could not be processed.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reservation Deposit - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>
<main class="container narrow">
    <div class="card">
        <h2>Reservation Deposit</h2>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <div class="row-between"><span>Table</span><strong><?= e($reservation['table_number']) ?> (<?= e($reservation['location']) ?>)</strong></div>
        <div class="row-between"><span>Date</span><strong><?= date('M d, Y', strtotime($reservation['reservation_date'])) ?></strong></div>
        <div class="row-between"><span>Time</span><strong><?= date('g:i A', strtotime($reservation['start_time'])) ?> - <?= date('g:i A', strtotime($reservation['end_time'])) ?></strong></div>
        <div class="row-between"><span>Guests</span><strong><?= (int)$reservation['guests'] ?></strong></div>
        <div class="row-between total-row"><strong>Deposit</strong><strong>$<?= number_format((float)$reservation['deposit_amount'], 2) ?></strong></div>
    </div>
    <form method="POST" class="card">
        <?= csrfField() ?>
        <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
        <h3>Choose payment method</h3>
        <div class="payment-methods">
            <label class="payment-option"><input type="radio" name="payment_method" value="esewa" checked><span>eSewa</span></label>
            <label class="payment-option"><input type="radio" name="payment_method" value="khalti"><span>Khalti</span></label>
            <label class="payment-option"><input type="radio" name="payment_method" value="cash"><span>Pay at counter (cash)</span></label>
        </div>
        <button type="submit" name="pay_deposit" class="btn-primary btn-block">Pay deposit</button>
    </form>
</main>
</body>
</html>