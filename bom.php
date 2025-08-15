<?php
$pageTitle = 'Bill of Materials Builder';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_bom');

$db = getDatabase();

// Get selected part ID from URL or form
$selectedPartId = isset($_GET['part_id']) ? (int)$_GET['part_id'] : (isset($_POST['part_id']) ? (int)$_POST['part_id'] : null);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ensureCSRFToken();
    
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add_material':
                checkPermissionOrRedirect('manage_bom');
                $partId = (int)$_POST['part_id'];
                $materialId = (int)$_POST['material_id'];
                $quantity = floatval($_POST['quantity_per_part']);
                
                if ($partId && $materialId && $quantity > 0) {
                    try {
                        $stmt = $db->prepare("INSERT INTO part_materials (part_id, material_id, quantity_per_part) VALUES (?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE quantity_per_part = VALUES(quantity_per_part)");
                        $stmt->execute([$partId, $materialId, $quantity]);
                        showAlert('Material added to BOM successfully!', 'success');
                        $selectedPartId = $partId;
                    } catch (PDOException $e) {
                        showAlert('Error adding material to BOM.', 'error');
                    }
                }
                break;
                
            case 'quick_add_material':
                checkPermissionOrRedirect('manage_materials');
                $partNumber = validateAndSanitizeInput($_POST['part_number'], true);
                $materialName = validateAndSanitizeInput($_POST['material_name'], true);
                $materialType = validateAndSanitizeInput($_POST['material_type'], true);
                $unitOfMeasure = validateAndSanitizeInput($_POST['unit_of_measure'], true);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                
                if ($partNumber && $materialName && $materialType && $unitOfMeasure) {
                    try {
                        $stmt = $db->prepare("INSERT INTO materials (part_number, material_name, material_type, unit_of_measure, supplier, current_stock, reorder_point) VALUES (?, ?, ?, ?, ?, 0, 0)");
                        $stmt->execute([$partNumber, $materialName, $materialType, $unitOfMeasure, $supplier]);
                        $newMaterialId = $db->lastInsertId();
                        
                        // Now add to BOM
                        $partId = (int)$_POST['part_id'];
                        $quantity = floatval($_POST['quantity_per_part']);
                        
                        if ($partId && $quantity > 0) {
                            $stmt = $db->prepare("INSERT INTO part_materials (part_id, material_id, quantity_per_part) VALUES (?, ?, ?)");
                            $stmt->execute([$partId, $newMaterialId, $quantity]);
                            showAlert("New material '$partNumber - $materialName' created and added to BOM!", 'success');
                            $selectedPartId = $partId;
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            showAlert('Material part number already exists.', 'error');
                        } else {
                            showAlert('Error creating material.', 'error');
                        }
                    }
                }
                break;
                
            case 'add_component':
                checkPermissionOrRedirect('manage_bom');
                $partId = (int)$_POST['part_id'];
                $componentId = (int)$_POST['component_id'];
                $quantity = (int)$_POST['quantity_per_part'];
                
                if ($partId && $componentId && $quantity > 0) {
                    try {
                        $stmt = $db->prepare("INSERT INTO part_components (part_id, component_id, quantity_per_part) VALUES (?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE quantity_per_part = VALUES(quantity_per_part)");
                        $stmt->execute([$partId, $componentId, $quantity]);
                        showAlert('Component added to BOM successfully!', 'success');
                        $selectedPartId = $partId;
                    } catch (PDOException $e) {
                        showAlert('Error adding component to BOM.', 'error');
                    }
                }
                break;
                
            case 'quick_add_component':
                checkPermissionOrRedirect('manage_components');
                $componentName = validateAndSanitizeInput($_POST['component_name'], true);
                $componentType = validateAndSanitizeInput($_POST['component_type'], true);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTimeDays = (int)($_POST['lead_time_days'] ?? 0);
                
                if ($componentName && $componentType) {
                    try {
                        $stmt = $db->prepare("INSERT INTO components (component_name, component_type, supplier, lead_time_days, current_stock, reorder_point) VALUES (?, ?, ?, ?, 0, 0)");
                        $stmt->execute([$componentName, $componentType, $supplier, $leadTimeDays]);
                        $newComponentId = $db->lastInsertId();
                        
                        // Now add to BOM
                        $partId = (int)$_POST['part_id'];
                        $quantity = (int)$_POST['quantity_per_part'];
                        
                        if ($partId && $quantity > 0) {
                            $stmt = $db->prepare("INSERT INTO part_components (part_id, component_id, quantity_per_part) VALUES (?, ?, ?)");
                            $stmt->execute([$partId, $newComponentId, $quantity]);
                            showAlert("New component '$componentName' created and added to BOM!", 'success');
                            $selectedPartId = $partId;
                        }
                    } catch (PDOException $e) {
                        showAlert('Error creating component.', 'error');
                    }
                }
                break;
                
            case 'add_consumable':
                checkPermissionOrRedirect('manage_bom');
                $partId = (int)$_POST['part_id'];
                $consumableId = (int)$_POST['consumable_id'];
                $required = isset($_POST['required']) ? 1 : 0;
                $applicationStep = (int)$_POST['application_step'];
                $notes = validateAndSanitizeInput($_POST['notes']);
                
                if ($partId && $consumableId) {
                    try {
                        $stmt = $db->prepare("INSERT INTO part_consumables (part_id, consumable_id, required, application_step, notes) VALUES (?, ?, ?, ?, ?) 
                                            ON DUPLICATE KEY UPDATE required = VALUES(required), application_step = VALUES(application_step), notes = VALUES(notes)");
                        $stmt->execute([$partId, $consumableId, $required, $applicationStep, $notes]);
                        showAlert('Consumable added to BOM successfully!', 'success');
                        $selectedPartId = $partId;
                    } catch (PDOException $e) {
                        showAlert('Error adding consumable to BOM.', 'error');
                    }
                }
                break;
                
            case 'quick_add_consumable':
                checkPermissionOrRedirect('manage_consumables');
                $consumableName = validateAndSanitizeInput($_POST['consumable_name'], true);
                $consumableType = validateAndSanitizeInput($_POST['consumable_type'], true);
                $containerSize = validateAndSanitizeInput($_POST['container_size'], true);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTimeDays = (int)($_POST['lead_time_days'] ?? 0);
                
                if ($consumableName && $consumableType && $containerSize) {
                    try {
                        $stmt = $db->prepare("INSERT INTO consumables (consumable_name, consumable_type, container_size, supplier, lead_time_days, current_stock, reorder_point) VALUES (?, ?, ?, ?, ?, 0, 0)");
                        $stmt->execute([$consumableName, $consumableType, $containerSize, $supplier, $leadTimeDays]);
                        $newConsumableId = $db->lastInsertId();
                        
                        // Now add to BOM
                        $partId = (int)$_POST['part_id'];
                        $required = isset($_POST['required']) ? 1 : 0;
                        $applicationStep = (int)$_POST['application_step'];
                        $notes = validateAndSanitizeInput($_POST['notes']);
                        
                        if ($partId) {
                            $stmt = $db->prepare("INSERT INTO part_consumables (part_id, consumable_id, required, application_step, notes) VALUES (?, ?, ?, ?, ?)");
                            $stmt->execute([$partId, $newConsumableId, $required, $applicationStep, $notes]);
                            showAlert("New consumable '$consumableName' created and added to BOM!", 'success');
                            $selectedPartId = $partId;
                        }
                    } catch (PDOException $e) {
                        showAlert('Error creating consumable.', 'error');
                    }
                }
                break;
                
            case 'add_packaging':
                checkPermissionOrRedirect('manage_bom');
                $partId = (int)$_POST['part_id'];
                $packagingId = (int)$_POST['packaging_id'];
                $partitionRequired = isset($_POST['partition_required']) ? 1 : 0;
                $builtInPartitions = isset($_POST['built_in_partitions']) ? 1 : 0;
                
                if ($partId && $packagingId) {
                    try {
                        $stmt = $db->prepare("INSERT INTO part_packaging (part_id, packaging_id, quantity_per_part, partition_required, built_in_partitions) VALUES (?, ?, 1, ?, ?) 
                                            ON DUPLICATE KEY UPDATE quantity_per_part = 1, partition_required = VALUES(partition_required), built_in_partitions = VALUES(built_in_partitions)");
                        $stmt->execute([$partId, $packagingId, $partitionRequired, $builtInPartitions]);
                        showAlert('Packaging added to BOM successfully!', 'success');
                        $selectedPartId = $partId;
                    } catch (PDOException $e) {
                        showAlert('Error adding packaging to BOM.', 'error');
                    }
                }
                break;
                
            case 'quick_add_packaging':
                checkPermissionOrRedirect('manage_packaging');
                $partNumber = validateAndSanitizeInput($_POST['part_number'], true);
                $packagingName = validateAndSanitizeInput($_POST['packaging_name'], true);
                $packagingType = validateAndSanitizeInput($_POST['packaging_type'], true);
                $packagingCategory = validateAndSanitizeInput($_POST['packaging_category'], true);
                $parentPackagingId = !empty($_POST['parent_packaging_id']) ? (int)$_POST['parent_packaging_id'] : null;
                $qpc = (int)($_POST['qpc'] ?? 1);
                $containersPerSkid = (int)($_POST['containers_per_skid'] ?? 1);
                $supplier = validateAndSanitizeInput($_POST['supplier']);
                $leadTimeDays = (int)($_POST['lead_time_days'] ?? 0);
                
                if ($partNumber && $packagingName && $packagingType && $packagingCategory && $qpc > 0 && $containersPerSkid > 0) {
                    try {
                        $stmt = $db->prepare("INSERT INTO packaging (part_number, packaging_name, packaging_type, packaging_category, parent_packaging_id, qpc, containers_per_skid, supplier, lead_time_days, current_stock, reorder_point) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 0, 0)");
                        $stmt->execute([$partNumber, $packagingName, $packagingType, $packagingCategory, $parentPackagingId, $qpc, $containersPerSkid, $supplier, $leadTimeDays]);
                        $newPackagingId = $db->lastInsertId();
                        
                        // Now add to BOM (packaging uses QPC, not quantity_per_part)
                        $partId = (int)$_POST['part_id'];
                        $partitionRequired = isset($_POST['partition_required']) ? 1 : 0;
                        $builtInPartitions = isset($_POST['built_in_partitions']) ? 1 : 0;
                        
                        if ($partId) {
                            $stmt = $db->prepare("INSERT INTO part_packaging (part_id, packaging_id, quantity_per_part, partition_required, built_in_partitions) VALUES (?, ?, 1, ?, ?)");
                            $stmt->execute([$partId, $newPackagingId, $partitionRequired, $builtInPartitions]);
                            showAlert("New packaging '$partNumber - $packagingName' created and added to BOM!", 'success');
                            $selectedPartId = $partId;
                        }
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) {
                            showAlert('Packaging part number already exists.', 'error');
                        } else {
                            showAlert('Error creating packaging.', 'error');
                        }
                    }
                }
                break;
                
            case 'remove_bom_item':
                checkPermissionOrRedirect('manage_bom');
                $partId = (int)$_POST['part_id'];
                $itemType = validateAndSanitizeInput($_POST['item_type']);
                $itemId = (int)$_POST['item_id'];
                
                if ($partId && $itemType && $itemId) {
                    $table = '';
                    $column = '';
                    switch ($itemType) {
                        case 'material':
                            $table = 'part_materials';
                            $column = 'material_id';
                            break;
                        case 'component':
                            $table = 'part_components';
                            $column = 'component_id';
                            break;
                        case 'consumable':
                            $table = 'part_consumables';
                            $column = 'consumable_id';
                            break;
                        case 'packaging':
                            $table = 'part_packaging';
                            $column = 'packaging_id';
                            break;
                    }
                    
                    if ($table && $column) {
                        try {
                            $stmt = $db->prepare("DELETE FROM {$table} WHERE part_id = ? AND {$column} = ?");
                            $stmt->execute([$partId, $itemId]);
                            showAlert('Item removed from BOM successfully!', 'success');
                            $selectedPartId = $partId;
                        } catch (PDOException $e) {
                            showAlert('Error removing item from BOM.', 'error');
                        }
                    }
                }
                break;
        }
    }
}

