<?php
$pid = pid();
$saya = uid();
$periode = periode_dipilih();
$rekap = can('laporan_lihat');
$semua = $rekap && ($_GET['semua'] ?? '') === '1' && (int) ($_GET['p'] ?? 0) === 0;

$dari = (string) ($_GET['dari'] ?? $periode['mulai']);
$sampai = (string) ($_GET['sampai'] ?? $periode['selesai']);
if (!tanggal_sah($dari)) $dari = $periode['mulai'];
if (!tanggal_sah($sampai)) $sampai = $periode['selesai'];
if ($dari > $sampai) [$dari, $sampai] = [$sampai, $dari];
$twLap = (int) ($_GET['tw'] ?? 0);
if ($twLap >= 1 && $twLap <= 4) {
    [$dari, $sampai] = tw_rentang($periode, $twLap);
} else {
    $twLap = 0;
}
$pilihTw = static function () use ($twLap, $periode): string {
    $h = '<div class="form-baris"><label class="fl">Triwulan</label><select name="tw" data-pilih-tw>'
       . '<option value="">Sepanjang periode / tanggal bebas</option>';
    for ($t = 1; $t <= 4; $t++) {
        $h .= '<option value="' . $t . '"' . ($twLap === $t ? ' selected' : '') . '>' . e(tw_label($periode, $t)) . '</option>';
    }
    return $h . '</select></div>';
};

$pegawaiId = (int) ($_GET['p'] ?? 0);
if ($pegawaiId && (!boleh_lihat_pegawai($pegawaiId)
        || val('SELECT pr.kode FROM pengguna p JOIN peran pr ON pr.id = p.peran_id WHERE p.id = ?', [$pegawaiId], '') === 'admin')) {
    $pegawaiId = 0;
}
if (!$rekap && !$pegawaiId) {
    $pegawaiId = $saya;
}

