<?php
require_once 'database.php';

function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

function authenticateUser($username, $password) {
    $db = getDatabase();
    
    $stmt = $db->prepare("SELECT user_id, username, password_hash, role, full_name, active FROM users WHERE username = ? AND active = 1");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && verifyPassword($password, $user['password_hash'])) {
        $_SESSION['user_id'] = $user['user_id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['last_activity'] = time();
        
        // Cache user permissions
        $_SESSION['permissions'] = getUserPermissions($user['user_id']);
        
        // Update last login
        $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
        $updateStmt->execute([$user['user_id']]);
        
        return true;
    }
    
    return false;
}

function isAdmin($user_id = null) {
    if ($user_id === null) {
        return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
    }
    
    $db = getDatabase();
    $stmt = $db->prepare("SELECT role FROM users WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
    
    return $user && $user['role'] === 'admin';
}

function hasPermission($permission_name, $user_id = null) {
    if ($user_id === null) {
        if (isAdmin()) return true;
        return isset($_SESSION['permissions']) && in_array($permission_name, $_SESSION['permissions']);
    }
    
    if (isAdmin($user_id)) return true;
    
    $db = getDatabase();
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM user_permissions up 
        JOIN permissions p ON up.permission_id = p.permission_id 
        WHERE up.user_id = ? AND p.permission_name = ?
    ");
    $stmt->execute([$user_id, $permission_name]);
    $result = $stmt->fetch();
    
    return $result['count'] > 0;
}

function getUserPermissions($user_id) {
    if (isAdmin($user_id)) {
        return getAllPermissionNames();
    }
    
    $db = getDatabase();
    $stmt = $db->prepare("
        SELECT p.permission_name FROM user_permissions up 
        JOIN permissions p ON up.permission_id = p.permission_id 
        WHERE up.user_id = ?
    ");
    $stmt->execute([$user_id]);
    
    $permissions = [];
    while ($row = $stmt->fetch()) {
        $permissions[] = $row['permission_name'];
    }
    
    return $permissions;
}

function getAllPermissionNames() {
    $db = getDatabase();
    $stmt = $db->query("SELECT permission_name FROM permissions");
    
    $permissions = [];
    while ($row = $stmt->fetch()) {
        $permissions[] = $row['permission_name'];
    }
    
    return $permissions;
}

function requirePermission($permission_name) {
    if (!hasPermission($permission_name)) {
        showAlert("You don't have permission to access this feature.", 'error');
        redirect('index.php');
    }
}

function requireAdmin() {
    if (!isAdmin()) {
        showAlert("Admin access required.", 'error');
        redirect('index.php');
    }
}

function createUser($username, $password, $full_name, $role = 'user', $permissions = []) {
    $db = getDatabase();
    
    try {
        $db->beginTransaction();
        
        $passwordHash = hashPassword($password);
        $stmt = $db->prepare("INSERT INTO users (username, password_hash, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute([$username, $passwordHash, $full_name, $role]);
        
        $userId = $db->lastInsertId();
        
        if ($role !== 'admin' && !empty($permissions)) {
            foreach ($permissions as $permissionName) {
                grantPermission($userId, $permissionName, $_SESSION['user_id']);
            }
        }
        
        $db->commit();
        return $userId;
        
    } catch (Exception $e) {
        $db->rollBack();
        throw $e;
    }
}

function grantPermission($user_id, $permission_name, $granted_by_admin_id) {
    $db = getDatabase();
    
    $permStmt = $db->prepare("SELECT permission_id FROM permissions WHERE permission_name = ?");
    $permStmt->execute([$permission_name]);
    $permission = $permStmt->fetch();
    
    if (!$permission) {
        throw new Exception("Permission not found: $permission_name");
    }
    
    $stmt = $db->prepare("INSERT IGNORE INTO user_permissions (user_id, permission_id, granted_by) VALUES (?, ?, ?)");
    $stmt->execute([$user_id, $permission['permission_id'], $granted_by_admin_id]);
}

function revokePermission($user_id, $permission_name) {
    $db = getDatabase();
    
    $stmt = $db->prepare("
        DELETE up FROM user_permissions up 
        JOIN permissions p ON up.permission_id = p.permission_id 
        WHERE up.user_id = ? AND p.permission_name = ?
    ");
    $stmt->execute([$user_id, $permission_name]);
}

function logout() {
    session_unset();
    session_destroy();
    redirect('login.php');
}
?>