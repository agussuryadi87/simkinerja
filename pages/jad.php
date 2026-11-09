<?php
$rekap = jad_rekap();
$tahun = JAD_TAHUN;
$awal = reset($tahun);
$akhir = end($tahun);
$sebelum = $tahun[count($tahun) - 2];
$skr = $rekap[$akhir];
$lalu = $rekap[$sebelum];
$label = array_map('strval', $tahun);
$btnEdit = '<a class="btn btn-garis btn-kecil no-cetak" href="' . e(url('jad_data', ['tahun' => $akhir])) . '">' . ikon('sunting', 14) . ' Edit data</a>';
$jafungCol = ['Tenaga Pendidik / Belum Jafung' => '#e08a3c', 'Asisten Ahli' => '#2a9d8f', 'Lektor' => '#5b8fd6', 'Lektor Kepala' => '#8e6bbf'];

$delta = static function (int $baru, int $lama): string {
    $d = $baru - $lama;
    if ($d === 0) {
        return '<em class="tetap">&ndash; tetap</em>';
    }
    return '<em class="' . ($d > 0 ? 'naik' : 'turun') . '">' . ($d > 0 ? '&#9650; +' : '&#9660; ') . angka($d) . '</em>';
};
$persen = static function (int $bagian, int $total): string {
    return $total ? number_format($bagian / $total * 100, 1, ',', '.') . '%' : '-';
};
$kolom = static function (array $r, string $kunci) {
    return array_values(array_map(static function ($x) use ($kunci) { return $x[$kunci]; }, $r));
};
$warnaTahun = ['#5b8fd6', '#2a9d8f', '#8e6bbf', '#e08a3c', '#1f4e9a'];
$warnaBatang = array_map(static function ($i) use ($warnaTahun) { return $warnaTahun[$i % count($warnaTahun)]; }, array_keys(JAD_TAHUN));
$jafungBaris = static function (array $r, string $nm): int {
    return $r['jafung'][$nm];
};
?>
<div class="jad-kpi">
  <div><span>Jumlah dosen <?= $akhir ?></span><b><?= angka($skr['total']) ?></b><small><?= $delta($skr['total'], $lalu['total']) ?> dari <?= $sebelum ?></small></div>
  <div><span>Berpendidikan S3</span><b><?= angka($skr['s3']) ?></b><small><?= $delta($skr['s3'], $lalu['s3']) ?> dari <?= $sebelum ?> &middot; <?= $persen($skr['s3'], $skr['total']) ?></small></div>
  <div><span>Lektor Kepala</span><b><?= angka($skr['jafung']['Lektor Kepala'] + $skr['jafung']['Guru Besar']) ?></b><small><?= $delta($skr['jafung']['Lektor Kepala'] + $skr['jafung']['Guru Besar'], $lalu['jafung']['Lektor Kepala'] + $lalu['jafung']['Guru Besar']) ?> dari <?= $sebelum ?> &middot; <?= $persen($skr['jafung']['Lektor Kepala'], $skr['total']) ?></small></div>
  <div><span>Tersertifikasi</span><b><?= angka($skr['serdos']) ?></b><small><?= $delta($skr['serdos'], $lalu['serdos']) ?> dari <?= $sebelum ?> &middot; <?= $persen($skr['serdos'], $skr['total']) ?></small></div>
</div>

