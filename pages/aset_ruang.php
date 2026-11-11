<?php
$aksi = $_GET['aksi'] ?? '';
$id = (int) ($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $rid = (int) post('id');

    if ($op === 'simpan') {
        $data = [
            'kode'             => mb_substr(strtoupper(post('kode')), 0, 30),
            'nama'             => mb_substr(post('nama'), 0, 150),
            'gedung'           => mb_substr(post('gedung'), 0, 100),
            'lantai'           => mb_substr(post('lantai'), 0, 20),
            'jenis'            => array_key_exists(post('jenis'), ASET_JENIS_RUANG) ? post('jenis') : 'lainnya',
            'kapasitas'        => post('kapasitas') !== '' && ctype_digit(post('kapasitas')) ? (int) post('kapasitas') : null,
            'luas'             => aset_angka_atau_null('luas'),
            'penanggung_jawab' => mb_substr(post('penanggung_jawab'), 0, 150),
            'keterangan'       => mb_substr(post_teks('keterangan'), 0, 3000),
            'aktif'            => post('aktif', '1') === '0' ? 0 : 1,
        ];
        $galat = [];
        if ($data['kode'] === '' || $data['nama'] === '') {
            $galat[] = 'Kode dan nama ruangan wajib diisi.';
        }
        if ($data['kode'] !== '' && (int) val('SELECT COUNT(*) FROM aset_ruang WHERE kode = ? AND id <> ?', [$data['kode'], $rid], 0) > 0) {
            $galat[] = 'Kode ruangan ' . $data['kode'] . ' sudah dipakai ruangan lain.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('aset_ruang', $rid ? ['aksi' => 'lihat', 'id' => $rid] : []);
        }
        if ($rid && row('SELECT id FROM aset_ruang WHERE id = ?', [$rid])) {
            update('aset_ruang', $data + ['diubah' => date('Y-m-d H:i:s')], 'id = ?', [$rid]);
            catat_log('ubah_aset_ruang', 'aset_ruang', $rid, $data['nama']);
            flash('Data ruangan <b>' . e($data['nama']) . '</b> diperbarui.');
        } else {
            $rid = insert('aset_ruang', $data);
            catat_log('tambah_aset_ruang', 'aset_ruang', $rid, $data['nama']);
            flash('Ruangan <b>' . e($data['nama']) . '</b> ditambahkan. Unggah foto ruangan dan catat barang-barangnya.');
        }
        kembali_ke('aset_ruang', ['aksi' => 'lihat', 'id' => $rid]);
    }

    $ruang = $rid ? row('SELECT * FROM aset_ruang WHERE id = ?', [$rid]) : null;
    if (!$ruang) {
        tolak_akses();
    }

    if ($op === 'foto_unggah') {
        [$n, $galat] = aset_simpan_foto($_FILES['foto'] ?? [], 'ruang_id', $rid, post('keterangan'));
        if ($n > 0) {
            catat_log('foto_aset_ruang', 'aset_ruang', $rid, $n . ' foto');
            flash($n . ' foto ditambahkan.');
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } elseif ($n === 0) {
            flash('Pilih minimal satu foto (JPG, PNG, atau WebP).', 'warn');
        }
        kembali_ke('aset_ruang', ['aksi' => 'lihat', 'id' => $rid]);
    } elseif ($op === 'foto_hapus') {
        aset_hapus_foto_satu((int) post('foto_id'), 'ruang_id', $rid);
        flash('Foto dihapus.');
        kembali_ke('aset_ruang', ['aksi' => 'lihat', 'id' => $rid]);
    } elseif ($op === 'hapus') {
        $jmlBarang = (int) val("SELECT COUNT(*) FROM aset_barang WHERE ruang_id = ? AND status = 'aktif'", [$rid], 0);
        $jmlRawat = (int) val('SELECT COUNT(*) FROM aset_perawatan WHERE ruang_id = ?', [$rid], 0);
        if ($jmlBarang > 0 || $jmlRawat > 0) {
            flash('Ruangan <b>' . e($ruang['nama']) . '</b> tidak dihapus karena masih memiliki ' . implode(' dan ', array_filter([
                $jmlBarang ? $jmlBarang . ' barang aktif' : '', $jmlRawat ? $jmlRawat . ' catatan kebersihan' : '',
            ])) . '. Pindahkan barangnya, atau ubah status ruangan menjadi Nonaktif agar riwayatnya tetap tersimpan.', 'warn');
            kembali_ke('aset_ruang', ['aksi' => 'lihat', 'id' => $rid]);
        }
        aset_hapus_semua_foto('ruang_id', $rid);
        q('DELETE FROM aset_ruang WHERE id = ?', [$rid]);
        catat_log('hapus_aset_ruang', 'aset_ruang', $rid, $ruang['nama']);
        flash('Ruangan <b>' . e($ruang['nama']) . '</b> dihapus. Barang di dalamnya tetap tercatat tanpa ruangan.');
        kembali_ke('aset_ruang');
    }
    kembali_ke('aset_ruang');
}

