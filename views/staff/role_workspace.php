<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/StaffWorkspace.php';
require_once __DIR__ . '/../../models/Ingredient.php';
require_once __DIR__ . '/../../models/Table.php';
require_once __DIR__ . '/../../controllers/EsewaController.php';
requireStaff();

$database = new Database();
$db = $database->connect();
$workspace = new StaffWorkspace($db);
$ingredientModel = new Ingredient($db);
$reservationModel = new Reservation($db);
$staff = isAdmin() ? [
    'id' => 0,
    'staff_name' => $_SESSION['name'] ?? 'Admin',
    'department' => 'Management',
    'staff_shift' => 'All day',
] : $workspace->currentStaff($_SESSION['user_id']);
if (!$staff) {
    redirect('staff_login.php');
}
$workspaceRecord = isAdmin() ? ['workspace_status' => 'active'] : $workspace->workspaceForStaff($staff);
if (!$workspaceRecord) {
    redirect('views/staff/dashboard.php');
}

if (empty($_SESSION['staff_workspace_csrf'])) {
    $_SESSION['staff_workspace_csrf'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['staff_workspace_csrf'];
$message = '';
$error = $_SESSION['payment_error'] ?? '';
$reviewLink = '';
unset($_SESSION['payment_error']);
$role = normalizeStaffRole($staff['staff_role'] ?? '');
if (!in_array($role, ['manager', 'admin', 'cashier', 'cook', 'waiter', 'inventory', 'finance'], true)) {
    redirect('views/staff/dashboard.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Please refresh and try again.';
    } elseif (isset($_POST['task_add'])) {
        $message = $workspace->addTask($staff['id'], $_POST['task_title'] ?? '', $_POST['task_due_at'] ?? '') ? 'Task added.' : 'Enter a task title.';
    } elseif (isset($_POST['task_toggle'])) {
        $workspace->toggleTask($staff['id'], $_POST['task_id'] ?? 0);
        $message = 'Task updated.';
    } elseif (isset($_POST['queue_status'])) {
        $message = $workspace->updateQueueStatus($_POST['order_id'] ?? 0, $staff['id'], $role, $_POST['queue_status']) ? 'Order status updated.' : 'That order cannot be moved to the selected status.';
    } elseif (isset($_POST['delivery_add'])) {
        $message = $workspace->addDelivery($_POST['supplier'] ?? '', $_POST['items_received'] ?? '', $_POST['quantity'] ?? 0) ? 'Delivery added.' : 'Enter valid receiving details.';
    } elseif (isset($_POST['delivery_receive'])) {
        $message = $workspace->markDeliveryReceived($_POST['delivery_id'] ?? 0, $staff['id']) ? 'Delivery marked as received.' : 'You are not authorized to receive this delivery.';
    } elseif (isset($_POST['cost_add'])) {
        $message = $workspace->addCost($_POST['category'] ?? '', $_POST['department'] ?? '', $_POST['amount'] ?? 0, $_POST['cost_date'] ?? '', $_POST['notes'] ?? '') ? 'Cost recorded.' : 'Enter valid cost details.';
    } elseif (isset($_POST['cashier_finalize'])) {
        $paymentMethod = $_POST['payment_method'] ?? '';
        if ($paymentMethod === 'esewa' && $role === 'cashier') {
            $esewa = new EsewaController($db);
            $payment = $esewa->begin($_POST['order_id'] ?? 0, $staff['id']);
            if (!$payment['success']) {
                $error = $payment['message'];
            } else {
                echo $esewa->renderRedirectForm($payment);
                exit;
            }
        } else {
            $finalized = $workspace->finalizeCashierBill($_POST['order_id'] ?? 0, $paymentMethod, $staff['id']);
            $message = $finalized ? 'Bill finalized and order marked paid.' : 'This order could not be finalized.';
            if ($finalized) {
                $reviewToken = $workspace->reviewTokenForOrder($_POST['order_id'] ?? 0);
                $reviewLink = BASE_URL . '/views/customer/review.php?token=' . rawurlencode($reviewToken);
            }
        }
    } elseif (isset($_POST['collect_reservation_deposit'])) {
        if ($role === 'cashier' || staffHasRole(['manager', 'admin'])) {
            $message = $reservationModel->collectCashDeposit($_POST['reservation_id'] ?? 0) ? 'Reservation deposit collected.' : 'This reservation deposit could not be collected.';
        } else {
            $error = 'You are not authorized to collect reservation deposits.';
        }
    } elseif (isset($_POST['update_reservation_status'])) {
        if (in_array($role, ['waiter', 'manager'], true) || staffHasRole(['manager', 'admin'])) {
            $status = $_POST['reservation_status'] ?? '';
            $id = (int)($_POST['reservation_id'] ?? 0);
            $message = $reservationModel->updateStatus($id, $status) ? 'Table reservation status updated.' : 'Could not update reservation status.';
        } else {
            $error = 'You are not authorized to update reservation status.';
        }
    }
}

$tasks = $workspace->tasks($staff['id']);
$reviews = $workspace->reviews($staff['id']);
$breakdown = $workspace->ratingBreakdown($staff['id']);
$rating = (float)($staff['performance_average'] ?? 0);
$queue = in_array($role, ['waiter', 'cook'], true) || staffHasRole(['manager', 'admin']) ? $workspace->queue() : [];
$deliveries = $role === 'inventory' || staffHasRole(['manager', 'admin']) ? $workspace->deliveries() : [];
$stock = $role === 'inventory' || staffHasRole(['manager', 'admin']) ? $workspace->stockLevels() : [];
$ingredientStock = $role === 'inventory' || staffHasRole(['manager', 'admin']) ? $ingredientModel->all() : [];
$cashierOrders = $role === 'cashier' || staffHasRole(['manager', 'admin']) ? $workspace->cashierOrders() : [];
$transactions = in_array($role, ['cashier', 'finance'], true) || staffHasRole(['manager', 'admin']) ? $workspace->cashierTransactionsToday() : [];
$reconciliation = in_array($role, ['cashier', 'finance'], true) || staffHasRole(['manager', 'admin']) ? $workspace->cashierReconciliation() : ['cash' => 0, 'card' => 0, 'system_total' => 0];
$today = date('Y-m-d');
$financePeriod = $_GET['period'] ?? 'month';
if (!in_array($financePeriod, ['today', 'week', 'month'], true)) {
    $financePeriod = 'month';
}
$periodStart = $financePeriod === 'today' ? $today : ($financePeriod === 'week' ? date('Y-m-d', strtotime('monday this week')) : date('Y-m-01'));
$finance = $role === 'finance' || staffHasRole(['manager', 'admin']) ? $workspace->financeSummary($periodStart, $today) : null;
$costs = $finance ? $workspace->costs($periodStart, $today) : [];
$pendingReservationDeposits = $role === 'cashier' || staffHasRole(['manager', 'admin']) ? $reservationModel->pendingCashDeposits() : [];
$todayBookings = in_array($role, ['waiter', 'manager'], true) || staffHasRole(['manager', 'admin']) ? $reservationModel->activeReservationsForDate($today) : [];
$reservationDeposits = $finance ? $reservationModel->paidDepositsSummary($periodStart, $today) : ['total' => 0, 'rows' => []];
$queueLabel = $role === 'cook' ? 'Kitchen queue' : 'Live order queue';
$allowedQueueStatuses = $role === 'cook' ? ['preparing', 'ready'] : ['completed'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e(ucfirst($role)) ?> Workspace - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_staff.php'; ?>
<main class="admin-main">
    <header class="admin-topbar row-between">
        <div>
            <p class="muted small">STAFF WORKSPACE / <?= e(strtoupper($staff['department'] ?? 'Operations')) ?></p>
            <h1><?= e(ucfirst($role)) ?> workspace</h1>
            <p class="muted"><?= e($staff['staff_name']) ?> · <?= e($staff['staff_shift']) ?> shift</p>
        </div>
        <a href="<?= BASE_URL ?>/views/staff/profile.php" class="btn-secondary btn-small">My profile</a>
    </header>

    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <?php if ($reviewLink): ?><div class="card"><h2>Would you like to rate your experience?</h2><a class="btn-primary" href="<?= e($reviewLink) ?>&mode=rate">Rate now</a> <a class="btn-secondary" href="<?= e($reviewLink) ?>&mode=skip">Skip</a></div><?php endif; ?>

    <div class="stat-grid">
        <div class="stat-card"><span class="muted small">My rating</span><h2><?= number_format($rating, 1) ?> / 5</h2><span class="muted small"><?= count($reviews) ?> review(s)</span></div>
        <div class="stat-card"><span class="muted small">Open tasks</span><h2><?= count(array_filter($tasks, static fn($task) => !(int)$task['is_done'])) ?></h2><span class="muted small">Assigned to me</span></div>
        <?php if (in_array($role, ['waiter', 'cook'], true)): ?><div class="stat-card"><span class="muted small">Active orders</span><h2><?= count($queue) ?></h2><span class="muted small">Shared live queue</span></div><?php endif; ?>
        <?php if ($role === 'inventory'): ?><div class="stat-card"><span class="muted small">Low stock</span><h2><?= count(array_filter($stock, static fn($item) => (int)$item['current_stock'] <= (int)$item['reorder_level'])) ?></h2><span class="muted small">Items need attention</span></div><?php endif; ?>
        <?php if ($role === 'cashier'): ?><div class="stat-card"><span class="muted small">Today's total</span><h2>$<?= number_format($reconciliation['system_total'], 2) ?></h2><span class="muted small"><?= count($transactions) ?> transactions</span></div><?php endif; ?>
        <?php if ($role === 'finance'): ?><div class="stat-card"><span class="muted small">Net profit</span><h2>$<?= number_format($finance['revenue'] - $finance['total_costs'], 2) ?></h2><span class="muted small"><?= e(ucfirst($financePeriod)) ?></span></div><?php endif; ?>
    </div>

    <section class="staff-workspace-grid">
        <div>
            <?php if (in_array($role, ['waiter', 'cook'], true)): ?>
            <div class="card staff-feature-panel">
                <div class="staff-toolbar"><div><h2><?= e($queueLabel) ?></h2><p class="muted small">Shared order data for kitchen and floor service.</p></div><span class="status-pill status-ready">Live</span></div>
                <table class="data-table"><thead><tr><th>Order</th><th>Table</th><th>Items</th><th>Placed</th><th>Status</th><th>Action</th></tr></thead><tbody>
                <?php foreach ($queue as $order): ?>
                    <?php $nextStatus = $order['status'] === 'pending' ? 'preparing' : ($order['status'] === 'preparing' ? 'ready' : 'completed'); $canMove = $role === 'cook' ? in_array($nextStatus, $allowedQueueStatuses, true) : $order['status'] === 'ready'; ?>
                    <tr><td>#<?= e($order['order_number']) ?><div class="muted small"><?= e($order['customer_name']) ?></div></td><td><?= e($order['table_number'] ?: 'Takeaway') ?></td><td><?= e($order['item_summary']) ?></td><td><?= date('g:i A', strtotime($order['order_at'])) ?></td><td><span class="status-pill status-<?= e($order['status']) ?>"><?= $order['status'] === 'pending' ? 'New' : ($order['status'] === 'preparing' ? 'Cooking' : ($order['status'] === 'ready' ? 'Ready' : 'Served')) ?></span></td><td><?php if ($canMove): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><button class="btn-small btn-primary" name="queue_status" value="<?= e($nextStatus) ?>"><?= $nextStatus === 'preparing' ? 'Start cooking' : ($nextStatus === 'ready' ? 'Mark ready' : 'Mark served') ?></button></form><?php else: ?><span class="muted small">Waiting</span><?php endif; ?></td></tr>
                <?php endforeach; ?>
                <?php if (!$queue): ?><tr><td colspan="6" class="muted center">No active orders.</td></tr><?php endif; ?>
                </tbody></table>
            </div>
            <?php endif; ?>

            <?php if (in_array($role, ['waiter', 'manager'], true) || staffHasRole(['manager', 'admin'])): ?>
            <div class="card staff-feature-panel">
                <div class="staff-toolbar">
                    <div>
                        <h2>Today's Table Bookings & Floor Overview</h2>
                        <p class="muted small">Track guest arrivals and seating for today.</p>
                    </div>
                </div>
                <table class="data-table">
                    <thead><tr><th>Table</th><th>Customer</th><th>Time Slot</th><th>Guests</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php foreach ($todayBookings as $b): ?>
                        <tr>
                            <td><strong>Table <?= e($b['table_number']) ?></strong> <span class="muted small">(<?= e($b['location']) ?>)</span></td>
                            <td><?= e($b['customer_name']) ?><div class="muted small"><?= e($b['customer_email']) ?> <?= $b['customer_phone'] ? '· ' . e($b['customer_phone']) : '' ?></div></td>
                            <td><?= date('g:i A', strtotime($b['start_time'])) ?> - <?= date('g:i A', strtotime($b['end_time'])) ?></td>
                            <td><?= (int)$b['guests'] ?> People</td>
                            <td><span class="status-pill status-<?= e($b['status']) ?>"><?= ucfirst($b['status']) ?></span></td>
                            <td>
                                <?php if (in_array($b['status'], ['pending', 'confirmed'], true)): ?>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                        <input type="hidden" name="reservation_id" value="<?= (int)$b['id'] ?>">
                                        <button class="btn-small btn-confirm" name="update_reservation_status" value="completed" onclick="return confirm('Mark guests seated/completed?');">Mark Seated</button>
                                        <button class="btn-small btn-cancel" name="update_reservation_status" value="cancelled" onclick="return confirm('Cancel this reservation?');">Cancel</button>
                                    </form>
                                <?php else: ?>
                                    <span class="muted small">Completed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$todayBookings): ?>
                        <tr><td colspan="6" class="muted center">No active table reservations scheduled for today.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>

            <?php if ($role === 'cashier' || staffHasRole(['manager', 'admin'])): ?>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Reservation deposits due at counter</h2><p class="muted small">Collect unpaid cash deposits from customers.</p></div></div><table class="data-table"><thead><tr><th>Customer</th><th>Table</th><th>Date/time</th><th>Amount</th><th>Action</th></tr></thead><tbody><?php foreach ($pendingReservationDeposits as $deposit): ?><tr><td><?= e($deposit['customer_name']) ?><div class="muted small"><?= e($deposit['customer_email']) ?></div></td><td><?= e($deposit['table_number']) ?></td><td><?= e($deposit['reservation_date']) ?><div class="muted small"><?= date('g:i A', strtotime($deposit['start_time'])) ?></div></td><td>$<?= number_format((float)$deposit['deposit_amount'], 2) ?></td><td><form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="reservation_id" value="<?= (int)$deposit['id'] ?>"><button class="btn-small btn-primary" name="collect_reservation_deposit">Collect payment</button></form></td></tr><?php endforeach; ?><?php if (!$pendingReservationDeposits): ?><tr><td colspan="5" class="muted center">No reservation deposits due at the counter.</td></tr><?php endif; ?></tbody></table></div>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Open orders for billing</h2><p class="muted small">Finalize a bill when payment is received.</p></div></div>
                <table class="data-table"><thead><tr><th>Order</th><th>Table</th><th>Items</th><th>Status</th><th>Total</th><th>Payment</th></tr></thead><tbody>
                <?php foreach ($cashierOrders as $order): ?><tr><td>#<?= e($order['order_number']) ?></td><td><?= e($order['table_number'] ?: 'Takeaway') ?></td><td><?= e($order['item_summary']) ?></td><td><span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span></td><td>$<?= number_format((float)$order['total_amount'], 2) ?></td><td><form method="POST" class="row-between"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="order_id" value="<?= (int)$order['id'] ?>"><select name="payment_method"><option value="cash">Cash</option><option value="card">Card</option><option value="esewa">eSewa</option></select><button class="btn-small btn-primary" name="cashier_finalize">Take payment</button></form></td></tr><?php endforeach; ?>
                <?php if (!$cashierOrders): ?><tr><td colspan="6" class="muted center">No open orders ready for billing.</td></tr><?php endif; ?></tbody></table>
            </div>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Today's reconciliation</h2><p class="muted small">Completed cashier transactions.</p></div></div><div class="finance-summary"><div><span class="muted small">Cash</span><strong>$<?= number_format($reconciliation['cash'], 2) ?></strong></div><div><span class="muted small">Card</span><strong>$<?= number_format($reconciliation['card'], 2) ?></strong></div><div><span class="muted small">System total</span><strong>$<?= number_format($reconciliation['system_total'], 2) ?></strong></div></div><table class="data-table"><thead><tr><th>Time</th><th>Table</th><th>Amount</th><th>Method</th></tr></thead><tbody><?php foreach ($transactions as $transaction): ?><tr><td><?= date('g:i A', strtotime($transaction['paid_at'])) ?></td><td><?= e($transaction['table_number'] ?: 'Takeaway') ?></td><td>$<?= number_format((float)$transaction['amount'], 2) ?></td><td><?= e(ucfirst($transaction['payment_method'])) ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>

            <?php if ($role === 'inventory'): ?>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Ingredient stock</h2><p class="muted small">Order deductions update these quantities immediately.</p></div></div><table class="data-table"><thead><tr><th>Ingredient</th><th>Unit</th><th>Current</th><th>Reorder level</th><th>Status</th></tr></thead><tbody><?php foreach ($ingredientStock as $ingredient): $low = (float)$ingredient['current_stock'] <= (float)$ingredient['reorder_level']; ?><tr><td><?= e($ingredient['name']) ?></td><td><?= e($ingredient['unit']) ?></td><td><?= e($ingredient['current_stock']) ?></td><td><?= e($ingredient['reorder_level']) ?></td><td><span class="status-pill <?= $low ? 'status-cancelled' : 'status-ready' ?>"><?= $low ? 'Low stock' : 'Healthy' ?></span></td></tr><?php endforeach; ?><?php if (!$ingredientStock): ?><tr><td colspan="5" class="muted center">No ingredient records yet.</td></tr><?php endif; ?></tbody></table></div>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Current stock</h2><p class="muted small">Low-stock items are shown first.</p></div></div><table class="data-table"><thead><tr><th>Item</th><th>Category</th><th>Current</th><th>Reorder level</th><th>Status</th></tr></thead><tbody><?php foreach ($stock as $item): $low = (int)$item['current_stock'] <= (int)$item['reorder_level']; ?><tr><td><?= e($item['name']) ?></td><td><?= e($item['category_name'] ?? '') ?></td><td><?= (int)$item['current_stock'] ?></td><td><?= (int)$item['reorder_level'] ?></td><td><span class="status-pill <?= $low ? 'status-cancelled' : 'status-ready' ?>"><?= $low ? 'Low stock' : 'Healthy' ?></span></td></tr><?php endforeach; ?></tbody></table></div>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Receiving log</h2><p class="muted small">Record incoming stock and mark it received.</p></div></div><form method="POST" class="grid-form staff-inline-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input name="supplier" placeholder="Supplier" required><input name="items_received" placeholder="Items received" required><input type="number" name="quantity" min="1" placeholder="Quantity" required><button class="btn-primary" name="delivery_add">Add delivery</button></form><table class="data-table"><thead><tr><th>Supplier</th><th>Items</th><th>Qty</th><th>Status</th><th>Time</th><th>Action</th></tr></thead><tbody><?php foreach ($deliveries as $delivery): ?><tr><td><?= e($delivery['supplier']) ?></td><td><?= e($delivery['items_received']) ?></td><td><?= (int)$delivery['quantity'] ?></td><td><span class="status-pill status-<?= e($delivery['status']) ?>"><?= e(ucfirst($delivery['status'])) ?></span></td><td><?= date('M d, g:i A', strtotime($delivery['created_at'])) ?></td><td><?php if ($delivery['status'] === 'pending'): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="delivery_id" value="<?= (int)$delivery['id'] ?>"><button class="btn-small btn-confirm" name="delivery_receive">Mark received</button></form><?php else: ?><span class="muted small"><?= e($delivery['received_by_name'] ?? '') ?></span><?php endif; ?></td></tr><?php endforeach; ?></tbody></table></div>
            <?php endif; ?>

            <?php if ($role === 'finance' || staffHasRole(['manager', 'admin'])): ?>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Reservation deposits</h2><p class="muted small">Paid reservation deposits for <?= e($periodStart) ?> to <?= e($today) ?>.</p></div></div><div class="finance-summary"><div><span class="muted small">Deposit revenue</span><strong>$<?= number_format((float)$reservationDeposits['total'], 2) ?></strong></div></div><table class="data-table"><thead><tr><th>Customer</th><th>Table</th><th>Date</th><th>Amount</th><th>Method</th></tr></thead><tbody><?php foreach ($reservationDeposits['rows'] as $deposit): ?><tr><td><?= e($deposit['customer_name']) ?></td><td><?= e($deposit['table_number']) ?></td><td><?= e($deposit['reservation_date']) ?></td><td>$<?= number_format((float)$deposit['deposit_amount'], 2) ?></td><td><?= e(ucfirst($deposit['payment_method'])) ?></td></tr><?php endforeach; ?><?php if (!$reservationDeposits['rows']): ?><tr><td colspan="5" class="muted center">No paid reservation deposits in this period.</td></tr><?php endif; ?></tbody></table></div>
            <div class="card staff-feature-panel"><div class="staff-toolbar"><div><h2>Revenue and costs</h2><p class="muted small"><?= e(ucfirst($financePeriod)) ?>: <?= e($periodStart) ?> to <?= e($today) ?></p></div><form method="GET"><select name="period" onchange="this.form.submit()"><option value="today" <?= $financePeriod === 'today' ? 'selected' : '' ?>>Today</option><option value="week" <?= $financePeriod === 'week' ? 'selected' : '' ?>>This week</option><option value="month" <?= $financePeriod === 'month' ? 'selected' : '' ?>>This month</option></select></form></div><div class="finance-summary"><div><span class="muted small">Revenue</span><strong>$<?= number_format($finance['revenue'], 2) ?></strong></div><div><span class="muted small">Costs</span><strong>$<?= number_format($finance['total_costs'], 2) ?></strong></div><div><span class="muted small">Net profit</span><strong>$<?= number_format($finance['revenue'] - $finance['total_costs'], 2) ?></strong></div></div><h3>Costs by department</h3><table class="data-table"><thead><tr><th>Department</th><th>Total</th></tr></thead><tbody><?php foreach ($finance['by_department'] as $cost): ?><tr><td><?= e($cost['department']) ?></td><td>$<?= number_format((float)$cost['total'], 2) ?></td></tr><?php endforeach; ?></tbody></table><h3>Cost line items</h3><table class="data-table"><thead><tr><th>Category</th><th>Department</th><th>Amount</th><th>Date</th></tr></thead><tbody><?php foreach ($costs as $cost): ?><tr><td><?= e($cost['category']) ?></td><td><?= e($cost['department']) ?></td><td>$<?= number_format((float)$cost['amount'], 2) ?></td><td><?= e($cost['cost_date']) ?></td></tr><?php endforeach; ?></tbody></table><h3>Record operating cost</h3><form method="POST" class="grid-form staff-inline-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input name="category" placeholder="Category" required><input name="department" placeholder="Department" value="Finance" required><input type="number" step="0.01" min="0" name="amount" placeholder="Amount" required><input type="date" name="cost_date" value="<?= e($today) ?>" required><input name="notes" placeholder="Notes"><button class="btn-primary" name="cost_add">Add cost</button></form></div>
            <?php endif; ?>

            <?php if (in_array($role, ['waiter', 'cook'], true)): ?>
            <div class="card staff-feature-panel"><h2>My customer ratings</h2><div class="profile-meta"><span><strong><?= number_format($rating, 1) ?> / 5</strong><small>Average rating</small></span><?php for ($stars = 5; $stars >= 1; $stars--): ?><span><strong><?= (int)$breakdown[$stars] ?></strong><small><?= $stars ?> star</small></span><?php endfor; ?></div><?php foreach ($reviews as $review): ?><article class="review-line"><strong><?= str_repeat('★', (int)$review['rating']) ?></strong><p><?= e($review['comment'] ?: 'No comment provided.') ?></p><span class="muted small"><?= e($review['customer_name']) ?></span></article><?php endforeach; ?><?php if (!$reviews): ?><p class="muted">No customer reviews yet.</p><?php endif; ?></div>
            <?php endif; ?>
        </div>

        <aside>
            <div class="card task-card"><div class="staff-toolbar"><div><h2>My tasks</h2><p class="muted small">Personal work list and due times.</p></div></div><form method="POST" class="task-form"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input name="task_title" placeholder="What needs doing?" required><input type="datetime-local" name="task_due_at"><button class="btn-primary btn-block" name="task_add">Add task</button></form><?php foreach ($tasks as $task): ?><form method="POST" class="task-row"><input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>"><input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>"><button class="task-check <?= $task['is_done'] ? 'done' : '' ?>" name="task_toggle" aria-label="Toggle task">✓</button><span class="<?= $task['is_done'] ? 'task-done' : '' ?>"><?= e($task['title']) ?><small><?= $task['due_at'] ? e(date('M d, g:i A', strtotime($task['due_at']))) : 'No due time' ?></small></span></form><?php endforeach; ?><?php if (!$tasks): ?><p class="muted small">No tasks yet.</p><?php endif; ?></div>
        </aside>
    </section>
</main>
</body>
</html>
