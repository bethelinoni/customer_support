<?php
// Load mail configuration and PHPMailer classes
require_once __DIR__ . '/mail_config.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/phpmailer/Exception.php';
require_once __DIR__ . '/phpmailer/PHPMailer.php';
require_once __DIR__ . '/phpmailer/SMTP.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Prevent multiple inclusions
if (!function_exists('sendSupportEmail')) {

    /**
     * Send a branded, responsive HTML support email and log it to email_logs
     * 
     * @param string $toEmail
     * @param string $toName
     * @param string $subject
     * @param string $headline
     * @param string $messageHtml
     * @param string|null $actionUrl
     * @param string|null $actionText
     * @param string|null $badgeText
     * @param string $badgeColor
     * @return bool
     */
    function sendSupportEmail($toEmail, $toName, $subject, $headline, $messageHtml, $actionUrl = null, $actionText = null, $badgeText = null, $badgeColor = '#1976d2') {
        global $conn;

        // Ensure database connection is present
        if (!isset($conn) || !($conn instanceof mysqli)) {
            require_once __DIR__ . '/connect.php';
        }

        $safeName = htmlspecialchars($toName ?: 'Customer');
        $safeHeadline = htmlspecialchars($headline);

        $siteBrandName = htmlspecialchars(defined('SITE_NAME') ? SITE_NAME : 'BethelDesk');

        // Action button HTML if URL provided
        $buttonHtml = '';
        if (!empty($actionUrl) && !empty($actionText)) {
            $safeUrl = htmlspecialchars($actionUrl);
            $safeBtnText = htmlspecialchars($actionText);
            $buttonHtml = "
                <div style='margin: 28px 0; text-align: center;'>
                    <a href='{$safeUrl}' target='_blank' style='display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; padding: 12px 28px; font-size: 15px; font-weight: 700; border-radius: 6px; box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);'>
                        {$safeBtnText} &rarr;
                    </a>
                </div>
            ";
        }

        // Optional badge (e.g. ticket ref or status)
        $badgeHtml = '';
        if (!empty($badgeText)) {
            $safeBadge = htmlspecialchars($badgeText);
            $badgeHtml = "<span style='display: inline-block; padding: 4px 12px; background: {$badgeColor}; color: #ffffff; font-size: 12px; font-weight: 700; border-radius: 20px; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 12px;'>{$safeBadge}</span><br>";
        }

        // Full Responsive HTML Email Template
        $fullHtml = "
<!DOCTYPE html>
<html lang='en'>
<head>
<meta charset='UTF-8'>
<meta name='viewport' content='width=device-width, initial-scale=1.0'>
<title>{$subject}</title>
</head>
<body style='margin: 0; padding: 0; background-color: #f8fafc; font-family: -apple-system, BlinkMacSystemFont, \"Segoe UI\", Roboto, \"Helvetica Neue\", Arial, sans-serif; color: #334155; -webkit-text-size-adjust: 100%;'>

<table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='background-color: #f8fafc; padding: 30px 10px;'>
  <tr>
    <td align='center'>
      
      <!-- Main Email Container -->
      <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%' style='max-width: 600px; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 8px 24px rgba(0,0,0,0.06); border: 1px solid #e2e8f0;'>
        
        <!-- Header Banner -->
        <tr>
          <td style='background: linear-gradient(135deg, #1e3a8a 0%, #2563eb 100%); padding: 28px 30px; text-align: left;'>
            <table role='presentation' border='0' cellpadding='0' cellspacing='0' width='100%'>
              <tr>
                <td>
                  <h1 style='margin: 0; color: #ffffff; font-size: 20px; font-weight: 800; letter-spacing: -0.3px;'>
                    {$siteBrandName}
                  </h1>
                  <p style='margin: 4px 0 0; color: #dbeafe; font-size: 13px;'>
                    Customer Support &amp; Helpdesk Platform
                  </p>
                </td>
              </tr>
            </table>
          </td>
        </tr>

        <!-- Body Content -->
        <tr>
          <td style='padding: 36px 32px 28px;'>
            
            {$badgeHtml}

            <h2 style='margin: 0 0 16px; color: #0f172a; font-size: 20px; font-weight: 700; line-height: 1.4;'>
              {$safeHeadline}
            </h2>

            <p style='margin: 0 0 18px; font-size: 15px; line-height: 1.6; color: #475569;'>
              Hello <strong>{$safeName}</strong>,
            </p>

            <div style='font-size: 14px; line-height: 1.7; color: #475569;'>
              {$messageHtml}
            </div>

            {$buttonHtml}

            <div style='margin-top: 28px; padding-top: 20px; border-top: 1px solid #f1f5f9; font-size: 13px; color: #64748b;'>
              <p style='margin: 0;'>
                If you have any further questions, simply visit the <a href='http://localhost/tega_project/dashboard.php' style='color: #2563eb; text-decoration: none; font-weight: 600;'>Customer Portal</a>.
              </p>
            </div>

          </td>
        </tr>

        <!-- Footer -->
        <tr>
          <td style='background-color: #f8fafc; padding: 20px 30px; border-top: 1px solid #e2e8f0; text-align: center;'>
            <p style='margin: 0 0 6px; font-size: 12px; color: #64748b;'>
              &copy; " . date('Y') . " {$siteBrandName}. All rights reserved.
            </p>
            <p style='margin: 0; font-size: 11px; color: #94a3b8;'>
              This is an automated notification. Please do not reply directly to this email address.
            </p>
          </td>
        </tr>

      </table>

    </td>
  </tr>
</table>

</body>
</html>
";

        // Queue email as Pending in email_logs for instant, non-blocking response
        $pendingStatus = 'Pending';
        $logStmt = $conn->prepare("INSERT INTO email_logs (recipient, subject, body, sent_status) VALUES (?, ?, ?, ?)");
        if ($logStmt) {
            $logStmt->bind_param("ssss", $toEmail, $subject, $fullHtml, $pendingStatus);
            $logStmt->execute();
            $logStmt->close();
        }

        return true;
    }

    /**
     * Process pending emails from email_logs using PHPMailer over Gmail SMTP
     * Includes atomic locking, 7-second connection timeout, and sent_status updates.
     * 
     * @param int $batchSize
     * @return int Number of emails processed
     */
    function processEmailQueue($batchSize = 5) {
        global $conn;

        if (!isset($conn) || !($conn instanceof mysqli)) {
            require_once __DIR__ . '/connect.php';
        }

        // Recover any jobs stuck in 'Processing' for > 5 minutes
        $conn->query("UPDATE email_logs SET sent_status = 'Pending' WHERE sent_status = 'Processing' AND created_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE)");

        // Select pending email IDs
        $stmt = $conn->prepare("SELECT id FROM email_logs WHERE sent_status = 'Pending' ORDER BY id ASC LIMIT ?");
        $stmt->bind_param("i", $batchSize);
        $stmt->execute();
        $res = $stmt->get_result();
        $pendingIds = [];
        while ($row = $res->fetch_assoc()) {
            $pendingIds[] = (int)$row['id'];
        }
        $stmt->close();

        if (empty($pendingIds)) {
            return 0;
        }

        // Lock these IDs by transitioning to 'Processing' to prevent concurrent duplicates
        $idList = implode(',', $pendingIds);
        $conn->query("UPDATE email_logs SET sent_status = 'Processing' WHERE id IN ($idList) AND sent_status = 'Pending'");

        // Fetch locked records
        $fetchStmt = $conn->query("SELECT id, recipient, subject, body FROM email_logs WHERE id IN ($idList) AND sent_status = 'Processing'");
        if (!$fetchStmt || $fetchStmt->num_rows === 0) {
            return 0;
        }

        $username = defined('MAIL_USERNAME') ? trim(MAIL_USERNAME) : '';
        $password = defined('MAIL_APP_PASSWORD') ? trim(MAIL_APP_PASSWORD) : '';
        $hasCredentials = !empty($username) && !empty($password);

        $processedCount = 0;

        while ($email = $fetchStmt->fetch_assoc()) {
            $emailId = (int)$email['id'];
            $toEmail = $email['recipient'];
            $subject = $email['subject'];
            $bodyHtml = $email['body'];

            $newStatus = 'Sent';

            if ($hasCredentials) {
                $mail = new PHPMailer(true);
                try {
                    // Server settings
                    $mail->isSMTP();
                    $mail->Host       = 'smtp.gmail.com';
                    $mail->SMTPAuth   = true;
                    $mail->Username   = $username;
                    $mail->Password   = $password;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port       = 587;
                    $mail->CharSet    = 'UTF-8';
                    $mail->Timeout    = 7; // Short 5-8s timeout so slow responses can't hang request

                    // Windows / XAMPP SSL stream options to prevent hanging on local certificate validation
                    $mail->SMTPOptions = array(
                        'ssl' => array(
                            'verify_peer' => false,
                            'verify_peer_name' => false,
                            'allow_self_signed' => true
                        )
                    );

                    // Recipients
                    $mail->setFrom($username, defined('SITE_NAME') ? SITE_NAME : 'BethelDesk');
                    $mail->addAddress($toEmail);

                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = $subject;
                    $mail->Body    = $bodyHtml;
                    $mail->AltBody = strip_tags($bodyHtml);

                    $mail->send();
                    $newStatus = 'Sent';
                } catch (Exception $e) {
                    $errorMsg = !empty($mail->ErrorInfo) ? $mail->ErrorInfo : $e->getMessage();
                    $newStatus = 'Failed: ' . $errorMsg;
                }
            } else {
                $newStatus = 'Failed: Missing MAIL_USERNAME or MAIL_APP_PASSWORD in mail_config.php';
            }

            if (strlen($newStatus) > 255) {
                $newStatus = substr($newStatus, 0, 252) . '...';
            }

            $upStmt = $conn->prepare("UPDATE email_logs SET sent_status = ? WHERE id = ?");
            $upStmt->bind_param("si", $newStatus, $emailId);
            $upStmt->execute();
            $upStmt->close();

            $processedCount++;
        }

        return $processedCount;
    }

    /**
     * Send confirmation email when customer files a new ticket
     */
    function sendTicketSubmittedEmail($toEmail, $toName, $ticketId, $subject, $category, $priority) {
        $formattedId = "#TKT-" . str_pad($ticketId, 4, "0", STR_PAD_LEFT);
        $emailSubject = "Complaint Received: {$formattedId} - {$subject}";
        $headline = "We Have Received Your Support Complaint";

        $message = "
            <p>Thank you for reaching out to us. We have successfully logged your complaint in our system and an agent will review it shortly.</p>
            
            <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #1976d2; padding: 14px 18px; border-radius: 6px; margin: 16px 0;'>
                <p style='margin: 0 0 6px;'><strong>Complaint Ref:</strong> {$formattedId}</p>
                <p style='margin: 0 0 6px;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p style='margin: 0 0 6px;'><strong>Category:</strong> " . htmlspecialchars($category) . "</p>
                <p style='margin: 0;'><strong>Priority:</strong> " . htmlspecialchars($priority) . "</p>
            </div>

            <p>You can track the progress of your complaint in real time or view updates directly from your dashboard.</p>
        ";

        $actionUrl = "http://localhost/tega_project/view_tickets.php";
        $actionText = "View Your Complaint";

        return sendSupportEmail($toEmail, $toName, $emailSubject, $headline, $message, $actionUrl, $actionText, "Pending Review", "#f9a825");
    }

    /**
     * Send email when ticket is marked In Progress
     */
    function sendTicketInProgressEmail($toEmail, $toName, $ticketId, $subject) {
        $formattedId = "#TKT-" . str_pad($ticketId, 4, "0", STR_PAD_LEFT);
        $emailSubject = "Investigation Started: {$formattedId} - {$subject}";
        $headline = "Your Complaint is Under Active Investigation";

        $message = "
            <p>A support agent has begun actively investigating your complaint (<strong>{$formattedId}</strong>: <em>" . htmlspecialchars($subject) . "</em>).</p>
            
            <div style='background: #f0f9ff; border: 1px solid #bae6fd; border-left: 4px solid #0284c7; padding: 14px 18px; border-radius: 6px; margin: 16px 0;'>
                <p style='margin: 0; color: #0369a1; font-weight: 600;'>
                    Status: In Progress &bull; Work has commenced on your issue.
                </p>
            </div>

            <p>We will notify you again as soon as an official resolution is provided.</p>
        ";

        $actionUrl = "http://localhost/tega_project/view_tickets.php";
        $actionText = "Track Complaint Status";

        return sendSupportEmail($toEmail, $toName, $emailSubject, $headline, $message, $actionUrl, $actionText, "In Progress", "#0284c7");
    }

    /**
     * Send email when agent provides official resolution
     */
    function sendTicketResolvedEmail($toEmail, $toName, $ticketId, $subject, $responseExcerpt) {
        $formattedId = "#TKT-" . str_pad($ticketId, 4, "0", STR_PAD_LEFT);
        $emailSubject = "Complaint Resolved: {$formattedId} - {$subject}";
        $headline = "Your Complaint Has Been Resolved";

        $safeExcerpt = nl2br(htmlspecialchars($responseExcerpt));

        $message = "
            <p>Our support team has reviewed and resolved your complaint (<strong>{$formattedId}</strong>: <em>" . htmlspecialchars($subject) . "</em>).</p>

            <div style='background: #f0fdf4; border: 1px solid #bbf7d0; border-left: 4px solid #16a34a; padding: 16px 18px; border-radius: 6px; margin: 18px 0;'>
                <strong style='display: block; color: #15803d; margin-bottom: 8px; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px;'>Official Response Excerpt:</strong>
                <div style='color: #166534; font-size: 14px; line-height: 1.6;'>
                    {$safeExcerpt}
                </div>
            </div>

            <p>Please log in to view the full resolution, download your printable PDF receipt, or rate your experience with our CSAT feedback rating.</p>
            <p style='font-size: 13px; color: #64748b;'>If the issue persists, you may also reopen the complaint directly from your portal within the allowed timeframe.</p>
        ";

        $actionUrl = "http://localhost/tega_project/view_tickets.php";
        $actionText = "View Resolution &amp; Rate Support";

        return sendSupportEmail($toEmail, $toName, $emailSubject, $headline, $message, $actionUrl, $actionText, "Resolved", "#16a34a");
    }

    /**
     * Send email when ticket is reopened
     */
    function sendTicketReopenedEmail($toEmail, $toName, $ticketId, $subject, $reopenReason) {
        $formattedId = "#TKT-" . str_pad($ticketId, 4, "0", STR_PAD_LEFT);
        $emailSubject = "Complaint Reopened: {$formattedId} - {$subject}";
        $headline = "Your Complaint Has Been Reopened";

        $safeReason = nl2br(htmlspecialchars($reopenReason));

        $message = "
            <p>We received your request to reopen complaint <strong>{$formattedId}</strong> (<em>" . htmlspecialchars($subject) . "</em>).</p>

            <div style='background: #f5f3ff; border: 1px solid #ddd6fe; border-left: 4px solid #7c3aed; padding: 14px 18px; border-radius: 6px; margin: 16px 0;'>
                <strong style='display: block; color: #5b21b6; margin-bottom: 6px; font-size: 12px; text-transform: uppercase;'>Your Reason for Reopening:</strong>
                <div style='color: #4c1d95; font-size: 13px;'>
                    {$safeReason}
                </div>
            </div>

            <p>Our support team has placed this ticket back in active review and an agent will follow up with you promptly.</p>
        ";

        $actionUrl = "http://localhost/tega_project/view_tickets.php";
        $actionText = "View Complaint";

        return sendSupportEmail($toEmail, $toName, $emailSubject, $headline, $message, $actionUrl, $actionText, "Reopened", "#7c3aed");
    }

    /**
     * Send password reset email with secure token link
     */
    function sendPasswordResetEmail($toEmail, $toName, $resetLink) {
        $emailSubject = "Password Reset Request - " . (defined('SITE_NAME') ? SITE_NAME : 'BethelDesk');
        $headline = "Reset Your Account Password";

        $message = "
            <p>We received a request to reset the password for your customer support account associated with <strong>" . htmlspecialchars($toEmail) . "</strong>.</p>
            
            <p>To choose a new password, click the secure reset button below:</p>

            <p style='background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; padding: 12px 16px; border-radius: 6px; font-size: 13px; color: #92400e; margin: 18px 0;'>
                <strong>Notice:</strong> This password reset link is valid for <strong>1 hour</strong> and can only be used once.
            </p>

            <p style='font-size: 13px; color: #64748b;'>If you did not request a password reset, you can safely disregard this email. Your existing password will remain unchanged.</p>
        ";

        $actionUrl = $resetLink;
        $actionText = "Reset My Password";

        return sendSupportEmail($toEmail, $toName, $emailSubject, $headline, $message, $actionUrl, $actionText, "Security Alert", "#f59e0b");
    }

    /**
     * Retrieve the active admin email address from the admin table,
     * falling back to MAIL_ADMIN_FALLBACK or MAIL_USERNAME constant if unconfigured.
     * 
     * @return string|null
     */
    function getAdminNotificationEmail() {
        global $conn;
        if (!isset($conn) || !($conn instanceof mysqli)) {
            require_once __DIR__ . '/connect.php';
        }

        $adminEmail = null;
        $res = $conn->query("SELECT email FROM admin WHERE email IS NOT NULL AND TRIM(email) != '' LIMIT 1");
        if ($res && $res->num_rows > 0) {
            $row = $res->fetch_assoc();
            $adminEmail = trim($row['email']);
        }

        if (empty($adminEmail)) {
            if (defined('MAIL_ADMIN_FALLBACK') && !empty(MAIL_ADMIN_FALLBACK)) {
                $adminEmail = trim(MAIL_ADMIN_FALLBACK);
            } elseif (defined('MAIL_USERNAME') && !empty(MAIL_USERNAME)) {
                $adminEmail = trim(MAIL_USERNAME);
            }
        }

        return $adminEmail;
    }

    /**
     * Send admin notification email when a customer submits a new complaint
     * 
     * @param int $ticketId
     * @param string $subject
     * @param string $category
     * @param string $priority
     * @param string $customerName
     * @param string $customerEmail
     * @return bool
     */
    function sendAdminNewTicketEmail($ticketId, $subject, $category, $priority, $customerName, $customerEmail) {
        $adminEmail = getAdminNotificationEmail();
        if (empty($adminEmail)) {
            return false;
        }

        $formattedId = "#TKT-" . str_pad($ticketId, 4, "0", STR_PAD_LEFT);
        $emailSubject = "[Admin Alert] New Ticket: {$formattedId} - {$subject}";
        $headline = "New Customer Complaint Received";

        $message = "
            <p>A new support complaint has been submitted and requires agent attention.</p>
            
            <div style='background: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #0284c7; padding: 14px 18px; border-radius: 6px; margin: 16px 0;'>
                <p style='margin: 0 0 6px;'><strong>Complaint Ref:</strong> {$formattedId}</p>
                <p style='margin: 0 0 6px;'><strong>Customer:</strong> " . htmlspecialchars($customerName) . " &lt;" . htmlspecialchars($customerEmail) . "&gt;</p>
                <p style='margin: 0 0 6px;'><strong>Subject:</strong> " . htmlspecialchars($subject) . "</p>
                <p style='margin: 0 0 6px;'><strong>Category:</strong> " . htmlspecialchars($category) . "</p>
                <p style='margin: 0;'><strong>Priority:</strong> " . htmlspecialchars($priority) . "</p>
            </div>

            <p>Log in to the " . (defined('SITE_NAME') ? SITE_NAME : 'BethelDesk') . " Agent Portal to review full details, assign, or respond to this ticket.</p>
        ";

        $actionUrl = "http://localhost/tega_project/admin_dashboard.php";
        $actionText = "Open Support Portal";

        return sendSupportEmail($adminEmail, "Support Administrator", $emailSubject, $headline, $message, $actionUrl, $actionText, "New Ticket Alert", "#0284c7");
    }

    /**
     * Send admin notification email when a customer reopens a ticket
     * 
     * @param int $ticketId
     * @param string $subject
     * @param string $reopenReason
     * @param string $customerName
     * @return bool
     */
    function sendAdminTicketReopenedEmail($ticketId, $subject, $reopenReason, $customerName) {
        $adminEmail = getAdminNotificationEmail();
        if (empty($adminEmail)) {
            return false;
        }

        $formattedId = "#TKT-" . str_pad($ticketId, 4, "0", STR_PAD_LEFT);
        $emailSubject = "[Admin Alert] Ticket Reopened: {$formattedId} - {$subject}";
        $headline = "Customer Reopened Complaint {$formattedId}";

        $safeReason = nl2br(htmlspecialchars($reopenReason));

        $message = "
            <p>Customer <strong>" . htmlspecialchars($customerName) . "</strong> has requested to reopen complaint <strong>{$formattedId}</strong> (<em>" . htmlspecialchars($subject) . "</em>).</p>

            <div style='background: #f5f3ff; border: 1px solid #ddd6fe; border-left: 4px solid #7c3aed; padding: 14px 18px; border-radius: 6px; margin: 16px 0;'>
                <strong style='display: block; color: #5b21b6; margin-bottom: 6px; font-size: 12px; text-transform: uppercase;'>Customer's Stated Reason:</strong>
                <div style='color: #4c1d95; font-size: 13px;'>
                    {$safeReason}
                </div>
            </div>

            <p>This ticket has been returned to the queue as <strong>Reopened</strong> and requires follow-up investigation.</p>
        ";

        $actionUrl = "http://localhost/tega_project/admin_dashboard.php";
        $actionText = "Review Reopened Ticket";

        return sendSupportEmail($adminEmail, "Support Administrator", $emailSubject, $headline, $message, $actionUrl, $actionText, "Ticket Reopened", "#7c3aed");
    }
}
?>
