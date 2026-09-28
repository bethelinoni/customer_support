<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

/*$user_id = $_SESSION['user_id'];

$sql = "SELECT * FROM tickets WHERE user_id='$user_id' ORDER BY created_at DESC";
$result = $conn->query($sql);*/

$user_id = $_SESSION['user_id'];

// Retrieve and sanitize search and filter inputs
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$category = isset($_GET['category']) ? trim($_GET['category']) : '';
$priority = isset($_GET['priority']) ? trim($_GET['priority']) : '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$limit = 5; // 5 tickets per page

// Target Ticket Deep Linking & Visibility Resolution
$targetTicketId = isset($_GET['ticket_id']) ? (int)$_GET['ticket_id'] : (isset($_GET['id']) ? (int)$_GET['id'] : 0);
$targetTicket = null;

if ($targetTicketId > 0) {
    $chkStmt = $conn->prepare("SELECT id, created_at, status, category, priority, subject, message FROM tickets WHERE id = ? AND user_id = ?");
    $chkStmt->bind_param("ii", $targetTicketId, $user_id);
    $chkStmt->execute();
    $targetTicket = $chkStmt->get_result()->fetch_assoc();
    $chkStmt->close();

    if (!$targetTicket) {
        $targetTicketId = 0; // Not owned by logged-in customer, ignore!
    } else {
        // If active filters or search would hide the target ticket, clear them so the ticket is visible
        $conflicts = false;
        if ($status !== '' && $status !== 'All' && $targetTicket['status'] !== $status) {
            $conflicts = true;
        }
        if ($category !== '' && $category !== 'All' && $targetTicket['category'] !== $category) {
            $conflicts = true;
        }
        if ($priority !== '' && $priority !== 'All' && $targetTicket['priority'] !== $priority) {
            $conflicts = true;
        }
        if ($search !== '') {
            if (mb_stripos($targetTicket['subject'], $search) === false && mb_stripos($targetTicket['message'], $search) === false) {
                $conflicts = true;
            }
        }

        if ($conflicts) {
            $search = '';
            $status = '';
            $category = '';
            $priority = '';
        }
    }
}

// Helper to preserve active query parameters in pagination URLs
function getPaginationUrl($targetPage, $search, $status, $category, $priority, $ticketId = 0) {
    $params = [];
    if ($search !== '') $params['search'] = $search;
    if ($status !== '' && $status !== 'All') $params['status'] = $status;
    if ($category !== '' && $category !== 'All') $params['category'] = $category;
    if ($priority !== '' && $priority !== 'All') $params['priority'] = $priority;
    if ($ticketId > 0) $params['ticket_id'] = $ticketId;
    $params['page'] = $targetPage;
    return '?' . http_build_query($params);
}

// Build query conditions
$whereClauses = ["tickets.user_id = ?"];
$paramTypes = "i";
$paramValues = [$user_id];