if ($pegawaiId) {
    $pg = pegawai_get($pegawaiId);
    $kegiatan = rows(
        'SELECT k.* FROM kegiatan k
          WHERE k.periode_id = ? AND k.pengguna_id = ? AND k.tanggal BETWEEN ? AND ?' . sql_bukan_draft('k.id') . '
          ORDER BY k.tanggal, k.jam_mulai, k.id',
        [$pid, $pegawaiId, $dari, $sampai]
    );
    if (($_GET['unduh'] ?? '') === 'csv') {
        while (ob_get_level() > 0) ob_end_clean();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="kegiatan-' . preg_replace('/[^a-z0-9]+/i', '-', $pg['nama']) . '-' . $dari . '.csv"');
        $f = fopen('php://output', 'w');
        fwrite($f, "\xEF\xBB\xBF");
        fputcsv($f, ['Tanggal', 'Jam mulai', 'Jam selesai', 'Jenis', 'Kehadiran', 'Kegiatan', 'Kepanitiaan', 'Pemberi tugas', 'Uraian', 'Hasil', 'Satuan', 'Status', 'Bukti'], ';');
        foreach ($kegiatan as $k) {
            fputcsv($f, array_map('csv_aman', [$k['tanggal'], jam_pendek($k['jam_mulai']), jam_pendek($k['jam_selesai']), JENIS_KERJA[$k['jenis']]['pendek'],
                KEHADIRAN_KERJA[$k['kehadiran'] ?? 'masuk']['label'] ?? 'Masuk kerja', $k['judul'], $k['kepanitiaan'], $k['pemberi_tugas'], $k['uraian'], $k['hasil'], $k['satuan'],
                STATUS_KEGIATAN[$k['status']]['label'], $k['tautan_bukti']]), ';');
        }
        exit;
    }
    $evTw = evaluasi_tw_pegawai($pid, [$pegawaiId])[$pegawaiId] ?? [];
    $capaian = $twLap ? capaian_evaluasi($periode, $pegawaiId, $twLap, $evTw[$twLap] ?? null) : capaian_rentang_evaluasi($periode, $pegawaiId, $dari, $sampai, $evTw);
    $nc = $capaian['persen'];
    $tahunan = nilai_tahunan($evTw);
    $ring = ringkasan_kegiatan($pid, [$pegawaiId], $dari, $sampai)[$pegawaiId];
    $subjudul = $pg['nama'];
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-bottom:16px">
      <?php if ($rekap): ?><a class="btn btn-garis btn-kecil" href="<?= e(url('laporan', ['dari' => $dari, 'sampai' => $sampai])) ?>"><?= ikon('kembali', 14) ?> Rekap</a><?php endif; ?>
      <form method="get" class="saring" style="margin:0">
        <input type="hidden" name="h" value="laporan"><input type="hidden" name="p" value="<?= $pegawaiId ?>">
        <?= $pilihTw() ?>
        <div class="form-baris"><label class="fl">Dari</label><input type="date" name="dari" value="<?= e($dari) ?>"></div>
        <div class="form-baris"><label class="fl">Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>"></div>
        <button class="btn btn-garis" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
      </form>
      <div style="margin-left:auto;display:flex;gap:8px">
        <a class="btn btn-garis btn-kecil" href="<?= e(url('laporan', ['p' => $pegawaiId, 'dari' => $dari, 'sampai' => $sampai, 'unduh' => 'csv'])) ?>"><?= ikon('unduh', 14) ?> Unduh CSV</a>
        <button class="btn btn-utama btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak</button>
      </div>
    </div>

    <div class="kartu">
      <div class="kartu-isi" style="text-align:center;border-bottom:2px solid var(--merek-700)">
        <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="" style="height:56px">
        <h2 style="margin:6px 0 2px;font-size:1.15rem">LAPORAN KINERJA PEGAWAI</h2>
        <div class="kecil"><?= APP_UNIT ?> · <?= APP_INST ?></div>
        <div class="kecil lemah"><?= e($periode['nama']) ?> · <?= e(tanggal_id($dari)) ?> s.d. <?= e(tanggal_id($sampai)) ?></div>
      </div>
      <div class="kartu-isi">
        <div class="grid grid-2">
          <dl class="rinci">
            <dt>Nama</dt><dd><?= e($pg['nama']) ?></dd>
            <dt>NIP/NIK</dt><dd><?= e($pg['nip'] ?: '-') ?></dd>
            <dt>Jabatan</dt><dd><?= e($pg['jabatan'] ?: $pg['peran_nama']) ?></dd>
            <dt>Unit</dt><dd><?= e($pg['unit_nama'] ?? '-') ?></dd>
            <dt>Atasan</dt><dd><?= e($pg['atasan_nama'] ?? '-') ?></dd>
          </dl>
          <dl class="rinci">
            <dt>Jumlah kegiatan</dt><dd><?= $ring['total'] ?> (rutin <?= $ring['rutin'] ?>, panitia <?= $ring['panitia'] ?>, insidental <?= $ring['insidental'] ?>)</dd>
            <dt>Terverifikasi</dt><dd><?= $ring['disetujui'] ?> kegiatan</dd>
            <dt>Jam kerja tercatat</dt><dd><?= angka_ringkas($ring['jam'], 1) ?> jam</dd>
            <?php $tjP = target_jam_kerja($dari, $sampai); $kjP = keterangan_jam_kerja((float) $ring['jam'], (float) $tjP['jam']); ?>
            <dt>Target jam kerja</dt><dd><?= $tjP['jam'] > 0 ? angka($tjP['jam']) . ' jam (' . $tjP['bulan'] . ' bulan x ' . JAM_KERJA_BULANAN . ' jam)' : '-' ?></dd>
            <dt>Ketercapaian jam</dt><dd><?= $kjP['persen'] === null ? '-' : angka($kjP['persen'], 1) . '% · ' . lencana($kjP['label'], $kjP['warna']) ?></dd>
            <dt>Nilai tahunan</dt><dd><?= $tahunan === null ? 'Belum ada TW final' : angka($tahunan, 2) . ' (' . e(predikat_nilai($tahunan)['label']) . ')' ?></dd>
          </dl>
        </div>
      </div>

      <div class="kartu-kepala" style="background:#fafbf9;border-top:1px solid var(--garis)"><h2>Nilai kinerja per triwulan</h2></div>
      <div class="tabel-bungkus"><table class="tabel">
        <thead><tr><th>Triwulan</th><th class="angka">Capaian</th><th class="angka">Perilaku</th><th class="angka">Nilai akhir</th><th>Predikat</th><th>Status</th></tr></thead>
        <tbody>
        <?php for ($t = 1; $t <= 4; $t++): $e = $evTw[$t] ?? null; $fin = $e && $e['status'] === 'final'; ?>
          <tr><td><?= e(tw_label($periode, $t)) ?></td>
            <td class="angka"><?= $fin && $e['nilai_capaian'] !== null ? angka($e['nilai_capaian'], 2) : '-' ?></td>
            <td class="angka"><?= $fin && $e['nilai_perilaku'] !== null ? angka($e['nilai_perilaku'], 2) : '-' ?></td>
            <td class="angka"><b><?= $fin ? angka($e['nilai_akhir'], 2) : '-' ?></b></td>
            <td><?= $fin ? e($e['predikat']) : '-' ?></td>
            <td><?= $fin ? 'Final' : ($e ? 'Draft' : 'Belum dinilai') ?></td></tr>
        <?php endfor; ?>
        </tbody>
        <tfoot><tr><td colspan="3">Nilai tahunan (rata-rata TW final)</td><td class="angka"><?= $tahunan === null ? '-' : angka($tahunan, 2) ?></td>
          <td colspan="2"><?= $tahunan === null ? '' : e(predikat_nilai($tahunan)['label']) ?></td></tr></tfoot>
      </table></div>

      <div class="kartu-kepala" style="background:#fafbfd;border-top:1px solid var(--garis)"><h2>Rincian kegiatan</h2></div>
      <?php $perHari = [];
      foreach ($kegiatan as $k) {
          $perHari[$k['tanggal']]['n'] = ($perHari[$k['tanggal']]['n'] ?? 0) + 1;
          $perHari[$k['tanggal']]['jam'] = ($perHari[$k['tanggal']]['jam'] ?? 0) + durasi_jam($k['jam_mulai'], $k['jam_selesai']);
      } ?>
      <div class="tabel-bungkus"><table class="tabel tabel-per-hari tabel-laporan-rinci">
        <thead><tr><th>No</th><th>Jam</th><th>Total jam kerja</th><th>Kegiatan</th><th>Jenis</th><th>Hasil</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (!$kegiatan): ?><tr><td colspan="7" class="tengah lemah">Tidak ada kegiatan pada rentang ini.</td></tr><?php endif; ?>
        <?php $hariKini = null; $urutHari = 0; foreach ($kegiatan as $i => $k):
            if ($k['tanggal'] !== $hariKini): $hariKini = $k['tanggal']; $urutHari++; ?>
          <tr class="baris-hari"><td colspan="7"><?= ikon('kalender', 14) ?> <b><?= e(tanggal_id($hariKini, true)) ?></b>
            <span><?= (int) $perHari[$hariKini]['n'] ?> kegiatan &middot; <?= angka_ringkas($perHari[$hariKini]['jam'], 1) ?> jam</span></td></tr>
        <?php endif; $durasi = durasi_jam($k['jam_mulai'], $k['jam_selesai']); ?>
          <tr class="<?= $urutHari % 2 === 0 ? 'hari-genap' : '' ?>"><td class="tengah"><?= $i + 1 ?></td>
            <td class="nowrap tengah"><?= $k['jam_mulai'] ? e(jam_pendek($k['jam_mulai'])) . '-' . e(jam_pendek($k['jam_selesai'])) : '-' ?></td>
            <td class="nowrap tengah ab-durasi"><?= $durasi > 0 ? '<b>' . angka_ringkas($durasi, 1) . '</b> jam' : '-' ?></td>
            <td class="kiri"><span><?= e($k['judul']) ?></span> <?= lencana_kehadiran($k['kehadiran'] ?? null) ?>
              <?php if ($k['kepanitiaan']): ?><span class="sub"><?= e($k['kepanitiaan']) ?><?= $k['peran_panitia'] ? ' · ' . e($k['peran_panitia']) : '' ?></span><?php endif; ?>
              <?php if ($k['pemberi_tugas']): ?><span class="sub">Penugasan: <?= e($k['pemberi_tugas']) ?></span><?php endif; ?></td>
            <td class="tengah"><?= e(JENIS_KERJA[$k['jenis']]['pendek']) ?></td>
            <td class="kiri"><?= e($k['hasil'] ?: '-') ?><?= $k['hasil'] && $k['satuan'] ? ' <span class="lemah">(' . e($k['satuan']) . ')</span>' : '' ?></td>
            <td class="tengah"><?= e(STATUS_KEGIATAN[$k['status']]['label']) ?></td></tr>
        <?php endforeach; ?>
        </tbody>
        <?php if ($kegiatan): ?>
        <tfoot><tr class="baris-total"><td colspan="2"><b>Total</b></td><td class="tengah"><b><?= angka_ringkas(array_sum(array_column($perHari, 'jam')), 1) ?> jam</b></td><td colspan="4"><?= count($kegiatan) ?> kegiatan dalam <?= count($perHari) ?> hari</td></tr></tfoot>
        <?php endif; ?>
      </table></div>

      <div class="kartu-isi">
        <?php $atasanBiro = !empty($pg['atasan_id'])
            ? row('SELECT b.nama FROM pengguna a JOIN pengguna b ON b.id = a.atasan_id WHERE a.id = ?', [(int) $pg['atasan_id']])
            : null; ?>
        <?php $punyaAtasan = !empty($pg['atasan_id']); ?>
        <div class="grid grid-2" style="text-align:center;margin-top:12px<?= $atasanBiro ? ';grid-template-columns:repeat(3,minmax(0,1fr))' : ($punyaAtasan ? '' : ';grid-template-columns:minmax(0,1fr);justify-items:end') ?>">
          <?php if ($punyaAtasan): ?>
          <div><p class="kecil mb0">Mengetahui,<br>Atasan Langsung</p><div style="height:70px"></div>
            <b><?= e($pg['atasan_nama'] ?? '........................') ?></b></div>
          <?php endif; ?>
          <?php if ($atasanBiro): ?>
          <div><p class="kecil mb0">Mengetahui,<br>Kepala Biro</p><div style="height:70px"></div>
            <b><?= e($atasanBiro['nama']) ?></b></div>
          <?php endif; ?>
          <div><p class="kecil mb0"><?= e(tanggal_id(date('Y-m-d'))) ?><br>Pegawai yang bersangkutan</p><div style="height:70px"></div>
            <b><?= e($pg['nama']) ?></b></div>
        </div>
      </div>
    </div>
    <?php
    return;
}

