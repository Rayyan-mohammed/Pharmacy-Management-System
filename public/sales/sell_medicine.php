<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);
require_once '../../app/Models/Sale.php';

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);
$saleModel = new Sale($db);

$message = '';
$error = '';
$sale_ids = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token(); // Verify CSRF

    $cart_json = $_POST['cart_data'] ?? '[]';
    $cart_items = json_decode($cart_json, true);
    $customer_name = $_POST['customer_name'] ?? 'Walk-in Client';

    if (empty($cart_items) || !is_array($cart_items)) {
        $error = "Cart is empty.";
    } else {
        $db->beginTransaction();
        $success_count = 0;
        $errors = [];

        foreach ($cart_items as $item) {
            $medicine_id = $item['id'];
            $quantity = $item['qty'];
            $has_prescription = $item['rx_verified'] ?? 0; // Assuming frontend sends this

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

                 $saleModel->medicine_id = $medicine_id;
                 $saleModel->quantity = $quantity;
                 $saleModel->unit_price = $unit_price;
                 $saleModel->total_price = $total_price;
                 $saleModel->profit = $profit;
                 $saleModel->customer_name = $customer_name;
                 
                 if ($saleModel->create()) {
                    $new_sale_id = $db->lastInsertId();
                    if ($inventory->adjustStock($medicine_id, $quantity, 'out', "Sale #$new_sale_id to " . $customer_name)) {
                        $sale_ids[] = $new_sale_id;
                        $success_count++;
                    } else {
                        $errors[] = "Inventory failed for " . $medicine->name;
                    }
                 } else {
                     $errors[] = "Failed to record sale for " . $medicine->name;
                 }
            } else {
                $errors[] = "Medicine ID $medicine_id not found.";
            }
        }

        if (count($errors) > 0) {
            $db->rollBack();
            $error = "Transaction failed: " . implode(", ", $errors);
        } else {
            $db->commit();
            $ids_str = implode(',', $sale_ids);
            $message = "Transaction successful! Items sold: $success_count.";
            
            // Log sale activity
            try {
                $activityLog = new ActivityLog($db);
                $activityLog->log('SALE', "Sold $success_count items. Invoice IDs: $ids_str", 'sale', $sale_ids[0] ?? null);
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
                            <a href="invoice.php?ids=<?php echo $ids_str; ?>" target="_blank" class="btn btn-sm btn-success fw-bold"><i class="bi bi-printer me-1"></i>Print Combined Invoice</a>
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
                            <h5 class="mb-0 text-muted">Total Payable</h5>
                            <h3 class="mb-0 fw-bold text-primary" id="cartTotal">₹0.00</h3>
                        </div>
                        
                        <form method="POST" action="" id="checkoutForm">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="cart_data" id="cart_data_input">
                            
                            <div class="mb-3">
                                <label for="customer_name" class="form-label text-muted">Customer Name</label>
                                <input type="text" class="form-control" id="customer_name" name="customer_name" placeholder="Walk-in Client" required>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-success btn-lg" id="checkoutBtn" disabled>
                                    <i class="bi bi-check-circle-fill me-2"></i>Process Transaction
                                </button>
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

            totalEl.textContent = '₹' + grandTotal.toFixed(2);
            countEl.textContent = `${itemCount} items`;
            
            // Sync with hidden input
            formInput.value = JSON.stringify(cart);
        }
        
        // Handle checkout form submission to ensure cart is not empty
        document.getElementById('checkoutForm').addEventListener('submit', function(e) {
            if (cart.length === 0) {
                e.preventDefault();
                alert('Cart is empty!');
            }
        });
    </script>
</body>
</html>