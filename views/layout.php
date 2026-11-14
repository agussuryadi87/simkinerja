<?php
$u = user();
$periodeLihat = periode_dipilih();
$antre = antrean_verifikasi();
$jmlAntre = $antre['kegiatan'];
$umpanBaru = umpan_balik_belum_dibaca();
$notif = ['baru' => 0, 'daftar' => []];
try {
    $notif = notifikasi_lonceng(uid());
} catch (Throwable $e) {
}
$bolehUmumkan = false;
try {
    $bolehUmumkan = boleh_umumkan();
} catch (Throwable $e) {
}
$bolehAset = false;
try {
    $bolehAset = boleh_aset();
} catch (Throwable $e) {
}
$bolehJad = false;
try {
    $bolehJad = boleh_jad();
} catch (Throwable $e) {
}
$bolehKeu = false;
try {
    $bolehKeu = boleh_keu();
} catch (Throwable $e) {
}
$rtlTerbukaMenu = 0;
try {
    $idRapatMenu = array_map('intval', array_column(rapat_daftar_terlihat(), 'id'));
    if ($idRapatMenu) {
        $rtlTerbukaMenu = (int) val("SELECT COUNT(*) FROM rapat_rtl WHERE status <> 'selesai' AND rapat_id IN (" . in_placeholder($idRapatMenu) . ')', $idRapatMenu, 0);
    }
} catch (Throwable $e) {
}

function grup_awal(string $judul, string $kunci, array $halaman, string $kelas = '', bool $awalBuka = false): void
{
    echo '<div class="sisi-grup">' . e($judul) . '</div><nav class="sisi-nav' . ($kelas !== '' ? ' ' . e($kelas) : '') . '" id="nav-' . e($kunci) . '">';
}

function menu(string $kunci, string $label, string $namaIkon, string $lencana = '', string $nomor = ''): void
{
    global $h;
    $aktif = ($h === $kunci) ? ' class="aktif"' : '';
    echo '<a href="' . e(url($kunci)) . '"' . $aktif . ' title="' . e($label) . '">'
       . ($nomor !== '' ? '<i class="no-siklus">' . e($nomor) . '</i>' : ikon($namaIkon, 18))
       . '<span>' . e($label) . '</span>'
       . ($lencana !== '' ? '<span class="lencana">' . e($lencana) . '</span>' : '')
       . '</a>';
}

function aset(string $relatif): string
{
    $berkas = APP_ROOT . '/' . ltrim($relatif, '/');
    return base_url($relatif) . '?v=' . (is_file($berkas) ? filemtime($berkas) : APP_VER);
}

