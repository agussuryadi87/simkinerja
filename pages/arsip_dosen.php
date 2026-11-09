<?php
$token = (string) ($_GET['t'] ?? '');
$nomor = mb_substr(trim((string) ($_POST['nomor'] ?? $_GET['nomor'] ?? '')), 0, 30);
$galat = [];
$isian = [];
$dosen = $nomor !== '' ? jad_arsip_cari_dosen($nomor) : null;
$arsip = jad_arsip_baca();
$catatan = $dosen ? ($arsip['dosen'][jad_arsip_kunci($dosen)] ?? null) : null;
$linkLama = is_string($catatan['link'] ?? null) ? $catatan['link'] : '';
$dokLama = is_array($catatan['dokumen'] ?? null) ? $catatan['dokumen'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $isian = $_POST;
    $kontak = mb_substr(trim(post('kontak')), 0, 60);
    $ketLain = mb_substr(trim(post('ket_lainnya')), 0, 300);
    $link = mb_substr(trim(post('link')), 0, 1000);
    $dok = array_values(array_intersect(array_keys(JAD_ARSIP_JENIS), array_map('strval', (array) ($_POST['dokumen'] ?? []))));
    if (post('situs') !== '') {
        $galat[] = 'Kiriman tidak dapat diproses.';
    }
    if (!$dosen) {
        $galat[] = 'NIDN / NUPTK tidak ditemukan pada data dosen.';
    }
    if ($link === '') {
        $galat[] = 'Link folder dokumen wajib diisi.';
    } elseif (!jad_arsip_url_sah($link)) {
        $galat[] = 'Link folder tidak sah. Gunakan alamat lengkap yang diawali https://';
    }
    if (!$dok) {
        $galat[] = 'Centang minimal satu dokumen yang sudah ada di folder.';
    }
    if ($kontak !== '' && !preg_match('/^[0-9+()\-\s.@a-zA-Z_]{5,60}$/', $kontak)) {
        $galat[] = 'Nomor HP / email tidak sah.';
    }
    if (!$galat && !jad_arsip_boleh_kirim(ip_pengunjung())) {
        $galat[] = 'Terlalu banyak kiriman dari perangkat ini. Silakan coba lagi sekitar 1 jam lagi.';
    }
    if (!$galat) {
        $kunci = jad_arsip_kunci($dosen);
        $tokenBaru = jad_arsip_ubah(static function (array &$d) use ($kunci, $dosen, $link, $dok, $kontak, $ketLain): string {
            $lama = is_array($d['dosen'][$kunci] ?? null) ? $d['dosen'][$kunci] : [];
            $riwayat = is_array($lama['riwayat'] ?? null) ? $lama['riwayat'] : [];
            if (!empty($lama['link'])) {
                array_unshift($riwayat, ['waktu' => $lama['waktu'] ?? '', 'link' => $lama['link'], 'dokumen' => $lama['dokumen'] ?? []]);
                $riwayat = array_slice($riwayat, 0, 10);
            }
            $tok = (string) ($lama['token'] ?? '');
            if (!preg_match('/^[a-f0-9]{32}$/', $tok)) {
                $tok = bin2hex(random_bytes(16));
            }
            $d['dosen'][$kunci] = [
                'nama' => (string) $dosen['nama'], 'nidn' => (string) $dosen['nidn'], 'nuptk' => (string) $dosen['nuptk'], 'prodi' => (string) $dosen['prodi'],
                'link' => $link, 'dokumen' => $dok, 'ket_lainnya' => $ketLain, 'kontak' => $kontak !== '' ? $kontak : (string) ($lama['kontak'] ?? ''),
                'waktu' => date('Y-m-d H:i:s'), 'kirim' => (int) ($lama['kirim'] ?? 0) + 1, 'token' => $tok, 'riwayat' => $riwayat,
            ];
            return $tok;
        });
        if (is_string($tokenBaru)) {
            catat_log('arsip_dosen_publik', 'dosen_data', (int) $dosen['id'], 'Link arsip dokumen ' . $dosen['nama']);
            redirect(base_url('index.php?h=arsip_dosen&t=' . $tokenBaru . '&baru=1'));
        }
        $galat[] = 'Kiriman gagal disimpan. Silakan coba lagi.';
    }
}

$selesai = null;
if ($token !== '' && preg_match('/^[a-f0-9]{32}$/', $token)) {
    foreach ($arsip['dosen'] as $info) {
        if (is_array($info) && hash_equals((string) ($info['token'] ?? ''), $token)) {
            $selesai = $info;
            break;
        }
    }
}

