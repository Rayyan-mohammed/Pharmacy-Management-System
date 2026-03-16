<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $alertId = (int)($_POST['alert_id'] ?? 0);
    if ($alertId > 0) {
        $ack = $db->prepare("UPDATE alert_events SET status = 'Acknowledged', acknowledged_by = :uid, acknowledged_at = NOW() WHERE id = :id");
        $ack->bindValue(':uid', (int)($_SESSION['currentUser']['user_id'] ?? 0), PDO::PARAM_INT);
        $ack->bindValue(':id', $alertId, PDO::PARAM_INT);
        $ack->execute();
        $message = 'Alert acknowledged.';
    }
}

function raise_alert_if_missing($db, $type, $entityId, $severity, $title, $details) {
    $chk = $db->prepare("SELECT id FROM alert_events WHERE alert_type = :type AND entity_id = :entity_id AND status = 'Open' LIMIT 1");
    $chk->bindValue(':type', $type);
    $chk->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
    $chk->execute();
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        $ins = $db->prepare("INSERT INTO alert_events (alert_type, severity, entity_type, entity_id, title, details, status) VALUES (:type, :severity, :entity_type, :entity_id, :title, :details, 'Open')");
        $ins->bindValue(':type', $type);
        $ins->bindValue(':severity', $severity);
        $ins->bindValue(':entity_type', 'medicine');
        $ins->bindValue(':entity_id', $entityId, PDO::PARAM_INT);
        $ins->bindValue(':title', $title);
        $ins->bindValue(':details', $details);
        $ins->execute();
    }
}

$lowStock = $db->query("SELECT id, name, stock, reorder_level FROM medicines WHERE stock <= reorder_level ORDER BY stock ASC")->fetchAll(PDO::FETCH_ASSOC);
foreach ($lowStock as $ls) {
    $sev = ((int)$ls['stock'] === 0) ? 'Critical' : (((int)$ls['stock'] <= max(1, (int)$ls['reorder_level'] / 2)) ? 'High' : 'Medium');
    raise_alert_if_missing($db, 'LOW_STOCK', (int)$ls['id'], $sev, 'Low stock: ' . $ls['name'], 'Stock ' . (int)$ls['stock'] . ', threshold ' . (int)$ls['reorder_level']);
}

$expiring = $db->query("SELECT m.id, m.name, MIN(mb.expiration_date) as nearest_expiry, DATEDIFF(MIN(mb.expiration_date), CURDATE()) as days_left
                        FROM medicine_batches mb JOIN medicines m ON m.id = mb.medicine_id
                        WHERE mb.quantity > 0 AND mb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                        GROUP BY m.id, m.name")->fetchAll(PDO::FETCH_ASSOC);
foreach ($expiring as $ex) {
    $sev = ((int)$ex['days_left'] <= 7) ? 'High' : 'Medium';
    raise_alert_if_missing($db, 'EXPIRY', (int)$ex['id'], $sev, 'Expiry warning: ' . $ex['name'], 'Nearest expiry in ' . (int)$ex['days_left'] . ' days (' . $ex['nearest_expiry'] . ')');
}

$dues = $db->query("SELECT p.id, p.invoice_number, s.name as supplier_name, p.due_amount, DATEDIFF(CURDATE(), p.purchase_date) as age_days
                    FROM purchases p JOIN suppliers s ON s.id = p.supplier_id
                    WHERE p.due_amount > 0")->fetchAll(PDO::FETCH_ASSOC);
foreach ($dues as $d) {
    $sev = ((int)$d['age_days'] > 90) ? 'Critical' : (((int)$d['age_days'] > 60) ? 'High' : 'Medium');
    $chk = $db->prepare("SELECT id FROM alert_events WHERE alert_type = 'SUPPLIER_DUE' AND entity_id = :id AND status = 'Open' LIMIT 1");
    $chk->bindValue(':id', (int)$d['id'], PDO::PARAM_INT);
    $chk->execute();
    if (!$chk->fetch(PDO::FETCH_ASSOC)) {
        $ins = $db->prepare("INSERT INTO alert_events (alert_type, severity, entity_type, entity_id, title, details, status) VALUES ('SUPPLIER_DUE', :severity, 'purchase', :entity_id, :title, :details, 'Open')");
        $ins->bindValue(':severity', $sev);
        $ins->bindValue(':entity_id', (int)$d['id'], PDO::PARAM_INT);
        $ins->bindValue(':title', 'Supplier due aging: ' . $d['invoice_number']);
        $ins->bindValue(':details', $d['supplier_name'] . ' due ₹' . number_format((float)$d['due_amount'],2) . ', age ' . (int)$d['age_days'] . ' days');
        $ins->execute();
    }
}

$alerts = $db->query("SELECT * FROM alert_events ORDER BY FIELD(status, 'Open', 'Acknowledged', 'Closed'), FIELD(severity, 'Critical', 'High', 'Medium', 'Low'), created_at DESC LIMIT 500")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Alert Center - PharmaFlow Pro</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"><link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet"><link href="../styles.css" rel="stylesheet"></head>
<body>
<nav class="navbar navbar-dark bg-primary"><div class="container"><a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro</a></div></nav>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h2 class="fw-bold text-primary mb-0"><i class="bi bi-bell-fill me-2"></i>Alert Escalation Center</h2>
        <a href="../api/export_csv.php?type=expiring&days=30" class="btn btn-outline-success">Export Expiry Action List</a>
    </div>
    <?php if ($message): ?><div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div><?php endif; ?>

    <div class="card border-0 shadow-sm">
        <div class="table-responsive"><table class="table table-sm table-striped align-middle mb-0"><thead class="table-light"><tr><th class="px-3">Type</th><th>Severity</th><th>Title</th><th>Details</th><th>Status</th><th>Created</th><th class="text-end px-3">Action</th></tr></thead><tbody>
        <?php if (empty($alerts)): ?><tr><td colspan="7" class="text-center text-muted py-3">No active alerts.</td></tr><?php else: foreach ($alerts as $a): ?>
            <tr>
                <td class="px-3"><?php echo htmlspecialchars($a['alert_type']); ?></td>
                <td><span class="badge bg-<?php echo $a['severity']==='Critical'?'danger':($a['severity']==='High'?'warning text-dark':'info'); ?>"><?php echo htmlspecialchars($a['severity']); ?></span></td>
                <td><?php echo htmlspecialchars($a['title']); ?></td>
                <td><small><?php echo htmlspecialchars($a['details'] ?? ''); ?></small></td>
                <td><?php echo htmlspecialchars($a['status']); ?></td>
                <td><?php echo date('d M Y H:i', strtotime($a['created_at'])); ?></td>
                <td class="text-end px-3">
                    <?php if ($a['status'] === 'Open'): ?>
                        <form method="POST" class="d-inline">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <input type="hidden" name="alert_id" value="<?php echo (int)$a['id']; ?>">
                            <button class="btn btn-sm btn-outline-primary">Acknowledge</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; endif; ?>
        </tbody></table></div>
    </div>
</div>
</body></html>

