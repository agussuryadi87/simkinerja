<?php
$saya = uid();
$penyetuju = in_array(user()['peran_kode'] ?? '', ['admin', 'kabiro'], true);
$tahunIni = (int) date('Y');
$daftarTahun = array_map('intval', array_column(rows('SELECT DISTINCT tahun FROM keu_rapb ORDER BY tahun DESC'), 'tahun'));
$daftarTahun = array_values(array_unique(array_merge($daftarTahun, [$tahunIni, $tahunIni + 1])));
rsort($daftarTahun);
$tahun = (int) ($_GET['tahun'] ?? $tahunIni);
if (!in_array($tahun, $daftarTahun, true)) {
    $tahun = $tahunIni;
}
$id = (int) ($_GET['id'] ?? 0);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && !$id) {
    redirect(url('rapb_prodi'));
}
$uang = static function (string $kunci): float {
    $v = str_replace(['.', ' '], '', post($kunci));
    return max(0.0, (float) str_replace(',', '.', $v));
};
$ambil = static function (int $rid): ?array {
    return $rid ? row('SELECT r.*, un.nama AS unit_nama FROM keu_rapb r LEFT JOIN unit un ON un.id = r.unit_id WHERE r.id = ?', [$rid]) : null;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $rid = (int) post('id');
    $dok = $ambil($rid);
    $balikDaftar = static function () use (&$tahun): void {
        kembali_ke('rapb', ['tahun' => $tahun]);
    };
    $balikDok = static function (int $d): void {
        kembali_ke('rapb', ['aksi' => 'lihat', 'id' => $d]);
    };

    if ($op === 'simpan_rapb') {
        $data = [
            'tahun'   => max(2000, min(2100, (int) post('tahun') ?: $tahun)),
            'unit_id' => (int) post('unit_id') ?: null,
            'nama'    => mb_substr(post('nama'), 0, 200),
            'catatan' => mb_substr(post('catatan'), 0, 1000),
        ];
        if ($data['nama'] === '') {
            flash('Nama RAPB wajib diisi.', 'galat');
            $balikDaftar();
        }
        $tahun = $data['tahun'];
        if ($dok) {
            if (!in_array($dok['status'], ['draf', 'ditolak'], true)) {
                flash('RAPB yang sudah diajukan atau disetujui tidak dapat diubah.', 'galat');
                $balikDok($rid);
            }
            update('keu_rapb', $data, 'id = ?', [$rid]);
            catat_log('ubah_rapb', 'keu_rapb', $rid, $data['nama']);
            flash('RAPB <b>' . e($data['nama']) . '</b> diperbarui.');
            $balikDok($rid);
        }
        $rid = insert('keu_rapb', $data + ['dibuat_oleh' => $saya]);
        catat_log('tambah_rapb', 'keu_rapb', $rid, $data['nama']);
        flash('RAPB <b>' . e($data['nama']) . '</b> dibuat. Tambahkan rincian pendapatan dan belanja.');
        $balikDok($rid);
    }

    if (!$dok) {
        flash('RAPB tidak ditemukan.', 'galat');
        $balikDaftar();
    }
    $tahun = (int) $dok['tahun'];

    if ($op === 'hapus_rapb') {
        if (!in_array($dok['status'], ['draf', 'ditolak'], true)) {
            flash('Hanya RAPB berstatus draf atau ditolak yang dapat dihapus.', 'galat');
            $balikDok($rid);
        }
        q('DELETE FROM keu_rapb WHERE id = ?', [$rid]);
        catat_log('hapus_rapb', 'keu_rapb', $rid, $dok['nama']);
        flash('RAPB dihapus.');
        $balikDaftar();
    }

    if ($op === 'status') {
        $baru = post('ke');
        $boleh = ($baru === 'diajukan' && in_array($dok['status'], ['draf', 'ditolak'], true))
              || (in_array($baru, ['disetujui', 'ditolak'], true) && $dok['status'] === 'diajukan' && $penyetuju)
              || ($baru === 'draf' && $dok['status'] === 'disetujui' && $penyetuju);
        if (!$boleh) {
            flash('Perubahan status tidak diizinkan.', 'galat');
            $balikDok($rid);
        }
        if ($baru === 'diajukan' && (int) val('SELECT COUNT(*) FROM keu_rapb_item WHERE rapb_id = ?', [$rid], 0) === 0) {
            flash('Isi rincian pendapatan atau belanja sebelum mengajukan.', 'galat');
            $balikDok($rid);
        }
        update('keu_rapb', ['status' => $baru, 'catatan' => $baru === 'ditolak' ? mb_substr(post('catatan'), 0, 1000) : $dok['catatan']], 'id = ?', [$rid]);
        catat_log('status_rapb', 'keu_rapb', $rid, $dok['nama'] . ' → ' . $baru);
        flash('Status RAPB menjadi <b>' . e(KEU_STATUS_RAPB[$baru]['label']) . '</b>.');
        $balikDok($rid);
    }

    if ($op === 'simpan_item') {
        $iid = (int) post('item_id');
        $lama = $iid ? row('SELECT * FROM keu_rapb_item WHERE id = ? AND rapb_id = ?', [$iid, $rid]) : null;
        $terkunci = $dok['status'] === 'disetujui';
        if ($terkunci) {
            if (!$lama) {
                flash('RAPB yang sudah disetujui tidak dapat ditambah rincian baru.', 'galat');
                $balikDok($rid);
            }
            update('keu_rapb_item', ['realisasi' => $uang('realisasi')], 'id = ?', [$iid]);
            catat_log('realisasi_rapb', 'keu_rapb_item', $iid, $lama['uraian']);
            flash('Realisasi diperbarui.');
            $balikDok($rid);
        }
        if ($dok['status'] === 'diajukan') {
            flash('RAPB yang sedang diajukan tidak dapat diubah.', 'galat');
            $balikDok($rid);
        }
        $data = [
            'jenis'     => array_key_exists(post('jenis'), KEU_JENIS_ITEM) ? post('jenis') : 'belanja',
            'kode'      => mb_substr(post('kode'), 0, 30),
            'uraian'    => mb_substr(post('uraian'), 0, 255),
            'volume'    => max(0.0, (float) str_replace(',', '.', post('volume', '1')) ?: 1),
            'satuan'    => mb_substr(post('satuan'), 0, 30),
            'harga'     => $uang('harga'),
            'realisasi' => $uang('realisasi'),
        ];
        if ($data['uraian'] === '') {
            flash('Uraian wajib diisi.', 'galat');
            $balikDok($rid);
        }
        if ($lama) {
            update('keu_rapb_item', $data, 'id = ?', [$iid]);
            catat_log('ubah_item_rapb', 'keu_rapb_item', $iid, $data['uraian']);
        } else {
            $iid = insert('keu_rapb_item', $data + ['rapb_id' => $rid]);
            catat_log('tambah_item_rapb', 'keu_rapb_item', $iid, $data['uraian']);
        }
        flash('Rincian disimpan.');
        $balikDok($rid);
    }

    if ($op === 'hapus_item' && in_array($dok['status'], ['draf', 'ditolak'], true)) {
        q('DELETE FROM keu_rapb_item WHERE id = ? AND rapb_id = ?', [(int) post('item_id'), $rid]);
        flash('Rincian dihapus.');
        $balikDok($rid);
    }
    tolak_akses();
}

