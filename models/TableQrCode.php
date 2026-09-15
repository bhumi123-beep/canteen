<?php
class TableQrCode {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
        $this->conn->exec("CREATE TABLE IF NOT EXISTS table_qr_codes (
            id INT AUTO_INCREMENT PRIMARY KEY,
            table_id INT NOT NULL UNIQUE,
            token CHAR(32) NOT NULL UNIQUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (table_id) REFERENCES tables_(id) ON DELETE CASCADE
        )");
        $tables = $this->conn->prepare("SELECT id FROM tables_ WHERE is_active = 1");
        $tables->execute();
        $insert = $this->conn->prepare("INSERT IGNORE INTO table_qr_codes (table_id, token) VALUES (?, ?)");
        foreach ($tables->fetchAll(PDO::FETCH_COLUMN) as $tableId) {
            $insert->execute([(int)$tableId, bin2hex(random_bytes(16))]);
        }
    }

    public function all() {
        $stmt = $this->conn->prepare("SELECT t.id, t.table_number, t.location, q.token FROM tables_ t INNER JOIN table_qr_codes q ON q.table_id = t.id WHERE t.is_active = 1 ORDER BY t.table_number");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function resolve($tableNumber, $token) {
        $stmt = $this->conn->prepare("SELECT t.table_number FROM tables_ t INNER JOIN table_qr_codes q ON q.table_id = t.id WHERE t.table_number = ? AND q.token = ? AND t.is_active = 1 LIMIT 1");
        $stmt->execute([trim($tableNumber), trim($token)]);
        return $stmt->fetchColumn() ?: false;
    }
}
