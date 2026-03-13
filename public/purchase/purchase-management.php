<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$purchase = new Purchase($db);
$supplier = new Supplier($db);
$medicine = new Medicine($db);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();

    try {
        $header = [
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
            'invoice_number' => trim($_POST['invoice_number'] ?? ''),
            'purchase_date' => $_POST['purchase_date'] ?? date('Y-m-d'),
            'tax_amount' => (float)($_POST['tax_amount'] ?? 0),
            'discount_amount' => (float)($_POST['discount_amount'] ?? 0),
            'amount_paid' => (float)($_POST['amount_paid'] ?? 0),
            'notes' => trim($_POST['notes'] ?? '')
        ];

        if ($header['supplier_id'] <= 0 || $header['invoice_number'] === '' || $header['purchase_date'] === '') {
            throw new Exception('Supplier, invoice number, and purchase date are required.');
        }

        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items) || count($items) === 0) {
            throw new Exception('At least one purchase row is required.');
        }

        $purchaseId = $purchase->createPurchase($header, $items, (int)$_SESSION['currentUser']['user_id']);
        $message = 'Purchase posted successfully. GRN #' . $purchaseId;
        $messageType = 'success';

        try {
            $al = new ActivityLog($db);
            $al->log('PURCHASE', 'Created purchase GRN #' . $purchaseId . ' invoice ' . $header['invoice_number'], 'purchase', $purchaseId);
        } catch (Exception $e) {}

    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

$suppliers = $supplier->read()->fetchAll(PDO::FETCH_ASSOC);
$medicines = $medicine->read()->fetchAll(PDO::FETCH_ASSOC);
$recent = $purchase->getRecentPurchases(15);
$dueSummary = $purchase->getSupplierDueSummary();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase & GRN - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro</a>
    </div>
