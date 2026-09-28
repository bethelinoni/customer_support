<?php
session_start();
require_once __DIR__ . '/connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id   = intval($_SESSION['user_id']);
    $ticket_id = isset($_POST['ticket_id']) ? intval($_POST['ticket_id']) : 0;
    $action    = isset($_POST['action']) ? trim($_POST['action']) : 'rate';

    if ($ticket_id <= 0) {
        header("Location: view_tickets.php?error=" . urlencode("Invalid ticket ID."));
        exit();
    }

    // Verify ticket ownership and ensure status is Resolved
    $checkStmt = $conn->prepare("SELECT id, status, rating, rated_at FROM tickets WHERE id = ? AND user_id = ?");
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
        header("Location: view_tickets.php?ticket_id={$ticket_id}#ticket-{$ticket_id}&error=" . urlencode("Ratings can only be submitted, changed, or removed for resolved complaints."));
        exit();
    }

    // Action 1: Remove Rating
    if ($action === 'remove') {
        $stmt = $conn->prepare("
            UPDATE tickets 
            SET rating = NULL, feedback = NULL, rated_at = NULL, rating_updated_at = NULL 
            WHERE id = ? AND user_id = ? AND status = 'Resolved'
        ");
        $stmt->bind_param("ii", $ticket_id, $user_id);

        if ($stmt->execute()) {
            $stmt->close();
            header("Location: view_tickets.php?ticket_id={$ticket_id}#ticket-{$ticket_id}&success=" . urlencode("Your rating has been removed."));
            exit();
        } else {
            $stmt->close();
            header("Location: view_tickets.php?ticket_id={$ticket_id}#ticket-{$ticket_id}&error=" . urlencode("Unable to remove rating. Please try again."));
            exit();
        }
    }

    // Action 2: Add or Update Rating
    $rating   = isset($_POST['rating']) ? intval($_POST['rating']) : 0;
    $feedback = isset($_POST['feedback']) ? trim($_POST['feedback']) : '';

    if ($rating < 1 || $rating > 5) {
        header("Location: view_tickets.php?ticket_id={$ticket_id}#ticket-{$ticket_id}&error=" . urlencode("Please select a valid rating between 1 and 5 stars."));
        exit();
    }

    // Check if this is an update to an existing rating
    $isUpdate = ($action === 'update') || (!empty($ticket['rating']) && (int)$ticket['rating'] > 0);

    if ($isUpdate) {
        // Changing existing rating: preserve original rated_at, set rating_updated_at = NOW()
        $stmt = $conn->prepare("
            UPDATE tickets 
            SET rating = ?, feedback = ?, rating_updated_at = NOW() 
            WHERE id = ? AND user_id = ? AND status = 'Resolved'
        ");
        $stmt->bind_param("isii", $rating, $feedback, $ticket_id, $user_id);
        $successMsg = "Your satisfaction rating has been updated successfully.";
    } else {
        // Initial rating: set rated_at = NOW(), rating_updated_at = NULL
        $stmt = $conn->prepare("
            UPDATE tickets 
            SET rating = ?, feedback = ?, rated_at = NOW(), rating_updated_at = NULL 
            WHERE id = ? AND user_id = ? AND status = 'Resolved'
        ");
        $stmt->bind_param("isii", $rating, $feedback, $ticket_id, $user_id);
        $successMsg = "Thank you! Your satisfaction feedback has been recorded.";
    }

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: view_tickets.php?ticket_id={$ticket_id}#ticket-{$ticket_id}&success=" . urlencode($successMsg));
        exit();
    } else {
        $stmt->close();
        header("Location: view_tickets.php?ticket_id={$ticket_id}#ticket-{$ticket_id}&error=" . urlencode("Unable to save rating. Please try again."));
        exit();
    }
} else {
    header("Location: view_tickets.php");
    exit();
}
