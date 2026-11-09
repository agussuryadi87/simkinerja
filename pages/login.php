<?php

$galat = '';
$lupa  = isset($_GET['lupa']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $username = post('username');
    $password = post_teks('password');

    if ($username === '' || $password === '') {
        $galat = 'Nama pengguna dan kata sandi wajib diisi.';
    } else {
        switch (login($username, $password)) {
            case 'ok':
                ingat_saya(!empty($_POST['ingat']));
                $tujuan = $_SESSION['tujuan'] ?? base_url('index.php');
                unset($_SESSION['tujuan']);
                redirect($tujuan);
                break;
            case 'ambigu':
                $galat = 'Kata sandi ini juga dipakai akun lain dengan nama pengguna yang sama, sehingga akun Anda tidak dapat dipastikan. Hubungi administrator untuk menyetel ulang kata sandi.';
                break;
            case 'terhambat':
                $sisa = (int) ceil((jeda_percobaan($username) ?? (JEDA_MASUK_MENIT * 60)) / 60);
                $galat = 'Terlalu banyak percobaan gagal. Coba lagi dalam ' . max(1, $sisa) . ' menit.';
                break;
            default:
                $galat = 'Nama pengguna atau kata sandi tidak sesuai.';
                usleep(400000);
        }
    }
}

$css = base_url('assets/css/app.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/app.css') ? filemtime(APP_ROOT . '/assets/css/app.css') : APP_VER);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk · <?= APP_NAME ?> <?= APP_UNIT_PENDEK ?></title>
<link rel="icon" href="<?= e(base_url('assets/img/favicon.png')) ?>">
<link rel="stylesheet" href="<?= e($css) ?>">
<link rel="stylesheet" href="<?= e(base_url('assets/css/tema-kantor.css') . '?v=' . (is_file(APP_ROOT . '/assets/css/tema-kantor.css') ? filemtime(APP_ROOT . '/assets/css/tema-kantor.css') : APP_VER)) ?>">
<script nonce="<?= csp_nonce() ?>">
  (function () { var t = 'kantor'; try { t = localStorage.getItem('simkin.tema') || 'kantor'; } catch (e) {} if (t !== 'hijau') document.documentElement.classList.add('tema-kantor'); })();
</script>
</head>
<body class="masuk" style="--foto:url('<?= e(base_url('assets/img/tim-bauk.jpg') . '?v=' . (is_file(APP_ROOT . '/assets/img/tim-bauk.jpg') ? filemtime(APP_ROOT . '/assets/img/tim-bauk.jpg') : APP_VER)) ?>')">

  <div class="masuk-cahaya" aria-hidden="true"></div>
  <div class="sambut-latar masuk-hias" aria-hidden="true">
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

  <header class="masuk-merek">
    <img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="Logo <?= APP_INST ?>">
    <div>
      <b><?= APP_INST ?></b>
      <span><?= APP_UNIT ?></span>
    </div>
  </header>

  <main class="masuk-tengah">
    <div class="masuk-kartu">
      <h2><?= APP_NAME ?> <span><?= APP_UNIT_PENDEK ?></span></h2>
      <p class="sub"><?= APP_LONG ?> <?= APP_UNIT ?></p>

      <?php if ($galat): ?>
        <div class="notif notif-galat"><?= ikon('silang', 17) ?><div><?= e($galat) ?></div></div>
      <?php endif; ?>

      <?php if ($lupa): ?>
        <div class="notif notif-info"><?= ikon('info', 17) ?>
          <div>Kata sandi hanya dapat disetel ulang oleh administrator. Hubungi Bidang Perencanaan dan
            Pengembangan <?= APP_UNIT_PENDEK ?> dengan menyebutkan nama pengguna Anda.</div>
        </div>
      <?php endif; ?>

      <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <div class="form-baris">
          <label class="fl" for="username">Nama Pengguna</label>
          <div class="medan">
            <input type="text" id="username" name="username" required autofocus
                   value="<?= e(post('username')) ?>" placeholder="Masukkan nama pengguna">
            <span class="ikon-bungkus"><?= ikon('profil', 18) ?></span>
          </div>
        </div>

        <div class="form-baris">
          <label class="fl" for="password">Kata Sandi</label>
          <div class="medan">
            <input type="password" id="password" name="password" required placeholder="Masukkan kata sandi">
            <span class="ikon-bungkus"><?= ikon('gembok', 18) ?></span>
            <button type="button" class="mata" data-tampil-sandi="password" aria-label="Tampilkan kata sandi">
              <span class="buka"><?= ikon('mata', 17) ?></span>
              <span class="tutup"><?= ikon('mata-tutup', 17) ?></span>
            </button>
          </div>
        </div>

        <div class="baris-bantu">
          <label for="ingat"><input type="checkbox" id="ingat" name="ingat" value="1"> Ingat saya</label>
          <a href="<?= e(base_url('index.php?h=login&lupa=1')) ?>">Lupa kata sandi?</a>
        </div>

        <button class="btn btn-utama btn-masuk" type="submit"><?= ikon('masuk', 17) ?> Masuk</button>
      </form>

      <p class="catatan-akses"><?= ikon('perisai', 16) ?>
        Akses khusus Kepala Biro, Kepala Bidang, dan Staf <?= APP_UNIT ?>.</p>
    </div>
    <p class="masuk-kaki">&copy; <?= date('Y') ?> Bidang Perencanaan dan Pengembangan <?= APP_UNIT_PENDEK ?> · <?= APP_INST_PENDEK ?></p>
  </main>

<script src="<?= e(base_url('assets/js/app.js')) ?>"></script>
</body>
</html>
