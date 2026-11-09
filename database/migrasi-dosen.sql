-- Sistem Jabatan Akademik Dosen (JAD): data dosen per tahun dan daftar dosen eligible, diimpor dari Excel.
-- Aman dijalankan berulang (IF NOT EXISTS).

ALTER TABLE `pengguna` ADD COLUMN IF NOT EXISTS `akses_jad` TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE IF NOT EXISTS `dosen_data` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tahun` SMALLINT UNSIGNED NOT NULL,
  `no_urut` INT UNSIGNED NULL,
  `nidn` VARCHAR(30) NOT NULL DEFAULT '',
  `nuptk` VARCHAR(30) NOT NULL DEFAULT '',
  `uisa` VARCHAR(40) NOT NULL DEFAULT '',
  `nama` VARCHAR(200) NOT NULL,
  `pendidikan` VARCHAR(10) NOT NULL DEFAULT '',
  `prodi` VARCHAR(120) NOT NULL DEFAULT '',
  `jafung` VARCHAR(60) NOT NULL DEFAULT '',
  `jafung_asli` VARCHAR(60) NOT NULL DEFAULT '',
  `status_dosen` VARCHAR(80) NOT NULL DEFAULT '',
  `no_sertifikat` VARCHAR(60) NOT NULL DEFAULT '',
  `status_serdos` VARCHAR(40) NOT NULL DEFAULT '',
  `tahun_serdos` SMALLINT UNSIGNED NULL,
  `tersertifikasi` TINYINT(1) NOT NULL DEFAULT 0,
  `angka_kredit` INT UNSIGNED NULL,
  `tahun_pangkat` VARCHAR(10) NOT NULL DEFAULT '',
  `tmt_jafung` DATE NULL,
  `status_keaktifan` VARCHAR(40) NOT NULL DEFAULT '',
  `tipe` VARCHAR(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_dosen_tahun` (`tahun`, `no_urut`),
  KEY `idx_dosen_nidn` (`nidn`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `dosen_eligible` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `kategori` ENUM('gb','lk','lektor') NOT NULL,
  `no_urut` INT UNSIGNED NULL,
  `nama` VARCHAR(200) NOT NULL,
  `nidn` VARCHAR(30) NOT NULL DEFAULT '',
  `nip` VARCHAR(40) NOT NULL DEFAULT '',
  `nuptk` VARCHAR(30) NOT NULL DEFAULT '',
  `status_kepegawaian` VARCHAR(40) NOT NULL DEFAULT '',
  `ikatan_kerja` VARCHAR(60) NOT NULL DEFAULT '',
  `perguruan_tinggi` VARCHAR(120) NOT NULL DEFAULT '',
  `prodi` VARCHAR(120) NOT NULL DEFAULT '',
  `jabfung` VARCHAR(60) NOT NULL DEFAULT '',
  `tmt_jabfung` VARCHAR(40) NOT NULL DEFAULT '',
  `golongan` VARCHAR(20) NOT NULL DEFAULT '',
  `tmt_golongan` VARCHAR(40) NOT NULL DEFAULT '',
  `rumpun_ilmu` VARCHAR(120) NOT NULL DEFAULT '',
  `pohon_ilmu` VARCHAR(200) NOT NULL DEFAULT '',
  `cabang_ilmu` VARCHAR(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `idx_eligible_kat` (`kategori`, `no_urut`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Beri akses kepada Administrator dan Kepala Biro.
UPDATE `pengguna` SET `akses_jad` = 1 WHERE `username` = 'admin' OR (`jabatan` LIKE 'Kepala Biro%' AND `aktif` = 1);
