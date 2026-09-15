<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/StaffWorkspace.php';
requireStaff();
$database = new Database();
$db = $database->connect();
$workspace = new StaffWorkspace($db);
$department = trim($_GET['department'] ?? '');
$role = trim($_GET['role'] ?? '');
$staffMembers = $workspace->directory($department, $role);
$departments = array_values(array_unique(array_filter(array_column($workspace->directory(), 'department'))));
$roles = array_values(array_unique(array_filter(array_column($workspace->directory(), 'staff_role'))));
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Staff Directory - CanteenPro</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css"></head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_staff.php'; ?>
<main class="admin-main">
    <header class="admin-topbar"><div><h1>Staff directory</h1><p class="muted">Find teams, roles, and colleague profiles.</p></div></header>
    <div class="card staff-toolbar"><form method="GET" class="staff-filter-bar"><select name="department"><option value="">All departments</option><?php foreach ($departments as $item): ?><option value="<?= e($item) ?>" <?= $department === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><select name="role"><option value="">All roles</option><?php foreach ($roles as $item): ?><option value="<?= e($item) ?>" <?= $role === $item ? 'selected' : '' ?>><?= e($item) ?></option><?php endforeach; ?></select><button class="btn-primary btn-small">Filter</button></form></div>
    <div class="staff-directory-grid">
        <?php foreach ($staffMembers as $member): ?><a class="card staff-profile-card" href="<?= BASE_URL ?>/views/staff/profile.php?id=<?= (int)$member['id'] ?>"><div class="avatar-circle">👤</div><h2><?= e($member['staff_name']) ?></h2><p class="muted"><?= e($member['staff_role']) ?></p><span class="source-badge"><?= e($member['department']) ?></span><div class="row-between staff-card-footer"><span class="muted small"><?= e(ucwords(str_replace('_', ' ', $member['staff_status']))) ?></span><strong><?= number_format((float)$member['average_rating'], 1) ?> ★</strong></div></a><?php endforeach; ?>
        <?php if (!$staffMembers): ?><div class="card"><p class="muted">No staff members match those filters.</p></div><?php endif; ?>
    </div>
</main>
</body>
</html>
