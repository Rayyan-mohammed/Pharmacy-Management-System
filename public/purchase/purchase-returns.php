<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);
checkPermission('purchase.create');

$database = new Database();
$db = $database->getConnection();
$purchaseModel = new Purchase($db);

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        $header = [
            'purchase_id' => (int)($_POST['purchase_id'] ?? 0),
            'supplier_id' => (int)($_POST['supplier_id'] ?? 0),
            'return_date' => $_POST['return_date'] ?? date('Y-m-d'),
            'reason' => trim($_POST['reason'] ?? '')
        ];
        $items = json_decode($_POST['items_json'] ?? '[]', true);
        if (!is_array($items) || count($items) === 0) {
            throw new Exception('At least one return row is required.');
        }

        $returnId = $purchaseModel->createPurchaseReturn($header, $items, (int)($_SESSION['currentUser']['user_id'] ?? 0));
        $message = 'Purchase return posted successfully. Return ID #' . $returnId;
        $messageType = 'success';

        try {
            $al = new ActivityLog($db);
            $al->log('PURCHASE_RETURN', 'Created supplier return #' . $returnId . ' for purchase #' . $header['purchase_id'], 'purchase_return', $returnId);
        } catch (Exception $e) {}
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

$outstandingBills = $purchaseModel->getOutstandingBills(0, 300);
$recentReturns = $purchaseModel->getRecentPurchaseReturns(50);
$medicine = new Medicine($db);
$medicines = $medicine->read()->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Purchase Returns - Pharmacy Pro</title>
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
    <h2 class="fw-bold text-primary mb-3"><i class="bi bi-arrow-counterclockwise me-2"></i>Purchase Returns to Supplier</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-7">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Create Purchase Return</h6></div>
                <div class="card-body">
                    <form method="POST" id="returnForm">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="items_json" id="items_json">
                        <div class="row g-2 mb-3">
                            <div class="col-md-8">
                                <label class="form-label">Source Purchase Bill</label>
                                <select class="form-select" name="purchase_id" id="purchase_id" onchange="syncSupplierFromBill()" required>
                                    <option value="">Select bill</option>
                                    <?php foreach ($outstandingBills as $b): ?>
                                        <option value="<?php echo (int)$b['id']; ?>" data-supplier-id="<?php echo (int)$b['supplier_id']; ?>" data-supplier="<?php echo htmlspecialchars($b['supplier_name']); ?>"><?php echo htmlspecialchars($b['supplier_name'] . ' | ' . $b['invoice_number'] . ' | Due ₹' . number_format((float)$b['due_amount'], 2)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Return Date</label>
                                <input type="date" class="form-control" name="return_date" value="<?php echo date('Y-m-d'); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Supplier</label>
                            <input type="text" class="form-control" id="supplier_name_view" placeholder="Auto-filled from bill" readonly>
                            <input type="hidden" name="supplier_id" id="supplier_id_hidden">
                        </div>

                        <div class="table-responsive mb-3">
                            <table class="table table-sm" id="returnItemsTable">
                                <thead class="table-light"><tr><th>Medicine</th><th>Batch</th><th class="text-end">Qty</th><th class="text-end">Cost</th><th class="text-end">Line</th><th></th></tr></thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-between mb-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addReturnRow()"><i class="bi bi-plus-lg me-1"></i>Add Row</button>
                            <div class="fw-bold">Credit Amount: <span id="creditTotal">₹0.00</span></div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Reason</label>
                            <textarea class="form-control" name="reason" rows="2" placeholder="Damage/expiry/quality issue"></textarea>
                        </div>

                        <button class="btn btn-warning w-100"><i class="bi bi-box-arrow-left me-1"></i>Post Return & Apply Credit</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-5">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 text-primary">Recent Supplier Returns</h6></div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light"><tr><th class="px-3">Return #</th><th>Supplier</th><th class="text-end px-3">Amount</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentReturns)): ?>
                                <tr><td colspan="3" class="text-center text-muted py-3">No returns yet.</td></tr>
                            <?php else: foreach ($recentReturns as $r): ?>
                                <tr>
                                    <td class="px-3"><?php echo htmlspecialchars($r['return_number']); ?><br><small class="text-muted"><?php echo date('d M Y', strtotime($r['return_date'])); ?></small></td>
                                    <td><?php echo htmlspecialchars($r['supplier_name']); ?><br><small class="text-muted">Bill <?php echo htmlspecialchars($r['invoice_number']); ?></small></td>
                                    <td class="text-end px-3 fw-bold">₹<?php echo number_format((float)$r['total_amount'], 2); ?></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const medicines = <?php echo json_encode(array_map(function($m){
    return ['id'=>(int)$m['id'],'name'=>$m['name'],'cost'=>(float)$m['inventory_price']];
}, $medicines)); ?>;

function medOptions() {
    return '<option value="">Select medicine</option>' + medicines.map(m => `<option value="${m.id}">${m.name}</option>`).join('');
}

function addReturnRow() {
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><select class="form-select form-select-sm med" onchange="onMedChange(this)">${medOptions()}</select></td>
        <td><input type="text" class="form-control form-control-sm batch" placeholder="Batch"></td>
        <td><input type="number" min="1" value="1" class="form-control form-control-sm qty" oninput="recalcReturnRow(this)"></td>
        <td><input type="number" step="0.01" min="0.01" value="0" class="form-control form-control-sm cost" oninput="recalcReturnRow(this)"></td>
        <td><input type="text" class="form-control form-control-sm line" value="0.00" readonly></td>
        <td><button type="button" class="btn btn-sm btn-outline-danger" onclick="this.closest('tr').remove(); recalcReturnTotal();"><i class="bi bi-trash"></i></button></td>`;
    document.querySelector('#returnItemsTable tbody').appendChild(tr);
    recalcReturnTotal();
}

function onMedChange(sel) {
    const med = medicines.find(m => String(m.id) === sel.value);
    if (!med) return;
    const tr = sel.closest('tr');
    tr.querySelector('.cost').value = med.cost.toFixed(2);
    recalcReturnRow(sel);
}

function recalcReturnRow(el) {
    const tr = el.closest('tr');
    const qty = parseFloat(tr.querySelector('.qty').value || '0');
    const cost = parseFloat(tr.querySelector('.cost').value || '0');
    tr.querySelector('.line').value = (qty * cost).toFixed(2);
    recalcReturnTotal();
}

function recalcReturnTotal() {
    let total = 0;
    document.querySelectorAll('#returnItemsTable tbody tr').forEach(tr => {
        total += parseFloat(tr.querySelector('.line').value || '0');
    });
    document.getElementById('creditTotal').textContent = '₹' + total.toFixed(2);
}

function syncSupplierFromBill() {
    const sel = document.getElementById('purchase_id');
    const opt = sel.options[sel.selectedIndex];
    document.getElementById('supplier_name_view').value = opt ? (opt.getAttribute('data-supplier') || '') : '';
    document.getElementById('supplier_id_hidden').value = opt ? (opt.getAttribute('data-supplier-id') || '') : '';
}

document.getElementById('returnForm').addEventListener('submit', function(e) {
    const purchaseId = parseInt(document.getElementById('purchase_id').value || '0');
    if (purchaseId <= 0) {
        e.preventDefault();
        alert('Select a source purchase bill.');
        return;
    }

    const items = [];
    document.querySelectorAll('#returnItemsTable tbody tr').forEach(tr => {
        const medicineId = parseInt(tr.querySelector('.med').value || '0');
        const batch = tr.querySelector('.batch').value.trim();
        const qty = parseInt(tr.querySelector('.qty').value || '0');
        const cost = parseFloat(tr.querySelector('.cost').value || '0');
        if (medicineId > 0 && qty > 0 && cost > 0 && batch !== '') {
            items.push({medicine_id: medicineId, batch_number: batch, quantity: qty, unit_cost: cost, line_total: qty * cost});
        }
    });

    if (items.length === 0) {
        e.preventDefault();
        alert('Add at least one valid return row.');
        return;
    }

    document.getElementById('items_json').value = JSON.stringify(items);
});

addReturnRow();
</script>
</body>
</html>
