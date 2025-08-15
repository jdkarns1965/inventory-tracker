<?php
$pageTitle = 'Reorder Management';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('reorder_management');

$db = getDatabase();

// Get all low stock items
$lowStockItems = getLowStockItems();

// Group by type for better organization
$itemsByType = [
    'material' => [],
    'component' => [],
    'consumable' => [],
    'packaging' => [],
    'part' => []
];

foreach ($lowStockItems as $item) {
    $itemsByType[$item['type']][] = $item;
}

// Get supplier summary
$supplierSummary = $db->query("
    SELECT supplier, COUNT(*) as item_count, 'material' as type FROM materials WHERE current_stock <= reorder_point AND supplier IS NOT NULL AND supplier != ''
    UNION ALL
    SELECT supplier, COUNT(*) as item_count, 'component' as type FROM components WHERE current_stock <= reorder_point AND supplier IS NOT NULL AND supplier != ''
    UNION ALL
    SELECT supplier, COUNT(*) as item_count, 'consumable' as type FROM consumables WHERE current_stock <= reorder_point AND supplier IS NOT NULL AND supplier != ''
    UNION ALL
    SELECT supplier, COUNT(*) as item_count, 'packaging' as type FROM packaging WHERE current_stock <= reorder_point AND supplier IS NOT NULL AND supplier != ''
    ORDER BY item_count DESC
")->fetchAll();

// Calculate urgency (based on lead times and stock levels)
function getUrgencyLevel($currentStock, $reorderPoint, $leadTime = null) {
    if ($currentStock <= 0) return ['level' => 'critical', 'text' => 'Critical', 'class' => 'danger'];
    if ($currentStock <= ($reorderPoint * 0.5)) return ['level' => 'high', 'text' => 'High', 'class' => 'warning'];
    return ['level' => 'normal', 'text' => 'Normal', 'class' => 'info'];
}
?>

<div class="row g-3">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="h4 mb-0">
                <i data-feather="shopping-cart" class="me-2"></i>
                Reorder Management
            </h2>
            <button class="btn btn-success" onclick="exportReorderList()">
                <i data-feather="download" class="me-1"></i>
                Export List
            </button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-2">
                <div class="metric-card bg-danger">
                    <div class="metric-value"><?= count($lowStockItems) ?></div>
                    <div class="metric-label">Total Items</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-primary">
                    <div class="metric-value"><?= count($itemsByType['material']) ?></div>
                    <div class="metric-label">Materials</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-success">
                    <div class="metric-value"><?= count($itemsByType['component']) ?></div>
                    <div class="metric-label">Components</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-warning">
                    <div class="metric-value"><?= count($itemsByType['consumable']) ?></div>
                    <div class="metric-label">Consumables</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= count($itemsByType['packaging']) ?></div>
                    <div class="metric-label">Packaging</div>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-secondary">
                    <div class="metric-value"><?= count($itemsByType['part']) ?></div>
                    <div class="metric-label">Parts</div>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($lowStockItems)): ?>
        <!-- No Items to Reorder -->
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i data-feather="check-circle" class="text-success mb-3" style="width: 64px; height: 64px;"></i>
                    <h4 class="text-success">All Stock Levels Good!</h4>
                    <p class="text-muted">No items are currently below their reorder points.</p>
                    <a href="status.php" class="btn btn-primary">
                        <i data-feather="bar-chart-2" class="me-1"></i>
                        View Status Report
                    </a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <!-- Supplier Summary -->
        <?php if (!empty($supplierSummary)): ?>
            <div class="col-12">
                <div class="card mb-3">
                    <div class="card-header">
                        <h5 class="mb-0">
                            <i data-feather="truck" class="me-2"></i>
                            Suppliers Needing Orders
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-2">
                            <?php foreach (array_slice($supplierSummary, 0, 6) as $supplier): ?>
                                <div class="col-6 col-md-4 col-lg-2">
                                    <div class="text-center p-2 bg-light rounded">
                                        <div class="fw-bold"><?= $supplier['item_count'] ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($supplier['supplier']) ?></small>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Reorder Items by Category -->
        <?php foreach ($itemsByType as $type => $items): ?>
            <?php if (!empty($items)): ?>
                <div class="col-12">
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i data-feather="<?= 
                                    $type === 'material' ? 'box' : 
                                    ($type === 'component' ? 'cpu' : 
                                    ($type === 'consumable' ? 'droplet' : 
                                    ($type === 'packaging' ? 'package' : 'package-2')))
                                ?>" class="me-2"></i>
                                <?= ucfirst($type === 'part' ? 'Parts' : $type . 's') ?>
                            </h5>
                            <span class="badge bg-<?= 
                                $type === 'material' ? 'primary' : 
                                ($type === 'component' ? 'success' : 
                                ($type === 'consumable' ? 'warning' : 
                                ($type === 'packaging' ? 'info' : 'secondary')))
                            ?>"><?= count($items) ?></span>
                        </div>
                        <div class="card-body p-0">
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center">Current</th>
                                            <th class="text-center">Reorder At</th>
                                            <th class="text-center">Suggested Order</th>
                                            <th>Supplier</th>
                                            <th class="text-center">Lead Time</th>
                                            <th class="text-center">Urgency</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                            <?php
                                            $suggestedOrder = max($item['reorder_point'] * 2 - $item['current_stock'], $item['reorder_point']);
                                            $urgency = getUrgencyLevel($item['current_stock'], $item['reorder_point']);
                                            
                                            // Get additional details for this item
                                            $details = $db->query("
                                                SELECT supplier, lead_time_days, unit_of_measure as unit
                                                FROM {$type}s 
                                                WHERE {$type}_id = ?
                                            ", [$item['id']])->fetch();
                                            ?>
                                            <tr>
                                                <td>
                                                    <div class="fw-bold"><?= htmlspecialchars($item['name']) ?></div>
                                                    <small class="text-muted"><?= ucfirst($type) ?></small>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?= getStockStatusClass($item['current_stock'], $item['reorder_point']) ?>">
                                                        <?= formatNumber($item['current_stock'], $type === 'material' ? 2 : 0) ?>
                                                    </span>
                                                </td>
                                                <td class="text-center">
                                                    <?= formatNumber($item['reorder_point'], $type === 'material' ? 2 : 0) ?>
                                                </td>
                                                <td class="text-center">
                                                    <strong><?= formatNumber($suggestedOrder, $type === 'material' ? 2 : 0) ?></strong>
                                                    <?php if ($details && $details['unit']): ?>
                                                        <br><small class="text-muted"><?= htmlspecialchars($details['unit']) ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($details && $details['supplier']): ?>
                                                        <?= htmlspecialchars($details['supplier']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">Not specified</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <?php if ($details && $details['lead_time_days']): ?>
                                                        <?= $details['lead_time_days'] ?> days
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="text-center">
                                                    <span class="badge bg-<?= $urgency['class'] ?>"><?= $urgency['text'] ?></span>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <!-- Quick Actions -->
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i data-feather="zap" class="me-2"></i>
                        Quick Actions
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-2">
                        <div class="col-6 col-md-3">
                            <button class="btn btn-primary w-100" onclick="printReorderList()">
                                <i data-feather="printer" class="me-1"></i>
                                Print List
                            </button>
                        </div>
                        <div class="col-6 col-md-3">
                            <button class="btn btn-success w-100" onclick="exportReorderList()">
                                <i data-feather="download" class="me-1"></i>
                                Export CSV
                            </button>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="inventory.php" class="btn btn-warning w-100">
                                <i data-feather="edit" class="me-1"></i>
                                Update Inventory
                            </a>
                        </div>
                        <div class="col-6 col-md-3">
                            <a href="status.php" class="btn btn-info w-100">
                                <i data-feather="bar-chart-2" class="me-1"></i>
                                Status Report
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
function exportReorderList() {
    // Create CSV content
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Category,Item Name,Current Stock,Reorder Point,Suggested Order,Supplier,Lead Time (Days),Urgency\n";
    
    // Add data from tables
    document.querySelectorAll('table tbody tr').forEach(row => {
        const cells = row.querySelectorAll('td');
        if (cells.length >= 7) {
            const category = cells[0].querySelector('.text-muted').textContent;
            const itemName = cells[0].querySelector('.fw-bold').textContent;
            const currentStock = cells[1].querySelector('.badge').textContent;
            const reorderPoint = cells[2].textContent.trim();
            const suggestedOrder = cells[3].querySelector('strong').textContent;
            const supplier = cells[4].textContent.trim();
            const leadTime = cells[5].textContent.trim();
            const urgency = cells[6].querySelector('.badge').textContent;
            
            csvContent += `"${category}","${itemName}","${currentStock}","${reorderPoint}","${suggestedOrder}","${supplier}","${leadTime}","${urgency}"\n`;
        }
    });
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "reorder_list_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showAlert('Reorder list exported successfully!', 'success');
}

function printReorderList() {
    window.print();
}

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
});
</script>

<!-- Print Styles -->
<style media="print">
    .btn, .card-header .badge, .navbar, .fab-container {
        display: none !important;
    }
    
    .card {
        break-inside: avoid;
        border: 1px solid #000 !important;
        margin-bottom: 20px;
    }
    
    .table {
        font-size: 12px;
    }
    
    .metric-card {
        border: 1px solid #000;
        text-align: center;
        padding: 10px;
    }
</style>

<?php require_once 'includes/footer.php'; ?>