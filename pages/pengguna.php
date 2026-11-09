<?php
$saya = uid();

$buatKartu = static function (int $id, string $sandi): ?array {
    $p = pegawai_get($id);
    if (!$p) {
        return null;
    }
    return ['nama' => $p['nama'], 'username' => $p['username'], 'jabatan' => $p['jabatan'] ?: $p['peran_nama'],
            'unit' => $p['unit_nama'] ?? '', 'sandi' => $sandi];
};
$simpanKartu = static function (array $daftar): void {
    $_SESSION['kartu_akun'] = ['waktu' => time(), 'daftar' => array_values(array_filter($daftar))];
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $idFokus = 0;
    $balik = ['cari' => post('f_cari') ?: null, 'unit' => post('f_unit') ?: null, 'peran' => post('f_peran') ?: null];

    if ($op === 'simpan') {
        $id = (int) post('id');
        $username = rapikan_username(post('username'));
        $data = [
            'username'  => $username,
            'nama'      => mb_substr(post('nama'), 0, 150),
            'nip'       => mb_substr(post('nip'), 0, 40),
            'jabatan'   => mb_substr(post('jabatan'), 0, 150),
            'email'     => mb_substr(post('email'), 0, 150),
            'unit_id'   => (int) post('unit_id') ?: null,
            'peran_id'  => (int) post('peran_id'),
            'atasan_id' => (int) post('atasan_id') ?: null,
            'aktif'     => post('aktif') === '1' ? 1 : 0,
        ];
        $galat = [];
        if (mb_strlen($username) < 3 || mb_strlen($username) > 60 || preg_match('/[\x00-\x1f\x7f]/', $username)) {
            $galat[] = 'Nama pengguna harus 3-60 karakter (bebas ditentukan, boleh memakai spasi dan simbol).';
        }
        if ($data['nama'] === '') {
            $galat[] = 'Nama lengkap wajib diisi.';
        }
        if (!val('SELECT id FROM peran WHERE id = ?', [$data['peran_id']])) {
            $galat[] = 'Peran tidak sah.';
        }
        if ($data['email'] !== '' && !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $galat[] = 'Format surel tidak sah.';
        }
        $lamaUser = $id ? row('SELECT * FROM pengguna WHERE id = ?', [$id]) : null;
        if ($id && !$lamaUser) {
            $galat[] = 'Pengguna tidak ditemukan.';
        } elseif ($id && $id !== $saya && !boleh_kelola_akun($id)) {
            $galat[] = 'Anda tidak berwenang mengubah akun dengan hak akses melebihi hak akses Anda.';
        } elseif ($id && $id !== $saya && akun_terkunci($id)) {
            $galat[] = 'Akun administrator hanya dapat diubah oleh pemiliknya sendiri.';
        } else {
            if ($id && akun_terkunci($id)) {
                $username = $data['username'] = $lamaUser['username'];
            }
            $peranBerubah = !$lamaUser || (int) $lamaUser['peran_id'] !== $data['peran_id'];
            if ($peranBerubah && !peran_boleh_diberikan($data['peran_id'])) {
                $galat[] = 'Anda tidak berwenang memberikan peran dengan hak akses melebihi hak akses Anda.';
            }
            if ($peranBerubah && $id === $saya) {
                $galat[] = 'Anda tidak dapat mengubah peran akun sendiri.';
            }
            if ($lamaUser && (int) $lamaUser['aktif'] === 1 && peran_punya_izin((int) $lamaUser['peran_id'], 'pengguna_kelola')
                && (!$data['aktif'] || !peran_punya_izin($data['peran_id'], 'pengguna_kelola'))
                && !admin_lain_tersisa($id)) {
                $galat[] = 'Harus tersisa minimal satu akun aktif yang dapat mengelola pengguna.';
            }
        }
        if ($data['atasan_id'] !== null) {
            if ($id && $data['atasan_id'] === $id) {
                $galat[] = 'Pegawai tidak dapat menjadi atasan dirinya sendiri.';
            } elseif ($id) {
                $cek = $data['atasan_id'];
                $langkah = 0;
                while ($cek && $langkah++ < 50) {
                    if ($cek === $id) {
                        $galat[] = 'Susunan atasan membentuk lingkaran.';
                        break;
                    }
                    $cek = (int) val('SELECT atasan_id FROM pengguna WHERE id = ?', [$cek], 0);
                }
            }
        }
        if ($id === $saya && !$data['aktif']) {
            $galat[] = 'Anda tidak dapat menonaktifkan akun sendiri.';
        }
        if ($id && !$data['aktif']) {
            $jmlBawahan = (int) val('SELECT COUNT(*) FROM pengguna WHERE atasan_id = ? AND aktif = 1', [$id], 0);
            if ($jmlBawahan > 0) {
                $galat[] = 'Akun ini masih menjadi atasan langsung ' . $jmlBawahan . ' pegawai aktif. Pindahkan atasan mereka terlebih dahulu.';
            }
        }
        if ($galat) {
            $_SESSION['isian_pengguna'] = [
                'galat' => $galat,
                'isi'   => ['id' => $id ?: '', 'username' => post('username'), 'nama' => post('nama'), 'nip' => post('nip'),
                            'jabatan' => post('jabatan'), 'email' => post('email'), 'unit_id' => post('unit_id'),
                            'peran_id' => post('peran_id'), 'atasan_id' => post('atasan_id'), 'aktif' => post('aktif') === '1' ? '1' : '0'],
            ];
            redirect(url('pengguna', $balik));
        }
        if ($id) {
            $namaDari = static function (string $tabel, $x): string {
                return $x ? (string) val("SELECT nama FROM $tabel WHERE id = ?", [(int) $x], '-') : '-';
            };
            $pasang = [
                'Nama'          => [$lamaUser['nama'], $data['nama']],
                'Nama pengguna' => [$lamaUser['username'], $data['username']],
                'Jabatan'       => [$lamaUser['jabatan'], $data['jabatan']],
                'Unit'          => [$namaDari('unit', $lamaUser['unit_id']), $namaDari('unit', $data['unit_id'])],
                'Peran'         => [$namaDari('peran', $lamaUser['peran_id']), $namaDari('peran', $data['peran_id'])],
                'Atasan'        => [$namaDari('pengguna', $lamaUser['atasan_id']), $namaDari('pengguna', $data['atasan_id'])],
                'Status'        => [$lamaUser['aktif'] ? 'Aktif' : 'Nonaktif', $data['aktif'] ? 'Aktif' : 'Nonaktif'],
            ];
            $selisih = [];
            foreach ($pasang as $label => [$dari, $ke]) {
                if ((string) $dari !== (string) $ke) {
                    $selisih[] = e($label) . ': ' . e($dari !== '' ? $dari : '-') . ' → <b>' . e($ke !== '' ? $ke : '-') . '</b>';
                }
            }
            update('pengguna', $data, 'id = ?', [$id]);
            catat_log('ubah_pengguna', 'pengguna', $id, $username);
            $idFokus = $id;
            flash('Data pengguna <b>' . e($data['nama']) . '</b> disimpan.' . ($selisih ? '<br>' . implode('<br>', $selisih) : ' Tidak ada yang berubah.'));
        } else {
            $sementara = sandi_acak();
            $data['password_hash'] = password_hash($sementara, PASSWORD_DEFAULT);
            $data['sandi_bawaan'] = 1;
            $baru = insert('pengguna', $data);
            $idFokus = $baru;
            catat_log('tambah_pengguna', 'pengguna', $baru, $username);
            $simpanKartu([$buatKartu($baru, $sementara)]);
            flash('Pengguna <b>' . e($data['nama']) . '</b> ditambahkan. Kata sandi sementara (hanya ditampilkan sekali): <code>' . e($sementara) . '</code>. Pengguna wajib menggantinya saat masuk pertama kali. '
                . '<a href="' . e(url('pengguna', ['kartu' => 1])) . '"><b>Cetak kartu akun</b></a>');
        }
        $kembar = (int) val('SELECT COUNT(*) FROM pengguna WHERE username = ? AND id <> ?', [$username, $idFokus], 0);
        if ($kembar > 0) {
            flash('Nama pengguna <b>' . e($username) . '</b> juga dipakai ' . $kembar . ' akun lain. Saat masuk, sistem mencocokkan nama pengguna lalu kata sandi, jadi pastikan kata sandi tiap akun berbeda.', 'info');
        }
    } elseif ($op === 'foto') {
        $id = (int) post('id');
        $target = row('SELECT id, nama, foto FROM pengguna WHERE id = ?', [$id]);
        if (!$target) {
            flash('Pengguna tidak ditemukan.', 'galat');
        } elseif ($id !== $saya && !boleh_kelola_akun($id)) {
            flash('Anda tidak berwenang mengubah akun dengan hak akses melebihi hak akses Anda.', 'galat');
        } elseif (post('hapus_foto') === '1') {
            hapus_berkas_foto($target['foto']);
            q('UPDATE pengguna SET foto = NULL WHERE id = ?', [$id]);
            catat_log('hapus_foto', 'pengguna', $id, '');
            $idFokus = $id;
            flash('Foto <b>' . e($target['nama']) . '</b> dihapus.');
        } else {
            $hasil = proses_foto_unggah($_FILES['foto'] ?? [], $id);
            if (isset($hasil['galat'])) {
                flash(e($hasil['galat']), 'galat');
            } else {
                hapus_berkas_foto($target['foto']);
                q('UPDATE pengguna SET foto = ? WHERE id = ?', [$hasil['file'], $id]);
                catat_log('ubah_foto', 'pengguna', $id, '');
                $idFokus = $id;
                flash('Foto <b>' . e($target['nama']) . '</b> diperbarui.');
            }
        }
    } elseif ($op === 'reset') {
        $id = (int) post('id');
        if (!val('SELECT id FROM pengguna WHERE id = ?', [$id])) {
            flash('Pengguna tidak ditemukan.', 'galat');
        } elseif ($id === $saya || !boleh_kelola_akun($id) || akun_terkunci($id)) {
            catat_log('tolak_reset_sandi', 'pengguna', $id, '');
            flash('Anda tidak berwenang menyetel ulang kata sandi akun ini.', 'galat');
        } else {
            $sementara = sandi_acak();
            q('UPDATE pengguna SET password_hash = ?, sandi_bawaan = 1 WHERE id = ?', [password_hash($sementara, PASSWORD_DEFAULT), $id]);
            catat_log('reset_sandi', 'pengguna', $id, '');
            $simpanKartu([$buatKartu($id, $sementara)]);
            flash('Kata sandi disetel ulang. Kata sandi sementara (hanya ditampilkan sekali): <code>' . e($sementara) . '</code>. Pengguna wajib menggantinya saat masuk. '
                . '<a href="' . e(url('pengguna', ['kartu' => 1])) . '"><b>Cetak kartu akun</b></a>');
        }
    } elseif ($op === 'cetak_akun') {
        $ids = post('satu') !== '' ? [(int) post('satu')] : array_map('intval', (array) ($_POST['ids'] ?? []));
        $ids = array_values(array_unique(array_filter($ids, static function ($i) use ($saya) { return $i > 0 && $i !== $saya; })));
        $kartu = [];
        foreach (array_slice($ids, 0, 100) as $id) {
            if (!val('SELECT id FROM pengguna WHERE id = ?', [$id]) || !boleh_kelola_akun($id) || akun_terkunci($id)) {
                continue;
            }
            $sementara = sandi_acak();
            q('UPDATE pengguna SET password_hash = ?, sandi_bawaan = 1 WHERE id = ?', [password_hash($sementara, PASSWORD_DEFAULT), $id]);
            catat_log('cetak_akun', 'pengguna', $id, '');
            $kartu[] = $buatKartu($id, $sementara);
        }
        if (!$kartu) {
            flash('Pilih minimal satu pengguna lain. Akun Anda sendiri tidak ikut disetel ulang.', 'warn');
        } else {
            $simpanKartu($kartu);
            kembali_ke('pengguna', ['kartu' => 1]);
        }
    } elseif ($op === 'hapus') {
        $id = (int) post('id');
        $targetAktif = row('SELECT peran_id, aktif FROM pengguna WHERE id = ?', [$id]);
        if ($id === $saya) {
            flash('Anda tidak dapat menghapus akun sendiri.', 'galat');
        } elseif ($targetAktif && (!boleh_kelola_akun($id) || akun_terkunci($id))) {
            flash('Anda tidak berwenang menghapus akun dengan hak akses melebihi hak akses Anda.', 'galat');
        } elseif ($targetAktif && (int) $targetAktif['aktif'] === 1 && peran_punya_izin((int) $targetAktif['peran_id'], 'pengguna_kelola') && !admin_lain_tersisa($id)) {
            flash('Harus tersisa minimal satu akun aktif yang dapat mengelola pengguna.', 'galat');
        } elseif ((int) val('SELECT COUNT(*) FROM pengguna WHERE atasan_id = ?', [$id], 0) > 0) {
            flash('Pengguna ini masih menjadi atasan langsung pegawai lain. Pindahkan atasan mereka terlebih dahulu.', 'warn');
        } elseif ((int) val('SELECT (SELECT COUNT(*) FROM kegiatan WHERE pengguna_id = ?)
                                  + (SELECT COUNT(*) FROM evaluasi WHERE pegawai_id = ?) + (SELECT COUNT(*) FROM umpan_balik WHERE pegawai_id = ? OR pemberi_id = ?)
                                  + (SELECT COUNT(*) FROM pengembangan WHERE pegawai_id = ?)', [$id, $id, $id, $id, $id], 0) > 0) {
            flash('Pengguna ini sudah memiliki data kinerja, evaluasi, atau umpan balik. Nonaktifkan saja agar riwayatnya tetap tersimpan.', 'warn');
        } else {
            q('DELETE FROM pengguna WHERE id = ?', [$id]);
            catat_log('hapus_pengguna', 'pengguna', $id, '');
            flash('Pengguna dihapus.');
        }
    }
    redirect(url('pengguna', $balik) . ($idFokus ? '#u' . $idFokus : ''));
}

if (isset($_GET['kartu'])) {
    $simpanan = $_SESSION['kartu_akun'] ?? null;
    $selesai = ($_GET['kartu'] ?? '') === 'selesai';
    if ($selesai || !$simpanan || time() - (int) $simpanan['waktu'] > 900) {
        unset($_SESSION['kartu_akun']);
        if (!$selesai) {
            flash('Kartu akun sudah tidak tersedia. Setel ulang kata sandi lagi bila perlu mencetak ulang.', 'warn');
        }
        kembali_ke('pengguna');
    }
    $subjudul = 'Kartu akun';
    $alamatMasuk = (pakai_https() ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . base_url('index.php');
    ?>
    <div class="notif notif-warn no-cetak"><?= ikon('seru', 17) ?>
      <div><b>Kata sandi hanya tampil di halaman ini.</b> Setelah Anda menekan Selesai atau meninggalkan halaman ini (maksimal 15 menit), sandi tidak dapat ditampilkan lagi.
        Cetak sekarang dan serahkan kartu hanya kepada pemilik akun.
        <div style="margin-top:10px;display:flex;gap:8px;flex-wrap:wrap">
          <button class="btn btn-utama btn-kecil" type="button" data-cetak><?= ikon('cetak', 15) ?> Cetak kartu akun</button>
          <a class="btn btn-garis btn-kecil" href="<?= e(url('pengguna', ['kartu' => 'selesai'])) ?>">Selesai</a>
        </div></div></div>
    <div class="kartu-akun-grid">
      <?php foreach ($simpanan['daftar'] as $k): ?>
        <div class="kartu-akun">
          <div class="ka-kepala"><img src="<?= e(base_url('assets/img/logo-uis.png')) ?>" alt="">
            <div><b><?= e(APP_NAME) ?> <?= e(APP_UNIT_PENDEK) ?></b><span><?= e(APP_INST) ?></span></div></div>
          <div class="ka-nama"><b><?= e($k['nama']) ?></b><span><?= e($k['jabatan']) ?><?= $k['unit'] ? ' · ' . e($k['unit']) : '' ?></span></div>
          <dl class="rinci">
            <dt>Alamat</dt><dd><?= e($alamatMasuk) ?></dd>
            <dt>Nama pengguna</dt><dd><code><?= e($k['username']) ?></code></dd>
            <dt>Kata sandi</dt><dd><code class="ka-sandi"><?= e($k['sandi']) ?></code></dd>
          </dl>
          <p class="ka-catatan">Kata sandi ini bersifat sementara. Anda wajib menggantinya saat masuk pertama kali. Jangan bagikan kartu ini kepada orang lain.</p>
        </div>
      <?php endforeach; ?>
    </div>
    <?php
    return;
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fUnit = (int) ($_GET['unit'] ?? 0);
$fPeran = (int) ($_GET['peran'] ?? 0);
$syarat = '1=1';
$param = [];
if ($cari !== '') {
    $syarat .= ' AND (p.nama LIKE ? OR p.username LIKE ? OR p.jabatan LIKE ? OR p.nip LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($param, $like, $like, $like, $like);
}
if ($fUnit) {
    $syarat .= ' AND p.unit_id = ?';
    $param[] = $fUnit;
}
if ($fPeran) {
    $syarat .= ' AND p.peran_id = ?';
    $param[] = $fPeran;
}
$daftar = rows(
    "SELECT p.*, un.nama AS unit_nama, pr.nama AS peran_nama, at.nama AS atasan_nama
       FROM pengguna p JOIN peran pr ON pr.id = p.peran_id
       LEFT JOIN unit un ON un.id = p.unit_id LEFT JOIN pengguna at ON at.id = p.atasan_id
      WHERE $syarat ORDER BY p.aktif DESC, un.id IS NULL, un.urut, un.nama, pr.urut, p.nama",
    $param
);
$peran = rows('SELECT * FROM peran ORDER BY urut, nama');
$semua = rows('SELECT p.id, p.nama, p.jabatan FROM pengguna p WHERE p.aktif = 1 ORDER BY p.nama');
$jmlAktif = count(array_filter($daftar, static function ($r) { return (int) $r['aktif'] === 1; }));
$isianGalat = $_SESSION['isian_pengguna'] ?? null;
unset($_SESSION['isian_pengguna']);
?>

<form method="get" class="saring">
  <input type="hidden" name="h" value="pengguna">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label>
    <input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nama, nama pengguna, jabatan, NIP"></div>
  <div class="form-baris"><label class="fl">Unit</label><select name="unit" data-auto-kirim><?= opsi_unit($fUnit, 'Semua unit') ?></select></div>
  <div class="form-baris"><label class="fl">Peran</label><select name="peran" data-auto-kirim><option value="">Semua peran</option>
    <?php foreach ($peran as $r): ?><option value="<?= (int) $r['id'] ?>" <?= $fPeran === (int) $r['id'] ? 'selected' : '' ?>><?= e($r['nama']) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<form id="formCetakAkun" method="post" hidden><?= csrf_field() ?><input type="hidden" name="op" value="cetak_akun"></form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('pengguna', 18) ?> Daftar pengguna</h2><p class="ket"><?= count($daftar) ?> akun · <?= $jmlAktif ?> aktif</p></div>
    <div class="aksi"><button class="btn btn-garis btn-kecil" type="submit" form="formCetakAkun" data-butuh="akun" disabled data-gaya="utama"
        data-konfirmasi="Cetak kartu akun untuk pengguna terpilih? Kata sandi masing-masing akan disetel ulang ke sandi sementara baru, dan sandi lama tidak berlaku lagi."><?= ikon('cetak', 15) ?> <span>Cetak akun terpilih (<span data-hitung="akun">0</span>)</span></button>
      <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPengguna" data-reset data-judul="Tambah pengguna"
        data-isi='{"id":"","aktif":"1","peran_id":"4"}'><?= ikon('tambah', 15) ?> Tambah pengguna</button></div>
  </div>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th style="width:36px"><input type="checkbox" data-pilih-semua="akun" aria-label="Pilih semua"></th><th>Pegawai</th><th>Nama pengguna</th><th>Peran</th><th>Atasan langsung</th><th>Masuk terakhir</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php
      $jmlPerUnit = array_count_values(array_map(static function ($r) { return (string) ($r['unit_id'] ?? 0); }, $daftar));
      $kelompokKini = null;
      foreach ($daftar as $r):
        $kunciKelompok = (string) ($r['unit_id'] ?? 0) . '|' . (int) $r['aktif'];
        if ($kunciKelompok !== $kelompokKini): $kelompokKini = $kunciKelompok; ?>
      <tr class="baris-kelompok"><td><input type="checkbox" data-pilih-unit="<?= e($kunciKelompok) ?>" aria-label="Pilih semua akun di unit ini"></td><td colspan="6"><?= e($r['unit_nama'] ?? 'Tanpa unit') ?> <span class="lemah">· <?= (int) $jmlPerUnit[(string) ($r['unit_id'] ?? 0)] ?> akun</span></td></tr>
    <?php endif; ?>
      <tr id="u<?= (int) $r['id'] ?>" style="<?= $r['aktif'] ? '' : 'opacity:.55' ?>">
        <td><?php if ((int) $r['id'] !== $saya): ?><input type="checkbox" name="ids[]" value="<?= (int) $r['id'] ?>" form="formCetakAkun" data-kelompok="akun" data-unit="<?= e($kunciKelompok) ?>" aria-label="Pilih <?= e($r['nama']) ?>"><?php endif; ?></td>
        <td><div class="orang"><?= avatar($r['nama'], 'kecil') ?><div><b><?= e($r['nama']) ?></b><span><?= $r['jabatan'] ? e($r['jabatan']) : '<i>Jabatan belum diisi</i>' ?><?= $r['nip'] ? ' · ' . e($r['nip']) : '' ?></span></div></div></td>
        <td><code><?= e($r['username']) ?></code></td>
        <td><?= lencana($r['peran_nama'], 'hijau') ?><?= $r['aktif'] ? '' : ' ' . lencana('Nonaktif', 'merah') ?></td>
        <td><?= e($r['atasan_nama'] ?? '-') ?></td>
        <td class="nowrap kecil"><?= $r['login_terakhir'] ? e(waktu_relatif($r['login_terakhir'])) : '<span class="lemah">belum pernah</span>' ?></td>
        <td><div class="aksi-baris">
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah foto" aria-label="Ubah foto <?= e($r['nama']) ?>" data-buka="tiraiFoto" data-reset
                  data-isi='<?= e(json_encode(['id' => (int) $r['id'], 'nama_teks' => $r['nama']])) ?>'><?= ikon('kamera', 15) ?></button>
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" aria-label="Ubah <?= e($r['nama']) ?>" data-buka="tiraiPengguna" data-judul="Ubah pengguna"
                  data-isi='<?= e(json_encode(['id' => (int) $r['id'], 'username' => $r['username'], 'nama' => $r['nama'], 'nip' => $r['nip'],
                      'jabatan' => $r['jabatan'], 'email' => $r['email'], 'unit_id' => $r['unit_id'] ?? '', 'peran_id' => $r['peran_id'],
                      'atasan_id' => $r['atasan_id'] ?? '', 'aktif' => (string) $r['aktif']])) ?>'><?= ikon('sunting', 15) ?></button>
          <?php if (can('peran_kelola') && (int) $r['id'] !== $saya && !akun_terkunci((int) $r['id'])): ?>
          <a class="btn btn-hantu btn-ikon" href="<?= e(url('peran', ['tab' => 'pegawai', 'p' => (int) $r['id']])) ?>" title="Hak akses pegawai" aria-label="Hak akses <?= e($r['nama']) ?>"><?= ikon('perisai', 15) ?></a>
          <?php endif; ?>
          <?php if ((int) $r['id'] !== $saya): ?>
          <button class="btn btn-hantu btn-ikon" type="submit" form="formCetakAkun" name="satu" value="<?= (int) $r['id'] ?>" title="Cetak kartu akun" aria-label="Cetak kartu akun <?= e($r['nama']) ?>" data-gaya="utama"
                  data-konfirmasi="Cetak kartu akun <?= e($r['nama']) ?>? Kata sandinya akan disetel ulang ke sandi sementara baru."><?= ikon('cetak', 15) ?></button>
          <?php endif; ?>
          <form method="post" data-konfirmasi="Setel ulang kata sandi <?= e($r['nama']) ?> ke sandi sementara baru?" data-gaya="utama">
            <?= csrf_field() ?><input type="hidden" name="op" value="reset"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Setel ulang kata sandi" aria-label="Setel ulang kata sandi <?= e($r['nama']) ?>"><?= ikon('kunci', 15) ?></button></form>
          <?php if ((int) $r['id'] !== $saya): ?>
          <form method="post" data-konfirmasi="Hapus akun <?= e($r['nama']) ?>?">
            <?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus" aria-label="Hapus <?= e($r['nama']) ?>"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<?php if ($isianGalat): ?>
