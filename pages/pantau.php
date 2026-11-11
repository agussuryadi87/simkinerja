<?php
$pid = pid();
$terbuka = periode_terbuka();
$tim = pegawai_tim();
$timIds = array_map('intval', array_column($tim, 'id'));
$tim = array_values(array_filter($tim, static function ($t) { return $t['peran_kode'] !== 'admin'; }));
$tab = in_array($_GET['tab'] ?? '', ['tim', 'verifikasi', 'pegawai'], true) ? $_GET['tab'] : 'tim';
$pegawaiId = (int) ($_GET['p'] ?? 0);
if ($pegawaiId && !in_array($pegawaiId, $timIds, true)) {
    $pegawaiId = 0;
}
if ($tab === 'pegawai' && !$pegawaiId) {
    $tab = 'tim';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $kembali = ['tab' => post('kembali_tab') ?: $tab, 'p' => $pegawaiId ?: null];

    if (!$terbuka && $op !== 'umpan') {
        flash('Periode ini sudah ditutup.', 'galat');
        kembali_ke('pantau', $kembali);
    }

    if ($op === 'verifikasi_massal') {
        $ids = array_values(array_filter(array_map('intval', (array) ($_POST['kegiatan'] ?? []))));
        if ($ids && $timIds) {
            $n = q(
                "UPDATE kegiatan SET status = 'disetujui', diverifikasi_oleh = ?, diverifikasi_pada = NOW(), catatan_atasan = ''
                  WHERE periode_id = ? AND status = 'diajukan' AND id IN (" . in_placeholder($ids) . ")
                    AND pengguna_id IN (" . in_placeholder($timIds) . ')' . sql_bukan_draft(),
                array_merge([uid(), $pid], $ids, $timIds)
            )->rowCount();
            catat_log('verifikasi_kegiatan', 'kegiatan', 0, $n . ' kegiatan');
            flash($n . ' kegiatan terverifikasi.');
        }
    } elseif (in_array($op, ['setujui', 'revisi'], true)) {
        $k = row('SELECT * FROM kegiatan WHERE id = ? AND periode_id = ?', [(int) post('id'), $pid]);
        if (!$k || !in_array((int) $k['pengguna_id'], $timIds, true)) {
            tolak_akses();
        }
        if (kegiatan_draft((int) $k['id'])) {
            flash('Kegiatan ini masih draft dan belum dikirim pegawai, sehingga belum dapat diverifikasi atau dinilai.', 'warn');
            kembali_ke('pantau', $kembali);
        }
        $catatan = mb_substr(post('catatan'), 0, 1000);
        if ($op === 'revisi' && $catatan === '') {
            flash('Tuliskan catatan perbaikan untuk pegawai.', 'galat');
            kembali_ke('pantau', $kembali);
        }
        update('kegiatan', [
            'status'            => $op === 'setujui' ? 'disetujui' : 'revisi',
            'catatan_atasan'    => $catatan,
            'diverifikasi_oleh' => uid(),
            'diverifikasi_pada' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$k['id']]);
        catat_log($op . '_kegiatan', 'kegiatan', (int) $k['id'], $catatan);
        flash($op === 'setujui' ? 'Kegiatan terverifikasi.' : 'Kegiatan dikembalikan untuk diperbaiki.');
    } elseif ($op === 'ubah_kegiatan' || $op === 'hapus_kegiatan') {
        $k = row('SELECT * FROM kegiatan WHERE id = ? AND periode_id = ?', [(int) post('id'), $pid]);
        if (!$k || !in_array((int) $k['pengguna_id'], $timIds, true)) {
            tolak_akses();
        }
        if ($op === 'hapus_kegiatan') {
            q('DELETE FROM kegiatan WHERE id = ?', [$k['id']]);
            hapus_foto_kegiatan((int) $k['id']);
            atur_kegiatan_draft((int) $k['id'], false);
            catat_log('hapus_kegiatan_atasan', 'kegiatan', (int) $k['id'], $k['judul']);
            flash('Kegiatan dihapus.');
            kembali_ke('pantau', $kembali);
        }
        $periodeAktif = periode_dipilih();
        $kehadiran = array_key_exists(post('kehadiran'), KEHADIRAN_KERJA) ? post('kehadiran') : 'masuk';
        $absen = $kehadiran !== 'masuk';
        $jenis = !$absen && array_key_exists(post('jenis'), JENIS_KERJA) ? post('jenis') : 'rutin';
        $tanggal = post('tanggal');
        $mulai = $absen ? '' : normalisasi_jam(post('jam_mulai'));
        $selesai = $absen ? '' : normalisasi_jam(post('jam_selesai'));
        $galat = [];
        if (!tanggal_sah($tanggal) || $tanggal < $periodeAktif['mulai'] || $tanggal > $periodeAktif['selesai'] || $tanggal > date('Y-m-d')) {
            $galat[] = 'Tanggal harus berada dalam periode dan tidak melewati hari ini.';
        }
        if (!$absen) {
            if ($mulai === '' || $selesai === '' || !jam_sah($mulai) || !jam_sah($selesai) || $selesai <= $mulai) {
                $galat[] = 'Jam mulai dan jam selesai wajib diisi dengan benar.';
            }
            if (post('judul') === '') {
                $galat[] = 'Nama kegiatan wajib diisi.';
            }
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('pantau', $kembali);
        }
        update('kegiatan', [
            'kehadiran'   => $kehadiran,
            'jenis'       => $jenis,
            'tanggal'     => $tanggal,
            'jam_mulai'   => $mulai !== '' ? $mulai : null,
            'jam_selesai' => $selesai !== '' ? $selesai : null,
            'judul'       => $absen ? ($kehadiran === 'dinas' ? 'Tugas dinas luar' : 'Tidak masuk kerja') : mb_substr(post('judul'), 0, 255),
            'hasil'       => $absen ? '' : mb_substr(post('hasil'), 0, 500),
            'uraian'      => $absen ? '' : mb_substr(post('uraian'), 0, 5000),
        ], 'id = ?', [$k['id']]);
        catat_log('ubah_kegiatan_atasan', 'kegiatan', (int) $k['id'], $k['judul']);
        flash('Kegiatan diperbarui.');
    } elseif ($op === 'buka_verifikasi') {
        $k = row('SELECT * FROM kegiatan WHERE id = ? AND periode_id = ?', [(int) post('id'), $pid]);
        if (!$k || !in_array((int) $k['pengguna_id'], $timIds, true)) {
            tolak_akses();
        }
        update('kegiatan', ['status' => 'diajukan', 'diverifikasi_oleh' => null, 'diverifikasi_pada' => null], 'id = ?', [$k['id']]);
        flash('Verifikasi dibatalkan; kegiatan kembali menunggu verifikasi.', 'info');
    } elseif ($op === 'umpan') {
        $target = (int) post('pegawai_id');
        if (!in_array($target, $timIds, true) || !$pid) {
            tolak_akses();
        }
        $isi = mb_substr(post('isi'), 0, 4000);
        if ($isi === '') {
            flash('Isi umpan balik tidak boleh kosong.', 'galat');
        } else {
            $id = insert('umpan_balik', [
                'periode_id' => $pid,
                'pegawai_id' => $target,
                'pemberi_id' => uid(),
                'jenis'      => array_key_exists(post('jenis'), JENIS_UMPAN) ? post('jenis') : 'arahan',
                'isi'        => $isi,
            ]);
            catat_log('umpan_balik', 'umpan_balik', $id, '');
            flash('Umpan balik terkirim.');
        }
    }
    kembali_ke('pantau', $kembali);
}

$formHapus = [];
$tombolUbahHapus = static function (array $k) use (&$formHapus): string {
    $formHapus[] = (int) $k['id'];
    $isi = ['id' => (int) $k['id'], 'kehadiran' => $k['kehadiran'] ?: 'masuk', 'jenis' => $k['jenis'], 'tanggal' => $k['tanggal'],
            'jam_mulai' => jam_isian($k['jam_mulai']), 'jam_selesai' => jam_isian($k['jam_selesai']),
            'judul' => $k['judul'], 'hasil' => $k['hasil'], 'uraian' => $k['uraian']];
    return '<button class="btn btn-garis btn-ikon" type="button" title="Ubah" aria-label="Ubah kegiatan" data-buka="tiraiUbahK" data-isi=\'' . e(json_encode($isi)) . '\'>' . ikon('sunting', 15) . '</button>'
         . '<button class="btn btn-hantu btn-ikon" type="submit" form="fHapusK' . (int) $k['id'] . '" title="Hapus" aria-label="Hapus kegiatan"'
         . ' data-konfirmasi="Hapus kegiatan ini dari catatan pegawai?">' . ikon('hapus', 15) . '</button>';
};

$antre = antrean_verifikasi();
$bulanIni = date('Y-m');
$bulan = (string) ($_GET['bulan'] ?? $bulanIni);
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $bulan)) {
    $bulan = $bulanIni;
}
$namaBulan = static function (string $ym): string {
    return NAMA_BULAN[(int) substr($ym, 5, 2)] . ' ' . substr($ym, 0, 4);
};
$geserBulan = static function (string $ym, int $n): string {
    return date('Y-m', strtotime($ym . '-01 ' . ($n > 0 ? '+' : '') . $n . ' month'));
};
$periodeLihat = periode_dipilih();
$daftarBulan = [];
$awalDaftar = $periodeLihat ? substr($periodeLihat['mulai'], 0, 7) : $bulanIni;
$akhirDaftar = $periodeLihat ? min(substr($periodeLihat['selesai'], 0, 7), $bulanIni) : $bulanIni;
for ($b = $awalDaftar; $b <= $akhirDaftar && count($daftarBulan) < 36; $b = $geserBulan($b, 1)) {
    $daftarBulan[$b] = $namaBulan($b);
}
if (!isset($daftarBulan[$bulan])) {
    $daftarBulan[$bulan] = $namaBulan($bulan);
    ksort($daftarBulan);
}
$ringBulan = ringkasan_kegiatan($pid, $timIds, $bulan . '-01', date('Y-m-t', strtotime($bulan . '-01')));
$ringPeriode = ringkasan_kegiatan($pid, $timIds);
?>

