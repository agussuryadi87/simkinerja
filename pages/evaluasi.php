<?php
$pid = pid();
$saya = uid();
$periode = periode_dipilih();
$terbuka = periode_terbuka();
$penilai = can('evaluasi_nilai');
$tw = tw_dipilih();
$twKini = tw_berjalan($periode);
$pegawaiId = isset($_GET['p']) ? (int) $_GET['p'] : 0;

if ($pegawaiId && $pegawaiId !== $saya && !($penilai && di_tim($pegawaiId)) && !boleh_lihat_pegawai($pegawaiId)) {
    $pegawaiId = 0;
}
if (!$pegawaiId && !$penilai) {
    $pegawaiId = $saya;
}
$bolehNilai = $pegawaiId && $pegawaiId !== $saya && $penilai && di_tim($pegawaiId) && $terbuka;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$bolehNilai) {
        tolak_akses();
    }
    $op = post('op');
    $ev = evaluasi_get($pid, $pegawaiId, $tw);
    $kembali = ['p' => $pegawaiId, 'tw' => $tw];

    if ($op === 'buka') {
        if ($ev) {
            update('evaluasi', ['status' => 'draft', 'difinalkan_pada' => null], 'id = ?', [$ev['id']]);
            catat_log('buka_evaluasi', 'evaluasi', (int) $ev['id'], 'TW' . $tw);
            flash('Evaluasi TW' . $tw . ' dibuka kembali untuk diubah.', 'info');
        }
        kembali_ke('evaluasi', $kembali);
    }

    if ($ev && $ev['status'] === 'final') {
        flash('Evaluasi TW' . $tw . ' sudah final. Buka kembali terlebih dahulu untuk mengubah.', 'warn');
        kembali_ke('evaluasi', $kembali);
    }

    if ($op === 'kosongkan' || $op === 'hitung_ulang') {
        if (!atur_capaian_kosong($pid, $pegawaiId, $tw, $op === 'kosongkan')) {
            flash('Penanda capaian tidak dapat disimpan di server.', 'galat');
            kembali_ke('evaluasi', $kembali);
        }
        if (!$ev) {
            flash($op === 'kosongkan' ? 'Penilaian TW' . $tw . ' dikosongkan.' : 'Capaian pencatatan TW' . $tw . ' dihitung ulang dari kegiatan terverifikasi.', 'info');
            kembali_ke('evaluasi', $kembali);
        }
    }

    $data = ['penilai_id' => $saya];
    foreach (array_keys(ASPEK_PERILAKU) as $k) {
        $n = $op === 'kosongkan' ? 0 : (int) post($k);
        $data[$k] = $n >= 1 && $n <= 5 ? $n : null;
    }
    $data['kekuatan'] = mb_substr(post('kekuatan'), 0, 4000);
    $data['perbaikan'] = mb_substr(post('perbaikan'), 0, 4000);

    $nc = capaian_harian($periode, $pegawaiId, $tw)['persen'];
    $np = nilai_perilaku($data);
    $na = nilai_akhir($nc, $np);
    $data['nilai_capaian'] = $nc !== null ? round($nc, 2) : null;
    $data['nilai_perilaku'] = $np !== null ? round($np, 2) : null;
    $data['nilai_akhir'] = $na !== null ? round($na, 2) : null;
    $data['predikat'] = $na !== null ? predikat_nilai($na)['label'] : '';

    if ($op === 'final') {
        $kosong = array_filter(array_keys(ASPEK_PERILAKU), static function ($k) use ($data) { return $data[$k] === null; });
        if ($kosong || $nc === null) {
            $data['status'] = 'draft';
            $ev ? update('evaluasi', $data, 'id = ?', [$ev['id']])
                : insert('evaluasi', $data + ['periode_id' => $pid, 'pegawai_id' => $pegawaiId, 'tw' => $tw]);
            flash(capaian_dikosongkan($pid, $pegawaiId, $tw) ? 'Capaian pencatatan masih dikosongkan. Klik Hitung ulang capaian terlebih dahulu. Disimpan sebagai draft.'
                : ($nc === null ? 'Triwulan ini belum berjalan sehingga capaian pencatatan harian belum dapat dihitung. Disimpan sebagai draft.'
                               : 'Lengkapi penilaian ketujuh aspek perilaku sebelum memfinalkan. Disimpan sebagai draft.'), 'warn');
            kembali_ke('evaluasi', $kembali);
        }
        $data['status'] = 'final';
        $data['difinalkan_pada'] = date('Y-m-d H:i:s');
    }

    if ($ev) {
        update('evaluasi', $data, 'id = ?', [$ev['id']]);
        $evId = (int) $ev['id'];
    } else {
        $evId = insert('evaluasi', $data + ['periode_id' => $pid, 'pegawai_id' => $pegawaiId, 'tw' => $tw]);
    }
    catat_log($op === 'final' ? 'final_evaluasi' : 'simpan_evaluasi', 'evaluasi', $evId, 'TW' . $tw . ' ' . $data['nilai_akhir']);
    if ($op === 'final') {
        flash('Evaluasi TW' . $tw . ' difinalkan dengan nilai <b>' . angka($data['nilai_akhir'], 2) . '</b> (' . e($data['predikat']) . ').');
    } elseif ($op === 'kosongkan') {
        flash('Penilaian TW' . $tw . ' dikosongkan: capaian pencatatan dan nilai perilaku 1-5. Klik Hitung ulang capaian bila ingin menghitung kembali.', 'info');
    } elseif ($op === 'hitung_ulang') {
        flash('Capaian pencatatan TW' . $tw . ' dihitung ulang dari kegiatan terverifikasi.', 'info');
    } else {
        flash('Draft evaluasi TW' . $tw . ' tersimpan.');
    }
    kembali_ke('evaluasi', $kembali);
}

