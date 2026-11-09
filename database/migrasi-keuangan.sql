-- Informasi Keuangan: RAPB dan Audit Independen.
ALTER TABLE pengguna ADD COLUMN akses_keu TINYINT(1) NOT NULL DEFAULT 0;

CREATE TABLE keu_rapb (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tahun SMALLINT NOT NULL,
  unit_id INT UNSIGNED NULL,
  nama VARCHAR(200) NOT NULL,
  status ENUM('draf','diajukan','disetujui','ditolak') NOT NULL DEFAULT 'draf',
  catatan VARCHAR(1000) NOT NULL DEFAULT '',
  dibuat_oleh INT UNSIGNED NULL,
  dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_rapb_tahun (tahun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE keu_rapb_item (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  rapb_id INT UNSIGNED NOT NULL,
  jenis ENUM('pendapatan','belanja') NOT NULL DEFAULT 'belanja',
  kode VARCHAR(30) NOT NULL DEFAULT '',
  uraian VARCHAR(255) NOT NULL,
  volume DECIMAL(12,2) NOT NULL DEFAULT 1,
  satuan VARCHAR(30) NOT NULL DEFAULT '',
  harga DECIMAL(16,2) NOT NULL DEFAULT 0,
  realisasi DECIMAL(16,2) NOT NULL DEFAULT 0,
  PRIMARY KEY (id),
  KEY ix_item_rapb (rapb_id),
  CONSTRAINT fk_item_rapb FOREIGN KEY (rapb_id) REFERENCES keu_rapb (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE keu_audit (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  tahun SMALLINT NOT NULL,
  judul VARCHAR(200) NOT NULL,
  auditor VARCHAR(150) NOT NULL DEFAULT '',
  nomor_laporan VARCHAR(80) NOT NULL DEFAULT '',
  tanggal_laporan DATE NULL,
  opini ENUM('','wtp','wdp','tmp','tw') NOT NULL DEFAULT '',
  status ENUM('berjalan','selesai') NOT NULL DEFAULT 'berjalan',
  dibuat DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY ix_audit_tahun (tahun)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE keu_temuan (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  audit_id INT UNSIGNED NOT NULL,
  kode VARCHAR(20) NOT NULL DEFAULT '',
  uraian TEXT NOT NULL,
  risiko ENUM('rendah','sedang','tinggi') NOT NULL DEFAULT 'sedang',
  rekomendasi TEXT NULL,
  unit_id INT UNSIGNED NULL,
  tenggat DATE NULL,
  status ENUM('baru','berjalan','selesai') NOT NULL DEFAULT 'baru',
  tindak_lanjut TEXT NULL,
  selesai_pada DATE NULL,
  PRIMARY KEY (id),
  KEY ix_temuan_audit (audit_id),
  CONSTRAINT fk_temuan_audit FOREIGN KEY (audit_id) REFERENCES keu_audit (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

UPDATE pengguna SET akses_keu = 1 WHERE id IN (SELECT id FROM (SELECT p.id FROM pengguna p JOIN peran r ON r.id = p.peran_id WHERE r.kode IN ('admin','kabiro')) x) OR jabatan LIKE 'Kepala Bidang Keuangan%';
