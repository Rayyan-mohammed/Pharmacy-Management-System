<?php
require_once '../../app/auth.php';
checkRole(['Administrator']);

$database = new Database();
$db = $database->getConnection();
$medicine = new Medicine($db);
$inventory = new Inventory($db);

$message = '';
$message_type = '';
$importResults = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    verify_csrf_token();

    $file = $_FILES['csv_file'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $message = "File upload failed.";
        $message_type = 'danger';
    } elseif (pathinfo($file['name'], PATHINFO_EXTENSION) !== 'csv') {
        $message = "Only CSV files are accepted.";
        $message_type = 'danger';
    } else {
        $handle = fopen($file['tmp_name'], 'r');
        if (!$handle) {
            $message = "Could not open uploaded file.";
            $message_type = 'danger';
        } else {
            // Read header row
            $header = fgetcsv($handle);
            if (!$header) {
                $message = "CSV file is empty.";
                $message_type = 'danger';
            } else {
                // Normalize headers
                $header = array_map(function($h) { return strtolower(trim($h)); }, $header);
                $required = ['name', 'inventory_price', 'sale_price', 'stock', 'expiration_date'];
                $missing = array_diff($required, $header);
                
                if (!empty($missing)) {
                    $message = "Missing required columns: " . implode(', ', $missing);
                    $message_type = 'danger';
                } else {
                    $rowNum = 1;
                    $successCount = 0;
                    $errorCount = 0;

                    while (($row = fgetcsv($handle)) !== false) {
                        $rowNum++;
                        $data = array_combine($header, array_pad($row, count($header), ''));
                        
                        $name = trim($data['name'] ?? '');
                        $invPrice = (float)($data['inventory_price'] ?? 0);
                        $salePrice = (float)($data['sale_price'] ?? 0);
                        $stock = (int)($data['stock'] ?? 0);
                        $expDate = trim($data['expiration_date'] ?? '');
                        $prescription = (int)($data['prescription_needed'] ?? 0);
                        $batch = trim($data['batch_number'] ?? ('IMPORT-' . date('Ymd') . '-' . $rowNum));
                        $description = trim($data['description'] ?? '');

                        // Validate
                        if (empty($name)) {
                            $importResults[] = ['row' => $rowNum, 'status' => 'error', 'msg' => 'Missing name'];
                            $errorCount++;
                            continue;
                        }
                        if ($invPrice <= 0 || $salePrice <= 0) {
                            $importResults[] = ['row' => $rowNum, 'status' => 'error', 'msg' => "Invalid prices for '$name'"];
                            $errorCount++;
                            continue;
                        }
                        if (empty($expDate) || !strtotime($expDate)) {
                            $importResults[] = ['row' => $rowNum, 'status' => 'error', 'msg' => "Invalid expiration date for '$name'"];
                            $errorCount++;
                            continue;
                        }

                        try {
                            $db->beginTransaction();

                            $medicine->name = $name;
                            $medicine->description = $description;
                            $medicine->category_id = null;
                            $medicine->inventory_price = $invPrice;
                            $medicine->sale_price = $salePrice;
                            $medicine->stock = $stock;
                            $medicine->prescription_needed = $prescription;
                            $medicine->expiration_date = $expDate;

                            if ($medicine->create()) {
                                if ($stock > 0) {
                                    $medicine->addBatch($batch, $stock, $expDate);
                                }
                                $inventory->medicine_id = $medicine->id;
                                $inventory->type = 'in';
                                $inventory->quantity = $stock;
                                $inventory->reason = "Bulk import (Batch: $batch)";
                                $inventory->create();
                                
                                $db->commit();
                                $importResults[] = ['row' => $rowNum, 'status' => 'success', 'msg' => "Imported '$name'"];
                                $successCount++;
                            } else {
                                throw new Exception("DB insert failed");
                            }
                        } catch (Exception $e) {
                            if ($db->inTransaction()) $db->rollBack();
                            $importResults[] = ['row' => $rowNum, 'status' => 'error', 'msg' => "Failed '$name': " . $e->getMessage()];
                            $errorCount++;
                        }
                    }

                    $message = "Import complete: $successCount added, $errorCount failed.";
                    $message_type = $errorCount > 0 ? ($successCount > 0 ? 'warning' : 'danger') : 'success';
                    
                    try {
                        $al = new ActivityLog($db);
                        $al->log('IMPORT', "Bulk imported $successCount medicines ($errorCount errors)", 'medicine', null);
                    } catch (Exception $e) {}
                }
            }
            fclose($handle);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Import - PharmaFlow Pro</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">
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
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show shadow-sm mb-4">
                        <i class="bi bi-info-circle me-2"></i><?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-header bg-white py-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <h4 class="mb-0 text-primary"><i class="bi bi-cloud-upload me-2"></i>Bulk Import Medicines</h4>
                            <a href="../dashboard/dashboard.php" class="btn btn-outline-secondary btn-sm">
                                <i class="bi bi-arrow-left me-1"></i>Back
                            </a>
                        </div>
                    </div>
                    <div class="card-body p-4">
                        <div class="alert alert-info border-0 mb-4">
                            <h6 class="fw-bold mb-2"><i class="bi bi-info-circle me-1"></i>CSV Format</h6>
                            <p class="mb-2 small">Upload a CSV file with the following columns:</p>
                            <code class="d-block bg-white p-2 rounded small">name, inventory_price, sale_price, stock, expiration_date, prescription_needed, batch_number, description</code>
                            <p class="mt-2 mb-0 small text-muted">
                                Required: <strong>name, inventory_price, sale_price, stock, expiration_date</strong>. 
                                Optional: prescription_needed (0/1), batch_number, description.
                            </p>
                        </div>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
                            <div class="mb-4">
                                <label for="csv_file" class="form-label">Select CSV File</label>
                                <input type="file" class="form-control" id="csv_file" name="csv_file" accept=".csv" required>
                            </div>
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-upload me-1"></i>Upload & Import
                            </button>
                        </form>

                        <hr class="my-4">
                        <h6 class="text-muted mb-2">Download Template</h6>
                        <a href="#" class="btn btn-outline-success btn-sm" onclick="downloadTemplate(); return false;">
                            <i class="bi bi-download me-1"></i>Download CSV Template
                        </a>
                    </div>
                </div>

                <?php if (!empty($importResults)): ?>
                <div class="card shadow-sm mt-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="mb-0 text-primary"><i class="bi bi-list-check me-2"></i>Import Results</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 400px; overflow-y: auto;">
                            <table class="table table-sm table-hover align-middle mb-0">
                                <thead class="bg-light sticky-top">
                                    <tr>
                                        <th class="ps-4">Row</th>
                                        <th>Status</th>
                                        <th>Message</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($importResults as $result): ?>
                                    <tr>
                                        <td class="ps-4"><?php echo $result['row']; ?></td>
                                        <td>
                                            <?php if ($result['status'] === 'success'): ?>
                                                <span class="badge bg-success">Success</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Error</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="small"><?php echo htmlspecialchars($result['msg']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function downloadTemplate() {
        const header = 'name,inventory_price,sale_price,stock,expiration_date,prescription_needed,batch_number,description';
        const sample = 'Paracetamol 500mg,5.00,10.00,100,2026-12-31,0,BATCH-001,Pain relief tablet';
        const csv = header + '\n' + sample + '\n';
        const blob = new Blob([csv], { type: 'text/csv' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = 'medicine_import_template.csv';
        a.click();
    }
    </script>
</body>
</html>

