<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$unseenTicketsCount = getUnseenTicketsCount($conn, $_SESSION['user_id']);
$unseenNavBadge = formatBadgeCount($unseenTicketsCount);
$pageTitle = 'Submit Ticket';
$activeNav = 'submit';
$navUnseenBadge = $unseenNavBadge;
ob_start();
?>
    <style>

        .nav-links a{
            text-decoration:none;
        }

        .nav-links a:hover{
            opacity:.8;
            text-decoration:none;
        }

        .page-summary{
            color: var(--text-secondary);
            text-align:center;
            margin:15px auto 30px;
            max-width:650px;
            line-height:1.7;
        }

        label{
            display:block;
            margin:18px 0 8px;
            font-weight:600;
            color: var(--text-main);
        }

        .success-message{
            background:#e8f5e9;
            color:#2e7d32;
            border:1px solid #81c784;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
            text-align:center;
        }

        .error-message{
            background:#ffebee;
            color:#c62828;
            border:1px solid #ef9a9a;
            padding:15px;
            border-radius:10px;
            margin-bottom:20px;
            text-align:center;
        }

        .after-success{
            display:flex;
            justify-content:center;
            gap:15px;
            margin-bottom:25px;
            flex-wrap:wrap;
        }

        .action-btn{
            display:inline-block;
            padding:10px 18px;
            background:#1976d2;
            color:#fff;
            text-decoration:none;
            border-radius:6px;
            transition:.25s;
        }

        .action-btn:hover{
            background:#0d47a1;
        }

        .action-btn.secondary{
            background:#43a047;
        }

        .action-btn.secondary:hover{
            background:#2e7d32;
        }

        .success-message i, .error-message i{
            margin-right:8px;
            font-size:18px;
        }
    </style>
