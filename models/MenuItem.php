<?php
class MenuItem {
    private $conn;
    private $table = 'menu_items';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function all($onlyActive = false) {
        $sql = "SELECT mi.*, c.name AS category_name FROM menu_items mi
                LEFT JOIN categories c ON mi.category_id = c.id";
        if ($onlyActive) $sql .= " WHERE mi.is_active = 1";
        $sql .= " ORDER BY mi.id DESC";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function find($id) {
        $stmt = $this->conn->prepare("SELECT mi.*, c.name AS category_name FROM {$this->table} mi
                LEFT JOIN categories c ON mi.category_id = c.id WHERE mi.id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function byCategory($categoryId) {
        $stmt = $this->conn->prepare("SELECT * FROM {$this->table} WHERE category_id = ? AND is_active = 1");
        $stmt->execute([$categoryId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($data) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table}
            (category_id, name, description, price, image, current_stock, reorder_level, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([
            $data['category_id'], $data['name'], $data['description'], $data['price'],
            $data['image'], $data['current_stock'], $data['reorder_level'], $data['is_active']
        ]);
        return $this->conn->lastInsertId();
    }

    public function update($id, $data) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET
            category_id = ?, name = ?, description = ?, price = ?, current_stock = ?,
            reorder_level = ?, is_active = ?, image = ? WHERE id = ?");
        $stmt->execute([
            $data['category_id'], $data['name'], $data['description'], $data['price'],
            $data['current_stock'], $data['reorder_level'], $data['is_active'], $data['image'], $id
        ]);
    }

    public function toggleAvailability($id) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET is_active = NOT is_active WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function delete($id) {
        $stmt = $this->conn->prepare("DELETE FROM {$this->table} WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function reduceStock($id, $qty) {
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET current_stock = current_stock - ?
            WHERE id = ? AND current_stock >= ?");
        $stmt->execute([$qty, $id, $qty]);
        return $stmt->rowCount() === 1;
    }

    public function counts() {
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM menu_items");
        $stmt->execute();
        $total = $stmt->fetchColumn();
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM menu_items WHERE current_stock <= reorder_level AND current_stock > 0");
        $stmt->execute();
        $low = $stmt->fetchColumn();
        $stmt = $this->conn->prepare("SELECT COUNT(*) FROM menu_items WHERE is_active = 0");
        $stmt->execute();
        $unavailable = $stmt->fetchColumn();
        return ['total' => $total, 'low' => $low, 'unavailable' => $unavailable];
    }

    public function lowStock() {
        $stmt = $this->conn->prepare("SELECT * FROM menu_items WHERE current_stock <= reorder_level ORDER BY current_stock ASC LIMIT 5");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function reorderQueue() {
        $stmt = $this->conn->prepare("SELECT * FROM menu_items WHERE current_stock <= reorder_level AND is_active = 1 ORDER BY current_stock ASC, name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recommendedRestockQty($currentStock, $reorderLevel) {
        $currentStock = max((int)$currentStock, 0);
        $reorderLevel = max((int)$reorderLevel, 0);
        return max($reorderLevel + 10 - $currentStock, 1);
    }

    public function autoReorder($id, $qty = null) {
        $item = $this->find($id);
        if (!$item) {
            return false;
        }

        $recommendedQty = $qty ?? $this->recommendedRestockQty(
            (int)($item['current_stock'] ?? 0),
            (int)($item['reorder_level'] ?? 0)
        );

        $stmt = $this->conn->prepare("UPDATE {$this->table} SET current_stock = current_stock + ? WHERE id = ?");
        return $stmt->execute([$recommendedQty, $id]);
    }

    public function updateStock($id, $deltaQty) {
        $deltaQty = (int)$deltaQty;
        $item = $this->find($id);
        if (!$item) {
            return false;
        }

        $previousStock = (int)($item['current_stock'] ?? 0);
        $newStock = max($previousStock + $deltaQty, 0);
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET current_stock = ? WHERE id = ?");
        $result = $stmt->execute([$newStock, $id]);

        if ($result) {
            $action = $deltaQty >= 0 ? 'restock' : 'adjustment';
            $log = new InventoryLog($this->conn);
            $log->add($id, $action, $deltaQty, $previousStock, $newStock, $deltaQty >= 0 ? 'Vendor restock' : 'Manual stock adjustment');
        }

        return $result;
    }

    public function updateReorderLevel($id, $reorderLevel) {
        $level = max((int)$reorderLevel, 0);
        $stmt = $this->conn->prepare("UPDATE {$this->table} SET reorder_level = ? WHERE id = ?");
        return $stmt->execute([$level, $id]);
    }
}
