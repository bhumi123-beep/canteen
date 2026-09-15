<?php
$current = basename($_SERVER['PHP_SELF']);
?>
<aside class="admin-sidebar">
    <div class="admin-user">
        <div class="avatar-circle">👤</div>
        <div>
            <strong><?= e($_SESSION['name'] ?? 'Staff') ?></strong>
            <div class="muted small"><?= e(ucwords(currentStaffRole() ?: 'Staff')) ?></div>
        </div>
    </div>

    <nav class="sidebar-nav">
        <a href="<?= BASE_URL ?>/<?= ltrim(staffDashboardPath(), '/') ?>" class="<?= $current === basename(staffDashboardPath()) ? 'active' : '' ?>">📋 Workspace</a>
        <?php if (staffHasRole(['finance'])): ?><a href="<?= BASE_URL ?>/views/admin/menu_management.php" class="<?= $current === 'menu_management.php' ? 'active' : '' ?>">🍽️ Menu Management</a><?php endif; ?>
        <a href="<?= BASE_URL ?>/views/staff/directory.php" class="<?= $current === 'directory.php' ? 'active' : '' ?>">👥 Staff Directory</a>
        <a href="<?= BASE_URL ?>/views/staff/profile.php" class="<?= $current === 'profile.php' ? 'active' : '' ?>">⭐ My Reviews</a>
    </nav>

    <div class="sidebar-footer">
        <form method="POST" action="<?= BASE_URL ?>/logout.php">
            <?= csrfField('logout_csrf') ?>
            <button type="submit" class="logout-button">🚪 Logout</button>
        </form>
    </div>
</aside>