<nav class="tab"<?= $tab === 'pegawai' ? '' : ' hidden' ?>>
  <a href="<?= e(url('pantau')) ?>" class="<?= $tab === 'tim' ? 'aktif' : '' ?>"><?= ikon('grup', 16) ?> Tim saya <?= lencana((string) count($tim)) ?></a>
  <a href="<?= e(url('pantau', ['tab' => 'verifikasi'])) ?>" class="<?= $tab === 'verifikasi' ? 'aktif' : '' ?>"><?= ikon('evaluasi', 16) ?> Menunggu verifikasi
    <?= $antre['kegiatan'] > 0 ? lencana((string) $antre['kegiatan'], 'emas') : '' ?></a>
  <?php if ($tab === 'pegawai'): $pg = pegawai_get($pegawaiId); ?>
    <a href="#" class="aktif"><?= ikon('profil', 16) ?> <?= e($pg['nama']) ?></a>
  <?php endif; ?>
</nav>

<?php if ($tab === 'tim'): ?>
  <?php if (!$tim): ?>
    <div class="kartu"><?= sel_kosong('Belum ada anggota tim') ?></div>
  <?php else: ?>
  <div class="saring no-cetak">
    <div class="form-baris" style="flex:0 1 340px;max-width:340px"><label class="fl" for="cariTim">Cari anggota tim</label>
      <input type="search" id="cariTim" placeholder="Ketik nama, jabatan, atau unit" autocomplete="off"></div>
    <span class="kecil lemah" id="infoCariTim" style="padding-bottom:10px"><?= count($tim) ?> anggota</span>
    <form method="get" class="pilih-bulan">
      <input type="hidden" name="h" value="pantau">
      <label class="fl" for="pilihBulan">Lihat bulan</label>
      <div class="pilih-bulan-isi">
        <a class="btn btn-garis btn-ikon" href="<?= e(url('pantau', ['bulan' => $geserBulan($bulan, -1)])) ?>" title="Bulan sebelumnya" aria-label="Bulan sebelumnya"><?= ikon('kembali', 15) ?></a>
        <select id="pilihBulan" name="bulan" data-auto-kirim><?php foreach (array_reverse($daftarBulan, true) as $kode => $label): ?><option value="<?= e($kode) ?>"<?= $kode === $bulan ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select>
        <?php if ($bulan < $bulanIni): ?>
          <a class="btn btn-garis btn-ikon" href="<?= e(url('pantau', ['bulan' => $geserBulan($bulan, 1)])) ?>" title="Bulan berikutnya" aria-label="Bulan berikutnya"><?= ikon('kanan', 15) ?></a>
          <a class="btn btn-garis btn-kecil" href="<?= e(url('pantau')) ?>">Bulan ini</a>
        <?php endif; ?>
      </div>
    </form>
    <a class="btn btn-garis" style="margin-bottom:2px" href="<?= e(url('pantau', ['tab' => 'verifikasi'])) ?>"><?= ikon('evaluasi', 15) ?> Menunggu verifikasi
      <?= $antre['kegiatan'] > 0 ? lencana((string) $antre['kegiatan'], 'emas') : '' ?></a>
  </div>
  <?php
  $timCs = array_values(array_filter($tim, static function ($t) { return $t['peran_kode'] === 'cs'; }));
  $timLain = array_values(array_filter($tim, static function ($t) { return $t['peran_kode'] !== 'cs'; }));
  $grupTim = static function (array $anggota, string $judulGrup, string $ikonGrup) use ($bulan, $ringBulan, $ringPeriode, $namaBulan): void { ?>
  <div class="kartu kartu-tim-grup">
  <div class="kartu-kepala"><div><h2><?= ikon($ikonGrup, 18) ?> <?= e($judulGrup) ?></h2><p class="ket"><?= count($anggota) ?> orang</p></div></div>
  <div class="tabel-bungkus"><table class="tabel tabel-tim-tengah tabel-anggota">
    <thead>
      <tr><th rowspan="2" class="kolom-no">No</th><th rowspan="2">Pegawai</th><th colspan="2" class="grup-kolom">Bulan <?= e($namaBulan($bulan)) ?></th><th colspan="4" class="grup-kolom">Tahun kinerja berjalan</th><th rowspan="2">Verifikasi</th><th rowspan="2" class="no-cetak kolom-aksi">Aksi</th></tr>
      <tr><th class="sub-kolom">Jum. Kegiatan</th><th class="sub-kolom">Jum. Jam kerja</th><th class="sub-kolom">Total</th><th class="sub-kolom">Rutin</th><th class="sub-kolom">Kepanitiaan</th><th class="sub-kolom">Insidental</th></tr>
    </thead>
    <tbody>
    <?php foreach ($anggota as $t): $id = (int) $t['id']; $rb = $ringBulan[$id]; $rp = $ringPeriode[$id]; ?>
      <tr data-cari="<?= e(mb_strtolower($t['nama'] . ' ' . ($t['jabatan'] ?? '') . ' ' . ($t['unit_nama'] ?? '') . ' ' . ($t['peran_nama'] ?? ''))) ?>">
        <td class="kolom-no"></td>
        <td><div class="orang"><?= avatar($t['nama'], 'kecil') ?><div><b><?= e($t['nama']) ?></b>
          <span><?= e($t['jabatan'] ?: $t['peran_nama']) ?></span></div></div></td>
        <td><?= (int) $rb['total'] ?></td>
        <td><?= angka_ringkas($rb['jam'], 1) ?></td>
        <td><?= (int) $rp['total'] ?></td>
        <td><?= (int) $rp['rutin'] ?></td>
        <td><?= (int) $rp['panitia'] ?></td>
        <td><?= (int) $rp['insidental'] ?></td>
        <td>
          <?php if ($rp['diajukan'] > 0): ?><?= lencana($rp['diajukan'] . ' menunggu', 'emas') ?><?php endif; ?>
          <?php if ($rp['draft'] > 0): ?><span title="Draft belum dikirim pegawai; jangan dinilai dulu"><?= lencana($rp['draft'] . ' draft', 'abu') ?></span><?php endif; ?>
          <?php if (!$rb['terakhir'] && $rb['draft'] <= 0): ?><?= lencana('Belum mencatat', 'abu') ?><?php endif; ?>
          <?php if ($rp['diajukan'] <= 0 && $rp['draft'] <= 0 && $rb['terakhir']): ?><span class="lemah">-</span><?php endif; ?>
        </td>
        <td class="no-cetak"><div class="aksi-baris" style="justify-content:center">
          <a class="btn btn-utama btn-kecil" href="<?= e(url('pantau', ['tab' => 'pegawai', 'p' => $id])) ?>"><?= ikon('pantau', 14) ?> Pantau</a>
          <button class="btn btn-ikon" type="button" data-buka="tiraiUmpan" data-reset title="Beri umpan balik" aria-label="Beri umpan balik kepada <?= e($t['nama']) ?>"
                  data-isi='<?= e(json_encode(['pegawai_id' => $id, 'nama_teks' => $t['nama']])) ?>'><?= ikon('pesan', 15) ?></button>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  </div>
  <?php };
  if ($timLain) { $grupTim($timLain, 'Pegawai', 'grup'); }
  if ($timCs) { $grupTim($timCs, 'Petugas Cleaning Service (CS)', 'kebersihan'); }
  ?>
  <?php endif; ?>

