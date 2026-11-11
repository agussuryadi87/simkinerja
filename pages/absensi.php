<?php
$saya = uid();
$rekap = can('laporan_lihat');

$bulan = (string) ($_GET['bulan'] ?? date('Y-m'));
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = date('Y-m');
}
$awal = $bulan . '-01';
$jmlHari = (int) date('t', strtotime($awal));
$akhir = $bulan . '-' . str_pad((string) $jmlHari, 2, '0', STR_PAD_LEFT);
$hariIni = date('Y-m-d');
$namaBulan = ['01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April', '05' => 'Mei', '06' => 'Juni',
              '07' => 'Juli', '08' => 'Agustus', '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember'];
$judulBulan = $namaBulan[substr($bulan, 5, 2)] . ' ' . substr($bulan, 0, 4);
$sebelum = date('Y-m', strtotime($awal . ' -1 month'));
$sesudah = date('Y-m', strtotime($awal . ' +1 month'));

$fUnit = (int) ($_GET['unit'] ?? 0);
$ids = $rekap ? array_merge([$saya], tim_ids()) : [$saya];
$ids = array_values(array_unique(array_map('intval', $ids)));
$syaratUnit = $fUnit ? ' AND p.unit_id = ?' : '';
$pegawai = rows(
    'SELECT p.id, p.nama, p.jabatan, un.nama AS unit_nama FROM pengguna p
       JOIN peran pr ON pr.id = p.peran_id LEFT JOIN unit un ON un.id = p.unit_id
      WHERE p.aktif = 1 AND pr.kode <> \'admin\' AND p.id IN (' . in_placeholder($ids) . ')' . $syaratUnit . '
      ORDER BY un.nama IS NULL, un.nama, pr.urut, p.nama',
    array_merge($ids, $fUnit ? [$fUnit] : [])
);
$unitPilihan = $rekap ? rows(
    'SELECT DISTINCT un.id, un.nama FROM pengguna p JOIN unit un ON un.id = p.unit_id
      WHERE p.aktif = 1 AND p.id IN (' . in_placeholder($ids) . ') ORDER BY un.nama',
    $ids
) : [];

