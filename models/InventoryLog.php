<?php
class InventoryLog {
    private $conn;
    private $table = 'inventory_logs';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function add($menuItemId, $action, $quantity, $previousStock, $newStock, $notes = null) {
        try {
            $stmt = $this->conn->prepare("INSERT INTO {$this->table}
                (menu_item_id, action, quantity, previous_stock, new_stock, notes, created_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())");
            return $stmt->execute([
                (int)$menuItemId,
                $action,
                (int)$quantity,
                (int)$previousStock,
                (int)$newStock,
                $notes
            ]);
        } catch (PDOException $e) {
            return false;
        }
    }

    public function recent($limit = 10) {
        try {
            $stmt = $this->conn->prepare("SELECT l.*, mi.name AS item_name
                FROM {$this->table} l
                LEFT JOIN menu_items mi ON mi.id = l.menu_item_id
                ORDER BY l.created_at DESC
                LIMIT ?");
            $stmt->bindValue(1, (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }
}
