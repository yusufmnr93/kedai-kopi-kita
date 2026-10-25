<?php
/**
 * transaksi.php
 * Riwayat Transaksi — Modul 8 (integrasi transaksi, search, filter, sorting, pagination)
 *
 * Data berasal dari tabel transaksi + detail_transaksi + menu + pengguna.
 * Otorisasi: Owner melihat semua transaksi; Kasir hanya melihat transaksi miliknya sendiri.
 */
require_once __DIR__ . '/includes/auth.php';
requireRole(['Owner', 'Kasir']);   // dicek SEBELUM layout dicetak

$pageTitle = 'Riwayat Transaksi';
require_once __DIR__ . '/includes/header.php';

$pdo     = getConnection();
$user    = getCurrentUser();
$isOwner = $user['role'] === 'Owner';

// ---------- 1. Ambil & validasi parameter ----------
$q       = trim($_GET['q'] ?? '');
$dari    = trim($_GET['dari'] ?? '');
$sampai  = trim($_GET['sampai'] ?? '');
$metode  = $_GET['metode'] ?? '';
$kasirId = intval($_GET['kasir'] ?? 0);
$urut    = $_GET['urut'] ?? 'terbaru';
$page    = intval($_GET['page'] ?? 1);
$errors  = [];

if ($dari !== '' && !validDate($dari))     { $errors[] = 'Format tanggal awal tidak valid.';  $dari = ''; }
if ($sampai !== '' && !validDate($sampai)) { $errors[] = 'Format tanggal akhir tidak valid.'; $sampai = ''; }
if ($dari !== '' && $sampai !== '' && $dari > $sampai) {
    $errors[] = 'Tanggal awal tidak boleh melebihi tanggal akhir. Filter periode diabaikan.';
    $dari = $sampai = '';
}
if (!in_array($metode, ['Tunai', 'QRIS'], true)) $metode = '';

$orderMap = [
    'terbaru'  => 't.tanggal_transaksi DESC, t.id_transaksi DESC',
    'terlama'  => 't.tanggal_transaksi ASC, t.id_transaksi ASC',
    'terbesar' => 't.total_harga DESC, t.id_transaksi DESC',
    'terkecil' => 't.total_harga ASC, t.id_transaksi DESC',
];
if (!isset($orderMap[$urut])) $urut = 'terbaru';

// ---------- 2. Susun klausa WHERE (prepared statement) ----------
$where  = ['1=1'];
$params = [];

if (!$isOwner) {                       // BR: Kasir hanya melihat transaksi miliknya
    $where[]  = 't.id_pengguna = ?';
    $params[] = $user['id'];
} elseif ($kasirId > 0) {
    $where[]  = 't.id_pengguna = ?';
    $params[] = $kasirId;
}
if ($dari !== '')   { $where[] = 't.tanggal_transaksi >= ?';                 $params[] = $dari . ' 00:00:00'; }
if ($sampai !== '') { $where[] = 't.tanggal_transaksi < DATE_ADD(?, INTERVAL 1 DAY)'; $params[] = $sampai; }
if ($metode !== '') { $where[] = 't.metode_pembayaran = ?';                  $params[] = $metode; }

if ($q !== '') {
    $like   = '%' . addcslashes($q, '%_\\') . '%';
    $cond   = '(p.nama_lengkap LIKE ? OR EXISTS (
                  SELECT 1 FROM detail_transaksi d2
                  JOIN menu m2 ON m2.id_menu = d2.id_menu
                  WHERE d2.id_transaksi = t.id_transaksi AND m2.nama_menu LIKE ?)';
    $params[] = $like;
    $params[] = $like;
    $num = ltrim(ltrim($q, '#'), '0');
    if ($num !== '' && ctype_digit($num)) {   // cari berdasarkan nomor transaksi (#000012 / 12)
        $cond    .= ' OR t.id_transaksi = ?';
        $params[] = (int)$num;
    }
    $where[] = $cond . ')';
}
$whereSql = implode(' AND ', $where);

