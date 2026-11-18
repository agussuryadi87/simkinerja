<?php
$masuk = user();
$token = (string) ($_GET['t'] ?? '');
$selesai = null;
$galat = [];
$isian = [];
$fotoMaks = 3;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $isian = $_POST;
    $nama = mb_substr(trim(post('nama')), 0, 100);
    $unit = mb_substr(trim(post('unit')), 0, 100);
    $kontak = mb_substr(trim(post('kontak')), 0, 60);
    $jenis = post('jenis') === 'prasarana' ? 'prasarana' : 'sarana';
    $ruangId = (int) post('ruang_id');
    if ($ruangId && (int) val('SELECT COUNT(*) FROM aset_ruang WHERE id = ? AND aktif = 1', [$ruangId], 0) === 0) {
        $ruangId = 0;
    }
    $barangId = $jenis === 'sarana' ? (int) post('barang_id') : 0;
    if ($barangId) {
        $brg = row('SELECT id, ruang_id FROM aset_barang WHERE id = ?', [$barangId]);
        if (!$brg) {
            $barangId = 0;
        } elseif (!$ruangId && $brg['ruang_id']) {
            $ruangId = (int) $brg['ruang_id'];
        }
    }
    $namaBarang = mb_substr(trim(post('nama_barang')), 0, 120);
    $judul = mb_substr(trim(post('judul')), 0, 200);
    if ($jenis === 'sarana' && !$barangId && $namaBarang !== '' && $judul === '') {
        $judul = $namaBarang . ' rusak';
    }
    $uraian = mb_substr(trim(post_teks('uraian')), 0, 5000);
    if ($jenis === 'sarana' && !$barangId && $namaBarang !== '') {
        $uraian = 'Barang: ' . $namaBarang . ($uraian !== '' ? "\n" . $uraian : '');
    }
    $data = [
        'jenis'         => $jenis,
        'ruang_id'      => $ruangId ?: null,
        'barang_id'     => $barangId ?: null,
        'bagian'        => $jenis === 'prasarana' ? mb_substr(trim(post('bagian')), 0, 100) : '',
        'judul'         => $judul,
        'uraian'        => $uraian,
        'tingkat'       => array_key_exists(post('tingkat'), ASET_TINGKAT) ? post('tingkat') : 'ringan',
        'status'        => 'dilaporkan',
        'tanggal_lapor' => date('Y-m-d'),
        'pelapor'       => mb_substr($nama . ($unit !== '' ? ' · ' . $unit : ''), 0, 150),
    ];
    if (post('situs') !== '') {
        $galat[] = 'Laporan tidak dapat diproses.';
    }
    if ($nama === '') {
        $galat[] = 'Nama pelapor wajib diisi.';
    }
    if ($unit === '') {
        $galat[] = 'Unit / bagian wajib diisi.';
    }
    if ($jenis === 'sarana' && !$barangId && $namaBarang === '') {
        $galat[] = 'Pilih barang yang rusak atau tuliskan nama barangnya.';
    }
    if ($jenis === 'prasarana' && !$ruangId) {
        $galat[] = 'Pilih ruangan / lokasi yang rusak.';
    }
    if ($data['judul'] === '') {
        $galat[] = 'Ringkasan kerusakan wajib diisi.';
    }
    if ($uraian === '') {
        $galat[] = 'Uraian kerusakan wajib diisi.';
    }
    if ($kontak !== '' && !preg_match('/^[0-9+()\-\s.@a-zA-Z_]{5,60}$/', $kontak)) {
        $galat[] = 'Nomor HP / email tidak sah.';
    }
    $jumlahFoto = 0;
    if (isset($_FILES['foto']['name'])) {
        $jumlahFoto = is_array($_FILES['foto']['name']) ? count(array_filter($_FILES['foto']['name'], static function ($n) { return $n !== ''; })) : ($_FILES['foto']['name'] !== '' ? 1 : 0);
    }
    if ($jumlahFoto > $fotoMaks) {
        $galat[] = 'Foto maksimal ' . $fotoMaks . ' berkas.';
    }
    if (!$galat && !aset_publik_boleh_kirim(ip_pengunjung())) {
        $galat[] = 'Terlalu banyak laporan dari perangkat ini. Silakan coba lagi sekitar 1 jam lagi.';
    }
    if (!$galat) {
        $id = insert('aset_kerusakan', $data + ['nomor' => aset_nomor_kerusakan($jenis), 'dibuat_oleh' => $masuk ? uid() : null]);
        if ($jumlahFoto > 0) {
            aset_simpan_foto($_FILES['foto'], 'kerusakan_id', $id, $data['judul']);
        }
        $tokenBaru = bin2hex(random_bytes(16));
        aset_publik_ubah('kerusakan-publik.json', static function (array &$d) use ($id, $nama, $unit, $kontak, $tokenBaru): void {
            $d[(string) $id] = ['nama' => $nama, 'unit' => $unit, 'kontak' => $kontak, 'waktu' => date('Y-m-d H:i:s'), 'token' => $tokenBaru];
        });
        catat_log('lapor_publik_aset_kerusakan', 'aset_kerusakan', $id, $data['judul'] . ' oleh ' . $nama);
        redirect(base_url('index.php?h=lapor_rusak&t=' . $tokenBaru . '&baru=1'));
    }
}

