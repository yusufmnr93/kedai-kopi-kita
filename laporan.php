<?php
/**
 * laporan.php
 * Laporan Penjualan & Pemakaian Bahan Baku (UC-09) — Modul 8 (Reporting)
 *
 * Alur data: Database → Query → Pengolahan → Laporan
 * Sumber: transaksi, detail_transaksi, menu, kategori, resep, bahan_baku, pengguna
 * Otorisasi: hanya Owner.
 * Output: tampilan layar, cetak / simpan PDF (window.print), dan export CSV.
 */
require_once __DIR__ . '/includes/auth.php';
requireRole(['Owner']);                       // dicek sebelum output apa pun

$pdo   = getConnection();
$user  = getCurrentUser();
$today = date('Y-m-d');

// ---------- 1. Periode laporan + validasi ----------
$dari   = trim($_GET['dari'] ?? '');
$sampai = trim($_GET['sampai'] ?? '');
$errors = [];
$reset  = false;

if ($dari === '' && $sampai === '') {
    $reset = true;                                   // default: 7 hari terakhir
} elseif (!validDate($dari) || !validDate($sampai)) {
    $errors[] = 'Tanggal awal dan tanggal akhir wajib diisi dengan format yang valid.';
    $reset = true;
} elseif ($dari > $sampai) {
    $errors[] = 'Tanggal awal tidak boleh melebihi tanggal akhir.';
    $reset = true;
} elseif ((strtotime($sampai) - strtotime($dari)) / 86400 > 366) {
    $errors[] = 'Rentang laporan maksimal 366 hari.';
    $reset = true;
}
if ($reset) {
    $dari   = date('Y-m-d', strtotime('-6 days'));
    $sampai = $today;
}
$hariRentang = (int)round((strtotime($sampai) - strtotime($dari)) / 86400) + 1;

// Batas waktu: [dari 00:00:00, sampai + 1 hari)
$rangeSql    = 't.tanggal_transaksi >= ? AND t.tanggal_transaksi < DATE_ADD(?, INTERVAL 1 DAY)';
$rangeParams = [$dari . ' 00:00:00', $sampai];

function q(PDO $pdo, string $sql, array $p): PDOStatement {
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st;
}

