<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    try {
        if (isset($_POST['add_branch'])) {
            $name = trim($_POST['branch_name'] ?? '');
            $code = strtoupper(trim($_POST['code'] ?? ''));
            $address = trim($_POST['address'] ?? '');
            if ($name === '' || $code === '') {
                throw new Exception('Branch name and code are required.');
            }
            $ins = $db->prepare("INSERT INTO branches (branch_name, code, address, is_active) VALUES (:branch_name, :code, :address, 1)");
            $ins->bindValue(':branch_name', $name);
            $ins->bindValue(':code', $code);
            $ins->bindValue(':address', $address ?: null);
            $ins->execute();
            $message = 'Branch added.';
            $messageType = 'success';
        }

        if (isset($_POST['toggle_branch'])) {
            $id = (int)($_POST['branch_id'] ?? 0);
            $toggle = $db->prepare("UPDATE branches SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END WHERE id = :id");
            $toggle->bindValue(':id', $id, PDO::PARAM_INT);
            $toggle->execute();
            $message = 'Branch status updated.';
            $messageType = 'success';
        }
    } catch (Exception $e) {
        $message = $e->getMessage();
        $messageType = 'danger';
    }
}

$branches = $db->query("SELECT * FROM branches ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
$transfers = $db->query("SELECT st.*, b1.branch_name as from_branch, b2.branch_name as to_branch FROM stock_transfers st JOIN branches b1 ON b1.id = st.from_branch_id JOIN branches b2 ON b2.id = st.to_branch_id ORDER BY st.id DESC LIMIT 100")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Branch Management - Pharmacy Pro</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../styles.css" rel="stylesheet"></head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container"><a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">Pharmacy Pro</a></div></nav>
<div class="container py-4">
    <h2 class="fw-bold text-primary mb-3">Branch Management (Multi-Store)</h2>
    <?php if ($message): ?><div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm"><div class="card-header bg-white"><h6 class="mb-0 text-primary">Add Branch</h6></div><div class="card-body">
                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                    <input type="hidden" name="add_branch" value="1">
                    <div class="mb-2"><label class="form-label">Branch Name</label><input type="text" class="form-control" name="branch_name" required></div>
                    <div class="mb-2"><label class="form-label">Code</label><input type="text" class="form-control" name="code" required></div>
                    <div class="mb-3"><label class="form-label">Address</label><textarea class="form-control" rows="2" name="address"></textarea></div>
                    <button class="btn btn-primary w-100">Create Branch</button>
                </form>
            </div></div>
        </div>

        <div class="col-lg-8">
            <div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white"><h6 class="mb-0 text-primary">Branches</h6></div>
                <div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead class="table-light"><tr><th class="px-3">ID</th><th>Name</th><th>Code</th><th>Status</th><th class="text-end px-3">Action</th></tr></thead><tbody>
                    <?php foreach ($branches as $b): ?>
                        <tr><td class="px-3"><?php echo (int)$b['id']; ?></td><td><?php echo htmlspecialchars($b['branch_name']); ?></td><td><?php echo htmlspecialchars($b['code']); ?></td><td><?php echo (int)$b['is_active'] ? 'Active' : 'Inactive'; ?></td><td class="text-end px-3"><form method="POST" class="d-inline"><input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>"><input type="hidden" name="toggle_branch" value="1"><input type="hidden" name="branch_id" value="<?php echo (int)$b['id']; ?>"><button class="btn btn-sm btn-outline-secondary">Toggle</button></form></td></tr>
                    <?php endforeach; ?>
                </tbody></table></div>
            </div>

            <div class="card border-0 shadow-sm"><div class="card-header bg-white"><h6 class="mb-0 text-primary">Recent Inter-Branch Transfers</h6></div>
                <div class="table-responsive"><table class="table table-sm table-striped mb-0"><thead class="table-light"><tr><th class="px-3">Transfer #</th><th>From</th><th>To</th><th>Date</th><th>Status</th></tr></thead><tbody>
                    <?php if (empty($transfers)): ?><tr><td colspan="5" class="text-center text-muted py-3">No transfers logged yet.</td></tr><?php else: foreach ($transfers as $t): ?>
                        <tr><td class="px-3"><?php echo htmlspecialchars($t['transfer_number']); ?></td><td><?php echo htmlspecialchars($t['from_branch']); ?></td><td><?php echo htmlspecialchars($t['to_branch']); ?></td><td><?php echo date('d M Y', strtotime($t['transfer_date'])); ?></td><td><?php echo htmlspecialchars($t['status']); ?></td></tr>
                    <?php endforeach; endif; ?>
                </tbody></table></div>
            </div>
        </div>
    </div>
</div>
</body></html>
