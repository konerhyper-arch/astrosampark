<?php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Already logged in → go to dashboard
if (!empty($_SESSION['admin_token'])) {
    header('Location: /admin/dashboard.php');
    exit;
}

$error   = '';
$csrfKey = 'login_csrf';

// Seed CSRF token for the form
if (empty($_SESSION[$csrfKey])) {
    $_SESSION[$csrfKey] = bin2hex(random_bytes(32));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate CSRF
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION[$csrfKey], $submittedToken)) {
        $error = 'Invalid request. Please try again.';
    } else {
        $login    = trim($_POST['login']    ?? '');
        $password = trim($_POST['password'] ?? '');

        if (!$login || !$password) {
            $error = 'Please enter your phone/email and password.';
        } else {
            // Call backend login API
            $apiBase = getenv('API_BASE_URL') ?: 'http://localhost/api';
            $ch = curl_init($apiBase . '/auth/login');
            curl_setopt_array($ch, [
                CURLOPT_POST           => true,
                // Backend checks phone OR email column, so send as 'phone' (covers both login types)
                CURLOPT_POSTFIELDS     => json_encode(['phone' => $login, 'password' => $password]),
                CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 10,
            ]);
            $raw  = curl_exec($ch);
            $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $resp = json_decode($raw, true) ?? [];

            if ($code === 200 && !empty($resp['data']['token'])) {
                $user = $resp['data']['user'] ?? [];

                // Enforce admin role
                if (($user['role'] ?? '') !== 'admin') {
                    $error = 'Access denied. Admin accounts only.';
                } else {
                    $_SESSION['admin_token']  = $resp['data']['token'];
                    $_SESSION['admin_name']   = $user['name']   ?? 'Admin';
                    $_SESSION['admin_email']  = $user['email']  ?? '';
                    $_SESSION['admin_id']     = $user['id']     ?? 0;
                    $_SESSION['admin_wallet'] = $user['wallet_balance'] ?? 0;
                    // Regenerate session ID on privilege escalation
                    session_regenerate_id(true);
                    unset($_SESSION[$csrfKey]);
                    header('Location: /admin/dashboard.php');
                    exit;
                }
            } else {
                $error = $resp['message'] ?? 'Login failed. Check your credentials.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — AstroSampark</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>

<div class="login-wrapper">
    <div class="login-card">

        <!-- Header -->
        <div class="login-header">
            <div style="font-size:2.5rem; margin-bottom:0.5rem;">🔮</div>
            <h3>AstroSampark</h3>
            <p>Admin Control Panel</p>
        </div>

        <!-- Body -->
        <div class="login-body">
            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/index.php" novalidate>
                <input type="hidden" name="csrf_token"
                       value="<?= htmlspecialchars($_SESSION[$csrfKey]) ?>">

                <div class="mb-3">
                    <label for="login" class="form-label fw-semibold">
                        <i class="bi bi-person me-1"></i>Phone / Email
                    </label>
                    <input type="text"
                           class="form-control form-control-lg"
                           id="login" name="login"
                           placeholder="admin@example.com"
                           value="<?= htmlspecialchars($_POST['login'] ?? '') ?>"
                           autocomplete="username"
                           required>
                </div>

                <div class="mb-4">
                    <label for="password" class="form-label fw-semibold">
                        <i class="bi bi-lock me-1"></i>Password
                    </label>
                    <div class="input-group">
                        <input type="password"
                               class="form-control form-control-lg"
                               id="password" name="password"
                               placeholder="••••••••"
                               autocomplete="current-password"
                               required>
                        <button class="btn btn-outline-secondary" type="button" id="togglePwd"
                                title="Show/hide password">
                            <i class="bi bi-eye" id="eyeIcon"></i>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-danger w-100 btn-lg fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-2"></i>Sign In
                </button>
            </form>

            <p class="text-center text-muted mt-4 mb-0" style="font-size:0.78rem;">
                <i class="bi bi-shield-lock me-1"></i>
                Restricted access — authorised personnel only
            </p>
        </div>

    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.getElementById('togglePwd').addEventListener('click', function () {
    const pwd  = document.getElementById('password');
    const icon = document.getElementById('eyeIcon');
    if (pwd.type === 'password') {
        pwd.type = 'text';
        icon.className = 'bi bi-eye-slash';
    } else {
        pwd.type = 'password';
        icon.className = 'bi bi-eye';
    }
});
</script>
</body>
</html>
