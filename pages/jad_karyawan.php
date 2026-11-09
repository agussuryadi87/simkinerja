<?php
$bolehMaster = jad_boleh_master();
$normalKet = static function (string $v): string {
    $k = preg_replace('/\s+/', '', mb_strtolower(trim($v)));
    if ($k === 'karyawan+dosen' || $k === 'dosen+karyawan') {
        return 'Karyawan + Dosen';
    }
    return $k === 'karyawan' ? 'Karyawan' : '';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $balik = static function (): void {
        kembali_ke('jad_karyawan', array_filter(['cari' => post('f_cari'), 'ket' => post('f_ket'), 'pos' => post('f_pos')], 'strlen'));
    };

    if ($op === 'simpan' || $op === 'hapus') {
        $id = (int) post('id');
        if ($op === 'hapus') {
            $nama = jad_karyawan_ubah(static function (array &$d) use ($id): string {
                foreach ($d['karyawan'] as $i => $k) {
                    if ((int) $k['id'] === $id) {
                        unset($d['karyawan'][$i]);
                        $d['karyawan'] = array_values($d['karyawan']);
                        return (string) $k['nama'];
                    }
                }
                return '';
            });
            if ($nama) {
                catat_log('hapus_karyawan', 'karyawan', $id, $nama);
            }
            flash($nama ? 'Data karyawan <b>' . e($nama) . '</b> dihapus.' : 'Data karyawan tidak ditemukan.', $nama ? 'ok' : 'galat');
            $balik();
        }
        $nama = jad_huruf_nama(mb_substr(trim(post_teks('nama')), 0, 200));
        if ($nama === '') {
            flash('Nama karyawan wajib diisi.', 'galat');
            $balik();
        }
        $baris = ['nama' => $nama, 'jabatan' => jad_huruf_jabatan(mb_substr(trim(post_teks('jabatan')), 0, 200)),
                  'keterangan' => in_array(post('keterangan'), JAD_KETERANGAN, true) ? post('keterangan') : '',
                  'posisi_id' => (int) post('posisi_id'), 'no_urut' => max(0, (int) post('no_urut'))];
        $posisiBaru = $bolehMaster && post('posisi_id') === 'baru' ? mb_substr(trim(post_teks('posisi_baru')), 0, 120) : '';
        $hasil = jad_karyawan_ubah(static function (array &$d) use ($id, $baris, $posisiBaru): int {
            if ($posisiBaru !== '') {
                foreach (jad_posisi_peta($d) as $pid => $pnm) {
                    if (mb_strtolower($pnm) === mb_strtolower($posisiBaru)) { $baris['posisi_id'] = $pid; }
                }
                if (!$baris['posisi_id']) {
                    $d['urut_posisi'] = (int) $d['urut_posisi'] + 1;
                    $d['posisi'][] = ['id' => $d['urut_posisi'], 'nama' => $posisiBaru];
                    $baris['posisi_id'] = $d['urut_posisi'];
                }
            }
            if (!isset(jad_posisi_peta($d)[$baris['posisi_id']])) {
                $baris['posisi_id'] = 0;
            }
            if ($id) {
                foreach ($d['karyawan'] as $i => $k) {
                    if ((int) $k['id'] === $id) {
                        $d['karyawan'][$i] = array_merge($k, $baris, ['no_urut' => $baris['no_urut'] ?: (int) $k['no_urut']]);
                        return $id;
                    }
                }
                return 0;
            }
            $d['urut'] = (int) $d['urut'] + 1;
            $maksUrut = 0;
            foreach ($d['karyawan'] as $k) {
                $maksUrut = max($maksUrut, (int) $k['no_urut']);
            }
            $d['karyawan'][] = ['id' => $d['urut']] + array_merge($baris, ['no_urut' => $baris['no_urut'] ?: $maksUrut + 1]);
            return $d['urut'];
        });
        if (!$hasil) {
            flash('Data karyawan gagal disimpan.', 'galat');
            $balik();
        }
        catat_log($id ? 'ubah_karyawan' : 'tambah_karyawan', 'karyawan', (int) $hasil, $nama);
        flash(($id ? 'Data karyawan <b>' : 'Karyawan <b>') . e($nama) . ($id ? '</b> diperbarui.' : '</b> ditambahkan.'));
        $balik();
    }

    if (in_array($op, ['posisi_tambah', 'posisi_ubah', 'posisi_hapus'], true)) {
        if (!$bolehMaster) {
            tolak_akses();
        }
        $pid = (int) post('posisi_id');
        $namaPos = mb_substr(trim(post_teks('nama_posisi')), 0, 120);
        if ($op !== 'posisi_hapus' && $namaPos === '') {
            flash('Nama posisi wajib diisi.', 'galat');
            $balik();
        }
        $pesan = jad_karyawan_ubah(static function (array &$d) use ($op, $pid, $namaPos): string {
            $peta = jad_posisi_peta($d);
            foreach ($peta as $id => $nm) {
                if ($op !== 'posisi_hapus' && $id !== $pid && mb_strtolower($nm) === mb_strtolower($namaPos)) {
                    return 'ada';
                }
            }
            if ($op === 'posisi_tambah') {
                $d['urut_posisi'] = (int) $d['urut_posisi'] + 1;
                $d['posisi'][] = ['id' => $d['urut_posisi'], 'nama' => $namaPos];
                return 'tambah';
            }
            foreach ($d['posisi'] as $i => $p) {
                if ((int) $p['id'] !== $pid) {
                    continue;
                }
                if ($op === 'posisi_ubah') {
                    $d['posisi'][$i]['nama'] = $namaPos;
                    return 'ubah';
                }
                unset($d['posisi'][$i]);
                $d['posisi'] = array_values($d['posisi']);
                foreach ($d['karyawan'] as $j => $k) {
                    if ((int) $k['posisi_id'] === $pid) {
                        $d['karyawan'][$j]['posisi_id'] = 0;
                    }
                }
                return 'hapus';
            }
            return '';
        });
        $teks = ['ada' => ['Nama posisi sudah ada di master.', 'galat'], 'tambah' => ['Posisi <b>' . e($namaPos) . '</b> ditambahkan.', 'ok'],
                 'ubah' => ['Nama posisi diperbarui.', 'ok'], 'hapus' => ['Posisi dihapus. Karyawan yang memakai posisi ini kini tanpa posisi.', 'info']][$pesan] ?? ['Posisi tidak ditemukan.', 'galat'];
        if (in_array($pesan, ['tambah', 'ubah', 'hapus'], true)) {
            catat_log($op, 'posisi_karyawan', $pid, $namaPos);
        }
        flash($teks[0], $teks[1]);
        $balik();
    }

    if ($op === 'impor') {
        $baca = xlsx_baca_unggahan($_FILES['berkas'] ?? [], ['sheet1', 'karyawan']);
        if (isset($baca['galat'])) {
            flash($baca['galat'], 'galat');
            $balik();
        }
        $ganti = post('ganti') === '1';
        $hasil = jad_karyawan_ubah(static function (array &$d) use ($baca, $ganti, $normalKet, $bolehMaster): array {
            $kolom = null;
            $baru = 0;
            $ubah = 0;
            if ($ganti) {
                $d['karyawan'] = [];
            }
            foreach ($baca['data'] as $sel) {
                $teks = array_map(static function ($v) { return trim((string) $v); }, (array) $sel);
                if ($kolom === null) {
                    foreach ($teks as $huruf => $v) {
                        if (mb_strtolower($v) === 'nama') {
                            $kolom = ['nama' => $huruf];
                            foreach ($teks as $h2 => $v2) {
                                $k2 = mb_strtolower($v2);
                                if (strpos($k2, 'jabatan') !== false) { $kolom['jabatan'] = $h2; }
                                if (strpos($k2, 'keterangan') !== false) { $kolom['keterangan'] = $h2; }
                                if (strpos($k2, 'posisi') !== false) { $kolom['posisi'] = $h2; }
                                if ($k2 === 'no' || $k2 === 'no.') { $kolom['no'] = $h2; }
                            }
                            break;
                        }
                    }
                    continue;
                }
                $nama = $teks[$kolom['nama']] ?? '';
                if ($nama === '') {
                    continue;
                }
                $posisiId = 0;
                $posTeks = isset($kolom['posisi']) ? ($teks[$kolom['posisi']] ?? '') : '';
                if ($posTeks !== '') {
                    foreach (jad_posisi_peta($d) as $pid => $pnm) {
                        if (mb_strtolower($pnm) === mb_strtolower($posTeks)) { $posisiId = $pid; }
                    }
                    if (!$posisiId && $bolehMaster) {
                        $d['urut_posisi'] = (int) $d['urut_posisi'] + 1;
                        $d['posisi'][] = ['id' => $d['urut_posisi'], 'nama' => mb_substr($posTeks, 0, 120)];
                        $posisiId = $d['urut_posisi'];
                    }
                }
                $baris = ['nama' => jad_huruf_nama(mb_substr($nama, 0, 200)), 'jabatan' => jad_huruf_jabatan(mb_substr(isset($kolom['jabatan']) ? ($teks[$kolom['jabatan']] ?? '') : '', 0, 200)),
                          'keterangan' => $normalKet(isset($kolom['keterangan']) ? ($teks[$kolom['keterangan']] ?? '') : ''), 'posisi_id' => $posisiId,
                          'no_urut' => isset($kolom['no']) ? (int) ($teks[$kolom['no']] ?? 0) : 0];
                $ketemu = false;
                foreach ($d['karyawan'] as $i => $k) {
                    if (mb_strtolower(trim($k['nama'])) === mb_strtolower($baris['nama'])) {
                        $d['karyawan'][$i] = array_merge($k, array_filter($baris, static function ($v) { return $v !== '' && $v !== 0; }));
                        $ketemu = true;
                        $ubah++;
                        break;
                    }
                }
                if (!$ketemu) {
                    $d['urut'] = (int) $d['urut'] + 1;
                    $d['karyawan'][] = ['id' => $d['urut']] + $baris;
                    $baru++;
                }
            }
            return [$kolom !== null, $baru, $ubah];
        });
        if (!$hasil || !$hasil[0]) {
            flash('Kolom "Nama" tidak ditemukan di file. Pastikan baris judul berisi No, Nama, Jabatan, Keterangan, Posisi.', 'galat');
            $balik();
        }
        catat_log('impor_karyawan', 'karyawan', 0, $hasil[1] . ' baru, ' . $hasil[2] . ' diperbarui');
        flash('Impor selesai: <b>' . $hasil[1] . '</b> karyawan baru, <b>' . $hasil[2] . '</b> diperbarui.');
        $balik();
    }
    tolak_akses();
}

