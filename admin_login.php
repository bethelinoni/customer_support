<?php
session_start();

// Forward all incoming query parameters (error, success, etc.) to the merged login page with type=admin
$params = $_GET;
$params['type'] = 'admin';

$queryString = http_build_query($params);
$redirectUrl = 'login.php?' . $queryString;

header("Location: " . $redirectUrl, true, 302);
exit();