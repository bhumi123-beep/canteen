<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/MenuController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$menuController = new MenuController($db);
$item = $menuController->getActive($_GET['id'] ?? null);

if (!$item) {
    redirect('views/customer/home.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?= e($item['name']) ?> - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container narrow">
    <a href="<?= BASE_URL ?>/views/customer/home.php" class="link">&larr; Back to Menu</a>

    <div class="detail-layout">
        <div class="detail-img-placeholder">
            <?php if (!empty($item['image'])): ?>
                <img src="<?= BASE_URL ?>/public/images/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
            <?php else: ?>
                <span class="menu-image-placeholder">🍽️</span>
            <?php endif; ?>
        </div>
        <div>
            <div class="row-between">
                <h1><?= e($item['name']) ?></h1>
                <span class="price-lg">$<?= number_format($item['price'], 2) ?></span>
            </div>
            <div class="tags">
                <span class="tag">Popular</span>
                <span class="tag"><?= e($item['category_name'] ?? 'Uncategorized') ?></span>
            </div>
            <p><?= e($item['description']) ?></p>

            <div class="info-box">
                <strong>Stock available:</strong> <?= (int)$item['current_stock'] ?>
            </div>

            <form method="POST" action="<?= BASE_URL ?>/views/customer/cart.php" class="add-form">
                <?= csrfField() ?>
                <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                <label>Quantity</label>
                <input type="number" name="qty" value="1" min="1" max="<?= max(1,(int)$item['current_stock']) ?>">
                <button type="submit" name="add_to_cart" class="btn-primary btn-block" <?= $item['current_stock'] <= 0 ? 'disabled' : '' ?>>
                    🛒 Add to Cart - $<?= number_format($item['price'], 2) ?>
                </button>
            </form>
        </div>
    </div>
</main>
</body>
</html>
