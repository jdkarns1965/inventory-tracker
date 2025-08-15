<?php
$pageTitle = 'Consumables Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_inventory');

$db = getDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_consumable':
                checkPermissionOrRedirect('manage_consumables');
                $consumableName = validateAndSanitizeInput($_POST['consumable_name'], true);
                $consumableType = validateAndSanitizeInput($_POST['consumable_type']);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTime = validateAndSanitizeInput($_POST['lead_time_days'], false, 'int');
                $currentStock = validateAndSanitizeInput($_POST['current_stock'], false, 'int') ?: 0;
                $containerSize = validateAndSanitizeInput($_POST['container_size']);
                $reorderPoint = validateAndSanitizeInput($_POST['reorder_point'], false, 'int') ?: 0;
                
                if ($consumableName) {
                    try {
                        $stmt = $db->prepare("INSERT INTO consumables (consumable_name, consumable_type, supplier, lead_time_days, current_stock, container_size, reorder_point) VALUES (?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$consumableName, $consumableType, $supplier, $leadTime, $currentStock, $containerSize, $reorderPoint]);
                        showAlert("Consumable '$consumableName' added successfully!", 'success');
                    } catch (PDOException $e) {
                        showAlert('Error adding consumable. Please try again.', 'error');
                    }
                } else {
                    showAlert('Please fill in the consumable name.', 'error');
                }
                break;
                
            case 'update_stock':
                checkPermissionOrRedirect('update_inventory');
                $consumableId = validateAndSanitizeInput($_POST['consumable_id'], true, 'int');
                $newStock = validateAndSanitizeInput($_POST['new_stock'], true, 'int');
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($consumableId && $newStock !== false) {
                    updateConsumableStock($consumableId, $newStock, 'physical_count', $notes);
                    showAlert('Consumable stock updated successfully!', 'success');
                }
                break;
        }
    }
}

