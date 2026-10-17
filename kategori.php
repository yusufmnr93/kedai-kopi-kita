<?php
/**
 * kategori.php
 * CRUD Kategori Menu — Aktivitas 5 Modul 7
 */
$pageTitle = 'Kelola Kategori';
require_once __DIR__ . '/includes/header.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner']);

$pdo = getConnection();

// Ambil semua kategori
$kategoriList = $pdo->query("SELECT * FROM kategori ORDER BY nama_kategori ASC")->fetchAll();

// Cek apakah sedang edit
$editData = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM kategori WHERE id_kategori = ?");
    $stmt->execute([$_GET['edit']]);
    $editData = $stmt->fetch();
}
?>

<div class="card">
  <div class="card-header">
    <h2>Daftar Kategori</h2>
  </div>
  <div class="card-body">
    
    <!-- Form Tambah / Edit -->
    <form method="POST" action="kategori_process.php" style="margin-bottom:1.5rem;">
      <input type="hidden" name="id_kategori" value="<?= $editData['id_kategori'] ?? '' ?>">
      <div style="display:flex; gap:0.75rem; align-items:flex-end;">
        <div class="form-group" style="flex:1; margin-bottom:0;">
          <label for="nama_kategori">Nama Kategori <span class="required">*</span></label>
          <input type="text" id="nama_kategori" name="nama_kategori" class="form-control"
                 placeholder="Contoh: Espresso Based" required
                 value="<?= htmlspecialchars($editData['nama_kategori'] ?? '') ?>">
        </div>
        <button type="submit" name="action" value="<?= $editData ? 'update' : 'create' ?>" 
                class="btn <?= $editData ? 'btn-accent' : 'btn-primary' ?>">
          <i data-lucide="<?= $editData ? 'save' : 'plus' ?>" style="width:16px;height:16px;"></i>
          <?= $editData ? 'Simpan Perubahan' : 'Tambah Kategori' ?>
        </button>
        <?php if ($editData): ?>
          <a href="kategori.php" class="btn btn-outline">Batal</a>
        <?php endif; ?>
      </div>
    </form>
    
    <!-- Tabel Kategori -->
    <div class="table-responsive">
      <table>
        <thead>
          <tr>
            <th style="width:60px;">No</th>
            <th>Nama Kategori</th>
            <th>Jumlah Menu</th>
            <th style="width:140px;">Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($kategoriList)): ?>
            <tr><td colspan="4" class="text-center text-muted" style="padding:2rem;">Belum ada kategori.</td></tr>
          <?php else: ?>
            <?php foreach ($kategoriList as $i => $kat): 
              $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE id_kategori = ?");
              $stmtCount->execute([$kat['id_kategori']]);
              $menuCount = $stmtCount->fetchColumn();
            ?>
            <tr>
              <td><?= $i + 1 ?></td>
              <td><strong><?= htmlspecialchars($kat['nama_kategori']) ?></strong></td>
              <td><span class="badge badge-primary"><?= $menuCount ?> menu</span></td>
              <td>
                <div class="action-btns">
                  <a href="kategori.php?edit=<?= $kat['id_kategori'] ?>" class="btn btn-sm btn-accent" title="Edit">
                    <i data-lucide="edit-2" style="width:14px;height:14px;"></i>
                  </a>
                  <form method="POST" action="kategori_process.php" style="display:inline;"
                        onsubmit="return confirm('Yakin ingin menghapus kategori ini?');">
                    <input type="hidden" name="id_kategori" value="<?= $kat['id_kategori'] ?>">
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
