<?php
/**
 * bahan_baku_process.php
 * Proses CRUD Bahan Baku
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Barista']);

$pdo    = getConnection();
$action = $_POST['action'] ?? '';
$id     = intval($_POST['id_bahan_baku'] ?? 0);
$nama   = trim($_POST['nama_bahan_baku'] ?? '');
$satuan = trim($_POST['satuan'] ?? '');
$stok   = floatval($_POST['stok_tersedia'] ?? 0);
$minimum= floatval($_POST['stok_minimum'] ?? 0);

try {
    switch ($action) {
        case 'create':
            if ($nama === '')   throw new Exception('Nama bahan baku wajib diisi.');
            if ($satuan === '') throw new Exception('Satuan wajib dipilih.');
            cekPanjang($nama, 100, 'Nama bahan baku');
            cekPanjang($satuan, 20, 'Satuan');
            cekMaks($stok, 'Stok');
            cekMaks($minimum, 'Stok minimum');
            if ($stok < 0)      throw new Exception('Stok tidak boleh negatif.');
            if ($minimum < 0)   throw new Exception('Stok minimum tidak boleh negatif.');
            
            $stmt = $pdo->prepare("INSERT INTO bahan_baku (nama_bahan_baku, satuan, stok_tersedia, stok_minimum) VALUES (?, ?, ?, ?)");
            $stmt->execute([$nama, $satuan, $stok, $minimum]);
            setFlash('success', 'Bahan baku "' . $nama . '" berhasil ditambahkan.');
            break;

        case 'update':
            if ($id <= 0) throw new Exception('ID bahan baku tidak valid.');
            if ($nama === '')   throw new Exception('Nama bahan baku wajib diisi.');
            if ($satuan === '') throw new Exception('Satuan wajib dipilih.');
            cekPanjang($nama, 100, 'Nama bahan baku');
            cekPanjang($satuan, 20, 'Satuan');
            cekMaks($stok, 'Stok');
            cekMaks($minimum, 'Stok minimum');
            if ($minimum < 0)   throw new Exception('Stok minimum tidak boleh negatif.');

            // BR (Modul 8): stok_tersedia TIDAK diubah dari form master data.
            // Stok hanya berubah lewat Stok Masuk (UC-06) dan transaksi POS (UC-03),
            // sehingga setiap perubahan stok memiliki jejak di database.
            $stmt = $pdo->prepare("UPDATE bahan_baku SET nama_bahan_baku = ?, satuan = ?, stok_minimum = ? WHERE id_bahan_baku = ?");
            $stmt->execute([$nama, $satuan, $minimum, $id]);
            setFlash('success', 'Bahan baku berhasil diperbarui.');
            break;

        case 'delete':
            if ($id <= 0) throw new Exception('ID bahan baku tidak valid.');
            // Cek apakah bahan dipakai di resep
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resep WHERE id_bahan_baku = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Bahan baku tidak dapat dihapus karena masih digunakan dalam resep.');
            }
            $stmt = $pdo->prepare("DELETE FROM bahan_baku WHERE id_bahan_baku = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Bahan baku berhasil dihapus.');
            break;

        default:
            throw new Exception('Aksi tidak valid.');
    }
} catch (Exception $e) {
    setFlash('error', userError($e));
}

header("Location: bahan_baku.php");
exit;
