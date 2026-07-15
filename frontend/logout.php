<?php
// logout.php
require_once 'includes/config.php';
require_once 'includes/functions.php';
require_once 'includes/auth.php';

// Log aktivitas logout
if (isLoggedIn()) {
    logUserActivity('Logout');
}

// Destroy session
destroyUserSession();

// Redirect ke login dengan pesan
$_SESSION['success'] = 'Anda telah berhasil logout.';
redirect('login.php');
exit();
?>