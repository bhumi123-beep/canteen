<?php
class CartController {
    public function __construct() {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
    }

    public function add($itemId, $qty, $name = '', $price = 0, $image = '') {
        $itemId = Validator::menuItemId($itemId);
        $qty = Validator::quantity($qty);
        if ($itemId === false || $qty === false) {
            return false;
        }
        if (isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId]['qty'] += $qty;
        } else {
            $_SESSION['cart'][$itemId] = [
                'id' => $itemId, 'qty' => $qty
            ];
        }
        $_SESSION['cart'][$itemId]['qty'] = min((int)$_SESSION['cart'][$itemId]['qty'], 1000);
        return true;
    }

    public function updateQty($itemId, $qty) {
        $itemId = Validator::menuItemId($itemId);
        $qty = Validator::quantity($qty);
        if ($itemId === false) {
            return false;
        }
        if ($qty === false) {
            unset($_SESSION['cart'][$itemId]);
        } elseif (isset($_SESSION['cart'][$itemId])) {
            $_SESSION['cart'][$itemId]['qty'] = $qty;
        }
        return true;
    }

    public function remove($itemId) {
        $itemId = Validator::menuItemId($itemId);
        if ($itemId !== false) {
            unset($_SESSION['cart'][$itemId]);
        }
    }

    public function items() {
        return $_SESSION['cart'];
    }

    public function count() {
        return array_sum(array_column($_SESSION['cart'], 'qty'));
    }

    public function subtotal() {
        $sum = 0;
        foreach ($_SESSION['cart'] as $item) {
            $sum += (float)($item['price'] ?? 0) * (int)($item['qty'] ?? 0);
        }
        return $sum;
    }

    public function clear() {
        $_SESSION['cart'] = [];
    }
}
