<?php
$pageTitle = 'Dashboard';
require_once 'includes/header.php';

// Get inventory summary
$summary = getInventorySummary();
$lowStockItems = getLowStockItems();

// Get recent transactions (last 10)
$db = getDatabase();
$recentTransactions = $db->query("
    SELECT it.*, u.full_name, u.username 
    FROM inventory_transactions it 
    LEFT JOIN users u ON it.user_id = u.user_id 
    ORDER BY it.transaction_date DESC 
    LIMIT 10
")->fetchAll();
?>

<div class="row g-3">
    <!-- Quick Metrics -->
    <div class="col-12">
        <h2 class="h4 mb-3">
            <i data-feather="bar-chart-2" class="me-2"></i>
            Inventory Overview
        </h2>
        
        <div class="swipe-container">
            <div class="swipe-item">
                <div class="metric-card">
                    <div class="metric-value"><?= $summary['materials']['total'] ?></div>
                    <div class="metric-label">Materials</div>
                    <?php if ($summary['materials']['low_stock'] > 0): ?>
                        <small class="d-block mt-2 opacity-75">
                            <?= $summary['materials']['low_stock'] ?> need reorder
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="swipe-item">
                <div class="metric-card bg-success">
                    <div class="metric-value"><?= $summary['components']['total'] ?></div>
                    <div class="metric-label">Components</div>
                    <?php if ($summary['components']['low_stock'] > 0): ?>
                        <small class="d-block mt-2 opacity-75">
                            <?= $summary['components']['low_stock'] ?> need reorder
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="swipe-item">
                <div class="metric-card bg-warning">
                    <div class="metric-value"><?= $summary['consumables']['total'] ?></div>
                    <div class="metric-label">Consumables</div>
                    <?php if ($summary['consumables']['low_stock'] > 0): ?>
                        <small class="d-block mt-2 opacity-75">
                            <?= $summary['consumables']['low_stock'] ?> containers low
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="swipe-item">
                <div class="metric-card bg-info">
                    <div class="metric-value"><?= $summary['packaging']['total'] ?></div>
                    <div class="metric-label">Packaging</div>
                    <?php if ($summary['packaging']['low_stock'] > 0): ?>
                        <small class="d-block mt-2 opacity-75">
                            <?= $summary['packaging']['low_stock'] ?> need reorder
                        </small>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="swipe-item">
                <div class="metric-card bg-secondary">
                    <div class="metric-value"><?= $summary['parts']['total'] ?></div>
                    <div class="metric-label">Finished Parts</div>
                    <?php if ($summary['parts']['low_stock'] > 0): ?>
                        <small class="d-block mt-2 opacity-75">
                            <?= $summary['parts']['low_stock'] ?> below reorder
                        </small>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Quick Actions -->
    <div class="col-12">
        <h3 class="h5 mb-3">
            <i data-feather="zap" class="me-2"></i>
            Quick Actions
        </h3>
        
        <div class="row g-2">
            <?php if (hasPermission('update_inventory')): ?>
                <div class="col-6 col-md-3">
                    <a href="inventory.php" class="btn btn-primary quick-action-btn w-100">
                        <i data-feather="edit" class="mb-2" style="width: 24px; height: 24px;"></i>
                        <div>Update Inventory</div>
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (hasPermission('view_reports')): ?>
                <div class="col-6 col-md-3">
                    <a href="status.php" class="btn btn-success quick-action-btn w-100">
                        <i data-feather="bar-chart" class="mb-2" style="width: 24px; height: 24px;"></i>
                        <div>View Status</div>
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (hasPermission('reorder_management')): ?>
                <div class="col-6 col-md-3">
                    <a href="reorder.php" class="btn btn-warning quick-action-btn w-100">
                        <i data-feather="shopping-cart" class="mb-2" style="width: 24px; height: 24px;"></i>
                        <div>Reorder List</div>
                    </a>
                </div>
            <?php endif; ?>
            
            <?php if (hasPermission('production_planning')): ?>
                <div class="col-6 col-md-3">
                    <a href="production.php" class="btn btn-info quick-action-btn w-100">
                        <i data-feather="activity" class="mb-2" style="width: 24px; height: 24px;"></i>
                        <div>Production</div>
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Low Stock Alerts -->
    <?php if (!empty($lowStockItems)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="h6 mb-0">
                        <i data-feather="alert-triangle" class="me-2 text-warning"></i>
                        Stock Alerts
                    </h3>
                    <span class="badge bg-warning"><?= count($lowStockItems) ?></span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($lowStockItems, 0, 5) as $item): ?>
                            <div class="list-group-item <?= getStockStatusClass($item['current_stock'], $item['reorder_point']) ?>">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="mb-1"><?= htmlspecialchars($item['name']) ?></h6>
                                        <small class="text-muted"><?= ucfirst($item['type']) ?></small>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?= formatNumber($item['current_stock']) ?></div>
                                        <small class="text-muted">Reorder: <?= formatNumber($item['reorder_point']) ?></small>
                                    </div>
                                </div>
                                <div class="stock-progress mt-2">
                                    <div class="stock-progress-bar bg-<?= getStockStatusClass($item['current_stock'], $item['reorder_point']) ?>" 
                                         style="width: <?= min(($item['current_stock'] / max($item['reorder_point'] * 2, 1)) * 100, 100) ?>%"></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        
                        <?php if (count($lowStockItems) > 5): ?>
                            <div class="list-group-item text-center">
                                <a href="reorder.php" class="btn btn-sm btn-outline-primary">
                                    View All <?= count($lowStockItems) ?> Items
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Recent Activity -->
    <?php if (hasPermission('view_transactions') && !empty($recentTransactions)): ?>
        <div class="col-12 col-lg-6">
            <div class="card">
                <div class="card-header">
                    <h3 class="h6 mb-0">
                        <i data-feather="clock" class="me-2"></i>
                        Recent Activity
                    </h3>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($recentTransactions, 0, 5) as $transaction): ?>
                            <div class="list-group-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="flex-grow-1">
                                        <div class="d-flex align-items-center mb-1">
                                            <?php
                                            $iconMap = [
                                                'material' => 'box',
                                                'component' => 'cpu',
                                                'consumable' => 'droplet',
                                                'packaging' => 'package',
                                                'finished_part' => 'package-2'
                                            ];
                                            $icon = $iconMap[$transaction['transaction_type']] ?? 'circle';
                                            ?>
                                            <i data-feather="<?= $icon ?>" class="me-2 text-muted" style="width: 16px; height: 16px;"></i>
                                            <span class="badge bg-light text-dark"><?= ucfirst($transaction['transaction_action']) ?></span>
                                        </div>
                                        <small class="text-muted">
                                            <?= ucfirst($transaction['transaction_type']) ?> #<?= $transaction['item_id'] ?>
                                            • <?= timeAgo($transaction['transaction_date']) ?>
                                        </small>
                                        <?php if ($transaction['notes']): ?>
                                            <div class="small text-muted mt-1">
                                                <?= htmlspecialchars($transaction['notes']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="text-end">
                                        <div class="fw-bold"><?= formatNumber($transaction['new_quantity']) ?></div>
                                        <small class="text-muted">
                                            <?= htmlspecialchars($transaction['full_name'] ?? $transaction['username'] ?? 'System') ?>
                                        </small>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- System Status -->
    <div class="col-12 col-lg-6">
        <div class="card">
            <div class="card-header">
                <h3 class="h6 mb-0">
                    <i data-feather="info" class="me-2"></i>
                    System Status
                </h3>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-6">
                        <div class="text-center">
                            <div class="text-success mb-2">
                                <i data-feather="check-circle" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="fw-bold">Online</div>
                            <small class="text-muted">System Status</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="text-center">
                            <div class="text-primary mb-2">
                                <i data-feather="users" style="width: 32px; height: 32px;"></i>
                            </div>
                            <div class="fw-bold"><?= htmlspecialchars($_SESSION['role']) ?></div>
                            <small class="text-muted">Your Role</small>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="text-center">
                            <small class="text-muted">
                                Last login: <?= isset($_SESSION['last_login']) ? formatDateTime($_SESSION['last_login']) : 'Welcome!' ?>
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Tips -->
                <div class="mt-4">
                    <h6 class="text-muted mb-2">💡 Quick Tips:</h6>
                    <ul class="list-unstyled small text-muted">
                        <li class="mb-1">• Swipe left/right on cards for quick actions</li>
                        <li class="mb-1">• Pull down to refresh data</li>
                        <li class="mb-1">• Use voice input for quantities (if supported)</li>
                        <li>• Add to home screen for app-like experience</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Install Prompt for PWA -->
<div id="installPrompt" class="d-none">
    <div class="alert alert-primary alert-dismissible fade show" role="alert">
        <i data-feather="download" class="me-2"></i>
        <strong>Install App:</strong> Add to your home screen for quick access!
        <button type="button" class="btn btn-sm btn-primary ms-2" onclick="installApp()">Install</button>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
</div>

<script>
// PWA Install Prompt
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    deferredPrompt = e;
    document.getElementById('installPrompt').classList.remove('d-none');
});