// Get all consumables with stock status
$consumables = $db->query("
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
    FROM consumables c
    ORDER BY c.consumable_name
")->fetchAll();

// Get consumables summary
$summary = $db->query("
    SELECT 
        COUNT(*) as total_consumables,
        SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN current_stock <= 0 THEN 1 ELSE 0 END) as out_of_stock_count,
        COUNT(DISTINCT consumable_type) as consumable_types
    FROM consumables
")->fetch();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="droplet" class="me-2"></i>
                Consumables Management
            </h2>
            <?php if (hasPermission('manage_consumables')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addConsumableModal">
                    <i data-feather="plus" class="me-1"></i>
                    Add Consumable
                </button>
            <?php endif; ?>
        </div>
        <p class="text-muted">Manage assembly consumables like promoters, adhesives, cleaners, and lubricants. Stock is tracked by container count.</p>
    </div>

    <!-- Summary Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-3">
                <div class="metric-card bg-warning">
                    <div class="metric-value"><?= $summary['total_consumables'] ?></div>
                    <div class="metric-label">Total Consumables</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['consumable_types'] ?></div>
                    <div class="metric-label">Consumable Types</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-danger">
                    <div class="metric-value"><?= $summary['low_stock_count'] ?></div>
                    <div class="metric-label">Low Stock</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card bg-secondary">
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
                    <input type="text" class="form-control search-input" placeholder="Search consumables..." data-target=".consumable-item">
                    <i data-feather="search" class="search-icon"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Consumables List -->
    <div class="col-12">
        <?php if (empty($consumables)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="droplet" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">No Consumables Found</h5>
                    <p class="text-muted">Start by adding your first consumable to the system.</p>
                    <?php if (hasPermission('manage_consumables')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addConsumableModal">
                            <i data-feather="plus" class="me-1"></i>
                            Add First Consumable
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($consumables as $consumable): ?>
                    <div class="col-12 col-md-6 col-lg-4 consumable-item">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <h5 class="mb-1"><?= htmlspecialchars($consumable['consumable_name']) ?></h5>
                                    <?php if ($consumable['consumable_type']): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($consumable['consumable_type']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <span class="badge bg-<?= $consumable['stock_status'] ?>"><?= $consumable['stock_text'] ?></span>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-<?= $consumable['stock_status'] ?>">
                                                <?= formatNumber($consumable['current_stock']) ?>
                                            </div>
                                            <small class="text-muted">Containers</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold"><?= formatNumber($consumable['reorder_point']) ?></div>
                                            <small class="text-muted">Reorder Point</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <?php if ($consumable['container_size']): ?>
                                    <div class="mb-2">
                                        <strong>Container Size:</strong> <?= htmlspecialchars($consumable['container_size']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($consumable['supplier']): ?>
                                    <div class="mb-2">
                                        <strong>Supplier:</strong> <?= htmlspecialchars($consumable['supplier']) ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($consumable['lead_time_days']): ?>
                                    <div class="mb-3">
                                        <strong>Lead Time:</strong> <?= $consumable['lead_time_days'] ?> days
                                    </div>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-1">
                                        <small>Stock Level</small>
                                        <small class="text-<?= $consumable['stock_status'] ?>"><?= $consumable['stock_text'] ?></small>
                                    </div>
                                    <div class="stock-progress">
                                        <div class="stock-progress-bar bg-<?= $consumable['stock_status'] ?>" 
                                             style="width: <?= min(($consumable['current_stock'] / max($consumable['reorder_point'] * 2, 1)) * 100, 100) ?>%"></div>
                                    </div>
                                </div>
                                
                                <!-- Usage indicator for common consumables -->
                                <?php if (in_array(strtolower($consumable['consumable_type']), ['promoter', 'adhesive', 'cleaner'])): ?>
                                    <div class="alert alert-info py-2 small">
                                        <i data-feather="info" style="width: 14px; height: 14px;" class="me-1"></i>
                                        Assembly process consumable
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-footer">
                                <?php if (hasPermission('update_inventory')): ?>
                                    <button class="btn btn-success w-100" onclick="updateStock(<?= $consumable['consumable_id'] ?>, '<?= htmlspecialchars($consumable['consumable_name']) ?>', <?= $consumable['current_stock'] ?>, '<?= htmlspecialchars($consumable['container_size']) ?>')">
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

<!-- Add Consumable Modal -->
<?php if (hasPermission('manage_consumables')): ?>
<div class="modal fade" id="addConsumableModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_consumable">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Consumable</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="consumable_name" name="consumable_name" required>
                        <label for="consumable_name">Consumable Name *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="consumable_type" name="consumable_type">
                            <option value="">Select Type...</option>
                            <option value="Promoter">Promoter</option>
                            <option value="Adhesive">Adhesive</option>
                            <option value="Cleaner">Cleaner</option>
                            <option value="Lubricant">Lubricant</option>
                            <option value="Solvent">Solvent</option>
                            <option value="Release Agent">Release Agent</option>
                            <option value="Primer">Primer</option>
                            <option value="Activator">Activator</option>
                            <option value="Other">Other</option>
                        </select>
                        <label for="consumable_type">Consumable Type</label>
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
                                <select class="form-select" id="container_size" name="container_size">
                                    <option value="1 gallon">1 gallon</option>
                                    <option value="5 gallon">5 gallon</option>
                                    <option value="55 gallon">55 gallon</option>
                                    <option value="1 liter">1 liter</option>
                                    <option value="5 liter">5 liter</option>
                                    <option value="32 oz">32 oz</option>
                                    <option value="16 oz">16 oz</option>
                                    <option value="8 oz">8 oz</option>
                                    <option value="1 quart">1 quart</option>
                                    <option value="Other">Other</option>
                                </select>
                                <label for="container_size">Container Size</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="reorder_point" name="reorder_point" min="0" value="1">
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
                    
                    <div class="alert alert-info mt-3">
                        <small><i data-feather="info" style="width: 14px; height: 14px;" class="me-1"></i>Stock is tracked by number of containers, not volume.</small>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Consumable</button>
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
                <input type="hidden" name="consumable_id" id="update_consumable_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Update Stock - <span id="update_consumable_name"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control form-control-lg" id="new_stock" name="new_stock" min="0" required>
                        <label for="new_stock">Container Count * <span id="stock_unit"></span></label>
                    </div>
                    
                    <div class="form-floating">
                        <textarea class="form-control" id="stock_notes" name="notes" placeholder="Optional notes about this count"></textarea>
                        <label for="stock_notes">Notes</label>
                    </div>
                    
                    <div class="alert alert-warning mt-3">
                        <small><i data-feather="alert-triangle" style="width: 14px; height: 14px;" class="me-1"></i>Count the number of containers, not the volume.</small>
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
function updateStock(consumableId, consumableName, currentStock, containerSize) {
    document.getElementById('update_consumable_id').value = consumableId;
    document.getElementById('update_consumable_name').textContent = consumableName;
    document.getElementById('new_stock').value = currentStock;
    document.getElementById('stock_unit').textContent = containerSize ? '(' + containerSize + ' containers)' : '(containers)';
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