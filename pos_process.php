<?php
/**
 * pos_process.php
 * Proses Transaksi POS — UC-03 Memproses Transaksi Penjualan
 * 
 * CORE MODULE Modul 7:
 * - Validasi input server-side
 * - Cek ketersediaan stok bahan baku via resep
 * - Simpan transaksi + detail_transaksi
 * - Kurangi stok_tersedia bahan_baku berdasarkan resep × porsi
 * - Menggunakan DB Transaction agar konsisten (rollback jika gagal)
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner', 'Kasir']);

$pdo = getConnection();
$user = getCurrentUser();

try {
    // === 1. Ambil & Validasi Input ===
    $cartData  = json_decode($_POST['cart_data'] ?? '[]', true);
    $metode    = trim($_POST['metode_pembayaran'] ?? '');
    $nominal   = floatval($_POST['nominal_bayar'] ?? 0);
    $totalPost = floatval($_POST['total_harga'] ?? 0);

    if (empty($cartData) || !is_array($cartData)) {
        throw new Exception('Keranjang kosong. Silakan pilih menu.');
    }

    if (!in_array($metode, ['Tunai', 'QRIS'])) {
        throw new Exception('Metode pembayaran tidak valid. Pilih Tunai atau QRIS.');
    }

    // === 2. Kalkulasi ulang total di server (jangan percaya client) ===
    $totalHarga = 0;
    $itemsValidated = [];

    foreach ($cartData as $item) {
        $menuId = intval($item['id'] ?? 0);
        $qty    = intval($item['qty'] ?? 0);

        if ($menuId <= 0) throw new Exception('ID menu tidak valid.');
        if ($qty <= 0) throw new Exception('Jumlah porsi harus lebih besar dari 0.');

        // Ambil data menu dari DB
        $stmt = $pdo->prepare("SELECT * FROM menu WHERE id_menu = ?");
        $stmt->execute([$menuId]);
        $menu = $stmt->fetch();

        if (!$menu) throw new Exception('Menu dengan ID ' . $menuId . ' tidak ditemukan.');

        $subtotal = $menu['harga'] * $qty;
        $totalHarga += $subtotal;

        $itemsValidated[] = [
            'id_menu' => $menuId,
            'nama'    => $menu['nama_menu'],
            'harga'   => $menu['harga'],
            'qty'     => $qty,
            'subtotal'=> $subtotal,
        ];
    }

    // === 3. Validasi pembayaran ===
    if ($metode === 'Tunai') {
        if ($nominal <= 0) {
            throw new Exception('Nominal bayar harus lebih besar dari 0.');
        }
        if ($nominal < $totalHarga) {
            throw new Exception('Nominal bayar (Rp ' . number_format($nominal, 0, ',', '.') . ') kurang dari total (Rp ' . number_format($totalHarga, 0, ',', '.') . ').');
        }
    } else {
        // QRIS: nominal_bayar = total_harga (pas)
        $nominal = $totalHarga;
    }

    $kembalian = $nominal - $totalHarga;

    // === 4. Cek ketersediaan stok bahan baku berdasarkan resep ===
    // Hitung total kebutuhan bahan dari seluruh item di keranjang
    $kebutuhanBahan = []; // id_bahan_baku => total_butuh

    foreach ($itemsValidated as $item) {
        $stmt = $pdo->prepare("
            SELECT r.id_bahan_baku, r.takaran, b.nama_bahan_baku, b.stok_tersedia, b.satuan
            FROM resep r
            JOIN bahan_baku b ON r.id_bahan_baku = b.id_bahan_baku
            WHERE r.id_menu = ?
        ");
        $stmt->execute([$item['id_menu']]);
        $resepList = $stmt->fetchAll();

        foreach ($resepList as $resep) {
            $idBahan = $resep['id_bahan_baku'];
            $butuh   = $resep['takaran'] * $item['qty'];

            if (!isset($kebutuhanBahan[$idBahan])) {
                $kebutuhanBahan[$idBahan] = [
                    'nama'  => $resep['nama_bahan_baku'],
                    'satuan'=> $resep['satuan'],
                    'stok'  => $resep['stok_tersedia'],
                    'butuh' => 0,
                ];
            }
            $kebutuhanBahan[$idBahan]['butuh'] += $butuh;
        }
    }

    // Validasi stok mencukupi
    foreach ($kebutuhanBahan as $idBahan => $data) {
        if ($data['butuh'] > $data['stok']) {
            throw new Exception(
                'Stok bahan baku "' . $data['nama'] . '" tidak mencukupi. ' .
                'Dibutuhkan: ' . fmtQty($data['butuh']) . ' ' . $data['satuan'] . ', ' .
                'Tersedia: ' . fmtQty($data['stok']) . ' ' . $data['satuan'] . '.'
            );
        }
    }

    // === 5. Database Transaction: Simpan transaksi + kurangi stok ===
    $pdo->beginTransaction();

    try {
        // 5a. Insert header transaksi
        $stmt = $pdo->prepare("
            INSERT INTO transaksi (id_pengguna, tanggal_transaksi, metode_pembayaran, total_harga, nominal_bayar, kembalian)
            VALUES (?, NOW(), ?, ?, ?, ?)
        ");
        $stmt->execute([$user['id'], $metode, $totalHarga, $nominal, $kembalian]);
        $idTransaksi = $pdo->lastInsertId();

        // 5b. Insert detail_transaksi
        $stmtDetail = $pdo->prepare("
            INSERT INTO detail_transaksi (id_transaksi, id_menu, jumlah_porsi, subtotal)
            VALUES (?, ?, ?, ?)
        ");
        foreach ($itemsValidated as $item) {
            $stmtDetail->execute([$idTransaksi, $item['id_menu'], $item['qty'], $item['subtotal']]);
        }

        // 5c. Kurangi stok bahan baku berdasarkan total kebutuhan resep
        $stmtUpdateStok = $pdo->prepare("
            UPDATE bahan_baku SET stok_tersedia = stok_tersedia - ? WHERE id_bahan_baku = ?
        ");
        foreach ($kebutuhanBahan as $idBahan => $data) {
            $stmtUpdateStok->execute([$data['butuh'], $idBahan]);
        }

        // 5d. Verifikasi tidak ada stok negatif (safety check)
        $stmtCheck = $pdo->query("SELECT id_bahan_baku, nama_bahan_baku FROM bahan_baku WHERE stok_tersedia < 0");
        $negatif = $stmtCheck->fetchAll();
        if (!empty($negatif)) {
            $names = array_map(fn($r) => $r['nama_bahan_baku'], $negatif);
            throw new Exception('Stok bahan baku berikut menjadi negatif: ' . implode(', ', $names) . '. Transaksi dibatalkan.');
        }

        $pdo->commit();

        // === 6. Redirect ke struk ===
        header("Location: receipt.php?id=" . $idTransaksi);
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    setFlash('error', $e->getMessage());
    header("Location: pos.php");
    exit;
}
