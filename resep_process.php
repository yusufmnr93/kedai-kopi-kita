<?php
/**
 * resep_process.php
 * Proses CRUD Resep
 */
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/config/database.php';
requireRole(['Owner']);

$pdo      = getConnection();
$action   = $_POST['action'] ?? '';
$id       = intval($_POST['id_resep'] ?? 0);
$idMenu   = intval($_POST['id_menu'] ?? 0);
$idBahan  = intval($_POST['id_bahan_baku'] ?? 0);
$takaran  = floatval($_POST['takaran'] ?? 0);

try {
    switch ($action) {
        case 'create':
            if ($idMenu <= 0)  throw new Exception('Menu wajib dipilih.');
            if ($idBahan <= 0) throw new Exception('Bahan baku wajib dipilih.');
            if ($takaran <= 0) throw new Exception('Takaran harus lebih besar dari 0.');
            
            // Cek duplikat kombinasi menu + bahan
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resep WHERE id_menu = ? AND id_bahan_baku = ?");
            $stmt->execute([$idMenu, $idBahan]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Kombinasi menu dan bahan baku ini sudah ada dalam resep.');
            }
            
            $stmt = $pdo->prepare("INSERT INTO resep (id_menu, id_bahan_baku, takaran) VALUES (?, ?, ?)");
            $stmt->execute([$idMenu, $idBahan, $takaran]);
            setFlash('success', 'Resep berhasil ditambahkan.');
            break;

        case 'update':
            if ($id <= 0)      throw new Exception('ID resep tidak valid.');
            if ($idMenu <= 0)  throw new Exception('Menu wajib dipilih.');
            if ($idBahan <= 0) throw new Exception('Bahan baku wajib dipilih.');
            if ($takaran <= 0) throw new Exception('Takaran harus lebih besar dari 0.');
            
            // Cek duplikat (kecuali dirinya sendiri)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM resep WHERE id_menu = ? AND id_bahan_baku = ? AND id_resep != ?");
            $stmt->execute([$idMenu, $idBahan, $id]);
            if ($stmt->fetchColumn() > 0) {
                throw new Exception('Kombinasi menu dan bahan baku ini sudah ada dalam resep.');
            }
            
            $stmt = $pdo->prepare("UPDATE resep SET id_menu = ?, id_bahan_baku = ?, takaran = ? WHERE id_resep = ?");
            $stmt->execute([$idMenu, $idBahan, $takaran, $id]);
            setFlash('success', 'Resep berhasil diperbarui.');
            break;

        case 'delete':
            if ($id <= 0) throw new Exception('ID resep tidak valid.');
            $stmt = $pdo->prepare("DELETE FROM resep WHERE id_resep = ?");
            $stmt->execute([$id]);
            setFlash('success', 'Resep berhasil dihapus.');
            break;

        default:
            throw new Exception('Aksi tidak valid.');
    }
} catch (Exception $e) {
    setFlash('error', $e->getMessage());
}

header("Location: resep.php");
exit;
