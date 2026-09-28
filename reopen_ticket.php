<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id       = intval($_SESSION['user_id']);
    $ticket_id     = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $reopen_reason = isset($_POST['reopen_reason']) ? trim($_POST['reopen_reason']) : '';

    if ($ticket_id <= 0) {
        header("Location: view_tickets.php?error=" . urlencode("Invalid ticket ID."));
        exit();
    }

    if (empty($reopen_reason)) {
        header("Location: view_tickets.php?error=" . urlencode("Please provide a reason for reopening this ticket."));
        exit();
    }

    // Check ownership and ensure status is Resolved
    $checkStmt = $conn->prepare("SELECT tickets.id, tickets.subject, tickets.status, users.email, users.fullname FROM tickets JOIN users ON tickets.user_id = users.id WHERE tickets.id = ? AND tickets.user_id = ?");
    $checkStmt->bind_param("ii", $ticket_id, $user_id);
    $checkStmt->execute();
    $res = $checkStmt->get_result();

    if ($res->num_rows === 0) {
        header("Location: view_tickets.php?error=" . urlencode("Ticket not found or unauthorized access."));
        exit();
    }

    $ticket = $res->fetch_assoc();
    $checkStmt->close();

    if ($ticket['status'] !== 'Resolved') {
        header("Location: view_tickets.php?error=" . urlencode("Only resolved tickets can be reopened."));
        exit();
    }

    // Set status to Reopened, record reopen_reason and reopened_at
    $stmt = $conn->prepare("
        UPDATE tickets 
        SET status = 'Reopened', reopen_reason = ?, reopened_at = NOW() 
        WHERE id = ? AND user_id = ? AND status = 'Resolved'
    ");
    $stmt->bind_param("sii", $reopen_reason, $ticket_id, $user_id);

    if ($stmt->execute()) {
        $stmt->close();

        // Dispatch confirmation email to customer
        require_once __DIR__ . '/mailer.php';
        if (!empty($ticket['email'])) {
            sendTicketReopenedEmail($ticket['email'], $ticket['fullname'], $ticket_id, $ticket['subject'], $reopen_reason);
        }

        // Dispatch alert email to support admin
        $custName = !empty($ticket['fullname']) ? $ticket['fullname'] : 'Customer';
        sendAdminTicketReopenedEmail($ticket_id, $ticket['subject'], $reopen_reason, $custName);

        header("Location: view_tickets.php?ticket_id={$ticket_id}&success=" . urlencode("Your complaint has been reopened and placed back into the priority support queue.") . "#ticket-{$ticket_id}");
        exit();
    } else {
        $stmt->close();
        header("Location: view_tickets.php?error=" . urlencode("Unable to reopen ticket. Please try again."));
        exit();
    }
} else {
    header("Location: view_tickets.php");
    exit();
}
?>
