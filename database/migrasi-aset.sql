-- Sistem Pendataan Aset: ruangan, inventaris barang, foto, perawatan (kebersihan & AC), laporan kerusakan.
-- Akses hanya untuk pengguna dengan pengguna.akses_aset = 1 (Kabid Sarana dan Prasarana).
-- Aman dijalankan berulang (IF NOT EXISTS).

ALTER TABLE `pengguna` ADD COLUMN IF NOT EXISTS `akses_aset` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `aset_ruang` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(30) NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `gedung` VARCHAR(100) NOT NULL DEFAULT '',
  `lantai` VARCHAR(20) NOT NULL DEFAULT '',
  `jenis` VARCHAR(30) NOT NULL DEFAULT 'lainnya',
  `kapasitas` INT UNSIGNED NULL,
  `luas` DECIMAL(9,2) NULL,
  `penanggung_jawab` VARCHAR(150) NOT NULL DEFAULT '',
  `keterangan` TEXT NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_ruang_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_barang` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(50) NOT NULL,
  `nama` VARCHAR(200) NOT NULL,
  `kategori` VARCHAR(30) NOT NULL DEFAULT 'lainnya',
  `merek` VARCHAR(100) NOT NULL DEFAULT '',
  `model` VARCHAR(100) NOT NULL DEFAULT '',
  `no_seri` VARCHAR(100) NOT NULL DEFAULT '',
  `ruang_id` INT UNSIGNED NULL,
  `jumlah` INT UNSIGNED NOT NULL DEFAULT 1,
  `satuan` VARCHAR(20) NOT NULL DEFAULT 'unit',
  `tahun` SMALLINT UNSIGNED NULL,
  `harga` DECIMAL(15,2) NULL,
  `sumber_dana` VARCHAR(100) NOT NULL DEFAULT '',
  `kondisi` ENUM('baik','rusak_ringan','rusak_berat','hilang') NOT NULL DEFAULT 'baik',
  `status` ENUM('aktif','dihapuskan') NOT NULL DEFAULT 'aktif',
  `keterangan` TEXT NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_barang_kode` (`kode`),
  KEY `idx_aset_barang_ruang` (`ruang_id`),
  CONSTRAINT `fk_aset_barang_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_perawatan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `jenis` ENUM('kebersihan','ac') NOT NULL,
  `ruang_id` INT UNSIGNED NULL,
  `barang_id` INT UNSIGNED NULL,
  `kegiatan` VARCHAR(200) NOT NULL,
  `tanggal` DATE NOT NULL,
  `status` ENUM('terjadwal','selesai') NOT NULL DEFAULT 'terjadwal',
  `petugas` VARCHAR(150) NOT NULL DEFAULT '',
  `ulang_hari` SMALLINT UNSIGNED NULL,
  `biaya` DECIMAL(15,2) NULL,
  `catatan` TEXT NULL,
  `selesai_pada` DATE NULL,
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_aset_rawat_jenis` (`jenis`, `status`, `tanggal`),
  CONSTRAINT `fk_aset_rawat_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aset_rawat_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_kerusakan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nomor` VARCHAR(30) NOT NULL,
  `jenis` ENUM('sarana','prasarana') NOT NULL,
  `ruang_id` INT UNSIGNED NULL,
  `barang_id` INT UNSIGNED NULL,
  `bagian` VARCHAR(100) NOT NULL DEFAULT '',
  `judul` VARCHAR(200) NOT NULL,
  `uraian` TEXT NULL,
  `tingkat` ENUM('ringan','sedang','berat') NOT NULL DEFAULT 'ringan',
  `status` ENUM('dilaporkan','diproses','selesai','ditolak') NOT NULL DEFAULT 'dilaporkan',
  `tanggal_lapor` DATE NOT NULL,
  `pelapor` VARCHAR(150) NOT NULL DEFAULT '',
  `tindak_lanjut` TEXT NULL,
  `tanggal_selesai` DATE NULL,
  `biaya` DECIMAL(15,2) NULL,
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_aset_kerusakan_nomor` (`nomor`),
  KEY `idx_aset_kerusakan_status` (`jenis`, `status`),
  CONSTRAINT `fk_aset_rusak_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_aset_rusak_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `aset_foto` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `ruang_id` INT UNSIGNED NULL,
  `barang_id` INT UNSIGNED NULL,
  `kerusakan_id` INT UNSIGNED NULL,
  `berkas` VARCHAR(80) NOT NULL,
  `keterangan` VARCHAR(200) NOT NULL DEFAULT '',
  `diunggah` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_aset_foto_ruang` (`ruang_id`),
  KEY `idx_aset_foto_barang` (`barang_id`),
  KEY `idx_aset_foto_rusak` (`kerusakan_id`),
  CONSTRAINT `fk_aset_foto_ruang` FOREIGN KEY (`ruang_id`) REFERENCES `aset_ruang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aset_foto_barang` FOREIGN KEY (`barang_id`) REFERENCES `aset_barang` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_aset_foto_rusak` FOREIGN KEY (`kerusakan_id`) REFERENCES `aset_kerusakan` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beri akses kepada Kepala Bidang Sarana dan Prasarana.
UPDATE `pengguna` p
  JOIN `peran` pr ON pr.id = p.peran_id
  JOIN `unit` un ON un.id = p.unit_id
   SET p.akses_aset = 1
 WHERE pr.kode = 'kabid' AND un.nama LIKE '%Sarana%';
