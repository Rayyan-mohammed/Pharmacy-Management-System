<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();

$checks = [];

function push_check(&$checks, $name, $ok, $details) {
    $checks[] = ['name' => $name, 'ok' => $ok, 'details' => $details];
}

try {
    $db->query('SELECT 1');
    push_check($checks, 'Database connectivity', true, 'Connection is healthy.');
} catch (Exception $e) {
    push_check($checks, 'Database connectivity', false, $e->getMessage());
}

$requiredTables = ['medicines','medicine_batches','sales','purchases','purchase_items','purchase_payments','purchase_returns','alert_events','backup_runs'];
foreach ($requiredTables as $t) {
    try {
        $stmt = $db->query("SHOW TABLES LIKE '" . $t . "'");
        $ok = $stmt->rowCount() > 0;
        push_check($checks, 'Table check: ' . $t, $ok, $ok ? 'Present' : 'Missing');
    } catch (Exception $e) {
        push_check($checks, 'Table check: ' . $t, false, $e->getMessage());
    }
}

try {
    $orphans = $db->query("SELECT COUNT(*) FROM medicine_batches mb LEFT JOIN medicines m ON m.id = mb.medicine_id WHERE m.id IS NULL")->fetchColumn();
    push_check($checks, 'Orphan batch records', ((int)$orphans === 0), (int)$orphans . ' orphan rows');
} catch (Exception $e) {
    push_check($checks, 'Orphan batch records', false, $e->getMessage());
}

try {
    $neg = $db->query("SELECT COUNT(*) FROM medicines WHERE stock < 0")->fetchColumn();
    push_check($checks, 'Negative stock check', ((int)$neg === 0), (int)$neg . ' medicines with negative stock');
} catch (Exception $e) {
    push_check($checks, 'Negative stock check', false, $e->getMessage());
}

try {
    $dues = $db->query("SELECT COUNT(*) FROM purchases WHERE due_amount > total_amount")->fetchColumn();
    push_check($checks, 'Purchase due consistency', ((int)$dues === 0), (int)$dues . ' inconsistent bills');
} catch (Exception $e) {
    push_check($checks, 'Purchase due consistency', false, $e->getMessage());
}

try {
    $recentBackup = $db->query("SELECT file_name, created_at FROM backup_runs ORDER BY created_at DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $ok = !empty($recentBackup);
    push_check($checks, 'Recent backup run', $ok, $ok ? ($recentBackup['file_name'] . ' at ' . $recentBackup['created_at']) : 'No backup run logged yet');
} catch (Exception $e) {
    push_check($checks, 'Recent backup run', false, $e->getMessage());
}

$okCount = count(array_filter($checks, function($c){ return $c['ok']; }));
$totalCount = count($checks);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>System Health Checks - Pharmacy Pro</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../styles.css" rel="stylesheet"></head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container"><a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">Pharmacy Pro</a></div></nav>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold text-primary mb-0">System Health Checks</h2>
        <span class="badge bg-<?php echo $okCount === $totalCount ? 'success' : 'warning text-dark'; ?> fs-6"><?php echo $okCount; ?>/<?php echo $totalCount; ?> checks passed</span>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive"><table class="table table-sm table-striped mb-0 align-middle"><thead class="table-light"><tr><th class="px-3">Check</th><th>Status</th><th>Details</th></tr></thead><tbody>
            <?php foreach ($checks as $c): ?>
                <tr>
                    <td class="px-3"><?php echo htmlspecialchars($c['name']); ?></td>
                    <td><span class="badge bg-<?php echo $c['ok'] ? 'success' : 'danger'; ?>"><?php echo $c['ok'] ? 'PASS' : 'FAIL'; ?></span></td>
                    <td><?php echo htmlspecialchars($c['details']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
</body></html>
