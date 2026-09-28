<?php
session_start();
include "connect.php";

if (!isset($_SESSION["admin"])) {
    header("Location: login.php?type=admin");
    exit();
}

// Unread email logs badge count
$unreadEmailsCount = getUnreadEmailLogsCount($conn);
$unreadEmailsBadge = formatBadgeCount($unreadEmailsCount);

// 1. CORE STATS
$total       = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets")->fetch_assoc()["c"];
$pending     = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Pending'")->fetch_assoc()["c"];
$inprogress  = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'In Progress'")->fetch_assoc()["c"];
$resolved    = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Resolved'")->fetch_assoc()["c"];
$reopened    = (int)$conn->query("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Reopened'")->fetch_assoc()["c"];

$avgRow      = $conn->query("SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, responded_at)) AS ah FROM tickets WHERE status='Resolved' AND responded_at IS NOT NULL")->fetch_assoc();
$avgHours    = round((float)($avgRow["ah"] ?? 0), 1);
$avgResolution = $avgHours >= 24 ? round($avgHours/24,1)." days" : ($avgHours > 0 ? $avgHours." hrs" : "N/A");
$resolutionRate = $total > 0 ? round(($resolved/$total)*100,1) : 0;

// 1.5. CSAT SATISFACTION METRICS
$csatRow = $conn->query("SELECT AVG(rating) AS avg_r, COUNT(rating) AS cnt_r FROM tickets WHERE rating IS NOT NULL")->fetch_assoc();
$avgRating = $csatRow["avg_r"] !== null ? round((float)$csatRow["avg_r"], 1) : null;
$ratedCount = (int)($csatRow["cnt_r"] ?? 0);

$feedbackResult = $conn->query("
    SELECT tickets.id, tickets.rating, tickets.feedback, tickets.rated_at, tickets.rating_updated_at, users.fullname 
    FROM tickets 
    JOIN users ON tickets.user_id = users.id 
    WHERE tickets.rating IS NOT NULL AND tickets.rating > 0
    ORDER BY COALESCE(tickets.rating_updated_at, tickets.rated_at) DESC 
    LIMIT 5
");


// 2. BY CATEGORY
$catResult = $conn->query("SELECT category, COUNT(*) AS cnt FROM tickets GROUP BY category ORDER BY cnt DESC");
$catLabels = []; $catData = [];
$catColors = ["Technical Support"=>"#1976d2","Billing & Payments"=>"#f9a825","Account & Security"=>"#e53935","General Inquiry"=>"#43a047","Customer Service"=>"#0891b2"];
while($r=$catResult->fetch_assoc()){ $catLabels[]=$r["category"]?:"Unspecified"; $catData[]=(int)$r["cnt"]; }

// 3. BY PRIORITY
$priResult = $conn->query("SELECT priority, COUNT(*) AS cnt FROM tickets GROUP BY priority ORDER BY FIELD(priority,'Urgent','High','Medium','Low')");
$priLabels=[]; $priData=[]; $priColors=[];
$priColorMap=["Urgent"=>"#c62828","High"=>"#e65100","Medium"=>"#2e7d32","Low"=>"#616161"];
while($r=$priResult->fetch_assoc()){ $l=$r["priority"]?:"Unspecified"; $priLabels[]=$l; $priData[]=(int)$r["cnt"]; $priColors[]=$priColorMap[$l]??"#90a4ae"; }

// 4. 30-DAY TREND
$trendResult = $conn->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM tickets WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 29 DAY) GROUP BY DATE(created_at) ORDER BY day ASC");
$trendRaw = [];
while ($r = $trendResult->fetch_assoc()) $trendRaw[$r["day"]] = (int)$r["cnt"];
$trendLabels = []; $trendData = []; $trendDatesRaw = [];
for ($i = 29; $i >= 0; $i--) {
    $d = date("Y-m-d", strtotime("-{$i} days"));
    $trendDatesRaw[] = $d;
    $trendLabels[] = date("d M", strtotime($d));
    $trendData[] = $trendRaw[$d] ?? 0;
}