<?php
$extraHead = ob_get_clean();
require_once __DIR__ . '/includes/header_customer.php';
?>
<div class="submit-page-wrapper">

    <!-- Section 1: Header Panel -->
    <section class="page-header-card">
        <div class="page-header-title-group">
            <h2>
                <i class="fa-solid fa-paper-plane"></i>
                Submit a New Complaint
            </h2>
            <p class="page-summary" style="margin: 0; text-align: left;">
                Please provide a clear subject and describe your issue in as much detail as possible.
                Our support team will review your complaint and respond promptly.
            </p>
        </div>
        <div class="page-header-actions">
            <a href="view_tickets.php" class="btn btn-outline" style="display: inline-flex; align-items: center; gap: 8px;">
                <i class="fa-solid fa-clipboard-list"></i> View My Complaints
            </a>
        </div>
    </section>

    <!-- Section 2: Form Panel -->
    <section class="section-panel submit-form-card" style="background: var(--bg-card); border: 1px solid var(--border-color); border-radius: var(--radius-lg); padding: 36px 40px; box-shadow: var(--shadow-sm); max-width: 900px; margin: 0 auto; width: 100%;">
        <?php if (isset($_GET['error'])): ?>
            <div class="error-message">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (!isset($_GET['success'])): ?>
            <form action="submit_ticket_process.php" method="POST" enctype="multipart/form-data">

                <label for="subject">Complaint Subject</label>

                <input
                    type="text"
                    id="subject"
                    name="subject"
                    placeholder="Enter complaint subject"
                    required
                >

                <div class="form-row">
                    <div class="form-group">
                        <label for="category">
                            <i class="fa-solid fa-tags"></i> Category
                        </label>
                        <select id="category" name="category" required>
                            <option value="General Inquiry" selected>ℹ️ General Inquiry</option>
                            <option value="Technical Support">💻 Technical Support</option>
                            <option value="Billing & Payments">💳 Billing & Payments</option>
                            <option value="Customer Service">🎧 Customer Service</option>
                            <option value="Account & Security">🔒 Account & Security</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label for="priority">
                            <i class="fa-solid fa-flag"></i> Priority Level
                        </label>
                        <select id="priority" name="priority" required>
                            <option value="Low">🟢 Low (Minor issue)</option>
                            <option value="Medium" selected>🟡 Medium (Standard)</option>
                            <option value="High">🟠 High (Important)</option>
                            <option value="Urgent">🔴 Urgent (Critical)</option>
                        </select>
                    </div>
                </div>

                <label for="message">Complaint Details</label>

                <textarea
                    id="message"
                    name="message"
                    placeholder="Describe your issue in detail..."
                    required
                ></textarea>

                <div class="file-upload-container">
                    <label for="attachment" class="attach-btn" id="attachBtn">
                        <i class="fa-solid fa-paperclip"></i>
                        <span>Attach Screenshot / Image</span>
                    </label>
                    <input
                        type="file"
                        id="attachment"
                        name="attachment"
                        accept=".jpg,.jpeg,.png,image/jpeg,image/png"
                        style="display:none;"
                        onchange="previewUploadImage(this, 'ticketPreviewCard', 'ticketPreviewImg', 'ticketPreviewName', 'ticketPreviewSize', 'attachBtn')"
                    >
                    <div id="ticketPreviewCard" class="upload-preview-card" style="display:none; align-items:center; gap:12px; max-width:100%; box-sizing:border-box; overflow:hidden;">
                        <img id="ticketPreviewImg" class="upload-preview-thumb" src="" alt="Selected image" title="Click to cross-check full image" onclick="openImageModal(this.src)" style="width:60px; height:60px; min-width:60px; max-width:60px; min-height:60px; max-height:60px; object-fit:cover; border-radius:6px; border:1px solid #d0d7de; display:block; flex-shrink:0; cursor:pointer; transition:transform 0.2s ease;">
                        <div class="upload-preview-info" style="display:flex; flex-direction:column; overflow:hidden; max-width:220px;">
                            <span id="ticketPreviewName" class="upload-preview-name" title="Click to cross-check full image" onclick="openImageModal(document.getElementById('ticketPreviewImg').src)" style="font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; font-size:13px; color:#1976d2; cursor:pointer;"></span>
                            <span id="ticketPreviewSize" class="upload-preview-size" style="font-size:11px; color:#777;"></span>
                        </div>
                        <button type="button" class="upload-preview-remove" title="Remove attachment" onclick="removeUploadImage('attachment', 'ticketPreviewCard', 'attachBtn')" style="outline:none;">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <p class="file-input-help">
                        <i class="fa-solid fa-circle-info"></i>
                        Allowed formats: JPG, JPEG, PNG (Max: 5MB)
                    </p>
                </div>

                <button type="submit">
                    <i class="fa-solid fa-paper-plane"></i>
                    Submit Complaint
                </button>

            </form>

        <?php else: ?>

            <div class="success-message">
                <i class="fa-solid fa-circle-check"></i>
                <?php echo htmlspecialchars($_GET['success']); ?>
            </div>

            <div class="after-success">

                <a href="submit_ticket.php" class="action-btn">
                    <i class="fa-solid fa-plus"></i>
                    Submit Another Complaint
                </a>

                <a href="view_tickets.php" class="action-btn secondary">
                    <i class="fa-solid fa-clipboard-list"></i>
                    View My Complaints
                </a>

            </div>

        <?php endif; ?>
    </section>

    <div class="link portal-bottom-link" style="text-align: center; margin-top: 16px; margin-bottom: 24px;">
        <a href="dashboard.php" class="btn btn-ghost" style="display: inline-flex; align-items: center; gap: 8px; text-decoration: none; font-weight: 600;">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Dashboard
        </a>
    </div>

</div> <!-- /.submit-page-wrapper -->

<script>
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

function openImageModal(src) {
    if (!src) return;
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
</script>

<div id="imageModal" class="image-modal" onclick="closeImageModal(event)">
    <button class="image-modal-close" onclick="closeImageModal(event)">&times;</button>
    <img id="modalImage" class="image-modal-content" src="" alt="Full preview">
</div>

<script>
// Fire-and-forget background email queue processor
(function() {
    if (window.fetch) {
        fetch('process_email_queue.php', { method: 'POST', keepalive: true }).catch(function() {});
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>