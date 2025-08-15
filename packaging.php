<?php
$pageTitle = 'Packaging Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_inventory');

$db = getDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_packaging':
                checkPermissionOrRedirect('manage_packaging');
                $packagingName = validateAndSanitizeInput($_POST['packaging_name'], true);
                $packagingType = validateAndSanitizeInput($_POST['packaging_type']);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTime = validateAndSanitizeInput($_POST['lead_time_days'], false, 'int');
                $currentStock = validateAndSanitizeInput($_POST['current_stock'], false, 'int') ?: 0;
                $reorderPoint = validateAndSanitizeInput($_POST['reorder_point'], false, 'int') ?: 0;
                
                if ($packagingName) {
                    try {
                        $stmt = $db->prepare("INSERT INTO packaging (packaging_name, packaging_type, supplier, lead_time_days, current_stock, reorder_point) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$packagingName, $packagingType, $supplier, $leadTime, $currentStock, $reorderPoint]);
                        showAlert("Packaging '$packagingName' added successfully!", 'success');
                    } catch (PDOException $e) {
                        showAlert('Error adding packaging. Please try again.', 'error');
                    }
                } else {
                    showAlert('Please fill in the packaging name.', 'error');
                }
                break;
                
            case 'update_stock':
                checkPermissionOrRedirect('update_inventory');
                $packagingId = validateAndSanitizeInput($_POST['packaging_id'], true, 'int');
                $newStock = validateAndSanitizeInput($_POST['new_stock'], true, 'int');
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($packagingId && $newStock !== false) {
                    updatePackagingStock($packagingId, $newStock, 'physical_count', $notes);
                    showAlert('Packaging stock updated successfully!', 'success');
                }
                break;
        }
    }
}

