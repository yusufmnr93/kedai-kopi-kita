<?php
/**
 * dashboard.php
 * Dashboard — Modul 8 (semua angka berasal dari database, bukan angka manual)
 *
 * Tampilan menyesuaikan role (authorization):
 *  - Owner  : seluruh penjualan + stok
 *  - Kasir  : penjualan miliknya sendiri + stok
 *  - Barista: ringkasan stok & stok masuk (tanpa data keuangan)
 */
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';

$pdo  = getConnection();
$user = getCurrentUser();
$role = $user['role'];

$showSales = in_array($role, ['Owner', 'Kasir'], true);
$scopeSql  = ($role === 'Kasir') ? ' AND t.id_pengguna = ' . (int)$user['id'] : '';
$scopeNote = ($role === 'Kasir') ? ' Saya' : '';
$awalBulan = date('Y-m-01');

// ---------- Stok (semua role) ----------
$totalBahan = (int)$pdo->query("SELECT COUNT(*) FROM bahan_baku")->fetchColumn();
$lowStockItems = $pdo->query("
    SELECT * FROM bahan_baku
    WHERE stok_tersedia <= stok_minimum
    ORDER BY (stok_tersedia / NULLIF(stok_minimum, 0)) ASC, nama_bahan_baku ASC
    LIMIT 10")->fetchAll();
$jumlahHabis = (int)$pdo->query("SELECT COUNT(*) FROM bahan_baku WHERE stok_tersedia <= 0")->fetchColumn();

if ($showSales) {
    // ---------- Indikator penjualan ----------
    $r = $pdo->query("SELECT COUNT(*) AS n, COALESCE(SUM(t.total_harga),0) AS omzet
                      FROM transaksi t WHERE t.tanggal_transaksi >= CURDATE() $scopeSql")->fetch();
    $trxHariIni   = (int)$r['n'];
    $omzetHariIni = (float)$r['omzet'];

    $stmt = $pdo->prepare("SELECT COALESCE(SUM(t.total_harga),0) FROM transaksi t
                           WHERE t.tanggal_transaksi >= ? $scopeSql");
    $stmt->execute([$awalBulan]);
    $omzetBulanIni = (float)$stmt->fetchColumn();

    // ---------- Transaksi terbaru ----------
    $recent = $pdo->query("
        SELECT t.id_transaksi, t.tanggal_transaksi, t.total_harga, t.metode_pembayaran, p.nama_lengkap
        FROM transaksi t JOIN pengguna p ON p.id_pengguna = t.id_pengguna
        WHERE 1=1 $scopeSql
        ORDER BY t.tanggal_transaksi DESC, t.id_transaksi DESC LIMIT 5")->fetchAll();

    // ---------- Menu terlaris bulan ini ----------
    $stmt = $pdo->prepare("
        SELECT m.nama_menu, SUM(d.jumlah_porsi) AS porsi, SUM(d.subtotal) AS omzet
        FROM detail_transaksi d
        JOIN transaksi t ON t.id_transaksi = d.id_transaksi
        JOIN menu m      ON m.id_menu = d.id_menu
        WHERE t.tanggal_transaksi >= ? $scopeSql
        GROUP BY m.id_menu, m.nama_menu
        ORDER BY porsi DESC, omzet DESC LIMIT 5");
    $stmt->execute([$awalBulan]);
    $terlaris = $stmt->fetchAll();
    $maxPorsi = 0;
    foreach ($terlaris as $t) $maxPorsi = max($maxPorsi, (int)$t['porsi']);

    // ---------- Penjualan 7 hari terakhir ----------
    $stmt = $pdo->prepare("
        SELECT DATE(t.tanggal_transaksi) AS tgl, SUM(t.total_harga) AS omzet
        FROM transaksi t
        WHERE t.tanggal_transaksi >= DATE_SUB(CURDATE(), INTERVAL 6 DAY) $scopeSql
        GROUP BY DATE(t.tanggal_transaksi)");
    $stmt->execute();
    $mapHari = [];
    foreach ($stmt->fetchAll() as $h) $mapHari[$h['tgl']] = (float)$h['omzet'];
    $tujuhHari = [];
    $maxHari = 0;
    for ($i = 6; $i >= 0; $i--) {
        $tgl = date('Y-m-d', strtotime("-$i days"));
        $tujuhHari[] = ['tgl' => $tgl, 'omzet' => $mapHari[$tgl] ?? 0];
        $maxHari = max($maxHari, $mapHari[$tgl] ?? 0);
    }
} else {
    // ---------- Barista: stok masuk ----------
    $masukHariIni = (int)$pdo->query("SELECT COUNT(*) FROM stok_masuk WHERE tanggal_masuk >= CURDATE()")->fetchColumn();
    $stokMasukTerbaru = $pdo->query("
        SELECT sm.tanggal_masuk, sm.jumlah_masuk, sm.keterangan, b.nama_bahan_baku, b.satuan
        FROM stok_masuk sm JOIN bahan_baku b ON b.id_bahan_baku = sm.id_bahan_baku
        ORDER BY sm.tanggal_masuk DESC, sm.id_stok_masuk DESC LIMIT 5")->fetchAll();
}
?>

<!-- ================= Indikator Utama ================= -->
<div class="stat-grid">
  <?php if ($showSales): ?>
  <div class="stat-card">
    <div class="stat-icon amber"><i data-lucide="dollar-sign"></i></div>
    <div>
      <div class="stat-value"><?= rupiah($omzetHariIni) ?></div>
      <div class="stat-label">Omzet<?= $scopeNote ?> Hari Ini</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i data-lucide="receipt"></i></div>
    <div>
      <div class="stat-value"><?= $trxHariIni ?></div>
      <div class="stat-label">Transaksi<?= $scopeNote ?> Hari Ini</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon brown"><i data-lucide="trending-up"></i></div>
    <div>
      <div class="stat-value"><?= rupiah($omzetBulanIni) ?></div>
      <div class="stat-label">Omzet<?= $scopeNote ?> Bulan Ini</div>
    </div>
  </div>
  <?php else: ?>
  <div class="stat-card">
    <div class="stat-icon brown"><i data-lucide="package"></i></div>
    <div>
      <div class="stat-value"><?= $totalBahan ?></div>
      <div class="stat-label">Total Bahan Baku</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon green"><i data-lucide="truck"></i></div>
    <div>
      <div class="stat-value"><?= $masukHariIni ?></div>
      <div class="stat-label">Stok Masuk Hari Ini</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $jumlahHabis > 0 ? 'red' : 'green' ?>"><i data-lucide="x-circle"></i></div>
    <div>
      <div class="stat-value"><?= $jumlahHabis ?></div>
      <div class="stat-label">Bahan Baku Habis</div>
    </div>
  </div>
  <?php endif; ?>
  <div class="stat-card">
    <div class="stat-icon <?= count($lowStockItems) > 0 ? 'red' : 'green' ?>"><i data-lucide="alert-triangle"></i></div>
    <div>
      <div class="stat-value"><?= count($lowStockItems) ?></div>
      <div class="stat-label">Stok Menipis / Habis</div>
    </div>
  </div>
</div>

<div class="dash-grid">

  <!-- Peringatan stok -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="alert-triangle" style="width:18px;height:18px;color:var(--color-warning);"></i> Peringatan Stok Menipis</h2>
      <?php if (in_array($role, ['Owner', 'Barista'], true)): ?>
        <a href="stok_masuk.php" class="btn btn-sm btn-outline">Catat Stok Masuk</a>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if (empty($lowStockItems)): ?>
        <div class="empty-state"><i data-lucide="check-circle"></i><p>Semua stok bahan baku aman</p></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead><tr><th>Bahan Baku</th><th>Stok</th><th>Minimum</th><th>Status</th></tr></thead>
            <tbody>
              <?php foreach ($lowStockItems as $item): ?>
              <tr>
                <td><strong><?= e($item['nama_bahan_baku']) ?></strong></td>
                <td><?= fmtQty($item['stok_tersedia']) ?> <?= e($item['satuan']) ?></td>
                <td><?= fmtQty($item['stok_minimum']) ?> <?= e($item['satuan']) ?></td>
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

  <?php if ($showSales): ?>
  <!-- Transaksi terbaru -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="clock" style="width:18px;height:18px;color:var(--color-primary);"></i> Transaksi Terbaru</h2>
      <a href="transaksi.php" class="btn btn-sm btn-outline">Lihat Semua</a>
    </div>
    <div class="card-body">
      <?php if (empty($recent)): ?>
        <div class="empty-state"><i data-lucide="inbox"></i><p>Belum ada transaksi</p></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead><tr><th>No.</th><th>Waktu</th><th>Kasir</th><th>Metode</th><th class="text-right">Total</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $trx): ?>
              <tr>
                <td class="nowrap"><a href="receipt.php?id=<?= (int)$trx['id_transaksi'] ?>">#<?= str_pad($trx['id_transaksi'], 6, '0', STR_PAD_LEFT) ?></a></td>
                <td class="nowrap"><?= date('d/m H:i', strtotime($trx['tanggal_transaksi'])) ?></td>
                <td><?= e($trx['nama_lengkap']) ?></td>
                <td><span class="badge badge-<?= $trx['metode_pembayaran'] === 'Tunai' ? 'success' : 'info' ?>"><?= e($trx['metode_pembayaran']) ?></span></td>
                <td class="text-right"><strong><?= rupiah($trx['total_harga']) ?></strong></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- Menu terlaris bulan ini -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="award" style="width:18px;height:18px;color:var(--color-accent);"></i> Menu Terlaris Bulan Ini</h2>
    </div>
    <div class="card-body">
      <?php if (empty($terlaris)): ?>
        <div class="empty-state"><i data-lucide="inbox"></i><p>Belum ada penjualan bulan ini</p></div>
      <?php else: foreach ($terlaris as $i => $t): ?>
        <div class="bar-row">
          <div class="bar-label" style="width:150px;"><?= ($i + 1) ?>. <?= e($t['nama_menu']) ?></div>
          <div class="bar-track"><div class="bar-fill" style="width:<?= $maxPorsi > 0 ? round($t['porsi'] / $maxPorsi * 100) : 0 ?>%;"></div></div>
          <div class="bar-value"><?= (int)$t['porsi'] ?> porsi</div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </div>

  <!-- Penjualan 7 hari -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="bar-chart-2" style="width:18px;height:18px;color:var(--color-primary);"></i> Penjualan 7 Hari Terakhir</h2>
      <?php if ($role === 'Owner'): ?><a href="laporan.php" class="btn btn-sm btn-outline">Buka Laporan</a><?php endif; ?>
    </div>
    <div class="card-body">
      <?php foreach ($tujuhHari as $h): ?>
        <div class="bar-row">
          <div class="bar-label"><?= date('d/m', strtotime($h['tgl'])) ?></div>
          <div class="bar-track"><div class="bar-fill alt" style="width:<?= $maxHari > 0 ? round($h['omzet'] / $maxHari * 100) : 0 ?>%;"></div></div>
          <div class="bar-value"><?= rupiah($h['omzet']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <?php else: ?>
  <!-- Barista: stok masuk terbaru -->
  <div class="card">
    <div class="card-header">
      <h2><i data-lucide="truck" style="width:18px;height:18px;color:var(--color-primary);"></i> Stok Masuk Terbaru</h2>
    </div>
    <div class="card-body">
      <?php if (empty($stokMasukTerbaru)): ?>
        <div class="empty-state"><i data-lucide="inbox"></i><p>Belum ada stok masuk</p></div>
      <?php else: ?>
        <div class="table-responsive">
          <table>
            <thead><tr><th>Tanggal</th><th>Bahan Baku</th><th>Jumlah</th><th>Keterangan</th></tr></thead>
            <tbody>
              <?php foreach ($stokMasukTerbaru as $s): ?>
              <tr>
                <td class="nowrap"><?= date('d/m H:i', strtotime($s['tanggal_masuk'])) ?></td>
                <td><strong><?= e($s['nama_bahan_baku']) ?></strong></td>
                <td>+<?= fmtQty($s['jumlah_masuk']) ?> <?= e($s['satuan']) ?></td>
                <td><?= e($s['keterangan'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <?php endif; ?>

</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