<button type="button" id="bukaOtomatis" hidden data-buka="tiraiPengguna" data-judul="<?= $isianGalat['isi']['id'] ? 'Ubah pengguna' : 'Tambah pengguna' ?>"
        data-isi='<?= e(json_encode($isianGalat['isi'])) ?>'></button>
<script nonce="<?= csp_nonce() ?>">window.addEventListener('load', function () { document.getElementById('bukaOtomatis').click(); });</script>
<?php endif; ?>

<div class="tirai" id="tiraiFoto" hidden>
  <div class="modal sempit">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="foto"><input type="hidden" name="id" value="">
      <input type="hidden" name="f_cari" value="<?= e($cari) ?>"><input type="hidden" name="f_unit" value="<?= $fUnit ?: '' ?>"><input type="hidden" name="f_peran" value="<?= $fPeran ?: '' ?>">
      <div class="modal-kepala"><h2>Ubah foto</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil">Pegawai: <b data-teks="nama_teks"></b></p>
        <div style="display:flex;gap:16px;align-items:center">
          <img id="fotoPratinjau" class="avatar foto" style="width:96px;height:96px;flex:0 0 96px" src="" alt="Pratinjau foto" hidden>
          <div style="flex:1;min-width:0">
            <input type="file" id="fotoInput" name="foto" accept="image/jpeg,image/png,image/webp">
          </div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="submit" name="hapus_foto" value="1" class="btn btn-garis" style="margin-right:auto"
                formnovalidate data-konfirmasi="Hapus foto pegawai ini dan kembali ke inisial?"><?= ikon('hapus', 15) ?> Hapus foto</button>
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan foto</button>
      </div>
    </form>
  </div>