$tampilDetail = ($_GET['aksi'] ?? '') === 'lihat';
$dok = $tampilDetail ? $ambil($id) : null;
if ($tampilDetail && !$dok) {
    kembali_ke('rapb', ['tahun' => $tahun]);
}
$aksiDetail = $dok !== null;
$subjudul = $aksiDetail ? $dok['nama'] : 'Tahun anggaran ' . $tahun;

if ($aksiDetail):
    $tahun = (int) $dok['tahun'];
    $item = rows('SELECT *, volume * harga AS jumlah FROM keu_rapb_item WHERE rapb_id = ? ORDER BY jenis, kode, id', [$id]);
    $tot = keu_total_rapb([$id])[$id];
    $surplus = $tot['pendapatan'] - $tot['belanja'];
    $bisaUbah = in_array($dok['status'], ['draf', 'ditolak'], true);
    $bisaRealisasi = $dok['status'] === 'disetujui';
    $st = KEU_STATUS_RAPB[$dok['status']];
    $persen = static function (float $real, float $rencana): string {
        return $rencana > 0 ? angka($real / $rencana * 100, 1) . '%' : '-';
    };
    ?>
<div class="aset-kpi">
  <div><span>Rencana pendapatan</span><b class="uang"><?= rupiah($tot['pendapatan']) ?></b><small><?= $persen($tot['real_pendapatan'], $tot['pendapatan']) ?> terealisasi</small></div>
  <div><span>Rencana belanja</span><b class="uang"><?= rupiah($tot['belanja']) ?></b><small><?= $persen($tot['real_belanja'], $tot['belanja']) ?> terealisasi</small></div>
  <div><span>Surplus / (defisit)</span><b class="uang<?= $surplus < 0 ? ' merah' : '' ?>"><?= $surplus < 0 ? '(' . rupiah(-$surplus) . ')' : rupiah($surplus) ?></b><small>rencana</small></div>
  <div><span>Status</span><b class="uang" style="padding-bottom:0"><?= lencana($st['label'], $st['warna']) ?></b><small><?= e($dok['unit_nama'] ?? 'Seluruh biro') ?> · <?= (int) $dok['tahun'] ?></small></div>
