<?php
$masuk = user();
$token = (string) ($_GET['t'] ?? '');
$selesai = null;
$galat = [];
$isian = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $isian = $_POST;
    $nama = mb_substr(trim(post('nama')), 0, 100);
    $unit = mb_substr(trim(post('unit')), 0, 100);
    $kontak = mb_substr(trim(post('kontak')), 0, 60);
    $data = [
        'tanggal'        => date('Y-m-d'),
        'nama_barang'    => mb_substr(trim(post('nama_barang')), 0, 200),
        'kategori'       => array_key_exists(post('kategori'), aset_kategori()) ? post('kategori') : 'lainnya',
        'jumlah'         => max(1, min(100000, (int) post('jumlah', '1'))),
        'satuan'         => mb_substr(trim(post('satuan')) ?: 'unit', 0, 20),
        'estimasi_harga' => aset_angka_atau_null('estimasi_harga'),
        'ruang_id'       => null,
        'alasan'         => mb_substr(trim(post_teks('alasan')), 0, 5000),
        'prioritas'      => array_key_exists(post('prioritas'), ASET_PRIORITAS) ? post('prioritas') : 'sedang',
        'pemohon'        => mb_substr($nama . ($unit !== '' ? ' · ' . $unit : ''), 0, 150),
    ];
    $ruang = (int) post('ruang_id');
    if ($ruang && (int) val('SELECT COUNT(*) FROM aset_ruang WHERE id = ? AND aktif = 1', [$ruang], 0) > 0) {
        $data['ruang_id'] = $ruang;
    }
    if (post('situs') !== '') {
        $galat[] = 'Pengajuan tidak dapat diproses.';
    }
    if ($nama === '') {
        $galat[] = 'Nama pemohon wajib diisi.';
    }
    if ($unit === '') {
        $galat[] = 'Unit / bagian wajib diisi.';
    }
    if ($data['nama_barang'] === '') {
        $galat[] = 'Nama barang wajib diisi.';
    }
    if ($data['alasan'] === '') {
        $galat[] = 'Alasan / kebutuhan wajib diisi.';
    }
    if ($kontak !== '' && !preg_match('/^[0-9+()\-\s.@a-zA-Z_]{5,60}$/', $kontak)) {
        $galat[] = 'Nomor HP / email tidak sah.';
    }
    if (!$galat && !aset_publik_boleh_kirim(ip_pengunjung())) {
        $galat[] = 'Terlalu banyak pengajuan dari perangkat ini. Silakan coba lagi sekitar 1 jam lagi.';
    }
    if (!$galat) {
        $id = insert('aset_pengajuan', $data + ['nomor' => aset_nomor_dokumen('aset_pengajuan', 'PBR', 'tanggal', $data['tanggal']), 'dibuat_oleh' => $masuk ? uid() : null]);
        $tokenBaru = bin2hex(random_bytes(16));
        aset_publik_ubah('pengajuan-publik.json', static function (array &$d) use ($id, $nama, $unit, $kontak, $tokenBaru): void {
            $d[(string) $id] = ['nama' => $nama, 'unit' => $unit, 'kontak' => $kontak, 'waktu' => date('Y-m-d H:i:s'), 'token' => $tokenBaru];
        });
        catat_log('ajukan_publik_aset_pengajuan', 'aset_pengajuan', $id, $data['nama_barang'] . ' oleh ' . $nama);
        redirect(base_url('index.php?h=ajukan_barang&t=' . $tokenBaru . '&baru=1'));
    }
}

if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    foreach (aset_publik_baca('pengajuan-publik.json') as $pid => $info) {
        if (is_array($info) && hash_equals((string) ($info['token'] ?? ''), $token)) {
            $selesai = row('SELECT p.*, r.nama AS ruang_nama FROM aset_pengajuan p LEFT JOIN aset_ruang r ON r.id = p.ruang_id WHERE p.id = ?', [(int) $pid]);
            break;
        }
    }
}

