<?php
/**
 * login.php
 * Halaman login dengan validasi username & password
 * Aktivitas 4 Modul 7: Implementasi Authentication
 */
require_once __DIR__ . '/includes/auth.php';

// Jika sudah login, redirect ke dashboard
if (isLoggedIn()) {
    header("Location: dashboard.php");
    exit;
}

$error = '';

// Proses login
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validasi input
    if (empty($username) || empty($password)) {
        $error = 'Username dan password wajib diisi.';
    } else {
        $user = login($username, $password);
        if ($user) {
            header("Location: dashboard.php");
            exit;
        } else {
            $error = 'Username atau password salah.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Login - Sistem Kasir Kedai Kopi Kita">
  <title>Login — Kedai Kopi Kita</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <script src="https://unpkg.com/lucide@latest"></script>
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="login-page">
  <div class="login-card">
    <div class="login-header">
      <div class="login-logo">☕</div>
      <h1>Kedai Kopi Kita</h1>
      <p>Sistem Kasir & Inventaris Bahan Baku</p>
    </div>
    
    <div class="login-body">
      <?php if ($error): ?>
        <div class="alert alert-error">
          <i data-lucide="alert-circle" style="width:18px;height:18px;flex-shrink:0;"></i>
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>
      
      <form method="POST" action="login.php" id="loginForm">
        <div class="form-group">
          <label for="username">Username <span class="required">*</span></label>
          <input type="text" id="username" name="username" class="form-control" 
                 placeholder="Masukkan username" required autofocus
                 value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
        </div>
        
        <div class="form-group">
          <label for="password">Password <span class="required">*</span></label>
          <input type="password" id="password" name="password" class="form-control" 
                 placeholder="Masukkan password" required>
        </div>
        
        <button type="submit" class="btn btn-primary btn-lg btn-block" style="margin-top:0.5rem;">
          <i data-lucide="log-in" style="width:18px;height:18px;"></i>
          Masuk
        </button>
      </form>
      
      <div style="margin-top:1.5rem; padding-top:1rem; border-top:1px solid var(--color-border); text-align:center;">
        <p class="text-muted" style="font-size:0.75rem; margin-bottom:0.5rem;">Akun Demo:</p>
        <div style="font-size:0.75rem; color:var(--color-text-secondary); line-height:1.8;">
          <div><strong>Owner:</strong> owner / owner123</div>
          <div><strong>Kasir:</strong> kasir / kasir123</div>
          <div><strong>Barista:</strong> barista / barista123</div>
        </div>
      </div>
    </div>
  </div>
</div>
<script>lucide.createIcons();</script>
</body>
</html>
