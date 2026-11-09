<?php
$saya = uid();
$aksi = $_GET['aksi'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$tab = ($_GET['tab'] ?? '') === 'rtl' ? 'rtl' : 'daftar';
$bolehBuat = can('rapat_kelola');

$kePage = static function (int $rid, array $tambah = []): void {
    kembali_ke('rapat', array_merge(['aksi' => 'lihat', 'id' => $rid], $tambah));
};

$daftarPegawai = static function (): array {
    return rows(
        'SELECT p.id, p.nama, p.jabatan, un.id AS unit_id, un.nama AS unit_nama
           FROM pengguna p LEFT JOIN unit un ON un.id = p.unit_id
          WHERE p.aktif = 1 ORDER BY un.id IS NULL, un.urut, un.nama, p.nama'
    );
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $rid = (int) post('rapat_id');
    $r = $rid ? rapat_get($rid) : null;

    if ($op === 'simpan') {
        $rid = (int) post('id');
        $lama = $rid ? rapat_get($rid) : null;
        if (($rid && (!$lama || !rapat_boleh_ubah($lama))) || (!$rid && !$bolehBuat)) {
            tolak_akses();
        }
        $tgl = post('tanggal');
        $mulai = normalisasi_jam(post('jam_mulai'));
        $selesai = normalisasi_jam(post('jam_selesai'));
        $data = [
            'judul'            => mb_substr(post('judul'), 0, 255),
            'jenis'            => array_key_exists(post('jenis'), JENIS_RAPAT) ? post('jenis') : 'antar_bidang',
            'nomor'            => mb_substr(post('nomor'), 0, 100),
            'tanggal'          => $tgl,
            'jam_mulai'        => $mulai !== '' ? $mulai : null,
            'jam_selesai'      => $selesai !== '' ? $selesai : null,
            'tempat'           => mb_substr(post('tempat'), 0, 200),
            'pimpinan_nama'    => mb_substr(post('pimpinan_nama'), 0, 150),
            'pimpinan_jabatan' => mb_substr(post('pimpinan_jabatan'), 0, 150),
            'notulis_id'       => (int) post('notulis_id') ?: null,
            'agenda'           => mb_substr(post_teks('agenda'), 0, 5000),
        ];
        $galat = [];
        if ($data['judul'] === '') {
            $galat[] = 'Judul atau nama rapat wajib diisi.';
        }
        if (!tanggal_sah($tgl)) {
            $galat[] = 'Tanggal rapat tidak sah.';
        }
        if (($mulai !== '' && !jam_sah($mulai)) || ($selesai !== '' && !jam_sah($selesai))) {
            $galat[] = 'Format jam tidak sah.';
        } elseif ($mulai !== '' && $selesai !== '' && $selesai <= $mulai) {
            $galat[] = 'Jam selesai harus setelah jam mulai.';
        }
        if ($galat) {
            $_SESSION['isian_rapat'] = $_POST;
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('rapat', $rid ? ['aksi' => 'ubah', 'id' => $rid] : ['aksi' => 'baru']);
        }
        if ($rid) {
            $data['diubah'] = date('Y-m-d H:i:s');
            update('rapat', $data, 'id = ?', [$rid]);
            $dipilihBaru = array_map('intval', (array) ($_POST['peserta'] ?? []));
            $ditambah = rapat_tambah_peserta_internal($rid, $dipilihBaru);
            $dibuang = q(
                "DELETE FROM rapat_peserta WHERE rapat_id = ? AND eksternal = 0 AND kehadiran = 'belum' AND pengguna_id IS NOT NULL"
                . ($dipilihBaru ? ' AND pengguna_id NOT IN (' . in_placeholder($dipilihBaru) . ')' : ''),
                array_merge([$rid], $dipilihBaru)
            )->rowCount();
            catat_log('ubah_rapat', 'rapat', $rid, $data['judul']);
            flash('Informasi rapat diperbarui. Undangan: ' . $ditambah . ' ditambahkan, ' . $dibuang . ' dihapus.'
                . ' (Peserta yang sudah diabsen tidak ikut terhapus.)');
        } else {
            $rid = insert('rapat', $data + ['periode_id' => pid() ?: null, 'dibuat_oleh' => $saya]);
            $n = rapat_tambah_peserta_internal($rid, (array) ($_POST['peserta'] ?? []));
            catat_log('tambah_rapat', 'rapat', $rid, $data['judul']);
            flash('Rapat <b>' . e($data['judul']) . '</b> dibuat dengan ' . $n . ' peserta. Lanjutkan mengisi absensi, notulen, RTL, dan dokumentasi di bawah.');
        }
        $kePage($rid);
    }

    if (!$r || !rapat_boleh_lihat($r)) {
        tolak_akses();
    }
    $bolehUbah = rapat_boleh_ubah($r);

    if ($op === 'hadir_saya') {
        if ($r['status'] === 'final' || !rapat_ikut_serta($rid, $saya)) {
            flash('Absensi tidak dapat diisi untuk rapat ini.', 'galat');
        } else {
            q("UPDATE rapat_peserta SET kehadiran = 'hadir', waktu_hadir = NOW() WHERE rapat_id = ? AND pengguna_id = ? AND kehadiran <> 'hadir'", [$rid, $saya]);
            catat_log('absen_rapat', 'rapat', $rid, '');
            flash('Kehadiran Anda tercatat.');
        }
    } elseif (!$bolehUbah && !in_array($op, ['rtl_simpan', 'rtl_status'], true)) {
        tolak_akses();
    } elseif ($op === 'peserta_tambah') {
        $n = rapat_tambah_peserta_internal($rid, (array) ($_POST['tambah'] ?? []));
        $namaLuar = mb_substr(post('nama_luar'), 0, 150);
        if ($namaLuar !== '') {
            q('INSERT INTO rapat_peserta (rapat_id, pengguna_id, nama, jabatan, unit_nama, eksternal) VALUES (?, NULL, ?, ?, ?, 1)',
              [$rid, $namaLuar, mb_substr(post('jabatan_luar'), 0, 150), mb_substr(post('instansi_luar'), 0, 150)]);
            $n++;
        }
        flash($n > 0 ? $n . ' peserta ditambahkan.' : 'Tidak ada peserta baru yang dipilih.', $n > 0 ? 'ok' : 'warn');
    } elseif ($op === 'absensi') {
        $hapus = (array) ($_POST['hapus'] ?? []);
        foreach (rapat_peserta($rid) as $p) {
            $pid = (int) $p['id'];
            if (isset($hapus[$pid])) {
                q('DELETE FROM rapat_peserta WHERE id = ? AND rapat_id = ?', [$pid, $rid]);
                continue;
            }
            $st = (string) ($_POST['kehadiran'][$pid] ?? 'belum');
            $st = array_key_exists($st, KEHADIRAN_RAPAT) ? $st : 'belum';
            $ket = mb_substr(trim((string) ($_POST['ket'][$pid] ?? '')), 0, 255);
            $waktu = $st === 'hadir' ? ($p['waktu_hadir'] ?: date('Y-m-d H:i:s')) : null;
            q('UPDATE rapat_peserta SET kehadiran = ?, waktu_hadir = ?, keterangan = ? WHERE id = ?', [$st, $waktu, $ket, $pid]);
        }
        catat_log('absensi_rapat', 'rapat', $rid, '');
        flash('Absensi tersimpan.');
    } elseif ($op === 'notulen') {
        update('rapat', [
            'pembahasan' => mb_substr(post_teks('pembahasan'), 0, 60000),
            'keputusan'  => mb_substr(post_teks('keputusan'), 0, 10000),
            'diubah'     => date('Y-m-d H:i:s'),
        ], 'id = ?', [$rid]);
        catat_log('notulen_rapat', 'rapat', $rid, '');
        flash('Notulen tersimpan.');
    } elseif ($op === 'rtl_simpan') {
        $tid = (int) post('id');
        $lamaRtl = $tid ? row('SELECT * FROM rapat_rtl WHERE id = ? AND rapat_id = ?', [$tid, $rid]) : null;
        if (($tid && (!$lamaRtl || !rapat_boleh_ubah_rtl($lamaRtl, $r))) || (!$tid && !$bolehUbah)) {
            tolak_akses();
        }
        $tenggat = post('tenggat');
        $dataRtl = [
            'unit_id'          => (int) post('unit_id') ?: null,
            'kendala'          => mb_substr(post_teks('kendala'), 0, 3000),
            'rtl'              => mb_substr(post_teks('rtl'), 0, 3000),
            'penanggung_jawab' => mb_substr(post('penanggung_jawab'), 0, 150),
            'tenggat'          => tanggal_sah($tenggat) ? $tenggat : null,
            'status'           => array_key_exists(post('status'), STATUS_RTL) ? post('status') : 'baru',
            'hasil'            => mb_substr(post('hasil'), 0, 500),
        ];
        if (trim($dataRtl['rtl']) === '') {
            flash('Rencana tindak lanjut wajib diisi.', 'galat');
        } elseif ($tid) {
            if (!$bolehUbah) {
                $dataRtl = array_intersect_key($dataRtl, array_flip(['status', 'hasil']));
            }
            $dataRtl['diubah'] = date('Y-m-d H:i:s');
            update('rapat_rtl', $dataRtl, 'id = ?', [$tid]);
            catat_log('ubah_rtl', 'rapat', $rid, '');
            flash('RTL diperbarui.');
        } else {
            insert('rapat_rtl', $dataRtl + ['rapat_id' => $rid]);
            catat_log('tambah_rtl', 'rapat', $rid, '');
            flash('RTL ditambahkan.');
        }
    } elseif ($op === 'rtl_hapus') {
        q('DELETE FROM rapat_rtl WHERE id = ? AND rapat_id = ?', [(int) post('id'), $rid]);
        flash('RTL dihapus.');
    } elseif ($op === 'dok_unggah') {
        $ket = mb_substr(post('keterangan'), 0, 255);
        $ok = 0;
        $galat = [];
        foreach (rapat_normalisasi_berkas($_FILES['dokumentasi'] ?? []) as $berkas) {
            $hasil = proses_dokumentasi_unggah($berkas, $rid);
            if (isset($hasil['abaikan'])) {
                continue;
            }
            if (isset($hasil['galat'])) {
                $galat[] = $hasil['galat'];
                continue;
            }
            insert('rapat_dokumentasi', ['rapat_id' => $rid, 'berkas' => $hasil['file'], 'keterangan' => $ket, 'diunggah_oleh' => $saya]);
            $ok++;
        }
        if ($ok > 0) {
            catat_log('dokumentasi_rapat', 'rapat', $rid, $ok . ' foto');
            flash($ok . ' foto dokumentasi diunggah.');
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', array_unique($galat))), 'galat');
        } elseif ($ok === 0) {
            flash('Pilih foto dokumentasi terlebih dahulu.', 'warn');
        }
    } elseif ($op === 'dok_hapus') {
        $dok = row('SELECT * FROM rapat_dokumentasi WHERE id = ? AND rapat_id = ?', [(int) post('id'), $rid]);
        if ($dok) {
            hapus_berkas_dokumentasi($dok['berkas']);
            q('DELETE FROM rapat_dokumentasi WHERE id = ?', [$dok['id']]);
            flash('Foto dokumentasi dihapus.');
        }
    } elseif ($op === 'final') {
        update('rapat', ['status' => 'final', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$rid]);
        catat_log('final_rapat', 'rapat', $rid, '');
        flash('Berita acara difinalkan. Hanya pengelola berita acara yang dapat mengubahnya lagi.');
    } elseif ($op === 'buka') {
        if (!can('rapat_kelola')) {
            tolak_akses();
        }
        update('rapat', ['status' => 'draft'], 'id = ?', [$rid]);
        flash('Berita acara dibuka kembali sebagai draft.', 'info');
    } elseif ($op === 'hapus') {
        if (!can('rapat_kelola') && !((int) $r['dibuat_oleh'] === $saya && $r['status'] === 'draft')) {
            tolak_akses();
        }
        foreach (rapat_dokumentasi_daftar($rid) as $dok) {
            hapus_berkas_dokumentasi($dok['berkas']);
        }
        q('DELETE FROM rapat WHERE id = ?', [$rid]);
        catat_log('hapus_rapat', 'rapat', $rid, $r['judul']);
        flash('Berita acara dihapus.');
        kembali_ke('rapat');
    }
    $kePage($rid);
}

if ($aksi === 'cetak') {
    $r = rapat_get($id);
    if (!$r || !rapat_boleh_lihat($r)) {
        tolak_akses();
    }
    $peserta = rapat_peserta($id);
    $rtlList = rapat_rtl_daftar($id);
    $dokList = rapat_dokumentasi_daftar($id);
    $subjudul = 'Cetak berita acara';
    $hadir = count(array_filter($peserta, static function ($p) { return $p['kehadiran'] === 'hadir'; }));
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px">
      <a class="btn btn-garis" href="<?= e(url('rapat', ['aksi' => 'lihat', 'id' => $id])) ?>"><?= ikon('kembali', 15) ?> Kembali</a>
      <button class="btn btn-utama" type="button" data-cetak><?= ikon('cetak', 15) ?> Cetak / simpan PDF</button>
    </div>

    <article class="berita-acara">
      <header class="kop-surat">
        <img class="kop-logo" src="<?= e(base_url('assets/img/kop-logo.png')) ?>" alt="Logo UIS">
        <div class="kop-teks">
          <div class="kop-yayasan">YAYASAN PENDIDIKAN IBNU SINA BATAM (YAPISTA)</div>
          <div class="kop-univ">UNIVERSITAS IBNU SINA (UIS)</div>
          <div class="kop-alamat">Jalan Teuku Umar, Lubuk Baja, Kota Batam-Indonesia Telp. 0778 &ndash; 408 3113</div>
          <div class="kop-alamat">email : info@uis.ac.id / uibnusina@gmail.com &nbsp; website : uis.ac.id</div>
        </div>
      </header>
      <div class="kop-garis"></div>

      <div class="ba-isi">
      <div class="ba-judul">
        <h1>BERITA ACARA RAPAT</h1>
        <div class="ba-nama"><?= e(mb_strtoupper($r['judul'])) ?></div>
        <?php if ($r['nomor'] !== ''): ?><div>Nomor: <?= e($r['nomor']) ?></div><?php endif; ?>
      </div>

      <table class="ba-info">
        <tr><td>Hari / Tanggal</td><td>:</td><td><?= e(tanggal_id($r['tanggal'], true)) ?></td></tr>
        <tr><td>Waktu</td><td>:</td><td><?= $r['jam_mulai'] ? e(jam_pendek($r['jam_mulai'])) . ($r['jam_selesai'] ? ' s.d. ' . e(jam_pendek($r['jam_selesai'])) : ' s.d. selesai') . ' WIB' : '-' ?></td></tr>
        <tr><td>Tempat</td><td>:</td><td><?= e($r['tempat'] ?: '-') ?></td></tr>
        <tr><td>Jenis rapat</td><td>:</td><td><?= e(JENIS_RAPAT[$r['jenis']]) ?></td></tr>
        <tr><td>Agenda</td><td>:</td><td><?= $r['agenda'] ? nl2br(e($r['agenda'])) : '-' ?></td></tr>
        <tr><td>Pimpinan rapat</td><td>:</td><td><?= e($r['pimpinan_nama'] ?: '-') ?><?= $r['pimpinan_jabatan'] ? ', ' . e($r['pimpinan_jabatan']) : '' ?></td></tr>
        <tr><td>Notulis</td><td>:</td><td><?= e($r['notulis_nama'] ?: '-') ?></td></tr>
        <tr><td>Undangan</td><td>:</td><td><?= e(implode(', ', array_unique(array_filter(array_column($peserta, 'unit_nama')))) ?: '-') ?></td></tr>
        <tr><td>Jumlah peserta</td><td>:</td><td><?= count($peserta) ?> orang (hadir <?= $hadir ?>)</td></tr>
      </table>

      <h2 class="ba-bagian">I. Daftar Hadir</h2>
      <table class="ba-tabel">
        <thead><tr><th style="width:34px">No</th><th>Nama</th><th>Jabatan / Unit</th><th style="width:88px">Kehadiran</th><th style="width:130px">Tanda Tangan</th></tr></thead>
        <tbody>
        <?php if (!$peserta): ?><tr><td colspan="5" style="text-align:center">Belum ada peserta.</td></tr><?php endif; ?>
        <?php foreach ($peserta as $i => $p): ?>
          <tr><td style="text-align:center"><?= $i + 1 ?></td><td><?= e($p['nama']) ?></td>
            <td><?= e(trim($p['jabatan'] . ($p['unit_nama'] ? ' / ' . $p['unit_nama'] : ''), ' /')) ?></td>
            <td><?= e(KEHADIRAN_RAPAT[$p['kehadiran']]['label']) ?></td>
            <td style="height:30px"></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <h2 class="ba-bagian">II. Pembahasan / Notulen Rapat</h2>
      <div class="ba-teks"><?= $r['pembahasan'] ? nl2br(e($r['pembahasan'])) : '-' ?></div>

      <h2 class="ba-bagian">III. Hasil dan Keputusan Rapat</h2>
      <div class="ba-teks"><?= $r['keputusan'] ? nl2br(e($r['keputusan'])) : '-' ?></div>

      <h2 class="ba-bagian">IV. Kendala dan Rencana Tindak Lanjut (RTL)</h2>
      <table class="ba-tabel">
        <thead><tr><th style="width:34px">No</th><th style="width:120px">Bidang</th><th>Kendala</th><th>Rencana Tindak Lanjut</th><th style="width:110px">Penanggung Jawab</th><th style="width:78px">Tenggat</th><th style="width:70px">Status</th></tr></thead>
        <tbody>
        <?php if (!$rtlList): ?><tr><td colspan="7" style="text-align:center">Tidak ada RTL.</td></tr><?php endif; ?>
        <?php foreach ($rtlList as $i => $t): ?>
          <tr><td style="text-align:center"><?= $i + 1 ?></td><td><?= e($t['unit_nama'] ?: '-') ?></td>
            <td><?= $t['kendala'] ? nl2br(e($t['kendala'])) : '-' ?></td><td><?= nl2br(e($t['rtl'])) ?></td>
            <td><?= e($t['penanggung_jawab'] ?: '-') ?></td><td><?= $t['tenggat'] ? e(tanggal_pendek($t['tenggat'])) : '-' ?></td>
            <td><?= e(STATUS_RTL[$t['status']]['label']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>

      <?php if ($dokList): ?>
        <h2 class="ba-bagian">V. Dokumentasi</h2>
        <div class="ba-dok">
          <?php foreach ($dokList as $d): ?>
            <figure><img src="<?= e(url_berkas('rapat', $d['berkas'])) ?>" alt="Dokumentasi rapat">
              <?php if ($d['keterangan']): ?><figcaption><?= e($d['keterangan']) ?></figcaption><?php endif; ?></figure>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <div class="ba-ttd">
        <div><div>&nbsp;</div><div>Pimpinan Rapat,</div><div class="ruang"></div><b><?= e($r['pimpinan_nama'] ?: '(................................)') ?></b><?php if ($r['pimpinan_jabatan']): ?><div><?= e($r['pimpinan_jabatan']) ?></div><?php endif; ?></div>
        <div><div><?= e(APP_KOTA) ?>, <?= e(tanggal_id($r['tanggal'])) ?></div><div>Notulis,</div><div class="ruang"></div><b><?= e($r['notulis_nama'] ?: '(................................)') ?></b></div>
      </div>
      </div>
    </article>
    <?php if (($_GET['langsung'] ?? '') === '1'): ?>
      <script nonce="<?= csp_nonce() ?>">window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 600); });</script>
    <?php endif; ?>
    <?php
    return;
}

