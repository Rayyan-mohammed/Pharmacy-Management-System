<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist', 'Staff']);

$database = new Database();
$db = $database->getConnection();
$prescription = new Prescription($db);
$medicine = new Medicine($db);

$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Handle form submissions
if($_POST) {
    verify_csrf_token();
    if(isset($_POST['add_prescription'])) {
        // Set prescription property values
        $prescription->patient_name = $_POST['patient_name'];
        $prescription->prescription_date = $_POST['prescription_date'];
        $prescription->doctor_name = $_POST['doctor_name'];
        $prescription->notes = $_POST['notes'] ?? '';
        $prescription->status = 'pending';

        // Create the prescription
        if($prescription_id = $prescription->create()) {
            // Add medicines to prescription
            foreach($_POST['medicines'] as $index => $medicine_id) {
                if($medicine_id && $_POST['quantities'][$index]) {
                    $prescription->id = $prescription_id;
                    $prescription->addMedicine(
                        $medicine_id, 
                        $_POST['quantities'][$index],
                        $_POST['dosages'][$index] ?? '',
                        $_POST['instructions'][$index] ?? ''
                    );
                }
            }
            $message = "Prescription was created successfully.";
            $action = 'list';
            try { $al = new ActivityLog($db); $al->log('CREATE', "Created prescription for {$_POST['patient_name']} (Dr. {$_POST['doctor_name']})", 'prescription', $prescription_id); } catch(Exception $e) {}
        } else {
            $message = "Unable to create prescription.";
        }
    }
    elseif(isset($_POST['update_status'])) {
        $prescription->id = $_POST['id'];
        $status = $_POST['status'];
        $currentUser = $_SESSION['currentUser'] ?? [];
        $changedBy = trim(($currentUser['first_name'] ?? '') . ' ' . ($currentUser['last_name'] ?? ''));
        if(!$changedBy) {
            $changedBy = $currentUser['username'] ?? 'system';
        }

        if($prescription->updateStatus($status, $changedBy)) {
            $message = "Prescription status was updated to " . ucfirst($status) . ".";
            $action = 'list';
            try { $al = new ActivityLog($db); $al->log('UPDATE', "Prescription #{$_POST['id']} status changed to {$status}", 'prescription', (int)$_POST['id']); } catch(Exception $e) {}
        } else {
            $message = "Unable to update prescription status.";
        }
    }
    elseif(isset($_POST['delete_prescription'])) {
        $prescription->id = $_POST['id'];
        if($prescription->delete()) {
            $message = "Prescription was deleted successfully.";
            $action = 'list';
            try { $al = new ActivityLog($db); $al->log('DELETE', "Deleted prescription #{$_POST['id']}", 'prescription', (int)$_POST['id']); } catch(Exception $e) {}
        } else {
            $message = "Unable to delete prescription.";
        }
    }
}

// Get prescriptions list
if($search) {
    $prescriptions_list = $prescription->search($search);
} else {
    $prescriptions_list = $prescription->read();
}

