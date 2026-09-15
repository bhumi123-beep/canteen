<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
require_once __DIR__ . '/../../controllers/ReservationController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$userModel = new User($db);
$user = $userModel->findById($_SESSION['user_id']);
$orderController = new OrderController($db);
$orders = array_slice($orderController->myOrders($_SESSION['user_id']), 0, 4);
$reservationController = new ReservationController($db);
$reservations = array_slice($reservationController->myReservations($_SESSION['user_id']), 0, 3);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Profile - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container">
    <div class="profile-header card">
        <div class="avatar-circle lg">👤</div>
        <div>
            <h1><?= e($user['name']) ?></h1>
            <p class="muted"><?= e($user['email']) ?></p>
        </div>
    </div>

    <div class="profile-layout">
        <div class="card">
            <h3>Order History</h3>
            <table class="data-table">
                <thead><tr><th>Order</th><th>Date</th><th>Status</th><th>Total</th></tr></thead>
                <tbody>
                <?php foreach ($orders as $o): ?>
                    <tr>
                        <td>#<?= e($o['order_number']) ?></td>
                        <td><?= date('M d, Y', strtotime($o['order_at'])) ?></td>
                        <td><span class="status-pill status-<?= e($o['status']) ?>"><?= strtoupper($o['status']) ?></span></td>
                        <td>$<?= number_format($o['total_amount'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($orders)): ?><tr><td colspan="4" class="muted">No orders yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div>
            <div class="card">
                <h3>🪑 Reservations</h3>
                <?php foreach ($reservations as $r): ?>
                    <div class="reservation-line">
                        <strong>Table <?= e($r['table_number']) ?> (<?= e($r['location']) ?>)</strong>
                        <div class="muted small"><?= date('M d', strtotime($r['reservation_date'])) ?>, <?= date('g:i A', strtotime($r['start_time'])) ?></div>
                        <span class="status-pill status-<?= e($r['status']) ?>">
                            <?= $r['status'] === 'confirmed' ? 'Reserved' : ($r['status'] === 'completed' ? 'TO BE SERVED' : strtoupper($r['status'])) ?>
                        </span>
                    </div>
                <?php endforeach; ?>
                <?php if (empty($reservations)): ?><p class="muted small">No reservations yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</main>
</body>
</html>