if ($aksi === 'baru' || $aksi === 'ubah') {
    $r = $aksi === 'ubah' ? rapat_get($id) : null;
    if ($aksi === 'baru' && !$bolehBuat) {
        tolak_akses();
    }
    if ($aksi === 'ubah' && (!$r || !rapat_boleh_ubah($r))) {
        tolak_akses();
    }
    $isian = $_SESSION['isian_rapat'] ?? null;
    unset($_SESSION['isian_rapat']);
    $v = static function (string $f, $bawaan = '') use ($isian, $r) {
        if ($isian !== null && array_key_exists($f, $isian)) {
            return $isian[$f];
        }
        return $r[$f] ?? $bawaan;
    };
    $subjudul = $r ? 'Ubah informasi rapat' : 'Rapat baru';
    $pegawai = $daftarPegawai();
    $dipilih = array_map('intval', (array) ($isian['peserta'] ?? ($r
        ? array_column(array_filter(rapat_peserta((int) $r['id']), static function ($p) { return $p['pengguna_id'] !== null; }), 'pengguna_id')
        : [])));
    $kelompok = [];
    foreach ($pegawai as $p) {
        $kelompok['u' . (int) ($p['unit_id'] ?? 0)][] = $p;
    }
    ?>
    <form method="post" class="kartu">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="<?= (int) ($r['id'] ?? 0) ?>">
      <div class="kartu-kepala">
        <div><h2><?= ikon('catatan', 18) ?> <?= $r ? 'Ubah informasi rapat' : 'Buat berita acara rapat' ?></h2>
          </div>
        <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e($r ? url('rapat', ['aksi' => 'lihat', 'id' => $r['id']]) : url('rapat')) ?>"><?= ikon('kembali', 15) ?> Batal</a></div>
      </div>
      <div class="kartu-isi">
        <div class="form-baris"><label class="fl" for="rJudul">Nama / judul rapat <span class="wajib">*</span></label>
          <input type="text" id="rJudul" name="judul" maxlength="255" required value="<?= e($v('judul')) ?>" placeholder="Contoh: Rapat Koordinasi Internal BAUK bulan Oktober"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="rJenis">Jenis rapat</label>
            <select id="rJenis" name="jenis"><?= pilihan(JENIS_RAPAT, $v('jenis', 'antar_bidang')) ?></select></div>
          <div class="form-baris"><label class="fl" for="rNomor">Nomor berita acara</label>
            <input type="text" id="rNomor" name="nomor" maxlength="100" value="<?= e($v('nomor')) ?>" placeholder="Bila ada"></div>
        </div>
        <div class="form-grid-3">
          <div class="form-baris"><label class="fl" for="rTgl">Tanggal <span class="wajib">*</span></label>
            <input type="date" id="rTgl" name="tanggal" required value="<?= e($v('tanggal', date('Y-m-d'))) ?>"></div>
          <div class="form-baris"><label class="fl" for="rMulai">Jam mulai</label>
            <input type="text" id="rMulai" name="jam_mulai" data-jam inputmode="numeric" maxlength="5" placeholder="09.00" autocomplete="off" value="<?= e(jam_isian($v('jam_mulai'))) ?>"></div>
          <div class="form-baris"><label class="fl" for="rSelesai">Jam selesai</label>
            <input type="text" id="rSelesai" name="jam_selesai" data-jam inputmode="numeric" maxlength="5" placeholder="11.00" autocomplete="off" value="<?= e(jam_isian($v('jam_selesai'))) ?>"></div>
        </div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="rTempat">Tempat</label>
            <input type="text" id="rTempat" name="tempat" maxlength="200" value="<?= e($v('tempat')) ?>" placeholder="Contoh: Ruang Rapat Rektorat"></div>
          <div class="form-baris"><label class="fl" for="rPimpinan">Pimpinan rapat</label>
            <input type="text" id="rPimpinan" name="pimpinan_nama" maxlength="150" value="<?= e($v('pimpinan_nama')) ?>" placeholder="Nama pimpinan rapat"></div>
          <div class="form-baris"><label class="fl" for="rPimpinanJab">Jabatan pimpinan rapat</label>
            <input type="text" id="rPimpinanJab" name="pimpinan_jabatan" maxlength="150" value="<?= e($v('pimpinan_jabatan')) ?>" placeholder="Contoh: Kepala Biro Administrasi Umum dan Keuangan"></div>
          <div class="form-baris penuh"><label class="fl" for="rNotulis">Notulis</label>
            <select id="rNotulis" name="notulis_id"><option value="">Pilih notulis</option>
              <?php foreach ($pegawai as $p): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $v('notulis_id', $saya) === (int) $p['id'] ? 'selected' : '' ?>><?= e($p['nama']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-baris"><label class="fl" for="rAgenda">Agenda rapat</label>
          <textarea id="rAgenda" name="agenda" rows="4" maxlength="5000" placeholder="Pokok-pokok yang akan dibahas"><?= e($v('agenda')) ?></textarea></div>

        <div class="form-baris mb0" id="undangan">
          <label class="fl">Undangan rapat: pilih siapa saja yang boleh ikut
            <span class="lemah" style="font-weight:500">(<b id="jmlUndangan">0</b> terpilih)</span></label>
          <div class="chip-bidang">
            <?php foreach ($kelompok as $kunciU => $anggota): ?>
              <label class="chip-unit"><input type="checkbox" data-pilih-unit="<?= e($kunciU) ?>">
                <span><?= e($anggota[0]['unit_nama'] ?? 'Tanpa unit') ?> <small><?= count($anggota) ?></small></span></label>
            <?php endforeach; ?>
            <button type="button" class="tautan-kecil" id="undanganSemua">Pilih semua</button>
            <button type="button" class="tautan-kecil" id="undanganKosong">Kosongkan</button>
          </div>
          <details class="rincian">
            <summary>Pilih orang tertentu</summary>
            <input type="search" id="cariUndangan" placeholder="Cari nama pegawai" autocomplete="off" style="margin-bottom:8px">
          <div class="pilih-peserta">
            <?php foreach ($kelompok as $kunciU => $anggota): ?>
              <div class="peserta-grup">
                <div class="peserta-unit"><b><?= e($anggota[0]['unit_nama'] ?? 'Tanpa unit') ?></b></div>
                <?php foreach ($anggota as $p): ?>
                  <label class="cek peserta-item" data-cari="<?= e(mb_strtolower($p['nama'] . ' ' . $p['jabatan'])) ?>"><input type="checkbox" name="peserta[]" value="<?= (int) $p['id'] ?>" data-unit="<?= e($kunciU) ?>" <?= in_array((int) $p['id'], $dipilih, true) ? 'checked' : '' ?>>
                    <span><?= e($p['nama']) ?><small><?= e($p['jabatan'] ?: '') ?></small></span></label>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          </details>
        </div>
        <script nonce="<?= csp_nonce() ?>">
          (function () {
            var wadah = document.getElementById('undangan');
            if (!wadah) return;
            var kotak = wadah.querySelectorAll('input[name="peserta[]"]');
            var grup = wadah.querySelectorAll('[data-pilih-unit]');
            var jml = document.getElementById('jmlUndangan');
            function segarkan() {
              var n = 0;
              Array.prototype.forEach.call(kotak, function (c) { if (c.checked) n++; });
              jml.textContent = n;
              Array.prototype.forEach.call(grup, function (g) {
                var anak = wadah.querySelectorAll('input[data-unit="' + g.getAttribute('data-pilih-unit') + '"]'), sudah = 0;
                Array.prototype.forEach.call(anak, function (a) { if (a.checked) sudah++; });
                g.checked = anak.length > 0 && sudah === anak.length;
                g.indeterminate = sudah > 0 && sudah < anak.length;
              });
            }
            function setSemua(nilai) {
              Array.prototype.forEach.call(kotak, function (c) { c.checked = nilai; });
              segarkan();
            }
            wadah.addEventListener('change', segarkan);
            document.getElementById('undanganSemua').addEventListener('click', function () { setSemua(true); });
            document.getElementById('undanganKosong').addEventListener('click', function () { setSemua(false); });
            document.getElementById('cariUndangan').addEventListener('input', function () {
              var kata = this.value.trim().toLowerCase();
              Array.prototype.forEach.call(wadah.querySelectorAll('.peserta-grup'), function (g) {
                var ada = 0;
                Array.prototype.forEach.call(g.querySelectorAll('.peserta-item'), function (it) {
                  var cocok = kata === '' || (it.getAttribute('data-cari') || '').indexOf(kata) !== -1;
                  it.hidden = !cocok;
                  if (cocok) ada++;
                });
                g.hidden = ada === 0;
              });
            });
            segarkan();
          })();
        </script>
      </div>
      <div class="kartu-kaki"><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> <?= $r ? 'Simpan perubahan' : 'Simpan dan lanjutkan' ?></button></div>
    </form>
    <?php
    return;
}

if ($aksi === 'lihat') {
    $r = rapat_get($id);
    if (!$r || !rapat_boleh_lihat($r)) {
        tolak_akses();
    }
    rapat_tandai_dilihat($saya, $id);
    $bolehUbah = rapat_boleh_ubah($r);
    $peserta = rapat_peserta($id);
    $rtlList = rapat_rtl_daftar($id);
    $dokList = rapat_dokumentasi_daftar($id);
    $hadir = count(array_filter($peserta, static function ($p) { return $p['kehadiran'] === 'hadir'; }));
    $saatIni = null;
    foreach ($peserta as $p) {
        if ((int) $p['pengguna_id'] === $saya) {
            $saatIni = $p;
        }
    }
    $subjudul = $r['judul'];
    $sudahAda = array_map('intval', array_column($peserta, 'pengguna_id'));
    $calonTambah = array_values(array_filter($daftarPegawai(), static function ($p) use ($sudahAda) { return !in_array((int) $p['id'], $sudahAda, true); }));
    ?>
    <div class="kartu">
      <div class="kartu-kepala">
        <div>
          <h2><?= ikon('grup', 18) ?> <?= e($r['judul']) ?></h2>
          <p class="ket"><?= lencana(JENIS_RAPAT[$r['jenis']], 'ungu') ?> <?= $r['status'] === 'final' ? lencana('Final', 'hijau') : lencana('Draft', 'abu') ?>
            <?= $r['nomor'] !== '' ? ' · No. ' . e($r['nomor']) : '' ?></p>
        </div>
        <div class="aksi">
          <a class="btn btn-garis btn-kecil" href="<?= e(url('rapat')) ?>"><?= ikon('kembali', 15) ?> Daftar</a>
          <a class="btn btn-utama btn-kecil" href="<?= e(url('rapat', ['aksi' => 'cetak', 'id' => $id])) ?>"><?= ikon('cetak', 15) ?> Cetak berita acara</a>
          <?php if ($bolehUbah): ?>
            <a class="btn btn-garis btn-kecil" href="<?= e(url('rapat', ['aksi' => 'ubah', 'id' => $id])) ?>"><?= ikon('sunting', 15) ?> Ubah info</a>
            <?php if ($r['status'] === 'draft'): ?>
              <form method="post" data-konfirmasi="Finalkan berita acara ini? Setelah final, hanya pengelola berita acara yang dapat mengubahnya." data-gaya="utama">
                <?= csrf_field() ?><input type="hidden" name="op" value="final"><input type="hidden" name="rapat_id" value="<?= $id ?>">
                <button class="btn btn-sukses btn-kecil" type="submit"><?= ikon('centang', 15) ?> Finalkan</button></form>
            <?php elseif (can('rapat_kelola')): ?>
              <form method="post" data-konfirmasi="Buka kembali berita acara ini sebagai draft?" data-gaya="utama">
                <?= csrf_field() ?><input type="hidden" name="op" value="buka"><input type="hidden" name="rapat_id" value="<?= $id ?>">
                <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('ulang', 15) ?> Buka kembali</button></form>
            <?php endif; ?>
          <?php endif; ?>
          <?php if (can('rapat_kelola') || ((int) $r['dibuat_oleh'] === $saya && $r['status'] === 'draft')): ?>
            <form method="post" data-konfirmasi="Hapus berita acara ini beserta absensi, RTL, dan dokumentasinya?">
              <?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="rapat_id" value="<?= $id ?>">
              <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus berita acara" aria-label="Hapus berita acara"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
        </div>
      </div>
      <div class="kartu-isi">
        <div class="grid grid-2">
          <dl class="rinci">
            <dt>Hari / tanggal</dt><dd><?= e(tanggal_id($r['tanggal'], true)) ?></dd>
            <dt>Waktu</dt><dd><?= $r['jam_mulai'] ? e(jam_pendek($r['jam_mulai'])) . ($r['jam_selesai'] ? ' - ' . e(jam_pendek($r['jam_selesai'])) : '') . ' WIB' : '-' ?></dd>
            <dt>Tempat</dt><dd><?= e($r['tempat'] ?: '-') ?></dd>
            <dt>Undangan</dt><dd><?= e(implode(', ', array_unique(array_filter(array_column($peserta, 'unit_nama')))) ?: '-') ?></dd>
          </dl>
          <dl class="rinci">
            <dt>Pimpinan rapat</dt><dd><?= e($r['pimpinan_nama'] ?: '-') ?><?= $r['pimpinan_jabatan'] ? ' <span class="lemah">· ' . e($r['pimpinan_jabatan']) . '</span>' : '' ?></dd>
            <dt>Notulis</dt><dd><?= e($r['notulis_nama'] ?: '-') ?></dd>
            <dt>Dibuat oleh</dt><dd><?= e($r['pembuat_nama'] ?: '-') ?></dd>
            <dt>Kehadiran</dt><dd><?= $hadir ?> hadir dari <?= count($peserta) ?> peserta</dd>
          </dl>
        </div>
        <?php if ($r['agenda']): ?><div class="mt16"><div class="kecil lemah" style="margin-bottom:4px">Agenda</div><div style="white-space:pre-line"><?= e($r['agenda']) ?></div></div><?php endif; ?>
      </div>
    </div>

    <div class="kartu">
      <div class="kartu-kepala">
        <div><h2><?= ikon('evaluasi', 18) ?> Absensi peserta</h2><p class="ket"><?= $hadir ?> hadir dari <?= count($peserta) ?> peserta</p></div>
        <div class="aksi">
          <?php if ($saatIni && $r['status'] !== 'final' && $saatIni['kehadiran'] !== 'hadir'): ?>
            <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="hadir_saya"><input type="hidden" name="rapat_id" value="<?= $id ?>">
              <button class="btn btn-sukses btn-kecil" type="submit"><?= ikon('centang', 15) ?> Saya hadir</button></form>
          <?php endif; ?>
          <?php if ($bolehUbah): ?><button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiPeserta" data-reset><?= ikon('tambah', 15) ?> Tambah peserta</button><?php endif; ?>
        </div>
      </div>
      <?php if (!$peserta): ?>
        <?= sel_kosong('Belum ada peserta') ?>
      <?php elseif ($bolehUbah): ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="op" value="absensi"><input type="hidden" name="rapat_id" value="<?= $id ?>">
          <div class="tabel-bungkus"><table class="tabel">
            <thead><tr><th style="width:36px">No</th><th>Nama</th><th>Jabatan / unit</th><th style="width:170px">Kehadiran</th><th>Keterangan</th><th style="width:60px" class="tengah">Hapus</th></tr></thead>
            <tbody>
            <?php foreach ($peserta as $i => $p): ?>
              <tr><td class="lemah"><?= $i + 1 ?></td>
                <td><b><?= e($p['nama']) ?></b><?= $p['eksternal'] ? ' ' . lencana('luar', 'abu') : '' ?></td>
                <td class="kecil"><?= e(trim($p['jabatan'] . ($p['unit_nama'] ? ' · ' . $p['unit_nama'] : ''), ' ·')) ?></td>
                <td><select name="kehadiran[<?= (int) $p['id'] ?>]" style="padding:6px 8px"><?php foreach (KEHADIRAN_RAPAT as $k => $d): ?><option value="<?= $k ?>" <?= $p['kehadiran'] === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach; ?></select></td>
                <td><input type="text" name="ket[<?= (int) $p['id'] ?>]" maxlength="255" value="<?= e($p['keterangan']) ?>" style="padding:6px 8px" placeholder="Opsional"></td>
                <td class="tengah"><input type="checkbox" name="hapus[<?= (int) $p['id'] ?>]" value="1" aria-label="Hapus <?= e($p['nama']) ?> dari daftar"></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
          <div class="kartu-kaki"><button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan absensi</button>
            <button class="btn" type="button" data-hadir-semua>Hadir semua</button></div>
        </form>
      <?php else: ?>
        <div class="tabel-bungkus"><table class="tabel">
          <thead><tr><th style="width:36px">No</th><th>Nama</th><th>Jabatan / unit</th><th>Kehadiran</th><th>Keterangan</th></tr></thead>
          <tbody>
          <?php foreach ($peserta as $i => $p): ?>
            <tr><td class="lemah"><?= $i + 1 ?></td><td><b><?= e($p['nama']) ?></b></td>
              <td class="kecil"><?= e(trim($p['jabatan'] . ($p['unit_nama'] ? ' · ' . $p['unit_nama'] : ''), ' ·')) ?></td>
              <td><?= lencana_dari(KEHADIRAN_RAPAT, $p['kehadiran']) ?></td><td class="kecil"><?= e($p['keterangan'] ?: '-') ?></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
      <?php endif; ?>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('catatan', 18) ?> Notulen dan hasil rapat</h2></div></div>
      <?php if ($bolehUbah): ?>
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="op" value="notulen"><input type="hidden" name="rapat_id" value="<?= $id ?>">
          <div class="kartu-isi">
            <div class="form-baris"><label class="fl" for="nPembahasan">Pembahasan / notulen rapat</label>
              <textarea id="nPembahasan" name="pembahasan" rows="12" placeholder="Tuliskan jalannya rapat, pokok pembahasan, dan masukan dari peserta"><?= e($r['pembahasan'] ?? '') ?></textarea></div>
            <div class="form-baris mb0"><label class="fl" for="nKeputusan">Hasil dan keputusan rapat</label>
              <textarea id="nKeputusan" name="keputusan" rows="5" placeholder="Hal-hal yang disepakati dan diputuskan"><?= e($r['keputusan'] ?? '') ?></textarea></div>
          </div>
          <div class="kartu-kaki"><button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan notulen</button></div>
        </form>
      <?php else: ?>
        <div class="kartu-isi">
          <div class="kecil lemah" style="margin-bottom:4px">Pembahasan</div>
          <div style="white-space:pre-line;margin-bottom:14px"><?= e($r['pembahasan'] ?: '-') ?></div>
          <div class="kecil lemah" style="margin-bottom:4px">Hasil dan keputusan</div>
          <div style="white-space:pre-line"><?= e($r['keputusan'] ?: '-') ?></div>
        </div>
      <?php endif; ?>
    </div>

    <div class="kartu">
      <div class="kartu-kepala">
        <div><h2><?= ikon('tumbuh', 18) ?> Kendala dan Rencana Tindak Lanjut (RTL)</h2>
          <p class="ket"><?= count($rtlList) ?> RTL · <?= count(array_filter($rtlList, static function ($t) { return $t['status'] !== 'selesai'; })) ?> belum selesai</p></div>
        <?php if ($bolehUbah): ?><div class="aksi"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiRtl" data-reset data-judul="Tambah RTL"
            data-isi='{"id":"","status":"baru","unit_id":""}'><?= ikon('tambah', 15) ?> Tambah RTL</button></div><?php endif; ?>
      </div>
      <?php if (!$rtlList): ?>
        <?= sel_kosong('Belum ada RTL') ?>
      <?php else: ?>
        <div class="tabel-bungkus"><table class="tabel">
          <thead><tr><th>Bidang</th><th>Kendala</th><th>Rencana tindak lanjut</th><th>PJ</th><th>Tenggat</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
          <tbody>
          <?php foreach ($rtlList as $t): $lewat = $t['tenggat'] && $t['status'] !== 'selesai' && $t['tenggat'] < date('Y-m-d'); ?>
            <tr><td><b><?= e($t['unit_nama'] ?: '-') ?></b></td>
              <td class="kecil" style="white-space:pre-line;min-width:160px"><?= e($t['kendala'] ?: '-') ?></td>
              <td style="white-space:pre-line;min-width:200px"><?= e($t['rtl']) ?><?= $t['hasil'] ? '<span class="sub">Perkembangan: ' . e($t['hasil']) . '</span>' : '' ?></td>
              <td class="kecil"><?= e($t['penanggung_jawab'] ?: '-') ?></td>
              <td class="nowrap kecil" <?= $lewat ? 'style="color:var(--merah);font-weight:650"' : '' ?>><?= $t['tenggat'] ? e(tanggal_pendek($t['tenggat'])) . ($lewat ? ' · lewat' : '') : '-' ?></td>
              <td><?= lencana_dari(STATUS_RTL, $t['status']) ?></td>
              <td><div class="aksi-baris">
                <?php if (rapat_boleh_ubah_rtl($t, $r)): ?>
                  <button class="btn btn-hantu btn-ikon" type="button" title="Ubah RTL" aria-label="Ubah RTL" data-buka="tiraiRtl" data-judul="Ubah RTL"
                          data-isi='<?= e(json_encode(['id' => (int) $t['id'], 'unit_id' => $t['unit_id'] ?? '', 'kendala' => $t['kendala'], 'rtl' => $t['rtl'],
                              'penanggung_jawab' => $t['penanggung_jawab'], 'tenggat' => $t['tenggat'] ?? '', 'status' => $t['status'], 'hasil' => $t['hasil']])) ?>'><?= ikon('sunting', 15) ?></button>
                <?php endif; ?>
                <?php if ($bolehUbah): ?>
                  <form method="post" data-konfirmasi="Hapus RTL ini?"><?= csrf_field() ?><input type="hidden" name="op" value="rtl_hapus"><input type="hidden" name="rapat_id" value="<?= $id ?>"><input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                    <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus RTL" aria-label="Hapus RTL"><?= ikon('hapus', 15) ?></button></form>
                <?php endif; ?>
              </div></td></tr>
          <?php endforeach; ?>
          </tbody></table></div>
      <?php endif; ?>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('kamera', 18) ?> Dokumentasi rapat</h2><p class="ket"><?= count($dokList) ?> foto</p></div></div>
      <div class="kartu-isi">
        <?php if (!$dokList): ?><p class="lemah kecil" style="margin:0 0 12px">Belum ada foto dokumentasi.</p><?php endif; ?>
        <?php if ($dokList): ?>
          <div class="galeri-dok">
            <?php foreach ($dokList as $d): ?>
              <figure>
                <a href="<?= e(url_berkas('rapat', $d['berkas'])) ?>" target="_blank" rel="noopener"><img src="<?= e(url_berkas('rapat', $d['berkas'])) ?>" alt="Dokumentasi rapat" loading="lazy"></a>
                <figcaption><span><?= e($d['keterangan'] ?: 'Tanpa keterangan') ?></span>
                  <?php if ($bolehUbah): ?>
                    <form method="post" data-konfirmasi="Hapus foto dokumentasi ini?"><?= csrf_field() ?><input type="hidden" name="op" value="dok_hapus"><input type="hidden" name="rapat_id" value="<?= $id ?>"><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                      <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus foto" aria-label="Hapus foto"><?= ikon('hapus', 14) ?></button></form>
                  <?php endif; ?></figcaption>
              </figure>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if ($bolehUbah): ?>
          <form method="post" enctype="multipart/form-data" class="form-unggah-dok">
            <?= csrf_field() ?><input type="hidden" name="op" value="dok_unggah"><input type="hidden" name="rapat_id" value="<?= $id ?>">
            <div class="form-grid">
              <div class="form-baris mb0"><label class="fl" for="dokBerkas">Unggah foto (boleh lebih dari satu)</label>
                <input type="file" id="dokBerkas" name="dokumentasi[]" accept="image/jpeg,image/png,image/webp" multiple></div>
              <div class="form-baris mb0"><label class="fl" for="dokKet">Keterangan foto</label>
                <input type="text" id="dokKet" name="keterangan" maxlength="255" placeholder="Contoh: Suasana rapat koordinasi"></div>
            </div>
            <div class="mt12"><button class="btn btn-utama btn-kecil" type="submit"><?= ikon('unduh', 15) ?> Unggah dokumentasi</button></div>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <?php if ($bolehUbah): ?>
    <div class="tirai" id="tiraiPeserta" hidden>
      <div class="modal">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="op" value="peserta_tambah"><input type="hidden" name="rapat_id" value="<?= $id ?>">
          <div class="modal-kepala"><h2>Tambah peserta</h2>
            <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
          <div class="modal-isi">
            <label class="fl">Pegawai</label>
            <?php if (!$calonTambah): ?><p class="lemah kecil">Semua pegawai aktif sudah menjadi peserta.</p><?php endif; ?>
            <div class="pilih-peserta">
              <?php $unitKini = null; foreach ($calonTambah as $p):
                  $kunciU = 'm' . (int) ($p['unit_id'] ?? 0);
                  if ($kunciU !== $unitKini): $unitKini = $kunciU; ?>
                    <div class="peserta-unit"><label class="cek"><input type="checkbox" data-pilih-unit="<?= e($kunciU) ?>"> <b><?= e($p['unit_nama'] ?? 'Tanpa unit') ?></b> <span class="lemah kecil">pilih semua</span></label></div>
                  <?php endif; ?>
                  <label class="cek peserta-item"><input type="checkbox" name="tambah[]" value="<?= (int) $p['id'] ?>" data-unit="<?= e($kunciU) ?>">
                    <span><?= e($p['nama']) ?><small><?= e($p['jabatan'] ?: '') ?></small></span></label>
              <?php endforeach; ?>
            </div>
            <div class="mt16"><label class="fl">Peserta dari luar (opsional)</label>
              <div class="form-grid-3">
                <input type="text" name="nama_luar" maxlength="150" placeholder="Nama">
                <input type="text" name="jabatan_luar" maxlength="150" placeholder="Jabatan">
                <input type="text" name="instansi_luar" maxlength="150" placeholder="Instansi">
              </div></div>
          </div>
          <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Tambahkan</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($bolehUbah || array_filter($rtlList, static function ($t) use ($r) { return rapat_boleh_ubah_rtl($t, $r); })): ?>
    <div class="tirai" id="tiraiRtl" hidden>
      <div class="modal">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="op" value="rtl_simpan"><input type="hidden" name="rapat_id" value="<?= $id ?>"><input type="hidden" name="id" value="">
          <div class="modal-kepala"><h2>RTL</h2>
            <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
          <div class="modal-isi">
            <div class="form-grid">
              <div class="form-baris"><label class="fl" for="tUnit">Bidang</label><select id="tUnit" name="unit_id"><?= opsi_unit(0, 'Pilih bidang') ?></select></div>
              <div class="form-baris"><label class="fl" for="tPj">Penanggung jawab</label><input type="text" id="tPj" name="penanggung_jawab" maxlength="150" placeholder="Nama atau jabatan"></div>
            </div>
            <div class="form-baris"><label class="fl" for="tKendala">Kendala</label><textarea id="tKendala" name="kendala" rows="3" maxlength="3000" placeholder="Kendala yang dihadapi bidang"></textarea></div>
            <div class="form-baris"><label class="fl" for="tRtl">Rencana tindak lanjut <span class="wajib">*</span></label><textarea id="tRtl" name="rtl" rows="3" maxlength="3000" required placeholder="Langkah yang akan dilakukan"></textarea></div>
            <div class="form-grid">
              <div class="form-baris"><label class="fl" for="tTenggat">Tenggat</label><input type="date" id="tTenggat" name="tenggat"></div>
              <div class="form-baris"><label class="fl" for="tStatus">Status</label><select id="tStatus" name="status"><?php foreach (STATUS_RTL as $k => $d): ?><option value="<?= $k ?>"><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
            </div>
            <div class="form-baris mb0"><label class="fl" for="tHasil">Perkembangan / hasil</label><input type="text" id="tHasil" name="hasil" maxlength="500" placeholder="Diisi saat RTL berjalan atau selesai"></div>
          </div>
          <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button>
            <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <script nonce="<?= csp_nonce() ?>">
      (function () {
        var inp = document.getElementById('dokBerkas');
        if (!inp) return;
        inp.addEventListener('change', function () {
          var daftar = Array.prototype.slice.call(inp.files || []).filter(function (f) { return /^image\/(jpeg|png|webp)$/.test(f.type); });
          if (!daftar.length) return;
          var dt = new DataTransfer(), sisa = daftar.length;
          daftar.forEach(function (f, i) {
            var img = new Image(), url = URL.createObjectURL(f);
            img.onload = function () {
              var maks = 1600, skala = Math.min(1, maks / Math.max(img.width, img.height)), c = document.createElement('canvas');
              c.width = Math.round(img.width * skala); c.height = Math.round(img.height * skala);
              c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
              c.toBlob(function (b) {
                dt.items.add(new File([b || f], 'dok-' + i + '.jpg', { type: 'image/jpeg' }));
                URL.revokeObjectURL(url);
                if (--sisa === 0) { try { inp.files = dt.files; } catch (e) {} }
              }, 'image/jpeg', 0.85);
            };
            img.onerror = function () { if (--sisa === 0 && dt.files.length) { try { inp.files = dt.files; } catch (e) {} } };
            img.src = url;
          });
        });
      })();
    </script>
    <?php
    return;
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fJenis = (string) ($_GET['jenis'] ?? '');
$fUnit = (int) ($_GET['unit'] ?? 0);
$daftar = rapat_daftar_terlihat($cari, $fJenis, $fUnit);
$idTerlihat = array_map('intval', array_column(rapat_daftar_terlihat(), 'id'));
$rtlTerbuka = 0;
if ($idTerlihat) {
    $rtlTerbuka = (int) val("SELECT COUNT(*) FROM rapat_rtl WHERE status <> 'selesai' AND rapat_id IN (" . in_placeholder($idTerlihat) . ')', $idTerlihat, 0);
}
?>

