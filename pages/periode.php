<?php
$urutTahap = array_merge(array_keys(TAHAP_SIKLUS), ['selesai']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'simpan') {
        $id = (int) post('id');
        $data = [
            'nama'    => mb_substr(post('nama'), 0, 100),
            'mulai'   => post('mulai'),
            'selesai' => post('selesai'),
            'tahap'   => in_array(post('tahap'), $urutTahap, true) ? post('tahap') : 'pelaksanaan',
        ];
        if ($data['nama'] === '' || !tanggal_sah($data['mulai']) || !tanggal_sah($data['selesai']) || $data['selesai'] < $data['mulai']) {
            flash('Nama periode wajib diisi dan tanggal selesai harus setelah tanggal mulai.', 'galat');
            kembali_ke('periode');
        }
        if ($id) {
            update('periode', $data, 'id = ?', [$id]);
        } else {
            $id = insert('periode', $data);
            if ((int) val('SELECT COUNT(*) FROM periode WHERE aktif = 1', [], 0) === 0) {
                q('UPDATE periode SET aktif = 1 WHERE id = ?', [$id]);
            }
        }
        catat_log('simpan_periode', 'periode', $id, $data['nama'] . ' / ' . $data['tahap']);
        flash('Periode <b>' . e($data['nama']) . '</b> tersimpan.');
    } elseif ($op === 'aktifkan') {
        $id = (int) post('id');
        if (!val('SELECT id FROM periode WHERE id = ?', [$id])) {
            flash('Periode tidak ditemukan.', 'galat');
        } else {
            q('UPDATE periode SET aktif = (id = ?)', [$id]);
            unset($_SESSION['periode_id']);
            catat_log('aktifkan_periode', 'periode', $id, '');
            flash('Periode aktif diganti.');
        }
    } elseif ($op === 'tahap') {
        $id = (int) post('id');
        $tahap = post('tahap');
        if (in_array($tahap, $urutTahap, true)) {
            q('UPDATE periode SET tahap = ? WHERE id = ?', [$tahap, $id]);
            catat_log('tahap_periode', 'periode', $id, $tahap);
            flash('Tahap siklus dipindahkan ke <b>' . e(TAHAP_SIKLUS[$tahap]['label'] ?? 'Selesai') . '</b>.');
        }
    } elseif ($op === 'hapus') {
        $id = (int) post('id');
        $isi = (int) val('SELECT (SELECT COUNT(*) FROM kegiatan WHERE periode_id = ?)
                                 + (SELECT COUNT(*) FROM evaluasi WHERE periode_id = ?) + (SELECT COUNT(*) FROM pengembangan WHERE periode_id = ?)
                                 + (SELECT COUNT(*) FROM umpan_balik WHERE periode_id = ?)', [$id, $id, $id, $id], 0);
        if ($isi > 0) {
            flash('Periode sudah berisi data kinerja sehingga tidak dapat dihapus.', 'warn');
        } else {
            q('DELETE FROM periode WHERE id = ?', [$id]);
            flash('Periode dihapus.');
        }
    }
    kembali_ke('periode');
}

$daftar = rows(
    'SELECT pr.*, (SELECT COUNT(*) FROM kegiatan k WHERE k.periode_id = pr.id) AS jml_kegiatan,
            (SELECT COUNT(DISTINCT k2.pengguna_id) FROM kegiatan k2 WHERE k2.periode_id = pr.id) AS jml_pegawai,
            (SELECT COUNT(*) FROM evaluasi e WHERE e.periode_id = pr.id AND e.status = "final") AS jml_final
       FROM periode pr ORDER BY pr.mulai DESC'
);
$thnBaru = (int) date('Y') + ((int) date('n') >= 8 ? 1 : 0);
?>

