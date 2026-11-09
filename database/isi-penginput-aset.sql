SET @admin := (SELECT p.id FROM pengguna p JOIN peran pr ON pr.id = p.peran_id WHERE p.username = 'admin' LIMIT 1);
UPDATE aset_kerusakan SET dibuat_oleh = @admin WHERE dibuat_oleh IS NULL AND @admin IS NOT NULL;
UPDATE aset_perawatan SET dibuat_oleh = @admin WHERE dibuat_oleh IS NULL AND @admin IS NOT NULL;
UPDATE aset_pengajuan SET dibuat_oleh = @admin WHERE dibuat_oleh IS NULL AND @admin IS NOT NULL;
UPDATE aset_opname SET dibuat_oleh = @admin WHERE dibuat_oleh IS NULL AND @admin IS NOT NULL;
UPDATE aset_penghapusan SET dibuat_oleh = @admin WHERE dibuat_oleh IS NULL AND @admin IS NOT NULL;
UPDATE aset_mutasi SET dibuat_oleh = @admin WHERE dibuat_oleh IS NULL AND @admin IS NOT NULL;
INSERT INTO log_aktivitas (pengguna_id, aksi, objek, objek_id, keterangan, ip, waktu)
SELECT @admin, 'tambah_aset_ruang', 'aset_ruang', r.id, LEFT(r.nama, 500), '', r.dibuat FROM aset_ruang r
 WHERE @admin IS NOT NULL AND NOT EXISTS (SELECT 1 FROM log_aktivitas l WHERE l.objek = 'aset_ruang' AND l.objek_id = r.id AND l.aksi LIKE 'tambah%');
INSERT INTO log_aktivitas (pengguna_id, aksi, objek, objek_id, keterangan, ip, waktu)
SELECT @admin, 'tambah_aset_barang', 'aset_barang', b.id, LEFT(b.nama, 500), '', b.dibuat FROM aset_barang b
 WHERE @admin IS NOT NULL AND NOT EXISTS (SELECT 1 FROM log_aktivitas l WHERE l.objek = 'aset_barang' AND l.objek_id = b.id AND l.aksi LIKE 'tambah%');
