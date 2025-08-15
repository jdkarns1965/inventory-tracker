<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

function formatNumber($number, $decimals = 0) {
    return number_format($number, $decimals);
}

function formatCurrency($amount) {
    return '$' . number_format($amount, 2);
}

function formatDate($date) {
    return date('M j, Y', strtotime($date));
}

function formatDateTime($datetime) {
    return date('M j, Y g:i A', strtotime($datetime));
}

function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' min ago';
    if ($time < 86400) return floor($time/3600) . ' hr ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    return floor($time/31536000) . ' years ago';
}

function getStockStatusClass($current, $reorder) {
    if ($current <= 0) return 'danger';
    if ($current <= $reorder) return 'warning';
    return 'success';
}

function getStockStatusText($current, $reorder) {
    if ($current <= 0) return 'Out of Stock';
    if ($current <= $reorder) return 'Low Stock';
    return 'In Stock';
}

function calculateMaxParts($mold_id, $available_material) {
    $db = getDatabase();
    $stmt = $db->prepare("SELECT total_cavities, shot_size FROM molds WHERE mold_id = ?");
    $stmt->execute([$mold_id]);
    $mold = $stmt->fetch();
    
    if (!$mold) return 0;
    
    $max_shots = floor($available_material / $mold['shot_size']);
    return $max_shots * $mold['total_cavities'];
}

function calculateMaterialNeeded($mold_id, $target_parts) {
    $db = getDatabase();
    $stmt = $db->prepare("SELECT total_cavities, shot_size FROM molds WHERE mold_id = ?");
    $stmt->execute([$mold_id]);
    $mold = $stmt->fetch();
    
    if (!$mold) return 0;
    
    $shots_needed = ceil($target_parts / $mold['total_cavities']);
    return $shots_needed * $mold['shot_size'];
}

function logInventoryTransaction($type, $item_id, $action, $new_quantity, $notes = '') {
    $db = getDatabase();
    $stmt = $db->prepare("
        INSERT INTO inventory_transactions (transaction_type, item_id, transaction_action, new_quantity, notes, user_id) 
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$type, $item_id, $action, $new_quantity, $notes, $_SESSION['user_id'] ?? null]);
}

function updateMaterialStock($material_id, $new_quantity, $action = 'physical_count', $notes = '') {
    $db = getDatabase();
    $stmt = $db->prepare("UPDATE materials SET current_stock = ? WHERE material_id = ?");
    $stmt->execute([$new_quantity, $material_id]);
    logInventoryTransaction('material', $material_id, $action, $new_quantity, $notes);
}

function updateComponentStock($component_id, $new_quantity, $action = 'physical_count', $notes = '') {
    $db = getDatabase();
    $stmt = $db->prepare("UPDATE components SET current_stock = ? WHERE component_id = ?");
    $stmt->execute([$new_quantity, $component_id]);
    logInventoryTransaction('component', $component_id, $action, $new_quantity, $notes);
}

function updateConsumableStock($consumable_id, $new_quantity, $action = 'physical_count', $notes = '') {
    $db = getDatabase();
    $stmt = $db->prepare("UPDATE consumables SET current_stock = ? WHERE consumable_id = ?");
    $stmt->execute([$new_quantity, $consumable_id]);
    logInventoryTransaction('consumable', $consumable_id, $action, $new_quantity, $notes);
}

function updatePackagingStock($packaging_id, $new_quantity, $action = 'physical_count', $notes = '') {
    $db = getDatabase();
    $stmt = $db->prepare("UPDATE packaging SET current_stock = ? WHERE packaging_id = ?");
    $stmt->execute([$new_quantity, $packaging_id]);
    logInventoryTransaction('packaging', $packaging_id, $action, $new_quantity, $notes);
}

function updatePartStock($part_id, $new_quantity, $action = 'physical_count', $notes = '') {
    $db = getDatabase();
    $stmt = $db->prepare("UPDATE parts SET current_stock = ? WHERE part_id = ?");
    $stmt->execute([$new_quantity, $part_id]);
    logInventoryTransaction('finished_part', $part_id, $action, $new_quantity, $notes);
}

function getLowStockItems() {
    $db = getDatabase();
    $items = [];
    
    // Materials
    $stmt = $db->query("SELECT 'material' as type, material_id as id, material_name as name, current_stock, reorder_point FROM materials WHERE current_stock <= reorder_point");
    while ($row = $stmt->fetch()) {
        $items[] = $row;
    }
    
    // Components
    $stmt = $db->query("SELECT 'component' as type, component_id as id, component_name as name, current_stock, reorder_point FROM components WHERE current_stock <= reorder_point");
    while ($row = $stmt->fetch()) {
        $items[] = $row;
    }
    
    // Consumables
    $stmt = $db->query("SELECT 'consumable' as type, consumable_id as id, consumable_name as name, current_stock, reorder_point FROM consumables WHERE current_stock <= reorder_point");
    while ($row = $stmt->fetch()) {
        $items[] = $row;
    }
    
    // Packaging
    $stmt = $db->query("SELECT 'packaging' as type, packaging_id as id, packaging_name as name, current_stock, reorder_point FROM packaging WHERE current_stock <= reorder_point");
    while ($row = $stmt->fetch()) {
        $items[] = $row;
    }
    
    // Parts
    $stmt = $db->query("SELECT 'part' as type, part_id as id, part_name as name, current_stock, reorder_point FROM parts WHERE current_stock <= reorder_point");
    while ($row = $stmt->fetch()) {
        $items[] = $row;
    }
    
    return $items;
}

