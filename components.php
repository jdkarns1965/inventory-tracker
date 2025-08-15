<?php
$pageTitle = 'Components Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_inventory');

$db = getDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_component':
                checkPermissionOrRedirect('manage_components');
                $componentName = validateAndSanitizeInput($_POST['component_name'], true);
                $componentType = validateAndSanitizeInput($_POST['component_type']);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTime = validateAndSanitizeInput($_POST['lead_time_days'], false, 'int');
                $currentStock = validateAndSanitizeInput($_POST['current_stock'], false, 'int') ?: 0;
                $reorderPoint = validateAndSanitizeInput($_POST['reorder_point'], false, 'int') ?: 0;
                
                if ($componentName) {
                    try {
                        $stmt = $db->prepare("INSERT INTO components (component_name, component_type, supplier, lead_time_days, current_stock, reorder_point) VALUES (?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$componentName, $componentType, $supplier, $leadTime, $currentStock, $reorderPoint]);
                        showAlert("Component '$componentName' added successfully!", 'success');
                    } catch (PDOException $e) {
                        showAlert('Error adding component. Please try again.', 'error');
                    }
                } else {
                    showAlert('Please fill in the component name.', 'error');
                }
                break;
                
            case 'update_stock':
                checkPermissionOrRedirect('update_inventory');
                $componentId = validateAndSanitizeInput($_POST['component_id'], true, 'int');
                $newStock = validateAndSanitizeInput($_POST['new_stock'], true, 'int');
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($componentId && $newStock !== false) {
                    updateComponentStock($componentId, $newStock, 'physical_count', $notes);
                    showAlert('Component stock updated successfully!', 'success');
                }
                break;
        }
    }
}

// Get all components with stock status
$components = $db->query("
    SELECT c.*, 
           CASE 
               WHEN c.current_stock <= 0 THEN 'danger'
               WHEN c.current_stock <= c.reorder_point THEN 'warning'
               ELSE 'success'
           END as stock_status,
           CASE 
               WHEN c.current_stock <= 0 THEN 'Out of Stock'
               WHEN c.current_stock <= c.reorder_point THEN 'Low Stock'
               ELSE 'In Stock'
           END as stock_text
    FROM components c
    ORDER BY c.component_name
")->fetchAll();

// Get components summary
$summary = $db->query("
    SELECT 
        COUNT(*) as total_components,
        SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
        COUNT(DISTINCT component_type) as component_types
    FROM components
")->fetch();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="cpu" class="me-2"></i>
                Components Management
            </h2>
            <?php if (hasPermission('manage_components')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addComponentModal">
                    <i data-feather="plus" class="me-1"></i>
                    Add Component
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card bg-success">
                    <div class="metric-value"><?= $summary['total_components'] ?></div>
                    <div class="metric-label">Total Components</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['component_types'] ?></div>
                    <div class="metric-label">Component Types</div>
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
                    <input type="text" class="form-control search-input" placeholder="Search components..." data-target=".component-item">
                    <i data-feather="search" class="search-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Components List -->
    <div class="col-12">
        <?php if (empty($components)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="cpu" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">No Components Found</h5>
                    <p class="text-muted">Start by adding your first component to the system.</p>
                    <?php if (hasPermission('manage_components')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addComponentModal">
                            <i data-feather="plus" class="me-1"></i>
                            Add First Component
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($components as $component): ?>
                    <div class="col-12 col-md-6 col-lg-4 component-item">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($component['component_name']) ?></h5>
                                    <?php if ($component['component_type']): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($component['component_type']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?= $component['stock_status'] ?>"><?= $component['stock_text'] ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-<?= $component['stock_status'] ?>">
                                                <?= formatNumber($component['current_stock']) ?>
                                            </div>
                                            <small class="text-muted">Current Stock</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold"><?= formatNumber($component['reorder_point']) ?></div>
                                            <small class="text-muted">Reorder Point</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($component['supplier']): ?>
                                    <div class="mb-2">
                                        <strong>Supplier:</strong> <?= htmlspecialchars($component['supplier']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($component['lead_time_days']): ?>
                                    <div class="mb-3">
                                        <strong>Lead Time:</strong> <?= $component['lead_time_days'] ?> days
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small>Stock Level</small>
                                        <small class="text-<?= $component['stock_status'] ?>"><?= $component['stock_text'] ?></small>
                                    </div>
                                    <div class="stock-progress">
                                        <div class="stock-progress-bar bg-<?= $component['stock_status'] ?>" 
                                             style="width: <?= min(($component['current_stock'] / max($component['reorder_point'] * 2, 1)) * 100, 100) ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <?php if (hasPermission('update_inventory')): ?>
                                    <button class="btn btn-success w-100" onclick="updateStock(<?= $component['component_id'] ?>, '<?= htmlspecialchars($component['component_name']) ?>', <?= $component['current_stock'] ?>)">
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

<!-- Add Component Modal -->
<?php if (hasPermission('manage_components')): ?>
<div class="modal fade" id="addComponentModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_component">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Component</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="component_name" name="component_name" required>
                        <label for="component_name">Component Name *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="component_type" name="component_type">
                            <option value="">Select Type...</option>
                            <option value="Hardware">Hardware</option>
                            <option value="Electronics">Electronics</option>
                            <option value="Labels">Labels</option>
                            <option value="Gasket">Gasket</option>
                            <option value="Fastener">Fastener</option>
                            <option value="Seal">Seal</option>
                            <option value="Insert">Insert</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="component_type">Component Type</label>
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
                    <button type="submit" class="btn btn-primary">Add Component</button>
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
                <input type="hidden" name="component_id" id="update_component_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock - <span id="update_component_name"></span></h5>
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
function updateStock(componentId, componentName, currentStock) {
    document.getElementById('update_component_id').value = componentId;
    document.getElementById('update_component_name').textContent = componentName;
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