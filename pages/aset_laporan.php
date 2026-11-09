<?php
$jenisLaporan = ['kir' => 'Kartu Inventaris Ruangan (KIR)', 'rekap' => 'Rekapitulasi Aset per Kategori', 'masalah' => 'Daftar Barang Rusak / Hilang'];
$jenis = array_key_exists($_GET['jenis'] ?? '', $jenisLaporan) ? $_GET['jenis'] : 'kir';
$ruangId = (int) ($_GET['ruang'] ?? 0);
$ruang = $ruangId ? row('SELECT * FROM aset_ruang WHERE id = ?', [$ruangId]) : null;
$umur = aset_umur_kategori();
$kategori = aset_kategori(true);

$ambilBarang = static function (string $tambahan = '', array $par = []): array {
    return rows(
        "SELECT b.*, r.nama AS ruang_nama, r.kode AS ruang_kode FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id
          WHERE b.status = 'aktif' $tambahan ORDER BY r.nama, b.kategori, b.nama, b.kode",
        $par
    );
};
$judul = $jenisLaporan[$jenis];
$subjudul = $judul;
?>
<form method="get" class="saring no-cetak">
  <input type="hidden" name="h" value="aset_laporan">
  <div class="form-baris"><label class="fl">Jenis laporan</label><select name="jenis" data-auto-kirim><?= pilihan($jenisLaporan, $jenis) ?></select></div>
  <?php if ($jenis === 'kir'): ?>
    <div class="form-baris" style="flex:1"><label class="fl">Ruangan</label><select name="ruang" data-auto-kirim><?= opsi_ruang($ruangId, '— Pilih ruangan —', false) ?></select></div>
  <?php endif; ?>
  <button class="btn btn-utama" type="button" data-cetak><?= ikon('cetak', 15) ?> Cetak / simpan PDF</button>
</form>

<article class="berita-acara">
  <header class="kop-surat">
    <img class="kop-logo" src="<?= e(base_url('assets/img/kop-logo.png')) ?>" alt="Logo UIS">
    <div class="kop-teks">
      <div class="kop-yayasan">YAYASAN PENDIDIKAN IBNU SINA BATAM (YAPISTA)</div>
      <div class="kop-univ">UNIVERSITAS IBNU SINA (UIS)</div>
      <div class="kop-alamat">Jalan Teuku Umar, Lubuk Baja, Kota Batam-Indonesia Telp. 0778 &ndash; 408 3113</div>
      <div class="kop-alamat">email : info@uis.ac.id / uibnusina@gmail.com &nbsp; website : uis.ac.id</div>
    </div>
  </header>
  <div class="kop-garis"></div>

  <div class="ba-isi">
    <div class="ba-judul"><h1><?= e(mb_strtoupper($judul)) ?></h1>
      <?php if ($jenis === 'kir' && $ruang): ?><div class="ba-nama"><?= e(mb_strtoupper($ruang['nama'])) ?></div><?php endif; ?>
      <div>Per tanggal <?= e(tanggal_id(date('Y-m-d'))) ?></div></div>

<?php if ($jenis === 'kir'): ?>
  <?php if (!$ruang): ?>
      <p style="text-align:center">Pilih ruangan untuk menampilkan kartu inventaris.</p>
  <?php else:
      $barang = $ambilBarang('AND b.ruang_id = ?', [$ruangId]);
      $totalNilai = 0.0; ?>
      <table class="ba-info">
        <tr><td>Kode ruangan</td><td>:</td><td><?= e($ruang['kode']) ?></td></tr>
        <tr><td>Jenis / lokasi</td><td>:</td><td><?= e(ASET_JENIS_RUANG[$ruang['jenis']] ?? '-') ?> · <?= e(trim($ruang['gedung'] . ($ruang['lantai'] !== '' ? ' lantai ' . $ruang['lantai'] : ''), ' ·') ?: '-') ?></td></tr>
        <tr><td>Penanggung jawab</td><td>:</td><td><?= e($ruang['penanggung_jawab'] ?: '-') ?></td></tr>
      </table>
      <table class="ba-tabel">
        <thead><tr><th style="width:30px">No</th><th style="width:96px">Kode</th><th>Nama barang</th><th>Merek / tipe</th><th style="width:48px">Tahun</th><th style="width:60px">Jumlah</th><th style="width:76px">Kondisi</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php if (!$barang): ?><tr><td colspan="8" style="text-align:center">Belum ada barang tercatat di ruangan ini.</td></tr><?php endif; ?>
        <?php foreach ($barang as $i => $b): ?>
          <tr><td style="text-align:center"><?= $i + 1 ?></td><td><?= e($b['kode']) ?></td><td><?= e($b['nama']) ?><?= $b['posisi'] !== '' ? '<br><small>Posisi: ' . e($b['posisi']) . '</small>' : '' ?></td>
            <td><?= e(trim($b['merek'] . ' ' . $b['model']) ?: '-') ?></td><td style="text-align:center"><?= $b['tahun'] ?: '-' ?></td>
            <td style="text-align:center"><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></td><td><?= e(ASET_KONDISI[$b['kondisi']]['label']) ?></td><td><?= e($b['keterangan'] ?: '') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <p>Jumlah: <b><?= count($barang) ?></b> jenis barang, <b><?= angka(array_sum(array_map('intval', array_column($barang, 'jumlah')))) ?></b> unit/item.</p>
      <div class="ba-ttd" style="justify-content:flex-end">
        <div><div><?= e(APP_KOTA) ?>, <?= e(tanggal_id(date('Y-m-d'))) ?></div><div>Kepala Bidang Sarana dan Prasarana,</div><div class="ruang"></div><?= aset_ttd_kabid() ?></div>
      </div>
  <?php endif; ?>

