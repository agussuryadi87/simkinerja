<?php
$u = user();
$p = periode_dipilih();
$pid = pid();
$saya = uid();

$jam = (int) date('G');
$salam = $jam < 11 ? 'Selamat pagi' : ($jam < 15 ? 'Selamat siang' : ($jam < 18 ? 'Selamat sore' : 'Selamat malam'));

$awalBulan = date('Y-m-01');
$akhirBulan = date('Y-m-t');
$bulanIni = ringkasan_kegiatan($pid, [$saya], $awalBulan, $akhirBulan)[$saya] ?? [];
$sePeriode = ringkasan_kegiatan($pid, [$saya])[$saya] ?? [];
$twKini = tw_berjalan($p);
$capaian = capaian_harian($p, $saya, $twKini)['persen'];
$ev = evaluasi_terakhir($pid, $saya);
$evTwSaya = evaluasi_tw_pegawai($pid, [$saya])[$saya] ?? [];
$nilaiTahunan = nilai_tahunan($evTwSaya);

$perBulan = kegiatan_per_bulan($pid, $saya);
$maksBulan = 1;
foreach ($perBulan as $b) {
    $maksBulan = max($maksBulan, array_sum($b));
}

$terbaru = rows(
    'SELECT k.* FROM kegiatan k
      WHERE k.periode_id = ? AND k.pengguna_id = ? ORDER BY k.tanggal DESC, k.id DESC LIMIT 6',
    [$pid, $saya]
);
$umpan = rows(
    'SELECT ub.*, pg.nama AS pemberi_nama FROM umpan_balik ub JOIN pengguna pg ON pg.id = ub.pemberi_id
      WHERE ub.pegawai_id = ? ORDER BY ub.dibuat DESC LIMIT 3',
    [$saya]
);

$profil = pegawai_get($saya);

$tim = can('pantau') ? pegawai_tim() : [];
$antre = antrean_verifikasi();

$adaKegiatan = (int) ($sePeriode['total'] ?? 0) > 0;
$ringTim = $tim ? ringkasan_kegiatan($pid, array_map('intval', array_column($tim, 'id')), $awalBulan, $akhirBulan) : [];

$tahapKini = $p['tahap'] ?? 'pelaksanaan';
$noKini = TAHAP_SIKLUS[$tahapKini]['no'] ?? 5;
$tautanTahap = ['pelaksanaan' => can('kegiatan_isi') ? 'kegiatan' : 'pantau',
                'evaluasi' => 'evaluasi', 'pengembangan' => 'pengembangan'];

$totalJenis = max(1, (int) ($sePeriode['total'] ?? 0));
$keliling = 2 * M_PI * 52;
?>

<section class="sambut">
  <div class="sambut-latar" aria-hidden="true">
    <div class="matahari"></div>
    <div class="lapis awan"></div>
    <div class="lapis burung"></div>
    <div class="lapis gunung"></div>
    <div class="lapis candi"></div>
    <div class="lapis laut"></div>
    <div class="perahu"><img src="<?= e(base_url('assets/img/nusantara/pinisi.svg')) ?>" alt=""></div>
    <div class="lapis laut2"></div>
    <div class="lapis batik"></div>
  </div>
  <div class="baris">
    <?= avatar($u['nama'], 'besar') ?>
    <div>
      <span class="salam"><?= e($salam) ?>,</span>
      <h1><?= e($u['nama']) ?></h1>
      <p><?= e($u['jabatan'] ?: $u['peran_nama']) ?><?= $u['unit_nama'] ? ' · ' . e($u['unit_nama']) : '' ?></p>
    </div>
  </div>
  <div class="sambut-info">
    <div class="chips">
      <?php if ($p): ?>
        <span class="chip"><?= ikon('siklus', 14) ?> <?= e($p['nama']) ?> · tahap <?= e(TAHAP_SIKLUS[$tahapKini]['label'] ?? 'Selesai') ?></span>
      <?php endif; ?>
      <?php if ($u['atasan_nama']): ?>
        <span class="chip"><?= ikon('profil', 14) ?> Atasan: <?= e($u['atasan_nama']) ?></span>
      <?php endif; ?>
    </div>
    <div class="aksi">
    </div>
  </div>
  <div class="jam-digital" id="jamDigital">
    <div class="jd-hari" data-jd="hari"><?= e(NAMA_HARI[(int) date('w')]) ?></div>
    <div class="jd-waktu">
      <span class="jd-jam" data-jd="jam"><?= date('H:i') ?></span>
      <span class="jd-sisi"><span class="jd-detik" data-jd="detik"><?= date('s') ?></span><span class="jd-zona">WIB</span></span>
    </div>
    <div class="jd-tanggal" data-jd="tanggal"><?= e(tanggal_id(date('Y-m-d'))) ?></div>
  </div>
