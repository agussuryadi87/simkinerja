<?php
$jenis = $h === 'aset_ac' ? 'ac' : 'kebersihan';
$namaJenis = $jenis === 'ac' ? 'Maintenance AC' : 'Maintenance Kebersihan';
$saranKegiatan = $jenis === 'ac' ? ASET_KEGIATAN_AC : ASET_KEGIATAN_KEBERSIHAN;
$sekarang = date('Y-m-d');
$penuh = boleh_aset();
$milikCs = static function (array $w): bool {
    return (int) $w['dibuat_oleh'] === uid();
};

$buatBerikutnya = static function (array $w, string $dasar) use ($jenis): void {
    $hari = (int) $w['ulang_hari'];
    if ($hari <= 0) {
        return;
    }
    $tgl = date('Y-m-d', strtotime($dasar . ' +' . $hari . ' days'));
    $ada = (int) val(
        "SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'terjadwal' AND kegiatan = ? AND ruang_id <=> ? AND barang_id <=> ?",
        [$jenis, $w['kegiatan'], $w['ruang_id'], $w['barang_id']], 0
    );
    if ($ada === 0) {
        insert('aset_perawatan', [
            'jenis' => $jenis, 'ruang_id' => $w['ruang_id'], 'barang_id' => $w['barang_id'], 'kegiatan' => $w['kegiatan'], 'tanggal' => $tgl,
            'status' => 'terjadwal', 'petugas' => $w['petugas'], 'ulang_hari' => $hari, 'dibuat_oleh' => uid(),
        ]);
    }
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $wid = (int) post('id');
    $lama = $wid ? row('SELECT * FROM aset_perawatan WHERE id = ? AND jenis = ?', [$wid, $jenis]) : null;
    if (!$penuh && ($op === 'hapus' || ($op === 'simpan' && $lama && !$milikCs($lama)))) {
        tolak_akses();
    }
    if (!$penuh) {
        $_POST['biaya'] = '';
        if (post('petugas') === '') {
            $_POST['petugas'] = (string) (user()['nama'] ?? '');
        }
    }

    if ($op === 'simpan') {
        $tanggal = post('tanggal');
        $ulang = post('ulang_hari');
        $data = [
            'jenis'      => $jenis,
            'ruang_id'   => $jenis === 'kebersihan' ? ((int) post('ruang_id') ?: null) : null,
            'barang_id'  => $jenis === 'ac' ? ((int) post('barang_id') ?: null) : null,
            'kegiatan'   => mb_substr(post('kegiatan'), 0, 200),
            'tanggal'    => $tanggal,
            'status'     => post('status') === 'selesai' ? 'selesai' : 'terjadwal',
            'petugas'    => mb_substr(post('petugas'), 0, 150),
            'ulang_hari' => array_key_exists($ulang, ASET_ULANG) && $ulang !== '' ? (int) $ulang : null,
            'biaya'      => aset_angka_atau_null('biaya'),
            'catatan'    => mb_substr(post_teks('catatan'), 0, 3000),
        ];
        $galat = [];
        if ($jenis === 'kebersihan' && (!$data['ruang_id'] || !row('SELECT id FROM aset_ruang WHERE id = ?', [$data['ruang_id']]))) {
            $galat[] = 'Pilih ruangan yang dibersihkan.';
        }
        if ($jenis === 'ac' && (!$data['barang_id'] || !row("SELECT id FROM aset_barang WHERE id = ? AND kategori = 'ac'", [$data['barang_id']]))) {
            $galat[] = 'Pilih unit AC. Catat AC terlebih dahulu di Inventaris Barang dengan kategori AC.';
        }
        if ($data['kegiatan'] === '') {
            $galat[] = 'Kegiatan wajib diisi.';
        }
        if (!tanggal_sah($tanggal)) {
            $galat[] = 'Tanggal tidak sah.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke($h);
        }
        $data['selesai_pada'] = $data['status'] === 'selesai' ? ($lama['selesai_pada'] ?? $tanggal) : null;
        if ($lama) {
            update('aset_perawatan', $data + ['diubah' => date('Y-m-d H:i:s')], 'id = ?', [$wid]);
            catat_log('ubah_aset_rawat', 'aset_perawatan', $wid, $data['kegiatan']);
            if ($lama['status'] === 'terjadwal' && $data['status'] === 'selesai') {
                $buatBerikutnya($data, $tanggal);
            }
            flash('Catatan ' . strtolower($namaJenis) . ' diperbarui.');
        } else {
            $wid = insert('aset_perawatan', $data + ['dibuat_oleh' => uid()]);
            catat_log('tambah_aset_rawat', 'aset_perawatan', $wid, $data['kegiatan']);
            if ($data['status'] === 'selesai') {
                $buatBerikutnya($data, $tanggal);
            }
            flash('Catatan ' . strtolower($namaJenis) . ' ditambahkan.');
        }
    } elseif ($op === 'selesai' && $lama && $lama['status'] === 'terjadwal') {
        $tgl = tanggal_sah(post('selesai_pada')) ? post('selesai_pada') : $sekarang;
        $lama['petugas'] = mb_substr(post('petugas') ?: $lama['petugas'], 0, 150);
        update('aset_perawatan', [
            'status' => 'selesai', 'selesai_pada' => $tgl, 'petugas' => $lama['petugas'], 'biaya' => aset_angka_atau_null('biaya'),
            'catatan' => mb_substr(post_teks('catatan'), 0, 3000), 'diubah' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$wid]);
        $buatBerikutnya($lama, $tgl);
        catat_log('selesai_aset_rawat', 'aset_perawatan', $wid, $lama['kegiatan']);
        flash('Pekerjaan ditandai selesai' . ((int) $lama['ulang_hari'] > 0 ? '. Jadwal berikutnya dibuat otomatis.' : '.'));
    } elseif ($op === 'hapus' && $lama) {
        q('DELETE FROM aset_perawatan WHERE id = ?', [$wid]);
        catat_log('hapus_aset_rawat', 'aset_perawatan', $wid, $lama['kegiatan']);
        flash('Catatan dihapus.');
    } else {
        tolak_akses();
    }
    kembali_ke($h, array_filter(['tampil' => $_GET['tampil'] ?? '']));
}

