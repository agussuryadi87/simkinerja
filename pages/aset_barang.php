<?php
$aksi = $_GET['aksi'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$fRuang = (int) ($_GET['ruang'] ?? 0);

$kodeOtomatis = static function (): string {
    $tahun = date('Y');
    $n = (int) val('SELECT COUNT(*) FROM aset_barang') + 1;
    do {
        $kode = sprintf('INV-%s-%04d', $tahun, $n++);
    } while ((int) val('SELECT COUNT(*) FROM aset_barang WHERE kode = ?', [$kode], 0) > 0);
    return $kode;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $bid = (int) post('id');

    if ($op === 'simpan') {
        $kategori = array_key_exists(post('kategori'), aset_kategori(true)) ? post('kategori') : 'lainnya';
        $tahun = post('tahun');
        $data = [
            'kode'        => mb_substr(strtoupper(post('kode')), 0, 50),
            'nama'        => mb_substr(post('nama'), 0, 200),
            'kategori'    => $kategori,
            'merek'       => mb_substr(post('merek'), 0, 100),
            'model'       => mb_substr(post('model'), 0, 100),
            'no_seri'     => mb_substr(post('no_seri'), 0, 100),
            'ruang_id'    => (int) post('ruang_id') ?: null,
            'posisi'      => mb_substr(post('posisi'), 0, 100),
            'jumlah'      => max(1, min(100000, (int) post('jumlah', '1'))),
            'satuan'      => mb_substr(post('satuan') ?: 'unit', 0, 20),
            'tahun'       => $tahun !== '' && ctype_digit($tahun) && (int) $tahun >= 1950 && (int) $tahun <= (int) date('Y') + 1 ? (int) $tahun : null,
            'harga'       => aset_angka_atau_null('harga'),
            'sumber_dana' => mb_substr(post('sumber_dana'), 0, 100),
            'kondisi'     => array_key_exists(post('kondisi'), ASET_KONDISI) ? post('kondisi') : 'baik',
            'keterangan'  => mb_substr(post_teks('keterangan'), 0, 3000),
        ];
        if ($data['kode'] === '') {
            $data['kode'] = $kodeOtomatis();
        }
        $galat = [];
        if ($data['nama'] === '') {
            $galat[] = 'Nama barang wajib diisi.';
        }
        if ((int) val('SELECT COUNT(*) FROM aset_barang WHERE kode = ? AND id <> ?', [$data['kode'], $bid], 0) > 0) {
            $galat[] = 'Kode inventaris ' . $data['kode'] . ' sudah dipakai barang lain.';
        }
        if ($data['ruang_id'] && !row('SELECT id FROM aset_ruang WHERE id = ?', [$data['ruang_id']])) {
            $galat[] = 'Ruangan yang dipilih tidak ditemukan.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('aset_barang', $bid ? ['aksi' => 'lihat', 'id' => $bid] : ['ruang' => $fRuang]);
        }
        $sebelum = $bid ? row('SELECT id, ruang_id, jumlah FROM aset_barang WHERE id = ?', [$bid]) : null;
        if ($sebelum) {
            update('aset_barang', $data + ['diubah' => date('Y-m-d H:i:s')], 'id = ?', [$bid]);
            if ((int) $sebelum['ruang_id'] !== (int) $data['ruang_id']) {
                insert('aset_mutasi', ['jenis' => 'pindah', 'barang_id' => $bid, 'dari_ruang_id' => $sebelum['ruang_id'] ?: null, 'ke_ruang_id' => $data['ruang_id'],
                    'jumlah' => $data['jumlah'], 'tanggal' => date('Y-m-d'), 'status' => 'selesai', 'keterangan' => 'Lokasi diubah melalui data barang', 'dibuat_oleh' => uid()]);
            }
            catat_log('ubah_aset_barang', 'aset_barang', $bid, $data['nama']);
            flash('Data barang <b>' . e($data['nama']) . '</b> diperbarui.');
        } else {
            $bid = insert('aset_barang', $data);
            catat_log('tambah_aset_barang', 'aset_barang', $bid, $data['nama']);
            flash('Barang <b>' . e($data['nama']) . '</b> dicatat dengan kode <b>' . e($data['kode']) . '</b>.');
        }
        if (isset($_FILES['foto'])) {
            [, $galatFoto] = aset_simpan_foto($_FILES['foto'], 'barang_id', $bid, $data['nama']);
            if ($galatFoto) {
                flash(implode('<br>', array_map('e', $galatFoto)), 'galat');
            }
        }
        kembali_ke('aset_barang', ['aksi' => 'lihat', 'id' => $bid]);
    }

    $b = $bid ? row('SELECT * FROM aset_barang WHERE id = ?', [$bid]) : null;
    if (!$b) {
        tolak_akses();
    }

    if ($op === 'foto_unggah') {
        [$n, $galat] = aset_simpan_foto($_FILES['foto'] ?? [], 'barang_id', $bid, post('keterangan'));
        if ($n > 0) {
            flash($n . ' foto ditambahkan.');
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } elseif ($n === 0) {
            flash('Pilih minimal satu foto (JPG, PNG, atau WebP).', 'warn');
        }
        kembali_ke('aset_barang', ['aksi' => 'lihat', 'id' => $bid]);
    } elseif ($op === 'foto_hapus') {
        aset_hapus_foto_satu((int) post('foto_id'), 'barang_id', $bid);
        flash('Foto dihapus.');
        kembali_ke('aset_barang', ['aksi' => 'lihat', 'id' => $bid]);
    } elseif ($op === 'hapuskan' || $op === 'aktifkan') {
        if ($op === 'hapuskan') {
            kembali_ke('aset_penghapusan');
        }
        q('DELETE FROM aset_penghapusan WHERE barang_id = ?', [$bid]);
        update('aset_barang', ['status' => 'aktif', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$bid]);
        catat_log($op . '_aset_barang', 'aset_barang', $bid, $b['nama']);
        flash($op === 'hapuskan' ? 'Barang dikeluarkan dari inventaris aktif (dihapuskan).' : 'Barang diaktifkan kembali.', 'info');
        kembali_ke('aset_barang', ['aksi' => 'lihat', 'id' => $bid]);
    } elseif ($op === 'hapus') {
        aset_hapus_semua_foto('barang_id', $bid);
        q('DELETE FROM aset_barang WHERE id = ?', [$bid]);
        catat_log('hapus_aset_barang', 'aset_barang', $bid, $b['nama']);
        flash('Barang <b>' . e($b['nama']) . '</b> dihapus permanen beserta fotonya.');
        kembali_ke('aset_barang');
    }
    kembali_ke('aset_barang');
}

$modalBarang = static function (int $ruangAwal): void { ?>
<div class="tirai" id="tiraiBarang" hidden>
  <div class="modal">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Barang</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-grid">
          <div class="form-baris penuh"><label class="fl" for="bNama">Nama barang <span class="wajib">*</span></label>
            <input type="text" id="bNama" name="nama" maxlength="200" required placeholder="Meja dosen / Proyektor / AC Split 1,5 PK"></div>
          <div class="form-baris"><label class="fl" for="bKode">Kode inventaris</label>
            <input type="text" id="bKode" name="kode" maxlength="50" placeholder="Kosongkan untuk dibuat otomatis"></div>
          <div class="form-baris"><label class="fl" for="bKat">Kategori</label>
            <select id="bKat" name="kategori"><?= pilihan(aset_kategori_formulir(), 'furnitur') ?></select></div>
          <div class="form-baris"><label class="fl" for="bRuang">Lokasi / ruangan</label>
            <select id="bRuang" name="ruang_id"><?= opsi_ruang($ruangAwal, '— Belum ditempatkan —') ?></select></div>
          <div class="form-baris"><label class="fl" for="bPosisi">Posisi / rak</label><input type="text" id="bPosisi" name="posisi" maxlength="100" placeholder="Lemari A / sudut kanan"></div>
          <div class="form-baris"><label class="fl" for="bKond">Kondisi</label>
            <select id="bKond" name="kondisi"><?php foreach (ASET_KONDISI as $k => $d): ?><option value="<?= $k ?>"><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="bMerek">Merek</label><input type="text" id="bMerek" name="merek" maxlength="100"></div>
          <div class="form-baris"><label class="fl" for="bModel">Tipe / model</label><input type="text" id="bModel" name="model" maxlength="100"></div>
          <div class="form-baris"><label class="fl" for="bSeri">Nomor seri</label><input type="text" id="bSeri" name="no_seri" maxlength="100"></div>
          <div class="form-baris"><label class="fl" for="bTahun">Tahun perolehan</label><input type="number" id="bTahun" name="tahun" min="1950" max="<?= (int) date('Y') + 1 ?>"></div>
          <div class="form-baris"><label class="fl" for="bJumlah">Jumlah</label><input type="number" id="bJumlah" name="jumlah" min="1" max="100000" value="1"></div>
          <div class="form-baris"><label class="fl" for="bSatuan">Satuan</label><input type="text" id="bSatuan" name="satuan" maxlength="20" value="unit"></div>
          <div class="form-baris"><label class="fl" for="bHarga">Harga perolehan per unit (Rp)</label>
            <input type="text" id="bHarga" name="harga" inputmode="numeric" maxlength="18" placeholder="2500000"></div>
          <div class="form-baris"><label class="fl" for="bDana">Sumber dana</label><input type="text" id="bDana" name="sumber_dana" maxlength="100" placeholder="Yayasan / hibah / APBN"></div>
        </div>
        <div class="form-baris"><label class="fl" for="bKet">Keterangan</label><textarea id="bKet" name="keterangan" rows="2" maxlength="3000"></textarea></div>
        <div class="form-baris"><label class="fl" for="bFoto">Foto barang (opsional, boleh lebih dari satu)</label>
          <input type="file" id="bFoto" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php };

$dataModal = static function (array $b): string {
    return e(json_encode([
        'id' => (int) $b['id'], 'kode' => $b['kode'], 'nama' => $b['nama'], 'kategori' => $b['kategori'], 'merek' => $b['merek'], 'model' => $b['model'],
        'no_seri' => $b['no_seri'], 'ruang_id' => $b['ruang_id'], 'posisi' => $b['posisi'], 'jumlah' => $b['jumlah'], 'satuan' => $b['satuan'], 'tahun' => $b['tahun'],
        'harga' => $b['harga'] !== null ? (string) (int) round((float) $b['harga']) : '', 'sumber_dana' => $b['sumber_dana'], 'kondisi' => $b['kondisi'],
        'keterangan' => $b['keterangan'],
    ]));
};

if ($aksi === 'lihat') {
    $b = row('SELECT b.*, r.nama AS ruang_nama, r.kode AS ruang_kode FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE b.id = ?', [$id]);
    if (!$b) {
        flash('Barang tidak ditemukan.', 'galat');
        kembali_ke('aset_barang');
    }
    $subjudul = $b['kode'];
    $fotos = aset_foto_daftar('barang_id', $id);
    $rusak = rows('SELECT * FROM aset_kerusakan WHERE barang_id = ? ORDER BY tanggal_lapor DESC, id DESC LIMIT 20', [$id]);
    $riwayatPindah = rows(
        "SELECT m.*, dr.nama AS dari_nama, kr.nama AS ke_nama, p.nama AS oleh_nama FROM aset_mutasi m
           LEFT JOIN aset_ruang dr ON dr.id = m.dari_ruang_id LEFT JOIN aset_ruang kr ON kr.id = m.ke_ruang_id LEFT JOIN pengguna p ON p.id = m.dibuat_oleh
          WHERE m.barang_id = ? ORDER BY m.tanggal DESC, m.id DESC LIMIT 50",
        [$id]
    );
    $rawat = $b['kategori'] === 'ac'
        ? rows("SELECT * FROM aset_perawatan WHERE jenis = 'ac' AND barang_id = ? ORDER BY tanggal DESC, id DESC LIMIT 20", [$id]) : [];
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <a class="btn btn-garis" href="<?= e(url('aset_barang', $b['ruang_id'] ? ['ruang' => $b['ruang_id']] : [])) ?>"><?= ikon('kembali', 15) ?> Daftar barang</a>
      <button class="btn btn-garis" type="button" data-buka="tiraiBarang" data-judul="Ubah barang" data-isi='<?= $dataModal($b) ?>'><?= ikon('sunting', 15) ?> Ubah data</button>
      <?php if ($b['status'] === 'aktif'): ?>
        <a class="btn btn-garis" href="<?= e(url('aset_penghapusan')) ?>">Hapuskan dari inventaris</a>
      <?php else: ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="aktifkan"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-garis" type="submit">Aktifkan kembali</button></form>
      <?php endif; ?>
      <form method="post" data-konfirmasi="Hapus permanen barang ini beserta fotonya, riwayat perawatan, dan jadwalnya? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?>
        <input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= $id ?>"><button class="btn btn-garis" type="submit"><?= ikon('hapus', 15) ?> Hapus permanen</button></form>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= e($b['nama']) ?></h2>
        <p class="ket"><code><?= e($b['kode']) ?></code> · <?= e(aset_kategori(true)[$b['kategori']] ?? '-') ?> <?= $b['status'] === 'dihapuskan' ? lencana('Dihapuskan', 'abu') : '' ?></p></div>
        <div><?= aset_lencana_kondisi($b['kondisi']) ?></div></div>
      <div class="kartu-isi">
        <div class="info-aset">
          <div><span>Posisi / rak</span><b><?= e($b['posisi'] ?: '-') ?></b></div>
          <div><span>Lokasi</span><b><?= $b['ruang_id'] ? '<a href="' . e(url('aset_ruang', ['aksi' => 'lihat', 'id' => $b['ruang_id']])) . '">' . e($b['ruang_nama']) . '</a>' : 'Belum ditempatkan' ?></b></div>
          <div><span>Merek / tipe</span><b><?= e(trim($b['merek'] . ' ' . $b['model']) ?: '-') ?></b></div>
          <div><span>Nomor seri</span><b><?= e($b['no_seri'] ?: '-') ?></b></div>
          <div><span>Jumlah</span><b><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></b></div>
          <div><span>Tahun perolehan</span><b><?= $b['tahun'] ?: '-' ?></b></div>
          <div><span>Harga per unit</span><b><?= e(aset_rupiah($b['harga'])) ?></b></div>
          <div><span>Sumber dana</span><b><?= e($b['sumber_dana'] ?: '-') ?></b></div>
          <div><span>Total nilai</span><b><?= $b['harga'] !== null ? e(aset_rupiah((float) $b['harga'] * (int) $b['jumlah'])) : '-' ?></b></div>
        </div>
        <?php if ($b['keterangan']): ?><p style="margin:14px 0 0"><?= nl2br(e($b['keterangan'])) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('ulang', 18) ?> Riwayat perpindahan &amp; peminjaman</h2></div>
        <div class="aksi no-cetak"><a class="btn btn-garis btn-kecil" href="<?= e(url('aset_mutasi')) ?>">Catat pemindahan</a></div></div>
      <?php if (!$riwayatPindah): ?><?= sel_kosong('Belum pernah dipindahkan atau dipinjam') ?>
      <?php else: ?>
      <div class="tabel-bungkus"><table class="tabel"><thead><tr><th>Tanggal</th><th>Jenis</th><th>Dari</th><th>Ke / peminjam</th><th>Keterangan</th><th>Dicatat oleh</th></tr></thead><tbody>
        <?php foreach ($riwayatPindah as $m): ?><tr>
          <td class="nowrap"><?= e(tanggal_pendek($m['tanggal'])) ?></td>
          <td><?= $m['jenis'] === 'pindah' ? lencana('Pindah', 'biru') : ($m['status'] === 'dikembalikan' ? lencana('Pinjam · kembali', 'hijau') : lencana('Dipinjam', 'emas')) ?></td>
          <td><?= e($m['dari_nama'] ?: '-') ?></td>
          <td><?= $m['jenis'] === 'pindah' ? '<b>' . e($m['ke_nama'] ?: '-') . '</b>' : e(trim($m['peminjam'] . ($m['unit_peminjam'] !== '' ? ' · ' . $m['unit_peminjam'] : ''))) . ($m['tanggal_kembali'] ? '<div class="kecil lemah">kembali ' . e(tanggal_pendek($m['tanggal_kembali'])) . '</div>' : '') ?></td>
          <td class="kecil"><?= e($m['keterangan'] ?: '-') ?></td>
          <td class="kecil"><?= e($m['oleh_nama'] ?: '-') ?></td>
        </tr><?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2>Label QR barang</h2></div>
        <div class="aksi no-cetak">
          <a class="btn btn-utama btn-kecil" href="<?= e(url('aset_barang', ['aksi' => 'label', 'id' => $id])) ?>" target="_blank" rel="noopener"><?= ikon('cetak', 15) ?> Cetak label</a>
          <?php if ((int) $b['jumlah'] > 1): ?><a class="btn btn-garis btn-kecil" href="<?= e(url('aset_barang', ['aksi' => 'label', 'id' => $id, 'per_unit' => 1])) ?>" target="_blank" rel="noopener">Cetak <?= angka($b['jumlah']) ?> label (per unit)</a><?php endif; ?>
        </div></div>
      <div class="kartu-isi qr-aset">
        <div class="qr-aset-gambar"><?= qr_svg(aset_url_qr($id)) ?></div>
        <div><b><?= e($b['nama']) ?></b><div><code><?= e($b['kode']) ?></code></div>
          <div class="kecil lemah"><?= e($b['ruang_nama'] ?: 'Belum ditempatkan') ?></div>
          <div class="kecil lemah qr-aset-url"><?= e(aset_url_qr($id)) ?></div></div>
      </div>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('kamera', 18) ?> Foto barang</h2><p class="ket"><?= count($fotos) ?> foto</p></div></div>
      <div class="kartu-isi">
        <?php if (!$fotos): ?><?= sel_kosong('Belum ada foto') ?><?php endif; ?>
        <div class="galeri-aset">
          <?php foreach ($fotos as $f): ?>
            <figure><a href="<?= e(aset_foto_url($f['berkas'])) ?>" target="_blank" rel="noopener"><img src="<?= e(aset_foto_url($f['berkas'])) ?>" alt="<?= e($b['nama']) ?>" loading="lazy"></a>
              <figcaption><?= e($f['keterangan'] ?: '') ?>
                <form method="post" class="no-cetak" data-konfirmasi="Hapus foto ini?"><?= csrf_field() ?><input type="hidden" name="op" value="foto_hapus"><input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="foto_id" value="<?= (int) $f['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus foto"><?= ikon('hapus', 14) ?></button></form></figcaption></figure>
          <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="unggah-aset no-cetak">
          <?= csrf_field() ?><input type="hidden" name="op" value="foto_unggah"><input type="hidden" name="id" value="<?= $id ?>">
          <div class="form-baris" style="flex:1;min-width:220px"><label class="fl" for="fotoBrg">Tambah foto (boleh banyak)</label>
            <input type="file" id="fotoBrg" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
          <div class="form-baris" style="flex:1;min-width:200px"><label class="fl" for="fotoBrgKet">Keterangan</label><input type="text" id="fotoBrgKet" name="keterangan" maxlength="200"></div>
          <button class="btn btn-utama" type="submit"><?= ikon('kamera', 15) ?> Unggah</button>
        </form>
      </div>
    </div>

    <?php if ($rawat): ?>
    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('ac', 18) ?> Riwayat perawatan AC</h2></div>
        <div class="aksi no-cetak"><a class="btn btn-garis btn-kecil" href="<?= e(url('aset_ac')) ?>">Buka Maintenance AC</a></div></div>
      <div class="tabel-bungkus"><table class="tabel"><thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Petugas</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($rawat as $w): ?><tr><td class="nowrap"><?= e(tanggal_pendek($w['tanggal'])) ?></td><td><?= e($w['kegiatan']) ?></td><td><?= e($w['petugas'] ?: '-') ?></td>
          <td><?= $w['status'] === 'selesai' ? lencana('Selesai', 'hijau') : lencana('Terjadwal', 'biru') ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
    </div>
    <?php endif; ?>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('segitiga', 18) ?> Riwayat kerusakan</h2></div>
        <div class="aksi no-cetak"><a class="btn btn-garis btn-kecil" href="<?= e(url('aset_sarana')) ?>">Buka Kerusakan Sarana</a></div></div>
      <?php if (!$rusak): ?><?= sel_kosong('Tidak ada laporan kerusakan') ?>
      <?php else: ?>
      <div class="tabel-bungkus"><table class="tabel"><thead><tr><th>Nomor</th><th>Tanggal</th><th>Masalah</th><th>Tingkat</th><th>Status</th></tr></thead><tbody>
        <?php foreach ($rusak as $k): ?><tr><td class="nowrap"><code><?= e($k['nomor']) ?></code></td><td class="nowrap"><?= e(tanggal_pendek($k['tanggal_lapor'])) ?></td><td><?= e($k['judul']) ?></td>
          <td><?= lencana_dari(ASET_TINGKAT, $k['tingkat']) ?></td><td><?= lencana_dari(ASET_STATUS_RUSAK, $k['status']) ?></td></tr><?php endforeach; ?>
      </tbody></table></div>
      <?php endif; ?>
    </div>
    <?php $modalBarang(0); return;
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fKategori = array_key_exists($_GET['kategori'] ?? '', aset_kategori(true)) ? $_GET['kategori'] : '';
$fKondisi = array_key_exists($_GET['kondisi'] ?? '', ASET_KONDISI) ? $_GET['kondisi'] : '';
$fStatus = in_array($_GET['status'] ?? 'aktif', ['aktif', 'dihapuskan', 'semua'], true) ? ($_GET['status'] ?? 'aktif') : 'aktif';
$syarat = ['1=1'];
$par = [];
if ($cari !== '') {
    $syarat[] = '(b.nama LIKE ? OR b.kode LIKE ? OR b.merek LIKE ? OR b.no_seri LIKE ?)';
    $par = array_merge($par, array_fill(0, 4, '%' . $cari . '%'));
}
if ($fKategori !== '') {
    $syarat[] = 'b.kategori = ?';
    $par[] = $fKategori;
}
if ($fKondisi !== '') {
    $syarat[] = 'b.kondisi = ?';
    $par[] = $fKondisi;
}
if ($fRuang) {
    $syarat[] = 'b.ruang_id = ?';
    $par[] = $fRuang;
}
if ($fStatus !== 'semua') {
    $syarat[] = 'b.status = ?';
    $par[] = $fStatus;
}
$where = implode(' AND ', $syarat);
if ($aksi === 'label') {
    $label = $id
        ? rows('SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE b.id = ?', [$id])
        : rows("SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE $where ORDER BY r.nama, b.nama, b.kode LIMIT 300", $par);
    $perUnit = !empty($_GET['per_unit']);
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    require APP_ROOT . '/views/label_aset.php';
    exit;
}
$total =(int) val("SELECT COUNT(*) FROM aset_barang b WHERE $where", $par, 0);
$nilai = (float) val("SELECT COALESCE(SUM(b.harga * b.jumlah), 0) FROM aset_barang b WHERE $where", $par, 0);
$daftar = rows(
    "SELECT b.*, r.nama AS ruang_nama,
            (SELECT f.berkas FROM aset_foto f WHERE f.barang_id = b.id ORDER BY f.id LIMIT 1) AS sampul
       FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id
      WHERE $where ORDER BY b.nama, b.kode LIMIT 300",
    $par
);
$penginput = aset_penginput('aset_barang', $daftar);
$asalPindah = [];
if ($daftar) {
    $idDaftar = array_map('intval', array_column($daftar, 'id'));
    foreach (rows(
        "SELECT m.barang_id, m.tanggal, dr.nama AS dari_nama FROM aset_mutasi m LEFT JOIN aset_ruang dr ON dr.id = m.dari_ruang_id
          WHERE m.jenis = 'pindah' AND m.barang_id IN (" . in_placeholder($idDaftar) . ') ORDER BY m.tanggal, m.id',
        $idDaftar
    ) as $m) {
        $asalPindah[(int) $m['barang_id']] = $m;
    }
}
$ruangFilter = $fRuang ? row('SELECT id, nama FROM aset_ruang WHERE id = ?', [$fRuang]) : null;
?>
<form method="get" class="saring">
  <input type="hidden" name="h" value="aset_barang">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nama, kode inventaris, merek, atau nomor seri"></div>
  <div class="form-baris"><label class="fl">Kategori</label><select name="kategori" data-auto-kirim><option value="">Semua kategori</option><?= pilihan(aset_kategori_formulir(), $fKategori) ?></select></div>
  <div class="form-baris"><label class="fl">Ruangan</label><select name="ruang" data-auto-kirim><?= opsi_ruang($fRuang, 'Semua ruangan', false) ?></select></div>
  <div class="form-baris"><label class="fl">Kondisi</label><select name="kondisi" data-auto-kirim><option value="">Semua kondisi</option>
    <?php foreach (ASET_KONDISI as $k => $d): ?><option value="<?= $k ?>" <?= $fKondisi === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
  <div class="form-baris"><label class="fl">Status</label><select name="status" data-auto-kirim>
    <option value="aktif" <?= $fStatus === 'aktif' ? 'selected' : '' ?>>Aktif</option><option value="dihapuskan" <?= $fStatus === 'dihapuskan' ? 'selected' : '' ?>>Dihapuskan</option><option value="semua" <?= $fStatus === 'semua' ? 'selected' : '' ?>>Semua</option></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('aset', 18) ?> Inventaris barang<?= $ruangFilter ? ' · ' . e($ruangFilter['nama']) : '' ?></h2>
      <p class="ket"><?= angka($total) ?> jenis barang · total nilai perolehan <?= e(aset_rupiah($nilai)) ?><?= $total > 300 ? ' · menampilkan 300 pertama, persempit pencarian' : '' ?></p></div>
    <div class="aksi no-cetak"><a class="btn btn-garis btn-kecil" href="<?= e(url('aset_foto_cek')) ?>" title="Periksa mengapa foto tidak tampil"><?= ikon('kamera', 15) ?> Cek foto</a>
      <a class="btn btn-garis btn-kecil" target="_blank" rel="noopener" title="Cetak label QR untuk barang yang sedang ditampilkan"
        href="<?= e(url('aset_barang', array_filter(['aksi' => 'label', 'cari' => $cari, 'kategori' => $fKategori, 'kondisi' => $fKondisi, 'ruang' => $fRuang ?: null, 'status' => $fStatus]))) ?>"><?= ikon('cetak', 15) ?> Cetak label QR</a>
      <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiBarang" data-reset data-judul="Tambah barang"
        data-isi='<?= e(json_encode(['id' => '', 'kategori' => 'furnitur', 'kondisi' => 'baik', 'jumlah' => '1', 'satuan' => 'unit', 'ruang_id' => $fRuang ?: ''])) ?>'><?= ikon('tambah', 15) ?> Tambah barang</button></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong('Belum ada barang') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th style="width:64px">Foto</th><th>Barang</th><th>Kategori</th><th>Ruangan</th><th class="angka">Jumlah</th><th>Tahun</th><th class="angka">Harga/unit</th><th>Kondisi</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $b): ?>
      <tr>
        <td><?php if ($b['sampul']): ?><img class="mini-aset kecil" src="<?= e(aset_foto_url($b['sampul'])) ?>" alt="" loading="lazy"><?php else: ?><span class="mini-aset kecil kosong"><?= ikon('aset', 16) ?></span><?php endif; ?></td>
        <td><a href="<?= e(url('aset_barang', ['aksi' => 'lihat', 'id' => $b['id']])) ?>"><b><?= e($b['nama']) ?></b></a>
          <div class="kecil lemah"><code><?= e($b['kode']) ?></code><?= $b['merek'] || $b['model'] ? ' · ' . e(trim($b['merek'] . ' ' . $b['model'])) : '' ?></div></td>
        <td><?= e(aset_kategori(true)[$b['kategori']] ?? '-') ?></td>
        <td class="kecil"><?= $b['ruang_nama'] ? e($b['ruang_nama']) : '<span class="lemah">-</span>' ?>
          <?php if ($ap = $asalPindah[(int) $b['id']] ?? null): ?><div class="lemah">Pindahan dari <?= e($ap['dari_nama'] ?: 'tanpa ruangan') ?>, <?= e(tanggal_pendek($ap['tanggal'])) ?></div><?php endif; ?></td>
        <td class="angka"><?= angka($b['jumlah']) ?> <?= e($b['satuan']) ?></td>
        <td class="tengah"><?= $b['tahun'] ?: '-' ?></td>
        <td class="angka uang"><?= $b['harga'] !== null ? e(angka($b['harga'])) : '-' ?></td>
        <td><?= $b['status'] === 'dihapuskan' ? lencana('Dihapuskan', 'abu') : aset_lencana_kondisi($b['kondisi']) ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $b['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiBarang" data-judul="Ubah barang" data-isi='<?= $dataModal($b) ?>'><?= ikon('sunting', 15) ?></button>
          <a class="btn btn-hantu btn-ikon" href="<?= e(url('aset_barang', ['aksi' => 'lihat', 'id' => $b['id']])) ?>" title="Lihat"><?= ikon('mata', 15) ?></a>
          <form method="post" data-konfirmasi="Hapus permanen barang <?= e($b['nama']) ?> beserta fotonya? Tindakan ini tidak dapat dibatalkan."><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $b['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php $modalBarang($fRuang); ?>
