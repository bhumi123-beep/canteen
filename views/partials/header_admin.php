<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="admin-top-actions">
        <form method="POST" action="<?= BASE_URL ?>/logout.php">
            <?= csrfField('logout_csrf') ?>
            <button type="submit" class="logout-button">🚪 Logout</button>
        </form>
    </div>
    <div class="admin-user">
        <div class="avatar-circle">👤</div>
        <div>
            <strong><?= e($_SESSION['name'] ?? 'Admin') ?></strong>
            <div class="muted small">Canteen Manager</div>
        </div>
    </div>

    <a href="<?= BASE_URL ?>/views/admin/menu_management.php" class="btn-primary btn-block sidebar-cta">+ New Menu Item</a>

    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>/views/admin/dashboard.php" class="<?= $current === 'dashboard.php' ? 'active' : '' ?>">📊 Dashboard</a>
        <a href="<?= BASE_URL ?>/views/admin/inventory.php" class="<?= $current === 'inventory.php' ? 'active' : '' ?>">📦 Inventory</a>
        <a href="<?= BASE_URL ?>/views/admin/staff_management.php" class="<?= $current === 'staff_management.php' ? 'active' : '' ?>">👥 Staff Accounts</a>
        <a href="<?= BASE_URL ?>/views/admin/account_management.php" class="<?= $current === 'account_management.php' ? 'active' : '' ?>">🔐 Account Management</a>
        <a href="<?= BASE_URL ?>/views/admin/staff_payroll.php" class="<?= $current === 'staff_payroll.php' ? 'active' : '' ?>">💰 Staff Salary</a>
        <a href="<?= BASE_URL ?>/views/admin/menu_management.php" class="<?= $current === 'menu_management.php' ? 'active' : '' ?>">🍽️ Menu Management</a>
        <a href="<?= BASE_URL ?>/views/admin/orders.php" class="<?= $current === 'orders.php' ? 'active' : '' ?>">🧾 Orders</a>
        <a href="<?= BASE_URL ?>/views/admin/report.php" class="<?= $current === 'report.php' ? 'active' : '' ?>">📈 Reports</a>
        <a href="<?= BASE_URL ?>/views/admin/reservations.php" class="<?= $current === 'reservations.php' ? 'active' : '' ?>">📅 Reservations</a>
        <a href="<?= BASE_URL ?>/views/admin/tables.php" class="<?= $current === 'tables.php' ? 'active' : '' ?>">🪑 Table Management</a>
        <a href="<?= BASE_URL ?>/views/admin/transactions.php" class="<?= $current === 'transactions.php' ? 'active' : '' ?>">💳 Transactions</a>
        <a href="<?= BASE_URL ?>/views/admin/recipes.php" class="<?= $current === 'recipes.php' ? 'active' : '' ?>">🧪 Recipes</a>
        <a href="<?= BASE_URL ?>/views/admin/table_qr_codes.php" class="<?= $current === 'table_qr_codes.php' ? 'active' : '' ?>">▦ Table QR Codes</a>
    </nav>

</aside>
