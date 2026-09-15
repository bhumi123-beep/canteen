<?php
class Promo {
    private $conn;
    private $table = 'promo_claims';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function claimForUser($userId) {
        $existing = $this->findByUser($userId);
        if ($existing) {
            return $existing;
        }

        $stmt = $this->conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
        $stmt->execute([$userId]);
        $customerName = $stmt->fetchColumn() ?: 'CUSTOMER';
        $customerName = strtoupper(preg_replace('/[^A-Z0-9]+/i', '-', $customerName));
        $customerName = trim(substr($customerName, 0, 18), '-');
        $offerName = 'HEALTHY-LUNCH-25OFF';
        $code = $customerName . '-' . $offerName;

        $suffix = 1;
        while (true) {
            $stmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE promo_code = ?");
            $stmt->execute([$code]);
            if (!$stmt->fetchColumn()) {
                break;
            }
            $code = $customerName . '-' . $offerName . '-' . $suffix++;
        }

        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (user_id, promo_code, discount_percent, shop_name) VALUES (?, ?, 25, 'CanteenPro')");
        $stmt->execute([$userId, $code]);
        return $this->findByUser($userId);
    }

    public function findByUser($userId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE user_id = ? LIMIT 1");
        $stmt->execute([$userId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function findValidForUser($userId, $code) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table}
            WHERE user_id = ? AND promo_code = ? AND shop_name = 'CanteenPro' LIMIT 1");
        $stmt->execute([$userId, strtoupper(trim($code))]);
        $claim = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$claim) {
            return false;
        }

        $stmt = $this->conn->prepare("SELECT o.id FROM orders o
            INNER JOIN payments p ON p.order_id = o.id
            WHERE o.user_id = ? AND o.promo_code = ?
            AND o.status <> 'cancelled' AND p.status = 'success' LIMIT 1");
        $stmt->execute([$userId, $claim['promo_code']]);
        return $stmt->fetchColumn() ? false : $claim;
    }

    public function all() {
        $stmt = $this->conn->prepare("SELECT p.*, u.name AS customer_name, u.email AS customer_email
            FROM promo_claims p
            JOIN users u ON p.user_id = u.id
            ORDER BY p.claimed_at DESC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