function installApp() {
    if (deferredPrompt) {
        deferredPrompt.prompt();
        deferredPrompt.userChoice.then((choiceResult) => {
            if (choiceResult.outcome === 'accepted') {
                console.log('User accepted the install prompt');
            }
            deferredPrompt = null;
            document.getElementById('installPrompt').classList.add('d-none');
        });
    }
}

// Auto-refresh metrics every 30 seconds
setInterval(() => {
    if (document.visibilityState === 'visible') {
        // Refresh metrics without full page reload
        fetch('api/metrics.php')
            .then(response => response.json())
            .then(data => {
                // Update metric values with animation
                document.querySelectorAll('.metric-value').forEach((element, index) => {
                    const newValue = Object.values(data)[index]?.total || 0;
                    const currentValue = parseInt(element.textContent) || 0;
                    if (newValue !== currentValue) {
                        animateNumber(element, currentValue, newValue);
                    }
                });
            })
            .catch(error => console.log('Metrics refresh failed:', error));
    }
}, 30000);

// Initialize tooltips for mobile
document.addEventListener('DOMContentLoaded', function() {
    // Add touch feedback to metric cards
    document.querySelectorAll('.metric-card').forEach(card => {
        card.addEventListener('touchstart', function() {
            this.style.transform = 'scale(0.98)';
        });
        card.addEventListener('touchend', function() {
            this.style.transform = 'scale(1)';
        });
    });
});
</script>

<?php require_once 'includes/footer.php'; ?>