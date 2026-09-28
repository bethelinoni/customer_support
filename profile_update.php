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
    $fullname = trim($_POST['fullname'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($fullname)) {
        header("Location: profile.php?error=" . urlencode("Full name cannot be empty."));
        exit();
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: profile.php?error=" . urlencode("Please enter a valid email address."));
        exit();
    }

    // Check duplicate email
    $checkStmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
    $checkStmt->bind_param("si", $email, $user_id);
    $checkStmt->execute();
    $checkStmt->store_result();

    if ($checkStmt->num_rows > 0) {
        $checkStmt->close();
        header("Location: profile.php?error=" . urlencode("This email address is already registered to another account."));
        exit();
    }
    $checkStmt->close();

    // Optional profile picture upload
    $newProfilePic = null;
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['profile_photo'];
        if ($file['size'] <= 3 * 1024 * 1024) {
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (in_array($ext, $allowedExtensions)) {
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mime = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                if (in_array($mime, $allowedMimes)) {
                    $uploadDir = __DIR__ . '/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'avatar_usr_' . $user_id . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        $newProfilePic = $filename;
                        // Remove old avatar if exists
                        $oldCheck = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
                        $oldCheck->bind_param("i", $user_id);
                        $oldCheck->execute();
                        $oldRes = $oldCheck->get_result()->fetch_assoc();
                        $oldCheck->close();
                        if (!empty($oldRes['profile_picture']) && file_exists($uploadDir . $oldRes['profile_picture'])) {
                            @unlink($uploadDir . $oldRes['profile_picture']);
                        }
                    }
                }
            }
        }
    }

    // Update user profile
    if ($newProfilePic !== null) {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ?, profile_picture = ? WHERE id = ?");
        $stmt->bind_param("ssssi", $fullname, $email, $phone, $newProfilePic, $user_id);
    } else {
        $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
        $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
    }

    if ($stmt->execute()) {
        $_SESSION['fullname'] = $fullname;
        $stmt->close();
        header("Location: profile.php?success=" . urlencode("Your profile details have been successfully updated."));
        exit();
    } else {
        $stmt->close();
        header("Location: profile.php?error=" . urlencode("Failed to update profile. Please try again."));
        exit();
    }
} else {
    header("Location: profile.php");
    exit();
}
?>
