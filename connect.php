<?php
require_once __DIR__ . '/config.php';
date_default_timezone_set('Africa/Lagos');

$conn = new mysqli("localhost", "root", "", "customer_support");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Align MySQL connection timezone to West Africa Time (+01:00)
$conn->query("SET time_zone = '+01:00'");

// Auto-check and add attachment columns to tickets table if missing
$colCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'attachment'");
if ($colCheck && $colCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN attachment VARCHAR(255) NULL AFTER message");
}

$adminColCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'admin_attachment'");
if ($adminColCheck && $adminColCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN admin_attachment VARCHAR(255) NULL AFTER response");
}

// Auto-check and add category and priority columns if missing
$catCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'category'");
if ($catCheck && $catCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'General Inquiry' AFTER subject");
}

$priCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'priority'");
if ($priCheck && $priCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN priority VARCHAR(20) NOT NULL DEFAULT 'Medium' AFTER category");
}

// Auto-check and add rating and feedback columns if missing
$ratingCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'rating'");
if ($ratingCheck && $ratingCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN rating INT NULL DEFAULT NULL AFTER admin_attachment");
}

$feedbackCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'feedback'");
if ($feedbackCheck && $feedbackCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN feedback TEXT NULL DEFAULT NULL AFTER rating");
}

$ratedAtCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'rated_at'");
if ($ratedAtCheck && $ratedAtCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN rated_at DATETIME NULL DEFAULT NULL AFTER feedback");
}

$ratingUpdatedAtCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'rating_updated_at'");
if ($ratingUpdatedAtCheck && $ratingUpdatedAtCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN rating_updated_at DATETIME NULL DEFAULT NULL AFTER rated_at");
}

// Auto-check and add reopen columns if missing
$reopenReasonCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'reopen_reason'");
if ($reopenReasonCheck && $reopenReasonCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN reopen_reason TEXT NULL DEFAULT NULL AFTER rated_at");
}

$reopenedAtCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'reopened_at'");
if ($reopenedAtCheck && $reopenedAtCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN reopened_at DATETIME NULL DEFAULT NULL AFTER reopen_reason");
}

// Auto-check and add in_progress_at column if missing
$inProgressCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'in_progress_at'");
if ($inProgressCheck && $inProgressCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN in_progress_at DATETIME NULL DEFAULT NULL AFTER reopened_at");
}

// Auto-check and add response_history column if missing (preserves previous resolutions upon reopen)
$respHistCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'response_history'");
if ($respHistCheck && $respHistCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN response_history TEXT NULL DEFAULT NULL AFTER in_progress_at");
}

// Auto-check and add customer_viewed_at column if missing (tracks when customer viewed the ticket)
$viewedAtCheck = $conn->query("SHOW COLUMNS FROM tickets LIKE 'customer_viewed_at'");
if ($viewedAtCheck && $viewedAtCheck->num_rows == 0) {
    $conn->query("ALTER TABLE tickets ADD COLUMN customer_viewed_at DATETIME NULL DEFAULT NULL AFTER response_history");
}

// Ensure status column supports 'Reopened' and 'In Progress'
$conn->query("ALTER TABLE tickets MODIFY COLUMN status VARCHAR(30) NOT NULL DEFAULT 'Pending'");

// Auto-check and add phone column to users table if missing
$phoneCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'phone'");
if ($phoneCheck && $phoneCheck->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN phone VARCHAR(30) NULL DEFAULT NULL AFTER email");
}

// Auto-check and add created_at column to users table if missing
$userCreatedCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'created_at'");
if ($userCreatedCheck && $userCreatedCheck->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN created_at DATETIME NULL DEFAULT CURRENT_TIMESTAMP AFTER password");
}

// Auto-check and add profile_picture column to users table if missing
$userPicCheck = $conn->query("SHOW COLUMNS FROM users LIKE 'profile_picture'");
if ($userPicCheck && $userPicCheck->num_rows == 0) {
    $conn->query("ALTER TABLE users ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER phone");
}

// Auto-check and add fullname column to admin table if missing
$adminNameCheck = $conn->query("SHOW COLUMNS FROM admin LIKE 'fullname'");
if ($adminNameCheck && $adminNameCheck->num_rows == 0) {
    $conn->query("ALTER TABLE admin ADD COLUMN fullname VARCHAR(100) NULL DEFAULT NULL AFTER username");
}