</section>


<div class="siklus siklus-ringkas">
  <?php foreach (TAHAP_SIKLUS as $kunci => $t):
      $kelas = $t['no'] < $noKini ? 'lewat' : ($t['no'] === $noKini ? 'kini' : ''); ?>
    <div class="siklus-langkah <?= $kelas ?>" title="<?= e($t['ket']) ?>">
      <span class="bulat"><?= $t['no'] < $noKini ? ikon('centang', 16) : (int) $t['no'] ?></span>
      <div>
        <small><?= e($t['sub']) ?><?= $kelas === 'kini' ? ' · tahap berjalan' : '' ?></small>
        <a href="<?= e(url($tautanTahap[$kunci])) ?>"><b><?= e($t['label']) ?></b></a>
        <p><?= e($t['ket']) ?></p>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if (can('kegiatan_isi') && periode_terbuka()): ?>
    <div class="siklus-aksi"><a class="btn btn-utama" href="<?= e(url('kegiatan')) ?>"><?= ikon('tambah', 16) ?> Catat Kegiatan Hari Ini</a></div>
  <?php endif; ?>
</div>

<div class="grid-stat">
  <div class="stat">
    <span class="ik"><?= ikon('catatan', 22) ?></span>
    <div>
      <div class="label">Kegiatan bulan ini</div>
      <div class="nilai"><?= angka($bulanIni['total'] ?? 0) ?> <small>kegiatan</small></div>
      <div class="ket"><?= angka($sePeriode['total'] ?? 0) ?> sepanjang periode</div>
    </div>
  </div>
  <div class="stat biru">
    <span class="ik"><?= ikon('jam', 22) ?></span>
    <div>
      <div class="label">Jam kerja tercatat</div>
      <div class="nilai"><?= angka_ringkas($bulanIni['jam'] ?? 0, 1) ?> <small>jam</small></div>
      <div class="ket">bulan <?= e(NAMA_BULAN[(int) date('n')]) ?> <?= date('Y') ?></div>
    </div>
  </div>
  <div class="stat emas">
    <span class="ik"><?= ikon('centang', 22) ?></span>
    <div style="flex:1">
      <div class="label">Kegiatan terverifikasi</div>
      <div class="nilai"><?= angka($sePeriode['disetujui'] ?? 0) ?> <small>dari <?= angka($sePeriode['total'] ?? 0) ?></small></div>
      <?= meter(($sePeriode['total'] ?? 0) ? ($sePeriode['disetujui'] ?? 0) / $sePeriode['total'] * 100 : 0, 'emas') ?>
    </div>
  </div>
  <div class="stat ungu">
    <span class="ik"><?= ikon('tumbuh', 22) ?></span>
    <div style="flex:1">
      <div class="label">Capaian pencatatan <?= e(tw_label($p, $twKini)) ?></div>
      <div class="nilai"><?= $capaian === null ? '-' : angka($capaian, 1) . '<small>%</small>' ?></div>
      <?= meter($capaian ?? 0) ?>
    </div>
  </div>
</div>

<?php if (!$adaKegiatan): ?>
<div class="kartu">
  <div class="kartu-kepala"><h2><?= ikon('rekap', 18) ?> Ringkasan kegiatan</h2></div>
  <?= sel_kosong('Belum ada kegiatan tercatat pada periode ini', '',
        can('kegiatan_isi') && periode_terbuka() ? '<a class="btn btn-utama btn-kecil" href="' . e(url('kegiatan')) . '">' . ikon('tambah', 15) . ' Catat kegiatan hari ini</a>' : '') ?>
