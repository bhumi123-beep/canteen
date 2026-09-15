<?php
class Payment {
    private $conn;
    private $table = 'payments';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($orderId, $method, $amount) {
        $method = Validator::paymentMethod($method);
        $orderId = Validator::positiveInteger($orderId);
        if ($method === false || $orderId === false) {
            return false;
        }
        $stmt = $this->conn->prepare("SELECT total_amount, status FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || in_array($order['status'], ['cancelled', 'completed'], true) || abs((float)$amount - (float)$order['total_amount']) > 0.01) {
            return false;
        }
        $existing = $this->conn->prepare("SELECT id FROM {$this->table} WHERE order_id = ? AND status IN ('pending', 'success') LIMIT 1");
        $existing->execute([$orderId]);
        if ($existing->fetchColumn()) {
            return false;
        }
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (order_id, method, status, amount) VALUES (?, ?, 'pending', ?)");
        $stmt->execute([$orderId, $method, (float)$order['total_amount']]);
        return $this->conn->lastInsertId();
    }

    public function markSuccess($id, $orderId) {
        $ref = 'TXN' . strtoupper(bin2hex(random_bytes(4)));
        $stmt = $this->conn->prepare("UPDATE {$this->table}
            SET status = 'success', transaction_ref = ?, paid_at = NOW()
            WHERE id = ? AND order_id = ? AND status = 'pending'");
        $stmt->execute([$ref, $id, $orderId]);
        return $stmt->rowCount() === 1 ? $ref : false;
    }

    public function findByOrder($orderId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE order_id = ? ORDER BY id DESC LIMIT 1");
        $stmt->execute([$orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findByOrderForUser($orderId, $userId) {
        $stmt = $this->conn->prepare("SELECT p.* FROM {$this->table} p INNER JOIN orders o ON o.id = p.order_id WHERE p.order_id = ? AND o.user_id = ? ORDER BY p.id DESC LIMIT 1");
        $stmt->execute([(int)$orderId, (int)$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