$unitFilter = (int) ($_GET['unit'] ?? 0);
$ids = array_merge([$saya], tim_ids());
$pegawai = rows(
    'SELECT p.id, p.nama, p.jabatan, p.unit_id, un.nama AS unit_nama, un.urut AS unit_urut, pr.nama AS peran_nama, pr.urut AS peran_urut
       FROM pengguna p JOIN peran pr ON pr.id = p.peran_id LEFT JOIN unit un ON un.id = p.unit_id
      WHERE pr.kode <> \'admin\' AND p.id IN (' . in_placeholder($ids) . ')' . ($unitFilter ? ' AND p.unit_id IN (' . in_placeholder(unit_turunan($unitFilter)) . ')' : '') . '
      ORDER BY pr.urut, p.nama',
    $unitFilter ? array_merge($ids, unit_turunan($unitFilter)) : $ids
);
$pIds = array_map('intval', array_column($pegawai, 'id'));
$ring = ringkasan_kegiatan($pid, $pIds, $dari, $sampai);
$evs = evaluasi_tw_pegawai($pid, $pIds);
$targetJam = target_jam_kerja($dari, $sampai);
$data = [];
foreach ($pegawai as $p) {
    $id = (int) $p['id'];
    $evTw = $evs[$id] ?? [];
    $cap = $twLap ? capaian_evaluasi($periode, $id, $twLap, $evTw[$twLap] ?? null) : capaian_rentang_evaluasi($periode, $id, $dari, $sampai, $evTw);
    $nilaiTw = [];
    for ($t = 1; $t <= 4; $t++) {
        $e = $evTw[$t] ?? null;
        $nilaiTw[$t] = $e && $e['status'] === 'final' ? (float) $e['nilai_akhir'] : null;
    }
    $nilai = $twLap ? $nilaiTw[$twLap] : nilai_tahunan($evTw);
    $data[] = $p + [
        'r' => $ring[$id],
        'hari' => $cap['hari_tercatat'],
        'hari_kerja' => $cap['hari_kerja'],
        'ket_jam' => keterangan_jam_kerja((float) $ring[$id]['jam'], (float) $targetJam['jam']),
        'capaian' => $cap['persen'],
        'kosong' => !empty($cap['dikosongkan']),
        'cap_final' => !empty($cap['final']),
        'nilai_tw' => $nilaiTw,
        'nilai' => $nilai,
        'predikat' => $nilai !== null ? predikat_nilai($nilai)['label'] : '',
    ];
}

