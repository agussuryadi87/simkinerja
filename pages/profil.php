<?php
$u = user();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'profil') {
        $email = mb_substr(post('email'), 0, 150);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('Format surel tidak sah.', 'galat');
        } else {
            update('pengguna', [
                'nama'  => mb_substr(post('nama'), 0, 150) ?: $u['nama'],
                'nip'   => mb_substr(post('nip'), 0, 40),
                'email' => $email,
            ], 'id = ?', [$u['id']]);
            flash('Profil diperbarui.');
        }
    } elseif ($op === 'foto') {
        $lamaFoto = (string) val('SELECT foto FROM pengguna WHERE id = ?', [$u['id']], '');
        if (post('hapus_foto') === '1') {
            hapus_berkas_foto($lamaFoto);
            q('UPDATE pengguna SET foto = NULL WHERE id = ?', [$u['id']]);
            catat_log('ubah_foto', 'pengguna', (int) $u['id'], 'hapus');
            flash('Foto profil dihapus.');
        } else {
            $hasil = proses_foto_unggah($_FILES['foto'] ?? [], (int) $u['id']);
            if (isset($hasil['galat'])) {
                flash(e($hasil['galat']), 'galat');
            } else {
                hapus_berkas_foto($lamaFoto);
                q('UPDATE pengguna SET foto = ? WHERE id = ?', [$hasil['file'], $u['id']]);
                catat_log('ubah_foto', 'pengguna', (int) $u['id'], '');
                flash('Foto profil diperbarui.');
            }
        }
    } elseif ($op === 'sandi') {
        $lama = post_teks('sandi_lama');
        $baru = post_teks('sandi_baru');
        $ulang = post_teks('sandi_ulang');
        $hash = (string) val('SELECT password_hash FROM pengguna WHERE id = ?', [$u['id']], '');
        if (!password_verify($lama, $hash)) {
            flash('Kata sandi lama tidak sesuai.', 'galat');
        } elseif (mb_strlen($baru) < 10 || mb_strlen($baru) > 128 || !preg_match('/[A-Za-z]/', $baru) || !preg_match('/\d/', $baru)) {
            flash('Kata sandi baru 10 sampai 128 karakter dan memuat huruf serta angka.', 'galat');
        } elseif (stripos($baru, $u['username']) !== false || stripos($baru, 'password') !== false || stripos($baru, 'kata sandi') !== false || preg_match('/^(.)\1+$/u', $baru)) {
            flash('Kata sandi terlalu mudah ditebak. Jangan memuat nama pengguna atau kata umum.', 'galat');
        } elseif ($baru !== $ulang) {
            flash('Konfirmasi kata sandi baru tidak sama.', 'galat');
        } elseif (sandi_bentrok((int) $u['id'], $u['username'], $baru)) {
            flash('Kata sandi ini tidak dapat dipakai. Silakan pilih kata sandi yang lain.', 'galat');
        } elseif ($baru === DEFAULT_PASSWORD || $baru === $lama) {
            flash('Gunakan kata sandi yang berbeda dari kata sandi sementara atau sandi lama.', 'galat');
        } else {
            q('UPDATE pengguna SET password_hash = ?, sandi_bawaan = 0 WHERE id = ?', [password_hash($baru, PASSWORD_DEFAULT), $u['id']]);
            catat_log('ganti_sandi', 'pengguna', (int) $u['id'], '');
            session_regenerate_id(true);
            flash('Kata sandi berhasil diganti.');
        }
    }
    kembali_ke('profil');
}

$izin = izin_saya();
$riwayat = rows('SELECT aksi, keterangan, waktu FROM log_aktivitas WHERE pengguna_id = ? ORDER BY id DESC LIMIT 5', [$u['id']]);
$fotoSaya = (string) val('SELECT foto FROM pengguna WHERE id = ?', [$u['id']], '');
?>

<?php if ((int) $u['sandi_bawaan'] === 1): ?>
  <div class="notif notif-warn"><?= ikon('seru', 17) ?><div>Akun Anda masih memakai <b>kata sandi sementara</b>. Ganti kata sandi di bawah untuk dapat memakai sistem.</div></div>
<?php endif; ?>