<?php elseif ($jenis === 'rekap'):
    $barang = $ambilBarang();
    $baris = [];
    foreach ($barang as $b) {
        $k = $b['kategori'];
        $baris[$k] ??= ['jenis' => 0, 'unit' => 0, 'nilai' => 0.0, 'buku' => 0.0, 'baik' => 0, 'rusak' => 0, 'hilang' => 0];
        $baris[$k]['jenis']++;
        $baris[$k]['unit'] += (int) $b['jumlah'];
        $baris[$k]['nilai'] += (float) $b['harga'] * (int) $b['jumlah'];
        $baris[$k]['buku'] += (float) aset_nilai_buku($b['harga'], $b['jumlah'], $b['tahun'], $umur[$k] ?? null);
        $baris[$k][$b['kondisi'] === 'baik' ? 'baik' : ($b['kondisi'] === 'hilang' ? 'hilang' : 'rusak')]++;
    }
    ksort($baris);
    $tot = ['jenis' => 0, 'unit' => 0, 'nilai' => 0.0, 'buku' => 0.0, 'baik' => 0, 'rusak' => 0, 'hilang' => 0];
    foreach ($baris as $r) { foreach ($tot as $kk => $_) { $tot[$kk] += $r[$kk]; } } ?>
      <table class="ba-tabel">
        <thead><tr><th style="width:30px">No</th><th>Kategori</th><th>Jenis</th><th>Unit</th><th>Baik</th><th>Rusak</th><th>Hilang</th><th>Nilai perolehan (Rp)</th><th>Nilai buku (Rp)</th></tr></thead>
        <tbody>
        <?php if (!$baris): ?><tr><td colspan="9" style="text-align:center">Belum ada barang tercatat.</td></tr><?php endif; ?>
        <?php $i = 0; foreach ($baris as $k => $r): $i++; ?>
          <tr><td style="text-align:center"><?= $i ?></td><td><?= e($kategori[$k] ?? $k) ?></td><td style="text-align:center"><?= $r['jenis'] ?></td><td style="text-align:center"><?= angka($r['unit']) ?></td>
            <td style="text-align:center"><?= $r['baik'] ?></td><td style="text-align:center"><?= $r['rusak'] ?></td><td style="text-align:center"><?= $r['hilang'] ?></td>
            <td style="text-align:right"><?= angka($r['nilai']) ?></td><td style="text-align:right"><?= angka($r['buku']) ?></td></tr>
        <?php endforeach; ?>
        <?php if ($baris): ?>
          <tr><td></td><td><b>Jumlah</b></td><td style="text-align:center"><b><?= $tot['jenis'] ?></b></td><td style="text-align:center"><b><?= angka($tot['unit']) ?></b></td><td style="text-align:center"><b><?= $tot['baik'] ?></b></td>
            <td style="text-align:center"><b><?= $tot['rusak'] ?></b></td><td style="text-align:center"><b><?= $tot['hilang'] ?></b></td><td style="text-align:right"><b><?= angka($tot['nilai']) ?></b></td><td style="text-align:right"><b><?= angka($tot['buku']) ?></b></td></tr>
        <?php endif; ?>
        </tbody>
      </table>
      <p style="font-size:10pt">Nilai buku dihitung dengan metode garis lurus berdasarkan umur ekonomis kategori dan tahun perolehan, tanpa nilai sisa.</p>
      <div class="ba-ttd" style="justify-content:flex-end">
        <div><div><?= e(APP_KOTA) ?>, <?= e(tanggal_id(date('Y-m-d'))) ?></div><div>Kepala Bidang Sarana dan Prasarana,</div><div class="ruang"></div><?= aset_ttd_kabid() ?></div>
      </div>

<?php else:
    $barang = $ambilBarang("AND b.kondisi <> 'baik'"); ?>
      <table class="ba-tabel">
        <thead><tr><th style="width:30px">No</th><th style="width:96px">Kode</th><th>Nama barang</th><th>Ruangan</th><th style="width:60px">Jumlah</th><th style="width:82px">Kondisi</th><th>Keterangan</th></tr></thead>
        <tbody>
        <?php if (!$barang): ?><tr><td colspan="7" style="text-align:center">Tidak ada barang rusak atau hilang.</td></tr><?php endif; ?>
        <?php foreach ($barang as $i => $b): ?>
          <tr><td style="text-align:center"><?= $i + 1 ?></td><td><?= e($b['kode']) ?></td><td><?= e($b['nama']) ?></td><td><?= e($b['ruang_nama'] ?: '-') ?></td>
            <td style="text-align:center"><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></td><td><?= e(ASET_KONDISI[$b['kondisi']]['label']) ?></td><td><?= e($b['keterangan'] ?: '') ?></td></tr>
        <?php endforeach; ?>
        </tbody>
      </table>
      <div class="ba-ttd" style="justify-content:flex-end">
        <div><div><?= e(APP_KOTA) ?>, <?= e(tanggal_id(date('Y-m-d'))) ?></div><div>Kepala Bidang Sarana dan Prasarana,</div><div class="ruang"></div><?= aset_ttd_kabid() ?></div>
      </div>
<?php endif; ?>
  </div>
</article>
