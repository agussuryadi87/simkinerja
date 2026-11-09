<?php
$tahunIni = (int) date('Y');
$daftarTahun = array_map('intval', array_column(rows('SELECT DISTINCT tahun FROM keu_audit ORDER BY tahun DESC'), 'tahun'));
$daftarTahun = array_values(array_unique(array_merge($daftarTahun, [$tahunIni])));
rsort($daftarTahun);
$tahun = (int) ($_GET['tahun'] ?? $tahunIni);
if (!in_array($tahun, $daftarTahun, true)) {
    $tahun = $tahunIni;
}
$id = (int) ($_GET['id'] ?? 0);
$ambil = static function (int $aid): ?array {
    return $aid ? row('SELECT * FROM keu_audit WHERE id = ?', [$aid]) : null;
};
$tglSah = static function (string $t): ?string {
    return $t !== '' && tanggal_sah($t) ? $t : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $aid = (int) post('id');
    $aud = $ambil($aid);
    $balikDaftar = static function () use (&$tahun): void {
        kembali_ke('audit', ['tahun' => $tahun]);
    };
    $balikAudit = static function (int $a): void {
        kembali_ke('audit', ['aksi' => 'lihat', 'id' => $a]);
    };
    $tautanPost = static function (string $medan): ?string {
        $url = trim(mb_substr(post($medan), 0, 1000));
        return $url === '' || tautan_sah($url) ? $url : null;
    };
    $unggahDok = static function (int $a, string $medan = 'dokumen', string $awalan = 'a'): string {
        $n = 0;
        $galat = [];
        foreach (rapat_normalisasi_berkas($_FILES[$medan] ?? []) as $b) {
            $hasil = keu_dok_simpan($b, $a, '', $awalan);
            if (isset($hasil['galat'])) {
                $galat[] = e((string) ($b['name'] ?? 'Dokumen')) . ': ' . e($hasil['galat']);
            } elseif (isset($hasil['file'])) {
                $n++;
            }
        }
        if ($n) {
            catat_log('unggah_dokumen_audit', 'keu_audit', $a, $n . ' dokumen');
        }
        if ($galat) {
            flash(implode('<br>', $galat), 'warn');
        }
        return $n ? ' ' . $n . ' dokumen diunggah.' : '';
    };

    if ($op === 'simpan_audit') {
        $data = [
            'tahun'           => max(2000, min(2100, (int) post('tahun') ?: $tahun)),
            'judul'           => mb_substr(post('judul'), 0, 200),
            'auditor'         => mb_substr(post('auditor'), 0, 150),
            'nomor_laporan'   => mb_substr(post('nomor_laporan'), 0, 80),
            'tanggal_laporan' => $tglSah(post('tanggal_laporan')),
            'opini'           => array_key_exists(post('opini'), KEU_OPINI) ? post('opini') : '',
            'status'          => post('status') === 'selesai' ? 'selesai' : 'berjalan',
        ];
        $tautan = $tautanPost('tautan');
        if ($data['judul'] === '' || $tautan === null) {
            flash($tautan === null ? 'Link drive harus berupa alamat http:// atau https://.' : 'Judul audit wajib diisi.', 'galat');
            $balikDaftar();
        }
        $tahun = $data['tahun'];
        if ($aud) {
            update('keu_audit', $data, 'id = ?', [$aid]);
            keu_tautan_simpan('a' . $aid, $tautan);
            catat_log('ubah_audit', 'keu_audit', $aid, $data['judul']);
            $infoDok = $unggahDok($aid);
            flash('Audit <b>' . e($data['judul']) . '</b> diperbarui.' . $infoDok);
            if (post('dari') === 'daftar') {
                $balikDaftar();
            }
            $balikAudit($aid);
        }
        $aid = insert('keu_audit', $data);
        keu_tautan_simpan('a' . $aid, $tautan);
        catat_log('tambah_audit', 'keu_audit', $aid, $data['judul']);
        $infoDok = $unggahDok($aid);
        flash('Audit <b>' . e($data['judul']) . '</b> dicatat.' . $infoDok . ' Tambahkan temuan dan rekomendasinya.');
        $balikAudit($aid);
    }

    if (!$aud) {
        flash('Audit tidak ditemukan.', 'galat');
        $balikDaftar();
    }
    $tahun = (int) $aud['tahun'];

    if ($op === 'hapus_audit') {
        $idTemuan = array_map('intval', array_column(rows('SELECT id FROM keu_temuan WHERE audit_id = ?', [$aid]), 'id'));
        q('DELETE FROM keu_audit WHERE id = ?', [$aid]);
        keu_dok_hapus_milik($aid, 'a');
        keu_tautan_simpan('a' . $aid, '');
        foreach ($idTemuan as $it) {
            keu_dok_hapus_milik($it, 't');
            keu_tautan_simpan('t' . $it, '');
        }
        catat_log('hapus_audit', 'keu_audit', $aid, $aud['judul']);
        flash('Audit dihapus beserta seluruh temuan dan dokumennya.');
        $balikDaftar();
    }

    if ($op === 'unggah_dok') {
        $info = $unggahDok($aid);
        if ($info !== '') {
            flash(trim($info));
        } elseif (!array_filter(rapat_normalisasi_berkas($_FILES['dokumen'] ?? []), static function ($b) { return ($b['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE; })) {
            flash('Pilih dokumen yang akan diunggah.', 'warn');
        }
        $balikAudit($aid);
    }

    if ($op === 'ubah_dok' || $op === 'hapus_dok') {
        $berkas = post('berkas');
        $milik = array_column(keu_dok_daftar($aid), 'file');
        if (!in_array($berkas, $milik, true)) {
            flash('Dokumen tidak ditemukan.', 'galat');
            $balikAudit($aid);
        }
        if ($op === 'hapus_dok') {
            keu_dok_hapus($berkas);
            catat_log('hapus_dokumen_audit', 'keu_audit', $aid, $berkas);
            flash('Dokumen dihapus.');
            $balikAudit($aid);
        }
        $judulBaru = mb_substr(post('judul_dok'), 0, 120);
        $pengganti = rapat_normalisasi_berkas($_FILES['pengganti'] ?? [])[0] ?? null;
        if ($pengganti && ($pengganti['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
            $hasil = keu_dok_simpan($pengganti, $aid, $judulBaru !== '' ? $judulBaru : str_replace('-', ' ', explode('-', pathinfo($berkas, PATHINFO_FILENAME), 3)[2] ?? ''));
            if (isset($hasil['galat'])) {
                flash(e($hasil['galat']), 'galat');
                $balikAudit($aid);
            }
            keu_dok_hapus($berkas);
            catat_log('ganti_dokumen_audit', 'keu_audit', $aid, $hasil['file']);
            flash('Dokumen diganti.');
        } elseif ($judulBaru !== '') {
            if (keu_dok_ganti_nama($berkas, $judulBaru) === null) {
                flash('Nama dokumen gagal diubah.', 'galat');
                $balikAudit($aid);
            }
            catat_log('ubah_dokumen_audit', 'keu_audit', $aid, $judulBaru);
            flash('Nama dokumen diperbarui.');
        }
        $balikAudit($aid);
    }

    if ($op === 'simpan_temuan') {
        $tid = (int) post('temuan_id');
        $lama = $tid ? row('SELECT * FROM keu_temuan WHERE id = ? AND audit_id = ?', [$tid, $aid]) : null;
        $status = array_key_exists(post('status'), KEU_STATUS_TEMUAN) ? post('status') : 'baru';
        $data = [
            'kode'          => mb_substr(post('kode'), 0, 20),
            'uraian'        => mb_substr(post('uraian'), 0, 5000),
            'risiko'        => array_key_exists(post('risiko'), KEU_RISIKO) ? post('risiko') : 'sedang',
            'rekomendasi'   => mb_substr(post('rekomendasi'), 0, 5000),
            'unit_id'       => (int) post('unit_id') ?: null,
            'tenggat'       => $tglSah(post('tenggat')),
            'status'        => $status,
            'tindak_lanjut' => mb_substr(post('tindak_lanjut'), 0, 5000),
            'selesai_pada'  => $status === 'selesai' ? ($lama['selesai_pada'] ?? date('Y-m-d')) : null,
        ];
        $tautanTemuan = $tautanPost('tautan_temuan');
        if ($data['uraian'] === '' || $tautanTemuan === null) {
            flash($tautanTemuan === null ? 'Link drive temuan harus berupa alamat http:// atau https://.' : 'Uraian temuan wajib diisi.', 'galat');
            $balikAudit($aid);
        }
        if ($tid && !$lama) {
            tolak_akses();
        }
        if ($lama) {
            if ($data['kode'] === '') {
                $data['kode'] = $lama['kode'];
            }
            update('keu_temuan', $data, 'id = ?', [$tid]);
            catat_log('ubah_temuan', 'keu_temuan', $tid, $data['kode'] ?: mb_strimwidth($data['uraian'], 0, 60, '…'));
        } else {
            if ($data['kode'] === '') {
                $data['kode'] = 'T-' . ((int) val('SELECT COUNT(*) FROM keu_temuan WHERE audit_id = ?', [$aid], 0) + 1);
            }
            $tid = insert('keu_temuan', $data + ['audit_id' => $aid]);
            catat_log('tambah_temuan', 'keu_temuan', $tid, $data['kode']);
        }
        keu_tautan_simpan('t' . $tid, $tautanTemuan);
        flash('Temuan disimpan.' . $unggahDok((int) $tid, 'dokumen_temuan', 't'));
        $balikAudit($aid);
    }

    if ($op === 'hapus_temuan') {
        $tid = (int) post('temuan_id');
        $n = q('DELETE FROM keu_temuan WHERE id = ? AND audit_id = ?', [$tid, $aid])->rowCount();
        if ($n) {
            keu_dok_hapus_milik($tid, 't');
            keu_tautan_simpan('t' . $tid, '');
        }
        flash('Temuan dihapus.');
        $balikAudit($aid);
    }

    if ($op === 'hapus_dok_temuan') {
        $tid = (int) post('temuan_id');
        $berkas = post('berkas');
        $sah = (int) val('SELECT COUNT(*) FROM keu_temuan WHERE id = ? AND audit_id = ?', [$tid, $aid], 0) > 0
            && in_array($berkas, array_column(keu_dok_daftar($tid, 't'), 'file'), true);
        if ($sah) {
            keu_dok_hapus($berkas);
            catat_log('hapus_dokumen_temuan', 'keu_temuan', $tid, $berkas);
            flash('Dokumen temuan dihapus.');
        } else {
            flash('Dokumen tidak ditemukan.', 'galat');
        }
        $balikAudit($aid);
    }
    tolak_akses();
}

$aud = ($_GET['aksi'] ?? '') === 'lihat' ? $ambil($id) : null;
if (($_GET['aksi'] ?? '') === 'lihat' && !$aud) {
    kembali_ke('audit', ['tahun' => $tahun]);
}
$subjudul = $aud ? $aud['judul'] : 'Tahun ' . $tahun;
$hariIni = date('Y-m-d');
$terlambat = static function (array $t) use ($hariIni): bool {
    return $t['status'] !== 'selesai' && $t['tenggat'] && $t['tenggat'] < $hariIni;
};
$nilaiAudit = static function (?array $a): array {
    return $a ? ['id' => (int) $a['id'], 'tahun' => (int) $a['tahun'], 'judul' => $a['judul'], 'auditor' => $a['auditor'], 'nomor_laporan' => $a['nomor_laporan'],
        'tanggal_laporan' => $a['tanggal_laporan'] ?? '', 'opini' => $a['opini'], 'status' => $a['status'], 'tautan' => keu_tautan('a' . $a['id'])] : ['id' => ''];
};

if ($aud):
    $tahun = (int) $aud['tahun'];
    $temuan = rows('SELECT t.*, un.nama AS unit_nama FROM keu_temuan t LEFT JOIN unit un ON un.id = t.unit_id WHERE t.audit_id = ? ORDER BY t.id', [$id]);
    $n = count($temuan);
    $selesai = count(array_filter($temuan, static function ($t) { return $t['status'] === 'selesai'; }));
    $lewat = count(array_filter($temuan, $terlambat));
    $tinggi = count(array_filter($temuan, static function ($t) { return $t['risiko'] === 'tinggi' && $t['status'] !== 'selesai'; }));
    ?>
<?php
$dokumen = keu_dok_daftar($id);
$jendelaDok = static function (string $idTirai, string $judul, array $daftarDok, string $link, string $opHapus, int $temuanId = 0) use ($id): string {
    $warna = static function (string $ext): string {
        return $ext === 'pdf' ? 'merah' : (in_array($ext, ['xls', 'xlsx'], true) ? 'hijau' : (in_array($ext, ['doc', 'docx'], true) ? 'biru' : 'abu'));
    };
    $o = '<div class="tirai" id="' . e($idTirai) . '" hidden><div class="modal"><div class="modal-kepala"><h2>' . ikon('mata', 17) . ' ' . e($judul) . '</h2>'
       . '<button type="button" class="tutup" data-tutup aria-label="Tutup">' . ikon('tutup', 16) . '</button></div><div class="modal-isi">';
    if (!$daftarDok && $link === '') {
        $o .= '<p class="lemah kecil mb0">Belum ada dokumen atau link.</p>';
    }
    if ($daftarDok) {
        $o .= '<ul class="daftar-lihat-dok">';
        foreach ($daftarDok as $d) {
            $u = url_berkas('audit', $d['file']);
            $o .= '<li><div><b>' . e($d['judul']) . '</b><span>' . lencana(KEU_DOK_JENIS[$d['ext']] ?? strtoupper($d['ext']), $warna($d['ext'])) . ' '
                . angka($d['ukuran'] / 1048576, $d['ukuran'] < 1048576 ? 2 : 1) . ' MB &middot; ' . e(tanggal_pendek(date('Y-m-d', $d['waktu']))) . '</span></div>'
                . '<div class="aksi-baris"><a class="btn btn-garis btn-ikon" href="' . e($u) . '" target="_blank" rel="noopener" title="Lihat">' . ikon('mata', 15) . '</a>'
                . '<a class="btn btn-garis btn-ikon" href="' . e($u . '&unduh=1') . '" title="Unduh">' . ikon('unduh', 15) . '</a>'
                . '<form method="post" class="sebaris" data-konfirmasi="Hapus dokumen ' . e($d['judul']) . '?">' . csrf_field()
                . '<input type="hidden" name="op" value="' . e($opHapus) . '"><input type="hidden" name="id" value="' . $id . '">'
                . ($temuanId ? '<input type="hidden" name="temuan_id" value="' . $temuanId . '">' : '')
                . '<input type="hidden" name="berkas" value="' . e($d['file']) . '"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus">' . ikon('hapus', 15) . '</button></form></div></li>';
        }
        $o .= '</ul>';
    }
    if ($link !== '') {
        $o .= '<a class="btn btn-garis btn-kecil" href="' . e($link) . '" target="_blank" rel="noopener" style="margin-top:10px">' . ikon('tautan', 14) . ' Buka link drive</a>';
    }
    if (!$temuanId) {
        $o .= '<form method="post" enctype="multipart/form-data" style="margin-top:14px">' . csrf_field()
            . '<input type="hidden" name="op" value="unggah_dok"><input type="hidden" name="id" value="' . $id . '">'
            . '<label class="btn btn-utama btn-kecil"><input type="file" name="dokumen[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png" hidden data-kirim-otomatis>'
            . ikon('tambah', 14) . ' Unggah dokumen laporan</label> <small class="lemah" style="margin-left:8px">PDF, Word, Excel, JPG, PNG. Maks. 20 MB</small></form>';
    } else {
        $o .= '<p class="bantuan" style="margin-top:12px">Tambah dokumen atau ubah link lewat tombol <b>Edit</b> pada temuan ini.</p>';
    }
    return $o . '</div><div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Tutup</button></div></div></div>';
};
$jendela = [$jendelaDok('tiraiDokAudit', 'Dokumen laporan audit', $dokumen, keu_tautan('a' . $id), 'hapus_dok')];
?>
<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('audit', 18) ?> <?= e($aud['judul']) ?></h2>
      <p class="ket"><?= e($aud['auditor'] ?: 'Auditor belum dicatat') ?><?= $aud['nomor_laporan'] !== '' ? ' · No. ' . e($aud['nomor_laporan']) : '' ?><?= $aud['tanggal_laporan'] ? ' · ' . e(tanggal_pendek($aud['tanggal_laporan'])) : '' ?></p></div>
    <div class="aksi no-cetak">
      <a class="btn btn-garis btn-kecil" href="<?= e(url('audit', ['tahun' => $tahun])) ?>"><?= ikon('kembali', 14) ?> Kembali</a>
      <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiDokAudit"><?= ikon('mata', 14) ?> Dokumen laporan (<?= count($dokumen) ?>)</button>
      <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiAudit" data-judul="Ubah audit" data-isi='<?= e(json_encode($nilaiAudit($aud))) ?>'><?= ikon('sunting', 14) ?> Ubah</button>
      <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiTemuan" data-reset data-judul="Tambah temuan" data-isi='{"temuan_id":"","risiko":"sedang","status":"baru"}'><?= ikon('tambah', 14) ?> Tambah temuan</button>
    </div>
  </div>
  <?php if (!$temuan): ?>
    <?= sel_kosong('Belum ada temuan', 'Tambahkan temuan dan rekomendasi dari laporan audit.') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-audit">
    <thead><tr><th>Kode</th><th>Temuan dan rekomendasi</th><th>Risiko</th><th>Penanggung jawab</th><th>Tenggat</th><th>Status</th><th>Dokumen</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($temuan as $t): $r = KEU_RISIKO[$t['risiko']]; $s = KEU_STATUS_TEMUAN[$t['status']]; $dokT = keu_dok_daftar((int) $t['id'], 't'); $linkT = keu_tautan('t' . $t['id']); ?>
      <tr>
        <td class="nowrap"><b><?= e($t['kode']) ?></b></td>
        <td class="kiri"><?= e($t['uraian']) ?>
          <?php if ($t['rekomendasi']): ?><span class="sub">Rekomendasi: <?= e($t['rekomendasi']) ?></span><?php endif; ?>
          <?php if ($t['tindak_lanjut']): ?><span class="sub" style="color:var(--merek-700)">Tindak lanjut: <?= e($t['tindak_lanjut']) ?></span><?php endif; ?></td>
        <td><?= lencana($r['label'], $r['warna']) ?></td>
        <td><?= e($t['unit_nama'] ?? '-') ?></td>
        <td class="nowrap"<?= $terlambat($t) ? ' style="color:var(--merah);font-weight:700"' : '' ?>><?= $t['tenggat'] ? e(tanggal_pendek($t['tenggat'])) : '-' ?></td>
        <td><?= lencana($s['label'], $s['warna']) ?></td>
        <td class="sel-dok tengah"><?php $jendela[] = $jendelaDok('tiraiDokT' . (int) $t['id'], 'Dokumen temuan ' . $t['kode'], $dokT, $linkT, 'hapus_dok_temuan', (int) $t['id']); ?>
          <?= $dokT ? '<b>' . count($dokT) . '</b> dokumen' : '' ?><?= $dokT && $linkT !== '' ? ' &middot; ' : '' ?><?= $linkT !== '' ? 'link' : '' ?><?= !$dokT && $linkT === '' ? '<span class="lemah">-</span>' : '' ?>
        </td>
        <td class="no-cetak"><div class="aksi-baris" style="justify-content:center">
          <button class="btn btn-garis btn-ikon" type="button" data-buka="tiraiDokT<?= (int) $t['id'] ?>" title="Lihat dokumen" aria-label="Lihat dokumen temuan"><?= ikon('mata', 15) ?></button>
          <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiTemuan" data-judul="Ubah temuan"
                  data-isi='<?= e(json_encode(['temuan_id' => (int) $t['id'], 'kode' => $t['kode'], 'uraian' => $t['uraian'], 'risiko' => $t['risiko'], 'rekomendasi' => $t['rekomendasi'] ?? '', 'unit_id' => (string) ($t['unit_id'] ?? ''), 'tenggat' => $t['tenggat'] ?? '', 'status' => $t['status'], 'tindak_lanjut' => $t['tindak_lanjut'] ?? '', 'tautan_temuan' => $linkT])) ?>'><?= ikon('sunting', 14) ?> Edit</button>
          <form method="post" class="sebaris" data-konfirmasi="Hapus temuan ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus_temuan"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="temuan_id" value="<?= (int) $t['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <div class="kartu-kaki no-cetak">
    <form method="post" class="sebaris" data-konfirmasi="Hapus audit ini beserta seluruh temuannya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus_audit"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('hapus', 14) ?> Hapus audit</button></form>
  </div>
</div>

<?= implode('', $jendela) ?>

<div class="tirai" id="tiraiTemuan" hidden>
  <div class="modal">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan_temuan"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="temuan_id" value="">
      <div class="modal-kepala"><h2>Temuan audit</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="tKode">Kode temuan</label><input type="text" id="tKode" name="kode" maxlength="20" placeholder="Otomatis"></div>
          <div class="form-baris"><label class="fl" for="tRisiko">Tingkat risiko</label><select id="tRisiko" name="risiko"><?= pilihan(array_map(static function ($j) { return $j['label']; }, KEU_RISIKO), 'sedang') ?></select></div>
        </div>
        <div class="form-baris"><label class="fl" for="tUraian">Uraian temuan <span class="wajib">*</span></label><textarea id="tUraian" name="uraian" rows="3" maxlength="5000" required></textarea></div>
        <div class="form-baris"><label class="fl" for="tRek">Rekomendasi auditor</label><textarea id="tRek" name="rekomendasi" rows="2" maxlength="5000"></textarea></div>
        <div class="form-grid-3">
          <div class="form-baris"><label class="fl" for="tUnit">Penanggung jawab</label><select id="tUnit" name="unit_id"><?= opsi_unit(null, 'Belum ditentukan') ?></select></div>
          <div class="form-baris"><label class="fl" for="tTenggat">Tenggat</label><input type="date" id="tTenggat" name="tenggat"></div>
          <div class="form-baris"><label class="fl" for="tStatus">Status</label><select id="tStatus" name="status"><?= pilihan(array_map(static function ($j) { return $j['label']; }, KEU_STATUS_TEMUAN), 'baru') ?></select></div>
        </div>
        <div class="form-baris"><label class="fl" for="tTl">Tindak lanjut</label><textarea id="tTl" name="tindak_lanjut" rows="2" maxlength="5000" placeholder="Langkah yang sudah atau akan dilakukan"></textarea></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="tDok">Upload dokumen <small class="lemah">(opsional, bisa lebih dari satu)</small></label>
            <input type="file" id="tDok" name="dokumen_temuan[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
            <p class="bantuan">PDF, Word, Excel, JPG, atau PNG. Maksimal 20 MB per berkas. Dokumen lama tetap tersimpan.</p></div>
          <div class="form-baris"><label class="fl" for="tLink">Link drive <small class="lemah">(opsional)</small></label>
            <input type="url" id="tLink" name="tautan_temuan" maxlength="1000" placeholder="https://drive.google.com/…"></div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php
else:
    $daftar = rows('SELECT a.*, COUNT(t.id) AS n, SUM(t.status = \'selesai\') AS selesai,
                           SUM(t.status <> \'selesai\' AND t.tenggat < CURDATE()) AS lewat
                      FROM keu_audit a LEFT JOIN keu_temuan t ON t.audit_id = a.id
                     WHERE a.tahun = ? GROUP BY a.id ORDER BY a.tanggal_laporan DESC, a.id DESC', [$tahun]);
    $jmlTemuan = array_sum(array_column($daftar, 'n'));
    $jmlSelesai = array_sum(array_column($daftar, 'selesai'));
    $jmlLewat = array_sum(array_column($daftar, 'lewat'));
    $jmlDok = keu_dok_hitung();
    ?>
<div class="tab-kecil no-cetak">
  <?php foreach ($daftarTahun as $t): ?>
    <a href="<?= e(url('audit', ['tahun' => $t])) ?>" class="<?= $tahun === $t ? 'aktif' : '' ?>"><?= $t ?></a>
  <?php endforeach; ?>
</div>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('audit', 18) ?> Laporan audit independen <?= $tahun ?></h2><p class="ket"><?= count($daftar) ?> laporan</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiAudit" data-reset data-judul="Catat laporan audit" data-isi='<?= e(json_encode(['id' => '', 'tahun' => $tahun, 'status' => 'berjalan'])) ?>'><?= ikon('tambah', 15) ?> Catat audit</button></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong('Belum ada laporan audit tahun ' . $tahun) ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-audit">
    <thead><tr><th>No</th><th>Audit</th><th>Nomor laporan</th><th>Tanggal</th><th>Opini</th><th>Tindak lanjut</th><th>Status</th><th>Link drive</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $i => $a): $n = (int) $a['n']; $sl = (int) $a['selesai']; ?>
      <tr>
        <td><?= $i + 1 ?></td>
        <td class="kiri"><a href="<?= e(url('audit', ['aksi' => 'lihat', 'id' => $a['id']])) ?>"><b><?= e($a['judul']) ?></b></a><span class="sub"><?= e($a['auditor'] ?: '-') ?><?= !empty($jmlDok[(int) $a['id']]) ? ' &middot; ' . ikon('catatan', 12) . ' ' . (int) $jmlDok[(int) $a['id']] . ' dokumen' : '' ?></span></td>
        <td class="nowrap"><?= $a['nomor_laporan'] !== '' ? e($a['nomor_laporan']) : '-' ?></td>
        <td class="nowrap"><?= $a['tanggal_laporan'] ? e(tanggal_pendek($a['tanggal_laporan'])) : '-' ?></td>
        <td><?= $a['opini'] !== '' ? lencana(strtoupper($a['opini']), $a['opini'] === 'wtp' ? 'hijau' : ($a['opini'] === 'wdp' ? 'emas' : 'merah')) : '<span class="lemah">-</span>' ?></td>
        <td><?= $n ? $sl . '/' . $n . ' selesai' . ((int) $a['lewat'] ? ' <span class="sub" style="color:var(--merah)">' . (int) $a['lewat'] . ' lewat tenggat</span>' : '') : '<span class="lemah">belum ada temuan</span>' ?></td>
        <td><?= lencana($a['status'] === 'selesai' ? 'Selesai' : 'Berjalan', $a['status'] === 'selesai' ? 'hijau' : 'biru') ?></td>
        <td><?php $linkA = keu_tautan('a' . $a['id']); ?><?= $linkA !== '' ? '<a class="chip-dok chip-link" href="' . e($linkA) . '" target="_blank" rel="noopener">' . ikon('tautan', 12) . ' Buka</a>' : '<span class="lemah">-</span>' ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <a class="btn btn-garis btn-ikon" href="<?= e(url('audit', ['aksi' => 'lihat', 'id' => $a['id']])) ?>" title="Buka"><?= ikon('mata', 15) ?></a>
          <button class="btn btn-garis btn-ikon" type="button" title="Edit" data-buka="tiraiAudit" data-judul="Ubah audit" data-isi='<?= e(json_encode($nilaiAudit($a) + ['dari' => 'daftar'])) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" class="sebaris" data-konfirmasi="Hapus audit <?= e($a['judul']) ?> beserta seluruh temuan dan dokumennya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus_audit"><input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="tirai" id="tiraiAudit" hidden>
  <div class="modal sempit">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan_audit"><input type="hidden" name="id" value=""><input type="hidden" name="dari" value="">
      <div class="modal-kepala"><h2>Laporan audit</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="aJudul">Judul audit <span class="wajib">*</span></label><input type="text" id="aJudul" name="judul" maxlength="200" required placeholder="Contoh: Audit laporan keuangan tahun buku 2025"></div>
        <div class="form-baris"><label class="fl" for="aAuditor">Auditor / kantor akuntan publik</label><input type="text" id="aAuditor" name="auditor" maxlength="150"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="aNomor">Nomor laporan</label><input type="text" id="aNomor" name="nomor_laporan" maxlength="80"></div>
          <div class="form-baris"><label class="fl" for="aTgl">Tanggal laporan</label><input type="date" id="aTgl" name="tanggal_laporan"></div>
          <div class="form-baris"><label class="fl" for="aTahun">Tahun</label><input type="number" id="aTahun" name="tahun" min="2000" max="2100" value="<?= $tahun ?>"></div>
          <div class="form-baris"><label class="fl" for="aStatus">Status</label><select id="aStatus" name="status"><option value="berjalan">Berjalan</option><option value="selesai">Selesai</option></select></div>
        </div>
        <div class="form-baris"><label class="fl" for="aOpini">Opini auditor</label><select id="aOpini" name="opini"><?= pilihan(KEU_OPINI, '') ?></select></div>
        <div class="form-baris"><label class="fl" for="aLink">Link drive <small class="lemah">(opsional)</small></label><input type="url" id="aLink" name="tautan" maxlength="1000" placeholder="https://drive.google.com/…"></div>
        <div class="form-baris"><label class="fl" for="aDok">Unggah dokumen <small class="lemah">(opsional, bisa lebih dari satu)</small></label>
          <input type="file" id="aDok" name="dokumen[]" multiple accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png">
          <p class="bantuan">PDF, Word, Excel, JPG, atau PNG. Maksimal 20 MB per berkas.</p></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
