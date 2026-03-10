<?php
require_once '../../app/auth.php';
require_once '../../app/Models/Sale.php';

// Allow any logged in user to view invoice
checkRole(['Administrator', 'Pharmacist', 'Staff']);

$database = new Database();
$db = $database->getConnection();
$saleModel = new Sale($db);

$sale_ids_str = $_GET['ids'] ?? ($_GET['id'] ?? 0);

if (!$sale_ids_str) {
    die("Invalid Sale ID(s)");
}

$sale_ids = array_map('intval', explode(',', $sale_ids_str));
if (empty($sale_ids)) die("No valid IDs");

// Fetch sales with medicine info and batch number (FIFO: earliest expiring batch)
$in  = str_repeat('?,', count($sale_ids) - 1) . '?';
$query = "SELECT s.*, m.name as medicine_name, m.description,
          (SELECT mb.batch_number FROM medicine_batches mb 
           WHERE mb.medicine_id = s.medicine_id 
           ORDER BY mb.expiration_date ASC LIMIT 1) as batch_number,
          (SELECT mb.expiration_date FROM medicine_batches mb 
           WHERE mb.medicine_id = s.medicine_id 
           ORDER BY mb.expiration_date ASC LIMIT 1) as batch_expiry
          FROM sales s
          JOIN medicines m ON s.medicine_id = m.id
          WHERE s.id IN ($in)";
$stmt = $db->prepare($query);
$stmt->execute($sale_ids);
$sales = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$sales) {
    die("Sales not found");
}

$first_sale = $sales[0];
$grand_total = 0;
$total_items = 0;
foreach ($sales as $s) {
    $grand_total += $s['total_price'];
    $total_items += $s['quantity'];
}

