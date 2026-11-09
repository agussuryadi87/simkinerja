<?php
$sekarang = date('Y-m-d');

$ruangAktif = (int) val('SELECT COUNT(*) FROM aset_ruang WHERE aktif = 1', [], 0);
$brg = row("SELECT COUNT(*) AS jenis, COALESCE(SUM(jumlah), 0) AS unit, COALESCE(SUM(harga * jumlah), 0) AS nilai FROM aset_barang WHERE status = 'aktif'") ?? [];
$totalBarang = (int) ($brg['jenis'] ?? 0);

$perKondisi = [];
foreach (rows("SELECT kondisi, COUNT(*) AS n FROM aset_barang WHERE status = 'aktif' GROUP BY kondisi") as $r) {
    $perKondisi[$r['kondisi']] = (int) $r['n'];
}
$warnaKondisi = ['baik' => '#3b8a66', 'rusak_ringan' => '#d2ac55', 'rusak_berat' => '#b8493f', 'hilang' => '#9aa39e'];
$irisan = [];
foreach (ASET_KONDISI as $k => $d) {
    $irisan[] = [$d['label'], $perKondisi[$k] ?? 0, $warnaKondisi[$k]];
}

$rusakTerbuka = (int) val("SELECT COUNT(*) FROM aset_kerusakan WHERE status IN ('dilaporkan','diproses')", [], 0);
$rusakBerat = (int) val("SELECT COUNT(*) FROM aset_kerusakan WHERE status IN ('dilaporkan','diproses') AND tingkat = 'berat'", [], 0);

$bulan = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime(date('Y-m-01') . " -$i months");
    $bulan[date('Y-m', $ts)] = mb_substr(NAMA_BULAN[(int) date('n', $ts)], 0, 3);
}
$perBulan = ['sarana' => array_fill_keys(array_keys($bulan), 0), 'prasarana' => array_fill_keys(array_keys($bulan), 0)];
foreach (rows(
    "SELECT DATE_FORMAT(tanggal_lapor, '%Y-%m') AS ym, jenis, COUNT(*) AS n FROM aset_kerusakan WHERE tanggal_lapor >= ? GROUP BY ym, jenis",
    [date('Y-m-01', strtotime('-5 months'))]
) as $r) {
    if (isset($perBulan[$r['jenis']][$r['ym']])) {
        $perBulan[$r['jenis']][$r['ym']] = (int) $r['n'];
    }
}
$totalLaporan = array_sum($perBulan['sarana']) + array_sum($perBulan['prasarana']);

$rawat = [];
foreach (['kebersihan' => 'Kebersihan', 'ac' => 'Pendingin ruangan (AC)'] as $jenis => $nama) {
    $rawat[$jenis] = [
        'nama'      => $nama,
        'selesai'   => (int) val("SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'selesai' AND selesai_pada >= ?", [$jenis, date('Y-m-d', strtotime('-60 days'))], 0),
        'terjadwal' => (int) val("SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'terjadwal' AND tanggal >= ?", [$jenis, $sekarang], 0),
        'terlambat' => (int) val("SELECT COUNT(*) FROM aset_perawatan WHERE jenis = ? AND status = 'terjadwal' AND tanggal < ?", [$jenis, $sekarang], 0),
    ];
}

$perKategori = rows("SELECT kategori, COUNT(*) AS n FROM aset_barang WHERE status = 'aktif' GROUP BY kategori ORDER BY n DESC, kategori LIMIT 6");
$maksKategori = max(array_merge([1], array_map('intval', array_column($perKategori, 'n'))));