<div class="jad-grid">
  <section class="jad-panel">
    <header><span class="no">1</span><h2>Jumlah Dosen per Tahun</h2>
      <ul class="legenda-baris"><?php foreach ($tahun as $i => $th): ?><li><i style="background:<?= $warnaBatang[$i] ?>"></i><?= $th ?></li><?php endforeach; ?><li><i class="tren" style="background:#b8913a"></i>Tren</li></ul><?= $btnEdit ?></header>
    <div class="grafik-dua"><div class="grafik"><?= jad_svg_batang($label, [['Jumlah Dosen', $warnaBatang, $kolom($rekap, 'total')]], 'Jumlah dosen per tahun', 130, '#b8913a', 440) ?></div><?= jad_donat(array_map(static function ($t, $i) use ($rekap, $warnaTahun) { return [(string) $t, $warnaTahun[$i % count($warnaTahun)], $rekap[$t]['total']]; }, $tahun, array_keys($tahun)), angka($skr['total']), 'Dosen ' . $akhir, 'Sebaran per tahun', 'Sebaran jumlah dosen per tahun') ?></div>
    <table class="tabel-padat">
      <thead><tr><th>Tahun</th><th>Jumlah Dosen</th><th>Perubahan</th></tr></thead>
      <tbody>
      <?php $prev = null; foreach ($rekap as $t => $r): ?>
        <tr class="<?= $t === $akhir ? 'terbaru' : '' ?>"><td><?= $t ?></td><td><b><?= angka($r['total']) ?></b></td><td><?= $prev === null ? '<em class="tetap">&ndash;</em>' : $delta($r['total'], $prev) ?></td></tr>
      <?php $prev = $r['total']; endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="jad-panel">
    <header><span class="no">2</span><h2>Pendidikan Dosen per Tahun</h2>
      <ul class="legenda-baris"><li><i style="background:#2a9d8f"></i>S2</li><li><i style="background:#8e6bbf"></i>S3</li><li><i class="tren" style="background:#b8913a"></i>Tren</li></ul><?= $btnEdit ?></header>
    <div class="grafik-dua"><div class="grafik"><?= jad_svg_batang($label, [['S2', '#2a9d8f', $kolom($rekap, 's2')], ['S3', '#8e6bbf', $kolom($rekap, 's3')]], 'Pendidikan dosen per tahun', 130, '#b8913a', 440) ?></div><?= jad_donat([['S2', '#2a9d8f', $skr['s2']], ['S3', '#8e6bbf', $skr['s3']]], angka($skr['total']), 'Dosen ' . $akhir, 'Komposisi ' . $akhir, 'Komposisi pendidikan dosen ' . $akhir) ?></div>
    <table class="tabel-padat">
      <thead><tr><th>Tahun</th><th>S2</th><th>S3</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rekap as $t => $r): ?>
        <tr class="<?= $t === $akhir ? 'terbaru' : '' ?>"><td><?= $t ?></td><td><?= angka($r['s2']) ?></td><td><?= angka($r['s3']) ?></td><td><b><?= angka($r['total']) ?></b></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="jad-panel">
    <header><span class="no">3</span><h2>Jabatan Fungsional Dosen per Tahun</h2>
      <ul class="legenda-baris"><?php foreach ($jafungCol as $nm => $w): ?><li><i style="background:<?= $w ?>"></i><?= e($nm === 'Tenaga Pendidik / Belum Jafung' ? 'Belum Jafung' : $nm) ?></li><?php endforeach; ?><li><i class="tren" style="background:#b8913a"></i>Tren</li></ul><?= $btnEdit ?></header>
    <div class="grafik-dua"><div class="grafik"><?= jad_svg_batang($label, array_map(static function ($nm) use ($rekap, $jafungCol) {
        return [$nm, $jafungCol[$nm], array_map(static function ($r) use ($nm) { return $r['jafung'][$nm]; }, array_values($rekap))];
    }, array_keys($jafungCol)), 'Jabatan fungsional dosen per tahun', 130, '#b8913a', 440) ?></div><?= jad_donat(array_map(static function ($nm) use ($skr, $jafungCol) { return [$nm === 'Tenaga Pendidik / Belum Jafung' ? 'Belum Jafung' : $nm, $jafungCol[$nm], $skr['jafung'][$nm]]; }, array_keys($jafungCol)), angka($skr['total']), 'Dosen ' . $akhir, 'Komposisi ' . $akhir, 'Komposisi jabatan fungsional ' . $akhir) ?></div>
    <table class="tabel-padat">
      <thead><tr><th>Tahun</th><?php foreach ($jafungCol as $nm => $w): ?><th><?= e($nm) ?></th><?php endforeach; ?><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rekap as $t => $r): ?>
        <tr class="<?= $t === $akhir ? 'terbaru' : '' ?>"><td><?= $t ?></td><?php foreach ($jafungCol as $nm => $w): ?><td><?= angka($r['jafung'][$nm]) ?></td><?php endforeach; ?><td><b><?= angka($r['total']) ?></b></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>

  <section class="jad-panel">
    <header><span class="no">4</span><h2>Sertifikasi Dosen per Tahun</h2>
      <ul class="legenda-baris"><li><i style="background:#2a9d8f"></i>Tersertifikasi</li><li><i style="background:#e08a3c"></i>Belum</li><li><i class="tren" style="background:#b8913a"></i>Tren</li></ul><?= $btnEdit ?></header>
    <div class="grafik-dua"><div class="grafik"><?= jad_svg_batang($label, [['Tersertifikasi', '#2a9d8f', $kolom($rekap, 'serdos')], ['Belum Tersertifikasi', '#e08a3c', $kolom($rekap, 'belum')]], 'Sertifikasi dosen per tahun', 130, '#b8913a', 440) ?></div><?= jad_donat([['Tersertifikasi', '#2a9d8f', $skr['serdos']], ['Belum Tersertifikasi', '#e08a3c', $skr['belum']]], $persen($skr['serdos'], $skr['total']), 'Tersertifikasi', 'Komposisi ' . $akhir, 'Komposisi sertifikasi dosen ' . $akhir) ?></div>
    <table class="tabel-padat">
      <thead><tr><th>Tahun</th><th>Tersertifikasi</th><th>Belum Tersertifikasi</th><th>Total</th><th>Persentase</th></tr></thead>
      <tbody>
      <?php foreach ($rekap as $t => $r): ?>
        <tr class="<?= $t === $akhir ? 'terbaru' : '' ?>"><td><?= $t ?></td><td><?= angka($r['serdos']) ?></td><td><?= angka($r['belum']) ?></td><td><b><?= angka($r['total']) ?></b></td><td><?= $persen($r['serdos'], $r['total']) ?></td></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </section>
