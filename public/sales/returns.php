<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$returns = new Returns($db);

$message = '';
$messageType = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $processedBy = (int)$_SESSION['currentUser']['user_id'];

    if ($action === 'create_return') {
        $saleId = (int)$_POST['sale_id'];
        $quantity = (int)$_POST['quantity'];
        $reason = trim($_POST['reason'] ?? '');

        if (empty($saleId) || $quantity <= 0 || empty($reason)) {
            $message = 'Please fill in all required fields.';
            $messageType = 'danger';
        } else {
            $sale = $returns->getSaleDetails($saleId);
            if (!$sale) {
                $message = 'Sale not found.';
                $messageType = 'danger';
            } else {
                $alreadyReturned = $returns->getReturnedQtyForSale($saleId);
                $maxReturnable = $sale['quantity'] - $alreadyReturned;
                if ($quantity > $maxReturnable) {
                    $message = "Cannot return more than $maxReturnable units (already returned: $alreadyReturned).";
                    $messageType = 'danger';
                } else {
                    $unitPrice = $sale['unit_price'] ?? ($sale['total_price'] / $sale['quantity']);
                    $refundAmount = $unitPrice * $quantity;
                    $originalPaymentMethod = $sale['payment_method'] ?? 'Cash';
                    if ($returns->create($saleId, $sale['medicine_id'], $quantity, $reason, $refundAmount, $originalPaymentMethod)) {
                        $message = 'Return request created successfully.';
                        $messageType = 'success';
                        try { $al = new ActivityLog($db); $al->log('RETURN', "Created return for sale #{$saleId}: {$quantity} units, refund ₹{$refundAmount}", 'sale', $saleId); } catch(Exception $e) {}
                    } else {
                        $message = 'Failed to create return request.';
                        $messageType = 'danger';
                    }
                }
            }
        }
    } elseif ($action === 'approve') {
        $returnId = (int)$_POST['return_id'];
        $refundMethod = $_POST['refund_method'] ?? '';
        $refundReference = trim($_POST['refund_reference'] ?? '');
        if ($returns->approve($returnId, $processedBy, $refundMethod, $refundReference)) {
            $message = 'Return approved. Stock has been restored.';
            $messageType = 'success';
            try { $al = new ActivityLog($db); $al->log('RETURN', "Approved return #{$returnId}", 'return', $returnId); } catch(Exception $e) {}
        } else {
            $message = 'Failed to approve return.';
            $messageType = 'danger';
        }
    } elseif ($action === 'reject') {
        $returnId = (int)$_POST['return_id'];
        if ($returns->reject($returnId, $processedBy)) {
            $message = 'Return rejected.';
            $messageType = 'warning';
            try { $al = new ActivityLog($db); $al->log('RETURN', "Rejected return #{$returnId}", 'return', $returnId); } catch(Exception $e) {}
        } else {
            $message = 'Failed to reject return.';
            $messageType = 'danger';
        }
    }
}

