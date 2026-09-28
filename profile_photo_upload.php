<?php
session_start();
include 'connect.php';

// Authentication check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = intval($_SESSION['user_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Remove profile photo
    if (isset($_POST['action']) && $_POST['action'] === 'remove') {
        $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $res = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!empty($res['profile_picture'])) {
            $oldFile = __DIR__ . '/uploads/' . $res['profile_picture'];
            if (file_exists($oldFile)) {
                @unlink($oldFile);
            }
        }

        $upStmt = $conn->prepare("UPDATE users SET profile_picture = NULL WHERE id = ?");
        $upStmt->bind_param("i", $user_id);
        $upStmt->execute();
        $upStmt->close();

        header("Location: profile.php?success=" . urlencode("Profile picture removed successfully."));
        exit();
    }

    // 2. Upload new profile photo
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] !== UPLOAD_ERR_NO_FILE) {
        $file = $_FILES['profile_photo'];

        if ($file['error'] !== UPLOAD_ERR_OK) {
            header("Location: profile.php?error=" . urlencode("File upload error occurred. Please try again."));
            exit();
        }

        // Validate file size (max 3MB)
        if ($file['size'] > 3 * 1024 * 1024) {
            header("Location: profile.php?error=" . urlencode("Profile picture must be less than 3MB."));
            exit();
        }

        // Validate image format
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExtensions)) {
            header("Location: profile.php?error=" . urlencode("Invalid file format. Please upload a JPG, PNG, WEBP, or GIF image."));
            exit();
        }

        // Validate mime type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($mime, $allowedMimes)) {
            header("Location: profile.php?error=" . urlencode("Uploaded file is not a valid image."));
            exit();
        }

        // Ensure uploads directory exists
        $uploadDir = __DIR__ . '/uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Generate unique filename
        $newFilename = 'avatar_usr_' . $user_id . '_' . time() . '.' . $ext;
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($file['tmp_name'], $destPath)) {
            // Delete previous avatar file if exists
            $stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $res = $stmt->get_result()->fetch_assoc();
            $stmt->close();

            if (!empty($res['profile_picture']) && $res['profile_picture'] !== $newFilename) {
                $oldFile = $uploadDir . $res['profile_picture'];
                if (file_exists($oldFile)) {
                    @unlink($oldFile);
                }
            }

            // Update database record
            $upStmt = $conn->prepare("UPDATE users SET profile_picture = ? WHERE id = ?");
            $upStmt->bind_param("si", $newFilename, $user_id);
            $upStmt->execute();
            $upStmt->close();

            header("Location: profile.php?success=" . urlencode("Profile picture updated successfully."));
            exit();
        } else {
            header("Location: profile.php?error=" . urlencode("Failed to save uploaded image. Please try again."));
            exit();
        }
    }

    header("Location: profile.php?error=" . urlencode("Please choose an image file to upload."));
    exit();
} else {
    header("Location: profile.php");
    exit();
}
