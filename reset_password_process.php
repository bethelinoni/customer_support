<?php
session_start();
require_once __DIR__ . '/connect.php';

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $token = isset($_POST['token']) ? trim($_POST['token']) : '';
    $email = isset($_POST['email']) ? trim($_POST['email']) : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';

    $redirectErrorUrl = "reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email) . "&error=";

    if (empty($token) || empty($email)) {
        header("Location: forgot_password.php?error=" . urlencode("Session expired. Please request a new reset link."));
        exit();
    }

    // Verify token validity
    $stmt = $conn->prepare("SELECT id, expires_at FROM password_resets WHERE token = ? AND email = ?");
    $stmt->bind_param("ss", $token, $email);
    $stmt->execute();
    $resetRecord = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$resetRecord || strtotime($resetRecord['expires_at']) < time()) {
        header("Location: reset_password.php?token=" . urlencode($token) . "&email=" . urlencode($email));
        exit();
    }

    // Validate password match
    if ($newPassword !== $confirmPassword) {
        header("Location: " . $redirectErrorUrl . urlencode("Passwords do not match. Please try again."));
        exit();
    }

    // Validate password length
    if (strlen($newPassword) < 6) {
        header("Location: " . $redirectErrorUrl . urlencode("Password must be at least 6 characters long."));
        exit();
    }

    // Securely hash the new password
    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

    // Update the password in users table
    $upStmt = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
    $upStmt->bind_param("ss", $hashedPassword, $email);

    if ($upStmt->execute()) {
        $upStmt->close();

        // Invalidate all tokens for this email to prevent reuse
        $delStmt = $conn->prepare("DELETE FROM password_resets WHERE email = ?");
        $delStmt->bind_param("s", $email);
        $delStmt->execute();
        $delStmt->close();

        header("Location: login.php?success=" . urlencode("Password has been reset successfully! You can now log in with your new password."));
        exit();
    } else {
        $upStmt->close();
        header("Location: " . $redirectErrorUrl . urlencode("Failed to update password. Please try again."));
        exit();
    }
} else {
    header("Location: forgot_password.php");
    exit();
}
?>