if (($_GET['unduh'] ?? '') === 'csv') {
    while (ob_get_level() > 0) ob_end_clean();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="rekap-kinerja-bauk-' . $dari . '-' . $sampai . '.csv"');
    $f = fopen('php://output', 'w');
    fwrite($f, "\xEF\xBB\xBF");
    fputcsv($f, ['Nama', 'Jabatan', 'Unit', 'Rutin', 'Kepanitiaan', 'Insidental', 'Jum. Kegiatan', 'Terverifikasi', 'Jum. Jam kerja',
                 'Target jam (' . JAM_KERJA_BULANAN . ' jam/bulan)', 'Ketercapaian jam (%)', 'Keterangan jam', 'Capaian pencatatan (%)', 'Nilai TW1', 'Nilai TW2', 'Nilai TW3', 'Nilai TW4',
                 $twLap ? 'Nilai TW' . $twLap : 'Nilai tahunan', 'Predikat'], ';');
    foreach ($data as $d) {
        $baris = [$d['nama'], $d['jabatan'], $d['unit_nama'], $d['r']['rutin'], $d['r']['panitia'], $d['r']['insidental'],
            $d['r']['total'], $d['r']['disetujui'], angka($d['r']['jam'], 1), $targetJam['jam'],
            $d['ket_jam']['persen'] === null ? '' : angka($d['ket_jam']['persen'], 1), $d['ket_jam']['label'],
            $d['capaian'] === null ? '' : angka($d['capaian'], 2)];
        foreach ($d['nilai_tw'] as $n) {
            $baris[] = $n === null ? '' : angka($n, 2);
        }
        $baris[] = $d['nilai'] === null ? '' : angka($d['nilai'], 2);
        $baris[] = $d['predikat'];
        fputcsv($f, array_map('csv_aman', $baris), ';');
    }
    exit;
}

