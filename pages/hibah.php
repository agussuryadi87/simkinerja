<?php
$saya = user();
$tahunKini = (int) date('Y');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $balik = static function () use ($h): void {
        kembali_ke($h, array_filter(['q' => post('f_q') ?: null, 'tahun' => post('f_tahun') ?: null, 'status' => post('f_status') ?: null, 'sumber' => post('f_sumber') ?: null]));
    };
    $daftar = hibah_semua(true);
    $id = post('id');
    $indeks = null;
    foreach ($daftar as $i => $d) {
        if (($d['id'] ?? '') === $id && $id !== '') {
            $indeks = $i;
        }
    }

    if ($op === 'simpan') {
        $lama = $indeks !== null ? $daftar[$indeks] : null;
        $tahun = (int) post('tahun');
        $status = array_key_exists(post('status'), HIBAH_STATUS) ? post('status') : 'diusulkan';
        $tautan = trim(mb_substr(post('tautan'), 0, 1000));
        $diusulkan = hibah_angka('diusulkan');
        $disetujui = hibah_angka('disetujui');
        if ($status === 'ditolak') {
            $disetujui = 0.0;
        }
        $data = [
            'nama'       => mb_substr(trim(post('nama')), 0, 250),
            'sumber'     => mb_substr(trim(post('sumber')), 0, 150),
            'tahun'      => $tahun >= 2000 && $tahun <= 2100 ? $tahun : $tahunKini,
            'ketua'      => mb_substr(trim(post('ketua')), 0, 150),
            'diusulkan'  => $diusulkan ?? 0.0,
            'disetujui'  => $disetujui,
            'status'     => $status,
            'tautan'     => $tautan,
            'keterangan' => mb_substr(trim(post_teks('keterangan')), 0, 2000),
        ];
        $galat = [];
        if ($data['nama'] === '') {
            $galat[] = 'Nama hibah wajib diisi.';
        }
        if ($data['sumber'] === '') {
            $galat[] = 'Sumber hibah wajib diisi.';
        }
        if ($data['ketua'] === '') {
            $galat[] = 'Nama ketua pengusul wajib diisi.';
        }
        if ($tautan !== '' && !tautan_sah($tautan)) {
            $galat[] = 'Link dokumen harus berupa alamat http:// atau https://.';
        }
        if (in_array($status, HIBAH_STATUS_DANAI, true) && $disetujui === null) {
            $galat[] = 'Isi total dana yang disetujui untuk status ' . HIBAH_STATUS[$status]['label'] . '.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            $balik();
        }
        $kini = date('Y-m-d H:i:s');
        $entri = $lama ?? ['id' => bin2hex(random_bytes(6)), 'dibuat' => $kini, 'dibuat_oleh' => $saya['nama']];
        $entri = array_merge($entri, $data, ['diubah' => $kini, 'diubah_oleh' => $saya['nama']]);
        if ($indeks !== null) {
            $daftar[$indeks] = $entri;
        } else {
            $daftar[] = $entri;
        }
        if (!hibah_simpan($daftar)) {
            flash('Data hibah gagal disimpan karena folder uploads/hibah tidak dapat ditulisi di server.', 'galat');
            $balik();
        }
        catat_log($lama ? 'ubah_hibah' : 'tambah_hibah', 'hibah', 0, $entri['nama']);
        flash('Hibah <b>' . e($entri['nama']) . '</b> ' . ($lama ? 'diperbarui.' : 'ditambahkan.'));
        $balik();
    }

    if ($op === 'hapus' && $indeks !== null) {
        $d = $daftar[$indeks];
        array_splice($daftar, $indeks, 1);
        if (hibah_simpan($daftar)) {
            catat_log('hapus_hibah', 'hibah', 0, $d['nama'] ?? '');
            flash('Hibah <b>' . e($d['nama'] ?? '') . '</b> dihapus.');
        } else {
            flash('Data hibah gagal dihapus.', 'galat');
        }
        $balik();
    }
    flash('Data hibah tidak ditemukan.', 'galat');
    $balik();
}

