<?php
/**
 * logout.php
 * Proses logout: hapus session dan redirect ke login
 */
require_once __DIR__ . '/includes/auth.php';
logout();
header("Location: login.php");
exit;