<?php elseif ($tab === 'verifikasi'): ?>
  <?php
  $kegiatanAntre = $timIds ? rows(
      "SELECT k.*, p.nama FROM kegiatan k
         JOIN pengguna p ON p.id = k.pengguna_id
        WHERE k.periode_id = ? AND k.status = 'diajukan' AND k.pengguna_id IN (" . in_placeholder($timIds) . ')' . sql_bukan_draft('k.id') . '
        ORDER BY k.tanggal, p.nama LIMIT 300',
      array_merge([$pid], $timIds)
  ) : [];
  ?>
  <form method="post" class="kartu" id="formVerifikasi">
    <?= csrf_field() ?><input type="hidden" name="op" value="verifikasi_massal"><input type="hidden" name="kembali_tab" value="verifikasi">
    <div class="kartu-kepala">
      <h2><?= ikon('catatan', 18) ?> Kegiatan menunggu verifikasi</h2>
      <p class="ket"><?= count($kegiatanAntre) ?> kegiatan</p>
      <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url('pantau')) ?>"><?= ikon('grup', 15) ?> Tim saya</a></div>
      <?php if ($kegiatanAntre && $terbuka): ?>
        <div class="aksi" style="margin-left:0"><button class="btn btn-sukses btn-kecil" type="submit" data-butuh="kg" disabled
                data-konfirmasi="Verifikasi semua kegiatan yang dicentang?" data-gaya="utama">
          <?= ikon('centang', 15) ?> Verifikasi terpilih (<span data-hitung="kg">0</span>)</button></div>
      <?php endif; ?>
    </div>
    <?php if (!$kegiatanAntre): ?>
      <?= sel_kosong('Semua sudah diverifikasi', '') ?>
    <?php else: ?>
    <div class="tabel-bungkus"><table class="tabel judul-tengah">
      <thead><tr><th style="width:36px"><input type="checkbox" data-pilih-semua="kg" aria-label="Pilih semua"></th>
        <th>Pegawai</th><th>Tanggal</th><th>Kegiatan</th><th>Jenis</th><th>Satuan</th><th class="kolom-aksi">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($kegiatanAntre as $k): ?>
        <tr>
          <td><input type="checkbox" name="kegiatan[]" value="<?= (int) $k['id'] ?>" data-kelompok="kg"></td>
          <td><div class="orang"><?= avatar($k['nama'], 'kecil') ?><b><?= e($k['nama']) ?></b></div></td>
          <td class="nowrap"><?= e(tanggal_pendek($k['tanggal'])) ?><span class="sub"><?= $k['jam_mulai'] ? e(jam_pendek($k['jam_mulai'])) . '-' . e(jam_pendek($k['jam_selesai'])) : '' ?></span></td>
          <td><b><?= e($k['judul']) ?></b> <?= lencana_kehadiran($k['kehadiran'] ?? null) ?>
            <?php if ($k['kepanitiaan']): ?><span class="sub"><?= ikon('grup', 13) ?> <?= e($k['kepanitiaan']) ?></span><?php endif; ?>
            <?php if ($k['pemberi_tugas']): ?><span class="sub"><?= ikon('petir', 13) ?> <?= e($k['pemberi_tugas']) ?></span><?php endif; ?>
            <?php if ($k['hasil']): ?><span class="sub">Hasil: <?= e($k['hasil']) ?></span><?php endif; ?>
            <?php if ($k['tautan_bukti']): ?><span class="sub"><a href="<?= e($k['tautan_bukti']) ?>" target="_blank" rel="noopener"><?= ikon('tautan', 13) ?> Bukti</a></span><?php endif; ?><?= tautan_foto_kegiatan((int) $k['id']) ?></td>
          <td><?= lencana_jenis($k['jenis']) ?></td>
          <td><?= e($k['satuan']) ?></td>
          <td><div class="aksi-baris">
            <?= kegiatan_rincian($k, $k['nama']) ?>
            <?php if ($terbuka): ?>
            <button class="btn btn-sukses btn-ikon" type="submit" form="fSetuju<?= (int) $k['id'] ?>" title="Verifikasi"><?= ikon('centang', 15) ?></button>
            <button class="btn btn-garis btn-ikon" type="button" title="Kembalikan" data-buka="tiraiRevisiK"
                    data-isi='<?= e(json_encode(['id' => (int) $k['id'], 'judul_teks' => $k['judul'] . ' - ' . $k['nama']])) ?>'><?= ikon('ulang', 15) ?></button>
            <?= $tombolUbahHapus($k) ?>
            <?php endif; ?>
          </div></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php endif; ?>
  </form>
  <?php foreach ($kegiatanAntre as $k): ?>
    <form method="post" id="fSetuju<?= (int) $k['id'] ?>" hidden><?= csrf_field() ?><input type="hidden" name="op" value="setujui">
      <input type="hidden" name="id" value="<?= (int) $k['id'] ?>"><input type="hidden" name="kembali_tab" value="verifikasi"></form>
  <?php endforeach; ?>