$terpilih = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_map('strval', (array) ($isian['dokumen'] ?? [])) : $dokLama;
$nilaiLink = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($isian['link'] ?? '') : $linkLama;
$nilaiKet = $_SERVER['REQUEST_METHOD'] === 'POST' ? (string) ($isian['ket_lainnya'] ?? '') : (string) ($catatan['ket_lainnya'] ?? '');
$versi = static function (string $f): string {
    return base_url($f) . '?v=' . (is_file(APP_ROOT . '/' . $f) ? filemtime(APP_ROOT . '/' . $f) : APP_VER);
};
$totalJenis = count(JAD_ARSIP_JENIS);
?>
<!DOCTYPE html>
<html lang="id" class="tema-kantor">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex">
<title>Arsip Dokumen Dosen · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e($versi('assets/css/app.css')) ?>">
<link rel="stylesheet" href="<?= e($versi('assets/css/tema-kantor.css')) ?>">
</head>
<body class="publik publik-arsip">
  <header class="publik-merek">
    <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="Logo <?= APP_INST ?>">
    <div><b><?= APP_INST ?></b><span><?= APP_UNIT ?></span></div>
  </header>

  <main class="publik-isi">
    <?php if ($selesai): $dokS = is_array($selesai['dokumen'] ?? null) ? $selesai['dokumen'] : []; ?>
      <section class="publik-kartu">
        <?php if (!empty($_GET['baru'])): ?>
          <div class="notif notif-ok"><?= ikon('centang', 17) ?><div><b>Link dokumen tersimpan.</b> Terima kasih, dokumen Anda sudah terarsip. Simpan halaman ini bila ingin melihat kiriman Anda kembali.</div></div>
        <?php endif; ?>
        <h1>Arsip Dokumen Dosen</h1>
        <p class="sub"><?= e(jad_huruf_nama($selesai['nama'])) ?> · <?= e($selesai['prodi'] ?: '-') ?> · diperbarui <?= e(tanggal_id(substr((string) $selesai['waktu'], 0, 10))) ?></p>
        <?php if (is_string($selesai['link'] ?? null) && $selesai['link'] !== ''): ?>
          <div class="arsip-folder"><?= ikon('tautan', 16) ?><div><span>Link folder dokumen</span><a href="<?= e($selesai['link']) ?>" target="_blank" rel="noopener noreferrer"><?= e($selesai['link']) ?></a></div></div>
        <?php endif; ?>
        <h2>Dokumen di dalam folder <small><?= count($dokS) ?> dari <?= $totalJenis ?></small></h2>
        <ul class="arsip-daftar">
          <?php foreach (JAD_ARSIP_JENIS as $k => $j): $ada = in_array($k, $dokS, true); ?>
            <li class="<?= $ada ? 'ada' : '' ?>"><span><?= ikon($ada ? 'centang' : 'silang', 14) ?></span><b><?= e($j[0]) ?></b><em><?= $ada ? 'Ada' : 'Belum ada' ?></em></li>
          <?php endforeach; ?>
        </ul>
        <div class="publik-kaki-aksi">
          <a class="btn btn-garis" href="<?= e(base_url('index.php?h=arsip_dosen&nomor=' . rawurlencode((string) ($selesai['nidn'] ?: $selesai['nuptk'])))) ?>"><?= ikon('sunting', 15) ?> Perbarui kiriman</a>
        </div>
      </section>
    <?php elseif (!$dosen): ?>
      <section class="publik-kartu">
        <h1>Arsip Dokumen Dosen</h1>
        <p class="sub">Bidang Kepegawaian &amp; BKD <?= APP_UNIT_PENDEK ?>. Masukkan NIDN atau NUPTK Anda untuk mengumpulkan link folder dokumen.</p>
        <?php if ($token !== ''): ?>
          <div class="notif notif-warn"><?= ikon('seru', 17) ?><div>Tautan kiriman tidak ditemukan. Silakan periksa kembali tautan yang Anda simpan.</div></div>
        <?php endif; ?>
        <?php if ($nomor !== ''): ?>
          <div class="notif notif-galat"><?= ikon('silang', 17) ?><div>NIDN / NUPTK <b><?= e($nomor) ?></b> tidak ditemukan pada data dosen. Periksa kembali atau hubungi Bidang Kepegawaian.</div></div>
        <?php endif; ?>
        <form method="get" action="<?= e(base_url('index.php')) ?>">
          <input type="hidden" name="h" value="arsip_dosen">
          <div class="form-baris"><label class="fl" for="aNomor">NIDN / NUPTK <span class="wajib">*</span></label>
            <input type="text" id="aNomor" name="nomor" inputmode="numeric" maxlength="30" required value="<?= e($nomor) ?>" placeholder="Contoh: 1025048304"></div>
          <button class="btn btn-utama btn-masuk" type="submit"><?= ikon('cari', 16) ?> Lanjutkan</button>
        </form>
      </section>
    <?php else: ?>
      <section class="publik-kartu">
        <h1>Pengumpulan Dokumen Dosen</h1>
        <div class="info-aset">
          <div><span>Nama</span><b><?= e(jad_huruf_nama((string) $dosen['nama'])) ?></b></div>
          <div><span>Program studi</span><b><?= e($dosen['prodi'] ?: '-') ?></b></div>
          <div><span>NIDN / NUPTK</span><b><?= e(($dosen['nidn'] ?: '-') . ' / ' . ($dosen['nuptk'] ?: '-')) ?></b></div>
        </div>
        <p class="sub">Bukan Anda? <a href="<?= e(base_url('index.php?h=arsip_dosen')) ?>">Ganti NIDN / NUPTK</a></p>
        <?php if ($catatan && $_SERVER['REQUEST_METHOD'] !== 'POST'): ?>
          <div class="notif notif-info"><?= ikon('info', 17) ?><div>Anda sudah mengirim pada <?= e(tanggal_id(substr((string) $catatan['waktu'], 0, 10))) ?>. Data sebelumnya sudah terisi di bawah; ubah bila ada pembaruan.</div></div>
        <?php endif; ?>
        <?php if ($galat): ?>
          <div class="notif notif-galat"><?= ikon('silang', 17) ?><div><?= implode('<br>', array_map('e', $galat)) ?></div></div>
        <?php endif; ?>
        <form method="post" action="<?= e(base_url('index.php?h=arsip_dosen')) ?>" autocomplete="off">
          <?= csrf_field() ?><input type="hidden" name="nomor" value="<?= e($nomor) ?>">
          <div class="publik-jebak" aria-hidden="true"><label>Situs <input type="text" name="situs" tabindex="-1" autocomplete="off"></label></div>
          <h2>1. Dokumen yang perlu dikumpulkan</h2>
          <p class="ket">Simpan seluruh dokumen berikut dalam <b>satu folder</b> (Google Drive / OneDrive), lalu centang dokumen yang sudah ada di folder tersebut.</p>
          <div class="arsip-pilih">
            <?php $no = 0; foreach (JAD_ARSIP_JENIS as $k => $j): $no++; ?>
              <label class="arsip-item"><input type="checkbox" name="dokumen[]" value="<?= $k ?>" <?= in_array($k, $terpilih, true) ? 'checked' : '' ?>>
                <span class="arsip-no"><?= $no ?></span><span class="arsip-teks"><b><?= e($j[0]) ?></b><small><?= e($j[1]) ?></small></span></label>
            <?php endforeach; ?>
          </div>
          <h2>2. Link folder pengumpulan</h2>
          <div class="form-baris"><label class="fl" for="aLink">Link folder dokumen <span class="wajib">*</span></label>
            <input type="url" id="aLink" name="link" maxlength="1000" required value="<?= e($nilaiLink) ?>" placeholder="https://drive.google.com/drive/folders/…">
            <small class="ket">Atur akses folder menjadi <b>"Siapa saja yang memiliki link dapat melihat"</b> agar dapat dibuka oleh petugas.</small></div>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="aKetLain">Keterangan</label>
              <input type="text" id="aKetLain" name="ket_lainnya" maxlength="300" value="<?= e($nilaiKet) ?>" placeholder="Bila ada catatan tambahan"></div>
            <div class="form-baris"><label class="fl" for="aKontak">No. HP / WhatsApp / email</label>
              <input type="text" id="aKontak" name="kontak" maxlength="60" value="<?= e((string) ($isian['kontak'] ?? '')) ?>" placeholder="Untuk konfirmasi bila diperlukan"></div>
          </div>
          <button class="btn btn-utama btn-masuk" type="submit"><?= ikon('kirim', 16) ?> Kirim dokumen</button>
        </form>
      </section>
    <?php endif; ?>
    <p class="publik-catatan">&copy; <?= date('Y') ?> Bidang Kepegawaian &amp; BKD <?= APP_UNIT_PENDEK ?> · <?= APP_INST_PENDEK ?></p>
  </main>
</body>
</html>
