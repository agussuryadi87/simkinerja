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
            $detail = aset_hapus_detail_dari_post($alasan);
            if ($alasan === 'dijual' && ($detail['harga_jual'] ?? null) === null) {
                flash('Isi harga jual barang.', 'galat');
                kembali_ke('aset_penghapusan');
            }
            $umur = aset_umur_kategori()[$b['kategori']] ?? null;
            $nilaiBuku = aset_nilai_buku($b['harga'], $b['jumlah'], $b['tahun'], $umur);
            $pid = insert('aset_penghapusan', ['nomor' => aset_nomor_dokumen('aset_penghapusan', 'PH', 'tanggal', $tgl), 'barang_id' => $b['id'], 'tanggal' => $tgl,
                'alasan' => $alasan, 'nilai_buku' => $nilaiBuku, 'keterangan' => mb_substr(post_teks('keterangan'), 0, 3000), 'dibuat_oleh' => uid()]);
            aset_hapus_detail_simpan((int) $pid, $detail);
            update('aset_barang', ['status' => 'dihapuskan', 'kondisi' => $alasan === 'hilang' ? 'hilang' : $b['kondisi'], 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$b['id']]);
            catat_log('hapus_aset', 'aset_penghapusan', $pid, $b['nama']);
            flash('<b>' . e($b['nama']) . '</b> dihapuskan dari inventaris aktif.');
        }
    } elseif ($op === 'ubah') {
        $hapus = row('SELECT * FROM aset_penghapusan WHERE id = ?', [(int) post('id')]);
        $tgl = post('tanggal');
        $alasan = array_key_exists(post('alasan'), ASET_ALASAN_HAPUS) ? post('alasan') : 'lainnya';
        if (!$hapus) {
            tolak_akses();
        }
        $detail = aset_hapus_detail_dari_post($alasan);
        if (!tanggal_sah($tgl)) {
            flash('Tanggal tidak sah.', 'galat');
        } elseif ($alasan === 'dijual' && ($detail['harga_jual'] ?? null) === null) {
            flash('Isi harga jual barang.', 'galat');
        } else {
            aset_hapus_detail_simpan((int) $hapus['id'], $detail);
            update('aset_penghapusan', ['tanggal' => $tgl, 'alasan' => $alasan, 'keterangan' => mb_substr(post_teks('keterangan'), 0, 3000)], 'id = ?', [$hapus['id']]);
            if ($alasan === 'hilang') {
                update('aset_barang', ['kondisi' => 'hilang', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$hapus['barang_id']]);
            }
            catat_log('ubah_hapus_aset', 'aset_penghapusan', (int) $hapus['id'], $hapus['nomor']);
            flash('Data penghapusan <b>' . e($hapus['nomor']) . '</b> diperbarui.');
        }
    } elseif ($op === 'batal') {
        $hapus = row('SELECT * FROM aset_penghapusan WHERE id = ?', [(int) post('id')]);
        if (!$hapus) {
            tolak_akses();
        }
        update('aset_barang', ['status' => 'aktif', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$hapus['barang_id']]);
        q('DELETE FROM aset_penghapusan WHERE id = ?', [$hapus['id']]);
        aset_hapus_detail_buang((int) $hapus['id']);
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
$detailHapus = aset_hapus_detail_semua();
$totalJual = 0.0;
foreach ($daftar as $r) {
    if ($r['alasan'] === 'dijual') {
        $totalJual += (float) ($detailHapus[(string) $r['id']]['harga_jual'] ?? 0);
    }
}
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

<div class="no-cetak penghapusan-tab">
  <a class="btn btn-kecil <?= $fAlasan === '' ? 'btn-utama' : 'btn-garis' ?>" href="<?= e(url('aset_penghapusan', array_filter(['cari' => $cari ?: null]))) ?>">Semua <span class="lemah"><?= array_sum($perAlasan) ?></span></a>
  <?php foreach (ASET_ALASAN_HAPUS as $kode => $nama): ?>
    <a class="btn btn-kecil <?= $fAlasan === $kode ? 'btn-utama' : 'btn-garis' ?>" href="<?= e(url('aset_penghapusan', array_filter(['alasan' => $kode, 'cari' => $cari ?: null]))) ?>"><?= e($nama) ?> <span class="lemah"><?= (int) ($perAlasan[$kode] ?? 0) ?></span></a>
  <?php endforeach; ?>
</div>
<div class="kartu">
  <div class="kartu-kepala">
    <div><h2>Penghapusan aset</h2><p class="ket"><?= count($daftar) ?> catatan · nilai buku <?= e(aset_rupiah($totalNilai)) ?><?= $totalJual > 0 && ($fAlasan === '' || $fAlasan === 'dijual') ? ' · hasil penjualan ' . e(aset_rupiah($totalJual)) : '' ?></p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiHapus" data-reset data-judul="Hapuskan barang"
        data-isi='<?= e(json_encode(['tanggal' => $sekarang, 'alasan' => 'rusak_berat', 'barang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Hapuskan barang</button></div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong('Belum ada penghapusan') ?>
  <?php else: ?>
  <?php
  $khusus = $fAlasan !== '' ? ASET_HAPUS_MEDAN[$fAlasan] : null;
  $sumNilai = 0.0;
  $sumJual = 0.0;
  $sumNilaiJual = 0.0;
  ?>
  <div class="tabel-bungkus"><table class="tabel tabel-aset">
    <thead><tr><th>Nomor / tanggal</th><th>Barang</th><th class="angka">Jumlah</th>
      <?php if ($khusus === null): ?><th>Alasan</th><th class="angka">Nilai buku</th><th class="angka">Harga jual</th><th>Rincian</th>
      <?php else: ?><th class="angka">Nilai buku</th>
        <?php foreach ($khusus as $kunci => $label): ?><th<?= $kunci === 'harga_jual' ? ' class="angka"' : '' ?>><?= e($kunci === 'harga_jual' ? 'Harga jual' : $label) ?></th><?php if ($kunci === 'harga_jual'): ?><th class="angka">Selisih dengan nilai buku</th><?php endif; ?><?php endforeach; ?>
      <?php endif; ?>
      <th>Keterangan</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $hapus): $dt = $detailHapus[(string) $hapus['id']] ?? [];
        $hj = $hapus['alasan'] === 'dijual' && isset($dt['harga_jual']) && $dt['harga_jual'] !== null ? (float) $dt['harga_jual'] : null;
        $sumNilai += (float) $hapus['nilai_buku'];
        $sumJual += $hj ?? 0.0;
        $sumNilaiJual += $hj !== null ? (float) $hapus['nilai_buku'] : 0.0;
        $selisih = $hj !== null ? $hj - (float) $hapus['nilai_buku'] : null; ?>
      <tr>
        <td class="nowrap"><code><?= e($hapus['nomor']) ?></code><div class="kecil lemah"><?= e(tanggal_pendek($hapus['tanggal'])) ?></div></td>
        <td><a href="<?= e(url('aset_barang', ['aksi' => 'lihat', 'id' => $hapus['barang_id']])) ?>"><b><?= e($hapus['barang_nama']) ?></b></a><div class="kecil lemah"><code><?= e($hapus['barang_kode']) ?></code><?= $hapus['ruang_nama'] ? ' · ' . e($hapus['ruang_nama']) : '' ?></div></td>
        <td class="angka"><?= angka($hapus['jumlah']) ?> <?= e($hapus['satuan']) ?></td>
        <?php if ($khusus === null): ?>
          <td><?= e(ASET_ALASAN_HAPUS[$hapus['alasan']] ?? $hapus['alasan']) ?></td>
          <td class="angka uang"><?= e(aset_rupiah($hapus['nilai_buku'])) ?></td>
          <td class="angka uang"><?= $hj !== null ? e(aset_rupiah($hj)) : '-' ?></td>
          <td><?= aset_hapus_ringkas($hapus['alasan'], $dt) ?></td>
        <?php else: ?>
          <td class="angka uang"><?= e(aset_rupiah($hapus['nilai_buku'])) ?></td>
          <?php foreach ($khusus as $kunci => $label): ?>
            <?php if ($kunci === 'harga_jual'): ?>
              <td class="angka uang"><?= $hj !== null ? e(aset_rupiah($hj)) : '-' ?></td>
              <td class="angka uang <?= $selisih !== null && $selisih < 0 ? 'teks-merah' : '' ?>"><?= $selisih !== null ? ($selisih >= 0 ? '+' : '-') . e(aset_rupiah(abs($selisih))) : '-' ?></td>
            <?php else: ?>
              <td class="kecil"><?= trim((string) ($dt[$kunci] ?? '')) !== '' ? e((string) $dt[$kunci]) : '-' ?></td>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php endif; ?>
        <td class="kecil"><?= e($hapus['keterangan'] ?: '-') ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $hapus['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiUbahHapus" data-judul="Ubah data penghapusan" data-isi='<?= e(json_encode(['id' => (int) $hapus['id'], 'tanggal' => $hapus['tanggal'], 'alasan' => $hapus['alasan'], 'keterangan' => $hapus['keterangan']] + aset_hapus_detail_isi($dt))) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" data-konfirmasi="Batalkan penghapusan dan kembalikan barang ke inventaris aktif?"><?= csrf_field() ?><input type="hidden" name="op" value="batal"><input type="hidden" name="id" value="<?= (int) $hapus['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus (batalkan penghapusan)"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <?php if ($khusus !== null): ?>
    <tfoot><tr><td colspan="3" style="text-align:right"><b>Total</b></td><td class="angka uang"><b><?= e(aset_rupiah($sumNilai)) ?></b></td>
      <?php foreach ($khusus as $kunci => $label): ?>
        <?php if ($kunci === 'harga_jual'): ?><td class="angka uang"><b><?= e(aset_rupiah($sumJual)) ?></b></td><td class="angka uang <?= $sumJual - $sumNilaiJual < 0 ? 'teks-merah' : '' ?>"><b><?= ($sumJual - $sumNilaiJual >= 0 ? '+' : '-') . e(aset_rupiah(abs($sumJual - $sumNilaiJual))) ?></b></td>
        <?php else: ?><td></td><?php endif; ?>
      <?php endforeach; ?>
      <td></td><td></td><td class="no-cetak"></td></tr></tfoot>
    <?php endif; ?>
    </table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiUbahHapus" hidden><div class="modal sempit"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="ubah"><input type="hidden" name="id" value="">
  <div class="modal-kepala"><h2>Ubah data penghapusan</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-grid">
      <div class="form-baris"><label class="fl" for="uhTgl">Tanggal</label><input type="date" id="uhTgl" name="tanggal" required></div>
      <div class="form-baris"><label class="fl" for="uhAlasan">Alasan</label><select id="uhAlasan" name="alasan" data-alasan-hapus><?= pilihan(ASET_ALASAN_HAPUS, 'rusak_berat') ?></select></div>
    </div>
    <div class="hapus-detail" data-labels='<?= e(json_encode(ASET_HAPUS_MEDAN)) ?>'>
      <div class="form-baris" data-medan="harga_jual" hidden><label class="fl" for="uhHarga">Harga jual</label><input type="text" id="uhHarga" name="harga_jual" data-uang inputmode="numeric" placeholder="0"></div>
      <div class="form-baris" data-medan="pihak" hidden><label class="fl" for="uhPihak">Pihak</label><input type="text" id="uhPihak" name="pihak" maxlength="150"></div>
      <div class="form-baris" data-medan="dokumen" hidden><label class="fl" for="uhDok">Dokumen</label><input type="text" id="uhDok" name="dokumen" maxlength="150"></div>
      <div class="form-baris" data-medan="rincian" hidden><label class="fl" for="uhRinci">Rincian</label><textarea id="uhRinci" name="rincian" rows="2" maxlength="1000"></textarea></div>
    </div>
    <div class="form-baris"><label class="fl" for="uhKet">Keterangan</label><textarea id="uhKet" name="keterangan" rows="3" maxlength="3000"></textarea></div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
</form></div></div>

<div class="tirai" id="tiraiHapus" hidden><div class="modal sempit"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="hapuskan">
  <div class="modal-kepala"><h2>Hapuskan barang</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-baris"><label class="fl" for="hBrg">Barang <span class="wajib">*</span></label><select id="hBrg" name="barang_id" required><?= opsi_barang(0, '— Pilih barang —') ?></select></div>
    <div class="form-grid">
      <div class="form-baris"><label class="fl" for="hTgl">Tanggal</label><input type="date" id="hTgl" name="tanggal" required></div>
      <div class="form-baris"><label class="fl" for="hAlasan">Alasan</label><select id="hAlasan" name="alasan" data-alasan-hapus><?= pilihan(ASET_ALASAN_HAPUS, 'rusak_berat') ?></select></div>
    </div>
    <div class="hapus-detail" data-labels='<?= e(json_encode(ASET_HAPUS_MEDAN)) ?>'>
      <div class="form-baris" data-medan="harga_jual" hidden><label class="fl" for="hHarga">Harga jual</label><input type="text" id="hHarga" name="harga_jual" data-uang inputmode="numeric" placeholder="0"></div>
      <div class="form-baris" data-medan="pihak" hidden><label class="fl" for="hPihak">Pihak</label><input type="text" id="hPihak" name="pihak" maxlength="150"></div>
      <div class="form-baris" data-medan="dokumen" hidden><label class="fl" for="hDok">Dokumen</label><input type="text" id="hDok" name="dokumen" maxlength="150"></div>
      <div class="form-baris" data-medan="rincian" hidden><label class="fl" for="hRinci">Rincian</label><textarea id="hRinci" name="rincian" rows="2" maxlength="1000"></textarea></div>
    </div>
    <div class="form-baris"><label class="fl" for="hKet">Keterangan</label><textarea id="hKet" name="keterangan" rows="3" maxlength="3000"></textarea></div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
</form></div></div>
