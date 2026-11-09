<?php
$tampil = ($_GET['tampil'] ?? 'pindah') === 'pinjam' ? 'pinjam' : 'pindah';
$sekarang = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $mid = (int) post('id');

    if ($op === 'pindah') {
        $b = row("SELECT * FROM aset_barang WHERE id = ? AND status = 'aktif'", [(int) post('barang_id')]);
        $ke = (int) post('ke_ruang_id');
        $tgl = post('tanggal');
        $galat = [];
        if (!$b) {
            $galat[] = 'Pilih barang yang dipindahkan.';
        }
        if (!$ke || !row('SELECT id FROM aset_ruang WHERE id = ?', [$ke])) {
            $galat[] = 'Pilih ruangan tujuan.';
        } elseif ($b && (int) $b['ruang_id'] === $ke) {
            $galat[] = 'Ruangan tujuan sama dengan ruangan asal.';
        }
        if (!tanggal_sah($tgl)) {
            $galat[] = 'Tanggal tidak sah.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } else {
            insert('aset_mutasi', ['jenis' => 'pindah', 'barang_id' => $b['id'], 'dari_ruang_id' => $b['ruang_id'], 'ke_ruang_id' => $ke, 'jumlah' => $b['jumlah'],
                'tanggal' => $tgl, 'status' => 'selesai', 'keterangan' => mb_substr(post_teks('keterangan'), 0, 3000), 'dibuat_oleh' => uid()]);
            update('aset_barang', ['ruang_id' => $ke, 'posisi' => '', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$b['id']]);
            catat_log('pindah_aset', 'aset_barang', (int) $b['id'], $b['nama']);
            flash('<b>' . e($b['nama']) . '</b> dipindahkan ke ruangan tujuan.');
        }
        kembali_ke('aset_mutasi', ['tampil' => 'pindah']);
    } elseif ($op === 'pinjam') {
        $b = row("SELECT * FROM aset_barang WHERE id = ? AND status = 'aktif'", [(int) post('barang_id')]);
        $jml = max(1, (int) post('jumlah', '1'));
        $tgl = post('tanggal');
        $kembali = aset_tanggal_sah_atau_null(post('rencana_kembali'));
        $galat = [];
        if (!$b) {
            $galat[] = 'Pilih barang yang dipinjam.';
        } else {
            $dipinjam = (int) val("SELECT COALESCE(SUM(jumlah), 0) FROM aset_mutasi WHERE jenis = 'pinjam' AND status = 'dipinjam' AND barang_id = ?", [$b['id']], 0);
            if ($jml > (int) $b['jumlah'] - $dipinjam) {
                $galat[] = 'Jumlah melebihi yang tersedia (' . ((int) $b['jumlah'] - $dipinjam) . ' ' . $b['satuan'] . ').';
            }
            if ($b['kondisi'] === 'rusak_berat' || $b['kondisi'] === 'hilang') {
                $galat[] = 'Barang berkondisi ' . strtolower(ASET_KONDISI[$b['kondisi']]['label']) . ' tidak dapat dipinjamkan.';
            }
        }
        if (post('peminjam') === '') {
            $galat[] = 'Nama peminjam wajib diisi.';
        }
        if (!tanggal_sah($tgl)) {
            $galat[] = 'Tanggal pinjam tidak sah.';
        } elseif ($kembali && $kembali < $tgl) {
            $galat[] = 'Rencana kembali tidak boleh sebelum tanggal pinjam.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } else {
            insert('aset_mutasi', ['jenis' => 'pinjam', 'barang_id' => $b['id'], 'dari_ruang_id' => $b['ruang_id'], 'jumlah' => $jml, 'peminjam' => mb_substr(post('peminjam'), 0, 150),
                'unit_peminjam' => mb_substr(post('unit_peminjam'), 0, 150), 'tanggal' => $tgl, 'rencana_kembali' => $kembali, 'status' => 'dipinjam',
                'keterangan' => mb_substr(post_teks('keterangan'), 0, 3000), 'dibuat_oleh' => uid()]);
            catat_log('pinjam_aset', 'aset_barang', (int) $b['id'], $b['nama']);
            flash('Peminjaman <b>' . e($b['nama']) . '</b> dicatat.');
        }
        kembali_ke('aset_mutasi', ['tampil' => 'pinjam']);
    } elseif ($op === 'kembali') {
        $m = row("SELECT * FROM aset_mutasi WHERE id = ? AND jenis = 'pinjam' AND status = 'dipinjam'", [$mid]);
        if (!$m) {
            tolak_akses();
        }
        $kondisi = array_key_exists(post('kondisi_kembali'), ASET_KONDISI) ? post('kondisi_kembali') : 'baik';
        $tglKembali = aset_tanggal_sah_atau_null(post('tanggal_kembali')) ?: $sekarang;
        if ($tglKembali < $m['tanggal']) {
            flash('Tanggal kembali tidak boleh sebelum tanggal pinjam (' . tanggal_id($m['tanggal']) . ').', 'galat');
            kembali_ke('aset_mutasi', ['tampil' => 'pinjam']);
        }
        update('aset_mutasi', [
            'status' => 'dikembalikan', 'tanggal_kembali' => $tglKembali,
            'kondisi_kembali' => $kondisi, 'keterangan' => mb_substr(post_teks('keterangan'), 0, 3000),
        ], 'id = ?', [$mid]);
        if ($kondisi !== 'baik') {
            update('aset_barang', ['kondisi' => $kondisi, 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$m['barang_id']]);
        }
        catat_log('kembali_aset', 'aset_mutasi', $mid, $kondisi);
        flash('Pengembalian dicatat' . ($kondisi !== 'baik' ? '. Kondisi barang diperbarui menjadi ' . strtolower(ASET_KONDISI[$kondisi]['label']) . '.' : '.'));
        kembali_ke('aset_mutasi', ['tampil' => 'pinjam']);
    } elseif ($op === 'hapus') {
        $m = row('SELECT * FROM aset_mutasi WHERE id = ?', [$mid]);
        if (!$m || $m['jenis'] === 'pindah') {
            flash('Riwayat pemindahan tidak dapat dihapus.', 'warn');
        } else {
            q('DELETE FROM aset_mutasi WHERE id = ?', [$mid]);
            flash('Catatan peminjaman dihapus.');
        }
        kembali_ke('aset_mutasi', ['tampil' => 'pinjam']);
    }
    tolak_akses();
}