if ($search !== '') {
    $whereClauses[] = "(tickets.subject LIKE ? OR tickets.message LIKE ?)";
    $searchTerm = '%' . $search . '%';
    $paramTypes .= "ss";
    $paramValues[] = $searchTerm;
    $paramValues[] = $searchTerm;
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

$whereSql = implode(" AND ", $whereClauses);

// Count total matching tickets
$countSql = "SELECT COUNT(*) AS total FROM tickets WHERE $whereSql";
$countStmt = $conn->prepare($countSql);
$countStmt->bind_param($paramTypes, ...$paramValues);
$countStmt->execute();
$totalTickets = (int)($countStmt->get_result()->fetch_assoc()['total'] ?? 0);
$countStmt->close();

// If target ticket is requested and belongs to user, find which page it is on
if ($targetTicketId > 0 && $targetTicket) {
    $posSql = "SELECT COUNT(*) AS pos FROM tickets WHERE $whereSql AND (tickets.created_at > ? OR (tickets.created_at = ? AND tickets.id > ?))";
    $posStmt = $conn->prepare($posSql);
    $posParamTypes = $paramTypes . "ssi";
    $posParamValues = array_merge($paramValues, [$targetTicket['created_at'], $targetTicket['created_at'], $targetTicketId]);
    $posStmt->bind_param($posParamTypes, ...$posParamValues);
    $posStmt->execute();
    $ticketPos = (int)($posStmt->get_result()->fetch_assoc()['pos'] ?? 0);
    $posStmt->close();

    $page = (int)floor($ticketPos / $limit) + 1;
}

// Compute pagination
$totalPages = $totalTickets > 0 ? (int)ceil($totalTickets / $limit) : 1;
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $limit;
if ($offset < 0) $offset = 0;

// Fetch paginated tickets
$sql = "SELECT tickets.* FROM tickets WHERE $whereSql ORDER BY tickets.created_at DESC, tickets.id DESC LIMIT ? OFFSET ?";
$stmt = $conn->prepare($sql);
$dataParamTypes = $paramTypes . "ii";
$dataParamValues = array_merge($paramValues, [$limit, $offset]);
$stmt->bind_param($dataParamTypes, ...$dataParamValues);
$stmt->execute();
$result = $stmt->get_result();
$ticketCount = $result->num_rows;
$statusFilter = $status;

$ticketsList = [];
$viewedTicketIds = [];
while ($tRow = $result->fetch_assoc()) {
    $ticketsList[] = $tRow;
    $viewedTicketIds[] = (int)$tRow['id'];
}
$stmt->close();

// Mark tickets opened/viewed on this page as seen (update customer_viewed_at to now)
if (!empty($viewedTicketIds)) {
    $idInList = implode(',', $viewedTicketIds);
    $conn->query("UPDATE tickets SET customer_viewed_at = NOW() WHERE id IN ($idInList) AND user_id = $user_id");
}

// Also mark specific ticket if opened via ?ticket_id= or ?id=
if ($targetTicketId > 0) {
    $conn->query("UPDATE tickets SET customer_viewed_at = NOW() WHERE id = $targetTicketId AND user_id = $user_id");
}

// Calculate remaining unseen tickets for the nav badge
$unseenTicketsCount = getUnseenTicketsCount($conn, $user_id);
$unseenNavBadge = formatBadgeCount($unseenTicketsCount);
$pageTitle = 'My Tickets';
$activeNav = 'tickets';
$navUnseenBadge = $unseenNavBadge;
ob_start();
?>
    <style>
        .ticket {
            border: 1px solid var(--border-color);
            padding: 24px 28px;
            border-radius: 12px;
            margin-bottom: 20px;
            background: var(--bg-card);
            box-shadow: var(--shadow-sm);
            transition: transform 0.2s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.2s ease, border-color 0.2s ease;
        }

        .ticket:hover {
            border-color: #93C5FD;
            box-shadow: var(--shadow-md);
            transform: translateY(-2px);
        }

        [data-theme="dark"] .ticket:hover {
            border-color: var(--primary-color);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.4);
        }

        .ticket h4 {
            color: var(--text-main);
            margin-bottom: 8px;
            font-size: 18px;
            font-weight: 700;
        }

        .status {
            font-size: 13px;
            margin-bottom: 10px;
            color: var(--text-muted);
        }

        /*.response {
            margin-top: 10px;
            padding: 10px;
            background: #e3f2fd;
            border-left: 4px solid #1976d2;
        }*/

        .response{
            margin-top:18px;
            padding:18px;
            background:#f4f9ff;
            border:1px solid #d6e8ff;
            border-left:5px solid #1976d2;
            border-radius:10px;
        }

        .response strong{
            display:block;
            margin-bottom:8px;
            color:#0d47a1;
        }

        .no-ticket {
            text-align: center;
            color: #666; /* #777*/
            padding:45px 20px;
            /*margin-top: 20px;*/
        }

        .no-ticket i{
            font-size:48px;
            color:#1976d2;
            margin-bottom:15px;
            display:block;
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

        .main-content {
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 40px 20px; 
        }

        .nav-links a {
            text-decoration: none;
        }

        .nav-links a:hover {
        opacity: 0.8;
        text-decoration: none;
        }

        .delete-btn{
            background:#ef4444;
            color:#ffffff;
            border:1px solid #ef4444;
        } 

        .delete-btn:hover{
            background:#dc2626;
            border-color:#dc2626;
            color:#ffffff;
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

        .filter-actions{
            margin:15px 0 25px;
        }

        .show-all-btn{
            display:inline-block;
            padding:10px 18px;
            background:#1976d2;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
            transition:.25s;
        }

        .show-all-btn:hover{
            background:#0d47a1;
        }

        .page-summary{
            color:#666;
            text-align:center;
            margin:12px 0 28px;
            font-size:15px;
        }

        .page-summary strong{
            color:#1976d2;
            font-size:16px;
        }

        .ticket-header{
            display:flex;
            justify-content:space-between;
            align-items:flex-start;
            gap:15px;
            margin-bottom:15px;
        }

        .ticket-subject{
            font-size:20px;
            font-weight:700;
            color:#1f2937;
            margin:0;
            word-break:break-word;
        }

        .ticket-message{
            color:#555;
            line-height:1.7;
            margin:18px 0;
        }

        .ticket-footer{
            display:flex;
            justify-content:space-between;
            align-items:center;
            flex-wrap:wrap;
            gap:12px;
            margin-top:18px;
        }

        .ticket-date{
            color:#777;
            font-size:14px;
        }

        .status-badge{
            padding:7px 14px;
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
            color:#666;
            font-size:14px;
            font-style:italic;
        }

        .success-message i, .error-message i{
            margin-right:8px;
            font-size:18px;
        }

        [data-theme="dark"] .ticket-subject {
            color: var(--text-main) !important;
        }

        [data-theme="dark"] .ticket-message {
            color: var(--text-secondary) !important;
        }

        [data-theme="dark"] .response {
            background: var(--bg-card-subtle) !important;
            border-color: var(--border-color) !important;
            border-left-color: var(--primary-color) !important;
        }

        [data-theme="dark"] .response strong {
            color: #60a5fa !important;
        }

        [data-theme="dark"] .no-ticket {
            background: var(--bg-card) !important;
            border-color: var(--border-color) !important;
            color: var(--text-secondary) !important;
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
require_once __DIR__ . '/includes/header_customer.php';
?>
<div class="tickets-page-wrapper"> 

    <!-- Section 1: Header Panel -->
    <section class="tickets-header-card">
        <div class="page-header-title-group">
            <h2>
                <?php
                    if ($statusFilter == "Pending") {
                        echo "<i class='fa-solid fa-clock'></i> Pending Complaints";
                    } elseif ($statusFilter == "In Progress") {
                        echo "<i class='fa-solid fa-spinner'></i> In-Progress Complaints";
                    } elseif ($statusFilter == "Resolved") {
                        echo "<i class='fa-solid fa-circle-check'></i> Resolved Complaints";
                    } elseif ($statusFilter == "Reopened") {
                        echo "<i class='fa-solid fa-arrows-rotate'></i> Reopened Complaints";
                    } else {
                        echo "<i class='fa-solid fa-clipboard-list'></i> My Complaints";
                    }
                ?>
            </h2>
            <p class="page-summary" style="margin: 0; text-align: left;">
                <?php if ($totalTickets > 0): ?>
                    <i class="fa-solid fa-list-check"></i>
                    Showing <strong><?php echo ($offset + 1); ?> &ndash; <?php echo min($offset + $limit, $totalTickets); ?></strong> of <strong><?php echo $totalTickets; ?></strong> complaint(s) &bull; Page <strong><?php echo $page; ?></strong> of <strong><?php echo $totalPages; ?></strong>
                <?php else: ?>
                    <i class="fa-solid fa-circle-info"></i>
                    No complaints found matching your criteria.
                <?php endif; ?>
            </p>
        </div>
        <div class="page-header-actions">
            <a href="submit_ticket.php" class="btn btn-primary" style="display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-plus"></i> Submit New Complaint
            </a>
        </div>
    </section>

    <!-- Section 2: Search & Filter Panel -->
    <section class="tickets-filter-card">
        <div class="search-filter-bar">
            <form method="GET" action="view_tickets.php" class="filter-form">
                <div class="filter-row">
                    <div class="search-group">
                        <label for="search"><i class="fa-solid fa-magnifying-glass"></i> Search Complaints</label>
                        <div class="search-input-wrapper">
                            <i class="fa-solid fa-magnifying-glass search-icon"></i>
                            <input type="text" id="search" name="search" value="<?php echo htmlspecialchars($search); ?>" placeholder="Search subject or message...">
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
                                <option value="Account & Security" <?php if ($category === 'Account & Security') echo 'selected'; ?>>Account & Security</option>
                                <option value="Customer Service" <?php if ($category === 'Customer Service') echo 'selected'; ?>>Customer Service</option>
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
                            <a href="view_tickets.php" class="reset-btn">
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

    <!-- Section 3: Tickets List Feed -->
    <section class="tickets-feed-section" style="display: flex; flex-direction: column; gap: 0;">

    <?php
    if (!empty($ticketsList)) {
        foreach ($ticketsList as $row) { 
            $isHighlighted = ($targetTicketId > 0 && (int)$row['id'] === $targetTicketId);
            $hlClass = $isHighlighted ? ' ticket-highlighted' : '';
            echo "<div class='ticket{$hlClass}' id='ticket-{$row['id']}'>";

            $statusClass = "status-pending";
            if ($row['status'] == "Resolved") {
                $statusClass = "status-resolved";
            } elseif ($row['status'] == "In Progress") {
                $statusClass = "status-inprogress";
            } elseif ($row['status'] == "Reopened") {
                $statusClass = "status-reopened";
            }

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

            echo "<div class='ticket-header'>";

            echo "<div>";
            echo "<h4 class='ticket-subject'>"
                . htmlspecialchars($row['subject']) .
                "</h4>";
            echo "<div class='ticket-meta-badges'>";
            echo "<span class='category-tag'><i class='fa-solid $catIcon'></i> $category</span>";
            echo "<span class='priority-badge $priorityClass'><i class='fa-solid $priorityIcon'></i> $priority Priority</span>";
            echo "</div>";
            echo "</div>";

            echo "<span class='status-badge $statusClass'>"
                . htmlspecialchars($row['status']) .
                "</span>";

            echo "</div>";

            // Prominent banner if ticket was reopened
            if ($row['status'] === "Reopened") {
                echo "<div class='reopen-banner'>";
                echo "<div class='reopen-banner-header'><i class='fa-solid fa-arrows-rotate'></i> Complaint Reopened by You</div>";
                if (!empty($row['reopen_reason'])) {
                    echo "<div><strong>Reason:</strong> " . htmlspecialchars($row['reopen_reason']) . "</div>";
                }
                if (!empty($row['reopened_at'])) {
                    echo "<div style='font-size:11px;color:#7c3aed;margin-top:4px;'><i class='fa-solid fa-clock'></i> Reopened on " . date("d M Y • h:i A", strtotime($row['reopened_at'])) . "</div>";
                }
                echo "</div>";
            }

            // Prominent banner if ticket is In Progress
            if ($row['status'] === "In Progress") {
                echo "<div class='inprogress-banner'>";
                echo "<div class='inprogress-banner-header'><i class='fa-solid fa-spinner fa-spin'></i> Complaint Under Active Investigation</div>";
                echo "<div>A support specialist is actively working on your complaint. You will receive an official response and resolution shortly.</div>";
                if (!empty($row['in_progress_at'])) {
                    echo "<div style='font-size:11px;color:#0284c7;margin-top:4px;'><i class='fa-solid fa-clock'></i> Work started on " . date("d M Y • h:i A", strtotime($row['in_progress_at'])) . "</div>";
                }
                echo "</div>";
            }

            echo "<div class='ticket-message'>"
                . nl2br(htmlspecialchars($row['message'])) .
                "</div>";

            // Customer Attachment Display
            if (!empty($row['attachment'])) {
                $attachFile = htmlspecialchars($row['attachment']);
                $attachPath = "uploads/" . $attachFile;
                if (file_exists(__DIR__ . "/" . $attachPath)) {
                    echo "<div class='attachment-container'>";
                    echo "<div class='attachment-title'><i class='fa-solid fa-paperclip'></i> Attached Screenshot</div>";
                    echo "<div class='attachment-image-link' onclick=\"openImageModal('$attachPath')\">";
                    echo "<img src='$attachPath' alt='Screenshot' class='attachment-image-thumb'>";
                    echo "<span class='attachment-overlay'><i class='fa-solid fa-magnifying-glass-plus'></i></span>";
                    echo "</div>";
                    echo "<div class='attachment-actions'><a href='javascript:void(0);' onclick=\"openImageModal('$attachPath')\"><i class='fa-solid fa-up-right-from-square'></i> View Full Image</a></div>";
                    echo "</div>";
                }
            }

            // Display Previous Response History if ticket had been reopened and re-resolved
            if (!empty($row['response_history'])) {
                $pastResponses = json_decode($row['response_history'], true);
                if (is_array($pastResponses)) {
                    $round = 1;
                    foreach ($pastResponses as $past) {
                        echo "<div class='response history-response'>";
                        echo "<div style='display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; flex-wrap:wrap; gap:6px;'>";
                        echo "<strong style='margin:0;'><i class='fa-solid fa-clock-rotate-left'></i> Previous Support Resolution (Round $round)</strong>";
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
                            echo "<strong><i class='fa-solid fa-arrows-rotate'></i> Reason Reopened by You:</strong> " . htmlspecialchars($past['reopen_reason']);
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

            if (!empty($row['response'])) {

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

                // Admin Response Attachment Display
                if (!empty($row['admin_attachment'])) {
                    $adminAttachFile = htmlspecialchars($row['admin_attachment']);
                    $adminAttachPath = "uploads/" . $adminAttachFile;
                    if (file_exists(__DIR__ . "/" . $adminAttachPath)) {
                        echo "<div class='attachment-container' style='margin-top:12px;'>";
                        echo "<div class='attachment-title'><i class='fa-solid fa-paperclip'></i> Support Attached Screenshot</div>";
                        echo "<div class='attachment-image-link' onclick=\"openImageModal('$adminAttachPath')\">";
                        echo "<img src='$adminAttachPath' alt='Support Screenshot' class='attachment-image-thumb'>";
                        echo "<span class='attachment-overlay'><i class='fa-solid fa-magnifying-glass-plus'></i></span>";
                        echo "</div>";
                        echo "<div class='attachment-actions'><a href='javascript:void(0);' onclick=\"openImageModal('$adminAttachPath')\"><i class='fa-solid fa-up-right-from-square'></i> View Full Image</a></div>";
                        echo "</div>";
                    }
                }

                echo "</div>"; // close response div
            }

            // Customer Satisfaction (CSAT) Rating Block
            if ($row['status'] === "Resolved") {
                if (!empty($row['rating'])) {
                    $stars = (int)$row['rating'];
                    $starsHtml = str_repeat('<i class="fa-solid fa-star star-filled" style="color:#f59e0b;"></i> ', $stars) . str_repeat('<i class="fa-regular fa-star star-empty" style="color:#94a3b8;"></i> ', 5 - $stars);

                    $lastRatingTime = !empty($row['rating_updated_at']) ? $row['rating_updated_at'] : $row['rated_at'];
                    $showReResolvePrompt = (!empty($row['responded_at']) && !empty($lastRatingTime) && strtotime($row['responded_at']) > strtotime($lastRatingTime));

                    echo "<div class='csat-box' id='csatBox_{$row['id']}'>";

                    if ($showReResolvePrompt) {
                        echo "
                        <div class='csat-re-resolve-prompt' id='reResolvePrompt_{$row['id']}'>
                            <div class='prompt-header'>
                                <i class='fa-solid fa-arrows-rotate'></i> This complaint was re-opened and resolved again. Would you like to update your rating?
                            </div>
                            <div class='prompt-actions'>
                                <button type='button' class='btn-prompt-update' onclick='showChangeRatingForm({$row['id']})'>
                                    <i class='fa-solid fa-pen-to-square'></i> Update Rating
                                </button>
                                <button type='button' class='btn-prompt-keep' onclick='dismissReResolvePrompt({$row['id']})'>
                                    <i class='fa-solid fa-check'></i> Keep My Rating
                                </button>
                                <form action='rate_ticket.php' method='POST' style='display:inline;' onsubmit=\"return confirm('Are you sure you want to remove your rating?');\">
                                    <input type='hidden' name='ticket_id' value='{$row['id']}'>
                                    <input type='hidden' name='action' value='remove'>
                                    <button type='submit' class='btn-prompt-remove'>
                                        <i class='fa-solid fa-trash-can'></i> Remove Rating
                                    </button>
                                </form>
                            </div>
                        </div>";
                    }

                    echo "<div class='csat-stars-row'>";
                    echo "<span>Your Satisfaction Rating:</span> ";
                    echo "<span class='csat-stars-gold'>$starsHtml</span> ";
                    echo "<span style='font-size:13px; color:#6b7280;'>($stars/5)</span>";
                    echo "</div>";

                    if (!empty($row['feedback'])) {
                        echo "<div class='csat-comment'>&ldquo;" . htmlspecialchars($row['feedback']) . "&rdquo;</div>";
                    }

                    echo "<div style='display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px; margin-top:8px;'>";
                    echo "<div class='csat-date'><i class='fa-solid fa-clock'></i> ";
                    if (!empty($row['rating_updated_at'])) {
                        echo "Updated on " . date("d M Y • h:i A", strtotime($row['rating_updated_at'])) . " <span class='badge-updated'>Updated</span>";
                    } elseif (!empty($row['rated_at'])) {
                        echo "Rated on " . date("d M Y • h:i A", strtotime($row['rated_at']));
                    }
                    echo "</div>";

                    echo "<div class='csat-actions' style='display:flex; gap:8px; align-items:center;'>";
                    echo "<button type='button' class='btn-csat-change' onclick='showChangeRatingForm({$row['id']})'><i class='fa-solid fa-pen-to-square'></i> Change Rating</button>";
                    echo "<form action='rate_ticket.php' method='POST' style='display:inline;' onsubmit=\"return confirm('Are you sure you want to remove your rating?');\">";
                    echo "<input type='hidden' name='ticket_id' value='{$row['id']}'>";
                    echo "<input type='hidden' name='action' value='remove'>";
                    echo "<button type='submit' class='btn-csat-remove'><i class='fa-solid fa-trash-can'></i> Remove Rating</button>";
                    echo "</form>";
                    echo "</div>";
                    echo "</div>";

                    // Hidden Change Rating Form (prefilled)
                    echo "
                    <div id='csatEditForm_{$row['id']}' class='csat-edit-form' style='display:none; margin-top:14px; padding-top:14px; border-top:1px dashed #e5e7eb;'>
                        <div class='csat-prompt-header'><i class='fa-solid fa-pen-to-square'></i> Change Your Rating</div>
                        <form action='rate_ticket.php' method='POST' class='csat-form'>
                            <input type='hidden' name='ticket_id' value='{$row['id']}'>
                            <input type='hidden' name='action' value='update'>
                            <div class='star-rating'>";
                    for ($s = 5; $s >= 1; $s--) {
                        $checked = ($stars === $s) ? 'checked' : '';
                        echo "<input type='radio' id='star_edit_{$row['id']}_{$s}' name='rating' value='$s' $checked required>";
                        echo "<label for='star_edit_{$row['id']}_{$s}' title='$s star" . ($s > 1 ? "s" : "") . "'><i class='fa-solid fa-star'></i></label>";
                    }
                    $safeFeedback = htmlspecialchars($row['feedback'] ?? '');
                    echo "  </div>
                            <textarea name='feedback' class='csat-feedback-input' placeholder='Optional: Update your comments or feedback...'>{$safeFeedback}</textarea>
                            <div style='display:flex; gap:8px; align-items:center;'>
                                <button type='submit' class='csat-submit-btn'><i class='fa-solid fa-check'></i> Save Changes</button>
                                <button type='button' class='reset-btn' style='padding:7px 12px; font-size:12px; cursor:pointer;' onclick='hideChangeRatingForm({$row['id']})'>Cancel</button>
                            </div>
                        </form>
                    </div>";

                    echo "</div>"; // close .csat-box
                } else {
                    // Initial Rating Prompt Form
                    echo "<div class='csat-box'>";
                    echo "<div class='csat-prompt-header'><i class='fa-solid fa-star'></i> Rate This Resolution</div>";
                    echo "<p style='font-size:12px; color:#6b7280; margin:0 0 10px;'>How satisfied are you with the solution provided by our support team?</p>";
                    echo "<form action='rate_ticket.php' method='POST' class='csat-form'>";
                    echo "<input type='hidden' name='ticket_id' value='{$row['id']}'>";
                    echo "<input type='hidden' name='action' value='rate'>";
                    echo "<div class='star-rating'>";
                    for ($s = 5; $s >= 1; $s--) {
                        echo "<input type='radio' id='star_{$row['id']}_{$s}' name='rating' value='$s' required>";
                        echo "<label for='star_{$row['id']}_{$s}' title='$s star" . ($s > 1 ? "s" : "") . "'><i class='fa-solid fa-star'></i></label>";
                    }
                    echo "</div>";
                    echo "<textarea name='feedback' class='csat-feedback-input' placeholder='Optional: Share quick feedback or comments about this resolution...'></textarea>";
                    echo "<button type='submit' class='csat-submit-btn'><i class='fa-solid fa-check'></i> Submit Feedback</button>";
                    echo "</form>";
                    echo "</div>";
                }
            } elseif (!empty($row['rating'])) {
                // Ticket is not Resolved (Reopened or In Progress) but has a previous rating: show as read-only
                $stars = (int)$row['rating'];
                $starsHtml = str_repeat('<i class="fa-solid fa-star star-filled" style="color:#f59e0b;"></i> ', $stars) . str_repeat('<i class="fa-regular fa-star star-empty" style="color:#94a3b8;"></i> ', 5 - $stars);
                echo "<div class='csat-box'>";
                echo "<div class='csat-stars-row'>";
                echo "<span style='font-size:13px; color:#475569; font-weight:600;'><i class='fa-solid fa-clock-rotate-left'></i> Your previous rating:</span> ";
                echo "<span class='csat-stars-gold'>$starsHtml</span> ";
                echo "<span style='font-size:12px; color:#64748b;'>($stars/5)</span>";
                echo "</div>";
                if (!empty($row['feedback'])) {
                    echo "<div class='csat-comment' style='border-left-color:#94a3b8; color:#475569;'>&ldquo;" . htmlspecialchars($row['feedback']) . "&rdquo;</div>";
                }
                echo "<div class='csat-date' style='margin-top:6px;'><i class='fa-solid fa-clock'></i> ";
                if (!empty($row['rating_updated_at'])) {
                    echo "Updated on " . date("d M Y • h:i A", strtotime($row['rating_updated_at'])) . " <span class='badge-updated'>Updated</span>";
                } elseif (!empty($row['rated_at'])) {
                    echo "Rated on " . date("d M Y • h:i A", strtotime($row['rated_at']));
                }
                echo "</div>";
                echo "</div>";
            }

            echo "<div class='ticket-footer'>";

            // Date
            echo "<div class='ticket-date'>";
            echo "<i class='fa-solid fa-calendar-days'></i> Submitted: " .
            date("d M Y • h:i A", strtotime($row['created_at']));
            echo "</div>";

            echo "<div class='ticket-actions' style='display:flex;gap:10px;align-items:center;flex-wrap:wrap;'>";

            // Ticket Receipt action button
            echo "<a href=\"ticket_receipt.php?id={$row['id']}\" target=\"_blank\" class=\"action-btn-sm btn-action-receipt\"><i class=\"fa-solid fa-file-invoice\"></i> View Receipt</a>";

            // Allow reopening resolved tickets
            if ($row['status'] == "Resolved") {
                echo "<button type='button' class='action-btn-sm reopen-action-btn' onclick='toggleReopenForm({$row['id']})'><i class='fa-solid fa-arrows-rotate'></i> Reopen Complaint</button>";
            }

            // Show delete button ONLY for pending complaints
            if ($row['status'] == "Pending") {
                echo "<a class='action-btn-sm delete-btn'
                        href='delete_ticket.php?id=".$row['id']."'
                        onclick=\"return confirm('Are you sure you want to delete this complaint?');\">
                        <i class='fa-solid fa-trash'></i>
                        Delete Complaint
                      </a>";
            } 
            echo "</div>";
            echo "</div>"; 

            // Expandable Reopen Form for Resolved tickets
            if ($row['status'] == "Resolved") {
                echo "
                <div id='reopenCard_{$row['id']}' class='reopen-modal-card'>
                    <div style='font-weight:700; color:#5b21b6; margin-bottom:6px;'><i class='fa-solid fa-triangle-exclamation'></i> Reopen This Complaint</div>
                    <p style='font-size:12px; color:#6b7280; margin:0 0 10px;'>If your issue was not resolved satisfactorily or has resurfaced, please let our team know why below:</p>
                    <form action='reopen_ticket.php' method='POST'>
                        <input type='hidden' name='ticket_id' value='{$row['id']}'>
                        <textarea name='reopen_reason' class='csat-feedback-input' placeholder='Please explain why you are reopening this complaint...' required style='border-color:#c084fc; min-height:55px; margin-bottom:8px;'></textarea>
                        <div style='display:flex; gap:10px;'>
                            <button type='submit' class='reopen-action-btn'><i class='fa-solid fa-check'></i> Confirm Reopening</button>
                            <button type='button' class='btn-reopen-cancel' onclick='toggleReopenForm({$row['id']})'>Cancel</button>
                        </div>
                    </form>
                </div>";
            }

            echo "</div>";
        }
    } else {
        $isFiltered = ($search !== '' || ($status !== '' && $status !== 'All') || ($category !== '' && $category !== 'All') || ($priority !== '' && $priority !== 'All'));
        if ($isFiltered) {
            echo "
            <div class='no-ticket'>
                <i class='fa-solid fa-magnifying-glass'></i>
                <p>No complaints match your search criteria.</p>
                <small>Try adjusting your keyword or filters, or click Reset.</small>
            </div>";
        } else {
            echo "
            <div class='no-ticket'>
                <i class='fa-solid fa-inbox'></i>
                <p>No complaints submitted yet.</p>
                <small>Submit your first complaint to get started.</small>
            </div>";  
        }
    }
    ?>
    </section> <!-- /.tickets-feed-section -->

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

    <div class="link portal-bottom-link" style="text-align: center; margin-top: 16px; margin-bottom: 24px;">
        <a href="dashboard.php" class="btn btn-ghost" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 600;">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>
</div> <!-- /.tickets-page-wrapper -->

    <!-- Image Preview Lightbox Modal -->
    <div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
        <button class="image-modal-close" onclick="closeImageModal(event)">&times;</button>
        <img id="modalImage" class="image-modal-content" src="" alt="Full preview">
    </div>

    <script>
    function toggleReopenForm(id) {
        const card = document.getElementById('reopenCard_' + id);
        if (card) {
            card.style.display = (card.style.display === 'block') ? 'none' : 'block';
        }
    }

    function showChangeRatingForm(id) {
        const form = document.getElementById('csatEditForm_' + id);
        if (form) {
            form.style.display = (form.style.display === 'block') ? 'none' : 'block';
        }
    }

    function hideChangeRatingForm(id) {
        const form = document.getElementById('csatEditForm_' + id);
        if (form) {
            form.style.display = 'none';
        }
    }

    function dismissReResolvePrompt(id) {
        const prompt = document.getElementById('reResolvePrompt_' + id);
        if (prompt) {
            prompt.style.display = 'none';
        }
    }

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

    // Fire-and-forget background email queue processor
    (function() {
        if (window.fetch) {
            fetch('process_email_queue.php', { method: 'POST', keepalive: true }).catch(function() {});
        }
    })();
    </script>
    <?php require_once __DIR__ . '/includes/footer.php'; ?>