</div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var inp = document.getElementById('fotoInput'), prev = document.getElementById('fotoPratinjau');
    if (!inp || !prev) return;
    document.addEventListener('click', function (ev) {
      if (ev.target.closest('[data-buka="tiraiFoto"]')) { prev.hidden = true; prev.removeAttribute('src'); }
    });
    inp.addEventListener('change', function () {
      var f = inp.files && inp.files[0];
      if (!f || !/^image\//.test(f.type)) return;
      var img = new Image(), url = URL.createObjectURL(f);
      img.onload = function () {
        var s = Math.min(img.width, img.height), out = 480, c = document.createElement('canvas');
        c.width = c.height = out;
        c.getContext('2d').drawImage(img, (img.width - s) / 2, (img.height - s) / 2, s, s, 0, 0, out, out);
        prev.src = c.toDataURL('image/jpeg', 0.9);
        prev.hidden = false;
        c.toBlob(function (b) {
          if (b) {
            try {
              var dt = new DataTransfer();
              dt.items.add(new File([b], 'foto.jpg', { type: 'image/jpeg' }));
              inp.files = dt.files;
            } catch (e) {}
          }
          URL.revokeObjectURL(url);
        }, 'image/jpeg', 0.9);
      };
      img.src = url;
    });
  })();
</script>

