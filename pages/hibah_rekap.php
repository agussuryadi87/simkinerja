<?php
$semua = hibah_semua();
$daftarTahun = array_values(array_unique(array_filter(array_map(static function ($d) { return (int) ($d['tahun'] ?? 0); }, $semua))));
rsort($daftarTahun);
$fTahun = (int) ($_GET['tahun'] ?? 0);
if ($fTahun && !in_array($fTahun, $daftarTahun, true)) {
    $fTahun = 0;
}
$data = array_values(array_filter($semua, static function ($d) use ($fTahun) {
    return !$fTahun || (int) ($d['tahun'] ?? 0) === $fTahun;
}));
$tot = hibah_total($data);
$persenDana = $tot['diusulkan'] > 0 ? $tot['disetujui'] / $tot['diusulkan'] * 100 : null;
$persenLolos = $tot['diputuskan'] > 0 ? $tot['didanai'] / $tot['diputuskan'] * 100 : null;
$subjudul = 'Rekap hibah' . ($fTahun ? ' · ' . $fTahun : '');

$perTahun = hibah_kelompok($semua, 'tahun');
ksort($perTahun);
$perSumber = hibah_kelompok($data, 'sumber');
uasort($perSumber, static function ($a, $b) { return hibah_total($b)['disetujui'] <=> hibah_total($a)['disetujui'] ?: count($b) <=> count($a); });
$perKetua = hibah_kelompok($data, 'ketua');
uasort($perKetua, static function ($a, $b) { return hibah_total($b)['disetujui'] <=> hibah_total($a)['disetujui'] ?: count($b) <=> count($a); });
$perStatus = [];
foreach (HIBAH_STATUS as $k => $st) {
    $perStatus[$k] = array_values(array_filter($data, static function ($d) use ($k) { return ($d['status'] ?? 'diusulkan') === $k; }));
}
$irisan = [];
foreach (HIBAH_STATUS as $k => $st) {
    $irisan[] = [$st['label'], count($perStatus[$k]), HIBAH_WARNA_DONAT[$k]];
}
$labelTahun = array_keys($perTahun);
$serieUsul = [];
$serieSetuju = [];
foreach ($perTahun as $kelompok) {
    $t = hibah_total($kelompok);
    $serieUsul[] = $t['diusulkan'];
    $serieSetuju[] = $t['disetujui'];
}
echo hibah_navigasi($h);
?>

<form method="get" class="saring no-cetak">
  <input type="hidden" name="h" value="<?= e($h) ?>">
  <div class="form-baris"><label class="fl" for="rkTahun">Tahun</label>
    <select id="rkTahun" name="tahun" data-auto-kirim><option value="">Semua tahun</option><?php foreach ($daftarTahun as $t): ?><option value="<?= $t ?>"<?= $fTahun === $t ? ' selected' : '' ?>><?= $t ?></option><?php endforeach; ?></select></div>
  <div class="kk-alat"><button class="btn btn-garis" type="button" data-cetak><?= ikon('cetak', 15) ?> Cetak rekap</button></div>
</form>

<?php if (!$semua): ?>
  <div class="kartu"><?= sel_kosong('Belum ada data hibah', 'Rekap akan tampil otomatis setelah hibah dicatat di menu Daftar Hibah.') ?></div>
<?php else: ?>

<div class="grid-stat">
  <div class="stat"><span class="ik"><?= ikon('hibah', 22) ?></span><div><div class="label">Jumlah hibah</div><div class="nilai"><?= angka($tot['jumlah']) ?> <small>usulan</small></div><div class="ket"><?= angka($tot['didanai']) ?> didanai</div></div></div>
  <div class="stat biru"><span class="ik"><?= ikon('kirim', 22) ?></span><div><div class="label">Total diusulkan</div><div class="nilai"><?= e(hibah_rp($tot['diusulkan'])) ?></div></div></div>
  <div class="stat emas"><span class="ik"><?= ikon('centang', 22) ?></span><div><div class="label">Total disetujui</div><div class="nilai"><?= e(hibah_rp($tot['disetujui'])) ?></div><div class="ket"><?= $persenDana === null ? '-' : angka($persenDana, 1) . '% dari dana diusulkan' ?></div></div></div>
  <div class="stat ungu"><span class="ik"><?= ikon('rekap', 22) ?></span><div><div class="label">Tingkat keberhasilan</div><div class="nilai"><?= $persenLolos === null ? '-' : angka($persenLolos, 0) . '<small>%</small>' ?></div><div class="ket"><?= angka($tot['didanai']) ?> dari <?= angka($tot['diputuskan']) ?> usulan yang sudah diputuskan</div></div></div>
