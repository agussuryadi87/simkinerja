<?php
$idBarang = (int) ($_GET['id'] ?? 0);
$b = $idBarang > 0 ? row('SELECT b.*, r.nama AS ruang_nama, r.kode AS ruang_kode FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE b.id = ?', [$idBarang]) : null;
$versi = static function (string $f): string {
    return base_url($f) . '?v=' . (is_file(APP_ROOT . '/' . $f) ? filemtime(APP_ROOT . '/' . $f) : APP_VER);
};
$isi = static function ($v): string {
    $v = trim((string) $v);
    return $v !== '' ? e($v) : '-';
};
?>
<!DOCTYPE html>
<html lang="id" class="tema-kantor">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title><?= $b ? e($b['nama']) . ' · ' : '' ?>Info Barang · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e($versi('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($versi('assets/css/tema-kantor.css')) ?>">
</head>
<body class="publik">
  <header class="publik-merek">
    <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="Logo <?= APP_INST ?>">
    <div><b><?= APP_INST ?></b><span><?= APP_UNIT ?></span></div>
  </header>

  <main class="publik-isi">
    <section class="publik-kartu">
      <?php if (!$b): ?>
        <h1>Barang tidak ditemukan</h1>
        <p class="sub">Label QR ini tidak terhubung dengan data inventaris. Silakan hubungi Bidang Sarana dan Prasarana.</p>
      <?php else: ?>
        <div class="info-barang-kepala">
          <div><span class="info-barang-label">Informasi Inventaris Barang</span>
            <h1><?= e($b['nama']) ?></h1>
            <p class="sub"><code><?= e($b['kode']) ?></code> · <?= e(aset_kategori(true)[$b['kategori']] ?? '-') ?></p></div>
          <div class="info-barang-status"><?= $b['status'] === 'aktif' ? aset_lencana_kondisi((string) $b['kondisi']) : lencana('Dihapuskan', 'abu') ?></div>
        </div>
        <div class="info-aset">
          <div><span>Lokasi / ruangan</span><b><?= $b['ruang_nama'] ? e($b['ruang_nama']) . ($b['ruang_kode'] ? ' (' . e($b['ruang_kode']) . ')' : '') : 'Belum ditempatkan' ?></b></div>
          <div><span>Posisi / rak</span><b><?= $isi($b['posisi']) ?></b></div>
          <div><span>Merek / tipe</span><b><?= $isi(trim($b['merek'] . ' ' . $b['model'])) ?></b></div>
          <div><span>Jumlah</span><b><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></b></div>
          <div><span>Tahun perolehan</span><b><?= $b['tahun'] ?: '-' ?></b></div>
          <div><span>Status</span><b><?= $b['status'] === 'aktif' ? 'Aktif digunakan' : 'Dihapuskan dari inventaris' ?></b></div>
        </div>
        <p class="info-barang-catatan"><?= ikon('info', 15) ?><span>Bila barang ini rusak atau hilang, silakan laporkan ke Bidang Sarana dan Prasarana <?= APP_UNIT_PENDEK ?> dengan menyebutkan kode <b><?= e($b['kode']) ?></b>.</span></p>
      <?php endif; ?>
      <div class="publik-kaki-aksi">
        <a class="btn btn-garis" href="<?= e(base_url('index.php?h=login')) ?>"><?= ikon('masuk', 15) ?> Masuk sebagai petugas</a>
      </div>
    </section>
    <p class="publik-catatan">&copy; <?= date('Y') ?> Bidang Sarana dan Prasarana <?= APP_UNIT_PENDEK ?> · <?= APP_INST_PENDEK ?></p>
  </main>
</body>
</html>
