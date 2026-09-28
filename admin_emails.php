<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

// Mark all unread email logs as read when viewing the outbox
$conn->query("UPDATE email_logs SET is_read = 1 WHERE is_read = 0");

$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Count total emails
$countRes = $conn->query("SELECT COUNT(*) AS total FROM email_logs");
$totalEmails = (int)($countRes->fetch_assoc()['total'] ?? 0);
$totalPages = max(1, (int)ceil($totalEmails / $limit));

// Fetch paginated emails
$stmt = $conn->prepare("SELECT id, recipient, subject, body, sent_status, created_at FROM email_logs ORDER BY created_at DESC LIMIT ? OFFSET ?");
$stmt->bind_param("ii", $limit, $offset);
$stmt->execute();
$emails = $stmt->get_result();
$pageTitle = 'Email Outbox Logs';
$activeNav = 'emails';
$navUnreadBadge = '';
ob_start();
?>
    <style>
        .outbox-page {
            width: 100%;
            max-width: 1600px;
            margin: 0 auto;
        }

        .outbox-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .outbox-header h2 {
            margin: 0;
            color: var(--primary-color);
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 22px;
        }

        .outbox-desc {
            color: var(--text-muted);
            font-size: 14px;
            margin-top: 4px;
        }

        .back-btn {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 9px 16px;
            background: var(--bg-card);
            color: var(--primary-color);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .back-btn:hover {
            background: var(--bg-card-subtle);
            color: var(--primary-hover);
        }

        .emails-card {
            background: var(--bg-card);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            box-shadow: 0 4px 14px rgba(0, 0, 0, 0.05);
            padding: 24px;
            overflow-x: auto;
        }

        .emails-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 13px;
        }

        .emails-table th {
            background: var(--bg-card-subtle);
            color: var(--text-secondary);
            padding: 12px 14px;
            text-align: left;
            font-weight: 700;
            border-bottom: 2px solid var(--border-color);
        }

        .emails-table td {
            padding: 12px 14px;
            border-bottom: 1px solid var(--border-subtle);
            color: var(--text-secondary);
            vertical-align: middle;
        }

        .emails-table tr:hover td {
            background: var(--bg-card-subtle);
        }

        .status-pill {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 3px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
        }

        .status-delivered {
            background: #dcfce7;
            color: #166534;
        }

        .status-logged {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-pending {
            background: #fef9c3;
            color: #854d0e;
        }

        .status-processing {
            background: #e0f2fe;
            color: #0369a1;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        .preview-btn {
            background: var(--primary-color);
            color: var(--text-inverse);
            border: 1px solid var(--border-color);
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 5px;
            transition: 0.2s;
        }

        .preview-btn:hover {
            background: var(--primary-hover);
            color: var(--text-inverse);
        }

        [data-theme="dark"] .preview-btn {
            background: #FAFAFA !important;
            color: #09090B !important;
            border-color: #FAFAFA !important;
        }

        [data-theme="dark"] .preview-btn:hover {
            background: #E4E4E7 !important;
            color: #000000 !important;
        }

        /* Modal Preview */
        .email-modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.7);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
            box-sizing: border-box;
        }

        .email-modal.active {
            display: flex;
        }

        .email-modal-box {
            background: var(--bg-card);
            color: var(--text-main);
            width: 100%;
            max-width: 680px;
            max-height: 90vh;
            border-radius: 12px;
            border: 1px solid var(--border-color);
            overflow: hidden;
            display: flex;
            flex-direction: column;
            box-shadow: 0 20px 40px rgba(0,0,0,0.25);
        }

        .email-modal-header {
            padding: 16px 20px;
            background: var(--bg-card-subtle);
            color: var(--text-main);
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .email-modal-header h3 {
            margin: 0;
            font-size: 16px;
            font-weight: 700;
        }

        .email-modal-close {
            background: transparent;
            border: none;
            color: var(--text-muted);
            font-size: 24px;
            cursor: pointer;
            line-height: 1;
            transition: 0.2s;
        }

        .email-modal-close:hover {
            color: var(--text-main);
        }

        .email-modal-body {
            padding: 20px;
            overflow-y: auto;
            flex: 1;
            background: var(--bg-page);
        }

        .email-modal-frame {
            width: 100%;
            height: 480px;
            border: none;
            border-radius: 8px;
            background: #ffffff;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_admin.php';
?>
        <div class="outbox-page">

            <div class="outbox-header">
                <div>
                    <h2><i class="fa-solid fa-envelopes-bulk"></i> Outbound Email Logs</h2>
                    <p class="outbox-desc">Audit history of all transactional emails dispatched to customers (password resets, notifications, replies).</p>
                </div>

                <a href="admin_dashboard.php" class="back-btn">
                    <i class="fa-solid fa-arrow-left"></i> Back to Dashboard
                </a>
            </div>

            <div class="emails-card">
                <?php if ($emails && $emails->num_rows > 0): ?>
                    <table class="emails-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Sent Date</th>
                                <th>Recipient</th>
                                <th>Subject</th>
                                <th>Status</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php while ($em = $emails->fetch_assoc()): ?>
                                <tr>
                                    <td style="color:#888; font-weight:700;">#<?php echo $em['id']; ?></td>
                                    <td style="font-size:12px; color:#64748b;"><?php echo date("d M Y, h:i A", strtotime($em['created_at'])); ?></td>
                                    <td><strong><?php echo htmlspecialchars($em['recipient']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($em['subject']); ?></td>
                                    <td>
                                        <?php 
                                            $st = $em['sent_status'];
                                            if ($st === 'Sent' || $st === 'Delivered'): ?>
                                                <span class="status-pill status-delivered"><i class="fa-solid fa-check"></i> <?php echo htmlspecialchars($st); ?></span>
                                            <?php elseif ($st === 'Pending'): ?>
                                                <span class="status-pill status-pending"><i class="fa-solid fa-clock"></i> Queued</span>
                                            <?php elseif ($st === 'Processing'): ?>
                                                <span class="status-pill status-processing"><i class="fa-solid fa-spinner fa-spin"></i> Sending...</span>
                                            <?php elseif (str_starts_with($st, 'Failed')): ?>
                                                <span class="status-pill status-failed" title="<?php echo htmlspecialchars($st); ?>"><i class="fa-solid fa-triangle-exclamation"></i> Failed</span>
                                            <?php else: ?>
                                                <span class="status-pill status-logged"><i class="fa-solid fa-file-lines"></i> <?php echo htmlspecialchars($st); ?></span>
                                            <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="preview-btn" onclick="openEmailPreview(<?php echo $em['id']; ?>)">
                                            <i class="fa-solid fa-eye"></i> View Email
                                        </button>
                                        <template id="email-body-<?php echo $em['id']; ?>"><?php echo htmlspecialchars($em['body']); ?></template>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <?php if ($totalPages > 1): ?>
                        <div class="pagination-container">
                            <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                                <a href="?page=<?php echo $p; ?>" class="page-btn <?php echo ($p === $page) ? 'active' : ''; ?>">
                                    <?php echo $p; ?>
                                </a>
                            <?php endfor; ?>
                        </div>
                    <?php endif; ?>

                <?php else: ?>
                    <p style="text-align: center; color: #888; padding: 40px 0;">No outbound emails logged yet.</p>
                <?php endif; ?>
            </div>

        </div>


    <!-- Email Modal Preview -->
    <div id="emailModal" class="email-modal" onclick="closeEmailModal(event)">
        <div class="email-modal-box">
            <div class="email-modal-header">
                <h3><i class="fa-solid fa-envelope"></i> Rendered Email Preview</h3>
                <button class="email-modal-close" onclick="closeEmailModal(event)">&times;</button>
            </div>
            <div class="email-modal-body">
                <iframe id="emailFrame" class="email-modal-frame"></iframe>
            </div>
        </div>
    </div>

    <script>
        function openEmailPreview(id) {
            const tmpl = document.getElementById('email-body-' + id);
            if (!tmpl) return;
            const htmlContent = tmpl.innerHTML;
            // Decode HTML entities
            const txt = document.createElement("textarea");
            txt.innerHTML = htmlContent;
            const decoded = txt.value;

            const modal = document.getElementById('emailModal');
            const frame = document.getElementById('emailFrame');
            modal.classList.add('active');
            frame.srcdoc = decoded;
            document.body.style.overflow = 'hidden';
        }

        function closeEmailModal(e) {
            if (e.target.id === 'emailModal' || e.target.classList.contains('email-modal-close')) {
                document.getElementById('emailModal').classList.remove('active');
                document.body.style.overflow = 'auto';
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
