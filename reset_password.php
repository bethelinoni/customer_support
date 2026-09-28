<?php
session_start();
require_once __DIR__ . '/connect.php';

if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$email = isset($_GET['email']) ? trim($_GET['email']) : '';

$isTokenValid = false;
$errorMessage = '';

if (empty($token) || empty($email)) {
    $errorMessage = "Invalid or missing password reset link.";
} else {
    // Validate token against database
    $stmt = $conn->prepare("SELECT id, expires_at FROM password_resets WHERE token = ? AND email = ?");
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $resetRecord = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resetRecord) {
        $errorMessage = "This password reset link is invalid or has already been used.";
    } elseif (strtotime($resetRecord['expires_at']) < time()) {
        $errorMessage = "This password reset link has expired. Reset links are valid for 1 hour.";
    } else {
        $isTokenValid = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set New Password — <?php echo SITE_NAME; ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=<?php echo file_exists(__DIR__ . '/style.css') ? filemtime(__DIR__ . '/style.css') : time(); ?>">
    <link rel="stylesheet" href="css/all.min.css">

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
                    <h1 class="auth-brand-headline">Secure your BethelDesk account.</h1>
                    <p class="auth-brand-desc">Set a strong, fresh password to restore full access to your support dashboard, view responses, and track your active tickets.</p>
                    <div class="auth-brand-visual">
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-lock"></i></span>
                            <span>Minimum 6 characters password requirement</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-shield-halved"></i></span>
                            <span>Secure bcrypt cryptographic hashing</span>
                        </div>
                        <div class="auth-feature-item">
                            <span class="auth-feature-icon"><i class="fa-solid fa-arrow-right-to-bracket"></i></span>
                            <span>Instant login after password update</span>
                        </div>
                    </div>
                </div>
                <div class="auth-brand-footer">
                    <i class="fa-solid fa-shield-check" style="color: #38BDF8;"></i>
                    <span>Credential Security Guaranteed &bull; BethelDesk</span>
                </div>
            </div>
            <div class="auth-form-panel">
                <div class="auth-form-container">
                    <?php if (!$isTokenValid): ?>
                        <div style="text-align: center; margin-bottom: 24px;">
                            <div style="width: 52px; height: 52px; border-radius: 50%; background: #FEF2F2; color: #EF4444; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px;">
                                <i class="fa-solid fa-triangle-exclamation"></i>
                            </div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">Reset Link Expired</h2>
                        </div>

                        <div class="error-message" style="background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; padding: 14px 16px; border-radius: var(--radius-md); margin-bottom: 22px; font-size: 13.5px; text-align: center; line-height: 1.5;">
                            <?php echo htmlspecialchars($errorMessage); ?>
                        </div>

                        <div style="text-align: center; margin-bottom: 16px;">
                            <a href="forgot_password.php" class="btn btn-primary" style="width: 100%;">
                                <i class="fa-solid fa-rotate-right"></i> Request New Reset Link
                            </a>
                        </div>

                        <div style="text-align: center; margin-top: 20px; font-size: 13.5px;">
                            <a href="login.php" style="color: var(--text-muted); text-decoration: none;">Back to Sign In</a>
                        </div>

                    <?php else: ?>

                        <div style="text-align: center; margin-bottom: 24px;">
                            <div style="width: 52px; height: 52px; border-radius: 50%; background: #EFF6FF; color: #2563EB; display: inline-flex; align-items: center; justify-content: center; font-size: 22px; margin-bottom: 16px;">
                                <i class="fa-solid fa-shield-check"></i>
                            </div>
                            <h2 style="font-size: 1.5rem; font-weight: 800; color: var(--text-main); margin-bottom: 8px;">Set New Password</h2>
                            <p style="font-size: 13.5px; color: var(--text-muted); line-height: 1.5; margin: 0;">
                                Enter a new secure password for <strong><?php echo htmlspecialchars($email); ?></strong>
                            </p>
                        </div>

                        <?php if (isset($_GET['error'])): ?>
                            <div class="error-message" style="background: #FEF2F2; color: #991B1B; border: 1px solid #FECACA; padding: 12px 14px; border-radius: var(--radius-md); margin-bottom: 18px; font-size: 13.5px; display: flex; align-items: center; gap: 8px;">
                                <i class="fa-solid fa-circle-exclamation" style="color: #EF4444;"></i>
                                <span><?php echo htmlspecialchars($_GET['error']); ?></span>
                            </div>
                        <?php endif; ?>

                        <form action="reset_password_process.php" method="POST">
                            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
                            <input type="hidden" name="email" value="<?php echo htmlspecialchars($email); ?>">

                            <div style="margin-bottom: 16px;">
                                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">New Password</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fa-solid fa-lock" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                    <input type="password" name="new_password" id="newPassword" placeholder="Minimum 6 characters" required minlength="6" autofocus style="width: 100%; padding-left: 40px; padding-right: 42px; margin-bottom: 0;">
                                    <button type="button" onclick="togglePasswordVisibility('newPassword', this)" style="position: absolute; right: 8px; background: none; border: none; color: var(--text-subtle); cursor: pointer; padding: 6px; min-height: unset; box-shadow: none;" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                            </div>

                            <div style="margin-bottom: 22px;">
                                <label style="display: block; font-size: 13px; font-weight: 600; color: var(--text-secondary); margin-bottom: 6px;">Confirm New Password</label>
                                <div style="position: relative; display: flex; align-items: center;">
                                    <i class="fa-solid fa-shield-halved" style="position: absolute; left: 14px; color: var(--text-subtle); font-size: 14px; pointer-events: none;"></i>
                                    <input type="password" name="confirm_password" id="confirmPassword" placeholder="Re-enter password" required minlength="6" style="width: 100%; padding-left: 40px; padding-right: 42px; margin-bottom: 0;">
                                    <button type="button" onclick="togglePasswordVisibility('confirmPassword', this)" style="position: absolute; right: 8px; background: none; border: none; color: var(--text-subtle); cursor: pointer; padding: 6px; min-height: unset; box-shadow: none;" aria-label="Toggle password visibility">
                                        <i class="fa-solid fa-eye"></i>
                                    </button>
                                </div>
                                <div style="font-size: 12px; color: var(--text-muted); margin-top: 5px;">
                                    <i class="fa-solid fa-circle-info"></i> Must match your new password
                                </div>
                            </div>

                            <button type="submit" class="btn btn-primary" style="width: 100%; min-height: 44px; font-size: 15px; font-weight: 600; border-radius: var(--radius-md);">
                                <i class="fa-solid fa-circle-check"></i> Update Password
                            </button>
                        </form>

                        <div style="text-align: center; margin-top: 24px; padding-top: 20px; border-top: 1px solid var(--border-color); font-size: 13.5px;">
                            <a href="login.php" style="color: var(--text-muted); text-decoration: none;">Cancel &amp; Return to Sign In</a>
                        </div>
                    <?php endif; ?>
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
