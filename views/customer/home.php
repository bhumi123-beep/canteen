<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/MenuController.php';
require_once __DIR__ . '/../../models/Order.php';
require_once __DIR__ . '/../../models/Promo.php';
require_once __DIR__ . '/../../models/TableQrCode.php';

$database = new Database();
$db = $database->connect();
$tableQrCode = new TableQrCode($db);
$qrTable = $tableQrCode->resolve($_GET['table'] ?? '', $_GET['qr'] ?? '');
requireCustomer();
if ($qrTable) {
    $_SESSION['order_table_number'] = $qrTable;
}

$menuController = new MenuController($db);
$items = $menuController->listActive();
$categories = $menuController->categoryModel->all();
$mostOrdered = (new Order($db))->mostOrdered('week', 1)[0] ?? null;
$mostOrderedItem = null;
if ($mostOrdered) {
    foreach ($items as $item) {
        if ($item['name'] === $mostOrdered['name']) {
            $mostOrderedItem = $item;
            break;
        }
    }
}
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
<title>CanteenPro - Menu</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container">
    <div class="promo-banner">
        <div>
            <h1>25% OFF</h1>
            <p>On all healthy lunch options this week.</p>
        </div>
        <?php if ($promoClaim): ?>
            <div class="promo-code-box">
                <span class="small">Your <?= (int)$promoClaim['discount_percent'] ?>% promo code</span>
                <strong><?= e($promoClaim['promo_code']) ?></strong>
                <span class="small">Valid at <?= e($promoClaim['shop_name']) ?></span>
            </div>
        <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>
                <button type="submit" name="claim_offer" class="btn-accent">Claim Offer</button>
            </form>
        <?php endif; ?>
    </div>

    <div class="card row-between" style="margin-bottom: 24px; background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%); color: var(--white); padding: 20px 24px; border-radius: var(--radius);">
        <div>
            <h2 style="color:var(--white); margin:0 0 6px;">🪑 Planning to dine in?</h2>
            <p style="color:var(--admin-sidebar-text); margin:0;" class="small">Reserve your table in advance and skip the line!</p>
        </div>
        <a href="<?= BASE_URL ?>/views/customer/reservation.php" class="btn-primary" style="white-space:nowrap;">Book a Table &rarr;</a>
    </div>

    <?php if ($mostOrderedItem): ?>
        <section class="customer-popular-item">
            <div class="customer-popular-image">
                <?php if (!empty($mostOrderedItem['image'])): ?>
                    <img src="<?= BASE_URL ?>/public/images/<?= e($mostOrderedItem['image']) ?>" alt="<?= e($mostOrderedItem['name']) ?>">
                <?php else: ?>
                    <span>🍽️</span>
                <?php endif; ?>
            </div>
            <div>
                <span class="popular-label">Most Ordered by Customers</span>
                <h2><?= e($mostOrderedItem['name']) ?></h2>
                <p class="muted">Ordered <?= (int)$mostOrdered['quantity_ordered'] ?> times this week.</p>
                <a href="<?= BASE_URL ?>/views/customer/menu_item.php?id=<?= (int)$mostOrderedItem['id'] ?>" class="link">View item</a>
            </div>
        </section>
    <?php endif; ?>

    <h2 id="menu">Categories</h2>
    <div class="category-row">
        <button type="button" class="category-pill category-filter active" data-category="all">🍽️ All</button>
        <?php foreach ($categories as $cat): ?>
            <button type="button" class="category-pill category-filter" data-category="<?= (int)$cat['id'] ?>"><?= e($cat['name']) ?></button>
        <?php endforeach; ?>
    </div>

    <h2>Menu Items</h2>
    <div class="menu-grid">
        <?php foreach ($items as $item): ?>
            <div class="menu-card" data-category="<?= $item['category_id'] === null ? 'uncategorized' : (int)$item['category_id'] ?>">
                <div class="menu-card-img">
                    <?php if (!empty($item['image'])): ?>
                        <img src="<?= BASE_URL ?>/public/images/<?= e($item['image']) ?>" alt="<?= e($item['name']) ?>">
                    <?php else: ?>
                        <span class="menu-image-placeholder">🍽️</span>
                    <?php endif; ?>
                    <?= $item['current_stock'] <= 0 ? '<span class="badge-status sold-out">Sold Out</span>' : '<span class="badge-status available">Available</span>' ?>
                </div>
                <div class="menu-card-body">
                    <div class="row-between">
                        <strong><?= e($item['name']) ?></strong>
                        <span class="price">$<?= number_format($item['price'], 2) ?></span>
                    </div>
                    <p class="muted small"><?= e($item['description']) ?></p>
                    <div class="row-between">
                        <a href="<?= BASE_URL ?>/views/customer/menu_item.php?id=<?= $item['id'] ?>" class="link small">View details</a>
                    </div>
                    <form method="POST" action="<?= BASE_URL ?>/views/customer/cart.php">
                        <?= csrfField() ?>
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <input type="hidden" name="qty" value="1">
                        <button type="submit" name="add_to_cart" class="btn-primary btn-block" <?= $item['current_stock'] <= 0 ? 'disabled' : '' ?>>
                            + Add to Cart
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
        <?php if (empty($items)): ?>
            <p class="muted">No menu items available right now.</p>
        <?php endif; ?>
    </div>
</main>
<script>
document.querySelectorAll('.category-filter').forEach(filter => {
    filter.addEventListener('click', () => {
        const selectedCategory = filter.dataset.category;
        document.querySelectorAll('.category-filter').forEach(item => item.classList.remove('active'));
        filter.classList.add('active');
        document.querySelectorAll('.menu-card').forEach(card => {
            card.hidden = selectedCategory !== 'all' && card.dataset.category !== selectedCategory;
        });
    });
});
</script>
</body>
</html>
