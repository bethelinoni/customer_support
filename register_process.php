<?php
session_start();
include 'connect.php';

$fullname = trim($_POST['fullname']);
$email = trim($_POST['email']);
$password = $_POST['password'];

/*
|--------------------------------------------------------------------------
| INPUT VALIDATION
|--------------------------------------------------------------------------
*/

if(empty($fullname)){

    header("Location: register.php?error=Full name is required");
    exit();

}

if(!filter_var($email, FILTER_VALIDATE_EMAIL)){

    header("Location: register.php?error=Please enter a valid email address&fullname="
        .urlencode($fullname).
        "&email=".urlencode($email));
    exit();

}

if(strlen($password) < 6){

    header("Location: register.php?error=Password must be at least 6 characters&fullname="
        .urlencode($fullname).
        "&email=".urlencode($email));
    exit();

}

$password = password_hash($password, PASSWORD_DEFAULT);

/*
|--------------------------------------------------------------------------
| CHECK DUPLICATE EMAIL
|--------------------------------------------------------------------------
*/

$check = $conn->prepare("SELECT id FROM users WHERE email=?");
$check->bind_param("s",$email);
$check->execute();
$check->store_result();

if($check->num_rows > 0){

    header("Location: register.php?error=Email already exists&fullname="
        .urlencode($fullname).
        "&email=".urlencode($email));
    exit();

}

$check->close();

/*
|--------------------------------------------------------------------------
| INSERT USER
|--------------------------------------------------------------------------
*/

$stmt = $conn->prepare("INSERT INTO users(fullname,email,password) VALUES(?,?,?)");

$stmt->bind_param("sss",$fullname,$email,$password);

if($stmt->execute()){

    session_unset();
    session_destroy();

    $allowedRedirects = ['submit_ticket.php', 'view_tickets.php', 'dashboard.php'];
    $returnTo = isset($_POST['return_to']) ? trim($_POST['return_to']) : '';
    $cleanReturnTo = in_array($returnTo, $allowedRedirects, true) ? $returnTo : '';

    $successUrl = "login.php?success=Account created successfully. Please log in.";
    if ($cleanReturnTo) {
        $successUrl .= "&return_to=" . urlencode($cleanReturnTo);
    }
    header("Location: " . $successUrl);
    exit();

}else{

    $errorUrl = "register.php?error=Registration failed. Please try again.";
    if (!empty($_POST['return_to']) && in_array(trim($_POST['return_to']), ['submit_ticket.php', 'view_tickets.php', 'dashboard.php'], true)) {
        $errorUrl .= "&return_to=" . urlencode(trim($_POST['return_to']));
    }
    header("Location: " . $errorUrl);
    exit();

}

$stmt->close();
$conn->close();

?>