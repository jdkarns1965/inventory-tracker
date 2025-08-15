<?php
$pageTitle = 'Inventory Status';
require_once 'includes/header.php';
require_once 'includes/auth_check.php';

checkPermissionOrRedirect('view_reports');

$db = getDatabase();

// Get comprehensive inventory status
$summary = getInventorySummary();
$lowStockItems = getLowStockItems();

// Get recent transactions
$recentTransactions = $db->query("
    SELECT it.*, u.full_name, u.username,
           CASE 
               WHEN it.transaction_type = 'material' THEN m.material_name
               WHEN it.transaction_type = 'component' THEN c.component_name
               WHEN it.transaction_type = 'consumable' THEN cs.consumable_name
               WHEN it.transaction_type = 'packaging' THEN p.packaging_name
               WHEN it.transaction_type = 'finished_part' THEN pt.part_number
           END as item_name
    FROM inventory_transactions it 
    LEFT JOIN users u ON it.user_id = u.user_id
    LEFT JOIN materials m ON it.transaction_type = 'material' AND it.item_id = m.material_id
    LEFT JOIN components c ON it.transaction_type = 'component' AND it.item_id = c.component_id
    LEFT JOIN consumables cs ON it.transaction_type = 'consumable' AND it.item_id = cs.consumable_id
    LEFT JOIN packaging p ON it.transaction_type = 'packaging' AND it.item_id = p.packaging_id
    LEFT JOIN parts pt ON it.transaction_type = 'finished_part' AND it.item_id = pt.part_id
    ORDER BY it.transaction_date DESC 
    LIMIT 20
")->fetchAll();

// Get stock value estimates (simplified)
$stockValues = $db->query("
    SELECT 
        'Materials' as category,
        COUNT(*) as item_count,
        SUM(current_stock) as total_quantity,
        'Various' as unit
    FROM materials
    WHERE current_stock > 0
    UNION ALL
    SELECT 
        'Components' as category,
        COUNT(*) as item_count,
        SUM(current_stock) as total_quantity,
        'pcs' as unit
    FROM components
    WHERE current_stock > 0
    UNION ALL
    SELECT 
        'Consumables' as category,
        COUNT(*) as item_count,
        SUM(current_stock) as total_quantity,
        'containers' as unit
    FROM consumables
    WHERE current_stock > 0
    UNION ALL
    SELECT 
        'Packaging' as category,
        COUNT(*) as item_count,
        SUM(current_stock) as total_quantity,
        'pcs' as unit
    FROM packaging
    WHERE current_stock > 0
    UNION ALL
    SELECT 
        'Finished Parts' as category,
        COUNT(*) as item_count,
        SUM(current_stock) as total_quantity,
        'pcs' as unit
    FROM parts
    WHERE current_stock > 0
")->fetchAll();
?>

<div class="row g-3">
    <div class="col-12">
        <h2 class="h4 mb-3">
            <i data-feather="bar-chart-2" class="me-2"></i>
            Inventory Status Report
        </h2>
    </div>

    <!-- Status Overview Cards -->
    <div class="col-12">
        <div class="row g-2 mb-4">
            <div class="col-6 col-md-2">
                <div class="metric-card bg-primary">
                    <div class="metric-value"><?= $summary['materials']['total'] ?></div>
                    <div class="metric-label">Materials</div>
                    <?php if ($summary['materials']['low_stock'] > 0): ?>
                        <small class="opacity-75"><?= $summary['materials']['low_stock'] ?> low</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-success">
                    <div class="metric-value"><?= $summary['components']['total'] ?></div>
                    <div class="metric-label">Components</div>
                    <?php if ($summary['components']['low_stock'] > 0): ?>
                        <small class="opacity-75"><?= $summary['components']['low_stock'] ?> low</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-warning">
                    <div class="metric-value"><?= $summary['consumables']['total'] ?></div>
                    <div class="metric-label">Consumables</div>
                    <?php if ($summary['consumables']['low_stock'] > 0): ?>
                        <small class="opacity-75"><?= $summary['consumables']['low_stock'] ?> low</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['packaging']['total'] ?></div>
                    <div class="metric-label">Packaging</div>
                    <?php if ($summary['packaging']['low_stock'] > 0): ?>
                        <small class="opacity-75"><?= $summary['packaging']['low_stock'] ?> low</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-secondary">
                    <div class="metric-value"><?= $summary['parts']['total'] ?></div>
                    <div class="metric-label">Parts</div>
                    <?php if ($summary['parts']['low_stock'] > 0): ?>
                        <small class="opacity-75"><?= $summary['parts']['low_stock'] ?> low</small>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-6 col-md-2">
                <div class="metric-card bg-danger">
                    <div class="metric-value"><?= count($lowStockItems) ?></div>
                    <div class="metric-label">Total Alerts</div>
                    <small class="opacity-75">Reorder needed</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Stock Value Summary -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i data-feather="trending-up" class="me-2"></i>
                    Stock Summary
                </h5>
            </div>
            <div class="card-body">
                <?php if (empty($stockValues)): ?>
                    <p class="text-muted">No stock data available.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Category</th>
                                    <th class="text-end">Items</th>
                                    <th class="text-end">Quantity</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($stockValues as $value): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($value['category']) ?></td>
                                        <td class="text-end"><?= formatNumber($value['item_count']) ?></td>
                                        <td class="text-end">
                                            <?= formatNumber($value['total_quantity'], 0) ?> 
                                            <small class="text-muted"><?= htmlspecialchars($value['unit']) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Low Stock Alerts -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i data-feather="alert-triangle" class="me-2 text-warning"></i>
                    Stock Alerts
                </h5>
                <span class="badge bg-warning"><?= count($lowStockItems) ?></span>
            </div>
            <div class="card-body p-0" style="max-height: 300px; overflow-y: auto;">
                <?php if (empty($lowStockItems)): ?>
                    <div class="p-3 text-center">
                        <i data-feather="check-circle" class="text-success mb-2" style="width: 32px; height: 32px;"></i>
                        <p class="text-muted mb-0">All items are above reorder points!</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($lowStockItems as $item): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                        <small class="text-muted"><?= ucfirst($item['type']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold text-<?= getStockStatusClass($item['current_stock'], $item['reorder_point']) ?>">
                                            <?= formatNumber($item['current_stock']) ?>
                                        </div>
                                        <small class="text-muted">Need: <?= formatNumber($item['reorder_point']) ?></small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <?php if (!empty($lowStockItems)): ?>
                <div class="card-footer text-center">
                    <a href="reorder.php" class="btn btn-warning btn-sm">
                        <i data-feather="shopping-cart" class="me-1"></i>
                        View Reorder List
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Activity -->
    <?php if (hasPermission('view_transactions')): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i data-feather="activity" class="me-2"></i>
                        Recent Inventory Activity
                    </h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($recentTransactions)): ?>
                        <div class="p-3 text-center">
                            <p class="text-muted mb-0">No recent transactions found.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th>Date/Time</th>
                                        <th>Item</th>
                                        <th>Action</th>
                                        <th class="text-end">Quantity</th>
                                        <th>User</th>
                                        <th>Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recentTransactions as $transaction): ?>
                                        <tr>
                                            <td>
                                                <small>
                                                    <?= formatDate($transaction['transaction_date']) ?><br>
                                                    <span class="text-muted"><?= date('g:i A', strtotime($transaction['transaction_date'])) ?></span>
                                                </small>
                                            </td>
                                            <td>
                                                <div>
                                                    <?= htmlspecialchars($transaction['item_name'] ?? 'Unknown') ?>
                                                </div>
                                                <small class="text-muted"><?= ucfirst($transaction['transaction_type']) ?></small>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?= 
                                                    $transaction['transaction_action'] === 'received' ? 'success' : 
                                                    ($transaction['transaction_action'] === 'used' ? 'danger' : 'primary') 
                                                ?>">
                                                    <?= ucfirst(str_replace('_', ' ', $transaction['transaction_action'])) ?>
                                                </span>
                                            </td>
                                            <td class="text-end fw-bold">
                                                <?= formatNumber($transaction['new_quantity'], 
                                                    $transaction['transaction_type'] === 'material' ? 2 : 0) ?>
                                            </td>
                                            <td>
                                                <small><?= htmlspecialchars($transaction['full_name'] ?? $transaction['username'] ?? 'System') ?></small>
                                            </td>
                                            <td>
                                                <small class="text-muted">
                                                    <?= $transaction['notes'] ? htmlspecialchars(substr($transaction['notes'], 0, 50)) . (strlen($transaction['notes']) > 50 ? '...' : '') : '-' ?>
                                                </small>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>

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
                    <?php if (hasPermission('update_inventory')): ?>
                        <div class="col-6 col-md-3">
                            <a href="inventory.php" class="btn btn-primary w-100">
                                <i data-feather="edit" class="me-1"></i>
                                Update Inventory
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('reorder_management')): ?>
                        <div class="col-6 col-md-3">
                            <a href="reorder.php" class="btn btn-warning w-100">
                                <i data-feather="shopping-cart" class="me-1"></i>
                                Reorder List
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('production_planning')): ?>
                        <div class="col-6 col-md-3">
                            <a href="production.php" class="btn btn-info w-100">
                                <i data-feather="activity" class="me-1"></i>
                                Production Planning
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (hasPermission('export_data')): ?>
                        <div class="col-6 col-md-3">
                            <button class="btn btn-success w-100" onclick="exportReport()">
                                <i data-feather="download" class="me-1"></i>
                                Export Report
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function exportReport() {
    // Simple CSV export of current status
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Category,Total Items,Low Stock Items\n";
    
    // Add summary data
    const categories = ['Materials', 'Components', 'Consumables', 'Packaging', 'Parts'];
    const totals = [<?= $summary['materials']['total'] ?>, <?= $summary['components']['total'] ?>, <?= $summary['consumables']['total'] ?>, <?= $summary['packaging']['total'] ?>, <?= $summary['parts']['total'] ?>];
    const lowStock = [<?= $summary['materials']['low_stock'] ?>, <?= $summary['components']['low_stock'] ?>, <?= $summary['consumables']['low_stock'] ?>, <?= $summary['packaging']['low_stock'] ?>, <?= $summary['parts']['low_stock'] ?>];
    
    for (let i = 0; i < categories.length; i++) {
        csvContent += categories[i] + "," + totals[i] + "," + lowStock[i] + "\n";
    }
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "inventory_status_" + new Date().toISOString().split('T')[0] + ".csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    
    showAlert('Report exported successfully!', 'success');
}

// Auto-refresh status every 60 seconds
setInterval(() => {
    if (document.visibilityState === 'visible') {
        location.reload();
    }
}, 60000);

// Initialize page
document.addEventListener('DOMContentLoaded', function() {
    feather.replace();
});
</script>

<?php require_once 'includes/footer.php'; ?>