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

// Retrieve and sanitize search, sort, and pagination inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? trim($_GET['sort']) : 'tickets_desc';
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Allowed sort mappings
$sortOrders = [
    'tickets_desc'  => 'total_tickets DESC, last_ticket_date DESC',
    'tickets_asc'   => 'total_tickets ASC, last_ticket_date DESC',
    'recent_ticket' => 'last_ticket_date DESC, total_tickets DESC',
    'name_asc'      => 'u.fullname ASC',
    'name_desc'     => 'u.fullname DESC',
    'pending_desc'  => 'pending_tickets DESC, total_tickets DESC',
];
$orderBy = $sortOrders[$sort] ?? $sortOrders['tickets_desc'];

// 1. Count matching customers for pagination
$countSql = "
    SELECT COUNT(DISTINCT u.id) AS total_customers
    FROM users u
    INNER JOIN tickets t ON u.id = t.user_id
";
$countParams = [];
$countTypes = "";

if ($search !== '') {
    $countSql .= " WHERE (u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $countSearch = '%' . $search . '%';
    $countParams = [$countSearch, $countSearch, $countSearch];
    $countTypes = "sss";
}

$countStmt = $conn->prepare($countSql);
if (!empty($countParams)) {
    $countStmt->bind_param($countTypes, ...$countParams);
}
$countStmt->execute();
$totalCustomers = (int)($countStmt->get_result()->fetch_assoc()['total_customers'] ?? 0);
$countStmt->close();

$totalPages = max(1, (int)ceil($totalCustomers / $limit));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

// 2. Fetch customers with aggregated ticket metrics
$sql = "
    SELECT 
        u.id AS user_id,
        u.fullname,
        u.email,
        u.phone,
        u.profile_picture,
        u.created_at AS member_since,
        COUNT(t.id) AS total_tickets,
        SUM(t.status = 'Pending') AS pending_tickets,
        SUM(t.status = 'In Progress') AS inprogress_tickets,
        SUM(t.status = 'Reopened') AS reopened_tickets,
        SUM(t.status = 'Resolved') AS resolved_tickets,
        MAX(t.created_at) AS last_ticket_date
    FROM users u
    INNER JOIN tickets t ON u.id = t.user_id
";

$params = [];
$types = "";

if ($search !== '') {
    $sql .= " WHERE (u.fullname LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $params = [$searchTerm, $searchTerm, $searchTerm];
    $types = "sss";
}

$sql .= "
    GROUP BY u.id, u.fullname, u.email, u.phone, u.profile_picture, u.created_at
    ORDER BY {$orderBy}
    LIMIT ? OFFSET ?
";
$types .= "ii";
$params[] = $limit;
$params[] = $offset;

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$customers = $stmt->get_result();
$stmt->close();

