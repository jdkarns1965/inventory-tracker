<?php
$pageTitle = 'Molds Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_molds');

$db = getDatabase();

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_mold':
                checkPermissionOrRedirect('manage_molds');
                $moldNumber = validateAndSanitizeInput($_POST['mold_number'], true);
                $totalCavities = validateAndSanitizeInput($_POST['total_cavities'], true, 'int');
                $shotSize = validateAndSanitizeInput($_POST['shot_size'], true, 'float');
                $shotSizeUnit = validateAndSanitizeInput($_POST['shot_size_unit']);
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($moldNumber && $totalCavities && $shotSize) {
                    try {
                        $stmt = $db->prepare("INSERT INTO molds (mold_number, total_cavities, shot_size, shot_size_unit, notes) VALUES (?, ?, ?, ?, ?)");
                        $stmt->execute([$moldNumber, $totalCavities, $shotSize, $shotSizeUnit, $notes]);
                        showAlert("Mold '$moldNumber' added successfully!", 'success');
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            showAlert('Mold number already exists.', 'error');
                        } else {
                            showAlert('Error adding mold. Please try again.', 'error');
                        }
                    }
                } else {
                    showAlert('Please fill in all required fields.', 'error');
                }
                break;
                
            case 'assign_cavity':
                checkPermissionOrRedirect('manage_molds');
                $moldId = validateAndSanitizeInput($_POST['mold_id'], true, 'int');
                $cavityNumber = validateAndSanitizeInput($_POST['cavity_number'], true, 'int');
                $partId = validateAndSanitizeInput($_POST['part_id'], true, 'int');
                $partsPerShot = validateAndSanitizeInput($_POST['parts_per_shot'], true, 'int');
                
                if ($moldId && $cavityNumber && $partId && $partsPerShot) {
                    try {
                        $stmt = $db->prepare("INSERT INTO mold_cavities (mold_id, cavity_number, part_id, parts_per_shot) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE part_id = VALUES(part_id), parts_per_shot = VALUES(parts_per_shot)");
                        $stmt->execute([$moldId, $cavityNumber, $partId, $partsPerShot]);
                        showAlert("Cavity assignment updated successfully!", 'success');
                    } catch (PDOException $e) {
                        showAlert('Error updating cavity assignment.', 'error');
                    }
                }
                break;
        }
    }
}

