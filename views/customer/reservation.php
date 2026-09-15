<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/ReservationController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$reservationController = new ReservationController($db);

$date = Validator::date($_GET['date'] ?? date('Y-m-d')) ?: date('Y-m-d');
$time = Validator::time($_GET['time'] ?? '12:00') ?: '12:00:00';
$guests = Validator::integer($_GET['guests'] ?? 2, 1, 8) ?: 2;
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    $date = Validator::date($_POST['date'] ?? null);
    $time = Validator::time($_POST['time'] ?? null);
    $guests = Validator::integer($_POST['guests'] ?? null, 1, 8);
    $tableId = Validator::tableId($_POST['table_id'] ?? null);
    if (isset($_POST['confirm']) && $date !== false && $time !== false && $guests !== false && $tableId !== false) {
        $newReservationId = $reservationController->book($_SESSION['user_id'], $tableId, $date, $time, $guests);
        if ($newReservationId) {
            flash('success', 'Table reserved successfully!');
            redirect('views/customer/reservation_payment.php?id=' . $newReservationId);
        }
        $message = 'That table is unavailable, too small, or the reservation details are invalid.';
    }
    if (isset($_POST['cancel_reservation'])) {
        $reservationId = Validator::positiveInteger($_POST['reservation_id'] ?? null);
        $outcome = $reservationId === false ? false : $reservationController->cancel($_SESSION['user_id'], $reservationId);
        if ($outcome === 'refunded') {
            flash('success', 'Reservation cancelled. Your deposit will be refunded.');
        } elseif ($outcome === 'forfeited') {
            flash('success', 'Reservation cancelled. The deposit was forfeited because it was cancelled within the free-cancel window.');
        } elseif ($outcome === 'no_charge') {
            flash('success', 'Reservation cancelled with no charge.');
        } else {
            flash('error', 'That reservation could not be cancelled.');
        }
        redirect('views/customer/reservation.php');
    }
}

