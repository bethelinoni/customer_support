<?php
session_start();
include 'connect.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $user_id = intval($_SESSION['user_id']);
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Validation
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        header("Location: profile.php?error=" . urlencode("All password fields are required."));
        exit();
    }

    if (strlen($new_password) < 6) {
        header("Location: profile.php?error=" . urlencode("New password must be at least 6 characters in length."));
        exit();
    }

    if ($new_password !== $confirm_password) {
        header("Location: profile.php?error=" . urlencode("New password and confirmation password do not match."));
        exit();
    }

    // Retrieve user's current password hash
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows !== 1) {
        $stmt->close();
        header("Location: login.php?error=" . urlencode("User session invalid. Please log in again."));
        exit();
    }

    $user = $result->fetch_assoc();
    $stmt->close();

    // Verify current password
    if (!password_verify($current_password, $user['password'])) {
        header("Location: profile.php?error=" . urlencode("The current password you entered is incorrect."));
        exit();
    }

    // Ensure new password is not identical to current password
    if (password_verify($new_password, $user['password'])) {
        header("Location: profile.php?error=" . urlencode("Your new password cannot be the same as your current password."));
        exit();
    }

    // Hash new password and update
    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
    $updateStmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $updateStmt->bind_param("si", $hashed_password, $user_id);

    if ($updateStmt->execute()) {
        $updateStmt->close();
        header("Location: profile.php?success=" . urlencode("Your password has been changed successfully."));
        exit();
    } else {
        $updateStmt->close();
        header("Location: profile.php?error=" . urlencode("Failed to update password. Please try again."));
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>
