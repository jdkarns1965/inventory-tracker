<?php
$pageTitle = 'Inventory Update';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('update_inventory');

$db = getDatabase();

// Handle batch updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['batch_updates'])) {
    ensureCSRFToken();
    
    $updates = json_decode($_POST['batch_updates'], true);
    $updateCount = 0;
    
    foreach ($updates as $update) {
        $type = $update['type'];
        $itemId = $update['item_id'];
        $newQuantity = $update['quantity'];
        $notes = $update['notes'] ?? '';
        
        try {
            switch ($type) {
                case 'material':
                    updateMaterialStock($itemId, $newQuantity, 'physical_count', $notes);
                    break;
                case 'component':
                    updateComponentStock($itemId, $newQuantity, 'physical_count', $notes);
                    break;
                case 'consumable':
                    updateConsumableStock($itemId, $newQuantity, 'physical_count', $notes);
                    break;
                case 'packaging':
                    updatePackagingStock($itemId, $newQuantity, 'physical_count', $notes);
                    break;
                case 'part':
                    updatePartStock($itemId, $newQuantity, 'physical_count', $notes);
                    break;
            }
            $updateCount++;
        } catch (Exception $e) {
            error_log("Inventory update failed for $type $itemId: " . $e->getMessage());
        }
    }
    
    showAlert("Successfully updated $updateCount items!", 'success');
    header('Location: inventory.php');
    exit;
}

// Get all inventory items for quick updating
$inventoryItems = [];

// Materials
$materials = $db->query("SELECT material_id as id, material_name as name, current_stock, unit_of_measure as unit, 'material' as type FROM materials ORDER BY material_name")->fetchAll();
$inventoryItems = array_merge($inventoryItems, $materials);

// Components
$components = $db->query("SELECT component_id as id, component_name as name, current_stock, 'pcs' as unit, 'component' as type FROM components ORDER BY component_name")->fetchAll();
$inventoryItems = array_merge($inventoryItems, $components);

// Consumables
$consumables = $db->query("SELECT consumable_id as id, consumable_name as name, current_stock, container_size as unit, 'consumable' as type FROM consumables ORDER BY consumable_name")->fetchAll();
$inventoryItems = array_merge($inventoryItems, $consumables);

// Packaging
$packaging = $db->query("SELECT packaging_id as id, packaging_name as name, current_stock, 'pcs' as unit, 'packaging' as type FROM packaging ORDER BY packaging_name")->fetchAll();
$inventoryItems = array_merge($inventoryItems, $packaging);

// Parts
$parts = $db->query("SELECT part_id as id, CONCAT(part_number, ' - ', part_name) as name, current_stock, 'pcs' as unit, 'part' as type FROM parts ORDER BY part_number")->fetchAll();
$inventoryItems = array_merge($inventoryItems, $parts);
?>

