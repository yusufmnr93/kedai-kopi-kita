<?php
/**
 * menu_process.php
 * Proses CRUD Menu (Create, Update, Delete)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner']);

$pdo    = getConnection();
$action = $_POST['action'] ?? '';
$id     = intval($_POST['id_menu'] ?? 0);
$idKat  = intval($_POST['id_kategori'] ?? 0);
$nama   = trim($_POST['nama_menu'] ?? '');
$harga  = floatval($_POST['harga'] ?? 0);

try {
    switch ($action) {
        case 'create':
            if (empty($nama)) throw new Exception('Nama menu wajib diisi.');
            if ($idKat <= 0) throw new Exception('Kategori wajib dipilih.');
            if ($harga <= 0) throw new Exception('Harga harus lebih besar dari 0.');
            
            $stmt = $pdo->prepare("INSERT INTO menu (id_kategori, nama_menu, harga) VALUES (?, ?, ?)");
            $stmt->execute([$idKat, $nama, $harga]);
            setFlash('success', 'Menu "' . $nama . '" berhasil ditambahkan.');
            break;

        case 'update':
            if ($id <= 0) throw new Exception('ID menu tidak valid.');
            if (empty($nama)) throw new Exception('Nama menu wajib diisi.');
            if ($idKat <= 0) throw new Exception('Kategori wajib dipilih.');
            if ($harga <= 0) throw new Exception('Harga harus lebih besar dari 0.');
            
            $stmt = $pdo->prepare("UPDATE menu SET id_kategori = ?, nama_menu = ?, harga = ? WHERE id_menu = ?");
            $stmt->execute([$idKat, $nama, $harga, $id]);
            setFlash('success', 'Menu berhasil diperbarui.');
            break;

        case 'delete':
            if ($id <= 0) throw new Exception('ID menu tidak valid.');
            // Cek detail_transaksi
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM detail_transaksi WHERE id_menu = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Menu tidak dapat dihapus karena sudah tercatat dalam transaksi.');
            }
            // Hapus resep terkait dulu (karena CASCADE), lalu menu
            $stmt = $pdo->prepare("DELETE FROM menu WHERE id_menu = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Menu berhasil dihapus.');
            break;

        default:
            throw new Exception('Aksi tidak valid.');
    }
} catch (Exception $e) {
    setFlash('error', $e->getMessage());
}

header("Location: menu.php");
exit;
