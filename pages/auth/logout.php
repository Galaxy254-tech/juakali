<?php
session_start();
session_destroy();
header('Location: ' . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '/juakali-lend/'));
exit;
?>