if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    foreach (aset_publik_baca('kerusakan-publik.json') as $pid => $info) {
        if (is_array($info) && hash_equals((string) ($info['token'] ?? ''), $token)) {
            $selesai = row('SELECT k.*, r.nama AS ruang_nama, b.nama AS barang_nama FROM aset_kerusakan k LEFT JOIN aset_ruang r ON r.id = k.ruang_id LEFT JOIN aset_barang b ON b.id = k.barang_id WHERE k.id = ?', [(int) $pid]);
            break;
        }
    }
}

$v = static function (string $k, string $bawaan = '') use ($isian): string {
    return isset($isian[$k]) && is_string($isian[$k]) ? $isian[$k] : $bawaan;
};
$css = base_url('assets/css/app.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/app.css') ? filemtime(APP_ROOT . '/assets/css/app.css') : APP_VER);
$jenisPilih = $v('jenis', 'sarana') === 'prasarana' ? 'prasarana' : 'sarana';
?>
<!DOCTYPE html>
<html lang="id" class="tema-kantor">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Laporan Kerusakan · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/tema-kantor.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/tema-kantor.css') ? filemtime(APP_ROOT . '/assets/css/tema-kantor.css') : APP_VER)) ?>">
</head>
<body class="publik">
  <header class="publik-merek">
    <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="Logo <?= APP_INST ?>">
    <div><b><?= APP_INST ?></b><span><?= APP_UNIT ?></span></div>
  </header>

  <main class="publik-isi">
    <?php if ($selesai): $st = ASET_STATUS_RUSAK[$selesai['status']] ?? ['label' => $selesai['status'], 'warna' => 'abu']; $tg = ASET_TINGKAT[$selesai['tingkat']] ?? ['label' => $selesai['tingkat'], 'warna' => 'abu']; ?>
      <section class="publik-kartu">
        <?php if (!empty($_GET['baru'])): ?>
          <div class="notif notif-ok"><?= ikon('centang', 17) ?><div><b>Laporan terkirim.</b> Simpan atau tandai halaman ini untuk memantau penanganannya.</div></div>
        <?php endif; ?>
        <h1>Status laporan kerusakan</h1>
        <p class="sub">Nomor <code><?= e($selesai['nomor']) ?></code> · dilaporkan <?= e(tanggal_id($selesai['tanggal_lapor'])) ?></p>
        <div class="publik-status"><span>Status saat ini</span><?= lencana($st['label'], $st['warna']) ?></div>
        <div class="info-aset">
          <div><span>Ringkasan</span><b><?= e($selesai['judul']) ?></b></div>
          <div><span>Jenis</span><b><?= $selesai['jenis'] === 'sarana' ? 'Barang / peralatan' : 'Bangunan / fasilitas' ?></b></div>
          <?php if ($selesai['barang_nama']): ?><div><span>Barang</span><b><?= e($selesai['barang_nama']) ?></b></div><?php endif; ?>
          <div><span>Lokasi</span><b><?= e(($selesai['ruang_nama'] ?: '-') . ($selesai['bagian'] !== '' ? ' · ' . $selesai['bagian'] : '')) ?></b></div>
          <div><span>Tingkat</span><b><?= lencana($tg['label'], $tg['warna']) ?></b></div>
          <div><span>Pelapor</span><b><?= e($selesai['pelapor'] ?: '-') ?></b></div>
          <?php if ($selesai['tanggal_selesai']): ?><div><span>Tanggal selesai</span><b><?= e(tanggal_id($selesai['tanggal_selesai'])) ?></b></div><?php endif; ?>
        </div>
        <?php if (!empty($selesai['tindak_lanjut'])): ?><h3>Tindak lanjut dari Bidang Sarana dan Prasarana</h3><p><?= nl2br(e($selesai['tindak_lanjut'])) ?></p><?php endif; ?>
        <div class="publik-kaki-aksi">
          <a class="btn btn-garis" href="<?= e(base_url('index.php?h=lapor_rusak')) ?>"><?= ikon('tambah', 15) ?> Laporkan kerusakan lain</a>
        </div>
      </section>
    <?php else: ?>
      <section class="publik-kartu">
        <h1>Formulir Pelaporan Kerusakan</h1>
        <p class="sub">Bidang Sarana dan Prasarana <?= APP_UNIT_PENDEK ?>. Laporkan barang, peralatan, atau fasilitas yang rusak; laporan akan diperiksa dan ditangani oleh petugas.</p>
        <?php if ($token !== ''): ?>
          <div class="notif notif-warn"><?= ikon('seru', 17) ?><div>Tautan status tidak ditemukan. Silakan periksa kembali tautan yang Anda simpan.</div></div>
        <?php endif; ?>
        <?php if ($galat): ?>
          <div class="notif notif-galat"><?= ikon('silang', 17) ?><div><?= implode('<br>', array_map('e', $galat)) ?></div></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('index.php?h=lapor_rusak')) ?>" enctype="multipart/form-data" autocomplete="on" id="formRusak">
          <?= csrf_field() ?>
          <div class="publik-jebak" aria-hidden="true"><label>Situs <input type="text" name="situs" tabindex="-1" autocomplete="off"></label></div>
          <h2>Data pelapor</h2>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="rNama">Nama lengkap <span class="wajib">*</span></label>
              <input type="text" id="rNama" name="nama" maxlength="100" required value="<?= e($v('nama', (string) ($masuk['nama'] ?? ''))) ?>"></div>
            <div class="form-baris"><label class="fl" for="rUnit">Unit / bagian / prodi <span class="wajib">*</span></label>
              <input type="text" id="rUnit" name="unit" maxlength="100" required value="<?= e($v('unit')) ?>" placeholder="Contoh: Prodi Akuntansi"></div>
            <div class="form-baris"><label class="fl" for="rKontak">No. HP / WhatsApp / email</label>
              <input type="text" id="rKontak" name="kontak" maxlength="60" value="<?= e($v('kontak')) ?>" placeholder="Untuk konfirmasi bila diperlukan"></div>
          </div>
          <h2>Yang rusak</h2>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="rJenis">Jenis kerusakan</label>
              <select id="rJenis" name="jenis"><option value="sarana"<?= $jenisPilih === 'sarana' ? ' selected' : '' ?>>Barang / peralatan (meja, AC, proyektor, komputer, dll.)</option><option value="prasarana"<?= $jenisPilih === 'prasarana' ? ' selected' : '' ?>>Bangunan / fasilitas (atap, dinding, listrik, air, jaringan, dll.)</option></select></div>
            <div class="form-baris"><label class="fl" for="rRuang">Ruangan / lokasi <span class="wajib" data-wajib-prasarana>*</span></label>
              <select id="rRuang" name="ruang_id"><?= opsi_ruang((int) $v('ruang_id', '0'), '— Pilih ruangan —') ?></select></div>
          </div>
          <div class="form-grid" data-bagian-sarana>
            <div class="form-baris"><label class="fl" for="rBarang">Barang yang rusak</label>
              <select id="rBarang" name="barang_id"><?= opsi_barang((int) $v('barang_id', '0'), '— Tidak ada di daftar —') ?></select></div>
            <div class="form-baris"><label class="fl" for="rNamaBarang">Atau tuliskan nama barang</label>
              <input type="text" id="rNamaBarang" name="nama_barang" maxlength="120" value="<?= e($v('nama_barang')) ?>" placeholder="Bila barang tidak ada di daftar"></div>
          </div>
          <div class="form-baris" data-bagian-prasarana><label class="fl" for="rBagian">Bagian yang rusak</label>
            <input type="text" id="rBagian" name="bagian" maxlength="100" value="<?= e($v('bagian')) ?>" placeholder="Contoh: atap, pintu, instalasi listrik"></div>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="rJudul">Ringkasan kerusakan <span class="wajib">*</span></label>
              <input type="text" id="rJudul" name="judul" maxlength="200" required value="<?= e($v('judul')) ?>" placeholder="Contoh: Proyektor tidak menyala"></div>
            <div class="form-baris"><label class="fl" for="rTingkat">Tingkat kerusakan</label>
              <select id="rTingkat" name="tingkat"><?php foreach (ASET_TINGKAT as $k => $d): ?><option value="<?= $k ?>" <?= $v('tingkat', 'ringan') === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
          </div>
          <div class="form-baris"><label class="fl" for="rUraian">Uraian kerusakan <span class="wajib">*</span></label>
            <textarea id="rUraian" name="uraian" rows="4" maxlength="5000" required placeholder="Jelaskan kondisi dan sejak kapan rusak"><?= e($v('uraian')) ?></textarea></div>
          <div class="form-baris"><label class="fl" for="rFoto">Foto kerusakan <small class="lemah">(opsional, maksimal <?= $fotoMaks ?> foto)</small></label>
            <input type="file" id="rFoto" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
          <button class="btn btn-utama btn-masuk" type="submit"><?= ikon('kirim', 16) ?> Kirim laporan</button>
        </form>
        <script nonce="<?= csp_nonce() ?>">
          (function () {
            var s = document.getElementById('rJenis');
            if (!s) return;
            var sar = document.querySelectorAll('[data-bagian-sarana]'), pra = document.querySelectorAll('[data-bagian-prasarana]'), wj = document.querySelectorAll('[data-wajib-prasarana]');
            var atur = function () {
              var p = s.value === 'prasarana';
              [].forEach.call(sar, function (e) { e.hidden = p; });
              [].forEach.call(pra, function (e) { e.hidden = !p; });
              [].forEach.call(wj, function (e) { e.hidden = !p; });
              var r = document.getElementById('rRuang'); if (r) r.required = p;
            };
            s.addEventListener('change', atur);
            atur();
          })();
        </script>
      </section>
    <?php endif; ?>
    <p class="publik-catatan">&copy; <?= date('Y') ?> Bidang Sarana dan Prasarana <?= APP_UNIT_PENDEK ?> · <?= APP_INST_PENDEK ?></p>
  </main>
</body>
</html>
