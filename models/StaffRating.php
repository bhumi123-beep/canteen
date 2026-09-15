<?php
class StaffRating {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    public function ensureTable() {
        $this->conn->exec("
            CREATE TABLE IF NOT EXISTS staff_ratings (
                id INT AUTO_INCREMENT PRIMARY KEY,
                staff_id INT NOT NULL,
                customer_id INT NOT NULL,
                order_id INT NULL,
                rating TINYINT UNSIGNED NOT NULL,
                comment TEXT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY unique_order_rating (customer_id, order_id),
                FOREIGN KEY (staff_id) REFERENCES staff_management(id) ON DELETE CASCADE,
                FOREIGN KEY (customer_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
            )
        ");
        $this->conn->exec("ALTER TABLE staff_ratings MODIFY COLUMN customer_id INT NULL");
    }

    public function add($staffId, $customerId, $orderId, $rating, $comment = '') {
        $this->ensureTable();
        $rating = max(1, min(5, (int)$rating));
        $comment = trim((string)$comment);

        $stmt = $this->conn->prepare("SELECT o.id FROM orders o
            INNER JOIN payments p ON p.order_id = o.id
            INNER JOIN staff_management sm ON sm.id = ? AND sm.is_active = 1 AND sm.staff_status <> 'removed'
            WHERE o.id = ? AND o.user_id = ? AND p.status = 'success' AND o.status = 'completed'");
        $stmt->execute([$staffId, $orderId, $customerId]);
        if (!$stmt->fetchColumn()) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT id FROM staff_ratings WHERE customer_id = ? AND order_id = ? LIMIT 1");
        $stmt->execute([$customerId, $orderId]);
        if ($stmt->fetchColumn()) {
            return false;
        }

        $stmt = $this->conn->prepare("INSERT INTO staff_ratings (staff_id, customer_id, order_id, rating, comment) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$staffId, $customerId, $orderId, $rating, $comment]);
        return (int)$this->conn->lastInsertId();
    }

    public function averageForStaff($staffId) {
        $this->ensureTable();
        $stmt = $this->conn->prepare("SELECT AVG(rating) AS avg_rating, COUNT(*) AS rating_count FROM staff_ratings WHERE staff_id = ?");
        $stmt->execute([$staffId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'avg_rating' => (float)($row['avg_rating'] ?? 0),
            'rating_count' => (int)($row['rating_count'] ?? 0),
        ];
    }

    public function activeStaff() {
        $stmt = $this->conn->prepare("SELECT id, staff_name, staff_role FROM staff_management WHERE is_active = 1 AND deleted_at IS NULL AND staff_status <> 'removed' ORDER BY staff_name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function listForStaff($staffId, $limit = 10) {
        $this->ensureTable();
        $stmt = $this->conn->prepare("SELECT sr.*, u.name AS customer_name FROM staff_ratings sr JOIN users u ON u.id = sr.customer_id WHERE sr.staff_id = ? ORDER BY sr.created_at DESC LIMIT ?");
        $stmt->bindValue(1, $staffId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
