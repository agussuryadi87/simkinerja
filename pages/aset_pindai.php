<?php
if (isset($_GET['cari'])) {
    $hasil = is_string($_GET['cari']) ? aset_cari_pindaian($_GET['cari']) : null;
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($hasil ? ['ok' => true] + $hasil : ['ok' => false], JSON_UNESCAPED_UNICODE);
    exit;
}
$subjudul = '';
?>
<div class="pindai-tata">
  <section class="kartu pindai-kartu">
    <div class="kartu-kepala"><div><h2><?= ikon('kamera', 18) ?> Pindai label QR</h2></div></div>
    <div class="kartu-isi">
      <div class="pindai-layar" id="pindaiLayar">
        <video id="pindaiVideo" playsinline muted></video>
        <div class="pindai-bingkai" aria-hidden="true"><i></i><i></i><i></i><i></i><span class="pindai-garis"></span></div>
        <div class="pindai-tirai" id="pindaiTirai">
          <div class="pindai-tirai-ikon"><?= ikon('kamera', 34) ?></div>
          <p id="pindaiPesan">Ketuk tombol di bawah untuk menyalakan kamera.</p>
          <button class="btn btn-utama" type="button" id="pindaiMulai"><?= ikon('kamera', 16) ?> Nyalakan kamera</button>
        </div>
      </div>
      <div class="pindai-alat">
        <button class="btn btn-garis btn-kecil" type="button" id="pindaiGanti" hidden><?= ikon('ulang', 15) ?> Ganti kamera</button>
        <button class="btn btn-garis btn-kecil" type="button" id="pindaiSenter" hidden aria-pressed="false"><?= ikon('petir', 15) ?> Senter</button>
        <button class="btn btn-garis btn-kecil" type="button" id="pindaiBerhenti" hidden><?= ikon('tutup', 15) ?> Matikan kamera</button>
        <label class="btn btn-garis btn-kecil" for="pindaiFoto"><?= ikon('unggah', 15) ?> Pindai dari foto</label>
        <input type="file" id="pindaiFoto" accept="image/*" capture="environment" hidden>
      </div>
      <form class="pindai-manual" id="pindaiManual" autocomplete="off">
        <label class="fl" for="pindaiKode">Atau ketik kode inventaris / kode ruangan</label>
        <div class="pindai-manual-baris">
          <input type="text" id="pindaiKode" maxlength="100" placeholder="Contoh: CTH-AC-001" autocapitalize="characters" spellcheck="false">
          <button class="btn btn-utama" type="submit"><?= ikon('cari', 15) ?> Cari</button>
        </div>
      </form>
    </div>
  </section>

  <section class="kartu pindai-kartu">
    <div class="kartu-kepala"><div><h2><?= ikon('aset', 18) ?> Hasil pindai</h2><p class="ket" id="pindaiStatus">Belum ada yang dipindai.</p></div></div>
    <div class="kartu-isi">
      <div id="pindaiHasil" class="pindai-hasil" aria-live="polite">
        <div class="pindai-kosong"><?= ikon('aset', 30) ?><p>Hasil pindai akan tampil di sini.</p></div>
      </div>
      <div class="pindai-riwayat" id="pindaiRiwayatBungkus" hidden>
        <h3>Terakhir dipindai</h3>
        <ul id="pindaiRiwayat"></ul>
      </div>
    </div>
  </section>
</div>
<script src="<?= e(base_url('assets/js/qr_baca.js')) ?>?v=<?= (int) @filemtime(APP_ROOT . '/assets/js/qr_baca.js') ?>"></script>
<script src="<?= e(base_url('assets/js/pindai.js')) ?>?v=<?= (int) @filemtime(APP_ROOT . '/assets/js/pindai.js') ?>" data-cari="<?= e(url('aset_pindai')) ?>"></script>
