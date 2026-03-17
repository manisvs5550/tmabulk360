<?php
/**
 * Dashboard header include — navbar + sidebar.
 * Expects: $current_page (string) to highlight active sidebar link.
 */
if (!defined('DB_HOST')) { require_once __DIR__ . '/../config.php'; }
$current_page = $current_page ?? '';
$current_lang = get_lang();
?>
<!DOCTYPE html>
<html lang="<?= e($current_lang) ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title ?? 'TMA Operations 360') ?></title>
    <link rel="stylesheet" href="static/css/style.css">
    <link rel="stylesheet" href="static/css/dashboard.css?v=12">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Space+Grotesk:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="manifest" href="static/manifest.json">
    <meta name="theme-color" content="#6366f1">
</head>
<body>
    <!-- Top Navbar: logo + language + user -->
    <nav class="dashboard-navbar" id="navbar">
        <div class="container nav-container">
            <div class="nav-left">
                <button class="sidebar-toggle" id="sidebarToggle" aria-label="Toggle sidebar">
                    <i class="fa-solid fa-bars"></i>
                </button>
                <a href="dashboard.php" class="nav-logo">
                    <img src="static/images/logo.svg" alt="TMA ops360" class="logo-img">
                </a>
            </div>
            <div class="nav-right-group">
                <!-- Language Selector -->
                <div class="nav-lang-menu">
                    <button class="lang-toggle" title="<?= e(t('language')) ?>">
                        <i class="fa-solid fa-globe"></i>
                        <span><?= strtoupper($current_lang) ?></span>
                        <i class="fa-solid fa-caret-down"></i>
                    </button>
                    <div class="lang-dropdown">
                        <?php foreach (LANGUAGE_LABELS as $code => $label): ?>
                        <a href="?set_lang=<?= $code ?>" class="lang-option<?= $code === $current_lang ? ' active' : '' ?>">
                            <span class="lang-code"><?= strtoupper($code) ?></span>
                            <span><?= e($label) ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <!-- User Profile Menu -->
                <div class="nav-user-menu">
                    <div class="user-info">
                        <i class="fa-solid fa-user-circle"></i>
                        <span><?= e($_SESSION['username'] ?? 'User') ?></span>
                        <i class="fa-solid fa-caret-down"></i>
                    </div>
                    <div class="dropdown-menu">
                        <ul>
                            <li><a href="change_password.php"><i class="fa-solid fa-key"></i> <?= e(t('change_password')) ?></a></li>
                            <li><a href="logout.php"><i class="fa-solid fa-right-from-bracket"></i> <?= e(t('logout')) ?></a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Sidebar Navigation -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar-nav">
            <a href="dashboard.php" class="sidebar-link<?= $current_page === 'dashboard' ? ' active' : '' ?>" data-tip="<?= e(t('dashboard')) ?>">
                <i class="fa-solid fa-house"></i><span><?= e(t('dashboard')) ?></span>
            </a>
            <a href="users.php" class="sidebar-link<?= $current_page === 'users' ? ' active' : '' ?>" data-tip="<?= e(t('users')) ?>">
                <i class="fa-solid fa-users"></i><span><?= e(t('users')) ?></span>
            </a>
            <a href="vessels.php" class="sidebar-link<?= $current_page === 'vessels' ? ' active' : '' ?>" data-tip="<?= e(t('vessels')) ?>">
                <i class="fa-solid fa-ship"></i><span><?= e(t('vessels')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('user_inputs')) ?>">
                <i class="fa-solid fa-keyboard"></i><span><?= e(t('user_inputs')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('ais_data')) ?>">
                <i class="fa-solid fa-satellite-dish"></i><span><?= e(t('ais_data')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('track_summary')) ?>">
                <i class="fa-solid fa-route"></i><span><?= e(t('track_summary')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('veson_report')) ?>">
                <i class="fa-solid fa-file-alt"></i><span><?= e(t('veson_report')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('ais_veson')) ?>">
                <i class="fa-solid fa-chart-column"></i><span><?= e(t('ais_veson')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('hull_scales')) ?>">
                <i class="fa-solid fa-anchor"></i><span><?= e(t('hull_scales')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('hull_action')) ?>">
                <i class="fa-solid fa-water"></i><span><?= e(t('hull_action')) ?></span>
            </a>
            <a href="#" class="sidebar-link" data-tip="<?= e(t('overview')) ?>">
                <i class="fa-solid fa-globe"></i><span><?= e(t('overview')) ?></span>
            </a>
            <a href="inventory.php" class="sidebar-link<?= in_array($current_page, ['inventory', 'inventory_history']) ? ' active' : '' ?>" data-tip="<?= e(t('inventory')) ?>">
                <i class="fa-solid fa-clipboard-list"></i><span><?= e(t('inventory')) ?></span>
            </a>
        </div>
    </aside>