<?php else: ?>
  <?php
  $pg = pegawai_get($pegawaiId);
  $periode = periode_dipilih();
  $bln = rentang_sah($periode, (string) ($_GET['bln'] ?? 'semua'));
  [$syarat, $paramRentang] = syarat_rentang($periode, $bln);
  $param = array_merge([$pid, $pegawaiId], $paramRentang);
  $kegiatan = rows(
      "SELECT k.* FROM kegiatan k
        WHERE k.periode_id = ? AND k.pengguna_id = ?$syarat ORDER BY k.tanggal DESC, k.jam_mulai IS NULL, k.jam_mulai, k.id LIMIT 500",
      $param
  );
  $rp = $ringPeriode[$pegawaiId];
  $bulanPilihan = pilihan_rentang($periode);
  $subjudul = $pg['nama'];
  $perHari = [];
  $jumlahDraft = 0;
  foreach ($kegiatan as $k) {
      $perHari[$k['tanggal']]['n'] = $perHari[$k['tanggal']]['n'] ?? 0;
      $perHari[$k['tanggal']]['jam'] = $perHari[$k['tanggal']]['jam'] ?? 0;
      if (kegiatan_draft((int) $k['id'])) {
          $jumlahDraft++;
          $perHari[$k['tanggal']]['draft'] = ($perHari[$k['tanggal']]['draft'] ?? 0) + 1;
          continue;
      }
      $perHari[$k['tanggal']]['n']++;
      $perHari[$k['tanggal']]['jam'] += durasi_jam($k['jam_mulai'], $k['jam_selesai']);
  }
  ?>
    <div class="kartu kartu-catatan-pegawai">
      <div class="kartu-kepala">
        <div><h2><?= ikon('catatan', 18) ?> Catatan kegiatan &middot; <?= e($pg['nama']) ?></h2>
          <p class="ket"><?= e($pg['jabatan'] ?: $pg['peran_nama']) ?><?= $pg['unit_nama'] ? ' &middot; ' . e($pg['unit_nama']) : '' ?> &middot; <?= (int) $rp['total'] ?> kegiatan (<?= (int) $rp['disetujui'] ?> terverifikasi) &middot; <?= angka_ringkas($rp['jam'], 1) ?> jam</p></div>
        <div class="aksi">
          <form method="get" style="display:contents">
            <input type="hidden" name="h" value="pantau"><input type="hidden" name="tab" value="pegawai"><input type="hidden" name="p" value="<?= $pegawaiId ?>">
            <select name="bln" data-auto-kirim style="padding:6px 10px;font-size:.82rem"><?= pilihan($bulanPilihan, $bln) ?></select>
          </form>
          <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiUmpan" data-reset
                  data-isi='<?= e(json_encode(['pegawai_id' => $pegawaiId, 'nama_teks' => $pg['nama']])) ?>'><?= ikon('pesan', 14) ?> Beri umpan balik</button>
          <?php if (can('evaluasi_nilai')): ?><a class="btn btn-garis btn-kecil" href="<?= e(url('evaluasi', ['p' => $pegawaiId])) ?>"><?= ikon('evaluasi', 14) ?> Evaluasi</a><?php endif; ?>
        </div>
      </div>
      <?php if (!$kegiatan): ?>
        <?= sel_kosong('Belum ada kegiatan', '') ?>
      <?php else: ?>
      <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-per-hari">
        <thead><tr><th>Jam</th><th>Total jam kerja</th><th>Kegiatan</th><th>Jenis</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        <?php $hariKini = null; $urutHari = 0; foreach ($kegiatan as $k):
            if ($k['tanggal'] !== $hariKini): $hariKini = $k['tanggal']; $urutHari++; ?>
          <tr class="baris-hari"><td colspan="6"><?= ikon('kalender', 14) ?> <b><?= e(tanggal_id($hariKini, true)) ?></b>
            <span><?= (int) $perHari[$hariKini]['n'] ?> kegiatan &middot; <?= angka_ringkas($perHari[$hariKini]['jam'], 1) ?> jam<?= !empty($perHari[$hariKini]['draft']) ? ' &middot; ' . (int) $perHari[$hariKini]['draft'] . ' draft' : '' ?></span></td></tr>
        <?php endif; ?>
          <tr class="<?= $urutHari % 2 === 0 ? 'hari-genap' : '' ?>">
            <td class="nowrap tengah"><?= $k['jam_mulai'] ? e(jam_pendek($k['jam_mulai'])) . '-' . e(jam_pendek($k['jam_selesai'])) : '<span class="lemah">-</span>' ?></td>
            <td class="nowrap tengah ab-durasi"><?php $durasi = durasi_jam($k['jam_mulai'], $k['jam_selesai']); ?><?= $durasi > 0 ? '<b>' . angka_ringkas($durasi, 1) . '</b> jam' : '<span class="lemah">-</span>' ?></td>
            <td><b><?= $k['judul'] !== '' ? e($k['judul']) : '<i class="lemah">(belum diberi judul)</i>' ?></b> <?= lencana_kehadiran($k['kehadiran'] ?? null) ?>
              <?php if (kegiatan_draft((int) $k['id'])): ?><span class="sub lemah"><?= ikon('info', 13) ?> Draft pegawai, belum dikirim. Jangan dinilai dulu.</span><?php endif; ?>
              <?php if ($k['kepanitiaan']): ?><span class="sub"><?= ikon('grup', 13) ?> <?= e($k['kepanitiaan']) ?></span><?php endif; ?>
              <?php if ($k['pemberi_tugas']): ?><span class="sub"><?= ikon('petir', 13) ?> <?= e($k['pemberi_tugas']) ?></span><?php endif; ?>
              <?php if ($k['hasil']): ?><span class="sub">Hasil: <?= e($k['hasil']) ?> (<?= e($k['satuan']) ?>)</span><?php endif; ?>
              <?php if ($k['tautan_bukti']): ?><span class="sub"><a href="<?= e($k['tautan_bukti']) ?>" target="_blank" rel="noopener"><?= ikon('tautan', 13) ?> Bukti</a></span><?php endif; ?><?= tautan_foto_kegiatan((int) $k['id']) ?>
              <?php if ($k['catatan_atasan']): ?><span class="sub"><?= ikon('pesan', 13) ?> <?= e($k['catatan_atasan']) ?></span><?php endif; ?></td>
            <td><?= lencana_jenis($k['jenis']) ?></td>
            <td><?= lencana_status_kegiatan($k) ?></td>
            <td><div class="aksi-baris">
              <?= kegiatan_rincian($k, $pg['nama']) ?>
              <?php if (kegiatan_draft((int) $k['id'])): ?>
              <?php elseif ($terbuka && $k['status'] === 'diajukan'): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="setujui"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <button class="btn btn-sukses btn-ikon" type="submit" title="Verifikasi"><?= ikon('centang', 15) ?></button></form>
                <button class="btn btn-garis btn-ikon" type="button" title="Kembalikan" data-buka="tiraiRevisiK"
                        data-isi='<?= e(json_encode(['id' => (int) $k['id'], 'judul_teks' => $k['judul']])) ?>'><?= ikon('ulang', 15) ?></button>
              <?php elseif ($terbuka && $k['status'] === 'disetujui'): ?>
                <form method="post" data-konfirmasi="Batalkan verifikasi kegiatan ini?"><?= csrf_field() ?><input type="hidden" name="op" value="buka_verifikasi">
                  <input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <button class="btn btn-hantu btn-ikon" type="submit" title="Batalkan verifikasi"><?= ikon('ulang', 15) ?></button></form>
              <?php endif; ?>
              <?php if ($terbuka): ?><?= $tombolUbahHapus($k) ?><?php endif; ?>
            </div></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr class="baris-total"><td><b>Total</b></td><td class="tengah"><b><?= angka_ringkas(array_sum(array_column($perHari, 'jam')), 1) ?> jam</b></td><td colspan="4"><?= count($kegiatan) - $jumlahDraft ?> kegiatan dalam <?= count(array_filter($perHari, static function ($h) { return $h['n'] > 0; })) ?> hari<?= $jumlahDraft ? ' &middot; ' . $jumlahDraft . ' draft (tidak dihitung)' : '' ?></td></tr></tfoot>
        </table></div>
      <?php endif; ?>
    </div>
