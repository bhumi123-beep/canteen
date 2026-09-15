<?php
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../controllers/CartController.php';
require_once __DIR__ . '/../../controllers/OrderController.php';
require_once __DIR__ . '/../../controllers/PaymentController.php';
require_once __DIR__ . '/../../controllers/Mailer.php';
require_once __DIR__ . '/../../models/Promo.php';
require_once __DIR__ . '/../../models/User.php';
require_once __DIR__ . '/../../models/Table.php';
require_once __DIR__ . '/../../controllers/ReservationController.php';
require_once __DIR__ . '/../../controllers/MenuController.php';
requireCustomer();

$database = new Database();
$db = $database->connect();
$cart = new CartController();
$orderController = new OrderController($db);
$reservationController = new ReservationController($db);
$myUpcomingReservations = $reservationController->myUpcomingReservations($_SESSION['user_id']);
$paymentController = new PaymentController($db);
$promo = new Promo($db);
$userModel = new User($db);
$tableModel = new TableModel($db);
$availableTables = $tableModel->all();
$menuController = new MenuController($db);

$items = [];
foreach ($cart->items() as $cartItem) {
    $menuItem = $menuController->getActive($cartItem['id'] ?? null);
    if ($menuItem) {
        $items[] = [
            'id' => (int)$menuItem['id'],
            'name' => $menuItem['name'],
            'price' => (float)$menuItem['price'],
            'qty' => (int)$cartItem['qty'],
            'image' => $menuItem['image'],
        ];
    }
}
if (empty($items)) {
    redirect('views/customer/cart.php');
}

$subtotal = 0;
foreach ($items as $item) {
    $subtotal += $item['price'] * $item['qty'];
}
$promoCode = trim($_SESSION['checkout_promo_code'] ?? '');
$defaultTableNumber = trim($_SESSION['order_table_number'] ?? '');
$promoClaim = $promoCode ? $promo->findValidForUser($_SESSION['user_id'], $promoCode) : null;
$discountAmount = $promoClaim ? round($subtotal * ((int)$promoClaim['discount_percent'] / 100), 2) : 0;
$taxableSubtotal = $subtotal - $discountAmount;
$tax = round($taxableSubtotal * 0.10, 2);
$serviceFee = 1.00;
$total = $taxableSubtotal + $tax + $serviceFee;

