<?php
class Returns {
    private $conn;
    private $table_name = "returns";

    public function __construct($db) {
        $this->conn = $db;
    }

    public function create($saleId, $medicineId, $quantity, $reason, $refundAmount) {
        $query = "INSERT INTO " . $this->table_name . " 
                  (sale_id, medicine_id, quantity, reason, refund_amount, status)
                  VALUES (:sale_id, :medicine_id, :quantity, :reason, :refund_amount, 'pending')";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':sale_id', $saleId, PDO::PARAM_INT);
        $stmt->bindParam(':medicine_id', $medicineId, PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $stmt->bindParam(':reason', $reason);
        $stmt->bindParam(':refund_amount', $refundAmount);
        return $stmt->execute();
    }

    public function approve($returnId, $processedBy) {
        $this->conn->beginTransaction();
        try {
            // Get return details
            $ret = $this->readOne($returnId);
            if (!$ret || $ret['status'] !== 'pending') {
                $this->conn->rollBack();
                return false;
            }

            // Update return status
            $query = "UPDATE " . $this->table_name . " 
                      SET status = 'approved', processed_by = :processed_by, processed_at = NOW() 
                      WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(':processed_by', $processedBy, PDO::PARAM_INT);
            $stmt->bindParam(':id', $returnId, PDO::PARAM_INT);
            $stmt->execute();

            // Restock: add quantity back to medicines.stock
            $stockQuery = "UPDATE medicines SET stock = stock + :qty WHERE id = :mid";
            $stockStmt = $this->conn->prepare($stockQuery);
            $stockStmt->bindParam(':qty', $ret['quantity'], PDO::PARAM_INT);
            $stockStmt->bindParam(':mid', $ret['medicine_id'], PDO::PARAM_INT);
            $stockStmt->execute();

            // Create inventory log for the return
            $logQuery = "INSERT INTO inventory_logs (medicine_id, type, quantity, reason) 
                         VALUES (:mid, 'in', :qty, :reason)";
            $logStmt = $this->conn->prepare($logQuery);
            $logStmt->bindParam(':mid', $ret['medicine_id'], PDO::PARAM_INT);
            $logStmt->bindParam(':qty', $ret['quantity'], PDO::PARAM_INT);
            $reason = 'Return approved - Refund #' . $returnId . ': ' . $ret['reason'];
            $logStmt->bindParam(':reason', $reason);
            $logStmt->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            $this->conn->rollBack();
            error_log("Return approval error: " . $e->getMessage());
            return false;
        }
    }

    public function reject($returnId, $processedBy) {
        $query = "UPDATE " . $this->table_name . " 
                  SET status = 'rejected', processed_by = :processed_by, processed_at = NOW() 
                  WHERE id = :id AND status = 'pending'";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':processed_by', $processedBy, PDO::PARAM_INT);
        $stmt->bindParam(':id', $returnId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function readOne($id) {
        $query = "SELECT r.*, m.name as medicine_name, s.customer_name, s.sale_date, s.unit_price
                  FROM " . $this->table_name . " r
                  JOIN medicines m ON r.medicine_id = m.id
                  JOIN sales s ON r.sale_id = s.id
                  WHERE r.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function readAll($status = null) {
        $where = '';
        $params = [];
        if ($status) {
            $where = ' WHERE r.status = :status';
            $params[':status'] = $status;
        }
        $query = "SELECT r.*, m.name as medicine_name, s.customer_name, s.sale_date,
                  u.first_name as processor_first, u.last_name as processor_last
                  FROM " . $this->table_name . " r
                  JOIN medicines m ON r.medicine_id = m.id
                  JOIN sales s ON r.sale_id = s.id
                  LEFT JOIN users u ON r.processed_by = u.user_id"
                  . $where . " ORDER BY r.created_at DESC";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) $stmt->bindValue($k, $v);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSaleDetails($saleId) {
        $query = "SELECT s.*, m.name as medicine_name, m.sale_price, m.id as medicine_id
                  FROM sales s
                  JOIN medicines m ON s.medicine_id = m.id
                  WHERE s.id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getReturnedQtyForSale($saleId) {
        $query = "SELECT COALESCE(SUM(quantity), 0) as total 
                  FROM " . $this->table_name . " 
                  WHERE sale_id = :id AND status IN ('pending', 'approved')";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(':id', $saleId, PDO::PARAM_INT);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)$row['total'];
    }

    public function getStats() {
        $query = "SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved,
                    SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected,
                    COALESCE(SUM(CASE WHEN status = 'approved' THEN refund_amount ELSE 0 END), 0) as total_refunded
                  FROM " . $this->table_name;
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
