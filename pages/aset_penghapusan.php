<?php
$sekarang = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');

    if ($op === 'hapuskan') {
        $b = row("SELECT * FROM aset_barang WHERE id = ? AND status = 'aktif'", [(int) post('barang_id')]);
        $tgl = post('tanggal');
        $alasan = array_key_exists(post('alasan'), ASET_ALASAN_HAPUS) ? post('alasan') : 'lainnya';
        $galat = [];
        if (!$b) {
            $galat[] = 'Pilih barang yang akan dihapuskan.';
        } elseif ((int) val("SELECT COUNT(*) FROM aset_mutasi WHERE jenis = 'pinjam' AND status = 'dipinjam' AND barang_id = ?", [$b['id']], 0) > 0) {
            $galat[] = 'Barang ini masih tercatat dipinjam. Catat pengembaliannya dahulu di menu Mutasi & Peminjaman.';
        }
        if (!tanggal_sah($tgl)) {
            $galat[] = 'Tanggal tidak sah.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } else {
            $umur = aset_umur_kategori()[$b['kategori']] ?? null;
            $nilaiBuku = aset_nilai_buku($b['harga'], $b['jumlah'], $b['tahun'], $umur);
            $pid = insert('aset_penghapusan', ['nomor' => aset_nomor_dokumen('aset_penghapusan', 'PH', 'tanggal', $tgl), 'barang_id' => $b['id'], 'tanggal' => $tgl,
                'alasan' => $alasan, 'nilai_buku' => $nilaiBuku, 'keterangan' => mb_substr(post_teks('keterangan'), 0, 3000), 'dibuat_oleh' => uid()]);
            update('aset_barang', ['status' => 'dihapuskan', 'kondisi' => $alasan === 'hilang' ? 'hilang' : $b['kondisi'], 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$b['id']]);
            catat_log('hapus_aset', 'aset_penghapusan', $pid, $b['nama']);
            flash('<b>' . e($b['nama']) . '</b> dihapuskan dari inventaris aktif.');
        }
    } elseif ($op === 'batal') {
        $hapus = row('SELECT * FROM aset_penghapusan WHERE id = ?', [(int) post('id')]);
        if (!$hapus) {
            tolak_akses();
        }
        update('aset_barang', ['status' => 'aktif', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$hapus['barang_id']]);
        q('DELETE FROM aset_penghapusan WHERE id = ?', [$hapus['id']]);
        catat_log('batal_hapus_aset', 'aset_penghapusan', (int) $hapus['id'], $hapus['nomor']);
        flash('Penghapusan dibatalkan, barang kembali ke inventaris aktif.', 'info');
    } else {
        tolak_akses();
    }
    kembali_ke('aset_penghapusan');
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fAlasan = array_key_exists($_GET['alasan'] ?? '', ASET_ALASAN_HAPUS) ? $_GET['alasan'] : '';
$syarat = ['1=1'];
$par = [];
if ($cari !== '') {
    $syarat[] = '(b.nama LIKE ? OR b.kode LIKE ? OR h.nomor LIKE ?)';
    $par = array_merge($par, array_fill(0, 3, '%' . $cari . '%'));
}
if ($fAlasan !== '') {
    $syarat[] = 'h.alasan = ?';
    $par[] = $fAlasan;
}
$daftar = rows(
    'SELECT h.*, b.nama AS barang_nama, b.kode AS barang_kode, b.jumlah, b.satuan, b.harga, r.nama AS ruang_nama
       FROM aset_penghapusan h JOIN aset_barang b ON b.id = h.barang_id LEFT JOIN aset_ruang r ON r.id = b.ruang_id
      WHERE ' . implode(' AND ', $syarat) . ' ORDER BY h.tanggal DESC, h.id DESC LIMIT 300',
    $par
);
$penginput = aset_penginput('aset_penghapusan', $daftar);
$totalNilai = array_sum(array_map(static function ($r) { return (float) $r['nilai_buku']; }, $daftar));
$perAlasan = [];
foreach (rows('SELECT alasan, COUNT(*) AS n FROM aset_penghapusan GROUP BY alasan') as $r) {
    $perAlasan[$r['alasan']] = (int) $r['n'];
}
?>
<form method="get" class="saring">
  <input type="hidden" name="h" value="aset_penghapusan">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nomor, nama barang, atau kode"></div>
  <div class="form-baris"><label class="fl">Alasan</label><select name="alasan" data-auto-kirim><option value="">Semua alasan</option><?= pilihan(ASET_ALASAN_HAPUS, $fAlasan) ?></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2>Penghapusan aset</h2><p class="ket"><?= count($daftar) ?> catatan · nilai buku <?= e(aset_rupiah($totalNilai)) ?></p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiHapus" data-reset data-judul="Hapuskan barang"
        data-isi='<?= e(json_encode(['tanggal' => $sekarang, 'alasan' => 'rusak_berat', 'barang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Hapuskan barang</button></div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong('Belum ada penghapusan') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-aset">
    <thead><tr><th>Nomor / tanggal</th><th>Barang</th><th class="angka">Jumlah</th><th>Alasan</th><th class="angka">Nilai buku</th><th>Keterangan</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $hapus): ?>
      <tr>
        <td class="nowrap"><code><?= e($hapus['nomor']) ?></code><div class="kecil lemah"><?= e(tanggal_pendek($hapus['tanggal'])) ?></div></td>
        <td><a href="<?= e(url('aset_barang', ['aksi' => 'lihat', 'id' => $hapus['barang_id']])) ?>"><b><?= e($hapus['barang_nama']) ?></b></a><div class="kecil lemah"><code><?= e($hapus['barang_kode']) ?></code><?= $hapus['ruang_nama'] ? ' · ' . e($hapus['ruang_nama']) : '' ?></div></td>
        <td class="angka"><?= angka($hapus['jumlah']) ?> <?= e($hapus['satuan']) ?></td>
        <td><?= e(ASET_ALASAN_HAPUS[$hapus['alasan']] ?? $hapus['alasan']) ?></td>
        <td class="angka uang"><?= e(aset_rupiah($hapus['nilai_buku'])) ?></td>
        <td class="kecil"><?= e($hapus['keterangan'] ?: '-') ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $hapus['id']] ?? null) ?></td>
        <td class="no-cetak"><form method="post" data-konfirmasi="Batalkan penghapusan dan kembalikan barang ke inventaris aktif?"><?= csrf_field() ?><input type="hidden" name="op" value="batal"><input type="hidden" name="id" value="<?= (int) $hapus['id'] ?>">
          <button class="btn btn-garis btn-kecil" type="submit">Batalkan</button></form></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiHapus" hidden><div class="modal sempit"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="hapuskan">
  <div class="modal-kepala"><h2>Hapuskan barang</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-baris"><label class="fl" for="hBrg">Barang <span class="wajib">*</span></label><select id="hBrg" name="barang_id" required><?= opsi_barang(0, '— Pilih barang —') ?></select></div>
    <div class="form-grid">
      <div class="form-baris"><label class="fl" for="hTgl">Tanggal</label><input type="date" id="hTgl" name="tanggal" required></div>
      <div class="form-baris"><label class="fl" for="hAlasan">Alasan</label><select id="hAlasan" name="alasan"><?= pilihan(ASET_ALASAN_HAPUS, 'rusak_berat') ?></select></div>
    </div>
    <div class="form-baris"><label class="fl" for="hKet">Keterangan</label><textarea id="hKet" name="keterangan" rows="3" maxlength="3000"></textarea></div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
</form></div></div>
