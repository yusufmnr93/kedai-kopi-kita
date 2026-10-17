<?php
/**
 * kategori_process.php
 * Proses CRUD Kategori (Create, Update, Delete)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner']);

$pdo    = getConnection();
$action = $_POST['action'] ?? '';
$id     = intval($_POST['id_kategori'] ?? 0);
$nama   = trim($_POST['nama_kategori'] ?? '');

try {
    switch ($action) {
        case 'create':
            // Validasi
            if (empty($nama)) {
                throw new Exception('Nama kategori wajib diisi.');
            }
            // Cek duplikat
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = ?");
            $stmt->execute([$nama]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Kategori "' . $nama . '" sudah ada.');
            }
            $stmt = $pdo->prepare("INSERT INTO kategori (nama_kategori) VALUES (?)");
            $stmt->execute([$nama]);
            setFlash('success', 'Kategori "' . $nama . '" berhasil ditambahkan.');
            break;

        case 'update':
            if ($id <= 0) throw new Exception('ID kategori tidak valid.');
            if (empty($nama)) throw new Exception('Nama kategori wajib diisi.');
            // Cek duplikat (kecuali dirinya sendiri)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM kategori WHERE nama_kategori = ? AND id_kategori != ?");
            $stmt->execute([$nama, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Kategori "' . $nama . '" sudah ada.');
            }
            $stmt = $pdo->prepare("UPDATE kategori SET nama_kategori = ? WHERE id_kategori = ?");
            $stmt->execute([$nama, $id]);
            setFlash('success', 'Kategori berhasil diperbarui.');
            break;

        case 'delete':
            if ($id <= 0) throw new Exception('ID kategori tidak valid.');
            // Cek apakah masih ada menu yang menggunakan kategori ini
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM menu WHERE id_kategori = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Kategori tidak dapat dihapus karena masih memiliki menu.');
            }
            $stmt = $pdo->prepare("DELETE FROM kategori WHERE id_kategori = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Kategori berhasil dihapus.');
            break;

        default:
            throw new Exception('Aksi tidak valid.');
    }
} catch (Exception $e) {
    setFlash('error', $e->getMessage());
}

header("Location: kategori.php");
exit;
