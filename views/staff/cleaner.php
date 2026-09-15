<?php
require_once __DIR__ . '/../../config/config.php';
requireStaffRole(['cleaner']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Cleaner Workspace - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_staff.php'; ?>
<main class="admin-main">
    <header class="admin-topbar">
        <div>
            <p class="muted small">STAFF WORKSPACE</p>
            <h1>Cleaner workspace</h1>
            <p class="muted">Welcome, <?= e($_SESSION['name'] ?? 'Staff') ?>.</p>
        </div>
    </header>

    <section class="card">
        <h2>Cleaning assignments</h2>
        <p class="muted">Your cleaning workspace is ready. Check with your manager for current assignments.</p>
    </section>
</main>
</body>
</html>