$tabTw = static function (array $param, ?array $evTw = null) use ($periode, $tw, $twKini): string {
    $h = '<nav class="tw-tab no-cetak">';
    for ($t = 1; $t <= 4; $t++) {
        [$m, $s] = tw_rentang($periode, $t);
        $ev = $evTw[$t] ?? null;
        $h .= '<a href="' . e(url('evaluasi', $param + ['tw' => $t])) . '" class="' . ($t === $tw ? 'aktif' : '') . '">'
            . '<b>TW' . $t . ($t === $twKini ? ' · berjalan' : '') . '</b>'
            . '<span>' . e(tanggal_pendek($m) . ' s.d. ' . tanggal_pendek($s)) . '</span>'
            . ($evTw !== null ? ($ev && $ev['status'] === 'final'
                ? lencana(angka($ev['nilai_akhir'], 2), 'hijau')
                : ($ev ? lencana('Draft', 'emas') : lencana('Belum dinilai'))) : '')
            . '</a>';
    }
    return $h . '</nav>';
};
?>

<?php if (!$pegawaiId): ?>
  <?php
  $tim = array_values(array_filter(pegawai_tim(), static function ($t) { return $t['peran_kode'] !== 'admin'; }));
  $ids = array_map('intval', array_column($tim, 'id'));
  $evAll = evaluasi_tw_pegawai($pid, $ids);
  $final = 0;
  $jumlahNilai = 0.0;
  foreach ($ids as $id) {
      $e = $evAll[$id][$tw] ?? null;
      if ($e && $e['status'] === 'final') {
          $final++;
          $jumlahNilai += (float) $e['nilai_akhir'];
      }
  }
  $rata = $final ? $jumlahNilai / $final : null;
  $subjudul = tw_label($periode, $tw, true);
  ?>
  <?= $tabTw([]) ?>


  <div class="kartu">
    <div class="kartu-kepala"><div><h2><?= ikon('evaluasi', 18) ?> Daftar evaluasi tim · TW<?= $tw ?></h2>
      <p class="ket"><?= e(tw_label($periode, $tw, true)) ?></p></div>
      <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url('evaluasi', ['p' => $saya, 'tw' => $tw])) ?>"><?= ikon('profil', 14) ?> Evaluasi saya</a></div></div>
    <?php if (!$tim): ?>
      <?= sel_kosong('Belum ada pegawai dalam tim', '') ?>
    <?php else: ?>
    <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-tim-tengah ada-no">
      <thead><tr><th>No</th><th>Pegawai</th><th class="angka">Capaian</th><th class="angka">Perilaku</th><th class="angka">Nilai TW<?= $tw ?></th>
        <th>Predikat</th><th>Status</th><th class="tengah">TW1</th><th class="tengah">TW2</th><th class="tengah">TW3</th><th class="tengah">TW4</th>
        <th class="angka">Nilai tahunan</th><th>Nilai</th></tr></thead>
      <tbody>
      <?php foreach ($tim as $no => $t): $id = (int) $t['id']; $evTw = $evAll[$id] ?? []; $ev = $evTw[$tw] ?? null;
          $fin = $ev && $ev['status'] === 'final';
          $ncBaris = capaian_evaluasi($periode, $id, $tw, $ev)['persen'];
          $npBaris = $ev && $ev['nilai_perilaku'] !== null ? (float) $ev['nilai_perilaku'] : null;
          $naBaris = $fin ? ($ev['nilai_akhir'] !== null ? (float) $ev['nilai_akhir'] : null) : ($ev ? nilai_akhir($ncBaris, $npBaris) : null);
          $tahunan = nilai_tahunan($evTw); ?>
        <tr>
          <td class="kolom-no"><?= $no + 1 ?></td>
          <td><div class="orang"><?= avatar($t['nama'], 'kecil') ?><div><b><?= e($t['nama']) ?></b><span><?= e($t['unit_nama'] ?? '') ?></span></div></div></td>
          <td class="angka"><?= $ncBaris === null ? '-' : ($fin ? angka($ncBaris, 2) : '<span class="lemah">' . angka($ncBaris, 2) . '</span>') ?></td>
          <td class="angka"><?= $npBaris === null ? '-' : angka($npBaris, 2) ?></td>
          <td class="angka"><?= $naBaris === null ? '-' : ($fin ? '<b>' . angka($naBaris, 2) . '</b>' : '<span class="lemah" title="Nilai sementara, belum final">' . angka($naBaris, 2) . '</span>') ?></td>
          <td><?php if ($naBaris === null): ?><span class="lemah">-</span><?php else: $prB = predikat_nilai($naBaris); ?><?= $fin ? lencana($prB['label'], $prB['warna']) : '<span class="lemah kecil">' . e($prB['label']) . ' (sementara)</span>' ?><?php endif; ?></td>
          <td><?= !$ev ? lencana('Belum dinilai') : ($ev['status'] === 'final' ? lencana('Final', 'hijau') : lencana('Draft', 'emas')) ?></td>
          <?php for ($n = 1; $n <= 4; $n++): $e = $evTw[$n] ?? null; ?>
            <td class="tengah"><?= $e && $e['status'] === 'final' ? angka($e['nilai_akhir'], 1) : '<span class="lemah">-</span>' ?></td>
          <?php endfor; ?>
          <td class="angka"><b><?= $tahunan === null ? '-' : angka($tahunan, 2) ?></b></td>
          <td class="kanan-teks"><a class="btn btn-<?= $ev && $ev['status'] === 'final' ? 'garis' : 'utama' ?> btn-kecil" href="<?= e(url('evaluasi', ['p' => $id, 'tw' => $tw])) ?>">
            <?= $ev && $ev['status'] === 'final' ? 'Lihat' : 'Nilai' ?> <?= ikon('kanan', 14) ?></a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </div>
  <?php return; ?>
