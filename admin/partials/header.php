<?php
/**
 * partials/header.php
 * Include after require_once 'auth.php' on every admin page.
 * Expects $pageTitle (string) to be set before inclusion.
 */
$pageTitle   = $pageTitle   ?? 'Admin Panel';
$activePage  = $activePage  ?? '';

$adminName    = htmlspecialchars($_SESSION['admin_name']    ?? 'Admin');
$adminBalance = isset($_SESSION['admin_wallet']) ? formatInr((float) $_SESSION['admin_wallet']) : '—';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — AstroSampark Admin</title>

    <!-- Bootstrap 5 -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <!-- Custom admin styles -->
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
</head>
<body>

<div id="wrapper">

<!-- ═══════════════════════════════════════
     SIDEBAR
════════════════════════════════════════ -->
<nav id="sidebar">
    <a href="/admin/dashboard.php" class="sidebar-brand">
        <span class="brand-icon">🔮</span>
        AstroSampark
    </a>

    <div class="mt-2">
        <div class="nav-label">Main</div>

        <a href="/admin/dashboard.php"
           class="nav-link <?= $activePage === 'dashboard' ? 'active' : '' ?>">
            <i class="bi bi-speedometer2"></i> Dashboard
        </a>

        <div class="nav-label">Leads</div>

        <a href="/admin/leads/import.php"
           class="nav-link <?= $activePage === 'import' ? 'active' : '' ?>">
            <i class="bi bi-cloud-upload"></i> Import Leads
        </a>

        <a href="/admin/leads/queue.php"
           class="nav-link <?= $activePage === 'queue' ? 'active' : '' ?>">
            <i class="bi bi-clipboard2-check"></i> Approval Queue
            <?php if (!empty($_SESSION['pending_count']) && (int) $_SESSION['pending_count'] > 0): ?>
                <span class="badge bg-danger ms-auto"><?= (int) $_SESSION['pending_count'] ?></span>
            <?php endif; ?>
        </a>

        <a href="/admin/leads/listings.php"
           class="nav-link <?= $activePage === 'listings' ? 'active' : '' ?>">
            <i class="bi bi-grid-3x3-gap"></i> Listings
        </a>

        <div class="nav-label">Account</div>

        <a href="/admin/logout.php" class="nav-link text-danger">
            <i class="bi bi-box-arrow-left"></i> Logout
        </a>
    </div>

    <div class="sidebar-footer">
        AstroSampark &copy; <?= date('Y') ?>
    </div>
</nav>

<!-- Sidebar overlay (mobile) -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- ═══════════════════════════════════════
     MAIN CONTENT
════════════════════════════════════════ -->
<div id="main-content">

    <!-- Top Bar -->
    <div id="topbar">
        <button class="btn btn-sm btn-light d-lg-none me-2" id="sidebarToggle">
            <i class="bi bi-list fs-5"></i>
        </button>

        <span class="topbar-title"><?= htmlspecialchars($pageTitle) ?></span>

        <!-- Wallet balance -->
        <span class="badge bg-success bg-opacity-10 text-success fw-semibold px-3 py-2 d-none d-md-inline">
            <i class="bi bi-wallet2 me-1"></i><?= $adminBalance ?>
        </span>

        <!-- Admin dropdown -->
        <div class="dropdown ms-2">
            <button class="btn btn-sm btn-light dropdown-toggle d-flex align-items-center gap-2 border"
                    type="button" data-bs-toggle="dropdown">
                <div class="admin-avatar"><?= adminInitial() ?></div>
                <span class="d-none d-md-inline fw-semibold"><?= $adminName ?></span>
            </button>
            <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                <li><h6 class="dropdown-header"><?= $adminName ?></h6></li>
                <li><hr class="dropdown-divider"></li>
                <li>
                    <a class="dropdown-item text-danger" href="/admin/logout.php">
                        <i class="bi bi-box-arrow-left me-2"></i>Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>
    <!-- /Top Bar -->

    <!-- Page body starts here; footer.php closes the tags -->
    <div class="page-body">
