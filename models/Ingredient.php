<?php
class Ingredient {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->ensureSchema();
    }

    private function ensureSchema() {
        $this->conn->exec("CREATE TABLE IF NOT EXISTS ingredients (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(150) NOT NULL UNIQUE,
            unit VARCHAR(30) NOT NULL DEFAULT 'unit',
            current_stock DECIMAL(10,3) NOT NULL DEFAULT 0,
            reorder_level DECIMAL(10,3) NOT NULL DEFAULT 0,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        $this->conn->exec("CREATE TABLE IF NOT EXISTS recipe_ingredients (
            menu_item_id INT NOT NULL,
            ingredient_id INT NOT NULL,
            quantity_per_item DECIMAL(10,3) NOT NULL,
            PRIMARY KEY (menu_item_id, ingredient_id),
            FOREIGN KEY (menu_item_id) REFERENCES menu_items(id) ON DELETE CASCADE,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE
        )");
        $this->conn->exec("CREATE TABLE IF NOT EXISTS ingredient_inventory_logs (
            id INT AUTO_INCREMENT PRIMARY KEY,
            ingredient_id INT NOT NULL,
            order_id INT NULL,
            action VARCHAR(40) NOT NULL,
            quantity DECIMAL(10,3) NOT NULL,
            previous_stock DECIMAL(10,3) NOT NULL,
            new_stock DECIMAL(10,3) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (ingredient_id) REFERENCES ingredients(id) ON DELETE CASCADE,
            FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE SET NULL
        )");
    }

    public function all() {
        $stmt = $this->conn->prepare("SELECT * FROM ingredients WHERE is_active = 1 ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function recipesForMenuItem($menuItemId) {
        $stmt = $this->conn->prepare("SELECT ri.*, i.name, i.unit, i.current_stock FROM recipe_ingredients ri INNER JOIN ingredients i ON i.id = ri.ingredient_id WHERE ri.menu_item_id = ? ORDER BY i.name");
        $stmt->execute([(int)$menuItemId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function add($name, $unit, $stock, $reorderLevel) {
        requireAdmin();
        $stmt = $this->conn->prepare("INSERT INTO ingredients (name, unit, current_stock, reorder_level) VALUES (?, ?, ?, ?)");
        return $stmt->execute([trim($name), trim($unit) ?: 'unit', max(0, (float)$stock), max(0, (float)$reorderLevel)]);
    }

    public function saveRecipe($menuItemId, $ingredientId, $quantity) {
        requireAdmin();
        if ((float)$quantity <= 0) {
            return false;
        }
        $stmt = $this->conn->prepare("INSERT INTO recipe_ingredients (menu_item_id, ingredient_id, quantity_per_item) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE quantity_per_item = VALUES(quantity_per_item)");
        return $stmt->execute([(int)$menuItemId, (int)$ingredientId, (float)$quantity]);
    }

    public function reserveForOrder($cartItems, $orderId) {
        foreach ($cartItems as $item) {
            $stmt = $this->conn->prepare("SELECT ri.ingredient_id, ri.quantity_per_item, i.name, i.current_stock FROM recipe_ingredients ri INNER JOIN ingredients i ON i.id = ri.ingredient_id WHERE ri.menu_item_id = ? FOR UPDATE");
            $stmt->execute([(int)$item['id']]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $recipe) {
                $required = (float)$recipe['quantity_per_item'] * (int)$item['qty'];
                if ((float)$recipe['current_stock'] < $required) {
                    throw new RuntimeException($recipe['name'] . ' is out of stock for this order.');
                }
                $update = $this->conn->prepare("UPDATE ingredients SET current_stock = current_stock - ? WHERE id = ? AND current_stock >= ?");
                $update->execute([$required, (int)$recipe['ingredient_id'], $required]);
                if ($update->rowCount() !== 1) {
                    throw new RuntimeException($recipe['name'] . ' stock changed. Please try again.');
                }
                $log = $this->conn->prepare("INSERT INTO ingredient_inventory_logs (ingredient_id, order_id, action, quantity, previous_stock, new_stock) VALUES (?, ?, 'order_deduction', ?, ?, ?)");
                $log->execute([(int)$recipe['ingredient_id'], (int)$orderId, $required, (float)$recipe['current_stock'], (float)$recipe['current_stock'] - $required]);
            }
        }
    }
}
