<?php
$modul = DOKUMEN_MODUL[$h];
$kode = $modul['kode'];
$saya = user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $balik = static function () use ($h): void {
        kembali_ke($h, array_filter(['q' => post('f_q') ?: null, 'kategori' => post('f_kategori') ?: null, 'tahun' => post('f_tahun') ?: null]));
    };
    $daftar = dokumen_semua($kode, true);
    $id = post('id');
    $indeks = null;
    foreach ($daftar as $i => $d) {
        if (($d['id'] ?? '') === $id) {
            $indeks = $i;
        }
    }

    if ($op === 'simpan') {
        $lama = $indeks !== null ? $daftar[$indeks] : null;
        $tautan = trim(mb_substr(post('tautan'), 0, 1000));
        $tahun = (int) post('tahun');
        $data = [
            'judul'      => mb_substr(trim(post('judul')), 0, 250),
            'kategori'   => array_key_exists(post('kategori'), DOKUMEN_KATEGORI) ? post('kategori') : 'lainnya',
            'nomor'      => mb_substr(trim(post('nomor')), 0, 120),
            'tahun'      => $tahun >= 1990 && $tahun <= 2100 ? $tahun : null,
            'tanggal'    => tanggal_sah(post('tanggal')) ? post('tanggal') : '',
            'penerbit'   => mb_substr(trim(post('penerbit')), 0, 150),
            'kata_kunci' => mb_substr(trim(post('kata_kunci')), 0, 300),
            'keterangan' => mb_substr(trim(post('keterangan')), 0, 2000),
            'tautan'     => $tautan,
        ];
        $galat = [];
        if ($data['judul'] === '') {
            $galat[] = 'Judul dokumen wajib diisi.';
        }
        if ($tautan !== '' && !tautan_sah($tautan)) {
            $galat[] = 'Link dokumen harus berupa alamat http:// atau https://.';
        }
        $adaBerkasBaru = (($_FILES['berkas']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE);
        $hapusBerkasLama = $lama && post('hapus_berkas') === '1';
        $tetapAdaBerkas = $lama && !empty($lama['berkas']) && !$hapusBerkasLama;
        if (!$adaBerkasBaru && !$tetapAdaBerkas && $tautan === '') {
            $galat[] = 'Unggah berkas atau isi link dokumen (minimal salah satu).';
        }
        $unggah = [];
        if (!$galat && $adaBerkasBaru) {
            $unggah = dokumen_unggah($kode, $_FILES['berkas']);
            if (isset($unggah['galat'])) {
                $galat[] = $unggah['galat'];
            }
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            $balik();
        }
        $kini = date('Y-m-d H:i:s');
        $entri = ($lama ?? ['id' => bin2hex(random_bytes(6)), 'dibuat' => $kini, 'dibuat_oleh' => $saya['nama'], 'berkas' => null, 'berkas_asli' => '', 'ext' => '', 'ukuran' => 0])
            + [];
        $entri = array_merge($entri, $data, ['diubah' => $kini, 'diubah_oleh' => $saya['nama']]);
        if (isset($unggah['file'])) {
            if (!empty($lama['berkas'])) {
                dokumen_hapus_berkas($kode, $lama['berkas']);
            }
            $entri['berkas'] = $unggah['file'];
            $entri['berkas_asli'] = $unggah['asli'];
            $entri['ext'] = $unggah['ext'];
            $entri['ukuran'] = $unggah['ukuran'];
        } elseif ($hapusBerkasLama) {
            dokumen_hapus_berkas($kode, $lama['berkas']);
            $entri['berkas'] = null;
            $entri['berkas_asli'] = '';
            $entri['ext'] = '';
            $entri['ukuran'] = 0;
        }
        if ($indeks !== null) {
            $daftar[$indeks] = $entri;
        } else {
            $daftar[] = $entri;
        }
        if (!dokumen_simpan_indeks($kode, $daftar)) {
            if (isset($unggah['file'])) {
                dokumen_hapus_berkas($kode, $unggah['file']);
            }
            flash('Dokumen gagal disimpan karena folder uploads/dokumen tidak dapat ditulisi di server.', 'galat');
            $balik();
        }
        catat_log($lama ? 'ubah_dokumen' : 'tambah_dokumen', 'dokumen_' . $kode, 0, $entri['judul']);
        flash('Dokumen <b>' . e($entri['judul']) . '</b> ' . ($lama ? 'diperbarui.' : 'ditambahkan.'));
        $balik();
    }

    if ($op === 'hapus' && $indeks !== null) {
        $d = $daftar[$indeks];
        array_splice($daftar, $indeks, 1);
        if (dokumen_simpan_indeks($kode, $daftar)) {
            dokumen_hapus_berkas($kode, $d['berkas'] ?? null);
            catat_log('hapus_dokumen', 'dokumen_' . $kode, 0, $d['judul'] ?? '');
            flash('Dokumen <b>' . e($d['judul'] ?? '') . '</b> dihapus.');
        } else {
            flash('Dokumen gagal dihapus.', 'galat');
        }
        $balik();
    }
    flash('Dokumen tidak ditemukan.', 'galat');
    $balik();
}

