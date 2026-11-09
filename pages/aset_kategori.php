<?php
$dilindungi = ['ac', 'lainnya'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $kid = (int) post('id');
    $lama = $kid ? row('SELECT * FROM aset_kategori WHERE id = ?', [$kid]) : null;

    if ($op === 'simpan') {
        $kode = $lama ? $lama['kode'] : preg_replace('/[^a-z0-9_]/', '', strtolower(str_replace(['-', ' '], '_', post('kode'))));
        $umur = post('umur_tahun');
        $data = [
            'nama'       => mb_substr(post('nama'), 0, 100),
            'umur_tahun' => $umur !== '' && ctype_digit($umur) && (int) $umur >= 1 && (int) $umur <= 60 ? (int) $umur : null,
            'aktif'      => post('aktif', '1') === '0' && !in_array($kode, $dilindungi, true) ? 0 : 1,
        ];
        $galat = [];
        if ($data['nama'] === '') {
            $galat[] = 'Nama kategori wajib diisi.';
        }
        if (!$lama && (strlen($kode) < 2 || strlen($kode) > 30)) {
            $galat[] = 'Kode kategori 2-30 karakter (huruf kecil, angka, garis bawah).';
        }
        if (!$lama && (int) val('SELECT COUNT(*) FROM aset_kategori WHERE kode = ?', [$kode], 0) > 0) {
            $galat[] = 'Kode kategori sudah ada.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('aset_kategori');
        }
        if ($lama) {
            update('aset_kategori', $data, 'id = ?', [$kid]);
            flash('Kategori <b>' . e($data['nama']) . '</b> diperbarui.');
        } else {
            insert('aset_kategori', $data + ['kode' => $kode]);
            flash('Kategori <b>' . e($data['nama']) . '</b> ditambahkan.');
        }
        catat_log('simpan_aset_kategori', 'aset_kategori', $kid, $data['nama']);
    } elseif ($op === 'hapus' && $lama) {
        $dipakai = (int) val('SELECT COUNT(*) FROM aset_barang WHERE kategori = ?', [$lama['kode']], 0)
                 + (int) val('SELECT COUNT(*) FROM aset_pengajuan WHERE kategori = ?', [$lama['kode']], 0);
        if (in_array($lama['kode'], $dilindungi, true)) {
            flash('Kategori bawaan tidak dapat dihapus.', 'warn');
        } elseif ($dipakai > 0) {
            flash('Kategori masih dipakai ' . $dipakai . ' data. Nonaktifkan saja agar tidak muncul di pilihan.', 'warn');
        } else {
            q('DELETE FROM aset_kategori WHERE id = ?', [$kid]);
            catat_log('hapus_aset_kategori', 'aset_kategori', $kid, $lama['nama']);
            flash('Kategori dihapus.');
        }
    } else {
        tolak_akses();
    }
    kembali_ke('aset_kategori');
}

$daftar = rows(
    "SELECT k.*, (SELECT COUNT(*) FROM aset_barang b WHERE b.kategori = k.kode) AS jml_barang,
            (SELECT COALESCE(SUM(b.harga * b.jumlah), 0) FROM aset_barang b WHERE b.kategori = k.kode AND b.status = 'aktif') AS nilai
       FROM aset_kategori k ORDER BY k.aktif DESC, k.nama"
);
?>
<div class="kartu">
  <div class="kartu-kepala">
    <div><h2>Kategori aset</h2><p class="ket"><?= count($daftar) ?> kategori</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiKategori" data-reset data-judul="Tambah kategori"
        data-isi='{"id":"","aktif":"1"}'><?= ikon('tambah', 15) ?> Tambah kategori</button></div>
  </div>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Kode</th><th>Nama kategori</th><th class="angka">Umur ekonomis</th><th class="angka">Barang</th><th class="angka">Nilai perolehan</th><th>Status</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $k): ?>
      <tr>
        <td class="nowrap"><code><?= e($k['kode']) ?></code></td>
        <td><b><?= e($k['nama']) ?></b></td>
        <td class="angka"><?= $k['umur_tahun'] ? (int) $k['umur_tahun'] . ' tahun' : '-' ?></td>
        <td class="angka"><?= angka($k['jml_barang']) ?></td>
        <td class="angka uang"><?= e(aset_rupiah($k['nilai'])) ?></td>
        <td><?= $k['aktif'] ? '<span class="status-titik">Aktif</span>' : lencana('Nonaktif') ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiKategori" data-judul="Ubah kategori"
            data-isi='<?= e(json_encode(['id' => (int) $k['id'], 'kode' => $k['kode'], 'nama' => $k['nama'], 'umur_tahun' => $k['umur_tahun'] ?? '', 'aktif' => (string) $k['aktif']])) ?>'><?= ikon('sunting', 15) ?></button>
          <?php if (!in_array($k['kode'], $dilindungi, true)): ?>
          <form method="post" data-konfirmasi="Hapus kategori <?= e($k['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="tirai" id="tiraiKategori" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Kategori</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="kNama">Nama kategori <span class="wajib">*</span></label><input type="text" id="kNama" name="nama" maxlength="100" required></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="kKode">Kode</label><input type="text" id="kKode" name="kode" maxlength="30" placeholder="mis. meubel"></div>
          <div class="form-baris"><label class="fl" for="kUmur">Umur ekonomis (tahun)</label><input type="number" id="kUmur" name="umur_tahun" min="1" max="60"></div>
        </div>
        <label class="cek"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1" checked> Kategori aktif</label>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
