USE `simkinerja_bauk`;

ALTER TABLE `sasaran`
  ADD COLUMN `target_tw1` DECIMAL(12,2) NULL AFTER `target`,
  ADD COLUMN `target_tw2` DECIMAL(12,2) NULL AFTER `target_tw1`,
  ADD COLUMN `target_tw3` DECIMAL(12,2) NULL AFTER `target_tw2`,
  ADD COLUMN `target_tw4` DECIMAL(12,2) NULL AFTER `target_tw3`;

CREATE TABLE IF NOT EXISTS `realisasi_tw` (
  `sasaran_id` INT UNSIGNED NOT NULL,
  `tw` TINYINT NOT NULL,
  `realisasi` DECIMAL(12,2) NOT NULL,
  PRIMARY KEY (`sasaran_id`, `tw`),
  CONSTRAINT `fk_rtw_sasaran` FOREIGN KEY (`sasaran_id`) REFERENCES `sasaran` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `evaluasi`
  ADD COLUMN `tw` TINYINT NOT NULL DEFAULT 1 AFTER `pegawai_id`,
  ADD UNIQUE KEY `uk_evaluasi_tw` (`periode_id`, `pegawai_id`, `tw`);

ALTER TABLE `evaluasi` DROP INDEX `uk_evaluasi`;

UPDATE `sasaran` SET `realisasi` = NULL;

UPDATE `periode`
   SET `nama` = 'Tahun Kinerja 2026/2027', `mulai` = '2026-08-01', `selesai` = '2027-07-31'
 WHERE `id` = 1 AND `mulai` = '2026-01-01';