$total = ['rutin' => 0, 'panitia' => 0, 'insidental' => 0, 'total' => 0, 'jam' => 0.0];
foreach ($data as $d) {
    foreach ($total as $k => $_) {
        $total[$k] += $d['r'][$k];
    }
}
$pred = array_count_values(array_filter(array_column($data, 'predikat')));
$subjudul = tanggal_id($dari) . ' s.d. ' . tanggal_id($sampai);
?>

<form method="get" class="saring saring-satu no-cetak">
  <input type="hidden" name="h" value="laporan">
  <?php if ($semua): ?><input type="hidden" name="semua" value="1"><?php endif; ?>
  <div class="form-baris"><label class="fl">Unit</label>
    <select name="unit"><?= opsi_unit($unitFilter, 'Semua unit') ?></select></div>
  <?= $pilihTw() ?>
  <div class="form-baris"><label class="fl">Dari</label><input type="date" name="dari" value="<?= e($dari) ?>"></div>
  <div class="form-baris"><label class="fl">Sampai</label><input type="date" name="sampai" value="<?= e($sampai) ?>"></div>
  <button class="btn btn-garis" type="submit"><?= ikon('filter', 15) ?> Terapkan</button>
  <div style="margin-left:auto;display:flex;gap:8px">
    <a class="btn btn-garis" href="<?= e(url('laporan', ['unit' => $unitFilter ?: null, 'tw' => $twLap ?: null, 'dari' => $twLap ? null : $dari, 'sampai' => $twLap ? null : $sampai, 'unduh' => 'csv'])) ?>"><?= ikon('unduh', 15) ?> Unduh CSV</a>
    <?php $paramRekap = ['unit' => $unitFilter ?: null, 'tw' => $twLap ?: null, 'dari' => $twLap ? null : $dari, 'sampai' => $twLap ? null : $sampai]; ?>
    <?php if ($semua): ?>
      <a class="btn btn-garis" href="<?= e(url('laporan', $paramRekap)) ?>"><?= ikon('kembali', 15) ?> Kembali ke rekap</a>
      <button class="btn btn-garis" type="button" data-buka="tiraiCetak"><?= ikon('filter', 15) ?> Pilihan cetak</button>
      <button class="btn btn-utama" type="button" data-cetak><?= ikon('cetak', 15) ?> Cetak semua</button>
    <?php else: ?>
      <button class="btn btn-utama" type="button" data-buka="tiraiCetak"><?= ikon('cetak', 15) ?> Cetak</button>
    <?php endif; ?>
  </div>
