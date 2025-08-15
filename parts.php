<?php
$pageTitle = 'Parts Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_parts');

$db = getDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_part':
                checkPermissionOrRedirect('manage_parts');
                $partNumber = validateAndSanitizeInput($_POST['part_number'], true);
                $partName = validateAndSanitizeInput($_POST['part_name'], true);
                $partType = validateAndSanitizeInput($_POST['part_type'], true);
                $description = validateAndSanitizeInput($_POST['description']);
                $reorderPoint = validateAndSanitizeInput($_POST['reorder_point'], false, 'int') ?: 0;
                $currentStock = validateAndSanitizeInput($_POST['current_stock'], false, 'int') ?: 0;
                
                if ($partNumber && $partName && $partType) {
                    try {
                        $stmt = $db->prepare("INSERT INTO parts (part_number, part_name, part_type, description, reorder_point, current_stock) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$partNumber, $partName, $partType, $description, $reorderPoint, $currentStock]);
                        showAlert("Part '$partNumber' added successfully!", 'success');
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            showAlert('Part number already exists.', 'error');
                        } else {
                            showAlert('Error adding part. Please try again.', 'error');
                        }
                    }
                } else {
                    showAlert('Please fill in all required fields.', 'error');
                }
                break;
                
            case 'update_stock':
                checkPermissionOrRedirect('update_inventory');
                $partId = validateAndSanitizeInput($_POST['part_id'], true, 'int');
                $newStock = validateAndSanitizeInput($_POST['new_stock'], true, 'int');
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($partId && $newStock !== false) {
                    updatePartStock($partId, $newStock, 'physical_count', $notes);
                    showAlert('Part stock updated successfully!', 'success');
                }
                break;
        }
    }
}

