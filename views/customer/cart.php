<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../controllers/MenuController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$menuController = new MenuController($db);
$cart = new CartController();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf()) {
        flash('error', 'Your cart form expired. Please try again.');
    } elseif (isset($_POST['add_to_cart'])) {
        $itemId = Validator::menuItemId($_POST['item_id'] ?? null);
        $item = $itemId !== false ? $menuController->getActive($itemId) : null;
        if ($item && (int)$item['is_active'] === 1) {
            $cart->add($item['id'], $_POST['qty'] ?? null);
        }
    } elseif (isset($_POST['update_qty'])) {
        $cart->updateQty($_POST['item_id'] ?? null, $_POST['update_qty'] ?? null);
    } elseif (isset($_POST['remove_item'])) {
        $cart->remove($_POST['item_id'] ?? null);
    }
    redirect('views/customer/cart.php');
}

$items = [];
foreach ($cart->items() as $cartItem) {
    $menuItem = $menuController->getActive($cartItem['id'] ?? null);
    if (!$menuItem) {
        $cart->remove($cartItem['id'] ?? null);
        continue;
    }
    $items[] = [
        'id' => (int)$menuItem['id'],
        'name' => $menuItem['name'],
        'price' => (float)$menuItem['price'],
        'qty' => (int)$cartItem['qty'],
        'image' => $menuItem['image'],
    ];
}
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['qty'];
}
$tax = round($subtotal * 0.10, 2);
$serviceFee = !empty($items) ? 1.00 : 0;
$total = $subtotal + $tax + $serviceFee;
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Your Cart - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<?php include __DIR__ . '/../partials/header_customer.php'; ?>

<main class="container">
    <h1>Your Cart</h1>

    <div class="cart-layout">
        <div>
            <?php if (empty($items)): ?>
                <p class="muted">Your cart is empty. <a href="<?= BASE_URL ?>/views/customer/home.php" class="link">Browse the menu</a>.</p>
            <?php endif; ?>
            <?php foreach ($items as $item): ?>
                <div class="cart-item-card">
                    <div class="cart-item-img">🍽️</div>
                    <div class="cart-item-info">
                        <strong><?= e($item['name']) ?></strong>
                        <div class="price"><?= '$' . number_format($item['price'], 2) ?></div>
                        <form method="POST" class="qty-form">
                            <?= csrfField() ?>
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" name="update_qty" value="<?= $item['qty'] - 1 ?>" class="qty-btn">-</button>
                            <input type="text" readonly value="<?= $item['qty'] ?>" class="qty-display">
                            <button type="submit" name="update_qty" value="<?= $item['qty'] + 1 ?>" class="qty-btn" onclick="this.form.querySelector('[name=update_qty]').value=<?= $item['qty']+1 ?>">+</button>
                        </form>
                    </div>
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                        <button type="submit" name="remove_item" class="link-danger">🗑 Remove</button>
                    </form>
                </div>
            <?php endforeach; ?>
            <?php if (!empty($items)): ?>
                <a href="<?= BASE_URL ?>/views/customer/home.php" class="link">+ Add more items</a>
            <?php endif; ?>
        </div>

        <div class="order-summary-card">
            <h3>Order Summary</h3>
            <div class="row-between"><span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span></div>
            <div class="row-between"><span>Tax (10%)</span><span>$<?= number_format($tax, 2) ?></span></div>
            <div class="row-between"><span>Service Fee</span><span>$<?= number_format($serviceFee, 2) ?></span></div>
            <hr>
            <div class="row-between total-row"><strong>Total</strong><strong>$<?= number_format($total, 2) ?></strong></div>
            <a href="<?= BASE_URL ?>/views/customer/checkout.php" class="btn-primary btn-block <?= empty($items) ? 'disabled' : '' ?>">Checkout &rarr;</a>
            <p class="muted small center">Taxes and fees calculated at checkout.</p>
        </div>
    </div>
</main>
</body>
</html>