<?php endif; ?>

<?php
$pg = pegawai_get($pegawaiId);
$evTw = evaluasi_tw_pegawai($pid, [$pegawaiId])[$pegawaiId] ?? [];
$ev = $evTw[$tw] ?? null;
if ($ev) {
    $ev['penilai_nama'] = val('SELECT nama FROM pengguna WHERE id = ?', [(int) $ev['penilai_id']], '-');
}
$milikSendiri = $pegawaiId === $saya;
$final = $ev && $ev['status'] === 'final';
$tampilNilai = !$milikSendiri || $final;
$capaian = capaian_harian($periode, $pegawaiId, $tw);
$nc = $capaian['persen'];
$capKosong = !empty($capaian['dikosongkan']);
$edit = $bolehNilai && !$final;
$subjudul = $pg['nama'] . ' · ' . tw_label($periode, $tw);
$np = $ev ? nilai_perilaku($ev) : null;
$na = $final ? (float) $ev['nilai_akhir'] : nilai_akhir($nc, $np);
$pred = predikat_nilai($na);
$tahunan = nilai_tahunan($evTw);
[$twMulai, $twSelesai] = tw_rentang($periode, $tw);
?>

<?php if ($penilai): ?>
  <p class="no-cetak"><a href="<?= e(url('evaluasi', ['tw' => $tw])) ?>"><?= ikon('kembali', 15) ?> Kembali ke daftar evaluasi</a></p>