$v = static function (string $k, string $bawaan = '') use ($isian): string {
    return isset($isian[$k]) && is_string($isian[$k]) ? $isian[$k] : $bawaan;
};
$css = base_url('assets/css/app.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/app.css') ? filemtime(APP_ROOT . '/assets/css/app.css') : APP_VER);
?>
<!DOCTYPE html>
<html lang="id" class="tema-kantor">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Pengajuan Barang · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
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
    <?php if ($selesai): $st = ASET_STATUS_PENGAJUAN[$selesai['status']] ?? ['label' => $selesai['status'], 'warna' => 'abu']; ?>
      <section class="publik-kartu">
        <?php if (!empty($_GET['baru'])): ?>
          <div class="notif notif-ok"><?= ikon('centang', 17) ?><div><b>Pengajuan terkirim.</b> Simpan atau tandai halaman ini untuk memantau statusnya.</div></div>
        <?php endif; ?>
        <h1>Status pengajuan barang</h1>
        <p class="sub">Nomor <code><?= e($selesai['nomor']) ?></code> · diajukan <?= e(tanggal_id($selesai['tanggal'])) ?></p>
        <div class="publik-status"><span>Status saat ini</span><?= lencana($st['label'], $st['warna']) ?></div>
        <div class="info-aset">
          <div><span>Barang</span><b><?= e($selesai['nama_barang']) ?></b></div>
          <div><span>Jumlah</span><b><?= angka($selesai['jumlah']) ?> <?= e($selesai['satuan']) ?></b></div>
          <div><span>Pemohon</span><b><?= e($selesai['pemohon'] ?: '-') ?></b></div>
          <div><span>Ruangan tujuan</span><b><?= e($selesai['ruang_nama'] ?: '-') ?></b></div>
          <?php if ($selesai['tanggal_keputusan']): ?><div><span>Tanggal keputusan</span><b><?= e(tanggal_id($selesai['tanggal_keputusan'])) ?></b></div><?php endif; ?>
        </div>
        <?php if ($selesai['catatan_keputusan']): ?><h3>Catatan dari Bidang Sarana dan Prasarana</h3><p><?= nl2br(e($selesai['catatan_keputusan'])) ?></p><?php endif; ?>
        <div class="publik-kaki-aksi">
          <a class="btn btn-garis" href="<?= e(base_url('index.php?h=ajukan_barang')) ?>"><?= ikon('tambah', 15) ?> Ajukan barang lain</a>
        </div>
      </section>
    <?php else: ?>
      <section class="publik-kartu">
        <h1>Formulir Pengajuan Barang</h1>
        <p class="sub">Bidang Sarana dan Prasarana <?= APP_UNIT_PENDEK ?>. Isi kebutuhan barang Anda; pengajuan akan diperiksa dan diputuskan oleh petugas.</p>
        <?php if ($token !== ''): ?>
          <div class="notif notif-warn"><?= ikon('seru', 17) ?><div>Tautan status tidak ditemukan. Silakan periksa kembali tautan yang Anda simpan.</div></div>
        <?php endif; ?>
        <?php if ($galat): ?>
          <div class="notif notif-galat"><?= ikon('silang', 17) ?><div><?= implode('<br>', array_map('e', $galat)) ?></div></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('index.php?h=ajukan_barang')) ?>" autocomplete="on">
          <?= csrf_field() ?>
          <div class="publik-jebak" aria-hidden="true"><label>Situs <input type="text" name="situs" tabindex="-1" autocomplete="off"></label></div>
          <h2>Data pemohon</h2>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="aNama">Nama lengkap <span class="wajib">*</span></label>
              <input type="text" id="aNama" name="nama" maxlength="100" required value="<?= e($v('nama', (string) ($masuk['nama'] ?? ''))) ?>"></div>
            <div class="form-baris"><label class="fl" for="aUnit">Unit / bagian / prodi <span class="wajib">*</span></label>
              <input type="text" id="aUnit" name="unit" maxlength="100" required value="<?= e($v('unit')) ?>" placeholder="Contoh: Prodi Akuntansi"></div>
            <div class="form-baris"><label class="fl" for="aKontak">No. HP / WhatsApp / email</label>
              <input type="text" id="aKontak" name="kontak" maxlength="60" value="<?= e($v('kontak')) ?>" placeholder="Untuk konfirmasi bila diperlukan"></div>
          </div>
          <h2>Barang yang dibutuhkan</h2>
          <div class="form-baris"><label class="fl" for="aBarang">Nama barang <span class="wajib">*</span></label>
            <input type="text" id="aBarang" name="nama_barang" maxlength="200" required value="<?= e($v('nama_barang')) ?>" placeholder="Contoh: Kursi kuliah lipat"></div>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="aKat">Kategori</label><select id="aKat" name="kategori"><?= pilihan(aset_kategori(), $v('kategori', 'lainnya')) ?></select></div>
            <div class="form-baris"><label class="fl" for="aPri">Prioritas</label><select id="aPri" name="prioritas"><?php foreach (ASET_PRIORITAS as $k => $d): ?><option value="<?= $k ?>" <?= $v('prioritas', 'sedang') === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
            <div class="form-baris"><label class="fl" for="aJml">Jumlah</label><input type="number" id="aJml" name="jumlah" min="1" max="100000" value="<?= e($v('jumlah', '1')) ?>"></div>
            <div class="form-baris"><label class="fl" for="aSat">Satuan</label><input type="text" id="aSat" name="satuan" maxlength="20" value="<?= e($v('satuan', 'unit')) ?>"></div>
            <div class="form-baris"><label class="fl" for="aHarga">Perkiraan harga per unit (Rp)</label><input type="text" id="aHarga" name="estimasi_harga" inputmode="numeric" maxlength="18" value="<?= e($v('estimasi_harga')) ?>" placeholder="Bila diketahui"></div>
            <div class="form-baris"><label class="fl" for="aRuang">Ruangan tujuan</label><select id="aRuang" name="ruang_id"><?= opsi_ruang((int) $v('ruang_id', '0'), '— Belum ditentukan —') ?></select></div>
          </div>
          <div class="form-baris"><label class="fl" for="aAlasan">Alasan / kebutuhan <span class="wajib">*</span></label>
            <textarea id="aAlasan" name="alasan" rows="4" maxlength="5000" required placeholder="Jelaskan untuk apa barang dibutuhkan"><?= e($v('alasan')) ?></textarea></div>
          <button class="btn btn-utama btn-masuk" type="submit"><?= ikon('kirim', 16) ?> Kirim pengajuan</button>
        </form>
      </section>
    <?php endif; ?>
    <p class="publik-catatan">&copy; <?= date('Y') ?> Bidang Sarana dan Prasarana <?= APP_UNIT_PENDEK ?> · <?= APP_INST_PENDEK ?></p>
  </main>
</body>
</html>