</div>

<div class="aset-grafik">
  <section class="kartu">
    <div class="kartu-kepala"><div><h2>Dana per tahun</h2></div>
      <ul class="legenda-baris"><li><i style="background:#3a6ea5"></i>Diusulkan</li><li><i style="background:#2f9e6a"></i>Disetujui</li></ul></div>
    <div class="kartu-isi"><?= $labelTahun ? hibah_svg_batang(array_map('strval', $labelTahun), $serieUsul, $serieSetuju) : sel_kosong('Belum ada data') ?></div>
  </section>
  <section class="kartu">
    <div class="kartu-kepala"><div><h2>Komposisi status</h2></div></div>
    <div class="kartu-isi grafik-donat-bungkus">
      <?= aset_svg_donat($irisan, (string) $tot['jumlah'], 'hibah') ?>
      <ul class="legenda-aset">
        <?php foreach ($irisan as [$nama, $nilai, $warna]): ?>
          <li><i style="background:<?= $warna ?>"></i><span><?= e($nama) ?></span><b><?= angka($nilai) ?></b><em><?= $tot['jumlah'] ? round($nilai / $tot['jumlah'] * 100) : 0 ?>%</em></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>
</div>

<div class="kartu rekap-hibah">
  <div class="kartu-kepala"><div><h2><?= ikon('rekap', 18) ?> Rekap per tahun</h2><p class="ket">Seluruh tahun, tidak terpengaruh filter tahun</p></div></div>
  <div class="tabel-bungkus"><table class="tabel tabel-hibah">
    <thead><tr><th class="kolom-no">No</th><th>Tahun</th><th class="angka">Jumlah Hibah</th><th class="angka">Didanai</th><th class="angka">Total Diusulkan</th><th class="angka">Total Disetujui</th><th class="angka">% Disetujui</th></tr></thead>
    <tbody>
    <?php $no = 0; foreach (array_reverse($perTahun, true) as $thn => $kel): $t = hibah_total($kel); $no++; ?>
      <tr><td class="kolom-no"><?= $no ?></td><td class="tengah"><b><?= e((string) $thn) ?></b></td><td class="angka"><?= $t['jumlah'] ?></td><td class="angka"><?= $t['didanai'] ?></td>
        <td class="angka uang"><?= e(hibah_rp($t['diusulkan'])) ?></td><td class="angka uang"><?= e(hibah_rp($t['disetujui'])) ?></td>
        <td class="angka"><?= $t['diusulkan'] > 0 ? angka($t['disetujui'] / $t['diusulkan'] * 100, 1) . '%' : '-' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <?php $semuaTot = hibah_total($semua); ?>
    <tfoot><tr><td colspan="2" class="kanan"><b>Jumlah</b></td><td class="angka"><b><?= $semuaTot['jumlah'] ?></b></td><td class="angka"><b><?= $semuaTot['didanai'] ?></b></td>
      <td class="angka uang"><b><?= e(hibah_rp($semuaTot['diusulkan'])) ?></b></td><td class="angka uang"><b><?= e(hibah_rp($semuaTot['disetujui'])) ?></b></td>
      <td class="angka"><b><?= $semuaTot['diusulkan'] > 0 ? angka($semuaTot['disetujui'] / $semuaTot['diusulkan'] * 100, 1) . '%' : '-' ?></b></td></tr></tfoot>
  </table></div>
