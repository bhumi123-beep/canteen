<?php
class CustomerReview {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->exec("ALTER TABLE staff_ratings MODIFY COLUMN customer_id INT NULL");
        $this->conn->exec("CREATE TABLE IF NOT EXISTS customer_review_decisions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            order_id INT NOT NULL UNIQUE,
            decision ENUM('skipped','completed') NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
        )");
    }

    public function orderForToken($token) {
        $stmt = $this->conn->prepare("SELECT o.* FROM orders o INNER JOIN payments p ON p.order_id = o.id AND p.status = 'success' WHERE o.public_review_token = ? AND o.status != 'cancelled' LIMIT 1");
        $stmt->execute([trim((string)$token)]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function staffForOrder($orderId) {
        $stmt = $this->conn->prepare("SELECT sm.id, sm.staff_name, sm.staff_role,
            CASE WHEN sm.id = o.served_by_staff_id THEN 'Waiter' WHEN sm.id = o.prepared_by_staff_id THEN 'Chef' END AS assignment
            FROM orders o INNER JOIN staff_management sm ON sm.id = o.served_by_staff_id OR sm.id = o.prepared_by_staff_id
            WHERE o.id = ? ORDER BY assignment, sm.staff_name");
        $stmt->execute([(int)$orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function decisionForOrder($orderId) {
        $stmt = $this->conn->prepare("SELECT decision FROM customer_review_decisions WHERE order_id = ? LIMIT 1");
        $stmt->execute([(int)$orderId]);
        return $stmt->fetchColumn() ?: null;
    }

    public function skip($orderId) {
        $stmt = $this->conn->prepare("INSERT INTO customer_review_decisions (order_id, decision) VALUES (?, 'skipped') ON DUPLICATE KEY UPDATE decision = decision");
        return $stmt->execute([(int)$orderId]);
    }

    public function addRating($token, $staffId, $rating, $comment) {
        $order = $this->orderForToken($token);
        if (!$order) {
            return false;
        }
        $staffId = (int)$staffId;
        $rating = (int)$rating;
        $comment = trim((string)$comment);
        if ($rating < 1 || $rating > 5 || strlen($comment) > 1000) {
            return false;
        }
        $staff = $this->conn->prepare("SELECT id FROM staff_management WHERE id = ? AND (id = ? OR id = ?) LIMIT 1");
        $staff->execute([$staffId, (int)$order['served_by_staff_id'], (int)$order['prepared_by_staff_id']]);
        if (!$staff->fetchColumn()) {
            return false;
        }
        $existing = $this->conn->prepare("SELECT id FROM staff_ratings WHERE staff_id = ? AND order_id = ? AND customer_id IS NULL LIMIT 1");
        $existing->execute([$staffId, (int)$order['id']]);
        if ($existing->fetchColumn()) {
            return false;
        }
        $insert = $this->conn->prepare("INSERT INTO staff_ratings (staff_id, customer_id, order_id, rating, comment) VALUES (?, NULL, ?, ?, ?)");
        return $insert->execute([$staffId, (int)$order['id'], $rating, $comment ?: null]);
    }

    public function complete($orderId) {
        $stmt = $this->conn->prepare("INSERT INTO customer_review_decisions (order_id, decision) VALUES (?, 'completed') ON DUPLICATE KEY UPDATE decision = 'completed'");
        return $stmt->execute([(int)$orderId]);
    }
}