$tables = $reservationController->availableTables($date, $time);
$myReservations = $reservationController->myReservations($_SESSION['user_id']);
$flashSuccess = flash('success');
$flashError = flash('error');
$myConfirmedTableIds = [];
foreach ($myReservations as $reservation) {
    $selectedTime = strtotime($date . ' ' . $time . ':00');
    $reservationStart = strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']);
    $reservationEnd = strtotime($reservation['reservation_date'] . ' ' . $reservation['end_time']);
    if ($reservation['status'] === 'confirmed' && $selectedTime >= $reservationStart && $selectedTime < $reservationEnd) {
        $myConfirmedTableIds[] = (int)$reservation['table_id'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reservation - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container">
    <div class="reservation-layout">
        <div class="card">
            <h2>Book a Table</h2>
            <p class="muted small">Select your details to view available tables.</p>
            <p class="muted small">A $<?= number_format(Reservation::DEPOSIT_AMOUNT, 2) ?> deposit is required. Paid deposits are refundable when cancelled at least <?= Reservation::FREE_CANCEL_HOURS ?> hours before the booking.</p>
            <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
            <?php if ($flashError): ?><div class="alert alert-error"><?= e($flashError) ?></div><?php endif; ?>
            <form method="GET" id="filter-form">
                <label>Date</label>
                <input type="date" name="date" value="<?= e($date) ?>" onchange="document.getElementById('filter-form').submit()">

                <label>Time</label>
                <input type="time" name="time" value="<?= e(substr($time, 0, 5)) ?>" onchange="document.getElementById('filter-form').submit()">

                <label>Guests</label>
                <select name="guests" onchange="document.getElementById('filter-form').submit()">
                    <?php for ($i = 1; $i <= 8; $i++): ?>
                        <option value="<?= $i ?>" <?= $guests == $i ? 'selected' : '' ?>><?= $i ?> People</option>
                    <?php endfor; ?>
                </select>
            </form>

            <form method="POST" id="book-form">
                <?= csrfField() ?>
                <input type="hidden" name="date" value="<?= e($date) ?>">
                <input type="hidden" name="time" value="<?= e($time) ?>">
                <input type="hidden" name="guests" value="<?= e($guests) ?>">
                <input type="hidden" name="table_id" id="selected-table-id" value="">
                <div class="row-between" style="margin-top:20px;">
                    <span class="muted">Selected Table</span>
                    <strong id="selected-table-label">None</strong>
                </div>
                <button type="submit" name="confirm" class="btn-primary btn-block" id="confirm-btn" disabled>Confirm Reservation</button>
            </form>

            <div class="my-reservations">
                <h3>My Reservations</h3>
                <?php if (empty($myReservations)): ?>
                    <p class="muted small">You have no table reservations yet.</p>
                <?php else: ?>
                    <?php foreach ($myReservations as $reservation): ?>
                        <div class="reservation-line">
                            <strong>Table <?= e($reservation['table_number']) ?> (<?= e($reservation['location']) ?>)</strong>
                            <div class="muted small">
                                <?= date('M d, Y', strtotime($reservation['reservation_date'])) ?>,
                                <?= date('g:i A', strtotime($reservation['start_time'])) ?> - <?= date('g:i A', strtotime($reservation['end_time'])) ?>
                            </div>
                            <span class="status-pill status-<?= e($reservation['status']) ?>">
                                <?= $reservation['status'] === 'confirmed' ? 'Reserved' : ucfirst($reservation['status']) ?>
                            </span>
                            <div class="muted small">Deposit: $<?= number_format((float)$reservation['deposit_amount'], 2) ?>
                                <span class="status-pill status-<?= e($reservation['payment_status']) ?>">
                                    <?= ['unpaid' => 'Deposit due', 'paid' => 'Deposit paid', 'refunded' => 'Deposit refunded', 'forfeited' => 'Deposit forfeited'][$reservation['payment_status']] ?? ucfirst($reservation['payment_status']) ?>
                                </span>
                            </div>
                            <?php if ($reservation['payment_status'] === 'unpaid' && !in_array($reservation['status'], ['cancelled', 'completed'], true)): ?>
                                <a class="link" href="<?= BASE_URL ?>/views/customer/reservation_payment.php?id=<?= (int)$reservation['id'] ?>">Pay deposit</a>
                            <?php endif; ?>
                            <?php if (!in_array($reservation['status'], ['cancelled', 'completed'], true)): ?>
                                <a class="btn-secondary btn-small" style="display:inline-block; margin-top:6px;" href="<?= BASE_URL ?>/views/customer/home.php?table=<?= rawurlencode($reservation['table_number']) ?>#menu">🍽️ Pre-order Food</a>
                                <?php $hoursUntil = (strtotime($reservation['reservation_date'] . ' ' . $reservation['start_time']) - time()) / 3600; ?>
                                <form method="POST" style="margin-top:8px;" onsubmit="return confirm('<?= $hoursUntil < Reservation::FREE_CANCEL_HOURS ? 'This cancellation is within the free-cancel window and a paid deposit may be forfeited. ' : '' ?>Cancel this reservation?');">
                                    <?= csrfField() ?>
                                    <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                    <button type="submit" name="cancel_reservation" class="btn-secondary btn-small">Cancel reservation</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card">
            <div class="row-between">
                <h3>Floor Plan</h3>
                <div class="legend">
                    <span><i class="dot available"></i> Available</span>
                    <span><i class="dot reserved"></i> Reserved</span>
                    <span><i class="dot my-reserved-dot"></i> Reserved by you</span>
                </div>
            </div>
            <div class="floor-plan">
                <?php foreach ($tables as $t): ?>
                    <?php 
                    $isMyConfirmedTable = in_array((int)$t['id'], $myConfirmedTableIds, true);
                    $tooSmall = (int)$t['capacity'] < $guests;
                    $tileClass = $isMyConfirmedTable ? 'my-reserved' : ($t['reserved'] ? 'reserved' : ($tooSmall ? 'too-small' : 'available'));
                    ?>
                    <div class="table-tile <?= $tileClass ?>"
                         data-id="<?= $t['id'] ?>" data-label="Table <?= e($t['table_number']) ?> (<?= $t['capacity'] ?> seats)">
                        🪑<br>Table <?= e($t['table_number']) ?>
                        <small>(<?= (int)$t['capacity'] ?> seats)</small>
                        <?php if ($isMyConfirmedTable): ?><small>Reserved by you</small>
                        <?php elseif ($tooSmall): ?><small>Too small for <?= (int)$guests ?></small><?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>
<script>
document.querySelectorAll('.table-tile.available').forEach(tile => {
    tile.addEventListener('click', () => {
        document.querySelectorAll('.table-tile').forEach(t => t.classList.remove('selected'));
        tile.classList.add('selected');
        document.getElementById('selected-table-id').value = tile.dataset.id;
        document.getElementById('selected-table-label').innerText = tile.dataset.label;
        document.getElementById('confirm-btn').disabled = false;
    });
});
</script>
</body>
</html>
