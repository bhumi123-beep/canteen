<?php
class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    public $conn;

    public function __construct() {
        $this->host = getenv('CANTEEN_DB_HOST') ?: 'localhost';
        $this->db_name = getenv('CANTEEN_DB_NAME') ?: 'canteen_db';
        $this->username = getenv('CANTEEN_DB_USER') ?: 'root';
        $this->password = getenv('CANTEEN_DB_PASSWORD') ?: '';
    }

    public function connect() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        } catch (PDOException $e) {
            error_log('Canteen database connection failed: ' . $e->getMessage());
            die('The application is temporarily unavailable. Please try again later.');
        }
        return $this->conn;
    }
}