$modalRuang = static function (): void { ?>
<div class="tirai" id="tiraiRuang" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Ruangan</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="rKode">Kode ruangan <span class="wajib">*</span></label>
            <input type="text" id="rKode" name="kode" maxlength="30" required placeholder="GD-A-101"></div>
          <div class="form-baris"><label class="fl" for="rNama">Nama ruangan <span class="wajib">*</span></label>
            <input type="text" id="rNama" name="nama" maxlength="150" required placeholder="Ruang Kelas A101"></div>
          <div class="form-baris"><label class="fl" for="rJenis">Jenis ruangan</label>
            <select id="rJenis" name="jenis"><?= pilihan(ASET_JENIS_RUANG, 'lainnya') ?></select></div>
          <div class="form-baris"><label class="fl" for="rGedung">Gedung</label>
            <input type="text" id="rGedung" name="gedung" maxlength="100" placeholder="Gedung A"></div>
          <div class="form-baris"><label class="fl" for="rLantai">Lantai</label>
            <input type="text" id="rLantai" name="lantai" maxlength="20" placeholder="1"></div>
          <div class="form-baris"><label class="fl" for="rKap">Kapasitas (orang)</label>
            <input type="number" id="rKap" name="kapasitas" min="0" max="100000"></div>
          <div class="form-baris"><label class="fl" for="rLuas">Luas (m&sup2;)</label>
            <input type="text" id="rLuas" name="luas" inputmode="decimal" maxlength="12" placeholder="48"></div>
          <div class="form-baris"><label class="fl" for="rPj">Penanggung jawab</label>
            <input type="text" id="rPj" name="penanggung_jawab" maxlength="150"></div>
        </div>
        <div class="form-baris"><label class="fl" for="rKet">Keterangan</label>
          <textarea id="rKet" name="keterangan" rows="3" maxlength="3000"></textarea></div>
        <label class="cek"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" checked> Ruangan aktif digunakan</label>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php };

if ($aksi === 'lihat') {
    $r = row('SELECT * FROM aset_ruang WHERE id = ?', [$id]);
    if (!$r) {
        flash('Ruangan tidak ditemukan.', 'galat');
        kembali_ke('aset_ruang');
    }
    $subjudul = $r['kode'];
    $fotos = aset_foto_daftar('ruang_id', $id);
    $barang = rows("SELECT * FROM aset_barang WHERE ruang_id = ? AND status = 'aktif' ORDER BY kategori, nama", [$id]);
    $jmlUnit = array_sum(array_map('intval', array_column($barang, 'jumlah')));
    $rusak = count(array_filter($barang, static function ($b) { return in_array($b['kondisi'], ['rusak_ringan', 'rusak_berat', 'hilang'], true); }));
    $terakhirBersih = val("SELECT MAX(selesai_pada) FROM aset_perawatan WHERE jenis = 'kebersihan' AND ruang_id = ? AND status = 'selesai'", [$id]);
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <a class="btn btn-garis" href="<?= e(url('aset_ruang')) ?>"><?= ikon('kembali', 15) ?> Daftar ruangan</a>
      <button class="btn btn-garis" type="button" data-buka="tiraiRuang" data-judul="Ubah ruangan"
        data-isi='<?= e(json_encode(['id' => (int) $r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'], 'jenis' => $r['jenis'], 'gedung' => $r['gedung'],
            'lantai' => $r['lantai'], 'kapasitas' => $r['kapasitas'], 'luas' => $r['luas'] !== null ? rtrim(rtrim((string) $r['luas'], '0'), '.') : '',
            'penanggung_jawab' => $r['penanggung_jawab'], 'keterangan' => $r['keterangan'], 'aktif' => (string) $r['aktif']])) ?>'><?= ikon('sunting', 15) ?> Ubah data</button>
      <a class="btn btn-garis" href="<?= e(url('aset_barang', ['ruang' => $id])) ?>"><?= ikon('aset', 15) ?> Kelola barang</a>
    </div>

    <div class="grid-stat">
      <div class="stat"><span class="ik"><?= ikon('gedung', 22) ?></span><div><div class="label">Ruangan</div>
        <div class="nilai" style="font-size:1.1rem"><?= e($r['nama']) ?></div>
        <div class="ket"><?= e(ASET_JENIS_RUANG[$r['jenis']] ?? '-') ?> · <?= e(trim($r['gedung'] . ($r['lantai'] !== '' ? ' lt. ' . $r['lantai'] : ''), ' ·') ?: 'lokasi belum diisi') ?></div></div></div>
      <div class="stat biru"><span class="ik"><?= ikon('aset', 22) ?></span><div><div class="label">Barang tercatat</div>
        <div class="nilai"><?= angka(count($barang)) ?> <small>jenis</small></div><div class="ket"><?= angka($jmlUnit) ?> unit/item</div></div></div>
      <div class="stat <?= $rusak ? 'merah' : 'emas' ?>"><span class="ik"><?= ikon('segitiga', 22) ?></span><div><div class="label">Perlu perhatian</div>
        <div class="nilai"><?= angka($rusak) ?> <small>barang</small></div><div class="ket">&nbsp;</div></div></div>
      <div class="stat ungu"><span class="ik"><?= ikon('kebersihan', 22) ?></span><div><div class="label">Terakhir dibersihkan</div>
        <div class="nilai" style="font-size:1.1rem"><?= $terakhirBersih ? e(tanggal_pendek($terakhirBersih)) : 'Belum ada' ?></div>
        <div class="ket"><?php if ($terakhirBersih): $selisih = (int) floor((strtotime(date('Y-m-d')) - strtotime($terakhirBersih)) / 86400); echo $selisih <= 0 ? 'hari ini' : $selisih . ' hari lalu'; else: ?>&nbsp;<?php endif; ?></div></div></div>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('kamera', 18) ?> Foto ruangan</h2><p class="ket"><?= count($fotos) ?> foto</p></div></div>
      <div class="kartu-isi">
        <?php if (!$fotos): ?><?= sel_kosong('Belum ada foto ruangan') ?><?php endif; ?>
        <div class="galeri-aset">
          <?php foreach ($fotos as $f): ?>
            <figure>
              <a href="<?= e(aset_foto_url($f['berkas'])) ?>" target="_blank" rel="noopener"><img src="<?= e(aset_foto_url($f['berkas'])) ?>" alt="<?= e($f['keterangan'] ?: 'Foto ruangan ' . $r['nama']) ?>" loading="lazy"></a>
              <figcaption><?= e($f['keterangan'] ?: '') ?>
                <form method="post" class="no-cetak" data-konfirmasi="Hapus foto ini?"><?= csrf_field() ?><input type="hidden" name="op" value="foto_hapus"><input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="foto_id" value="<?= (int) $f['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus foto"><?= ikon('hapus', 14) ?></button></form></figcaption>
            </figure>
          <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="unggah-aset no-cetak">
          <?= csrf_field() ?><input type="hidden" name="op" value="foto_unggah"><input type="hidden" name="id" value="<?= $id ?>">
          <div class="form-baris" style="flex:1;min-width:220px"><label class="fl" for="fotoRuang">Tambah foto (boleh banyak)</label>
            <input type="file" id="fotoRuang" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
          <div class="form-baris" style="flex:1;min-width:200px"><label class="fl" for="fotoKet">Keterangan foto</label>
            <input type="text" id="fotoKet" name="keterangan" maxlength="200" placeholder="mis. Sisi depan / Lemari arsip"></div>
          <button class="btn btn-utama" type="submit"><?= ikon('kamera', 15) ?> Unggah</button>
        </form>
      </div>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('aset', 18) ?> Barang di ruangan ini</h2><p class="ket"><?= count($barang) ?> jenis barang</p></div>
        <div class="aksi no-cetak"><a class="btn btn-utama btn-kecil" href="<?= e(url('aset_barang', ['ruang' => $id])) ?>"><?= ikon('tambah', 15) ?> Tambah / kelola barang</a></div></div>
      <?php if (!$barang): ?><?= sel_kosong('Belum ada barang tercatat') ?>
      <?php else: ?>
      <div class="tabel-bungkus"><table class="tabel">
        <thead><tr><th>Kode</th><th>Nama barang</th><th>Kategori</th><th class="angka">Jumlah</th><th>Kondisi</th></tr></thead>
        <tbody>
        <?php foreach ($barang as $b): ?>
          <tr><td class="nowrap"><code><?= e($b['kode']) ?></code></td>
            <td><b><?= e($b['nama']) ?></b><div class="kecil lemah"><?= e(trim($b['merek'] . ' ' . $b['model'])) ?></div></td>
            <td><?= e(aset_kategori(true)[$b['kategori']] ?? '-') ?></td>
            <td class="angka"><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></td>
            <td><?= aset_lencana_kondisi($b['kondisi']) ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
    <?php $modalRuang(); return;
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fJenis = array_key_exists($_GET['jenis'] ?? '', ASET_JENIS_RUANG) ? $_GET['jenis'] : '';
$syarat = ['1=1'];
$par = [];
if ($cari !== '') {
    $syarat[] = '(r.nama LIKE ? OR r.kode LIKE ? OR r.gedung LIKE ?)';
    $par = array_merge($par, ['%' . $cari . '%', '%' . $cari . '%', '%' . $cari . '%']);
}
if ($fJenis !== '') {
    $syarat[] = 'r.jenis = ?';
    $par[] = $fJenis;
}
$daftar = rows(
    "SELECT r.*, (SELECT COUNT(*) FROM aset_barang b WHERE b.ruang_id = r.id AND b.status = 'aktif') AS jml_barang,
            (SELECT COUNT(*) FROM aset_foto f WHERE f.ruang_id = r.id) AS jml_foto,
            (SELECT f.berkas FROM aset_foto f WHERE f.ruang_id = r.id ORDER BY f.id LIMIT 1) AS sampul
       FROM aset_ruang r WHERE " . implode(' AND ', $syarat) . ' ORDER BY r.gedung, r.lantai, r.nama',
    $par
);
$penginput = aset_penginput('aset_ruang', $daftar);
?>
<form method="get" class="saring">
  <input type="hidden" name="h" value="aset_ruang">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nama, kode, atau gedung"></div>
  <div class="form-baris"><label class="fl">Jenis</label><select name="jenis" data-auto-kirim><option value="">Semua jenis</option><?= pilihan(ASET_JENIS_RUANG, $fJenis) ?></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('gedung', 18) ?> Daftar ruangan</h2><p class="ket"><?= count($daftar) ?> ruangan</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiRuang" data-reset data-judul="Tambah ruangan"
        data-isi='{"id":"","jenis":"ruang_kelas","aktif":"1"}'><?= ikon('tambah', 15) ?> Tambah ruangan</button></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong($cari !== '' || $fJenis !== '' ? 'Tidak ada ruangan yang cocok' : 'Belum ada ruangan') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th style="width:84px">Foto</th><th>Ruangan</th><th>Jenis</th><th>Lokasi</th><th class="angka">Kapasitas</th><th class="angka">Barang</th><th>Status</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $r): ?>
      <tr>
        <td><?php if ($r['sampul']): ?><a href="<?= e(url('aset_ruang', ['aksi' => 'lihat', 'id' => $r['id']])) ?>"><img class="mini-aset" src="<?= e(aset_foto_url($r['sampul'])) ?>" alt="" loading="lazy"></a>
            <?php else: ?><span class="mini-aset kosong"><?= ikon('kamera', 18) ?></span><?php endif; ?></td>
        <td><a href="<?= e(url('aset_ruang', ['aksi' => 'lihat', 'id' => $r['id']])) ?>"><b><?= e($r['nama']) ?></b></a>
          <div class="kecil lemah"><code><?= e($r['kode']) ?></code><?= $r['jml_foto'] ? ' · ' . (int) $r['jml_foto'] . ' foto' : '' ?></div></td>
        <td><?= e(ASET_JENIS_RUANG[$r['jenis']] ?? '-') ?></td>
        <td class="kecil"><?= e(trim($r['gedung'] . ($r['lantai'] !== '' ? ' · lt. ' . $r['lantai'] : ''), ' ·') ?: '-') ?></td>
        <td class="angka"><?= $r['kapasitas'] !== null ? angka($r['kapasitas']) : '-' ?></td>
        <td class="angka"><?= angka($r['jml_barang']) ?></td>
        <td><?= $r['aktif'] ? '<span class="status-titik">Aktif</span>' : lencana('Nonaktif') ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $r['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <a class="btn btn-hantu btn-ikon" href="<?= e(url('aset_ruang', ['aksi' => 'lihat', 'id' => $r['id']])) ?>" title="Lihat"><?= ikon('mata', 15) ?></a>
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiRuang" data-judul="Ubah ruangan"
            data-isi='<?= e(json_encode(['id' => (int) $r['id'], 'kode' => $r['kode'], 'nama' => $r['nama'], 'jenis' => $r['jenis'], 'gedung' => $r['gedung'],
                'lantai' => $r['lantai'], 'kapasitas' => $r['kapasitas'], 'luas' => $r['luas'] !== null ? rtrim(rtrim((string) $r['luas'], '0'), '.') : '',
                'penanggung_jawab' => $r['penanggung_jawab'], 'keterangan' => $r['keterangan'], 'aktif' => (string) $r['aktif']])) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" data-konfirmasi="Hapus ruangan <?= e($r['nama']) ?> beserta semua fotonya? Barang di dalamnya tidak ikut terhapus."><?= csrf_field() ?>
            <input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php $modalRuang(); ?>
