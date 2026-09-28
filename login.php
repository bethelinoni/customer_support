<?php
session_start();
require_once __DIR__ . '/config.php';

// Determine active login type: 'admin' or 'customer' (default 'customer')
$loginType = (isset($_GET['type']) && strtolower(trim($_GET['type'])) === 'admin') ? 'admin' : 'customer';

// Handle optional return_to redirection target safely
$allowedRedirects = ['submit_ticket.php', 'view_tickets.php', 'dashboard.php'];
$returnTo = isset($_GET['return_to']) ? trim($_GET['return_to']) : '';
$cleanReturnTo = in_array($returnTo, $allowedRedirects, true) ? $returnTo : '';

// Redirect if already logged in for the requested mode
if ($loginType === 'admin' && isset($_SESSION['admin'])) {
    header("Location: admin_dashboard.php");
    exit();
} elseif ($loginType === 'customer' && isset($_SESSION['user_id'])) {
    $target = $cleanReturnTo ? $cleanReturnTo : 'dashboard.php';
    header("Location: " . $target);
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo ($loginType === 'admin') ? 'Staff Login' : 'Customer Login'; ?> — <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
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
</head>
<body style="background-color: var(--bg-page); min-height: 100vh; display: flex; flex-direction: column;">

    <header class="navbar">
        <div class="navbar-brand">
            <?php echo renderBethelDeskLogo('sm', 'Portal Sign In', 'index.php'); ?>
        </div>
        <div class="nav-links">
            <a href="index.php" class="nav-home-link"><i class="fa-solid fa-house"></i> <span>Home</span></a>
            <a href="register.php" id="navRegisterLink"><i class="fa-solid fa-user-plus"></i> <span>Register</span></a>

            <button type="button" id="themeToggleBtn" class="theme-toggle-btn" aria-label="Toggle theme" title="Switch between dark and light mode">
                <i class="fa-solid fa-moon theme-moon-icon"></i>
                <i class="fa-solid fa-sun theme-sun-icon"></i>
                <span class="theme-toggle-label">Theme</span>
            </button>
        </div>
    </header>

    <div class="main-content auth-main-wrapper">
        <div class="auth-split-layout">
            <!-- Left Side: Dynamic Branded Panel -->
            <div class="auth-brand-panel <?php echo ($loginType === 'admin') ? 'admin' : ''; ?>" id="authBrandPanel">
                <div class="auth-brand-header">
                    <?php echo renderBethelDeskLogo('md', ($loginType === 'admin') ? 'Staff Portal' : 'Customer Portal', 'index.php'); ?>
                </div>

                <!-- Customer Brand Content -->
                <div class="auth-brand-content" id="customerBrandContent" style="<?php echo ($loginType === 'admin') ? 'display: none;' : ''; ?>">
                    <h1 class="auth-brand-headline">Track every ticket, every step of the way.</h1>
                    <p class="auth-brand-desc">Access fast, transparent customer support with real-time status updates, instant agent responses, and seamless ticket management.</p>
                    <div class="auth-brand-visual">
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-bolt"></i></span>
                            <span>Real-time resolution updates</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-comments"></i></span>
                            <span>Direct communication with agents</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-shield-halved"></i></span>
                            <span>Enterprise-grade account security</span>
                        </div>
                    </div>
                </div>

                <!-- Admin Brand Content -->
                <div class="auth-brand-content" id="adminBrandContent" style="<?php echo ($loginType === 'admin') ? '' : 'display: none;'; ?>">
                    <h1 class="auth-brand-headline">Empowering support agents &amp; operations.</h1>
                    <p class="auth-brand-desc">Access the unified helpdesk command center to triage incoming customer issues, monitor resolution SLAs, and deliver prompt solutions.</p>
                    <div class="auth-brand-visual">
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-layer-group"></i></span>
                            <span>Centralized queue &amp; priority triage</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-chart-line"></i></span>
                            <span>Live resolution analytics &amp; KPI metrics</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-lock"></i></span>
                            <span>Restricted staff role authorization</span>
                        </div>
                    </div>
                </div>

                <div class="auth-brand-footer" id="authBrandFooter">
                    <i class="fa-solid fa-circle-check" style="color: #38BDF8;"></i>
                    <span id="authBrandFooterText">
                        <?php echo ($loginType === 'admin') ? 'Authorized staff operations only &bull; BethelDesk' : 'Trusted by teams for reliable customer care'; ?>
                    </span>
                </div>
            </div>

            <!-- Right Side: Forms Container -->
            <div class="auth-form-panel">
                <div class="auth-form-container">
                    
                    <!-- Segmented Role Selector -->
                    <div class="login-toggle-segmented" role="tablist" aria-label="Login Role Selection">
                        <button type="button" 
                                id="tabBtnCustomer" 
                                class="login-toggle-btn <?php echo ($loginType === 'customer') ? 'active' : ''; ?>" 
                                role="tab" 
                                aria-selected="<?php echo ($loginType === 'customer') ? 'true' : 'false'; ?>"
                                aria-controls="customerLoginForm"
                                onclick="switchLoginMode('customer')">
                            <i class="fa-solid fa-user"></i>
                            <span>Login as Customer</span>
                        </button>
                        <button type="button" 
                                id="tabBtnAdmin" 
                                class="login-toggle-btn <?php echo ($loginType === 'admin') ? 'active' : ''; ?>" 
                                role="tab" 
                                aria-selected="<?php echo ($loginType === 'admin') ? 'true' : 'false'; ?>"
                                aria-controls="adminLoginForm"
                                onclick="switchLoginMode('admin')">
                            <i class="fa-solid fa-user-shield"></i>
                            <span>Login as Admin</span>
                        </button>
                    </div>

                    <!-- Global Notifications -->
                    <?php if (isset($_GET['success'])): ?>
                        <div class="success-message" style="background: #ECFDF5; color: #065F46; border: 1px solid #A7F3D0; padding: 12px 14px; border-radius: var(--radius-md); margin-bottom: 18px; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-circle-check" style="color: #10B981;"></i>
                            <span><?php echo htmlspecialchars($_GET['success']); ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="error-message" style="background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; padding: 12px 14px; border-radius: var(--radius-md); margin-bottom: 18px; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                            <i class="fa-solid fa-circle-exclamation" style="color: #EF4444;"></i>
                            <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Form 1: Customer Login -->
                    <div id="customerLoginForm" style="<?php echo ($loginType === 'customer') ? '' : 'display: none;'; ?>">
                        <div style="text-align: center; margin-bottom: 24px;">
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Welcome back</h2>
                            <p style="font-size: 13.5px; color: var(--text-muted); margin: 0;">Sign in to your customer support account</p>
                        </div>

                        <form action="login_process.php" method="POST">
                            <?php if ($cleanReturnTo): ?>
                                <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($cleanReturnTo); ?>">
                            <?php endif; ?>
                            <div style="margin-bottom: 16px;">
                                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Email Address</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fa-solid fa-envelope" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                    <input type="email" name="email" placeholder="name@example.com" required style="width: 100%; padding-left: 40px; margin-bottom: 0;">
                                </div>
                            </div>

                            <div style="margin-bottom: 18px;">
                                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                                    <label style="font-size: 13px; font-weight: 600; color: var(--text-secondary);">Password</label>
                                    <a href="forgot_password.php" style="color: var(--primary-color); text-decoration: none; font-size: 12.5px; font-weight: 500;">Forgot password?</a>
                                </div>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fa-solid fa-lock" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                    <input type="password" name="password" id="customerPassword" placeholder="Enter your password" required style="width: 100%; padding-left: 40px; padding-right: 42px; margin-bottom: 0;">
                                    <button type="button" onclick="togglePasswordVisibility('customerPassword', this)" style="position: absolute; right: 8px; background: none; border: none; color: var(--text-subtle); cursor: pointer; padding: 6px; min-height: unset; box-shadow: none;" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; min-height: 44px; font-size: 15px; font-weight: 600; border-radius: var(--radius-md);">
                                <i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In as Customer
                            </button>
                        </form>

                        <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-muted);">
                            Don't have an account? <a href="register.php<?php echo $cleanReturnTo ? '?return_to=' . urlencode($cleanReturnTo) : ''; ?>" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Create one now</a>
                        </div>
                    </div>

                    <!-- Form 2: Admin Login -->
                    <div id="adminLoginForm" style="<?php echo ($loginType === 'admin') ? '' : 'display: none;'; ?>">
                        <div style="text-align: center; margin-bottom: 24px;">
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Staff Authentication</h2>
                            <p style="font-size: 13.5px; color: var(--text-muted); margin: 0;">Authorized support agents &amp; administrators only</p>
                        </div>

                        <form action="admin_login_process.php" method="POST">
                            <div style="margin-bottom: 16px;">
                                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Staff Username</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fa-solid fa-user-shield" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                    <input type="text" name="username" placeholder="Username" required style="width: 100%; padding-left: 40px; margin-bottom: 0;">
                                </div>
                            </div>

                            <div style="margin-bottom: 22px;">
                                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Password</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fa-solid fa-lock" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                    <input type="password" name="password" id="adminPassword" placeholder="Password" required style="width: 100%; padding-left: 40px; padding-right: 42px; margin-bottom: 0;">
                                    <button type="button" onclick="togglePasswordVisibility('adminPassword', this)" style="position: absolute; right: 8px; background: none; border: none; color: var(--text-subtle); cursor: pointer; padding: 6px; min-height: unset; box-shadow: none;" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; min-height: 44px; font-size: 15px; font-weight: 600; border-radius: var(--radius-md); background: linear-gradient(135deg, #7C3AED 0%, #6D28D9 100%); border-color: #6D28D9;">
                                <i class="fa-solid fa-shield-halved"></i> Authenticate Agent
                            </button>
                        </form>

                        <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-muted);">
                            Need customer assistance? <a href="javascript:void(0)" onclick="switchLoginMode('customer')" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Customer Login</a>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <script>
        function switchLoginMode(mode) {
            const isCustomer = (mode === 'customer');

            // 1. Update Segmented Control Buttons
            const btnCustomer = document.getElementById('tabBtnCustomer');
            const btnAdmin = document.getElementById('tabBtnAdmin');

            if (btnCustomer) {
                btnCustomer.classList.toggle('active', isCustomer);
                btnCustomer.setAttribute('aria-selected', isCustomer ? 'true' : 'false');
            }
            if (btnAdmin) {
                btnAdmin.classList.toggle('active', !isCustomer);
                btnAdmin.setAttribute('aria-selected', !isCustomer ? 'true' : 'false');
            }

            // 2. Toggle Form Displays
            const customerForm = document.getElementById('customerLoginForm');
            const adminForm = document.getElementById('adminLoginForm');
            if (customerForm) customerForm.style.display = isCustomer ? 'block' : 'none';
            if (adminForm) adminForm.style.display = isCustomer ? 'none' : 'block';

            // 3. Update Left-Side Brand Panel Visuals
            const brandPanel = document.getElementById('authBrandPanel');
            const customerBrand = document.getElementById('customerBrandContent');
            const adminBrand = document.getElementById('adminBrandContent');
            const footerText = document.getElementById('authBrandFooterText');

            if (brandPanel) {
                if (isCustomer) {
                    brandPanel.classList.remove('admin');
                } else {
                    brandPanel.classList.add('admin');
                }
            }

            if (customerBrand) customerBrand.style.display = isCustomer ? 'block' : 'none';
            if (adminBrand) adminBrand.style.display = isCustomer ? 'none' : 'block';
            if (footerText) {
                footerText.innerHTML = isCustomer 
                    ? 'Trusted by teams for reliable customer care' 
                    : 'Authorized staff operations only &bull; BethelDesk';
            }

            // 4. Update Document Title
            document.title = (isCustomer ? 'Customer Login' : 'Staff Login') + ' — <?php echo SITE_NAME; ?>';

            // 5. Update URL Query Parameter Without Page Reload
            const url = new URL(window.location.href);
            if (isCustomer) {
                url.searchParams.delete('type');
            } else {
                url.searchParams.set('type', 'admin');
            }
            window.history.replaceState({}, '', url.toString());

            // 6. Focus First Field of Newly Active Form
            setTimeout(function() {
                if (isCustomer) {
                    const emailInput = document.querySelector('#customerLoginForm input[name="email"]');
                    if (emailInput) emailInput.focus();
                } else {
                    const userInput = document.querySelector('#adminLoginForm input[name="username"]');
                    if (userInput) userInput.focus();
                }
            }, 50);
        }

        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
            if (!input || !icon) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>