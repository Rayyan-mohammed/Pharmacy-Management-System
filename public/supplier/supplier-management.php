<?php
require_once '../../app/auth.php';
checkRole(['Administrator', 'Pharmacist']);

$database = new Database();
$db = $database->getConnection();
$supplier = new Supplier($db);

$message = '';
$message_type = '';
// Determine current action: 'list', 'add', 'edit', 'delete' (handled via POST usually)
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Handle POST submissions
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf_token();
    if(isset($_POST['add_supplier'])) {
        $supplier->name = $_POST['name'];
        $supplier->contact_person = $_POST['contact_person'];
        $supplier->email = $_POST['email'];
        $supplier->phone = $_POST['phone'];
        $supplier->address = $_POST['address'];

        if($supplier->create()) {
            $message = "Supplier added successfully.";
            $message_type = "success";
            $action = 'list';
        } else {
            $message = "Unable to create supplier.";
            $message_type = "danger";
        }
    }
    elseif(isset($_POST['edit_supplier'])) {
        $supplier->id = $_POST['id'];
        $supplier->name = $_POST['name'];
        $supplier->contact_person = $_POST['contact_person'];
        $supplier->email = $_POST['email'];
        $supplier->phone = $_POST['phone'];
        $supplier->address = $_POST['address'];

        if($supplier->update()) {
            $message = "Supplier updated successfully.";
            $message_type = "success";
            $action = 'list';
        } else {
            $message = "Unable to update supplier.";
            $message_type = "danger";
        }
    }
    elseif(isset($_POST['delete_supplier'])) {
        $supplier->id = $_POST['id'];
        if($supplier->delete()) {
            $message = "Supplier deleted successfully.";
            $message_type = "success";
            $action = 'list';
        } else {
            $message = "Unable to delete supplier.";
             $message_type = "danger";
        }
    }
}