<div class="row g-3">
    <div class="col-12">
        <h2 class="h4 mb-3">
            <i data-feather="edit" class="me-2"></i>
            Inventory Update
        </h2>
        <p class="text-muted">Update stock levels by entering new counts. Changes are saved when you tap "Save Updates".</p>
    </div>

    <!-- Quick Instructions -->
    <div class="col-12">
        <div class="alert alert-info" role="alert">
            <i data-feather="info" class="me-2"></i>
            <strong>Mobile Tips:</strong>
            <ul class="mb-0 mt-2">
                <li>Tap any quantity to edit it</li>
                <li>Use voice input by tapping the microphone icon (if supported)</li>
                <li>Swipe left on items for quick actions</li>
                <li>Your changes are saved locally until you submit</li>
            </ul>
        </div>
    </div>

    <!-- Search and Filter -->
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-8">
                        <div class="search-container">
                            <input type="text" class="form-control form-control-lg search-input" placeholder="Search items..." data-target=".inventory-item">
                            <i data-feather="search" class="search-icon"></i>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <select class="form-select form-select-lg" id="typeFilter" onchange="filterByType()">
                            <option value="">All Types</option>
                            <option value="material">Materials</option>
                            <option value="component">Components</option>
                            <option value="consumable">Consumables</option>
                            <option value="packaging">Packaging</option>
                            <option value="part">Parts</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Batch Actions -->
    <div class="col-12">
        <div class="card mb-3">
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-6">
                        <button class="btn btn-success btn-lg w-100" onclick="saveAllUpdates()" id="saveButton" disabled>
                            <i data-feather="save" class="me-2"></i>
                            Save Updates
                            <span class="badge bg-light text-dark ms-2" id="updateCount">0</span>
                        </button>
                    </div>
                    <div class="col-6">
                        <button class="btn btn-outline-secondary btn-lg w-100" onclick="clearAllUpdates()">
                            <i data-feather="x" class="me-2"></i>
                            Clear Changes
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Inventory Items -->
    <div class="col-12">
        <div class="row g-2" id="inventoryList">
            <?php foreach ($inventoryItems as $item): ?>
                <div class="col-12 col-md-6 col-lg-4 inventory-item" data-type="<?= $item['type'] ?>">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div class="flex-grow-1">
                                    <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                    <small class="text-muted badge bg-light text-dark"><?= ucfirst($item['type']) ?></small>
                                </div>
                            </div>
                            
                            <div class="row g-2 align-items-center">
                                <div class="col-8">
                                    <div class="input-group">
                                        <input type="number" 
                                               class="form-control form-control-lg text-center auto-focus voice-input-enabled" 
                                               value="<?= $item['current_stock'] ?>" 
                                               data-original="<?= $item['current_stock'] ?>"
                                               data-type="<?= $item['type'] ?>"
                                               data-id="<?= $item['id'] ?>"
                                               data-name="<?= htmlspecialchars($item['name']) ?>"
                                               min="0" 
                                               step="<?= $item['type'] === 'material' ? '0.01' : '1' ?>"
                                               onchange="markAsUpdated(this)"
                                               style="font-size: 1.2rem; font-weight: 600;">
                                        <span class="input-group-text"><?= htmlspecialchars($item['unit'] ?? 'pcs') ?></span>
                                    </div>
                                </div>
                                <div class="col-4">
                                    <button class="btn btn-outline-primary btn-sm w-100" onclick="showNotesModal(this)" data-notes="">
                                        <i data-feather="message-circle" style="width: 16px; height: 16px;"></i>
                                        Notes
                                    </button>
                                </div>
                            </div>
                            
                            <div class="mt-2">
                                <small class="text-muted">
                                    Original: <?= formatNumber($item['current_stock'], $item['type'] === 'material' ? 2 : 0) ?>
                                    <?= htmlspecialchars($item['unit'] ?? 'pcs') ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Notes Modal -->
<div class="modal fade" id="notesModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Notes - <span id="notesItemName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="form-floating">
                    <textarea class="form-control" id="notesText" style="height: 100px;" placeholder="Add notes about this count..."></textarea>
                    <label for="notesText">Notes</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="saveNotes()">Save Notes</button>
            </div>
        </div>
    </div>
</div>

<script>
let pendingUpdates = [];
let currentNotesButton = null;

function markAsUpdated(input) {
    const originalValue = parseFloat(input.dataset.original);
    const newValue = parseFloat(input.value) || 0;
    const card = input.closest('.card');
    
    if (newValue !== originalValue) {
        card.classList.add('border-warning');
        card.style.boxShadow = '0 0 10px rgba(255, 193, 7, 0.3)';
        
        // Update pending updates
        const update = {
            type: input.dataset.type,
            item_id: input.dataset.id,
            name: input.dataset.name,
            quantity: newValue,
            notes: input.closest('.card').querySelector('[data-notes]').dataset.notes || ''
        };
        
        // Remove existing update for this item
        pendingUpdates = pendingUpdates.filter(u => !(u.type === update.type && u.item_id === update.item_id));
        
        // Add new update
        pendingUpdates.push(update);
    } else {
        card.classList.remove('border-warning');
        card.style.boxShadow = '';
        
        // Remove from pending updates
        pendingUpdates = pendingUpdates.filter(u => !(u.type === input.dataset.type && u.item_id === input.dataset.id));
    }
    
    updateSaveButton();
}

function updateSaveButton() {
    const saveButton = document.getElementById('saveButton');
    const updateCount = document.getElementById('updateCount');
    
    updateCount.textContent = pendingUpdates.length;
    saveButton.disabled = pendingUpdates.length === 0;
    
    if (pendingUpdates.length > 0) {
        saveButton.classList.remove('btn-success');
        saveButton.classList.add('btn-warning');
    } else {
        saveButton.classList.remove('btn-warning');
        saveButton.classList.add('btn-success');
    }
}

