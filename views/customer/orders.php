<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$orderController = new OrderController($db);
$orders = $orderController->myOrders($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Orders - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container">
    <h1>Order History</h1>
    <table class="data-table">
        <thead>
        <tr><th>Order ID</th><th>Date</th><th>Status</th><th>Total</th><th></th></tr>
        </thead>
        <tbody>
        <?php foreach ($orders as $o): ?>
            <tr>
                <td><strong>#<?= e($o['order_number']) ?></strong></td>
                <td><?= date('M d, Y', strtotime($o['order_at'])) ?></td>
                <td><span class="status-pill status-<?= e($o['status']) ?>"><?= strtoupper($o['status']) ?></span></td>
                <td>$<?= number_format($o['total_amount'], 2) ?></td>
                <td><a href="<?= BASE_URL ?>/views/customer/order_tracking.php?id=<?= $o['id'] ?>" class="link">Track</a></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($orders)): ?>
            <tr><td colspan="5" class="muted center">No orders yet.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</main>
</body>
</html>
