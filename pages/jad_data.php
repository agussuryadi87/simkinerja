<?php
$tahunTerbaru = JAD_TAHUN[count(JAD_TAHUN) - 1];
$tahun = (int) ($_GET['tahun'] ?? $tahunTerbaru);
if (!in_array($tahun, JAD_TAHUN, true)) {
    $tahun = $tahunTerbaru;
}
$formatBaru = $tahun >= 2025;
$judulKartu = $formatBaru ? 'Dosen Universitas Ibnu Sina (Data Jafung)' : 'Data Dosen Tahun ' . $tahun;
$subjudul = 'Tahun ' . $tahun;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $did = (int) post('id');
    $lama = $did ? row('SELECT * FROM dosen_data WHERE id = ?', [$did]) : null;
    if ($lama) {
        $tahun = (int) $lama['tahun'];
    }
    $balik = static function () use (&$tahun): void {
        kembali_ke('jad_data', ['tahun' => $tahun]);
    };
    if ($op === 'hapus' && $lama) {
        q('DELETE FROM dosen_data WHERE id = ?', [$did]);
        catat_log('hapus_dosen', 'dosen_data', $did, $lama['nama']);
        flash('Data dosen <b>' . e($lama['nama']) . '</b> dihapus.');
        $balik();
    }
    if ($op === 'simpan') {
        $baru = $tahun >= 2025;
        $jafungAsli = mb_substr(post('jafung_asli'), 0, 60);
        $noSert = mb_substr(post('no_sertifikat'), 0, 60);
        $statusSerdos = mb_substr(post('status_serdos'), 0, 40);
        $tersertifikasi = $baru
            ? ($noSert !== '' && stripos($noSert, 'belum') === false ? 1 : 0)
            : (stripos($statusSerdos, 'tersertifikasi') !== false && stripos($statusSerdos, 'belum') === false ? 1 : 0);
        $thSerdos = post('tahun_serdos');
        $ak = post('angka_kredit');
        $tmt = post('tmt_jafung');
        $data = [
            'nidn' => mb_substr(post('nidn'), 0, 30), 'nuptk' => mb_substr(post('nuptk'), 0, 30), 'uisa' => mb_substr(post('uisa'), 0, 40),
            'nama' => jad_huruf_nama(mb_substr(post('nama'), 0, 200)), 'pendidikan' => in_array(post('pendidikan'), ['S2', 'S3'], true) ? post('pendidikan') : '',
            'prodi' => mb_substr(post('prodi'), 0, 120), 'jafung' => jad_jafung_baku($jafungAsli), 'jafung_asli' => $jafungAsli,
            'status_dosen' => $baru ? mb_substr(post('status_dosen'), 0, 80) : '', 'no_sertifikat' => $baru ? $noSert : '',
            'status_serdos' => $baru ? ($tersertifikasi ? 'Tersertifikasi' : 'Belum tersertifikasi') : $statusSerdos,
            'tahun_serdos' => ctype_digit($thSerdos) ? (int) $thSerdos : null, 'tersertifikasi' => $tersertifikasi,
            'angka_kredit' => ctype_digit($ak) ? (int) $ak : null, 'tahun_pangkat' => mb_substr(post('tahun_pangkat'), 0, 10),
            'tmt_jafung' => $tmt !== '' && tanggal_sah($tmt) ? $tmt : null,
            'status_keaktifan' => mb_substr(post('status_keaktifan'), 0, 40), 'tipe' => mb_substr(post('tipe'), 0, 60),
            'no_urut' => ctype_digit(post('no_urut')) ? (int) post('no_urut') : null,
        ];
        if ($data['nama'] === '') {
            flash('Nama dosen wajib diisi.', 'galat');
            $balik();
        }
        if ($lama) {
            update('dosen_data', $data, 'id = ?', [$did]);
            catat_log('ubah_dosen', 'dosen_data', $did, $data['nama']);
            flash('Data dosen <b>' . e($data['nama']) . '</b> diperbarui.');
        } else {
            if ($data['no_urut'] === null) {
                $data['no_urut'] = (int) val('SELECT COALESCE(MAX(no_urut), 0) + 1 FROM dosen_data WHERE tahun = ?', [$tahun], 1);
            }
            $did = insert('dosen_data', $data + ['tahun' => $tahun]);
            catat_log('tambah_dosen', 'dosen_data', $did, $data['nama']);
            flash('Dosen <b>' . e($data['nama']) . '</b> ditambahkan ke data tahun ' . $tahun . '.');
        }
        $balik();
    }
    tolak_akses();
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fProdi = (string) ($_GET['prodi'] ?? '');
$fPend = in_array($_GET['pendidikan'] ?? '', ['S2', 'S3'], true) ? $_GET['pendidikan'] : '';
$fJafung = in_array($_GET['jafung'] ?? '', JAD_JAFUNG, true) ? $_GET['jafung'] : '';
$fSerdos = in_array($_GET['serdos'] ?? '', ['ya', 'belum'], true) ? $_GET['serdos'] : '';

