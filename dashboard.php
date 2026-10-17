<?php
/**
 * dashboard.php
 * Halaman utama setelah login — menampilkan ringkasan operasional
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$pdo = getConnection();
$user = getCurrentUser();

// Statistik ringkas
$totalMenu      = $pdo->query("SELECT COUNT(*) FROM menu")->fetchColumn();
$totalBahanBaku = $pdo->query("SELECT COUNT(*) FROM bahan_baku")->fetchColumn();
$totalTransaksi = $pdo->query("SELECT COUNT(*) FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE()")->fetchColumn();
$omzetHariIni   = $pdo->query("SELECT COALESCE(SUM(total_harga), 0) FROM transaksi WHERE DATE(tanggal_transaksi) = CURDATE()")->fetchColumn();

// Bahan baku yang stoknya menipis (stok_tersedia <= stok_minimum)
$stmtLowStock = $pdo->query("SELECT * FROM bahan_baku WHERE stok_tersedia <= stok_minimum ORDER BY (stok_tersedia / NULLIF(stok_minimum, 0)) ASC LIMIT 10");
$lowStockItems = $stmtLowStock->fetchAll();

// Transaksi terakhir hari ini
$stmtRecent = $pdo->query("
    SELECT t.id_transaksi, t.tanggal_transaksi, t.total_harga, t.metode_pembayaran, p.nama_lengkap
    FROM transaksi t
    JOIN pengguna p ON t.id_pengguna = p.id_pengguna
    ORDER BY t.tanggal_transaksi DESC
    LIMIT 5
");
$recentTransactions = $stmtRecent->fetchAll();
?>

<!-- Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon amber"><i data-lucide="dollar-sign"></i></div>
    <div>
      <div class="stat-value">Rp <?= number_format($omzetHariIni, 0, ',', '.') ?></div>
      <div class="stat-label">Omzet Hari Ini</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i data-lucide="receipt"></i></div>
    <div>
      <div class="stat-value"><?= $totalTransaksi ?></div>
      <div class="stat-label">Transaksi Hari Ini</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon brown"><i data-lucide="coffee"></i></div>
    <div>
      <div class="stat-value"><?= $totalMenu ?></div>
      <div class="stat-label">Total Menu</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= count($lowStockItems) > 0 ? 'red' : 'green' ?>"><i data-lucide="package"></i></div>
    <div>
      <div class="stat-value"><?= count($lowStockItems) ?></div>
      <div class="stat-label">Stok Menipis</div>
    </div>
  </div>
</div>

<div style="display:grid; grid-template-columns:1fr 1fr; gap:1.5rem;">
  
  <!-- Alert Stok Menipis -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="alert-triangle" style="width:18px;height:18px;color:var(--color-warning);"></i> Peringatan Stok Menipis</h2>
    </div>
    <div class="card-body">
      <?php if (empty($lowStockItems)): ?>
        <div class="empty-state">
          <i data-lucide="check-circle"></i>
          <p>Semua stok bahan baku aman</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>Bahan Baku</th>
                <th>Stok</th>
                <th>Minimum</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($lowStockItems as $item): ?>
              <tr>
                <td><strong><?= htmlspecialchars($item['nama_bahan_baku']) ?></strong></td>
                <td><?= number_format($item['stok_tersedia'], 1) ?> <?= htmlspecialchars($item['satuan']) ?></td>
                <td><?= number_format($item['stok_minimum'], 1) ?> <?= htmlspecialchars($item['satuan']) ?></td>
                <td>
                  <?php if ($item['stok_tersedia'] <= 0): ?>
                    <span class="badge badge-danger">Habis</span>
                  <?php else: ?>
                    <span class="badge badge-warning">Menipis</span>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <!-- Transaksi Terakhir -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="clock" style="width:18px;height:18px;color:var(--color-primary);"></i> Transaksi Terakhir</h2>
    </div>
    <div class="card-body">
      <?php if (empty($recentTransactions)): ?>
        <div class="empty-state">
          <i data-lucide="inbox"></i>
          <p>Belum ada transaksi</p>
        </div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead>
              <tr>
                <th>#</th>
                <th>Waktu</th>
                <th>Kasir</th>
                <th>Metode</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($recentTransactions as $trx): ?>
              <tr>
                <td><?= $trx['id_transaksi'] ?></td>
                <td><?= date('d/m H:i', strtotime($trx['tanggal_transaksi'])) ?></td>
                <td><?= htmlspecialchars($trx['nama_lengkap']) ?></td>
                <td><span class="badge badge-<?= $trx['metode_pembayaran'] === 'Tunai' ? 'success' : 'info' ?>"><?= $trx['metode_pembayaran'] ?></span></td>
                <td><strong>Rp <?= number_format($trx['total_harga'], 0, ',', '.') ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
