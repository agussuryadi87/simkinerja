<?php
$saya = uid();
$tab = ($_GET['tab'] ?? '') === 'dikirim' && can('pantau') ? 'dikirim' : 'diterima';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'tanggapi') {
        $ub = row('SELECT * FROM umpan_balik WHERE id = ? AND pegawai_id = ?', [(int) post('id'), $saya]);
        if (!$ub) {
            tolak_akses();
        }
        update('umpan_balik', ['tanggapan' => mb_substr(post('tanggapan'), 0, 4000), 'dibaca' => 1], 'id = ?', [$ub['id']]);
        flash('Tanggapan tersimpan.');
    } elseif ($op === 'hapus') {
        $n = q('DELETE FROM umpan_balik WHERE id = ? AND pemberi_id = ?', [(int) post('id'), $saya])->rowCount();
        flash($n ? 'Umpan balik dihapus.' : 'Umpan balik tidak ditemukan.', $n ? 'ok' : 'galat');
    }
    kembali_ke('umpan', ['tab' => $tab === 'dikirim' ? 'dikirim' : null]);
}

if ($tab === 'diterima') {
    $daftar = rows(
        'SELECT ub.*, pg.nama AS lawan_nama, pr.nama AS periode_nama FROM umpan_balik ub
           JOIN pengguna pg ON pg.id = ub.pemberi_id JOIN periode pr ON pr.id = ub.periode_id
          WHERE ub.pegawai_id = ? ORDER BY ub.dibuat DESC',
        [$saya]
    );
    q('UPDATE umpan_balik SET dibaca = 1 WHERE pegawai_id = ? AND dibaca = 0', [$saya]);
} else {
    $daftar = rows(
        'SELECT ub.*, pg.nama AS lawan_nama, pr.nama AS periode_nama FROM umpan_balik ub
           JOIN pengguna pg ON pg.id = ub.pegawai_id JOIN periode pr ON pr.id = ub.periode_id
          WHERE ub.pemberi_id = ? ORDER BY ub.dibuat DESC',
        [$saya]
    );
}
?>

<nav class="tab tab-atas">
  <a href="<?= e(url('umpan')) ?>" class="<?= $tab === 'diterima' ? 'aktif' : '' ?>"><?= ikon('pesan', 16) ?> Diterima</a>
  <?php if (can('pantau')): ?>
    <a href="<?= e(url('umpan', ['tab' => 'dikirim'])) ?>" class="<?= $tab === 'dikirim' ? 'aktif' : '' ?>"><?= ikon('kirim', 16) ?> Dikirim ke tim</a>
  <?php endif; ?>
</nav>

<div class="kartu umpan-kartu">
  <div class="kartu-kepala kepala-nusantara"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="lapis awan"></div><div class="lapis gunung"></div><div class="lapis candi"></div><div class="lapis laut"></div><div class="lapis batik"></div></div>
    <div><h2><?= ikon($tab === 'diterima' ? 'pesan' : 'kirim', 18) ?> <?= $tab === 'diterima' ? 'Umpan balik diterima' : 'Umpan balik dikirim ke tim' ?></h2>
      <p class="ket"><?= count($daftar) ?> pesan</p></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong($tab === 'diterima' ? 'Belum ada umpan balik' : 'Belum mengirim umpan balik') ?>
  <?php else: ?>
  <div class="kartu-isi">
  <?php endif; ?>

  <?php foreach ($daftar as $ub): ?>
    <div class="pesan-kartu<?= $tab === 'diterima' && !$ub['dibaca'] ? ' baru' : '' ?>">
      <div class="kepala">
        <?= avatar($ub['lawan_nama'], 'kecil') ?>
        <b class="kecil"><?= $tab === 'dikirim' ? 'Kepada ' : '' ?><?= e($ub['lawan_nama']) ?></b>
        <?= lencana_dari(JENIS_UMPAN, $ub['jenis']) ?>
        <span class="wkt"><?= e(tanggal_id(substr($ub['dibuat'], 0, 10))) ?> · <?= e($ub['periode_nama']) ?></span>
      </div>
      <div class="isi-pesan"><?= e($ub['isi']) ?></div>
      <?php if ($ub['tanggapan']): ?>
        <div class="tanggapan"><b><?= $tab === 'diterima' ? 'Tanggapan Anda' : 'Tanggapan pegawai' ?>:</b> <?= e($ub['tanggapan']) ?></div>
      <?php endif; ?>
      <div class="mt8" style="display:flex;gap:6px">
        <?php if ($tab === 'diterima'): ?>
          <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiTanggap"
                  data-isi='<?= e(json_encode(['id' => (int) $ub['id'], 'tanggapan' => $ub['tanggapan'] ?? ''])) ?>'>
            <?= ikon('pesan', 14) ?> <?= $ub['tanggapan'] ? 'Ubah tanggapan' : 'Tanggapi' ?></button>
        <?php else: ?>
          <form method="post" data-konfirmasi="Hapus umpan balik ini?"><?= csrf_field() ?>
            <input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $ub['id'] ?>">
            <button class="btn btn-hantu btn-kecil" type="submit"><?= ikon('hapus', 14) ?> Hapus</button></form>
        <?php endif; ?>
      </div>
    </div>
  <?php endforeach; ?>
  <?php if ($daftar): ?></div><?php endif; ?>
</div>

<div class="tirai" id="tiraiTanggap" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="tanggapi"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Tanggapi umpan balik</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <label class="fl" for="tgIsi">Tanggapan / rencana tindak lanjut</label>
        <textarea id="tgIsi" name="tanggapan" rows="5" maxlength="4000" placeholder="Contoh: Siap, akan saya lengkapi buktinya paling lambat Jumat."></textarea>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
