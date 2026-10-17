<?php
/**
 * stok_masuk_process.php
 * Proses pencatatan stok masuk — menambah stok_tersedia di bahan_baku
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Barista']);

$pdo = getConnection();

$idBahan   = intval($_POST['id_bahan_baku'] ?? 0);
$jumlah    = floatval($_POST['jumlah_masuk'] ?? 0);
$tanggal   = trim($_POST['tanggal_masuk'] ?? '');
$keterangan= trim($_POST['keterangan'] ?? '');
$userId    = getCurrentUser()['id'];

try {
    if ($idBahan <= 0) throw new Exception('Bahan baku wajib dipilih.');
    if ($jumlah <= 0)  throw new Exception('Jumlah masuk harus lebih besar dari 0.');
    if (empty($tanggal)) throw new Exception('Tanggal masuk wajib diisi.');

    // Gunakan transaction untuk konsistensi
    $pdo->beginTransaction();

    // 1. Catat di tabel stok_masuk
    $stmt = $pdo->prepare("INSERT INTO stok_masuk (id_bahan_baku, id_pengguna, tanggal_masuk, jumlah_masuk, keterangan) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$idBahan, $userId, $tanggal, $jumlah, $keterangan ?: null]);

    // 2. Tambahkan stok_tersedia di bahan_baku
    $stmt = $pdo->prepare("UPDATE bahan_baku SET stok_tersedia = stok_tersedia + ? WHERE id_bahan_baku = ?");
    $stmt->execute([$jumlah, $idBahan]);

    $pdo->commit();
    setFlash('success', 'Stok masuk berhasil dicatat. Stok bahan baku telah diperbarui.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    setFlash('error', $e->getMessage());
}

header("Location: stok_masuk.php");
exit;
