<?php
session_start();
require_once __DIR__ . '/connect.php';
require_once __DIR__ . '/mailer.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';

    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=" . urlencode("Please enter a valid email address."));
        exit();
    }

    // Check if customer exists in the users table
    $stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($user) {
        // Generate a cryptographically secure 64-character token
        $token = bin2hex(random_bytes(32));

        // Invalidate any older reset requests for this email
        $delStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $delStmt->bind_param("s", $email);
        $delStmt->execute();
        $delStmt->close();

        // Insert new token with 1-hour expiration
        $insStmt = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR))");
        $insStmt->bind_param("ss", $email, $token);
        $insStmt->execute();
        $insStmt->close();

        // Construct secure password reset URL
        $resetLink = "http://localhost/tega_project/reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email);

        // Send reset email via mailer
        sendPasswordResetEmail($user['email'], $user['fullname'], $resetLink);
    }

    // Always display the same friendly message for security (anti-enumeration)
    header("Location: forgot_password.php?success=" . urlencode("If that email is registered, a reset link has been sent. Please check your inbox."));
    exit();
} else {
    header("Location: forgot_password.php");
    exit();
}
?>