</div>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('dokumen', 18) ?> <?= e($dok['nama']) ?></h2>
      <p class="ket"><?= count($item) ?> rincian<?= $dok['status'] === 'ditolak' && $dok['catatan'] !== '' ? ' · Catatan penolakan: ' . e($dok['catatan']) : '' ?></p></div>
    <div class="aksi no-cetak">
      <a class="btn btn-garis btn-kecil" href="<?= e(url('rapb', ['tahun' => $tahun])) ?>"><?= ikon('kembali', 14) ?> Kembali</a>
      <?php if ($bisaUbah): ?>
        <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiRapb" data-judul="Ubah RAPB"
                data-isi='<?= e(json_encode(['id' => (int) $dok['id'], 'tahun' => (int) $dok['tahun'], 'unit_id' => (string) ($dok['unit_id'] ?? ''), 'nama' => $dok['nama'], 'catatan' => $dok['catatan']])) ?>'><?= ikon('sunting', 14) ?> Ubah</button>
        <button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiItem" data-reset data-judul="Tambah rincian" data-isi='{"item_id":""}'><?= ikon('tambah', 14) ?> Tambah rincian</button>
        <form method="post" class="sebaris"><?= csrf_field() ?><input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="ke" value="diajukan">
          <button class="btn btn-sukses btn-kecil" type="submit" data-konfirmasi="Ajukan RAPB ini untuk ditelaah dan disetujui? Rincian tidak dapat diubah selama pengajuan.">Ajukan</button></form>
      <?php elseif ($dok['status'] === 'diajukan' && $penyetuju): ?>
        <form method="post" class="sebaris"><?= csrf_field() ?><input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="ke" value="ditolak"><input type="hidden" name="catatan" value="">
          <button class="btn btn-garis btn-kecil" type="submit" data-konfirmasi="Tolak RAPB ini dan kembalikan untuk diperbaiki?">Tolak</button></form>
        <form method="post" class="sebaris"><?= csrf_field() ?><input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="ke" value="disetujui">
          <button class="btn btn-sukses btn-kecil" type="submit" data-konfirmasi="Setujui RAPB ini?"><?= ikon('centang', 14) ?> Setujui</button></form>
      <?php elseif ($dok['status'] === 'disetujui' && $penyetuju): ?>
        <form method="post" class="sebaris"><?= csrf_field() ?><input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="ke" value="draf">
          <button class="btn btn-garis btn-kecil" type="submit" data-konfirmasi="Buka kembali RAPB ini sebagai draf? Persetujuan akan dicabut.">Buka kembali</button></form>
      <?php endif; ?>
    </div>
  </div>
  <?php if (!$item): ?>
    <?= sel_kosong('Belum ada rincian', 'Tambahkan rincian pendapatan dan belanja.') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-rapb">
    <thead><tr><th>No</th><th>Kode</th><th>Uraian</th><th>Jenis</th><th>Volume</th><th>Harga satuan</th><th>Rencana</th><th>Realisasi</th><th>%</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($item as $n => $it): ?>
      <tr>
        <td><?= $n + 1 ?></td>
        <td class="nowrap"><?= $it['kode'] !== '' ? e($it['kode']) : '-' ?></td>
        <td class="kiri"><b><?= e($it['uraian']) ?></b></td>
        <td><?= lencana_dari(KEU_JENIS_ITEM, $it['jenis']) ?></td>
        <td class="nowrap"><?= angka_ringkas($it['volume']) ?> <?= e($it['satuan']) ?></td>
        <td class="nowrap uang"><?= rupiah($it['harga']) ?></td>
        <td class="nowrap uang"><b><?= rupiah($it['jumlah']) ?></b></td>
        <td class="nowrap uang"><?= (float) $it['realisasi'] > 0 ? rupiah($it['realisasi']) : '-' ?></td>
        <td><?= $persen((float) $it['realisasi'], (float) $it['jumlah']) ?></td>
        <td class="no-cetak"><div class="aksi-baris" style="justify-content:center">
          <?php if ($bisaUbah || $bisaRealisasi): ?>
            <button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiItem" data-judul="<?= $bisaRealisasi ? 'Ubah realisasi' : 'Ubah rincian' ?>"
                    data-isi='<?= e(json_encode(['item_id' => (int) $it['id'], 'jenis' => $it['jenis'], 'kode' => $it['kode'], 'uraian' => $it['uraian'], 'volume' => angka_ringkas($it['volume']), 'satuan' => $it['satuan'], 'harga' => (string) (int) $it['harga'], 'realisasi' => (string) (int) $it['realisasi']])) ?>'><?= ikon('sunting', 14) ?> <?= $bisaRealisasi ? 'Realisasi' : 'Edit' ?></button>
          <?php endif; ?>
          <?php if ($bisaUbah): ?>
            <form method="post" class="sebaris" data-konfirmasi="Hapus rincian ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus_item"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="item_id" value="<?= (int) $it['id'] ?>">
              <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
  <?php if ($bisaUbah): ?>
  <div class="kartu-kaki no-cetak">
    <form method="post" class="sebaris" data-konfirmasi="Hapus RAPB ini beserta seluruh rinciannya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus_rapb"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('hapus', 14) ?> Hapus RAPB</button></form>
  </div>
  <?php endif; ?>
