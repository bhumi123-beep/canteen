<?php
require_once __DIR__ . '/../models/Order.php';
require_once __DIR__ . '/../models/MenuItem.php';
require_once __DIR__ . '/../models/Ingredient.php';
require_once __DIR__ . '/../models/Promo.php';

class OrderController {
    private $conn;
    private $orderModel;
    private $orderItemModel;
    private $menuItemModel;
    private $ingredientModel;
    private $promoModel;

    public function __construct($db) {
        $this->conn = $db;
        $this->orderModel = new Order($db);
        $this->orderItemModel = new OrderItem($db);
        $this->menuItemModel = new MenuItem($db);
        $this->ingredientModel = new Ingredient($db);
        $this->promoModel = new Promo($db);
    }

    public function placeOrder($userId, $cartItems, $orderType = 'takeaway', $instructions = '', $discountAmount = 0, $promoCode = null, $tableNumber = null, $reservationId = null) {
        requireCustomer();
        $userId = (int)($_SESSION['user_id'] ?? 0);
        $orderType = Validator::orderType($orderType);
        $instructions = Validator::description($instructions);
        if ($orderType === false || $instructions === false || !is_array($cartItems)) {
            return ['success' => false, 'message' => 'Invalid order details.'];
        }
        $tableNumber = trim((string)$tableNumber);
        $reservationId = $reservationId ? (int)$reservationId : null;
        if ($orderType === 'dine-in') {
            if ($tableNumber === '' || strlen($tableNumber) > 20) {
                return ['success' => false, 'message' => 'Select a valid dining table.'];
            }
            $tableStmt = $this->conn->prepare('SELECT id FROM tables_ WHERE table_number = ? AND is_active = 1 LIMIT 1');
            $tableStmt->execute([$tableNumber]);
            if (!$tableStmt->fetchColumn()) {
                return ['success' => false, 'message' => 'The selected table is unavailable.'];
            }
        } else {
            $tableNumber = null;
        }
        if (empty($cartItems)) {
            return ['success' => false, 'message' => 'Cart is empty.'];
        }
        $this->conn->beginTransaction();
        try {
            $validatedItems = [];
            $subtotal = 0;
            foreach ($cartItems as $item) {
                $itemId = Validator::menuItemId($item['id'] ?? null);
                $quantity = Validator::quantity($item['qty'] ?? null);
                if ($itemId === false || $quantity === false) {
                    throw new RuntimeException('Invalid item quantity.');
                }

                $stmt = $this->conn->prepare("SELECT id, name, price, image, current_stock, is_active
                    FROM menu_items WHERE id = ? FOR UPDATE");
                $stmt->execute([$itemId]);
                $menuItem = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$menuItem || (int)$menuItem['is_active'] !== 1) {
                    throw new RuntimeException('One of the selected items is no longer available.');
                }
                if ((int)$menuItem['current_stock'] < $quantity) {
                    throw new RuntimeException($menuItem['name'] . ' does not have enough stock.');
                }

                $validatedItems[] = [
                    'id' => (int)$menuItem['id'],
                    'name' => $menuItem['name'],
                    'price' => (float)$menuItem['price'],
                    'image' => $menuItem['image'],
                    'qty' => $quantity,
                ];
                $subtotal += (float)$menuItem['price'] * $quantity;
            }

            $discountAmount = 0;
            $promoCode = strtoupper(trim((string)$promoCode));
            if ($promoCode !== '') {
                $promo = $this->promoModel->findValidForUser($userId, $promoCode);
                if (!$promo) {
                    throw new RuntimeException('The promo code is invalid or has already been used.');
                }
                $discountAmount = round($subtotal * ((int)$promo['discount_percent'] / 100), 2);
            } else {
                $promoCode = null;
            }
            $taxableSubtotal = $subtotal - $discountAmount;
            $tax = round($taxableSubtotal * 0.10, 2);
            $serviceFee = 1.00;
            $total = $taxableSubtotal + $tax + $serviceFee;
            $orderId = $this->orderModel->create($userId, $subtotal, $tax, $serviceFee, $total, $orderType, $instructions, $discountAmount, $promoCode, $tableNumber, $reservationId);
            $this->ingredientModel->reserveForOrder($validatedItems, $orderId);
            foreach ($validatedItems as $item) {
                $this->orderItemModel->create($orderId, $item['id'], $item['price'], $item['qty']);
                $this->menuItemModel->reduceStock($item['id'], $item['qty']);
            }
            $this->conn->commit();
            return ['success' => true, 'order_id' => $orderId, 'total' => $total];
        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    public function getOrder($id) {
        requireCustomer();
        return $this->orderModel->findForUser($id, (int)$_SESSION['user_id']);
    }

    public function getItems($orderId) {
        requireCustomer();
        if (!$this->orderModel->findForUser($orderId, (int)$_SESSION['user_id'])) {
            return [];
        }
        return $this->orderItemModel->findByOrder($orderId);
    }

    public function myOrders($userId) {
        requireCustomer();
        return $this->orderModel->findByUser($userId);
    }

    public function recent($limit = 10) {
        requireAdmin();
        return $this->orderModel->recent($limit);
    }

    public function updateStatus($id, $status) {
        requireAdmin();
        $this->orderModel->updateStatus($id, $status);
    }

    public function todayStats() {
        requireAdmin();
        return $this->orderModel->todayStats();
    }
}