// Generate invoice number from sale IDs
$invoice_number = 'INV-' . str_pad($first_sale['id'], 6, '0', STR_PAD_LEFT);
$invoice_date = date('d M Y, h:i A', strtotime($first_sale['sale_date']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Invoice <?php echo $invoice_number; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <style>
        :root {
            --primary: #1a5276;
            --primary-light: #2980b9;
            --accent: #27ae60;
            --bg: #f0f2f5;
            --text-dark: #2c3e50;
            --text-muted: #7f8c8d;
            --border: #dee2e6;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { background-color: var(--bg); padding: 30px 15px; font-family: 'Segoe UI', system-ui, -apple-system, sans-serif; color: var(--text-dark); }

        .invoice-container {
            background: #fff;
            max-width: 820px;
            margin: 0 auto;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 24px rgba(0,0,0,0.08);
        }

        /* Header */
        .invoice-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            color: #fff;
            padding: 28px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .invoice-header .brand h2 { font-size: 1.6rem; font-weight: 700; letter-spacing: 0.5px; }
        .invoice-header .brand p { opacity: 0.85; font-size: 0.85rem; margin-top: 2px; }
        .invoice-header .invoice-meta { text-align: right; }
        .invoice-header .invoice-meta .inv-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            opacity: 0.75;
        }
        .invoice-header .invoice-meta .inv-number {
            font-size: 1.3rem;
            font-weight: 700;
            letter-spacing: 1px;
        }

        .invoice-body { padding: 32px 36px; }

        /* Info cards row */
        .info-row {
            display: flex;
            gap: 20px;
            margin-bottom: 28px;
        }
        .info-card {
            flex: 1;
            background: #f8f9fa;
            border-radius: 8px;
            padding: 16px 20px;
            border-left: 3px solid var(--primary-light);
        }
        .info-card .info-label {
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: var(--text-muted);
            margin-bottom: 4px;
            font-weight: 600;
        }
        .info-card .info-value {
            font-size: 0.95rem;
            font-weight: 600;
            color: var(--text-dark);
        }
        .info-card .info-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin-top: 2px;
        }

        /* Items table */
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        .items-table thead th {
            background: var(--primary);
            color: #fff;
            padding: 12px 16px;
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            font-weight: 600;
        }
        .items-table thead th:first-child { border-radius: 8px 0 0 0; }
        .items-table thead th:last-child { border-radius: 0 8px 0 0; }
        .items-table tbody td {
            padding: 14px 16px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 0.9rem;
        }
        .items-table tbody tr:hover { background: #fafbfc; }
        .items-table tbody tr:last-child td { border-bottom: none; }
        .item-name { font-weight: 600; color: var(--text-dark); }
        .item-batch {
            display: inline-block;
            margin-top: 4px;
            font-size: 0.75rem;
            color: var(--primary-light);
            background: #eaf2f8;
            padding: 2px 8px;
            border-radius: 4px;
            font-weight: 500;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        /* Totals */
        .totals-section {
            display: flex;
            justify-content: flex-end;
            margin-bottom: 28px;
        }
        .totals-box {
            width: 280px;
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid var(--border);
        }
        .totals-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 18px;
            font-size: 0.9rem;
        }
        .totals-row.sub { background: #f8f9fa; color: var(--text-muted); }
        .totals-row.grand {
            background: var(--primary);
            color: #fff;
            font-weight: 700;
            font-size: 1.05rem;
            padding: 14px 18px;
        }

        /* Footer */
        .invoice-footer {
            border-top: 1px solid #eee;
            padding: 24px 36px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .footer-note {
            font-size: 0.82rem;
            color: var(--text-muted);
            line-height: 1.6;
        }
        .footer-note i { color: var(--accent); }

        /* Action buttons */
        .actions { padding: 0 36px 28px; display: flex; gap: 10px; justify-content: center; }
        .btn-invoice {
            padding: 10px 28px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.9rem;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s ease;
        }
        .btn-invoice:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.15); }
        .btn-print { background: var(--primary); color: #fff; }
        .btn-close-inv { background: #e9ecef; color: var(--text-dark); }

        /* Watermark */
        .watermark {
            text-align: center;
            padding: 12px;
            font-size: 0.7rem;
            color: #bdc3c7;
            letter-spacing: 1px;
        }

        @media print {
            body { background: white; padding: 0; }
            .invoice-container { box-shadow: none; border-radius: 0; }
            .no-print { display: none !important; }
            .invoice-header { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .items-table thead th { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .totals-row.grand { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }

        @media (max-width: 600px) {
            .invoice-header { flex-direction: column; text-align: center; gap: 12px; }
            .invoice-header .invoice-meta { text-align: center; }
            .info-row { flex-direction: column; }
            .invoice-body { padding: 20px; }
            .invoice-footer { flex-direction: column; gap: 12px; text-align: center; }
        }
    </style>
</head>
<body>

<div class="invoice-container">
    <!-- Header -->
    <div class="invoice-header">
        <div class="brand">
            <h2><i class="bi bi-capsule"></i> Pharmacy Pro</h2>
            <p>123 Medical Plaza, Health City | (555) 123-4567</p>
        </div>
        <div class="invoice-meta">
            <div class="inv-label">Invoice</div>
            <div class="inv-number"><?php echo $invoice_number; ?></div>
            <div style="font-size:0.82rem; opacity:0.85; margin-top:2px;"><?php echo $invoice_date; ?></div>
        </div>
    </div>

    <div class="invoice-body">
        <!-- Info Cards -->
        <div class="info-row">
            <div class="info-card">
                <div class="info-label"><i class="bi bi-person"></i> Billed To</div>
                <div class="info-value"><?php echo htmlspecialchars($first_sale['customer_name']); ?></div>
            </div>
            <div class="info-card">
                <div class="info-label"><i class="bi bi-calendar3"></i> Date</div>
                <div class="info-value"><?php echo date('d M Y', strtotime($first_sale['sale_date'])); ?></div>
                <div class="info-sub"><?php echo date('h:i A', strtotime($first_sale['sale_date'])); ?></div>
            </div>
            <div class="info-card">
                <div class="info-label"><i class="bi bi-receipt"></i> Items</div>
                <div class="info-value"><?php echo count($sales); ?> item<?php echo count($sales) > 1 ? 's' : ''; ?></div>
                <div class="info-sub"><?php echo $total_items; ?> unit<?php echo $total_items > 1 ? 's' : ''; ?> total</div>
            </div>
        </div>

        <!-- Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width:5%">#</th>
                    <th style="width:40%">Medicine</th>
                    <th class="text-center" style="width:12%">Qty</th>
                    <th class="text-right" style="width:18%">Unit Price</th>
                    <th class="text-right" style="width:20%">Amount</th>
                </tr>
            </thead>
            <tbody>
                <?php $row_num = 0; foreach ($sales as $sale): $row_num++; ?>
                <tr>
                    <td><?php echo $row_num; ?></td>
                    <td>
                        <div class="item-name"><?php echo htmlspecialchars($sale['medicine_name']); ?></div>
                        <?php if (!empty($sale['batch_number'])): ?>
                            <span class="item-batch">
                                <i class="bi bi-upc-scan"></i> Batch: <?php echo htmlspecialchars($sale['batch_number']); ?>
                                <?php if (!empty($sale['batch_expiry'])): ?>
                                    &middot; Exp: <?php echo date('M Y', strtotime($sale['batch_expiry'])); ?>
                                <?php endif; ?>
                            </span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center"><?php echo $sale['quantity']; ?></td>
                    <td class="text-right"><?php echo number_format($sale['unit_price'], 2); ?></td>
                    <td class="text-right" style="font-weight:600;"><?php echo number_format($sale['total_price'], 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Totals -->
        <div class="totals-section">
            <div class="totals-box">
                <div class="totals-row sub">
                    <span>Subtotal</span>
                    <span><?php echo number_format($grand_total, 2); ?></span>
                </div>
                <div class="totals-row sub">
                    <span>Tax</span>
                    <span>0.00</span>
                </div>
                <div class="totals-row grand">
                    <span>Grand Total</span>
                    <span><?php echo number_format($grand_total, 2); ?></span>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <div class="invoice-footer">
        <div class="footer-note">
            <i class="bi bi-heart-pulse"></i> Thank you for choosing <strong>Pharmacy Pro</strong>.<br>
            We wish you a speedy recovery. Get well soon!
        </div>
        <div class="footer-note" style="text-align:right;">
            <strong>Payment:</strong> Completed<br>
            Ref: <?php echo htmlspecialchars($sale_ids_str); ?>
        </div>
    </div>

    <div class="watermark">
        This is a computer-generated invoice &mdash; No signature required
    </div>

    <!-- Actions -->
    <div class="actions no-print">
        <button onclick="window.print()" class="btn-invoice btn-print">
            <i class="bi bi-printer"></i> Print Invoice
        </button>
        <button onclick="window.close()" class="btn-invoice btn-close-inv">
            <i class="bi bi-x-lg"></i> Close
        </button>
    </div>
</div>

</body>
</html>