// ---------- 2. Query ----------
// Ringkasan
$sum = q($pdo, "SELECT COUNT(*) AS n, COALESCE(SUM(t.total_harga),0) AS omzet
                FROM transaksi t WHERE $rangeSql", $rangeParams)->fetch();
$porsi = (int)q($pdo, "SELECT COALESCE(SUM(d.jumlah_porsi),0)
                       FROM detail_transaksi d JOIN transaksi t ON t.id_transaksi = d.id_transaksi
                       WHERE $rangeSql", $rangeParams)->fetchColumn();
$rata = $sum['n'] > 0 ? $sum['omzet'] / $sum['n'] : 0;

// Per metode pembayaran
$perMetode = q($pdo, "SELECT t.metode_pembayaran, COUNT(*) AS n, SUM(t.total_harga) AS omzet
                      FROM transaksi t WHERE $rangeSql
                      GROUP BY t.metode_pembayaran ORDER BY omzet DESC", $rangeParams)->fetchAll();

// Per hari
$perHari = q($pdo, "SELECT DATE(t.tanggal_transaksi) AS tgl, COUNT(*) AS n, SUM(t.total_harga) AS omzet
                    FROM transaksi t WHERE $rangeSql
                    GROUP BY DATE(t.tanggal_transaksi) ORDER BY tgl ASC", $rangeParams)->fetchAll();

// Menu terjual (harga diambil dari subtotal saat transaksi, bukan harga menu sekarang)
$perMenu = q($pdo, "SELECT m.nama_menu, k.nama_kategori,
                           SUM(d.jumlah_porsi) AS porsi, SUM(d.subtotal) AS omzet
                    FROM detail_transaksi d
                    JOIN transaksi t ON t.id_transaksi = d.id_transaksi
                    JOIN menu m      ON m.id_menu = d.id_menu
                    JOIN kategori k  ON k.id_kategori = m.id_kategori
                    WHERE $rangeSql
                    GROUP BY m.id_menu, m.nama_menu, k.nama_kategori
                    ORDER BY porsi DESC, omzet DESC", $rangeParams)->fetchAll();

// Pemakaian bahan baku = porsi terjual × takaran resep (rekap pemotongan stok)
$perBahan = q($pdo, "SELECT b.nama_bahan_baku, b.satuan, b.stok_tersedia, b.stok_minimum,
                            SUM(r.takaran * d.jumlah_porsi) AS terpakai
                     FROM detail_transaksi d
                     JOIN transaksi t   ON t.id_transaksi = d.id_transaksi
                     JOIN resep r       ON r.id_menu = d.id_menu
                     JOIN bahan_baku b  ON b.id_bahan_baku = r.id_bahan_baku
                     WHERE $rangeSql
                     GROUP BY b.id_bahan_baku, b.nama_bahan_baku, b.satuan, b.stok_tersedia, b.stok_minimum
                     ORDER BY terpakai DESC", $rangeParams)->fetchAll();

// Rincian transaksi
$LIMIT = 500;
$trxList = q($pdo, "SELECT t.id_transaksi, t.tanggal_transaksi, t.metode_pembayaran, t.total_harga,
                           p.nama_lengkap,
                           (SELECT GROUP_CONCAT(CONCAT(m.nama_menu, ' ×', d.jumlah_porsi)
                                   ORDER BY d.id_detail_transaksi SEPARATOR ', ')
                              FROM detail_transaksi d JOIN menu m ON m.id_menu = d.id_menu
                             WHERE d.id_transaksi = t.id_transaksi) AS ringkasan
                    FROM transaksi t JOIN pengguna p ON p.id_pengguna = t.id_pengguna
                    WHERE $rangeSql
                    ORDER BY t.tanggal_transaksi ASC, t.id_transaksi ASC
                    LIMIT $LIMIT", $rangeParams)->fetchAll();

// ---------- 3. Export CSV (sebelum ada output HTML) ----------
if (($_GET['export'] ?? '') === 'csv' && !$errors) {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="laporan_penjualan_' . $dari . '_sd_' . $sampai . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // BOM agar Excel membaca UTF-8
    fputcsv($out, ['Laporan Penjualan Kedai Kopi Kita']);
    fputcsv($out, ['Periode', tglIndo($dari) . ' - ' . tglIndo($sampai)]);
    fputcsv($out, ['Total Penjualan', (float)$sum['omzet']]);
    fputcsv($out, ['Jumlah Transaksi', (int)$sum['n']]);
    fputcsv($out, []);
    fputcsv($out, ['No. Transaksi', 'Waktu', 'Kasir', 'Metode', 'Item', 'Total']);
    foreach ($trxList as $r) {
        fputcsv($out, [
            '#' . str_pad($r['id_transaksi'], 6, '0', STR_PAD_LEFT),
            $r['tanggal_transaksi'],
            csvSafe($r['nama_lengkap']),
            $r['metode_pembayaran'],
            csvSafe($r['ringkasan']),
            (float)$r['total_harga'],
        ]);
    }
    fclose($out);
    exit;
}

$pageTitle = 'Laporan Penjualan';
require_once __DIR__ . '/includes/header.php';

// Pintasan periode
$pintasan = [
    'Hari ini'      => [$today, $today],
    '7 hari'        => [date('Y-m-d', strtotime('-6 days')), $today],
    'Bulan ini'     => [date('Y-m-01'), $today],
    '30 hari'       => [date('Y-m-d', strtotime('-29 days')), $today],
];
$maxOmzetHari = 0;
foreach ($perHari as $h) $maxOmzetHari = max($maxOmzetHari, (float)$h['omzet']);
?>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error no-print"><i data-lucide="alert-circle" style="width:18px;height:18px;"></i> <?= e($err) ?> Periode diatur ke 7 hari terakhir.</div>
<?php endforeach; ?>

<!-- Filter periode (tidak ikut tercetak) -->
<div class="card no-print">
  <div class="card-body">
    <form method="GET" action="laporan.php" class="filter-bar">
      <div class="form-group">
        <label for="dari">Dari Tanggal <span class="required">*</span></label>
        <input type="date" id="dari" name="dari" class="form-control" value="<?= e($dari) ?>" required>
      </div>
      <div class="form-group">
        <label for="sampai">Sampai Tanggal <span class="required">*</span></label>
        <input type="date" id="sampai" name="sampai" class="form-control" value="<?= e($sampai) ?>" required>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary"><i data-lucide="search" style="width:16px;height:16px;"></i> Tampilkan</button>
      </div>
      <div class="filter-actions" style="margin-left:auto;">
        <a class="btn btn-outline" href="?<?= e(http_build_query(['dari' => $dari, 'sampai' => $sampai, 'export' => 'csv'])) ?>">
          <i data-lucide="download" style="width:16px;height:16px;"></i> Export CSV
        </a>
        <button type="button" class="btn btn-accent" onclick="window.print()">
          <i data-lucide="printer" style="width:16px;height:16px;"></i> Cetak / PDF
        </button>
      </div>
    </form>
    <div class="quick-range">
      <span class="text-muted">Pintasan:</span>
      <?php foreach ($pintasan as $label => [$a, $b]): ?>
        <a href="?<?= e(http_build_query(['dari' => $a, 'sampai' => $b])) ?>"
           class="chip <?= ($dari === $a && $sampai === $b) ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ================= AREA LAPORAN (yang dicetak) ================= -->
<div class="report-area">

  <div class="report-head">
    <div>
      <h2 style="margin:0;">Laporan Penjualan &amp; Pemakaian Bahan Baku</h2>
      <div class="text-muted">Kedai Kopi Kita — Jl. Gajayana No. 50, Malang</div>
    </div>
    <div class="report-meta">
      <div><strong>Periode:</strong> <?= tglIndo($dari) ?> – <?= tglIndo($sampai) ?> (<?= $hariRentang ?> hari)</div>
      <div><strong>Dicetak oleh:</strong> <?= e($user['nama']) ?></div>
      <div><strong>Waktu cetak:</strong> <?= date('d/m/Y H:i') ?></div>
    </div>
  </div>

  <!-- Ringkasan -->
  <div class="stat-grid">
    <div class="stat-card">
      <div class="stat-icon amber"><i data-lucide="dollar-sign"></i></div>
      <div><div class="stat-value"><?= rupiah($sum['omzet']) ?></div><div class="stat-label">Total Penjualan</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon green"><i data-lucide="receipt"></i></div>
      <div><div class="stat-value"><?= number_format($sum['n'], 0, ',', '.') ?></div><div class="stat-label">Jumlah Transaksi</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon brown"><i data-lucide="coffee"></i></div>
      <div><div class="stat-value"><?= number_format($porsi, 0, ',', '.') ?></div><div class="stat-label">Porsi Terjual</div></div>
    </div>
    <div class="stat-card">
      <div class="stat-icon brown"><i data-lucide="calculator"></i></div>
      <div><div class="stat-value"><?= rupiah($rata) ?></div><div class="stat-label">Rata-rata per Transaksi</div></div>
    </div>
  </div>

  <?php if ($sum['n'] == 0): ?>
    <div class="card"><div class="card-body">
      <div class="empty-state"><i data-lucide="inbox"></i><p>Tidak ada transaksi pada periode ini.</p></div>
    </div></div>
  <?php else: ?>

  <div class="report-grid">
    <!-- Per metode -->
    <div class="card">
      <div class="card-header"><h2>Per Metode Pembayaran</h2></div>
      <div class="card-body">
        <table>
          <thead><tr><th>Metode</th><th class="text-right">Transaksi</th><th class="text-right">Penjualan</th><th class="text-right">Kontribusi</th></tr></thead>
          <tbody>
          <?php foreach ($perMetode as $m): ?>
            <tr>
              <td><span class="badge badge-<?= $m['metode_pembayaran'] === 'Tunai' ? 'success' : 'info' ?>"><?= e($m['metode_pembayaran']) ?></span></td>
              <td class="text-right"><?= (int)$m['n'] ?></td>
              <td class="text-right"><?= rupiah($m['omzet']) ?></td>
              <td class="text-right"><?= $sum['omzet'] > 0 ? round($m['omzet'] / $sum['omzet'] * 100) : 0 ?>%</td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Per hari -->
    <div class="card">
      <div class="card-header"><h2>Penjualan per Hari</h2></div>
      <div class="card-body">
        <?php foreach ($perHari as $h): $w = $maxOmzetHari > 0 ? round($h['omzet'] / $maxOmzetHari * 100) : 0; ?>
          <div class="bar-row">
            <div class="bar-label"><?= date('d/m', strtotime($h['tgl'])) ?></div>
            <div class="bar-track"><div class="bar-fill" style="width:<?= $w ?>%;"></div></div>
            <div class="bar-value"><?= rupiah($h['omzet']) ?> <small class="text-muted">(<?= (int)$h['n'] ?> trx)</small></div>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- Menu terjual -->
  <div class="card">
    <div class="card-header"><h2>Menu Terjual (Terlaris di Atas)</h2></div>
    <div class="card-body table-responsive">
      <table>
        <thead><tr><th style="width:50px;">#</th><th>Menu</th><th>Kategori</th><th class="text-right">Porsi</th><th class="text-right">Penjualan</th></tr></thead>
        <tbody>
        <?php foreach ($perMenu as $i => $m): ?>
          <tr>
            <td><?= $i + 1 ?></td>
            <td><strong><?= e($m['nama_menu']) ?></strong></td>
            <td><?= e($m['nama_kategori']) ?></td>
            <td class="text-right"><?= (int)$m['porsi'] ?></td>
            <td class="text-right"><?= rupiah($m['omzet']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="3"><strong>Total</strong></td><td class="text-right"><strong><?= $porsi ?></strong></td><td class="text-right"><strong><?= rupiah($sum['omzet']) ?></strong></td></tr>
        </tfoot>
      </table>
    </div>
  </div>

  <!-- Pemakaian bahan baku -->
  <div class="card">
    <div class="card-header"><h2>Rekap Pemakaian Bahan Baku</h2></div>
    <div class="card-body table-responsive">
      <table>
        <thead><tr><th>Bahan Baku</th><th class="text-right">Terpakai</th><th class="text-right">Stok Saat Ini</th><th class="text-right">Stok Minimum</th><th>Status Stok</th></tr></thead>
        <tbody>
        <?php foreach ($perBahan as $b): ?>
          <tr>
            <td><strong><?= e($b['nama_bahan_baku']) ?></strong></td>
            <td class="text-right"><?= fmtQty($b['terpakai']) ?> <?= e($b['satuan']) ?></td>
            <td class="text-right"><?= fmtQty($b['stok_tersedia']) ?> <?= e($b['satuan']) ?></td>
            <td class="text-right"><?= fmtQty($b['stok_minimum']) ?> <?= e($b['satuan']) ?></td>
            <td>
              <?php if ($b['stok_tersedia'] <= 0): ?><span class="badge badge-danger">Habis</span>
              <?php elseif ($b['stok_tersedia'] <= $b['stok_minimum']): ?><span class="badge badge-warning">Menipis</span>
              <?php else: ?><span class="badge badge-success">Normal</span><?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p class="form-text">Pemakaian dihitung dari porsi terjual × takaran resep yang berlaku saat laporan dibuat.</p>
    </div>
  </div>

  <!-- Rincian transaksi -->
  <div class="card">
    <div class="card-header"><h2>Rincian Transaksi</h2></div>
    <div class="card-body table-responsive">
      <table>
        <thead><tr><th>No. Transaksi</th><th>Waktu</th><th>Kasir</th><th>Item</th><th>Metode</th><th class="text-right">Total</th></tr></thead>
        <tbody>
        <?php foreach ($trxList as $r): ?>
          <tr>
            <td class="nowrap">#<?= str_pad($r['id_transaksi'], 6, '0', STR_PAD_LEFT) ?></td>
            <td class="nowrap"><?= date('d/m H:i', strtotime($r['tanggal_transaksi'])) ?></td>
            <td><?= e($r['nama_lengkap']) ?></td>
            <td class="cell-wrap"><?= e($r['ringkasan']) ?></td>
            <td><?= e($r['metode_pembayaran']) ?></td>
            <td class="text-right"><?= rupiah($r['total_harga']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
          <tr><td colspan="5"><strong>Total Penjualan</strong></td><td class="text-right"><strong><?= rupiah($sum['omzet']) ?></strong></td></tr>
        </tfoot>
      </table>
      <?php if ($sum['n'] > $LIMIT): ?>
        <p class="form-text">Menampilkan <?= $LIMIT ?> transaksi pertama dari <?= (int)$sum['n'] ?>. Persempit periode untuk melihat seluruhnya.</p>
      <?php endif; ?>
    </div>
  </div>

  <?php endif; ?>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