$tindakan = [];
foreach (rows(
    "SELECT k.id, k.jenis, k.judul, k.tingkat, k.status, k.tanggal_lapor AS tgl, b.nama AS barang_nama, r.nama AS ruang_nama
       FROM aset_kerusakan k LEFT JOIN aset_barang b ON b.id = k.barang_id LEFT JOIN aset_ruang r ON r.id = k.ruang_id
      WHERE k.status IN ('dilaporkan','diproses') ORDER BY FIELD(k.tingkat, 'berat','sedang','ringan'), k.tanggal_lapor LIMIT 6"
) as $k) {
    $tindakan[] = [
        'jenis' => $k['jenis'] === 'sarana' ? 'Kerusakan sarana' : 'Kerusakan prasarana', 'uraian' => $k['judul'],
        'lokasi' => $k['jenis'] === 'sarana' ? trim(($k['barang_nama'] ?? '') . ($k['ruang_nama'] ? ' · ' . $k['ruang_nama'] : ''), ' ·') : (string) $k['ruang_nama'],
        'tgl' => $k['tgl'], 'status' => ASET_TINGKAT[$k['tingkat']]['label'] . ' · ' . ASET_STATUS_RUSAK[$k['status']]['label'],
        'berat' => $k['tingkat'] === 'berat', 'url' => url($k['jenis'] === 'sarana' ? 'aset_sarana' : 'aset_prasarana', ['aksi' => 'lihat', 'id' => $k['id']]),
    ];
}
foreach (rows(
    "SELECT w.jenis, w.kegiatan, w.tanggal, r.nama AS ruang_nama, b.nama AS barang_nama
       FROM aset_perawatan w LEFT JOIN aset_ruang r ON r.id = w.ruang_id LEFT JOIN aset_barang b ON b.id = w.barang_id
      WHERE w.status = 'terjadwal' AND w.tanggal < ? ORDER BY w.tanggal LIMIT 4",
    [$sekarang]
) as $w) {
    $hari = (int) floor((strtotime($sekarang) - strtotime($w['tanggal'])) / 86400);
    $tindakan[] = [
        'jenis' => $w['jenis'] === 'ac' ? 'Jadwal AC' : 'Jadwal kebersihan', 'uraian' => $w['kegiatan'],
        'lokasi' => (string) ($w['jenis'] === 'ac' ? $w['barang_nama'] : $w['ruang_nama']), 'tgl' => $w['tanggal'],
        'status' => 'Terlambat ' . $hari . ' hari', 'berat' => false, 'url' => url($w['jenis'] === 'ac' ? 'aset_ac' : 'aset_kebersihan'),
    ];
}
?>
<div class="aset-grafik">
  <section class="kartu">
    <div class="kartu-kepala"><div><h2>Kondisi barang</h2></div></div>
    <div class="kartu-isi grafik-donat-bungkus">
      <?= aset_svg_donat($irisan, (string) $totalBarang, 'jenis barang') ?>
      <ul class="legenda-aset">
        <?php foreach ($irisan as [$nama, $nilai, $warna]): ?>
          <li><i style="background:<?= $warna ?>"></i><span><?= e($nama) ?></span><b><?= angka($nilai) ?></b>
            <em><?= $totalBarang ? round($nilai / $totalBarang * 100) : 0 ?>%</em></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </section>

  <section class="kartu">
    <div class="kartu-kepala"><div><h2>Laporan kerusakan</h2></div>
      <ul class="legenda-baris"><li><i style="background:#124d4d"></i>Sarana</li><li><i style="background:#b6cccb"></i>Prasarana</li></ul></div>
    <div class="kartu-isi"><?= aset_svg_batang(array_values($bulan), [['Sarana', '#124d4d', array_values($perBulan['sarana'])], ['Prasarana', '#b6cccb', array_values($perBulan['prasarana'])]]) ?></div>
  </section>

  <section class="kartu">
    <div class="kartu-kepala"><div><h2>Pemeliharaan berkala</h2></div></div>
    <div class="kartu-isi">
      <?php foreach ($rawat as $r): $jml = $r['selesai'] + $r['terjadwal'] + $r['terlambat']; ?>
        <div class="tumpuk">
          <div class="tumpuk-judul"><span><?= e($r['nama']) ?></span><small><?= angka($jml) ?> catatan</small></div>
          <div class="tumpuk-bar<?= $jml ? '' : ' kosong' ?>">
            <?php if ($jml): foreach ([['selesai', '#3b8a66'], ['terjadwal', '#b6cccb'], ['terlambat', '#b8493f']] as [$kk, $wr]): if ($r[$kk]): ?>
              <span style="width:<?= round($r[$kk] / $jml * 100, 2) ?>%;background:<?= $wr ?>" title="<?= e(ucfirst($kk)) ?>: <?= $r[$kk] ?>"></span>
            <?php endif; endforeach; endif; ?>
          </div>
          <div class="tumpuk-ket"><span><i style="background:#3b8a66"></i>Selesai <?= $r['selesai'] ?></span><span><i style="background:#b6cccb"></i>Terjadwal <?= $r['terjadwal'] ?></span><span><i style="background:#b8493f"></i>Terlambat <?= $r['terlambat'] ?></span></div>
        </div>
      <?php endforeach; ?>
    </div>
  </section>

  <section class="kartu">
    <div class="kartu-kepala"><div><h2>Barang per kategori</h2></div></div>
    <div class="kartu-isi">
      <?php if (!$perKategori): ?><p class="lemah" style="margin:0">Belum ada barang tercatat.</p><?php endif; ?>
      <?php foreach ($perKategori as $r): ?>
        <div class="hbar"><span><?= e(aset_kategori(true)[$r['kategori']] ?? $r['kategori']) ?></span>
          <div><i style="width:<?= round($r['n'] / $maksKategori * 100, 1) ?>%"></i></div><b><?= angka($r['n']) ?></b></div>
      <?php endforeach; ?>
    </div>
  </section>
</div>

<section class="kartu">
  <div class="kartu-kepala"><div><h2>Perlu tindakan</h2></div></div>
  <?php if (!$tindakan): ?><p class="lemah" style="padding:4px 20px 22px;margin:0">Tidak ada.</p>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Jenis</th><th>Uraian</th><th>Lokasi</th><th>Tanggal</th><th>Keterangan</th></tr></thead>
    <tbody>
    <?php foreach ($tindakan as $t): ?>
      <tr><td class="nowrap lemah"><?= e($t['jenis']) ?></td>
        <td><a href="<?= e($t['url']) ?>"><b><?= e($t['uraian']) ?></b></a></td>
        <td class="kecil"><?= e($t['lokasi'] ?: '-') ?></td>
        <td class="nowrap"><?= e(tanggal_pendek($t['tgl'])) ?></td>
        <td class="nowrap kecil<?= $t['berat'] || strpos($t['status'], 'Terlambat') === 0 ? ' teks-merah' : '' ?>"><?= e($t['status']) ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</section>
