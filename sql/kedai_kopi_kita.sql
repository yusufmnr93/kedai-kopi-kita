-- ============================================================
-- Database: kedai_kopi_kita
-- Sistem Kasir dan Inventaris Bahan Baku Kedai Kopi Kita
-- Berdasarkan rancangan Laprak 5 (Database Design)
-- ============================================================

CREATE DATABASE IF NOT EXISTS `kedai_kopi_kita`
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `kedai_kopi_kita`;

-- ============================================================
-- 1. Tabel pengguna
-- Menyimpan akun login (Owner, Kasir, Barista)
-- ============================================================
CREATE TABLE IF NOT EXISTS `pengguna` (
  `id_pengguna`   INT AUTO_INCREMENT PRIMARY KEY,
  `nama_lengkap`  VARCHAR(100)  NOT NULL,
  `username`      VARCHAR(50)   NOT NULL UNIQUE,
  `password`      VARCHAR(255)  NOT NULL,
  `role`          ENUM('Owner','Kasir','Barista') NOT NULL
) ENGINE=InnoDB;

-- ============================================================
-- 2. Tabel kategori
-- Mengelompokkan menu (Coffee, Non-Coffee, Pastry, dll.)
-- ============================================================
CREATE TABLE IF NOT EXISTS `kategori` (
  `id_kategori`    INT AUTO_INCREMENT PRIMARY KEY,
  `nama_kategori`  VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- ============================================================
-- 3. Tabel menu
-- Produk makanan/minuman beserta harga jual
-- ============================================================
CREATE TABLE IF NOT EXISTS `menu` (
  `id_menu`      INT AUTO_INCREMENT PRIMARY KEY,
  `id_kategori`  INT            NOT NULL,
  `nama_menu`    VARCHAR(100)   NOT NULL,
  `harga`        DECIMAL(10,2)  NOT NULL,
  CONSTRAINT `fk_menu_kategori`
    FOREIGN KEY (`id_kategori`) REFERENCES `kategori`(`id_kategori`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 4. Tabel bahan_baku
-- Inventaris bahan baku + stok & batas minimum
-- ============================================================
CREATE TABLE IF NOT EXISTS `bahan_baku` (
  `id_bahan_baku`   INT AUTO_INCREMENT PRIMARY KEY,
  `nama_bahan_baku` VARCHAR(100)   NOT NULL,
  `satuan`          VARCHAR(20)    NOT NULL,
  `stok_tersedia`   DECIMAL(10,2)  NOT NULL DEFAULT 0,
  `stok_minimum`    DECIMAL(10,2)  NOT NULL DEFAULT 0
) ENGINE=InnoDB;

-- ============================================================
-- 5. Tabel resep (junction: menu <-> bahan_baku)
-- Takaran bahan per 1 porsi menu
-- ============================================================
CREATE TABLE IF NOT EXISTS `resep` (
  `id_resep`       INT AUTO_INCREMENT PRIMARY KEY,
  `id_menu`        INT            NOT NULL,
  `id_bahan_baku`  INT            NOT NULL,
  `takaran`        DECIMAL(10,2)  NOT NULL,
  CONSTRAINT `fk_resep_menu`
    FOREIGN KEY (`id_menu`) REFERENCES `menu`(`id_menu`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_resep_bahan`
    FOREIGN KEY (`id_bahan_baku`) REFERENCES `bahan_baku`(`id_bahan_baku`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 6. Tabel transaksi
-- Header transaksi penjualan POS
-- ============================================================
CREATE TABLE IF NOT EXISTS `transaksi` (
  `id_transaksi`        INT AUTO_INCREMENT PRIMARY KEY,
  `id_pengguna`         INT              NOT NULL,
  `tanggal_transaksi`   DATETIME         NOT NULL,
  `metode_pembayaran`   ENUM('Tunai','QRIS') NOT NULL,
  `total_harga`         DECIMAL(12,2)    NOT NULL,
  `nominal_bayar`       DECIMAL(12,2)    NOT NULL,
  `kembalian`           DECIMAL(12,2)    NOT NULL DEFAULT 0,
  CONSTRAINT `fk_transaksi_pengguna`
    FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna`(`id_pengguna`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 7. Tabel detail_transaksi (junction: transaksi <-> menu)
-- Item pesanan per transaksi
-- ============================================================
CREATE TABLE IF NOT EXISTS `detail_transaksi` (
  `id_detail_transaksi` INT AUTO_INCREMENT PRIMARY KEY,
  `id_transaksi`        INT            NOT NULL,
  `id_menu`             INT            NOT NULL,
  `jumlah_porsi`        INT            NOT NULL,
  `subtotal`            DECIMAL(12,2)  NOT NULL,
  CONSTRAINT `fk_detail_transaksi`
    FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi`(`id_transaksi`)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_detail_menu`
    FOREIGN KEY (`id_menu`) REFERENCES `menu`(`id_menu`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- 8. Tabel stok_masuk
-- Histori penerimaan stok dari supplier
-- ============================================================
CREATE TABLE IF NOT EXISTS `stok_masuk` (
  `id_stok_masuk`  INT AUTO_INCREMENT PRIMARY KEY,
  `id_bahan_baku`  INT            NOT NULL,
  `id_pengguna`    INT            NOT NULL,
  `tanggal_masuk`  DATETIME       NOT NULL,
  `jumlah_masuk`   DECIMAL(10,2)  NOT NULL,
  `keterangan`     VARCHAR(255)   DEFAULT NULL,
  CONSTRAINT `fk_stok_bahan`
    FOREIGN KEY (`id_bahan_baku`) REFERENCES `bahan_baku`(`id_bahan_baku`)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT `fk_stok_pengguna`
    FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna`(`id_pengguna`)
    ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Pengguna (password di-hash menggunakan password_hash PHP)
-- Password: owner123, kasir123, barista123
INSERT INTO `pengguna` (`nama_lengkap`, `username`, `password`, `role`) VALUES
('Pak Budi (Owner)',   'owner',   '$2y$10$AELgTR1sKFTwWEJmQa2rGO7mzTe1.2uTDwbU8uA/1/WTn/nps/Deq', 'Owner'),
('Rian Kasir',         'kasir',   '$2y$10$qVFTKq/OzJdZpyy.vmicx./Xo5iXq6PyonBzJ0jq01dduelEPcram', 'Kasir'),
('Siti Barista',       'barista', '$2y$10$hbStWixe5yuhlEAOzFedZ.JVI169iRSZkgrTbD.GO9bgg5jTH/s2e', 'Barista');

-- Kategori menu
INSERT INTO `kategori` (`nama_kategori`) VALUES
('Espresso Based'),
('Non-Coffee'),
('Manual Brew'),
('Pastry');

-- Menu
INSERT INTO `menu` (`id_kategori`, `nama_menu`, `harga`) VALUES
(1, 'Espresso',        15000.00),
(1, 'Americano',       18000.00),
(1, 'Caffe Latte',     22000.00),
(1, 'Cappuccino',      22000.00),
(1, 'Caramel Macchiato', 28000.00),
(2, 'Matcha Latte',    25000.00),
(2, 'Cokelat Panas',   20000.00),
(2, 'Teh Tarik',       15000.00),
(3, 'V60 Drip',        25000.00),
(3, 'French Press',    23000.00),
(4, 'Croissant',       18000.00),
(4, 'Banana Bread',    16000.00);

-- Bahan baku
INSERT INTO `bahan_baku` (`nama_bahan_baku`, `satuan`, `stok_tersedia`, `stok_minimum`) VALUES
('Biji Kopi Arabica', 'gram',  5000.00, 500.00),
('Susu UHT Fresh',    'ml',    10000.00, 1000.00),
('Air Mineral',       'ml',    50000.00, 5000.00),
('Gula Pasir',        'gram',  3000.00, 300.00),
('Sirup Caramel',     'ml',    2000.00, 200.00),
('Bubuk Matcha',      'gram',  500.00, 50.00),
('Bubuk Cokelat',     'gram',  1000.00, 100.00),
('Teh Tarik Blend',   'gram',  800.00, 80.00),
('Susu Kental Manis', 'ml',    2000.00, 200.00),
('Butter Croissant',  'pcs',   50.00, 10.00),
('Pisang',            'pcs',   30.00, 5.00),
('Tepung Terigu',     'gram',  2000.00, 200.00);

-- Resep (takaran per 1 porsi)
INSERT INTO `resep` (`id_menu`, `id_bahan_baku`, `takaran`) VALUES
-- Espresso: 18g kopi, 30ml air
(1, 1, 18.00),
(1, 3, 30.00),
-- Americano: 18g kopi, 200ml air
(2, 1, 18.00),
(2, 3, 200.00),
-- Caffe Latte: 18g kopi, 200ml susu
(3, 1, 18.00),
(3, 2, 200.00),
-- Cappuccino: 18g kopi, 150ml susu
(4, 1, 18.00),
(4, 2, 150.00),
-- Caramel Macchiato: 18g kopi, 200ml susu, 30ml sirup caramel
(5, 1, 18.00),
(5, 2, 200.00),
(5, 5, 30.00),
-- Matcha Latte: 5g matcha, 250ml susu, 10g gula
(6, 6, 5.00),
(6, 2, 250.00),
(6, 4, 10.00),
-- Cokelat Panas: 25g cokelat, 200ml susu, 15g gula
(7, 7, 25.00),
(7, 2, 200.00),
(7, 4, 15.00),
-- Teh Tarik: 10g teh, 150ml susu kental manis, 200ml air
(8, 8, 10.00),
(8, 9, 150.00),
(8, 3, 200.00),
-- V60 Drip: 15g kopi, 250ml air
(9, 1, 15.00),
(9, 3, 250.00),
-- French Press: 20g kopi, 300ml air
(10, 1, 20.00),
(10, 3, 300.00),
-- Croissant: 1 pcs butter croissant
(11, 10, 1.00),
-- Banana Bread: 1 pcs pisang, 50g tepung
(12, 11, 1.00),
(12, 12, 50.00);
