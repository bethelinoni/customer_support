<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Condition for an unseen ticket update
$unseenCondition = "(customer_viewed_at IS NULL OR GREATEST(COALESCE(responded_at, '1970-01-01 00:00:00'), COALESCE(in_progress_at, '1970-01-01 00:00:00'), COALESCE(reopened_at, '1970-01-01 00:00:00')) > customer_viewed_at)";

$statsStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total,
        SUM(status = 'Pending') AS pending,
        SUM(status = 'In Progress') AS inprogress,
        SUM(status = 'Reopened') AS reopened,
        SUM(status = 'Resolved') AS resolved,
        SUM($unseenCondition) AS total_unseen,
        SUM(status = 'Pending' AND $unseenCondition) AS unseen_pending,
        SUM(status = 'In Progress' AND $unseenCondition) AS unseen_inprogress,
        SUM(status = 'Reopened' AND $unseenCondition) AS unseen_reopened,
        SUM(status = 'Resolved' AND $unseenCondition) AS unseen_resolved
    FROM tickets 
    WHERE user_id = ?
");
$statsStmt->bind_param("i", $user_id);
$statsStmt->execute();
$stats = $statsStmt->get_result()->fetch_assoc();
$statsStmt->close();

$total          = (int)($stats['total'] ?? 0);
$pending        = (int)($stats['pending'] ?? 0);
$inprogress     = (int)($stats['inprogress'] ?? 0);
$reopened       = (int)($stats['reopened'] ?? 0);
$resolved       = (int)($stats['resolved'] ?? 0);
$totalUnseen    = (int)($stats['total_unseen'] ?? 0);
$unseenPending  = (int)($stats['unseen_pending'] ?? 0);
$unseenInprog   = (int)($stats['unseen_inprogress'] ?? 0);
$unseenReopened = (int)($stats['unseen_reopened'] ?? 0);
$unseenResolved = (int)($stats['unseen_resolved'] ?? 0);

$navUnseenBadge      = formatBadgeCount($totalUnseen);
$unseenPendingBadge  = formatBadgeCount($unseenPending);
$unseenInprogBadge   = formatBadgeCount($unseenInprog);
$unseenReopenedBadge = formatBadgeCount($unseenReopened);
$unseenResolvedBadge = formatBadgeCount($unseenResolved);

