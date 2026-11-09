<?php
$pid = pid();
$saya = uid();
$kelola = can('pengembangan_kelola');
$timIds = $kelola ? tim_ids() : [];
$filterP = (int) ($_GET['p'] ?? 0);
if ($filterP && $filterP !== $saya && !in_array($filterP, $timIds, true)) {
    $filterP = 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    if (!$kelola) {
        tolak_akses();
    }
    $op = post('op');
    if ($op === 'simpan') {
        $id = (int) post('id');
        $pegawai = (int) post('pegawai_id');
        if (!in_array($pegawai, $timIds, true)) {
            tolak_akses();
        }
        $tgl = post('target_waktu');
        $data = [
            'pegawai_id'   => $pegawai,
            'jenis'        => array_key_exists(post('jenis'), JENIS_PENGEMBANGAN) ? post('jenis') : 'pelatihan',
            'uraian'       => mb_substr(post('uraian'), 0, 1000),
            'target_waktu' => tanggal_sah($tgl) ? $tgl : null,
            'status'       => array_key_exists(post('status'), STATUS_PENGEMBANGAN) ? post('status') : 'usulan',
            'hasil'        => mb_substr(post('hasil'), 0, 1000),
        ];
        if ($data['uraian'] === '') {
            flash('Uraian tindak lanjut wajib diisi.', 'galat');
            kembali_ke('pengembangan', ['p' => $filterP ?: null]);
        }
        if ($id) {
            $lama = row('SELECT * FROM pengembangan WHERE id = ? AND periode_id = ?', [$id, $pid]);
            if (!$lama || !in_array((int) $lama['pegawai_id'], $timIds, true)) {
                tolak_akses();
            }
            update('pengembangan', $data, 'id = ?', [$id]);
            catat_log('ubah_pengembangan', 'pengembangan', $id, $data['uraian']);
            flash('Rencana pengembangan diperbarui.');
        } else {
            $baru = insert('pengembangan', $data + ['periode_id' => $pid, 'dibuat_oleh' => $saya]);
            catat_log('tambah_pengembangan', 'pengembangan', $baru, $data['uraian']);
            flash('Rencana penghargaan/pengembangan ditambahkan.');
        }
    } elseif ($op === 'hapus') {
        $lama = row('SELECT * FROM pengembangan WHERE id = ? AND periode_id = ?', [(int) post('id'), $pid]);
        if (!$lama || !in_array((int) $lama['pegawai_id'], $timIds, true)) {
            tolak_akses();
        }
        q('DELETE FROM pengembangan WHERE id = ?', [$lama['id']]);
        flash('Rencana pengembangan dihapus.');
    }
    kembali_ke('pengembangan', ['p' => $filterP ?: null]);
}

$lingkup = $filterP ? [$filterP] : array_merge([$saya], $timIds);
$daftar = rows(
    "SELECT pg.*, p.nama, un.nama AS unit_nama, ev.nilai_akhir, ev.predikat, ev.status AS ev_status, ev.tw AS ev_tw, pb.nama AS pembuat
       FROM pengembangan pg
       JOIN pengguna p ON p.id = pg.pegawai_id
       LEFT JOIN unit un ON un.id = p.unit_id
       LEFT JOIN evaluasi ev ON ev.id = (SELECT e2.id FROM evaluasi e2 WHERE e2.pegawai_id = pg.pegawai_id
                                          AND e2.periode_id = pg.periode_id AND e2.status = 'final' ORDER BY e2.tw DESC LIMIT 1)
       LEFT JOIN pengguna pb ON pb.id = pg.dibuat_oleh
      WHERE pg.periode_id = ? AND pg.pegawai_id IN (" . in_placeholder($lingkup) . ")
      ORDER BY FIELD(pg.status, 'usulan', 'disetujui', 'berjalan', 'selesai', 'batal'), p.nama",
    array_merge([$pid], $lingkup)
);

$perJenis = array_fill_keys(array_keys(JENIS_PENGEMBANGAN), 0);
foreach ($daftar as $d) {
    $perJenis[$d['jenis']]++;
}

$rekomendasi = [];
if ($kelola && $timIds && !$filterP) {
    $rekomendasi = rows(
        "SELECT ev.pegawai_id, ev.tw, ev.nilai_akhir, ev.predikat, ev.perbaikan, p.nama, un.nama AS unit_nama
           FROM evaluasi ev JOIN pengguna p ON p.id = ev.pegawai_id LEFT JOIN unit un ON un.id = p.unit_id
          WHERE ev.periode_id = ? AND ev.status = 'final' AND ev.pegawai_id IN (" . in_placeholder($timIds) . ")
            AND ev.tw = (SELECT MAX(e3.tw) FROM evaluasi e3 WHERE e3.periode_id = ev.periode_id
                          AND e3.pegawai_id = ev.pegawai_id AND e3.status = 'final')
            AND NOT EXISTS (SELECT 1 FROM pengembangan x WHERE x.periode_id = ev.periode_id AND x.pegawai_id = ev.pegawai_id
                             AND x.dibuat >= COALESCE(ev.difinalkan_pada, '1970-01-01'))
          ORDER BY ev.nilai_akhir DESC",
        array_merge([$pid], $timIds)
    );
}

