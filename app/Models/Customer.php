<?php
class Customer {
    private $conn;
    private $table_name = 'customers';

    public function __construct($db) {
        $this->conn = $db;
    }

    private function hasCustomersTable() {
        try {
            $stmt = $this->conn->query("SHOW TABLES LIKE 'customers'");
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function findOrCreateByMobile($mobile, $name = 'Walk-in Client') {
        if (!$this->hasCustomersTable() || $mobile === '') {
            return null;
        }

        $query = "SELECT id FROM {$this->table_name} WHERE mobile = :mobile LIMIT 1";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':mobile', $mobile);
        $stmt->execute();
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return (int)$existing['id'];
        }

        $insert = "INSERT INTO {$this->table_name} (customer_name, mobile) VALUES (:name, :mobile)";
        $insStmt = $this->conn->prepare($insert);
        $insStmt->bindValue(':name', $name ?: 'Walk-in Client');
        $insStmt->bindValue(':mobile', $mobile);
        if ($insStmt->execute()) {
            return (int)$this->conn->lastInsertId();
        }

        return null;
    }

    public function updateLedgerStats($customerId, $netTotal, $saleDate = null) {
        if (!$this->hasCustomersTable() || empty($customerId)) {
            return;
        }

        $query = "UPDATE {$this->table_name}
                  SET total_orders = total_orders + 1,
                      total_spent = total_spent + :net_total,
                      last_visit = COALESCE(:sale_date, NOW())
                  WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':net_total', (float)$netTotal);
        $stmt->bindValue(':sale_date', $saleDate);
        $stmt->bindValue(':id', (int)$customerId, PDO::PARAM_INT);
        $stmt->execute();
    }

    public function getSummary($search = '') {
        if (!$this->hasCustomersTable()) {
            return [];
        }

        $where = '';
        $params = [];
        if ($search !== '') {
            $where = ' WHERE customer_name LIKE :search OR mobile LIKE :search ';
            $params[':search'] = '%' . $search . '%';
        }

        $query = "SELECT id, customer_name, mobile, total_orders, total_spent, last_visit, created_at
                  FROM {$this->table_name}
                  {$where}
                  ORDER BY COALESCE(last_visit, created_at) DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPurchaseHistory($customerId, $limit = 50) {
        if (empty($customerId)) {
            return [];
        }

        try {
            $query = "SELECT s.invoice_number, s.sale_date, m.name as medicine_name, s.quantity,
                             COALESCE(s.net_total, s.total_price) as line_total,
                             s.payment_method, s.customer_phone
                      FROM sales s
                      JOIN medicines m ON m.id = s.medicine_id
                      WHERE s.customer_id = :customer_id
                      ORDER BY s.sale_date DESC
                      LIMIT :limit";
            $stmt = $this->conn->prepare($query);
            $stmt->bindValue(':customer_id', (int)$customerId, PDO::PARAM_INT);
            $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Exception $e) {
            return [];
        }
    }
}
