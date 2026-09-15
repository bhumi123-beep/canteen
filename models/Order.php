<?php
class Order {
    private $conn;
    private $table = 'orders';

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureReviewColumns();
    }

    private function ensureReviewColumns() {
        $columns = [
            'prepared_by_staff_id' => "ALTER TABLE orders ADD COLUMN prepared_by_staff_id INT NULL AFTER served_by_staff_id",
            'public_review_token' => "ALTER TABLE orders ADD COLUMN public_review_token CHAR(64) NULL AFTER prepared_by_staff_id",
            'reservation_id' => "ALTER TABLE orders ADD COLUMN reservation_id INT NULL AFTER table_number",
        ];
        foreach ($columns as $column => $sql) {
            $check = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'orders' AND column_name = ?");
            $check->execute([$column]);
            if ((int)$check->fetchColumn() === 0) {
                $this->conn->exec($sql);
            }
        }
        $this->conn->exec("UPDATE orders SET public_review_token = SHA2(CONCAT(id, '-', order_number, '-', UUID()), 256) WHERE public_review_token IS NULL");
        try {
            $this->conn->exec("ALTER TABLE orders MODIFY COLUMN public_review_token CHAR(64) NOT NULL UNIQUE");
        } catch (PDOException $exception) {
            // Existing installations may already have a compatible unique key.
        }
    }

    public function create($userId, $subtotal, $tax, $serviceFee, $total, $orderType = 'takeaway', $instructions = '', $discountAmount = 0, $promoCode = null, $tableNumber = null, $reservationId = null) {
        $orderNumber = 'ORD-' . strtoupper(substr(uniqid(), -6));
        $publicReviewToken = bin2hex(random_bytes(32));
        $reservationId = $reservationId ? (int)$reservationId : null;
        $stmt = $this->conn->prepare("INSERT INTO orders
            (order_number, user_id, status, order_type, subtotal, discount_amount, promo_code, tax, service_fee, total_amount, special_instructions, table_number, reservation_id, public_review_token)
            VALUES (?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) ");
        $stmt->execute([$orderNumber, $userId, $orderType, $subtotal, $discountAmount, $promoCode, $tax, $serviceFee, $total, $instructions, $tableNumber, $reservationId, $publicReviewToken]);
        return $this->conn->lastInsertId();
    }

    public function find($id) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findForUser($id, $userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE id = ? AND user_id = ? LIMIT 1");
        $stmt->execute([(int)$id, (int)$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByPublicReviewToken($token) {
        $stmt = $this->conn->prepare("SELECT * FROM orders WHERE public_review_token = ? LIMIT 1");
        $stmt->execute([trim((string)$token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByUser($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ? ORDER BY order_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recent($limit = 10) {
        $stmt = $this->conn->prepare("SELECT o.*, u.name AS customer_name FROM {$this->table} o
            JOIN users u ON o.user_id = u.id ORDER BY o.order_at DESC LIMIT ?");
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function updateStatus($id, $status) {
        $allowedStatuses = ['pending', 'preparing', 'ready', 'completed', 'cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT status FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
        $currentStatus = $stmt->fetchColumn();
        $transitions = [
            'pending' => ['pending', 'preparing', 'cancelled'],
            'preparing' => ['preparing', 'ready', 'cancelled'],
            'ready' => ['ready', 'completed'],
            'completed' => ['completed'],
            'cancelled' => ['cancelled'],
        ];
        if (!$currentStatus || !in_array($status, $transitions[$currentStatus], true)) {
            return false;
        }

        $stmt = $this->conn->prepare("UPDATE {$this->table} SET status = ? WHERE id = ?");
        return $stmt->execute([$status, $id]);
    }

    public function todayStats() {
        $stmt = $this->conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE DATE(order_at) = CURDATE() AND status <> 'cancelled'");
        $stmt->execute();
        $revenue = $stmt->fetchColumn();
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_at) = CURDATE()");
        $stmt->execute();
        $totalOrders = $stmt->fetchColumn();
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM orders WHERE DATE(order_at) = CURDATE() AND status = 'pending'");
        $stmt->execute();
        $pending = $stmt->fetchColumn();
        return ['revenue' => $revenue, 'total_orders' => $totalOrders, 'pending' => $pending];
    }

    public function mostOrdered($period, $limit = 5) {
        $periodConditions = [
            'today' => 'DATE(o.order_at) = CURDATE()',
            'week' => 'YEARWEEK(o.order_at, 1) = YEARWEEK(CURDATE(), 1)',
        ];
        $condition = $periodConditions[$period] ?? $periodConditions['today'];
        $limit = max(1, (int)$limit);
        $stmt = $this->conn->prepare("SELECT mi.name, SUM(oi.quantity) AS quantity_ordered,
                SUM(oi.total_price) AS sales_amount
                FROM order_items oi
                JOIN orders o ON oi.order_id = o.id
                JOIN menu_items mi ON oi.menu_item_id = mi.id
                WHERE {$condition} AND o.status <> 'cancelled'
                GROUP BY mi.id, mi.name
                ORDER BY quantity_ordered DESC, sales_amount DESC
                LIMIT {$limit}");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

class OrderItem {
    private $conn;
    private $table = 'order_items';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($orderId, $menuItemId, $unitPrice, $qty) {
        $total = $unitPrice * $qty;
        $stmt = $this->conn->prepare("INSERT INTO {$this->table}
            (order_id, menu_item_id, unit_price, quantity, total_price) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$orderId, $menuItemId, $unitPrice, $qty, $total]);
        return $this->conn->lastInsertId();
    }

    public function findByOrder($orderId) {
        $stmt = $this->conn->prepare("SELECT oi.*, mi.name, mi.image FROM {$this->table} oi
            JOIN menu_items mi ON oi.menu_item_id = mi.id WHERE oi.order_id = ?");
        $stmt->execute([$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