$semua = dokumen_semua($kode);
$q = trim(mb_substr((string) ($_GET['q'] ?? ''), 0, 100));
$fKategori = array_key_exists($_GET['kategori'] ?? '', DOKUMEN_KATEGORI) ? $_GET['kategori'] : '';
$fTahun = (int) ($_GET['tahun'] ?? 0);
$daftarTahun = array_values(array_unique(array_filter(array_map(static function ($d) { return (int) ($d['tahun'] ?? 0); }, $semua))));
rsort($daftarTahun);
$hitungKategori = array_count_values(array_map(static function ($d) { return $d['kategori'] ?? 'lainnya'; }, $semua));
$hasil = array_values(array_filter($semua, static function ($d) use ($q, $fKategori, $fTahun) {
    return dokumen_cocok($d, $q) && ($fKategori === '' || ($d['kategori'] ?? '') === $fKategori) && (!$fTahun || (int) ($d['tahun'] ?? 0) === $fTahun);
}));
usort($hasil, static function ($a, $b) {
    return [(int) ($b['tahun'] ?? 0), $b['diubah'] ?? ''] <=> [(int) ($a['tahun'] ?? 0), $a['diubah'] ?? ''];
});
$filter = array_filter(['q' => $q ?: null, 'kategori' => $fKategori ?: null, 'tahun' => $fTahun ?: null]);

$tandai = static function (string $teks) use ($q): string {
    $aman = e($teks);
    if ($q === '') {
        return $aman;
    }
    foreach (array_filter(preg_split('/\s+/u', $q)) as $kata) {
        $aman = preg_replace('/(' . preg_quote(e($kata), '/') . ')/iu', '<mark>$1</mark>', $aman);
    }
    return $aman;
};
$warnaJenis = ['pdf' => 'merah', 'doc' => 'biru', 'docx' => 'biru', 'xls' => 'hijau', 'xlsx' => 'hijau', 'ppt' => 'emas', 'pptx' => 'emas'];
$subjudul = 'Dokumen ' . $modul['nama'];
if ($kode === 'rapat') {
    echo rapat_navigasi($h);
}
?>

