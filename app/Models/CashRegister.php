<?php
class CashRegister {
    private $conn;
    private $salesColumnCache = [];
    private $returnsColumnCache = [];

    public function __construct($db) {
        $this->conn = $db;
    }

    private function hasSalesColumn($column) {
        if (!array_key_exists($column, $this->salesColumnCache)) {
            try {
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sales' AND COLUMN_NAME = :column");
                $stmt->bindValue(':column', $column);
                $stmt->execute();
                $this->salesColumnCache[$column] = ((int)$stmt->fetchColumn()) > 0;
            } catch (Exception $e) {
                $this->salesColumnCache[$column] = false;
            }
        }
        return $this->salesColumnCache[$column];
    }

    private function hasReturnsColumn($column) {
        if (!array_key_exists($column, $this->returnsColumnCache)) {
            try {
                $stmt = $this->conn->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'returns' AND COLUMN_NAME = :column");
                $stmt->bindValue(':column', $column);
                $stmt->execute();
                $this->returnsColumnCache[$column] = ((int)$stmt->fetchColumn()) > 0;
            } catch (Exception $e) {
                $this->returnsColumnCache[$column] = false;
            }
        }
        return $this->returnsColumnCache[$column];
    }

    public function upsertOpeningCash($businessDate, $openingCash, $userId) {
        $query = "INSERT INTO cash_register_sessions (business_date, opening_cash, created_by)
                  VALUES (:business_date, :opening_cash, :user_id)
                  ON DUPLICATE KEY UPDATE opening_cash = VALUES(opening_cash), updated_at = NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':business_date', $businessDate);
        $stmt->bindValue(':opening_cash', (float)$openingCash);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function addMovement($businessDate, $type, $amount, $reason, $userId) {
        $query = "INSERT INTO cash_movements (business_date, movement_type, amount, reason, created_by)
                  VALUES (:business_date, :movement_type, :amount, :reason, :user_id)";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':business_date', $businessDate);
        $stmt->bindValue(':movement_type', $type);
        $stmt->bindValue(':amount', (float)$amount);
        $stmt->bindValue(':reason', $reason);
        $stmt->bindValue(':user_id', (int)$userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function closeDay($businessDate, $actualClosing, $notes, $userId) {
        $summary = $this->buildSummary($businessDate);
        $query = "INSERT INTO cash_register_sessions
                    (business_date, opening_cash, cash_sales, cash_refunds, cash_in, cash_out, expected_closing, actual_closing, variance, notes, closed_by)
                  VALUES
                    (:business_date, :opening_cash, :cash_sales, :cash_refunds, :cash_in, :cash_out, :expected_closing, :actual_closing, :variance, :notes, :closed_by)
                  ON DUPLICATE KEY UPDATE
                    cash_sales = VALUES(cash_sales),
                    cash_refunds = VALUES(cash_refunds),
                    cash_in = VALUES(cash_in),
                    cash_out = VALUES(cash_out),
                    expected_closing = VALUES(expected_closing),
                    actual_closing = VALUES(actual_closing),
                    variance = VALUES(variance),
                    notes = VALUES(notes),
                    closed_by = VALUES(closed_by),
                    updated_at = NOW()";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':business_date', $businessDate);
        $stmt->bindValue(':opening_cash', $summary['opening_cash']);
        $stmt->bindValue(':cash_sales', $summary['cash_sales']);
        $stmt->bindValue(':cash_refunds', $summary['cash_refunds']);
        $stmt->bindValue(':cash_in', $summary['cash_in']);
        $stmt->bindValue(':cash_out', $summary['cash_out']);
        $stmt->bindValue(':expected_closing', $summary['expected_closing']);
        $stmt->bindValue(':actual_closing', (float)$actualClosing);
        $stmt->bindValue(':variance', (float)$actualClosing - (float)$summary['expected_closing']);
        $stmt->bindValue(':notes', $notes ?: null);
        $stmt->bindValue(':closed_by', (int)$userId, PDO::PARAM_INT);
        return $stmt->execute();
    }

    public function buildSummary($businessDate) {
        $openQuery = "SELECT opening_cash FROM cash_register_sessions WHERE business_date = :business_date LIMIT 1";
        $openStmt = $this->conn->prepare($openQuery);
        $openStmt->bindValue(':business_date', $businessDate);
        $openStmt->execute();
        $openingCash = (float)($openStmt->fetch(PDO::FETCH_ASSOC)['opening_cash'] ?? 0);

        $salesAmountExpr = $this->hasSalesColumn('net_total') ? 'COALESCE(net_total, total_price)' : 'total_price';
        $salesPaymentFilter = $this->hasSalesColumn('payment_method') ? " AND payment_method = 'Cash'" : '';
        $salesQuery = "SELECT COALESCE(SUM({$salesAmountExpr}), 0) as cash_sales
                   FROM sales
                   WHERE DATE(sale_date) = :business_date{$salesPaymentFilter}";
        $salesStmt = $this->conn->prepare($salesQuery);
        $salesStmt->bindValue(':business_date', $businessDate);
        $salesStmt->execute();
        $cashSales = (float)$salesStmt->fetch(PDO::FETCH_ASSOC)['cash_sales'];

                $refundDateExpr = $this->hasReturnsColumn('refunded_at')
                        ? 'COALESCE(refunded_at, processed_at, created_at)'
                        : ($this->hasReturnsColumn('processed_at') ? 'COALESCE(processed_at, created_at)' : 'created_at');
                $refundMethodExpr = $this->hasReturnsColumn('refund_method')
                        ? "COALESCE(refund_method, " . ($this->hasReturnsColumn('original_payment_method') ? 'original_payment_method' : "'Cash'") . ", 'Cash')"
                        : "'Cash'";
                $refundQuery = "SELECT COALESCE(SUM(refund_amount), 0) as cash_refunds
                                                FROM `returns`
                                                WHERE DATE({$refundDateExpr}) = :business_date
                                                    AND status = 'approved'
                                                    AND {$refundMethodExpr} = 'Cash'";
        $refundStmt = $this->conn->prepare($refundQuery);
        $refundStmt->bindValue(':business_date', $businessDate);
        $refundStmt->execute();
        $cashRefunds = (float)$refundStmt->fetch(PDO::FETCH_ASSOC)['cash_refunds'];

        $moveQuery = "SELECT
                        COALESCE(SUM(CASE WHEN movement_type = 'in' THEN amount ELSE 0 END), 0) as cash_in,
                        COALESCE(SUM(CASE WHEN movement_type = 'out' THEN amount ELSE 0 END), 0) as cash_out
                      FROM cash_movements
                      WHERE business_date = :business_date";
        $moveStmt = $this->conn->prepare($moveQuery);
        $moveStmt->bindValue(':business_date', $businessDate);
        $moveStmt->execute();
        $move = $moveStmt->fetch(PDO::FETCH_ASSOC);

        $cashIn = (float)($move['cash_in'] ?? 0);
        $cashOut = (float)($move['cash_out'] ?? 0);
        $expected = $openingCash + $cashSales + $cashIn - $cashOut - $cashRefunds;

        return [
            'opening_cash' => $openingCash,
            'cash_sales' => $cashSales,
            'cash_refunds' => $cashRefunds,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_closing' => $expected
        ];
    }

    public function getMovements($businessDate) {
        $query = "SELECT * FROM cash_movements WHERE business_date = :business_date ORDER BY created_at DESC";
        $stmt = $this->conn->prepare($query);
        $stmt->bindValue(':business_date', $businessDate);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