</div>
<?php else: ?>
<div class="grid grid-21">
  <div class="kartu">
    <div class="kartu-kepala">
      <h2><?= ikon('rekap', 18) ?> Kegiatan per bulan</h2>
      <div class="aksi legenda">
        <span><i style="background:#1fa463"></i>Rutin</span>
        <span><i style="background:#2f7fe0"></i>Panitia</span>
        <span><i style="background:#e5484d"></i>Insidental</span>
      </div>
    </div>
    <div class="kartu-isi">
      <div class="grafik-batang">
        <?php foreach ($perBulan as $bln => $b):
            $jml = array_sum($b); ?>
          <div class="kolom" title="<?= e(NAMA_BULAN[(int) substr($bln, 5, 2)] . ' ' . substr($bln, 0, 4)) ?>: <?= $jml ?> kegiatan">
            <div class="tumpuk">
              <?php foreach (['rutin', 'panitia', 'insidental'] as $j): if ($b[$j] > 0): ?>
                <i class="<?= $j ?>" style="height:<?= round($b[$j] / $maksBulan * 100, 2) ?>%"></i>
              <?php endif; endforeach; ?>
            </div>
            <span class="lbl"><?= e(mb_substr(NAMA_BULAN[(int) substr($bln, 5, 2)], 0, 3)) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <div class="kartu">
    <div class="kartu-kepala"><h2><?= ikon('target', 18) ?> Komposisi pekerjaan</h2></div>
    <div class="kartu-isi" style="display:flex;gap:20px;align-items:center;flex-wrap:wrap">
      <div class="donat">
        <svg width="132" height="132" viewBox="0 0 132 132">
          <circle cx="66" cy="66" r="52" fill="none" stroke="#eef1ed" stroke-width="16"/>
          <?php
            $geser = 0;
            $warnaJenis = ['rutin' => '#1fa463', 'panitia' => '#2f7fe0', 'insidental' => '#e5484d'];
            foreach ($warnaJenis as $j => $w):
                $bagian = ($sePeriode[$j] ?? 0) / $totalJenis * $keliling;
                if ($bagian <= 0) continue; ?>
            <circle cx="66" cy="66" r="52" fill="none" stroke="<?= $w ?>" stroke-width="16"
                    stroke-dasharray="<?= round($bagian, 2) ?> <?= round($keliling, 2) ?>"
                    stroke-dashoffset="<?= round(-$geser, 2) ?>"/>
          <?php $geser += $bagian; endforeach; ?>
        </svg>
        <div class="tengah"><div><b><?= angka($sePeriode['total'] ?? 0) ?></b><span>kegiatan</span></div></div>
      </div>
      <div class="daftar-jenis">
        <?php foreach (JENIS_KERJA as $j => $d): ?>
          <div class="baris">
            <span class="titik titik-<?= $j ?>" style="width:10px;height:10px;border-radius:50%;display:inline-block"></span>
            <?= e($d['label']) ?>
            <b><?= angka($sePeriode[$j] ?? 0) ?></b>
          </div>
        <?php endforeach; ?>
        <div class="baris lemah kecil">
          <?= angka($sePeriode['disetujui'] ?? 0) ?> terverifikasi · <?= angka($sePeriode['diajukan'] ?? 0) ?> menunggu
        </div>
      </div>
    </div>
  </div>
</div>

<?php endif; ?>

<?php if (can('pantau') && $tim): ?>
<div class="kartu mt16">
  <div class="kartu-kepala">
    <h2><?= ikon('grup', 18) ?> Tim yang Anda pantau</h2>
    <p class="ket"><?= count($tim) ?> pegawai · aktivitas bulan <?= e(NAMA_BULAN[(int) date('n')]) ?></p>
    <div class="aksi">
      <?php if ($antre['kegiatan'] > 0): ?>
        <a class="btn btn-emas btn-kecil" href="<?= e(url('pantau', ['tab' => 'verifikasi'])) ?>">
          <?= ikon('evaluasi', 15) ?> <?= $antre['kegiatan'] ?> kegiatan menunggu verifikasi
        </a>
      <?php endif; ?>
      <a class="btn btn-garis btn-kecil" href="<?= e(url('pantau')) ?>">Buka pemantauan <?= ikon('kanan', 14) ?></a>
    </div>
  </div>
  <div class="tabel-bungkus">
    <table class="tabel tabel-tim-tengah">
      <thead><tr><th>Pegawai</th><th class="angka">Rutin</th><th class="angka">Panitia</th><th class="angka">Insidental</th>
        <th class="angka">Jam</th><th>Kegiatan terakhir</th></tr></thead>
      <tbody>
      <?php foreach (array_slice($tim, 0, 8) as $t): $r = $ringTim[(int) $t['id']] ?? []; ?>
        <tr>
          <td><div class="orang"><?= avatar($t['nama'], 'kecil') ?><div><b><?= e($t['nama']) ?></b><span><?= e($t['unit_nama'] ?? '-') ?></span></div></div></td>
          <td class="angka"><?= angka($r['rutin'] ?? 0) ?></td>
          <td class="angka"><?= angka($r['panitia'] ?? 0) ?></td>
          <td class="angka"><?= angka($r['insidental'] ?? 0) ?></td>
          <td class="angka"><?= angka_ringkas($r['jam'] ?? 0, 1) ?></td>
          <td><?= !empty($r['terakhir']) ? e(tanggal_pendek($r['terakhir'])) : '<span class="lemah">belum ada</span>' ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>


