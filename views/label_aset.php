<?php
$kartu = [];
foreach ($label as $b) {
    $n = $perUnit ? max(1, min(200, (int) $b['jumlah'])) : 1;
    $qr = qr_svg(aset_url_qr((int) $b['id']), 2);
    for ($i = 1; $i <= $n; $i++) {
        $kartu[] = [$b, $qr, $n > 1 ? $i . '/' . $n : ''];
    }
}
$kartu = array_slice($kartu, 0, 600);
$css = base_url('assets/css/app.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/app.css') ? filemtime(APP_ROOT . '/assets/css/app.css') : APP_VER);
$paramUnit = $_GET;
unset($paramUnit['h']);
$paramUnit['per_unit'] = $perUnit ? null : 1;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Label QR Barang · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body class="label-aset-hal">
  <div class="label-alat no-cetak">
    <div><b>Label QR barang</b> · <?= count($label) ?> barang, <?= count($kartu) ?> label<?= count($label) >= 300 ? ' (maksimal 300 barang, persempit saringan)' : '' ?></div>
    <div class="label-alat-tombol">
      <a class="btn btn-garis btn-kecil" href="<?= e(url('aset_barang', $paramUnit)) ?>"><?= $perUnit ? 'Satu label per barang' : 'Satu label per unit' ?></a>
      <button class="btn btn-utama btn-kecil" type="button" id="tombolCetakLabel"><?= ikon('cetak', 15) ?> Cetak</button>
    </div>
  </div>
  <?php if (!$kartu): ?>
    <p class="label-kosong">Tidak ada barang untuk dicetak.</p>
  <?php endif; ?>
  <div class="label-lembar">
    <?php foreach ($kartu as [$b, $qr, $urut]): ?>
      <div class="label-aset">
        <div class="label-aset-qr"><?= $qr ?></div>
        <div class="label-aset-teks">
          <div class="label-aset-inst"><img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt=""><span><?= e(APP_INST) ?> · <?= e(APP_UNIT_PENDEK) ?></span></div>
          <b><?= e($b['nama']) ?></b>
          <code><?= e($b['kode']) ?><?= $urut !== '' ? ' · ' . e($urut) : '' ?></code>
          <span><?= e($b['ruang_nama'] ?: 'Belum ditempatkan') ?><?= $b['tahun'] ? ' · ' . (int) $b['tahun'] : '' ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<script nonce="<?= csp_nonce() ?>">
  document.getElementById('tombolCetakLabel').addEventListener('click', function () { window.print(); });
</script>
</body>
</html>
