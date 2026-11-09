<?php
$dataCetak = kk_baca($jenisKK, $tahun);
$css = base_url('assets/css/app.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/app.css') ? filemtime(APP_ROOT . '/assets/css/app.css') : APP_VER);
$bulanPendek = [];
for ($b = 1; $b <= 12; $b++) {
    $bulanPendek[$b] = NAMA_BULAN[$b];
}
$daftarRuang = $ruangId ? [$petaRuang[$ruangId]] : array_values($petaRuang);
$daftarAc = $barangId ? [$unitAc[$barangId]] : array_values($unitAc);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e(KK_HALAMAN[$h]) ?> <?= $tahun ?> · <?= APP_NAME ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
</head>
<body class="kk-cetak-hal">
  <div class="label-alat no-cetak">
    <div><b>Kartu Kontrol · <?= e(KK_HALAMAN[$h]) ?></b> · Tahun <?= $tahun ?></div>
    <div class="label-alat-tombol"><button class="btn btn-utama btn-kecil" type="button" id="tombolCetakKK"><?= ikon('cetak', 15) ?> Cetak</button></div>
  </div>

<?php if ($jenisKK === 'ruang'): ?>
  <?php foreach ($daftarRuang as $r): $d = $dataCetak[(string) $r['id']] ?? []; ?>
  <section class="kk-lembar">
    <?= kk_kop() ?>
    <h1 class="kk-judul">KARTU KONTROL</h1>
    <table class="kk-identitas"><tr><td>Ruangan</td><td>:</td><td><?= e($r['nama']) ?></td></tr><tr><td>PJ Ruangan</td><td>:</td><td><?= e(kk_pj_ruang($r, kk_penugasan($jenisKK))) ?></td></tr><tr><td>Tahun</td><td>:</td><td><?= $tahun ?></td></tr></table>
    <table class="kk-grid kk-grid-ruang">
      <thead>
        <tr><th rowspan="2" class="kk-no">No</th><th rowspan="2" class="kk-uraian">Uraian</th><th colspan="48">Bulan</th></tr>
        <tr><?php foreach ($bulanPendek as $nm): ?><th colspan="4"><?= e($nm) ?></th><?php endforeach; ?></tr>
        <tr><th></th><th></th><?php for ($b = 1; $b <= 12; $b++): foreach (KK_ROMAWI as $rm): ?><th class="kk-sel"><?= $rm ?></th><?php endforeach; endfor; ?></tr>
      </thead>
      <tbody>
      <?php foreach (KK_URAIAN_RUANG as $no => $uraian): ?>
        <tr><td class="kk-no"><?= $no ?></td><td class="kk-uraian"><?= e($uraian) ?></td>
          <?php for ($b = 1; $b <= 12; $b++): for ($m = 1; $m <= 4; $m++): ?><td class="kk-sel"><?= e((string) ($d['nilai'][(string) $no][$b . '-' . $m] ?? '')) ?></td><?php endfor; endfor; ?></tr>
      <?php endforeach; ?>
        <tr><td></td><td class="kk-uraian"><i>Paraf kontrol Kabid</i></td><?php for ($b = 1; $b <= 12; $b++): ?><td colspan="4" class="kk-paraf"><?= !empty($d['kontrol'][(string) $b]) ? '&#10003; ' . e(tanggal_pendek(substr($d['kontrol'][(string) $b]['waktu'], 0, 10))) : '' ?></td><?php endfor; ?></tr>
      </tbody>
    </table>
    <div class="kk-bawah">
      <div class="kk-indikator"><b>Indikator Nilai</b><?php foreach (KK_NILAI as $kn => $ket): ?><div><?= $kn ?> : <?= e($ket) ?></div><?php endforeach; ?></div>
      <?= kk_ttd() ?>
    </div>
  </section>
  <?php endforeach; ?>

