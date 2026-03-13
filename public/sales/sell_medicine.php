<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);
require_once '../../app/Models/Sale.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);
$saleModel = new Sale($db);
$customerModel = new Customer($db);

$message = '';
$error = '';
$sale_ids = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Verify CSRF

    $cart_json = $_POST['cart_data'] ?? '[]';
    $cart_items = json_decode($cart_json, true);
    $customer_name = trim($_POST['customer_name'] ?? '');
    if ($customer_name === '') {
        $customer_name = 'Walk-in Client';
    }

    $customer_phone = preg_replace('/\D+/', '', trim($_POST['customer_phone'] ?? ''));
    if ($customer_phone !== '' && !preg_match('/^\d{10,15}$/', $customer_phone)) {
        $error = "Customer mobile number must be 10 to 15 digits.";
    }

    $allowed_payment_methods = ['Cash', 'Card', 'UPI'];
    $payment_method = $_POST['payment_method'] ?? 'Cash';
    if (!in_array($payment_method, $allowed_payment_methods, true)) {
        $payment_method = 'Cash';
    }
    $discount_type = $_POST['discount_type'] ?? 'amount';
    if (!in_array($discount_type, ['amount', 'percent'], true)) {
        $discount_type = 'amount';
    }
    $discount_value = isset($_POST['discount_value']) ? max(0, (float)$_POST['discount_value']) : 0.0;
    $tax_percent = isset($_POST['tax_percent']) ? max(0, (float)$_POST['tax_percent']) : 0.0;

    $payment_reference = trim($_POST['payment_reference'] ?? '');
    $upi_txn_id = trim($_POST['upi_txn_id'] ?? '');
    $card_last4 = preg_replace('/\D+/', '', trim($_POST['card_last4'] ?? ''));
    $card_auth_ref = trim($_POST['card_auth_ref'] ?? '');

    // Payment references are optional. Validate format only when present.
    if ($card_last4 !== '' && strlen($card_last4) !== 4) {
        $error = "Card last 4 must contain exactly 4 digits when provided.";
    }

    $amount_tendered = isset($_POST['amount_tendered']) ? (float)$_POST['amount_tendered'] : 0.0;

    if (!$error && (empty($cart_items) || !is_array($cart_items))) {
        $error = "Cart is empty.";
    } elseif (!$error) {
        $db->beginTransaction();
        $success_count = 0;
        $errors = [];
        $prepared_sales = [];
        $invoice_subtotal = 0.0;
        $invoice_number = $saleModel->generateInvoiceNumber();

        foreach ($cart_items as $item) {
            $medicine_id = isset($item['id']) ? (int)$item['id'] : 0;
            $quantity = isset($item['qty']) ? (int)$item['qty'] : 0;
            $has_prescription = $item['rx_verified'] ?? 0; // Assuming frontend sends this

            if ($medicine_id <= 0 || $quantity <= 0) {
                $errors[] = "Invalid medicine item in cart.";
                continue;
            }

            // Validate logic similar to before
            $medicine->id = $medicine_id;
            if ($medicine->readOne()) {
                 if ($medicine->prescription_needed && !$has_prescription) {
                    $errors[] = "Prescription required for " . $medicine->name;
                    continue;
                 }

                 $start_stock = $medicine->stock;
                 if ($start_stock < $quantity) {
                    $errors[] = "Insufficient stock for " . $medicine->name;
                    continue;
                 }

                 $unit_price = $medicine->sale_price;
                 $total_price = $unit_price * $quantity;
                 $profit = ($unit_price - $medicine->inventory_price) * $quantity;

                 $prepared_sales[] = [
                    'medicine_id' => $medicine_id,
                    'medicine_name' => $medicine->name,
                    'quantity' => $quantity,
                    'unit_price' => $unit_price,
                    'total_price' => $total_price,
                    'profit' => $profit
                 ];
                 $invoice_subtotal += $total_price;
            } else {
                $errors[] = "Medicine ID $medicine_id not found.";
            }
        }

        $discount_amount = $discount_type === 'percent'
            ? ($invoice_subtotal * min($discount_value, 100) / 100)
            : min($discount_value, $invoice_subtotal);
        $taxable_amount = max(0, $invoice_subtotal - $discount_amount);
        $tax_amount = $taxable_amount * ($tax_percent / 100);
        $cgst_amount = $tax_amount / 2;
        $sgst_amount = $tax_amount / 2;
        $igst_amount = 0;
        $invoice_net_total = $taxable_amount + $tax_amount;
        $branchId = getCurrentBranchId();

        if (count($errors) === 0 && $payment_method === 'Cash') {
            if ($amount_tendered <= 0) {
                $errors[] = "Cash received amount is required for cash payments.";
            } elseif ($amount_tendered < $invoice_net_total) {
                $errors[] = "Cash received cannot be less than total amount.";
            }
        }

        $customer_id = null;
        if (count($errors) === 0 && $customer_phone !== '') {
            $customer_id = $customerModel->findOrCreateByMobile($customer_phone, $customer_name);
        }

        $final_amount_tendered = $payment_method === 'Cash' ? $amount_tendered : $invoice_net_total;
        $change_due = $payment_method === 'Cash' ? max(0, $amount_tendered - $invoice_net_total) : 0;

        if (count($errors) === 0) {
            foreach ($prepared_sales as $sale_item) {
                $saleModel->medicine_id = $sale_item['medicine_id'];
                $saleModel->quantity = $sale_item['quantity'];
                $saleModel->unit_price = $sale_item['unit_price'];
                $saleModel->total_price = $sale_item['total_price'];
                $saleModel->profit = $sale_item['profit'];
                $saleModel->customer_name = $customer_name;
                $saleModel->customer_id = $customer_id;
                $saleModel->invoice_number = $invoice_number;
                $saleModel->discount = $discount_amount;
                $saleModel->subtotal = $invoice_subtotal;
                $saleModel->discount_type = $discount_type;
                $saleModel->discount_value = $discount_value;
                $saleModel->discount_amount = $discount_amount;
                $saleModel->taxable_amount = $taxable_amount;
                $saleModel->tax_percent = $tax_percent;
                $saleModel->tax_amount = $tax_amount;
                $saleModel->cgst_amount = $cgst_amount;
                $saleModel->sgst_amount = $sgst_amount;
                $saleModel->igst_amount = $igst_amount;
                $saleModel->net_total = $invoice_net_total;
                $saleModel->customer_phone = $customer_phone;
                $saleModel->payment_method = $payment_method;
                $saleModel->payment_reference = $payment_reference;
                $saleModel->upi_txn_id = $upi_txn_id;
                $saleModel->card_last4 = $card_last4;
                $saleModel->card_auth_ref = $card_auth_ref;
                $saleModel->amount_tendered = $final_amount_tendered;
                $saleModel->change_due = $change_due;
                $saleModel->branch_id = $branchId;

                if ($saleModel->create()) {
                    $new_sale_id = $db->lastInsertId();
                    if ($inventory->adjustStock($sale_item['medicine_id'], $sale_item['quantity'], 'out', "Invoice $invoice_number sale to " . $customer_name, null, null, $branchId)) {
                        $sale_ids[] = $new_sale_id;
                        $success_count++;
                    } else {
                        $errors[] = "Inventory failed for " . $sale_item['medicine_name'];
                    }
                } else {
                    $errors[] = "Failed to record sale for " . $sale_item['medicine_name'];
                }
            }
        }

        if (count($errors) > 0) {
            $db->rollBack();
            $error = "Transaction failed: " . implode(", ", $errors);
        } else {
            if (!empty($customer_id)) {
                $customerModel->updateLedgerStats($customer_id, $invoice_net_total);
            }
            $db->commit();
            $ids_str = implode(',', $sale_ids);
            $message = "Transaction successful! Invoice: $invoice_number, Items sold: $success_count, Net Total: " . number_format($invoice_net_total, 2) . ".";
            
            // Log sale activity
            try {
                $activityLog = new ActivityLog($db);
                $activityLog->log('SALE', "Invoice $invoice_number: Sold $success_count items to $customer_name via $payment_method (Net ₹" . number_format($invoice_net_total, 2) . ")", 'sale', $sale_ids[0] ?? null);
            } catch (Exception $e) { /* activity_logs table may not exist yet */ }
        }
    }
}
// Get all medicines
$medicines = $medicine->read();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sell Medicine - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        
        <?php if ($message): ?>
            <div class="alert alert-success d-flex align-items-center mb-4">
                <i class="bi bi-check-circle-fill me-2 fs-5"></i>
                <div>
                    <?php echo $message; ?>
                    <?php if(!empty($ids_str)): ?>
                        <div class="mt-2">
                            <a href="invoice.php?invoice=<?php echo urlencode($invoice_number); ?>&ids=<?php echo urlencode($ids_str); ?>" target="_blank" class="btn btn-sm btn-success fw-bold"><i class="bi bi-printer me-1"></i>Print Invoice</a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger mb-4"><i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="row g-4">
            <!-- Left Panel: Add Item Form -->
            <div class="col-lg-5">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="bi bi-plus-circle me-2"></i>Add Item to Cart</h5>
                    </div>
                    <div class="card-body p-4">
                        <form id="addItemForm" onsubmit="event.preventDefault(); addToCart();">
                            <div class="mb-3">
                                <label for="barcode_input" class="form-label text-muted">Barcode Scan</label>
                                <input type="text" class="form-control" id="barcode_input" placeholder="Scan or type barcode and press Enter">
                            </div>
                            <div class="mb-4">
                                <label for="medicine_search" class="form-label text-muted">Select Product</label>
                                <input type="text" class="form-control form-control-lg mb-2" id="medicine_search" placeholder="Type to search medicines..." list="medicine_options" oninput="syncMedicineFromSearch()">
                                <datalist id="medicine_options"></datalist>
                                <select class="form-select form-select-lg" id="medicine_id" required onchange="updateProductDetails(this)">
                                    <option value="">-- Choose Medicine --</option>
                                    <?php 
                                    // Load all medicines once into an array to reuse for datalist
                                    $medList = $medicines->fetchAll(PDO::FETCH_ASSOC);
                                    foreach ($medList as $row): 
                                        $stock = $row['current_stock']; // Use calculated stock
                                        $label = $stock > 0 ? '' : ' (Out of Stock)';
                                        $disabled = $stock > 0 ? '' : 'disabled';

                                        // Color rules: 0 => red (disabled); <=10 => yellow; otherwise normal
                                        if ($stock <= 0) {
                                            $class = 'text-danger';
                                        } elseif ($stock <= 10) {
                                            $class = 'text-warning';
                                        } else {
                                            $class = '';
                                        }
                                    ?>
                                        <option value="<?php echo $row['id']; ?>" 
                                                class="<?php echo $class; ?>"
                                                <?php echo $disabled; ?>
                                                data-prescription="<?php echo $row['prescription_needed']; ?>"
                                                data-price="<?php echo $row['sale_price']; ?>"
                                                data-stock="<?php echo $stock; ?>"
                                                data-barcode="<?php echo htmlspecialchars($row['barcode'] ?? ''); ?>"
                                                data-name="<?php echo htmlspecialchars($row['name']); ?>">
                                            <?php echo htmlspecialchars($row['name']); ?> <?php echo $label; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="quantity" class="form-label text-muted">Quantity</label>
                                <div class="input-group">
                                    <button class="btn btn-outline-secondary" type="button" onclick="adjustQty(-1)">-</button>
                                    <input type="number" class="form-control text-center" id="quantity" value="1" min="1" required 
                                           oninput="validateQuantity(this)">
                                    <button class="btn btn-outline-secondary" type="button" onclick="adjustQty(1)">+</button>
                                </div>
                                <div id="stockBadge" class="mt-2"></div>
                            </div>
                            
                            <dl class="row mb-4" id="productInfoPanel" style="opacity: 0.5;">
                                <dt class="col-sm-4 text-muted">Price</dt>
                                <dd class="col-sm-8 fw-bold" id="infoPrice">-</dd>
                                <dt class="col-sm-4 text-muted">Stock</dt>
                                <dd class="col-sm-8" id="infoStock">-</dd>
                                <dt class="col-sm-4 text-muted">Rx</dt>
                                <dd class="col-sm-8" id="infoRx">-</dd>
                            </dl>

                            <div class="alert alert-warning mb-4" id="prescriptionAlert" style="display:none;">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="has_prescription">
                                    <label class="form-check-label fw-bold small" for="has_prescription">
                                        Verify: Valid Prescription
                                    </label>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary" id="addBtn" disabled>
                                    <i class="bi bi-cart-plus me-2"></i>Add to Cart
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Right Panel: Cart & Checkout -->
            <div class="col-lg-7">
                <div class="card shadow-sm h-100">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 text-primary"><i class="bi bi-cart3 me-2"></i>Current Cart</h5>
                        <span class="badge bg-primary rounded-pill" id="cartCount">0 items</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 300px; overflow-y: auto;">
                            <table class="table table-hover align-middle mb-0">
                                <thead class="table-light sticky-top">
                                    <tr>
                                        <th>Item</th>
                                        <th class="text-center">Qty</th>
                                        <th class="text-end">Price</th>
                                        <th class="text-end">Total</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="cartTableBody">
                                    <tr>
                                        <td colspan="5" class="text-center text-muted py-5">Cart is empty</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer bg-light p-4">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h5 class="mb-0 text-muted">Subtotal</h5>
                            <h5 class="mb-0 fw-bold text-primary" id="cartTotal">₹0.00</h5>
                        </div>
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="discount_type" class="form-label text-muted">Discount Type</label>
                                <select class="form-select" id="discount_type" name="discount_type" onchange="recalculateBill()">
                                    <option value="amount">Amount (₹)</option>
                                    <option value="percent">Percent (%)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="discount_value" class="form-label text-muted">Discount Value</label>
                                <input type="number" step="0.01" min="0" class="form-control" id="discount_value" name="discount_value" value="0" oninput="recalculateBill()">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="tax_percent" class="form-label text-muted">Tax (%)</label>
                            <input type="number" step="0.01" min="0" class="form-control" id="tax_percent" name="tax_percent" value="0" oninput="recalculateBill()">
                        </div>

                        <div class="alert alert-light border py-2 mb-3">
                            <div class="d-flex justify-content-between"><span class="text-muted">Discount</span><span id="discountAmountText">₹0.00</span></div>
                            <div class="d-flex justify-content-between"><span class="text-muted">Tax Amount</span><span id="taxAmountText">₹0.00</span></div>
                            <div class="d-flex justify-content-between mt-1"><span class="fw-bold">Net Payable</span><span class="fw-bold text-primary" id="netPayableText">₹0.00</span></div>
                        </div>
                        
                        <form method="POST" action="" id="checkoutForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="cart_data" id="cart_data_input">
                            <input type="hidden" name="discount_type" id="discount_type_input">
                            <input type="hidden" name="discount_value" id="discount_value_input">
                            <input type="hidden" name="tax_percent" id="tax_percent_input">
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label text-muted">Customer Name</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Walk-in Client">
                            </div>

                            <div class="mb-3">
                                <label for="customer_phone" class="form-label text-muted">Customer Mobile (Optional)</label>
                                <input type="tel" class="form-control" id="customer_phone" name="customer_phone" placeholder="10-15 digit mobile number" pattern="[0-9]{10,15}">
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="payment_method" class="form-label text-muted">Payment Method</label>
                                    <select class="form-select" id="payment_method" name="payment_method" onchange="onPaymentMethodChange()" required>
                                        <option value="Cash">Cash</option>
                                        <option value="Card">Card</option>
                                        <option value="UPI">UPI</option>
                                    </select>
                                </div>
                                <div class="col-md-6" id="cashReceivedWrapper">
                                    <label for="amount_tendered" class="form-label text-muted">Cash Received</label>
                                    <input type="number" step="0.01" min="0" class="form-control" id="amount_tendered" name="amount_tendered" placeholder="0.00" oninput="calculateChangeDue()">
                                </div>
                            </div>

                            <div class="mb-3" id="paymentReferenceWrapper" style="display:none;">
                                <label for="payment_reference" class="form-label text-muted">Payment Reference</label>
                                <input type="text" class="form-control" id="payment_reference" name="payment_reference" placeholder="Optional transaction/reference number">
                            </div>

                            <div class="row g-3 mb-3" id="upiFields" style="display:none;">
                                <div class="col-12">
                                    <label for="upi_txn_id" class="form-label text-muted">UPI Transaction ID</label>
                                    <input type="text" class="form-control" id="upi_txn_id" name="upi_txn_id" placeholder="Required for UPI">
                                </div>
                            </div>

                            <div class="row g-3 mb-3" id="cardFields" style="display:none;">
                                <div class="col-md-4">
                                    <label for="card_last4" class="form-label text-muted">Card Last 4</label>
                                    <input type="text" maxlength="4" class="form-control" id="card_last4" name="card_last4" placeholder="1234">
                                </div>
                                <div class="col-md-8">
                                    <label for="card_auth_ref" class="form-label text-muted">Card Auth Ref</label>
                                    <input type="text" class="form-control" id="card_auth_ref" name="card_auth_ref" placeholder="Required for card payment">
                                </div>
                            </div>

                            <div class="alert alert-light border py-2 mb-3" id="changeDueBox">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Change Due</span>
                                    <span class="fw-bold" id="changeDueValue">₹0.00</span>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg" id="checkoutBtn" disabled>
                                    <i class="bi bi-check-circle-fill me-2"></i>Process Transaction
                                </button>
                            </div>

                            <div class="row g-2 mt-2">
                                <div class="col-6 d-grid">
                                    <button type="button" class="btn btn-outline-secondary" onclick="holdCurrentCart()"><i class="bi bi-pause-circle me-1"></i>Hold Cart</button>
                                </div>
                                <div class="col-6 d-grid">
                                    <button type="button" class="btn btn-outline-primary" onclick="loadHeldCarts()"><i class="bi bi-play-circle me-1"></i>Resume Cart</button>
                                </div>
                            </div>

                            <div class="mt-2" id="heldCartsWrap" style="display:none;">
                                <div class="card border-0 bg-light">
                                    <div class="card-body p-2" id="heldCartsList"></div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Shopping Cart State
        let cart = [];
        let currentProduct = null;
        let cartGrandTotal = 0;
        let cartNetTotal = 0;

        // Build datalist for search-as-you-type
        document.addEventListener('DOMContentLoaded', function() {
            const select = document.getElementById('medicine_id');
            const datalist = document.getElementById('medicine_options');
            const medicineSearch = document.getElementById('medicine_search');

            function populateDatalist() {
                if (!datalist || !select) return;
                datalist.innerHTML = '';
                Array.from(select.options).forEach(opt => {
                    if (!opt.value) return;
                    const option = document.createElement('option');
                    option.value = opt.textContent;
                    datalist.appendChild(option);
                });
            }

            populateDatalist();

            if (medicineSearch) {
                medicineSearch.addEventListener('input', syncMedicineFromSearch);
            }

            const barcodeInput = document.getElementById('barcode_input');
            if (barcodeInput) {
                barcodeInput.addEventListener('keydown', function(e) {
                    if (e.key !== 'Enter') return;
                    e.preventDefault();
                    const code = this.value.trim();
                    if (!code) return;
                    const match = Array.from(select.options).find(opt => (opt.getAttribute('data-barcode') || '') === code);
                    if (match) {
                        select.value = match.value;
                        updateProductDetails(select);
                        addToCart();
                        this.value = '';
                    } else {
                        alert('Barcode not found.');
                    }
                });
            }

            if (select) {
                updateProductDetails(select);
            }
        });
        function updateProductDetails(select) {
            const option = select.options[select.selectedIndex];
            const infoPanel = document.getElementById('productInfoPanel');
            const stockBadge = document.getElementById('stockBadge');
            const btn = document.getElementById('addBtn');
            
            if (!option.value) {
                currentProduct = null;
                infoPanel.style.opacity = '0.5';
                btn.disabled = true;
                resetDisplay();
                return;
            }

            infoPanel.style.opacity = '1';
            
            // Get data
            currentProduct = {
                id: option.value,
                name: option.getAttribute('data-name'),
                price: parseFloat(option.getAttribute('data-price')),
                stock: parseInt(option.getAttribute('data-stock')),
                rx: option.getAttribute('data-prescription') === '1'
            };

            // Info Panel
            document.getElementById('infoPrice').textContent = '₹' + currentProduct.price.toFixed(2);
            document.getElementById('infoStock').textContent = currentProduct.stock + ' units';
            document.getElementById('infoRx').innerHTML = currentProduct.rx 
                ? '<span class="badge bg-warning text-dark">Required</span>' 
                : '<span class="badge bg-success">Optional</span>';

            // Stock Badge
            if(currentProduct.stock < 10) {
                 stockBadge.innerHTML = `<span class="badge bg-danger">Low Stock: ${currentProduct.stock} left</span>`;
            } else {
                 stockBadge.innerHTML = `<span class="badge bg-success">In Stock: ${currentProduct.stock}</span>`;
            }

            // Prescription logic
            const rxAlert = document.getElementById('prescriptionAlert');
            const rxCheck = document.getElementById('has_prescription');
            
            if (currentProduct.rx) {
                rxAlert.style.display = 'block';
                rxCheck.checked = false;
            } else {
                rxAlert.style.display = 'none';
                rxCheck.checked = false; // Reset
            }
            
            btn.disabled = false;
            validateQuantity(document.getElementById('quantity'));
        }

        function adjustQty(delta) {
            const qtyInput = document.getElementById('quantity');
            let val = parseInt(qtyInput.value) || 0;
            val += delta;
            if (val < 1) val = 1;
            qtyInput.value = val;
            validateQuantity(qtyInput);
        }

        function validateQuantity(input) {
            if (!currentProduct) return;
            
            const qty = parseInt(input.value) || 0;
            const btn = document.getElementById('addBtn');

            // Validating against total stock minus what's already in cart?
            // Let's implement that:
            const inCart = cart.find(item => item.id === currentProduct.id);
            const cartQty = inCart ? inCart.qty : 0;
            const available = currentProduct.stock - cartQty;

            if (qty > available) {
                input.classList.add('is-invalid');
                btn.disabled = true;
                if (available <= 0) {
                    alert('No more stock available!'); // Simple feedback
                }
            } else {
                input.classList.remove('is-invalid');
                btn.disabled = false;
            }
        }

        function resetDisplay() {
            document.getElementById('infoPrice').textContent = '-';
            document.getElementById('infoStock').textContent = '-';
            document.getElementById('infoRx').textContent = '-';
            document.getElementById('stockBadge').innerHTML = '';
            document.getElementById('prescriptionAlert').style.display = 'none';
        }

        function syncMedicineFromSearch() {
            const input = document.getElementById('medicine_search');
            const select = document.getElementById('medicine_id');
            if (!input || !select) return;

            const term = input.value.trim().toLowerCase();
            if (!term) {
                select.value = '';
                updateProductDetails(select);
                return;
            }

            const match = Array.from(select.options).find(opt => opt.textContent.toLowerCase().includes(term));
            if (match) {
                select.value = match.value;
                updateProductDetails(select);
            }
        }

        function addToCart() {
            if (!currentProduct) return;
            
            const qtyInput = document.getElementById('quantity');
            const qty = parseInt(qtyInput.value) || 0;
            const rxCheck = document.getElementById('has_prescription');
            
            if (currentProduct.rx && !rxCheck.checked) {
                alert('Please verify prescription for this item.');
                return;
            }

            // Check if already in cart
            const existingItemIndex = cart.findIndex(item => item.id === currentProduct.id);
            
            if (existingItemIndex > -1) {
                // Update quantity
                // Validate total stock again
                if (cart[existingItemIndex].qty + qty > currentProduct.stock) {
                    alert('Cannot add more quantity than available stock.');
                    return;
                }
                cart[existingItemIndex].qty += qty;
                cart[existingItemIndex].total = cart[existingItemIndex].qty * cart[existingItemIndex].price;
            } else {
                 if (qty > currentProduct.stock) {
                    alert('Insufficient stock.');
                    return;
                }
                cart.push({
                    ...currentProduct,
                    qty: qty,
                    total: qty * currentProduct.price,
                    rx_verified: rxCheck.checked ? 1 : 0
                });
            }

            // Reset Form somewhat
            qtyInput.value = 1;
            rxCheck.checked = false;
            // Ideally reset selection too, but let's keep it for fast adding of same item type? 
            // Better to reset to prevent accidental duplicate adds.
            document.getElementById('medicine_id').value = "";
            updateProductDetails(document.getElementById('medicine_id'));

            renderCart();
        }

        function removeFromCart(index) {
            cart.splice(index, 1);
            renderCart();
        }

        function renderCart() {
            const tbody = document.getElementById('cartTableBody');
            const totalEl = document.getElementById('cartTotal');
            const countEl = document.getElementById('cartCount');
            const checkoutBtn = document.getElementById('checkoutBtn');
            const formInput = document.getElementById('cart_data_input');
            const checkoutForm = document.getElementById('checkoutForm');

            tbody.innerHTML = '';
            let grandTotal = 0;
            let itemCount = 0;

            if (cart.length === 0) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-5">Cart is empty</td></tr>';
                checkoutBtn.disabled = true;
            } else {
                cart.forEach((item, index) => {
                    grandTotal += item.total;
                    itemCount += item.qty;
                    
                    const row = document.createElement('tr');
                    row.innerHTML = `
                        <td>
                            <div class="fw-bold">${item.name}</div>
                            ${item.rx_verified ? '<span class="badge bg-warning text-dark" style="font-size:0.7em">Rx Verified</span>' : ''}
                        </td>
                        <td class="text-center">${item.qty}</td>
                        <td class="text-end">₹${item.price.toFixed(2)}</td>
                        <td class="text-end fw-bold">₹${item.total.toFixed(2)}</td>
                        <td class="text-end">
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${index})">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
                checkoutBtn.disabled = false;
            }

            cartGrandTotal = grandTotal;
            totalEl.textContent = '₹' + grandTotal.toFixed(2);
            countEl.textContent = `${itemCount} items`;
            recalculateBill();
            calculateChangeDue();
            
            // Sync with hidden input
            formInput.value = JSON.stringify(cart);
        }

        function onPaymentMethodChange() {
            const paymentMethod = document.getElementById('payment_method').value;
            const cashWrapper = document.getElementById('cashReceivedWrapper');
            const cashInput = document.getElementById('amount_tendered');
            const paymentReferenceWrapper = document.getElementById('paymentReferenceWrapper');
            const upiFields = document.getElementById('upiFields');
            const cardFields = document.getElementById('cardFields');
            const upiTxn = document.getElementById('upi_txn_id');
            const cardLast4 = document.getElementById('card_last4');
            const cardAuthRef = document.getElementById('card_auth_ref');

            if (paymentMethod === 'Cash') {
                cashWrapper.style.display = '';
                cashInput.setAttribute('required', 'required');
                paymentReferenceWrapper.style.display = 'none';
                upiFields.style.display = 'none';
                cardFields.style.display = 'none';
            } else {
                cashWrapper.style.display = 'none';
                cashInput.removeAttribute('required');
                cashInput.value = '';
                paymentReferenceWrapper.style.display = '';

                if (paymentMethod === 'UPI') {
                    upiFields.style.display = '';
                    cardFields.style.display = 'none';
                } else if (paymentMethod === 'Card') {
                    upiFields.style.display = 'none';
                    cardFields.style.display = '';
                }
            }
            calculateChangeDue();
        }

        function recalculateBill() {
            const discountType = document.getElementById('discount_type').value;
            const discountValue = parseFloat(document.getElementById('discount_value').value || '0');
            const taxPercent = parseFloat(document.getElementById('tax_percent').value || '0');

            let discountAmount = 0;
            if (discountType === 'percent') {
                discountAmount = cartGrandTotal * (Math.min(discountValue, 100) / 100);
            } else {
                discountAmount = Math.min(discountValue, cartGrandTotal);
            }

            const taxable = Math.max(0, cartGrandTotal - discountAmount);
            const taxAmount = taxable * (taxPercent / 100);
            cartNetTotal = taxable + taxAmount;

            document.getElementById('discountAmountText').textContent = '₹' + discountAmount.toFixed(2);
            document.getElementById('taxAmountText').textContent = '₹' + taxAmount.toFixed(2);
            document.getElementById('netPayableText').textContent = '₹' + cartNetTotal.toFixed(2);
            calculateChangeDue();
        }

        function calculateChangeDue() {
            const paymentMethod = document.getElementById('payment_method').value;
            const cashInput = document.getElementById('amount_tendered');
            const changeEl = document.getElementById('changeDueValue');

            if (paymentMethod !== 'Cash') {
                changeEl.textContent = '₹0.00';
                changeEl.classList.remove('text-danger');
                return;
            }

            const paid = parseFloat(cashInput.value || '0');
            const change = paid - cartNetTotal;
            if (change < 0) {
                changeEl.textContent = 'Short: ₹' + Math.abs(change).toFixed(2);
                changeEl.classList.add('text-danger');
            } else {
                changeEl.textContent = '₹' + change.toFixed(2);
                changeEl.classList.remove('text-danger');
            }
        }
        
        // Handle checkout form submission to ensure cart is not empty
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Cart is empty!');
                return;
            }

            const paymentMethod = document.getElementById('payment_method').value;
            if (paymentMethod === 'Cash') {
                const paid = parseFloat(document.getElementById('amount_tendered').value || '0');
                if (paid < cartNetTotal) {
                    e.preventDefault();
                    alert('Cash received cannot be less than total amount.');
                    return;
                }
            }

            document.getElementById('discount_type_input').value = document.getElementById('discount_type').value;
            document.getElementById('discount_value_input').value = document.getElementById('discount_value').value || '0';
            document.getElementById('tax_percent_input').value = document.getElementById('tax_percent').value || '0';
        });

        onPaymentMethodChange();

        function holdCurrentCart() {
            if (!cart || cart.length === 0) {
                alert('Cart is empty.');
                return;
            }
            const payload = {
                action: 'hold',
                customer_name: document.getElementById('customer_name').value || '',
                customer_phone: document.getElementById('customer_phone').value || '',
                cart_json: JSON.stringify(cart),
                bill_snapshot_json: JSON.stringify({
                    discount_type: document.getElementById('discount_type').value,
                    discount_value: document.getElementById('discount_value').value,
                    tax_percent: document.getElementById('tax_percent').value
                })
            };

            fetch('hold_cart_api.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify(payload)
            }).then(r => r.json()).then(res => {
                if (!res.success) {
                    alert(res.message || 'Failed to hold cart.');
                    return;
                }
                alert('Cart held as ' + res.hold_code);
                cart = [];
                renderCart();
            }).catch(() => alert('Failed to hold cart.'));
        }

        function loadHeldCarts() {
            fetch('hold_cart_api.php?action=list')
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        alert(res.message || 'Failed to load held carts.');
                        return;
                    }
                    const wrap = document.getElementById('heldCartsWrap');
                    const list = document.getElementById('heldCartsList');
                    if (!res.data || res.data.length === 0) {
                        list.innerHTML = '<div class="text-muted small">No held carts found.</div>';
                        wrap.style.display = '';
                        return;
                    }
                    list.innerHTML = res.data.map(c => `
                        <div class="d-flex justify-content-between align-items-center border-bottom py-1">
                            <div>
                                <div class="fw-semibold small">${c.hold_code}</div>
                                <div class="text-muted" style="font-size:0.75rem">${c.customer_name || 'Walk-in'} ${c.customer_phone || ''}</div>
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="resumeHeldCart(${c.id})">Resume</button>
                        </div>
                    `).join('');
                    wrap.style.display = '';
                })
                .catch(() => alert('Failed to load held carts.'));
        }

        function resumeHeldCart(id) {
            fetch('hold_cart_api.php?action=resume&id=' + encodeURIComponent(id))
                .then(r => r.json())
                .then(res => {
                    if (!res.success) {
                        alert(res.message || 'Failed to resume cart.');
                        return;
                    }
                    cart = JSON.parse(res.data.cart_json || '[]');
                    const bill = JSON.parse(res.data.bill_snapshot_json || '{}');
                    document.getElementById('customer_name').value = res.data.customer_name || '';
                    document.getElementById('customer_phone').value = res.data.customer_phone || '';
                    if (bill.discount_type) document.getElementById('discount_type').value = bill.discount_type;
                    if (typeof bill.discount_value !== 'undefined') document.getElementById('discount_value').value = bill.discount_value;
                    if (typeof bill.tax_percent !== 'undefined') document.getElementById('tax_percent').value = bill.tax_percent;
                    renderCart();
                    document.getElementById('heldCartsWrap').style.display = 'none';
                })
                .catch(() => alert('Failed to resume cart.'));
        }
    </script>
</body>
</html>