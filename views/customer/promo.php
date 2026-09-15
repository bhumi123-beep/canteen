<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../models/Promo.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$promo = new Promo($db);
$promoClaim = $promo->findByUser($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_offer']) && verifyCsrf()) {
    $promoClaim = $promo->claimForUser($_SESSION['user_id']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Promo Code - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container narrow">
    <div class="card promo-page-card">
        <h1>My Promo Code</h1>
        <p class="muted">Use your CanteenPro offer code during checkout.</p>

        <?php if ($promoClaim): ?>
            <div class="promo-code-display">
                <span class="muted small"><?= (int)$promoClaim['discount_percent'] ?>% OFF HEALTHY LUNCH</span>
                <strong><?= e($promoClaim['promo_code']) ?></strong>
                <span class="muted small">Valid at <?= e($promoClaim['shop_name']) ?></span>
            </div>
            <a href="<?= BASE_URL ?>/views/customer/checkout.php" class="btn-primary btn-block">Use Code at Checkout</a>
        <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <button type="submit" name="claim_offer" class="btn-accent">Claim My Promo Code</button>
            </form>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