</nav>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold text-primary mb-1"><i class="bi bi-truck me-2"></i>Purchase & GRN</h2>
            <p class="text-muted mb-0">Post supplier invoices, update stock batches, and track dues.</p>
        </div>
        <div class="d-flex gap-2">
            <a href="purchase-history.php" class="btn btn-outline-primary"><i class="bi bi-clock-history me-1"></i>Purchase History</a>
            <a href="supplier-payables.php" class="btn btn-outline-danger"><i class="bi bi-hourglass-split me-1"></i>Payable Aging</a>
            <a href="settlements.php" class="btn btn-outline-success"><i class="bi bi-wallet2 me-1"></i>Settlements</a>
            <a href="purchase-returns.php" class="btn btn-outline-warning"><i class="bi bi-arrow-counterclockwise me-1"></i>Supplier Returns</a>
        </div>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-primary">New Purchase Entry</h5></div>
                <div class="card-body">
                    <form method="POST" id="purchaseForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="items_json" id="items_json">

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label">Supplier</label>
                                <select class="form-select" name="supplier_id" required>
                                    <option value="">Select supplier</option>
                                    <?php foreach ($suppliers as $s): ?>
                                        <option value="<?php echo (int)$s['id']; ?>"><?php echo htmlspecialchars($s['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Invoice Number</label>
                                <input type="text" class="form-control" name="invoice_number" required>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Purchase Date</label>
                                <input type="date" class="form-control" name="purchase_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm align-middle" id="purchaseItemsTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Medicine</th>
                                        <th>Batch</th>
                                        <th>Expiry</th>
                                        <th>Qty</th>
                                        <th>Cost</th>
                                        <th>Sale</th>
                                        <th>Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addRow()"><i class="bi bi-plus-lg me-1"></i>Add Row</button>
                            <div class="text-end">
                                <div>Subtotal: <strong id="subtotalText">₹0.00</strong></div>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <label class="form-label">Discount</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="discount_amount" id="discount_amount" value="0" oninput="recalcTotals()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Tax</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="tax_amount" id="tax_amount" value="0" oninput="recalcTotals()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Amount Paid</label>
                                <input type="number" step="0.01" min="0" class="form-control" name="amount_paid" id="amount_paid" value="0" oninput="recalcTotals()">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Due Amount</label>
                                <input type="text" class="form-control" id="due_amount_text" value="₹0.00" readonly>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>

                        <div class="d-grid">
                            <button class="btn btn-success" type="submit"><i class="bi bi-check-circle me-2"></i>Post Purchase & Update Stock</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Supplier Due Summary</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th class="px-3">Supplier</th><th class="text-end px-3">Due</th></tr></thead>
                            <tbody>
                                <?php if (empty($dueSummary)): ?>
                                    <tr><td colspan="2" class="text-center text-muted py-3">No dues pending.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($dueSummary as $d): ?>
                                        <tr><td class="px-3"><?php echo htmlspecialchars($d['supplier_name']); ?></td><td class="text-end px-3 fw-bold">₹<?php echo number_format((float)$d['total_due'], 2); ?></td></tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Recent Purchases</h6></div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead class="table-light"><tr><th class="px-3">Invoice</th><th>Date</th><th class="text-end px-3">Amount</th></tr></thead>
                            <tbody>
                                <?php if (empty($recent)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-3">No purchases yet.</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent as $p): ?>
                                        <tr>
                                            <td class="px-3"><div class="fw-semibold"><?php echo htmlspecialchars($p['invoice_number']); ?></div><small class="text-muted"><?php echo htmlspecialchars($p['supplier_name']); ?></small></td>
                                            <td><?php echo date('d M Y', strtotime($p['purchase_date'])); ?></td>
                                            <td class="text-end px-3">₹<?php echo number_format((float)$p['total_amount'], 2); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const medicines = <?php echo json_encode(array_map(function($m) {
    return [
        'id' => (int)$m['id'],
        'name' => $m['name'],
        'cost' => (float)$m['inventory_price'],
        'sale' => (float)$m['sale_price']
    ];
}, $medicines)); ?>;

function medicineOptions() {
    return '<option value="">Select medicine</option>' + medicines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
}

function addRow() {
    const tbody = document.querySelector('#purchaseItemsTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm med" onchange="onMedicineChange(this)">${medicineOptions()}</select></td>
        <td><input type="text" class="form-control form-control-sm batch" value="BATCH-${new Date().toISOString().slice(0,10).replaceAll('-', '')}"></td>
        <td><input type="date" class="form-control form-control-sm exp"></td>
        <td><input type="number" min="1" class="form-control form-control-sm qty" value="1" oninput="recalcRow(this)"></td>
        <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm cost" value="0" oninput="recalcRow(this)"></td>
        <td><input type="number" step="0.01" min="0.01" class="form-control form-control-sm sale" value="0" oninput="recalcRow(this)"></td>
        <td><input type="text" class="form-control form-control-sm line-total" value="0.00" readonly></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="removeRow(this)"><i class="bi bi-trash"></i></button></td>
    `;
    tbody.appendChild(tr);
    recalcTotals();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    recalcTotals();
}

function onMedicineChange(sel) {
    const tr = sel.closest('tr');
    const med = medicines.find(m => String(m.id) === sel.value);
    if (!med) return;
    tr.querySelector('.cost').value = med.cost.toFixed(2);
    tr.querySelector('.sale').value = med.sale.toFixed(2);
    recalcRow(tr.querySelector('.qty'));
}

function recalcRow(el) {
    const tr = el.closest('tr');
    const qty = parseFloat(tr.querySelector('.qty').value || '0');
    const cost = parseFloat(tr.querySelector('.cost').value || '0');
    const line = qty * cost;
    tr.querySelector('.line-total').value = line.toFixed(2);
    recalcTotals();
}

function gatherItems() {
    const rows = Array.from(document.querySelectorAll('#purchaseItemsTable tbody tr'));
    return rows.map(tr => {
        const qty = parseInt(tr.querySelector('.qty').value || '0');
        const cost = parseFloat(tr.querySelector('.cost').value || '0');
        const line = qty * cost;
        return {
            medicine_id: parseInt(tr.querySelector('.med').value || '0'),
            batch_number: tr.querySelector('.batch').value.trim(),
            expiration_date: tr.querySelector('.exp').value,
            quantity: qty,
            cost_price: cost,
            sale_price: parseFloat(tr.querySelector('.sale').value || '0'),
            line_total: line
        };
    }).filter(i => i.medicine_id > 0 && i.quantity > 0);
}

function recalcTotals() {
    const items = gatherItems();
    const subtotal = items.reduce((s, i) => s + i.line_total, 0);
    const discount = parseFloat(document.getElementById('discount_amount').value || '0');
    const tax = parseFloat(document.getElementById('tax_amount').value || '0');
    const paid = parseFloat(document.getElementById('amount_paid').value || '0');
    const total = Math.max(0, subtotal - discount + tax);
    const due = Math.max(0, total - paid);

    document.getElementById('subtotalText').textContent = '₹' + subtotal.toFixed(2);
    document.getElementById('due_amount_text').value = '₹' + due.toFixed(2);
}

document.getElementById('purchaseForm').addEventListener('submit', function(e) {
    const items = gatherItems();
    if (items.length === 0) {
        e.preventDefault();
        alert('Add at least one valid purchase row.');
        return;
    }

    for (const i of items) {
        if (!i.batch_number || !i.expiration_date || i.cost_price <= 0 || i.sale_price <= 0) {
            e.preventDefault();
            alert('Complete batch, expiry, cost, and sale fields for all rows.');
            return;
        }
    }

    document.getElementById('items_json').value = JSON.stringify(items);
});

addRow();
</script>
</body>
</html>
