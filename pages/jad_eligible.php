<?php
$jenis = array_key_exists($_GET['jenis'] ?? '', JAD_ELIGIBLE) ? $_GET['jenis'] : 'gb';
$kodeJudul = ['gb' => 'GB', 'lk' => 'LK', 'lektor' => 'Lektor'][$jenis];
$subjudul = JAD_ELIGIBLE[$jenis];

$bidang = ['nama' => 200, 'nidn' => 30, 'nip' => 40, 'nuptk' => 30, 'status_kepegawaian' => 40, 'ikatan_kerja' => 60, 'perguruan_tinggi' => 120, 'prodi' => 120,
    'jabfung' => 60, 'tmt_jabfung' => 40, 'golongan' => 20, 'tmt_golongan' => 40, 'rumpun_ilmu' => 120, 'pohon_ilmu' => 200, 'cabang_ilmu' => 200];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $eid = (int) post('id');
    $lama = $eid ? row('SELECT * FROM dosen_eligible WHERE id = ?', [$eid]) : null;
    if ($lama) {
        $jenis = $lama['kategori'];
    } elseif (array_key_exists(post('jenis'), JAD_ELIGIBLE)) {
        $jenis = post('jenis');
    }
    $balik = static function () use (&$jenis): void {
        kembali_ke('jad_eligible', ['jenis' => $jenis]);
    };
    if ($op === 'hapus' && $lama) {
        q('DELETE FROM dosen_eligible WHERE id = ?', [$eid]);
        catat_log('hapus_dosen_eligible', 'dosen_eligible', $eid, $lama['nama']);
        flash('Data <b>' . e($lama['nama']) . '</b> dihapus dari daftar eligible.');
        $balik();
    }
    if ($op === 'simpan') {
        $data = [];
        foreach ($bidang as $k => $maks) {
            $data[$k] = mb_substr(post($k), 0, $maks);
        }
        $data['nama'] = jad_huruf_nama($data['nama']);
        if ($data['nama'] === '') {
            flash('Nama dosen wajib diisi.', 'galat');
            $balik();
        }
        $data['no_urut'] = ctype_digit(post('no_urut')) ? (int) post('no_urut') : null;
        if ($lama) {
            update('dosen_eligible', $data, 'id = ?', [$eid]);
            catat_log('ubah_dosen_eligible', 'dosen_eligible', $eid, $data['nama']);
            flash('Data <b>' . e($data['nama']) . '</b> diperbarui.');
        } else {
            if ($data['no_urut'] === null) {
                $data['no_urut'] = (int) val('SELECT COALESCE(MAX(no_urut), 0) + 1 FROM dosen_eligible WHERE kategori = ?', [$jenis], 1);
            }
            $eid = insert('dosen_eligible', $data + ['kategori' => $jenis]);
            catat_log('tambah_dosen_eligible', 'dosen_eligible', $eid, $data['nama']);
            flash('<b>' . e($data['nama']) . '</b> ditambahkan ke ' . e(JAD_ELIGIBLE[$jenis]) . '.');
        }
        $balik();
    }
    tolak_akses();
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fProdi = (string) ($_GET['prodi'] ?? '');

$daftarProdi = array_column(rows('SELECT DISTINCT prodi FROM dosen_eligible WHERE kategori = ? AND prodi <> \'\' ORDER BY prodi', [$jenis]), 'prodi');
$syarat = ['kategori = ?'];
$par = [$jenis];
if ($cari !== '') {
    $syarat[] = '(nama LIKE ? OR nidn LIKE ? OR nuptk LIKE ?)';
    $par = array_merge($par, array_fill(0, 3, '%' . $cari . '%'));
}
if ($fProdi !== '' && in_array($fProdi, $daftarProdi, true)) {
    $syarat[] = 'prodi = ?';
    $par[] = $fProdi;
}
$daftar = rows('SELECT * FROM dosen_eligible WHERE ' . implode(' AND ', $syarat) . ' ORDER BY no_urut, id', $par);
$jml = (int) val('SELECT COUNT(*) FROM dosen_eligible WHERE kategori = ?', [$jenis], 0);
$kosong = static function ($v): string {
    return $v === null || $v === '' ? '-' : e((string) $v);
};
$label = ['nama' => 'Nama Dosen', 'nidn' => 'NIDN/NIDK', 'nip' => 'NIP', 'nuptk' => 'NUPTK', 'status_kepegawaian' => 'Status Kepegawaian', 'ikatan_kerja' => 'Ikatan Kerja',
    'perguruan_tinggi' => 'Perguruan Tinggi', 'prodi' => 'Prodi', 'jabfung' => 'Jabfung', 'tmt_jabfung' => 'TMT Jabfung', 'golongan' => 'Golongan', 'tmt_golongan' => 'TMT Golongan',
    'rumpun_ilmu' => 'Rumpun Ilmu', 'pohon_ilmu' => 'Pohon Ilmu', 'cabang_ilmu' => 'Cabang Ilmu'];
