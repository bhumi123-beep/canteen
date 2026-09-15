<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Transaction.php';
requireAdmin();

$database = new Database();
$db = $database->connect();
$transactions = new Transaction($db);
if (empty($_SESSION['admin_transaction_csrf'])) {
    $_SESSION['admin_transaction_csrf'] = bin2hex(random_bytes(32));
}
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['void_transaction'])) {
    if (!hash_equals($_SESSION['admin_transaction_csrf'], (string)($_POST['csrf_token'] ?? ''))) {
        $error = 'Your session token expired. Please try again.';
    } elseif ($transactions->void($_POST['source_type'] ?? '', $_POST['source_id'] ?? 0, $_POST['reason'] ?? '', $_SESSION['user_id'])) {
        $message = 'Transaction voided and the reason was logged.';
    } else {
        $error = 'This transaction could not be voided.';
    }
}
$rows = $transactions->allForAdmin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Transactions - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>
<main class="admin-main">
    <header class="admin-topbar row-between"><div><p class="muted small">ADMIN / MANAGER OVERSIGHT</p><h1>Transactions</h1></div><a href="<?= BASE_URL ?>/views/admin/dashboard.php" class="btn-secondary btn-small">Dashboard</a></header>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <div class="card"><table class="data-table"><thead><tr><th>Reference</th><th>Order</th><th>Method</th><th>Amount</th><th>Status</th><th>Cashier</th><th>Action</th></tr></thead><tbody>
    <?php foreach ($rows as $row): ?><tr><td><?= e($row['reference']) ?><div class="muted small"><?= e($row['created_at']) ?></div></td><td>#<?= (int)$row['order_id'] ?></td><td><?= e(ucfirst($row['payment_method'])) ?></td><td>$<?= number_format((float)$row['amount'], 2) ?></td><td><span class="status-pill <?= $row['void_reason'] ? 'status-cancelled' : ($row['status'] === 'PAID' || $row['status'] === 'paid' ? 'status-ready' : 'status-pending') ?>"><?= $row['void_reason'] ? 'Voided' : e($row['status']) ?></span><?php if ($row['void_reason']): ?><div class="muted small"><?= e($row['void_reason']) ?></div><?php endif; ?></td><td><?= e($row['cashier_name'] ?: 'Admin') ?></td><td><?php if (!$row['void_reason'] && ($row['status'] === 'PAID' || $row['status'] === 'paid')): ?><form method="POST"><input type="hidden" name="csrf_token" value="<?= e($_SESSION['admin_transaction_csrf']) ?>"><input type="hidden" name="source_type" value="<?= e($row['source_type']) ?>"><input type="hidden" name="source_id" value="<?= (int)$row['source_id'] ?>"><input name="reason" maxlength="500" placeholder="Required reason" required><button class="btn-small btn-danger" name="void_transaction">Void</button></form><?php else: ?><span class="muted small">No action</span><?php endif; ?></td></tr><?php endforeach; ?>
    <?php if (!$rows): ?><tr><td colspan="7" class="muted center">No transactions found.</td></tr><?php endif; ?></tbody></table></div>
</main>
</body>
</html>