// Prepare data for the view
if ($action == 'list') {
    if($search) {
        $suppliers_list = $supplier->search($search);
    } else {
        $suppliers_list = $supplier->read();
    }
} elseif ($action == 'edit' && isset($_GET['id'])) {
    $supplier->id = $_GET['id'];
    $supplier->readOne();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Pharmacy Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.7.2/font/bootstrap-icons.css">
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../dashboard/dashboard.php">
                <i class="bi bi-heart-pulse-fill me-2"></i>Pharmacy Pro
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="../dashboard/dashboard.php">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../index.php">Logout</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container py-5">
        <?php if($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm mb-4" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if($action == 'list'): ?>
            <div class="row align-items-center mb-4">
                 <div class="col-md-6">
                    <h2 class="fw-bold text-primary mb-1">Suppliers</h2>
                    <p class="text-secondary mb-0">Manage your network of medicine distributors.</p>
                </div>
                <div class="col-md-6 text-md-end mt-3 mt-md-0">
                     <a href="?action=add" class="btn btn-primary shadow-sm"><i class="bi bi-plus-lg me-2"></i>Add New Supplier</a>
                </div>
            </div>

            <div class="card shadow border-0">
                <div class="card-header bg-white py-3 border-bottom">
                    <form method="GET" class="d-flex">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" class="form-control border-start-0 ps-0" name="search" placeholder="Search by name, contact or email..." value="<?php echo htmlspecialchars($search); ?>">
                            <button type="submit" class="btn btn-outline-primary">Search</button>
                            <?php if($search): ?>
                                <a href="?" class="btn btn-outline-secondary">Clear</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="bg-light text-secondary">
                                <tr>
                                    <th class="px-4 py-3">Supplier Name</th>
                                    <th class="py-3">Contact Person</th>
                                    <th class="py-3">Contact Info</th>
                                    <th class="py-3">Address</th>
                                    <th class="py-3 text-end px-4">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($suppliers_list && $suppliers_list->rowCount() > 0): ?>
                                    <?php while ($row = $suppliers_list->fetch(PDO::FETCH_ASSOC)): ?>
                                        <tr>
                                            <td class="px-4">
                                                <div class="d-flex align-items-center">
                                                    <div class="avatar-sm bg-primary bg-opacity-10 rounded-circle me-3 d-flex align-items-center justify-content-center text-primary fw-bold" style="width: 40px; height: 40px; min-width: 40px;">
                                                        <?php echo strtoupper(substr($row['name'], 0, 1)); ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-bold text-dark"><?php echo htmlspecialchars($row['name']); ?></div>
                                                        <small class="text-muted">ID: #<?php echo str_pad($row['id'], 4, '0', STR_PAD_LEFT); ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <i class="bi bi-person me-2 text-muted"></i><?php echo htmlspecialchars($row['contact_person']); ?>
                                            </td>
                                            <td>
                                                <div class="d-flex flex-column small">
                                                    <a href="mailto:<?php echo htmlspecialchars($row['email']); ?>" class="text-decoration-none text-secondary mb-1">
                                                        <i class="bi bi-envelope me-2"></i><?php echo htmlspecialchars($row['email']); ?>
                                                    </a>
                                                    <span class="text-secondary">
                                                        <i class="bi bi-telephone me-2"></i><?php echo htmlspecialchars($row['phone']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-muted small">
                                                <div class="d-flex">
                                                    <i class="bi bi-geo-alt me-2 mt-1"></i>
                                                    <span class="text-truncate" style="max-width: 200px;" title="<?php echo htmlspecialchars($row['address']); ?>">
                                                        <?php echo htmlspecialchars($row['address']); ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td class="text-end px-4">
                                                <div class="btn-group">
                                                    <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal<?php echo $row['id']; ?>" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>

                                                <!-- Delete Modal -->
                                                <div class="modal fade text-start" id="deleteModal<?php echo $row['id']; ?>" tabindex="-1" aria-hidden="true">
                                                    <div class="modal-dialog">
                                                        <div class="modal-content">
                                                            <div class="modal-header">
                                                                <h5 class="modal-title">Confirm Delete</h5>
                                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                            </div>
                                                            <div class="modal-body">
                                                                Are you sure you want to remove <strong><?php echo htmlspecialchars($row['name']); ?></strong> from suppliers? This action cannot be undone.
                                                            </div>
                                                            <div class="modal-footer">
                                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                <form method="POST" action="">
                                                                    <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                                                    <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                                    <button type="submit" name="delete_supplier" class="btn btn-danger">Delete Supplier</button>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="bi bi-people fs-1 d-block mb-3"></i>
                                            No suppliers found. <a href="?action=add">Add one now</a>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
        <?php elseif($action == 'add' || $action == 'edit'): ?>
            <div class="row justify-content-center">
                <div class="col-lg-8">
                     <div class="card shadow border-0">
                        <div class="card-header bg-white py-3">
                            <h4 class="mb-0 text-primary">
                                <?php echo $action == 'add' ? '<i class="bi bi-person-plus me-2"></i>New Supplier' : '<i class="bi bi-pencil-square me-2"></i>Edit Supplier'; ?>
                            </h4>
                        </div>
                        <div class="card-body p-4">
                            <forminput type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                                < method="POST" action="">
                                <?php if($action == 'edit'): ?>
                                    <input type="hidden" name="id" value="<?php echo $supplier->id; ?>">
                                <?php endif; ?>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="name" class="form-label text-muted">Company / Supplier Name</label>
                                        <input type="text" class="form-control" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($supplier->name ?? ''); ?>" required placeholder="e.g. HealthCorp Ltd">
                                    </div>
                                    <div class="col-md-6">
                                        <label for="contact_person" class="form-label text-muted">Contact Person</label>
                                        <input type="text" class="form-control" id="contact_person" name="contact_person" 
                                               value="<?php echo htmlspecialchars($supplier->contact_person ?? ''); ?>" required placeholder="e.g. John Doe">
                                    </div>
                                </div>

                                <div class="row g-3 mb-3">
                                    <div class="col-md-6">
                                        <label for="email" class="form-label text-muted">Email Address</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                                            <input type="email" class="form-control" id="email" name="email" 
                                                   value="<?php echo htmlspecialchars($supplier->email ?? ''); ?>" required placeholder="email@example.com">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="phone" class="form-label text-muted">Phone Number</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="bi bi-telephone"></i></span>
                                            <input type="text" class="form-control" id="phone" name="phone" 
                                                   value="<?php echo htmlspecialchars($supplier->phone ?? ''); ?>" required placeholder="+1 234 567 8900">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-4">
                                    <label for="address" class="form-label text-muted">Full Address</label>
                                    <textarea class="form-control" id="address" name="address" rows="3" required placeholder="Street address, City, ZIP code..."><?php echo htmlspecialchars($supplier->address ?? ''); ?></textarea>
                                </div>

                                <div class="d-flex justify-content-between pt-3 border-top">
                                    <a href="?action=list" class="btn btn-outline-secondary">Cancel</a>
                                    <button type="submit" name="<?php echo $action; ?>_supplier" class="btn btn-primary px-4">
                                        <i class="bi bi-save me-2"></i><?php echo $action == 'add' ? 'Save Supplier' : 'Update Details'; ?>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 