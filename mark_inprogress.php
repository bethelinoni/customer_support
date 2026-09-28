<?php
session_start();
include 'connect.php';

// Only authenticated admins can update status to In Progress
if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;

    if ($ticket_id <= 0) {
        header("Location: admin_dashboard.php?error=" . urlencode("Invalid ticket ID provided."));
        exit();
    }

    // Verify ticket exists and fetch customer info
    $checkStmt = $conn->prepare("SELECT tickets.id, tickets.subject, tickets.status, users.email, users.fullname FROM tickets JOIN users ON tickets.user_id = users.id WHERE tickets.id = ?");
    $checkStmt->bind_param("i", $ticket_id);
    $checkStmt->execute();
    $ticket = $checkStmt->get_result()->fetch_assoc();
    $checkStmt->close();

    if (!$ticket) {
        header("Location: admin_dashboard.php?error=" . urlencode("Ticket not found."));
        exit();
    }

    if ($ticket['status'] === 'Resolved') {
        header("Location: admin_dashboard.php?error=" . urlencode("Cannot mark an already resolved ticket as In Progress."));
        exit();
    }

    // Update status to 'In Progress' and record timestamp
    $stmt = $conn->prepare("UPDATE tickets SET status = 'In Progress', in_progress_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $ticket_id);

    if ($stmt->execute()) {
        $stmt->close();

        // Dispatch in-progress email notification to customer
        require_once __DIR__ . '/mailer.php';
        if (!empty($ticket['email'])) {
            sendTicketInProgressEmail($ticket['email'], $ticket['fullname'], $ticket_id, $ticket['subject']);
        }

        header("Location: admin_dashboard.php?success=" . urlencode("Ticket #TKT-" . sprintf("%05d", $ticket_id) . " has been marked as In Progress."));
        exit();
    } else {
        $stmt->close();
        header("Location: admin_dashboard.php?error=" . urlencode("Failed to update ticket status."));
        exit();
    }
} else {
    header("Location: admin_dashboard.php");
    exit();
}
?>
