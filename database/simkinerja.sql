CREATE DATABASE IF NOT EXISTS `simkinerja_bauk` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `simkinerja_bauk`;

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE IF NOT EXISTS `unit` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `induk_id` INT UNSIGNED NULL,
  `kode` VARCHAR(20) NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `urut` INT NOT NULL DEFAULT 0,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_unit_kode` (`kode`),
  KEY `ix_unit_induk` (`induk_id`),
  CONSTRAINT `fk_unit_induk` FOREIGN KEY (`induk_id`) REFERENCES `unit` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `peran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kode` VARCHAR(40) NOT NULL,
  `nama` VARCHAR(100) NOT NULL,
  `keterangan` VARCHAR(255) NOT NULL DEFAULT '',
  `lingkup` ENUM('diri','unit','semua') NOT NULL DEFAULT 'diri',
  `sistem` TINYINT(1) NOT NULL DEFAULT 0,
  `urut` INT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_peran_kode` (`kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `peran_izin` (
  `peran_id` INT UNSIGNED NOT NULL,
  `izin` VARCHAR(40) NOT NULL,
  PRIMARY KEY (`peran_id`, `izin`),
  CONSTRAINT `fk_izin_peran` FOREIGN KEY (`peran_id`) REFERENCES `peran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pengguna` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(60) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `nama` VARCHAR(150) NOT NULL,
  `nip` VARCHAR(40) NOT NULL DEFAULT '',
  `jabatan` VARCHAR(150) NOT NULL DEFAULT '',
  `email` VARCHAR(150) NOT NULL DEFAULT '',
  `foto` VARCHAR(120) NULL,
  `unit_id` INT UNSIGNED NULL,
  `peran_id` INT UNSIGNED NOT NULL,
  `atasan_id` INT UNSIGNED NULL,
  `aktif` TINYINT(1) NOT NULL DEFAULT 1,
  `sandi_bawaan` TINYINT(1) NOT NULL DEFAULT 1,
  `login_terakhir` DATETIME NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_pengguna_username` (`username`),
  KEY `ix_pengguna_unit` (`unit_id`),
  KEY `ix_pengguna_atasan` (`atasan_id`),
  CONSTRAINT `fk_pengguna_unit` FOREIGN KEY (`unit_id`) REFERENCES `unit` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_pengguna_peran` FOREIGN KEY (`peran_id`) REFERENCES `peran` (`id`),
  CONSTRAINT `fk_pengguna_atasan` FOREIGN KEY (`atasan_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `periode` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(100) NOT NULL,
  `mulai` DATE NOT NULL,
  `selesai` DATE NOT NULL,
  `tahap` ENUM('perencanaan','pelaksanaan','evaluasi','pengembangan','selesai') NOT NULL DEFAULT 'perencanaan',
  `aktif` TINYINT(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `sasaran` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT UNSIGNED NOT NULL,
  `pengguna_id` INT UNSIGNED NOT NULL,
  `jenis` ENUM('rutin','panitia','insidental') NOT NULL DEFAULT 'rutin',
  `uraian` VARCHAR(500) NOT NULL,
  `indikator` VARCHAR(500) NOT NULL DEFAULT '',
  `target` DECIMAL(12,2) NOT NULL DEFAULT 0,
  `target_tw1` DECIMAL(12,2) NULL,
  `target_tw2` DECIMAL(12,2) NULL,
  `target_tw3` DECIMAL(12,2) NULL,
  `target_tw4` DECIMAL(12,2) NULL,
  `satuan` VARCHAR(40) NOT NULL DEFAULT 'kegiatan',
  `bobot` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `realisasi` DECIMAL(12,2) NULL,
  `status` ENUM('draft','diajukan','revisi','disetujui') NOT NULL DEFAULT 'draft',
  `catatan_atasan` VARCHAR(1000) NOT NULL DEFAULT '',
  `disetujui_oleh` INT UNSIGNED NULL,
  `disetujui_pada` DATETIME NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_sasaran_pegawai` (`periode_id`, `pengguna_id`),
  CONSTRAINT `fk_sasaran_periode` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_sasaran_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `realisasi_tw` (
  `sasaran_id` INT UNSIGNED NOT NULL,
  `tw` TINYINT NOT NULL,
  `realisasi` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`sasaran_id`, `tw`),
  CONSTRAINT `fk_rtw_sasaran` FOREIGN KEY (`sasaran_id`) REFERENCES `sasaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `kegiatan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT UNSIGNED NOT NULL,
  `pengguna_id` INT UNSIGNED NOT NULL,
  `sasaran_id` INT UNSIGNED NULL,
  `tanggal` DATE NOT NULL,
  `jam_mulai` TIME NULL,
  `jam_selesai` TIME NULL,
  `jenis` ENUM('rutin','panitia','insidental') NOT NULL DEFAULT 'rutin',
  `judul` VARCHAR(255) NOT NULL,
  `uraian` TEXT NULL,
  `hasil` VARCHAR(500) NOT NULL DEFAULT '',
  `volume` DECIMAL(12,2) NOT NULL DEFAULT 1,
  `satuan` VARCHAR(40) NOT NULL DEFAULT 'kegiatan',
  `kepanitiaan` VARCHAR(255) NOT NULL DEFAULT '',
  `peran_panitia` VARCHAR(100) NOT NULL DEFAULT '',
  `nomor_sk` VARCHAR(100) NOT NULL DEFAULT '',
  `pemberi_tugas` VARCHAR(150) NOT NULL DEFAULT '',
  `tautan_bukti` VARCHAR(1000) NOT NULL DEFAULT '',
  `status` ENUM('diajukan','revisi','disetujui') NOT NULL DEFAULT 'diajukan',
  `catatan_atasan` VARCHAR(1000) NOT NULL DEFAULT '',
  `diverifikasi_oleh` INT UNSIGNED NULL,
  `diverifikasi_pada` DATETIME NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_kegiatan_pegawai` (`periode_id`, `pengguna_id`, `tanggal`),
  KEY `ix_kegiatan_status` (`status`),
  KEY `ix_kegiatan_sasaran` (`sasaran_id`),
  CONSTRAINT `fk_kegiatan_periode` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kegiatan_pengguna` FOREIGN KEY (`pengguna_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_kegiatan_sasaran` FOREIGN KEY (`sasaran_id`) REFERENCES `sasaran` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `umpan_balik` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT UNSIGNED NOT NULL,
  `pegawai_id` INT UNSIGNED NOT NULL,
  `pemberi_id` INT UNSIGNED NOT NULL,
  `jenis` ENUM('apresiasi','arahan','koreksi') NOT NULL DEFAULT 'arahan',
  `isi` TEXT NOT NULL,
  `tanggapan` TEXT NULL,
  `dibaca` TINYINT(1) NOT NULL DEFAULT 0,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_ub_pegawai` (`pegawai_id`, `periode_id`),
  CONSTRAINT `fk_ub_periode` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ub_pemberi` FOREIGN KEY (`pemberi_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `evaluasi` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT UNSIGNED NOT NULL,
  `pegawai_id` INT UNSIGNED NOT NULL,
  `tw` TINYINT NOT NULL DEFAULT 1,
  `penilai_id` INT UNSIGNED NULL,
  `pk_pelayanan` TINYINT NULL,
  `pk_akuntabel` TINYINT NULL,
  `pk_kompeten` TINYINT NULL,
  `pk_harmonis` TINYINT NULL,
  `pk_loyal` TINYINT NULL,
  `pk_adaptif` TINYINT NULL,
  `pk_kolaboratif` TINYINT NULL,
  `nilai_capaian` DECIMAL(6,2) NULL,
  `nilai_perilaku` DECIMAL(6,2) NULL,
  `nilai_akhir` DECIMAL(6,2) NULL,
  `predikat` VARCHAR(30) NOT NULL DEFAULT '',
  `kekuatan` TEXT NULL,
  `perbaikan` TEXT NULL,
  `status` ENUM('draft','final') NOT NULL DEFAULT 'draft',
  `difinalkan_pada` DATETIME NULL,
  `diubah` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_evaluasi_tw` (`periode_id`, `pegawai_id`, `tw`),
  CONSTRAINT `fk_ev_periode` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ev_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_ev_penilai` FOREIGN KEY (`penilai_id`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `pengembangan` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `periode_id` INT UNSIGNED NOT NULL,
  `pegawai_id` INT UNSIGNED NOT NULL,
  `jenis` ENUM('penghargaan','pelatihan','promosi','pembinaan','mutasi') NOT NULL DEFAULT 'pelatihan',
  `uraian` VARCHAR(1000) NOT NULL,
  `target_waktu` DATE NULL,
  `status` ENUM('usulan','disetujui','berjalan','selesai','batal') NOT NULL DEFAULT 'usulan',
  `hasil` VARCHAR(1000) NOT NULL DEFAULT '',
  `dibuat_oleh` INT UNSIGNED NULL,
  `dibuat` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_pg_pegawai` (`pegawai_id`, `periode_id`),
  CONSTRAINT `fk_pg_periode` FOREIGN KEY (`periode_id`) REFERENCES `periode` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pg_pegawai` FOREIGN KEY (`pegawai_id`) REFERENCES `pengguna` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_pg_pembuat` FOREIGN KEY (`dibuat_oleh`) REFERENCES `pengguna` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `log_aktivitas` (
  `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  `pengguna_id` INT UNSIGNED NULL,
  `aksi` VARCHAR(40) NOT NULL,
  `objek` VARCHAR(40) NOT NULL DEFAULT '',
  `objek_id` INT UNSIGNED NULL,
  `keterangan` VARCHAR(500) NOT NULL DEFAULT '',
  `ip` VARCHAR(45) NOT NULL DEFAULT '',
  `waktu` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `ix_log_aksi` (`aksi`, `waktu`),
  KEY `ix_log_ip` (`ip`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

INSERT IGNORE INTO `unit` (`id`, `induk_id`, `kode`, `nama`, `urut`) VALUES
(1, NULL, 'BAUK', 'Biro Administrasi Umum dan Keuangan', 1),
(2, 1, 'BAUK-KPG', 'Bidang Kepegawaian & BKD', 2),
(3, 1, 'BAUK-KEU', 'Bidang Keuangan', 3),
(4, 1, 'BAUK-REN', 'Bidang Perencanaan dan Pengembangan', 4),
(5, 1, 'BAUK-SAR', 'Bidang Sarana dan Prasarana', 5),
(6, 1, 'BAUK-TU', 'Tata Usaha Universitas', 6);

INSERT IGNORE INTO `peran` (`id`, `kode`, `nama`, `keterangan`, `lingkup`, `sistem`, `urut`) VALUES
(1, 'admin', 'Administrator', 'Mengelola seluruh sistem, pengguna, peran, unit, dan periode', 'semua', 1, 1),
(2, 'kabiro', 'Kepala Biro', 'Memantau, menilai, dan menetapkan pengembangan seluruh pegawai BAUK', 'semua', 0, 2),
(3, 'kabid', 'Kepala Bidang', 'Memantau dan menilai staf di bidangnya', 'unit', 0, 3),
(4, 'staf', 'Staf', 'Mencatat kegiatan dan menyusun sasaran kerja pribadi', 'diri', 0, 4);

INSERT IGNORE INTO `peran_izin` (`peran_id`, `izin`) VALUES
(1, 'sasaran_isi'), (1, 'kegiatan_isi'), (1, 'pantau'), (1, 'evaluasi_nilai'), (1, 'pengembangan_kelola'),
(1, 'laporan_lihat'), (1, 'pengguna_kelola'), (1, 'peran_kelola'), (1, 'unit_kelola'), (1, 'periode_kelola'),
(2, 'sasaran_isi'), (2, 'kegiatan_isi'), (2, 'pantau'), (2, 'evaluasi_nilai'), (2, 'pengembangan_kelola'),
(2, 'laporan_lihat'), (2, 'periode_kelola'),
(3, 'sasaran_isi'), (3, 'kegiatan_isi'), (3, 'pantau'), (3, 'evaluasi_nilai'), (3, 'pengembangan_kelola'),
(3, 'laporan_lihat'),
(4, 'sasaran_isi'), (4, 'kegiatan_isi');

INSERT IGNORE INTO `pengguna` (`id`, `username`, `password_hash`, `nama`, `jabatan`, `unit_id`, `peran_id`, `atasan_id`) VALUES
(1, 'admin', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Administrator', 'Pengelola Sistem', NULL, 1, NULL),
(2, 'kabiro', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Ka. BAUK', 'Kepala Biro Administrasi Umum dan Keuangan', 1, 2, NULL),
(3, 'kabid.kepegawaian', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Kabid Kepegawaian & Operator BKD', 'Kepala Bidang Kepegawaian & Operator BKD', 2, 3, 2),
(4, 'kabid.keuangan', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Kabid Keuangan', 'Kepala Bidang Keuangan', 3, 3, 2),
(5, 'kabid.perencanaan', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Kabid Perencanaan dan Pengembangan', 'Kepala Bidang Perencanaan dan Pengembangan', 4, 3, 2),
(6, 'kabid.sarpras', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Kabid Sarana dan Prasarana', 'Kepala Bidang Sarana dan Prasarana', 5, 3, 2),
(7, 'katu', '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.', 'Kepala Tata Usaha Universitas', 'Kepala Tata Usaha Universitas', 6, 3, 2);

INSERT IGNORE INTO `periode` (`id`, `nama`, `mulai`, `selesai`, `tahap`, `aktif`) VALUES
(1, 'Tahun Kinerja 2026/2027', '2026-08-01', '2027-07-31', 'pelaksanaan', 1);