</form>

<?php $twPilih = $twLap ?: tw_berjalan($periode); ?>
<div class="tirai" id="tiraiCetak" hidden>
  <div class="modal sempit">
    <form method="get">
      <input type="hidden" name="h" value="laporan"><input type="hidden" name="cetak" value="1">
      <?php if ($unitFilter): ?><input type="hidden" name="unit" value="<?= (int) $unitFilter ?>"><?php endif; ?>
      <div class="modal-kepala"><h2>Pilihan cetak laporan</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris">
          <label class="fl">Periode laporan</label>
          <div class="opsi-cetak">
            <?php for ($t = 1; $t <= 4; $t++): ?>
              <label><input type="radio" name="tw" value="<?= $t ?>" <?= $twLap && $t === $twPilih ? 'checked' : (!$twLap && $t === $twPilih ? 'checked' : '') ?>>
                <span><b>Triwulan <?= $t ?></b><small><?= e(tw_label($periode, $t, true)) ?><?= $t === tw_berjalan($periode) ? ' · sedang berjalan' : '' ?></small></span></label>
            <?php endfor; ?>
            <label><input type="radio" name="tw" value="">
              <span><b>Seluruh periode</b><small>Dari awal sampai akhir periode kinerja</small></span></label>
          </div>
        </div>
        <div class="form-baris mb0">
          <label class="fl">Isi cetakan</label>
          <div class="opsi-cetak">
            <label><input type="radio" name="semua" value="" <?= $semua ? '' : 'checked' ?>>
              <span><b>Rekapitulasi saja</b><small>Satu tabel ringkasan seluruh pegawai</small></span></label>
            <label><input type="radio" name="semua" value="1" <?= $semua ? 'checked' : '' ?>>
              <span><b>Rekapitulasi + laporan tiap pegawai</b><small>Satu halaman laporan lengkap untuk setiap pegawai</small></span></label>
          </div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('cetak', 15) ?> Cetak</button>
      </div>
    </form>
  </div>
</div>

<div class="cetak-saja" style="text-align:center;margin-bottom:12px">
  <h2 style="margin:0">REKAPITULASI KINERJA PEGAWAI</h2>
  <div><?= APP_UNIT ?> · <?= APP_INST ?></div>
  <div><?= e($periode['nama']) ?> · <?= e(tanggal_id($dari)) ?> s.d. <?= e(tanggal_id($sampai)) ?></div>
  <?php if ($twLap): ?><div><b><?= e(tw_label($periode, $twLap, true)) ?></b></div><?php endif; ?>
</div>