</div>

<?php
$rk = jad_rekap_karyawan();
$warnaKet = ['Karyawan + Dosen' => '#8e6bbf', 'Karyawan' => '#2a9d8f', 'Belum diisi' => '#e08a3c'];
$pendek = ['Pimpinan' => 'Pimpinan', 'Kepala / Kabid / Koordinator' => 'Kepala/Kabid', 'Staf' => 'Staf', 'Cleaning Service' => 'CS', 'Belum diisi' => 'Belum diisi'];
$btnKaryawan = '<a class="btn btn-garis btn-kecil no-cetak" href="' . e(url('jad_karyawan')) . '">' . ikon('grup', 14) . ' Kelola data</a>';
$legendaKet = '<ul class="legenda-baris">' . implode('', array_map(static function ($nm, $w) { return '<li><i style="background:' . $w . '"></i>' . e($nm) . '</li>'; }, array_keys($warnaKet), $warnaKet)) . '</ul>';
$warnaUrut = static function (int $n): array {
    return array_map(static function ($i) { return JAD_WARNA_DONAT[$i % count(JAD_WARNA_DONAT)]; }, $n > 0 ? range(0, $n - 1) : []);
};
$legendaWarna = static function (array $nama) use ($warnaUrut): string {
    $w = $warnaUrut(count($nama));
    return '<ul class="legenda-baris">' . implode('', array_map(static function ($nm, $i) use ($w) { return '<li><i style="background:' . $w[$i] . '"></i>' . e($nm) . '</li>'; }, $nama, array_keys($nama))) . '</ul>';
};
$seri = static function (array $baris) use ($warnaKet): array {
    return array_map(static function ($nm) use ($baris, $warnaKet) {
        return [$nm, $warnaKet[$nm], array_values(array_map(static function ($r) use ($nm) { return $r[$nm]; }, $baris))];
    }, array_keys($warnaKet));
};
?>
<h2 class="jad-subjudul"><?= ikon('grup', 18) ?> Rekap Data Karyawan <span><?= angka($rk['total']) ?> karyawan &middot; <?= angka($rk['ket']['Karyawan + Dosen']) ?> Karyawan + Dosen &middot; <?= angka($rk['ket']['Karyawan']) ?> Karyawan<?= $rk['ket']['Belum diisi'] ? ' &middot; ' . angka($rk['ket']['Belum diisi']) . ' belum diisi' : '' ?></span></h2>
<?php if (!$rk['total']): ?>
  <div class="kartu"><?= sel_kosong('Belum ada data karyawan', '', $btnKaryawan) ?></div>
