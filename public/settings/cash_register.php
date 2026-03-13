<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();
$cashRegister = new CashRegister($db);

$businessDate = $_GET['date'] ?? date('Y-m-d');
$userId = (int)($_SESSION['currentUser']['user_id'] ?? 0);
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $businessDate = $_POST['business_date'] ?? date('Y-m-d');

    if ($action === 'set_opening') {
        $openingCash = (float)($_POST['opening_cash'] ?? 0);
        if ($cashRegister->upsertOpeningCash($businessDate, $openingCash, $userId)) {
            $message = 'Opening cash updated.';
            $messageType = 'success';
        } else {
            $message = 'Failed to update opening cash.';
            $messageType = 'danger';
        }
    }

    if ($action === 'add_movement') {
        $movementType = $_POST['movement_type'] ?? 'in';
        $amount = (float)($_POST['amount'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        if (!in_array($movementType, ['in', 'out'], true) || $amount <= 0 || $reason === '') {
            $message = 'Provide valid movement details.';
            $messageType = 'danger';
        } elseif ($cashRegister->addMovement($businessDate, $movementType, $amount, $reason, $userId)) {
            $message = 'Cash movement added.';
            $messageType = 'success';
        } else {
            $message = 'Failed to add movement.';
            $messageType = 'danger';
        }
    }

    if ($action === 'close_day') {
        $actualClosing = (float)($_POST['actual_closing'] ?? 0);
        $notes = trim($_POST['notes'] ?? '');
        if ($cashRegister->closeDay($businessDate, $actualClosing, $notes, $userId)) {
            $message = 'Day closed and variance recorded.';
            $messageType = 'success';
        } else {
            $message = 'Failed to close day.';
            $messageType = 'danger';
        }
    }
}

$summary = $cashRegister->buildSummary($businessDate);
$movements = $cashRegister->getMovements($businessDate);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cash Register - Pharmacy Pro</title>
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
        <h2 class="fw-bold text-primary mb-0"><i class="bi bi-cash-stack me-2"></i>End-of-Day Cash Register</h2>
        <form method="GET" class="d-flex gap-2">
            <input type="date" class="form-control" name="date" value="<?php echo htmlspecialchars($businessDate); ?>">
            <button class="btn btn-outline-primary" type="submit">Load</button>
        </form>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row g-3 mb-4">
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Opening Cash</small><h5>₹<?php echo number_format($summary['opening_cash'], 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Cash Sales</small><h5>₹<?php echo number_format($summary['cash_sales'], 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Cash In - Out</small><h5>₹<?php echo number_format($summary['cash_in'] - $summary['cash_out'], 2); ?></h5></div></div></div>
        <div class="col-md-3"><div class="card border-0 shadow-sm"><div class="card-body"><small class="text-muted">Expected Closing</small><h5>₹<?php echo number_format($summary['expected_closing'], 2); ?></h5></div></div></div>
    </div>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white">Set Opening Cash</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="set_opening">
                        <input type="hidden" name="business_date" value="<?php echo htmlspecialchars($businessDate); ?>">
                        <input type="number" step="0.01" min="0" class="form-control mb-2" name="opening_cash" placeholder="Opening cash">
                        <button class="btn btn-primary w-100">Save</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0 mb-3">
                <div class="card-header bg-white">Add Cash Movement</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="add_movement">
                        <input type="hidden" name="business_date" value="<?php echo htmlspecialchars($businessDate); ?>">
                        <select name="movement_type" class="form-select mb-2">
                            <option value="in">Cash In</option>
                            <option value="out">Cash Out</option>
                        </select>
                        <input type="number" step="0.01" min="0.01" class="form-control mb-2" name="amount" placeholder="Amount">
                        <input type="text" class="form-control mb-2" name="reason" placeholder="Reason">
                        <button class="btn btn-outline-primary w-100">Add</button>
                    </form>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">Close Day</div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="close_day">
                        <input type="hidden" name="business_date" value="<?php echo htmlspecialchars($businessDate); ?>">
                        <input type="number" step="0.01" min="0" class="form-control mb-2" name="actual_closing" placeholder="Actual closing cash" required>
                        <textarea class="form-control mb-2" rows="2" name="notes" placeholder="Notes (optional)"></textarea>
                        <button class="btn btn-success w-100">Close & Record Variance</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-8">
            <div class="card shadow-sm border-0">
                <div class="card-header bg-white">Cash Movements - <?php echo htmlspecialchars($businessDate); ?></div>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="px-3">Time</th>
                                <th>Type</th>
                                <th>Reason</th>
                                <th class="text-end px-3">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($movements)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-4">No movements logged.</td></tr>
                            <?php else: ?>
                                <?php foreach ($movements as $m): ?>
                                    <tr>
                                        <td class="px-3"><?php echo date('H:i', strtotime($m['created_at'])); ?></td>
                                        <td><span class="badge bg-<?php echo $m['movement_type'] === 'in' ? 'success' : 'danger'; ?>"><?php echo strtoupper($m['movement_type']); ?></span></td>
                                        <td><?php echo htmlspecialchars($m['reason']); ?></td>
                                        <td class="text-end px-3">₹<?php echo number_format((float)$m['amount'], 2); ?></td>
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
</body>
</html>