?>
<div class="tab-kecil no-cetak">
  <?php foreach (JAD_ELIGIBLE as $k => $nm): ?>
    <a href="<?= e(url('jad_eligible', ['jenis' => $k])) ?>" class="<?= $jenis === $k ? 'aktif' : '' ?>"><?= e($nm) ?></a>
  <?php endforeach; ?>
</div>

<form method="get" class="saring">
  <input type="hidden" name="h" value="jad_eligible"><input type="hidden" name="jenis" value="<?= e($jenis) ?>">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nama, NIDN/NIDK, atau NUPTK"></div>
  <div class="form-baris"><label class="fl">Prodi</label><select name="prodi" data-auto-kirim><option value="">Semua prodi</option>
    <?php foreach ($daftarProdi as $p): ?><option value="<?= e($p) ?>" <?= $fProdi === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2>SISTER | Daftar Dosen Eligible <?= e($kodeJudul) ?></h2><p class="ket"><?= count($daftar) ?><?= count($daftar) !== $jml ? ' dari ' . $jml : '' ?> dosen</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiEligible" data-reset data-judul="Tambah dosen eligible" data-isi='{"id":""}'><?= ikon('tambah', 15) ?> Tambah dosen</button></div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong('Tidak ada dosen yang cocok') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-dosen">
    <thead><tr><th class="no-cetak kolom-aksi">Aksi</th><th>No</th><th>Nama Dosen</th><th>NIDN/NIDK</th><th>NIP</th><th>NUPTK</th><th>Status Kepegawaian</th><th>Ikatan Kerja</th><th>Perguruan Tinggi</th><th>Prodi</th><th>Jabfung</th><th>TMT Jabfung</th><th>Golongan</th><th>TMT Golongan</th><th>Rumpun Ilmu</th><th>Pohon Ilmu</th><th>Cabang Ilmu</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $urut => $d):
        $isi = ['id' => (int) $d['id'], 'no_urut' => $d['no_urut']];
        foreach ($bidang as $k => $_) {
            $isi[$k] = $d[$k];
        } ?>
      <tr><td class="no-cetak aksi-sel"><button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiEligible" data-judul="Edit dosen eligible" data-isi='<?= e(json_encode($isi)) ?>'><?= ikon('sunting', 14) ?> Edit</button></td>
        <td><?= $urut + 1 ?></td><td class="kol-nama"><?= e(jad_huruf_nama((string) $d['nama'])) ?></td><td class="nowrap"><?= $kosong($d['nidn']) ?></td><td class="nowrap"><?= $kosong($d['nip']) ?></td><td class="nowrap"><?= $kosong($d['nuptk']) ?></td>
        <td><?= $kosong($d['status_kepegawaian']) ?></td><td><?= $kosong($d['ikatan_kerja']) ?></td><td><?= $kosong($d['perguruan_tinggi']) ?></td><td><?= $kosong($d['prodi']) ?></td>
        <td><?= $kosong($d['jabfung']) ?></td><td class="nowrap"><?= $kosong($d['tmt_jabfung']) ?></td><td><?= $kosong($d['golongan']) ?></td><td class="nowrap"><?= $kosong($d['tmt_golongan']) ?></td>
        <td><?= $kosong($d['rumpun_ilmu']) ?></td><td><?= $kosong($d['pohon_ilmu']) ?></td><td><?= $kosong($d['cabang_ilmu']) ?></td></tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiEligible" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value=""><input type="hidden" name="jenis" value="<?= e($jenis) ?>">
      <div class="modal-kepala"><h2>Dosen eligible</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris penuh"><label class="fl" for="eNama">Nama Dosen <span class="wajib">*</span></label><input type="text" id="eNama" name="nama" maxlength="200" required></div>
          <div class="form-baris"><label class="fl" for="eNo">No</label><input type="number" id="eNo" name="no_urut" min="1" placeholder="Otomatis"></div>
          <?php foreach ($label as $k => $lb): if ($k === 'nama') { continue; } ?>
            <div class="form-baris<?= in_array($k, ['rumpun_ilmu', 'pohon_ilmu', 'cabang_ilmu'], true) ? ' penuh' : '' ?>"><label class="fl" for="e_<?= $k ?>"><?= e($lb) ?></label>
              <input type="text" id="e_<?= $k ?>" name="<?= $k ?>" maxlength="<?= $bidang[$k] ?>"></div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="submit" class="btn btn-garis" name="op" value="hapus" id="eHapus" data-konfirmasi="Hapus dosen ini dari daftar eligible?" hidden>Hapus</button>
        <span style="flex:1"></span>
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama" name="op" value="simpan"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var f = document.querySelector('#tiraiEligible [name="id"]'), h = document.getElementById('eHapus');
    if (f && h) { f.addEventListener('change', function () { h.hidden = !f.value; }); }
  })();
</script>