// Get all molds with cavity information
$molds = $db->query("
    SELECT m.*, 
           COUNT(mc.cavity_id) as assigned_cavities,
           GROUP_CONCAT(CONCAT(mc.cavity_number, ':', p.part_number, ' (', mc.parts_per_shot, ')') SEPARATOR ', ') as cavity_assignments
    FROM molds m
    LEFT JOIN mold_cavities mc ON m.mold_id = mc.mold_id
    LEFT JOIN parts p ON mc.part_id = p.part_id
    GROUP BY m.mold_id
    ORDER BY m.mold_number
")->fetchAll();

// Get all parts for cavity assignment
$parts = $db->query("SELECT part_id, part_number, part_name FROM parts ORDER BY part_number")->fetchAll();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="tool" class="me-2"></i>
                Molds Management
            </h2>
            <?php if (hasPermission('manage_molds')): ?>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMoldModal">
                    <i data-feather="plus" class="me-1"></i>
                    Add Mold
                </button>
            <?php endif; ?>
        </div>
    </div>

    <!-- Molds List -->
    <div class="col-12">
        <?php if (empty($molds)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="tool" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">No Molds Found</h5>
                    <p class="text-muted">Start by adding your first mold to the system.</p>
                    <?php if (hasPermission('manage_molds')): ?>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addMoldModal">
                            <i data-feather="plus" class="me-1"></i>
                            Add First Mold
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row g-3">
                <?php foreach ($molds as $mold): ?>
                    <div class="col-12 col-md-6 col-lg-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><?= htmlspecialchars($mold['mold_number']) ?></h5>
                                <?php if (hasPermission('manage_molds')): ?>
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary" data-bs-toggle="dropdown">
                                            <i data-feather="more-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li><a class="dropdown-item" href="#" onclick="assignCavity(<?= $mold['mold_id'] ?>)">
                                                <i data-feather="settings" class="me-2"></i>Assign Cavities
                                            </a></li>
                                            <li><a class="dropdown-item" href="#" onclick="editMold(<?= $mold['mold_id'] ?>)">
                                                <i data-feather="edit" class="me-2"></i>Edit Mold
                                            </a></li>
                                        </ul>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <div class="row g-2 mb-3">
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-primary"><?= $mold['total_cavities'] ?></div>
                                            <small class="text-muted">Total Cavities</small>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="text-center p-2 bg-light rounded">
                                            <div class="fw-bold text-success"><?= $mold['assigned_cavities'] ?></div>
                                            <small class="text-muted">Assigned</small>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <strong>Shot Size:</strong> <?= formatNumber($mold['shot_size'], 2) ?> <?= htmlspecialchars($mold['shot_size_unit']) ?>
                                </div>
                                
                                <?php if ($mold['cavity_assignments']): ?>
                                    <div class="mb-3">
                                        <strong>Cavity Assignments:</strong>
                                        <div class="small text-muted mt-1">
                                            <?= htmlspecialchars($mold['cavity_assignments']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($mold['notes']): ?>
                                    <div>
                                        <strong>Notes:</strong>
                                        <div class="small text-muted mt-1">
                                            <?= htmlspecialchars($mold['notes']) ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Production Calculation -->
                            <div class="card-footer bg-light">
                                <div class="small">
                                    <strong>Quick Calc:</strong> 100 lbs material = 
                                    <?= formatNumber(floor(100 / $mold['shot_size']) * $mold['total_cavities']) ?> parts
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Add Mold Modal -->
<?php if (hasPermission('manage_molds')): ?>
<div class="modal fade" id="addMoldModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="add_mold">
                
                <div class="modal-header">
                    <h5 class="modal-title">Add New Mold</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="text" class="form-control" id="mold_number" name="mold_number" required>
                        <label for="mold_number">Mold Number *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="total_cavities" name="total_cavities" min="1" required>
                        <label for="total_cavities">Total Cavities *</label>
                    </div>
                    
                    <div class="row g-2 mb-3">
                        <div class="col-8">
                            <div class="form-floating">
                                <input type="number" class="form-control" id="shot_size" name="shot_size" step="0.0001" required>
                                <label for="shot_size">Shot Size *</label>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="form-floating">
                                <select class="form-select" id="shot_size_unit" name="shot_size_unit">
                                    <option value="lbs" selected>lbs</option>
                                    <option value="kg">kg</option>
                                    <option value="oz">oz</option>
                                    <option value="g">g</option>
                                </select>
                                <label for="shot_size_unit">Unit</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-floating">
                        <textarea class="form-control" id="notes" name="notes" style="height: 80px;"></textarea>
                        <label for="notes">Notes</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Add Mold</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Assign Cavity Modal -->
<div class="modal fade" id="assignCavityModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="action" value="assign_cavity">
                <input type="hidden" name="mold_id" id="assign_mold_id">
                
                <div class="modal-header">
                    <h5 class="modal-title">Assign Cavity</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <div class="modal-body">
                    <div class="form-floating mb-3">
                        <input type="number" class="form-control" id="cavity_number" name="cavity_number" min="1" required>
                        <label for="cavity_number">Cavity Number *</label>
                    </div>
                    
                    <div class="form-floating mb-3">
                        <select class="form-select" id="part_id" name="part_id" required>
                            <option value="">Select Part...</option>
                            <?php foreach ($parts as $part): ?>
                                <option value="<?= $part['part_id'] ?>">
                                    <?= htmlspecialchars($part['part_number']) ?> - <?= htmlspecialchars($part['part_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label for="part_id">Part *</label>
                    </div>
                    
                    <div class="form-floating">
                        <input type="number" class="form-control" id="parts_per_shot" name="parts_per_shot" min="1" value="1" required>
                        <label for="parts_per_shot">Parts Per Shot *</label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Assign Cavity</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function assignCavity(moldId) {
    document.getElementById('assign_mold_id').value = moldId;
    new bootstrap.Modal(document.getElementById('assignCavityModal')).show();
}

function editMold(moldId) {
    // Implementation for edit mold functionality
    showAlert('Edit functionality coming soon!', 'info');
}

// Auto-calculate parts from material input
function calculateParts() {
    const materialInput = document.getElementById('material_amount');
    const moldSelect = document.getElementById('mold_select');
    
    if (materialInput && moldSelect && materialInput.value && moldSelect.value) {
        // This would make an AJAX call to calculate parts
        // For demo purposes, we'll show a simple calculation
        console.log('Calculating parts for material amount:', materialInput.value);
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>