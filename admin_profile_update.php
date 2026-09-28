<?php
session_start();
include 'connect.php';

if (!isset($_SESSION['admin'])) {
    header("Location: login.php?type=admin");
    exit();
}

$adminUsername = $_SESSION['admin'];

// Fetch current admin record
$stmt = $conn->prepare("SELECT id, username, fullname, email, password, profile_picture FROM admin WHERE username = ?");
$stmt->bind_param("s", $adminUsername);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$admin) {
    header("Location: logout.php");
    exit();
}

$adminId = (int)$admin['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_info';

    // 1. Remove profile photo
    if ($action === 'remove_photo') {
        if (!empty($admin['profile_picture'])) {
            $oldPath = __DIR__ . '/uploads/' . $admin['profile_picture'];
            if (file_exists($oldPath)) {
                @unlink($oldPath);
            }
        }
        $up = $conn->prepare("UPDATE admin SET profile_picture = NULL WHERE id = ?");
        $up->bind_param("i", $adminId);
        $up->execute();
        $up->close();

        header("Location: admin_profile.php?success=" . urlencode("Admin profile picture removed successfully."));
        exit();
    }

    // 2. Quick upload photo via avatar camera trigger
    if ($action === 'upload_photo') {
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];

            if ($file['size'] > 3 * 1024 * 1024) {
                header("Location: admin_profile.php?error=" . urlencode("Image must be smaller than 3MB."));
                exit();
            }

            $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            if (!in_array($ext, $allowedExtensions)) {
                header("Location: admin_profile.php?error=" . urlencode("Invalid format. Please upload JPG, PNG, WEBP, or GIF."));
                exit();
            }

            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);

            $allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            if (!in_array($mime, $allowedMimes)) {
                header("Location: admin_profile.php?error=" . urlencode("Uploaded file is not a valid image."));
                exit();
            }

            $uploadDir = __DIR__ . '/uploads/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $filename = 'avatar_admin_' . $adminId . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                if (!empty($admin['profile_picture']) && file_exists($uploadDir . $admin['profile_picture'])) {
                    @unlink($uploadDir . $admin['profile_picture']);
                }
                $up = $conn->prepare("UPDATE admin SET profile_picture = ? WHERE id = ?");
                $up->bind_param("si", $filename, $adminId);
                $up->execute();
                $up->close();

                header("Location: admin_profile.php?success=" . urlencode("Admin profile picture updated successfully."));
                exit();
            } else {
                header("Location: admin_profile.php?error=" . urlencode("Failed to save uploaded image."));
                exit();
            }
        } else {
            header("Location: admin_profile.php?error=" . urlencode("No valid image was selected."));
            exit();
        }
    }

    // 3. Update account information (Full Name, Username, Notification Email, optional photo)
    if ($action === 'update_info') {
        $fullname = trim($_POST['fullname'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');

        if (empty($username)) {
            header("Location: admin_profile.php?error=" . urlencode("Username cannot be empty."));
            exit();
        }

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            header("Location: admin_profile.php?error=" . urlencode("Please enter a valid notification email address."));
            exit();
        }

        // Check if username is already taken by another admin
        $chk = $conn->prepare("SELECT id FROM admin WHERE username = ? AND id != ?");
        $chk->bind_param("si", $username, $adminId);
        $chk->execute();
        if ($chk->get_result()->num_rows > 0) {
            $chk->close();
            header("Location: admin_profile.php?error=" . urlencode("That username is already in use by another administrator."));
            exit();
        }
        $chk->close();

        // Optional photo attached in form
        $newPhoto = null;
        if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['profile_photo'];
            if ($file['size'] <= 3 * 1024 * 1024) {
                $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                $allowed = ['jpg', 'jpeg', 'png', 'webp', 'gif'];
                if (in_array($ext, $allowed)) {
                    $uploadDir = __DIR__ . '/uploads/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    $filename = 'avatar_admin_' . $adminId . '_' . time() . '.' . $ext;
                    if (move_uploaded_file($file['tmp_name'], $uploadDir . $filename)) {
                        $newPhoto = $filename;
                        if (!empty($admin['profile_picture']) && file_exists($uploadDir . $admin['profile_picture'])) {
                            @unlink($uploadDir . $admin['profile_picture']);
                        }
                    }
                }
            }
        }

        if ($newPhoto !== null) {
            $stmt = $conn->prepare("UPDATE admin SET fullname = ?, username = ?, email = ?, profile_picture = ? WHERE id = ?");
            $stmt->bind_param("ssssi", $fullname, $username, $email, $newPhoto, $adminId);
        } else {
            $stmt = $conn->prepare("UPDATE admin SET fullname = ?, username = ?, email = ? WHERE id = ?");
            $stmt->bind_param("sssi", $fullname, $username, $email, $adminId);
        }

        if ($stmt->execute()) {
            $_SESSION['admin'] = $username;
            $stmt->close();
            header("Location: admin_profile.php?success=" . urlencode("Administrator profile updated successfully."));
            exit();
        } else {
            $stmt->close();
            header("Location: admin_profile.php?error=" . urlencode("Failed to update profile. Please try again."));
            exit();
        }
    }

    // 4. Update Password
    if ($action === 'update_password') {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPassword, $admin['password'])) {
            header("Location: admin_profile.php?error=" . urlencode("The current password entered is incorrect."));
            exit();
        }

        if (strlen($newPassword) < 6) {
            header("Location: admin_profile.php?error=" . urlencode("New password must be at least 6 characters long."));
            exit();
        }

        if ($newPassword !== $confirmPassword) {
            header("Location: admin_profile.php?error=" . urlencode("New password and confirm password do not match."));
            exit();
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $up = $conn->prepare("UPDATE admin SET password = ? WHERE id = ?");
        $up->bind_param("si", $hashed, $adminId);

        if ($up->execute()) {
            $up->close();
            header("Location: admin_profile.php?success=" . urlencode("Administrator password changed successfully."));
            exit();
        } else {
            $up->close();
            header("Location: admin_profile.php?error=" . urlencode("Failed to change password. Please try again."));
            exit();
        }
    }
}

header("Location: admin_profile.php");
exit();
