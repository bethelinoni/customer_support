<?php
session_start();
include 'connect.php';

$username = trim($_POST['username']);
$password = $_POST['password'];

// Find the admin by username
$stmt = $conn->prepare("SELECT id, username, password FROM admin WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $admin = $result->fetch_assoc();

    if (password_verify($password, $admin['password'])) {

        $_SESSION['admin'] = $admin['username'];
        $_SESSION['admin_id'] = $admin['id'];

        header("Location: admin_dashboard.php");
        exit();

    } else {

        header("Location: login.php?type=admin&error=Invalid username or password");
        exit();

    }

} else {

    header("Location: login.php?type=admin&error=Invalid username or password");
    exit();

}

$stmt->close();
$conn->close();
?>