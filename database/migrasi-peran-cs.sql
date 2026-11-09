-- Peran CS, akses Kartu Kontrol CS, dan 17 akun petugas CS.
-- Aman dijalankan lebih dari sekali: data yang sudah ada tidak digandakan.

INSERT INTO peran (kode, nama, keterangan, lingkup, sistem, urut)
SELECT 'cs', 'CS', 'Petugas kebersihan (cleaning service): mencatat kegiatan harian dan mengisi Kartu Kontrol CS', 'diri', 0, 5
 WHERE NOT EXISTS (SELECT 1 FROM peran WHERE kode = 'cs');

SET @cs := (SELECT id FROM peran WHERE kode = 'cs' LIMIT 1);
SET @staf := (SELECT id FROM peran WHERE kode = 'staf' LIMIT 1);
SET @unit := (SELECT id FROM unit WHERE nama = 'Bidang Sarana dan Prasarana' LIMIT 1);
SET @kabid := (SELECT p.id FROM pengguna p JOIN peran pr ON pr.id = p.peran_id WHERE p.unit_id = @unit AND pr.kode = 'kabid' AND p.aktif = 1 ORDER BY p.id LIMIT 1);

INSERT INTO peran_izin (peran_id, izin)
SELECT @cs, z.izin FROM (SELECT izin FROM peran_izin WHERE peran_id = @staf
  UNION SELECT 'kegiatan_isi' UNION SELECT 'menu_diatur' UNION SELECT 'menu_umpan' UNION SELECT 'menu_evaluasi' UNION SELECT 'menu_laporan' UNION SELECT 'aset_kontrol') z
 WHERE NOT EXISTS (SELECT 1 FROM peran_izin x WHERE x.peran_id = @cs AND x.izin = z.izin);

INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.azmi', '$2y$10$RbqxKgTt820VENt4uj15TO9sO.QACxOub0Lyx/e4TrVCdO7A4Mfl2', 'Azmi', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.azmi');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.msaid', '$2y$10$ldmsInkxzAPI.An7n2j6suTFXTJlyHw.HzJwZr7lRi.59JmkL/Hx6', 'M. Said', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.msaid');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.samsulrizal', '$2y$10$k8myG7wj7b.NEQopo5oEIe9/Q1JWsTrX2ZXp7WRDH3f7VkCsJpb7e', 'Samsul Rizal', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.samsulrizal');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.mridha', '$2y$10$A.Rqn81gC.Qf9Hzdjxt1h.cmyX8l19M7N3IL7BCBZPAt.a9H13Icu', 'M. Ridha', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.mridha');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.andihairil', '$2y$10$vjBUn2zuPOkFmhIq/CMvTebuQAPjaf1.Y3DcPghr1I6C7H82rRs3.', 'Andi Hairil', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.andihairil');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.ahmadnur', '$2y$10$YzgAmc3uZ29wcgX8s1m4feCnYiqNxRAGeDhu6PVfMLsQF4xixISvO', 'Ahmad Nur', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.ahmadnur');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.edisusanto', '$2y$10$ScEHQ5T1JJscHiub3HuN/e0fBQyYwdWbaOdZNeI76J48ofjmrcPgW', 'Edi Susanto', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.edisusanto');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.juliansyah', '$2y$10$tuWQzcPUgrgS3NOM5eoMFePnXv8RuDcWaU5WUGe/rqgxpbnNZq9KS', 'Juliansyah, S.M.', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.juliansyah');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.sabri', '$2y$10$9kmDih/1fGvwX4ElUeH3NeK7zooD8jhAlcYDgECLqgxx0o5jTBRFa', 'Sabri', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.sabri');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.muri', '$2y$10$H1DS2lxuAyCV18nQROVJwezSTEOo7lqh4X.AskDXOOblLTLz5XoQu', 'Muri', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.muri');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.randi', '$2y$10$Xln4HGeW2Dxzz4YrIcGCp.6OI2J.GBOn9YU7EwvVf00LJfo21iEla', 'Randi', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.randi');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.agustiawan', '$2y$10$87WN9MkI7tgUekw8VMMrCewKbhLxaaQw/iQF2QmUoOQsEFp8qSyEu', 'Agus Tiawan', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.agustiawan');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.misbatul', '$2y$10$P6ck.MNRYunhP2E9zTkneOL4jX03gMSO32B.cMKuPwka1iwRhYGLS', 'Misbatul', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.misbatul');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.sriwahyuni', '$2y$10$T9J2IwhYH2ijNNoqpXWUQumGAzW1cgN4arbJikgQLPsUVMMEtiicS', 'Sri Wahyuni', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.sriwahyuni');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.salasiah', '$2y$10$g1EwM13PtycomIlmiecususxv1Q/fXxd6Ona.y0vRv8BPkjqBCKIC', 'Salasiah', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.salasiah');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.jimmi', '$2y$10$./qW2SBhOn1dqo54VfKMXeJAOcNjEmQhnawpk7YC/WbSpgqj5eAKy', 'Jimmi', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.jimmi');
INSERT INTO pengguna (username, password_hash, nama, nip, jabatan, email, unit_id, peran_id, atasan_id, aktif, sandi_bawaan)
SELECT 'cs.rudiansyah', '$2y$10$IcFSipIFZbEKfQAXQCukT.Uo/aLJh02/Go98t8UZhWNlI.DX3vnKK', 'Rudiansyah', '', 'Cleaning Service', '', @unit, @cs, @kabid, 1, 1
 WHERE NOT EXISTS (SELECT 1 FROM pengguna WHERE username = 'cs.rudiansyah');

UPDATE pengguna SET peran_id = @cs WHERE username LIKE 'cs.%' AND jabatan = 'Cleaning Service';

SELECT CONCAT('Peran CS id ', @cs, ' | akses: ', (SELECT GROUP_CONCAT(izin ORDER BY izin) FROM peran_izin WHERE peran_id = @cs), ' | akun CS: ', (SELECT COUNT(*) FROM pengguna WHERE peran_id = @cs)) AS hasil;
