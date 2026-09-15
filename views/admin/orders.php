<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$orderController = new OrderController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_id']) && verifyCsrf()) {
    $orderController->updateStatus((int)$_POST['order_id'], $_POST['status']);
    redirect('views/admin/orders.php');
}

$orders = $orderController->recent(50);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Orders - CanteenPro Admin</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar"><h1>Orders</h1></header>
    <p class="muted">Manage and update the status of incoming orders.</p>

    <div class="card">
        <table class="data-table">
            <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Placed</th><th>Status</th><th>Update</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td>#<?= e($o['order_number']) ?></td>
                    <td><?= e($o['customer_name']) ?></td>
                    <td>$<?= number_format($o['total_amount'], 2) ?></td>
                    <td><?= date('M d, g:i A', strtotime($o['order_at'])) ?></td>
                    <td><span class="status-pill status-<?= e($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
                    <td>
                        <form method="POST" class="inline-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                            <select name="status" onchange="this.form.submit()">
                                <?php foreach (['pending','preparing','ready','completed','cancelled'] as $s): ?>
                                    <option value="<?= $s ?>" <?= $o['status'] === $s ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($orders)): ?><tr><td colspan="6" class="muted center">No orders yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
