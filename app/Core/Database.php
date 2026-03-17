<?php
class Database {
    public $conn;

    public function getConnection() {
        $this->conn = null;

        $host = "pharmaflow-db.co30ame6iy1l.us-east-1.rds.amazonaws.com";
        $user = "admin";
        $password = "PharmaFlow123!";
        $db = "medical_management";

        try {
            $this->conn = new PDO(
                "mysql:host=" . $host . ";dbname=" . $db,
                $user,
                $password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $e) {
            // In a real app, log this error instead of echoing it to avoid leaking info.
            error_log("Connection Error: " . $e->getMessage());
            die("Database connection failed: " . $e->getMessage());
        }

        return $this->conn;
    }
}

