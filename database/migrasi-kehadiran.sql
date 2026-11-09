-- Keterangan kehadiran pada catatan kegiatan harian.
ALTER TABLE kegiatan
  ADD COLUMN kehadiran ENUM('masuk','sakit','izin','cuti','dinas') NOT NULL DEFAULT 'masuk' AFTER jenis;