<div class="kartu">
  <div class="kartu-kepala">
    <h2><?= ikon('siklus', 18) ?> Periode kinerja</h2>
    <div class="aksi"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPeriode" data-reset data-judul="Buka periode baru"
        data-isi='<?= e(json_encode(['id' => '', 'tahap' => 'pelaksanaan', 'nama' => 'Tahun Kinerja ' . $thnBaru . '/' . ($thnBaru + 1),
            'mulai' => $thnBaru . '-08-01', 'selesai' => ($thnBaru + 1) . '-07-31'])) ?>'><?= ikon('tambah', 15) ?> Periode baru</button></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong('Belum ada periode') ?>
  <?php endif; ?>
  <?php foreach ($daftar as $p): $noKini = TAHAP_SIKLUS[$p['tahap']]['no'] ?? 5; ?>
    <div class="kartu-isi" style="border-bottom:1px solid var(--garis)">
      <div style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin-bottom:12px">
        <div><b style="font-size:1rem"><?= e($p['nama']) ?></b> <?= $p['aktif'] ? lencana('Aktif', 'hijau') : '' ?> <?= $p['tahap'] === 'selesai' ? lencana('Selesai') : '' ?>
          <div class="kecil lemah"><?= e(tanggal_id($p['mulai'])) ?> s.d. <?= e(tanggal_id($p['selesai'])) ?> · <?= (int) $p['jml_pegawai'] ?> pegawai mencatat ·
            <?= (int) $p['jml_kegiatan'] ?> kegiatan · <?= (int) $p['jml_final'] ?> evaluasi final</div></div>
        <div class="aksi-baris" style="margin-left:auto">
          <?php if (!$p['aktif']): ?>
            <form method="post" data-konfirmasi="Jadikan <?= e($p['nama']) ?> sebagai periode aktif?" data-gaya="utama"><?= csrf_field() ?>
              <input type="hidden" name="op" value="aktifkan"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
              <button class="btn btn-garis btn-kecil" type="submit">Jadikan aktif</button></form>
          <?php endif; ?>
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah periode" aria-label="Ubah periode <?= e($p['nama']) ?>" data-buka="tiraiPeriode" data-judul="Ubah periode"
                  data-isi='<?= e(json_encode(['id' => (int) $p['id'], 'nama' => $p['nama'], 'mulai' => $p['mulai'], 'selesai' => $p['selesai'], 'tahap' => $p['tahap']])) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" data-konfirmasi="Hapus periode <?= e($p['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus">
            <input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus periode" aria-label="Hapus periode <?= e($p['nama']) ?>"><?= ikon('hapus', 15) ?></button></form>
        </div>
      </div>
      <div style="margin:-4px 0 12px">
        <?php for ($t = 1; $t <= 4; $t++): [$m, $s] = tw_rentang($p, $t); ?>
          <span class="lc lc-abu" style="margin:0 4px 4px 0" title="Triwulan <?= $t ?>"><b>TW<?= $t ?></b>&nbsp;<?= e(tanggal_pendek($m)) ?> s.d. <?= e(tanggal_pendek($s)) ?></span>
        <?php endfor; ?>
      </div>
      <div class="siklus" style="margin-bottom:0">
        <?php foreach (TAHAP_SIKLUS as $k => $t): $kelas = $t['no'] < $noKini ? 'lewat' : ($t['no'] === $noKini ? 'kini' : ''); ?>
          <div class="siklus-langkah <?= $kelas ?>" style="align-items:center">
            <span class="bulat"><?= $t['no'] < $noKini ? ikon('centang', 16) : (int) $t['no'] ?></span>
            <div style="flex:1"><small><?= e($t['sub']) ?><?= $kelas === 'kini' ? ' · tahap berjalan' : '' ?></small><b><?= e($t['label']) ?></b>
              <p><?= e($t['ket']) ?></p></div>
            <?php if ($kelas !== 'kini'): ?>
              <form method="post" data-konfirmasi="Pindahkan tahap siklus ke <?= e($t['label']) ?>?" data-gaya="utama"><?= csrf_field() ?>
                <input type="hidden" name="op" value="tahap"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="tahap" value="<?= $k ?>">
                <button class="btn btn-garis btn-kecil" type="submit" title="Jadikan tahap ini sebagai tahap berjalan">Jadikan berjalan</button></form>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <?php if ($p['tahap'] !== 'selesai'): ?>
        <form method="post" class="mt8" data-konfirmasi="Tandai periode ini selesai? Data periode menjadi hanya-baca."><?= csrf_field() ?>
          <input type="hidden" name="op" value="tahap"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="tahap" value="selesai">
          <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('gembok', 14) ?> Tandai periode selesai</button></form>
      <?php else: ?>
        <form method="post" class="mt8"><?= csrf_field() ?>
          <input type="hidden" name="op" value="tahap"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>"><input type="hidden" name="tahap" value="pengembangan">
          <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('ulang', 14) ?> Buka kembali periode</button></form>
      <?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>

<div class="tirai" id="tiraiPeriode" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Periode</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="pdNama">Nama periode <span class="wajib">*</span></label>
          <input type="text" id="pdNama" name="nama" maxlength="100" required placeholder="Tahun Kinerja 2027"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="pdMulai">Mulai</label><input type="date" id="pdMulai" name="mulai" required></div>
          <div class="form-baris"><label class="fl" for="pdSelesai">Selesai</label><input type="date" id="pdSelesai" name="selesai" required></div>
        </div>
        <div class="form-baris mb0"><label class="fl" for="pdTahap">Tahap siklus</label>
          <select id="pdTahap" name="tahap"><?php foreach (TAHAP_SIKLUS as $k => $t): ?><option value="<?= $k ?>"><?= $t['no'] ?>. <?= e($t['label']) ?></option><?php endforeach; ?>
            <option value="selesai">Selesai</option></select></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
