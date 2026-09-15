<?php
require_once __DIR__ . '/../../controllers/CartController.php';
$cart = new CartController();
$cartCount = $cart->count();
$current = basename($_SERVER['PHP_SELF']);
?>
<header class="top-nav">
    <a href="<?= BASE_URL ?>/views/customer/home.php" class="brand">CanteenPro</a>
    <nav class="main-nav">
        <a href="<?= BASE_URL ?>/views/customer/home.php" class="<?= $current === 'home.php' ? 'active' : '' ?>">Home</a>
        <a href="<?= BASE_URL ?>/views/customer/home.php#menu">Menu</a>
        <a href="<?= BASE_URL ?>/views/customer/orders.php" class="<?= $current === 'orders.php' ? 'active' : '' ?>">Orders</a>
        <a href="<?= BASE_URL ?>/views/customer/reservation.php" class="<?= $current === 'reservation.php' ? 'active' : '' ?>">Reservation</a>
        <a href="<?= BASE_URL ?>/views/customer/promo.php" class="<?= $current === 'promo.php' ? 'active' : '' ?>">My Promo Code</a>
    </nav>
    <div class="nav-icons">
        <a href="<?= BASE_URL ?>/views/customer/cart.php" class="icon-btn">
            🛒 <?php if ($cartCount > 0): ?><span class="badge"><?= $cartCount ?></span><?php endif; ?>
        </a>
        <a href="<?= BASE_URL ?>/views/customer/profile.php" class="avatar">👤</a>
        <form method="POST" action="<?= BASE_URL ?>/logout.php">
            <?= csrfField('logout_csrf') ?>
            <button type="submit" class="logout-button">Logout</button>
        </form>
    </div>
</header>
