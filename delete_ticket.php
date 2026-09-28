<?php
session_start();
include 'connect.php';

// User must be logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Make sure an ID was provided
if (!isset($_GET['id'])) {
    header("Location: view_tickets.php");
    exit();
}

$ticket_id = $_GET['id'];

// Retrieve attachment before deleting to clean up filesystem
$findStmt = $conn->prepare("SELECT attachment FROM tickets WHERE id = ? AND user_id = ? AND status = 'Pending'");
$findStmt->bind_param("ii", $ticket_id, $user_id);
$findStmt->execute();
$findResult = $findStmt->get_result();

if ($findResult->num_rows === 1) {
    $ticket = $findResult->fetch_assoc();
    $attachment = $ticket['attachment'];

    $delStmt = $conn->prepare("DELETE FROM tickets WHERE id = ? AND user_id = ? AND status = 'Pending'");
    $delStmt->bind_param("ii", $ticket_id, $user_id);

    if ($delStmt->execute()) {
        // Remove file from disk if exists
        if (!empty($attachment)) {
            $filePath = __DIR__ . '/uploads/' . $attachment;
            if (file_exists($filePath)) {
                unlink($filePath);
            }
        }

        header("Location: view_tickets.php?success=Complaint deleted successfully");
        exit();
    } else {
        header("Location: view_tickets.php?error=Unable to delete complaint");
        exit();
    }
} else {
    header("Location: view_tickets.php?error=Complaint not found or cannot be deleted");
    exit();
}
$conn->close();
?>