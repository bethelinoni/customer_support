<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

// Unread email logs count for notification badge
$unreadEmailsCount = getUnreadEmailLogsCount($conn);
$unreadEmailsBadge = formatBadgeCount($unreadEmailsCount);

// Retrieve and sanitize search and filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$rawStatus = isset($_GET['status']) ? trim($_GET['status']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$period = isset($_GET['period']) ? trim($_GET['period']) : '';
$type = isset($_GET['type']) ? trim($_GET['type']) : '';
$date = isset($_GET['date']) ? trim($_GET['date']) : '';
$customerId = isset($_GET['customer_id']) ? (int)$_GET['customer_id'] : 0;
$rated = isset($_GET['rated']) ? (int)$_GET['rated'] : 0;
$rating = isset($_GET['rating']) ? (int)$_GET['rating'] : 0;
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : '';
$targetTicketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$highlight = isset($_GET['highlight']) ? trim($_GET['highlight']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 5; // 5 tickets per page

// Normalize status (supports both lowercase and PascalCase)
$status = '';
$statusMap = [
    'pending' => 'Pending',
    'in_progress' => 'In Progress',
    'in progress' => 'In Progress',
    'resolved' => 'Resolved',
    'reopened' => 'Reopened',
    'Pending' => 'Pending',
    'In Progress' => 'In Progress',
    'Resolved' => 'Resolved',
    'Reopened' => 'Reopened',
];
if (!empty($rawStatus) && isset($statusMap[$rawStatus])) {
    $status = $statusMap[$rawStatus];
} elseif (!empty($rawStatus) && $rawStatus !== 'All') {
    $status = $rawStatus;
}

// Fetch customer name if filtering by customer_id
$customerFilterName = '';
if ($customerId > 0) {
    $cStmt = $conn->prepare("SELECT fullname FROM users WHERE id = ?");
    $cStmt->bind_param("i", $customerId);
    $cStmt->execute();
    $cRes = $cStmt->get_result();
    if ($cRow = $cRes->fetch_assoc()) {
        $customerFilterName = $cRow['fullname'];
    }
    $cStmt->close();
}

// Build active filter chips for display
$activeFilterChips = [];

if ($search !== '') {
    $activeFilterChips[] = ['label' => 'Search: "' . htmlspecialchars($search) . '"', 'remove' => ['search']];
}
if ($status !== '' && $status !== 'All') {
    $activeFilterChips[] = ['label' => 'Status: ' . htmlspecialchars($status), 'remove' => ['status']];
}
if ($category !== '' && $category !== 'All') {
    $activeFilterChips[] = ['label' => 'Category: ' . htmlspecialchars($category), 'remove' => ['category']];
}
if ($priority !== '' && $priority !== 'All') {
    $activeFilterChips[] = ['label' => 'Priority: ' . htmlspecialchars($priority), 'remove' => ['priority']];
}
if ($period === 'this_week') {
    if ($type === 'resolved') {
        $activeFilterChips[] = ['label' => 'Period: Resolved This Week', 'remove' => ['period', 'type']];
    } elseif ($type === 'still_pending') {
        $activeFilterChips[] = ['label' => 'Period: Still Pending from This Week', 'remove' => ['period', 'type']];
    } else {
        $activeFilterChips[] = ['label' => 'Period: New This Week', 'remove' => ['period', 'type']];
    }
}
if (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $activeFilterChips[] = ['label' => 'Date: ' . date('d M Y', strtotime($date)), 'remove' => ['date']];
}
if ($customerId > 0) {
    $label = !empty($customerFilterName) ? "Customer: " . htmlspecialchars($customerFilterName) : "Customer #$customerId";
    $activeFilterChips[] = ['label' => $label, 'remove' => ['customer_id']];
}
if ($rated === 1) {
    if ($rating >= 1 && $rating <= 5) {
        $activeFilterChips[] = ['label' => "Rating: $rating Stars", 'remove' => ['rated', 'rating']];
    } else {
        $activeFilterChips[] = ['label' => "Customer Rated", 'remove' => ['rated', 'rating']];
    }
}
if ($sort === 'resolution_time_desc') {
    $activeFilterChips[] = ['label' => 'Sorted: Longest Resolution Time', 'remove' => ['sort']];
}
if ($targetTicketId > 0) {
    $activeFilterChips[] = ['label' => 'Ref: #TKT-' . str_pad($targetTicketId, 4, '0', STR_PAD_LEFT), 'remove' => ['ticket_id', 'id']];
}

// Compute dynamic header title
$dashboardTitle = "Support Agent Dashboard";
if ($targetTicketId > 0) {
    $dashboardTitle = "Complaint #TKT-" . str_pad($targetTicketId, 4, '0', STR_PAD_LEFT);
} elseif ($customerId > 0 && !empty($customerFilterName)) {
    $dashboardTitle = "Complaints from " . htmlspecialchars($customerFilterName);
} elseif ($period === 'this_week') {
    if ($type === 'resolved') {
        $dashboardTitle = "Complaints Resolved This Week";
    } elseif ($type === 'still_pending') {
        $dashboardTitle = "Complaints Still Pending from This Week";
    } else {
        $dashboardTitle = "New Complaints Submitted This Week";
    }
} elseif (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $dashboardTitle = "Complaints Submitted on " . date('d M Y', strtotime($date));
} elseif ($rated === 1) {
    $dashboardTitle = ($rating >= 1 && $rating <= 5) ? "$rating-Star Rated Complaints" : "Customer Rated Complaints";
} elseif ($sort === 'resolution_time_desc') {
    $dashboardTitle = "Resolved Complaints (Longest Resolution Time First)";
} elseif (!empty($status) && $status !== 'All') {
    $dashboardTitle = "$status Complaints";
} elseif (!empty($category) && $category !== 'All') {
    $dashboardTitle = "$category Complaints";
} elseif (!empty($priority) && $priority !== 'All') {
    $dashboardTitle = "$priority Priority Complaints";
}

// Helpers to preserve active query parameters
function getFilterUrl($newParams = [], $unsetKeys = []) {
    $params = $_GET;
    foreach ($unsetKeys as $k) {
        unset($params[$k]);
    }
    foreach ($newParams as $k => $v) {
        if ($v === null || $v === '') {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $query = http_build_query($params);
    return 'admin_dashboard.php' . ($query !== '' ? '?' . $query : '');
}

function getPaginationUrl($targetPage, $search = '', $status = '', $category = '', $priority = '') {
    return getFilterUrl(['page' => $targetPage]);
}

// Build WHERE conditions
$whereClauses = [];
$paramTypes = "";
$paramValues = [];

if ($targetTicketId > 0) {
    $whereClauses[] = "tickets.id = ?";
    $paramTypes .= "i";
    $paramValues[] = $targetTicketId;
}

if ($search !== '') {
    $whereClauses[] = "(tickets.subject LIKE ? OR tickets.message LIKE ? OR users.fullname LIKE ?)";
    $likeSearch = '%' . $search . '%';
    $paramTypes .= "sss";
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
}

if ($status !== '' && $status !== 'All') {
    $whereClauses[] = "tickets.status = ?";
    $paramTypes .= "s";
    $paramValues[] = $status;
}

if ($category !== '' && $category !== 'All') {
    $whereClauses[] = "tickets.category = ?";
    $paramTypes .= "s";
    $paramValues[] = $category;
}

if ($priority !== '' && $priority !== 'All') {
    $whereClauses[] = "tickets.priority = ?";
    $paramTypes .= "s";
    $paramValues[] = $priority;
}

if ($period === 'this_week') {
    list($thisMonday, $thisSunday) = getThisWeekRange();
    if ($type === 'resolved') {
        $whereClauses[] = "tickets.status = 'Resolved' AND tickets.responded_at >= ? AND tickets.responded_at <= ?";
        $paramTypes .= "ss";
        $paramValues[] = $thisMonday;
        $paramValues[] = $thisSunday;
    } elseif ($type === 'still_pending') {
        $whereClauses[] = "tickets.created_at >= ? AND tickets.created_at <= ? AND tickets.status != 'Resolved'";
        $paramTypes .= "ss";
        $paramValues[] = $thisMonday;
        $paramValues[] = $thisSunday;
    } else { // 'new'
        $whereClauses[] = "tickets.created_at >= ? AND tickets.created_at <= ?";
        $paramTypes .= "ss";
        $paramValues[] = $thisMonday;
        $paramValues[] = $thisSunday;
    }
}

if (!empty($date) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
    $whereClauses[] = "DATE(tickets.created_at) = ?";
    $paramTypes .= "s";
    $paramValues[] = $date;
}

if ($customerId > 0) {
    $whereClauses[] = "tickets.user_id = ?";
    $paramTypes .= "i";
    $paramValues[] = $customerId;
}

if ($rated === 1) {
    if ($rating >= 1 && $rating <= 5) {
        $whereClauses[] = "tickets.rating = ?";
        $paramTypes .= "i";
        $paramValues[] = $rating;
    } else {
        $whereClauses[] = "tickets.rating IS NOT NULL";
    }
}

$whereSql = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Sorting
$orderBySql = "ORDER BY tickets.created_at DESC";
if ($sort === 'resolution_time_desc') {
    $orderBySql = "ORDER BY (CASE WHEN tickets.responded_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, tickets.created_at, tickets.responded_at) ELSE 0 END) DESC, tickets.id DESC";
}

// Count total matching tickets
$countSql = "SELECT COUNT(*) AS total FROM tickets JOIN users ON tickets.user_id = users.id $whereSql";
$countStmt = $conn->prepare($countSql);
if ($countStmt) {
    if (!empty($paramTypes)) {
        $countStmt->bind_param($paramTypes, ...$paramValues);
    }
    $countStmt->execute();
    $totalTickets = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
    $countStmt->close();
} else {
    $totalTickets = 0;
}

// Compute pagination
$totalPages = $totalTickets > 0 ? (int)ceil($totalTickets / $limit) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;
if ($offset < 0) $offset = 0;

// Fetch paginated tickets
$sql = "SELECT tickets.*, users.fullname FROM tickets JOIN users ON tickets.user_id = users.id $whereSql $orderBySql LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$dataParamTypes = $paramTypes . "ii";
$dataParamValues = array_merge($paramValues, [$limit, $offset]);
$stmt->bind_param($dataParamTypes, ...$dataParamValues);
$stmt->execute();
$result = $stmt->get_result();
$ticketCount = $result->num_rows;
$pageTitle = 'Tickets Dashboard';
$activeNav = 'tickets';
$navUnreadBadge = $unreadEmailsBadge;
ob_start();
?>
    <style>
        .admin-page-wrapper {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
            display: flex;
            flex-direction: column;
            gap: 22px;
        }

        .admin-header-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 22px 28px;
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 16px;
        }

        .admin-header-title h2 {
            margin: 0 0 6px;
            font-size: 22px;
            font-weight: 800;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .admin-header-title .page-summary {
            margin: 0;
            font-size: 13.5px;
            color: var(--text-muted);
        }

        .admin-header-actions {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }

        .admin-controls-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 20px 24px;
            box-shadow: var(--shadow-sm);
        }

        .admin-tickets-section {
            display: flex;
            flex-direction: column;
            gap: 0;
        }

        .ticket {
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            margin-bottom: 20px;
            border-radius: 12px;
            background: var(--bg-card);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .ticket:hover {
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        [data-theme="dark"] .ticket:hover {
            border-color: var(--primary-color);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .ticket strong {
            color: var(--text-main);
        }

        textarea{
            width:100%;
            min-height:130px;
            padding:15px;
            margin-top:10px;
            border:1px solid #d6e8ff;
            border-radius:10px;
            resize:vertical;
            font-size:15px;
            line-height:1.6;
            transition:.25s;
            box-sizing:border-box;
        }

        textarea:focus{
            outline:none;
            border-color:#1976d2;
            box-shadow:0 0 0 3px rgba(25,118,210,.15);
        }

        /* Reply button */
        .reply-btn {
            /*margin-top: 10px;*/
            padding: 12px 22px; /*10px*/
            background: var(--primary-color);
            color: var(--text-inverse);
            border: 1px solid var(--primary-color);
            border-radius: 6px;
            cursor: pointer;
            /*margin-top:15px;*/
            font-size:15px;
            font-weight:600;
            transition:.25s;
        }

        .reply-btn:hover {
            background: var(--primary-hover);
            transform:translateY(-2px);
        }

        [data-theme="dark"] .reply-btn {
            background: #FAFAFA !important;
            color: #09090B !important;
            border-color: #FAFAFA !important;
        }

        [data-theme="dark"] .reply-btn:hover {
            background: #E4E4E7 !important;
            color: #000000 !important;
        }

        .reply-btn i,
        .reply-btn span {
            color: inherit !important;
        }

        /* Response display */
        .response {
            margin-top: 10px;
            padding: 10px;
            background: #e8f5e9;
            border-left: 4px solid #4caf50;
        }

        .response strong{
            display:block;
            margin-bottom:10px;
            color:#0d47a1;
        }

        .response p{
            margin:0;
            color:var(--text-secondary);
            line-height:1.7;
        }

        .status {
            margin-top: 5px;
            font-size: 14px;
            color: #555;
        }

        .resolved {
            color: green;
            font-weight: bold;
            margin-top: 10px;
        }

        /* Logout button */
        .logout,
        .portal-bottom-link a.logout {
            display: inline-flex !important;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 0;
            padding: 10px 22px !important;
            background: #FEF2F2 !important;
            color: #DC2626 !important;
            border: 1px solid #FECACA !important;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            box-shadow: 0 1px 3px rgba(220, 38, 38, 0.08);
            transition: all 0.2s ease;
        }

        .logout:hover,
        .portal-bottom-link a.logout:hover {
            background: #DC2626 !important;
            color: #FFFFFF !important;
            border-color: #DC2626 !important;
            box-shadow: 0 4px 12px rgba(220, 38, 38, 0.25);
            transform: translateY(-1px);
        }

        .logout i,
        .portal-bottom-link a.logout i {
            color: inherit !important;
            font-size: 14px;
        }

        [data-theme="dark"] .logout,
        [data-theme="dark"] .portal-bottom-link a.logout {
            background: rgba(239, 68, 68, 0.16) !important;
            color: #F87171 !important;
            border: 1px solid rgba(248, 113, 113, 0.35) !important;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
        }

        [data-theme="dark"] .logout:hover,
        [data-theme="dark"] .portal-bottom-link a.logout:hover {
            background: #EF4444 !important;
            color: #FFFFFF !important;
            border-color: #EF4444 !important;
            box-shadow: 0 4px 14px rgba(239, 68, 68, 0.4);
        }

        @media (max-width: 500px) {
            .ticket {
                padding: 12px;
            }
        }

        .main-content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px; 
        }

        /* Styles now handled in top section */
        .ticket-header{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:20px;
            margin-bottom:18px;
        }

        .customer-name{
            font-size:22px;
            font-weight:700;
            color:var(--text-main);
        }

        .ticket-subject{
            font-size:18px;
            font-weight:600;
            color:#1976d2;
            margin:12px 0;
        }

        .ticket-message{
            line-height:1.8;
            color:var(--text-secondary);
            margin-bottom:18px;
        }

        .ticket-date{
            color:var(--text-muted);
            font-size:14px;
            margin-bottom:18px;
        }

        .status-badge{
            padding:8px 16px;
            border-radius:20px;
            color:#fff;
            font-size:13px;
            font-weight:600;
        }

        .status-pending{
            background:#f9a825;
        }

        .status-inprogress{
            background:#0284c7;
        }

        .status-reopened{
            background:#7c3aed;
        }

        .status-resolved{
            background:#43a047;
        }

        .reply-date{
            margin:8px 0 14px;
            color:var(--text-muted);
            font-size:14px;
            font-style:italic;
        }

        .no-ticket{
            text-align:center;
            color:#666;
            padding:45px 20px;
        }

        .no-ticket i{
            display:block;
            font-size:48px;
            color:#1976d2;
            margin-bottom:15px;
        }

        .no-ticket p{
            font-size:20px;
            font-weight:600;
            margin-bottom:8px;
        }

        .no-ticket small{
            color:#888;
            font-size:15px;
        }

        .success-message{
            background:#e8f5e9;
            color:#2e7d32;
            border:1px solid #81c784;
            padding:12px;
            border-radius:8px;
            margin-bottom:20px;
            text-align:center;
        }

        .error-message{
            background:#ffebee;
            color:#c62828;
            border:1px solid #ef9a9a;
            padding:12px;
            border-radius:8px;
            margin-bottom:20px;
            text-align:center;
        }

        .success-message i, .error-message i{
            margin-right:8px;
            font-size:18px;
        }

        .ticket-highlighted {
            border: 2px solid #1976d2 !important;
            box-shadow: 0 0 0 4px rgba(25, 118, 210, 0.25) !important;
            animation: ticketPulse 2s ease-in-out infinite alternate;
        }

        @keyframes ticketPulse {
            0% { box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.2); }
            100% { box-shadow: 0 0 0 6px rgba(25, 118, 210, 0.45); }
        }

        .csat-highlighted {
            animation: csatPulse 1.5s ease-in-out infinite alternate;
            border-color: #f59e0b !important;
        }

        @keyframes csatPulse {
            0% { transform: scale(1); box-shadow: 0 0 0 0 rgba(245, 158, 11, 0.4); }
            100% { transform: scale(1.05); box-shadow: 0 0 0 6px rgba(245, 158, 11, 0); }
        }

        .active-filters-bar {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 12px 18px;
            margin-bottom: 22px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .filter-chip {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .filter-chip-remove {
            color: #0369a1;
            text-decoration: none;
            font-size: 14px;
            line-height: 1;
            font-weight: bold;
            padding-left: 2px;
        }

        .filter-chip-remove:hover {
            color: #dc2626;
        }

        .back-to-analytics-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #1976d2;
            text-decoration: none;
            font-weight: 600;
            font-size: 13px;
            padding: 6px 12px;
            background: #f0f7ff;
            border-radius: 6px;
            border: 1px solid #cbe2ff;
            transition: all 0.2s;
        }

        .back-to-analytics-btn:hover {
            background: #1976d2;
            color: #ffffff;
        }

        [data-theme="dark"] .active-filters-bar {
            background: var(--bg-card-subtle) !important;
            border-color: var(--border-color) !important;
        }

        [data-theme="dark"] .active-filters-bar span {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .filter-chip {
            background: rgba(3, 105, 161, 0.25) !important;
            color: #7dd3fc !important;
            border-color: rgba(3, 105, 161, 0.4) !important;
        }

        [data-theme="dark"] .back-to-analytics-btn {
            background: var(--bg-card-subtle) !important;
            border-color: var(--border-color) !important;
            color: #60a5fa !important;
        }

        [data-theme="dark"] .back-to-analytics-btn:hover {
            background: #2563eb !important;
            color: #ffffff !important;
        }

        [data-theme="dark"] .no-ticket {
            background: var(--bg-card) !important;
            border-color: var(--border-color) !important;
        }

        [data-theme="dark"] .no-ticket p {
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .no-ticket small {
            color: var(--text-muted) !important;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_admin.php';
?>
        <div class="admin-page-wrapper">

            <!-- Section 1: Admin Header Panel -->
            <section class="admin-header-card">
                <div class="admin-header-title">
                    <h2>
                        <i class="fa-solid fa-headset"></i>
                        <?php echo htmlspecialchars($dashboardTitle); ?> 
                    </h2>
                    <p class="page-summary">
                        <?php if ($totalTickets > 0): ?>
                            <i class="fa-solid fa-list-check"></i>
                            Showing <strong><?php echo ($offset + 1); ?> &ndash; <?php echo min($offset + $limit, $totalTickets); ?></strong> of <strong><?php echo $totalTickets; ?></strong> complaint(s) &bull; Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
                        <?php else: ?>
                            <i class="fa-solid fa-circle-info"></i>
                            No complaints found matching your criteria.
                        <?php endif; ?>
                    </p>
                </div>
                <div class="admin-header-actions">
                    <a href="analytics.php" class="back-to-analytics-btn">
                        <i class="fa-solid fa-chart-line"></i> <?php echo !empty($activeFilterChips) ? 'Back to Analytics' : 'View Analytics &amp; Insights &rarr;'; ?>
                    </a>
                </div>
            </section>

            <!-- Section 2: Search and Filters Control Panel -->
            <section class="admin-controls-card">
                <?php if (!empty($activeFilterChips)): ?>
                    <div class="active-filters-bar" style="margin-bottom: 16px;">
                        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px;">
                            <span style="font-size: 13px; font-weight: 700; color: #475569;">
                                <i class="fa-solid fa-filter"></i> Active Filters (<?php echo count($activeFilterChips); ?>):
                            </span>
                            <?php foreach ($activeFilterChips as $chip): ?>
                                <span class="filter-chip">
                                    <?php echo $chip['label']; ?>
                                    <a href="<?php echo htmlspecialchars(getFilterUrl([], $chip['remove'])); ?>" class="filter-chip-remove" title="Remove filter">&times;</a>
                                </span>
                            <?php endforeach; ?>
                            <a href="admin_dashboard.php" style="color: #ef4444; font-size: 12px; font-weight: 600; text-decoration: none; padding: 4px 8px; margin-left: 4px;" title="Reset all filters">
                                <i class="fa-solid fa-rotate-left"></i> Clear All
                            </a>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Modern Search and Filter Bar -->
                <div class="search-filter-bar">
                    <form method="GET" action="admin_dashboard.php" class="filter-form">
                        <?php if ($customerId > 0): ?><input type="hidden" name="customer_id" value="<?php echo $customerId; ?>"><?php endif; ?>
                        <?php if (!empty($period)): ?><input type="hidden" name="period" value="<?php echo htmlspecialchars($period); ?>"><?php endif; ?>
                        <?php if (!empty($type)): ?><input type="hidden" name="type" value="<?php echo htmlspecialchars($type); ?>"><?php endif; ?>
                        <?php if (!empty($date)): ?><input type="hidden" name="date" value="<?php echo htmlspecialchars($date); ?>"><?php endif; ?>
                        <?php if ($rated): ?><input type="hidden" name="rated" value="1"><?php endif; ?>
                        <?php if ($rating > 0): ?><input type="hidden" name="rating" value="<?php echo $rating; ?>"><?php endif; ?>
                        <?php if (!empty($sort)): ?><input type="hidden" name="sort" value="<?php echo htmlspecialchars($sort); ?>"><?php endif; ?>

                        <div class="filter-row">
                            <div class="search-group">
                                <label for="search"><i class="fa-solid fa-magnifying-glass"></i> Search Complaints</label>
                                <div class="search-input-wrapper">
                                    <i class="fa-solid fa-magnifying-glass search-icon"></i>
                                    <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search customer, subject or message...">
                                </div>
                            </div>

                            <div class="filter-controls">
                                <div class="filter-select-item">
                                    <label for="status"><i class="fa-solid fa-chart-simple"></i> Status</label>
                                    <select id="status" name="status">
                                        <option value="">All Statuses</option>
                                        <option value="Pending" <?php if ($status === 'Pending') echo 'selected'; ?>>Pending</option>
                                        <option value="In Progress" <?php if ($status === 'In Progress') echo 'selected'; ?>>In Progress</option>
                                        <option value="Resolved" <?php if ($status === 'Resolved') echo 'selected'; ?>>Resolved</option>
                                        <option value="Reopened" <?php if ($status === 'Reopened') echo 'selected'; ?>>Reopened</option>
                                    </select>
                                </div>

                                <div class="filter-select-item">
                                    <label for="category"><i class="fa-solid fa-tags"></i> Category</label>
                                    <select id="category" name="category">
                                        <option value="">All Categories</option>
                                        <option value="Technical Support" <?php if ($category === 'Technical Support') echo 'selected'; ?>>Technical Support</option>
                                        <option value="Billing & Payments" <?php if ($category === 'Billing & Payments') echo 'selected'; ?>>Billing & Payments</option>
                                        <option value="Account & Security" <?php echo (isset($_GET['category']) && $_GET['category']==='Account & Security')?'selected':''; ?>>Account & Security</option>
                                        <option value="Customer Service" <?php echo (isset($_GET['category']) && $_GET['category']==='Customer Service')?'selected':''; ?>>Customer Service</option>
                                        <option value="General Inquiry" <?php if ($category === 'General Inquiry') echo 'selected'; ?>>General Inquiry</option>
                                    </select>
                                </div>

                                <div class="filter-select-item">
                                    <label for="priority"><i class="fa-solid fa-flag"></i> Priority</label>
                                    <select id="priority" name="priority">
                                        <option value="">All Priorities</option>
                                        <option value="Urgent" <?php if ($priority === 'Urgent') echo 'selected'; ?>>Urgent</option>
                                        <option value="High" <?php if ($priority === 'High') echo 'selected'; ?>>High</option>
                                        <option value="Medium" <?php if ($priority === 'Medium') echo 'selected'; ?>>Medium</option>
                                        <option value="Low" <?php if ($priority === 'Low') echo 'selected'; ?>>Low</option>
                                    </select>
                                </div>

                                <div class="filter-buttons">
                                    <button type="submit" class="filter-btn">
                                        <i class="fa-solid fa-filter"></i> Filter
                                    </button>
                                    <a href="admin_dashboard.php" class="reset-btn">
                                        <i class="fa-solid fa-rotate-left"></i> Reset
                                    </a>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </section>

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

            <!-- Section 3: Tickets Feed -->
            <section class="admin-tickets-section">

           <?php
                 if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {

                        $isTargetTicket = ($targetTicketId > 0 && $targetTicketId == $row['id']);
                        $highlightClass = $isTargetTicket ? ' ticket-highlighted' : '';
                        echo "<div class='ticket{$highlightClass}' id='ticket-{$row['id']}'>";
                        /*echo "<strong>User:</strong> " . $row['fullname'] . "<br>";
                        echo "<strong>Subject:</strong> " . $row['subject'] . "<br>";
                        echo "<strong>Message:</strong> " . $row['message'] . "<br>";
                        echo "<p class='status'>Status: " . $row['status'] . "</p>";*/

                        $statusClass = "status-pending";
                        if ($row['status'] == "Resolved") {
                            $statusClass = "status-resolved";
                        } elseif ($row['status'] == "In Progress") {
                            $statusClass = "status-inprogress";
                        } elseif ($row['status'] == "Reopened") {
                            $statusClass = "status-reopened";
                        }

                        echo "<div class='ticket-header'>";

                        $category = htmlspecialchars($row['category'] ?? 'General Inquiry');
                        $priority = htmlspecialchars($row['priority'] ?? 'Medium');

                        $priorityClass = 'priority-medium';
                        $priorityIcon = 'fa-circle-dot';
                        if ($priority === 'Urgent') {
                            $priorityClass = 'priority-urgent';
                            $priorityIcon = 'fa-circle-exclamation';
                        } elseif ($priority === 'High') {
                            $priorityClass = 'priority-high';
                            $priorityIcon = 'fa-triangle-exclamation';
                        } elseif ($priority === 'Low') {
                            $priorityClass = 'priority-low';
                            $priorityIcon = 'fa-circle';
                        }

                        $catIcon = 'fa-circle-info';
                        if ($category === 'Technical Support') $catIcon = 'fa-laptop-code';
                        elseif ($category === 'Billing & Payments') $catIcon = 'fa-credit-card';
                        elseif ($category === 'Account & Security') $catIcon = 'fa-shield-halved';
                            elseif ($category === 'Customer Service') $catIcon = 'fa-headset';

                        echo "<div>";

                        echo "<div class='customer-name'>"
                            . htmlspecialchars($row['fullname']) .
                            "</div>";

                        echo "<div class='ticket-subject'>"
                            . htmlspecialchars($row['subject']) .
                            "</div>";

                        echo "<div class='ticket-meta-badges'>";
                        echo "<span class='category-tag'><i class='fa-solid $catIcon'></i> $category</span>";
                        echo "<span class='priority-badge $priorityClass'><i class='fa-solid $priorityIcon'></i> $priority Priority</span>";
                        if ($row['status'] === 'Resolved') {
                            if (!empty($row['rating'])) {
                                $csatHl = ($highlight === 'csat' && $isTargetTicket) ? ' csat-highlighted' : '';
                                echo "<span class='csat-badge{$csatHl}' title='Customer Satisfaction Score'><i class='fa-solid fa-star' style='color:#f59e0b;'></i> {$row['rating']}/5 CSAT</span>";
                            } else {
                                echo "<span class='csat-badge' style='background:#f3f4f6;color:#6b7280;border-color:#e5e7eb;'><i class='fa-regular fa-star'></i> Awaiting Rating</span>";
                            }

                            if (!empty($row['responded_at'])) {
                                $resSecs = strtotime($row['responded_at']) - strtotime($row['created_at']);
                                if ($resSecs >= 0) {
                                    $resHours = round($resSecs / 3600, 1);
                                    $resLabel = $resHours >= 24 ? round($resHours / 24, 1) . ' days' : ($resHours > 0 ? $resHours . ' hrs' : '<1 hr');
                                    echo "<span class='duration-badge' style='background:#f0fdf4;color:#166534;border:1px solid #bbf7d0;padding:2px 8px;border-radius:12px;font-size:11px;font-weight:600;display:inline-flex;align-items:center;gap:4px;'><i class='fa-solid fa-stopwatch'></i> Resolved in $resLabel</span>";
                                }
                            }
                        }
                        echo "</div>";

                        echo "</div>";

                        echo "<span class='status-badge $statusClass'>"
                            . htmlspecialchars($row['status']) .
                            "</span>";

                        echo "</div>";

                        // Display prominent reopen banner for admin if reopened
                        if ($row['status'] === 'Reopened') {
                            echo "<div class='reopen-banner'>";
                            echo "<div class='reopen-banner-header'><i class='fa-solid fa-triangle-exclamation'></i> Complaint Reopened by Customer</div>";
                            if (!empty($row['reopen_reason'])) {
                                echo "<div style='margin-bottom:4px;'><strong>Reason for Reopening:</strong> " . htmlspecialchars($row['reopen_reason']) . "</div>";
                            }
                            if (!empty($row['reopened_at'])) {
                                echo "<div style='font-size:11px;color:#7c3aed;'><i class='fa-solid fa-clock'></i> Reopened on " . date("d M Y • h:i A", strtotime($row['reopened_at'])) . "</div>";
                            }
                            echo "</div>";
                        }

                        // Display prominent in-progress banner for admin if in progress
                        if ($row['status'] === 'In Progress') {
                            echo "<div class='inprogress-banner'>";
                            echo "<div class='inprogress-banner-header'><i class='fa-solid fa-spinner fa-spin'></i> Ticket Under Active Investigation</div>";
                            echo "<div>This complaint is currently being investigated. Send an official response below to provide resolution and conclude the ticket.</div>";
                            if (!empty($row['in_progress_at'])) {
                                echo "<div style='font-size:11px;color:#0284c7;margin-top:4px;'><i class='fa-solid fa-clock'></i> Marked In Progress on " . date("d M Y • h:i A", strtotime($row['in_progress_at'])) . "</div>";
                            }
                            echo "</div>";
                        }

                        echo "<div class='ticket-message'>"
                            . nl2br(htmlspecialchars($row['message'])) .
                            "</div>";

                        // Customer Attachment Display in Admin Portal
                        if (!empty($row['attachment'])) {
                            $attachFile = htmlspecialchars($row['attachment']);
                            $attachPath = "uploads/" . $attachFile;
                            if (file_exists(__DIR__ . "/" . $attachPath)) {
                                echo "<div class='attachment-container' style='margin-bottom:15px;'>";
                                echo "<div class='attachment-title'><i class='fa-solid fa-paperclip'></i> Customer Attached Screenshot</div>";
                                echo "<div class='attachment-image-link' onclick=\"openImageModal('$attachPath')\">";
                                echo "<img src='$attachPath' alt='Customer Screenshot' class='attachment-image-thumb'>";
                                echo "<span class='attachment-overlay'><i class='fa-solid fa-magnifying-glass-plus'></i></span>";
                                echo "</div>";
                                echo "<div class='attachment-actions'><a href='javascript:void(0);' onclick=\"openImageModal('$attachPath')\"><i class='fa-solid fa-up-right-from-square'></i> View Full Image</a></div>";
                                echo "</div>";
                            }
                        }

                        echo "<div class='ticket-date' style='display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;margin-bottom:12px;'>";

                        echo "<span><i class='fa-solid fa-calendar-days'></i> Submitted: "
                            . date("d M Y • h:i A", strtotime($row['created_at'])) . "</span>";

                        echo "<div style='display:flex;gap:8px;align-items:center;flex-wrap:wrap;'>";

                        // Mark as In Progress button for Pending tickets
                        if ($row['status'] === 'Pending') {
                            echo "<form method='POST' action='mark_inprogress.php' style='display:inline;' onsubmit=\"return confirm('Mark this complaint as In Progress?');\">";
                            echo "<input type='hidden' name='ticket_id' value='{$row['id']}'>";
                            echo "<button type='submit' class='inprogress-action-btn'><i class='fa-solid fa-spinner'></i> Mark In Progress</button>";
                            echo "</form>";
                        }

                        echo "<a href=\"ticket_receipt.php?id={$row['id']}\" target=\"_blank\" class=\"action-btn-sm btn-action-receipt\"><i class=\"fa-solid fa-file-pdf\"></i> Receipt / PDF</a>";

                        echo "</div>";

                        echo "</div>";

                        // 🔥 LOGIC: Show form if Pending, In Progress, or Reopened
                        if ($row['status'] == 'Pending' || $row['status'] == 'In Progress' || $row['status'] == 'Reopened') {

                            // If reopened, show previous response for context
                            if ($row['status'] === 'Reopened' && !empty($row['response'])) {
                                echo "<div style='background:#f8fafc; border:1px solid #e2e8f0; border-left:3px solid #7c3aed; padding:10px 14px; border-radius:6px; margin-bottom:14px; font-size:13px;'>";
                                echo "<strong style='color:#5b21b6;'><i class='fa-solid fa-clock-rotate-left'></i> Previous Agent Response:</strong> ";
                                echo "<p style='color:#334155; margin:4px 0 0;'>" . nl2br(htmlspecialchars($row['response'])) . "</p>";
                                echo "</div>";
                            }

                            echo "<form method='POST' action='reply_ticket.php' enctype='multipart/form-data'>";

                            $formTitle = 'Support Team Response';
                            $formIcon  = 'fa-headset';
                            if ($row['status'] === 'Reopened') {
                                $formTitle = 'Provide Follow-Up Resolution';
                                $formIcon  = 'fa-arrows-rotate';
                            } elseif ($row['status'] === 'In Progress') {
                                $formTitle = 'Resolve Ticket with Official Response';
                                $formIcon  = 'fa-circle-check';
                            }

                            echo "<h4 style='margin-bottom:12px;color:#1976d2;'>
                                    <i class='fa-solid $formIcon'></i>
                                    $formTitle
                                  </h4>";

                            echo "<input type='hidden'
                                name='ticket_id'
                                value='".$row['id']."'>";

                            echo "<textarea
                                name='response'
                                placeholder='Type your response to the customer...'
                                required></textarea>";

                            $ticketId = $row['id'];

                            echo "<div class='file-upload-container' style='margin-top:12px;'>
                                    <label for='admin_attachment_$ticketId' class='attach-btn' id='adminAttachBtn_$ticketId'>
                                        <i class='fa-solid fa-paperclip'></i>
                                        <span>Attach Screenshot / Image</span>
                                    </label>
                                    <input
                                        type='file'
                                        id='admin_attachment_$ticketId'
                                        name='admin_attachment'
                                        accept='.jpg,.jpeg,.png,image/jpeg,image/png'
                                        style='display:none;'
                                        onchange=\"previewUploadImage(this, 'adminPreviewCard_$ticketId', 'adminPreviewImg_$ticketId', 'adminPreviewName_$ticketId', 'adminPreviewSize_$ticketId', 'adminAttachBtn_$ticketId')\"
                                    >
                                    <div id='adminPreviewCard_$ticketId' class='upload-preview-card' style='display:none; align-items:center; gap:12px; max-width:100%; box-sizing:border-box; overflow:hidden;'>
                                        <img id='adminPreviewImg_$ticketId' class='upload-preview-thumb' src='' alt='Selected image' title='Click to cross-check full image' onclick=\"openImageModal(this.src)\" style='width:60px; height:60px; min-width:60px; max-width:60px; min-height:60px; max-height:60px; object-fit:cover; border-radius:6px; border:1px solid #d0d7de; display:block; flex-shrink:0; cursor:pointer; transition:transform 0.2s ease;'>
                                        <div class='upload-preview-info' style='display:flex; flex-direction:column; overflow:hidden; max-width:220px;'>
                                            <span id='adminPreviewName_$ticketId' class='upload-preview-name' title='Click to cross-check full image' onclick=\"openImageModal(document.getElementById('adminPreviewImg_$ticketId').src)\" style='font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:13px; color:#1976d2; cursor:pointer;'></span>
                                            <span id='adminPreviewSize_$ticketId' class='upload-preview-size' style='font-size:11px; color:#777;'></span>
                                        </div>
                                        <button type='button' class='upload-preview-remove' title='Remove attachment' onclick=\"removeUploadImage('admin_attachment_$ticketId', 'adminPreviewCard_$ticketId', 'adminAttachBtn_$ticketId')\" style='outline:none;'>
                                            <i class='fa-solid fa-xmark'></i>
                                        </button>
                                    </div>
                                    <p class='file-input-help'>
                                        <i class='fa-solid fa-circle-info'></i>
                                        Allowed formats: JPG, JPEG, PNG (Max: 5MB)
                                    </p>
                                  </div>";

                            echo "<button
                                class='reply-btn'
                                type='submit'>
                                <i class='fa-solid fa-paper-plane'></i>
                                Send Response
                                </button>";

                            echo "</form>"; 
                        } else {

                                // Display Previous Resolution Rounds if reopened & resolved before
                                if (!empty($row['response_history'])) {
                                    $pastResponses = json_decode($row['response_history'], true);
                                    if (is_array($pastResponses)) {
                                        $round = 1;
                                        foreach ($pastResponses as $past) {
                                            echo "<div class='response history-response'>";
                                            echo "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px;'>";
                                            echo "<strong style='margin:0;'><i class='fa-solid fa-clock-rotate-left'></i> Previous Agent Resolution (Round $round)</strong>";
                                            if (!empty($past['responded_at'])) {
                                                echo "<span style='font-size:12px; color:var(--text-muted);'><i class='fa-solid fa-clock'></i> " . date("d M Y • h:i A", strtotime($past['responded_at'])) . "</span>";
                                            }
                                            echo "</div>";
                                            echo "<p style='margin:0; line-height:1.6;'>" . nl2br(htmlspecialchars($past['response'] ?? '')) . "</p>";
                                            if (!empty($past['admin_attachment'])) {
                                                $pastAttach = htmlspecialchars($past['admin_attachment']);
                                                if (file_exists(__DIR__ . "/uploads/" . $pastAttach)) {
                                                    echo "<div style='margin-top:8px; font-size:12px;'><a href='javascript:void(0);' onclick=\"openImageModal('uploads/$pastAttach')\" style='color:#1976d2;'><i class='fa-solid fa-paperclip'></i> View Attached Resolution Screenshot</a></div>";
                                                }
                                            }
                                            if (!empty($past['reopen_reason'])) {
                                                echo "<div class='past-reopen-reason-box'>";
                                                echo "<strong><i class='fa-solid fa-triangle-exclamation'></i> Customer Reopen Reason:</strong> " . htmlspecialchars($past['reopen_reason']);
                                                if (!empty($past['reopened_at'])) {
                                                    echo " <span style='font-size:11px;'>(" . date("d M Y • h:i A", strtotime($past['reopened_at'])) . ")</span>";
                                                }
                                                echo "</div>";
                                            }
                                            echo "</div>";
                                            $round++;
                                        }
                                    }
                                }

                                $hasHistory = !empty($row['response_history']);
                                $responseTitle = $hasHistory ? "Latest Support Team Resolution" : "Support Team Response";

                                echo "<div class='response'>";

                                echo "<strong>
                                    <i class='fa-solid fa-headset'></i>
                                    $responseTitle
                                </strong>";

                                if (!empty($row['responded_at'])) {
                                    echo "<div class='reply-date'>";
                                    
                                    echo "<i class='fa-solid fa-clock'></i> Replied on " .
                                        date("d M Y • h:i A", strtotime($row['responded_at']));
                                    echo "</div>";
                                }

                                echo "<p>" . nl2br(htmlspecialchars($row['response'])) . "</p>";

                                // Admin Attachment Display in Admin Portal
                                if (!empty($row['admin_attachment'])) {
                                    $adminAttachFile = htmlspecialchars($row['admin_attachment']);
                                    $adminAttachPath = "uploads/" . $adminAttachFile;
                                    if (file_exists(__DIR__ . "/" . $adminAttachPath)) {
                                        echo "<div class='attachment-container' style='margin-top:12px;'>";
                                        echo "<div class='attachment-title'><i class='fa-solid fa-paperclip'></i> Attached Response Screenshot</div>";
                                        echo "<div class='attachment-image-link' onclick=\"openImageModal('$adminAttachPath')\">";
                                        echo "<img src='$adminAttachPath' alt='Support Screenshot' class='attachment-image-thumb'>";
                                        echo "<span class='attachment-overlay'><i class='fa-solid fa-magnifying-glass-plus'></i></span>";
                                        echo "</div>";
                                        echo "<div class='attachment-actions'><a href='javascript:void(0);' onclick=\"openImageModal('$adminAttachPath')\"><i class='fa-solid fa-up-right-from-square'></i> View Full Image</a></div>";
                                        echo "</div>";
                                    }
                                }

                                echo "</div>";

                                if (!empty($row['rating'])) {
                                    $stars = (int)$row['rating'];
                                    $starsHtml = str_repeat('<i class="fa-solid fa-star star-filled" style="color:#f59e0b;"></i> ', $stars) . str_repeat('<i class="fa-regular fa-star star-empty" style="color:#94a3b8;"></i> ', 5 - $stars);
                                    echo "<div class='csat-box' style='margin-top:12px;'>";
                                    echo "<div class='csat-stars-row'>";
                                    echo "<span style='font-size:13px;'>Customer Satisfaction Rating:</span> ";
                                    echo "<span class='csat-stars-gold'>$starsHtml</span> ";
                                    echo "<span style='font-size:12px; color:#6b7280;'>($stars/5)</span>";
                                    echo "</div>";
                                    if (!empty($row['feedback'])) {
                                        echo "<div class='csat-comment'>&ldquo;" . htmlspecialchars($row['feedback']) . "&rdquo;</div>";
                                    }
                                    if (!empty($row['rating_updated_at'])) {
                                        echo "<div class='csat-date'><i class='fa-solid fa-clock'></i> Updated on " . date("d M Y • h:i A", strtotime($row['rating_updated_at'])) . " <span class='badge-updated'>Updated</span></div>";
                                    } elseif (!empty($row['rated_at'])) {
                                        echo "<div class='csat-date'><i class='fa-solid fa-clock'></i> Rated on " . date("d M Y • h:i A", strtotime($row['rated_at'])) . "</div>";
                                    }
                                    echo "</div>";
                                }
                        }

                        echo "</div>";
                    }
                } else {
                    $isFiltered = !empty($activeFilterChips);
                    if ($isFiltered) {
                        echo "
                        <div class='no-ticket' style='background:#fff;border-radius:12px;border:1px dashed #cbd5e1;padding:45px 20px;margin-top:15px;'>
                            <i class='fa-solid fa-magnifying-glass' style='color:#94a3b8;font-size:42px;margin-bottom:12px;'></i>
                            <p style='color:#1e293b;font-size:18px;margin-bottom:6px;'>No complaints match your active filters.</p>
                            <small style='color:#64748b;display:block;margin-bottom:18px;'>Try adjusting or clearing your active filters to view complaints.</small>
                            <div style='display:inline-flex;gap:10px;flex-wrap:wrap;justify-content:center;'>
                                <a href='admin_dashboard.php' class='filter-btn' style='text-decoration:none;display:inline-flex;align-items:center;gap:6px;'><i class='fa-solid fa-rotate-left'></i> Clear All Filters</a>
                                <a href='analytics.php' class='back-to-analytics-btn' style='text-decoration:none;display:inline-flex;align-items:center;gap:6px;'><i class='fa-solid fa-chart-line'></i> Back to Analytics</a>
                            </div>
                        </div>";
                    } else {
                        echo "
                        <div class='no-ticket'>
                            <i class='fa-solid fa-inbox'></i>
                            <p>No complaints available.</p>
                            <small>Customer complaints will appear here once they are submitted.</small>
                        </div>";
                    }
                }
            ?>
            </section> <!-- /.admin-tickets-section -->

            <?php if ($totalPages > 1): ?>
                <section class="pagination-section" style="display: flex; justify-content: center;">
                    <div class="pagination-container">
                        <!-- Prev Button -->
                        <?php if ($page > 1): ?>
                            <a href="<?php echo htmlspecialchars(getPaginationUrl($page - 1, $search, $status, $category, $priority)); ?>" class="page-btn">
                                <i class="fa-solid fa-chevron-left"></i> Prev
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                <i class="fa-solid fa-chevron-left"></i> Prev
                            </span>
                        <?php endif; ?>

                        <!-- Page Number Links -->
                        <?php
                        $startPage = max(1, $page - 2);
                        $endPage = min($totalPages, $page + 2);

                        if ($startPage > 1) {
                            echo '<a href="' . htmlspecialchars(getPaginationUrl(1, $search, $status, $category, $priority)) . '" class="page-btn">1</a>';
                            if ($startPage > 2) {
                                echo '<span class="page-ellipsis">&hellip;</span>';
                            }
                        }

                        for ($i = $startPage; $i <= $endPage; $i++) {
                            if ($i == $page) {
                                echo '<span class="page-btn active">' . $i . '</span>';
                            } else {
                                echo '<a href="' . htmlspecialchars(getPaginationUrl($i, $search, $status, $category, $priority)) . '" class="page-btn">' . $i . '</a>';
                            }
                        }

                        if ($endPage < $totalPages) {
                            if ($endPage < $totalPages - 1) {
                                echo '<span class="page-ellipsis">&hellip;</span>';
                            }
                            echo '<a href="' . htmlspecialchars(getPaginationUrl($totalPages, $search, $status, $category, $priority)) . '" class="page-btn">' . $totalPages . '</a>';
                        }
                        ?>

                        <!-- Next Button -->
                        <?php if ($page < $totalPages): ?>
                            <a href="<?php echo htmlspecialchars(getPaginationUrl($page + 1, $search, $status, $category, $priority)); ?>" class="page-btn">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </a>
                        <?php else: ?>
                            <span class="page-btn disabled">
                                Next <i class="fa-solid fa-chevron-right"></i>
                            </span>
                        <?php endif; ?>
                    </div>
                </section>
            <?php endif; ?>

            <div class="link portal-bottom-link" style="text-align:center; margin-top:16px; margin-bottom:24px;">
                <a href="logout.php" class="logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    Logout
                </a>
            </div>
        </div> <!-- /.admin-page-wrapper -->
    <!-- Image Preview Lightbox Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
        <button class="image-modal-close" onclick="closeImageModal(event)">&times;</button>
        <img id="modalImage" class="image-modal-content" src="" alt="Full preview">
    </div>

<script>
function openImageModal(src) {
    const modal = document.getElementById('imageModal');
    const modalImg = document.getElementById('modalImage');
    modalImg.src = src;
    modal.classList.add('active');
    document.body.style.overflow = 'hidden';
}

function closeImageModal(e) {
    if (e.target.id === 'imageModal' || e.target.classList.contains('image-modal-close') || e.target.tagName === 'BUTTON') {
        const modal = document.getElementById('imageModal');
        modal.classList.remove('active');
        document.body.style.overflow = 'auto';
    }
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        const modal = document.getElementById('imageModal');
        if (modal && modal.classList.contains('active')) {
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }
    }
});

function formatFileSize(bytes) {
    if (!bytes || bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function previewUploadImage(input, cardId, imgId, nameId, sizeId, btnId) {
    const file = input.files && input.files[0];
    const card = typeof cardId === 'string' ? document.getElementById(cardId) : cardId;
    const img = typeof imgId === 'string' ? document.getElementById(imgId) : imgId;
    const nameEl = typeof nameId === 'string' ? document.getElementById(nameId) : nameId;
    const sizeEl = typeof sizeId === 'string' ? document.getElementById(sizeId) : sizeId;
    const btn = typeof btnId === 'string' ? document.getElementById(btnId) : btnId;

    if (!file) {
        return;
    }

    // Validate size (5MB)
    if (file.size > 5 * 1024 * 1024) {
        alert('Attachment exceeds the 5MB size limit. Please choose a smaller image.');
        input.value = '';
        return;
    }

    // Validate format
    const validTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (file.type && !validTypes.includes(file.type.toLowerCase())) {
        alert('Invalid file format. Only JPG, JPEG, and PNG images are allowed.');
        input.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        if (img) img.src = e.target.result;
        if (nameEl) nameEl.textContent = file.name;
        if (sizeEl) sizeEl.textContent = formatFileSize(file.size);
        if (card) card.style.display = 'flex';
        if (btn) btn.style.display = 'none';
    };
    reader.readAsDataURL(file);
}

function removeUploadImage(inputId, cardId, btnId) {
    const input = typeof inputId === 'string' ? document.getElementById(inputId) : inputId;
    const card = typeof cardId === 'string' ? document.getElementById(cardId) : cardId;
    const btn = typeof btnId === 'string' ? document.getElementById(btnId) : btnId;

    if (input) {
        input.value = '';
    }
    if (card) {
        card.style.display = 'none';
        const img = card.querySelector('.upload-preview-thumb');
        if (img) img.src = '';
    }
    if (btn) {
        btn.style.display = 'inline-flex';
    }
}

// Fire-and-forget background email queue processor
(function() {
    if (window.fetch) {
        fetch('process_email_queue.php', { method: 'POST', keepalive: true }).catch(function() {});
    }
})();

// Auto-scroll to target ticket if specified in URL
(function() {
    var targetId = <?php echo $targetTicketId > 0 ? $targetTicketId : 'null'; ?>;
    if (targetId) {
        var el = document.getElementById('ticket-' + targetId);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    } else if (window.location.hash) {
        var hashEl = document.querySelector(window.location.hash);
        if (hashEl) {
            hashEl.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
})();
</script>
<?php require_once __DIR__ . '/includes/footer.php'; ?>