$semua = hibah_semua();
$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
$fTahun = (int) ($_GET['tahun'] ?? 0);
$fStatus = array_key_exists($_GET['status'] ?? '', HIBAH_STATUS) ? $_GET['status'] : '';
$fSumber = trim(mb_substr((string) ($_GET['sumber'] ?? ''), 0, 150));
$daftarTahun = array_values(array_unique(array_filter(array_map(static function ($d) { return (int) ($d['tahun'] ?? 0); }, $semua))));
rsort($daftarTahun);
$daftarSumber = array_values(array_unique(array_filter(array_map(static function ($d) { return (string) ($d['sumber'] ?? ''); }, $semua))));
sort($daftarSumber);
$hitungStatus = array_count_values(array_map(static function ($d) { return (string) ($d['status'] ?? 'diusulkan'); }, $semua));
$hasil = array_values(array_filter($semua, static function ($d) use ($q, $fTahun, $fStatus, $fSumber) {
    return hibah_cocok($d, $q) && (!$fTahun || (int) ($d['tahun'] ?? 0) === $fTahun)
        && ($fStatus === '' || ($d['status'] ?? '') === $fStatus) && ($fSumber === '' || ($d['sumber'] ?? '') === $fSumber);
}));
usort($hasil, static function ($a, $b) {
    return [(int) ($b['tahun'] ?? 0), $b['diubah'] ?? ''] <=> [(int) ($a['tahun'] ?? 0), $a['diubah'] ?? ''];
});
$filter = array_filter(['q' => $q ?: null, 'tahun' => $fTahun ?: null, 'status' => $fStatus ?: null, 'sumber' => $fSumber ?: null]);
$tot = hibah_total($hasil);
$subjudul = 'Daftar hibah';
echo hibah_navigasi($h);
?>