function getInventorySummary() {
    $db = getDatabase();
    
    $summary = [
        'materials' => ['total' => 0, 'low_stock' => 0],
        'components' => ['total' => 0, 'low_stock' => 0],
        'consumables' => ['total' => 0, 'low_stock' => 0],
        'packaging' => ['total' => 0, 'low_stock' => 0],
        'parts' => ['total' => 0, 'low_stock' => 0]
    ];
    
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock FROM materials");
    $row = $stmt->fetch();
    $summary['materials'] = $row;
    
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock FROM components");
    $row = $stmt->fetch();
    $summary['components'] = $row;
    
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock FROM consumables");
    $row = $stmt->fetch();
    $summary['consumables'] = $row;
    
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock FROM packaging");
    $row = $stmt->fetch();
    $summary['packaging'] = $row;
    
    $stmt = $db->query("SELECT COUNT(*) as total, SUM(CASE WHEN current_stock <= reorder_point THEN 1 ELSE 0 END) as low_stock FROM parts");
    $row = $stmt->fetch();
    $summary['parts'] = $row;
    
    return $summary;
}

function getPartBOM($part_id) {
    $db = getDatabase();
    $bom = [
        'materials' => [],
        'components' => [],
        'consumables' => [],
        'packaging' => []
    ];
    
    // Materials
    $stmt = $db->prepare("
        SELECT m.*, pm.quantity_per_part 
        FROM part_materials pm 
        JOIN materials m ON pm.material_id = m.material_id 
        WHERE pm.part_id = ?
    ");
    $stmt->execute([$part_id]);
    $bom['materials'] = $stmt->fetchAll();
    
    // Components
    $stmt = $db->prepare("
        SELECT c.*, pc.quantity_per_part 
        FROM part_components pc 
        JOIN components c ON pc.component_id = c.component_id 
        WHERE pc.part_id = ?
    ");
    $stmt->execute([$part_id]);
    $bom['components'] = $stmt->fetchAll();
    
    // Consumables
    $stmt = $db->prepare("
        SELECT c.*, pc.required, pc.application_step, pc.notes 
        FROM part_consumables pc 
        JOIN consumables c ON pc.consumable_id = c.consumable_id 
        WHERE pc.part_id = ? 
        ORDER BY pc.application_step
    ");
    $stmt->execute([$part_id]);
    $bom['consumables'] = $stmt->fetchAll();
    
    // Packaging
    $stmt = $db->prepare("
        SELECT p.*, pp.quantity_per_part 
        FROM part_packaging pp 
        JOIN packaging p ON pp.packaging_id = p.packaging_id 
        WHERE pp.part_id = ?
    ");
    $stmt->execute([$part_id]);
    $bom['packaging'] = $stmt->fetchAll();
    
    return $bom;
}

function getMoldsForPart($part_id) {
    $db = getDatabase();
    $stmt = $db->prepare("
        SELECT m.*, mc.cavity_number, mc.parts_per_shot 
        FROM mold_cavities mc 
        JOIN molds m ON mc.mold_id = m.mold_id 
        WHERE mc.part_id = ? 
        ORDER BY m.mold_number, mc.cavity_number
    ");
    $stmt->execute([$part_id]);
    return $stmt->fetchAll();
}

function getNavigationItems() {
    $nav = [];
    
    if (hasPermission('view_inventory') || hasPermission('view_parts') || hasPermission('view_reports')) {
        $nav[] = ['url' => 'index.php', 'title' => 'Dashboard', 'icon' => 'home'];
    }
    
    if (hasPermission('view_parts')) {
        $nav[] = ['url' => 'parts.php', 'title' => 'Parts', 'icon' => 'package'];
    }
    
    if (hasPermission('view_molds')) {
        $nav[] = ['url' => 'molds.php', 'title' => 'Molds', 'icon' => 'tool'];
    }
    
    if (hasPermission('view_inventory')) {
        $nav[] = ['url' => 'materials.php', 'title' => 'Materials', 'icon' => 'box'];
        $nav[] = ['url' => 'components.php', 'title' => 'Components', 'icon' => 'cpu'];
        $nav[] = ['url' => 'consumables.php', 'title' => 'Consumables', 'icon' => 'droplet'];
        $nav[] = ['url' => 'packaging.php', 'title' => 'Packaging', 'icon' => 'package-2'];
    }
    
    if (hasPermission('view_bom')) {
        $nav[] = ['url' => 'bom.php', 'title' => 'BOM Builder', 'icon' => 'list'];
    }
    
    if (hasPermission('update_inventory')) {
        $nav[] = ['url' => 'inventory.php', 'title' => 'Inventory Update', 'icon' => 'edit'];
    }
    
    if (hasPermission('view_reports')) {
        $nav[] = ['url' => 'status.php', 'title' => 'Status', 'icon' => 'bar-chart-2'];
        $nav[] = ['url' => 'reorder.php', 'title' => 'Reorder', 'icon' => 'shopping-cart'];
    }
    
    if (hasPermission('production_planning')) {
        $nav[] = ['url' => 'production.php', 'title' => 'Production', 'icon' => 'activity'];
    }
    
    if (hasPermission('admin_panel')) {
        $nav[] = ['url' => 'admin.php', 'title' => 'Admin', 'icon' => 'settings'];
    }
    
    return $nav;
}
?>