<div class="kartu">
  <div class="kartu-kepala"><h2><?= ikon('rekap', 18) ?> Rekapitulasi kinerja pegawai</h2></div>
  <div class="tabel-bungkus"><table class="tabel tabel-rekap-tengah">
    <thead><tr><th class="kolom-no">No</th><th>Pegawai</th><th class="angka">Rutin</th><th class="angka">Kepanitiaan</th><th class="angka">Insidental</th><th class="angka">Jum. Kegiatan</th>
      <th class="angka">Jum. Jam kerja</th><th class="angka" title="<?= JAM_KERJA_HARIAN ?> jam x <?= HARI_KERJA_SEPEKAN ?> hari x <?= PEKAN_SEBULAN ?> minggu = <?= JAM_KERJA_BULANAN ?> jam per bulan">Target jam<span class="sub-judul"><?= JAM_KERJA_BULANAN ?> jam/bulan</span></th><th style="min-width:120px">Capaian</th>
      <th class="tengah">TW1</th><th class="tengah">TW2</th><th class="tengah">TW3</th><th class="tengah">TW4</th>
      <th class="angka"><?= $twLap ? 'Nilai TW' . $twLap : 'Nilai tahunan' ?></th><th>Predikat</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($data as $no => $d): $pr = predikat_nilai($d['nilai']); ?>
      <tr>
        <td class="kolom-no"><?= $no + 1 ?></td>
        <td class="kolom-pegawai"><div class="orang"><?= avatar($d['nama'], 'kecil') ?><div><b><?= e($d['nama']) ?></b><span><?= e($d['jabatan'] ?: $d['peran_nama']) ?></span></div></div></td>
        <td class="angka"><?= $d['r']['rutin'] ?></td><td class="angka"><?= $d['r']['panitia'] ?></td><td class="angka"><?= $d['r']['insidental'] ?></td>
        <td class="angka"><b><?= $d['r']['total'] ?></b></td>
        <td class="angka"><?= angka_ringkas($d['r']['jam'], 1) ?></td>
        <td class="angka sel-target-jam"><?php if ($targetJam['jam'] > 0): ?><b><?= angka($targetJam['jam']) ?></b> jam
          <span class="sub"><?= lencana($d['ket_jam']['label'], $d['ket_jam']['warna']) ?> <?= angka($d['ket_jam']['persen'], 1) ?>%</span><?php else: ?><span class="lemah">-</span><?php endif; ?></td>
        <td><?php if ($d['capaian'] !== null): ?><b class="kecil"><?= angka($d['capaian'], 1) ?>%</b><?= meter($d['capaian']) ?><?php if ($d['cap_final']): ?><span class="sub" title="Memakai capaian yang tersimpan saat evaluasi difinalkan">nilai saat final</span><?php endif; ?><?php else: ?><span class="lemah">-</span><?php endif; ?></td>
        <?php foreach ($d['nilai_tw'] as $t => $n): ?>
          <td class="tengah<?= $twLap === $t ? ' tebal' : '' ?>"><?= $n === null ? '<span class="lemah">-</span>' : angka($n, 1) ?></td>
        <?php endforeach; ?>
        <td class="angka"><b><?= $d['nilai'] === null ? '-' : angka($d['nilai'], 2) ?></b></td>
        <td><?= $d['nilai'] === null ? '<span class="lemah kecil">belum final</span>' : lencana($pr['label'], $pr['warna']) ?></td>
        <td class="no-cetak kanan-teks"><a class="btn btn-hantu btn-kecil" href="<?= e(url('laporan', ['p' => $d['id'], 'tw' => $twLap ?: null, 'dari' => $twLap ? null : $dari, 'sampai' => $twLap ? null : $sampai])) ?>">Rincian <?= ikon('kanan', 13) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot><tr><td colspan="2" class="kolom-pegawai">Jumlah</td><td class="angka"><?= $total['rutin'] ?></td><td class="angka"><?= $total['panitia'] ?></td><td class="angka"><?= $total['insidental'] ?></td>
      <td class="angka"><?= $total['total'] ?></td><td class="angka"><?= angka_ringkas($total['jam'], 1) ?></td>
      <td class="angka kecil"><?= $targetJam['jam'] > 0 ? $targetJam['bulan'] . ' bln x ' . JAM_KERJA_BULANAN . ' jam' : '' ?></td><td colspan="8"></td></tr></tfoot>
  </table></div>
</div>

<?php if ($semua): ?>
  <?php
  $untukPegawai = static function (int $idPegawai): void {
      $sebelumP = $_GET['p'] ?? null;
      $sebelumS = $_GET['semua'] ?? null;
      $_GET['p'] = (string) $idPegawai;
      unset($_GET['semua']);
      echo '<section class="laporan-pegawai-cetak">';
      require __FILE__;
      echo '</section>';
      if ($sebelumP === null) {
          unset($_GET['p']);
      } else {
          $_GET['p'] = $sebelumP;
      }
      if ($sebelumS !== null) {
          $_GET['semua'] = $sebelumS;
      }
  };
  foreach ($data as $d) {
      $untukPegawai((int) $d['id']);
  }
  ?>
<?php endif; ?>

<?php if (($_GET['cetak'] ?? '') === '1'): ?>
<script nonce="<?= csp_nonce() ?>">window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 500); });</script>
<?php endif; ?>
