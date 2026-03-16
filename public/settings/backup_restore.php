<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();

$message = '';
$messageType = '';

function build_insert_sql($db, $table) {
    $rows = $db->query("SELECT * FROM `{$table}`")->fetchAll(PDO::FETCH_ASSOC);
    if (empty($rows)) {
        return "-- No rows in {$table}\n";
    }

    $lines = [];
    foreach ($rows as $row) {
        $values = [];
        foreach ($row as $v) {
            if ($v === null) {
                $values[] = 'NULL';
            } else {
                $values[] = $db->quote($v);
            }
        }
        $lines[] = '(' . implode(', ', $values) . ')';
    }

    $columns = array_map(function($c) { return "`{$c}`"; }, array_keys($rows[0]));
    return "INSERT INTO `{$table}` (" . implode(', ', $columns) . ") VALUES\n" . implode(",\n", $lines) . ";\n\n";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf_token();
    $action = $_POST['action'] ?? '';
    $sectionPassword = $_POST['section_password'] ?? '';

    if (!defined('BACKUP_RESTORE_PASSWORD') || !hash_equals(BACKUP_RESTORE_PASSWORD, (string)$sectionPassword)) {
        $message = 'Invalid Backup & Restore password.';
        $messageType = 'danger';
        $action = '';
    }

    if ($action === 'backup') {
        try {
            $tables = $db->query("SHOW TABLES")->fetchAll(PDO::FETCH_NUM);
            $filename = 'pharmacy_backup_' . date('Ymd_His') . '.sql';
            $content = "-- PharmaFlow Pro SQL Backup\n";
            $content .= "-- Generated: " . date('Y-m-d H:i:s') . "\n\n";
            $content .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

            foreach ($tables as $t) {
                $table = $t[0];
                $createRow = $db->query("SHOW CREATE TABLE `{$table}`")->fetch(PDO::FETCH_ASSOC);
                $createSql = $createRow['Create Table'] ?? array_values($createRow)[1] ?? null;
                if (!$createSql) {
                    continue;
                }
                $content .= "DROP TABLE IF EXISTS `{$table}`;\n";
                $content .= $createSql . ";\n\n";
                $content .= build_insert_sql($db, $table);
            }

            $content .= "SET FOREIGN_KEY_CHECKS = 1;\n";

            try {
                $checksum = hash('sha256', $content);
                $ins = $db->prepare("INSERT INTO backup_runs (file_name, file_size, checksum_sha256, run_status, notes, created_by) VALUES (:file_name, :file_size, :checksum, 'SUCCESS', 'Manual download backup', :created_by)");
                $ins->bindValue(':file_name', $filename);
                $ins->bindValue(':file_size', strlen($content), PDO::PARAM_INT);
                $ins->bindValue(':checksum', $checksum);
                $ins->bindValue(':created_by', (int)($_SESSION['currentUser']['user_id'] ?? 0), PDO::PARAM_INT);
                $ins->execute();
            } catch (Exception $e) {}

            header('Content-Type: application/sql');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $content;
            exit;
        } catch (Exception $e) {
            $message = 'Backup failed: ' . $e->getMessage();
            $messageType = 'danger';
            try {
                $ins = $db->prepare("INSERT INTO backup_runs (file_name, file_size, checksum_sha256, run_status, notes, created_by) VALUES (:file_name, 0, NULL, 'FAILED', :notes, :created_by)");
                $ins->bindValue(':file_name', 'pharmacy_backup_' . date('Ymd_His') . '.sql');
                $ins->bindValue(':notes', $e->getMessage());
                $ins->bindValue(':created_by', (int)($_SESSION['currentUser']['user_id'] ?? 0), PDO::PARAM_INT);
                $ins->execute();
            } catch (Exception $ee) {}
        }
    }

    if ($action === 'restore') {
        $confirm = $_POST['confirm_restore'] ?? '';
        if ($confirm !== 'YES') {
            $message = 'Restore blocked. Type YES to confirm restore.';
            $messageType = 'danger';
        } elseif (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            $message = 'Please upload a valid .sql file.';
            $messageType = 'danger';
        } else {
            $ext = strtolower(pathinfo($_FILES['sql_file']['name'], PATHINFO_EXTENSION));
            if ($ext !== 'sql') {
                $message = 'Only .sql files are allowed.';
                $messageType = 'danger';
            } else {
                $sqlContent = file_get_contents($_FILES['sql_file']['tmp_name']);
                if ($sqlContent === false || trim($sqlContent) === '') {
                    $message = 'Uploaded SQL file is empty.';
                    $messageType = 'danger';
                } else {
                    try {
                        $db->beginTransaction();
                        $statements = preg_split('/;\s*\n/', $sqlContent);
                        foreach ($statements as $statement) {
                            $statement = trim($statement);
                            if ($statement === '' || strpos($statement, '--') === 0) {
                                continue;
                            }
                            $db->exec($statement);
                        }
                        $db->commit();
                        $message = 'Database restore completed successfully.';
                        $messageType = 'success';
                        try {
                            $ins = $db->prepare("INSERT INTO backup_runs (file_name, file_size, checksum_sha256, run_status, notes, created_by) VALUES (:file_name, :file_size, :checksum, 'SUCCESS', 'Manual restore', :created_by)");
                            $ins->bindValue(':file_name', $_FILES['sql_file']['name'] ?? ('restore_' . date('Ymd_His') . '.sql'));
                            $ins->bindValue(':file_size', (int)($_FILES['sql_file']['size'] ?? 0), PDO::PARAM_INT);
                            $ins->bindValue(':checksum', hash('sha256', $sqlContent));
                            $ins->bindValue(':created_by', (int)($_SESSION['currentUser']['user_id'] ?? 0), PDO::PARAM_INT);
                            $ins->execute();
                        } catch (Exception $ee) {}
                    } catch (Exception $e) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        $message = 'Restore failed: ' . $e->getMessage();
                        $messageType = 'danger';
                        try {
                            $ins = $db->prepare("INSERT INTO backup_runs (file_name, file_size, checksum_sha256, run_status, notes, created_by) VALUES (:file_name, :file_size, NULL, 'FAILED', :notes, :created_by)");
                            $ins->bindValue(':file_name', $_FILES['sql_file']['name'] ?? ('restore_' . date('Ymd_His') . '.sql'));
                            $ins->bindValue(':file_size', (int)($_FILES['sql_file']['size'] ?? 0), PDO::PARAM_INT);
                            $ins->bindValue(':notes', $e->getMessage());
                            $ins->bindValue(':created_by', (int)($_SESSION['currentUser']['user_id'] ?? 0), PDO::PARAM_INT);
                            $ins->execute();
                        } catch (Exception $ee) {}
                    }
                }
            }
        }
    }
}

