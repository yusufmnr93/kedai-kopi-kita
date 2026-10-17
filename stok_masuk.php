<?php
/**
 * stok_masuk.php
 * Input Stok Masuk dari Supplier — UC-06
 */
$pageTitle = 'Stok Masuk';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Barista']);

$pdo = getConnection();

// Bahan baku untuk dropdown
$bahanList = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama_bahan_baku")->fetchAll();

// Buat map satuan untuk JavaScript
$bahanMap = [];
foreach ($bahanList as $b) {
    $bahanMap[$b['id_bahan_baku']] = $b['satuan'];
}

// Histori stok masuk
$stokMasukList = $pdo->query("
    SELECT sm.*, b.nama_bahan_baku, b.satuan, p.nama_lengkap 
    FROM stok_masuk sm 
    JOIN bahan_baku b ON sm.id_bahan_baku = b.id_bahan_baku 
    JOIN pengguna p ON sm.id_pengguna = p.id_pengguna 
    ORDER BY sm.tanggal_masuk DESC 
    LIMIT 50
")->fetchAll();
?>

<div class="card">
  <div class="card-header">
    <h2>Input Stok Masuk</h2>
  </div>
  <div class="card-body">
    
    <!-- Form Input Stok -->
    <form method="POST" action="stok_masuk_process.php" style="margin-bottom:1.5rem;">
      <div class="form-row">
        <div class="form-group">
          <label for="id_bahan_baku">Bahan Baku <span class="required">*</span></label>
          <select id="id_bahan_baku_stok" name="id_bahan_baku" class="form-control" required
                  onchange="updateSatuan(this.value)">
            <option value="">— Pilih Bahan Baku —</option>
            <?php foreach ($bahanList as $b): ?>
              <option value="<?= $b['id_bahan_baku'] ?>"><?= htmlspecialchars($b['nama_bahan_baku']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="jumlah_masuk">Jumlah Masuk <span class="required">*</span></label>
          <div style="display:flex; gap:0.5rem; align-items:center;">
            <input type="number" id="jumlah_masuk" name="jumlah_masuk" class="form-control"
                   placeholder="0" required min="0.01" step="0.01" style="flex:1;">
            <span id="label_satuan" class="badge badge-primary" style="min-width:50px; text-align:center;">—</span>
          </div>
        </div>
        <div class="form-group">
          <label for="tanggal_masuk">Tanggal Masuk <span class="required">*</span></label>
          <input type="datetime-local" id="tanggal_masuk" name="tanggal_masuk" class="form-control" required
                 value="<?= date('Y-m-d\TH:i') ?>">
        </div>
        <div class="form-group">
          <label for="keterangan">Keterangan / Supplier</label>
          <input type="text" id="keterangan" name="keterangan" class="form-control"
                 placeholder="Contoh: Supplier CV Kopi Nusantara">
        </div>
      </div>
      <button type="submit" name="action" value="create" class="btn btn-primary">
        <i data-lucide="plus" style="width:16px;height:16px;"></i>
        Simpan Stok Masuk
      </button>
    </form>
  </div>
</div>

<!-- Histori Stok Masuk -->
<div class="card" style="margin-top:1.5rem;">
  <div class="card-header">
    <h2>Histori Stok Masuk</h2>
    <span class="badge badge-primary"><?= count($stokMasukList) ?> record</span>
  </div>
  <div class="card-body">
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Tanggal</th>
            <th>Bahan Baku</th>
            <th>Jumlah</th>
            <th>Petugas</th>
            <th>Keterangan</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($stokMasukList)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">Belum ada data stok masuk.</td></tr>
          <?php else: ?>
            <?php foreach ($stokMasukList as $i => $sm): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><?= date('d/m/Y H:i', strtotime($sm['tanggal_masuk'])) ?></td>
              <td><strong><?= htmlspecialchars($sm['nama_bahan_baku']) ?></strong></td>
              <td><span class="badge badge-success">+<?= number_format($sm['jumlah_masuk'], 1) ?> <?= htmlspecialchars($sm['satuan']) ?></span></td>
              <td><?= htmlspecialchars($sm['nama_lengkap']) ?></td>
              <td><?= htmlspecialchars($sm['keterangan'] ?? '-') ?></td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
// Map bahan baku → satuan (read-only auto-fill sesuai Laprak 6)
const bahanSatuan = <?= json_encode($bahanMap) ?>;

function updateSatuan(id) {
    const label = document.getElementById('label_satuan');
    label.textContent = bahanSatuan[id] || '—';
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
