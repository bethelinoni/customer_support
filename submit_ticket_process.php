<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$subject = trim($_POST['subject']);
$message = trim($_POST['message']);

// Validate Category & Priority
$allowedCategories = ['Technical Support', 'Billing & Payments', 'Account & Security', 'Customer Service', 'General Inquiry'];
$category = isset($_POST['category']) && in_array($_POST['category'], $allowedCategories) ? $_POST['category'] : 'General Inquiry';

$allowedPriorities = ['Low', 'Medium', 'High', 'Urgent'];
$priority = isset($_POST['priority']) && in_array($_POST['priority'], $allowedPriorities) ? $_POST['priority'] : 'Medium';

// Basic validation
if (empty($subject) || empty($message)) {
    header("Location: submit_ticket.php?error=Please complete all fields.");
    exit();
}

// Handle Attachment (JPG, JPEG, PNG - Max 5MB)
$attachment = null;

if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
    if ($_FILES['attachment']['error'] !== UPLOAD_ERR_OK) {
        header("Location: submit_ticket.php?error=Error uploading file. Please try again.");
        exit();
    }

    $fileTmp = $_FILES['attachment']['tmp_name'];
    $fileName = $_FILES['attachment']['name'];
    $fileSize = $_FILES['attachment']['size'];

    // Check size limit: 5MB
    if ($fileSize > 5 * 1024 * 1024) {
        header("Location: submit_ticket.php?error=File size exceeds 5MB limit.");
        exit();
    }

    // Validate extension
    $allowedExts = ['jpg', 'jpeg', 'png'];
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedExts)) {
        header("Location: submit_ticket.php?error=Only JPG, JPEG, and PNG images are allowed.");
        exit();
    }

    // Validate MIME type
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $fileTmp);
    finfo_close($finfo);

    $allowedMimes = ['image/jpeg', 'image/png'];
    if (!in_array($mimeType, $allowedMimes)) {
        header("Location: submit_ticket.php?error=Invalid image file format.");
        exit();
    }

    // Ensure uploads folder exists
    $uploadDir = __DIR__ . '/uploads/';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    $newFileName = 'ticket_' . $user_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
    $destination = $uploadDir . $newFileName;

    if (move_uploaded_file($fileTmp, $destination)) {
        $attachment = $newFileName;
    } else {
        header("Location: submit_ticket.php?error=Failed to save uploaded image. Please try again.");
        exit();
    }
}

// Insert complaint using a prepared statement
$stmt = $conn->prepare("INSERT INTO tickets (user_id, subject, category, priority, message, attachment) VALUES (?, ?, ?, ?, ?, ?)");
$stmt->bind_param("isssss", $user_id, $subject, $category, $priority, $message, $attachment);

if ($stmt->execute()) {
    $ticketId = $stmt->insert_id;

    // Send confirmation email to customer
    require_once __DIR__ . '/mailer.php';
    $uStmt = $conn->prepare("SELECT email, fullname FROM users WHERE id = ?");
    $uStmt->bind_param("i", $user_id);
    $uStmt->execute();
    $uRes = $uStmt->get_result()->fetch_assoc();
    $uStmt->close();

    if ($uRes && !empty($uRes['email'])) {
        sendTicketSubmittedEmail($uRes['email'], $uRes['fullname'], $ticketId, $subject, $category, $priority);
    }

    // Send notification email to support admin
    $custName = ($uRes && !empty($uRes['fullname'])) ? $uRes['fullname'] : 'Customer';
    $custEmail = ($uRes && !empty($uRes['email'])) ? $uRes['email'] : 'customer@support';
    sendAdminNewTicketEmail($ticketId, $subject, $category, $priority, $custName, $custEmail);

    header("Location: submit_ticket.php?success=Your complaint has been submitted successfully.");
    exit();
} else {

    header("Location: submit_ticket.php?error=Unable to submit complaint. Please try again.");
    exit();

}

$stmt->close();
$conn->close();
?>