$recentRuns = [];
try {
    $recentRuns = $db->query("SELECT file_name, file_size, run_status, created_at FROM backup_runs ORDER BY created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup & Restore - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-dark bg-primary">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php"><i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro</a>
    </div>
</nav>

<div class="container py-4">
    <h2 class="fw-bold text-primary mb-4"><i class="bi bi-database me-2"></i>Backup & Restore</h2>

    <?php if ($message): ?>
        <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <div class="row g-4">
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-primary">One-click SQL Backup</h5></div>
                <div class="card-body">
                    <p class="text-muted">Download a full SQL snapshot of the current database.</p>
                    <form method="POST">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="backup">
                        <div class="mb-2">
                            <label class="form-label">Section Password</label>
                            <input type="password" class="form-control" name="section_password" placeholder="Enter backup password" required>
                        </div>
                        <button class="btn btn-success"><i class="bi bi-download me-1"></i>Download Backup (.sql)</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white"><h5 class="mb-0 text-danger">Restore Database</h5></div>
                <div class="card-body">
                    <p class="text-muted">Restore from an SQL file. This can overwrite existing data.</p>
                    <form method="POST" enctype="multipart/form-data" onsubmit="return confirm('Restore will modify database data. Continue?');">
                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                        <input type="hidden" name="action" value="restore">
                        <div class="mb-2">
                            <label class="form-label">Section Password</label>
                            <input type="password" class="form-control" name="section_password" placeholder="Enter restore password" required>
                        </div>
                        <div class="mb-2">
                            <input type="file" class="form-control" name="sql_file" accept=".sql" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Safety confirmation: type YES</label>
                            <input type="text" class="form-control" name="confirm_restore" placeholder="YES" required>
                        </div>
                        <button class="btn btn-danger"><i class="bi bi-upload me-1"></i>Restore Backup</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-12">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary">Backup Run History</h5>
                    <span class="small text-muted">Scheduler: run <code>C:\xampp\php\php.exe database/setup/scheduled_backup.php</code></span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-striped mb-0">
                        <thead class="table-light"><tr><th class="px-3">Time</th><th>File</th><th class="text-end">Size (bytes)</th><th class="text-center px-3">Status</th></tr></thead>
                        <tbody>
                            <?php if (empty($recentRuns)): ?>
                                <tr><td colspan="4" class="text-center text-muted py-3">No backup runs logged yet.</td></tr>
                            <?php else: foreach ($recentRuns as $r): ?>
                                <tr>
                                    <td class="px-3"><?php echo date('d M Y H:i', strtotime($r['created_at'])); ?></td>
                                    <td><?php echo htmlspecialchars($r['file_name']); ?></td>
                                    <td class="text-end"><?php echo number_format((float)$r['file_size']); ?></td>
                                    <td class="text-center px-3"><span class="badge bg-<?php echo $r['run_status'] === 'SUCCESS' ? 'success' : 'danger'; ?>"><?php echo htmlspecialchars($r['run_status']); ?></span></td>
                                </tr>
                            <?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>

