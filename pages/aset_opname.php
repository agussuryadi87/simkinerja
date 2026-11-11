<?php
$aksi = $_GET['aksi'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$sekarang = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $oid = (int) post('id');

    if ($op === 'mulai') {
        $ruang = row('SELECT * FROM aset_ruang WHERE id = ?', [(int) post('ruang_id')]);
        $tgl = post('tanggal');
        if (!$ruang || !tanggal_sah($tgl)) {
            flash('Pilih ruangan dan tanggal opname yang sah.', 'galat');
            kembali_ke('aset_opname');
        }
        $berjalan = row("SELECT id, nomor FROM aset_opname WHERE ruang_id = ? AND status = 'berjalan' LIMIT 1", [$ruang['id']]);
        if ($berjalan) {
            flash('Ruangan ini masih memiliki opname yang belum selesai (' . e($berjalan['nomor']) . '). Selesaikan atau hapus dahulu.', 'warn');
            kembali_ke('aset_opname', ['aksi' => 'lihat', 'id' => $berjalan['id']]);
        }
        $barang = rows("SELECT id, kode, nama, jumlah FROM aset_barang WHERE ruang_id = ? AND status = 'aktif' ORDER BY nama, kode", [$ruang['id']]);
        if (!$barang) {
            flash('Ruangan ini belum memiliki barang aktif untuk diopname.', 'warn');
            kembali_ke('aset_opname');
        }
        db()->beginTransaction();
        $oid = insert('aset_opname', ['nomor' => aset_nomor_dokumen('aset_opname', 'OPN', 'tanggal', $tgl), 'tanggal' => $tgl, 'ruang_id' => $ruang['id'],
            'petugas' => mb_substr(post('petugas'), 0, 150), 'status' => 'berjalan', 'dibuat_oleh' => uid()]);
        foreach ($barang as $b) {
            insert('aset_opname_item', ['opname_id' => $oid, 'barang_id' => $b['id'], 'kode' => $b['kode'], 'nama' => $b['nama'], 'jumlah_sistem' => $b['jumlah']]);
        }
        db()->commit();
        catat_log('mulai_aset_opname', 'aset_opname', $oid, $ruang['nama']);
        flash('Opname dimulai dengan ' . count($barang) . ' barang. Isi jumlah dan kondisi hasil pemeriksaan fisik.');
        kembali_ke('aset_opname', ['aksi' => 'lihat', 'id' => $oid]);
    }

    $o = $oid ? row('SELECT * FROM aset_opname WHERE id = ?', [$oid]) : null;
    if (!$o) {
        tolak_akses();
    }
    $balik = static function () use ($oid): void {
        kembali_ke('aset_opname', ['aksi' => 'lihat', 'id' => $oid]);
    };

    if ($op === 'hapus') {
        q('DELETE FROM aset_opname WHERE id = ?', [$oid]);
        flash('Opname dihapus.');
        kembali_ke('aset_opname');
    }
    if ($o['status'] !== 'berjalan') {
        flash('Opname yang sudah selesai tidak dapat diubah.', 'warn');
        $balik();
    }

    $items = rows('SELECT * FROM aset_opname_item WHERE opname_id = ?', [$oid]);
    $kosong = 0;
    foreach ($items as $it) {
        $iid = (int) $it['id'];
        $jml = $_POST['jumlah'][$iid] ?? '';
        $jml = is_string($jml) && ctype_digit(trim($jml)) ? (int) trim($jml) : null;
        $kond = (string) ($_POST['kondisi'][$iid] ?? '');
        $kond = array_key_exists($kond, ASET_KONDISI) ? $kond : null;
        $cat = mb_substr(trim((string) ($_POST['catatan'][$iid] ?? '')), 0, 255);
        q('UPDATE aset_opname_item SET jumlah_fisik = ?, kondisi_fisik = ?, catatan = ? WHERE id = ?', [$jml, $kond, $cat, $iid]);
        if ($jml === null) {
            $kosong++;
        }
    }
    update('aset_opname', ['petugas' => mb_substr(post('petugas'), 0, 150), 'catatan' => mb_substr(post_teks('catatan_umum'), 0, 3000)], 'id = ?', [$oid]);

    if ($op === 'selesai') {
        if ($kosong > 0) {
            flash($kosong . ' barang belum diisi jumlah fisiknya. Lengkapi dahulu sebelum menyelesaikan opname.', 'warn');
            $balik();
        }
        foreach (rows('SELECT * FROM aset_opname_item WHERE opname_id = ? AND barang_id IS NOT NULL', [$oid]) as $it) {
            $set = [];
            if ((int) $it['jumlah_fisik'] === 0) {
                $set['kondisi'] = 'hilang';
            } else {
                $set['jumlah'] = (int) $it['jumlah_fisik'];
                if ($it['kondisi_fisik']) {
                    $set['kondisi'] = $it['kondisi_fisik'];
                }
            }
            update('aset_barang', $set + ['diubah' => date('Y-m-d H:i:s')], 'id = ?', [$it['barang_id']]);
        }
        update('aset_opname', ['status' => 'selesai', 'selesai_pada' => date('Y-m-d H:i:s')], 'id = ?', [$oid]);
        catat_log('selesai_aset_opname', 'aset_opname', $oid, $o['nomor']);
        flash('Opname selesai. Jumlah dan kondisi di inventaris sudah diperbarui sesuai hasil fisik.');
    } else {
        flash('Isian opname tersimpan.');
    }
    $balik();
}

