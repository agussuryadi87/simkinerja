<?php
$semuaIzin = [];
foreach (DAFTAR_IZIN as $grup) {
    $semuaIzin = array_merge($semuaIzin, array_keys($grup));
}
$izinKunci = ['peran_kelola', 'pengguna_kelola'];
$saya = uid();
$tab = ($_GET['tab'] ?? '') === 'pegawai' ? 'pegawai' : 'peran';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');

    if ($op === 'simpan') {
        $id = (int) post('id');
        $lama = $id ? row('SELECT * FROM peran WHERE id = ?', [$id]) : null;
        if ($id && !$lama) {
            tolak_akses();
        }
        $kode = mb_strtolower(post('kode'));
        $data = [
            'kode'       => $kode,
            'nama'       => mb_substr(post('nama'), 0, 100),
            'keterangan' => mb_substr(post('keterangan'), 0, 255),
            'lingkup'    => array_key_exists(post('lingkup'), LINGKUP_DATA) ? post('lingkup') : 'diri',
            'urut'       => (int) post('urut'),
        ];
        $izin = array_values(array_intersect($semuaIzin, (array) ($_POST['izin'] ?? [])));
        if ($lama && (int) $lama['sistem'] === 1) {
            $izin = array_values(array_unique(array_merge($izin, $izinKunci)));
            $data['kode'] = $lama['kode'];
        }
        $galat = [];
        if (!preg_match('/^[a-z0-9_]{2,40}$/', $data['kode'])) {
            $galat[] = 'Kode peran 2-40 karakter: huruf kecil, angka, atau garis bawah.';
        } elseif ((int) val('SELECT COUNT(*) FROM peran WHERE kode = ? AND id <> ?', [$data['kode'], $id], 0) > 0) {
            $galat[] = 'Kode peran sudah dipakai.';
        }
        if ($data['nama'] === '') {
            $galat[] = 'Nama peran wajib diisi.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('peran');
        }
        db()->beginTransaction();
        if ($id) {
            update('peran', $data, 'id = ?', [$id]);
        } else {
            $id = insert('peran', $data);
        }
        q('DELETE FROM peran_izin WHERE peran_id = ?', [$id]);
        foreach (array_merge($izin, array_keys(IZIN_BAWAAN_BARU)) as $i) {
            insert('peran_izin', ['peran_id' => $id, 'izin' => $i]);
        }
        db()->commit();
        catat_log('simpan_peran', 'peran', $id, $data['nama'] . ': ' . implode(',', $izin));
        flash('Peran <b>' . e($data['nama']) . '</b> tersimpan dengan ' . count($izin) . ' hak akses.');
    } elseif ($op === 'hapus') {
        $r = row('SELECT * FROM peran WHERE id = ?', [(int) post('id')]);
        if (!$r || (int) $r['sistem'] === 1) {
            flash('Peran sistem tidak dapat dihapus.', 'galat');
        } elseif ((int) val('SELECT COUNT(*) FROM pengguna WHERE peran_id = ?', [$r['id']], 0) > 0) {
            flash('Peran masih dipakai pengguna. Pindahkan penggunanya ke peran lain terlebih dahulu.', 'warn');
        } else {
            q('DELETE FROM peran WHERE id = ?', [$r['id']]);
            catat_log('hapus_peran', 'peran', (int) $r['id'], $r['nama']);
            flash('Peran dihapus.');
        }
    } elseif ($op === 'izin_pegawai') {
        $pid = (int) post('pegawai_id');
        $target = row('SELECT id, nama, peran_id, aktif FROM pengguna WHERE id = ?', [$pid]);
        if (!$target) {
            flash('Pegawai tidak ditemukan.', 'galat');
            kembali_ke('peran', ['tab' => 'pegawai']);
        }
        if ($pid === $saya || akun_terkunci($pid) || !boleh_kelola_akun($pid)) {
            catat_log('tolak_izin_pegawai', 'pengguna', $pid, '');
            flash('Hak akses akun ini tidak dapat diubah dari sini.', 'galat');
            kembali_ke('peran', ['tab' => 'pegawai']);
        }
        $pilihan = (array) ($_POST['z'] ?? []);
        $baru = [];
        foreach ($semuaIzin as $kode) {
            $v = (string) ($pilihan[$kode] ?? 'ikut');
            if ($v === 'ya' || $v === 'tidak') {
                $baru[$kode] = $v === 'ya' ? 1 : 0;
            }
        }
        $akhir = izin_pengguna($pid, (int) $target['peran_id'], $baru);
        $punyaKelolaKini = in_array('pengguna_kelola', izin_pengguna($pid, (int) $target['peran_id']), true);
        if ((int) $target['aktif'] === 1 && $punyaKelolaKini && !in_array('pengguna_kelola', $akhir, true) && !admin_lain_tersisa($pid)) {
            flash('Harus tersisa minimal satu akun aktif yang dapat mengelola pengguna.', 'galat');
            kembali_ke('peran', ['tab' => 'pegawai', 'p' => $pid]);
        }
        if (!simpan_penyesuaian_izin($pid, $baru)) {
            flash('Hak akses gagal disimpan karena folder uploads/akses tidak dapat ditulisi di server.', 'galat');
            kembali_ke('peran', ['tab' => 'pegawai', 'p' => $pid]);
        }
        $ringkas = [];
        foreach ($baru as $kode => $boleh) {
            $ringkas[] = ($boleh ? '+' : '-') . $kode;
        }
        catat_log('atur_izin_pegawai', 'pengguna', $pid, $ringkas ? implode(',', $ringkas) : 'ikut peran');
        flash('Hak akses <b>' . e($target['nama']) . '</b> disimpan' . ($baru ? ' dengan ' . count($baru) . ' penyesuaian.' : ', sepenuhnya mengikuti peran.'));
        kembali_ke('peran', ['tab' => 'pegawai', 'p' => $pid]);
    }
    kembali_ke('peran');
}

