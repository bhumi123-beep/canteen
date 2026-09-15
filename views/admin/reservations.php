<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/ReservationController.php';
requireReservationManagement();

$database = new Database();
$db = $database->connect();
$reservationController = new ReservationController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (isset($_POST['admin_update_reservation'])) {
        $updated = $reservationController->adminUpdate(
            (int)($_POST['reservation_id'] ?? 0),
            $_POST['table_id'] ?? 0,
            $_POST['reservation_date'] ?? '',
            $_POST['start_time'] ?? '',
            $_POST['end_time'] ?? '',
            $_POST['guests'] ?? 0,
            $_POST['status'] ?? ''
        );
        flash($updated ? 'success' : 'error', $updated ? 'Reservation updated.' : 'Reservation could not be updated.');
    } elseif (isset($_POST['admin_cancel_reservation'])) {
        $outcome = $_POST['payment_outcome'] ?? 'unpaid';
        $cancelled = $reservationController->adminCancel((int)($_POST['reservation_id'] ?? 0), $outcome);
        flash($cancelled ? 'success' : 'error', $cancelled ? 'Reservation cancelled.' : 'Reservation could not be cancelled.');
    }
    redirect('views/admin/reservations.php');
}

$flashSuccess = flash('success');
$flashError = flash('error');
$reservations = $reservationController->adminAll();
$tables = $reservationController->allTables();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Reservations - CanteenPro Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>
<main class="admin-main">
    <header class="admin-topbar"><h1>Reservations</h1></header>
    <p class="muted">Manage table bookings, deposits, and cancellation outcomes.</p>
    <?php if ($flashSuccess): ?><div class="alert alert-success"><?= e($flashSuccess) ?></div><?php endif; ?>
    <?php if ($flashError): ?><div class="alert alert-error"><?= e($flashError) ?></div><?php endif; ?>
    <div class="card">
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Table</th><th>Date/time</th><th>Guests</th><th>Status</th><th>Deposit</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach ($reservations as $reservation): ?>
                <tr>
                    <td><?= e($reservation['customer_name']) ?><div class="muted small"><?= e($reservation['customer_email']) ?></div></td>
                    <td><?= e($reservation['table_number']) ?> <span class="muted small"><?= e($reservation['location']) ?></span></td>
                    <td><?= e($reservation['reservation_date']) ?><div class="muted small"><?= date('g:i A', strtotime($reservation['start_time'])) ?> - <?= date('g:i A', strtotime($reservation['end_time'])) ?></div></td>
                    <td><?= (int)$reservation['guests'] ?></td>
                    <td><span class="status-pill status-<?= e($reservation['status']) ?>"><?= e(ucfirst($reservation['status'])) ?></span></td>
                    <td>$<?= number_format((float)$reservation['deposit_amount'], 2) ?><div class="muted small"><?= e($reservation['payment_status']) ?><?= $reservation['payment_method'] ? ' / ' . e($reservation['payment_method']) : '' ?></div></td>
                    <td>
                        <?php if (!in_array($reservation['status'], ['cancelled', 'completed'], true)): ?>
                            <form method="POST" class="grid-form" style="min-width:360px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                <select name="table_id" required>
                                    <?php foreach ($tables as $table): ?><option value="<?= (int)$table['id'] ?>" <?= (int)$table['id'] === (int)$reservation['table_id'] ? 'selected' : '' ?>><?= e($table['table_number']) ?> - <?= e($table['capacity']) ?> seats - <?= e($table['location']) ?></option><?php endforeach; ?>
                                </select>
                                <input type="date" name="reservation_date" value="<?= e($reservation['reservation_date']) ?>" required>
                                <input type="time" name="start_time" value="<?= e(substr($reservation['start_time'], 0, 5)) ?>" required>
                                <input type="time" name="end_time" value="<?= e(substr($reservation['end_time'], 0, 5)) ?>" required>
                                <input type="number" name="guests" min="1" max="20" value="<?= (int)$reservation['guests'] ?>" required>
                                <select name="status" required><?php foreach (['pending', 'confirmed', 'completed', 'cancelled'] as $status): ?><option value="<?= $status ?>" <?= $reservation['status'] === $status ? 'selected' : '' ?>><?= ucfirst($status) ?></option><?php endforeach; ?></select>
                                <button type="submit" name="admin_update_reservation" class="btn-small btn-primary">Save edit</button>
                            </form>
                            <form method="POST" class="inline-form" style="margin-top:8px;">
                                <?= csrfField() ?>
                                <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                <?php if ($reservation['payment_status'] === 'paid'): ?><select name="payment_outcome"><option value="refunded">Refund deposit</option><option value="forfeited">Forfeit deposit</option></select><?php else: ?><input type="hidden" name="payment_outcome" value="unpaid"><?php endif; ?>
                                <button type="submit" name="admin_cancel_reservation" class="btn-small btn-secondary" onclick="return confirm('Cancel this reservation?');">Cancel reservation</button>
                            </form>
                        <?php else: ?><span class="muted small">No actions</span><?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$reservations): ?><tr><td colspan="7" class="muted center">No reservations yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>