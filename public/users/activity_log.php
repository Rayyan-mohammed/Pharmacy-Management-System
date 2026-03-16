<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();
$activityLog = new ActivityLog($db);
$user = new User($db);

// Filters
$filters = [];
if (!empty($_GET['action'])) $filters['action'] = $_GET['action'];
if (!empty($_GET['user_id'])) $filters['user_id'] = $_GET['user_id'];
if (!empty($_GET['date_from'])) $filters['date_from'] = $_GET['date_from'];
if (!empty($_GET['date_to'])) $filters['date_to'] = $_GET['date_to'];

$logs = $activityLog->read(200, $filters);
$actions = $activityLog->getDistinctActions();
$allUsers = $user->readAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <div class="container py-4">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-1"><i class="bi bi-clock-history me-2"></i>Activity Log</h2>
                <p class="text-muted mb-0">Track all user actions across the system.</p>
            </div>
            <span class="badge bg-primary fs-6"><?php echo count($logs); ?> entries</span>
        </div>

        <!-- Filters -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3 align-items-end">
                    <div class="col-md-3">
                        <label class="form-label text-muted small">Action Type</label>
                        <select name="action" class="form-select">
                            <option value="">All Actions</option>
                            <?php foreach ($actions as $a): ?>
                                <option value="<?php echo htmlspecialchars($a); ?>" <?php echo ($filters['action'] ?? '')===$a?'selected':''; ?>>
                                    <?php echo htmlspecialchars($a); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label text-muted small">User</label>
                        <select name="user_id" class="form-select">
                            <option value="">All Users</option>
                            <?php foreach ($allUsers as $u): ?>
                                <option value="<?php echo $u['user_id']; ?>" <?php echo (($filters['user_id'] ?? '')==$u['user_id'])?'selected':''; ?>>
                                    <?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small">From</label>
                        <input type="date" name="date_from" class="form-control" value="<?php echo htmlspecialchars($filters['date_from'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2">
                        <label class="form-label text-muted small">To</label>
                        <input type="date" name="date_to" class="form-control" value="<?php echo htmlspecialchars($filters['date_to'] ?? ''); ?>">
                    </div>
                    <div class="col-md-2 d-flex gap-2">
                        <button type="submit" class="btn btn-primary flex-grow-1"><i class="bi bi-funnel"></i> Filter</button>
                        <a href="activity_log.php" class="btn btn-outline-secondary"><i class="bi bi-x-lg"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Log Table -->
        <div class="card border-0 shadow">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="bg-light">
                            <tr>
                                <th class="px-4 py-3">Time</th>
                                <th class="py-3">User</th>
                                <th class="py-3">Action</th>
                                <th class="py-3">Description</th>
                                <th class="py-3">Entity</th>
                                <th class="py-3 px-4">IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($logs)): ?>
                                <tr><td colspan="6" class="text-center text-muted py-5">No activity logs found.</td></tr>
                            <?php else: ?>
                                <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="px-4">
                                        <div class="fw-bold"><?php echo date('d M Y', strtotime($log['created_at'])); ?></div>
                                        <small class="text-muted"><?php echo date('h:i:s A', strtotime($log['created_at'])); ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-bold"><?php echo htmlspecialchars($log['user_name']); ?></div>
                                    </td>
                                    <td>
                                        <?php
                                        $actionColors = [
                                            'LOGIN' => 'primary', 'LOGOUT' => 'secondary',
                                            'CREATE' => 'success', 'UPDATE' => 'info',
                                            'DELETE' => 'danger', 'SALE' => 'warning',
                                            'RETURN' => 'dark', 'STOCK' => 'primary',
                                        ];
                                        $color = 'secondary';
                                        foreach ($actionColors as $key => $col) {
                                            if (stripos($log['action'], $key) !== false) { $color = $col; break; }
                                        }
                                        ?>
                                        <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($log['action']); ?></span>
                                    </td>
                                    <td><small><?php echo htmlspecialchars($log['description']); ?></small></td>
                                    <td>
                                        <?php if ($log['entity_type']): ?>
                                            <small class="text-muted"><?php echo htmlspecialchars($log['entity_type']); ?> #<?php echo $log['entity_id']; ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">-</small>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4"><code class="small"><?php echo htmlspecialchars($log['ip_address'] ?? '-'); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