$kpiPinjam = (int) val("SELECT COUNT(*) FROM aset_mutasi WHERE jenis = 'pinjam' AND status = 'dipinjam'", [], 0);
$kpiTelat = (int) val("SELECT COUNT(*) FROM aset_mutasi WHERE jenis = 'pinjam' AND status = 'dipinjam' AND rencana_kembali < ?", [$sekarang], 0);
$kpiPindah = (int) val("SELECT COUNT(*) FROM aset_mutasi WHERE jenis = 'pindah' AND tanggal >= ?", [date('Y-m-01')], 0);
$kpiKembali = (int) val("SELECT COUNT(*) FROM aset_mutasi WHERE jenis = 'pinjam' AND status = 'dikembalikan' AND tanggal_kembali >= ?", [date('Y-m-01')], 0);

$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = in_array($_GET['status'] ?? 'dipinjam', ['dipinjam', 'semua'], true) ? ($_GET['status'] ?? 'dipinjam') : 'dipinjam';
$syarat = ['m.jenis = ?'];
$par = [$tampil];
if ($tampil === 'pinjam' && $fStatus === 'dipinjam') {
    $syarat[] = "m.status = 'dipinjam'";
}
if ($cari !== '') {
    $syarat[] = '(b.nama LIKE ? OR b.kode LIKE ? OR m.peminjam LIKE ? OR m.unit_peminjam LIKE ?)';
    $par = array_merge($par, array_fill(0, 4, '%' . $cari . '%'));
}
$daftar = rows(
    "SELECT m.*, b.nama AS barang_nama, b.kode AS barang_kode, b.satuan, dr.nama AS dari_nama, kr.nama AS ke_nama
       FROM aset_mutasi m JOIN aset_barang b ON b.id = m.barang_id
       LEFT JOIN aset_ruang dr ON dr.id = m.dari_ruang_id LEFT JOIN aset_ruang kr ON kr.id = m.ke_ruang_id
      WHERE " . implode(' AND ', $syarat) . ' ORDER BY m.tanggal DESC, m.id DESC LIMIT 300',
    $par
);
$penginput = aset_penginput('aset_mutasi', $daftar);
?>
<div class="tab-kecil no-cetak">
  <a href="<?= e(url('aset_mutasi', ['tampil' => 'pindah'])) ?>" class="<?= $tampil === 'pindah' ? 'aktif' : '' ?>">Pemindahan ruangan</a>
  <a href="<?= e(url('aset_mutasi', ['tampil' => 'pinjam'])) ?>" class="<?= $tampil === 'pinjam' ? 'aktif' : '' ?>">Peminjaman</a>