</div>

<div class="kartu rekap-hibah">
  <div class="kartu-kepala"><div><h2><?= ikon('rekap', 18) ?> Rekap per sumber<?= $fTahun ? ' · ' . $fTahun : '' ?></h2></div></div>
  <div class="tabel-bungkus"><table class="tabel tabel-hibah">
    <thead><tr><th class="kolom-no">No</th><th>Sumber</th><th class="angka">Jumlah Hibah</th><th class="angka">Didanai</th><th class="angka">Total Diusulkan</th><th class="angka">Total Disetujui</th><th class="angka">% Disetujui</th></tr></thead>
    <tbody>
    <?php $no = 0; foreach ($perSumber as $nm => $kel): $t = hibah_total($kel); $no++; ?>
      <tr><td class="kolom-no"><?= $no ?></td><td class="kiri"><b><?= e((string) $nm) ?></b></td><td class="angka"><?= $t['jumlah'] ?></td><td class="angka"><?= $t['didanai'] ?></td>
        <td class="angka uang"><?= e(hibah_rp($t['diusulkan'])) ?></td><td class="angka uang"><?= e(hibah_rp($t['disetujui'])) ?></td>
        <td class="angka"><?= $t['diusulkan'] > 0 ? angka($t['disetujui'] / $t['diusulkan'] * 100, 1) . '%' : '-' ?></td></tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2" class="kanan"><b>Jumlah</b></td><td class="angka"><b><?= $tot['jumlah'] ?></b></td><td class="angka"><b><?= $tot['didanai'] ?></b></td>
      <td class="angka uang"><b><?= e(hibah_rp($tot['diusulkan'])) ?></b></td><td class="angka uang"><b><?= e(hibah_rp($tot['disetujui'])) ?></b></td>
      <td class="angka"><b><?= $persenDana === null ? '-' : angka($persenDana, 1) . '%' ?></b></td></tr></tfoot>
  </table></div>
</div>

<div class="aset-grafik">
  <section class="kartu rekap-hibah">
    <div class="kartu-kepala"><div><h2><?= ikon('rekap', 18) ?> Rekap per status</h2></div></div>
    <div class="tabel-bungkus"><table class="tabel tabel-hibah">
      <thead><tr><th>Status</th><th class="angka">Jumlah</th><th class="angka">Total Diusulkan</th><th class="angka">Total Disetujui</th></tr></thead>
      <tbody>
      <?php foreach (HIBAH_STATUS as $k => $st): $t = hibah_total($perStatus[$k]); ?>
        <tr><td class="kiri"><?= lencana($st['label'], $st['warna']) ?></td><td class="angka"><?= $t['jumlah'] ?></td><td class="angka uang"><?= e(hibah_rp($t['diusulkan'])) ?></td><td class="angka uang"><?= $t['jumlah'] ? e(hibah_rp($t['disetujui'])) : '-' ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>
  <section class="kartu rekap-hibah">
    <div class="kartu-kepala"><div><h2><?= ikon('rekap', 18) ?> Rekap per ketua pengusul<?= $fTahun ? ' · ' . $fTahun : '' ?></h2></div></div>
    <div class="tabel-bungkus"><table class="tabel tabel-hibah">
      <thead><tr><th>Ketua Pengusul</th><th class="angka">Hibah</th><th class="angka">Total Disetujui</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($perKetua, 0, 12, true) as $nm => $kel): $t = hibah_total($kel); ?>
        <tr><td class="kiri"><b><?= e((string) $nm) ?></b></td><td class="angka"><?= $t['jumlah'] ?></td><td class="angka uang"><?= e(hibah_rp($t['disetujui'])) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if (count($perKetua) > 12): ?><p class="kecil lemah" style="padding:8px 16px">Menampilkan 12 ketua pengusul teratas dari <?= count($perKetua) ?> orang.</p><?php endif; ?>
  </section>
</div>

<?php endif; ?>
<?= hibah_ttd_cetak() ?>
