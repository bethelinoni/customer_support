<?php
/**
 * BethelDesk - Admin Layout Header
 * 
 * Expected optional variables:
 * - $pageTitle: string
 * - $activeNav: string ('tickets', 'analytics', 'customers', 'emails', 'profile')
 * - $navUnreadBadge: string|int (formatted unread emails count)
 * - $extraHead: string (additional styles/scripts)
 */

if (!defined('SITE_NAME')) {
    require_once __DIR__ . '/../config.php';
}

$pageTitle = isset($pageTitle) && !empty($pageTitle) 
    ? htmlspecialchars($pageTitle) . ' — Admin — ' . SITE_NAME 
    : 'Admin Portal — ' . SITE_NAME;

$activeNav = $activeNav ?? '';
$navUnreadBadge = $navUnreadBadge ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/../style.css') ? filemtime(__DIR__ . '/../style.css') : time(); ?>">
    <!-- Local & CDN Phosphor Icons (Resilient Fallback) -->
    <link rel="stylesheet" href="css/phosphor.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/regular/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/bold/style.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@phosphor-icons/web@2.1.2/src/fill/style.css">
    <script src="https://unpkg.com/@phosphor-icons/web"></script>

    <!-- Local & CDN Font Awesome (Resilient Fallback) -->
    <link rel="stylesheet" href="css/all.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('bethel_theme');
                if (!savedTheme) {
                    savedTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                if (savedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>
    <?php if (isset($extraHead)) echo $extraHead; ?>
</head>
<body class="logged-in-body">
    <!-- Mobile Topbar (< 960px) -->
    <div class="app-mobile-topbar">
        <div class="mobile-brand">
            <?php echo renderBethelDeskLogo('sm', 'Admin', 'admin_dashboard.php'); ?>
        </div>
        <div class="mobile-actions">
            <button type="button" class="mobile-btn theme-toggle-btn" aria-label="Toggle theme" title="Switch theme">
                <i class="ph ph-moon theme-moon-icon"></i>
                <i class="ph ph-sun theme-sun-icon"></i>
            </button>
            <button type="button" id="mobileMenuToggleBtn" class="mobile-btn" aria-label="Toggle navigation menu" title="Menu">
                <i class="ph ph-list"></i>
            </button>
        </div>
    </div>
    <div class="app-sidebar-backdrop" id="appSidebarBackdrop"></div>

    <div class="app-layout">
        <!-- Dual-Card Sidebar Assembly (No Topbar, Zero Radius, Shadow Elevated) -->
        <aside class="app-sidebar-assembly" id="appSidebarAssembly">
            <!-- Card 1: Icons Only -->
            <div class="sidebar-icons-card" aria-label="Quick Icon Actions">
                <a href="admin_dashboard.php" class="sidebar-icon-brand" title="BethelDesk Admin Portal">
                    <i class="ph-bold ph-shield-check"></i>
                </a>
                <div class="sidebar-icon-stack">
                    <a href="index.php" class="sidebar-icon-btn" title="Home (Landing Page)">
                        <i class="ph ph-house"></i>
                    </a>
                    <a href="admin_dashboard.php" class="sidebar-icon-btn <?php echo ($activeNav === 'tickets') ? 'active' : ''; ?>" title="Tickets">
                        <i class="ph ph-ticket"></i>
                    </a>
                    <a href="analytics.php" class="sidebar-icon-btn <?php echo ($activeNav === 'analytics') ? 'active' : ''; ?>" title="Analytics">
                        <i class="ph ph-chart-line-up"></i>
                    </a>
                    <a href="admin_customers.php" class="sidebar-icon-btn <?php echo ($activeNav === 'customers') ? 'active' : ''; ?>" title="Customers">
                        <i class="ph ph-users"></i>
                    </a>
                    <a href="admin_emails.php" class="sidebar-icon-btn <?php echo ($activeNav === 'emails') ? 'active' : ''; ?>" title="Email Logs">
                        <i class="ph ph-envelope-simple"></i>
                    </a>
                    <a href="admin_profile.php" class="sidebar-icon-btn <?php echo ($activeNav === 'profile') ? 'active' : ''; ?>" title="Admin Profile">
                        <i class="ph ph-user-gear"></i>
                    </a>
                </div>
                <div class="sidebar-icon-footer">
                    <button type="button" class="sidebar-icon-btn theme-toggle-btn" aria-label="Toggle theme" title="Switch between dark and light mode">
                        <i class="ph ph-moon theme-moon-icon"></i>
                        <i class="ph ph-sun theme-sun-icon"></i>
                    </button>
                    <a href="logout.php" class="sidebar-icon-btn sidebar-nav-logout" title="Sign Out">
                        <i class="ph ph-sign-out"></i>
                    </a>
                </div>
            </div>

            <!-- Card 2: Companion Navigation Card (Icons & Labels) -->
            <div class="sidebar-nav-card" aria-label="Primary Admin Navigation">
                <div>
                    <div class="sidebar-nav-header">
                        <?php echo renderBethelDeskLogo('sm', 'Admin', 'admin_dashboard.php'); ?>
                    </div>

                    <nav class="sidebar-nav-list">
                        <a href="index.php" class="sidebar-nav-link" title="Return to Landing Page">
                            <i class="ph ph-house"></i>
                            <span>Home</span>
                        </a>

                        <a href="admin_dashboard.php" class="sidebar-nav-link <?php echo ($activeNav === 'tickets') ? 'active' : ''; ?>">
                            <i class="ph ph-ticket"></i>
                            <span>Tickets</span>
                        </a>

                        <a href="analytics.php" class="sidebar-nav-link <?php echo ($activeNav === 'analytics') ? 'active' : ''; ?>">
                            <i class="ph ph-chart-line-up"></i>
                            <span>Analytics</span>
                        </a>

                        <a href="admin_customers.php" class="sidebar-nav-link <?php echo ($activeNav === 'customers') ? 'active' : ''; ?>">
                            <i class="ph ph-users"></i>
                            <span>Customers</span>
                        </a>

                        <a href="admin_emails.php" class="sidebar-nav-link <?php echo ($activeNav === 'emails') ? 'active' : ''; ?>">
                            <i class="ph ph-envelope-simple"></i>
                            <span>Email Logs</span>
                            <?php if (!empty($navUnreadBadge)): ?>
                                <span class="nav-badge"><?php echo $navUnreadBadge; ?></span>
                            <?php endif; ?>
                        </a>

                        <a href="admin_profile.php" class="sidebar-nav-link <?php echo ($activeNav === 'profile') ? 'active' : ''; ?>">
                            <i class="ph ph-user-gear"></i>
                            <span>Profile</span>
                        </a>
                    </nav>
                </div>

                <div class="sidebar-nav-footer">
                    <?php
                    $adminName = $_SESSION['admin_fullname'] ?? $_SESSION['admin'] ?? 'Support Agent';
                    $adminInitial = strtoupper(substr($adminName, 0, 1));
                    ?>
                    <div class="sidebar-user-chip">
                        <div class="sidebar-user-avatar"><?php echo htmlspecialchars($adminInitial); ?></div>
                        <div class="sidebar-user-meta">
                            <span class="sidebar-user-name" title="<?php echo htmlspecialchars($adminName); ?>"><?php echo htmlspecialchars($adminName); ?></span>
                            <span class="sidebar-user-role">Agent Console</span>
                        </div>
                    </div>

                    <button type="button" class="sidebar-nav-btn theme-toggle-btn" aria-label="Toggle theme">
                        <i class="ph ph-moon theme-moon-icon"></i>
                        <i class="ph ph-sun theme-sun-icon"></i>
                        <span>Toggle Theme</span>
                    </button>

                    <a href="logout.php" class="sidebar-nav-btn sidebar-nav-logout">
                        <i class="ph ph-sign-out"></i>
                        <span>Sign Out</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content Area -->
        <main class="app-main-content">
