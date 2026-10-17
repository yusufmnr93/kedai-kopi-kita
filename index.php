<?php
/**
 * index.php
 * Entry point: redirect ke dashboard jika sudah login, atau ke login
 */
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) {
    header("Location: dashboard.php");
} else {
    header("Location: login.php");
}
exit;