// Auto-check and add email column to admin table if missing
$adminEmailCheck = $conn->query("SHOW COLUMNS FROM admin LIKE 'email'");
if ($adminEmailCheck && $adminEmailCheck->num_rows == 0) {
    $conn->query("ALTER TABLE admin ADD COLUMN email VARCHAR(100) NULL DEFAULT NULL AFTER password");
}

// Auto-check and add profile_picture column to admin table if missing
$adminPicCheck = $conn->query("SHOW COLUMNS FROM admin LIKE 'profile_picture'");
if ($adminPicCheck && $adminPicCheck->num_rows == 0) {
    $conn->query("ALTER TABLE admin ADD COLUMN profile_picture VARCHAR(255) NULL DEFAULT NULL AFTER email");
}

// Auto-create password_resets table for forgot password workflow
$conn->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    email VARCHAR(100) NOT NULL,
    token VARCHAR(128) NOT NULL,
    expires_at DATETIME NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_token (token),
    INDEX idx_email (email)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Auto-create email_logs table for outbound email tracking & localhost testing
$conn->query("CREATE TABLE IF NOT EXISTS email_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    recipient VARCHAR(100) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body LONGTEXT NOT NULL,
    sent_status VARCHAR(255) DEFAULT 'Pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sent_status (sent_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Ensure sent_status column is VARCHAR(255) to accommodate detailed failure reasons
$conn->query("ALTER TABLE email_logs MODIFY COLUMN sent_status VARCHAR(255) DEFAULT 'Pending'");
$statusIdxCheck = $conn->query("SHOW INDEX FROM email_logs WHERE Key_name = 'idx_sent_status'");
if ($statusIdxCheck && $statusIdxCheck->num_rows == 0) {
    $conn->query("ALTER TABLE email_logs ADD INDEX idx_sent_status (sent_status)");
}

// Auto-check and add is_read column to email_logs table if missing (tracks admin outbox unread badge)
$isReadCheck = $conn->query("SHOW COLUMNS FROM email_logs LIKE 'is_read'");
if ($isReadCheck && $isReadCheck->num_rows == 0) {
    $conn->query("ALTER TABLE email_logs ADD COLUMN is_read TINYINT(1) NOT NULL DEFAULT 0 AFTER sent_status");
}
$isReadIdxCheck = $conn->query("SHOW INDEX FROM email_logs WHERE Key_name = 'idx_is_read'");
if ($isReadIdxCheck && $isReadIdxCheck->num_rows == 0) {
    $conn->query("ALTER TABLE email_logs ADD INDEX idx_is_read (is_read)");
}

/**
 * Format badge counter string with a cap at '9+'
 * 
 * @param int $count
 * @return string
 */
if (!function_exists('formatBadgeCount')) {
    function formatBadgeCount($count) {
        if ($count > 9) return '9+';
        if ($count > 0) return (string)$count;
        return '';
    }
}

/**
 * Get count of unread email logs for admin badge
 * 
 * @param mysqli $conn
 * @return int
 */
if (!function_exists('getUnreadEmailLogsCount')) {
    function getUnreadEmailLogsCount($conn) {
        $res = $conn->query("SELECT COUNT(*) AS c FROM email_logs WHERE is_read = 0");
        return $res ? (int)$res->fetch_assoc()['c'] : 0;
    }
}

/**
 * Get count of unseen tickets across all statuses for customer
 * 
 * @param mysqli $conn
 * @param int $userId
 * @return int
 */
if (!function_exists('getUnseenTicketsCount')) {
    function getUnseenTicketsCount($conn, $userId) {
        $stmt = $conn->prepare("
            SELECT COUNT(*) AS c 
            FROM tickets 
            WHERE user_id = ? 
              AND (customer_viewed_at IS NULL 
                   OR GREATEST(
                       COALESCE(responded_at, '1970-01-01 00:00:00'),
                       COALESCE(in_progress_at, '1970-01-01 00:00:00'),
                       COALESCE(reopened_at, '1970-01-01 00:00:00')
                   ) > customer_viewed_at)
        ");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $cnt = (int)$stmt->get_result()->fetch_assoc()['c'];
        $stmt->close();
        return $cnt;
    }
}

/**
 * Get start and end timestamp of 'this week' (Monday 00:00:00 to Sunday 23:59:59, server timezone)
 *
 * @return array [string $start, string $end]
 */
if (!function_exists('getThisWeekRange')) {
    function getThisWeekRange() {
        $monday = date('Y-m-d 00:00:00', strtotime('monday this week'));
        $sunday = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        return [$monday, $sunday];
    }
}
?>