<div class="kartu profil-ringkas">
  <div class="profil-foto">
    <?= avatar($u['nama'], 'profil-besar') ?>
    <form method="post" enctype="multipart/form-data" id="formFotoProfil">
      <?= csrf_field() ?><input type="hidden" name="op" value="foto">
      <label class="btn btn-garis btn-kecil"><input type="file" id="fotoInput" name="foto" accept="image/jpeg,image/png,image/webp" hidden><?= ikon('kamera', 14) ?> <?= foto_nama_sah($fotoSaya) ? 'Ganti foto' : 'Unggah foto' ?></label>
      <?php if (foto_nama_sah($fotoSaya)): ?>
        <button class="btn btn-hantu btn-kecil" type="submit" name="hapus_foto" value="1" formnovalidate data-konfirmasi="Hapus foto profil dan kembali ke inisial?"><?= ikon('hapus', 14) ?> Hapus</button>
      <?php endif; ?>
    </form>
    <small class="lemah">JPG, PNG, atau WEBP</small>
  </div>
  <form method="post" class="profil-data">
    <?= csrf_field() ?><input type="hidden" name="op" value="profil">
    <div class="profil-judul"><b><?= e($u['nama']) ?></b><span><?= e($u['jabatan'] ?: $u['peran_nama']) ?></span></div>
    <div class="form-grid-3">
      <div class="form-baris"><label class="fl" for="pNama">Nama lengkap</label><input type="text" id="pNama" name="nama" maxlength="150" value="<?= e($u['nama']) ?>"></div>
      <div class="form-baris"><label class="fl" for="pNip">NIP / NIK</label><input type="text" id="pNip" name="nip" maxlength="40" value="<?= e($u['nip']) ?>"></div>
      <div class="form-baris"><label class="fl" for="pEmail">Surel</label><input type="email" id="pEmail" name="email" maxlength="150" value="<?= e($u['email']) ?>"></div>
    </div>
    <div class="profil-rinci">
      <span>Nama pengguna <b><code><?= e($u['username']) ?></code></b></span>
      <span>Unit <b><?= e($u['unit_nama'] ?? '-') ?></b></span>
      <span>Peran <b><?= e($u['peran_nama']) ?></b></span>
      <span>Atasan <b><?= e($u['atasan_nama'] ?? '-') ?></b></span>
      <button class="btn btn-utama btn-kecil" type="submit"><?= ikon('simpan', 14) ?> Simpan profil</button>
    </div>
    <div class="profil-izin">
      <span class="lemah kecil"><?= ikon('perisai', 14) ?> Hak akses:</span>
      <?php foreach (DAFTAR_IZIN as $grup): foreach ($grup as $kode => $def): if (in_array($kode, $izin, true) && empty($def[2])): ?>
        <span class="chip-izin"><?= e($def[0]) ?></span>
      <?php endif; endforeach; endforeach; ?>
      <?php if (!$izin): ?><span class="lemah kecil">Belum ada hak akses khusus.</span><?php endif; ?>
    </div>
  </form>
</div>

<div class="grid grid-2 profil-bawah">
  <form method="post" class="kartu" autocomplete="off">
    <?= csrf_field() ?><input type="hidden" name="op" value="sandi">
    <div class="kartu-kepala"><h2><?= ikon('gembok', 17) ?> Ganti kata sandi</h2></div>
    <div class="kartu-isi">
      <div class="form-grid-3">
        <div class="form-baris"><label class="fl" for="sLama">Kata sandi lama</label><input type="password" id="sLama" name="sandi_lama" required autocomplete="current-password"></div>
        <div class="form-baris"><label class="fl" for="sBaru">Kata sandi baru</label><input type="password" id="sBaru" name="sandi_baru" required minlength="10" maxlength="128" autocomplete="new-password"></div>
        <div class="form-baris"><label class="fl" for="sUlang">Ulangi sandi baru</label><input type="password" id="sUlang" name="sandi_ulang" required minlength="10" maxlength="128" autocomplete="new-password"></div>
      </div>
      <div class="profil-sandi-kaki"><p class="bantuan">Minimal 10 karakter, memuat huruf dan angka.</p>
        <button class="btn btn-utama btn-kecil" type="submit"><?= ikon('kunci', 14) ?> Ganti kata sandi</button></div>
    </div>
  </form>

  <div class="kartu">
    <div class="kartu-kepala"><h2><?= ikon('jam', 17) ?> Aktivitas terakhir</h2></div>
    <ul class="profil-aktivitas">
      <?php foreach ($riwayat as $r): ?>
        <li><b><?= e(ucfirst(str_replace('_', ' ', $r['aksi']))) ?></b><?php if ($r['keterangan'] !== ''): ?><span><?= e(mb_strimwidth($r['keterangan'], 0, 60, '…')) ?></span><?php endif; ?><time><?= e(waktu_relatif($r['waktu'])) ?></time></li>
      <?php endforeach; ?>
      <?php if (!$riwayat): ?><li class="lemah">Belum ada aktivitas.</li><?php endif; ?>
    </ul>
  </div>
</div>

<script nonce="<?= csp_nonce() ?>">
  (function () {
    var inp = document.getElementById('fotoInput'), form = document.getElementById('formFotoProfil');
    if (!inp || !form) return;
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f || !/^image\//.test(f.type)) return;
      var pembaca = new FileReader(), img = new Image();
      img.onload = function () {
        var s = Math.min(img.width, img.height), c = document.createElement('canvas');
        c.width = c.height = 480;
        c.getContext('2d').drawImage(img, (img.width - s) / 2, (img.height - s) / 2, s, s, 0, 0, 480, 480);
        c.toBlob(function (b) {
          if (b) {
            try {
              var dt = new DataTransfer();
              dt.items.add(new File([b], 'foto.jpg', { type: 'image/jpeg' }));
              inp.files = dt.files;
            } catch (e) {}
          }
          form.submit();
        }, 'image/jpeg', 0.9);
      };
      img.onerror = function () { form.submit(); };
      pembaca.onload = function () { img.src = pembaca.result; };
      pembaca.readAsDataURL(f);
    });
  })();
</script>