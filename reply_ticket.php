<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $ticket_id = intval($_POST['ticket_id']);
    $response = trim($_POST['response']);

    if (empty($response)) {
        header("Location: admin_dashboard.php?error=Response message cannot be empty.");
        exit();
    }

    // Handle Admin Attachment (JPG, JPEG, PNG - Max 5MB)
    $admin_attachment = null;

    if (isset($_FILES['admin_attachment']) && $_FILES['admin_attachment']['error'] !== UPLOAD_ERR_NO_FILE) {
        if ($_FILES['admin_attachment']['error'] !== UPLOAD_ERR_OK) {
            header("Location: admin_dashboard.php?error=Error uploading response attachment.");
            exit();
        }

        $fileTmp = $_FILES['admin_attachment']['tmp_name'];
        $fileName = $_FILES['admin_attachment']['name'];
        $fileSize = $_FILES['admin_attachment']['size'];

        // Size check: 5MB
        if ($fileSize > 5 * 1024 * 1024) {
            header("Location: admin_dashboard.php?error=Attachment exceeds 5MB size limit.");
            exit();
        }

        // Extension check
        $allowedExts = ['jpg', 'jpeg', 'png'];
        $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            header("Location: admin_dashboard.php?error=Only JPG, JPEG, and PNG images are allowed.");
            exit();
        }

        // MIME check
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $fileTmp);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png'];
        if (!in_array($mimeType, $allowedMimes)) {
            header("Location: admin_dashboard.php?error=Invalid image file format.");
            exit();
        }

        // Ensure upload directory
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newFileName = 'admin_reply_' . $ticket_id . '_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destination = $uploadDir . $newFileName;

        if (move_uploaded_file($fileTmp, $destination)) {
            $admin_attachment = $newFileName;
        } else {
            header("Location: admin_dashboard.php?error=Failed to save uploaded image.");
            exit();
        }
    }

    // Fetch existing response to preserve interaction history upon reopening
    $fetchStmt = $conn->prepare("SELECT response, responded_at, admin_attachment, response_history, reopen_reason, reopened_at FROM tickets WHERE id = ?");
    $fetchStmt->bind_param("i", $ticket_id);
    $fetchStmt->execute();
    $existing = $fetchStmt->get_result()->fetch_assoc();
    $fetchStmt->close();

    $response_history = null;
    if ($existing && !empty($existing['response'])) {
        $history = [];
        if (!empty($existing['response_history'])) {
            $decoded = json_decode($existing['response_history'], true);
            if (is_array($decoded)) {
                $history = $decoded;
            }
        }
        $history[] = [
            'response' => $existing['response'],
            'responded_at' => $existing['responded_at'],
            'admin_attachment' => $existing['admin_attachment'],
            'reopen_reason' => $existing['reopen_reason'],
            'reopened_at' => $existing['reopened_at']
        ];
        $response_history = json_encode($history);
    } elseif ($existing && !empty($existing['response_history'])) {
        $response_history = $existing['response_history'];
    }

    if ($admin_attachment !== null) {
        $stmt = $conn->prepare("
            UPDATE tickets
            SET
                response = ?,
                admin_attachment = ?,
                response_history = ?,
                status = 'Resolved',
                responded_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("sssi", $response, $admin_attachment, $response_history, $ticket_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE tickets
            SET
                response = ?,
                response_history = ?,
                status = 'Resolved',
                responded_at = NOW()
            WHERE id = ?
        ");
        $stmt->bind_param("ssi", $response, $response_history, $ticket_id);
    }

    if ($stmt->execute()) {
        $stmt->close();

        // Dispatch resolution email to customer
        require_once __DIR__ . '/mailer.php';
        $custStmt = $conn->prepare("SELECT tickets.subject, users.email, users.fullname FROM tickets JOIN users ON tickets.user_id = users.id WHERE tickets.id = ?");
        $custStmt->bind_param("i", $ticket_id);
        $custStmt->execute();
        $cust = $custStmt->get_result()->fetch_assoc();
        $custStmt->close();

        if ($cust && !empty($cust['email'])) {
            sendTicketResolvedEmail($cust['email'], $cust['fullname'], $ticket_id, $cust['subject'], $response);
        }

        header("Location: admin_dashboard.php?success=Response sent successfully.");
        exit();
    } else {
        $stmt->close();
        header("Location: admin_dashboard.php?error=Unable to send response.");
        exit();
    }
}
?>