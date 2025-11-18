<?php
session_start();

if (!isset($_SESSION['flash'])) {
    $_SESSION['flash'] = [];
}

// Session timeout check
if (isLoggedIn()) {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
        session_destroy();
        redirect(APP_URL . '/auth/login.php');
    }
    $_SESSION['last_activity'] = time();
}
?>
