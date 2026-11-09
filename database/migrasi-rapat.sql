-- Modul Berita Acara Rapat: agenda, absensi, notulen, RTL, dokumentasi.
-- Aman dijalankan berulang (IF NOT EXISTS / INSERT IGNORE).

CREATE TABLE IF NOT EXISTS `rapat` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT UNSIGNED NULL,
  `judul` VARCHAR(255) NOT NULL,
  `jenis` ENUM('antar_bidang','pimpinan','internal','lainnya') NOT NULL DEFAULT 'antar_bidang',
  `nomor` VARCHAR(100) NOT NULL DEFAULT '',
  `tanggal` DATE NOT NULL,
  `jam_mulai` TIME NULL,
  `jam_selesai` TIME NULL,
  `tempat` VARCHAR(200) NOT NULL DEFAULT '',
  `unit_id` INT UNSIGNED NULL,
  `pimpinan_nama` VARCHAR(150) NOT NULL DEFAULT '',
  `pimpinan_jabatan` VARCHAR(150) NOT NULL DEFAULT '',
  `notulis_id` INT UNSIGNED NULL,
  `agenda` TEXT NULL,
  `pembahasan` MEDIUMTEXT NULL,
  `keputusan` TEXT NULL,
  `status` ENUM('draft','final') NOT NULL DEFAULT 'draft',
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rapat_tanggal` (`tanggal`),
  CONSTRAINT `fk_rapat_periode` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rapat_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rapat_notulis` FOREIGN KEY (`notulis_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_rapat_pembuat` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rapat_peserta` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rapat_id` INT UNSIGNED NOT NULL,
  `pengguna_id` INT UNSIGNED NULL,
  `nama` VARCHAR(150) NOT NULL,
  `jabatan` VARCHAR(150) NOT NULL DEFAULT '',
  `unit_nama` VARCHAR(150) NOT NULL DEFAULT '',
  `eksternal` TINYINT(1) NOT NULL DEFAULT 0,
  `kehadiran` ENUM('belum','hadir','izin','sakit','tanpa_kabar') NOT NULL DEFAULT 'belum',
  `waktu_hadir` DATETIME NULL,
  `keterangan` VARCHAR(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_peserta` (`rapat_id`, `pengguna_id`),
  CONSTRAINT `fk_peserta_rapat` FOREIGN KEY (`rapat_id`) REFERENCES `rapat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_peserta_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rapat_rtl` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rapat_id` INT UNSIGNED NOT NULL,
  `unit_id` INT UNSIGNED NULL,
  `kendala` TEXT NULL,
  `rtl` TEXT NOT NULL,
  `penanggung_jawab` VARCHAR(150) NOT NULL DEFAULT '',
  `tenggat` DATE NULL,
  `status` ENUM('baru','berjalan','selesai','tertunda') NOT NULL DEFAULT 'baru',
  `hasil` VARCHAR(500) NOT NULL DEFAULT '',
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `diubah` DATETIME NULL,
  PRIMARY KEY (`id`),
  KEY `idx_rtl_status` (`status`),
  CONSTRAINT `fk_rtl_rapat` FOREIGN KEY (`rapat_id`) REFERENCES `rapat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_rtl_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `rapat_dokumentasi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `rapat_id` INT UNSIGNED NOT NULL,
  `berkas` VARCHAR(120) NOT NULL,
  `keterangan` VARCHAR(255) NOT NULL DEFAULT '',
  `diunggah_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_dok_rapat` FOREIGN KEY (`rapat_id`) REFERENCES `rapat` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_dok_pengguna` FOREIGN KEY (`diunggah_oleh`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pemberitahuan undangan rapat (lonceng di header).
ALTER TABLE `rapat_peserta`
  ADD COLUMN IF NOT EXISTS `diundang_pada` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS `dilihat_pada` DATETIME NULL;

-- Izin baru: administrator, kepala biro, dan kepala bidang dapat melihat semua dan mengelola berita acara.
INSERT IGNORE INTO `peran_izin` (`peran_id`, `izin`)
SELECT `id`, 'rapat_lihat_semua' FROM `peran` WHERE `kode` IN ('admin', 'kabiro', 'kabid');
INSERT IGNORE INTO `peran_izin` (`peran_id`, `izin`)
SELECT `id`, 'rapat_kelola' FROM `peran` WHERE `kode` IN ('admin', 'kabiro', 'kabid');