<?php endif; ?>

<?php foreach ($formHapus as $idHapus): ?>
  <form method="post" id="fHapusK<?= $idHapus ?>" hidden><?= csrf_field() ?><input type="hidden" name="op" value="hapus_kegiatan">
    <input type="hidden" name="id" value="<?= $idHapus ?>"><input type="hidden" name="kembali_tab" value="<?= e($tab) ?>"></form>
<?php endforeach; ?>

<?php if ($formHapus): $periodeForm = periode_dipilih(); ?>
<div class="tirai" id="tiraiUbahK" hidden>
  <div class="modal">
    <form method="post" data-wadah-jenis>
      <?= csrf_field() ?><input type="hidden" name="op" value="ubah_kegiatan"><input type="hidden" name="id" value="">
      <input type="hidden" name="kembali_tab" value="<?= e($tab) ?>">
      <div class="modal-kepala"><h2>Ubah kegiatan pegawai</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="ukHadir">Kehadiran</label>
            <select id="ukHadir" name="kehadiran"><?php foreach (KEHADIRAN_KERJA as $kh => $j): ?><option value="<?= $kh ?>"><?= e($j['label']) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="ukTanggal">Tanggal <span class="wajib">*</span></label>
            <input type="date" id="ukTanggal" name="tanggal" required min="<?= e($periodeForm['mulai']) ?>" max="<?= e(min($periodeForm['selesai'], date('Y-m-d'))) ?>"></div>
        </div>
        <div data-hanya-kerja>
          <div class="form-grid-3">
            <div class="form-baris"><label class="fl" for="ukJenis">Jenis pekerjaan</label>
              <select id="ukJenis" name="jenis"><?php foreach (JENIS_KERJA as $kj => $j): ?><option value="<?= $kj ?>"><?= e($j['label']) ?></option><?php endforeach; ?></select></div>
            <div class="form-baris"><label class="fl" for="ukMulai">Jam mulai <span class="wajib">*</span></label>
              <input type="text" id="ukMulai" name="jam_mulai" data-jam inputmode="numeric" maxlength="5" placeholder="08.00" autocomplete="off"></div>
            <div class="form-baris"><label class="fl" for="ukSelesai">Jam selesai <span class="wajib">*</span></label>
              <input type="text" id="ukSelesai" name="jam_selesai" data-jam inputmode="numeric" maxlength="5" placeholder="16.00" autocomplete="off"></div>
          </div>
          <div class="form-baris"><label class="fl" for="ukJudul">Nama kegiatan <span class="wajib">*</span></label>
            <input type="text" id="ukJudul" name="judul" maxlength="255"></div>
          <div class="form-baris"><label class="fl" for="ukHasil">Hasil / keluaran</label>
            <input type="text" id="ukHasil" name="hasil" maxlength="500"></div>
          <div class="form-baris"><label class="fl" for="ukUraian">Uraian pekerjaan</label>
            <textarea id="ukUraian" name="uraian" rows="3" maxlength="5000"></textarea></div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script nonce="<?= csp_nonce() ?>">
  (function () {
    var kolom = document.getElementById('cariTim'), info = document.getElementById('infoCariTim');
    if (!kolom) return;
    var kartu = Array.prototype.slice.call(document.querySelectorAll('.tabel-anggota tbody tr'));
    kolom.addEventListener('input', function () {
      var kata = kolom.value.trim().toLowerCase(), tampil = 0;
      kartu.forEach(function (k) {
        var cocok = kata === '' || (k.getAttribute('data-cari') || '').indexOf(kata) !== -1;
        k.hidden = !cocok;
        if (cocok) tampil++;
      });
      info.textContent = kata === '' ? kartu.length + ' anggota' : tampil + ' dari ' + kartu.length + ' anggota';
      Array.prototype.forEach.call(document.querySelectorAll('.kartu-tim-grup'), function (g) {
        g.hidden = !g.querySelector('tbody tr:not([hidden])');
      });
    });
  })();