</div>

<div class="tirai" id="tiraiItem" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan_item"><input type="hidden" name="id" value="<?= $id ?>"><input type="hidden" name="item_id" value="">
      <div class="modal-kepala"><h2>Rincian</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <?php if ($bisaRealisasi): ?>
          <div class="form-baris"><label class="fl">Uraian</label><input type="text" name="uraian" readonly></div>
          <div class="form-baris"><label class="fl" for="iReal">Realisasi (Rp)</label><input type="text" id="iReal" name="realisasi" inputmode="numeric" placeholder="0"></div>
        <?php else: ?>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="iJenis">Jenis</label><select id="iJenis" name="jenis"><?= pilihan(array_map(static function ($j) { return $j['label']; }, KEU_JENIS_ITEM), 'belanja') ?></select></div>
            <div class="form-baris"><label class="fl" for="iKode">Kode akun</label><input type="text" id="iKode" name="kode" maxlength="30" placeholder="Contoh: 5.1.01"></div>
          </div>
          <div class="form-baris"><label class="fl" for="iUraian">Uraian <span class="wajib">*</span></label><input type="text" id="iUraian" name="uraian" maxlength="255" required></div>
          <div class="form-grid-3">
            <div class="form-baris"><label class="fl" for="iVol">Volume</label><input type="text" id="iVol" name="volume" inputmode="decimal" value="1"></div>
            <div class="form-baris"><label class="fl" for="iSat">Satuan</label><input type="text" id="iSat" name="satuan" maxlength="30" placeholder="bulan, paket, orang"></div>
            <div class="form-baris"><label class="fl" for="iHarga">Harga satuan (Rp)</label><input type="text" id="iHarga" name="harga" inputmode="numeric" placeholder="0"></div>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php
    $dokUntukModal = $dok;
else:
    $dokumen = rows('SELECT r.*, un.nama AS unit_nama FROM keu_rapb r LEFT JOIN unit un ON un.id = r.unit_id WHERE r.tahun = ? ORDER BY un.urut, r.id', [$tahun]);
    $total = keu_total_rapb(array_column($dokumen, 'id'));
    $sumP = $sumB = $realB = $realP = 0.0;
    foreach ($total as $t) {
        $sumP += $t['pendapatan'];
        $sumB += $t['belanja'];
        $realP += $t['real_pendapatan'];
        $realB += $t['real_belanja'];
    }
    $label = [];
    $serP = $serB = $serR = [];
    foreach ($dokumen as $d) {
        $t = $total[(int) $d['id']];
        $label[] = mb_strimwidth($d['unit_nama'] ?? 'Biro', 0, 14, '…');
        $serP[] = $t['pendapatan'];
        $serB[] = $t['belanja'];
        $serR[] = $t['real_belanja'];
    }
    $sembunyikanDaftar = true;
    ?>
<?php if ($sembunyikanDaftar): ?>
<div class="kartu rapb-disiapkan">
  <div class="kartu-isi">
    <span class="rapb-disiapkan-ikon"><?= ikon('dokumen', 30) ?></span>
    <h2>RAPB sedang disiapkan dengan format baru</h2>
    <p>Tampilan dan data RAPB lama disembunyikan sementara. Data lama tetap tersimpan dan tidak dihapus.</p>
  </div>
</div>
<?php else: ?>
<div class="baris-tahun">
<div class="tab-kecil no-cetak">
  <?php foreach ($daftarTahun as $t): ?>
    <a href="<?= e(url('rapb', ['tahun' => $t])) ?>" class="<?= $tahun === $t ? 'aktif' : '' ?>"><?= $t ?></a>
  <?php endforeach; ?>
</div>

