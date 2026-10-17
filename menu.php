<?php
/**
 * menu.php
 * CRUD Menu Makanan/Minuman — Aktivitas 5 Modul 7
 */
$pageTitle = 'Kelola Menu';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner']);

$pdo = getConnection();

// Ambil kategori untuk dropdown
$kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// Ambil semua menu beserta kategori
$menuList = $pdo->query("
    SELECT m.*, k.nama_kategori 
    FROM menu m 
    JOIN kategori k ON m.id_kategori = k.id_kategori 
    ORDER BY k.nama_kategori, m.nama_menu
")->fetchAll();

// Cek apakah sedang edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM menu WHERE id_menu = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch();
}
?>

<div class="card">
  <div class="card-header">
    <h2>Daftar Menu</h2>
    <span class="badge badge-primary"><?= count($menuList) ?> item</span>
  </div>
  <div class="card-body">
    
    <!-- Form Tambah / Edit -->
    <form method="POST" action="menu_process.php" style="margin-bottom:1.5rem;">
      <input type="hidden" name="id_menu" value="<?= $editData['id_menu'] ?? '' ?>">
      <div class="form-row">
        <div class="form-group">
          <label for="id_kategori">Kategori <span class="required">*</span></label>
          <select id="id_kategori" name="id_kategori" class="form-control" required>
            <option value="">— Pilih Kategori —</option>
            <?php foreach ($kategoriList as $kat): ?>
              <option value="<?= $kat['id_kategori'] ?>" 
                <?= ($editData['id_kategori'] ?? '') == $kat['id_kategori'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($kat['nama_kategori']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label for="nama_menu">Nama Menu <span class="required">*</span></label>
          <input type="text" id="nama_menu" name="nama_menu" class="form-control"
                 placeholder="Contoh: Caffe Latte" required
                 value="<?= htmlspecialchars($editData['nama_menu'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label for="harga">Harga (Rp) <span class="required">*</span></label>
          <input type="number" id="harga" name="harga" class="form-control"
                 placeholder="22000" required min="0" step="500"
                 value="<?= $editData['harga'] ?? '' ?>">
        </div>
      </div>
      <div style="display:flex; gap:0.5rem;">
        <button type="submit" name="action" value="<?= $editData ? 'update' : 'create' ?>"
                class="btn <?= $editData ? 'btn-accent' : 'btn-primary' ?>">
          <i data-lucide="<?= $editData ? 'save' : 'plus' ?>" style="width:16px;height:16px;"></i>
          <?= $editData ? 'Simpan Perubahan' : 'Tambah Menu' ?>
        </button>
        <?php if ($editData): ?>
          <a href="menu.php" class="btn btn-outline">Batal</a>
        <?php endif; ?>
      </div>
    </form>
    
    <!-- Tabel Menu -->
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th style="width:50px;">No</th>
            <th>Nama Menu</th>
            <th>Kategori</th>
            <th>Harga</th>
            <th style="width:140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($menuList)): ?>
            <tr><td colspan="5" class="text-center text-muted" style="padding:2rem;">Belum ada menu.</td></tr>
          <?php else: ?>
            <?php foreach ($menuList as $i => $m): ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= htmlspecialchars($m['nama_menu']) ?></strong></td>
              <td><span class="badge badge-primary"><?= htmlspecialchars($m['nama_kategori']) ?></span></td>
              <td>Rp <?= number_format($m['harga'], 0, ',', '.') ?></td>
              <td>
                <div class="action-btns">
                  <a href="menu.php?edit=<?= $m['id_menu'] ?>" class="btn btn-sm btn-accent" title="Edit">
                    <i data-lucide="edit-2" style="width:14px;height:14px;"></i>
                  </a>
                  <form method="POST" action="menu_process.php" style="display:inline;"
                        onsubmit="return confirm('Yakin ingin menghapus menu ini?');">
                    <input type="hidden" name="id_menu" value="<?= $m['id_menu'] ?>">
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
