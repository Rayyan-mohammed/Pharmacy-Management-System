<?php
class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
                DB_USER,
                DB_PASS
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            // In a real app, log this error instead of echoing it to avoid leaking info.
            error_log("Connection Error: " . $e->getMessage());
            die("Database connection failed. Please check logs.");
        }

        return $this->conn;
    }
}