// Pagination URL builder
function getCustomerPaginationUrl($p, $s, $st) {
    $params = [];
    if ($s !== '') $params['search'] = $s;
    if ($st !== '' && $st !== 'tickets_desc') $params['sort'] = $st;
    $params['page'] = $p;
    return '?' . http_build_query($params);
}
$pageTitle = 'Customer Directory';
$activeNav = 'customers';
$navUnreadBadge = $unreadEmailsBadge;
ob_start();
?>
    <style>
        .customers-page-wrapper {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        .customers-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }

        .customers-title-group h2 {
            margin: 0 0 6px;
            font-size: 24px;
            color: var(--text-main);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .customers-title-group p {
            margin: 0;
            color: var(--text-muted);
            font-size: 14px;
        }

        .customers-controls-card {
            background: var(--bg-card);
            border-radius: 12px;
            padding: 16px 20px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            margin-bottom: 20px;
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
        }

        .search-sort-form {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 12px;
            width: 100%;
        }

        .search-box-wrap {
            position: relative;
            flex: 1;
            min-width: 260px;
        }

        .search-box-wrap i {
            position: absolute;
            left: 12px;
            top: 50%;
            transform: translateY(-50%);
            color: #94a3b8;
            font-size: 14px;
        }

        .search-box-wrap input {
            width: 100%;
            padding: 9px 12px 9px 36px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            background: var(--bg-card-subtle);
            color: var(--text-main);
            outline: none;
            transition: border-color 0.2s, box-shadow 0.2s;
            box-sizing: border-box;
        }

        .search-box-wrap input:focus {
            border-color: #1976d2;
            box-shadow: 0 0 0 3px rgba(25, 118, 210, 0.12);
        }

        .sort-select-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .sort-select-wrap label {
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            white-space: nowrap;
        }

        .sort-select-wrap select {
            padding: 9px 14px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 14px;
            color: var(--text-main);
            background: var(--bg-card-subtle);
            outline: none;
            cursor: pointer;
        }

        .btn-filter-action {
            padding: 9px 16px;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-primary-action {
            background: #1976d2;
            color: #ffffff;
        }

        .btn-primary-action:hover {
            background: #1565c0;
        }

        .btn-reset-action {
            background: var(--bg-card-subtle);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .btn-reset-action:hover {
            background: var(--border-color);
            color: var(--text-main);
        }

        .customers-table-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        .customers-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
            text-align: left;
        }

        .customers-table th {
            background: var(--bg-card-subtle);
            color: var(--text-secondary);
            padding: 13px 16px;
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid var(--border-color);
            white-space: nowrap;
        }

        .customers-table td {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .customers-table tr.clickable-customer-row {
            cursor: pointer;
            transition: background 0.15s ease;
        }

        .customers-table tr.clickable-customer-row:hover td {
            background-color: var(--bg-card-subtle) !important;
        }

        [data-theme="dark"] .customers-table tr.clickable-customer-row:hover td {
            background-color: var(--bg-card-subtle) !important;
        }

        .customer-identity-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 220px;
        }

        .customer-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
            border: 2px solid #ffffff;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
        }

        .customer-avatar-circle {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, #1976d2, #0d47a1);
            color: #ffffff;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            flex-shrink: 0;
            box-shadow: 0 2px 6px rgba(25, 118, 210, 0.25);
        }

        .customer-name-meta {
            display: flex;
            flex-direction: column;
        }

        .customer-name {
            font-weight: 700;
            color: var(--text-main);
            font-size: 14px;
            line-height: 1.3;
        }

        .customer-subtext {
            font-size: 12px;
            color: var(--text-muted);
        }

        .customer-email {
            font-size: 13.5px;
            font-weight: 500;
            color: var(--text-secondary);
        }

        [data-theme="dark"] .customer-email {
            color: #F8FAFC;
            font-weight: 600;
        }

        .customer-phone {
            font-size: 11.5px;
            color: var(--text-muted);
            margin-top: 2px;
        }

        [data-theme="dark"] .customer-phone {
            color: #94A3B8;
        }

        .metric-pill {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 28px;
            padding: 4px 9px;
            border-radius: 20px;
            font-weight: 700;
            font-size: 12px;
            line-height: 1;
        }

        .metric-total {
            background: var(--bg-card-subtle);
            color: var(--text-secondary);
            border: 1px solid var(--border-color);
        }

        .metric-pending {
            background: #fff8e1;
            color: #b45309;
            border: 1px solid #fef3c7;
        }

        .metric-inprogress {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .metric-reopened {
            background: #f5f3ff;
            color: #7c3aed;
            border: 1px solid #ddd6fe;
        }

        .metric-resolved {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .view-btn-cell {
            white-space: nowrap;
            text-align: right;
        }

        .btn-view-profile {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            color: #1976d2;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .btn-view-profile:hover {
            background: #1976d2;
            color: #ffffff;
            border-color: #1976d2;
        }

        .empty-customers-state {
            padding: 50px 20px;
            text-align: center;
            color: var(--text-muted);
        }

        .empty-customers-state i {
            font-size: 42px;
            color: var(--text-subtle);
            margin-bottom: 14px;
        }

        .pagination-container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 20px;
            background: var(--bg-card-subtle);
            border-top: 1px solid var(--border-color);
            flex-wrap: wrap;
            gap: 12px;
            font-size: 13px;
            color: var(--text-muted);
        }

        .pagination-nav {
            display: flex;
            gap: 6px;
        }

        .page-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 32px;
            height: 32px;
            padding: 0 8px;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            background: var(--bg-card);
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 600;
            font-size: 12px;
            transition: all 0.15s ease;
        }

        .page-link:hover:not(.active):not(.disabled) {
            background: var(--bg-card-subtle);
            color: var(--text-main);
        }

        .page-link.active {
            background: #1976d2;
            color: #ffffff;
            border-color: #1976d2;
        }

        .page-link.disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_admin.php';
?>
        <div class="customers-page-wrapper">

            <div class="customers-header-bar">
                <div class="customers-title-group">
                    <h2><i class="fa-solid fa-users"></i> Customer Directory</h2>
                    <p>Comprehensive overview and ticket history for all registered support customers.</p>
                </div>
                <div style="font-size:13px; font-weight:600; color:var(--text-secondary); background:var(--bg-card); padding:8px 14px; border-radius:8px; border:1px solid var(--border-color); box-shadow:0 1px 3px rgba(0,0,0,0.03);">
                    Total Active Customers: <span style="color:#1976d2; font-weight:800;"><?php echo number_format($totalCustomers); ?></span>
                </div>
            </div>

            <!-- Search, Sort & Filter Bar -->
            <div class="customers-controls-card">
                <form method="GET" action="admin_customers.php" class="search-sort-form">
                    <div class="search-box-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="text" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search customer by name, email or phone...">
                    </div>

                    <div class="sort-select-wrap">
                        <label for="sort"><i class="fa-solid fa-arrow-down-short-wide"></i> Sort By:</label>
                        <select name="sort" id="sort" onchange="this.form.submit();">
                            <option value="tickets_desc" <?php if ($sort === 'tickets_desc') echo 'selected'; ?>>Most Complaints</option>
                            <option value="recent_ticket" <?php if ($sort === 'recent_ticket') echo 'selected'; ?>>Most Recent Activity</option>
                            <option value="pending_desc" <?php if ($sort === 'pending_desc') echo 'selected'; ?>>Most Pending Tickets</option>
                            <option value="name_asc" <?php if ($sort === 'name_asc') echo 'selected'; ?>>Name (A &rarr; Z)</option>
                            <option value="name_desc" <?php if ($sort === 'name_desc') echo 'selected'; ?>>Name (Z &rarr; A)</option>
                            <option value="tickets_asc" <?php if ($sort === 'tickets_asc') echo 'selected'; ?>>Fewest Complaints</option>
                        </select>
                    </div>

                    <button type="submit" class="btn-filter-action btn-primary-action">
                        <i class="fa-solid fa-filter"></i> Apply
                    </button>

                    <?php if ($search !== '' || $sort !== 'tickets_desc'): ?>
                        <a href="admin_customers.php" class="btn-filter-action btn-reset-action">
                            <i class="fa-solid fa-rotate-left"></i> Reset
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- Customers Directory Table -->
            <div class="customers-table-card">
                <?php if ($customers && $customers->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="customers-table">
                            <thead>
                                <tr>
                                    <th>Customer</th>
                                    <th>Email</th>
                                    <th style="text-align:center;">Total</th>
                                    <th style="text-align:center;">Pending</th>
                                    <th style="text-align:center;">In Progress</th>
                                    <th style="text-align:center;">Re-opened</th>
                                    <th style="text-align:center;">Resolved</th>
                                    <th>Last Ticket</th>
                                    <th style="text-align:right;">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = $customers->fetch_assoc()): 
                                    $cid = (int)$row['user_id'];
                                    $cName = htmlspecialchars($row['fullname']);
                                    $cEmail = htmlspecialchars($row['email']);
                                    $cPic = $row['profile_picture'];
                                    
                                    // Initials
                                    $parts = explode(' ', trim($row['fullname']));
                                    $inits = '';
                                    foreach ($parts as $p) {
                                        if (!empty($p)) $inits .= strtoupper($p[0]);
                                        if (strlen($inits) >= 2) break;
                                    }
                                    if (empty($inits)) $inits = 'CU';
                                    
                                    $lastDate = !empty($row['last_ticket_date']) ? date("d M Y, h:i A", strtotime($row['last_ticket_date'])) : '—';
                                ?>
                                    <tr class="clickable-customer-row" onclick="window.location.href='admin_customer_profile.php?id=<?php echo $cid; ?>'">
                                        <td>
                                            <div class="customer-identity-cell">
                                                <?php if (!empty($cPic) && file_exists(__DIR__ . '/uploads/' . $cPic)): ?>
                                                    <img src="uploads/<?php echo htmlspecialchars($cPic); ?>" alt="<?php echo $cName; ?>" class="customer-avatar">
                                                <?php else: ?>
                                                    <div class="customer-avatar-circle"><?php echo $inits; ?></div>
                                                <?php endif; ?>
                                                <div class="customer-name-meta">
                                                    <span class="customer-name"><?php echo $cName; ?></span>
                                                    <span class="customer-subtext">#USR-<?php echo sprintf("%04d", $cid); ?></span>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="customer-email"><?php echo $cEmail; ?></span>
                                            <?php if (!empty($row['phone'])): ?>
                                                <div class="customer-phone"><?php echo htmlspecialchars($row['phone']); ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="metric-pill metric-total"><?php echo (int)$row['total_tickets']; ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="metric-pill metric-pending"><?php echo (int)$row['pending_tickets']; ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="metric-pill metric-inprogress"><?php echo (int)$row['inprogress_tickets']; ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="metric-pill metric-reopened"><?php echo (int)$row['reopened_tickets']; ?></span>
                                        </td>
                                        <td style="text-align:center;">
                                            <span class="metric-pill metric-resolved"><?php echo (int)$row['resolved_tickets']; ?></span>
                                        </td>
                                        <td style="white-space:nowrap; font-size:13px; color:#64748b;">
                                            <?php echo $lastDate; ?>
                                        </td>
                                        <td class="view-btn-cell" onclick="event.stopPropagation();">
                                            <a href="admin_customer_profile.php?id=<?php echo $cid; ?>" class="btn-view-profile" title="View <?php echo $cName; ?>'s Profile">
                                                <i class="fa-solid fa-arrow-up-right-from-square"></i> Profile
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Pagination -->
                    <div class="pagination-container">
                        <div>
                            Showing <strong><?php echo min($totalCustomers, $offset + 1); ?></strong> to <strong><?php echo min($totalCustomers, $offset + $limit); ?></strong> of <strong><?php echo $totalCustomers; ?></strong> customers
                        </div>
                        <?php if ($totalPages > 1): ?>
                            <div class="pagination-nav">
                                <a href="<?php echo getCustomerPaginationUrl(max(1, $page - 1), $search, $sort); ?>" class="page-link <?php if ($page <= 1) echo 'disabled'; ?>">
                                    <i class="fa-solid fa-chevron-left"></i>
                                </a>

                                <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                    <a href="<?php echo getCustomerPaginationUrl($p, $search, $sort); ?>" class="page-link <?php if ($p === $page) echo 'active'; ?>">
                                        <?php echo $p; ?>
                                    </a>
                                <?php endfor; ?>

                                <a href="<?php echo getCustomerPaginationUrl(min($totalPages, $page + 1), $search, $sort); ?>" class="page-link <?php if ($page >= $totalPages) echo 'disabled'; ?>">
                                    <i class="fa-solid fa-chevron-right"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-customers-state">
                        <i class="fa-solid fa-user-slash"></i>
                        <h3 style="margin: 0 0 8px; color: #1e293b;">No Customers Found</h3>
                        <p style="margin: 0 0 16px;">
                            <?php if ($search !== ''): ?>
                                No customer matched your search query "<strong><?php echo htmlspecialchars($search); ?></strong>".
                            <?php else: ?>
                                No customer ticket records exist in the system yet.
                            <?php endif; ?>
                        </p>
                        <?php if ($search !== '' || $sort !== 'tickets_desc'): ?>
                            <a href="admin_customers.php" class="btn-filter-action btn-primary-action">
                                <i class="fa-solid fa-rotate-left"></i> Clear Filters
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            </div>

        </div>
<?php require_once __DIR__ . '/includes/footer.php'; ?>