$data = jad_karyawan_baca();
$petaPosisi = jad_posisi_peta($data);
$fCari = trim((string) ($_GET['cari'] ?? ''));
$fKet = in_array($_GET['ket'] ?? '', JAD_KETERANGAN, true) ? $_GET['ket'] : (($_GET['ket'] ?? '') === '-' ? '-' : '');
$fPos = (string) ($_GET['pos'] ?? '');
$daftar = array_values(array_filter($data['karyawan'], static function (array $k) use ($fCari, $fKet, $fPos): bool {
    if ($fCari !== '' && mb_stripos($k['nama'] . ' ' . $k['jabatan'], $fCari) === false) { return false; }
    if ($fKet === '-' && $k['keterangan'] !== '') { return false; }
    if ($fKet !== '' && $fKet !== '-' && $k['keterangan'] !== $fKet) { return false; }
    if ($fPos === '-' && (int) $k['posisi_id'] !== 0) { return false; }
    if ($fPos !== '' && $fPos !== '-' && (int) $k['posisi_id'] !== (int) $fPos) { return false; }
    return true;
}));
usort($daftar, static function (array $a, array $b): int {
    return [(int) $a['no_urut'], mb_strtolower($a['nama'])] <=> [(int) $b['no_urut'], mb_strtolower($b['nama'])];
});
$jumlahPos = [];
foreach ($data['karyawan'] as $k) {
    $jumlahPos[(int) $k['posisi_id']] = ($jumlahPos[(int) $k['posisi_id']] ?? 0) + 1;
}
$saring = ['f_cari' => $fCari, 'f_ket' => $fKet, 'f_pos' => $fPos];
$subjudul = 'Data karyawan struktural & pendukung';
$opsiPosisi = ['0' => '- Belum ditentukan -'];
foreach ($petaPosisi as $pid => $pnm) {
    $opsiPosisi[(string) $pid] = $pnm;
}
?>
<form method="get" class="saring no-cetak">
  <input type="hidden" name="h" value="jad_karyawan">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($fCari) ?>" placeholder="Nama atau jabatan…"></div>
  <div class="form-baris"><label class="fl">Keterangan</label><select name="ket" data-auto-kirim><option value="">Semua</option>
    <?php foreach (JAD_KETERANGAN as $k): ?><option value="<?= e($k) ?>" <?= $fKet === $k ? 'selected' : '' ?>><?= e($k) ?></option><?php endforeach; ?>
    <option value="-" <?= $fKet === '-' ? 'selected' : '' ?>>Belum diisi</option></select></div>
  <div class="form-baris"><label class="fl">Posisi</label><select name="pos" data-auto-kirim><option value="">Semua</option>
    <?php foreach ($petaPosisi as $pid => $pnm): ?><option value="<?= $pid ?>" <?= $fPos === (string) $pid ? 'selected' : '' ?>><?= e($pnm) ?></option><?php endforeach; ?>
    <option value="-" <?= $fPos === '-' ? 'selected' : '' ?>>Belum ditentukan</option></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('grup', 18) ?> Data Karyawan</h2><p class="ket"><?= count($daftar) ?> dari <?= count($data['karyawan']) ?> karyawan</p></div>
    <div class="aksi no-cetak">
      <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiImporKaryawan"><?= ikon('unggah', 14) ?> Impor Excel</button>
      <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiKaryawan" data-reset data-isi='{"id":"","posisi_id":"0","keterangan":""}'><?= ikon('tambah', 15) ?> Tambah karyawan</button>
    </div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong($data['karyawan'] ? 'Tidak ada karyawan yang cocok dengan saringan' : 'Belum ada data karyawan', $data['karyawan'] ? '' : 'Klik "Impor Excel" untuk memasukkan data dari file, atau "Tambah karyawan".') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-karyawan">
    <thead><tr><th>No</th><th>Nama</th><th>Jabatan</th><th>Keterangan</th><th>Posisi</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $k): ?>
      <tr>
        <td><?= (int) $k['no_urut'] ?: '-' ?></td>
        <td class="kol-nama"><?= e($k['nama']) ?></td>
        <td><?= $k['jabatan'] !== '' ? e($k['jabatan']) : '<span class="lemah">-</span>' ?></td>
        <td><?= $k['keterangan'] !== '' ? e($k['keterangan']) : '<span class="lemah">-</span>' ?></td>
        <td><?= isset($petaPosisi[(int) $k['posisi_id']]) ? e($petaPosisi[(int) $k['posisi_id']]) : '<span class="lemah">-</span>' ?></td>
        <td class="no-cetak aksi-sel"><button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiKaryawan" data-isi='<?= e(json_encode(['id' => (int) $k['id'], 'nama' => $k['nama'], 'jabatan' => $k['jabatan'], 'keterangan' => $k['keterangan'], 'posisi_id' => (string) (int) $k['posisi_id'], 'no_urut' => (int) $k['no_urut']])) ?>'><?= ikon('sunting', 14) ?> Edit</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="kartu no-cetak">
  <div class="kartu-kepala">
    <div><h2><?= ikon('filter', 18) ?> Master Posisi</h2><p class="ket">Pengelompokan posisi karyawan<?= $bolehMaster ? '' : ' · hanya Administrator yang dapat mengubah' ?></p></div>
    <?php if ($bolehMaster): ?>
    <form method="post" class="aksi master-posisi-tambah"><?= csrf_field() ?><input type="hidden" name="op" value="posisi_tambah">
      <?php foreach ($saring as $kk => $vv): ?><input type="hidden" name="<?= $kk ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
      <input type="text" name="nama_posisi" maxlength="120" required placeholder="Nama posisi baru, mis. Fakultas FST" aria-label="Nama posisi baru">
      <button class="btn btn-utama btn-kecil" type="submit"><?= ikon('tambah', 14) ?> Tambah</button></form>
    <?php endif; ?>
  </div>
  <div class="tabel-bungkus"><table class="tabel tabel-karyawan tabel-master">
    <thead><tr><th>Posisi</th><th>Jumlah karyawan</th><?php if ($bolehMaster): ?><th class="no-cetak">Aksi</th><?php endif; ?></tr></thead>
    <tbody>
    <?php foreach ($petaPosisi as $pid => $pnm): ?>
      <tr>
        <td><?php if ($bolehMaster): ?><form method="post" class="master-posisi-ubah" id="ubahPos<?= $pid ?>"><?= csrf_field() ?><input type="hidden" name="op" value="posisi_ubah"><input type="hidden" name="posisi_id" value="<?= $pid ?>">
          <?php foreach ($saring as $kk => $vv): ?><input type="hidden" name="<?= $kk ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
          <input type="text" name="nama_posisi" value="<?= e($pnm) ?>" maxlength="120" required aria-label="Nama posisi"></form><?php else: ?><?= e($pnm) ?><?php endif; ?></td>
        <td><a href="<?= e(url('jad_karyawan', ['pos' => $pid])) ?>"><?= (int) ($jumlahPos[$pid] ?? 0) ?> karyawan</a></td>
        <?php if ($bolehMaster): ?><td class="no-cetak"><div class="aksi-baris">
          <button class="btn btn-garis btn-kecil" type="submit" form="ubahPos<?= $pid ?>"><?= ikon('simpan', 14) ?> Simpan</button>
          <form method="post" data-konfirmasi="Hapus posisi <?= e($pnm) ?>? Karyawan yang memakai posisi ini akan menjadi tanpa posisi."><?= csrf_field() ?><input type="hidden" name="op" value="posisi_hapus"><input type="hidden" name="posisi_id" value="<?= $pid ?>">
            <?php foreach ($saring as $kk => $vv): ?><input type="hidden" name="<?= $kk ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
            <button class="btn btn-hantu btn-kecil" type="submit"><?= ikon('hapus', 14) ?> Hapus</button></form>
        </div></td><?php endif; ?>
      </tr>
    <?php endforeach; ?>
    <?php if (!$petaPosisi): ?><tr><td colspan="3" class="lemah">Belum ada posisi.</td></tr><?php endif; ?>
    </tbody>
  </table></div>