<div class="kartu dok-kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('hibah', 18) ?> Daftar hibah</h2><p class="ket"><?= count($semua) ?> hibah tercatat</p></div>
    <div class="aksi no-cetak">
      <button class="btn btn-garis btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak daftar</button>
      <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiHibah" data-reset data-judul="Tambah hibah" data-isi='<?= e(json_encode(['id' => '', 'tahun' => (string) $tahunKini, 'status' => 'diusulkan', 'diusulkan' => '', 'disetujui' => '', 'sumber' => '', 'ketua' => '', 'nama' => '', 'tautan' => '', 'keterangan' => ''])) ?>'><?= ikon('tambah', 14) ?> Tambah hibah</button>
    </div>
  </div>

  <form method="get" class="dok-cari no-cetak">
    <input type="hidden" name="h" value="<?= e($h) ?>">
    <div class="dok-cari-kotak"><?= ikon('cari', 17) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari nama hibah, sumber, ketua pengusul, atau keterangan…" autocomplete="off"></div>
    <select name="tahun" data-auto-kirim><option value="">Semua tahun</option><?php foreach ($daftarTahun as $t): ?><option value="<?= $t ?>"<?= $fTahun === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
    <select name="sumber" data-auto-kirim><option value="">Semua sumber</option><?php foreach ($daftarSumber as $sm): ?><option value="<?= e($sm) ?>"<?= $fSumber === $sm ? ' selected' : '' ?>><?= e($sm) ?></option><?php endforeach; ?></select>
    <?php if ($fStatus): ?><input type="hidden" name="status" value="<?= e($fStatus) ?>"><?php endif; ?>
    <button class="btn btn-utama" type="submit"><?= ikon('cari', 15) ?> Cari</button>
    <?php if ($filter): ?><a class="btn btn-hantu" href="<?= e(url($h)) ?>">Hapus filter</a><?php endif; ?>
  </form>

  <div class="dok-kategori no-cetak">
    <a href="<?= e(url($h, array_filter(['q' => $q ?: null, 'tahun' => $fTahun ?: null, 'sumber' => $fSumber ?: null]))) ?>" class="<?= $fStatus === '' ? 'aktif' : '' ?>">Semua <b><?= count($semua) ?></b></a>
    <?php foreach (HIBAH_STATUS as $k => $st): if (empty($hitungStatus[$k])) continue; ?>
      <a href="<?= e(url($h, array_filter(['q' => $q ?: null, 'tahun' => $fTahun ?: null, 'sumber' => $fSumber ?: null, 'status' => $k]))) ?>" class="<?= $fStatus === $k ? 'aktif' : '' ?>"><?= e($st['label']) ?> <b><?= (int) $hitungStatus[$k] ?></b></a>
    <?php endforeach; ?>
  </div>

  <?php if ($filter): ?><p class="dok-ringkas"><?= count($hasil) ?> hibah ditemukan<?= $q !== '' ? ' untuk "<b>' . e($q) . '</b>"' : '' ?><?= $fStatus ? ' &middot; ' . e(HIBAH_STATUS[$fStatus]['label']) : '' ?><?= $fTahun ? ' &middot; tahun ' . $fTahun : '' ?><?= $fSumber ? ' &middot; ' . e($fSumber) : '' ?></p><?php endif; ?>

  <?php if (!$hasil): ?>
    <?= sel_kosong($semua ? 'Tidak ada hibah yang cocok' : 'Belum ada data hibah', $semua ? 'Coba kata kunci lain atau hapus filter.' : 'Klik "Tambah hibah" untuk mencatat hibah pertama.') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-hibah">
    <thead><tr><th class="kolom-no">No</th><th>Nama Hibah</th><th>Sumber</th><th>Tahun</th><th>Ketua Pengusul</th><th class="angka">Total Diusulkan</th><th class="angka">Total Disetujui</th><th>Status</th><th>Link Dokumen</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($hasil as $i => $d): $st = (string) ($d['status'] ?? 'diusulkan'); ?>
      <tr>
        <td class="kolom-no"><?= $i + 1 ?></td>
        <td class="kiri hibah-nama"><b><?= e($d['nama'] ?? '') ?></b><?php if (($d['keterangan'] ?? '') !== ''): ?><span class="sub"><?= e(mb_strimwidth((string) $d['keterangan'], 0, 140, '…')) ?></span><?php endif; ?></td>
        <td class="tengah"><?= e($d['sumber'] ?? '-') ?></td>
        <td class="tengah"><?= !empty($d['tahun']) ? (int) $d['tahun'] : '-' ?></td>
        <td class="tengah"><?= e($d['ketua'] ?? '-') ?></td>
        <td class="angka uang"><?= e(hibah_rp($d['diusulkan'] ?? null)) ?></td>
        <td class="angka uang"><?= isset($d['disetujui']) && $d['disetujui'] !== null ? e(hibah_rp($d['disetujui'])) : '<span class="lemah">-</span>' ?></td>
        <td class="tengah"><?= lencana_dari(HIBAH_STATUS, $st) ?></td>
        <td class="tengah nowrap"><?php if (($d['tautan'] ?? '') !== ''): ?><a href="<?= e($d['tautan']) ?>" target="_blank" rel="noopener" class="chip-dok chip-link"><?= ikon('tautan', 12) ?> Buka</a><?php else: ?><span class="lemah">-</span><?php endif; ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <button class="btn btn-garis btn-ikon" type="button" title="Edit" data-buka="tiraiHibah" data-judul="Edit hibah"
                  data-isi='<?= e(json_encode(['id' => $d['id'], 'nama' => $d['nama'] ?? '', 'sumber' => $d['sumber'] ?? '', 'tahun' => (string) ($d['tahun'] ?? ''), 'ketua' => $d['ketua'] ?? '',
                      'diusulkan' => isset($d['diusulkan']) ? (string) (int) round((float) $d['diusulkan']) : '', 'disetujui' => isset($d['disetujui']) && $d['disetujui'] !== null ? (string) (int) round((float) $d['disetujui']) : '',
                      'status' => $st, 'tautan' => $d['tautan'] ?? '', 'keterangan' => $d['keterangan'] ?? ''])) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" class="sebaris" data-konfirmasi="Hapus hibah <?= e($d['nama'] ?? '') ?>? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?>
            <input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <input type="hidden" name="f_q" value="<?= e($q) ?>"><input type="hidden" name="f_tahun" value="<?= $fTahun ?: '' ?>"><input type="hidden" name="f_status" value="<?= e($fStatus) ?>"><input type="hidden" name="f_sumber" value="<?= e($fSumber) ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="5" class="kanan"><b>Jumlah (<?= count($hasil) ?> hibah)</b></td><td class="angka uang"><b><?= e(hibah_rp($tot['diusulkan'])) ?></b></td><td class="angka uang"><b><?= e(hibah_rp($tot['disetujui'])) ?></b></td><td colspan="3" class="no-cetak"></td></tr></tfoot>
  </table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiHibah" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <input type="hidden" name="f_q" value="<?= e($q) ?>"><input type="hidden" name="f_tahun" value="<?= $fTahun ?: '' ?>"><input type="hidden" name="f_status" value="<?= e($fStatus) ?>"><input type="hidden" name="f_sumber" value="<?= e($fSumber) ?>">
      <div class="modal-kepala"><h2>Hibah</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="hbNama">Nama hibah <span class="wajib">*</span></label>
          <input type="text" id="hbNama" name="nama" maxlength="250" required placeholder="Contoh: Hibah Penelitian Dosen Pemula"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="hbSumber">Sumber <span class="wajib">*</span></label>
            <input type="text" id="hbSumber" name="sumber" maxlength="150" required list="hbSumberSaran" placeholder="Pilih atau ketik sumber dana">
            <datalist id="hbSumberSaran"><?php foreach (array_unique(array_merge(HIBAH_SUMBER_SARAN, $daftarSumber)) as $sm): ?><option value="<?= e($sm) ?>"><?php endforeach; ?></datalist></div>
          <div class="form-baris"><label class="fl" for="hbTahun">Tahun <span class="wajib">*</span></label>
            <input type="number" id="hbTahun" name="tahun" min="2000" max="2100" required></div>
        </div>
        <div class="form-baris"><label class="fl" for="hbKetua">Ketua pengusul <span class="wajib">*</span></label>
          <input type="text" id="hbKetua" name="ketua" maxlength="150" required placeholder="Nama ketua pengusul"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="hbUsul">Total diusulkan (Rp)</label>
            <input type="text" id="hbUsul" name="diusulkan" data-uang inputmode="numeric" maxlength="20" placeholder="0"></div>
          <div class="form-baris"><label class="fl" for="hbSetuju">Total disetujui (Rp)</label>
            <input type="text" id="hbSetuju" name="disetujui" data-uang inputmode="numeric" maxlength="20" placeholder="Isi setelah ada keputusan"></div>
        </div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="hbStatus">Status <span class="wajib">*</span></label>
            <select id="hbStatus" name="status"><?php foreach (HIBAH_STATUS as $k => $st): ?><option value="<?= $k ?>"><?= e($st['label']) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="hbTautan">Link dokumen</label>
            <input type="url" id="hbTautan" name="tautan" maxlength="1000" placeholder="https://drive.google.com/…"></div>
        </div>
        <div class="form-baris"><label class="fl" for="hbKet">Keterangan</label>
          <textarea id="hbKet" name="keterangan" rows="3" maxlength="2000" placeholder="Catatan singkat, skema, atau tahapan hibah"></textarea></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<?= hibah_ttd_cetak() ?>