// Get all parts for selection
$parts = $db->query("SELECT part_id, part_number, part_name, part_type FROM parts ORDER BY part_number")->fetchAll();

// Get selected part details if one is selected
$selectedPart = null;
$bomData = null;
$moldInfo = null;

if ($selectedPartId) {
    // Get part details
    $stmt = $db->prepare("SELECT * FROM parts WHERE part_id = ?");
    $stmt->execute([$selectedPartId]);
    $selectedPart = $stmt->fetch();
    
    if ($selectedPart) {
        // Get BOM data for this part
        $bomData = [];
        
        // Get materials
        $stmt = $db->prepare("
            SELECT pm.*, m.part_number, m.material_name, m.material_type, m.unit_of_measure, m.current_stock
            FROM part_materials pm
            JOIN materials m ON pm.material_id = m.material_id
            WHERE pm.part_id = ?
            ORDER BY m.part_number, m.material_name
        ");
        $stmt->execute([$selectedPartId]);
        $bomData['materials'] = $stmt->fetchAll();
        
        // Get components
        $stmt = $db->prepare("
            SELECT pc.*, c.component_name, c.component_type, c.current_stock
            FROM part_components pc
            JOIN components c ON pc.component_id = c.component_id
            WHERE pc.part_id = ?
            ORDER BY c.component_name
        ");
        $stmt->execute([$selectedPartId]);
        $bomData['components'] = $stmt->fetchAll();
        
        // Get consumables
        $stmt = $db->prepare("
            SELECT pcon.*, con.consumable_name, con.consumable_type, con.current_stock, con.container_size
            FROM part_consumables pcon
            JOIN consumables con ON pcon.consumable_id = con.consumable_id
            WHERE pcon.part_id = ?
            ORDER BY pcon.application_step, con.consumable_name
        ");
        $stmt->execute([$selectedPartId]);
        $bomData['consumables'] = $stmt->fetchAll();
        
        // Get packaging
        $stmt = $db->prepare("
            SELECT pp.*, p.part_number, p.packaging_name, p.packaging_type, p.packaging_category, p.qpc, p.containers_per_skid, p.current_stock, parent.packaging_name as parent_name
            FROM part_packaging pp
            JOIN packaging p ON pp.packaging_id = p.packaging_id
            LEFT JOIN packaging parent ON p.parent_packaging_id = parent.packaging_id
            WHERE pp.part_id = ?
            ORDER BY p.packaging_category, p.part_number, p.packaging_name
        ");
        $stmt->execute([$selectedPartId]);
        $bomData['packaging'] = $stmt->fetchAll();
        
        // Get mold information for this part
        $stmt = $db->prepare("
            SELECT m.*, mc.cavity_number, mc.parts_per_shot
            FROM molds m
            JOIN mold_cavities mc ON m.mold_id = mc.mold_id
            WHERE mc.part_id = ?
            ORDER BY m.mold_number, mc.cavity_number
        ");
        $stmt->execute([$selectedPartId]);
        $moldInfo = $stmt->fetchAll();
    }
}

// Get available items for dropdown
$availableMaterials = $db->query("SELECT material_id, part_number, material_name, material_type, unit_of_measure FROM materials ORDER BY part_number, material_name")->fetchAll();
$availableComponents = $db->query("SELECT component_id, component_name, component_type FROM components ORDER BY component_name")->fetchAll();
$availableConsumables = $db->query("SELECT consumable_id, consumable_name, consumable_type, container_size FROM consumables ORDER BY consumable_name")->fetchAll();
$availablePackaging = $db->query("SELECT p.packaging_id, p.part_number, p.packaging_name, p.packaging_type, p.packaging_category, p.qpc, p.containers_per_skid, parent.packaging_name as parent_name FROM packaging p LEFT JOIN packaging parent ON p.parent_packaging_id = parent.packaging_id ORDER BY p.packaging_category, p.part_number, p.packaging_name")->fetchAll();
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="list" class="me-2"></i>
                Bill of Materials Builder
            </h2>
        </div>
    </div>

    <!-- Part Selection -->
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <form method="GET" class="d-flex gap-2 align-items-end">
                    <div class="flex-grow-1">
                        <label for="part_select" class="form-label">Select Part</label>
                        <select class="form-select" id="part_select" name="part_id" onchange="this.form.submit()">
                            <option value="">Choose a part...</option>
                            <?php foreach ($parts as $part): ?>
                                <option value="<?= $part['part_id'] ?>" <?= $selectedPartId == $part['part_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($part['part_number']) ?> - <?= htmlspecialchars($part['part_name']) ?>
                                    (<?= $part['part_type'] === 'shoot_ship' ? 'Shoot & Ship' : 'Value Added' ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php if ($selectedPartId): ?>
                        <a href="bom.php" class="btn btn-outline-secondary">Clear</a>
                    <?php endif; ?>
                </form>
            </div>
        </div>
    </div>

    <?php if ($selectedPart): ?>
        <!-- Part Info -->
        <div class="col-12">
            <div class="card mb-3">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i data-feather="package" class="me-2"></i>
                        <?= htmlspecialchars($selectedPart['part_number']) ?> - <?= htmlspecialchars($selectedPart['part_name']) ?>
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold text-<?= $selectedPart['part_type'] === 'shoot_ship' ? 'success' : 'info' ?>">
                                    <?= $selectedPart['part_type'] === 'shoot_ship' ? 'Shoot & Ship' : 'Value Added' ?>
                                </div>
                                <small class="text-muted">Part Type</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold"><?= formatNumber($selectedPart['current_stock']) ?></div>
                                <small class="text-muted">Current Stock</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold"><?= formatNumber($selectedPart['reorder_point']) ?></div>
                                <small class="text-muted">Reorder Point</small>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="text-center p-2 bg-light rounded">
                                <div class="fw-bold text-primary"><?= count($moldInfo) ?></div>
                                <small class="text-muted">Mold Cavities</small>
                            </div>
                        </div>
                    </div>
                    <?php if ($selectedPart['description']): ?>
                        <div class="mt-3">
                            <small class="text-muted"><?= htmlspecialchars($selectedPart['description']) ?></small>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Mold Information -->
        <?php if (!empty($moldInfo)): ?>
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i data-feather="tool" class="me-2"></i>
                            Mold Information
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php 
                            $groupedMolds = [];
                            foreach ($moldInfo as $mold) {
                                $moldNumber = $mold['mold_number'];
                                if (!isset($groupedMolds[$moldNumber])) {
                                    $groupedMolds[$moldNumber] = [
                                        'mold' => $mold,
                                        'cavities' => []
                                    ];
                                }
                                $groupedMolds[$moldNumber]['cavities'][] = $mold;
                            }
                            ?>
                            <?php foreach ($groupedMolds as $moldNumber => $moldGroup): ?>
                                <div class="col-md-6 col-lg-4">
                                    <div class="p-2 border rounded bg-light">
                                        <div class="fw-bold"><?= htmlspecialchars($moldNumber) ?></div>
                                        <small class="text-muted d-block">
                                            <?= $moldGroup['mold']['total_cavities'] ?> cavities, 
                                            <?= $moldGroup['mold']['shot_size'] ?> <?= $moldGroup['mold']['shot_size_unit'] ?> per shot
                                        </small>
                                        <small class="text-primary d-block">
                                            Produces <?= array_sum(array_column($moldGroup['cavities'], 'parts_per_shot')) ?> parts per shot
                                        </small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- BOM Sections -->
        <div class="col-12">
            <div class="row g-3">
                <!-- Materials -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="layers" class="me-2"></i>
                                Materials
                            </h6>
                            <?php if (hasPermission('manage_bom')): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addMaterialModal">
                                    <i data-feather="plus" class="me-1"></i>Add
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bomData['materials'])): ?>
                                <div class="text-center py-3 text-muted">
                                    <i data-feather="layers" class="mb-2"></i>
                                    <div>No materials assigned</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($bomData['materials'] as $material): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <div class="fw-bold">
                                                <?php if ($material['part_number']): ?>
                                                    <span class="text-primary"><?= htmlspecialchars($material['part_number']) ?></span> - 
                                                <?php endif; ?>
                                                <?= htmlspecialchars($material['material_name']) ?>
                                            </div>
                                            <small class="text-muted">
                                                <?= $material['quantity_per_part'] ?> <?= $material['unit_of_measure'] ?> per shot
                                                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($material['material_type']) ?></span>
                                            </small>
                                        </div>
                                        <?php if (hasPermission('manage_bom')): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeBomItem('material', <?= $material['material_id'] ?>)">
                                                <i data-feather="x"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Components -->
                <?php if ($selectedPart['part_type'] === 'value_added'): ?>
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">
                                    <i data-feather="cpu" class="me-2"></i>
                                    Components
                                </h6>
                                <?php if (hasPermission('manage_bom')): ?>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addComponentModal">
                                        <i data-feather="plus" class="me-1"></i>Add
                                    </button>
                                <?php endif; ?>
                            </div>
                            <div class="card-body">
                                <?php if (empty($bomData['components'])): ?>
                                    <div class="text-center py-3 text-muted">
                                        <i data-feather="cpu" class="mb-2"></i>
                                        <div>No components assigned</div>
                                    </div>
                                <?php else: ?>
                                    <?php foreach ($bomData['components'] as $component): ?>
                                        <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                            <div>
                                                <div class="fw-bold"><?= htmlspecialchars($component['component_name']) ?></div>
                                                <small class="text-muted">
                                                    <?= $component['quantity_per_part'] ?> per part
                                                    <span class="badge bg-secondary ms-1"><?= htmlspecialchars($component['component_type']) ?></span>
                                                </small>
                                            </div>
                                            <?php if (hasPermission('manage_bom')): ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="removeBomItem('component', <?= $component['component_id'] ?>)">
                                                    <i data-feather="x"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Consumables -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="droplet" class="me-2"></i>
                                Consumables
                            </h6>
                            <?php if (hasPermission('manage_bom')): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addConsumableModal">
                                    <i data-feather="plus" class="me-1"></i>Add
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bomData['consumables'])): ?>
                                <div class="text-center py-3 text-muted">
                                    <i data-feather="droplet" class="mb-2"></i>
                                    <div>No consumables assigned</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($bomData['consumables'] as $consumable): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <div class="fw-bold">
                                                <?= htmlspecialchars($consumable['consumable_name']) ?>
                                                <?php if ($consumable['required']): ?>
                                                    <span class="badge bg-warning ms-1">Required</span>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                Step <?= $consumable['application_step'] ?>
                                                <span class="badge bg-secondary ms-1"><?= htmlspecialchars($consumable['consumable_type']) ?></span>
                                            </small>
                                            <?php if ($consumable['notes']): ?>
                                                <div class="small text-info"><?= htmlspecialchars($consumable['notes']) ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (hasPermission('manage_bom')): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeBomItem('consumable', <?= $consumable['consumable_id'] ?>)">
                                                <i data-feather="x"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Packaging -->
                <div class="col-lg-6">
                    <div class="card h-100">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6 class="mb-0">
                                <i data-feather="box" class="me-2"></i>
                                Packaging
                            </h6>
                            <?php if (hasPermission('manage_bom')): ?>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addPackagingModal">
                                    <i data-feather="plus" class="me-1"></i>Add
                                </button>
                            <?php endif; ?>
                        </div>
                        <div class="card-body">
                            <?php if (empty($bomData['packaging'])): ?>
                                <div class="text-center py-3 text-muted">
                                    <i data-feather="box" class="mb-2"></i>
                                    <div>No packaging assigned</div>
                                </div>
                            <?php else: ?>
                                <?php foreach ($bomData['packaging'] as $packaging): ?>
                                    <div class="d-flex justify-content-between align-items-center py-2 border-bottom">
                                        <div>
                                            <div class="fw-bold">
                                                <?php if ($packaging['part_number']): ?>
                                                    <span class="text-<?= $packaging['packaging_category'] === 'Returnable' ? 'success' : 'primary' ?>">
                                                        <?= htmlspecialchars($packaging['part_number']) ?>
                                                    </span> - 
                                                <?php endif; ?>
                                                <?= htmlspecialchars($packaging['packaging_name']) ?>
                                                <?php if ($packaging['parent_name']): ?>
                                                    <small class="text-muted">(for <?= htmlspecialchars($packaging['parent_name']) ?>)</small>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <span class="badge bg-<?= $packaging['packaging_category'] === 'Returnable' ? 'success' : 'info' ?> ms-1">
                                                    <?= htmlspecialchars($packaging['packaging_category']) ?> <?= htmlspecialchars($packaging['packaging_type']) ?>
                                                </span>
                                            </small>
                                            <?php if ($packaging['qpc'] && $packaging['containers_per_skid']): ?>
                                                <small class="text-info d-block">
                                                    QPC: <?= $packaging['qpc'] ?> parts/container | Max <?= $packaging['containers_per_skid'] ?> containers/skid
                                                </small>
                                            <?php endif; ?>
                                            <?php if ($packaging['packaging_category'] === 'Alternate'): ?>
                                                <?php if ($packaging['partition_required']): ?>
                                                    <small class="text-warning d-block">
                                                        <i data-feather="grid" style="width: 14px; height: 14px;" class="me-1"></i>
                                                        Partition insert required for this part
                                                    </small>
                                                <?php endif; ?>
                                                <?php if ($packaging['built_in_partitions']): ?>
                                                    <small class="text-success d-block">
                                                        <i data-feather="layers" style="width: 14px; height: 14px;" class="me-1"></i>
                                                        Container has built-in partitions
                                                    </small>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (hasPermission('manage_bom')): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="removeBomItem('packaging', <?= $packaging['packaging_id'] ?>)">
                                                <i data-feather="x"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Production Calculator -->
        <?php if (!empty($moldInfo) && !empty($bomData['materials'])): ?>
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">
                            <i data-feather="calculator" class="me-2"></i>
                            Production Calculator
                        </h6>
                    </div>
                    <div class="card-body">
                        <div class="row g-3" id="production-calculator">
                            <div class="col-md-6">
                                <label class="form-label">Target Parts to Produce</label>
                                <div class="input-group">
                                    <input type="number" class="form-control form-control-lg" id="target-parts" min="1" value="100" onchange="calculateMaterials()">
                                    <span class="input-group-text">parts</span>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estimated Shots Needed</label>
                                <div class="form-control form-control-lg bg-light" id="shots-needed">--</div>
                            </div>
                            <div class="col-12">
                                <h6 class="mb-3">Material Requirements:</h6>
                                <div id="material-requirements"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            const moldData = <?= json_encode($moldInfo) ?>;
            const materialData = <?= json_encode($bomData['materials']) ?>;

            function calculateMaterials() {
                const targetParts = parseInt(document.getElementById('target-parts').value) || 0;
                if (targetParts <= 0) return;

                // Calculate total parts per shot from all mold cavities
                const partsPerShot = moldData.reduce((total, mold) => total + parseInt(mold.parts_per_shot), 0);
                const shotsNeeded = Math.ceil(targetParts / partsPerShot);
                
                document.getElementById('shots-needed').textContent = shotsNeeded.toLocaleString();
                
                let requirementsHtml = '<div class="row g-2">';
                materialData.forEach(material => {
                    const totalNeeded = (parseFloat(material.quantity_per_part) * targetParts).toFixed(4);
                    const currentStock = parseFloat(material.current_stock) || 0;
                    const sufficient = currentStock >= totalNeeded;
                    
                    const materialDisplay = material.part_number ? `${material.part_number} - ${material.material_name}` : material.material_name;
                    requirementsHtml += `
                        <div class="col-md-6">
                            <div class="p-3 border rounded ${sufficient ? 'bg-success bg-opacity-10 border-success' : 'bg-danger bg-opacity-10 border-danger'}">
                                <div class="fw-bold">${materialDisplay}</div>
                                <div class="small text-muted">Need: ${totalNeeded} ${material.unit_of_measure}</div>
                                <div class="small text-muted">Available: ${currentStock} ${material.unit_of_measure}</div>
                                <div class="small ${sufficient ? 'text-success' : 'text-danger'} fw-bold">
                                    ${sufficient ? 'Sufficient' : `Short: ${(totalNeeded - currentStock).toFixed(4)} ${material.unit_of_measure}`}
                                </div>
                            </div>
                        </div>
                    `;
                });
                requirementsHtml += '</div>';
                
                document.getElementById('material-requirements').innerHTML = requirementsHtml;
            }

            // Initialize calculator
            document.addEventListener('DOMContentLoaded', function() {
                calculateMaterials();
            });
            </script>
        <?php endif; ?>
    <?php else: ?>
        <!-- No Part Selected -->
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="list" class="text-muted mb-3" style="width: 48px; height: 48px;"></i>
                    <h5 class="text-muted">Select a Part</h5>
                    <p class="text-muted">Choose a part from the dropdown above to view and manage its Bill of Materials.</p>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Add Material Modal -->
<?php if (hasPermission('manage_bom') && $selectedPartId): ?>
<div class="modal fade" id="addMaterialModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Material to BOM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="mb-3">Select Existing Material</h6>
                        <form method="POST" id="existing-material-form">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_material">
                            <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" name="material_id" required>
                                    <option value="">Select Material...</option>
                                    <?php foreach ($availableMaterials as $material): ?>
                                        <option value="<?= $material['material_id'] ?>">
                                            <?= $material['part_number'] ? htmlspecialchars($material['part_number']) . ' - ' : '' ?><?= htmlspecialchars($material['material_name']) ?> (<?= $material['material_type'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Material *</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" name="quantity_per_part" step="0.0001" min="0" required>
                                <label>Shot Size (per mold cycle) *</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Add Existing Material</button>
                        </form>
                    </div>
                    
                    <div class="col-lg-6">
                        <h6 class="mb-3">Or Create New Material</h6>
                        <?php if (hasPermission('manage_materials')): ?>
                            <form method="POST" id="new-material-form">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="quick_add_material">
                                <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="part_number" required>
                                    <label>Part Number *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="material_name" required>
                                    <label>Material Name *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <select class="form-select" name="material_type" required>
                                        <option value="">Type...</option>
                                        <option value="Resin">Resin</option>
                                        <option value="Colorant">Colorant</option>
                                        <option value="Additive">Additive</option>
                                        <option value="Filler">Filler</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <label>Material Type *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <select class="form-select" name="unit_of_measure" required>
                                        <option value="">Unit...</option>
                                        <option value="lbs">lbs</option>
                                        <option value="kg">kg</option>
                                        <option value="oz">oz</option>
                                        <option value="grams">grams</option>
                                    </select>
                                    <label>Unit of Measure *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="number" class="form-control" name="quantity_per_part" step="0.0001" min="0" required>
                                    <label>Shot Size (per mold cycle) *</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="text" class="form-control" name="supplier">
                                    <label>Supplier (Optional)</label>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">Create & Add Material</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i data-feather="lock" class="mb-2"></i>
                                <div>No permission to create materials</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Component Modal -->
<?php if (hasPermission('manage_bom') && $selectedPartId && $selectedPart['part_type'] === 'value_added'): ?>
<div class="modal fade" id="addComponentModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Component to BOM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="mb-3">Select Existing Component</h6>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_component">
                            <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" name="component_id" required>
                                    <option value="">Select Component...</option>
                                    <?php foreach ($availableComponents as $component): ?>
                                        <option value="<?= $component['component_id'] ?>">
                                            <?= htmlspecialchars($component['component_name']) ?> (<?= $component['component_type'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Component *</label>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <input type="number" class="form-control" name="quantity_per_part" min="1" value="1" required>
                                <label>Quantity per Part *</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Add Existing Component</button>
                        </form>
                    </div>
                    
                    <div class="col-lg-6">
                        <h6 class="mb-3">Or Create New Component</h6>
                        <?php if (hasPermission('manage_components')): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="quick_add_component">
                                <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="component_name" required>
                                    <label>Component Name *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <select class="form-select" name="component_type" required>
                                        <option value="">Type...</option>
                                        <option value="Hardware">Hardware</option>
                                        <option value="Electronics">Electronics</option>
                                        <option value="Labels">Labels</option>
                                        <option value="Gasket">Gasket</option>
                                        <option value="Insert">Insert</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <label>Component Type *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="number" class="form-control" name="quantity_per_part" min="1" value="1" required>
                                    <label>Quantity per Part *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="supplier">
                                    <label>Supplier (Optional)</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <input type="number" class="form-control" name="lead_time_days" min="0" value="0">
                                    <label>Lead Time (Days)</label>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">Create & Add Component</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i data-feather="lock" class="mb-2"></i>
                                <div>No permission to create components</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Consumable Modal -->
<?php if (hasPermission('manage_bom') && $selectedPartId): ?>
<div class="modal fade" id="addConsumableModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Consumable to BOM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="mb-3">Select Existing Consumable</h6>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_consumable">
                            <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" name="consumable_id" required>
                                    <option value="">Select Consumable...</option>
                                    <?php foreach ($availableConsumables as $consumable): ?>
                                        <option value="<?= $consumable['consumable_id'] ?>">
                                            <?= htmlspecialchars($consumable['consumable_name']) ?> (<?= $consumable['consumable_type'] ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Consumable *</label>
                            </div>
                            
                            <div class="row g-2 mb-3">
                                <div class="col-6">
                                    <div class="form-floating">
                                        <input type="number" class="form-control" name="application_step" min="1" value="1" required>
                                        <label>Assembly Step *</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check form-switch mt-3">
                                        <input class="form-check-input" type="checkbox" name="required" id="required" checked>
                                        <label class="form-check-label" for="required">Required</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="form-floating mb-3">
                                <textarea class="form-control" name="notes" style="height: 60px;"></textarea>
                                <label>Notes</label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Add Existing Consumable</button>
                        </form>
                    </div>
                    
                    <div class="col-lg-6">
                        <h6 class="mb-3">Or Create New Consumable</h6>
                        <?php if (hasPermission('manage_consumables')): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="quick_add_consumable">
                                <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="consumable_name" required>
                                    <label>Consumable Name *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <select class="form-select" name="consumable_type" required>
                                        <option value="">Type...</option>
                                        <option value="Promoter">Promoter</option>
                                        <option value="Adhesive">Adhesive</option>
                                        <option value="Cleaner">Cleaner</option>
                                        <option value="Lubricant">Lubricant</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <label>Consumable Type *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <select class="form-select" name="container_size" required>
                                        <option value="">Container Size...</option>
                                        <option value="1 gallon">1 gallon</option>
                                        <option value="5 gallon">5 gallon</option>
                                        <option value="32 oz">32 oz</option>
                                        <option value="16 oz">16 oz</option>
                                        <option value="8 oz">8 oz</option>
                                        <option value="5 liter">5 liter</option>
                                        <option value="1 liter">1 liter</option>
                                    </select>
                                    <label>Container Size *</label>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="application_step" min="1" value="1" required>
                                            <label>Assembly Step *</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-check form-switch mt-3">
                                            <input class="form-check-input" type="checkbox" name="required" id="required2" checked>
                                            <label class="form-check-label" for="required2">Required</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="supplier">
                                    <label>Supplier (Optional)</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="number" class="form-control" name="lead_time_days" min="0" value="0">
                                    <label>Lead Time (Days)</label>
                                </div>
                                
                                <div class="form-floating mb-3">
                                    <textarea class="form-control" name="notes" style="height: 50px;"></textarea>
                                    <label>Assembly Notes</label>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">Create & Add Consumable</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i data-feather="lock" class="mb-2"></i>
                                <div>No permission to create consumables</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Add Packaging Modal -->
<?php if (hasPermission('manage_bom') && $selectedPartId): ?>
<div class="modal fade" id="addPackagingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Packaging to BOM</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            
            <div class="modal-body">
                <div class="row">
                    <div class="col-lg-6">
                        <h6 class="mb-3">Select Existing Packaging</h6>
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="add_packaging">
                            <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                            
                            <div class="form-floating mb-3">
                                <select class="form-select" name="packaging_id" required onchange="checkPackagingCategory(this)">
                                    <option value="">Select Packaging...</option>
                                    <?php foreach ($availablePackaging as $packaging): ?>
                                        <option value="<?= $packaging['packaging_id'] ?>" data-category="<?= $packaging['packaging_category'] ?>">
                                            <?= $packaging['part_number'] ? htmlspecialchars($packaging['part_number']) . ' - ' : '' ?><?= htmlspecialchars($packaging['packaging_name']) ?> (<?= $packaging['packaging_category'] ?> <?= $packaging['packaging_type'] ?>)
                                            <?= $packaging['parent_name'] ? ' - for ' . htmlspecialchars($packaging['parent_name']) : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <label>Packaging *</label>
                            </div>
                            
                            <div class="mb-3" id="existing-partition-checkbox" style="display: none;">
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" name="partition_required" id="existing_partition_required">
                                    <label class="form-check-label" for="existing_partition_required">
                                        Partition insert required for this part
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="built_in_partitions" id="existing_built_in_partitions">
                                    <label class="form-check-label" for="existing_built_in_partitions">
                                        Container has built-in partitions
                                    </label>
                                </div>
                                <small class="text-muted">Select partition insert if separate inserts are needed, or built-in partitions if the container comes pre-partitioned.</small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary w-100">Add Existing Packaging</button>
                        </form>
                    </div>
                    
                    <div class="col-lg-6">
                        <h6 class="mb-3">Or Create New Packaging</h6>
                        <?php if (hasPermission('manage_packaging')): ?>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                <input type="hidden" name="action" value="quick_add_packaging">
                                <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="part_number" required>
                                    <label>Part Number *</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="packaging_name" required>
                                    <label>Packaging Name *</label>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <select class="form-select" name="packaging_category" required onchange="togglePackagingFields(this.value)">
                                                <option value="">Category...</option>
                                                <option value="Returnable">Returnable</option>
                                                <option value="Alternate">Alternate</option>
                                            </select>
                                            <label>Category *</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <select class="form-select" name="packaging_type" required>
                                                <option value="">Type...</option>
                                                <option value="Tote">Tote</option>
                                                <option value="Box">Box</option>
                                                <option value="Lid">Lid</option>
                                                <option value="Partition">Partition</option>
                                                <option value="Insert">Insert</option>
                                                <option value="Label">Label</option>
                                                <option value="Wrap">Wrap</option>
                                                <option value="Tray">Tray</option>
                                                <option value="Other">Other</option>
                                            </select>
                                            <label>Type *</label>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="form-floating mb-2" id="parent-packaging-field" style="display: none;">
                                    <select class="form-select" name="parent_packaging_id">
                                        <option value="">Select Parent Packaging...</option>
                                        <?php foreach ($availablePackaging as $pkg): ?>
                                            <?php if ($pkg['packaging_type'] !== 'Lid'): // Don't show lids as potential parents ?>
                                                <option value="<?= $pkg['packaging_id'] ?>">
                                                    <?= $pkg['part_number'] ? htmlspecialchars($pkg['part_number']) . ' - ' : '' ?><?= htmlspecialchars($pkg['packaging_name']) ?>
                                                </option>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </select>
                                    <label>Parent Packaging (for lids/components)</label>
                                </div>
                                
                                <div class="row g-2 mb-2">
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="qpc" min="1" value="1" required>
                                            <label>QPC (Parts/Container) *</label>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="form-floating">
                                            <input type="number" class="form-control" name="containers_per_skid" min="1" value="1" required>
                                            <label>Max Containers/Skid *</label>
                                        </div>
                                    </div>
                                </div>
                                
                                
                                <div class="form-floating mb-2">
                                    <input type="text" class="form-control" name="supplier">
                                    <label>Supplier (Optional)</label>
                                </div>
                                
                                <div class="form-floating mb-2">
                                    <input type="number" class="form-control" name="lead_time_days" min="0" value="0">
                                    <label>Lead Time (Days)</label>
                                </div>
                                
                                <div class="mb-3" id="new-partition-checkbox" style="display: none;">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" name="partition_required" id="new_partition_required">
                                        <label class="form-check-label" for="new_partition_required">
                                            Partition insert required for this part
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="built_in_partitions" id="new_built_in_partitions">
                                        <label class="form-check-label" for="new_built_in_partitions">
                                            Container has built-in partitions
                                        </label>
                                    </div>
                                    <small class="text-muted">Select partition insert if separate inserts are needed, or built-in partitions if the container comes pre-partitioned.</small>
                                </div>
                                
                                <button type="submit" class="btn btn-success w-100">Create & Add Packaging</button>
                            </form>
                        <?php else: ?>
                            <div class="text-center text-muted">
                                <i data-feather="lock" class="mb-2"></i>
                                <div>No permission to create packaging</div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
function checkPackagingCategory(selectElement) {
    const selectedOption = selectElement.options[selectElement.selectedIndex];
    const category = selectedOption.dataset.category;
    const partitionCheckbox = document.getElementById('existing-partition-checkbox');
    
    if (category === 'Alternate') {
        partitionCheckbox.style.display = 'block';
    } else {
        partitionCheckbox.style.display = 'none';
        document.getElementById('existing_partition_required').checked = false;
        document.getElementById('existing_built_in_partitions').checked = false;
    }
}

function removeBomItem(itemType, itemId) {
    if (!confirm('Are you sure you want to remove this item from the BOM?')) {
        return;
    }
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    form.innerHTML = `
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
        <input type="hidden" name="action" value="remove_bom_item">
        <input type="hidden" name="part_id" value="<?= $selectedPartId ?>">
        <input type="hidden" name="item_type" value="${itemType}">
        <input type="hidden" name="item_id" value="${itemId}">
    `;
    
    document.body.appendChild(form);
    form.submit();
}

function togglePackagingFields(category) {
    const parentField = document.getElementById('parent-packaging-field');
    const typeSelect = document.querySelector('select[name="packaging_type"]');
    const partitionCheckbox = document.getElementById('new-partition-checkbox');
    
    if (category === 'Returnable') {
        // For returnables, hide parent field and partition checkbox
        parentField.style.display = 'none';
        partitionCheckbox.style.display = 'none';
        document.getElementById('new_partition_required').checked = false;
        document.getElementById('new_built_in_partitions').checked = false;
        // Could add specific returnable part number formatting hints
    } else if (category === 'Alternate') {
        // For alternate packaging, may include lids that need parent reference
        // Show parent field when lid is selected
        const currentType = typeSelect.value;
        if (currentType === 'Lid') {
            parentField.style.display = 'block';
        }
        // Show partition checkbox for alternate packaging
        partitionCheckbox.style.display = 'block';
    }
}

// Toggle parent packaging field when type changes to Lid or Partition
document.addEventListener('change', function(e) {
    if (e.target.name === 'packaging_type') {
        const parentField = document.getElementById('parent-packaging-field');
        if (e.target.value === 'Lid' || e.target.value === 'Partition') {
            parentField.style.display = 'block';
        } else {
            parentField.style.display = 'none';
        }
    }
    
    // Mutual exclusion for partition checkboxes - only one can be selected
    if (e.target.name === 'partition_required' && e.target.checked) {
        const isExisting = e.target.id.includes('existing');
        const otherCheckbox = document.getElementById(isExisting ? 'existing_built_in_partitions' : 'new_built_in_partitions');
        if (otherCheckbox) otherCheckbox.checked = false;
    }
    
    if (e.target.name === 'built_in_partitions' && e.target.checked) {
        const isExisting = e.target.id.includes('existing');
        const otherCheckbox = document.getElementById(isExisting ? 'existing_partition_required' : 'new_partition_required');
        if (otherCheckbox) otherCheckbox.checked = false;
    }
});

document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
});
</script>

<?php require_once 'includes/footer.php'; ?>