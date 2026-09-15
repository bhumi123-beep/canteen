<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/StaffWorkspace.php';
requireStaff();

if (staffDashboardPath() !== 'views/staff/dashboard.php') {
    redirect(staffDashboardPath());
}

$database = new Database();
$db = $database->connect();
$workspace = new StaffWorkspace($db);
$staff = $workspace->currentStaff($_SESSION['user_id']);
if (!$staff) {
    redirect('staff_login.php');
}

if (empty($_SESSION['staff_workspace_csrf'])) {
    $_SESSION['staff_workspace_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['staff_workspace_csrf'];
$message = '';
$error = '';
$role = currentStaffRole();
$canQueue = staffHasRole(['cook', 'waiter', 'cashier']);
$isInventory = staffHasRole(['inventory']);
$isFinance = staffHasRole(['finance']);
$financePeriod = $_GET['period'] ?? 'month';
if (!in_array($financePeriod, ['today', 'week', 'month'], true)) {
    $financePeriod = 'month';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Please refresh and try again.';
    } elseif (isset($_POST['task_add'])) {
        $message = $workspace->addTask($staff['id'], $_POST['task_title'] ?? '', $_POST['task_due_at'] ?? '') ? 'Task added.' : 'Enter a task title.';
    } elseif (isset($_POST['task_toggle'])) {
        $workspace->toggleTask($staff['id'], $_POST['task_id'] ?? 0);
        $message = 'Task updated.';
    } elseif (isset($_POST['queue_status']) && $canQueue) {
        $message = $workspace->updateQueueStatus($_POST['order_id'] ?? 0, $staff['id'], $role, $_POST['queue_status']) ? 'Order status updated.' : 'That order cannot be moved to the selected status.';
    } elseif (isset($_POST['delivery_add']) && $isInventory) {
        $message = $workspace->addDelivery($_POST['supplier'] ?? '', $_POST['items_received'] ?? '', $_POST['quantity'] ?? 0) ? 'Delivery added.' : 'Enter a supplier, item description, and quantity.';
    } elseif (isset($_POST['delivery_receive']) && $isInventory) {
        $workspace->markDeliveryReceived($_POST['delivery_id'] ?? 0, $staff['id']);
        $message = 'Delivery marked as received.';
    } elseif (isset($_POST['cost_add']) && $isFinance) {
        $message = $workspace->addCost($_POST['category'] ?? '', $_POST['department'] ?? '', $_POST['amount'] ?? 0, $_POST['cost_date'] ?? '', $_POST['notes'] ?? '') ? 'Cost recorded.' : 'Enter valid cost details.';
    }
}

$tasks = $workspace->tasks($staff['id']);
$queue = $canQueue ? $workspace->queue() : [];
$deliveries = $isInventory ? $workspace->deliveries() : [];
$today = date('Y-m-d');
$periodStart = $financePeriod === 'today' ? $today : ($financePeriod === 'week' ? date('Y-m-d', strtotime('monday this week')) : date('Y-m-01'));
$finance = $isFinance ? $workspace->financeSummary($periodStart, $today) : null;
$costs = $isFinance ? $workspace->costs($periodStart, $today) : [];
$reviews = $workspace->reviews($staff['id']);
$rating = (float)($staff['performance_average'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Staff Workspace - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_staff.php'; ?>
<main class="admin-main">
    <header class="admin-topbar row-between">
        <div>
            <p class="muted small">STAFF WORKSPACE / <?= e(strtoupper($staff['department'] ?? 'Operations')) ?></p>
            <h1>Good morning, <?= e($staff['staff_name']) ?>.</h1>
            <p class="muted"><?= e($staff['staff_role']) ?> · <?= e($staff['staff_shift']) ?> shift</p>
        </div>
        <a href="<?= BASE_URL ?>/views/staff/profile.php" class="btn-secondary btn-small">My profile</a>
    </header>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card"><span class="muted small">My rating</span><h2><?= number_format($rating, 1) ?> / 5</h2><span class="muted small"><?= count($reviews) ?> review(s)</span></div>
        <div class="stat-card"><span class="muted small">Open tasks</span><h2><?= count(array_filter($tasks, static fn($task) => !(int)$task['is_done'])) ?></h2><span class="muted small">Assigned to me</span></div>
        <?php if ($canQueue): ?><div class="stat-card"><span class="muted small">Live queue</span><h2><?= count($queue) ?></h2><span class="muted small">Refresh after updates</span></div><?php endif; ?>
        <?php if ($isInventory): ?><div class="stat-card"><span class="muted small">Pending deliveries</span><h2><?= count(array_filter($deliveries, static fn($delivery) => $delivery['status'] === 'pending')) ?></h2><span class="muted small">Receiving log</span></div><?php endif; ?>
        <?php if ($isFinance): ?><div class="stat-card"><span class="muted small">Month net</span><h2>$<?= number_format($finance['revenue'] - $finance['total_costs'], 2) ?></h2><span class="muted small">Revenue less costs</span></div><?php endif; ?>
    </div>

    <section class="staff-workspace-grid">
        <div>
            <?php if ($canQueue): ?>
            <div class="card staff-feature-panel">
                <div class="staff-toolbar"><div><h2>Live order queue</h2><p class="muted small">Cooks move New to Cooking to Ready. Waiters mark Ready orders Served.</p></div><span class="status-pill status-ready">Live</span></div>
                <table class="data-table"><thead><tr><th>Order</th><th>Table</th><th>Items</th><th>Placed</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($queue as $order): ?>
                    <?php $nextStatus = $order['status'] === 'pending' ? 'preparing' : ($order['status'] === 'preparing' ? 'ready' : 'completed'); ?>
                    <tr><td>#<?= e($order['order_number']) ?><div class="muted small"><?= e($order['customer_name']) ?></div></td><td><?= e($order['table_number'] ?: 'Takeaway') ?></td><td><?= e($order['item_summary']) ?></td><td><?= date('g:i A', strtotime($order['order_at'])) ?></td><td><span class="status-pill status-<?= e($order['status']) ?>"><?= $order['status'] === 'pending' ? 'New' : ($order['status'] === 'preparing' ? 'Cooking' : 'Ready') ?></span></td><td><form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="btn-small btn-primary" name="queue_status" value="<?= $nextStatus ?>"><?= $nextStatus === 'preparing' ? 'Start cooking' : ($nextStatus === 'ready' ? 'Mark ready' : 'Mark served') ?></button></form></td></tr>
                <?php endforeach; ?>
                <?php if (!$queue): ?><tr><td colspan="6" class="muted center">No active orders in the queue.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <?php endif; ?>

            <?php if ($isInventory): ?>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Receiving log</h2><p class="muted small">Track incoming shipments and mark them received.</p></div></div>
                <form method="POST" class="grid-form staff-inline-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input name="supplier" placeholder="Supplier" required><input name="items_received" placeholder="Items received" required><input type="number" name="quantity" min="1" placeholder="Quantity" required><button class="btn-primary" name="delivery_add">Add delivery</button></form>
                <table class="data-table"><thead><tr><th>Supplier</th><th>Items</th><th>Qty</th><th>Status</th><th>Action</th></tr></thead><tbody><?php foreach ($deliveries as $delivery): ?><tr><td><?= e($delivery['supplier']) ?></td><td><?= e($delivery['items_received']) ?></td><td><?= (int)$delivery['quantity'] ?></td><td><span class="status-pill status-<?= e($delivery['status']) ?>"><?= ucfirst($delivery['status']) ?></span></td><td><?php if ($delivery['status'] === 'pending'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="delivery_id" value="<?= (int)$delivery['id'] ?>"><button class="btn-small btn-confirm" name="delivery_receive">Mark received</button></form><?php else: ?><span class="muted small"><?= e($delivery['received_by_name'] ?? '') ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table>
            </div>
            <?php endif; ?>

            <?php if ($isFinance): ?>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Finance dashboard</h2><p class="muted small"><?= e(ucfirst($financePeriod)) ?>: <?= e($periodStart) ?> to <?= e($today) ?></p></div><form method="GET"><select name="period" onchange="this.form.submit()"><option value="today" <?= $financePeriod === 'today' ? 'selected' : '' ?>>Today</option><option value="week" <?= $financePeriod === 'week' ? 'selected' : '' ?>>This week</option><option value="month" <?= $financePeriod === 'month' ? 'selected' : '' ?>>This month</option></select></form></div><div class="finance-summary"><div><span class="muted small">Revenue</span><strong>$<?= number_format($finance['revenue'], 2) ?></strong></div><div><span class="muted small">Costs</span><strong>$<?= number_format($finance['total_costs'], 2) ?></strong></div><div><span class="muted small">Net profit</span><strong>$<?= number_format($finance['revenue'] - $finance['total_costs'], 2) ?></strong></div></div><h3>Record operating cost</h3><form method="POST" class="grid-form staff-inline-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input name="category" placeholder="Category" required><input name="department" placeholder="Department" value="Finance" required><input type="number" step="0.01" min="0" name="amount" placeholder="Amount" required><input type="date" name="cost_date" value="<?= e($today) ?>" required><input name="notes" placeholder="Notes"><button class="btn-primary" name="cost_add">Add cost</button></form><table class="data-table"><thead><tr><th>Department</th><th>Total</th></tr></thead><tbody><?php foreach ($finance['by_department'] as $cost): ?><tr><td><?= e($cost['department']) ?></td><td>$<?= number_format((float)$cost['total'], 2) ?></td></tr><?php endforeach; ?></tbody></table><h3>Cost line items</h3><table class="data-table"><thead><tr><th>Category</th><th>Department</th><th>Amount</th><th>Date</th></tr></thead><tbody><?php foreach ($costs as $cost): ?><tr><td><?= e($cost['category']) ?></td><td><?= e($cost['department']) ?></td><td>$<?= number_format((float)$cost['amount'], 2) ?></td><td><?= e($cost['cost_date']) ?></td></tr><?php endforeach; ?><?php if (!$costs): ?><tr><td colspan="4" class="muted center">No costs recorded for this period.</td></tr><?php endif; ?></tbody></table></div>
            <?php endif; ?>
        </div>

        <aside>
            <div class="card task-card"><div class="staff-toolbar"><div><h2>My tasks</h2><p class="muted small">Only you can see and update these.</p></div></div><form method="POST" class="task-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input name="task_title" placeholder="What needs doing?" required><input type="datetime-local" name="task_due_at"><button class="btn-primary btn-block" name="task_add">Add task</button></form><?php foreach ($tasks as $task): ?><form method="POST" class="task-row"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><button class="task-check <?= $task['is_done'] ? 'done' : '' ?>" name="task_toggle" aria-label="Toggle task">✓</button><span class="<?= $task['is_done'] ? 'task-done' : '' ?>"><?= e($task['title']) ?><small><?= $task['due_at'] ? e(date('M d, g:i A', strtotime($task['due_at']))) : 'No due time' ?></small></span></form><?php endforeach; ?><?php if (!$tasks): ?><p class="muted small">No tasks yet.</p><?php endif; ?></div>
            <div class="card"><div class="staff-toolbar"><div><h2>Recent reviews</h2><p class="muted small">Feedback from customers.</p></div><a class="link small" href="<?= BASE_URL ?>/views/staff/profile.php">View all</a></div><?php foreach (array_slice($reviews, 0, 3) as $review): ?><div class="review-line"><strong><?= str_repeat('★', (int)$review['rating']) ?></strong><p><?= e($review['comment'] ?: 'No comment') ?></p><span class="muted small"><?= e($review['customer_name']) ?></span></div><?php endforeach; ?><?php if (!$reviews): ?><p class="muted small">No reviews yet.</p><?php endif; ?></div>
        </aside>
    </section>
</main>
</body>
</html>