$daftarProdi = array_column(rows('SELECT DISTINCT prodi FROM dosen_data WHERE tahun = ? AND prodi <> \'\' ORDER BY prodi', [$tahun]), 'prodi');
$syarat = ['tahun = ?'];
$par = [$tahun];
if ($cari !== '') {
    $syarat[] = '(nama LIKE ? OR nidn LIKE ? OR nuptk LIKE ?)';
    $par = array_merge($par, array_fill(0, 3, '%' . $cari . '%'));
}
if ($fProdi !== '' && in_array($fProdi, $daftarProdi, true)) {
    $syarat[] = 'prodi = ?';
    $par[] = $fProdi;
}
if ($fPend !== '') {
    $syarat[] = 'pendidikan = ?';
    $par[] = $fPend;
}
if ($fJafung !== '') {
    $syarat[] = 'jafung = ?';
    $par[] = $fJafung;
}
if ($fSerdos !== '') {
    $syarat[] = 'tersertifikasi = ?';
    $par[] = $fSerdos === 'ya' ? 1 : 0;
}
$daftar = rows('SELECT * FROM dosen_data WHERE ' . implode(' AND ', $syarat) . ' ORDER BY no_urut, id', $par);
$arsipDosen = jad_arsip_baca()['dosen'];
$fArsip = in_array($_GET['arsip'] ?? '', ['sudah', 'belum'], true) ? $_GET['arsip'] : '';
if ($fArsip !== '') {
    $daftar = array_values(array_filter($daftar, static function ($d) use ($arsipDosen, $fArsip) {
        $ada = !empty($arsipDosen[jad_arsip_kunci($d)]['link']);
        return $fArsip === 'sudah' ? $ada : !$ada;
    }));
}
$dosenTerbaru = rows('SELECT id, nidn, nuptk FROM dosen_data WHERE tahun = ?', [$tahunTerbaru]);
$sudahArsip = count(array_filter($dosenTerbaru, static function ($d) use ($arsipDosen) { return !empty($arsipDosen[jad_arsip_kunci($d)]['link']); }));
$totalJenis = count(JAD_ARSIP_JENIS);
$kolArsip = static function (array $d) use ($arsipDosen, $totalJenis): string {
    $c = $arsipDosen[jad_arsip_kunci($d)] ?? null;
    $link = $c && is_string($c['link'] ?? null) ? $c['link'] : '';
    if ($link === '') {
        return '<td class="no-cetak"><span class="lemah">Belum dikirim</span></td>';
    }
    $dok = is_array($c['dokumen'] ?? null) ? $c['dokumen'] : [];
    $n = count($dok);
    $isi = ['nama' => jad_huruf_nama((string) $d['nama']), 'waktu' => tanggal_id(substr((string) $c['waktu'], 0, 10)), 'kontak' => (string) ($c['kontak'] ?? ''), 'ket' => (string) ($c['ket_lainnya'] ?? ''), 'folder' => $link, 'dok' => []];
    foreach (JAD_ARSIP_JENIS as $k => $j) {
        $isi['dok'][] = [$j[0], in_array($k, $dok, true)];
    }
    $aman = jad_arsip_url_sah($link);
    return '<td class="no-cetak"><div class="arsip-sel">' . ($aman ? '<a class="arsip-buka" href="' . e($link) . '" target="_blank" rel="noopener noreferrer" title="' . e($link) . '">' . ikon('tautan', 13) . ' Buka folder</a>' : '<span class="lemah">Link tidak sah</span>')
        . '<button class="arsip-jumlah" type="button" data-arsip=\'' . e(json_encode($isi)) . '\' title="Lihat rincian dokumen">' . $n . '/' . $totalJenis . '</button></div></td>';
};
$tgl = static function (?string $d): string {
    return $d ? tanggal_pendek($d) : '-';
};
$kosong = static function ($v): string {
    return $v === null || $v === '' ? '-' : e((string) $v);
};
$aksiBaris = static function (array $d): string {
    $isi = [
        'id' => (int) $d['id'], 'no_urut' => $d['no_urut'], 'nidn' => $d['nidn'], 'nuptk' => $d['nuptk'], 'uisa' => $d['uisa'], 'nama' => $d['nama'],
        'pendidikan' => $d['pendidikan'], 'prodi' => $d['prodi'], 'jafung_asli' => $d['jafung_asli'], 'status_dosen' => $d['status_dosen'],
        'no_sertifikat' => $d['no_sertifikat'], 'status_serdos' => $d['status_serdos'], 'tahun_serdos' => $d['tahun_serdos'], 'angka_kredit' => $d['angka_kredit'],
        'tahun_pangkat' => $d['tahun_pangkat'], 'tmt_jafung' => $d['tmt_jafung'], 'status_keaktifan' => $d['status_keaktifan'], 'tipe' => $d['tipe'],
    ];
    return '<td class="no-cetak aksi-sel"><button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiDosen" data-judul="Edit data dosen" data-isi=\'' . e(json_encode($isi)) . '\'>'
         . ikon('sunting', 14) . ' Edit</button></td>';
};
?>