// Recent Tickets
$stmt = $conn->prepare("
    SELECT id, subject, category, priority, message, status, attachment, created_at
    FROM tickets
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 5
");

$stmt->bind_param("i", $user_id);
$stmt->execute();

$recentTickets = $stmt->get_result();
$stmt->close();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
ob_start();
?>
    <style>
        /* Customer Dashboard - Modern Sectioned Layout */
        .dashboard-page-wrapper {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 24px;
        }

        /* 1. Welcome Hero Section */
        .welcome-hero-card {
            background: linear-gradient(135deg, #EFF6FF 0%, #DBEAFE 100%);
            border: 1px solid #BFDBFE;
            border-radius: 14px;
            padding: 28px 32px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            box-shadow: var(--shadow-sm);
        }

        .welcome-hero-content h2 {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin: 0 0 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .welcome-hero-content p {
            font-size: 14px;
            color: var(--text-secondary);
            margin: 0;
            max-width: 680px;
            line-height: 1.55;
        }

        .welcome-hero-actions {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        /* 2. Stats Grid Section */
        .dashboard-stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            gap: 18px;
        }

        .stat-card-link {
            text-decoration: none;
            color: inherit;
            display: block;
            outline: none;
        }

        .stat-tile {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 22px 20px;
            position: relative;
            box-shadow: var(--shadow-xs);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 130px;
        }

        .stat-tile:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-md);
        }

        .stat-tile:active {
            transform: scale(0.99);
        }

        .stat-tile-top {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
        }

        .stat-tile-icon {
            width: 44px;
            height: 44px;
            border-radius: 0px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 18px;
        }

        .stat-tile-total .stat-tile-icon { background: #F4F4F5; color: #18181B; }
        .stat-tile-pending .stat-tile-icon { background: #FEF3C7; color: #D97706; }
        .stat-tile-inprogress .stat-tile-icon { background: #E0F2FE; color: #0284C7; }
        .stat-tile-reopened .stat-tile-icon { background: #F5F3FF; color: #7C3AED; }
        .stat-tile-resolved .stat-tile-icon { background: #ECFDF5; color: #059669; }

        .stat-tile-num {
            font-size: 32px;
            font-weight: 800;
            color: var(--text-main);
            line-height: 1.1;
            margin-bottom: 4px;
        }

        .stat-tile-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-muted);
        }

        .stat-badge-chip {
            position: absolute;
            top: 14px;
            right: 14px;
            background: #EF4444;
            color: #FFFFFF;
            font-size: 11px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 999px;
            box-shadow: 0 2px 6px rgba(239, 68, 68, 0.35);
        }

        /* 3. Recent Complaints Section */
        .recent-complaints-panel {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 14px;
            padding: 26px 30px;
            box-shadow: var(--shadow-sm);
        }

        .recent-complaints-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 22px;
            padding-bottom: 16px;
            border-bottom: 1px solid var(--border-subtle);
            flex-wrap: wrap;
            gap: 12px;
        }

        .recent-complaints-title {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .recent-complaints-title h3 {
            margin: 0;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
        }

        .recent-complaints-subtext {
            font-size: 13px;
            color: var(--text-muted);
        }

        .recent-tickets-feed {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .recent-ticket-link {
            text-decoration: none;
            color: inherit;
            display: block;
            outline: none;
        }

        .recent-ticket-item {
            background: var(--bg-card-subtle);
            border: 1px solid var(--border-subtle);
            border-radius: 10px;
            padding: 18px 22px;
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease, background 0.2s ease;
            display: flex;
            flex-direction: column;
            gap: 10px;
            cursor: pointer;
        }

        .recent-ticket-item:hover {
            transform: translateY(-2px);
            background: var(--bg-card);
            border-color: #93C5FD;
            box-shadow: var(--shadow-md);
        }

        .recent-ticket-item:active {
            transform: scale(0.995);
        }

        .ticket-item-top {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 12px;
            flex-wrap: wrap;
        }

        .ticket-id-tag {
            font-size: 12px;
            font-weight: 700;
            color: var(--primary-color);
            background: var(--primary-light);
            padding: 3px 8px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .ticket-item-subject {
            font-size: 16px;
            font-weight: 700;
            color: var(--text-main);
            line-height: 1.4;
            margin-top: 4px;
        }

        .ticket-item-badges {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }

        .ticket-item-preview {
            font-size: 13.5px;
            color: var(--text-secondary);
            line-height: 1.55;
            margin: 0;
        }

        .ticket-item-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 8px;
            border-top: 1px dashed var(--border-subtle);
            font-size: 12.5px;
            color: var(--text-muted);
            flex-wrap: wrap;
            gap: 10px;
        }

        .ticket-item-meta {
            display: flex;
            align-items: center;
            gap: 14px;
            flex-wrap: wrap;
        }

        .meta-date, .meta-attachment {
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .view-details-hint {
            color: var(--primary-color);
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12.5px;
            transition: transform 0.15s ease;
        }

        .recent-ticket-item:hover .view-details-hint {
            transform: translateX(3px);
        }

        /* Dark Mode Overrides for Customer Dashboard */
        [data-theme="dark"] .welcome-hero-card {
            background: linear-gradient(135deg, #1E293B 0%, #0F172A 100%) !important;
            border-color: var(--border-color) !important;
        }

        [data-theme="dark"] .stat-tile:hover {
            border-color: var(--primary-color) !important;
            background: var(--bg-card-subtle) !important;
        }

        [data-theme="dark"] .recent-ticket-item:hover {
            background: var(--bg-card-subtle) !important;
            border-color: var(--primary-color) !important;
        }

        [data-theme="dark"] .stat-tile-total .stat-tile-icon { background: #18181B; color: #FAFAFA; }
        [data-theme="dark"] .stat-tile-pending .stat-tile-icon { background: rgba(245, 158, 11, 0.16); color: #FBBF24; }
        [data-theme="dark"] .stat-tile-inprogress .stat-tile-icon { background: rgba(2, 132, 199, 0.16); color: #38BDF8; }
        [data-theme="dark"] .stat-tile-reopened .stat-tile-icon { background: rgba(124, 58, 237, 0.16); color: #A78BFA; }
        [data-theme="dark"] .stat-tile-resolved .stat-tile-icon { background: rgba(16, 185, 129, 0.16); color: #34D399; }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_customer.php';
?>
<div class="dashboard-page-wrapper">

    <!-- 1. Hero Welcome Section -->
    <section class="welcome-hero-card">
        <div class="welcome-hero-content">
            <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['fullname']); ?> 👋</h2>
            <p>
                From your customer portal, you can submit new support requests, monitor ticket progress in real-time, and review resolutions from our dedicated support team.
            </p>
        </div>
        <div class="welcome-hero-actions">
            <a href="submit_ticket.php" class="btn btn-primary">
                <i class="fa-solid fa-plus-circle"></i> Submit New Ticket
            </a>
            <a href="view_tickets.php" class="btn btn-secondary">
                <i class="fa-solid fa-clipboard-list"></i> View All Tickets
            </a>
        </div>
    </section>

    <!-- 2. Interactive Stats Grid Section -->
    <section class="dashboard-stats-grid">
        <a href="view_tickets.php" class="stat-card-link" title="View all complaints">
            <div class="stat-tile stat-tile-total">
                <div class="stat-tile-top">
                    <div class="stat-tile-icon">
                        <i class="fa-solid fa-clipboard-list"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-tile-num"><?php echo $total; ?></div>
                    <div class="stat-tile-label">Total Tickets</div>
                </div>
            </div>
        </a>

        <a href="view_tickets.php?status=Pending" class="stat-card-link" title="View pending complaints">
            <div class="stat-tile stat-tile-pending">
                <?php if (!empty($unseenPendingBadge)): ?>
                    <span class="stat-badge-chip" title="<?php echo $unseenPending; ?> unseen updates"><?php echo $unseenPendingBadge; ?></span>
                <?php endif; ?>
                <div class="stat-tile-top">
                    <div class="stat-tile-icon">
                        <i class="fa-solid fa-clock"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-tile-num"><?php echo $pending; ?></div>
                    <div class="stat-tile-label">Pending Review</div>
                </div>
            </div>
        </a>

        <a href="view_tickets.php?status=In+Progress" class="stat-card-link" title="View tickets in progress">
            <div class="stat-tile stat-tile-inprogress">
                <?php if (!empty($unseenInprogBadge)): ?>
                    <span class="stat-badge-chip" title="<?php echo $unseenInprog; ?> unseen updates"><?php echo $unseenInprogBadge; ?></span>
                <?php endif; ?>
                <div class="stat-tile-top">
                    <div class="stat-tile-icon">
                        <i class="fa-solid fa-spinner"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-tile-num"><?php echo $inprogress; ?></div>
                    <div class="stat-tile-label">In Progress</div>
                </div>
            </div>
        </a>

        <a href="view_tickets.php?status=Reopened" class="stat-card-link" title="View reopened complaints">
            <div class="stat-tile stat-tile-reopened">
                <?php if (!empty($unseenReopenedBadge)): ?>
                    <span class="stat-badge-chip" title="<?php echo $unseenReopened; ?> unseen updates"><?php echo $unseenReopenedBadge; ?></span>
                <?php endif; ?>
                <div class="stat-tile-top">
                    <div class="stat-tile-icon">
                        <i class="fa-solid fa-arrows-rotate"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-tile-num"><?php echo $reopened; ?></div>
                    <div class="stat-tile-label">Re-opened</div>
                </div>
            </div>
        </a>

        <a href="view_tickets.php?status=Resolved" class="stat-card-link" title="View resolved complaints">
            <div class="stat-tile stat-tile-resolved">
                <?php if (!empty($unseenResolvedBadge)): ?>
                    <span class="stat-badge-chip" title="<?php echo $unseenResolved; ?> unseen updates"><?php echo $unseenResolvedBadge; ?></span>
                <?php endif; ?>
                <div class="stat-tile-top">
                    <div class="stat-tile-icon">
                        <i class="fa-solid fa-circle-check"></i>
                    </div>
                </div>
                <div>
                    <div class="stat-tile-num"><?php echo $resolved; ?></div>
                    <div class="stat-tile-label">Resolved Tickets</div>
                </div>
            </div>
        </a>
    </section>

    <!-- 3. Recent Complaints Section -->
    <section class="recent-complaints-panel">
        <div class="recent-complaints-header">
            <div class="recent-complaints-title">
                <h3><i class="fa-solid fa-clock-rotate-left"></i> Recent Complaints</h3>
                <span class="recent-complaints-subtext">&bull; Latest 5 submissions</span>
            </div>
            <a href="view_tickets.php" class="btn btn-secondary btn-sm">
                View All Complaints <i class="fa-solid fa-arrow-right"></i>
            </a>
        </div>

        <div class="recent-tickets-feed">
            <?php if ($recentTickets && $recentTickets->num_rows > 0): ?>
                <?php while ($ticket = $recentTickets->fetch_assoc()): 
                    $ticketId = (int)$ticket['id'];
                    $tSubject = htmlspecialchars($ticket['subject']);
                    $tMessage = htmlspecialchars($ticket['message']);
                    if (strlen($tMessage) > 130) {
                        $tMessage = substr($tMessage, 0, 130) . "...";
                    }
                    $category = htmlspecialchars($ticket['category'] ?? 'General Inquiry');
                    $priority = htmlspecialchars($ticket['priority'] ?? 'Medium');
                    $status   = htmlspecialchars($ticket['status'] ?? 'Pending');
                    $date     = date("d M Y • h:i A", strtotime($ticket['created_at']));
                    $isNew    = (strtotime($ticket['created_at']) >= strtotime("-24 hours"));

                    $priorityClass = 'priority-medium';
                    $priorityIcon  = 'fa-circle-dot';
                    if ($priority === 'Urgent') {
                        $priorityClass = 'priority-urgent';
                        $priorityIcon  = 'fa-circle-exclamation';
                    } elseif ($priority === 'High') {
                        $priorityClass = 'priority-high';
                        $priorityIcon  = 'fa-triangle-exclamation';
                    } elseif ($priority === 'Low') {
                        $priorityClass = 'priority-low';
                        $priorityIcon  = 'fa-circle';
                    }

                    $catIcon = 'fa-circle-info';
                    if ($category === 'Technical Support') $catIcon = 'fa-laptop-code';
                    elseif ($category === 'Billing & Payments') $catIcon = 'fa-credit-card';
                    elseif ($category === 'Account & Security') $catIcon = 'fa-shield-halved';
                    elseif ($category === 'Customer Service') $catIcon = 'fa-headset';

                    $statusClass = 'status-pending';
                    if ($status === 'Resolved') $statusClass = 'status-resolved';
                    elseif ($status === 'In Progress') $statusClass = 'status-inprogress';
                    elseif ($status === 'Reopened') $statusClass = 'status-reopened';
                ?>
                    <a href="view_tickets.php?ticket_id=<?php echo $ticketId; ?>#ticket-<?php echo $ticketId; ?>" class="recent-ticket-link" title="View complaint details">
                        <div class="recent-ticket-item">
                            <div class="ticket-item-top">
                                <div>
                                    <span class="ticket-id-tag">#TKT-<?php echo sprintf("%04d", $ticketId); ?></span>
                                    <div class="ticket-item-subject">
                                        <?php echo $tSubject; ?>
                                    </div>
                                </div>
                                <div class="ticket-item-badges">
                                    <span class="status-badge <?php echo $statusClass; ?>"><?php echo $status; ?></span>
                                    <span class="priority-badge <?php echo $priorityClass; ?>"><i class="fa-solid <?php echo $priorityIcon; ?>"></i> <?php echo $priority; ?></span>
                                    <?php if ($isNew): ?>
                                        <span class="new-badge">NEW</span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <p class="ticket-item-preview"><?php echo $tMessage; ?></p>

                            <div class="ticket-item-footer">
                                <div class="ticket-item-meta">
                                    <span class="category-tag"><i class="fa-solid <?php echo $catIcon; ?>"></i> <?php echo $category; ?></span>
                                    <span class="meta-date"><i class="fa-regular fa-calendar"></i> <?php echo $date; ?></span>
                                    <?php if (!empty($ticket['attachment'])): ?>
                                        <span class="meta-attachment" style="color: #1976d2;"><i class="fa-solid fa-paperclip"></i> Screenshot Attached</span>
                                    <?php endif; ?>
                                </div>
                                <span class="view-details-hint">
                                    View ticket <i class="fa-solid fa-chevron-right"></i>
                                </span>
                            </div>
                        </div>
                    </a>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="empty-state" style="padding: 48px 24px; text-align: center; background: var(--bg-card-subtle); border: 1px dashed var(--border-color); border-radius: 12px;">
                    <div style="font-size: 38px; color: var(--text-subtle); margin-bottom: 12px;">
                        <i class="fa-regular fa-clipboard"></i>
                    </div>
                    <h4 style="font-size: 17px; font-weight: 700; color: var(--text-main); margin-bottom: 6px;">No complaints submitted yet</h4>
                    <p style="font-size: 14px; color: var(--text-muted); margin-bottom: 20px;">Need assistance? Our support team is here to assist you promptly.</p>
                    <a href="submit_ticket.php" class="btn btn-primary btn-sm">
                        <i class="fa-solid fa-plus-circle"></i> Create Your First Ticket
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </section>

</div>

<script>
function confirmLogout() {
    if (confirm("Are you sure you want to logout?")) {
        window.location.href = "logout.php";
    }
}

// Fire-and-forget background email queue processor
(function() {
    if (window.fetch) {
        fetch('process_email_queue.php', { method: 'POST', keepalive: true }).catch(function() {});
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>