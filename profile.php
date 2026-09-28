<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

$unseenTicketsCount = getUnseenTicketsCount($conn, $user_id);
$unseenNavBadge = formatBadgeCount($unseenTicketsCount);

// Fetch user data including profile picture
$stmt = $conn->prepare("SELECT id, fullname, email, phone, profile_picture, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$userResult = $stmt->get_result();

if ($userResult->num_rows !== 1) {
    header("Location: logout.php");
    exit();
}

$user = $userResult->fetch_assoc();
$stmt->close();

// Fetch ticket statistics for this user (5 distinct categories)
$statStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'In Progress') AS inprogress,
        SUM(status = 'Reopened') AS reopened,
        SUM(status = 'Resolved') AS resolved
    FROM tickets 
    WHERE user_id = ?
");
$statStmt->bind_param("i", $user_id);
$statStmt->execute();
$stats = $statStmt->get_result()->fetch_assoc();
$statStmt->close();

$totalTickets = (int)($stats['total'] ?? 0);
$pendingTickets = (int)($stats['pending'] ?? 0);
$inprogressTickets = (int)($stats['inprogress'] ?? 0);
$reopenedTickets = (int)($stats['reopened'] ?? 0);
$resolvedTickets = (int)($stats['resolved'] ?? 0);

// Generate initials for avatar
$nameParts = explode(' ', trim($user['fullname']));
$initials = '';
foreach ($nameParts as $part) {
    if (!empty($part)) {
        $initials .= strtoupper($part[0]);
    }
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = 'CU';

$memberSince = !empty($user['created_at']) ? date("d M Y", strtotime($user['created_at'])) : 'Recent Member';
$pageTitle = 'My Profile';
$activeNav = 'profile';
$navUnseenBadge = $unseenNavBadge;
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
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
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
            background: linear-gradient(135deg, #1976d2, #0d47a1);
            color: #ffffff;
            font-size: 30px;
            font-weight: 700;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 4px 14px rgba(13, 71, 161, 0.25);
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
            color: var(--text-secondary);
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

        .profile-tag-joined {
            background: #fdf2f8;
            color: #9d174d;
            border: 1px solid #fbcfe8;
        }

        .profile-tag-phone {
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

        [data-theme="dark"] .profile-tag-joined,
        [data-theme="dark"] .profile-hero-tags .profile-tag-joined {
            background: rgba(244, 114, 182, 0.2) !important;
            color: #f472b6 !important;
            border-color: rgba(244, 114, 182, 0.45) !important;
        }

        [data-theme="dark"] .profile-tag-phone,
        [data-theme="dark"] .profile-hero-tags .profile-tag-phone {
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
            border-color: var(--primary-color);
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
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.1;
        }

        .profile-stat-lbl {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
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
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            padding: 24px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .settings-card-header {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 18px;
            font-weight: 700;
            color: var(--primary-color);
            margin-bottom: 6px;
        }

        .settings-card-desc {
            color: var(--text-secondary);
            font-size: 13px;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        .form-field-group {
            margin-bottom: 16px;
        }

        .form-field-group label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            font-size: 13px;
            color: var(--text-main);
        }

        .input-icon-wrap {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-icon-wrap i {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 14px;
        }

        .input-icon-wrap input {
            width: 100%;
            padding: 11px 14px 11px 38px;
            background: var(--bg-card-subtle);
            color: var(--text-main);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .input-icon-wrap input:focus {
            outline: none;
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.12);
        }

        .save-btn {
            width: 100%;
            padding: 12px;
            background: var(--primary-color);
            color: var(--text-inverse);
            border: 1px solid var(--primary-color);
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: background 0.2s ease, transform 0.15s ease;
            margin-top: 8px;
        }

        .save-btn:hover {
            background: var(--primary-hover);
            transform: translateY(-1px);
        }

        .save-btn.btn-password {
            background: #27272A;
            border-color: #3F3F46;
            color: #FFFFFF;
        }

        .save-btn.btn-password:hover {
            background: #3F3F46;
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

        [data-theme="dark"] .save-btn.btn-password {
            background: #27272A !important;
            color: #FAFAFA !important;
            border-color: #3F3F46 !important;
        }

        [data-theme="dark"] .save-btn.btn-password:hover {
            background: #3F3F46 !important;
        }

        .save-btn i,
        .save-btn span {
            color: inherit !important;
        }

        .success-message {
            background: #e8f5e9;
            color: #2e7d32;
            border: 1px solid #81c784;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 22px;
            text-align: center;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .error-message {
            background: #ffebee;
            color: #c62828;
            border: 1px solid #ef9a9a;
            padding: 14px;
            border-radius: 8px;
            margin-bottom: 22px;
            text-align: center;
            font-size: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_customer.php';
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

            <!-- Profile Hero Summary Card -->
            <div class="profile-hero-card">
                <div class="profile-avatar-container">
                    <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $user['profile_picture'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($user['profile_picture']); ?>" alt="Profile Picture" class="profile-avatar-img">
                    <?php else: ?>
                        <div class="profile-avatar-circle" title="<?php echo htmlspecialchars($user['fullname']); ?>">
                            <?php echo $initials; ?>
                        </div>
                    <?php endif; ?>
                    <label for="quick_photo_input" class="avatar-upload-trigger" title="Upload profile picture">
                        <i class="fa-solid fa-camera"></i>
                    </label>
                    <form id="quickPhotoForm" action="profile_photo_upload.php" method="POST" enctype="multipart/form-data" style="display:none;">
                        <input type="file" id="quick_photo_input" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif" onchange="document.getElementById('quickPhotoForm').submit();">
                    </form>
                </div>

                <div class="profile-hero-info">
                    <div class="profile-hero-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                    <div class="profile-hero-email">
                        <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?>
                    </div>
                    <div class="profile-hero-tags">
                        <span class="profile-tag profile-tag-id">
                            <i class="fa-solid fa-id-card"></i> Account ID: #USR-<?php echo sprintf("%04d", $user['id']); ?>
                        </span>
                        <span class="profile-tag profile-tag-joined">
                            <i class="fa-solid fa-calendar-check"></i> Joined <?php echo $memberSince; ?>
                        </span>
                        <?php if (!empty($user['phone'])): ?>
                            <span class="profile-tag profile-tag-phone">
                                <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?>
                            </span>
                        <?php endif; ?>
                        <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $user['profile_picture'])): ?>
                            <form action="profile_photo_upload.php" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to remove your profile picture?');">
                                <input type="hidden" name="action" value="remove">
                                <button type="submit" style="background:#fee2e2; color:#b91c1c; border:1px solid #fca5a5; padding:4px 10px; border-radius:20px; font-weight:600; font-size:12px; cursor:pointer; display:inline-flex; align-items:center; gap:5px;">
                                    <i class="fa-solid fa-trash-can"></i> Remove Photo
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Activity Statistics Strip (4 Distinct Sections) -->
            <div class="profile-stats-strip">
                <a href="view_tickets.php" style="text-decoration:none; color:inherit;">
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

                <a href="view_tickets.php?status=Pending" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#fff8e1; color:#f59e0b;">
                            <i class="fa-solid fa-clock"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $pendingTickets; ?></div>
                            <div class="profile-stat-lbl">Pending</div>
                        </div>
                    </div>
                </a>

                <a href="view_tickets.php?status=In+Progress" style="text-decoration:none; color:inherit;">
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

                <a href="view_tickets.php?status=Reopened" style="text-decoration:none; color:inherit;">
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

                <a href="view_tickets.php?status=Resolved" style="text-decoration:none; color:inherit;">
                    <div class="profile-stat-box">
                        <div class="profile-stat-icon" style="background:#e8f5e9; color:#2e7d32;">
                            <i class="fa-solid fa-circle-check"></i>
                        </div>
                        <div>
                            <div class="profile-stat-val"><?php echo $resolvedTickets; ?></div>
                            <div class="profile-stat-lbl">Resolved Complaints</div>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Settings & Password Forms Grid -->
            <div class="profile-forms-grid">

                <!-- Form 1: Personal Profile Details -->
                <div class="settings-card">
                    <div>
                        <div class="settings-card-header">
                            <i class="fa-solid fa-user-pen"></i> Personal Information
                        </div>
                        <p class="settings-card-desc">
                            Update your basic contact and account information across the customer portal.
                        </p>

                        <form action="profile_update.php" method="POST" enctype="multipart/form-data">
                            <div class="form-field-group">
                                <label for="fullname">Full Name</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-user"></i>
                                    <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" placeholder="Enter your full name" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="email">Email Address</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-envelope"></i>
                                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" placeholder="Enter your email address" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="phone">Phone Number (Optional)</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-phone"></i>
                                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" placeholder="e.g. +234 800 123 4567">
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="profile_photo_input">Profile Picture (Optional)</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-image"></i>
                                    <input type="file" id="profile_photo_input" name="profile_photo" accept="image/jpeg,image/png,image/webp,image/gif">
                                </div>
                                <small style="color:#64748b; font-size:11px; margin-top:4px; display:block;">PNG, JPG, WEBP or GIF (Max 3MB)</small>
                            </div>

                            <button type="submit" class="save-btn">
                                <i class="fa-solid fa-floppy-disk"></i> Save Profile Changes
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Form 2: Security & Password Update -->
                <div class="settings-card">
                    <div>
                        <div class="settings-card-header">
                            <i class="fa-solid fa-shield-halved"></i> Security &amp; Password
                        </div>
                        <p class="settings-card-desc">
                            Change your account password to ensure your account remains safe and secure.
                        </p>

                        <form action="password_update.php" method="POST">
                            <div class="form-field-group">
                                <label for="current_password">Current Password</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-key"></i>
                                    <input type="password" id="current_password" name="current_password" placeholder="Enter current password" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="new_password">New Password (Min. 6 characters)</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-lock"></i>
                                    <input type="password" id="new_password" name="new_password" placeholder="Enter new password" minlength="6" required>
                                </div>
                            </div>

                            <div class="form-field-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <div class="input-icon-wrap">
                                    <i class="fa-solid fa-lock-open"></i>
                                    <input type="password" id="confirm_password" name="confirm_password" placeholder="Re-type new password" minlength="6" required>
                                </div>
                            </div>

                            <button type="submit" class="save-btn btn-password">
                                <i class="fa-solid fa-key"></i> Update Password
                            </button>
                        </form>
                    </div>
                </div>

            </div>

            <div style="text-align:center; margin-top:28px;">
                <a href="dashboard.php" style="color:#1976d2; text-decoration:none; font-size:14px; font-weight:600; display:inline-flex; align-items:center; gap:6px;">
                    <i class="fa-solid fa-arrow-left"></i> Return to Dashboard
                </a>
            </div>

        </div>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>