<?php endif; ?>

<?= $tabTw(['p' => $pegawaiId], $milikSendiri ? array_filter($evTw, static function ($e) { return $e['status'] === 'final'; }) : $evTw) ?>

<?php if ($milikSendiri && !$final): ?>
  <div class="notif notif-info"><?= ikon('info', 17) ?><div>Evaluasi TW<?= $tw ?> Anda belum difinalkan oleh atasan. Berikut capaian pencatatan
    sementara berdasarkan kegiatan terverifikasi pada <?= e(tanggal_id($twMulai)) ?> s.d. <?= e(tanggal_id($twSelesai)) ?>.</div></div>
<?php endif; ?>

<form method="post" <?= $edit ? 'data-hitung-nilai' : '' ?> data-capaian="<?= $nc ?? '' ?>" data-bobot-capaian="<?= BOBOT_CAPAIAN ?>" data-bobot-perilaku="<?= BOBOT_PERILAKU ?>">
  <?= csrf_field() ?>
  <div class="grid grid-21">
    <div>
      <div class="kartu">
        <div class="kartu-kepala">
          <div class="orang"><?= avatar($pg['nama'], 'besar') ?><div><b style="font-size:1rem"><?= e($pg['nama']) ?></b>
            <span><?= e($pg['jabatan'] ?: $pg['peran_nama']) ?> · <?= e($pg['unit_nama'] ?? '-') ?></span></div></div>
          <div class="aksi"><?= lencana(tw_label($periode, $tw), 'ungu') ?>
            <?= $final ? lencana('Final', 'hijau') : ($ev ? lencana('Draft', 'emas') : lencana('Belum dinilai')) ?>
            <?php if ($edit): ?>
              <button class="btn btn-garis btn-kecil no-cetak" type="submit" name="op" value="kosongkan" formnovalidate
                data-konfirmasi="Kosongkan penilaian TW<?= $tw ?>? Capaian pencatatan (A) dan nilai perilaku 1-5 (B) akan dikosongkan. Data catatan harian pegawai tidak dihapus."><?= ikon('ulang', 14) ?> Kosongkan penilaian</button>
            <?php endif; ?>
            <button class="btn btn-garis btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak</button></div>
        </div>
        <div class="kartu-kepala" style="background:#fafbf9"><div><h2><?= ikon('catatan', 18) ?> A. Capaian pencatatan kerja harian TW<?= $tw ?></h2>
          <p class="ket"><?= e(tanggal_id($twMulai)) ?> s.d. <?= e(tanggal_id($twSelesai)) ?> · bobot <?= angka(BOBOT_CAPAIAN * 100) ?>% dari nilai akhir</p></div>
          <?php if ($capKosong && $edit): ?>
            <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="submit" name="op" value="hitung_ulang" formnovalidate><?= ikon('ulang', 14) ?> Hitung ulang capaian</button></div>
          <?php endif; ?></div>
        <div class="tabel-bungkus"><table class="tabel">
          <tbody>
            <?php if ($capKosong): ?>
            <tr><td>Hari kerja dalam triwulan (sampai hari ini)</td><td class="angka lemah">-</td></tr>
            <tr><td>Hari kerja dengan kegiatan terverifikasi</td><td class="angka lemah">-</td></tr>
            <tr><td>Kegiatan terverifikasi dari seluruh kegiatan</td><td class="angka lemah">-</td></tr>
            <tr><td>Jam kerja terverifikasi</td><td class="angka lemah">-</td></tr>
            <?php else: ?>
            <tr><td>Hari kerja dalam triwulan (sampai hari ini)</td><td class="angka"><b><?= angka($capaian['hari_kerja']) ?></b> hari</td></tr>
            <tr><td>Hari kerja dengan kegiatan terverifikasi</td><td class="angka"><b><?= angka($capaian['hari_tercatat']) ?></b> hari</td></tr>
            <tr><td>Kegiatan terverifikasi dari seluruh kegiatan</td><td class="angka"><b><?= angka($capaian['kegiatan_ok']) ?></b> dari <?= angka($capaian['kegiatan_total']) ?></td></tr>
            <tr><td>Jam kerja terverifikasi</td><td class="angka"><b><?= angka_ringkas($capaian['jam'], 1) ?></b> jam</td></tr>
            <tr><td>Jam kerja tercatat dari target <?= angka($capaian['target_jam'] ?? 0) ?> jam per triwulan</td><td class="angka"><b><?= angka_ringkas($capaian['jam_diakui'] ?? 0, 1) ?></b> jam</td></tr>
            <?php endif; ?>
          </tbody>
          <tfoot><tr><td>Capaian pencatatan TW<?= $tw ?></td><td class="angka"><?= $nc === null ? '-' : angka($nc, 1) . '%' ?><?= $nc === null ? '' : meter($nc) ?></td></tr></tfoot>
        </table></div>
      </div>

      <?php if ($tampilNilai): ?>
      <div class="kartu">
        <div class="kartu-kepala" style="background:#fafbf9"><div><h2><?= ikon('bintang', 18) ?> B. Perilaku kerja TW<?= $tw ?> (nilai BerAKHLAK)</h2>
          <p class="ket">Skala 1-5 · bobot <?= angka(BOBOT_PERILAKU * 100) ?>%</p></div>