// ---------- 3. Total (untuk ringkasan & pagination) ----------
$stmt = $pdo->prepare("
    SELECT COUNT(*) AS n, COALESCE(SUM(t.total_harga), 0) AS omzet
    FROM transaksi t
    JOIN pengguna p ON p.id_pengguna = t.id_pengguna
    WHERE $whereSql");
$stmt->execute($params);
$sum = $stmt->fetch();

$pg = paginate((int)$sum['n'], 10, $page);

// ---------- 4. Data halaman ini ----------
$stmt = $pdo->prepare("
    SELECT t.*, p.nama_lengkap,
           (SELECT GROUP_CONCAT(CONCAT(m.nama_menu, ' ×', d.jumlah_porsi)
                                ORDER BY d.id_detail_transaksi SEPARATOR ', ')
              FROM detail_transaksi d
              JOIN menu m ON m.id_menu = d.id_menu
             WHERE d.id_transaksi = t.id_transaksi) AS ringkasan
    FROM transaksi t
    JOIN pengguna p ON p.id_pengguna = t.id_pengguna
    WHERE $whereSql
    ORDER BY {$orderMap[$urut]}
    LIMIT " . (int)$pg['perPage'] . " OFFSET " . (int)$pg['offset']);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Daftar kasir untuk filter (khusus Owner)
$kasirList = $isOwner
    ? $pdo->query("SELECT id_pengguna, nama_lengkap FROM pengguna WHERE role IN ('Owner','Kasir') ORDER BY nama_lengkap")->fetchAll()
    : [];

$filterAktif = ($q !== '' || $dari !== '' || $sampai !== '' || $metode !== '' || $kasirId > 0);
?>

<?php foreach ($errors as $err): ?>
  <div class="alert alert-error"><i data-lucide="alert-circle" style="width:18px;height:18px;"></i> <?= e($err) ?></div>
<?php endforeach; ?>

<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);">
  <div class="stat-card">
    <div class="stat-icon green"><i data-lucide="receipt"></i></div>
    <div>
      <div class="stat-value"><?= number_format($sum['n'], 0, ',', '.') ?></div>
      <div class="stat-label">Transaksi <?= $filterAktif ? '(sesuai filter)' : ($isOwner ? '(semua)' : '(milik saya)') ?></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon amber"><i data-lucide="dollar-sign"></i></div>
    <div>
      <div class="stat-value"><?= rupiah($sum['omzet']) ?></div>
      <div class="stat-label">Total Penjualan</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon brown"><i data-lucide="calculator"></i></div>
    <div>
      <div class="stat-value"><?= $sum['n'] > 0 ? rupiah($sum['omzet'] / $sum['n']) : 'Rp 0' ?></div>
      <div class="stat-label">Rata-rata per Transaksi</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Daftar Transaksi</h2>
    <a href="pos.php" class="btn btn-primary btn-sm"><i data-lucide="plus-circle" style="width:14px;height:14px;"></i> Transaksi Baru</a>
  </div>
  <div class="card-body">

    <!-- Filter -->
    <form method="GET" action="transaksi.php" class="filter-bar" id="filterForm">
      <div class="form-group" style="flex:2; min-width:200px;">
        <label for="q">Cari</label>
        <input type="text" id="q" name="q" class="form-control" value="<?= e($q) ?>"
               placeholder="No. transaksi, nama menu, atau nama kasir">
      </div>
      <div class="form-group">
        <label for="dari">Dari Tanggal</label>
        <input type="date" id="dari" name="dari" class="form-control" value="<?= e($dari) ?>">
      </div>
      <div class="form-group">
        <label for="sampai">Sampai Tanggal</label>
        <input type="date" id="sampai" name="sampai" class="form-control" value="<?= e($sampai) ?>">
      </div>
      <div class="form-group">
        <label for="metode">Metode</label>
        <select id="metode" name="metode" class="form-control">
          <option value="">Semua</option>
          <option value="Tunai" <?= $metode === 'Tunai' ? 'selected' : '' ?>>Tunai</option>
          <option value="QRIS"  <?= $metode === 'QRIS'  ? 'selected' : '' ?>>QRIS</option>
        </select>
      </div>
      <?php if ($isOwner): ?>
      <div class="form-group">
        <label for="kasir">Kasir</label>
        <select id="kasir" name="kasir" class="form-control">
          <option value="">Semua</option>
          <?php foreach ($kasirList as $k): ?>
            <option value="<?= (int)$k['id_pengguna'] ?>" <?= $kasirId === (int)$k['id_pengguna'] ? 'selected' : '' ?>><?= e($k['nama_lengkap']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <?php endif; ?>
      <div class="form-group">
        <label for="urut">Urutkan</label>
        <select id="urut" name="urut" class="form-control">
          <option value="terbaru"  <?= $urut === 'terbaru'  ? 'selected' : '' ?>>Terbaru</option>
          <option value="terlama"  <?= $urut === 'terlama'  ? 'selected' : '' ?>>Terlama</option>
          <option value="terbesar" <?= $urut === 'terbesar' ? 'selected' : '' ?>>Total terbesar</option>
          <option value="terkecil" <?= $urut === 'terkecil' ? 'selected' : '' ?>>Total terkecil</option>
        </select>
      </div>
      <div class="filter-actions">
        <button type="submit" class="btn btn-primary"><i data-lucide="search" style="width:16px;height:16px;"></i> Terapkan</button>
        <?php if ($filterAktif || $urut !== 'terbaru'): ?>
          <a href="transaksi.php" class="btn btn-outline">Reset</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabel -->
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>No. Transaksi</th>
            <th>Waktu</th>
            <th>Kasir</th>
            <th>Item</th>
            <th>Metode</th>
            <th class="text-right">Total</th>
            <th style="width:90px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($rows)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:2rem;">
              <?= $filterAktif ? 'Tidak ada transaksi yang sesuai dengan filter.' : 'Belum ada transaksi.' ?>
            </td></tr>
          <?php else: foreach ($rows as $r): ?>
            <tr>
              <td class="nowrap"><strong>#<?= str_pad($r['id_transaksi'], 6, '0', STR_PAD_LEFT) ?></strong></td>
              <td class="nowrap"><?= date('d/m/Y H:i', strtotime($r['tanggal_transaksi'])) ?></td>
              <td><?= e($r['nama_lengkap']) ?></td>
              <td class="cell-wrap"><?= e($r['ringkasan']) ?></td>
              <td><span class="badge badge-<?= $r['metode_pembayaran'] === 'Tunai' ? 'success' : 'info' ?>"><?= e($r['metode_pembayaran']) ?></span></td>
              <td class="text-right"><strong><?= rupiah($r['total_harga']) ?></strong></td>
              <td>
                <a href="receipt.php?id=<?= (int)$r['id_transaksi'] ?>" class="btn btn-sm btn-outline" title="Lihat struk">
                  <i data-lucide="receipt" style="width:14px;height:14px;"></i> Struk
                </a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Pagination -->
    <?php if ($pg['total'] > 0): ?>
    <div class="pagination-bar">
      <span class="text-muted">
        Menampilkan <?= $pg['offset'] + 1 ?>–<?= $pg['offset'] + count($rows) ?> dari <?= $pg['total'] ?> transaksi
      </span>
      <?php if ($pg['pages'] > 1): ?>
      <div class="pagination">
        <?php if ($pg['page'] > 1): ?>
          <a href="?<?= e(queryString(['page' => $pg['page'] - 1])) ?>">‹ Sebelumnya</a>
        <?php endif; ?>
        <?php for ($i = 1; $i <= $pg['pages']; $i++): ?>
          <?php if ($i === $pg['page']): ?>
            <span class="active"><?= $i ?></span>
          <?php else: ?>
            <a href="?<?= e(queryString(['page' => $i])) ?>"><?= $i ?></a>
          <?php endif; ?>
        <?php endfor; ?>
        <?php if ($pg['page'] < $pg['pages']): ?>
          <a href="?<?= e(queryString(['page' => $pg['page'] + 1])) ?>">Berikutnya ›</a>
        <?php endif; ?>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
