-- Sistem Pendataan Aset, tahap 2: master kategori, pengajuan barang, mutasi & peminjaman, penghapusan, stock opname.
-- Aman dijalankan berulang (IF NOT EXISTS / INSERT IGNORE).

ALTER TABLE `aset_barang` ADD COLUMN IF NOT EXISTS `posisi` VARCHAR(100) NOT NULL DEFAULT '';
ALTER TABLE `aset_foto` ADD COLUMN IF NOT EXISTS `pengajuan_id` INT UNSIGNED NULL;

CREATE TABLE IF NOT EXISTS `aset_kategori` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(30) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `umur_tahun` TINYINT UNSIGNED NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_kategori_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `aset_kategori` (`kode`, `nama`, `umur_tahun`) VALUES
  ('furnitur', 'Furnitur', 8),
  ('elektronik', 'Elektronik', 5),
  ('ac', 'Pendingin ruangan (AC)', 8),
  ('audio_visual', 'Audio visual / proyektor', 5),
  ('komputer', 'Komputer & jaringan', 4),
  ('alat_kantor', 'Alat kantor', 5),
  ('laboratorium', 'Peralatan laboratorium', 8),
  ('kendaraan', 'Kendaraan', 8),
  ('kebersihan', 'Alat kebersihan', 3),
  ('lainnya', 'Lainnya', 5);

CREATE TABLE IF NOT EXISTS `aset_pengajuan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor` VARCHAR(30) NOT NULL,
  `tanggal` DATE NOT NULL,
  `nama_barang` VARCHAR(200) NOT NULL,
  `kategori` VARCHAR(30) NOT NULL DEFAULT 'lainnya',
  `jumlah` INT UNSIGNED NOT NULL DEFAULT 1,
  `satuan` VARCHAR(20) NOT NULL DEFAULT 'unit',
  `estimasi_harga` DECIMAL(15,2) NULL,
  `ruang_id` INT UNSIGNED NULL,
  `alasan` TEXT NULL,
  `prioritas` ENUM('rendah','sedang','tinggi') NOT NULL DEFAULT 'sedang',
  `pemohon` VARCHAR(150) NOT NULL DEFAULT '',
  `status` ENUM('diajukan','disetujui','ditolak','dibeli','diterima') NOT NULL DEFAULT 'diajukan',
  `catatan_keputusan` TEXT NULL,
  `tanggal_keputusan` DATE NULL,
  `no_kwitansi` VARCHAR(60) NOT NULL DEFAULT '',
  `tanggal_kwitansi` DATE NULL,
  `penyedia` VARCHAR(150) NOT NULL DEFAULT '',
  `nilai_realisasi` DECIMAL(15,2) NULL,
  `barang_id` INT UNSIGNED NULL,
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_pengajuan_nomor` (`nomor`),
  KEY `idx_aset_pengajuan_status` (`status`),
  CONSTRAINT `fk_aset_pengajuan_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aset_pengajuan_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_mutasi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jenis` ENUM('pindah','pinjam') NOT NULL,
  `barang_id` INT UNSIGNED NOT NULL,
  `dari_ruang_id` INT UNSIGNED NULL,
  `ke_ruang_id` INT UNSIGNED NULL,
  `jumlah` INT UNSIGNED NOT NULL DEFAULT 1,
  `peminjam` VARCHAR(150) NOT NULL DEFAULT '',
  `unit_peminjam` VARCHAR(150) NOT NULL DEFAULT '',
  `tanggal` DATE NOT NULL,
  `rencana_kembali` DATE NULL,
  `tanggal_kembali` DATE NULL,
  `status` ENUM('selesai','dipinjam','dikembalikan') NOT NULL DEFAULT 'selesai',
  `kondisi_kembali` VARCHAR(30) NOT NULL DEFAULT '',
  `keterangan` TEXT NULL,
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aset_mutasi_jenis` (`jenis`, `status`),
  CONSTRAINT `fk_aset_mutasi_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aset_mutasi_dari` FOREIGN KEY (`dari_ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aset_mutasi_ke` FOREIGN KEY (`ke_ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_penghapusan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor` VARCHAR(30) NOT NULL,
  `barang_id` INT UNSIGNED NOT NULL,
  `tanggal` DATE NOT NULL,
  `alasan` ENUM('rusak_berat','hilang','usang','dijual','dihibahkan','lainnya') NOT NULL DEFAULT 'rusak_berat',
  `nilai_buku` DECIMAL(15,2) NULL,
  `keterangan` TEXT NULL,
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_penghapusan_nomor` (`nomor`),
  UNIQUE KEY `uq_aset_penghapusan_barang` (`barang_id`),
  CONSTRAINT `fk_aset_penghapusan_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_opname` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor` VARCHAR(30) NOT NULL,
  `tanggal` DATE NOT NULL,
  `ruang_id` INT UNSIGNED NULL,
  `petugas` VARCHAR(150) NOT NULL DEFAULT '',
  `status` ENUM('berjalan','selesai') NOT NULL DEFAULT 'berjalan',
  `catatan` TEXT NULL,
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `selesai_pada` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_opname_nomor` (`nomor`),
  CONSTRAINT `fk_aset_opname_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_opname_item` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `opname_id` INT UNSIGNED NOT NULL,
  `barang_id` INT UNSIGNED NULL,
  `kode` VARCHAR(50) NOT NULL DEFAULT '',
  `nama` VARCHAR(200) NOT NULL,
  `jumlah_sistem` INT UNSIGNED NOT NULL DEFAULT 1,
  `jumlah_fisik` INT UNSIGNED NULL,
  `kondisi_fisik` ENUM('baik','rusak_ringan','rusak_berat','hilang') NULL,
  `catatan` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_aset_opname_item` (`opname_id`),
  CONSTRAINT `fk_aset_opname_item_opname` FOREIGN KEY (`opname_id`) REFERENCES `aset_opname` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aset_opname_item_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