<?php else: ?>
<div class="jad-grid">
  <section class="jad-panel">
    <header><span class="no">5</span><h2>Karyawan per Posisi</h2><?= $legendaWarna(array_values(array_map(static function ($r) { return $r['nama']; }, $rk['posisi']))) ?><?= $btnKaryawan ?></header>
    <div class="grafik-dua"><div class="grafik"><?= jad_svg_batang(array_values(array_map(static function ($r) { return $r['nama']; }, $rk['posisi'])), [['Karyawan', $warnaUrut(count($rk['posisi'])), array_values(array_map(static function ($r) { return $r['total']; }, $rk['posisi']))]], 'Karyawan per posisi', 130, '', 440) ?></div><?= jad_donat(array_values(array_map(static function ($r, $i) { return [$r['nama'], JAD_WARNA_DONAT[$i % count(JAD_WARNA_DONAT)], $r['total']]; }, $rk['posisi'], range(0, max(count($rk['posisi']) - 1, 0)))), angka($rk['total']), 'Karyawan', 'Sebaran per posisi', 'Sebaran karyawan per posisi') ?></div>
    <table class="tabel-padat">
      <thead><tr><th>Posisi</th><th>Karyawan + Dosen</th><th>Karyawan</th><th>Belum diisi</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rk['posisi'] as $id => $r): ?>
        <tr><td><a href="<?= e(url('jad_karyawan', ['pos' => $id ?: '-'])) ?>"><?= e($r['nama']) ?></a></td><td><?= angka($r['ket']['Karyawan + Dosen']) ?></td><td><?= angka($r['ket']['Karyawan']) ?></td><td><?= angka($r['ket']['Belum diisi']) ?></td><td><b><?= angka($r['total']) ?></b></td></tr>
      <?php endforeach; ?>
        <tr class="terbaru"><td><b>Jumlah</b></td><td><b><?= angka($rk['ket']['Karyawan + Dosen']) ?></b></td><td><b><?= angka($rk['ket']['Karyawan']) ?></b></td><td><b><?= angka($rk['ket']['Belum diisi']) ?></b></td><td><b><?= angka($rk['total']) ?></b></td></tr>
      </tbody>
    </table>
  </section>

  <section class="jad-panel">
    <header><span class="no">6</span><h2>Karyawan per Kelompok Jabatan</h2><?= $legendaWarna(array_keys($rk['kelompok'])) ?><?= $btnKaryawan ?></header>
    <div class="grafik-dua"><div class="grafik"><?= jad_svg_batang(array_values(array_map(static function ($nm) use ($pendek) { return $pendek[$nm]; }, array_keys($rk['kelompok']))), [['Karyawan', $warnaUrut(count($rk['kelompok'])), array_values(array_map(static function ($r) { return $r['total']; }, $rk['kelompok']))]], 'Karyawan per kelompok jabatan', 130, '', 440) ?></div><?= jad_donat(array_values(array_map(static function ($nm, $i) use ($rk) { return [$nm, JAD_WARNA_DONAT[$i % count(JAD_WARNA_DONAT)], $rk['kelompok'][$nm]['total']]; }, array_keys($rk['kelompok']), range(0, max(count($rk['kelompok']) - 1, 0)))), angka($rk['total']), 'Karyawan', 'Sebaran per kelompok', 'Sebaran karyawan per kelompok jabatan') ?></div>
    <table class="tabel-padat">
      <thead><tr><th>Kelompok jabatan</th><th>Karyawan + Dosen</th><th>Karyawan</th><th>Belum diisi</th><th>Total</th></tr></thead>
      <tbody>
      <?php foreach ($rk['kelompok'] as $nm => $r): ?>
        <tr><td><?= e($nm) ?></td><td><?= angka($r['Karyawan + Dosen']) ?></td><td><?= angka($r['Karyawan']) ?></td><td><?= angka($r['Belum diisi']) ?></td><td><b><?= angka($r['total']) ?></b></td></tr>
      <?php endforeach; ?>
        <tr class="terbaru"><td><b>Jumlah</b></td><td><b><?= angka($rk['ket']['Karyawan + Dosen']) ?></b></td><td><b><?= angka($rk['ket']['Karyawan']) ?></b></td><td><b><?= angka($rk['ket']['Belum diisi']) ?></b></td><td><b><?= angka($rk['total']) ?></b></td></tr>
      </tbody>
    </table>
  </section>
</div>
<?php endif; ?>