</div>
        <div class="kartu-isi" style="padding-top:6px;padding-bottom:6px">
          <?php foreach (ASPEK_PERILAKU as $k => [$nama, $ket]): $nilai = (int) ($ev[$k] ?? 0); ?>
            <div class="aspek">
              <div><b><?= e($nama) ?></b><p><?= e($ket) ?></p></div>
              <div class="skala">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                  <label title="<?= e(SKALA_PERILAKU[$i]) ?>"><input type="radio" name="<?= $k ?>" value="<?= $i ?>" <?= $nilai === $i ? 'checked' : '' ?> <?= $edit ? '' : 'disabled' ?>><span><?= $i ?></span></label>
                <?php endfor; ?>
              </div>
            </div>
          <?php endforeach; ?>
          <div class="skala-ket"><?php foreach (SKALA_PERILAKU as $i => $l): ?><span class="sk-<?= $i ?>"><b><?= $i ?></b> <?= e($l) ?></span><?php endforeach; ?></div>
        </div>
      </div>

      <div class="kartu">
        <div class="kartu-kepala" style="background:#fafbf9"><h2><?= ikon('pesan', 18) ?> C. Catatan penilai</h2></div>
        <div class="kartu-isi">
          <div class="form-baris">
            <label class="fl" for="eKekuatan">Kekuatan / prestasi menonjol</label>
            <?php if ($edit): ?>
              <textarea id="eKekuatan" name="kekuatan" rows="3" maxlength="4000"><?= e($ev['kekuatan'] ?? '') ?></textarea>
            <?php else: ?><p class="mb0"><?= nl2br(e($ev['kekuatan'] ?? '-') ?: '-') ?></p><?php endif; ?>
          </div>
          <div class="form-baris mb0">
            <label class="fl" for="ePerbaikan">Area perbaikan &amp; kebutuhan pengembangan</label>
            <?php if ($edit): ?>
              <textarea id="ePerbaikan" name="perbaikan" rows="3" maxlength="4000"><?= e($ev['perbaikan'] ?? '') ?></textarea>
            <?php else: ?><p class="mb0"><?= nl2br(e($ev['perbaikan'] ?? '-') ?: '-') ?></p><?php endif; ?>
          </div>
        </div>
      </div>
      <?php endif; ?>
    </div>

    <div>
      <div class="kartu" style="position:sticky;top:80px">
        <div class="kartu-kepala"><h2><?= ikon('rekap', 18) ?> Ringkasan nilai TW<?= $tw ?></h2></div>
        <div class="kartu-isi">
          <div class="skor-besar">
            <div class="angka-besar" data-out="akhir"><?= $tampilNilai && $na !== null ? angka($na, 2) : '-' ?></div>
            <div class="mt8"><span class="lc lc-<?= e($tampilNilai ? $pred['warna'] : 'abu') ?>" data-out="predikat"><?= e($tampilNilai ? $pred['label'] : 'Belum final') ?></span></div>
            <div class="ket">Nilai akhir TW<?= $tw ?></div>
          </div>
          <dl class="rinci mt16">
            <dt>Capaian pencatatan</dt><dd><?= $final && $ev['nilai_capaian'] !== null ? angka($ev['nilai_capaian'], 2) : ($nc === null ? '-' : angka($nc, 2)) ?></dd>
            <dt>Perilaku kerja</dt><dd data-out="perilaku"><?= $tampilNilai && $np !== null ? angka($np, 2) : '-' ?></dd>
            <dt>Penilai</dt><dd><?= e($ev['penilai_nama'] ?? '-') ?></dd>
            <?php if ($final): ?><dt>Difinalkan</dt><dd><?= e(tanggal_id(substr($ev['difinalkan_pada'], 0, 10))) ?></dd><?php endif; ?>
          </dl>

          <div class="mt16">
            <div class="kecil lemah" style="margin-bottom:6px">Nilai per triwulan</div>
            <div class="nilai-tw">
              <?php for ($n = 1; $n <= 4; $n++): $e = $evTw[$n] ?? null; ?>
                <div><span>TW<?= $n ?></span><b><?= $e && $e['status'] === 'final' ? angka($e['nilai_akhir'], 1) : '-' ?></b></div>
              <?php endfor; ?>
            </div>
            <p class="mt8 mb0 kecil">Nilai tahunan (rata-rata TW final): <b><?= $tahunan === null ? '-' : angka($tahunan, 2) ?></b>
              <?= $tahunan !== null ? lencana(predikat_nilai($tahunan)['label'], predikat_nilai($tahunan)['warna']) : '' ?></p>
          </div>
          <?php
          $rentangPredikat = [[90, null, 'Sangat Baik', 'hijau'], [76, 90, 'Baik', 'biru'], [61, 76, 'Cukup', 'emas'], [51, 61, 'Kurang', 'jingga'], [null, 51, 'Sangat Kurang', 'merah']];
          $ncNilai = $final && $ev['nilai_capaian'] !== null ? (float) $ev['nilai_capaian'] : $nc;
          $kc = $ncNilai !== null ? $ncNilai * BOBOT_CAPAIAN : null;
          $kp = $np !== null ? $np * BOBOT_PERILAKU : null;
          $penjelasan = '';
          if ($tampilNilai && $na !== null) {
              $penjelasan = 'Nilai akhir ' . angka($na, 2) . ' termasuk predikat <b>' . e($pred['label']) . '</b>. ';
              $rasioC = $ncNilai ?? 0;
              $rasioP = $np ?? 0;
              $penjelasan .= $ncNilai === null
                  ? 'Komponen terlemah: capaian pencatatan masih kosong sehingga belum menyumbang poin (maksimal ' . angka(BOBOT_CAPAIAN * 100) . ' poin).'
                  : ($rasioC <= $rasioP
                  ? 'Komponen terlemah: capaian pencatatan baru ' . angka($rasioC, 2) . '% sehingga hanya menyumbang ' . angka($kc ?? 0, 2) . ' dari maksimal ' . angka(BOBOT_CAPAIAN * 100) . ' poin.'
                  : 'Komponen terlemah: nilai perilaku kerja ' . angka($rasioP, 2) . ' hanya menyumbang ' . angka($kp ?? 0, 2) . ' dari maksimal ' . angka(BOBOT_PERILAKU * 100) . ' poin.');
              foreach (array_reverse($rentangPredikat) as [$min, $maks, $label]) {
                  if ($min !== null && $na < $min) {
                      $kurang = $min - $na;
                      $penjelasan .= ' Untuk naik ke predikat <b>' . e($label) . '</b> dibutuhkan tambahan ' . angka($kurang, 2) . ' poin'
                          . ($rasioC + $kurang / BOBOT_CAPAIAN <= 100 ? ', setara kenaikan capaian pencatatan sekitar ' . angka($kurang / BOBOT_CAPAIAN, 1) . '%.' : '.');
                      break;
                  }
              }
          }
          ?>
          <div class="penjelasan-nilai mt16" data-penjelasan-nilai>
            <div class="kecil lemah" style="margin-bottom:6px">Cara menghitung nilai akhir</div>
            <table class="tabel tabel-penjelasan">
              <thead><tr><th>Komponen</th><th>Nilai</th><th>Bobot</th><th>Kontribusi</th></tr></thead>
              <tbody>
                <tr><td>A. Capaian pencatatan</td><td><?= $ncNilai === null ? '-' : angka($ncNilai, 2) ?></td><td><?= angka(BOBOT_CAPAIAN * 100) ?>%</td><td data-out="kontribusi-capaian"><?= $kc === null ? '-' : angka($kc, 2) ?></td></tr>
                <tr><td>B. Perilaku kerja</td><td data-out="perilaku-tabel"><?= $tampilNilai && $np !== null ? angka($np, 2) : '-' ?></td><td><?= angka(BOBOT_PERILAKU * 100) ?>%</td><td data-out="kontribusi-perilaku"><?= $tampilNilai && $kp !== null ? angka($kp, 2) : '-' ?></td></tr>
              </tbody>
              <tfoot><tr><td colspan="3">Nilai akhir (A + B)</td><td data-out="akhir-tabel"><?= $tampilNilai && $na !== null ? angka($na, 2) : '-' ?></td></tr></tfoot>
            </table>
            <div class="kecil lemah" style="margin:12px 0 6px">Rentang predikat</div>
            <table class="tabel tabel-penjelasan tabel-predikat">
              <thead><tr><th>Rentang nilai akhir</th><th>Predikat</th></tr></thead>
              <tbody>
                <?php foreach ($rentangPredikat as [$min, $maks, $label, $warna]): ?>
                  <tr data-predikat="<?= e($label) ?>"<?= $tampilNilai && $na !== null && $pred['label'] === $label ? ' class="aktif"' : '' ?>>
                    <td><?= $min === null ? 'di bawah ' . $maks : ($maks === null ? $min . ' ke atas' : $min . ' s.d. di bawah ' . $maks) ?></td>
                    <td><?= lencana($label, $warna) ?></td></tr>
                <?php endforeach; ?>
              </tbody>
            </table>
            <p class="penjelasan-teks mt12 mb0" data-out="penjelasan"<?= $penjelasan === '' ? ' hidden' : '' ?>><?= $penjelasan ?></p>
          </div>

          <?php if ($final && $tampilNilai): [$jenisRek, $teksRek] = rekomendasi_predikat($ev['predikat']); ?>
            <div class="notif notif-info mt12 mb0"><?= ikon('tumbuh', 17) ?><div><b>Rekomendasi:</b> <?= e($teksRek) ?></div></div>
          <?php endif; ?>

          <div class="mt16 no-cetak" style="display:flex;flex-direction:column;gap:8px">
            <?php if ($edit): ?>
              <button class="btn btn-garis" type="submit" name="op" value="simpan"><?= ikon('simpan', 15) ?> Simpan draft</button>
              <button class="btn btn-utama" type="submit" name="op" value="final"
                      data-konfirmasi="Finalkan evaluasi TW<?= $tw ?>? Nilai akan terlihat oleh pegawai." data-gaya="utama"><?= ikon('centang', 15) ?> Finalkan evaluasi TW<?= $tw ?></button>
            <?php elseif ($bolehNilai && $final): ?>
              <button class="btn btn-garis" type="submit" name="op" value="buka" data-konfirmasi="Buka kembali evaluasi TW<?= $tw ?> yang sudah final?"><?= ikon('ulang', 15) ?> Buka kembali</button>
              <a class="btn btn-emas" href="<?= e(url('pengembangan', ['p' => $pegawaiId])) ?>"><?= ikon('tumbuh', 15) ?> Tindak lanjut pengembangan</a>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</form>