</div>

<div class="tirai" id="tiraiKaryawan" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="">
      <?php foreach ($saring as $kk => $vv): ?><input type="hidden" name="<?= $kk ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
      <div class="modal-kepala"><h2>Data karyawan</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="kNama">Nama <span class="wajib">*</span></label><input type="text" id="kNama" name="nama" maxlength="200" required></div>
        <div class="form-baris"><label class="fl" for="kJabatan">Jabatan</label><input type="text" id="kJabatan" name="jabatan" maxlength="200" placeholder="Contoh: KABID KEUANGAN"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="kKet">Keterangan</label><select id="kKet" name="keterangan"><option value="">- Belum diisi -</option>
            <?php foreach (JAD_KETERANGAN as $k): ?><option value="<?= e($k) ?>"><?= e($k) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="kPos">Posisi</label><select id="kPos" name="posisi_id"><?= pilihan($opsiPosisi, '0') ?><?php if ($bolehMaster): ?><option value="baru">+ Tambah posisi baru…</option><?php endif; ?></select>
            <?php if ($bolehMaster): ?><input type="text" id="kPosBaru" name="posisi_baru" maxlength="120" placeholder="Ketik nama posisi baru, mis. Fakultas FST" style="margin-top:6px" hidden><?php endif; ?></div>
          <div class="form-baris"><label class="fl" for="kNo">Nomor urut</label><input type="number" id="kNo" name="no_urut" min="1" placeholder="Otomatis"></div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="submit" class="btn btn-garis" name="op" value="hapus" id="kHapus" formnovalidate data-konfirmasi="Hapus data karyawan ini?" hidden><?= ikon('hapus', 14) ?> Hapus</button>
        <span style="flex:1"></span>
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama" name="op" value="simpan"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="tirai" id="tiraiImporKaryawan" hidden>
  <div class="modal sempit">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="impor">
      <?php foreach ($saring as $kk => $vv): ?><input type="hidden" name="<?= $kk ?>" value="<?= e($vv) ?>"><?php endforeach; ?>
      <div class="modal-kepala"><h2>Impor data karyawan</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil">File Excel (.xlsx) dengan baris judul <b>No, Nama, Jabatan, Keterangan, Posisi</b>. Karyawan dengan nama yang sama akan diperbarui, nama baru ditambahkan.</p>
        <div class="form-baris"><label class="fl" for="kBerkas">File Excel</label><input type="file" id="kBerkas" name="berkas" accept=".xlsx,.csv" required></div>
        <label class="kecil" style="display:flex;gap:8px;align-items:center"><input type="checkbox" name="ganti" value="1"> Kosongkan data lama sebelum impor</label>
      </div>
      <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('unggah', 15) ?> Impor</button></div>
    </form>
  </div>
</div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var f = document.querySelector('#tiraiKaryawan [name="id"]'), h = document.getElementById('kHapus');
    if (!f || !h) return;
    var cek = function () { h.hidden = !f.value; };
    f.addEventListener('change', cek);
    document.addEventListener('click', function (ev) { if (ev.target.closest('[data-buka="tiraiKaryawan"]')) setTimeout(cek, 0); });
  })();
  (function () {
    var sel = document.getElementById('kPos'), baru = document.getElementById('kPosBaru');
    if (!sel || !baru) return;
    var atur = function () {
      var aktif = sel.value === 'baru';
      baru.hidden = !aktif;
      baru.required = aktif;
      if (aktif) baru.focus(); else baru.value = '';
    };
    sel.addEventListener('change', atur);
    document.addEventListener('click', function (ev) { if (ev.target.closest('[data-buka="tiraiKaryawan"]')) setTimeout(atur, 0); });
  })();
</script>