$kode = [
    'hadir' => ['H', 'Hadir'],
    'sakit' => ['S', 'Sakit'],
    'izin'  => ['I', 'Izin'],
    'cuti'  => ['C', 'Cuti'],
    'dinas' => ['DL', 'Dinas luar'],
];
$peta = [];
$idTampil = array_map('intval', array_column($pegawai, 'id'));
if ($idTampil) {
    $catatan = rows(
        'SELECT pengguna_id, tanggal, kehadiran FROM kegiatan
          WHERE tanggal BETWEEN ? AND ? AND pengguna_id IN (' . in_placeholder($idTampil) . ')' . sql_bukan_draft(),
        array_merge([$awal, $akhir], $idTampil)
    );
    foreach ($catatan as $c) {
        $hari = (int) substr($c['tanggal'], 8, 2);
        $jenis = isset($kode[$c['kehadiran']]) && $c['kehadiran'] !== 'masuk' ? $c['kehadiran'] : 'hadir';
        $sebelumnya = $peta[(int) $c['pengguna_id']][$hari] ?? null;
        if ($sebelumnya === null || $sebelumnya === 'hadir') {
            $peta[(int) $c['pengguna_id']][$hari] = $jenis;
        }
    }
}
$libur = [];
for ($d = 1; $d <= $jmlHari; $d++) {
    $libur[$d] = (int) date('N', strtotime($bulan . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT))) >= 6;
}
$hariKerja = count(array_filter($libur, static function ($l) { return !$l; }));
$namaHari = ['', 'Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];
$subjudul = $judulBulan;
?>

<form method="get" class="saring no-cetak">
  <input type="hidden" name="h" value="absensi">
  <div class="form-baris"><label class="fl" for="abBulan">Bulan</label>
    <input type="month" id="abBulan" name="bulan" value="<?= e($bulan) ?>" data-auto-kirim></div>
  <?php if ($unitPilihan): ?>
  <div class="form-baris"><label class="fl" for="abUnit">Unit</label>
    <select id="abUnit" name="unit" data-auto-kirim><option value="">Semua unit</option>
      <?php foreach ($unitPilihan as $u): ?><option value="<?= (int) $u['id'] ?>"<?= $fUnit === (int) $u['id'] ? ' selected' : '' ?>><?= e($u['nama']) ?></option><?php endforeach; ?>
    </select></div>
  <?php endif; ?>
  <div class="aksi-baris" style="margin-left:auto">
    <a class="btn btn-garis" href="<?= e(url('absensi', ['bulan' => $sebelum, 'unit' => $fUnit ?: null])) ?>"><?= ikon('kiri', 15) ?> Bulan lalu</a>
    <a class="btn btn-garis" href="<?= e(url('absensi', ['bulan' => $sesudah, 'unit' => $fUnit ?: null])) ?>">Bulan berikutnya <?= ikon('kanan', 15) ?></a>
  </div>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('kalender', 18) ?> Rekap kehadiran &middot; <?= e($judulBulan) ?></h2>
      <p class="ket"><?= count($pegawai) ?> pegawai &middot; <?= $jmlHari ?> hari kalender &middot; <?= $hariKerja ?> hari kerja (Senin-Jumat), Sabtu dan Minggu ditandai abu-abu</p></div>
    <ul class="legenda-absen">
      <?php foreach ($kode as $k => [$sing, $label]): ?><li><i class="ab ab-<?= $k ?>"><?= $sing ?></i><?= e($label) ?></li><?php endforeach; ?>
      <li><i class="ab ab-kosong">&ndash;</i>Belum ada catatan</li>
    </ul>
  </div>
  <?php if (!$pegawai): ?>
    <?= sel_kosong('Belum ada pegawai untuk ditampilkan') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel tabel-absensi">
    <thead>
      <tr><th rowspan="2" class="ab-no">No</th><th rowspan="2" class="ab-nama">Nama pegawai</th>
        <th colspan="<?= $jmlHari ?>"><?= e($judulBulan) ?></th>
        <th colspan="<?= count($kode) ?>">Jumlah</th></tr>
      <tr>
        <?php for ($d = 1; $d <= $jmlHari; $d++): ?><th class="ab-tgl<?= $libur[$d] ? ' ab-akhir-pekan' : '' ?>"><?= $d ?><small><?= $namaHari[(int) date('N', strtotime($bulan . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT)))] ?></small></th><?php endfor; ?>
        <?php foreach ($kode as $k => [$sing]): ?><th class="ab-jml"><?= $sing ?></th><?php endforeach; ?>
      </tr>
    </thead>
    <tbody>
    <?php $no = 0; foreach ($pegawai as $p): $no++; $id = (int) $p['id']; $jumlah = array_fill_keys(array_keys($kode), 0); ?>
      <tr>
        <td class="ab-no"><?= $no ?></td>
        <td class="ab-nama"><b><?= e($p['nama']) ?></b><span class="sub"><?= e($p['unit_nama'] ?? ($p['jabatan'] ?: '-')) ?></span></td>
        <?php for ($d = 1; $d <= $jmlHari; $d++):
            $tgl = $bulan . '-' . str_pad((string) $d, 2, '0', STR_PAD_LEFT);
            $isi = $peta[$id][$d] ?? null;
            if ($isi) { $jumlah[$isi]++; }
            ?>
          <td class="ab-tgl<?= $libur[$d] ? ' ab-akhir-pekan' : '' ?>"><?php if ($isi): ?><i class="ab ab-<?= $isi ?>" title="<?= e($kode[$isi][1] . ' - ' . tanggal_id($tgl)) ?>"><?= $kode[$isi][0] ?></i><?php elseif ($tgl <= $hariIni && !$libur[$d]): ?><span class="ab-kosong">&ndash;</span><?php endif; ?></td>
        <?php endfor; ?>
        <?php foreach ($kode as $k => $x): ?><td class="ab-jml"><b><?= $jumlah[$k] ?: '' ?></b></td><?php endforeach; ?>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <p class="kecil lemah ab-catatan">Kehadiran diambil otomatis dari Catatan Harian: hari yang memiliki catatan kegiatan dihitung <b>Hadir</b>. Bila pegawai memilih Sakit, Izin, Cuti, atau Dinas luar, keterangan itu yang ditampilkan.</p>
  <?php endif; ?>
</div>