function showNotesModal(button) {
    currentNotesButton = button;
    const card = button.closest('.card');
    const itemName = card.querySelector('h6').textContent;
    const currentNotes = button.dataset.notes || '';
    
    document.getElementById('notesItemName').textContent = itemName;
    document.getElementById('notesText').value = currentNotes;
    
    new bootstrap.Modal(document.getElementById('notesModal')).show();
}

function saveNotes() {
    const notes = document.getElementById('notesText').value;
    currentNotesButton.dataset.notes = notes;
    
    // Update button appearance
    if (notes.trim()) {
        currentNotesButton.classList.remove('btn-outline-primary');
        currentNotesButton.classList.add('btn-primary');
    } else {
        currentNotesButton.classList.remove('btn-primary');
        currentNotesButton.classList.add('btn-outline-primary');
    }
    
    // Trigger update for this item
    const input = currentNotesButton.closest('.card').querySelector('input[type="number"]');
    markAsUpdated(input);
    
    bootstrap.Modal.getInstance(document.getElementById('notesModal')).hide();
}

function saveAllUpdates() {
    if (pendingUpdates.length === 0) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.style.display = 'none';
    
    // Add CSRF token
    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = document.getElementById('csrf-token').value;
    form.appendChild(csrfInput);
    
    // Add batch updates
    const updatesInput = document.createElement('input');
    updatesInput.type = 'hidden';
    updatesInput.name = 'batch_updates';
    updatesInput.value = JSON.stringify(pendingUpdates);
    form.appendChild(updatesInput);
    
    document.body.appendChild(form);
    
    // Show loading state
    const saveButton = document.getElementById('saveButton');
    saveButton.innerHTML = '<i data-feather="loader" class="me-2"></i>Saving...';
    saveButton.disabled = true;
    
    form.submit();
}

function clearAllUpdates() {
    pendingUpdates = [];
    
    // Reset all inputs to original values
    document.querySelectorAll('input[type="number"]').forEach(input => {
        input.value = input.dataset.original;
        const card = input.closest('.card');
        card.classList.remove('border-warning');
        card.style.boxShadow = '';
    });
    
    // Reset all notes buttons
    document.querySelectorAll('[data-notes]').forEach(button => {
        button.dataset.notes = '';
        button.classList.remove('btn-primary');
        button.classList.add('btn-outline-primary');
    });
    
    updateSaveButton();
}

function filterByType() {
    const selectedType = document.getElementById('typeFilter').value;
    const items = document.querySelectorAll('.inventory-item');
    
    items.forEach(item => {
        if (selectedType === '' || item.dataset.type === selectedType) {
            item.style.display = '';
        } else {
            item.style.display = 'none';
        }
    });
}

// Auto-save to localStorage for offline support
function saveToLocalStorage() {
    localStorage.setItem('inventoryUpdates', JSON.stringify(pendingUpdates));
}

function loadFromLocalStorage() {
    const saved = localStorage.getItem('inventoryUpdates');
    if (saved) {
        pendingUpdates = JSON.parse(saved);
        
        // Apply saved updates to the form
        pendingUpdates.forEach(update => {
            const input = document.querySelector(`input[data-type="${update.type}"][data-id="${update.item_id}"]`);
            if (input) {
                input.value = update.quantity;
                markAsUpdated(input);
                
                if (update.notes) {
                    const notesButton = input.closest('.card').querySelector('[data-notes]');
                    notesButton.dataset.notes = update.notes;
                    notesButton.classList.remove('btn-outline-primary');
                    notesButton.classList.add('btn-primary');
                }
            }
        });
    }
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
    loadFromLocalStorage();
    updateSaveButton();
    
    // Save to localStorage on changes
    document.addEventListener('input', saveToLocalStorage);
    
    // Focus first input on mobile
    if (window.innerWidth < 768) {
        const firstInput = document.querySelector('input[type="number"]');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 500);
        }
    }
});

// Handle offline status
window.addEventListener('offline', () => {
    showAlert('You are offline. Changes will be saved locally.', 'warning');
});

window.addEventListener('online', () => {
    if (pendingUpdates.length > 0) {
        showAlert('Connection restored. You have unsaved changes.', 'info');
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>