</div>

<form method="get" class="saring">
  <input type="hidden" name="h" value="aset_mutasi"><input type="hidden" name="tampil" value="<?= e($tampil) ?>">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="<?= $tampil === 'pinjam' ? 'Barang, kode, atau peminjam' : 'Barang atau kode' ?>"></div>
  <?php if ($tampil === 'pinjam'): ?>
    <div class="form-baris"><label class="fl">Status</label><select name="status" data-auto-kirim><option value="dipinjam" <?= $fStatus === 'dipinjam' ? 'selected' : '' ?>>Sedang dipinjam</option><option value="semua" <?= $fStatus === 'semua' ? 'selected' : '' ?>>Semua</option></select></div>
  <?php endif; ?>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= $tampil === 'pinjam' ? 'Peminjaman barang' : 'Pemindahan barang antar ruangan' ?></h2><p class="ket"><?= count($daftar) ?> catatan</p></div>
    <div class="aksi no-cetak">
      <?php if ($tampil === 'pinjam'): ?>
        <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPinjam" data-reset data-judul="Catat peminjaman" data-isi='<?= e(json_encode(['tanggal' => $sekarang, 'jumlah' => '1', 'barang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Catat peminjaman</button>
      <?php else: ?>
        <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPindah" data-reset data-judul="Pindahkan barang" data-isi='<?= e(json_encode(['tanggal' => $sekarang, 'barang_id' => '', 'ke_ruang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Pindahkan barang</button>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong($tampil === 'pinjam' ? 'Belum ada peminjaman' : 'Belum ada pemindahan') ?>
  <?php elseif ($tampil === 'pindah'): ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Tanggal</th><th>Barang</th><th>Dari</th><th>Ke</th><th>Keterangan</th><th>Diinput oleh</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $m): ?>
      <tr><td class="nowrap"><?= e(tanggal_pendek($m['tanggal'])) ?></td>
        <td><a href="<?= e(url('aset_barang', ['aksi' => 'lihat', 'id' => $m['barang_id']])) ?>"><b><?= e($m['barang_nama']) ?></b></a><div class="kecil lemah"><code><?= e($m['barang_kode']) ?></code></div></td>
        <td><?= e($m['dari_nama'] ?: '-') ?></td><td><?= e($m['ke_nama'] ?: '-') ?></td>
        <td class="kecil"><?= e($m['keterangan'] ?: '-') ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $m['id']] ?? null) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Dipinjam</th><th>Barang</th><th class="angka">Jumlah</th><th>Peminjam</th><th>Rencana kembali</th><th>Status</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $m): $telat = $m['status'] === 'dipinjam' && $m['rencana_kembali'] && $m['rencana_kembali'] < $sekarang; ?>
      <tr><td class="nowrap"><?= e(tanggal_pendek($m['tanggal'])) ?></td>
        <td><b><?= e($m['barang_nama']) ?></b><div class="kecil lemah"><code><?= e($m['barang_kode']) ?></code></div></td>
        <td class="angka"><?= angka($m['jumlah']) ?> <?= e($m['satuan']) ?></td>
        <td><b><?= e($m['peminjam']) ?></b><div class="kecil lemah"><?= e($m['unit_peminjam'] ?: '') ?></div></td>
        <td class="nowrap"><?= $m['rencana_kembali'] ? e(tanggal_pendek($m['rencana_kembali'])) : '-' ?></td>
        <td><?php if ($m['status'] === 'dikembalikan'): ?><?= lencana('Dikembalikan ' . tanggal_pendek($m['tanggal_kembali']), 'hijau') ?>
            <?php else: ?><?= $telat ? lencana('Terlambat', 'merah') : lencana('Dipinjam', 'biru') ?><?php endif; ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $m['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <?php if ($m['status'] === 'dipinjam'): ?>
            <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiKembali" data-judul="Pengembalian barang" data-isi='<?= e(json_encode(['id' => (int) $m['id'], 'tanggal_kembali' => $sekarang, 'kondisi_kembali' => 'baik', 'keterangan' => $m['keterangan']])) ?>'>Dikembalikan</button>
          <?php endif; ?>
          <form method="post" data-konfirmasi="Hapus catatan peminjaman ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiPindah" hidden><div class="modal sempit"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="pindah">
  <div class="modal-kepala"><h2>Pindahkan barang</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-baris"><label class="fl" for="mBrg">Barang <span class="wajib">*</span></label><select id="mBrg" name="barang_id" required><?= opsi_barang(0, '— Pilih barang —') ?></select></div>
    <div class="form-baris"><label class="fl" for="mKe">Ruangan tujuan <span class="wajib">*</span></label><select id="mKe" name="ke_ruang_id" required><?= opsi_ruang(0, '— Pilih ruangan —') ?></select></div>
    <div class="form-baris"><label class="fl" for="mTgl">Tanggal</label><input type="date" id="mTgl" name="tanggal" required></div>
    <div class="form-baris"><label class="fl" for="mKet">Keterangan</label><textarea id="mKet" name="keterangan" rows="2" maxlength="3000"></textarea></div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
</form></div></div>

<div class="tirai" id="tiraiPinjam" hidden><div class="modal"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="pinjam">
  <div class="modal-kepala"><h2>Catat peminjaman</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-baris"><label class="fl" for="pjBrg">Barang <span class="wajib">*</span></label><select id="pjBrg" name="barang_id" required><?= opsi_barang(0, '— Pilih barang —') ?></select></div>
    <div class="form-grid">
      <div class="form-baris"><label class="fl" for="pjJml">Jumlah</label><input type="number" id="pjJml" name="jumlah" min="1" value="1"></div>
      <div class="form-baris"><label class="fl" for="pjTgl">Tanggal pinjam</label><input type="date" id="pjTgl" name="tanggal" required></div>
      <div class="form-baris"><label class="fl" for="pjNama">Peminjam <span class="wajib">*</span></label><input type="text" id="pjNama" name="peminjam" maxlength="150" required></div>
      <div class="form-baris"><label class="fl" for="pjUnit">Unit / bagian</label><input type="text" id="pjUnit" name="unit_peminjam" maxlength="150"></div>
      <div class="form-baris"><label class="fl" for="pjKembali">Rencana kembali</label><input type="date" id="pjKembali" name="rencana_kembali"></div>
    </div>
    <div class="form-baris"><label class="fl" for="pjKet">Keperluan / keterangan</label><textarea id="pjKet" name="keterangan" rows="2" maxlength="3000"></textarea></div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
</form></div></div>

<div class="tirai" id="tiraiKembali" hidden><div class="modal sempit"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="kembali"><input type="hidden" name="id" value="">
  <div class="modal-kepala"><h2>Pengembalian barang</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-baris"><label class="fl" for="kbTgl">Tanggal kembali</label><input type="date" id="kbTgl" name="tanggal_kembali" required></div>
    <div class="form-baris"><label class="fl" for="kbKond">Kondisi saat kembali</label>
      <select id="kbKond" name="kondisi_kembali"><?php foreach (ASET_KONDISI as $k => $d): ?><option value="<?= $k ?>"><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
    <div class="form-baris"><label class="fl" for="kbKet">Keterangan</label><textarea id="kbKet" name="keterangan" rows="2" maxlength="3000"></textarea></div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
</form></div></div>
