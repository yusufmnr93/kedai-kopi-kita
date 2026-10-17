<?php
/**
 * includes/header.php
 * Layout header: sidebar navigasi + topbar
 * Navigasi disesuaikan dengan role pengguna (RBAC)
 */

require_once __DIR__ . '/auth.php';
requireLogin();

$currentUser = getCurrentUser();
$currentPage = basename($_SERVER['PHP_SELF'], '.php');

// Definisi menu navigasi berdasarkan role (Laprak 6: Role-Based Navigation)
$navMenus = [
    'Utama' => [
        ['page' => 'dashboard',   'label' => 'Dashboard',       'icon' => 'layout-dashboard', 'roles' => ['Owner', 'Kasir', 'Barista']],
        ['page' => 'pos',         'label' => 'POS Kasir',       'icon' => 'shopping-cart',     'roles' => ['Owner', 'Kasir']],
    ],
    'Master Data' => [
        ['page' => 'kategori',    'label' => 'Kategori',        'icon' => 'folder',            'roles' => ['Owner']],
        ['page' => 'menu',        'label' => 'Menu',            'icon' => 'coffee',            'roles' => ['Owner']],
        ['page' => 'bahan_baku',  'label' => 'Bahan Baku',      'icon' => 'package',           'roles' => ['Owner', 'Barista']],
        ['page' => 'resep',       'label' => 'Resep',           'icon' => 'book-open',         'roles' => ['Owner']],
    ],
    'Operasional' => [
        ['page' => 'stok_masuk',  'label' => 'Stok Masuk',      'icon' => 'truck',             'roles' => ['Owner', 'Barista']],
    ],
];
?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sistem Kasir dan Inventaris Bahan Baku Kedai Kopi Kita">
  <title><?= $pageTitle ?? 'Kedai Kopi Kita' ?> — Kedai Kopi Kita</title>
  
  <!-- Google Fonts: Inter -->
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  
  <!-- Lucide Icons (CDN) -->
  <script src="https://unpkg.com/lucide@latest"></script>
  
  <!-- Stylesheet -->
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
<div class="app-layout">
  
  <!-- Sidebar -->
  <aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
      <div class="brand-icon">☕</div>
      <div>
        <h2>Kedai Kopi Kita</h2>
        <small>Sistem Kasir & Inventaris</small>
      </div>
    </div>
    
    <nav class="sidebar-nav">
      <?php foreach ($navMenus as $section => $items): ?>
        <div class="nav-section"><?= htmlspecialchars($section) ?></div>
        <?php foreach ($items as $item): ?>
          <?php if (in_array($currentUser['role'], $item['roles'])): ?>
            <a href="<?= $item['page'] ?>.php" 
               class="<?= $currentPage === $item['page'] ? 'active' : '' ?>">
              <i data-lucide="<?= $item['icon'] ?>"></i>
              <?= htmlspecialchars($item['label']) ?>
            </a>
          <?php endif; ?>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </nav>
    
    <div class="sidebar-user">
      <div class="user-avatar"><?= strtoupper(substr($currentUser['nama'], 0, 1)) ?></div>
      <div class="user-info">
        <div class="user-name"><?= htmlspecialchars($currentUser['nama']) ?></div>
        <div class="user-role"><?= htmlspecialchars($currentUser['role']) ?></div>
      </div>
      <a href="logout.php" title="Logout" style="color: rgba(255,255,255,0.6); margin-left: auto;">
        <i data-lucide="log-out" style="width:18px; height:18px;"></i>
      </a>
    </div>
  </aside>
  
  <!-- Main Content -->
  <main class="main-content">
    <div class="topbar">
      <div>
        <h1><?= $pageTitle ?? 'Dashboard' ?></h1>
        <div class="breadcrumb">Kedai Kopi Kita / <?= $pageTitle ?? 'Dashboard' ?></div>
      </div>
      <div class="d-flex align-center gap-1">
        <span class="badge badge-primary"><?= htmlspecialchars($currentUser['role']) ?></span>
      </div>
    </div>
    
    <div class="content">
      <?php
      // Tampilkan flash messages
      $flashSuccess = getFlash('success');
      $flashError   = getFlash('error');
      if ($flashSuccess): ?>
        <div class="alert alert-success"><i data-lucide="check-circle" style="width:18px;height:18px;"></i> <?= htmlspecialchars($flashSuccess) ?></div>
      <?php endif; ?>
      <?php if ($flashError): ?>
        <div class="alert alert-error"><i data-lucide="alert-circle" style="width:18px;height:18px;"></i> <?= htmlspecialchars($flashError) ?></div>
      <?php endif; ?>