// Get all parts with stock status
$parts = $db->query("
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
    FROM parts p
    ORDER BY p.part_number
")->fetchAll();

// Get parts summary
$summary = $db->query("
    SELECT 
        COUNT(*) as total_parts,
        SUM(CASE WHEN part_type = 'shoot_ship' THEN 1 ELSE 0 END) as shoot_ship_count,
        SUM(CASE WHEN part_type = 'value_added' THEN 1 ELSE 0 END) as value_added_count,
        SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock_count
    FROM parts
")->fetch();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="package" class="me-2"></i>
                Parts Management
            </h2>
            <?php if (hasPermission('manage_parts')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                    <i data-feather="plus" class="me-1"></i>
                    Add Part
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card bg-primary">
                    <div class="metric-value"><?= $summary['total_parts'] ?></div>
                    <div class="metric-label">Total Parts</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-success">
                    <div class="metric-value"><?= $summary['shoot_ship_count'] ?></div>
                    <div class="metric-label">Shoot & Ship</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['value_added_count'] ?></div>
                    <div class="metric-label">Value Added</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-warning">
                    <div class="metric-value"><?= $summary['low_stock_count'] ?></div>
                    <div class="metric-label">Low Stock</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Search and Filters -->
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-6">
                        <div class="search-container">
                            <input type="text" class="form-control search-input" placeholder="Search parts..." data-target=".part-item">
                            <i data-feather="search" class="search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="btn-group w-100" role="group">
                            <button class="btn btn-outline-secondary quick-filter active" data-filter="all" data-target=".part-item">All</button>
                            <button class="btn btn-outline-success quick-filter" data-filter="shoot_ship" data-target=".part-item">Shoot & Ship</button>
                            <button class="btn btn-outline-info quick-filter" data-filter="value_added" data-target=".part-item">Value Added</button>
                            <button class="btn btn-outline-warning quick-filter" data-filter="stock-warning" data-target=".part-item">Low Stock</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Parts List -->
    <div class="col-12">
        <?php if (empty($parts)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="package" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">No Parts Found</h5>
                    <p class="text-muted">Start by adding your first part to the system.</p>
                    <?php if (hasPermission('manage_parts')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                            <i data-feather="plus" class="me-1"></i>
                            Add First Part
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($parts as $part): ?>
                    <div class="col-12 col-md-6 col-lg-4 part-item <?= $part['part_type'] ?> <?= $part['stock_status'] === 'warning' ? 'stock-warning' : '' ?>">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <h5 class="mb-0"><?= htmlspecialchars($part['part_number']) ?></h5>
                                    <small class="text-muted"><?= htmlspecialchars($part['part_name']) ?></small>
                                </div>
                                <span class="badge bg-<?= $part['part_type'] === 'shoot_ship' ? 'success' : 'info' ?>">
                                    <?= $part['part_type'] === 'shoot_ship' ? 'Shoot & Ship' : 'Value Added' ?>
                                </span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-<?= $part['stock_status'] ?>"><?= formatNumber($part['current_stock']) ?></div>
                                            <small class="text-muted">Current Stock</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold"><?= formatNumber($part['reorder_point']) ?></div>
                                            <small class="text-muted">Reorder Point</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small>Stock Level</small>
                                        <small class="text-<?= $part['stock_status'] ?>"><?= $part['stock_text'] ?></small>
                                    </div>
                                    <div class="stock-progress">
                                        <div class="stock-progress-bar bg-<?= $part['stock_status'] ?>" 
                                             style="width: <?= min(($part['current_stock'] / max($part['reorder_point'] * 2, 1)) * 100, 100) ?>%"></div>
                                    </div>
                                </div>
                                
                                <?php if ($part['description']): ?>
                                    <p class="small text-muted mb-3"><?= htmlspecialchars($part['description']) ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <div class="btn-group w-100">
                                    <?php if (hasPermission('view_bom')): ?>
                                        <a href="bom.php?part_id=<?= $part['part_id'] ?>" class="btn btn-outline-primary btn-sm">
                                            <i data-feather="list" class="me-1"></i>BOM
                                        </a>
                                    <?php endif; ?>
                                    <?php if (hasPermission('update_inventory')): ?>
                                        <button class="btn btn-outline-success btn-sm" onclick="updateStock(<?= $part['part_id'] ?>, '<?= htmlspecialchars($part['part_number']) ?>', <?= $part['current_stock'] ?>)">
                                            <i data-feather="edit" class="me-1"></i>Update
                                        </button>
                                    <?php endif; ?>
                                    <?php if (hasPermission('view_molds')): ?>
                                        <a href="molds.php?part_id=<?= $part['part_id'] ?>" class="btn btn-outline-info btn-sm">
                                            <i data-feather="tool" class="me-1"></i>Molds
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Part Modal -->
<?php if (hasPermission('manage_parts')): ?>
<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_part">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Part</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="part_number" name="part_number" required>
                        <label for="part_number">Part Number *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="part_name" name="part_name" required>
                        <label for="part_name">Part Name *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="part_type" name="part_type" required>
                            <option value="">Select Type...</option>
                            <option value="shoot_ship">Shoot & Ship</option>
                            <option value="value_added">Value Added (Assembly Required)</option>
                        </select>
                        <label for="part_type">Part Type *</label>
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
                        <textarea class="form-control" id="description" name="description" style="height: 80px;"></textarea>
                        <label for="description">Description</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Part</button>
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
                <input type="hidden" name="part_id" id="update_part_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock - <span id="update_part_number"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control form-control-lg" id="new_stock" name="new_stock" min="0" required>
                        <label for="new_stock">New Stock Count *</label>
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
function updateStock(partId, partNumber, currentStock) {
    document.getElementById('update_part_id').value = partId;
    document.getElementById('update_part_number').textContent = partNumber;
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

// Auto-focus on search
document.addEventListener('DOMContentLoaded', function() {
    // Initialize search functionality
    feather.replace();
});
</script>

<?php require_once 'includes/footer.php'; ?>