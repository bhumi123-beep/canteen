<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/TableQrCode.php';
requireAdmin();

$database = new Database();
$qrCodes = new TableQrCode($database->connect());
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$codes = $qrCodes->all();
?>
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><title>Table QR Codes - CanteenPro</title><link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css"></head>
<body class="admin-body">
<?php include __DIR__ . '/../partials/header_admin.php'; ?>
<main class="admin-main">
    <header class="admin-topbar"><h1>Table QR codes</h1></header>
    <p class="muted">Each code opens the customer menu with its table securely pre-filled.</p>
    <div class="menu-grid">
        <?php foreach ($codes as $code): $orderUrl = $scheme . '://' . $host . BASE_URL . '/views/customer/home.php?table=' . rawurlencode($code['table_number']) . '&qr=' . rawurlencode($code['token']); $qrImage = 'https://api.qrserver.com/v1/create-qr-code/?size=240x240&data=' . rawurlencode($orderUrl); ?>
            <div class="card center"><h2>Table <?= e($code['table_number']) ?></h2><p class="muted small"><?= e($code['location']) ?></p><img src="<?= e($qrImage) ?>" alt="QR code for table <?= e($code['table_number']) ?>"><p class="small"><a class="link" href="<?= e($orderUrl) ?>">Open ordering link</a></p><button type="button" class="btn-secondary btn-small" onclick="window.print()">Print</button></div>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