</script>

<?= modal_rincian_kegiatan() ?>

<div class="tirai" id="tiraiRevisiK" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="revisi"><input type="hidden" name="id" value="">
      <input type="hidden" name="kembali_tab" value="<?= e($tab) ?>">
      <div class="modal-kepala"><h2>Kembalikan kegiatan</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil lemah" data-teks="judul_teks"></p>
        <label class="fl" for="rkCatatan">Catatan perbaikan <span class="wajib">*</span></label>
        <textarea id="rkCatatan" name="catatan" rows="4" maxlength="1000" required placeholder="Contoh: Lampirkan tautan bukti notulen rapat."></textarea>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-bahaya"><?= ikon('ulang', 15) ?> Kembalikan</button>
      </div>
    </form>
  </div>
</div>

<div class="tirai" id="tiraiUmpan" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="umpan"><input type="hidden" name="pegawai_id" value="">
      <input type="hidden" name="kembali_tab" value="<?= e($tab) ?>">
      <div class="modal-kepala"><h2>Beri umpan balik</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil">Untuk: <b data-teks="nama_teks"></b></p>
        <div class="form-baris">
          <label class="fl">Jenis umpan balik</label>
          <div class="skala" style="gap:8px">
            <?php foreach (JENIS_UMPAN as $kj => $j): ?>
              <label><input type="radio" name="jenis" value="<?= $kj ?>" <?= $kj === 'arahan' ? 'checked' : '' ?>><span style="width:auto;padding:0 14px"><?= e($j['label']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        <label class="fl" for="ubIsi">Pesan <span class="wajib">*</span></label>
        <textarea id="ubIsi" name="isi" rows="5" maxlength="4000" required placeholder="Sampaikan apresiasi, arahan, atau koreksi yang spesifik dan dapat ditindaklanjuti."></textarea>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('kirim', 15) ?> Kirim</button>
      </div>
    </form>
  </div>
</div>
