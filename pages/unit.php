<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'simpan') {
        $id = (int) post('id');
        $data = [
            'kode'     => mb_strtoupper(mb_substr(post('kode'), 0, 20)),
            'nama'     => mb_substr(post('nama'), 0, 150),
            'induk_id' => (int) post('induk_id') ?: null,
            'urut'     => (int) post('urut'),
            'aktif'    => post('aktif') === '1' ? 1 : 0,
        ];
        $galat = [];
        if ($data['kode'] === '' || $data['nama'] === '') {
            $galat[] = 'Kode dan nama unit wajib diisi.';
        } elseif ((int) val('SELECT COUNT(*) FROM unit WHERE kode = ? AND id <> ?', [$data['kode'], $id], 0) > 0) {
            $galat[] = 'Kode unit sudah dipakai.';
        }
        if ($id && $data['induk_id'] && in_array($data['induk_id'], unit_turunan($id), true)) {
            $galat[] = 'Unit induk tidak boleh unit itu sendiri atau sub-unitnya.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('unit');
        }
        if ($id) {
            update('unit', $data, 'id = ?', [$id]);
        } else {
            $id = insert('unit', $data);
        }
        catat_log('simpan_unit', 'unit', $id, $data['nama']);
        flash('Unit <b>' . e($data['nama']) . '</b> tersimpan.');
    } elseif ($op === 'hapus') {
        $id = (int) post('id');
        $pakai = (int) val('SELECT (SELECT COUNT(*) FROM pengguna WHERE unit_id = ?) + (SELECT COUNT(*) FROM unit WHERE induk_id = ?)', [$id, $id], 0);
        if ($pakai > 0) {
            flash('Unit masih memiliki pegawai atau sub-unit. Nonaktifkan atau pindahkan terlebih dahulu.', 'warn');
        } else {
            q('DELETE FROM unit WHERE id = ?', [$id]);
            flash('Unit dihapus.');
        }
    }
    kembali_ke('unit');
}

$units = unit_semua();
$jml = [];
foreach (rows('SELECT unit_id, COUNT(*) AS n FROM pengguna WHERE aktif = 1 GROUP BY unit_id') as $r) {
    $jml[(int) $r['unit_id']] = (int) $r['n'];
}
$kepala = [];
foreach (rows("SELECT p.unit_id, p.nama FROM pengguna p JOIN peran pr ON pr.id = p.peran_id
               WHERE p.aktif = 1 AND pr.lingkup IN ('unit','semua') AND pr.sistem = 0 ORDER BY pr.urut") as $r) {
    $kepala[(int) $r['unit_id']] ??= $r['nama'];
}
?>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('gedung', 18) ?> Struktur unit kerja</h2></div>
    <div class="aksi"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiUnit" data-reset data-judul="Tambah unit"
        data-isi='{"id":"","induk_id":"1","aktif":"1","urut":"10"}'><?= ikon('tambah', 15) ?> Tambah unit</button></div>
  </div>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Kode</th><th>Nama unit</th><th>Pimpinan</th><th class="angka">Pegawai</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($units as $un): ?>
      <tr>
        <td class="nowrap"><code><?= e($un['kode']) ?></code></td>
        <td style="padding-left:<?= 14 + (int) $un['tingkat'] * 24 ?>px"><?= $un['tingkat'] ? '<span class="lemah">└ </span>' : '' ?><b><?= e($un['nama']) ?></b></td>
        <td><?= e($kepala[(int) $un['id']] ?? '-') ?></td>
        <td class="angka"><?= $jml[(int) $un['id']] ?? 0 ?></td>
        <td><?= $un['aktif'] ? '<span class="status-titik">Aktif</span>' : lencana('Nonaktif') ?></td>
        <td><div class="aksi-baris">
          <button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiUnit" data-judul="Ubah unit"
                  data-isi='<?= e(json_encode(['id' => (int) $un['id'], 'kode' => $un['kode'], 'nama' => $un['nama'],
                      'induk_id' => $un['induk_id'] ?? '', 'urut' => (string) $un['urut'], 'aktif' => (string) $un['aktif']])) ?>'><?= ikon('sunting', 15) ?></button>
          <form method="post" data-konfirmasi="Hapus unit <?= e($un['nama']) ?>?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus">
            <input type="hidden" name="id" value="<?= (int) $un['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="tirai" id="tiraiUnit" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Unit</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="unNama">Nama unit <span class="wajib">*</span></label>
          <input type="text" id="unNama" name="nama" maxlength="150" required></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="unKode">Kode <span class="wajib">*</span></label>
            <input type="text" id="unKode" name="kode" maxlength="20" required placeholder="BAUK-KEU"></div>
          <div class="form-baris"><label class="fl" for="unUrut">Urutan</label><input type="number" id="unUrut" name="urut" min="0" max="999"></div>
        </div>
        <div class="form-baris"><label class="fl" for="unInduk">Unit induk</label><select id="unInduk" name="induk_id"><?= opsi_unit(0, 'Unit puncak') ?></select></div>
        <label class="cek"><input type="hidden" name="aktif" value="0"><input type="checkbox" name="aktif" value="1"> Unit aktif</label>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
