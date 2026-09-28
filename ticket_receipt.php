<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'connect.php';

// 1. Security & Authentication: Must be logged in as customer or admin
$isCustomer = isset($_SESSION['user_id']);
$isAdmin = isset($_SESSION['admin']);

if (!$isCustomer && !$isAdmin) {
    header("Location: login.php?error=" . urlencode("Please log in to view ticket receipts."));
    exit();
}

$ticket_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($ticket_id <= 0) {
    if ($isAdmin) {
        header("Location: admin_dashboard.php?error=" . urlencode("Invalid ticket ID provided."));
    } else {
        header("Location: view_tickets.php?error=" . urlencode("Invalid ticket ID provided."));
    }
    exit();
}

// 2. Fetch Data: Join tickets with users table
if ($isAdmin) {
    $stmt = $conn->prepare("
        SELECT tickets.*, users.fullname, users.email 
        FROM tickets 
        LEFT JOIN users ON tickets.user_id = users.id 
        WHERE tickets.id = ?
    ");
    $stmt->bind_param("i", $ticket_id);
} else {
    $user_id = intval($_SESSION['user_id']);
    $stmt = $conn->prepare("
        SELECT tickets.*, users.fullname, users.email 
        FROM tickets 
        LEFT JOIN users ON tickets.user_id = users.id 
        WHERE tickets.id = ? AND tickets.user_id = ?
    ");
    $stmt->bind_param("ii", $ticket_id, $user_id);
}

$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    if ($isAdmin) {
        header("Location: admin_dashboard.php?error=" . urlencode("Ticket not found."));
    } else {
        header("Location: view_tickets.php?error=" . urlencode("Ticket not found or access denied."));
    }
    exit();
}

$ticket = $result->fetch_assoc();
$stmt->close();

// Variables for display
$trackingId = sprintf("#TKT-%05d", $ticket['id']);
$status = htmlspecialchars($ticket['status'] ?? 'Pending');
$category = htmlspecialchars($ticket['category'] ?? 'General Inquiry');
$priority = htmlspecialchars($ticket['priority'] ?? 'Medium');
$customerName = htmlspecialchars($ticket['fullname'] ?? 'Valued Customer');
$customerEmail = htmlspecialchars($ticket['email'] ?? 'Not provided');
$subject = htmlspecialchars($ticket['subject'] ?? 'No Subject');
$message = nl2br(htmlspecialchars($ticket['message'] ?? ''));
$response = !empty($ticket['response']) ? nl2br(htmlspecialchars($ticket['response'])) : '';

$dateSubmitted = date("d M Y • h:i A", strtotime($ticket['created_at']));
$dateGenerated = date("d M Y • h:i A");
$dateResolved = (!empty($ticket['responded_at'])) ? date("d M Y • h:i A", strtotime($ticket['responded_at'])) : null;

// Category icon selection
$catIcon = 'fa-circle-info';
if ($category === 'Technical Support') $catIcon = 'fa-laptop-code';
elseif ($category === 'Billing & Payments') $catIcon = 'fa-credit-card';
elseif ($category === 'Account & Security') $catIcon = 'fa-shield-halved';
elseif ($category === 'Customer Service') $catIcon = 'fa-headset';

// Priority styling
$priorityClass = 'priority-medium';
$priorityIcon = 'fa-circle';
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

// Back navigation target
$backUrl = $isAdmin ? 'admin_dashboard.php' : 'view_tickets.php';
$backLabel = $isAdmin ? 'Back to Admin Portal' : 'Back to My Tickets';

// Auto-print parameter
$autoPrint = isset($_GET['print']) && $_GET['print'] == '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo defined('SITE_NAME') ? SITE_NAME : 'BethelDesk'; ?> Ticket Receipt - <?php echo $trackingId; ?></title>
    <link rel="icon" type="image/svg+xml" href="favicon.svg">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="css/all.min.css">

    <script>
        (function() {
            try {
                var savedTheme = localStorage.getItem('bethel_theme');
                if (!savedTheme) {
                    savedTheme = (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) ? 'dark' : 'light';
                }
                if (savedTheme === 'dark') {
                    document.documentElement.setAttribute('data-theme', 'dark');
                } else {
                    document.documentElement.setAttribute('data-theme', 'light');
                }
            } catch (e) {}
        })();
    </script>

    <style>
        /* Base Reset */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        }

        body {
            background: #eef2f6;
            color: #1e293b;
            min-height: 100vh;
            padding: 24px 16px 40px;
        }

        /* Non-Printable Top Action Toolbar */
        .toolbar-wrapper {
            max-width: 860px;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-action {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 18px;
            font-size: 14px;
            font-weight: 600;
            border-radius: 8px;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid transparent;
        }

        .btn-back {
            background: #ffffff;
            color: #334155;
            border-color: #cbd5e1;
        }

        .btn-back:hover {
            background: #f8fafc;
            color: #0f172a;
            border-color: #94a3b8;
        }

        .btn-print {
            background: #1976d2;
            color: #ffffff;
            box-shadow: 0 4px 12px rgba(25, 118, 210, 0.25);
        }

        .btn-print:hover {
            background: #0d47a1;
            box-shadow: 0 4px 16px rgba(13, 71, 161, 0.35);
            transform: translateY(-1px);
        }

        .toolbar-hint {
            font-size: 13px;
            color: #64748b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        /* Printable Receipt Container */
        .receipt-container {
            max-width: 860px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.07), 0 1px 3px rgba(0,0,0,0.05);
            border: 1px solid #e2e8f0;
            overflow: hidden;
            position: relative;
        }

        /* Receipt Header Accent */
        .receipt-top-banner {
            height: 6px;
            background: linear-gradient(90deg, #0d47a1, #1976d2, #42a5f5);
        }

        .receipt-body {
            padding: 36px 40px;
        }

        /* Header Section */
        .receipt-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 20px;
            padding-bottom: 24px;
            border-bottom: 2px dashed #e2e8f0;
        }

        .brand-block {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .brand-logo-icon {
            width: 52px;
            height: 52px;
            background: linear-gradient(135deg, #0d47a1, #1976d2);
            color: #ffffff;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            box-shadow: 0 4px 10px rgba(13, 71, 161, 0.25);
        }

        .brand-text h1 {
            font-size: 22px;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.5px;
            line-height: 1.2;
        }

        .brand-text p {
            font-size: 13px;
            color: #64748b;
            font-weight: 500;
            margin-top: 3px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }

        .tracking-block {
            text-align: right;
        }

        .tracking-tag {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.7px;
            color: #64748b;
        }

        .tracking-number {
            font-family: 'Courier New', Courier, monospace;
            font-size: 24px;
            font-weight: 800;
            color: #0d47a1;
            letter-spacing: 1px;
            margin: 2px 0 6px;
        }

        /* Visual Barcode Strip */
        .barcode-strip {
            display: inline-block;
            height: 24px;
            width: 140px;
            background: repeating-linear-gradient(
                90deg,
                #1e293b,
                #1e293b 2px,
                transparent 2px,
                transparent 4px,
                #1e293b 4px,
                #1e293b 7px,
                transparent 7px,
                transparent 9px,
                #1e293b 9px,
                #1e293b 10px,
                transparent 10px,
                transparent 13px
            );
            opacity: 0.85;
            border-radius: 2px;
        }

        /* Timeline & Dates Bar */
        .dates-ribbon {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            padding: 18px 20px;
            background: #f8fafc;
            border-radius: 10px;
            border: 1px solid #f1f5f9;
            margin: 24px 0;
        }

        .date-item {
            display: flex;
            flex-direction: column;
            gap: 3px;
        }

        .date-item .label {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }

        .date-item .val {
            font-size: 13.5px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .date-item .val i {
            color: #1976d2;
            font-size: 13px;
        }

        /* Metadata Grid */
        .meta-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }

        .meta-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .meta-card .meta-label {
            font-size: 11px;
            text-transform: uppercase;
            font-weight: 700;
            letter-spacing: 0.6px;
            color: #64748b;
            margin-bottom: 8px;
        }

        .meta-card .meta-value {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
            word-break: break-word;
        }

        /* Badges */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            width: fit-content;
        }

        .badge-pending {
            background: #fef3c7;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        .badge-inprogress {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-resolved {
            background: #dcfce7;
            color: #15803d;
            border: 1px solid #bbf7d0;
        }

        .badge-reopened {
            background: #ede9fe;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
        }

        .priority-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            font-size: 12px;
            font-weight: 700;
            padding: 3px 8px;
            border-radius: 6px;
            width: fit-content;
        }

        .priority-urgent {
            background: #fee2e2;
            color: #b91c1c;
            border: 1px solid #fecaca;
        }

        .priority-high {
            background: #ffedd5;
            color: #c2410c;
            border: 1px solid #fed7aa;
        }

        .priority-medium {
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
        }

        .priority-low {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .category-val {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            color: #334155;
            font-size: 13px;
        }

        .category-val i {
            color: #1976d2;
        }

        .customer-meta {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }

        .customer-meta .c-name {
            font-size: 14px;
            font-weight: 700;
            color: #0f172a;
        }

        .customer-meta .c-email {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }

        /* Section Containers */
        .receipt-section {
            margin-bottom: 24px;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            background: #ffffff;
            overflow: hidden;
        }

        .section-header {
            padding: 12px 18px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .section-header h3 {
            font-size: 14px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
            text-transform: uppercase;
            letter-spacing: 0.4px;
        }

        .section-header h3 i {
            color: #1976d2;
            font-size: 14px;
        }

        .section-body {
            padding: 20px;
        }

        .ticket-subject-title {
            font-size: 17px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
            line-height: 1.4;
        }

        .ticket-text-box {
            background: #fcfdfe;
            border: 1px solid #edf2f7;
            border-left: 4px solid #1976d2;
            padding: 16px;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.7;
            color: #334155;
            white-space: normal;
            word-wrap: break-word;
        }

        /* Resolution Box Specifics */
        .section-resolution {
            border-color: #bbf7d0;
            background: #fcfffd;
        }

        .section-resolution .section-header {
            background: #f0fdf4;
            border-bottom-color: #dcfce7;
        }

        .section-resolution .section-header h3 i {
            color: #16a34a;
        }

        .resolution-text-box {
            background: #ffffff;
            border: 1px solid #bbf7d0;
            border-left: 4px solid #16a34a;
            padding: 16px;
            border-radius: 6px;
            font-size: 14px;
            line-height: 1.7;
            color: #166534;
            word-wrap: break-word;
        }

        .responder-meta {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 12px;
            font-size: 13px;
            color: #15803d;
            font-weight: 600;
        }

        .responder-meta .agent-tag {
            background: #dcfce7;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* Pending State Notice */
        .section-pending-notice {
            background: #fffbeb;
            border: 1px solid #fef3c7;
            border-radius: 10px;
            padding: 20px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            margin-bottom: 24px;
        }

        .pending-notice-icon {
            font-size: 24px;
            color: #d97706;
            margin-top: 2px;
        }

        .pending-notice-content h4 {
            font-size: 14px;
            font-weight: 700;
            color: #92400e;
            margin-bottom: 4px;
        }

        .pending-notice-content p {
            font-size: 13px;
            color: #b45309;
            line-height: 1.5;
        }

        /* Attachment Display */
        .attachment-card {
            margin-top: 16px;
            padding: 12px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
        }

        .attachment-thumb {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 6px;
            border: 1px solid #cbd5e1;
            cursor: pointer;
            transition: transform 0.2s ease;
        }

        .attachment-thumb:hover {
            transform: scale(1.03);
        }

        .attachment-info {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }

        .attachment-title {
            font-size: 13px;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .attachment-filename {
            font-size: 12px;
            color: #64748b;
            word-break: break-all;
        }

        .attachment-view-link {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 12px;
            color: #1976d2;
            text-decoration: none;
            font-weight: 600;
            margin-top: 4px;
            cursor: pointer;
        }

        .attachment-view-link:hover {
            text-decoration: underline;
        }

        /* Footer & Official Seal */
        .receipt-footer {
            margin-top: 36px;
            padding-top: 24px;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            gap: 20px;
        }

        .footer-disclaimer {
            max-width: 520px;
            font-size: 11px;
            line-height: 1.5;
            color: #64748b;
        }

        .footer-disclaimer strong {
            color: #334155;
        }

        .official-seal {
            text-align: right;
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 4px;
        }

        .seal-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 12px;
            border: 1.5px solid #0d47a1;
            border-radius: 4px;
            color: #0d47a1;
            font-size: 11px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            background: #f0f7ff;
        }

        .seal-id {
            font-size: 10px;
            color: #94a3b8;
            font-family: monospace;
        }

        /* Image Preview Lightbox Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(15, 23, 42, 0.85);
            backdrop-filter: blur(4px);
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .image-modal.active {
            display: flex;
        }

        .image-modal-content {
            max-width: 90%;
            max-height: 88vh;
            border-radius: 8px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
            background: #fff;
        }

        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 25px;
            font-size: 32px;
            color: #ffffff;
            cursor: pointer;
            background: transparent;
            border: none;
            line-height: 1;
        }

        /* Responsive Breakpoints */
        @media (max-width: 768px) {
            .receipt-body {
                padding: 24px 20px;
            }

            .receipt-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .tracking-block {
                text-align: left;
            }

            .meta-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .receipt-footer {
                flex-direction: column;
                align-items: flex-start;
            }

            .official-seal {
                align-items: flex-start;
                text-align: left;
            }
        }

        @media (max-width: 480px) {
            .meta-grid {
                grid-template-columns: 1fr;
            }
        }

        /* PRINT STYLESHEET */
        @media print {
            @page {
                size: A4 portrait;
                margin: 12mm 15mm 12mm 15mm;
            }

            html, body {
                background: #ffffff !important;
                color: #0f172a !important;
                font-size: 12px !important;
                line-height: 1.45 !important;
                margin: 0 !important;
                padding: 0 !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .no-print {
                display: none !important;
            }

            .receipt-container {
                box-shadow: none !important;
                border: 1px solid #cbd5e1 !important;
                border-radius: 0 !important;
                max-width: 100% !important;
                width: 100% !important;
                margin: 0 !important;
                padding: 0 !important;
            }

            .receipt-body {
                padding: 24px 28px !important;
            }

            .receipt-top-banner {
                height: 4px !important;
            }

            .brand-logo-icon {
                box-shadow: none !important;
                -webkit-print-color-adjust: exact !important;
                print-color-adjust: exact !important;
            }

            .dates-ribbon {
                background: #f8fafc !important;
                border: 1px solid #e2e8f0 !important;
                padding: 12px 16px !important;
                margin: 18px 0 !important;
                gap: 10px !important;
            }

            .meta-grid {
                margin-bottom: 20px !important;
                gap: 12px !important;
            }

            .meta-card {
                padding: 10px 12px !important;
                border: 1px solid #e2e8f0 !important;
            }

            .receipt-section {
                margin-bottom: 18px !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                border: 1px solid #cbd5e1 !important;
            }

            .section-header {
                padding: 8px 14px !important;
                background: #f1f5f9 !important;
            }

            .section-body {
                padding: 14px 16px !important;
            }

            .ticket-text-box, .resolution-text-box {
                padding: 12px !important;
                font-size: 12px !important;
                line-height: 1.6 !important;
            }

            .attachment-card {
                break-inside: avoid !important;
                page-break-inside: avoid !important;
                padding: 8px 10px !important;
            }

            .attachment-thumb {
                width: 70px !important;
                height: 70px !important;
            }

            .attachment-view-link {
                display: none !important;
            }

            .receipt-footer {
                margin-top: 24px !important;
                padding-top: 16px !important;
                break-inside: avoid !important;
                page-break-inside: avoid !important;
            }

            .seal-badge {
                border-color: #0d47a1 !important;
                background: #ffffff !important;
                color: #0d47a1 !important;
            }
        }

        /* Screen Dark Theme for Ticket Receipt */
        @media screen {
            [data-theme="dark"] body {
                background: #0B0F19;
                color: #F1F5F9;
            }
            [data-theme="dark"] .btn-back {
                background: #151D2C;
                color: #CBD5E1;
                border-color: #27354A;
            }
            [data-theme="dark"] .btn-back:hover {
                background: #1C273B;
                color: #FFFFFF;
            }
            [data-theme="dark"] .receipt-container {
                background: #151D2C;
                border-color: #27354A;
                color: #F1F5F9;
                box-shadow: 0 10px 30px rgba(0,0,0,0.6);
            }
            [data-theme="dark"] .receipt-header {
                border-bottom-color: #27354A;
            }
            [data-theme="dark"] .brand-text h1 {
                color: #FFFFFF;
            }
            [data-theme="dark"] .brand-text p,
            [data-theme="dark"] .toolbar-hint {
                color: #94A3B8;
            }
            [data-theme="dark"] .tracking-block {
                background: #1C273B;
                border-color: #27354A;
            }
            [data-theme="dark"] .tracking-tag {
                color: #94A3B8;
            }
            [data-theme="dark"] .tracking-number {
                color: #60A5FA;
            }
            [data-theme="dark"] .tracking-id {
                color: #3B82F6;
            }
            [data-theme="dark"] .barcode-strip {
                opacity: 0.4;
            }

            /* Dates Ribbon */
            [data-theme="dark"] .dates-ribbon {
                background: #1C273B;
                border-color: #27354A;
            }
            [data-theme="dark"] .date-item .label {
                color: #94A3B8;
            }
            [data-theme="dark"] .date-item .val {
                color: #E2E8F0;
            }
            [data-theme="dark"] .date-item .val i {
                color: #60A5FA;
            }

            /* Meta Grid */
            [data-theme="dark"] .meta-grid {
                background: transparent;
            }
            [data-theme="dark"] .meta-card {
                background: #1C273B;
                border-color: #27354A;
            }
            [data-theme="dark"] .meta-label {
                color: #94A3B8;
            }
            [data-theme="dark"] .meta-value {
                color: #F1F5F9;
            }
            [data-theme="dark"] .category-val {
                color: #CBD5E1;
            }
            [data-theme="dark"] .category-val i {
                color: #60A5FA;
            }
            [data-theme="dark"] .customer-meta .c-name {
                color: #F1F5F9;
            }
            [data-theme="dark"] .customer-meta .c-email {
                color: #94A3B8;
            }

            /* Section Headers & Bodies */
            [data-theme="dark"] .receipt-section {
                border-color: #27354A;
                background: #151D2C;
            }
            [data-theme="dark"] .section-header {
                background: #1C273B;
                border-bottom-color: #27354A;
            }
            [data-theme="dark"] .section-header h3 {
                color: #F1F5F9;
            }
            [data-theme="dark"] .section-header h3 i {
                color: #60A5FA;
            }
            [data-theme="dark"] .section-title {
                color: #FFFFFF;
                border-bottom-color: #27354A;
            }

            /* Inquiry / Complaint Box */
            [data-theme="dark"] .ticket-subject-title {
                color: #F1F5F9;
            }
            [data-theme="dark"] .ticket-text-box {
                background: #1C273B;
                border-color: #27354A;
                border-left-color: #3B82F6;
                color: #CBD5E1;
            }

            /* Resolution Section (green-tinted) */
            [data-theme="dark"] .section-resolution {
                border-color: rgba(16, 185, 129, 0.3);
                background: #151D2C;
            }
            [data-theme="dark"] .section-resolution .section-header {
                background: rgba(16, 185, 129, 0.1);
                border-bottom-color: rgba(16, 185, 129, 0.25);
            }
            [data-theme="dark"] .section-resolution .section-header h3 i {
                color: #34D399;
            }
            [data-theme="dark"] .resolution-text-box {
                background: rgba(16, 185, 129, 0.08);
                border-color: rgba(16, 185, 129, 0.3);
                border-left-color: #10B981;
                color: #D1FAE5;
            }
            [data-theme="dark"] .responder-meta {
                color: #6EE7B7;
            }
            [data-theme="dark"] .responder-meta .agent-tag {
                background: rgba(16, 185, 129, 0.15);
                color: #6EE7B7;
                border-color: rgba(16, 185, 129, 0.3);
            }

            /* Attachment Cards */
            [data-theme="dark"] .attachment-card {
                background: #1C273B;
                border-color: #27354A;
            }
            [data-theme="dark"] .attachment-title {
                color: #E2E8F0;
            }
            [data-theme="dark"] .attachment-filename {
                color: #94A3B8;
            }
            [data-theme="dark"] .attachment-thumb {
                border-color: #334155;
            }

            /* CSAT Assessment Section */
            [data-theme="dark"] .receipt-section[style*="border-color:#fef3c7"] {
                background: #151D2C !important;
                border-color: rgba(245, 158, 11, 0.3) !important;
            }
            [data-theme="dark"] .receipt-section[style*="border-color:#fef3c7"] .section-header {
                background: rgba(245, 158, 11, 0.08) !important;
                border-bottom-color: rgba(245, 158, 11, 0.2) !important;
            }
            [data-theme="dark"] .receipt-section[style*="border-color:#fef3c7"] .section-header h3 {
                color: #FBBF24 !important;
            }
            [data-theme="dark"] .receipt-section[style*="border-color:#fef3c7"] .section-body div[style*="color:#92400e"] {
                color: #FDE68A !important;
            }
            [data-theme="dark"] .receipt-section[style*="border-color:#fef3c7"] .section-body div[style*="background:#ffffff"] {
                background: #1C273B !important;
                border-color: rgba(245, 158, 11, 0.3) !important;
                color: #CBD5E1 !important;
            }

            /* Previous Resolution History (inline-styled sections) */
            [data-theme="dark"] .receipt-section[style*="background:#f8fafc"] {
                background: rgba(30, 41, 59, 0.5) !important;
                border-color: #27354A !important;
            }
            [data-theme="dark"] .receipt-section[style*="background:#f8fafc"] .section-header {
                background: #1C273B !important;
                border-bottom-color: #27354A !important;
            }
            [data-theme="dark"] .receipt-section[style*="background:#f8fafc"] .section-header h3 {
                color: #94A3B8 !important;
            }
            [data-theme="dark"] .resolution-text-box[style*="background:#ffffff"] {
                background: #1C273B !important;
                border-color: #27354A !important;
                color: #CBD5E1 !important;
            }
            [data-theme="dark"] .responder-meta .agent-tag[style*="background:#e2e8f0"] {
                background: rgba(148, 163, 184, 0.15) !important;
                color: #94A3B8 !important;
            }
            [data-theme="dark"] .responder-meta span[style*="color:#64748b"] {
                color: #94A3B8 !important;
            }

            /* Reopen Reason in History */
            [data-theme="dark"] div[style*="background:#faf5ff"] {
                background: rgba(139, 92, 246, 0.12) !important;
                border-color: rgba(139, 92, 246, 0.3) !important;
                color: #C4B5FD !important;
            }

            /* Pending / In-Progress Notice */
            [data-theme="dark"] .section-pending-notice {
                background: rgba(217, 119, 6, 0.1);
                border-color: rgba(217, 119, 6, 0.25);
            }
            [data-theme="dark"] .pending-notice-icon {
                color: #FBBF24;
            }
            [data-theme="dark"] .pending-notice-content h4 {
                color: #FDE68A;
            }
            [data-theme="dark"] .pending-notice-content p {
                color: #FCD34D;
            }
            [data-theme="dark"] .section-pending-notice[style*="background:#f0f9ff"] {
                background: rgba(14, 165, 233, 0.1) !important;
                border-color: rgba(14, 165, 233, 0.25) !important;
            }
            [data-theme="dark"] .section-pending-notice[style*="background:#f0f9ff"] .pending-notice-icon {
                background: rgba(14, 165, 233, 0.15) !important;
                color: #38BDF8 !important;
            }
            [data-theme="dark"] .section-pending-notice[style*="background:#f0f9ff"] .pending-notice-content h4 {
                color: #7DD3FC !important;
            }
            [data-theme="dark"] .section-pending-notice[style*="background:#f0f9ff"] .pending-notice-content p {
                color: #BAE6FD !important;
            }

            /* Timeline (if present) */
            [data-theme="dark"] .timeline-step-content {
                background: #1C273B;
                border-color: #27354A;
            }
            [data-theme="dark"] .timeline-step-title {
                color: #FFFFFF;
            }
            [data-theme="dark"] .timeline-step-time {
                color: #94A3B8;
            }

            /* Receipt Footer */
            [data-theme="dark"] .receipt-footer {
                border-top-color: #27354A;
            }
            [data-theme="dark"] .footer-disclaimer {
                color: #94A3B8;
            }
            [data-theme="dark"] .footer-disclaimer strong {
                color: #CBD5E1;
            }
            [data-theme="dark"] .seal-badge {
                background: rgba(59, 130, 246, 0.12);
                border-color: #3B82F6;
                color: #60A5FA;
            }
            [data-theme="dark"] .seal-id {
                color: #64748B;
            }

            /* Theme Toggle Icons */
            [data-theme="dark"] .theme-moon-icon {
                display: none;
            }
            [data-theme="dark"] .theme-sun-icon {
                display: inline-block !important;
            }

            /* History section heading inline color overrides */
            [data-theme="dark"] .section-header h3[style*="color:#475569"] {
                color: #94A3B8 !important;
            }

            /* CSAT feedback quote box inline override */
            [data-theme="dark"] div[style*="background:#ffffff"][style*="color:#475569"] {
                background: #1C273B !important;
                color: #CBD5E1 !important;
                border-color: rgba(245, 158, 11, 0.3) !important;
            }

            /* CSAT date submitted inline override */
            [data-theme="dark"] div[style*="color:#94a3b8"] {
                color: #64748B !important;
            }

            /* Green-bordered attachment card in resolution */
            [data-theme="dark"] .attachment-card[style*="background:#f0fdf4"] {
                background: rgba(16, 185, 129, 0.08) !important;
                border-color: rgba(16, 185, 129, 0.3) !important;
            }
            [data-theme="dark"] .attachment-title[style*="color:#15803d"] {
                color: #6EE7B7 !important;
            }
            [data-theme="dark"] .attachment-view-link[style*="color:#15803d"] {
                color: #6EE7B7 !important;
            }

            /* CSAT stars label text inline override */
            [data-theme="dark"] span[style*="color:#64748b"] {
                color: #94A3B8 !important;
            }

            /* Reopen reason inline date color */
            [data-theme="dark"] span[style*="color:#9333ea"] {
                color: #A78BFA !important;
            }
        }
    </style>
</head>
<body>

    <!-- Non-Printable Header & Toolbar -->
    <div class="toolbar-wrapper no-print">
        <a href="<?php echo htmlspecialchars($backUrl); ?>" class="btn-action btn-back">
            <i class="fa-solid fa-arrow-left"></i>
            <?php echo $backLabel; ?>
        </a>

        <div class="toolbar-hint">
            <i class="fa-solid fa-circle-info"></i>
            Tip: In the print dialog, select <strong>"Save as PDF"</strong> as your destination.
        </div>

        <div style="display:flex; align-items:center; gap:8px;">
            <button type="button" id="receiptThemeBtn" class="btn-action btn-back" style="cursor:pointer;" aria-label="Toggle theme">
                <i class="fa-solid fa-moon theme-moon-icon"></i>
                <i class="fa-solid fa-sun theme-sun-icon" style="display:none; color:#FBBF24;"></i>
                <span>Theme</span>
            </button>

            <button onclick="window.print()" class="btn-action btn-print">
                <i class="fa-solid fa-print"></i>
                Print / Save as PDF
            </button>
        </div>
    </div>

    <!-- Official Receipt Printable Container -->
    <div class="receipt-container">
        <!-- Colored top decorative line -->
        <div class="receipt-top-banner"></div>

        <div class="receipt-body">
            
            <!-- Company & Tracking Header -->
            <header class="receipt-header">
                <div class="brand-block">
                    <div class="brand-logo-icon">
                        <i class="fa-solid fa-headset"></i>
                    </div>
                    <div class="brand-text">
                        <h1><?php echo defined('SITE_NAME') ? SITE_NAME : 'BethelDesk'; ?> Helpdesk</h1>
                        <p>Official Support Ticket Report &amp; Receipt &bull; Customer Support Platform</p>
                    </div>
                </div>

                <div class="tracking-block">
                    <div class="tracking-tag">Ticket Tracking Reference</div>
                    <div class="tracking-number"><?php echo $trackingId; ?></div>
                    <div class="barcode-strip" title="Automated Barcode"></div>
                </div>
            </header>

            <!-- Timestamps & Generation Dates -->
            <div class="dates-ribbon">
                <div class="date-item">
                    <span class="label">Date Generated</span>
                    <span class="val"><i class="fa-solid fa-file-invoice"></i> <?php echo $dateGenerated; ?></span>
                </div>
                <div class="date-item">
                    <span class="label">Ticket Submitted</span>
                    <span class="val"><i class="fa-solid fa-calendar-plus"></i> <?php echo $dateSubmitted; ?></span>
                </div>
                <div class="date-item">
                    <span class="label">Status / Resolution Date</span>
                    <span class="val">
                        <?php if ($dateResolved): ?>
                            <i class="fa-solid fa-calendar-check" style="color:#16a34a;"></i> <?php echo $dateResolved; ?>
                        <?php elseif ($status === 'In Progress'): ?>
                            <i class="fa-solid fa-spinner" style="color:#0284c7;"></i> In Progress
                        <?php elseif ($status === 'Reopened'): ?>
                            <i class="fa-solid fa-arrows-rotate" style="color:#7c3aed;"></i> Reopened Review
                        <?php else: ?>
                            <i class="fa-solid fa-hourglass-start" style="color:#d97706;"></i> Pending Review
                        <?php endif; ?>
                    </span>
                </div>
            </div>

            <!-- Status & Metadata Grid -->
            <div class="meta-grid">
                <!-- Status -->
                <div class="meta-card">
                    <div class="meta-label">Ticket Status</div>
                    <div class="meta-value">
                        <?php if ($status === 'Resolved'): ?>
                            <span class="badge badge-resolved">
                                <i class="fa-solid fa-circle-check"></i> Resolved
                            </span>
                        <?php elseif ($status === 'In Progress'): ?>
                            <span class="badge badge-inprogress">
                                <i class="fa-solid fa-spinner"></i> In Progress
                            </span>
                        <?php elseif ($status === 'Reopened'): ?>
                            <span class="badge badge-reopened">
                                <i class="fa-solid fa-arrows-rotate"></i> Reopened
                            </span>
                        <?php else: ?>
                            <span class="badge badge-pending">
                                <i class="fa-solid fa-hourglass-half"></i> Pending
                            </span>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Category -->
                <div class="meta-card">
                    <div class="meta-label">Category</div>
                    <div class="meta-value">
                        <span class="category-val">
                            <i class="fa-solid <?php echo $catIcon; ?>"></i>
                            <?php echo $category; ?>
                        </span>
                    </div>
                </div>

                <!-- Priority -->
                <div class="meta-card">
                    <div class="meta-label">Priority Level</div>
                    <div class="meta-value">
                        <span class="priority-badge <?php echo $priorityClass; ?>">
                            <i class="fa-solid <?php echo $priorityIcon; ?>"></i>
                            <?php echo $priority; ?> Priority
                        </span>
                    </div>
                </div>

                <!-- Customer Details -->
                <div class="meta-card">
                    <div class="meta-label">Customer Account</div>
                    <div class="meta-value customer-meta">
                        <span class="c-name"><?php echo $customerName; ?></span>
                        <span class="c-email"><?php echo $customerEmail; ?></span>
                    </div>
                </div>
            </div>

            <!-- Customer Complaint & Inquiry Section -->
            <section class="receipt-section">
                <div class="section-header">
                    <h3><i class="fa-solid fa-circle-question"></i> Customer Inquiry / Complaint Details</h3>
                </div>
                <div class="section-body">
                    <div class="ticket-subject-title"><?php echo $subject; ?></div>
                    <div class="ticket-text-box">
                        <?php echo $message; ?>
                    </div>

                    <?php 
                    // Customer Screenshot Attachment
                    if (!empty($ticket['attachment'])): 
                        $custAttachFile = htmlspecialchars($ticket['attachment']);
                        $custAttachPath = "uploads/" . $custAttachFile;
                        if (file_exists(__DIR__ . "/" . $custAttachPath)):
                            $custFileSize = round(filesize(__DIR__ . "/" . $custAttachPath) / 1024, 1);
                    ?>
                        <div class="attachment-card">
                            <img src="<?php echo $custAttachPath; ?>" 
                                 alt="Customer Attachment" 
                                 class="attachment-thumb" 
                                 onclick="openImageModal('<?php echo $custAttachPath; ?>')">
                            <div class="attachment-info">
                                <span class="attachment-title">
                                    <i class="fa-solid fa-paperclip"></i> Attached Supporting Screenshot
                                </span>
                                <span class="attachment-filename"><?php echo $custAttachFile; ?> (<?php echo $custFileSize; ?> KB)</span>
                                <a href="javascript:void(0);" 
                                   class="attachment-view-link no-print" 
                                   onclick="openImageModal('<?php echo $custAttachPath; ?>')">
                                    <i class="fa-solid fa-up-right-from-square"></i> Click to preview full image
                                </a>
                            </div>
                        </div>
                    <?php 
                        endif;
                    endif; 
                    ?>
                </div>
            </section>

            <!-- Resolution Section (if resolved) or Pending Notice -->
            <?php if (!empty($ticket['response']) || $ticket['status'] === 'Resolved'): ?>

                <?php 
                // Display Previous Resolution History on receipt if reopened previously
                if (!empty($ticket['response_history'])):
                    $pastResponses = json_decode($ticket['response_history'], true);
                    if (is_array($pastResponses)):
                        $rNum = 1;
                        foreach ($pastResponses as $past):
                ?>
                    <section class="receipt-section" style="border-color:#cbd5e1; background:#f8fafc; margin-bottom:16px;">
                        <div class="section-header" style="background:#f1f5f9; border-bottom-color:#cbd5e1;">
                            <h3 style="color:#475569;"><i class="fa-solid fa-clock-rotate-left"></i> Previous Support Resolution (Round <?php echo $rNum; ?>)</h3>
                        </div>
                        <div class="section-body">
                            <div class="responder-meta">
                                <span class="agent-tag" style="background:#e2e8f0; color:#475569; border-color:#cbd5e1;">Helpdesk Support Engineering</span>
                                <?php if (!empty($past['responded_at'])): ?>
                                    <span style="color:#64748b;"><i class="fa-solid fa-clock"></i> Responded: <?php echo date("d M Y • h:i A", strtotime($past['responded_at'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="resolution-text-box" style="background:#ffffff; border-color:#e2e8f0; color:#334155;">
                                <?php echo nl2br(htmlspecialchars($past['response'] ?? '')); ?>
                            </div>
                            <?php if (!empty($past['reopen_reason'])): ?>
                                <div style="margin-top:10px; padding:10px 14px; background:#faf5ff; border:1px solid #e9d5ff; border-radius:6px; font-size:12px; color:#6b21a8;">
                                    <strong><i class="fa-solid fa-arrows-rotate"></i> Customer Reopen Reason:</strong> <?php echo htmlspecialchars($past['reopen_reason']); ?>
                                    <?php if (!empty($past['reopened_at'])): ?>
                                        <span style="color:#9333ea; font-size:11px;">(<?php echo date("d M Y • h:i A", strtotime($past['reopened_at'])); ?>)</span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php 
                        $rNum++;
                        endforeach;
                    endif;
                endif; 
                ?>

                <section class="receipt-section section-resolution">
                    <div class="section-header">
                        <h3><i class="fa-solid fa-circle-check"></i> <?php echo !empty($ticket['response_history']) ? 'Latest Official Support Resolution' : 'Official Support Resolution &amp; Response'; ?></h3>
                    </div>
                    <div class="section-body">
                        <div class="responder-meta">
                            <span class="agent-tag">Helpdesk Support Engineering</span>
                            <?php if ($dateResolved): ?>
                                <span><i class="fa-solid fa-clock"></i> Responded: <?php echo $dateResolved; ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="resolution-text-box">
                            <?php echo !empty($response) ? $response : '<em>Case marked as resolved by customer support.</em>'; ?>
                        </div>

                        <?php 
                        // Admin Screenshot Attachment
                        if (!empty($ticket['admin_attachment'])): 
                            $adminAttachFile = htmlspecialchars($ticket['admin_attachment']);
                            $adminAttachPath = "uploads/" . $adminAttachFile;
                            if (file_exists(__DIR__ . "/" . $adminAttachPath)):
                                $adminFileSize = round(filesize(__DIR__ . "/" . $adminAttachPath) / 1024, 1);
                        ?>
                            <div class="attachment-card" style="border-color:#bbf7d0; background:#f0fdf4;">
                                <img src="<?php echo $adminAttachPath; ?>" 
                                     alt="Support Team Attachment" 
                                     class="attachment-thumb" 
                                     onclick="openImageModal('<?php echo $adminAttachPath; ?>')">
                                <div class="attachment-info">
                                    <span class="attachment-title" style="color:#15803d;">
                                        <i class="fa-solid fa-paperclip"></i> Support Resolution Attachment
                                    </span>
                                    <span class="attachment-filename"><?php echo $adminAttachFile; ?> (<?php echo $adminFileSize; ?> KB)</span>
                                    <a href="javascript:void(0);" 
                                       class="attachment-view-link no-print" 
                                       style="color:#15803d;" 
                                       onclick="openImageModal('<?php echo $adminAttachPath; ?>')">
                                        <i class="fa-solid fa-up-right-from-square"></i> Click to preview full image
                                    </a>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endif; 
                        ?>
                    </div>
                </section>

                <!-- Customer Satisfaction Rating Section (if rated) -->
                <?php if (!empty($ticket['rating'])): 
                    $stars = (int)$ticket['rating'];
                    $starsHtml = str_repeat('<i class="fa-solid fa-star star-filled" style="color:#f59e0b;"></i> ', $stars) . str_repeat('<i class="fa-regular fa-star star-empty" style="color:#94a3b8;"></i> ', 5 - $stars);
                    $dateRated = !empty($ticket['rated_at']) ? date("d M Y • h:i A", strtotime($ticket['rated_at'])) : null;
                ?>
                    <section class="receipt-section" style="border-color:#fef3c7; background:#fffdfa;">
                        <div class="section-header" style="background:#fffbeb; border-bottom-color:#fef3c7;">
                            <h3 style="color:#b45309;"><i class="fa-solid fa-star" style="color:#f59e0b;"></i> Customer Satisfaction Assessment (CSAT)</h3>
                        </div>
                        <div class="section-body">
                            <div style="display:flex; align-items:center; gap:8px; font-size:15px; font-weight:700; color:#92400e; margin-bottom:8px;">
                                <span>Verified Client Rating:</span>
                                <span><?php echo $starsHtml; ?></span>
                                <span style="font-size:13px; color:#64748b;">(<?php echo $stars; ?> out of 5 Stars)</span>
                            </div>
                            <?php if (!empty($ticket['feedback'])): ?>
                                <div style="background:#ffffff; border:1px solid #fde68a; border-left:4px solid #f59e0b; padding:12px; border-radius:6px; font-size:13px; color:#475569; font-style:italic;">
                                    &ldquo;<?php echo htmlspecialchars($ticket['feedback']); ?>&rdquo;
                                </div>
                            <?php endif; ?>
                            <?php if ($dateRated): ?>
                                <div style="font-size:11px; color:#94a3b8; margin-top:8px;">
                                    <i class="fa-solid fa-clock"></i> Feedback submitted on <?php echo $dateRated; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endif; ?>
            <?php else: ?>
                <?php if ($status === 'In Progress'): ?>
                    <div class="section-pending-notice" style="background:#f0f9ff; border-color:#bae6fd;">
                        <div class="pending-notice-icon" style="background:#e0f2fe; color:#0284c7;">
                            <i class="fa-solid fa-spinner"></i>
                        </div>
                        <div class="pending-notice-content">
                            <h4 style="color:#0369a1;">Investigation In Progress</h4>
                            <p style="color:#0c4a6e;">This support ticket has been acknowledged and is actively being handled by our support specialists. An official resolution statement will be added here upon completion.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="section-pending-notice">
                        <div class="pending-notice-icon">
                            <i class="fa-solid fa-clock-rotate-left"></i>
                        </div>
                        <div class="pending-notice-content">
                            <h4>Awaiting Support Specialist Resolution</h4>
                            <p>This support request is actively being reviewed by our customer care and technical support team. Once resolved, an official resolution statement and updated receipt will be issued.</p>
                        </div>
                    </div>
                <?php endif; ?>
            <?php endif; ?>

            <!-- Official Seal & Footer Verification -->
            <footer class="receipt-footer">
                <div class="footer-disclaimer">
                    <p><strong>Official Customer Record:</strong> This receipt is automatically generated by the <?php echo defined('SITE_NAME') ? SITE_NAME : 'BethelDesk'; ?> Support Platform. Please cite Reference <strong><?php echo $trackingId; ?></strong> during follow-up inquiries. Any alterations invalidate this document.</p>
                </div>
                <div class="official-seal">
                    <div class="seal-badge">
                        <i class="fa-solid fa-shield-halved"></i> Verified Support Record
                    </div>
                    <div class="seal-id">AUTH-ID: <?php echo strtoupper(substr(md5($trackingId . $ticket['created_at']), 0, 16)); ?></div>
                </div>
            </footer>

        </div>
    </div>

    <!-- Image Lightbox Modal (Non-printable) -->
    <div id="imageModal" class="image-modal no-print" onclick="closeImageModal(event)">
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

    <?php if ($autoPrint): ?>
    // Auto-launch print dialog if ?print=1 is present
    window.addEventListener('DOMContentLoaded', function() {
        setTimeout(function() {
            window.print();
        }, 350);
    });
    <?php endif; ?>

    var themeBtn = document.getElementById('receiptThemeBtn');
    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            var current = document.documentElement.getAttribute('data-theme') === 'dark' ? 'dark' : 'light';
            var next = current === 'dark' ? 'light' : 'dark';
            document.documentElement.setAttribute('data-theme', next);
            try {
                localStorage.setItem('bethel_theme', next);
            } catch(e) {}
        });
    }
    </script>
</body>
</html>
