<?php
include_once '../database.php';
include_once '../models/Supplier.php';

$database = new Database();
$db = $database->getConnection();
$supplier = new Supplier($db);

$message = '';
$action = isset($_GET['action']) ? $_GET['action'] : 'list';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Handle form submissions
if($_POST) {
    if(isset($_POST['add_supplier'])) {
        // Set supplier property values
        $supplier->name = $_POST['name'];
        $supplier->contact = $_POST['contact'];
        $supplier->email = $_POST['email'];
        $supplier->phone = $_POST['phone'];
        $supplier->address = $_POST['address'];

        // Create the supplier
        if($supplier->create()) {
            $message = "Supplier was created successfully.";
            $action = 'list';
        } else {
            $message = "Unable to create supplier.";
        }
    }
    elseif(isset($_POST['edit_supplier'])) {
        // Set supplier property values
        $supplier->id = $_POST['id'];
        $supplier->name = $_POST['name'];
        $supplier->contact = $_POST['contact'];
        $supplier->email = $_POST['email'];
        $supplier->phone = $_POST['phone'];
        $supplier->address = $_POST['address'];

        // Update the supplier
        if($supplier->update()) {
            $message = "Supplier was updated successfully.";
            $action = 'list';
        } else {
            $message = "Unable to update supplier.";
        }
    }
    elseif(isset($_POST['delete_supplier'])) {
        $supplier->id = $_POST['id'];
        if($supplier->delete()) {
            $message = "Supplier was deleted successfully.";
            $action = 'list';
        } else {
            $message = "Unable to delete supplier.";
        }
    }
}

// Get suppliers list
if($search) {
    $suppliers_list = $supplier->search($search);
} else {
    $suppliers_list = $supplier->read();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Supplier Management - Pharmacy Management System</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="../styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="#">Pharmacy Management System</a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="/medical_management_new/public/dashboard/dashboard.php">Dashboard</a>
                <a class="nav-link" href="/medical_management_new/public/index.php">Logout</a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php if($message): ?>
            <div class="alert alert-info"><?php echo $message; ?></div>
        <?php endif; ?>

        <?php if($action == 'list'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                    <h3>Supplier Management</h3>
                    <a href="?action=add" class="btn btn-light">Add New Supplier</a>
                </div>
                <div class="card-body">
                    <!-- Search Form -->
                    <form method="GET" class="row g-3 mb-4">
                        <div class="col-md-8">
                            <input type="text" class="form-control" name="search" placeholder="Search suppliers..." value="<?php echo $search; ?>">
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-primary w-100">Search</button>
                        </div>
                    </form>

                    <!-- Suppliers Table -->
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Contact Person</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Address</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $suppliers_list->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td><?php echo $row['name']; ?></td>
                                        <td><?php echo $row['contact']; ?></td>
                                        <td><?php echo $row['email']; ?></td>
                                        <td><?php echo $row['phone']; ?></td>
                                        <td><?php echo $row['address']; ?></td>
                                        <td>
                                            <a href="?action=edit&id=<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Edit</a>
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="id" value="<?php echo $row['id']; ?>">
                                                <button type="submit" name="delete_supplier" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this supplier?')">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php elseif($action == 'add' || $action == 'edit'): ?>
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3><?php echo $action == 'add' ? 'Add New Supplier' : 'Edit Supplier'; ?></h3>
                </div>
                <div class="card-body">
                    <?php if($action == 'edit'): ?>
                        <?php
                        $supplier->id = $_GET['id'];
                        $supplier->readOne();
                        ?>
                    <?php endif; ?>

                    <form method="POST" action="">
                        <?php if($action == 'edit'): ?>
                            <input type="hidden" name="id" value="<?php echo $supplier->id; ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="name" class="form-label">Supplier Name</label>
                            <input type="text" class="form-control" id="name" name="name" value="<?php echo $supplier->name ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="contact" class="form-label">Contact Person</label>
                            <input type="text" class="form-control" id="contact" name="contact" value="<?php echo $supplier->contact ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo $supplier->email ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="phone" class="form-label">Phone</label>
                            <input type="text" class="form-control" id="phone" name="phone" value="<?php echo $supplier->phone ?? ''; ?>" required>
                        </div>
                        <div class="mb-3">
                            <label for="address" class="form-label">Address</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo $supplier->address ?? ''; ?></textarea>
                        </div>
                        <div class="d-flex justify-content-between">
                            <a href="?action=list" class="btn btn-secondary">Cancel</a>
                            <button type="submit" name="<?php echo $action; ?>_supplier" class="btn btn-primary">
                                <?php echo $action == 'add' ? 'Add Supplier' : 'Update Supplier'; ?>
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 