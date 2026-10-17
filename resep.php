<?php
/**
 * resep.php
 * Kelola Resep (takaran bahan per menu) — UC-02
 */
$pageTitle = 'Kelola Resep';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner']);

$pdo = getConnection();

// Ambil data menu dan bahan baku untuk dropdown
$menuList  = $pdo->query("SELECT m.*, k.nama_kategori FROM menu m JOIN kategori k ON m.id_kategori = k.id_kategori ORDER BY k.nama_kategori, m.nama_menu")->fetchAll();
$bahanList = $pdo->query("SELECT * FROM bahan_baku ORDER BY nama_bahan_baku")->fetchAll();

// Filter menu
$filterMenu = intval($_GET['menu'] ?? 0);

// Ambil resep
$sql = "SELECT r.*, m.nama_menu, b.nama_bahan_baku, b.satuan 
        FROM resep r 
        JOIN menu m ON r.id_menu = m.id_menu 
        JOIN bahan_baku b ON r.id_bahan_baku = b.id_bahan_baku";
$params = [];
if ($filterMenu > 0) {
    $sql .= " WHERE r.id_menu = ?";
    $params[] = $filterMenu;
}
$sql .= " ORDER BY m.nama_menu, b.nama_bahan_baku";
$stmtResep = $pdo->prepare($sql);
$stmtResep->execute($params);
$resepList = $stmtResep->fetchAll();

// Data edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM resep WHERE id_resep = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch();
}
?>

<div class="card">
  <div class="card-header">
    <h2>Data Resep</h2>
    <!-- Filter menu -->
    <form method="GET" style="display:flex; gap:0.5rem; align-items:center;">
      <select name="menu" class="form-control" style="width:220px;" onchange="this.form.submit()">
        <option value="0">— Semua Menu —</option>
        <?php foreach ($menuList as $m): ?>
          <option value="<?= $m['id_menu'] ?>" <?= $filterMenu == $m['id_menu'] ? 'selected' : '' ?>>
            <?= htmlspecialchars($m['nama_menu']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </form>
  </div>
  <div class="card-body">
    
    <!-- Form Tambah / Edit Resep -->
    <form method="POST" action="resep_process.php" style="margin-bottom:1.5rem;">
      <input type="hidden" name="id_resep" value="<?= $editData['id_resep'] ?? '' ?>">
      <div class="form-row">
        <div class="form-group">
          <label for="id_menu_resep">Menu <span class="required">*</span></label>
          <select id="id_menu_resep" name="id_menu" class="form-control" required>
            <option value="">— Pilih Menu —</option>
            <?php foreach ($menuList as $m): ?>
              <option value="<?= $m['id_menu'] ?>" <?= ($editData['id_menu'] ?? '') == $m['id_menu'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($m['nama_menu']) ?> (<?= htmlspecialchars($m['nama_kategori']) ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="id_bahan_baku_resep">Bahan Baku <span class="required">*</span></label>
          <select id="id_bahan_baku_resep" name="id_bahan_baku" class="form-control" required>
            <option value="">— Pilih Bahan —</option>
            <?php foreach ($bahanList as $b): ?>
              <option value="<?= $b['id_bahan_baku'] ?>" <?= ($editData['id_bahan_baku'] ?? '') == $b['id_bahan_baku'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($b['nama_bahan_baku']) ?> (<?= $b['satuan'] ?>)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="takaran">Takaran per Porsi <span class="required">*</span></label>
          <input type="number" id="takaran" name="takaran" class="form-control"
                 placeholder="18.00" required min="0.01" step="0.01"
                 value="<?= $editData['takaran'] ?? '' ?>">
        </div>
      </div>
      <div style="display:flex; gap:0.5rem;">
        <button type="submit" name="action" value="<?= $editData ? 'update' : 'create' ?>"
                class="btn <?= $editData ? 'btn-accent' : 'btn-primary' ?>">
          <i data-lucide="<?= $editData ? 'save' : 'plus' ?>" style="width:16px;height:16px;"></i>
          <?= $editData ? 'Simpan Perubahan' : 'Tambah Resep' ?>
        </button>
        <?php if ($editData): ?>
          <a href="resep.php" class="btn btn-outline">Batal</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabel Resep -->
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th>No</th>
            <th>Menu</th>
            <th>Bahan Baku</th>
            <th>Takaran</th>
            <th>Satuan</th>
            <th style="width:140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($resepList)): ?>
            <tr><td colspan="6" class="text-center text-muted" style="padding:2rem;">Belum ada resep.</td></tr>
          <?php else: ?>
            <?php foreach ($resepList as $i => $r): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= htmlspecialchars($r['nama_menu']) ?></strong></td>
              <td><?= htmlspecialchars($r['nama_bahan_baku']) ?></td>
              <td><?= number_format($r['takaran'], 2) ?></td>
              <td><?= htmlspecialchars($r['satuan']) ?></td>
              <td>
                <div class="action-btns">
                  <a href="resep.php?edit=<?= $r['id_resep'] ?>" class="btn btn-sm btn-accent" title="Edit">
                    <i data-lucide="edit-2" style="width:14px;height:14px;"></i>
                  </a>
                  <form method="POST" action="resep_process.php" style="display:inline;"
                        onsubmit="return confirm('Yakin ingin menghapus resep ini?');">
                    <input type="hidden" name="id_resep" value="<?= $r['id_resep'] ?>">
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
