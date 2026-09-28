<?php
require_once __DIR__ . '/config.php';

$fullname = $_GET['fullname'] ?? "";
$email = $_GET['email'] ?? "";

$allowedRedirects = ['submit_ticket.php', 'view_tickets.php', 'dashboard.php'];
$returnTo = isset($_GET['return_to']) ? trim($_GET['return_to']) : '';
$cleanReturnTo = in_array($returnTo, $allowedRedirects, true) ? $returnTo : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Account — <?php echo SITE_NAME; ?></title>
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

    <header class="navbar" style="background: #FFFFFF; border-bottom: 1px solid var(--border-color); padding: 14px 28px;">
        <div class="navbar-brand">
            <?php echo renderBethelDeskLogo('sm', 'Customer Portal', 'index.php'); ?>
        </div>
        <div class="nav-links">
            <a href="index.php"><i class="fa-solid fa-house"></i> Home</a>
            <a href="login.php"><i class="fa-solid fa-arrow-right-to-bracket"></i> Sign In</a>

            <button type="button" id="themeToggleBtn" class="theme-toggle-btn" aria-label="Toggle theme" title="Switch between dark and light mode">
                <i class="fa-solid fa-moon theme-moon-icon"></i>
                <i class="fa-solid fa-sun theme-sun-icon"></i>
                <span class="theme-toggle-label">Theme</span>
            </button>
        </div>
    </header>

    <div class="main-content auth-main-wrapper">
        <div class="auth-split-layout">
            <div class="auth-brand-panel">
                <div class="auth-brand-header">
                    <?php echo renderBethelDeskLogo('md', 'Customer Portal', 'index.php'); ?>
                </div>
                <div class="auth-brand-content">
                    <h1 class="auth-brand-headline">Join thousands managing support the smart way.</h1>
                    <p class="auth-brand-desc">Create your customer account today to submit complaints, follow ticket progress in real-time, and get prompt resolutions.</p>
                    <div class="auth-brand-visual">
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-user-check"></i></span>
                            <span>Simple setup in under a minute</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-clock-rotate-left"></i></span>
                            <span>Full ticket history & receipts</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-star"></i></span>
                            <span>Direct satisfaction rating & feedback</span>
                        </div>
                    </div>
                </div>
                <div class="auth-brand-footer">
                    <i class="fa-solid fa-shield-halved" style="color: #38BDF8;"></i>
                    <span>Secure, encrypted communication guaranteed</span>
                </div>
            </div>
            <div class="auth-form-panel">
                <div class="auth-form-container">
                    <div style="text-align: center; margin-bottom: 24px;">
                        <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 6px;">Create your account</h2>
                        <p style="font-size: 13.5px; color: var(--text-muted); margin: 0;">Get started with BethelDesk customer support</p>
                    </div>

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

                    <form action="register_process.php" method="POST">
                        <?php if ($cleanReturnTo): ?>
                            <input type="hidden" name="return_to" value="<?php echo htmlspecialchars($cleanReturnTo); ?>">
                        <?php endif; ?>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Full Name</label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fa-solid fa-user" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                <input type="text" name="fullname" placeholder="John Doe" value="<?php echo htmlspecialchars($fullname); ?>" style="width: 100%; padding-left: 40px; margin-bottom: 0;">
                            </div>
                        </div>

                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Email Address</label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fa-solid fa-envelope" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                <input type="email" name="email" placeholder="name@example.com" required value="<?php echo htmlspecialchars($email); ?>" style="width: 100%; padding-left: 40px; margin-bottom: 0;">
                            </div>
                        </div>

                        <div style="margin-bottom: 22px;">
                            <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Password</label>
                            <div style="position: relative; display: flex; align-items: center;">
                                <i class="fa-solid fa-lock" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                <input type="password" name="password" id="regPassword" placeholder="Create a secure password" required minlength="6" style="width: 100%; padding-left: 40px; padding-right: 42px; margin-bottom: 0;">
                                <button type="button" onclick="togglePasswordVisibility('regPassword', this)" style="position: absolute; right: 8px; background: none; border: none; color: var(--text-subtle); cursor: pointer; padding: 6px; min-height: unset; box-shadow: none;" aria-label="Toggle password visibility">
                                    <i class="fa-solid fa-eye"></i>
                                </button>
                            </div>
                            <div style="font-size: 12px; color: var(--text-muted); margin-top: 5px;">
                                <i class="fa-solid fa-circle-info"></i> Minimum 6 characters
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width: 100%; min-height: 44px; font-size: 15px; font-weight: 600; border-radius: var(--radius-md);">
                            <i class="fa-solid fa-user-plus"></i> Create Account
                        </button>
                    </form>

                    <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 13.5px; color: var(--text-muted);">
                        Already have an account? <a href="login.php<?php echo $cleanReturnTo ? '?return_to=' . urlencode($cleanReturnTo) : ''; ?>" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">Sign in</a>
                    </div>
                </div>
            </div>
        </div>

    <script>
        function togglePasswordVisibility(inputId, btn) {
            const input = document.getElementById(inputId);
            const icon = btn.querySelector('i');
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