$statusFilter = $_GET['status'] ?? '';
$allReturns = $returns->readAll($statusFilter ?: null);
$stats = $returns->getStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Returns & Refunds - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-arrow-return-left me-2"></i>Returns & Refunds</h2>
                <p class="text-muted mb-0">Process returns, approve refunds, and track return history.</p>
            </div>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newReturnModal">
                <i class="bi bi-plus-lg me-2"></i>New Return
            </button>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
                <?php echo htmlspecialchars($message); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stats Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-warning">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Pending</h6>
                        <h3 class="mb-0 fw-bold text-warning"><?php echo $stats['pending'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-success">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Approved</h6>
                        <h3 class="mb-0 fw-bold text-success"><?php echo $stats['approved'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-danger">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Rejected</h6>
                        <h3 class="mb-0 fw-bold text-danger"><?php echo $stats['rejected'] ?? 0; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm border-start border-4 border-info">
                    <div class="card-body py-3">
                        <h6 class="text-muted small mb-1">Total Refunded</h6>
                        <h3 class="mb-0 fw-bold text-info">₹<?php echo number_format($stats['total_refunded'] ?? 0, 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter Tabs -->
        <ul class="nav nav-pills mb-3">
            <li class="nav-item"><a class="nav-link <?php echo !$statusFilter?'active':''; ?>" href="returns.php">All</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $statusFilter==='pending'?'active':''; ?>" href="?status=pending">Pending</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $statusFilter==='approved'?'active':''; ?>" href="?status=approved">Approved</a></li>
            <li class="nav-item"><a class="nav-link <?php echo $statusFilter==='rejected'?'active':''; ?>" href="?status=rejected">Rejected</a></li>
        </ul>

        <!-- Returns Table -->
        <div class="card border-0 shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">#</th>
                                <th class="py-3">Date</th>
                                <th class="py-3">Medicine</th>
                                <th class="py-3">Customer</th>
                                <th class="py-3 text-center">Qty</th>
                                <th class="py-3 text-end">Refund</th>
                                <th class="py-3">Payment Trail</th>
                                <th class="py-3">Reason</th>
                                <th class="py-3 text-center">Status</th>
                                <th class="py-3 text-end px-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($allReturns)): ?>
                                <tr><td colspan="10" class="text-center text-muted py-5">No returns found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($allReturns as $r): ?>
                                <tr>
                                    <td class="px-4 fw-bold">#<?php echo $r['id']; ?></td>
                                    <td>
                                        <div><?php echo date('d M Y', strtotime($r['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i A', strtotime($r['created_at'])); ?></small>
                                    </td>
                                    <td class="fw-bold text-primary"><?php echo htmlspecialchars($r['medicine_name']); ?></td>
                                    <td><?php echo htmlspecialchars($r['customer_name'] ?? '-'); ?></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border"><?php echo $r['quantity']; ?></span></td>
                                    <td class="text-end fw-bold">₹<?php echo number_format($r['refund_amount'], 2); ?></td>
                                    <td>
                                        <small class="d-block">Original: <?php echo htmlspecialchars($r['original_payment_method'] ?: ($r['sale_payment_method'] ?? 'Cash')); ?></small>
                                        <?php if (!empty($r['refund_method'])): ?>
                                            <small class="d-block text-success">Refunded via: <?php echo htmlspecialchars($r['refund_method']); ?></small>
                                        <?php endif; ?>
                                        <?php if (!empty($r['refund_reference'])): ?>
                                            <small class="d-block text-muted">Ref: <?php echo htmlspecialchars($r['refund_reference']); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><small><?php echo htmlspecialchars(mb_strimwidth($r['reason'], 0, 40, '...')); ?></small></td>
                                    <td class="text-center">
                                        <?php
                                        $sBadge = ['pending'=>'warning','approved'=>'success','rejected'=>'danger'][$r['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?php echo $sBadge; ?>"><?php echo ucfirst($r['status']); ?></span>
                                    </td>
                                    <td class="text-end px-4">
                                        <?php if ($r['status'] === 'pending'): ?>
                                            <form method="POST" class="d-inline-flex align-items-center gap-1" onsubmit="return confirm('Approve this return and restock?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="approve">
                                                <input type="hidden" name="return_id" value="<?php echo $r['id']; ?>">
                                                <select name="refund_method" class="form-select form-select-sm" style="width:90px;">
                                                    <option value="Cash" <?php echo ($r['original_payment_method'] ?? '') === 'Cash' ? 'selected' : ''; ?>>Cash</option>
                                                    <option value="Card" <?php echo ($r['original_payment_method'] ?? '') === 'Card' ? 'selected' : ''; ?>>Card</option>
                                                    <option value="UPI" <?php echo ($r['original_payment_method'] ?? '') === 'UPI' ? 'selected' : ''; ?>>UPI</option>
                                                </select>
                                                <input type="text" name="refund_reference" class="form-control form-control-sm" style="width:110px;" placeholder="Ref (opt)">
                                                <button class="btn btn-sm btn-success"><i class="bi bi-check-lg"></i></button>
                                            </form>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Reject this return?')">
                                                <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                <input type="hidden" name="action" value="reject">
                                                <input type="hidden" name="return_id" value="<?php echo $r['id']; ?>">
                                                <button class="btn btn-sm btn-danger"><i class="bi bi-x-lg"></i></button>
                                            </form>
                                        <?php else: ?>
                                            <?php if ($r['processor_first']): ?>
                                                <small class="text-muted">by <?php echo htmlspecialchars($r['processor_first']); ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- New Return Modal -->
    <div class="modal fade" id="newReturnModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="action" value="create_return">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="bi bi-arrow-return-left me-2"></i>Process New Return</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Sale ID *</label>
                            <input type="number" name="sale_id" class="form-control" placeholder="Enter the Sale/Invoice ID" required min="1">
                            <small class="text-muted">Find Sale ID from Sales Records page.</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity to Return *</label>
                            <input type="number" name="quantity" class="form-control" required min="1">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Reason for Return *</label>
                            <textarea name="reason" class="form-control" rows="3" required placeholder="e.g., Defective product, wrong medicine dispensed, customer request..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Submit Return</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