<?= rapat_navigasi($tab, count($daftar), $rtlTerbuka) ?>

<?php if ($tab === 'rtl'): ?>
  <?php
  $fStatus = (string) ($_GET['status'] ?? '');
  $rtlRows = [];
  if ($idTerlihat) {
      $syarat = 't.rapat_id IN (' . in_placeholder($idTerlihat) . ')';
      $param = $idTerlihat;
      if ($fStatus === 'terbuka') {
          $syarat .= " AND t.status <> 'selesai'";
      } elseif (array_key_exists($fStatus, STATUS_RTL)) {
          $syarat .= ' AND t.status = ?';
          $param[] = $fStatus;
      }
      if ($fUnit) {
          $syarat .= ' AND t.unit_id = ?';
          $param[] = $fUnit;
      }
      $rtlRows = rows(
          "SELECT t.*, un.nama AS unit_nama, r.judul, r.tanggal, r.id AS rid
             FROM rapat_rtl t JOIN rapat r ON r.id = t.rapat_id LEFT JOIN unit un ON un.id = t.unit_id
            WHERE $syarat
            ORDER BY (t.status = 'selesai'), t.tenggat IS NULL, t.tenggat, r.tanggal DESC, t.id",
          $param
      );
  }
  ?>
  <form method="get" class="saring">
    <input type="hidden" name="h" value="rapat"><input type="hidden" name="tab" value="rtl">
    <div class="form-baris"><label class="fl">Status</label>
      <select name="status" data-auto-kirim><option value="">Semua status</option><option value="terbuka" <?= $fStatus === 'terbuka' ? 'selected' : '' ?>>Belum selesai</option>
        <?php foreach (STATUS_RTL as $k => $d): ?><option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
    <div class="form-baris"><label class="fl">Bidang</label><select name="unit" data-auto-kirim><?= opsi_unit($fUnit, 'Semua bidang') ?></select></div>
  </form>
  <div class="kartu">
    <div class="kartu-kepala"><div><h2><?= ikon('tumbuh', 18) ?> Pemantauan RTL seluruh rapat</h2><p class="ket"><?= count($rtlRows) ?> RTL</p></div></div>
    <?php if (!$rtlRows): ?>
      <?= sel_kosong('Tidak ada RTL') ?>
    <?php else: ?>
    <div class="tabel-bungkus"><table class="tabel">
      <thead><tr><th>Bidang</th><th>Kendala</th><th>Rencana tindak lanjut</th><th>PJ</th><th>Tenggat</th><th>Status</th><th>Rapat</th></tr></thead>
      <tbody>
      <?php foreach ($rtlRows as $t): $lewat = $t['tenggat'] && $t['status'] !== 'selesai' && $t['tenggat'] < date('Y-m-d'); ?>
        <tr><td><b><?= e($t['unit_nama'] ?: '-') ?></b></td>
          <td class="kecil" style="white-space:pre-line;min-width:150px"><?= e($t['kendala'] ?: '-') ?></td>
          <td style="white-space:pre-line;min-width:200px"><?= e($t['rtl']) ?></td>
          <td class="kecil"><?= e($t['penanggung_jawab'] ?: '-') ?></td>
          <td class="nowrap kecil" <?= $lewat ? 'style="color:var(--merah);font-weight:650"' : '' ?>><?= $t['tenggat'] ? e(tanggal_pendek($t['tenggat'])) . ($lewat ? ' · lewat' : '') : '-' ?></td>
          <td><?= lencana_dari(STATUS_RTL, $t['status']) ?></td>
          <td class="kecil"><a href="<?= e(url('rapat', ['aksi' => 'lihat', 'id' => $t['rid']])) ?>"><?= e($t['judul']) ?></a><span class="sub"><?= e(tanggal_pendek($t['tanggal'])) ?></span></td></tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
