<?php
/**
 * bahan_baku.php
 * CRUD Bahan Baku + Indikator Stok Menipis (UC-08)
 */
$pageTitle = 'Inventaris Bahan Baku';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Barista']);

$pdo = getConnection();

// Filter pencarian
$search = trim($_GET['search'] ?? '');
$sql = "SELECT * FROM bahan_baku";
$params = [];
if ($search) {
    $sql .= " WHERE nama_bahan_baku LIKE ?";
    $params[] = "%$search%";
}
$sql .= " ORDER BY nama_bahan_baku ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bahanList = $stmt->fetchAll();

// Data edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM bahan_baku WHERE id_bahan_baku = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch();
}

// Summary cards
$totalBahan  = count($bahanList);
$lowStock    = 0;
$outOfStock  = 0;
foreach ($bahanList as $b) {
    if ($b['stok_tersedia'] <= 0) $outOfStock++;
    elseif ($b['stok_tersedia'] <= $b['stok_minimum']) $lowStock++;
}
?>

<!-- Summary Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-icon brown"><i data-lucide="package"></i></div>
    <div>
      <div class="stat-value"><?= $totalBahan ?></div>
      <div class="stat-label">Total Bahan Baku</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $lowStock > 0 ? 'red' : 'green' ?>"><i data-lucide="alert-triangle"></i></div>
    <div>
      <div class="stat-value"><?= $lowStock ?></div>
      <div class="stat-label">Stok Menipis</div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-icon <?= $outOfStock > 0 ? 'red' : 'green' ?>"><i data-lucide="x-circle"></i></div>
    <div>
      <div class="stat-value"><?= $outOfStock ?></div>
      <div class="stat-label">Stok Habis</div>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">
    <h2>Data Bahan Baku</h2>
    <!-- Search -->
    <form method="GET" style="display:flex; gap:0.5rem;">
      <input type="text" name="search" class="form-control" placeholder="Cari bahan..." 
             value="<?= htmlspecialchars($search) ?>" style="width:200px;">
      <button type="submit" class="btn btn-outline btn-sm"><i data-lucide="search" style="width:14px;height:14px;"></i></button>
    </form>
  </div>
  <div class="card-body">
    
    <!-- Form Tambah / Edit -->
    <form method="POST" action="bahan_baku_process.php" style="margin-bottom:1.5rem;">
      <input type="hidden" name="id_bahan_baku" value="<?= $editData['id_bahan_baku'] ?? '' ?>">
      <div class="form-row">
        <div class="form-group">
          <label for="nama_bahan_baku">Nama Bahan Baku <span class="required">*</span></label>
          <input type="text" id="nama_bahan_baku" name="nama_bahan_baku" class="form-control"
                 placeholder="Contoh: Biji Kopi Arabica" required
                 value="<?= htmlspecialchars($editData['nama_bahan_baku'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="satuan">Satuan <span class="required">*</span></label>
          <select id="satuan" name="satuan" class="form-control" required>
            <option value="">— Pilih —</option>
            <?php foreach (['gram', 'ml', 'pcs'] as $sat): ?>
              <option value="<?= $sat ?>" <?= ($editData['satuan'] ?? '') === $sat ? 'selected' : '' ?>><?= $sat ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="stok_tersedia">Stok Tersedia <span class="required">*</span></label>
          <input type="number" id="stok_tersedia" name="stok_tersedia" class="form-control"
                 placeholder="0" required min="0" step="0.01"
                 value="<?= $editData['stok_tersedia'] ?? '' ?>">
        </div>
        <div class="form-group">
          <label for="stok_minimum">Stok Minimum <span class="required">*</span></label>
          <input type="number" id="stok_minimum" name="stok_minimum" class="form-control"
                 placeholder="0" required min="0" step="0.01"
                 value="<?= $editData['stok_minimum'] ?? '' ?>">
        </div>
      </div>
      <div style="display:flex; gap:0.5rem;">
        <button type="submit" name="action" value="<?= $editData ? 'update' : 'create' ?>"
                class="btn <?= $editData ? 'btn-accent' : 'btn-primary' ?>">
          <i data-lucide="<?= $editData ? 'save' : 'plus' ?>" style="width:16px;height:16px;"></i>
          <?= $editData ? 'Simpan Perubahan' : 'Tambah Bahan' ?>
        </button>
        <?php if ($editData): ?>
          <a href="bahan_baku.php" class="btn btn-outline">Batal</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabel Bahan Baku -->
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Nama Bahan Baku</th>
            <th>Satuan</th>
            <th>Stok Tersedia</th>
            <th>Stok Minimum</th>
            <th>Status</th>
            <th style="width:140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($bahanList)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:2rem;">Belum ada bahan baku.</td></tr>
          <?php else: ?>
            <?php foreach ($bahanList as $i => $b): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= htmlspecialchars($b['nama_bahan_baku']) ?></strong></td>
              <td><?= htmlspecialchars($b['satuan']) ?></td>
              <td><?= number_format($b['stok_tersedia'], 1) ?></td>
              <td><?= number_format($b['stok_minimum'], 1) ?></td>
              <td>
                <?php if ($b['stok_tersedia'] <= 0): ?>
                  <span class="badge badge-danger">Habis</span>
                <?php elseif ($b['stok_tersedia'] <= $b['stok_minimum']): ?>
                  <span class="badge badge-warning">Menipis</span>
                <?php else: ?>
                  <span class="badge badge-success">Normal</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="action-btns">
                  <a href="bahan_baku.php?edit=<?= $b['id_bahan_baku'] ?>" class="btn btn-sm btn-accent" title="Edit">
                    <i data-lucide="edit-2" style="width:14px;height:14px;"></i>
                  </a>
                  <form method="POST" action="bahan_baku_process.php" style="display:inline;"
                        onsubmit="return confirm('Yakin ingin menghapus bahan baku ini?');">
                    <input type="hidden" name="id_bahan_baku" value="<?= $b['id_bahan_baku'] ?>">
                    <button type="submit" name="action" value="delete" class="btn btn-sm btn-danger" title="Hapus">
                      <i data-lucide="trash-2" style="width:14px;height:14px;"></i>
                    </button>
                  </form>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