$peran = rows('SELECT pr.*, (SELECT COUNT(*) FROM pengguna p WHERE p.peran_id = pr.id) AS jml FROM peran pr ORDER BY pr.urut, pr.nama');
$izinPeran = [];
foreach ($peran as $r) {
    $izinPeran[(int) $r['id']] = izin_peran((int) $r['id']);
}
$jmlSesuai = [];
foreach (semua_penyesuaian_izin() as $idPegawai => $peta) {
    if ($peta) {
        $jmlSesuai[(int) $idPegawai] = count((array) $peta);
    }
}
?>

<nav class="tab">
  <a href="<?= e(url('peran')) ?>" class="<?= $tab === 'peran' ? 'aktif' : '' ?>"><?= ikon('perisai', 16) ?> Per peran <?= lencana((string) count($peran)) ?></a>
  <a href="<?= e(url('peran', ['tab' => 'pegawai'])) ?>" class="<?= $tab === 'pegawai' ? 'aktif' : '' ?>"><?= ikon('pengguna', 16) ?> Per pegawai
    <?= $jmlSesuai ? lencana(count($jmlSesuai) . ' disesuaikan', 'emas') : '' ?></a>
</nav>

<?php if ($tab === 'peran'): ?>
<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('perisai', 18) ?> Hak akses per peran</h2><p class="ket">Setiap baris mengikuti menu di sidebar. Pegawai mengikuti hak akses perannya, kecuali disesuaikan di tab Per pegawai.</p></div>
    <div class="aksi"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPeran" data-reset data-judul="Tambah peran"
       data-isi='{"id":"","lingkup":"diri","urut":"10"}' data-peran-izin="[]"><?= ikon('tambah', 15) ?> Tambah peran</button></div>
  </div>
  <div class="tabel-bungkus tabel-lekat"><table class="tabel matriks">
    <thead><tr><th>Menu &amp; hak akses</th>
      <?php foreach ($peran as $r): ?><th class="tengah"><?= e($r['nama']) ?><span class="sub" style="text-transform:none;font-weight:500"><?= (int) $r['jml'] ?> pengguna</span>
        <div class="aksi-baris" style="justify-content:center;margin-top:8px;text-transform:none">
          <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiPeran" data-judul="Ubah peran: <?= e($r['nama']) ?>"
                  data-isi='<?= e(json_encode(['id' => (int) $r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'], 'keterangan' => $r['keterangan'],
                      'lingkup' => $r['lingkup'], 'urut' => (string) $r['urut']])) ?>'
                  data-peran-izin='<?= e(json_encode($izinPeran[(int) $r['id']] ?? [])) ?>' data-sistem="<?= (int) $r['sistem'] ?>"><?= ikon('sunting', 14) ?> Ubah</button>
          <?php if (!(int) $r['sistem']): ?>
            <form method="post" data-konfirmasi="Hapus peran <?= e($r['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus">
              <input type="hidden" name="id" value="<?= (int) $r['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus" aria-label="Hapus peran <?= e($r['nama']) ?>"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
        </div></th><?php endforeach; ?></tr></thead>
    <tbody>
      <tr><td><b>Lingkup data</b></td>
        <?php foreach ($peran as $r): ?><td class="tengah"><?= lencana(LINGKUP_DATA[$r['lingkup']][0], 'abu') ?></td><?php endforeach; ?></tr>
      <tr><td colspan="<?= count($peran) + 1 ?>" class="grup-izin">Beranda</td></tr>
      <tr><td><b>Beranda</b><span class="sub">Selalu terbuka untuk semua pegawai</span></td>
        <?php foreach ($peran as $r): ?><td class="tengah"><?= ikon('centang', 18) ?></td><?php endforeach; ?></tr>
      <?php foreach (DAFTAR_IZIN as $namaGrup => $grup): ?>
        <tr><td colspan="<?= count($peran) + 1 ?>" class="grup-izin"><?= e($namaGrup) ?></td></tr>
        <?php foreach ($grup as $kode => $def): ?>
          <tr<?= !empty($def[2]) ? ' class="izin-anak"' : '' ?>><td><b><?= e($def[0]) ?></b><span class="sub"><?= e($def[1]) ?></span></td>
            <?php foreach ($peran as $r): ?><td class="tengah"><?= in_array($kode, $izinPeran[(int) $r['id']] ?? [], true) ? ikon('centang', 18) : '<span class="tidak">-</span>' ?></td><?php endforeach; ?></tr>
        <?php endforeach; ?>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<div class="tirai" id="tiraiPeran" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Peran</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="prNama">Nama peran <span class="wajib">*</span></label>
            <input type="text" id="prNama" name="nama" maxlength="100" required placeholder="Contoh: Sekretaris Biro"></div>
          <div class="form-baris"><label class="fl" for="prKode">Kode <span class="wajib">*</span></label>
            <input type="text" id="prKode" name="kode" maxlength="40" required pattern="[a-zA-Z0-9_]{2,40}" placeholder="sekretaris_biro"></div>
          <div class="form-baris penuh"><label class="fl" for="prKet">Keterangan</label>
            <input type="text" id="prKet" name="keterangan" maxlength="255"></div>
          <div class="form-baris"><label class="fl" for="prLingkup">Lingkup data</label>
            <select id="prLingkup" name="lingkup"><?php foreach (LINGKUP_DATA as $k => [$n, $ket]): ?><option value="<?= $k ?>"><?= e($n) ?> - <?= e($ket) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="prUrut">Urutan tampil</label>
            <input type="number" id="prUrut" name="urut" min="0" max="999"></div>
        </div>
        <?php foreach (DAFTAR_IZIN as $namaGrup => $grup): ?>
          <div class="izin-grup">
            <h3><?= e($namaGrup) ?></h3>
            <?php foreach ($grup as $kode => $def): ?>
              <label class="cek<?= !empty($def[2]) ? ' izin-anak' : '' ?>"><input type="checkbox" name="izin[]" value="<?= $kode ?>" data-izin="<?= $kode ?>">
                <span><b><?= e($def[0]) ?></b><small><?= e($def[1]) ?></small></span></label>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>