<?php $linkArsip = aset_url_mutlak('index.php?h=arsip_dosen'); $persenArsip = count($dosenTerbaru) ? round($sudahArsip / count($dosenTerbaru) * 100) : 0; ?>
<div class="notif notif-info no-cetak link-publik"><?= ikon('tautan', 17) ?>
  <div><b>Link formulir arsip dokumen untuk dosen</b>
    <div class="link-publik-baris"><input type="text" readonly value="<?= e($linkArsip) ?>" id="linkArsip" aria-label="Link formulir arsip dokumen dosen">
      <button class="btn btn-garis btn-kecil" type="button" id="salinLinkArsip"><?= ikon('tautan', 14) ?> Salin</button>
      <a class="btn btn-garis btn-kecil" href="<?= e($linkArsip) ?>" target="_blank" rel="noopener"><?= ikon('mata', 14) ?> Buka</a>
      <span class="arsip-progres"><i><b style="width:<?= $persenArsip ?>%"></b></i><?= $sudahArsip ?> dari <?= count($dosenTerbaru) ?> dosen sudah mengirim</span></div></div></div>

<form method="get" class="saring saring-satu">
  <input type="hidden" name="h" value="jad_data">
  <div class="form-baris"><label class="fl">Tahun</label><select name="tahun" data-auto-kirim><?php foreach (array_reverse(JAD_TAHUN) as $t): ?><option value="<?= $t ?>" <?= $tahun === $t ? 'selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="form-baris" style="flex:1;min-width:150px"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nama, NIDN, atau NUPTK"></div>
  <div class="form-baris"><label class="fl">Program studi</label><select name="prodi" data-auto-kirim><option value="">Semua prodi</option>
    <?php foreach ($daftarProdi as $p): ?><option value="<?= e($p) ?>" <?= $fProdi === $p ? 'selected' : '' ?>><?= e($p) ?></option><?php endforeach; ?></select></div>
  <div class="form-baris"><label class="fl">Pendidikan</label><select name="pendidikan" data-auto-kirim><option value="">Semua</option><option value="S2" <?= $fPend === 'S2' ? 'selected' : '' ?>>S2</option><option value="S3" <?= $fPend === 'S3' ? 'selected' : '' ?>>S3</option></select></div>
  <div class="form-baris"><label class="fl">Jabatan fungsional</label><select name="jafung" data-auto-kirim><option value="">Semua</option>
    <?php foreach (JAD_JAFUNG as $j): ?><option value="<?= e($j) ?>" <?= $fJafung === $j ? 'selected' : '' ?>><?= e($j) ?></option><?php endforeach; ?></select></div>
  <div class="form-baris"><label class="fl">Arsip dokumen</label><select name="arsip" data-auto-kirim><option value="">Semua</option><option value="sudah" <?= $fArsip === 'sudah' ? 'selected' : '' ?>>Sudah kirim</option><option value="belum" <?= $fArsip === 'belum' ? 'selected' : '' ?>>Belum kirim</option></select></div>
  <div class="form-baris"><label class="fl">Sertifikasi</label><select name="serdos" data-auto-kirim><option value="">Semua</option><option value="ya" <?= $fSerdos === 'ya' ? 'selected' : '' ?>>Tersertifikasi</option><option value="belum" <?= $fSerdos === 'belum' ? 'selected' : '' ?>>Belum</option></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= e($judulKartu) ?></h2><p class="ket"><?= count($daftar) ?> dosen</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiDosen" data-reset data-judul="Tambah dosen tahun <?= $tahun ?>" data-isi='{"id":""}'><?= ikon('tambah', 15) ?> Tambah dosen</button></div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong('Tidak ada dosen yang cocok') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-dosen">
    <?php if ($formatBaru): ?>
    <thead><tr><th>NO.</th><th>STATUS</th><th>NO. SERTIFIKAT</th><th>NIDN</th><?= $tahun === 2025 ? '<th>NUPTK</th><th>NAMA DOSEN</th><th>UISA</th>' : '<th>UISA</th><th>NUPTK</th><th>NAMA DOSEN</th>' ?><th>PENDIDIKAN</th><th>PRODI</th><th>JABATAN FUNGSIONAL</th><th class="no-cetak">LINK DOKUMEN</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $urut => $d): ?>
      <tr><td><?= $urut + 1 ?></td><td><?= e($d['status_dosen']) ?></td><td class="nowrap"><?= $kosong($d['no_sertifikat']) ?></td><td class="nowrap"><?= $kosong($d['nidn']) ?></td>
        <?php if ($tahun === 2025): ?><td class="nowrap"><?= $kosong($d['nuptk']) ?></td><td class="kol-nama"><?= e(jad_huruf_nama((string) $d['nama'])) ?></td><td><?= $kosong($d['uisa']) ?></td>
        <?php else: ?><td><?= $kosong($d['uisa']) ?></td><td class="nowrap"><?= $kosong($d['nuptk']) ?></td><td class="kol-nama"><?= e(jad_huruf_nama((string) $d['nama'])) ?></td><?php endif; ?>
        <td><?= e($d['pendidikan']) ?></td><td><?= e($d['prodi']) ?></td><td><?= $kosong($d['jafung_asli']) ?></td><?= $kolArsip($d) ?><?= $aksiBaris($d) ?></tr>
    <?php endforeach; ?>
    </tbody>
    <?php else: ?>
    <thead><tr><th>NO</th><th>NIDN</th><th>Nama</th><th>Jenjang Pendidikan</th><th>Tahun pangkat</th><th>Jafung</th><th>Angka Kredit</th><th>TMT Jafung</th><th>Program Studi</th><th>Status KeAktifan</th><th>Status Serdos</th><th>Tahun Serdos</th><th>Tipe</th><th class="no-cetak">Link dokumen</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $urut => $d): ?>
      <tr><td><?= $urut + 1 ?></td><td class="nowrap"><?= $kosong($d['nidn']) ?></td><td class="kol-nama"><?= e(jad_huruf_nama((string) $d['nama'])) ?></td><td><?= e($d['pendidikan']) ?></td><td><?= $kosong($d['tahun_pangkat']) ?></td>
        <td><?= $kosong($d['jafung_asli']) ?></td><td><?= $kosong($d['angka_kredit']) ?></td><td class="nowrap"><?= $tgl($d['tmt_jafung']) ?></td><td><?= e($d['prodi']) ?></td>
        <td><?= $kosong($d['status_keaktifan']) ?></td><td><?= $kosong($d['status_serdos']) ?></td><td><?= $kosong($d['tahun_serdos']) ?></td><td><?= $kosong($d['tipe']) ?></td><?= $kolArsip($d) ?><?= $aksiBaris($d) ?></tr>
    <?php endforeach; ?>
    </tbody>
    <?php endif; ?>
  </table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiDosen" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value=""><input type="hidden" name="tahun" value="<?= $tahun ?>">
      <div class="modal-kepala"><h2>Data dosen</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris penuh"><label class="fl" for="dNama">Nama dosen <span class="wajib">*</span></label><input type="text" id="dNama" name="nama" maxlength="200" required></div>
          <div class="form-baris"><label class="fl" for="dNidn">NIDN</label><input type="text" id="dNidn" name="nidn" maxlength="30"></div>
          <div class="form-baris"><label class="fl" for="dNo">Nomor urut</label><input type="number" id="dNo" name="no_urut" min="1" placeholder="Otomatis"></div>
          <?php if ($formatBaru): ?>
            <div class="form-baris"><label class="fl" for="dNuptk">NUPTK</label><input type="text" id="dNuptk" name="nuptk" maxlength="30"></div>
            <div class="form-baris"><label class="fl" for="dUisa">UISA</label><input type="text" id="dUisa" name="uisa" maxlength="40"></div>
            <div class="form-baris"><label class="fl" for="dStatus">Status</label>
              <select id="dStatus" name="status_dosen"><option value="Dosen (DS)">Dosen (DS)</option><option value="Dosen dengan tugas tambahan (DT)">Dosen dengan tugas tambahan (DT)</option></select></div>
            <div class="form-baris"><label class="fl" for="dSert">No. sertifikat</label><input type="text" id="dSert" name="no_sertifikat" maxlength="60" placeholder="Kosongkan / &quot;Belum tersertifikasi dosen&quot;"></div>
          <?php else: ?>
            <div class="form-baris"><label class="fl" for="dTipe">Tipe</label><input type="text" id="dTipe" name="tipe" maxlength="60" list="saranTipe"></div>
            <div class="form-baris"><label class="fl" for="dAktif">Status keaktifan</label><input type="text" id="dAktif" name="status_keaktifan" maxlength="40" list="saranAktif"></div>
            <div class="form-baris"><label class="fl" for="dSerdos">Status serdos</label><select id="dSerdos" name="status_serdos"><option value="">Belum</option><option value="Tersertifikasi">Tersertifikasi</option></select></div>
            <div class="form-baris"><label class="fl" for="dThSerdos">Tahun serdos</label><input type="number" id="dThSerdos" name="tahun_serdos" min="1990" max="2100"></div>
            <div class="form-baris"><label class="fl" for="dAK">Angka kredit</label><input type="number" id="dAK" name="angka_kredit" min="0"></div>
            <div class="form-baris"><label class="fl" for="dThPangkat">Tahun pangkat</label><input type="text" id="dThPangkat" name="tahun_pangkat" maxlength="10"></div>
            <div class="form-baris"><label class="fl" for="dTmt">TMT Jafung</label><input type="date" id="dTmt" name="tmt_jafung"></div>
            <datalist id="saranTipe"><?php foreach (['Dosen Tetap', 'Dosen dengan Perjanjian Kerja', 'Dosen PNS Dpk', 'Dosen Tidak Tetap'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
            <datalist id="saranAktif"><?php foreach (['Aktif', 'IJIN BELAJAR', 'TUGAS BELAJAR'] as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist>
          <?php endif; ?>
          <div class="form-baris"><label class="fl" for="dPend">Pendidikan</label><select id="dPend" name="pendidikan"><option value="S2">S2</option><option value="S3">S3</option></select></div>
          <div class="form-baris"><label class="fl" for="dJafung">Jabatan fungsional</label><select id="dJafung" name="jafung_asli"><?= pilihan(jad_pilihan_jafung(), '-') ?></select></div>
          <div class="form-baris penuh"><label class="fl" for="dProdi">Program studi</label><input type="text" id="dProdi" name="prodi" maxlength="120" list="saranProdi">
            <datalist id="saranProdi"><?php foreach ($daftarProdi as $p): ?><option value="<?= e($p) ?>"><?php endforeach; ?></datalist></div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="submit" class="btn btn-garis" name="op" value="hapus" id="dHapus" data-konfirmasi="Hapus data dosen ini dari tahun <?= $tahun ?>?" hidden>Hapus</button>
        <span style="flex:1"></span>
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama" name="op" value="simpan"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<div class="tirai" id="tiraiArsip" hidden>
  <div class="modal">
    <div class="modal-kepala"><h2 id="arsipJudul">Arsip dokumen</h2>
      <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
    <div class="modal-isi">
      <p class="ket" id="arsipInfo"></p>
      <div class="arsip-folder"><?= ikon('tautan', 16) ?><div><span>Link folder dokumen</span><a id="arsipFolder" href="#" target="_blank" rel="noopener noreferrer"></a></div></div>
      <ul class="arsip-daftar arsip-modal-daftar" id="arsipDaftar"></ul>
    </div>
    <div class="modal-kaki"><span style="flex:1"></span><button type="button" class="btn btn-garis" data-tutup>Tutup</button></div>
  </div>
</div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var s = document.getElementById('salinLinkArsip'), i = document.getElementById('linkArsip');
    if (s && i) {
      s.addEventListener('click', function () {
        var ok = function () { var a = s.innerHTML; s.textContent = 'Tersalin'; setTimeout(function () { s.innerHTML = a; }, 1600); };
        if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(i.value).then(ok); }
        else { i.select(); try { document.execCommand('copy'); ok(); } catch (e) {} }
      });
    }
    var tirai = document.getElementById('tiraiArsip');
    var ikonAda = <?= json_encode(ikon('centang', 14)) ?>, ikonTidak = <?= json_encode(ikon('silang', 14)) ?>;
    document.addEventListener('click', function (ev) {
      var b = ev.target.closest('[data-arsip]');
      if (!b || !tirai) return;
      var d; try { d = JSON.parse(b.getAttribute('data-arsip')); } catch (e) { return; }
      document.getElementById('arsipJudul').textContent = d.nama;
      document.getElementById('arsipInfo').textContent = 'Diperbarui ' + d.waktu + (d.kontak ? ' · Kontak: ' + d.kontak : '') + (d.ket ? ' · Keterangan: ' + d.ket : '');
      var fa = document.getElementById('arsipFolder');
      if (/^https?:\/\//i.test(d.folder)) { fa.href = d.folder; } else { fa.removeAttribute('href'); }
      fa.textContent = d.folder;
      var ul = document.getElementById('arsipDaftar');
      ul.innerHTML = '';
      d.dok.forEach(function (l) {
        var li = document.createElement('li'), sp = document.createElement('span'), nm = document.createElement('b'), em = document.createElement('em');
        if (l[1]) li.className = 'ada';
        sp.innerHTML = l[1] ? ikonAda : ikonTidak;
        nm.textContent = l[0];
        em.textContent = l[1] ? 'Ada' : 'Belum ada';
        li.appendChild(sp); li.appendChild(nm); li.appendChild(em);
        ul.appendChild(li);
      });
      tirai.hidden = false;
      document.body.style.overflow = 'hidden';
    });
  })();
  (function () {
    var f = document.querySelector('#tiraiDosen [name="id"]'), h = document.getElementById('dHapus');
    if (f && h) { f.addEventListener('change', function () { h.hidden = !f.value; }); }
  })();
</script>
