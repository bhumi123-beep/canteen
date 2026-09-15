<?php
class Transaction {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->exec("CREATE TABLE IF NOT EXISTS transaction_voids (
            id INT AUTO_INCREMENT PRIMARY KEY,
            source_type ENUM('esewa','cashier') NOT NULL,
            source_id INT NOT NULL,
            reason VARCHAR(500) NOT NULL,
            voided_by INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY unique_void (source_type, source_id),
            FOREIGN KEY (voided_by) REFERENCES users(id) ON DELETE RESTRICT
        )");
    }

    public function createPending($orderId, $cashierId, $transactionUuid) {
        requireCashierPayments();
        $stmt = $this->conn->prepare("SELECT id, total_amount, status FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$orderId]);
        $order = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$order || $order['status'] === 'cancelled' || $order['status'] === 'completed') {
            return false;
        }

        $stmt = $this->conn->prepare("INSERT INTO transactions (order_id, transaction_uuid, amount, status, cashier_id) VALUES (?, ?, ?, 'PENDING', ?)");
        $cashierId = (int)$cashierId > 0 ? (int)$cashierId : null;
        $stmt->execute([(int)$order['id'], $transactionUuid, (float)$order['total_amount'], $cashierId]);
        return [
            'id' => (int)$this->conn->lastInsertId(),
            'order_id' => (int)$order['id'],
            'amount' => number_format((float)$order['total_amount'], 2, '.', ''),
            'transaction_uuid' => $transactionUuid,
        ];
    }

    public function findByUuid($transactionUuid) {
        $stmt = $this->conn->prepare("SELECT t.*, o.order_number, o.table_number, o.public_review_token, o.status AS order_status, o.total_amount, o.order_at FROM transactions t INNER JOIN orders o ON o.id = t.order_id WHERE t.transaction_uuid = ? LIMIT 1");
        $stmt->execute([$transactionUuid]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function markFailed($transactionUuid, $paymentRef = null) {
        $stmt = $this->conn->prepare("UPDATE transactions SET status = 'FAILED', payment_ref = COALESCE(?, payment_ref) WHERE transaction_uuid = ? AND status = 'PENDING'");
        $stmt->execute([$paymentRef, $transactionUuid]);
        return $stmt->rowCount() === 1;
    }

    public function markPaid($transactionUuid, $paymentRef) {
        requireCashierPayments();
        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("SELECT t.*, o.total_amount, o.status AS order_status FROM transactions t INNER JOIN orders o ON o.id = t.order_id WHERE t.transaction_uuid = ? FOR UPDATE");
            $stmt->execute([$transactionUuid]);
            $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$transaction || $transaction['status'] === 'FAILED' || (float)$transaction['amount'] !== (float)$transaction['total_amount']) {
                $this->conn->rollBack();
                return false;
            }
            if ($transaction['status'] === 'PAID') {
                $this->conn->commit();
                return true;
            }
            $update = $this->conn->prepare("UPDATE transactions SET status = 'PAID', payment_ref = ? WHERE id = ? AND status = 'PENDING'");
            $update->execute([(string)$paymentRef, (int)$transaction['id']]);
            if ($update->rowCount() !== 1) {
                $this->conn->rollBack();
                return false;
            }
            $orderUpdate = $this->conn->prepare("UPDATE orders SET status = 'completed' WHERE id = ? AND status IN ('pending', 'preparing', 'ready')");
            $orderUpdate->execute([(int)$transaction['order_id']]);
            $payment = $this->conn->prepare("INSERT INTO payments (order_id, method, status, transaction_ref, amount, paid_at) VALUES (?, 'esewa', 'success', ?, ?, NOW())");
            $payment->execute([(int)$transaction['order_id'], (string)$paymentRef, (float)$transaction['amount']]);
            $this->conn->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }

    public function allForAdmin() {
        requireAdmin();
        $sql = "SELECT t.id AS source_id, 'esewa' AS source_type, t.order_id, t.transaction_uuid AS reference,
            t.amount, t.status, t.created_at, 'eSewa' AS payment_method, u.name AS cashier_name,
            tv.reason AS void_reason, tv.created_at AS voided_at
            FROM transactions t LEFT JOIN staff_management sm ON sm.id = t.cashier_id LEFT JOIN users u ON u.email = sm.staff_email
            LEFT JOIN transaction_voids tv ON tv.source_type = 'esewa' AND tv.source_id = t.id
            UNION ALL
            SELECT ct.id AS source_id, 'cashier' AS source_type, ct.order_id, CONCAT('LOCAL-', ct.id) AS reference,
            ct.amount, ct.status, ct.paid_at AS created_at, ct.payment_method, sm.staff_name AS cashier_name,
            tv.reason AS void_reason, tv.created_at AS voided_at
            FROM cashier_transactions ct LEFT JOIN staff_management sm ON sm.id = ct.cashier_staff_id
            LEFT JOIN transaction_voids tv ON tv.source_type = 'cashier' AND tv.source_id = ct.id
            ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function void($sourceType, $sourceId, $reason, $adminId) {
        requireAdmin();
        $sourceType = in_array($sourceType, ['esewa', 'cashier'], true) ? $sourceType : '';
        $reason = trim((string)$reason);
        if ($sourceType === '' || (int)$sourceId < 1 || $reason === '' || strlen($reason) > 500) {
            return false;
        }
        $this->conn->beginTransaction();
        try {
            if ($sourceType === 'esewa') {
                $stmt = $this->conn->prepare("SELECT order_id, status FROM transactions WHERE id = ? FOR UPDATE");
                $stmt->execute([(int)$sourceId]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$transaction || $transaction['status'] === 'FAILED') {
                    $this->conn->rollBack();
                    return false;
                }
                $update = $this->conn->prepare("UPDATE transactions SET status = 'FAILED' WHERE id = ? AND status = 'PENDING'");
                $update->execute([(int)$sourceId]);
                $orderId = (int)$transaction['order_id'];
            } else {
                $stmt = $this->conn->prepare("SELECT order_id, status FROM cashier_transactions WHERE id = ? FOR UPDATE");
                $stmt->execute([(int)$sourceId]);
                $transaction = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$transaction || $transaction['status'] === 'refunded') {
                    $this->conn->rollBack();
                    return false;
                }
                $update = $this->conn->prepare("UPDATE cashier_transactions SET status = 'refunded' WHERE id = ? AND status = 'paid'");
                $update->execute([(int)$sourceId]);
                $orderId = (int)$transaction['order_id'];
            }
            $log = $this->conn->prepare("INSERT INTO transaction_voids (source_type, source_id, reason, voided_by) VALUES (?, ?, ?, ?)");
            $log->execute([$sourceType, (int)$sourceId, $reason, (int)$adminId]);
            $order = $this->conn->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ? AND status <> 'cancelled'");
            $order->execute([$orderId]);
            $this->conn->commit();
            return true;
        } catch (Throwable $exception) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            return false;
        }
    }
}
