<?php
require_once __DIR__ . '/functions.php';

function checkPermissionOrRedirect($permission, $redirectTo = 'index.php') {
    if (!hasPermission($permission)) {
        showAlert("You don't have permission to access this feature.", 'error');
        redirect($redirectTo);
    }
}

function checkAdminOrRedirect($redirectTo = 'index.php') {
    if (!isAdmin()) {
        showAlert("Admin access required.", 'error');
        redirect($redirectTo);
    }
}

function ensurePostRequest() {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        showAlert("Invalid request method.", 'error');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function ensureCSRFToken() {
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        showAlert("Invalid security token. Please try again.", 'error');
        redirect($_SERVER['HTTP_REFERER'] ?? 'index.php');
    }
}

function validateAndSanitizeInput($data, $required = false, $type = 'string') {
    if ($required && (empty($data) || trim($data) === '')) {
        return false;
    }
    
    $data = sanitizeInput($data);
    
    switch ($type) {
        case 'int':
            return filter_var($data, FILTER_VALIDATE_INT);
        case 'float':
            return filter_var($data, FILTER_VALIDATE_FLOAT);
        case 'email':
            return filter_var($data, FILTER_VALIDATE_EMAIL);
        case 'url':
            return filter_var($data, FILTER_VALIDATE_URL);
        case 'string':
        default:
            return $data;
    }
}

function handleAjaxRequest() {
    header('Content-Type: application/json');
    
    if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid CSRF token']);
        exit;
    }
}

function ajaxSuccess($data = null) {
    echo json_encode(['success' => true, 'data' => $data]);
    exit;
}

function ajaxError($message, $code = 400) {
    http_response_code($code);
    echo json_encode(['error' => $message]);
    exit;
}
?>