$evFilter = $filterP ? evaluasi_terakhir($pid, $filterP) : null;
$bawaanBaru = ['id' => '', 'pegawai_id' => $filterP && $filterP !== $saya ? $filterP : '', 'status' => 'usulan', 'jenis' => 'pelatihan'];
if ($evFilter && $evFilter['status'] === 'final') {
    [$jr, $tr] = rekomendasi_predikat($evFilter['predikat']);
    $bawaanBaru['jenis'] = $jr;
    $bawaanBaru['uraian'] = $tr;
}
$timPilihan = $kelola ? pegawai_tim() : [];
usort($timPilihan, 'bandingkan_pilihan_pegawai');
if ($filterP) {
    $subjudul = pegawai_get($filterP)['nama'] ?? '';
}
?>

<?php if ($filterP && $evFilter && $evFilter['status'] === 'final'): $pr = predikat_nilai((float) $evFilter['nilai_akhir']); ?>
  <div class="notif notif-info"><?= ikon('evaluasi', 17) ?><div>Hasil evaluasi terakhir (TW<?= (int) $evFilter['tw'] ?>): nilai akhir <b><?= angka($evFilter['nilai_akhir'], 2) ?></b>
    <?= lencana($pr['label'], $pr['warna']) ?><?= $evFilter['perbaikan'] ? '<br>Area perbaikan: ' . e($evFilter['perbaikan']) : '' ?></div></div>
<?php endif; ?>

<div class="grid-stat grid-stat-atas">
  <?php $kelasStat = ['penghargaan' => 'emas', 'promosi' => '', 'pelatihan' => 'biru', 'pembinaan' => 'ungu', 'mutasi' => 'toska'];
        $ikonStat = ['penghargaan' => 'bintang', 'promosi' => 'tumbuh', 'pelatihan' => 'acuan', 'pembinaan' => 'pesan', 'mutasi' => 'ulang'];
  foreach (JENIS_PENGEMBANGAN as $k => $j): ?>
    <div class="stat <?= $kelasStat[$k] ?>"><span class="ik"><?= ikon($ikonStat[$k], 22) ?: ikon('dokumen', 22) ?></span><div>
      <div class="label"><?= e($j['label']) ?></div><div class="nilai"><?= $perJenis[$k] ?></div></div></div>
  <?php endforeach; ?>
</div>


<?php if ($kelola && !$filterP && $rekomendasi): ?>
<div class="kartu">
  <?php if (!$rekomendasi): ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-tim-tengah tabel-rekomendasi">
    <thead><tr><th>Pegawai</th><th class="angka">Nilai</th><th>Predikat</th><th>Saran tindak lanjut</th><th class="kolom-aksi">Tindak lanjuti</th></tr></thead>
    <tbody>
    <?php foreach ($rekomendasi as $r): [$jr, $tr] = rekomendasi_predikat($r['predikat']); $pr = predikat_nilai((float) $r['nilai_akhir']);
        $uraianAwal = $tr . ($r['perbaikan'] ? ' Area perbaikan: ' . $r['perbaikan'] : ''); ?>
      <tr>
        <td><div class="orang"><?= avatar($r['nama'], 'kecil') ?><div><b><?= e($r['nama']) ?></b><span><?= e($r['unit_nama'] ?? '') ?></span></div></div></td>
        <td class="angka"><b><?= angka($r['nilai_akhir'], 2) ?></b><span class="sub">TW<?= (int) $r['tw'] ?></span></td>
        <td><?= lencana($pr['label'], $pr['warna']) ?></td>
        <td class="kiri"><?= lencana_dari(JENIS_PENGEMBANGAN, $jr) ?> <span class="kecil"><?= e($tr) ?></span>
          <?php if ($r['perbaikan']): ?><span class="sub">Catatan penilai: <?= e($r['perbaikan']) ?></span><?php endif; ?></td>
        <td><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPengembangan" data-reset data-judul="Tindak lanjut rekomendasi · <?= e($r['nama']) ?>"
          data-isi='<?= e(json_encode(['id' => '', 'pegawai_id' => (int) $r['pegawai_id'], 'jenis' => $jr, 'uraian' => mb_substr($uraianAwal, 0, 1000), 'status' => 'usulan', 'target_waktu' => date('Y-m-d', strtotime('+3 months'))])) ?>'>
          <?= ikon('tambah', 14) ?> Tindak lanjuti</button></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php endif; ?>