// 5. THIS WEEK (Monday 00:00:00 to Sunday 23:59:59 server timezone)
list($thisMonday, $thisSunday) = getThisWeekRange();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tickets WHERE created_at >= ? AND created_at <= ?");
$stmt->bind_param("ss", $thisMonday, $thisSunday);
$stmt->execute();
$weekNew = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tickets WHERE status = 'Resolved' AND responded_at >= ? AND responded_at <= ?");
$stmt->bind_param("ss", $thisMonday, $thisSunday);
$stmt->execute();
$weekResolved = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

$stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tickets WHERE created_at >= ? AND created_at <= ? AND status != 'Resolved'");
$stmt->bind_param("ss", $thisMonday, $thisSunday);
$stmt->execute();
$weekStillPending = (int)($stmt->get_result()->fetch_assoc()['c'] ?? 0);
$stmt->close();

// 6. TOP 5 CUSTOMERS
$topResult = $conn->query("
    SELECT users.id AS user_id, users.fullname, users.email, users.profile_picture,
           COUNT(tickets.id) AS total,
           SUM(tickets.status = 'Pending') AS pending,
           SUM(tickets.status = 'In Progress') AS in_progress,
           SUM(tickets.status = 'Resolved') AS resolved,
           SUM(tickets.status = 'Reopened') AS reopened
    FROM tickets 
    JOIN users ON tickets.user_id = users.id 
    GROUP BY tickets.user_id 
    ORDER BY total DESC 
    LIMIT 5
");
$topCustomers = [];
while ($r = $topResult->fetch_assoc()) $topCustomers[] = $r;

// 7. RECENTLY RESOLVED
$recentResolved = $conn->query("SELECT tickets.id, tickets.subject, tickets.priority, tickets.category, users.fullname, TIMESTAMPDIFF(HOUR, tickets.created_at, tickets.responded_at) AS hrs FROM tickets JOIN users ON tickets.user_id = users.id WHERE tickets.status = 'Resolved' AND tickets.responded_at IS NOT NULL ORDER BY tickets.responded_at DESC LIMIT 5");
$pageTitle = 'Analytics & Insights';
$activeNav = 'analytics';
$navUnreadBadge = $unreadEmailsBadge;
ob_start();
?>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<style>
.analytics-page{max-width:1600px;margin:0 auto;width:100%;padding:0 20px 60px}
.analytics-page h2{color:var(--primary-color);font-size:22px;margin-bottom:6px;display:flex;align-items:center;gap:10px}
.analytics-subtitle{color:var(--text-secondary);font-size:14px;margin-bottom:28px}
.kpi-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:16px;margin-bottom:28px}
.kpi-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:22px 20px;box-shadow:0 2px 12px rgba(0,0,0,.05);border-left:5px solid #1976d2;display:flex;flex-direction:column;gap:6px;transition:transform .2s ease,box-shadow .2s ease;cursor:pointer;text-decoration:none}
.kpi-card:hover{transform:translateY(-3px);box-shadow:0 6px 18px rgba(0,0,0,.12)}
.kpi-card.pending{border-left-color:#f9a825}.kpi-card.resolved{border-left-color:#43a047}.kpi-card.rate{border-left-color:#7b1fa2}.kpi-card.avg{border-left-color:#00838f}
.kpi-icon{font-size:22px;color:#1976d2}
.kpi-card.pending .kpi-icon{color:#f9a825}.kpi-card.resolved .kpi-icon{color:#43a047}.kpi-card.rate .kpi-icon{color:#7b1fa2}.kpi-card.avg .kpi-icon{color:#00838f}
.kpi-value{font-size:32px;font-weight:800;color:var(--text-main);line-height:1}
.kpi-label{font-size:13px;color:var(--text-secondary);font-weight:500}
.week-strip{display:flex;gap:16px;flex-wrap:wrap;margin-bottom:24px}
.week-card{flex:1;min-width:180px;background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:18px 20px;box-shadow:0 2px 12px rgba(0,0,0,.05);display:flex;align-items:center;gap:14px;transition:transform .2s ease,box-shadow .2s ease;cursor:pointer;text-decoration:none}
.week-card:hover{transform:translateY(-3px);box-shadow:0 6px 18px rgba(0,0,0,.12)}
.week-card-icon{width:46px;height:46px;border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:18px;flex-shrink:0}
.week-card-value{font-size:26px;font-weight:800;color:var(--text-main);line-height:1}
.week-card-label{font-size:12px;color:var(--text-secondary)}
.charts-grid{display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px}
@media(max-width:768px){.charts-grid{grid-template-columns:1fr}}
.chart-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.05)}
.chart-card.wide{grid-column:1/-1}
.chart-card h3{font-size:15px;color:var(--text-main);margin:0 0 18px;display:flex;align-items:center;gap:8px;font-weight:700}
.chart-canvas-wrap{position:relative;height:260px}
.table-card{background:var(--bg-card);border:1px solid var(--border-color);border-radius:12px;padding:22px;box-shadow:0 2px 12px rgba(0,0,0,.05);margin-bottom:24px;overflow-x:auto}
.table-card h3{font-size:15px;color:var(--text-main);margin:0 0 16px;display:flex;align-items:center;gap:8px;font-weight:700}
.analytics-table{width:100%;border-collapse:collapse;font-size:13px}
.analytics-table th{background:var(--bg-card-subtle);color:var(--text-main);padding:10px 14px;text-align:left;font-weight:700;border-bottom:2px solid var(--border-color)}
.analytics-table td{padding:10px 14px;border-bottom:1px solid var(--border-color);color:var(--text-main);vertical-align:middle}
.analytics-table tr:last-child td{border-bottom:none}
.analytics-table tbody tr{cursor:pointer;transition:background .15s ease}
.analytics-table tbody tr:hover td{background:var(--bg-card-subtle) !important}
.customer-initials{width:34px;height:34px;border-radius:50%;background:#1976d2;color:#fff;font-weight:700;font-size:13px;display:inline-flex;align-items:center;justify-content:center;margin-right:8px;flex-shrink:0}
.customer-avatar-thumb{width:34px;height:34px;border-radius:50%;object-fit:cover;margin-right:8px;flex-shrink:0;border:1px solid var(--border-color)}
.customer-cell{display:flex;align-items:center}
.customer-name-text{font-weight:600;color:var(--text-main);display:block}
.customer-email-text{font-size:11px;color:var(--text-secondary);display:block}
.mini-bar-wrap{width:100%;background:var(--border-color);border-radius:4px;height:8px}
.mini-bar{height:8px;border-radius:4px;background:#1976d2}
.speed-fast{color:#2e7d32;font-weight:600}.speed-medium{color:#e65100;font-weight:600}.speed-slow{color:#c62828;font-weight:600}
.back-link{display:inline-flex;align-items:center;gap:6px;color:var(--primary-color);text-decoration:none;font-weight:600;font-size:14px;margin-bottom:20px}
.back-link:hover{color:var(--primary-dark)}
</style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_admin.php';
?>
<div class="analytics-page">

<a href="admin_dashboard.php" class="back-link"><i class="fa-solid fa-arrow-left"></i> Back to Dashboard</a>

<h2><i class="fa-solid fa-chart-line"></i> Analytics &amp; Insights</h2>
<p class="analytics-subtitle">Live metrics from your support ticketing system &mdash; updated on every page load. Tap any card, chart or table row to view its complaints.</p>

<!-- KPI Cards (Clickable) -->
<div class="kpi-grid">
  <a href="admin_dashboard.php" class="kpi-card" title="View all complaints">
    <span class="kpi-icon"><i class="fa-solid fa-ticket"></i></span>
    <span class="kpi-value"><?php echo $total;?></span>
    <span class="kpi-label">Total Complaints</span>
  </a>
  <a href="admin_dashboard.php?status=Pending" class="kpi-card pending" title="View pending complaints">
    <span class="kpi-icon"><i class="fa-solid fa-hourglass-half"></i></span>
    <span class="kpi-value"><?php echo $pending;?></span>
    <span class="kpi-label">Pending</span>
  </a>
  <a href="admin_dashboard.php?status=In+Progress" class="kpi-card" style="border-left-color:#0284c7;" title="View in-progress complaints">
    <span class="kpi-icon" style="color:#0284c7;"><i class="fa-solid fa-spinner"></i></span>
    <span class="kpi-value"><?php echo $inprogress;?></span>
    <span class="kpi-label">In Progress</span>
  </a>
  <a href="admin_dashboard.php?status=Reopened" class="kpi-card" style="border-left-color:#7c3aed;" title="View reopened complaints">
    <span class="kpi-icon" style="color:#7c3aed;"><i class="fa-solid fa-arrows-rotate"></i></span>
    <span class="kpi-value"><?php echo $reopened;?></span>
    <span class="kpi-label">Reopened</span>
  </a>
  <a href="admin_dashboard.php?status=Resolved" class="kpi-card resolved" title="View resolved complaints">
    <span class="kpi-icon"><i class="fa-solid fa-circle-check"></i></span>
    <span class="kpi-value"><?php echo $resolved;?></span>
    <span class="kpi-label">Resolved</span>
  </a>
  <a href="admin_dashboard.php?status=Resolved" class="kpi-card rate" title="View resolved complaints">
    <span class="kpi-icon"><i class="fa-solid fa-percent"></i></span>
    <span class="kpi-value"><?php echo $resolutionRate;?>%</span>
    <span class="kpi-label">Resolution Rate</span>
  </a>
  <a href="admin_dashboard.php?status=Resolved&sort=resolution_time_desc" class="kpi-card avg" title="View resolved complaints sorted by longest resolution time">
    <span class="kpi-icon"><i class="fa-solid fa-stopwatch"></i></span>
    <span class="kpi-value" style="font-size:22px"><?php echo $avgResolution;?></span>
    <span class="kpi-label">Avg. Resolution Time</span>
  </a>
  <a href="admin_dashboard.php?rated=1" class="kpi-card" style="border-left-color:#f59e0b;" title="View all rated complaints">
    <span class="kpi-icon" style="color:#f59e0b;"><i class="fa-solid fa-star"></i></span>
    <span class="kpi-value" style="font-size:22px;color:#92400e;"><?php echo $avgRating !== null ? $avgRating . ' <small style="font-size:14px;color:#6b7280;">/5</small>' : 'N/A';?></span>
    <span class="kpi-label">CSAT Rating (<?php echo $ratedCount;?> rated)</span>
  </a>
</div>

<!-- This Week (Monday to Sunday) -->
<div class="week-strip">
  <a href="admin_dashboard.php?period=this_week&type=new" class="week-card" style="border-left:4px solid #1976d2" title="View tickets created this week">
    <div class="week-card-icon" style="background:#e3f2fd;color:#1976d2"><i class="fa-solid fa-inbox"></i></div>
    <div><div class="week-card-value"><?php echo $weekNew;?></div><div class="week-card-label">New tickets this week</div></div>
  </a>
  <a href="admin_dashboard.php?period=this_week&type=resolved" class="week-card" style="border-left:4px solid #43a047" title="View tickets resolved this week">
    <div class="week-card-icon" style="background:#e8f5e9;color:#43a047"><i class="fa-solid fa-check-double"></i></div>
    <div><div class="week-card-value"><?php echo $weekResolved;?></div><div class="week-card-label">Resolved this week</div></div>
  </a>
  <a href="admin_dashboard.php?period=this_week&type=still_pending" class="week-card" style="border-left:4px solid #f9a825" title="View tickets created this week that are still unresolved">
    <div class="week-card-icon" style="background:#fff8e1;color:#f9a825"><i class="fa-solid fa-triangle-exclamation"></i></div>
    <div><div class="week-card-value"><?php echo $weekStillPending;?></div><div class="week-card-label">Still pending from this week</div></div>
  </a>
</div>

<!-- Charts (Interactive) -->
<div class="charts-grid">
  <div class="chart-card">
    <h3><i class="fa-solid fa-tags"></i> Tickets by Category <small style="font-size:11px;color:#64748b;font-weight:normal;margin-left:auto;">Click slice to filter</small></h3>
    <div class="chart-canvas-wrap"><canvas id="categoryChart"></canvas></div>
  </div>
  <div class="chart-card">
    <h3><i class="fa-solid fa-flag"></i> Tickets by Priority <small style="font-size:11px;color:#64748b;font-weight:normal;margin-left:auto;">Click bar to filter</small></h3>
    <div class="chart-canvas-wrap"><canvas id="priorityChart"></canvas></div>
  </div>
  <div class="chart-card wide">
    <h3><i class="fa-solid fa-chart-area"></i> Daily Submission Trend &mdash; Last 30 Days <small style="font-size:11px;color:#64748b;font-weight:normal;margin-left:auto;">Click point to view date</small></h3>
    <div class="chart-canvas-wrap" style="height:220px"><canvas id="trendChart"></canvas></div>
  </div>
</div>

<!-- Top 5 Customers -->
<div class="table-card">
  <h3><i class="fa-solid fa-users"></i> Top 5 Most Active Customers <small style="font-size:11px;color:#64748b;font-weight:normal;margin-left:auto;">Click row to view customer profile</small></h3>
  <?php if(!empty($topCustomers)): ?>
  <table class="analytics-table">
    <thead><tr><th>#</th><th>Customer</th><th>Total</th><th>Pending</th><th>In Progress</th><th>Resolved</th><th>Re-opened</th><th>Volume</th></tr></thead>
    <tbody>
    <?php $maxT=(int)($topCustomers[0]["total"]??1); foreach($topCustomers as $i=>$c): 
      $init=strtoupper(substr($c["fullname"],0,1)); 
      $bar=$maxT>0?round(($c["total"]/$maxT)*100):0;
      $hasAvatar = !empty($c['profile_picture']) && file_exists(__DIR__ . '/uploads/' . $c['profile_picture']);
    ?>
    <tr onclick="window.location.href='admin_customer_profile.php?id=<?php echo (int)$c['user_id']; ?>';" title="View profile for <?php echo htmlspecialchars($c['fullname']); ?>">
      <td style="color:#999;font-weight:700"><?php echo $i+1;?></td>
      <td>
        <div class="customer-cell">
          <?php if ($hasAvatar): ?>
            <img src="uploads/<?php echo htmlspecialchars($c['profile_picture']); ?>" class="customer-avatar-thumb" alt="Avatar">
          <?php else: ?>
            <span class="customer-initials"><?php echo htmlspecialchars($init);?></span>
          <?php endif; ?>
          <div>
            <span class="customer-name-text"><?php echo htmlspecialchars($c["fullname"]);?></span>
            <span class="customer-email-text"><?php echo htmlspecialchars($c["email"]);?></span>
          </div>
        </div>
      </td>
      <td><strong><?php echo $c["total"];?></strong></td>
      <td><span class="priority-badge priority-high" style="font-size:11px;padding:2px 8px"><?php echo $c["pending"];?></span></td>
      <td><span class="priority-badge" style="font-size:11px;padding:2px 8px;background:#e3f2fd;color:#1565c0"><?php echo $c["in_progress"];?></span></td>
      <td><span class="priority-badge priority-medium" style="font-size:11px;padding:2px 8px"><?php echo $c["resolved"];?></span></td>
      <td><span class="priority-badge" style="font-size:11px;padding:2px 8px;background:#ede9fe;color:#6d28d9"><?php echo $c["reopened"];?></span></td>
      <td style="min-width:100px"><div class="mini-bar-wrap"><div class="mini-bar" style="width:<?php echo $bar;?>%"></div></div></td>
    </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  <div style="text-align: right; margin-top: 14px;">
    <a href="admin_customers.php" class="back-link" style="margin: 0; font-size: 13px;">
      <i class="fa-solid fa-users"></i> View all customers &rarr;
    </a>
  </div>
  <?php else: ?><p style="color:#999;text-align:center;padding:20px 0">No customer data available yet.</p><?php endif; ?>
</div>

<!-- Recently Resolved -->
<div class="table-card">
  <h3><i class="fa-solid fa-circle-check"></i> Recently Resolved Tickets <small style="font-size:11px;color:#64748b;font-weight:normal;margin-left:auto;">Click row to open ticket</small></h3>
  <?php if($recentResolved&&$recentResolved->num_rows>0): ?>
  <table class="analytics-table">
    <thead><tr><th>Ref</th><th>Customer</th><th>Subject</th><th>Category</th><th>Priority</th><th>Resolved In</th></tr></thead>
    <tbody>
    <?php while($r=$recentResolved->fetch_assoc()):
      $hrs=(int)$r["hrs"];
      if($hrs<=4){$sc="speed-fast";$sl=$hrs."h";}
      elseif($hrs<=24){$sc="speed-medium";$sl=$hrs."h";}
      else{$sc="speed-slow";$sl=round($hrs/24,1)."d";}
      $ci="fa-circle-info";
      if($r["category"]==="Technical Support")$ci="fa-laptop-code";
      elseif($r["category"]==="Billing & Payments")$ci="fa-credit-card";
      elseif($r["category"]==="Account & Security")$ci="fa-shield-halved";
      elseif($r["category"]==="Customer Service")$ci="fa-headset";
      $pc="priority-medium";
      if($r["priority"]==="Urgent")$pc="priority-urgent";
      elseif($r["priority"]==="High")$pc="priority-high";
      elseif($r["priority"]==="Low")$pc="priority-low";
    ?>
    <tr onclick="window.location.href='admin_dashboard.php?ticket_id=<?php echo $r['id']; ?>#ticket-<?php echo $r['id']; ?>';" title="Open ticket #TKT-<?php echo str_pad($r['id'],4,'0',STR_PAD_LEFT);?>">
      <td style="color:#999;font-size:12px">#TKT-<?php echo str_pad($r["id"],4,"0",STR_PAD_LEFT);?></td>
      <td><?php echo htmlspecialchars($r["fullname"]);?></td>
      <td><?php echo htmlspecialchars($r["subject"]);?></td>
      <td><span class="category-tag" style="font-size:11px"><i class="fa-solid <?php echo $ci;?>"></i> <?php echo htmlspecialchars($r["category"]);?></span></td>
      <td><span class="priority-badge <?php echo $pc;?>" style="font-size:11px;padding:2px 8px"><?php echo htmlspecialchars($r["priority"]);?></span></td>
      <td class="<?php echo $sc;?>"><i class="fa-solid fa-stopwatch"></i> <?php echo $sl;?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <?php else: ?><p style="color:#999;text-align:center;padding:20px 0">No resolved tickets yet.</p><?php endif; ?>
</div>

<!-- Customer Satisfaction & Feedback Stream -->
<div class="table-card">
  <h3><i class="fa-solid fa-star" style="color:#f59e0b;"></i> Customer Satisfaction &amp; Feedback Stream <small style="font-size:11px;color:#64748b;font-weight:normal;margin-left:auto;">Click row to view rated ticket</small></h3>
  <?php if($feedbackResult && $feedbackResult->num_rows > 0): ?>
  <table class="analytics-table">
    <thead><tr><th>Ref</th><th>Customer</th><th>Rating</th><th>Feedback Comment</th><th>Date</th></tr></thead>
    <tbody>
    <?php while($fb = $feedbackResult->fetch_assoc()): 
      $fbStars = (int)$fb['rating'];
      $fbStarsHtml = str_repeat('<i class="fa-solid fa-star star-filled" style="color:#f59e0b;"></i> ', $fbStars) . str_repeat('<i class="fa-regular fa-star star-empty" style="color:#94a3b8;"></i> ', 5 - $fbStars);
    ?>
    <tr onclick="window.location.href='admin_dashboard.php?ticket_id=<?php echo $fb['id']; ?>&highlight=csat#ticket-<?php echo $fb['id']; ?>';" title="Open rated ticket #TKT-<?php echo str_pad($fb['id'],4,'0',STR_PAD_LEFT);?>">
      <td style="color:var(--text-muted);font-size:12px">#TKT-<?php echo str_pad($fb["id"],4,"0",STR_PAD_LEFT);?></td>
      <td><strong><?php echo htmlspecialchars($fb["fullname"]);?></strong></td>
      <td><span class="csat-stars-gold" style="font-size:12px;"><?php echo $fbStarsHtml;?></span> (<?php echo $fbStars;?>/5)</td>
      <td style="color:var(--text-secondary); font-style:italic;"><?php echo !empty($fb["feedback"]) ? '&ldquo;' . htmlspecialchars($fb["feedback"]) . '&rdquo;' : '<span style="color:var(--text-muted);">No comment left</span>'; ?></td>
      <td style="color:var(--text-muted);font-size:11px"><?php 
        if (!empty($fb["rating_updated_at"])) {
          echo date("d M Y • h:i A", strtotime($fb["rating_updated_at"])) . " <span class='badge-updated'>Updated</span>";
        } elseif (!empty($fb["rated_at"])) {
          echo date("d M Y • h:i A", strtotime($fb["rated_at"]));
        } else {
          echo "-";
        }
      ?></td>
    </tr>
    <?php endwhile; ?>
    </tbody>
  </table>
  <?php else: ?><p style="color:#999;text-align:center;padding:20px 0">No customer satisfaction ratings submitted yet.</p><?php endif; ?>
</div>

<script>
const catColors = <?php echo json_encode(array_map(fn($l)=>["Technical Support"=>"#1976d2","Billing & Payments"=>"#f9a825","Account & Security"=>"#e53935","General Inquiry"=>"#43a047","Customer Service"=>"#0891b2"][$l]??"#90a4ae",$catLabels??[]));?>;
const trendDatesRaw = <?php echo json_encode($trendDatesRaw); ?>;

// 1. Category Doughnut Chart (Interactive)
new Chart(document.getElementById("categoryChart"),{
  type:"doughnut",
  data:{
    labels:<?php echo json_encode($catLabels);?>,
    datasets:[{data:<?php echo json_encode($catData);?>,backgroundColor:catColors,borderWidth:3,borderColor:"#ffffff",hoverOffset:8}]
  },
  options:{
    cutout:"62%",
    responsive:true,
    maintainAspectRatio:false,
    plugins:{
      legend:{position:"bottom",labels:{padding:14,font:{size:12}}},
      tooltip:{backgroundColor:"#1a1a2e",padding:10,cornerRadius:8}
    },
    onClick:(evt, elements, chart) => {
      if (elements && elements.length > 0) {
        const index = elements[0].index;
        const categoryName = chart.data.labels[index];
        window.location.href = 'admin_dashboard.php?category=' + encodeURIComponent(categoryName);
      }
    },
    onHover:(evt, elements) => {
      evt.native.target.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
    }
  }
});

// 2. Priority Bar Chart (Interactive)
new Chart(document.getElementById("priorityChart"),{
  type:"bar",
  data:{
    labels:<?php echo json_encode($priLabels);?>,
    datasets:[{label:"Tickets",data:<?php echo json_encode($priData);?>,backgroundColor:<?php echo json_encode($priColors);?>,borderRadius:6,borderSkipped:false}]
  },
  options:{
    responsive:true,
    maintainAspectRatio:false,
    plugins:{
      legend:{display:false},
      tooltip:{backgroundColor:"#1a1a2e",padding:10,cornerRadius:8}
    },
    scales:{
      y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:"#f0f0f0"}},
      x:{ticks:{font:{size:11}},grid:{display:false}}
    },
    onClick:(evt, elements, chart) => {
      if (elements && elements.length > 0) {
        const index = elements[0].index;
        const priorityName = chart.data.labels[index];
        window.location.href = 'admin_dashboard.php?priority=' + encodeURIComponent(priorityName);
      }
    },
    onHover:(evt, elements) => {
      evt.native.target.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
    }
  }
});

// 3. Daily Submission Trend Chart (Interactive)
new Chart(document.getElementById("trendChart"),{
  type:"line",
  data:{
    labels:<?php echo json_encode($trendLabels);?>,
    datasets:[{label:"Tickets Submitted",data:<?php echo json_encode($trendData);?>,borderColor:"#1976d2",backgroundColor:"rgba(25,118,210,0.08)",borderWidth:2.5,pointRadius:4,pointBackgroundColor:"#1976d2",pointHoverRadius:6,fill:true,tension:0.38}]
  },
  options:{
    responsive:true,
    maintainAspectRatio:false,
    plugins:{
      legend:{display:false},
      tooltip:{backgroundColor:"#1a1a2e",padding:10,cornerRadius:8}
    },
    scales:{
      y:{beginAtZero:true,ticks:{stepSize:1,font:{size:11}},grid:{color:"#f0f0f0"}},
      x:{ticks:{font:{size:10},maxTicksLimit:10,maxRotation:0},grid:{display:false}}
    },
    onClick:(evt, elements, chart) => {
      if (elements && elements.length > 0) {
        const index = elements[0].index;
        const rawDate = trendDatesRaw[index];
        if (rawDate) {
          window.location.href = 'admin_dashboard.php?date=' + encodeURIComponent(rawDate);
        }
      }
    },
    onHover:(evt, elements) => {
      evt.native.target.style.cursor = (elements && elements.length) ? 'pointer' : 'default';
    }
  }
});

function applyChartTheme() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  const gridColor = isDark ? '#27354A' : '#f0f0f0';
  const tickColor = isDark ? '#94A3B8' : '#666';
  const tooltipBg = isDark ? '#1C273B' : '#1a1a2e';
  
  for (let id in Chart.instances) {
      let instance = Chart.instances[id];
      if (instance.options.scales) {
          if (instance.options.scales.x) {
              if(!instance.options.scales.x.grid) instance.options.scales.x.grid = {};
              instance.options.scales.x.grid.color = gridColor;
              if(!instance.options.scales.x.ticks) instance.options.scales.x.ticks = {};
              instance.options.scales.x.ticks.color = tickColor;
          }
          if (instance.options.scales.y) {
              if(!instance.options.scales.y.grid) instance.options.scales.y.grid = {};
              instance.options.scales.y.grid.color = gridColor;
              if(!instance.options.scales.y.ticks) instance.options.scales.y.ticks = {};
              instance.options.scales.y.ticks.color = tickColor;
          }
      }
      if (instance.options.plugins) {
          if (instance.options.plugins.tooltip) {
              instance.options.plugins.tooltip.backgroundColor = tooltipBg;
          }
          if (instance.options.plugins.legend && instance.options.plugins.legend.labels) {
              instance.options.plugins.legend.labels.color = tickColor;
          }
      }
      if (instance.config.type === 'doughnut') {
          instance.data.datasets.forEach(ds => {
              ds.borderColor = isDark ? '#151D2C' : '#ffffff';
          });
      }
      instance.update();
  }
}

setTimeout(applyChartTheme, 100);
window.addEventListener('themeChanged', applyChartTheme);
</script>
</div><!-- /.analytics-page -->
<?php require_once __DIR__ . '/includes/footer.php'; ?>