// Get all packaging with stock status
$packaging = $db->query("
    SELECT p.*, 
           CASE 
               WHEN p.current_stock <= 0 THEN 'danger'
               WHEN p.current_stock <= p.reorder_point THEN 'warning'
               ELSE 'success'
           END as stock_status,
           CASE 
               WHEN p.current_stock <= 0 THEN 'Out of Stock'
               WHEN p.current_stock <= p.reorder_point THEN 'Low Stock'
               ELSE 'In Stock'
           END as stock_text
    FROM packaging p
    ORDER BY p.packaging_name
")->fetchAll();

// Get packaging summary
$summary = $db->query("
    SELECT 
        COUNT(*) as total_packaging,
        SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
        COUNT(DISTINCT packaging_type) as packaging_types
    FROM packaging
")->fetch();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="package" class="me-2"></i>
                Packaging Management
            </h2>
            <?php if (hasPermission('manage_packaging')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackagingModal">
                    <i data-feather="plus" class="me-1"></i>
                    Add Packaging
                </button>
            <?php endif; ?>
        </div>
        <p class="text-muted">Manage packaging materials including boxes, bags, inserts, and labels for finished products.</p>
    </div>

    <!-- Summary Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['total_packaging'] ?></div>
                    <div class="metric-label">Total Packaging</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-primary">
                    <div class="metric-value"><?= $summary['packaging_types'] ?></div>
                    <div class="metric-label">Packaging Types</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-warning">
                    <div class="metric-value"><?= $summary['low_stock_count'] ?></div>
                    <div class="metric-label">Low Stock</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-danger">
                    <div class="metric-value"><?= $summary['out_of_stock_count'] ?></div>
                    <div class="metric-label">Out of Stock</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="search-container">
                    <input type="text" class="form-control search-input" placeholder="Search packaging..." data-target=".packaging-item">
                    <i data-feather="search" class="search-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Packaging List -->
    <div class="col-12">
        <?php if (empty($packaging)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="package" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">No Packaging Found</h5>
                    <p class="text-muted">Start by adding your first packaging material to the system.</p>
                    <?php if (hasPermission('manage_packaging')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPackagingModal">
                            <i data-feather="plus" class="me-1"></i>
                            Add First Packaging
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($packaging as $pack): ?>
                    <div class="col-12 col-md-6 col-lg-4 packaging-item">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($pack['packaging_name']) ?></h5>
                                    <?php if ($pack['packaging_type']): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($pack['packaging_type']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?= $pack['stock_status'] ?>"><?= $pack['stock_text'] ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-<?= $pack['stock_status'] ?>">
                                                <?= formatNumber($pack['current_stock']) ?>
                                            </div>
                                            <small class="text-muted">Current Stock</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold"><?= formatNumber($pack['reorder_point']) ?></div>
                                            <small class="text-muted">Reorder Point</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($pack['supplier']): ?>
                                    <div class="mb-2">
                                        <strong>Supplier:</strong> <?= htmlspecialchars($pack['supplier']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($pack['lead_time_days']): ?>
                                    <div class="mb-3">
                                        <strong>Lead Time:</strong> <?= $pack['lead_time_days'] ?> days
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small>Stock Level</small>
                                        <small class="text-<?= $pack['stock_status'] ?>"><?= $pack['stock_text'] ?></small>
                                    </div>
                                    <div class="stock-progress">
                                        <div class="stock-progress-bar bg-<?= $pack['stock_status'] ?>" 
                                             style="width: <?= min(($pack['current_stock'] / max($pack['reorder_point'] * 2, 1)) * 100, 100) ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Usage indicator for common packaging types -->
                                <?php if (in_array(strtolower($pack['packaging_type']), ['box', 'bag', 'label'])): ?>
                                    <div class="alert alert-info py-2 small">
                                        <i data-feather="info" style="width: 14px; height: 14px;" class="me-1"></i>
                                        Primary packaging material
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <?php if (hasPermission('update_inventory')): ?>
                                    <button class="btn btn-success w-100" onclick="updateStock(<?= $pack['packaging_id'] ?>, '<?= htmlspecialchars($pack['packaging_name']) ?>', <?= $pack['current_stock'] ?>)">
                                        <i data-feather="edit" class="me-1"></i>Update Stock
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Packaging Modal -->
<?php if (hasPermission('manage_packaging')): ?>
<div class="modal fade" id="addPackagingModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_packaging">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Packaging</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="packaging_name" name="packaging_name" required>
                        <label for="packaging_name">Packaging Name *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="packaging_type" name="packaging_type">
                            <option value="">Select Type...</option>
                            <option value="Box">Box</option>
                            <option value="Bag">Bag</option>
                            <option value="Insert">Insert</option>
                            <option value="Label">Label</option>
                            <option value="Tape">Tape</option>
                            <option value="Bubble Wrap">Bubble Wrap</option>
                            <option value="Foam">Foam</option>
                            <option value="Shrink Wrap">Shrink Wrap</option>
                            <option value="Pallet">Pallet</option>
                            <option value="Divider">Divider</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="packaging_type">Packaging Type</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="supplier" name="supplier">
                        <label for="supplier">Supplier</label>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="current_stock" name="current_stock" min="0" value="0">
                                <label for="current_stock">Current Stock</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="reorder_point" name="reorder_point" min="0" value="0">
                                <label for="reorder_point">Reorder Point</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating">
                        <input type="number" class="form-control" id="lead_time_days" name="lead_time_days" min="0">
                        <label for="lead_time_days">Lead Time (days)</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Packaging</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Update Stock Modal -->
<?php if (hasPermission('update_inventory')): ?>
<div class="modal fade" id="updateStockModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="update_stock">
                <input type="hidden" name="packaging_id" id="update_packaging_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock - <span id="update_packaging_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control form-control-lg" id="new_stock" name="new_stock" min="0" required>
                        <label for="new_stock">New Stock Count * (pieces)</label>
                    </div>
                    
                    <div class="form-floating">
                        <textarea class="form-control" id="stock_notes" name="notes" placeholder="Optional notes about this count"></textarea>
                        <label for="stock_notes">Notes</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Update Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function updateStock(packagingId, packagingName, currentStock) {
    document.getElementById('update_packaging_id').value = packagingId;
    document.getElementById('update_packaging_name').textContent = packagingName;
    document.getElementById('new_stock').value = currentStock;
    document.getElementById('stock_notes').value = '';
    
    new bootstrap.Modal(document.getElementById('updateStockModal')).show();
    
    // Focus and select the stock input
    setTimeout(() => {
        const stockInput = document.getElementById('new_stock');
        stockInput.focus();
        stockInput.select();
    }, 500);
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
});
</script>

<?php require_once 'includes/footer.php'; ?>