<div class="aset-kpi">
  <div><span>Rencana pendapatan</span><b class="uang"><?= rupiah($sumP) ?></b><small><?= count($dokumen) ?> dokumen RAPB</small></div>
  <div><span>Rencana belanja</span><b class="uang"><?= rupiah($sumB) ?></b><small><?= $sumB > 0 ? angka($realB / $sumB * 100, 1) . '% terealisasi' : 'belum ada rincian' ?></small></div>
  <div><span>Surplus / (defisit)</span><b class="uang<?= $sumP - $sumB < 0 ? ' merah' : '' ?>"><?= $sumP - $sumB < 0 ? '(' . rupiah($sumB - $sumP) . ')' : rupiah($sumP - $sumB) ?></b><small>rencana</small></div>
  <div><span>Realisasi belanja</span><b class="uang"><?= rupiah($realB) ?></b><small>seluruh unit</small></div>
</div>
</div>

<?php if ($dokumen): ?>
<div class="kartu">
  <div class="kartu-kepala"><h2><?= ikon('rekap', 18) ?> Rencana per unit</h2>
    <div class="legenda-aset"><span><i style="background:#1fa463"></i>Pendapatan</span><span><i style="background:#2f7fe0"></i>Belanja</span><span><i style="background:#e5a82f"></i>Realisasi belanja</span></div></div>
  <div class="kartu-isi"><?= keu_svg_batang($label, [['Pendapatan', '#1fa463', $serP], ['Belanja', '#2f7fe0', $serB], ['Realisasi belanja', '#e5a82f', $serR]], 'Rencana pendapatan, belanja, dan realisasi per unit') ?></div>
</div>
<?php endif; ?>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('dokumen', 18) ?> Dokumen RAPB <?= $tahun ?></h2><p class="ket"><?= count($dokumen) ?> dokumen</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiRapb" data-reset data-judul="Buat RAPB baru" data-isi='<?= e(json_encode(['id' => '', 'tahun' => $tahun])) ?>'><?= ikon('tambah', 15) ?> Buat RAPB</button></div>
  </div>
  <?php if (!$dokumen): ?>
    <?= sel_kosong('Belum ada RAPB tahun ' . $tahun) ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-rapb">
    <thead><tr><th>No</th><th>Dokumen</th><th>Pendapatan</th><th>Belanja</th><th>Selisih</th><th>Realisasi belanja</th><th>Status</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($dokumen as $n => $d): $t = $total[(int) $d['id']]; $s = KEU_STATUS_RAPB[$d['status']]; $sel = $t['pendapatan'] - $t['belanja']; ?>
      <tr>
        <td><?= $n + 1 ?></td>
        <td class="kiri"><a href="<?= e(url('rapb', ['aksi' => 'lihat', 'id' => $d['id']])) ?>"><b><?= e($d['nama']) ?></b></a><span class="sub"><?= e($d['unit_nama'] ?? 'Seluruh biro') ?> · <?= $t['item'] ?> rincian</span></td>
        <td class="nowrap uang"><?= rupiah($t['pendapatan']) ?></td>
        <td class="nowrap uang"><?= rupiah($t['belanja']) ?></td>
        <td class="nowrap uang"<?= $sel < 0 ? ' style="color:var(--merah)"' : '' ?>><?= $sel < 0 ? '(' . rupiah(-$sel) . ')' : rupiah($sel) ?></td>
        <td class="nowrap"><?= $t['belanja'] > 0 ? angka($t['real_belanja'] / $t['belanja'] * 100, 1) . '%' : '-' ?></td>
        <td><?= lencana($s['label'], $s['warna']) ?></td>
        <td class="no-cetak"><a class="btn btn-garis btn-kecil" href="<?= e(url('rapb', ['aksi' => 'lihat', 'id' => $d['id']])) ?>">Buka <?= ikon('kanan', 14) ?></a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<div class="tirai" id="tiraiRapb" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan_rapb"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>RAPB</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="rNama">Nama RAPB <span class="wajib">*</span></label><input type="text" id="rNama" name="nama" maxlength="200" required placeholder="Contoh: RAPB Bidang Keuangan"></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="rTahun">Tahun anggaran</label><input type="number" id="rTahun" name="tahun" min="2000" max="2100" value="<?= $tahun ?>"></div>
          <div class="form-baris"><label class="fl" for="rUnit">Unit</label><select id="rUnit" name="unit_id"><?= opsi_unit(null, 'Seluruh biro') ?></select></div>
        </div>
        <div class="form-baris"><label class="fl" for="rCat">Catatan</label><textarea id="rCat" name="catatan" rows="2" maxlength="1000"></textarea></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
