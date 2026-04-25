<?php
// =============================================
// Header Include — Sidebar + Topbar
// =============================================
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/auth.php';

$unreadCount  = countUnreadNotif();
$currentPage  = basename($_SERVER['PHP_SELF']);
$pageDir      = basename(dirname($_SERVER['PHP_SELF']));
$isAdminArea  = ($pageDir === 'admin');

// Page title (set $pageTitle before including header)
$pageTitle    = $pageTitle  ?? 'Dashboard';
$pageSubtitle = $pageSubtitle ?? '';

function navLink(string $label, string $page, string $icon, string $currentPage, string $hrefBase = ''): void {
    $active = ($currentPage === $page) ? 'active' : '';
    $href   = BASE_URL . $hrefBase . '/' . $page;
    echo "<li class=\"nav-item\">
        <a href=\"{$href}\" class=\"nav-link {$active}\">
            <i class=\"fa-solid {$icon}\"></i>
            <span>{$label}</span>
        </a>
    </li>";
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> — KontrakanKu</title>
    <meta name="description" content="Sistem Keuangan Kontrakan — Kelola biaya, pembayaran, dan pengeluaran bersama.">
    <!-- Inter Font -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <!-- Main CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<div class="sidebar-overlay" id="sidebarOverlay"></div>

<div class="app-layout">
    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-header">
            <a href="<?= BASE_URL ?>/<?= $isAdminArea ? 'admin/dashboard.php' : 'dashboard.php' ?>" class="sidebar-brand">
                <div class="sidebar-brand-icon">🏠</div>
                <div class="sidebar-brand-text">
                    <strong>KontrakanKu</strong>
                    <span>Sistem Keuangan</span>
                </div>
            </a>
        </div>

        <nav class="sidebar-nav">
            <?php if ($isAdminArea): ?>
            <!-- ADMIN NAV -->
            <p class="nav-section-label">Admin Panel</p>
            <ul>
                <?php navLink('Dashboard',    'dashboard.php',    'fa-gauge-high',   $currentPage, '/admin'); ?>
                <?php navLink('Data Penghuni','users.php',        'fa-users',        $currentPage, '/admin'); ?>
                <?php navLink('Input Biaya',  'biaya.php',        'fa-file-invoice-dollar', $currentPage, '/admin'); ?>
                <?php navLink('Pembayaran',   'pembayaran.php',   'fa-money-check-dollar', $currentPage, '/admin'); ?>
                <?php navLink('Pengeluaran',  'pengeluaran.php',  'fa-receipt',      $currentPage, '/admin'); ?>
                <?php navLink('Saldo Kas',    'kas.php',          'fa-piggy-bank',   $currentPage, '/admin'); ?>
                <?php navLink('Notifikasi',   'notifikasi.php',   'fa-bell',         $currentPage, '/admin'); ?>
            </ul>
            <div class="divider"></div>
            <p class="nav-section-label">Penghuni</p>
            <ul>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/dashboard.php" class="nav-link">
                        <i class="fa-solid fa-arrow-left"></i>
                        <span>Kembali ke User View</span>
                    </a>
                </li>
            </ul>
            <?php else: ?>
            <!-- USER NAV -->
            <p class="nav-section-label">Menu</p>
            <ul>
                <?php navLink('Dashboard',     'dashboard.php',     'fa-gauge-high',  $currentPage); ?>
                <?php navLink('Biaya Bulanan', 'biaya.php',         'fa-file-invoice-dollar', $currentPage); ?>
                <?php navLink('Pembayaran',    'pembayaran.php',    'fa-money-check-dollar', $currentPage); ?>
                <?php navLink('Pengeluaran',   'pengeluaran.php',   'fa-receipt',     $currentPage); ?>
                <?php navLink('Notifikasi',    'notifikasi.php',    'fa-bell',        $currentPage); ?>
            </ul>
            <?php if (isAdmin()): ?>
            <div class="divider"></div>
            <p class="nav-section-label">Admin</p>
            <ul>
                <li class="nav-item">
                    <a href="<?= BASE_URL ?>/admin/dashboard.php" class="nav-link">
                        <i class="fa-solid fa-shield-halved"></i>
                        <span>Admin Panel</span>
                    </a>
                </li>
            </ul>
            <?php endif; ?>
            <?php endif; ?>
        </nav>

        <div class="sidebar-footer">
            <div class="user-card">
                <div class="user-avatar"><?= strtoupper(substr(currentUserName(), 0, 1)) ?></div>
                <div class="user-info">
                    <div class="user-name"><?= htmlspecialchars(currentUserName()) ?></div>
                    <div class="user-role"><?= isAdmin() ? '👑 Admin' : '🏠 Penghuni' ?></div>
                </div>
                <a href="<?= BASE_URL ?>/logout.php" class="btn-logout" title="Logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>
            </div>
        </div>
    </aside>

    <!-- ===== MAIN CONTENT ===== -->
    <div class="main-content">
        <!-- Topbar -->
        <header class="topbar">
            <div class="d-flex align-center gap-12">
                <button class="sidebar-toggle" id="sidebarToggle">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <div class="topbar-left">
                    <h2><?= htmlspecialchars($pageTitle) ?></h2>
                    <?php if ($pageSubtitle): ?>
                    <p><?= htmlspecialchars($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <div class="topbar-right">
                <a href="<?= BASE_URL ?>/notifikasi.php" class="notif-btn" title="Notifikasi">
                    <i class="fa-solid fa-bell"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="notif-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </header>

        <!-- Page Content -->
        <main class="page-content">
