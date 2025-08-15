<?php
$pageTitle = 'Materials Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_inventory');

$db = getDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_material':
                checkPermissionOrRedirect('manage_materials');
                $materialName = validateAndSanitizeInput($_POST['material_name'], true);
                $materialType = validateAndSanitizeInput($_POST['material_type']);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTime = validateAndSanitizeInput($_POST['lead_time_days'], false, 'int');
                $currentStock = validateAndSanitizeInput($_POST['current_stock'], false, 'float') ?: 0;
                $unitOfMeasure = validateAndSanitizeInput($_POST['unit_of_measure']);
                $reorderPoint = validateAndSanitizeInput($_POST['reorder_point'], false, 'float') ?: 0;
                
                if ($materialName) {
                    try {
                        $stmt = $db->prepare("INSERT INTO materials (material_name, material_type, supplier, lead_time_days, current_stock, unit_of_measure, reorder_point) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$materialName, $materialType, $supplier, $leadTime, $currentStock, $unitOfMeasure, $reorderPoint]);
                        showAlert("Material '$materialName' added successfully!", 'success');
                    } catch (PDOException $e) {
                        showAlert('Error adding material. Please try again.', 'error');
                    }
                } else {
                    showAlert('Please fill in the material name.', 'error');
                }
                break;
                
            case 'update_stock':
                checkPermissionOrRedirect('update_inventory');
                $materialId = validateAndSanitizeInput($_POST['material_id'], true, 'int');
                $newStock = validateAndSanitizeInput($_POST['new_stock'], true, 'float');
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($materialId && $newStock !== false) {
                    updateMaterialStock($materialId, $newStock, 'physical_count', $notes);
                    showAlert('Material stock updated successfully!', 'success');
                }
                break;
        }
    }
}

// Get all materials with stock status
$materials = $db->query("
    SELECT m.*, 
           CASE 
               WHEN m.current_stock <= 0 THEN 'danger'
               WHEN m.current_stock <= m.reorder_point THEN 'warning'
               ELSE 'success'
           END as stock_status,
           CASE 
               WHEN m.current_stock <= 0 THEN 'Out of Stock'
               WHEN m.current_stock <= m.reorder_point THEN 'Low Stock'
               ELSE 'In Stock'
           END as stock_text
    FROM materials m
    ORDER BY m.material_name
")->fetchAll();

// Get materials summary
$summary = $db->query("
    SELECT 
        COUNT(*) as total_materials,
        SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
        COUNT(DISTINCT material_type) as material_types
    FROM materials
")->fetch();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="box" class="me-2"></i>
                Materials Management
            </h2>
            <?php if (hasPermission('manage_materials')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                    <i data-feather="plus" class="me-1"></i>
                    Add Material
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card bg-primary">
                    <div class="metric-value"><?= $summary['total_materials'] ?></div>
                    <div class="metric-label">Total Materials</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['material_types'] ?></div>
                    <div class="metric-label">Material Types</div>
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
                    <input type="text" class="form-control search-input" placeholder="Search materials..." data-target=".material-item">
                    <i data-feather="search" class="search-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Materials List -->
    <div class="col-12">
        <?php if (empty($materials)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="box" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">No Materials Found</h5>
                    <p class="text-muted">Start by adding your first material to the system.</p>
                    <?php if (hasPermission('manage_materials')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                            <i data-feather="plus" class="me-1"></i>
                            Add First Material
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($materials as $material): ?>
                    <div class="col-12 col-md-6 col-lg-4 material-item">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($material['material_name']) ?></h5>
                                    <?php if ($material['material_type']): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($material['material_type']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?= $material['stock_status'] ?>"><?= $material['stock_text'] ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-<?= $material['stock_status'] ?>">
                                                <?= formatNumber($material['current_stock'], 2) ?>
                                            </div>
                                            <small class="text-muted">Current Stock</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold"><?= formatNumber($material['reorder_point'], 2) ?></div>
                                            <small class="text-muted">Reorder Point</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($material['unit_of_measure']): ?>
                                    <div class="mb-2">
                                        <strong>Unit:</strong> <?= htmlspecialchars($material['unit_of_measure']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($material['supplier']): ?>
                                    <div class="mb-2">
                                        <strong>Supplier:</strong> <?= htmlspecialchars($material['supplier']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($material['lead_time_days']): ?>
                                    <div class="mb-3">
                                        <strong>Lead Time:</strong> <?= $material['lead_time_days'] ?> days
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small>Stock Level</small>
                                        <small class="text-<?= $material['stock_status'] ?>"><?= $material['stock_text'] ?></small>
                                    </div>
                                    <div class="stock-progress">
                                        <div class="stock-progress-bar bg-<?= $material['stock_status'] ?>" 
                                             style="width: <?= min(($material['current_stock'] / max($material['reorder_point'] * 2, 1)) * 100, 100) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <?php if (hasPermission('update_inventory')): ?>
                                    <button class="btn btn-success w-100" onclick="updateStock(<?= $material['material_id'] ?>, '<?= htmlspecialchars($material['material_name']) ?>', <?= $material['current_stock'] ?>, '<?= htmlspecialchars($material['unit_of_measure']) ?>')">
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

<!-- Add Material Modal -->
<?php if (hasPermission('manage_materials')): ?>
<div class="modal fade" id="addMaterialModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_material">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Material</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="material_name" name="material_name" required>
                        <label for="material_name">Material Name *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="material_type" name="material_type">
                            <option value="">Select Type...</option>
                            <option value="Resin">Resin</option>
                            <option value="Colorant">Colorant</option>
                            <option value="Additive">Additive</option>
                            <option value="Regrind">Regrind</option>
                            <option value="Release Agent">Release Agent</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="material_type">Material Type</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="supplier" name="supplier">
                        <label for="supplier">Supplier</label>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="current_stock" name="current_stock" step="0.01" min="0" value="0">
                                <label for="current_stock">Current Stock</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <select class="form-select" id="unit_of_measure" name="unit_of_measure">
                                    <option value="lbs">lbs</option>
                                    <option value="kg">kg</option>
                                    <option value="oz">oz</option>
                                    <option value="g">g</option>
                                    <option value="gallons">gallons</option>
                                    <option value="liters">liters</option>
                                </select>
                                <label for="unit_of_measure">Unit</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="reorder_point" name="reorder_point" step="0.01" min="0" value="0">
                                <label for="reorder_point">Reorder Point</label>
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="lead_time_days" name="lead_time_days" min="0">
                                <label for="lead_time_days">Lead Time (days)</label>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Material</button>
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
                <input type="hidden" name="material_id" id="update_material_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock - <span id="update_material_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control form-control-lg" id="new_stock" name="new_stock" step="0.01" min="0" required>
                        <label for="new_stock">New Stock Count * <span id="stock_unit"></span></label>
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
function updateStock(materialId, materialName, currentStock, unit) {
    document.getElementById('update_material_id').value = materialId;
    document.getElementById('update_material_name').textContent = materialName;
    document.getElementById('new_stock').value = currentStock;
    document.getElementById('stock_unit').textContent = unit ? '(' + unit + ')' : '';
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