if ($aksi === 'lihat') {
    $o = row('SELECT o.*, r.nama AS ruang_nama, r.kode AS ruang_kode FROM aset_opname o LEFT JOIN aset_ruang r ON r.id = o.ruang_id WHERE o.id = ?', [$id]);
    if (!$o) {
        flash('Opname tidak ditemukan.', 'galat');
        kembali_ke('aset_opname');
    }
    $subjudul = $o['nomor'];
    $items = rows('SELECT * FROM aset_opname_item WHERE opname_id = ? ORDER BY nama, kode', [$id]);
    $berjalan = $o['status'] === 'berjalan';
    $sesuai = $lebih = $kurang = $rusak = $belum = 0;
    foreach ($items as $it) {
        if ($it['jumlah_fisik'] === null) {
            $belum++;
            continue;
        }
        $d = (int) $it['jumlah_fisik'] - (int) $it['jumlah_sistem'];
        $d === 0 ? $sesuai++ : ($d > 0 ? $lebih++ : $kurang++);
        if (in_array($it['kondisi_fisik'], ['rusak_ringan', 'rusak_berat', 'hilang'], true)) {
            $rusak++;
        }
    }
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <a class="btn btn-garis" href="<?= e(url('aset_opname')) ?>"><?= ikon('kembali', 15) ?> Daftar opname</a>
      <form method="post" data-konfirmasi="Hapus opname ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-garis" type="submit"><?= ikon('hapus', 15) ?> Hapus</button></form>
    </div>

    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="id" value="<?= $id ?>">
      <div class="kartu">
        <div class="kartu-kepala"><div><h2><?= e($o['ruang_nama'] ?: 'Ruangan') ?></h2>
          <p class="ket"><code><?= e($o['nomor']) ?></code> · <?= e(tanggal_id($o['tanggal'])) ?> · <?= $berjalan ? lencana('Berjalan', 'biru') : lencana('Selesai', 'hijau') ?></p></div>
          <?php if ($berjalan): ?><div class="form-baris" style="margin:0;min-width:220px"><label class="fl" for="opPetugas">Petugas</label><input type="text" id="opPetugas" name="petugas" maxlength="150" value="<?= e($o['petugas']) ?>"></div>
          <?php else: ?><div class="kecil">Petugas: <b><?= e($o['petugas'] ?: '-') ?></b></div><?php endif; ?></div>
        <div class="tabel-bungkus"><table class="tabel">
          <thead><tr><th>Kode</th><th>Barang</th><th class="angka">Di sistem</th><th style="width:110px">Jumlah fisik</th><th style="width:150px">Kondisi fisik</th><th>Selisih</th><th>Catatan</th></tr></thead>
          <tbody>
          <?php foreach ($items as $it): $iid = (int) $it['id'];
              $d = $it['jumlah_fisik'] === null ? null : (int) $it['jumlah_fisik'] - (int) $it['jumlah_sistem']; ?>
            <tr><td class="nowrap"><code><?= e($it['kode']) ?></code></td><td><b><?= e($it['nama']) ?></b></td>
              <td class="angka"><?= angka($it['jumlah_sistem']) ?></td>
              <?php if ($berjalan): ?>
                <td><input type="number" min="0" name="jumlah[<?= $iid ?>]" value="<?= $it['jumlah_fisik'] ?>" style="padding:6px 8px;width:100%"></td>
                <td><select name="kondisi[<?= $iid ?>]" style="padding:6px 8px"><option value="">— tidak berubah —</option>
                  <?php foreach (ASET_KONDISI as $k => $kd): ?><option value="<?= $k ?>" <?= $it['kondisi_fisik'] === $k ? 'selected' : '' ?>><?= e($kd['label']) ?></option><?php endforeach; ?></select></td>
                <td class="nowrap"><?= $d === null ? '-' : ($d === 0 ? '0' : ($d > 0 ? '+' . $d : (string) $d)) ?></td>
                <td><input type="text" name="catatan[<?= $iid ?>]" maxlength="255" value="<?= e($it['catatan']) ?>" style="padding:6px 8px;width:100%" placeholder="Opsional"></td>
              <?php else: ?>
                <td class="angka"><?= $it['jumlah_fisik'] === null ? '-' : angka($it['jumlah_fisik']) ?></td>
                <td><?= $it['kondisi_fisik'] ? aset_lencana_kondisi($it['kondisi_fisik']) : '<span class="lemah">-</span>' ?></td>
                <td class="nowrap<?= $d ? ' teks-merah' : '' ?>"><?= $d === null ? '-' : ($d === 0 ? '0' : ($d > 0 ? '+' . $d : (string) $d)) ?></td>
                <td class="kecil"><?= e($it['catatan'] ?: '-') ?></td>
              <?php endif; ?>
            </tr>
          <?php endforeach; ?>
          </tbody></table></div>
        <?php if ($berjalan): ?>
        <div class="kartu-kaki no-cetak">
          <button class="btn btn-garis" type="submit" name="op" value="simpan"><?= ikon('simpan', 15) ?> Simpan sementara</button>
          <button class="btn btn-utama" type="submit" name="op" value="selesai" data-konfirmasi="Selesaikan opname dan perbarui jumlah serta kondisi di inventaris sesuai hasil fisik?">Selesaikan opname</button>
        </div>
        <?php endif; ?>
      </div>
    </form>
    <?php return;
}