$tahapKini = $periodeLihat['tahap'] ?? 'pelaksanaan';
$menuAdmin = can_salah_satu(['pengguna_kelola', 'peran_kelola', 'unit_kelola', 'periode_kelola']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($judul) ?> · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e(aset('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e(aset('assets/css/tema-kantor.css')) ?>">
<script nonce="<?= csp_nonce() ?>">
  (function () { var t = 'kantor'; try { t = localStorage.getItem('simkin.tema') || 'kantor'; } catch (e) {} if (t !== 'hijau') document.documentElement.classList.add('tema-kantor'); try { localStorage.removeItem('simkin.penuh'); } catch (e) {} if (window.top !== window.self) document.documentElement.classList.add('layar-penuh', 'dalam-bingkai'); })();
</script>
</head>
<body data-hal="<?= e($h) ?>" class="<?= in_array($h, ['kegiatan', 'pantau', 'evaluasi', 'pengembangan', 'pengguna', 'peran', 'unit', 'periode', 'absensi', 'laporan', 'rapb_lkps', 'hibah', 'hibah_rekap', 'hibah_dokumen', 'jad_data', 'jad_eligible', 'aset_kk_rekap'], true) ? 'lanskap' : (in_array($h, ['rapb_prodi', 'rapb_upps'], true) ? 'rapb-a4' : '') ?>">
<div class="tata-letak" id="tataLetak">
<script nonce="<?= csp_nonce() ?>">
  try { if (localStorage.getItem('simkin.ciut') === '1') document.getElementById('tataLetak').classList.add('ringkas'); } catch (e) {}
</script>

  <aside class="sisi" id="sisi">
    <div class="mac-lampu" aria-hidden="true"><i class="m"></i><i class="k"></i><i class="h" id="macLayarPenuh" title="Layar penuh"></i></div>
    <label class="mac-cari"><?= ikon('cari', 15) ?><input type="search" id="cariMenu" placeholder="Cari menu" aria-label="Cari menu" autocomplete="off"></label>
    <a class="sisi-merek" href="<?= e(url('dashboard')) ?>">
      <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="Logo <?= APP_INST ?>">
      <div>
        <div class="nm"><?= APP_NAME ?> <span><?= APP_UNIT_PENDEK ?></span></div>
        <div class="sub"><?= APP_INST ?></div>
      </div>
    </a>

    <nav class="sisi-nav sisi-nav-beranda">
      <?php menu('dashboard', 'Beranda', 'beranda'); ?>
      <button class="tombol-ciut sisi-ciut" id="tombolCiut" type="button" title="Ciutkan menu" aria-label="Ciutkan menu"><?= ikon('panel', 18) ?></button>
    </nav>

    <?php if (can_salah_satu(['kegiatan_isi', 'pantau', 'menu_umpan', 'menu_evaluasi', 'menu_pengembangan'])): ?>
    <?php grup_awal('Siklus Kinerja', 'siklus', ['kegiatan', 'pantau', 'umpan', 'evaluasi', 'pengembangan'], 'siklus-nav', true); ?>
      <?php
        if (can('kegiatan_isi')) {
            menu('kegiatan', 'Catatan Harian', 'catatan', '', '1');
        }
        if (can('pantau')) {
            menu('pantau', 'Pemantauan Tim', 'pantau', $jmlAntre > 0 ? (string) $jmlAntre : '', can('kegiatan_isi') ? '2' : '1');
        }
        if (can('menu_umpan'))        menu('umpan', 'Umpan Balik', 'pesan', $umpanBaru > 0 ? (string) $umpanBaru : '');
        if (can('menu_evaluasi'))     menu('evaluasi', 'Evaluasi Kinerja', 'evaluasi', '', '3');
        if (can('menu_pengembangan')) menu('pengembangan', 'Penghargaan & Pengembangan', 'tumbuh', '', '4');
      ?>
    </nav>
    <?php endif; ?>

    <?php if (can_salah_satu(['menu_laporan', 'menu_absensi'])): ?>
    <?php grup_awal('Laporan', 'laporan', ['laporan', 'absensi']); ?>
      <?php if (can('menu_laporan')) menu('laporan', 'Laporan Kinerja', 'rekap'); ?>
      <?php if (can('menu_absensi')) menu('absensi', 'Laporan Absensi', 'kalender'); ?>
    </nav>
    <?php endif; ?>

    <?php $bolehKk = !$bolehAset && kk_boleh(); ?>
    <?php if ($bolehAset || $bolehKk || $bolehKeu || $bolehJad || can_salah_satu(['menu_rapat', 'menu_hibah'])): ?>
    <?php grup_awal('Modul Layanan', 'terintegrasi', array_merge(['rapat', 'rapat_dokumen', 'hibah', 'hibah_rekap', 'hibah_dokumen'], keu_halaman_aktif(), jad_halaman_aktif(), aset_halaman_aktif())); ?>
      <?php $keuAktif = in_array($h, keu_halaman_aktif(), true); ?>
      <?php if ($bolehKeu): ?>
      <a href="<?= e(url('rapb')) ?>"<?= $keuAktif ? ' class="aktif"' : '' ?> title="Bidang Keuangan"><?= ikon('audit', 18) ?><span>Bidang Keuangan</span></a>
      <?php endif; ?>
      <?php if ($bolehJad): ?>
      <a href="<?= e(url('jad')) ?>"<?= in_array($h, jad_halaman_aktif(), true) ? ' class="aktif"' : '' ?> title="Kepegawaian & BKD"><?= ikon('topi', 18) ?><span>Kepegawaian &amp; BKD</span></a>
      <?php endif; ?>
      <?php if ($bolehKk): ?>
      <a href="<?= e(url('aset_kk_ruang')) ?>"<?= in_array($h, KK_HALAMAN_CS, true) ? ' class="aktif"' : '' ?> title="Sarana dan Prasarana"><?= ikon('aset', 18) ?><span>Sarana dan Prasarana</span></a>
      <?php endif; ?>
      <?php if ($bolehAset): ?>
      <a href="<?= e(url('aset')) ?>"<?= in_array($h, aset_halaman_aktif(), true) ? ' class="aktif"' : '' ?> title="Sarana dan Prasarana"><?= ikon('aset', 18) ?><span>Sarana dan Prasarana</span></a>
      <?php endif; ?>
      <?php if (can('menu_rapat')): ?>
      <a href="<?= e(url('rapat')) ?>"<?= in_array($h, ['rapat', 'rapat_dokumen'], true) ? ' class="aktif"' : '' ?> title="Administrasi Surat"><?= ikon('grup', 18) ?><span>Administrasi Surat</span><?= $rtlTerbukaMenu > 0 ? '<span class="lencana">' . $rtlTerbukaMenu . '</span>' : '' ?></a>
      <?php endif; ?>
      <?php if (can('menu_hibah')): ?><a href="<?= e(url('hibah')) ?>"<?= in_array($h, ['hibah', 'hibah_rekap', 'hibah_dokumen'], true) ? ' class="aktif"' : '' ?> title="Perencanaan dan Hibah"><?= ikon('hibah', 18) ?><span>Perencanaan dan Hibah</span></a><?php endif; ?>
    </nav>
    <?php endif; ?>

    <?php if ($menuAdmin): ?>
    <?php grup_awal('Pengaturan', 'pengaturan', ['pengguna', 'peran', 'unit', 'periode']); ?>
      <?php
        if (can('pengguna_kelola')) menu('pengguna', 'Pengguna', 'pengguna');
        if (can('peran_kelola'))    menu('peran', 'Peran & Hak Akses', 'perisai');
        if (can('unit_kelola'))     menu('unit', 'Unit Kerja', 'gedung');
        if (can('periode_kelola'))  menu('periode', 'Periode & Siklus', 'siklus');
      ?>
    </nav>
    <?php endif; ?>

    <div class="sisi-kaki">
      <span class="inst"><?= APP_UNIT ?></span>
      <span class="hak">@copyright Bidang Perencanaan <?= APP_INST_PENDEK ?></span>
    </div>
  </aside>
  <div class="tirai-sisi" id="tiraiSisi"></div>

  <div class="utama">
    <header class="atas">
      <button class="tombol-menu" id="tombolMenu" type="button" aria-label="Buka menu"><?= ikon('menu', 21) ?></button>
      <span class="mac-navigasi"><button type="button" id="navMundur" title="Kembali" aria-label="Kembali"><?= ikon('kiri', 18) ?></button><button type="button" id="navMaju" title="Maju" aria-label="Maju"><?= ikon('kanan', 18) ?></button></span>

        <?php if (count(semua_periode()) > 0): ?>
        <form method="get" class="pilih-periode" title="Periode kinerja yang ditampilkan">
          <input type="hidden" name="h" value="<?= e($h) ?>">
          <?php foreach (['p', 'bln', 'jenis', 'tab'] as $sisa): if (isset($_GET[$sisa]) && is_string($_GET[$sisa])): ?>
            <input type="hidden" name="<?= $sisa ?>" value="<?= e($_GET[$sisa]) ?>">
          <?php endif; endforeach; ?>
          <?= ikon('kalender', 16) ?>
          <select name="periode" data-auto-kirim aria-label="Pilih periode">
            <?php foreach (semua_periode() as $pr): ?>
              <option value="<?= (int) $pr['id'] ?>" <?= (int) $pr['id'] === pid() ? 'selected' : '' ?>>
                <?= e($pr['nama']) ?><?= $pr['aktif'] ? ' • aktif' : '' ?>
              </option>
            <?php endforeach; ?>
          </select>
        </form>
        <?php endif; ?>

      <div class="judul">
        <?= e($judul) ?>
        <small><?= $subjudul !== '' ? e($subjudul) : e(APP_LONG . ' ' . APP_UNIT_PENDEK) ?></small>
      </div>

      <div class="kanan">
        <div class="lonceng" id="lonceng" data-url="<?= e(url('notifikasi')) ?>" data-csrf="<?= e(csrf_token()) ?>">
          <button type="button" class="lonceng-tombol" id="loncengTombol" aria-haspopup="true" aria-expanded="false" aria-label="Notifikasi" title="Notifikasi">
            <?= ikon('lonceng', 19) ?><span class="lonceng-lencana" id="loncengLencana"<?= $notif['baru'] > 0 ? '' : ' hidden' ?>><?= (int) $notif['baru'] ?></span>
          </button>
          <div class="lonceng-tarik" role="menu">
            <div class="lt-kepala"><b>Notifikasi</b>
              <span class="lt-kepala-aksi"><button type="button" id="loncengBaca"<?= $notif['baru'] > 0 ? '' : ' hidden' ?>>Tandai dibaca</button>
              <?php if ($bolehUmumkan): ?><button type="button" class="lt-tulis" data-buka="tiraiPengumuman"><?= ikon('tambah', 13) ?> Pengumuman</button><?php endif; ?></span></div>
            <div id="loncengDaftar">
              <?php if (!$notif['daftar']): ?><p class="lt-kosong">Belum ada notifikasi.</p><?php endif; ?>
              <?php foreach ($notif['daftar'] as $n): ?>
                <a class="lt-item<?= $n['baru'] ? ' baru' : '' ?><?= ($n['jenis'] ?? '') === 'pengumuman' ? ' lt-umum' : '' ?>" href="<?= e($n['url']) ?>">
                  <span class="lt-judul"><?= e($n['judul']) ?></span>
                  <span class="lt-ket"><?= e($n['waktu']) ?><?= $n['tempat'] ? ' · ' . e($n['tempat']) : '' ?></span>
                </a>
              <?php endforeach; ?>
            </div>
            <div class="lt-kaki"><a class="lt-semua" href="<?= e(url('pengumuman')) ?>">Semua pengumuman</a><a class="lt-semua" href="<?= e(url('rapat')) ?>">Semua rapat</a></div>
          </div>
        </div>

        <button class="btn btn-garis btn-kecil tombol-cetak" type="button" data-cetak title="Cetak halaman ini atau simpan sebagai PDF">
          <?= ikon('cetak', 15) ?> <span>Cetak PDF</span>
        </button>


        <div class="pengguna-menu" id="penggunaMenu">
          <button type="button" class="pengguna-chip" id="penggunaChip" aria-haspopup="true" aria-expanded="false">
            <?= avatar($u['nama']) ?>
            <span class="info">
              <b><?= e($u['nama']) ?></b>
              <span><?= e($u['peran_nama']) ?></span>
            </span>
          </button>
          <div class="pengguna-tarik" role="menu">
            <div class="pt-kepala">
              <b><?= e($u['nama']) ?></b>
              <span><?= e($u['jabatan'] ?: $u['peran_nama']) ?></span>
              <?php if ($u['unit_nama']): ?><span class="lemah"><?= e($u['unit_nama']) ?></span><?php endif; ?>
            </div>
            <a href="<?= e(url('profil')) ?>"><?= ikon('profil', 16) ?> Profil &amp; kata sandi</a>
            <div class="pilih-tema-grup" id="pilihTema"><small>Tampilan</small><div class="pilih-tema-pilihan"><button type="button" data-tema="kantor"><i></i>Kantor</button><button type="button" data-tema="hijau"><i></i>Hijau klasik</button></div></div>
            <form method="post" action="<?= e(url('logout')) ?>">
              <?= csrf_field() ?>
              <button type="submit"><?= ikon('keluar', 16) ?> Keluar</button>
            </form>
          </div>
        </div>
      </div>
    </header>

    <main class="isi">
      <?php if (!in_array($h, ['laporan', 'aset_laporan', 'rapb_prodi', 'rapb_upps', 'rapb_lkps'], true) && !($h === 'rapat' && ($_GET['aksi'] ?? '') === 'cetak')): ?>
        <div class="cetak-saja cetak-kop">
          <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="">
          <div>
            <b><?= e(APP_INST) ?></b>
            <span><?= e(APP_UNIT) ?></span>
            <h2><?= e($judul) ?><?= $subjudul !== '' ? ' · ' . e($subjudul) : '' ?></h2>
            <span><?= $periodeLihat ? e($periodeLihat['nama']) . ' · ' : '' ?>Dicetak oleh <?= e($u['nama']) ?>, <?= e(tanggal_id(date('Y-m-d'))) ?> <?= e(date('H:i')) ?> WIB</span>
          </div>
        </div>
      <?php endif; ?>
      <?php if ($periodeLihat && !$periodeLihat['aktif']): ?>
        <div class="notif notif-info"><?= ikon('info', 17) ?>
          <div>Anda sedang melihat periode <b><?= e($periodeLihat['nama']) ?></b>, bukan periode aktif.</div>
        </div>
      <?php endif; ?>
      <?php
      $ikonNotif = ['ok' => 'centang', 'galat' => 'silang', 'warn' => 'seru', 'info' => 'info'];
      foreach (ambil_flash() as $f): ?>
        <div class="notif notif-<?= e($f['tipe']) ?>">
          <?= ikon($ikonNotif[$f['tipe']] ?? 'info', 17) ?>
          <div><?= $f['pesan'] ?></div>
          <button class="tutup" type="button" data-tutup-notif aria-label="Tutup"><?= ikon('tutup', 15) ?></button>
        </div>
      <?php endforeach; ?>

      <?php if ($bolehAset && in_array($h, aset_halaman_aktif(), true)) { echo aset_navigasi($h); } elseif (in_array($h, KK_HALAMAN_CS, true) && kk_boleh()) { echo kk_navigasi($h); } ?>
      <?php if ($bolehJad && in_array($h, jad_halaman_aktif(), true)) { echo jad_navigasi($h); } ?>
      <?php if ($bolehKeu && in_array($h, keu_halaman_aktif(), true)) { echo keu_navigasi($h); } ?>
      <?= $konten ?>
    </main>
  </div>
</div>

<div class="tirai" id="tiraiKonfirmasi" hidden>
  <div class="modal sempit" role="dialog" aria-modal="true" aria-labelledby="konfJudul">
    <div class="modal-kepala"><h2 id="konfJudul">Konfirmasi</h2>
      <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
    <div class="modal-isi"><p id="konfPesan" class="mb0"></p></div>
    <div class="modal-kaki">
      <button type="button" class="btn btn-garis" data-tutup>Batal</button>
      <button type="button" class="btn btn-bahaya" id="konfYa">Ya, lanjutkan</button>
    </div>
  </div>
</div>

<?php if ($bolehUmumkan): ?>
<div class="tirai" id="tiraiPengumuman" hidden>
  <div class="modal" role="dialog" aria-modal="true" aria-labelledby="pgJudulModal">
    <form method="post" action="<?= e(url('pengumuman')) ?>">
      <?= csrf_field() ?><input type="hidden" name="op" value="kirim"><input type="hidden" name="balik" value="<?= e(http_build_query(['h' => $h] + array_filter($_GET, 'is_string'))) ?>">
      <div class="modal-kepala"><h2 id="pgJudulModal"><?= ikon('lonceng', 17) ?> Tulis pengumuman</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="pgJudul">Judul</label><input type="text" id="pgJudul" name="judul" maxlength="150" required placeholder="Contoh: Apel pagi hari Senin"></div>
        <div class="form-baris"><label class="fl" for="pgIsi">Isi pesan</label><textarea id="pgIsi" name="isi" rows="5" maxlength="3000" required placeholder="Tulis pesan yang ingin disampaikan…"></textarea></div>
        <div class="pg-baris">
          <div class="form-baris"><label class="fl" for="pgSasaran">Ditujukan kepada</label><select id="pgSasaran" name="sasaran"><?= pilihan(PENGUMUMAN_SASARAN, 'semua') ?></select></div>
          <div class="form-baris"><label class="fl" for="pgSampai">Tampil sampai <small class="lemah">(opsional)</small></label><input type="date" id="pgSampai" name="sampai" min="<?= date('Y-m-d') ?>"></div>
        </div>
        <label class="pg-penting"><input type="checkbox" name="penting" value="1"> Tandai <b>penting</b> (tampil paling atas)</label>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('kirim', 15) ?> Kirim pengumuman</button>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<script src="<?= e(aset('assets/js/app.js')) ?>"></script>
</body>
</html>