$step = $_SESSION['checkout_order_id'] ?? null ? 'verify' : 'select';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && verifyCsrf()) {
    if (isset($_POST['place_order'])) {
        // Step 1: create order + payment record, "send OTP"
        $method = Validator::paymentMethod($_POST['method'] ?? '');
        $orderType = Validator::orderType($_POST['order_type'] ?? '');
        $tableNumber = trim($_POST['table_number'] ?? '');
        $reservationId = Validator::positiveInteger($_POST['reservation_id'] ?? null);
        if ($defaultTableNumber !== '') {
            $tableNumber = $defaultTableNumber;
        }
        if ($method === false || $orderType === false) {
            $error = 'Select a valid payment method and order type.';
            $step = 'select';
        } elseif ($orderType === 'takeaway') {
            $tableNumber = null;
            $reservationId = null;
        }
        $promoCode = strtoupper(trim($_POST['promo_code'] ?? ''));
        $promoClaim = $error === '' && $promoCode ? $promo->findValidForUser($_SESSION['user_id'], $promoCode) : null;
        if ($promoCode && !$promoClaim) {
            $error = 'That promo code is invalid, expired, or has already been used.';
            $step = 'select';
        } else {
            $discountAmount = $promoClaim ? round($subtotal * ((int)$promoClaim['discount_percent'] / 100), 2) : 0;
            $_SESSION['checkout_promo_code'] = $promoClaim['promo_code'] ?? '';
            $result = $orderController->placeOrder($_SESSION['user_id'], $items, $orderType, '', $discountAmount, $promoClaim['promo_code'] ?? null, $tableNumber, $reservationId);
        }
        if (isset($result) && $result['success']) {
            $paymentId = $paymentController->initiate($result['order_id'], $method, $result['total']);
            if ($paymentId === false) {
                $error = 'A payment is already pending for this order. Please refresh and try again.';
                $step = 'select';
            } else {
                $_SESSION['checkout_order_id'] = $result['order_id'];
                $_SESSION['checkout_payment_id'] = $paymentId;
                $_SESSION['checkout_otp'] = strval(random_int(1000, 9999));
                $_SESSION['checkout_otp_expires_at'] = time() + 300;
                $_SESSION['checkout_otp_attempts'] = 0;

                $customer = $userModel->findById($_SESSION['user_id']);
                $customerEmail = $customer['email'] ?? '';
                $customerName = $customer['name'] ?? 'Customer';
                if ($customerEmail !== '' && !Mailer::sendOtp($customerEmail, $customerName, $_SESSION['checkout_otp'])) {
                    unset($_SESSION['checkout_order_id'], $_SESSION['checkout_payment_id'], $_SESSION['checkout_otp'], $_SESSION['checkout_otp_expires_at'], $_SESSION['checkout_otp_attempts'], $_SESSION['checkout_promo_code']);
                    $error = 'We could not send the verification code to your email. Please try again.';
                    $step = 'select';
                } else {
                    $step = 'verify';
                }
            }
        } elseif (isset($result)) {
            $error = $result['message'];
        }
    } elseif (isset($_POST['verify_otp'])) {
        // Step 2: verify OTP and confirm payment
        $entered = trim($_POST['otp'] ?? '');
        $otpValid = preg_match('/\A\d{4}\z/', $entered)
            && isset($_SESSION['checkout_otp'], $_SESSION['checkout_otp_expires_at'])
            && time() <= (int)$_SESSION['checkout_otp_expires_at']
            && (int)($_SESSION['checkout_otp_attempts'] ?? 0) < 5
            && hash_equals((string)$_SESSION['checkout_otp'], $entered);
        if ($otpValid) {
            $paymentReference = $paymentController->confirm($_SESSION['checkout_payment_id'], $_SESSION['checkout_order_id']);
            if (!$paymentReference) {
                $error = 'This payment session is invalid or has already been completed.';
                $step = 'verify';
            } else {
                $orderId = $_SESSION['checkout_order_id'];
                $cart->clear();
                unset($_SESSION['checkout_order_id'], $_SESSION['checkout_payment_id'], $_SESSION['checkout_otp'], $_SESSION['checkout_otp_expires_at'], $_SESSION['checkout_otp_attempts'], $_SESSION['checkout_promo_code']);

                redirect('views/customer/order_tracking.php?id=' . $orderId);
            }
        } else {
            $_SESSION['checkout_otp_attempts'] = (int)($_SESSION['checkout_otp_attempts'] ?? 0) + 1;
            if ($_SESSION['checkout_otp_attempts'] >= 5 || empty($_SESSION['checkout_otp_expires_at']) || time() > (int)$_SESSION['checkout_otp_expires_at']) {
                unset($_SESSION['checkout_otp'], $_SESSION['checkout_otp_expires_at'], $_SESSION['checkout_otp_attempts']);
                $error = 'This verification code has expired. Please start checkout again.';
            } else {
                $error = 'Incorrect OTP. Please try again.';
            }
            $step = 'verify';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Checkout - CanteenPro</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/public/css/style.css">
</head>
<body>
<header class="top-nav">
    <a href="<?= BASE_URL ?>/views/customer/cart.php" class="brand">&larr; CanteenPro</a>
    <div class="secure-badge">🔒 Secure Checkout</div>
</header>

<main class="container">
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

    <div class="checkout-layout">
        <div>
            <div class="card">
                <h3>Order Summary</h3>
                <?php foreach ($items as $item): ?>
                    <div class="row-between summary-line">
                        <span><?= e($item['name']) ?> <span class="muted">x<?= $item['qty'] ?></span></span>
                        <span>$<?= number_format($item['price'] * $item['qty'], 2) ?></span>
                    </div>
                <?php endforeach; ?>
                <hr>
                <div class="row-between"><span>Subtotal</span><span>$<?= number_format($subtotal, 2) ?></span></div>
                <?php if ($promoClaim): ?><div class="row-between discount-row"><span>Promo (<?= e($promoClaim['promo_code']) ?>)</span><span>- $<?= number_format($discountAmount, 2) ?></span></div><?php endif; ?>
                <div class="row-between"><span>Tax</span><span>$<?= number_format($tax, 2) ?></span></div>
                <div class="row-between total-row"><strong>Total</strong><strong>$<?= number_format($total, 2) ?></strong></div>
            </div>

            <?php if ($step === 'select'): ?>
            <form method="POST" class="card">
                <?= csrfField() ?>
                <h3>Select Payment Method</h3>
                <label for="promo-code">Promo Code</label>
                <div class="promo-input-row">
                    <input type="text" id="promo-code" name="promo_code" value="<?= e($promoCode) ?>" placeholder="CUSTOMER-HEALTHY-LUNCH-25OFF">
                </div>
                <p class="muted small">Enter the code you claimed from CanteenPro.</p>
                <label for="order-type">Order type</label>
                <select name="order_type" id="order-type">
                    <option value="takeaway" <?= $defaultTableNumber === '' ? 'selected' : '' ?>>Takeaway</option>
                    <option value="dine-in" <?= $defaultTableNumber !== '' ? 'selected' : '' ?>>Dine-in</option>
                </select>
                <?php if (!empty($myUpcomingReservations)): ?>
                    <div class="card" style="background:var(--light-surface); border:1px solid var(--primary); margin:14px 0; padding:12px;">
                        <strong style="color:var(--primary-dark);">📅 Link to your confirmed reservation:</strong>
                        <select name="reservation_id" id="reservation-id" onchange="if(this.value){ const tbl = this.options[this.selectedIndex].dataset.table; if(tbl){ document.getElementById('order-type').value='dine-in'; toggleTableField(); const tSelect = document.getElementById('table-number'); if(tSelect){ tSelect.value=tbl; } } }">
                            <option value="">No reservation linked (Standard order)</option>
                            <?php foreach ($myUpcomingReservations as $res): ?>
                                <option value="<?= (int)$res['id'] ?>" data-table="<?= e($res['table_number']) ?>">
                                    Table <?= e($res['table_number']) ?> (<?= e($res['reservation_date']) ?> <?= date('g:i A', strtotime($res['start_time'])) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <?php if ($defaultTableNumber !== ''): ?>
                    <input type="hidden" name="table_number" value="<?= e($defaultTableNumber) ?>" readonly>
                <?php else: ?>
                    <div id="table-field">
                        <label for="table-number">Table number <span class="muted small">(for dine-in)</span></label>
                        <select id="table-number" name="table_number" required>
                            <option value="">Select a table</option>
                            <?php foreach ($availableTables as $table): ?>
                                <option value="<?= e($table['table_number']) ?>">
                                    Table <?= e($table['table_number']) ?> - <?= e($table['capacity']) ?> seats - <?= e($table['location']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="payment-methods">
                    <label class="payment-option">
                        <input type="radio" name="method" value="esewa" checked>
                        <span>eSewa</span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="method" value="khalti">
                        <span>Khalti</span>
                    </label>
                    <label class="payment-option">
                        <input type="radio" name="method" value="cash">
                        <span>Cash on Pickup</span>
                    </label>
                </div>
                <button type="submit" name="place_order" class="btn-primary btn-block">Continue to Verification</button>
            </form>
            <?php endif; ?>
        </div>

        <div>
            <?php if ($step === 'verify'): ?>
            <form method="POST">
                <?= csrfField() ?>
                <div class="card verify-card">
                    <div class="section-header">
                        <h3>🛡 Verification</h3>
                        <span class="mini-tag">Secure payment</span>
                    </div>
                    <p class="muted small otp-text">Enter the 4-digit OTP sent to your registered email address to confirm payment.</p>

                    <div class="form-group">
                        <input type="text" name="otp" maxlength="4" placeholder="••••" class="otp-input" required>
                    </div>

                    <button type="submit" name="verify_otp" class="btn-primary btn-block">✔ Pay Now</button>
                </div>
            </form>
            <?php endif; ?>
            <p class="muted small center">🔒 256-bit Secure Encryption</p>
        </div>
    </div>
</main>
<?php if ($defaultTableNumber === ''): ?>
<script>
    const orderType = document.getElementById('order-type');
    const tableField = document.getElementById('table-field');
    const tableNumber = document.getElementById('table-number');

    function toggleTableField() {
        const isDineIn = orderType.value === 'dine-in';
        tableField.hidden = !isDineIn;
        tableNumber.required = isDineIn;
    }

    orderType.addEventListener('change', toggleTableField);
    toggleTableField();
</script>
<?php endif; ?>
</body>
</html>
