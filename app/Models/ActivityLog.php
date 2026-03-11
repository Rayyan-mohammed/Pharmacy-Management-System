<?php
class ActivityLog {
    private $conn;
    private $table_name = "activity_logs";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function log($action, $description, $entityType = null, $entityId = null) {
        $userId = $_SESSION['currentUser']['user_id'] ?? 0;
        $userName = ($_SESSION['currentUser']['first_name'] ?? '') . ' ' . ($_SESSION['currentUser']['last_name'] ?? '');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $query = "INSERT INTO " . $this->table_name . " 
                  (user_id, user_name, action, description, entity_type, entity_id, ip_address)
                  VALUES (:user_id, :user_name, :action, :description, :entity_type, :entity_id, :ip)";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindParam(':user_name', $userName);
        $stmt->bindParam(':action', $action);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':entity_type', $entityType);
        $stmt->bindParam(':entity_id', $entityId);
        $stmt->bindParam(':ip', $ip);

        return $stmt->execute();
    }

    public function read($limit = 100, $filters = []) {
        $conditions = [];
        $params = [];

        if (!empty($filters['action'])) {
            $conditions[] = "action = :action";
            $params[':action'] = $filters['action'];
        }
        if (!empty($filters['user_id'])) {
            $conditions[] = "user_id = :user_id";
            $params[':user_id'] = $filters['user_id'];
        }
        if (!empty($filters['date_from'])) {
            $conditions[] = "DATE(created_at) >= :date_from";
            $params[':date_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $conditions[] = "DATE(created_at) <= :date_to";
            $params[':date_to'] = $filters['date_to'];
        }

        $where = count($conditions) ? ' WHERE ' . implode(' AND ', $conditions) : '';
        $query = "SELECT * FROM " . $this->table_name . $where . " ORDER BY created_at DESC LIMIT :limit";

        $stmt = $this->conn->prepare($query);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getDistinctActions() {
        $query = "SELECT DISTINCT action FROM " . $this->table_name . " ORDER BY action ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
