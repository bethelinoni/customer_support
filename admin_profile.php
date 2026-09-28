<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

$adminUsername = $_SESSION['admin'];

// Fetch admin details
$stmt = $conn->prepare("SELECT id, username, fullname, email, profile_picture FROM admin WHERE username = ?");
$stmt->bind_param("s", $adminUsername);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    header("Location: logout.php");
    exit();
}

// Generate initials
$displayName = !empty($admin['fullname']) ? $admin['fullname'] : $admin['username'];
$parts = explode(' ', trim($displayName));
$initials = '';
foreach ($parts as $p) {
    if (!empty($p)) $initials .= strtoupper($p[0]);
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = 'AD';

// Email logs unread badge count
$unreadEmailsCount = getUnreadEmailLogsCount($conn);
$unreadEmailsBadge = formatBadgeCount($unreadEmailsCount);

// System overview stats
$totalTickets = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets")->fetch_assoc()['c'];
$pendingTickets = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Pending'")->fetch_assoc()['c'];
$inprogressTickets = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'In Progress'")->fetch_assoc()['c'];
$reopenedTickets = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Reopened'")->fetch_assoc()['c'];
$resolvedTickets = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Resolved'")->fetch_assoc()['c'];
$pageTitle = 'Administrator Profile & Settings';
$activeNav = 'profile';
$navUnreadBadge = $unreadEmailsBadge;
ob_start();
?>
    <style>
        .profile-page-wrapper {
            max-width: 1200px;
            margin: 0 auto;
            width: 100%;
        }

        .profile-hero-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 14px rgba(25, 118, 210, 0.08);
            padding: 28px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 24px;
            flex-wrap: wrap;
        }

        .profile-avatar-container {
            position: relative;
            width: 88px;
            height: 88px;
            flex-shrink: 0;
        }

        .profile-avatar-img {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 14px rgba(13, 71, 161, 0.22);
            display: block;
        }

        .profile-avatar-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1e293b, #0f172a);
            color: #ffffff;
            font-size: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(15, 23, 42, 0.3);
            border: 3px solid #ffffff;
        }

        .avatar-upload-trigger {
            position: absolute;
            bottom: 0;
            right: 0;
            background: #1976d2;
            color: #ffffff;
            width: 28px;
            height: 28px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 5px rgba(0,0,0,0.25);
            font-size: 12px;
            transition: background 0.2s ease, transform 0.2s ease;
        }

        .avatar-upload-trigger:hover {
            background: #0d47a1;
            transform: scale(1.1);
        }

        .profile-hero-info {
            flex: 1;
            min-width: 240px;
        }

        .profile-hero-name {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 4px;
        }

        .profile-hero-email {
            color: var(--text-muted);
            font-size: 14px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .profile-hero-tags {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            font-size: 12px;
        }

        .profile-tag {
            background: #f0f7ff;
            color: #1976d2;
            border: 1px solid #cbe2ff;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .profile-tag-role {
            background: #1e293b;
            color: #f8fafc;
            border: 1px solid #0f172a;
        }

        .profile-tag-alerts {
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
        }

        [data-theme="dark"] .profile-tag,
        [data-theme="dark"] .profile-hero-tags .profile-tag,
        [data-theme="dark"] .profile-tag-id,
        [data-theme="dark"] .profile-hero-tags .profile-tag-id {
            background: rgba(59, 130, 246, 0.2) !important;
            color: #93c5fd !important;
            border-color: rgba(96, 165, 250, 0.45) !important;
        }

        [data-theme="dark"] .profile-tag-role,
        [data-theme="dark"] .profile-hero-tags .profile-tag-role {
            background: rgba(168, 85, 247, 0.2) !important;
            color: #c084fc !important;
            border-color: rgba(168, 85, 247, 0.45) !important;
        }

        [data-theme="dark"] .profile-tag-alerts,
        [data-theme="dark"] .profile-hero-tags .profile-tag-alerts {
            background: rgba(52, 211, 153, 0.2) !important;
            color: #6ee7b7 !important;
            border-color: rgba(52, 211, 153, 0.45) !important;
        }

        [data-theme="dark"] .profile-tag i,
        [data-theme="dark"] .profile-hero-tags .profile-tag i {
            color: inherit !important;
        }

        .profile-stats-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-bottom: 28px;
        }

        .profile-stat-box {
            background: var(--bg-card);
            border-radius: 10px;
            border: 1px solid var(--border-color);
            padding: 16px 20px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.04);
            transition: transform 0.2s ease, border-color 0.2s ease;
        }

        .profile-stat-box:hover {
            transform: translateY(-2px);
            border-color: #1976d2;
        }

        .profile-stat-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
            flex-shrink: 0;
        }

        .profile-stat-val {
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
        }

        .profile-stat-lbl {
            font-size: 12px;
            color: var(--text-muted);
            margin-top: 2px;
            font-weight: 500;
        }

        .profile-forms-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
        }

        @media (max-width: 768px) {
            .profile-forms-grid {
                grid-template-columns: 1fr;
            }
        }

        .settings-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 10px rgba(0,0,0,0.04);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .settings-card-header {
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            margin-bottom: 6px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .settings-card-desc {
            font-size: 13px;
            color: var(--text-muted);
            margin-bottom: 20px;
            line-height: 1.4;
        }

        .form-field-group {
            margin-bottom: 16px;
        }

        .form-field-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
        }

        .input-icon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-wrap i {
            position: absolute;
            left: 12px;
            color: #9ca3af;
            font-size: 14px;
        }

        .input-icon-wrap input {
            width: 100%;
            padding: 10px 12px 10px 36px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-main);
            background: var(--bg-card-subtle);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .input-icon-wrap input:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.12);
        }

        .save-btn {
            background: var(--primary-color);
            color: var(--text-inverse);
            border: 1px solid var(--primary-color);
            padding: 10px 20px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            transition: background 0.2s, transform 0.1s;
            width: 100%;
            justify-content: center;
            margin-top: 6px;
        }

        .save-btn:hover {
            background: var(--primary-hover);
        }

        .save-btn:active {
            transform: scale(0.99);
        }

        [data-theme="dark"] .save-btn {
            background: #FAFAFA !important;
            color: #09090B !important;
            border-color: #FAFAFA !important;
        }

        [data-theme="dark"] .save-btn:hover {
            background: #E4E4E7 !important;
            color: #000000 !important;
        }

        .save-btn i,
        .save-btn span {
            color: inherit !important;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_admin.php';
?>
        <div class="profile-page-wrapper">

            <?php if (isset($_GET['success'])): ?>
                <div class="success-message">
                    <i class="fa-solid fa-circle-check"></i>
                    <?php echo htmlspecialchars($_GET['success']); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error'])): ?>
                <div class="error-message">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                    <?php echo htmlspecialchars($_GET['error']); ?>
                </div>
            <?php endif; ?>

            <!-- Admin Hero Summary Card -->
            <div class="profile-hero-card">
                <div class="profile-avatar-container">
                    <?php if (!empty($admin['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $admin['profile_picture'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($admin['profile_picture']); ?>" alt="Admin Profile Picture" class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar-circle" title="<?php echo htmlspecialchars($displayName); ?>">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    <label for="admin_quick_photo" class="avatar-upload-trigger" title="Upload admin profile photo">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <form id="adminQuickPhotoForm" action="admin_profile_update.php" method="POST" enctype="multipart/form-data" style="display:none;">
                        <input type="hidden" name="action" value="upload_photo">
                        <input type="file" id="admin_quick_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" onchange="document.getElementById('adminQuickPhotoForm').submit();">
                    </form>
                </div>

                <div class="profile-hero-info">
                    <div class="profile-hero-name"><?php echo htmlspecialchars($displayName); ?></div>
                    <div class="profile-hero-email">
                        <i class="fa-regular fa-envelope"></i> 
                        <?php echo !empty($admin['email']) ? htmlspecialchars($admin['email']) : '<span style="color:#f59e0b;font-style:italic;">No alert email configured yet</span>'; ?>
                    </div>
                    <div class="profile-hero-tags">
                        <span class="profile-tag profile-tag-role">
                            <i class="fa-solid fa-shield-halved"></i> Administrator
                        </span>
                        <span class="profile-tag profile-tag-id">
                            <i class="fa-solid fa-user-tag"></i> @<?php echo htmlspecialchars($admin['username']); ?>
                        </span>
                        <span class="profile-tag profile-tag-alerts">
                            <i class="fa-solid fa-bell"></i> Alerts: <?php echo !empty($admin['email']) ? 'Active' : 'Unconfigured'; ?>
                        </span>
                        <?php if (!empty($admin['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $admin['profile_picture'])): ?>
                            <form action="admin_profile_update.php" method="POST" style="display:inline;" onsubmit="return confirm('Remove your admin profile picture?');">
                                <input type="hidden" name="action" value="remove_photo">
                                <button type="submit" style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; padding:4px 10px; border-radius:20px; font-weight:600; font-size:12px; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fa-solid fa-trash-can"></i> Remove Photo
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- System Overview Statistics Strip -->
            <div class="profile-stats-strip">
                <a href="admin_dashboard.php" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#e3f2fd; color:#1976d2;">
                            <i class="fa-solid fa-ticket"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $totalTickets; ?></div>
                            <div class="profile-stat-lbl">Total Complaints</div>
                        </div>
                    </div>
                </a>

                <a href="admin_dashboard.php?status=Pending" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#fff8e1; color:#f59e0b;">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $pendingTickets; ?></div>
                            <div class="profile-stat-lbl">Pending Review</div>
                        </div>
                    </div>
                </a>

                <a href="admin_dashboard.php?status=In+Progress" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#e0f2fe; color:#0284c7;">
                            <i class="fa-solid fa-spinner"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $inprogressTickets; ?></div>
                            <div class="profile-stat-lbl">In Progress</div>
                        </div>
                    </div>
                </a>

                <a href="admin_dashboard.php?status=Reopened" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#f5f3ff; color:#7c3aed;">
                            <i class="fa-solid fa-arrows-rotate"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $reopenedTickets; ?></div>
                            <div class="profile-stat-lbl">Re-opened</div>
                        </div>
                    </div>
                </a>

                <a href="admin_dashboard.php?status=Resolved" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#e8f5e9; color:#2e7d32;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $resolvedTickets; ?></div>
                            <div class="profile-stat-lbl">Resolved</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Settings & Security Forms Grid -->
            <div class="profile-forms-grid">

                <!-- Form 1: Administrator Account Details -->
                <div class="settings-card">
                    <div>
                        <div class="settings-card-header">
                            <i class="fa-solid fa-user-pen"></i> Agent Information
                        </div>
                        <p class="settings-card-desc">
                            Update your name, admin login username, and email for receiving ticket notifications.
                        </p>

                        <form action="admin_profile_update.php" method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="update_info">

                            <div class="form-field-group">
                                <label for="admin_fullname">Full Name / Agent Name</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-user-tie"></i>
                                    <input type="text" id="admin_fullname" name="fullname" value="<?php echo htmlspecialchars($admin['fullname'] ?? ''); ?>" placeholder="e.g. Lead Support Agent">
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="admin_username">Login Username</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="admin_username" name="username" value="<?php echo htmlspecialchars($admin['username']); ?>" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="admin_email">Notification Alert Email</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="admin_email" name="email" value="<?php echo htmlspecialchars($admin['email'] ?? ''); ?>" placeholder="e.g. support-lead@company.com">
                                </div>
                                <small style="color:#64748b; font-size:11px; margin-top:4px; display:block;">Admin notifications for new and reopened complaints will be sent here.</small>
                            </div>

                            <div class="form-field-group">
                                <label for="admin_profile_photo">Profile Photo (Optional)</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-image"></i>
                                    <input type="file" id="admin_profile_photo" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
                                </div>
                                <small style="color:#64748b; font-size:11px; margin-top:4px; display:block;">PNG, JPG, WEBP or GIF (Max 3MB)</small>
                            </div>

                            <button type="submit" class="save-btn">
                                <i class="fa-solid fa-floppy-disk"></i> Save Agent Details
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Form 2: Admin Password Update -->
                <div class="settings-card">
                    <div>
                        <div class="settings-card-header">
                            <i class="fa-solid fa-shield-halved"></i> Security &amp; Password
                        </div>
                        <p class="settings-card-desc">
                            Change your administrator password to maintain portal and system security.
                        </p>

                        <form action="admin_profile_update.php" method="POST">
                            <input type="hidden" name="action" value="update_password">

                            <div class="form-field-group">
                                <label for="current_password">Current Password</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-key"></i>
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter current admin password" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="new_password">New Password (Min. 6 characters)</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-lock"></i>
                                    <input type="password" id="new_password" name="new_password" placeholder="Enter new strong password" minlength="6" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-circle-check"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat new password" minlength="6" required>
                                </div>
                            </div>

                            <button type="submit" class="save-btn" style="background:#0f172a;">
                                <i class="fa-solid fa-shield"></i> Update Admin Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>

        </div>

    <!-- Fire-and-forget background email queue processor -->
    <script>
    (function() {
        if (window.fetch) {
            fetch('process_email_queue.php', { method: 'POST', keepalive: true }).catch(function() {});
        }
    })();
    </script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