<?php else: ?>
  <form method="get" class="saring">
    <input type="hidden" name="h" value="rapat">
    <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Judul, agenda, atau tempat rapat"></div>
    <div class="form-baris"><label class="fl">Jenis</label><select name="jenis" data-auto-kirim><option value="">Semua jenis</option><?= pilihan(JENIS_RAPAT, $fJenis) ?></select></div>
    <div class="form-baris"><label class="fl">Bidang</label><select name="unit" data-auto-kirim><?= opsi_unit($fUnit, 'Semua bidang') ?></select></div>
    <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
  </form>

  <div class="kartu">
    <div class="kartu-kepala">
      <div><h2><?= ikon('grup', 18) ?> Berita acara rapat</h2><p class="ket"><?= count($daftar) ?> rapat<?= can('rapat_lihat_semua') ? '' : ' yang Anda ikuti' ?></p></div>
      <?php if ($bolehBuat): ?><div class="aksi"><a class="btn btn-utama btn-kecil" href="<?= e(url('rapat', ['aksi' => 'baru'])) ?>"><?= ikon('tambah', 15) ?> Rapat baru</a></div><?php endif; ?>
    </div>
    <?php if (!$daftar): ?>
      <?= sel_kosong('Belum ada berita acara rapat') ?>
    <?php else: ?>
    <div class="tabel-bungkus"><table class="tabel tabel-rapat">
      <thead><tr><th>Tanggal</th><th>Rapat</th><th>Jenis</th><th class="tengah">Hadir</th><th class="tengah">RTL</th><th class="tengah">Foto</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($daftar as $d): ?>
        <tr>
          <td class="nowrap"><b><?= e(tanggal_pendek($d['tanggal'])) ?></b><span class="sub"><?= $d['jam_mulai'] ? e(jam_pendek($d['jam_mulai'])) : '' ?></span></td>
          <td><a href="<?= e(url('rapat', ['aksi' => 'lihat', 'id' => $d['id']])) ?>"><b><?= e($d['judul']) ?></b></a>
            <span class="sub"><?= e($d['tempat'] ?: '-') ?> · <?= (int) $d['jml_peserta'] ?> undangan</span></td>
          <td class="kecil"><?= e(JENIS_RAPAT[$d['jenis']]) ?></td>
          <td class="tengah"><?= (int) $d['jml_hadir'] ?>/<?= (int) $d['jml_peserta'] ?></td>
          <td class="tengah"><?= (int) $d['jml_rtl'] === 0 ? '<span class="lemah">-</span>' : ((int) $d['rtl_terbuka'] > 0 ? lencana($d['rtl_terbuka'] . ' terbuka', 'emas') : lencana('selesai', 'hijau')) ?></td>
          <td class="tengah"><?= (int) $d['jml_dok'] ?: '<span class="lemah">-</span>' ?></td>
          <td><?= $d['status'] === 'final' ? lencana('Final', 'hijau') : lencana('Draft', 'abu') ?></td>
          <td><div class="aksi-baris">
            <a class="btn btn-hantu btn-ikon" href="<?= e(url('rapat', ['aksi' => 'cetak', 'id' => $d['id']])) ?>" title="Cetak berita acara" aria-label="Cetak berita acara <?= e($d['judul']) ?>"><?= ikon('cetak', 15) ?></a>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
<?php endif; ?>