<script nonce="<?= csp_nonce() ?>">
  document.addEventListener('click', function (ev) {
    var b = ev.target.closest('[data-peran-izin]');
    if (!b) return;
    var izin = [];
    try { izin = JSON.parse(b.getAttribute('data-peran-izin')); } catch (e) {}
    var sistem = b.getAttribute('data-sistem') === '1';
    document.querySelectorAll('#tiraiPeran [data-izin]').forEach(function (c) {
      c.checked = izin.indexOf(c.value) !== -1;
    });
    var kode = document.getElementById('prKode');
    kode.readOnly = sistem;
  });
</script>

<?php else:
    $pid = (int) ($_GET['p'] ?? 0);
    $pegawai = rows('SELECT p.id, p.nama, p.jabatan, p.aktif, p.peran_id, pr.nama AS peran_nama, pr.kode AS peran_kode, un.nama AS unit_nama
                       FROM pengguna p JOIN peran pr ON pr.id = p.peran_id LEFT JOIN unit un ON un.id = p.unit_id
                      ORDER BY p.aktif DESC, un.nama IS NULL, un.nama, pr.urut, p.nama');
    $pilih = null;
    foreach ($pegawai as $r) {
        if ((int) $r['id'] === $pid) {
            $pilih = $r;
        }
    }
    $bisaUbah = static function (array $r) use ($saya): bool {
        return (int) $r['id'] !== $saya && !akun_terkunci((int) $r['id']) && boleh_kelola_akun((int) $r['id']);
    };
?>

<?php if ($pilih):
    $dariPeran = izin_pengguna((int) $pilih['id'], (int) $pilih['peran_id'], []);
    $sesuai = izin_penyesuaian((int) $pilih['id']);
    $efektif = izin_pengguna((int) $pilih['id'], (int) $pilih['peran_id']);
    $boleh = $bisaUbah($pilih);
?>
<div class="kartu">
  <div class="kartu-kepala">
    <div class="orang"><?= avatar($pilih['nama']) ?><div><h2 style="margin:0"><?= e($pilih['nama']) ?></h2>
      <p class="ket" style="margin:3px 0 0"><?= $pilih['jabatan'] ? e($pilih['jabatan']) . ' · ' : '' ?>Peran <?= lencana($pilih['peran_nama'], 'hijau') ?> · <?= count($efektif) ?> hak akses aktif</p></div></div>
    <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url('peran', ['tab' => 'pegawai'])) ?>"><?= ikon('kiri', 15) ?> Semua pegawai</a></div>
  </div>
  <?php if (!$boleh): ?>
    <div class="notif notif-info" style="margin:0 20px 16px"><?= ikon('info', 17) ?>
      <div><?= (int) $pilih['id'] === $saya ? 'Hak akses akun Anda sendiri tidak dapat diubah dari sini.' : 'Akun ini dilindungi, hak aksesnya hanya mengikuti peran.' ?></div></div>
  <?php endif; ?>
  <form method="post">
    <?= csrf_field() ?><input type="hidden" name="op" value="izin_pegawai"><input type="hidden" name="pegawai_id" value="<?= (int) $pilih['id'] ?>">
    <div class="tabel-bungkus"><table class="tabel matriks tabel-izin-pegawai">
      <thead><tr><th>Menu &amp; hak akses</th><th class="tengah">Bawaan peran</th><th class="tengah">Pengaturan pegawai ini</th><th class="tengah">Hasil</th></tr></thead>
      <tbody>
        <?php foreach (DAFTAR_IZIN as $namaGrup => $grup): ?>
          <tr><td colspan="4" class="grup-izin"><?= e($namaGrup) ?></td></tr>
          <?php foreach ($grup as $kode => $def):
              $bawaan = in_array($kode, $dariPeran, true);
              $nilai = array_key_exists($kode, $sesuai) ? ($sesuai[$kode] ? 'ya' : 'tidak') : 'ikut';
              $hasil = in_array($kode, $efektif, true); ?>
            <tr<?= !empty($def[2]) ? ' class="izin-anak"' : '' ?> data-bawaan="<?= $bawaan ? '1' : '0' ?>">
              <td><b><?= e($def[0]) ?></b><span class="sub"><?= e($def[1]) ?></span></td>
              <td class="tengah"><?= $bawaan ? ikon('centang', 18) : '<span class="tidak">-</span>' ?></td>
              <td class="tengah"><div class="pilih-hadir pilih-izin">
                <?php foreach (['ikut' => 'Ikuti peran', 'ya' => 'Izinkan', 'tidak' => 'Tolak'] as $v => $lbl): ?>
                  <label class="izin-<?= $v ?>"><input type="radio" name="z[<?= e($kode) ?>]" value="<?= $v ?>"<?= $nilai === $v ? ' checked' : '' ?><?= $boleh ? '' : ' disabled' ?>><span><?= $lbl ?></span></label>
                <?php endforeach; ?>
              </div></td>
              <td class="tengah" data-hasil><?= $hasil ? lencana('Boleh', 'hijau') : lencana('Tidak', 'abu') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php if ($boleh): ?>
    <div class="modal-kaki" style="border-top:1px solid var(--garis)">
      <button type="button" class="btn btn-garis" id="semuaIkut" style="margin-right:auto"><?= ikon('siklus', 15) ?> Kembalikan semua ke bawaan peran</button>
      <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan hak akses</button>
    </div>
    <?php endif; ?>
  </form>
