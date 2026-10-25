<?php
/**
 * receipt.php
 * Struk Transaksi — ditampilkan setelah transaksi berhasil
 */
$pageTitle = 'Struk Transaksi';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Kasir']);

$pdo = getConnection();

$idTransaksi = intval($_GET['id'] ?? 0);
if ($idTransaksi <= 0) {
    setFlash('error', 'ID transaksi tidak valid.');
    header("Location: pos.php");
    exit;
}

// Ambil header transaksi
$stmt = $pdo->prepare("
    SELECT t.*, p.nama_lengkap 
    FROM transaksi t 
    JOIN pengguna p ON t.id_pengguna = p.id_pengguna 
    WHERE t.id_transaksi = ?
");
$stmt->execute([$idTransaksi]);
$transaksi = $stmt->fetch();

if (!$transaksi) {
    setFlash('error', 'Transaksi tidak ditemukan.');
    header("Location: pos.php");
    exit;
}

// Otorisasi: Kasir hanya boleh membuka struk transaksi miliknya sendiri
if (getCurrentUser()['role'] === 'Kasir' && (int)$transaksi['id_pengguna'] !== (int)getCurrentUser()['id']) {
    setFlash('error', 'Anda hanya dapat melihat struk transaksi milik Anda sendiri.');
    header("Location: transaksi.php");
    exit;
}

// Ambil detail transaksi
$stmt = $pdo->prepare("
    SELECT dt.*, m.nama_menu 
    FROM detail_transaksi dt 
    JOIN menu m ON dt.id_menu = m.id_menu 
    WHERE dt.id_transaksi = ?
    ORDER BY dt.id_detail_transaksi
");
$stmt->execute([$idTransaksi]);
$details = $stmt->fetchAll();
?>

<div class="receipt-container">
  <div class="receipt-header">
    <div style="font-size:2rem; margin-bottom:0.5rem;">☕</div>
    <h2>Kedai Kopi Kita</h2>
    <p style="font-size:0.75rem; color:var(--color-text-secondary);">Jl. Gajayana No. 50, Malang</p>
  </div>
  
  <div class="receipt-body">
    <!-- Info Transaksi -->
    <div class="receipt-info">
      <div><span>No. Transaksi</span><span><strong>#<?= str_pad($transaksi['id_transaksi'], 6, '0', STR_PAD_LEFT) ?></strong></span></div>
      <div><span>Tanggal</span><span><?= date('d/m/Y H:i:s', strtotime($transaksi['tanggal_transaksi'])) ?></span></div>
      <div><span>Kasir</span><span><?= htmlspecialchars($transaksi['nama_lengkap']) ?></span></div>
      <div><span>Metode</span><span><strong><?= $transaksi['metode_pembayaran'] ?></strong></span></div>
    </div>
    
    <!-- Item Pesanan -->
    <div class="receipt-items">
      <?php foreach ($details as $d): ?>
        <div class="receipt-item">
          <div>
            <div><?= htmlspecialchars($d['nama_menu']) ?></div>
            <div class="item-detail"><?= $d['jumlah_porsi'] ?> × <?= rupiah($d['subtotal'] / $d['jumlah_porsi']) ?></div>
          </div>
          <div><strong>Rp <?= number_format($d['subtotal'], 0, ',', '.') ?></strong></div>
        </div>
      <?php endforeach; ?>
    </div>
    
    <!-- Total -->
    <div class="receipt-totals">
      <div>
        <span>Total</span>
        <span><strong>Rp <?= number_format($transaksi['total_harga'], 0, ',', '.') ?></strong></span>
      </div>
      <div>
        <span>Bayar (<?= $transaksi['metode_pembayaran'] ?>)</span>
        <span>Rp <?= number_format($transaksi['nominal_bayar'], 0, ',', '.') ?></span>
      </div>
      <?php if ($transaksi['metode_pembayaran'] === 'Tunai'): ?>
        <div class="grand-total">
          <span>Kembalian</span>
          <span style="color:var(--color-success);">Rp <?= number_format($transaksi['kembalian'], 0, ',', '.') ?></span>
        </div>
      <?php endif; ?>
    </div>
  </div>
  
  <div class="receipt-footer">
    <p>Terima kasih atas kunjungan Anda!</p>
    <p>☕ Kedai Kopi Kita — Freshly Brewed</p>
  </div>
</div>

<!-- Tombol aksi -->
<div style="text-align:center; margin-top:1.5rem; display:flex; gap:0.75rem; justify-content:center;">
  <button onclick="window.print()" class="btn btn-primary">
    <i data-lucide="printer" style="width:16px;height:16px;"></i> Cetak Struk
  </button>
  <a href="pos.php" class="btn btn-accent">
    <i data-lucide="plus-circle" style="width:16px;height:16px;"></i> Transaksi Baru
  </a>
  <a href="transaksi.php" class="btn btn-outline">
    <i data-lucide="history" style="width:16px;height:16px;"></i> Riwayat
  </a>
  <a href="dashboard.php" class="btn btn-outline">
    <i data-lucide="home" style="width:16px;height:16px;"></i> Dashboard
  </a>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