<div class="tirai" id="tiraiPengguna" hidden>
  <div class="modal">
    <form method="post" autocomplete="off">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <input type="hidden" name="f_cari" value="<?= e($cari) ?>"><input type="hidden" name="f_unit" value="<?= $fUnit ?: '' ?>"><input type="hidden" name="f_peran" value="<?= $fPeran ?: '' ?>">
      <div class="modal-kepala"><h2>Pengguna</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <?php if ($isianGalat): ?>
          <div class="notif notif-galat"><?= ikon('silang', 17) ?>
            <div><b>Data belum tersimpan.</b><?php foreach ($isianGalat['galat'] as $g): ?><br><?= e($g) ?><?php endforeach; ?></div></div>
        <?php endif; ?>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="uNama">Nama lengkap <span class="wajib">*</span></label>
            <input type="text" id="uNama" name="nama" maxlength="150" required></div>
          <div class="form-baris"><label class="fl" for="uUser">Nama pengguna <span class="wajib">*</span></label>
            <input type="text" id="uUser" name="username" minlength="3" maxlength="60" required placeholder="bebas, contoh: budi.keuangan"></div>
          <div class="form-baris"><label class="fl" for="uNip">NIP / NIK</label><input type="text" id="uNip" name="nip" maxlength="40"></div>
          <div class="form-baris"><label class="fl" for="uEmail">Surel</label><input type="email" id="uEmail" name="email" maxlength="150"></div>
          <div class="form-baris penuh"><label class="fl" for="uJabatan">Jabatan</label>
            <input type="text" id="uJabatan" name="jabatan" maxlength="150" placeholder="Contoh: Staf Bidang Keuangan"></div>
          <div class="form-baris"><label class="fl" for="uUnit">Unit kerja</label><select id="uUnit" name="unit_id"><?= opsi_unit(0) ?></select></div>
          <div class="form-baris"><label class="fl" for="uPeran">Peran / hak akses <span class="wajib">*</span></label>
            <select id="uPeran" name="peran_id" required><?php foreach ($peran as $r): ?><option value="<?= (int) $r['id'] ?>"><?= e($r['nama']) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="uAtasan">Atasan langsung</label>
            <select id="uAtasan" name="atasan_id"><option value="">Tidak ada</option>
              <?php foreach ($semua as $s): ?><option value="<?= (int) $s['id'] ?>"><?= e($s['nama']) ?><?= $s['jabatan'] ? ' - ' . e($s['jabatan']) : '' ?></option><?php endforeach; ?></select>
            </div>
          <div class="form-baris"><label class="fl">Status akun</label>
            <label class="cek"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1"> Akun aktif dan dapat masuk</label></div>
        </div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