</div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var tabel = document.querySelector('.tabel-izin-pegawai');
    if (!tabel) return;
    function segarkan(tr) {
      var c = tr.querySelector('input[type=radio]:checked');
      var v = c ? c.value : 'ikut', ok = v === 'ya' || (v === 'ikut' && tr.getAttribute('data-bawaan') === '1');
      var sel = tr.querySelector('[data-hasil]');
      if (sel) sel.innerHTML = ok ? '<span class="lc lc-hijau">Boleh</span>' : '<span class="lc lc-abu">Tidak</span>';
    }
    tabel.addEventListener('change', function (ev) { var tr = ev.target.closest('tr'); if (tr) segarkan(tr); });
    var tombol = document.getElementById('semuaIkut');
    if (tombol) tombol.addEventListener('click', function () {
      tabel.querySelectorAll('input[value=ikut]').forEach(function (r) { r.checked = true; segarkan(r.closest('tr')); });
    });
  })();
</script>

<?php else: ?>
<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('pengguna', 18) ?> Hak akses per pegawai</h2><p class="ket">Pilih pegawai untuk menambah atau mencabut menu tertentu tanpa mengubah perannya.</p></div>
    <div class="aksi"><input type="search" id="cariPegawaiIzin" placeholder="Cari nama, peran, atau unit" style="min-width:240px"></div>
  </div>
  <div class="tabel-bungkus"><table class="tabel" id="tabelPegawaiIzin">
    <thead><tr><th>Pegawai</th><th>Unit</th><th>Peran</th><th class="tengah">Penyesuaian</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
      <?php foreach ($pegawai as $r): $n = $jmlSesuai[(int) $r['id']] ?? 0; ?>
      <tr style="<?= $r['aktif'] ? '' : 'opacity:.55' ?>">
        <td><div class="orang"><?= avatar($r['nama'], 'kecil') ?><div><b><?= e($r['nama']) ?></b><span><?= $r['jabatan'] ? e($r['jabatan']) : '<i>Jabatan belum diisi</i>' ?></span></div></div></td>
        <td><?= e($r['unit_nama'] ?? '-') ?></td>
        <td><?= lencana($r['peran_nama'], 'hijau') ?><?= $r['aktif'] ? '' : ' ' . lencana('Nonaktif', 'merah') ?></td>
        <td class="tengah"><?= $n > 0 ? lencana($n . ' disesuaikan', 'emas') : '<span class="lemah">Ikuti peran</span>' ?></td>
        <td class="kanan"><a class="btn btn-garis btn-kecil" href="<?= e(url('peran', ['tab' => 'pegawai', 'p' => (int) $r['id']])) ?>">
          <?= ikon($bisaUbah($r) ? 'sunting' : 'perisai', 14) ?> <?= $bisaUbah($r) ? 'Atur akses' : 'Lihat' ?></a></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table></div>
</div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var cari = document.getElementById('cariPegawaiIzin');
    if (!cari) return;
    cari.addEventListener('input', function () {
      var q = cari.value.toLowerCase();
      document.querySelectorAll('#tabelPegawaiIzin tbody tr').forEach(function (tr) {
        tr.hidden = q !== '' && tr.textContent.toLowerCase().indexOf(q) === -1;
      });
    });
  })();
</script>
<?php endif; ?>
<?php endif; ?>
