<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/AdminController.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$admin = new AdminController($db);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['reservation_id'], $_POST['reservation_status'])) {
    $admin->updateReservationStatus((int)$_POST['reservation_id'], $_POST['reservation_status']);
    redirect('views/admin/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf() && isset($_POST['reorder_item_id'])) {
    $admin->autoReorder((int)$_POST['reorder_item_id']);
    redirect('views/admin/dashboard.php');
}

$data = $admin->dashboardData();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Admin Console - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>

<main class="admin-main">
    <header class="admin-topbar">
        <h1>Admin Console</h1>
    </header>

    <div class="card">
        <div class="staff-toolbar"><div><h2>Role workspaces</h2><p class="muted small">Review the live workspace for each operating role.</p></div><a href="<?= BASE_URL ?>/views/admin/transactions.php" class="btn-secondary btn-small">Transaction oversight</a></div>
        <div class="category-row">
            <a class="btn-secondary btn-small" href="<?= BASE_URL ?>/views/staff/waiter.php">Waiter</a>
            <a class="btn-secondary btn-small" href="<?= BASE_URL ?>/views/staff/chef.php">Chef</a>
            <a class="btn-secondary btn-small" href="<?= BASE_URL ?>/views/staff/cashier.php">Cashier</a>
            <a class="btn-secondary btn-small" href="<?= BASE_URL ?>/views/staff/inventory.php">Inventory</a>
            <a class="btn-secondary btn-small" href="<?= BASE_URL ?>/views/staff/finance.php">Finance</a>
        </div>
    </div>

    <h2>Overview</h2>
    <p class="muted">Today's metrics and recent activity.</p>

    <div class="stat-grid">
        <div class="stat-card">
            <span class="muted small">Total Orders</span>
            <h2><?= (int)$data['order_stats']['total_orders'] ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Today's Revenue</span>
            <h2>$<?= number_format($data['order_stats']['revenue'], 2) ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Customers</span>
            <h2><?= (int)$data['customers'] ?></h2>
        </div>
        <div class="stat-card">
            <span class="muted small">Pending Orders</span>
            <h2><?= (int)$data['order_stats']['pending'] ?></h2>
        </div>
    </div>

    <div class="popular-items-grid">
        <div class="card">
            <h3>Most Ordered Today</h3>
            <?php foreach ($data['most_ordered_today'] as $item): ?>
                <div class="popular-item row-between">
                    <span><?= e($item['name']) ?></span>
                    <strong><?= (int)$item['quantity_ordered'] ?> ordered</strong>
                </div>
            <?php endforeach; ?>
            <?php if (empty($data['most_ordered_today'])): ?><p class="muted small">No orders placed today.</p><?php endif; ?>
        </div>

        <div class="card">
            <h3>Most Ordered This Week</h3>
            <?php foreach ($data['most_ordered_week'] as $item): ?>
                <div class="popular-item row-between">
                    <span><?= e($item['name']) ?></span>
                    <strong><?= (int)$item['quantity_ordered'] ?> ordered</strong>
                </div>
            <?php endforeach; ?>
            <?php if (empty($data['most_ordered_week'])): ?><p class="muted small">No orders placed this week.</p><?php endif; ?>
        </div>
    </div>

    <div class="admin-panels">
        <div class="card">
            <div class="row-between">
                <h3>Recent Orders</h3>
                <a href="<?= BASE_URL ?>/views/admin/orders.php" class="link">View All</a>
            </div>
            <table class="data-table">
                <thead><tr><th>Order ID</th><th>Customer</th><th>Amount</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($data['recent_orders'] as $o): ?>
                    <tr>
                        <td>#<?= e($o['order_number']) ?></td>
                        <td><?= e($o['customer_name']) ?></td>
                        <td>$<?= number_format($o['total_amount'], 2) ?></td>
                        <td><span class="status-pill status-<?= e($o['status']) ?>"><?= ucfirst($o['status']) ?></span></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (empty($data['recent_orders'])): ?><tr><td colspan="4" class="muted">No orders yet.</td></tr><?php endif; ?>
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3>⚠ Low Stock Alerts</h3>
            <?php foreach ($data['low_stock'] as $item): ?>
                <div class="row-between low-stock-line">
                    <span><?= e($item['name']) ?></span>
                    <span class="danger"><?= (int)$item['current_stock'] ?> left</span>
                </div>
            <?php endforeach; ?>
            <?php if (empty($data['low_stock'])): ?><p class="muted small">All stock levels healthy.</p><?php endif; ?>
        </div>

        <div class="card">
            <div class="row-between">
                <h3>📦 Automated Vendor Reordering</h3>
                <span class="muted small"><?= count($data['vendor_reorders']) ?> pending</span>
            </div>
            <?php foreach ($data['vendor_reorders'] as $item): ?>
                <div class="row-between low-stock-line">
                    <div>
                        <span><?= e($item['name']) ?></span>
                        <div class="muted small">Stock: <?= (int)$item['current_stock'] ?> / Reorder level: <?= (int)$item['reorder_level'] ?></div>
                    </div>
                    <form method="POST" style="display:inline">
                        <?= csrfField() ?>
                        <input type="hidden" name="reorder_item_id" value="<?= (int)$item['id'] ?>">
                        <button type="submit" class="btn-small btn-primary">Restock +<?= (int)$item['reorder_level'] + 10 ?></button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (empty($data['vendor_reorders'])): ?><p class="muted small">No vendor reorder needed right now.</p><?php endif; ?>
        </div>
    </div>

    <div class="card admin-wide-panel">
        <div class="row-between">
            <h3>Table Reservation Requests</h3>
            <span class="muted small"><?= count($data['reservations']) ?> total</span>
        </div>
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Table</th><th>Date</th><th>Time</th><th>Guests</th><th>Status</th><th>Action</th></tr></thead>
            <tbody>
            <?php foreach ($data['reservations'] as $reservation): ?>
                <tr>
                    <td>
                        <strong><?= e($reservation['customer_name']) ?></strong>
                        <div class="muted small"><?= e($reservation['customer_email']) ?></div>
                    </td>
                    <td>
                        <strong><?= e($reservation['table_number']) ?></strong>
                        <div class="muted small"><?= e($reservation['location']) ?></div>
                    </td>
                    <td><?= date('M d, Y', strtotime($reservation['reservation_date'])) ?></td>
                    <td><?= date('g:i A', strtotime($reservation['start_time'])) ?> - <?= date('g:i A', strtotime($reservation['end_time'])) ?></td>
                    <td><?= (int)$reservation['guests'] ?></td>
                    <td><span class="status-pill status-<?= e($reservation['status']) ?>"><?= $reservation['status'] === 'confirmed' ? 'Reserved' : ucfirst($reservation['status']) ?></span></td>
                    <td>
                        <?php if ($reservation['status'] === 'pending'): ?>
                            <form method="POST" class="reservation-actions">
                                <?= csrfField() ?>
                                <input type="hidden" name="reservation_id" value="<?= (int)$reservation['id'] ?>">
                                <button type="submit" name="reservation_status" value="confirmed" class="btn-small btn-confirm">Confirm Reservation</button>
                                <button type="submit" name="reservation_status" value="cancelled" class="btn-small btn-cancel">Cancel Reservation</button>
                                <button type="submit" name="reservation_status" value="pending" class="btn-small btn-pending">Keep Pending</button>
                            </form>
                        <?php else: ?>
                            <span class="muted small">No action</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($data['reservations'])): ?><tr><td colspan="7" class="muted center">No table reservation requests yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card admin-wide-panel">
        <div class="row-between">
            <h3>Promo Code Claims</h3>
            <span class="muted small"><?= count($data['promo_claims']) ?> claimed</span>
        </div>
        <table class="data-table">
            <thead><tr><th>Customer</th><th>Promo Code</th><th>Discount</th><th>Source</th><th>Claimed</th></tr></thead>
            <tbody>
            <?php foreach ($data['promo_claims'] as $claim): ?>
                <tr>
                    <td>
                        <strong><?= e($claim['customer_name']) ?></strong>
                        <div class="muted small"><?= e($claim['customer_email']) ?></div>
                    </td>
                    <td><code class="promo-code"><?= e($claim['promo_code']) ?></code></td>
                    <td><?= (int)$claim['discount_percent'] ?>%</td>
                    <td><span class="source-badge">Verified: <?= e($claim['shop_name']) ?></span></td>
                    <td><?= date('M d, Y g:i A', strtotime($claim['claimed_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($data['promo_claims'])): ?><tr><td colspan="5" class="muted center">No promo codes claimed yet.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</main>
</body>
</html>
