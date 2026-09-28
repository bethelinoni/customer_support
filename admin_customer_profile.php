<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

// Unread email logs count for notification badge
$unreadEmailsCount = getUnreadEmailLogsCount($conn);
$unreadEmailsBadge = formatBadgeCount($unreadEmailsCount);

$customerId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($customerId <= 0) {
    header("Location: admin_customers.php");
    exit();
}

// 1. Fetch Customer Profile Details
$stmt = $conn->prepare("SELECT id, fullname, email, phone, profile_picture, created_at FROM users WHERE id = ?");
$stmt->bind_param("i", $customerId);
$stmt->execute();
$customer = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$customer) {
    header("Location: admin_customers.php");
    exit();
}

// Generate customer initials
$cName = $customer['fullname'];
$parts = explode(' ', trim($cName));
$initials = '';
foreach ($parts as $p) {
    if (!empty($p)) $initials .= strtoupper($p[0]);
    if (strlen($initials) >= 2) break;
}
if (empty($initials)) $initials = 'CU';

$dateJoined = !empty($customer['created_at']) ? date("d M Y", strtotime($customer['created_at'])) : 'Unknown';

// 2. Fetch Aggregated Metrics for this Customer
$statStmt = $conn->prepare("
    SELECT 
        COUNT(*) AS total_tickets,
        SUM(status = 'Pending') AS pending_tickets,
        SUM(status = 'In Progress') AS inprogress_tickets,
        SUM(status = 'Reopened') AS reopened_tickets,
        SUM(status = 'Resolved') AS resolved_tickets,
        MIN(created_at) AS first_ticket_date,
        MAX(created_at) AS latest_ticket_date,
        AVG(CASE WHEN rating IS NOT NULL AND rating > 0 THEN rating ELSE NULL END) AS avg_csat,
        SUM(CASE WHEN rating IS NOT NULL AND rating > 0 THEN 1 ELSE 0 END) AS rated_count
    FROM tickets
    WHERE user_id = ?
");
$statStmt->bind_param("i", $customerId);
$statStmt->execute();
$stats = $statStmt->get_result()->fetch_assoc();
$statStmt->close();

$totalTickets = (int)($stats['total_tickets'] ?? 0);
$pendingTickets = (int)($stats['pending_tickets'] ?? 0);
$inprogressTickets = (int)($stats['inprogress_tickets'] ?? 0);
$reopenedTickets = (int)($stats['reopened_tickets'] ?? 0);
$resolvedTickets = (int)($stats['resolved_tickets'] ?? 0);
$firstTicket = !empty($stats['first_ticket_date']) ? date("d M Y", strtotime($stats['first_ticket_date'])) : 'None';
$latestTicket = !empty($stats['latest_ticket_date']) ? date("d M Y, h:i A", strtotime($stats['latest_ticket_date'])) : 'None';
$avgCsat = $stats['avg_csat'] !== null ? round((float)$stats['avg_csat'], 1) : null;
$ratedCount = (int)($stats['rated_count'] ?? 0);

// 3. Fetch All Tickets for this Customer
$tktStmt = $conn->prepare("
    SELECT 
        id, subject, category, priority, status, created_at, responded_at, reopened_at, rating, feedback, rated_at, rating_updated_at
    FROM tickets
    WHERE user_id = ?
    ORDER BY created_at DESC
");
$tktStmt->bind_param("i", $customerId);
$tktStmt->execute();
$tickets = $tktStmt->get_result();
$tktStmt->close();
$pageTitle = htmlspecialchars($cName) . ' - Customer Details';
$activeNav = 'customers';
$navUnreadBadge = $unreadEmailsBadge;
ob_start();
?>
    <style>
        .customer-profile-page {
            max-width: 1600px;
            margin: 0 auto;
            width: 100%;
        }

        .breadcrumb-strip {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 20px;
            font-size: 13px;
        }

        .breadcrumb-strip a {
            color: #1976d2;
            text-decoration: none;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            transition: all 0.15s ease;
        }

        .breadcrumb-strip a:hover {
            background: var(--primary-color);
            color: #ffffff;
        }

        /* Customer Hero Profile Card */
        .customer-hero-card {
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

        .customer-hero-avatar-wrap {
            position: relative;
            width: 88px;
            height: 88px;
            flex-shrink: 0;
        }

        .customer-hero-avatar-img {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid #ffffff;
            box-shadow: 0 4px 14px rgba(13, 71, 161, 0.22);
            display: block;
        }

        .customer-hero-avatar-circle {
            width: 88px;
            height: 88px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1976d2, #0d47a1);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: 800;
            letter-spacing: 1px;
            box-shadow: 0 4px 14px rgba(13, 71, 161, 0.22);
            border: 3px solid #ffffff;
        }

        .customer-hero-info {
            flex: 1;
            min-width: 280px;
        }

        .customer-hero-name {
            font-size: 24px;
            font-weight: 800;
            color: var(--text-main);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customer-hero-email {
            font-size: 14px;
            color: var(--text-secondary);
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .customer-hero-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
        }

        .customer-tag {
            font-size: 12px;
            background: #f8fafc;
            color: #475569;
            border: 1px solid #e2e8f0;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        /* 5-Card Stats Strip */
        .profile-stats-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(190px, 1fr));
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
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
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
            color: var(--text-secondary);
            margin-top: 2px;
            font-weight: 500;
        }

        /* Tickets Table Card */
        .tickets-table-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .tickets-table-header {
            padding: 18px 22px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .tickets-table-header h3 {
            margin: 0;
            font-size: 17px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .tickets-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            text-align: left;
        }

        .tickets-table th {
            background: var(--bg-card-subtle);
            color: var(--text-main);
            padding: 12px 16px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .tickets-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .history-subject-cell {
            font-weight: 600;
            color: var(--text-main);
        }

        [data-theme="dark"] .history-subject-cell {
            color: #F8FAFC !important;
        }

        .tickets-table tr.clickable-ticket-row {
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .tickets-table tr.clickable-ticket-row:hover {
            background: var(--bg-card-subtle);
        }

        .badge-status {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 10px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 11px;
            line-height: 1;
        }

        .badge-status-pending {
            background: #fff8e1;
            color: #b45309;
            border: 1px solid #fef3c7;
        }

        .badge-status-inprogress {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-status-reopened {
            background: #f5f3ff;
            color: #7c3aed;
            border: 1px solid #ddd6fe;
        }

        .badge-status-resolved {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-priority {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-priority-low { background: #f1f5f9; color: #475569; }
        .badge-priority-medium { background: #fef9c3; color: #854d0e; }
        .badge-priority-high { background: #fee2e2; color: #b91c1c; }
        .badge-priority-critical { background: #7f1d1d; color: #ffffff; }

        .btn-open-ticket {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #1976d2;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            text-decoration: none;
            transition: all 0.15s ease;
            white-space: nowrap;
        }

        .btn-open-ticket:hover {
            background: #1976d2;
            color: #ffffff;
            border-color: #1976d2;
        }

        .csat-star-rating {
            color: #f59e0b;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 2px;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_admin.php';
?>
        <div class="customer-profile-page">

            <!-- Breadcrumb Navigation -->
            <div class="breadcrumb-strip">
                <a href="admin_customers.php">
                    <i class="fa-solid fa-arrow-left"></i> Back to Customers Directory
                </a>
                <a href="analytics.php">
                    <i class="fa-solid fa-chart-line"></i> Back to Analytics
                </a>
            </div>

            <!-- Customer Hero Profile Card -->
            <div class="customer-hero-card">
                <div class="customer-hero-avatar-wrap">
                    <?php if (!empty($customer['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $customer['profile_picture'])): ?>
                        <img src="uploads/<?php echo htmlspecialchars($customer['profile_picture']); ?>" alt="<?php echo htmlspecialchars($cName); ?>" class="customer-hero-avatar-img">
                    <?php else: ?>
                        <div class="customer-hero-avatar-circle"><?php echo $initials; ?></div>
                    <?php endif; ?>
                </div>

                <div class="customer-hero-info">
                    <div class="customer-hero-name">
                        <?php echo htmlspecialchars($cName); ?>
                    </div>
                    <div class="customer-hero-email">
                        <i class="fa-regular fa-envelope"></i> <?php echo htmlspecialchars($customer['email']); ?>
                        <?php if (!empty($customer['phone'])): ?>
                            <span style="color:#cbd5e1;">&bull;</span>
                            <i class="fa-solid fa-phone"></i> <?php echo htmlspecialchars($customer['phone']); ?>
                        <?php endif; ?>
                    </div>
                    <div class="customer-hero-tags">
                        <span class="customer-tag">
                            <i class="fa-solid fa-id-card"></i> Customer ID: #USR-<?php echo sprintf("%04d", $customerId); ?>
                        </span>
                        <span class="customer-tag" style="background:#fdf2f8; color:#9d174d; border-color:#fbcfe8;">
                            <i class="fa-solid fa-calendar-check"></i> Joined <?php echo $dateJoined; ?>
                        </span>
                        <span class="customer-tag" style="background:#f0fdf4; color:#166534; border-color:#bbf7d0;">
                            <i class="fa-solid fa-clock-rotate-left"></i> First Ticket: <?php echo $firstTicket; ?>
                        </span>
                        <span class="customer-tag" style="background:#f0f9ff; color:#0369a1; border-color:#bae6fd;">
                            <i class="fa-solid fa-bolt"></i> Latest: <?php echo $latestTicket; ?>
                        </span>
                        <?php if ($avgCsat !== null): ?>
                            <span class="customer-tag" style="background:#fffbeb; color:#b45309; border-color:#fde68a;">
                                <i class="fa-solid fa-star" style="color:#f59e0b;"></i> Avg CSAT: <?php echo $avgCsat; ?>/5 (<?php echo $ratedCount; ?> rated)
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 5 Clickable Stat Cards for THIS Customer -->
            <div class="profile-stats-strip">
                <a href="admin_dashboard.php?customer_id=<?php echo $customerId; ?>" style="text-decoration:none; color:inherit;" title="View all tickets from <?php echo htmlspecialchars($cName); ?>">
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

                <a href="admin_dashboard.php?customer_id=<?php echo $customerId; ?>&status=Pending" style="text-decoration:none; color:inherit;" title="View pending tickets from <?php echo htmlspecialchars($cName); ?>">
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

                <a href="admin_dashboard.php?customer_id=<?php echo $customerId; ?>&status=In+Progress" style="text-decoration:none; color:inherit;" title="View in-progress tickets from <?php echo htmlspecialchars($cName); ?>">
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

                <a href="admin_dashboard.php?customer_id=<?php echo $customerId; ?>&status=Reopened" style="text-decoration:none; color:inherit;" title="View re-opened tickets from <?php echo htmlspecialchars($cName); ?>">
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

                <a href="admin_dashboard.php?customer_id=<?php echo $customerId; ?>&status=Resolved" style="text-decoration:none; color:inherit;" title="View resolved tickets from <?php echo htmlspecialchars($cName); ?>">
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

            <!-- Tickets Table for THIS Customer -->
            <div class="tickets-table-card">
                <div class="tickets-table-header">
                    <h3>
                        <i class="fa-solid fa-list-check"></i> Ticket History for <?php echo htmlspecialchars($cName); ?>
                    </h3>
                    <div style="font-size:13px; color:#64748b;">
                        Showing all <strong><?php echo $totalTickets; ?></strong> complaints
                    </div>
                </div>

                <?php if ($tickets && $tickets->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="tickets-table">
                            <thead>
                                <tr>
                                    <th>Ticket ID</th>
                                    <th>Subject</th>
                                    <th>Category</th>
                                    <th>Priority</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Resolved</th>
                                    <th>CSAT / Feedback</th>
                                    <th style="text-align:right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($tkt = $tickets->fetch_assoc()): 
                                    $tid = (int)$tkt['id'];
                                    $tSubject = htmlspecialchars($tkt['subject']);
                                    $tCat = htmlspecialchars($tkt['category']);
                                    $tPrio = htmlspecialchars($tkt['priority']);
                                    $tStatus = htmlspecialchars($tkt['status']);
                                    $createdDate = date("d M Y, h:i A", strtotime($tkt['created_at']));
                                    $resolvedDate = !empty($tkt['responded_at']) ? date("d M Y, h:i A", strtotime($tkt['responded_at'])) : '—';
                                    
                                    $statusClass = 'badge-status-pending';
                                    if ($tStatus === 'In Progress') $statusClass = 'badge-status-inprogress';
                                    if ($tStatus === 'Reopened') $statusClass = 'badge-status-reopened';
                                    if ($tStatus === 'Resolved') $statusClass = 'badge-status-resolved';
                                    
                                    $prioClass = 'badge-priority-medium';
                                    $lp = strtolower($tPrio);
                                    if ($lp === 'low') $prioClass = 'badge-priority-low';
                                    if ($lp === 'high') $prioClass = 'badge-priority-high';
                                    if ($lp === 'critical') $prioClass = 'badge-priority-critical';
                                    
                                    $targetUrl = "admin_dashboard.php?ticket_id={$tid}#ticket-{$tid}";
                                ?>
                                    <tr class="clickable-ticket-row" onclick="window.location.href='<?php echo $targetUrl; ?>'">
                                        <td style="font-weight:700; color:#1976d2; white-space:nowrap;">
                                            #TKT-<?php echo sprintf("%04d", $tid); ?>
                                        </td>
                                        <td class="history-subject-cell" style="font-weight:600; max-width:240px; word-break:break-word;">
                                            <?php echo $tSubject; ?>
                                        </td>
                                        <td style="white-space:nowrap;">
                                            <?php echo $tCat; ?>
                                        </td>
                                        <td>
                                            <span class="badge-priority <?php echo $prioClass; ?>">
                                                <?php echo $tPrio; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge-status <?php echo $statusClass; ?>">
                                                <?php echo $tStatus; ?>
                                            </span>
                                        </td>
                                        <td style="white-space:nowrap; font-size:12px; color:#64748b;">
                                            <?php echo $createdDate; ?>
                                        </td>
                                        <td style="white-space:nowrap; font-size:12px; color:#64748b;">
                                            <?php echo $resolvedDate; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($tkt['rating'])): ?>
                                                <div class="csat-star-rating">
                                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                                        <i class="fa-<?php echo $i <= $tkt['rating'] ? 'solid' : 'regular'; ?> fa-star <?php echo $i <= $tkt['rating'] ? 'star-filled' : 'star-empty'; ?>"></i>
                                                    <?php endfor; ?>
                                                    <span style="color:var(--text-secondary); font-weight:700; margin-left:4px;"><?php echo $tkt['rating']; ?>/5</span>
                                                </div>
                                                <?php if (!empty($tkt['feedback'])): ?>
                                                    <div style="font-size:11px; color:#64748b; font-style:italic; margin-top:3px; max-width:200px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="<?php echo htmlspecialchars($tkt['feedback']); ?>">
                                                        &ldquo;<?php echo htmlspecialchars($tkt['feedback']); ?>&rdquo;
                                                    </div>
                                                <?php endif; ?>
                                                <?php if (!empty($tkt['rating_updated_at'])): ?>
                                                    <div style="font-size:10px; color:#94a3b8; margin-top:2px;">Updated <?php echo date("d M Y", strtotime($tkt['rating_updated_at'])); ?> <span class="badge-updated" style="font-size:9px; padding:1px 5px;">Updated</span></div>
                                                <?php elseif (!empty($tkt['rated_at'])): ?>
                                                    <div style="font-size:10px; color:#94a3b8; margin-top:2px;">Rated <?php echo date("d M Y", strtotime($tkt['rated_at'])); ?></div>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span style="color:#94a3b8; font-size:12px;">Not rated</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:right; white-space:nowrap;" onclick="event.stopPropagation();">
                                            <a href="<?php echo $targetUrl; ?>" class="btn-open-ticket">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Open
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div style="padding: 40px 20px; text-align: center; color: #64748b;">
                        <i class="fa-solid fa-ticket-simple" style="font-size: 36px; color: #cbd5e1; margin-bottom: 10px;"></i>
                        <p style="margin: 0;">This customer has not submitted any tickets yet.</p>
                    </div>
                <?php endif; ?>
            </div>

        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
