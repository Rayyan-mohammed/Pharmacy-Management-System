<?php
class Purchase {
    private $conn;

    public function __construct($db) {
        $this->conn = $db;
    }

    private function hasTable($tableName) {
        try {
            $stmt = $this->conn->prepare("SHOW TABLES LIKE :table_name");
            $stmt->bindValue(':table_name', $tableName);
            $stmt->execute();
            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            return false;
        }
    }

    public function createPurchase($header, $items, $userId) {
        if (!$this->hasTable('purchases') || !$this->hasTable('purchase_items')) {
            throw new Exception('Purchase tables not found. Run migration first.');
        }

        if (empty($items)) {
            throw new Exception('At least one item is required.');
        }

        $inventory = new Inventory($this->conn);

        $this->conn->beginTransaction();
        try {
            $subtotal = 0;
            foreach ($items as $item) {
                $subtotal += (float)$item['line_total'];
            }

            $discountAmount = (float)($header['discount_amount'] ?? 0);
            $taxAmount = (float)($header['tax_amount'] ?? 0);
            $totalAmount = max(0, $subtotal - $discountAmount + $taxAmount);
            $amountPaid = (float)($header['amount_paid'] ?? 0);
            if ($amountPaid < 0) {
                $amountPaid = 0;
            }
            $dueAmount = max(0, $totalAmount - $amountPaid);
            $status = $dueAmount <= 0 ? 'Paid' : ($amountPaid > 0 ? 'Partial' : 'Pending');

            $purchaseInsert = "INSERT INTO purchases
                (supplier_id, invoice_number, purchase_date, subtotal, tax_amount, discount_amount, total_amount, amount_paid, due_amount, payment_status, notes, created_by)
                VALUES
                (:supplier_id, :invoice_number, :purchase_date, :subtotal, :tax_amount, :discount_amount, :total_amount, :amount_paid, :due_amount, :payment_status, :notes, :created_by)";

            $stmt = $this->conn->prepare($purchaseInsert);
            $stmt->bindValue(':supplier_id', (int)$header['supplier_id'], PDO::PARAM_INT);
            $stmt->bindValue(':invoice_number', $header['invoice_number']);
            $stmt->bindValue(':purchase_date', $header['purchase_date']);
            $stmt->bindValue(':subtotal', $subtotal);
            $stmt->bindValue(':tax_amount', $taxAmount);
            $stmt->bindValue(':discount_amount', $discountAmount);
            $stmt->bindValue(':total_amount', $totalAmount);
            $stmt->bindValue(':amount_paid', $amountPaid);
            $stmt->bindValue(':due_amount', $dueAmount);
            $stmt->bindValue(':payment_status', $status);
            $stmt->bindValue(':notes', $header['notes'] ?? null);
            $stmt->bindValue(':created_by', (int)$userId, PDO::PARAM_INT);
            $stmt->execute();

            $purchaseId = (int)$this->conn->lastInsertId();

            $itemInsert = "INSERT INTO purchase_items
                (purchase_id, medicine_id, batch_number, expiration_date, quantity, cost_price, sale_price, line_total)
                VALUES
                (:purchase_id, :medicine_id, :batch_number, :expiration_date, :quantity, :cost_price, :sale_price, :line_total)";
            $itemStmt = $this->conn->prepare($itemInsert);

            $medicineUpdate = $this->conn->prepare("UPDATE medicines SET inventory_price = :cost_price, sale_price = :sale_price WHERE id = :id");

            foreach ($items as $item) {
                $medicineId = (int)$item['medicine_id'];
                $quantity = (int)$item['quantity'];
                $batch = $item['batch_number'];
                $exp = $item['expiration_date'];
                $cost = (float)$item['cost_price'];
                $sale = (float)$item['sale_price'];
                $lineTotal = (float)$item['line_total'];

                if ($medicineId <= 0 || $quantity <= 0 || $cost <= 0 || $sale <= 0 || empty($batch) || empty($exp)) {
                    throw new Exception('Invalid item data in purchase rows.');
                }

                $itemStmt->bindValue(':purchase_id', $purchaseId, PDO::PARAM_INT);
                $itemStmt->bindValue(':medicine_id', $medicineId, PDO::PARAM_INT);
                $itemStmt->bindValue(':batch_number', $batch);
                $itemStmt->bindValue(':expiration_date', $exp);
                $itemStmt->bindValue(':quantity', $quantity, PDO::PARAM_INT);
                $itemStmt->bindValue(':cost_price', $cost);
                $itemStmt->bindValue(':sale_price', $sale);
                $itemStmt->bindValue(':line_total', $lineTotal);
                $itemStmt->execute();

                $medicineUpdate->bindValue(':cost_price', $cost);
                $medicineUpdate->bindValue(':sale_price', $sale);
                $medicineUpdate->bindValue(':id', $medicineId, PDO::PARAM_INT);
                $medicineUpdate->execute();

                $reason = 'Purchase GRN #' . $purchaseId . ' Invoice: ' . $header['invoice_number'];
                if (!$inventory->adjustStock($medicineId, $quantity, 'in', $reason, $batch, $exp)) {
                    throw new Exception('Failed stock posting for medicine ID ' . $medicineId);
                }
            }

            $this->conn->commit();
            return $purchaseId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getRecentPurchases($limit = 20) {
        if (!$this->hasTable('purchases')) {
            return [];
        }
        $query = "SELECT p.*, s.name as supplier_name
                  FROM purchases p
                  JOIN suppliers s ON s.id = p.supplier_id
                  ORDER BY p.created_at DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupplierDueSummary() {
        if (!$this->hasTable('purchases')) {
            return [];
        }
        $query = "SELECT s.id as supplier_id, s.name as supplier_name,
                         COUNT(p.id) as total_bills,
                         SUM(p.total_amount) as total_purchased,
                         SUM(p.amount_paid) as total_paid,
                         SUM(p.due_amount) as total_due
                  FROM purchases p
                  JOIN suppliers s ON s.id = p.supplier_id
                  GROUP BY s.id, s.name
                  HAVING total_due > 0
                  ORDER BY total_due DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPurchaseHistory($filters = [], $page = 1, $perPage = 25) {
        if (!$this->hasTable('purchases')) {
            return ['rows' => [], 'total' => 0];
        }

        $conditions = [];
        $params = [];

        if (!empty($filters['supplier_id'])) {
            $conditions[] = 'p.supplier_id = :supplier_id';
            $params[':supplier_id'] = (int)$filters['supplier_id'];
        }
        if (!empty($filters['start_date'])) {
            $conditions[] = 'p.purchase_date >= :start_date';
            $params[':start_date'] = $filters['start_date'];
        }
        if (!empty($filters['end_date'])) {
            $conditions[] = 'p.purchase_date <= :end_date';
            $params[':end_date'] = $filters['end_date'];
        }
        if (!empty($filters['payment_status'])) {
            $conditions[] = 'p.payment_status = :payment_status';
            $params[':payment_status'] = $filters['payment_status'];
        }

        $where = count($conditions) ? (' WHERE ' . implode(' AND ', $conditions)) : '';

        $countStmt = $this->conn->prepare("SELECT COUNT(*) FROM purchases p" . $where);
        foreach ($params as $k => $v) {
            $countStmt->bindValue($k, $v);
        }
        $countStmt->execute();
        $total = (int)$countStmt->fetchColumn();

        $page = max(1, (int)$page);
        $perPage = max(1, (int)$perPage);
        $offset = ($page - 1) * $perPage;

        $query = "SELECT p.*, s.name as supplier_name,
                         DATEDIFF(CURDATE(), p.purchase_date) as bill_age_days
                  FROM purchases p
                  JOIN suppliers s ON s.id = p.supplier_id" . $where . "
                  ORDER BY p.purchase_date DESC, p.id DESC
                  LIMIT :limit OFFSET :offset";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();

        return [
            'rows' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            'total' => $total
        ];
    }

    public function getPurchaseItems($purchaseId) {
        if (!$this->hasTable('purchase_items')) {
            return [];
        }

        $query = "SELECT pi.*, m.name as medicine_name
                  FROM purchase_items pi
                  JOIN medicines m ON m.id = pi.medicine_id
                  WHERE pi.purchase_id = :purchase_id
                  ORDER BY pi.id ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':purchase_id', (int)$purchaseId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getSupplierPayableAging() {
        if (!$this->hasTable('purchases')) {
            return [];
        }

        $query = "SELECT s.id as supplier_id,
                         s.name as supplier_name,
                         COUNT(CASE WHEN p.due_amount > 0 THEN 1 END) as due_bills,
                         SUM(CASE WHEN p.due_amount > 0 THEN p.due_amount ELSE 0 END) as total_due,
                         SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) BETWEEN 0 AND 30 THEN p.due_amount ELSE 0 END) as bucket_0_30,
                         SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) BETWEEN 31 AND 60 THEN p.due_amount ELSE 0 END) as bucket_31_60,
                         SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) BETWEEN 61 AND 90 THEN p.due_amount ELSE 0 END) as bucket_61_90,
                         SUM(CASE WHEN p.due_amount > 0 AND DATEDIFF(CURDATE(), p.purchase_date) > 90 THEN p.due_amount ELSE 0 END) as bucket_90_plus
                  FROM suppliers s
                  LEFT JOIN purchases p ON p.supplier_id = s.id
                  GROUP BY s.id, s.name
                  HAVING total_due > 0
                  ORDER BY total_due DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getOutstandingBills($supplierId = 0, $limit = 200) {
        if (!$this->hasTable('purchases')) {
            return [];
        }

        $where = ' WHERE p.due_amount > 0 ';
        $params = [];
        if ((int)$supplierId > 0) {
            $where .= ' AND p.supplier_id = :supplier_id ';
            $params[':supplier_id'] = (int)$supplierId;
        }

        $query = "SELECT p.id, p.supplier_id, p.invoice_number, p.purchase_date, p.total_amount, p.amount_paid, p.due_amount, p.payment_status,
                         s.name as supplier_name,
                         DATEDIFF(CURDATE(), p.purchase_date) as bill_age_days
                  FROM purchases p
                  JOIN suppliers s ON s.id = p.supplier_id
                  {$where}
                  ORDER BY bill_age_days DESC, p.id DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function addSettlement($purchaseId, $paymentDate, $amount, $paymentMethod, $referenceNo, $notes, $userId) {
        if (!$this->hasTable('purchase_payments')) {
            throw new Exception('purchase_payments table not found. Run migration first.');
        }

        $this->conn->beginTransaction();
        try {
            $stmt = $this->conn->prepare("SELECT total_amount, amount_paid, due_amount FROM purchases WHERE id = :id LIMIT 1");
            $stmt->bindValue(':id', (int)$purchaseId, PDO::PARAM_INT);
            $stmt->execute();
            $purchase = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$purchase) {
                throw new Exception('Purchase bill not found.');
            }

            $due = (float)$purchase['due_amount'];
            if ($amount <= 0 || $amount > $due) {
                throw new Exception('Settlement amount must be greater than 0 and not exceed due amount.');
            }

            $ins = $this->conn->prepare("INSERT INTO purchase_payments
                (purchase_id, payment_date, amount, payment_method, reference_no, notes, created_by)
                VALUES
                (:purchase_id, :payment_date, :amount, :payment_method, :reference_no, :notes, :created_by)");
            $ins->bindValue(':purchase_id', (int)$purchaseId, PDO::PARAM_INT);
            $ins->bindValue(':payment_date', $paymentDate ?: date('Y-m-d'));
            $ins->bindValue(':amount', (float)$amount);
            $ins->bindValue(':payment_method', $paymentMethod ?: 'Cash');
            $ins->bindValue(':reference_no', $referenceNo ?: null);
            $ins->bindValue(':notes', $notes ?: null);
            $ins->bindValue(':created_by', (int)$userId, PDO::PARAM_INT);
            $ins->execute();

            $newPaid = (float)$purchase['amount_paid'] + (float)$amount;
            $newDue = max(0, (float)$purchase['total_amount'] - $newPaid);
            $newStatus = $newDue <= 0 ? 'Paid' : 'Partial';

            $upd = $this->conn->prepare("UPDATE purchases SET amount_paid = :amount_paid, due_amount = :due_amount, payment_status = :payment_status WHERE id = :id");
            $upd->bindValue(':amount_paid', $newPaid);
            $upd->bindValue(':due_amount', $newDue);
            $upd->bindValue(':payment_status', $newStatus);
            $upd->bindValue(':id', (int)$purchaseId, PDO::PARAM_INT);
            $upd->execute();

            $this->conn->commit();
            return true;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getSettlementHistory($purchaseId = 0, $limit = 100) {
        if (!$this->hasTable('purchase_payments')) {
            return [];
        }

        $where = '';
        $params = [];
        if ((int)$purchaseId > 0) {
            $where = ' WHERE pp.purchase_id = :purchase_id ';
            $params[':purchase_id'] = (int)$purchaseId;
        }

        $query = "SELECT pp.*, p.invoice_number, s.name as supplier_name
                  FROM purchase_payments pp
                  JOIN purchases p ON p.id = pp.purchase_id
                  JOIN suppliers s ON s.id = p.supplier_id
                  {$where}
                  ORDER BY pp.payment_date DESC, pp.id DESC
                  LIMIT :limit";
        $stmt = $this->conn->prepare($query);
        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v, PDO::PARAM_INT);
        }
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPurchaseReturn($header, $items, $userId) {
        if (!$this->hasTable('purchase_returns') || !$this->hasTable('purchase_return_items')) {
            throw new Exception('Purchase return tables not found. Run migration first.');
        }
        if (empty($items)) {
            throw new Exception('At least one return row is required.');
        }

        $inventory = new Inventory($this->conn);
        $this->conn->beginTransaction();
        try {
            $total = 0;
            foreach ($items as $it) {
                $total += (float)$it['line_total'];
            }

            $returnNo = 'PRN-' . date('Ymd') . '-' . str_pad((string)rand(1, 9999), 4, '0', STR_PAD_LEFT);
            $ins = $this->conn->prepare("INSERT INTO purchase_returns
                (purchase_id, supplier_id, return_number, return_date, reason, total_amount, credit_applied, status, created_by)
                VALUES
                (:purchase_id, :supplier_id, :return_number, :return_date, :reason, :total_amount, :credit_applied, 'Processed', :created_by)");
            $ins->bindValue(':purchase_id', (int)$header['purchase_id'], PDO::PARAM_INT);
            $ins->bindValue(':supplier_id', (int)$header['supplier_id'], PDO::PARAM_INT);
            $ins->bindValue(':return_number', $returnNo);
            $ins->bindValue(':return_date', $header['return_date'] ?: date('Y-m-d'));
            $ins->bindValue(':reason', $header['reason'] ?: null);
            $ins->bindValue(':total_amount', $total);
            $ins->bindValue(':credit_applied', $total);
            $ins->bindValue(':created_by', (int)$userId, PDO::PARAM_INT);
            $ins->execute();

            $returnId = (int)$this->conn->lastInsertId();

            $itemIns = $this->conn->prepare("INSERT INTO purchase_return_items
                (purchase_return_id, medicine_id, batch_number, quantity, unit_cost, line_total)
                VALUES
                (:purchase_return_id, :medicine_id, :batch_number, :quantity, :unit_cost, :line_total)");

            foreach ($items as $it) {
                $itemIns->bindValue(':purchase_return_id', $returnId, PDO::PARAM_INT);
                $itemIns->bindValue(':medicine_id', (int)$it['medicine_id'], PDO::PARAM_INT);
                $itemIns->bindValue(':batch_number', $it['batch_number']);
                $itemIns->bindValue(':quantity', (int)$it['quantity'], PDO::PARAM_INT);
                $itemIns->bindValue(':unit_cost', (float)$it['unit_cost']);
                $itemIns->bindValue(':line_total', (float)$it['line_total']);
                $itemIns->execute();

                if (!$inventory->adjustStock((int)$it['medicine_id'], (int)$it['quantity'], 'out', 'Supplier return #' . $returnNo, $it['batch_number'])) {
                    throw new Exception('Failed stock reduction for return item medicine ID ' . (int)$it['medicine_id']);
                }
            }

            // Apply return credit against bill due immediately
            $qPurchase = $this->conn->prepare("SELECT total_amount, amount_paid FROM purchases WHERE id = :id LIMIT 1");
            $qPurchase->bindValue(':id', (int)$header['purchase_id'], PDO::PARAM_INT);
            $qPurchase->execute();
            $row = $qPurchase->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $newTotal = max(0, (float)$row['total_amount'] - $total);
                $paid = (float)$row['amount_paid'];
                $newDue = max(0, $newTotal - $paid);
                $status = $newDue <= 0 ? 'Paid' : ($paid > 0 ? 'Partial' : 'Pending');

                $upBill = $this->conn->prepare("UPDATE purchases SET total_amount = :total_amount, due_amount = :due_amount, payment_status = :status WHERE id = :id");
                $upBill->bindValue(':total_amount', $newTotal);
                $upBill->bindValue(':due_amount', $newDue);
                $upBill->bindValue(':status', $status);
                $upBill->bindValue(':id', (int)$header['purchase_id'], PDO::PARAM_INT);
                $upBill->execute();
            }

            $this->conn->commit();
            return $returnId;
        } catch (Exception $e) {
            if ($this->conn->inTransaction()) {
                $this->conn->rollBack();
            }
            throw $e;
        }
    }

    public function getRecentPurchaseReturns($limit = 50) {
        if (!$this->hasTable('purchase_returns')) {
            return [];
        }

        $q = "SELECT pr.*, s.name as supplier_name, p.invoice_number
              FROM purchase_returns pr
              JOIN suppliers s ON s.id = pr.supplier_id
              JOIN purchases p ON p.id = pr.purchase_id
              ORDER BY pr.created_at DESC
              LIMIT :limit";
        $stmt = $this->conn->prepare($q);
        $stmt->bindValue(':limit', (int)$limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