$daftar = rows(
    "SELECT o.*, r.nama AS ruang_nama,
            (SELECT COUNT(*) FROM aset_opname_item i WHERE i.opname_id = o.id) AS jml_item,
            (SELECT COUNT(*) FROM aset_opname_item i WHERE i.opname_id = o.id AND i.jumlah_fisik IS NOT NULL AND i.jumlah_fisik <> i.jumlah_sistem) AS jml_selisih
       FROM aset_opname o LEFT JOIN aset_ruang r ON r.id = o.ruang_id ORDER BY o.tanggal DESC, o.id DESC LIMIT 300"
);
$penginput = aset_penginput('aset_opname', $daftar);
?>
<div class="kartu">
  <div class="kartu-kepala">
    <div><h2>Stock opname</h2><p class="ket"><?= count($daftar) ?> sesi</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiOpname" data-reset data-judul="Mulai opname"
        data-isi='<?= e(json_encode(['tanggal' => $sekarang, 'ruang_id' => '', 'petugas' => user()['nama'] ?? ''])) ?>'><?= ikon('tambah', 15) ?> Mulai opname</button></div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong('Belum ada opname') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Nomor / tanggal</th><th>Ruangan</th><th>Petugas</th><th class="angka">Barang</th><th class="angka">Selisih</th><th>Status</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $o): ?>
      <tr>
        <td class="nowrap"><a href="<?= e(url('aset_opname', ['aksi' => 'lihat', 'id' => $o['id']])) ?>"><code><?= e($o['nomor']) ?></code></a><div class="kecil lemah"><?= e(tanggal_pendek($o['tanggal'])) ?></div></td>
        <td><b><?= e($o['ruang_nama'] ?: '-') ?></b></td>
        <td class="kecil"><?= e($o['petugas'] ?: '-') ?></td>
        <td class="angka"><?= angka($o['jml_item']) ?></td>
        <td class="angka<?= (int) $o['jml_selisih'] ? ' teks-merah' : '' ?>"><?= $o['status'] === 'selesai' || (int) $o['jml_selisih'] ? angka($o['jml_selisih']) : '-' ?></td>
        <td><?= $o['status'] === 'selesai' ? lencana('Selesai', 'hijau') : lencana('Berjalan', 'biru') ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $o['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris">
          <a class="btn btn-hantu btn-ikon" href="<?= e(url('aset_opname', ['aksi' => 'lihat', 'id' => $o['id']])) ?>" title="<?= $o['status'] === 'selesai' ? 'Lihat hasil' : 'Isi / ubah hasil' ?>"><?= ikon($o['status'] === 'selesai' ? 'mata' : 'sunting', 15) ?></a>
          <form method="post" data-konfirmasi="Hapus opname <?= e($o['nomor']) ?> beserta seluruh hasil pemeriksaannya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $o['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiOpname" hidden><div class="modal sempit"><form method="post">
  <?= csrf_field() ?><input type="hidden" name="op" value="mulai">
  <div class="modal-kepala"><h2>Mulai opname</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
  <div class="modal-isi">
    <div class="form-baris"><label class="fl" for="opRuang">Ruangan <span class="wajib">*</span></label><select id="opRuang" name="ruang_id" required><?= opsi_ruang(0, '— Pilih ruangan —') ?></select></div>
    <div class="form-grid">
      <div class="form-baris"><label class="fl" for="opTgl">Tanggal</label><input type="date" id="opTgl" name="tanggal" required></div>
      <div class="form-baris"><label class="fl" for="opPt">Petugas</label><input type="text" id="opPt" name="petugas" maxlength="150"></div>
    </div>
  </div>
  <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama">Mulai</button></div>
</form></div></div>
