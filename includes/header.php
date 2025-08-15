<?php
require_once __DIR__ . '/functions.php';
requireLogin();

$pageTitle = isset($pageTitle) ? $pageTitle . ' - ' . APP_NAME : APP_NAME;
$currentPage = basename($_SERVER['PHP_SELF']);
$navItems = getNavigationItems();
$alert = getAlert();
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= APP_NAME ?>">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand fw-bold" href="index.php">
                <i data-feather="box" class="me-2"></i>
                <?= APP_NAME ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <?php foreach ($navItems as $item): ?>
                        <li class="nav-item">
                            <a class="nav-link <?= $currentPage === $item['url'] ? 'active' : '' ?>" 
                               href="<?= htmlspecialchars($item['url']) ?>">
                                <i data-feather="<?= htmlspecialchars($item['icon']) ?>" class="me-1"></i>
                                <?= htmlspecialchars($item['title']) ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" data-bs-toggle="dropdown">
                            <i data-feather="user" class="me-1"></i>
                            <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><h6 class="dropdown-header">
                                <?= htmlspecialchars($_SESSION['full_name'] ?? $_SESSION['username']) ?>
                                <small class="text-muted d-block"><?= htmlspecialchars($_SESSION['role']) ?></small>
                            </h6></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><button class="dropdown-item" onclick="toggleTheme()">
                                <i data-feather="moon" class="me-2"></i>Toggle Theme
                            </button></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item text-danger" href="logout.php">
                                <i data-feather="log-out" class="me-2"></i>Logout
                            </a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <main class="container-fluid py-3">
        <?php if ($alert): ?>
            <div class="alert alert-<?= htmlspecialchars($alert['type']) ?> alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($alert['message']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <input type="hidden" id="csrf-token" value="<?= generateCSRFToken() ?>">