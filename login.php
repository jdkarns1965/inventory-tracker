<?php
require_once 'config/config.php';
require_once 'config/auth.php';

// Redirect if already logged in
if (isLoggedIn()) {
    redirect('index.php');
}

$error = '';
$username = '';
$rememberMe = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitizeInput($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $rememberMe = isset($_POST['remember_me']);
    
    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } else {
        if (authenticateUser($username, $password)) {
            // Set remember me cookie if requested
            if ($rememberMe) {
                $token = bin2hex(random_bytes(32));
                setcookie('remember_token', $token, time() + (86400 * 30), '/', '', true, true); // 30 days
                // In a real app, you'd store this token in the database
            }
            
            showAlert('Login successful! Welcome back.', 'success');
            redirect('index.php');
        } else {
            $error = 'Invalid username or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - <?= APP_NAME ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.css" rel="stylesheet">
    <link href="css/custom.css" rel="stylesheet">
    <meta name="theme-color" content="#0d6efd">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= APP_NAME ?>">
</head>
<body class="bg-light">
    <div class="container-fluid">
        <div class="row min-vh-100">
            <!-- Left Side - Branding -->
            <div class="col-lg-6 d-none d-lg-flex bg-primary">
                <div class="d-flex flex-column justify-content-center align-items-center text-white p-5">
                    <div class="text-center mb-5">
                        <i data-feather="box" style="width: 120px; height: 120px;"></i>
                        <h1 class="display-4 fw-bold mb-3"><?= APP_NAME ?></h1>
                        <p class="lead">Streamlined inventory management for plastic injection molding operations</p>
                    </div>
                    
                    <div class="row text-center">
                        <div class="col-4">
                            <i data-feather="smartphone" class="mb-2" style="width: 40px; height: 40px;"></i>
                            <p class="small">Mobile Optimized</p>
                        </div>
                        <div class="col-4">
                            <i data-feather="zap" class="mb-2" style="width: 40px; height: 40px;"></i>
                            <p class="small">Lightning Fast</p>
                        </div>
                        <div class="col-4">
                            <i data-feather="shield" class="mb-2" style="width: 40px; height: 40px;"></i>
                            <p class="small">Secure</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Right Side - Login Form -->
            <div class="col-lg-6">
                <div class="d-flex flex-column justify-content-center min-vh-100 p-4">
                    <div class="mx-auto" style="max-width: 400px; width: 100%;">
                        <!-- Mobile Header -->
                        <div class="text-center mb-5 d-lg-none">
                            <i data-feather="box" class="text-primary mb-3" style="width: 60px; height: 60px;"></i>
                            <h2 class="fw-bold text-primary"><?= APP_NAME ?></h2>
                            <p class="text-muted">Sign in to continue</p>
                        </div>
                        
                        <!-- Desktop Header -->
                        <div class="mb-5 d-none d-lg-block">
                            <h2 class="fw-bold mb-2">Welcome Back</h2>
                            <p class="text-muted">Sign in to your account to continue</p>
                        </div>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i data-feather="alert-circle" class="me-2"></i>
                                <?= htmlspecialchars($error) ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" class="auto-submit">
                            <div class="form-floating mb-3">
                                <input type="text" 
                                       class="form-control form-control-lg" 
                                       id="username" 
                                       name="username" 
                                       placeholder="Username"
                                       value="<?= htmlspecialchars($username) ?>"
                                       required 
                                       autofocus>
                                <label for="username">
                                    <i data-feather="user" class="me-2"></i>Username
                                </label>
                            </div>
                            
                            <div class="form-floating mb-4">
                                <input type="password" 
                                       class="form-control form-control-lg" 
                                       id="password" 
                                       name="password" 
                                       placeholder="Password"
                                       required>
                                <label for="password">
                                    <i data-feather="lock" class="me-2"></i>Password
                                </label>
                            </div>
                            
                            <div class="form-check mb-4">
                                <input class="form-check-input" 
                                       type="checkbox" 
                                       id="remember_me" 
                                       name="remember_me"
                                       <?= $rememberMe ? 'checked' : '' ?>>
                                <label class="form-check-label" for="remember_me">
                                    Keep me signed in
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100 quick-action-btn">
                                <i data-feather="log-in" class="me-2"></i>Sign In
                            </button>
                        </form>
                        
                        <!-- Demo Credentials -->
                        <div class="mt-5">
                            <h6 class="text-muted mb-3">Demo Credentials:</h6>
                            <div class="row g-2">
                                <div class="col-12">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                            onclick="fillCredentials('admin', 'admin123')">
                                        <i data-feather="settings" class="me-1"></i>
                                        Admin: admin / admin123
                                    </button>
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                            onclick="fillCredentials('supervisor', 'super123')">
                                        <i data-feather="users" class="me-1"></i>
                                        Supervisor: supervisor / super123
                                    </button>
                                </div>
                                <div class="col-12">
                                    <button type="button" class="btn btn-outline-secondary btn-sm w-100" 
                                            onclick="fillCredentials('inventory_clerk', 'clerk123')">
                                        <i data-feather="clipboard" class="me-1"></i>
                                        Clerk: inventory_clerk / clerk123
                                    </button>
                                </div>
                            </div>
                            
                            <div class="alert alert-warning mt-3" role="alert">
                                <i data-feather="alert-triangle" class="me-2"></i>
                                <strong>Security Notice:</strong> Change default passwords after first login!
                            </div>
                        </div>
                        
                        <!-- Theme Toggle -->
                        <div class="text-center mt-4">
                            <button type="button" class="btn btn-link text-muted" onclick="toggleTheme()">
                                <i data-feather="moon" class="me-1"></i>
                                Toggle Dark Mode
                            </button>
                        </div>
                        
                        <!-- Footer -->
                        <div class="text-center mt-5">
                            <small class="text-muted">
                                <?= APP_NAME ?> v<?= APP_VERSION ?><br>
                                Secure inventory management system
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/feather-icons@4.29.0/dist/feather.min.js"></script>
    <script src="js/app.js"></script>
    <script>
        // Initialize Feather icons
        feather.replace();
        
        // Fill demo credentials
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            document.getElementById('username').focus();
        }
        
        // Auto-submit on Enter key
        document.querySelectorAll('input').forEach(input => {
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    document.querySelector('form').submit();
                }
            });
        });
        
        // Show loading state on form submit
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = document.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i data-feather="loader" class="me-2"></i>Signing In...';
            submitBtn.disabled = true;
            submitBtn.classList.add('loading');
        });
    </script>
</body>
</html>