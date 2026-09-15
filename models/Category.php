<?php
class Category {
    private $conn;
    private $table = 'categories';

    public function __construct($db) {
        $this->conn = $db;
    }

    public function all() {
        $stmt = $this->conn->prepare("SELECT * FROM categories ORDER BY name");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function create($name) {
        $stmt = $this->conn->prepare("INSERT INTO {$this->table} (name) VALUES (?)");
        $stmt->execute([$name]);
        return $this->conn->lastInsertId();
    }

    public function exists($id) {
        $stmt = $this->conn->prepare("SELECT id FROM {$this->table} WHERE id = ? LIMIT 1");
        $stmt->execute([(int)$id]);
        return (bool)$stmt->fetchColumn();
    }
}