$tampil = in_array($_GET['tampil'] ?? 'jadwal', ['jadwal', 'riwayat'], true) ? ($_GET['tampil'] ?? 'jadwal') : 'jadwal';
$cari = trim((string) ($_GET['cari'] ?? ''));

$statTerlambat = (int) val("SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'terjadwal' AND tanggal < ?", [$jenis, $sekarang], 0);
$statPekan = (int) val("SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'terjadwal' AND tanggal BETWEEN ? AND ?", [$jenis, $sekarang, date('Y-m-d', strtotime('+7 days'))], 0);
$statBulan = (int) val("SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'selesai' AND selesai_pada >= ?", [$jenis, date('Y-m-01')], 0);
$statTotalObjek = $jenis === 'ac'
    ? (int) val("SELECT COUNT(*) FROM aset_barang WHERE kategori = 'ac' AND status = 'aktif'", [], 0)
    : (int) val('SELECT COUNT(*) FROM aset_ruang WHERE aktif = 1', [], 0);

$syarat = ['w.jenis = ?', $tampil === 'jadwal' ? "w.status = 'terjadwal'" : "w.status = 'selesai'"];
$par = [$jenis];
if ($cari !== '') {
    $syarat[] = '(w.kegiatan LIKE ? OR w.petugas LIKE ? OR r.nama LIKE ? OR b.nama LIKE ? OR b.kode LIKE ?)';
    $par = array_merge($par, array_fill(0, 5, '%' . $cari . '%'));
}
$daftar = rows(
    "SELECT w.*, r.nama AS ruang_nama, r.kode AS ruang_kode, b.nama AS barang_nama, b.kode AS barang_kode, br.nama AS barang_ruang
       FROM aset_perawatan w LEFT JOIN aset_ruang r ON r.id = w.ruang_id
       LEFT JOIN aset_barang b ON b.id = w.barang_id LEFT JOIN aset_ruang br ON br.id = b.ruang_id
      WHERE " . implode(' AND ', $syarat) . ' ORDER BY ' . ($tampil === 'jadwal' ? 'w.tanggal ASC, w.id' : 'w.selesai_pada DESC, w.id DESC') . ' LIMIT 300',
    $par
);
$dataModal = static function (array $w): string {
    return e(json_encode([
        'id' => (int) $w['id'], 'ruang_id' => $w['ruang_id'], 'barang_id' => $w['barang_id'], 'kegiatan' => $w['kegiatan'], 'tanggal' => $w['tanggal'],
        'status' => $w['status'], 'petugas' => $w['petugas'], 'ulang_hari' => $w['ulang_hari'] ? (string) $w['ulang_hari'] : '',
        'biaya' => $w['biaya'] !== null ? (string) (int) round((float) $w['biaya']) : '', 'catatan' => $w['catatan'],
    ]));
};
$ikJenis = $jenis === 'ac' ? 'ac' : 'kebersihan';
$penginput = aset_penginput('aset_perawatan', $daftar);
?>
<form method="get" class="saring">
  <input type="hidden" name="h" value="<?= e($h) ?>">
  <div class="form-baris"><label class="fl">Tampilkan</label>
    <select name="tampil" data-auto-kirim><option value="jadwal" <?= $tampil === 'jadwal' ? 'selected' : '' ?>>Jadwal berjalan</option><option value="riwayat" <?= $tampil === 'riwayat' ? 'selected' : '' ?>>Riwayat selesai</option></select></div>
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="<?= $jenis === 'ac' ? 'Unit AC, kegiatan, atau petugas' : 'Ruangan, kegiatan, atau petugas' ?>"></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon($ikJenis, 18) ?> <?= $tampil === 'jadwal' ? 'Jadwal ' . strtolower($namaJenis) : 'Riwayat ' . strtolower($namaJenis) ?></h2>
      <p class="ket"><?= count($daftar) ?> catatan</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiRawat" data-reset data-judul="Tambah catatan"
        data-isi='<?= e(json_encode(['id' => '', 'tanggal' => $sekarang, 'status' => 'terjadwal', 'ulang_hari' => '', 'ruang_id' => '', 'barang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Tambah jadwal / catatan</button></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong($tampil === 'jadwal' ? 'Belum ada jadwal' : 'Belum ada riwayat') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th><?= $tampil === 'jadwal' ? 'Jatuh tempo' : 'Selesai' ?></th><th><?= $jenis === 'ac' ? 'Unit AC' : 'Ruangan' ?></th><th>Kegiatan</th><th>Petugas</th><th>Ulang</th><th>Status</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $w):
        $terlambat = $w['status'] === 'terjadwal' && $w['tanggal'] < $sekarang;
        $segera = $w['status'] === 'terjadwal' && !$terlambat && $w['tanggal'] <= date('Y-m-d', strtotime('+7 days')); ?>
      <tr>
        <td class="nowrap"><?= e(tanggal_pendek($tampil === 'jadwal' ? $w['tanggal'] : ($w['selesai_pada'] ?: $w['tanggal']))) ?></td>
        <td><?php if ($jenis === 'ac'): ?><b><?= e($w['barang_nama'] ?? '-') ?></b><div class="kecil lemah"><code><?= e($w['barang_kode'] ?? '') ?></code><?= $w['barang_ruang'] ? ' · ' . e($w['barang_ruang']) : '' ?></div>
            <?php else: ?><b><?= e($w['ruang_nama'] ?? '-') ?></b><div class="kecil lemah"><code><?= e($w['ruang_kode'] ?? '') ?></code></div><?php endif; ?></td>
        <td><?= e($w['kegiatan']) ?><?php if ($w['catatan']): ?><div class="kecil lemah"><?= e(mb_strimwidth($w['catatan'], 0, 90, '…')) ?></div><?php endif; ?></td>
        <td><?= e($w['petugas'] ?: '-') ?></td>
        <td class="kecil"><?= $w['ulang_hari'] ? e(ASET_ULANG[(string) $w['ulang_hari']] ?? ($w['ulang_hari'] . ' hari')) : '-' ?></td>
        <td><?= $terlambat ? lencana('Terlambat', 'merah') : ($segera ? lencana('Segera', 'kuning') : ($w['status'] === 'selesai' ? lencana('Selesai', 'hijau') : lencana('Terjadwal', 'biru'))) ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $w['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <?php if ($w['status'] === 'terjadwal'): ?>
            <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiSelesai" data-judul="Tandai selesai"
              data-isi='<?= e(json_encode(['id' => (int) $w['id'], 'selesai_pada' => $sekarang, 'petugas' => $w['petugas'], 'catatan' => $w['catatan'], 'biaya' => ''])) ?>'
              data-teks-kegiatan="1">Selesai</button>
          <?php endif; ?>
          <?php if ($penuh || $milikCs($w)): ?><button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiRawat" data-judul="Ubah catatan" data-isi='<?= $dataModal($w) ?>'><?= ikon('sunting', 15) ?></button><?php endif; ?>
          <?php if ($penuh): ?><form method="post" data-konfirmasi="Hapus catatan ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $w['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form><?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiRawat" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2><?= e($namaJenis) ?></h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <?php if ($jenis === 'ac'): ?>
          <div class="form-baris"><label class="fl" for="wBarang">Unit AC <span class="wajib">*</span></label>
            <select id="wBarang" name="barang_id" required><?= opsi_barang(0, '— Pilih unit AC —', 'ac') ?></select></div>
        <?php else: ?>
          <div class="form-baris"><label class="fl" for="wRuang">Ruangan <span class="wajib">*</span></label>
            <select id="wRuang" name="ruang_id" required><?= opsi_ruang(0, '— Pilih ruangan —') ?></select></div>
        <?php endif; ?>
        <div class="form-baris"><label class="fl" for="wKegiatan">Kegiatan <span class="wajib">*</span></label>
          <input type="text" id="wKegiatan" name="kegiatan" maxlength="200" required list="saranKegiatan" autocomplete="off">
          <datalist id="saranKegiatan"><?php foreach ($saranKegiatan as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="wTanggal">Tanggal <span class="wajib">*</span></label><input type="date" id="wTanggal" name="tanggal" required></div>
          <div class="form-baris"><label class="fl" for="wStatus">Status</label>
            <select id="wStatus" name="status"><option value="terjadwal">Terjadwal (belum dikerjakan)</option><option value="selesai">Selesai dikerjakan</option></select></div>
          <div class="form-baris"><label class="fl" for="wPetugas">Petugas / teknisi</label><input type="text" id="wPetugas" name="petugas" maxlength="150"></div>
          <div class="form-baris"><label class="fl" for="wUlang">Pengulangan jadwal</label>
            <select id="wUlang" name="ulang_hari"><?= pilihan(ASET_ULANG, '') ?></select></div>
          <?php if ($penuh): ?><div class="form-baris"><label class="fl" for="wBiaya">Biaya (Rp, opsional)</label><input type="text" id="wBiaya" name="biaya" inputmode="numeric" maxlength="18"></div><?php endif; ?>
        </div>
        <div class="form-baris"><label class="fl" for="wCatatan">Catatan</label><textarea id="wCatatan" name="catatan" rows="2" maxlength="3000"></textarea></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="tirai" id="tiraiSelesai" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="selesai"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Tandai selesai</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="sTgl">Tanggal selesai</label><input type="date" id="sTgl" name="selesai_pada" required></div>
        <div class="form-baris"><label class="fl" for="sPetugas">Petugas / teknisi</label><input type="text" id="sPetugas" name="petugas" maxlength="150"></div>
        <?php if ($penuh): ?><div class="form-baris"><label class="fl" for="sBiaya">Biaya (Rp, opsional)</label><input type="text" id="sBiaya" name="biaya" inputmode="numeric" maxlength="18"></div><?php endif; ?>
        <div class="form-baris"><label class="fl" for="sCatatan">Catatan hasil pekerjaan</label><textarea id="sCatatan" name="catatan" rows="3" maxlength="3000"></textarea></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