<div class="kartu dok-kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('catatan', 18) ?> Dokumen <?= e($modul['nama']) ?></h2></div>
    <div class="aksi no-cetak">
      <button class="btn btn-garis btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak daftar</button>
      <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiDokumen" data-reset data-judul="Tambah dokumen" data-isi='{"id":"","kategori":"kebijakan","tahun":"<?= date('Y') ?>"}'><?= ikon('tambah', 14) ?> Tambah dokumen</button>
    </div>
  </div>

  <form method="get" class="dok-cari no-cetak">
    <input type="hidden" name="h" value="<?= e($h) ?>">
    <div class="dok-cari-kotak"><?= ikon('cari', 17) ?><input type="search" name="q" value="<?= e($q) ?>" placeholder="Cari judul, nomor, kata kunci, penerbit, atau isi keterangan…" autocomplete="off"></div>
    <select name="tahun" data-auto-kirim><option value="">Semua tahun</option><?php foreach ($daftarTahun as $t): ?><option value="<?= $t ?>"<?= $fTahun === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select>
    <?php if ($fKategori): ?><input type="hidden" name="kategori" value="<?= e($fKategori) ?>"><?php endif; ?>
    <button class="btn btn-utama" type="submit"><?= ikon('cari', 15) ?> Cari</button>
    <?php if ($filter): ?><a class="btn btn-hantu" href="<?= e(url($h)) ?>">Hapus filter</a><?php endif; ?>
  </form>

  <div class="dok-kategori no-cetak">
    <a href="<?= e(url($h, array_filter(['q' => $q ?: null, 'tahun' => $fTahun ?: null]))) ?>" class="<?= $fKategori === '' ? 'aktif' : '' ?>">Semua <b><?= count($semua) ?></b></a>
    <?php foreach (DOKUMEN_KATEGORI as $k => $label): if (empty($hitungKategori[$k])) continue; ?>
      <a href="<?= e(url($h, array_filter(['q' => $q ?: null, 'tahun' => $fTahun ?: null, 'kategori' => $k]))) ?>" class="<?= $fKategori === $k ? 'aktif' : '' ?>"><?= e($label) ?> <b><?= (int) $hitungKategori[$k] ?></b></a>
    <?php endforeach; ?>
  </div>

  <?php if ($filter): ?><p class="dok-ringkas"><?= count($hasil) ?> dokumen ditemukan<?= $q !== '' ? ' untuk "<b>' . e($q) . '</b>"' : '' ?><?= $fKategori ? ' &middot; ' . e(DOKUMEN_KATEGORI[$fKategori]) : '' ?><?= $fTahun ? ' &middot; tahun ' . $fTahun : '' ?></p><?php endif; ?>

  <?php if (!$hasil): ?>
    <?= sel_kosong($semua ? 'Tidak ada dokumen yang cocok' : 'Belum ada dokumen', $semua ? 'Coba kata kunci lain atau hapus filter.' : 'Klik "Tambah dokumen" untuk mengunggah pedoman, SOP, panduan, atau tautan dokumen pertama.') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-dokumen">
    <thead><tr><th>No</th><th>Dokumen</th><th>Kategori</th><th>Tahun</th><th>Berkas / Link</th><th>Diperbarui</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($hasil as $i => $d):
        $urlBerkas = !empty($d['berkas']) ? base_url('index.php?' . http_build_query(['h' => 'berkas', 'j' => 'dok_' . $kode, 'n' => $d['berkas']])) : '';
        $kata = array_filter(array_map('trim', explode(',', (string) ($d['kata_kunci'] ?? '')))); ?>
      <tr>
        <td class="tengah"><?= $i + 1 ?></td>
        <td class="kiri dok-judul">
          <b><?= $tandai((string) ($d['judul'] ?? '')) ?></b>
          <?php if (($d['nomor'] ?? '') !== '' || ($d['penerbit'] ?? '') !== '' || ($d['tanggal'] ?? '') !== ''): ?>
            <span class="sub"><?= implode(' &middot; ', array_filter([($d['nomor'] ?? '') !== '' ? 'No. ' . $tandai($d['nomor']) : '', ($d['penerbit'] ?? '') !== '' ? $tandai($d['penerbit']) : '', ($d['tanggal'] ?? '') !== '' ? e(tanggal_id($d['tanggal'])) : ''])) ?></span>
          <?php endif; ?>
          <?php if (($d['keterangan'] ?? '') !== ''): ?><span class="sub dok-ket"><?= $tandai(mb_strimwidth($d['keterangan'], 0, 220, '…')) ?></span><?php endif; ?>
          <?php if ($kata): ?><span class="dok-kata"><?php foreach ($kata as $kk): ?><i><?= $tandai($kk) ?></i><?php endforeach; ?></span><?php endif; ?>
        </td>
        <td class="tengah"><span class="dok-kat dok-kat-<?= e($d['kategori'] ?? 'lainnya') ?>"><?= e(DOKUMEN_KATEGORI[$d['kategori'] ?? 'lainnya'] ?? 'Lainnya') ?></span></td>
        <td class="tengah"><?= !empty($d['tahun']) ? (int) $d['tahun'] : '-' ?></td>
        <td class="tengah nowrap">
          <?php if ($urlBerkas): ?><a href="<?= e($urlBerkas) ?>" target="_blank" rel="noopener" class="dok-berkas"><?= lencana(DOKUMEN_JENIS[$d['ext']] ?? strtoupper((string) $d['ext']), $warnaJenis[$d['ext']] ?? 'abu') ?><small><?= angka(((int) ($d['ukuran'] ?? 0)) / 1048576, ((int) ($d['ukuran'] ?? 0)) < 1048576 ? 2 : 1) ?> MB</small></a><?php endif; ?>
          <?php if (($d['tautan'] ?? '') !== ''): ?><a href="<?= e($d['tautan']) ?>" target="_blank" rel="noopener" class="chip-dok chip-link"><?= ikon('tautan', 12) ?> Link</a><?php endif; ?>
        </td>
        <td class="tengah nowrap kecil"><?= e(tanggal_pendek(substr((string) ($d['diubah'] ?? ''), 0, 10))) ?><span class="sub"><?= e($d['diubah_oleh'] ?? '') ?></span></td>
        <td class="no-cetak"><div class="aksi-baris">
          <?php if ($urlBerkas): ?>
            <a class="btn btn-garis btn-ikon" href="<?= e($urlBerkas) ?>" target="_blank" rel="noopener" title="Lihat berkas"><?= ikon('mata', 15) ?></a>
            <a class="btn btn-garis btn-ikon" href="<?= e($urlBerkas . '&unduh=1') ?>" title="Unduh berkas"><?= ikon('unduh', 15) ?></a>
          <?php endif; ?>
          <button class="btn btn-garis btn-ikon" type="button" title="Edit" data-buka="tiraiDokumen" data-judul="Edit dokumen"
                  data-isi='<?= e(json_encode(['id' => $d['id'], 'judul' => $d['judul'] ?? '', 'kategori' => $d['kategori'] ?? 'lainnya', 'nomor' => $d['nomor'] ?? '', 'tahun' => (string) ($d['tahun'] ?? ''),
                      'tanggal' => $d['tanggal'] ?? '', 'penerbit' => $d['penerbit'] ?? '', 'kata_kunci' => $d['kata_kunci'] ?? '', 'keterangan' => $d['keterangan'] ?? '', 'tautan' => $d['tautan'] ?? '',
                      'berkas_teks' => !empty($d['berkas']) ? ($d['berkas_asli'] ?: 'Berkas tersimpan') : 'Belum ada berkas', 'hapus_berkas' => '0'])) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" class="sebaris" data-konfirmasi="Hapus dokumen <?= e($d['judul'] ?? '') ?>? Berkasnya juga akan dihapus."><?= csrf_field() ?>
            <input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= e($d['id']) ?>">
            <input type="hidden" name="f_q" value="<?= e($q) ?>"><input type="hidden" name="f_kategori" value="<?= e($fKategori) ?>"><input type="hidden" name="f_tahun" value="<?= $fTahun ?: '' ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiDokumen" hidden>
  <div class="modal">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <input type="hidden" name="f_q" value="<?= e($q) ?>"><input type="hidden" name="f_kategori" value="<?= e($fKategori) ?>"><input type="hidden" name="f_tahun" value="<?= $fTahun ?: '' ?>">
      <div class="modal-kepala"><h2>Dokumen</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="dkJudul">Judul dokumen <span class="wajib">*</span></label>
          <input type="text" id="dkJudul" name="judul" maxlength="250" required placeholder="Contoh: Pedoman Pengelolaan Keuangan Universitas"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="dkKategori">Kategori <span class="wajib">*</span></label>
            <select id="dkKategori" name="kategori"><?= pilihan(DOKUMEN_KATEGORI, 'kebijakan') ?></select></div>
          <div class="form-baris"><label class="fl" for="dkNomor">Nomor dokumen</label>
            <input type="text" id="dkNomor" name="nomor" maxlength="120" placeholder="Contoh: 012/SK/REK/UIS/2026"></div>
        </div>
        <div class="form-grid-3">
          <div class="form-baris"><label class="fl" for="dkTahun">Tahun</label><input type="number" id="dkTahun" name="tahun" min="1990" max="2100"></div>
          <div class="form-baris"><label class="fl" for="dkTanggal">Tanggal terbit</label><input type="date" id="dkTanggal" name="tanggal"></div>
          <div class="form-baris"><label class="fl" for="dkPenerbit">Penerbit / unit</label><input type="text" id="dkPenerbit" name="penerbit" maxlength="150" placeholder="Contoh: Rektorat"></div>
        </div>
        <div class="form-baris"><label class="fl" for="dkKata">Kata kunci <small class="lemah">(pisahkan dengan koma)</small></label>
          <input type="text" id="dkKata" name="kata_kunci" maxlength="300" placeholder="Contoh: akreditasi, standar 5, keuangan, anggaran"></div>
        <div class="form-baris"><label class="fl" for="dkKet">Ringkasan / keterangan</label>
          <textarea id="dkKet" name="keterangan" rows="3" maxlength="2000" placeholder="Isi singkat dokumen, kegunaan, atau butir akreditasi yang didukung"></textarea></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="dkBerkas">Unggah berkas</label>
            <input type="file" id="dkBerkas" name="berkas" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png">
            <p class="bantuan">PDF, Word, Excel, PowerPoint, JPG, PNG. Maks. 20 MB.<br>Tersimpan: <b data-teks="berkas_teks">Belum ada berkas</b></p>
            <label class="cek kecil"><input type="hidden" name="hapus_berkas" value="0"><input type="checkbox" name="hapus_berkas" value="1"> Hapus berkas tersimpan</label></div>
          <div class="form-baris"><label class="fl" for="dkTautan">Link dokumen</label>
            <input type="url" id="dkTautan" name="tautan" maxlength="1000" placeholder="https://drive.google.com/…">
            <p class="bantuan">Isi berkas, link, atau keduanya.</p></div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<div class="cetak-saja dok-cetak-kaki">Daftar dokumen <?= e($modul['nama']) ?> &middot; dicetak <?= e(tanggal_id(date('Y-m-d'))) ?></div>
