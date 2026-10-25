<?php
/**
 * includes/auth.php
 * Fungsi autentikasi, session, dan proteksi halaman
 */

// Mulai session jika belum dimulai
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/helpers.php';

/**
 * Proses login: verifikasi username & password
 * @param string $username
 * @param string $password
 * @return array|false Data pengguna jika berhasil, false jika gagal
 */
function login($username, $password) {
    $pdo = getConnection();
    $stmt = $pdo->prepare("SELECT * FROM pengguna WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    
    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true); // cegah session fixation
        // Simpan data pengguna ke session
        $_SESSION['user_id']   = $user['id_pengguna'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['nama']      = $user['nama_lengkap'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['logged_in'] = true;
        return $user;
    }
    
    return false;
}

/**
 * Proses logout: hapus session
 */
function logout() {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Cek apakah pengguna sudah login
 * @return bool
 */
function isLoggedIn() {
    return isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
}

/**
 * Proteksi halaman: redirect ke login jika belum login
 */
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit;
    }
}

/**
 * Proteksi berdasarkan role
 * @param array $allowedRoles Array role yang diizinkan
 */
function requireRole($allowedRoles) {
    requireLogin();
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        // Redirect ke dashboard jika role tidak sesuai
        $_SESSION['flash_error'] = 'Anda tidak memiliki akses ke halaman tersebut.';
        header("Location: dashboard.php");
        exit;
    }
}

/**
 * Mendapatkan data user yang sedang login
 * @return array
 */
function getCurrentUser() {
    return [
        'id'       => $_SESSION['user_id'] ?? null,
        'username' => $_SESSION['username'] ?? null,
        'nama'     => $_SESSION['nama'] ?? null,
        'role'     => $_SESSION['role'] ?? null,
    ];
}

/**
 * Set flash message (pesan sementara)
 */
function setFlash($type, $message) {
    $_SESSION['flash_' . $type] = $message;
}

/**
 * Ambil dan hapus flash message
 */
function getFlash($type) {
    $key = 'flash_' . $type;
    if (isset($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}