// Get medicines list for dropdown
$medicines_list = $medicine->read();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Prescriptions - PharmaFlow Pro</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../styles.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>PharmaFlow Pro
            </a>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container py-4">
        <?php if($message): ?>
            <div class="alert alert-info alert-dismissible fade show shadow-sm">
                <i class="bi bi-info-circle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if($action == 'list'): ?>
            <div class="card shadow-sm">
                <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 text-primary">Prescription Records</h5>
                    <a href="?action=add" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i>New Entry</a>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-9">
                            <div class="input-group">
                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                <input type="text" class="form-control" name="search" placeholder="Search by patient or doctor name..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <button type="submit" class="btn btn-outline-primary w-100">Find Records</button>
                        </div>
                    </form>

                    <!-- Prescriptions Table -->
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light">
                                <tr>
                                    <th class="ps-4">Date</th>
                                    <th>Patient</th>
                                    <th>Doctor</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-end pe-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $prescriptions_list->fetch(PDO::FETCH_ASSOC)): 
                                    $status = strtolower($row['status']);
                                ?>
                                    <tr>
                                        <td class="ps-4 text-secondary"><i class="bi bi-calendar-event me-2"></i><?php echo date('d M Y', strtotime($row['prescription_date'])); ?></td>
                                        <td class="fw-bold text-dark"><?php echo htmlspecialchars($row['patient_name']); ?></td>
                                        <td>Dr. <?php echo htmlspecialchars($row['doctor_name']); ?></td>
                                        <td class="text-center">
                                            <span class="badge rounded-pill px-3 <?php 
                                                echo $status == 'pending' ? 'bg-warning text-dark' : 
                                                    ($status == 'filled' ? 'bg-success' : 'bg-danger'); 
                                            ?>">
                                                <?php echo ucfirst($status); ?>
                                            </span>
                                        </td>
                                        <td class="text-end pe-4">
                                            <div class="btn-group">
                                                <a href="?action=view&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-info" title="View Details"><i class="bi bi-eye"></i></a>
                                                <?php if($status == 'pending'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="status" value="filled">
                                                        <button type="submit" name="update_status" class="btn btn-sm btn-outline-success" title="Mark Filled"><i class="bi bi-check-lg"></i></button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Reject this prescription?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <input type="hidden" name="status" value="rejected">
                                                        <button type="submit" name="update_status" class="btn btn-sm btn-outline-warning" title="Mark Rejected"><i class="bi bi-x-lg"></i></button>
                                                    </form>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure?');">
                                                        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                        <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                        <button type="submit" name="delete_prescription" class="btn btn-sm btn-outline-danger" title="Delete"><i class="bi bi-trash"></i></button>
                                                    </form>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif($action == 'add'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3">
                            <h4 class="mb-0 text-primary">New Prescription Entry</h4>
                        </div>
                        <div class="card-body p-4">
                            <form method="POST" action="">
                                <div class="row g-3 mb-4">
                                    <div class="col-md-4">
                                        <label for="patient_name" class="form-label text-muted">Patient Name</label>
                                        <input type="text" class="form-control" id="patient_name" name="patient_name" required placeholder="Full Name">
                                    </div>
                                    <div class="col-md-4">
                                        <label for="doctor_name" class="form-label text-muted">Doctor Name</label>
                                        <div class="input-group">
                                            <span class="input-group-text">Dr.</span>
                                            <input type="text" class="form-control" id="doctor_name" name="doctor_name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <label for="prescription_date" class="form-label text-muted">Date Prescribed</label>
                                        <input type="date" class="form-control" id="prescription_date" name="prescription_date" required value="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>

                                <div class="card bg-light border-0 mb-4">
                                    <div class="card-body">
                                        <h6 class="card-title text-secondary mb-3">Prescribed Medicines</h6>
                                        <div id="medicine-rows">
                                            <!-- Dynamic Rows -->
                                        </div>
                                        <button type="button" class="btn btn-outline-primary btn-sm mt-2" id="add-medicine-btn">
                                            <i class="bi bi-plus-circle me-1"></i> Add Another Medicine
                                        </button>
                                    </div>
                                </div>

                                <div class="mb-4">
                                     <label for="notes" class="form-label text-muted">Additional Notes (Optional)</label>
                                     <textarea class="form-control" id="notes" name="notes" rows="2"></textarea>
                                </div>

                                <div class="d-flex justify-content-between align-items-center pt-3 border-top">
                                    <a href="?action=list" class="btn btn-outline-secondary">Cancel</a>
                                    <button type="submit" name="add_prescription" class="btn btn-primary px-4"><i class="bi bi-save me-2"></i>Save Prescription</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

        <?php elseif($action == 'view'): ?>
            <?php
            // Re-query needed for view context
            $prescription->id = $_GET['id'];
            if (!$prescription->readOne()) {
                echo "<div class='alert alert-danger'>Prescription not found.</div>";
            } else {
                $medicines_stmt = $prescription->getMedicines(); // Assuming this returns a statement
            ?>
            <?php $status_view = strtolower($prescription->status); $status_logs = $prescription->getStatusLogs(5); ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card shadow">
                        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                            <h5 class="mb-0 text-primary">Rx #<?php echo str_pad($prescription->id, 5, '0', STR_PAD_LEFT); ?></h5>
                            <span class="badge rounded-pill <?php 
                                echo $status_view == 'pending' ? 'bg-warning text-dark' : 
                                    ($status_view == 'filled' ? 'bg-success' : 'bg-danger'); 
                            ?> fs-6 px-3">
                                <?php echo ucfirst($status_view); ?>
                            </span>
                        </div>
                        <div class="card-body p-4">
                            <div class="row mb-5">
                                <div class="col-sm-6">
                                    <h6 class="text-muted text-uppercase small">Patient</h6>
                                    <p class="fs-5 fw-bold mb-0"><?php echo htmlspecialchars($prescription->patient_name); ?></p>
                                </div>
                                <div class="col-sm-6 text-sm-end">
                                    <h6 class="text-muted text-uppercase small">Prescribed By</h6>
                                    <p class="fs-5 mb-0">Dr. <?php echo htmlspecialchars($prescription->doctor_name); ?></p>
                                    <small class="text-muted"><?php echo date('d M Y', strtotime($prescription->prescription_date)); ?></small>
                                </div>
                            </div>

                            <div class="table-responsive mb-4">
                                <table class="table table-bordered">
                                    <thead class="bg-light">
                                        <tr>
                                            <th>Medicine</th>
                                            <th class="text-center">Qty</th>
                                            <th>Dosage</th>
                                            <th>Instructions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while ($row = $medicines_stmt->fetch(PDO::FETCH_ASSOC)): ?>
                                            <tr>
                                                <td class="fw-bold"><?php echo htmlspecialchars($row['medicine_name']); ?></td>
                                                <td class="text-center"><?php echo $row['quantity']; ?></td>
                                                <td><span class="badge bg-light text-dark border"><?php echo htmlspecialchars($row['dosage'] ?? '-'); ?></span></td>
                                                <td class="text-muted fst-italic"><?php echo htmlspecialchars($row['instructions'] ?? '-'); ?></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <?php if(!empty($prescription->notes)): ?>
                                <div class="alert alert-light border">
                                    <strong>Notes:</strong> <?php echo nl2br(htmlspecialchars($prescription->notes)); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Status actions (only when pending) -->
                            <?php if($status_view == 'pending'): ?>
                            <div class="d-flex gap-2 mb-3">
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?php echo $prescription->id; ?>">
                                    <input type="hidden" name="status" value="filled">
                                    <button type="submit" name="update_status" class="btn btn-success">
                                        <i class="bi bi-check-lg me-1"></i>Mark as Filled
                                    </button>
                                </form>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Reject this prescription?');">
                                    <input type="hidden" name="id" value="<?php echo $prescription->id; ?>">
                                    <input type="hidden" name="status" value="rejected">
                                    <button type="submit" name="update_status" class="btn btn-warning text-dark">
                                        <i class="bi bi-x-lg me-1"></i>Reject
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>

                            <!-- Status log -->
                            <div class="mb-4">
                                <h6 class="text-muted text-uppercase small">Status History</h6>
                                <?php if($status_logs && $status_logs->rowCount() > 0): ?>
                                    <ul class="list-group list-group-flush">
                                        <?php while($log = $status_logs->fetch(PDO::FETCH_ASSOC)): ?>
                                            <li class="list-group-item px-0 d-flex justify-content-between align-items-center">
                                                <div>
                                                    <span class="badge bg-light text-dark border me-2"><?php echo ucfirst($log['status']); ?></span>
                                                    <span class="text-muted small">by <?php echo htmlspecialchars($log['changed_by'] ?? 'system'); ?></span>
                                                </div>
                                                <small class="text-muted"><?php echo date('d M Y, h:i A', strtotime($log['changed_at'])); ?></small>
                                            </li>
                                        <?php endwhile; ?>
                                    </ul>
                                <?php else: ?>
                                    <p class="text-muted small">No history available.</p>
                                <?php endif; ?>
                            </div>

                            <div class="d-flex justify-content-between mt-4 pt-3 border-top">
                                <a href="?action=list" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back to List</a>
                                <button onclick="window.print()" class="btn btn-outline-primary"><i class="bi bi-printer me-1"></i> Print</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Custom JS for Dynamic Rows -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if($action == 'add'): ?>
                const container = document.getElementById('medicine-rows');
                const btn = document.getElementById('add-medicine-btn');
                // Fetch medicines data from PHP
                const medicinesList = <?php 
                    $medicines_list->execute(); 
                    echo json_encode($medicines_list->fetchAll(PDO::FETCH_ASSOC)); 
                ?>;

                function addRow() {
                    const div = document.createElement('div');
                    div.className = 'row g-2 mb-2 align-items-end medicine-row';
                    div.innerHTML = `
                        <div class="col-md-4">
                            <label class="small text-muted mb-1">Medicine</label>
                            <select class="form-select" name="medicines[]" required>
                                <option value="">Select...</option>
                                ${medicinesList.map(m => `<option value="${m.id}">${m.name}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-2">
                             <label class="small text-muted mb-1">Qty</label>
                            <input type="number" class="form-control" name="quantities[]" min="1" required>
                        </div>
                        <div class="col-md-2">
                             <label class="small text-muted mb-1">Dosage</label>
                            <input type="text" class="form-control" name="dosages[]" placeholder="1-0-1">
                        </div>
                        <div class="col-md-3">
                             <label class="small text-muted mb-1">Instructions</label>
                            <input type="text" class="form-control" name="instructions[]" placeholder="e.g. After food">
                        </div>
                        <div class="col-md-1">
                            <button type="button" class="btn btn-outline-danger w-100" onclick="this.closest('.medicine-row').remove()"><i class="bi bi-trash"></i></button>
                        </div>
                    `;
                    container.appendChild(div);
                }

                // Add initial row
                addRow();

                // Event listener
                btn.addEventListener('click', addRow);
            <?php endif; ?>
        });
    </script>
</body>
</html> 