<div class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('tumbuh', 18) ?> Rencana penghargaan &amp; pengembangan</h2>
    <div class="aksi">
      <?php if ($kelola && $timPilihan): ?>
        <form method="get" style="display:flex;gap:6px">
          <input type="hidden" name="h" value="pengembangan">
          <select name="p" data-auto-kirim style="padding:6px 10px;font-size:.82rem;min-width:220px">
            <option value="">Saya &amp; seluruh tim</option>
            <?php foreach (pilihan_pegawai() as $pp): ?><option value="<?= $pp['id'] ?>" <?= $filterP === $pp['id'] ? 'selected' : '' ?>><?= e($pp['nama']) ?></option><?php endforeach; ?>
          </select>
        </form>
        <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPengembangan" data-reset data-judul="Tambah rencana tindak lanjut"
                data-isi='<?= e(json_encode($bawaanBaru)) ?>'><?= ikon('tambah', 15) ?> Tambah</button>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong('Belum ada rencana', '') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel judul-tengah">
    <thead><tr><th>Pegawai</th><th>Jenis</th><th>Uraian</th><th>Target</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $d): $bisa = $kelola && in_array((int) $d['pegawai_id'], $timIds, true); ?>
      <tr>
        <td><div class="orang"><?= avatar($d['nama'], 'kecil') ?><div><b><?= e($d['nama']) ?></b>
          <span><?= $d['ev_status'] === 'final' ? 'Nilai TW' . (int) $d['ev_tw'] . ' ' . angka($d['nilai_akhir'], 2) . ' · ' . e($d['predikat']) : e($d['unit_nama'] ?? '') ?></span></div></div></td>
        <td><?= lencana_dari(JENIS_PENGEMBANGAN, $d['jenis']) ?></td>
        <td><?= e($d['uraian']) ?><?php if ($d['hasil']): ?><span class="sub">Hasil: <?= e($d['hasil']) ?></span><?php endif; ?>
          <span class="sub">Diusulkan oleh <?= e($d['pembuat'] ?? '-') ?></span></td>
        <td class="nowrap"><?= $d['target_waktu'] ? e(tanggal_pendek($d['target_waktu'])) : '-' ?></td>
        <td><?= lencana_dari(STATUS_PENGEMBANGAN, $d['status']) ?></td>
        <td><div class="aksi-baris">
          <?php if ($bisa): ?>
            <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiPengembangan" data-judul="Ubah rencana tindak lanjut"
                    data-isi='<?= e(json_encode(['id' => (int) $d['id'], 'pegawai_id' => (int) $d['pegawai_id'], 'jenis' => $d['jenis'], 'uraian' => $d['uraian'],
                        'target_waktu' => $d['target_waktu'] ?? '', 'status' => $d['status'], 'hasil' => $d['hasil']])) ?>'><?= ikon('sunting', 15) ?></button>
            <form method="post" data-konfirmasi="Hapus rencana ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $d['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<?php if ($kelola): ?>
<div class="tirai" id="tiraiPengembangan" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Rencana tindak lanjut</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris">
            <label class="fl" for="pgPegawai">Pegawai <span class="wajib">*</span></label>
            <select id="pgPegawai" name="pegawai_id" required>
              <option value="">Pilih pegawai</option>
              <?php foreach ($timPilihan as $t): ?><option value="<?= (int) $t['id'] ?>"><?= e($t['nama']) ?> - <?= e($t['unit_nama'] ?? '') ?></option><?php endforeach; ?>
            </select>
          </div>
          <div class="form-baris">
            <label class="fl" for="pgJenis">Jenis</label>
            <select id="pgJenis" name="jenis"><?php foreach (JENIS_PENGEMBANGAN as $k => $j): ?><option value="<?= $k ?>"><?= e($j['label']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="form-baris penuh">
            <label class="fl" for="pgUraian">Uraian <span class="wajib">*</span></label>
            <textarea id="pgUraian" name="uraian" rows="3" maxlength="1000" required
                      placeholder="Contoh: Diikutsertakan pelatihan penyusunan laporan keuangan berbasis SAK ETAP"></textarea>
          </div>
          <div class="form-baris">
            <label class="fl" for="pgTarget">Target waktu</label>
            <input type="date" id="pgTarget" name="target_waktu">
          </div>
          <div class="form-baris">
            <label class="fl" for="pgStatus">Status</label>
            <select id="pgStatus" name="status"><?php foreach (STATUS_PENGEMBANGAN as $k => $s): ?><option value="<?= $k ?>"><?= e($s['label']) ?></option><?php endforeach; ?></select>
          </div>
          <div class="form-baris penuh mb0">
            <label class="fl" for="pgHasil">Hasil / realisasi</label>
            <input type="text" id="pgHasil" name="hasil" maxlength="1000" placeholder="Diisi setelah berjalan atau selesai">
          </div>
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