<?php elseif ($jenisKK === 'sarana'): ?>
  <?php foreach ($daftarRuang as $r): $d = $dataCetak[(string) $r['id']] ?? []; $barang = kk_barang_ruang((int) $r['id'], array_keys((array) ($d['isi'] ?? []))); ?>
  <section class="kk-lembar">
    <?= kk_kop() ?>
    <h1 class="kk-judul">KARTU KONTROL<br>INVENTARIS SARANA</h1>
    <table class="kk-identitas"><tr><td>Ruangan</td><td>:</td><td><?= e($r['nama']) ?></td></tr><tr><td>PJ Ruangan</td><td>:</td><td><?= e(kk_pj_ruang($r, kk_penugasan($jenisKK))) ?></td></tr><tr><td>Tahun</td><td>:</td><td><?= $tahun ?></td></tr></table>
    <table class="kk-grid kk-grid-sarana">
      <thead>
        <tr><th rowspan="2" class="kk-no">No</th><th rowspan="2" class="kk-uraian">Nama Sarana</th><th rowspan="2">Code</th><?php foreach ($bulanPendek as $nm): ?><th colspan="6"><?= e($nm) ?></th><?php endforeach; ?></tr>
        <tr><?php for ($b = 1; $b <= 12; $b++): foreach (array_keys(KK_KOLOM_SARANA) as $kk): ?><th class="kk-sel"><?= strtoupper($kk) ?></th><?php endforeach; endfor; ?></tr>
      </thead>
      <tbody>
      <?php if (!$barang): ?><tr><td colspan="75" style="text-align:center">Belum ada sarana tercatat di ruangan ini.</td></tr><?php endif; ?>
      <?php $no = 0; foreach ($barang as $id => $b): $no++; ?>
        <tr><td class="kk-no"><?= $no ?></td><td class="kk-uraian"><?= e($b['nama']) ?></td><td class="kk-kode"><?= e($b['kode']) ?></td>
          <?php for ($bl = 1; $bl <= 12; $bl++): $isi = $d['isi'][(string) $id][(string) $bl] ?? []; ?>
            <td class="kk-sel"><?= e((string) ($isi['q'] ?? '')) ?></td><td class="kk-sel"><?= e((string) ($isi['k'] ?? '')) ?></td><td class="kk-sel"><?= e((string) ($isi['p'] ?? '')) ?></td>
            <td class="kk-sel" title="<?= e((string) ($isi['m'] ?? '')) ?>"><?= ($isi['m'] ?? '') !== '' ? '&#10003;' : '' ?></td><td class="kk-sel" title="<?= e((string) ($isi['u'] ?? '')) ?>"><?= e(mb_substr((string) ($isi['u'] ?? ''), 0, 3)) ?></td>
            <td class="kk-sel" title="<?= e((string) ($isi['s']['nama'] ?? '')) ?>"><?= !empty($isi['s']['nama']) ? e(kk_inisial($isi['s']['nama'])) : '' ?></td>
          <?php endfor; ?></tr>
      <?php endforeach; ?>
      </tbody>
    </table>
    <div class="kk-bawah">
      <div class="kk-indikator"><b>Keterangan</b><?php foreach (KK_KOLOM_SARANA as $kk => $ket): ?><div><?= strtoupper($kk) ?> : <?= e(mb_strtoupper($ket)) ?></div><?php endforeach; ?><div class="kk-catatan-cetak">M = ada mutasi, U = 3 huruf awal pengguna, S = inisial petugas</div></div>
      <?= kk_ttd() ?>
    </div>
  </section>
  <?php endforeach; ?>

<?php else: ?>
  <?php foreach (array_chunk($daftarAc, 2) as $pasangan): ?>
  <section class="kk-lembar kk-lembar-ac">
    <?php foreach ($pasangan as $u): $d = $dataCetak[(string) $u['id']] ?? []; $brs = (array) ($d['baris'] ?? []); $total = max(12, count($brs)); ?>
    <div class="kk-ac">
      <?= kk_kop() ?>
      <h1 class="kk-judul">KARTU PEMELIHARAAN<br>AIR CONDITIONER TAHUN <?= $tahun ?></h1>
      <table class="kk-identitas">
        <tr><td>MITRA</td><td>:</td><td><?= e($d['mitra'] ?? '') ?></td></tr>
        <tr><td>JENIS AC</td><td>:</td><td><?= e($d['jenis_ac'] ?? '') ?></td></tr>
        <tr><td>MERK</td><td>:</td><td><?= e($u['merek']) ?></td></tr>
        <tr><td>TYPE</td><td>:</td><td><?= e($u['model']) ?></td></tr>
        <tr><td>KAPASITAS</td><td>:</td><td><?= e($d['kapasitas'] ?? '') ?></td></tr>
        <tr><td>NO SERI</td><td>:</td><td><?= e($u['no_seri']) ?></td></tr>
        <tr><td>RUANGAN</td><td>:</td><td><?= e($u['ruang_nama'] ?: '') ?></td></tr>
      </table>
      <table class="kk-grid kk-grid-ac">
        <thead><tr><th rowspan="2" class="kk-no">NO</th><th rowspan="2">TGL</th><th rowspan="2">DESCRIPTION</th><th colspan="3">PENGECEKAN</th><th rowspan="2">REMAKS</th></tr>
          <tr><th>ARUS</th><th>TEGANGAN</th><th>TEKANAN</th></tr></thead>
        <tbody>
        <?php for ($i = 0; $i < $total; $i++): $b = $brs[$i] ?? null; ?>
          <tr><td class="kk-no"><?= $i + 1 ?></td><td class="nowrap"><?= $b ? e(date('d/m/Y', strtotime($b['tgl']))) : '' ?></td><td><?= $b ? e($b['deskripsi']) : '' ?></td>
            <td><?= $b ? e($b['arus']) : '' ?></td><td><?= $b ? e($b['tegangan']) : '' ?></td><td><?= $b ? e($b['tekanan']) : '' ?></td><td><?= $b ? e($b['remaks']) : '' ?></td></tr>
        <?php endfor; ?>
        </tbody>
      </table>
      <div class="kk-bawah"><div></div><?= kk_ttd() ?></div>
    </div>
    <?php endforeach; ?>
  </section>
  <?php endforeach; ?>
  <?php if (!$daftarAc): ?><p class="label-kosong">Belum ada unit AC di inventaris.</p><?php endif; ?>
<?php endif; ?>
<script nonce="<?= csp_nonce() ?>">
  document.getElementById('tombolCetakKK').addEventListener('click', function () { window.print(); });
</script>
</body>
</html>
