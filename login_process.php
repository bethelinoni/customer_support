<?php
session_start();
include 'connect.php';

$email = trim($_POST['email']);
$password = $_POST['password'];

$stmt = $conn->prepare("SELECT id, fullname, password FROM users WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();

$result = $stmt->get_result();

if ($result->num_rows === 1) {

    $user = $result->fetch_assoc();

    if (password_verify($password, $user['password'])) {

        $_SESSION['user_id'] = $user['id'];
        $_SESSION['fullname'] = $user['fullname'];

        $allowedRedirects = ['submit_ticket.php', 'view_tickets.php', 'dashboard.php'];
        $returnTo = isset($_POST['return_to']) ? trim($_POST['return_to']) : '';
        $target = in_array($returnTo, $allowedRedirects, true) ? $returnTo : 'dashboard.php';

        header("Location: " . $target);
        exit();

    }

}

$errorRedirect = "login.php?error=Invalid email or password";
if (!empty($_POST['return_to']) && in_array(trim($_POST['return_to']), ['submit_ticket.php', 'view_tickets.php', 'dashboard.php'], true)) {
    $errorRedirect .= "&return_to=" . urlencode(trim($_POST['return_to']));
}
header("Location: " . $errorRedirect);
exit();
?>