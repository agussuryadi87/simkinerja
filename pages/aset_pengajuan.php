<?php
$aksi = $_GET['aksi'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$sekarang = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $pid = (int) post('id');
    $lama = $pid ? row('SELECT * FROM aset_pengajuan WHERE id = ?', [$pid]) : null;
    $balik = static function (int $pid): void {
        if (post('kembali') === 'daftar') {
            kembali_ke('aset_pengajuan', array_filter(['status' => post('f_status') ?: null]));
        }
        kembali_ke('aset_pengajuan', ['aksi' => 'lihat', 'id' => $pid]);
    };

    if ($op === 'simpan') {
        if ($lama && !in_array($lama['status'], ['diajukan', 'disetujui'], true)) {
            flash('Pengajuan yang sudah dibeli atau diterima tidak dapat diubah.', 'warn');
            $balik($pid);
        }
        $tgl = post('tanggal');
        $data = [
            'tanggal'        => $tgl,
            'nama_barang'    => mb_substr(post('nama_barang'), 0, 200),
            'kategori'       => array_key_exists(post('kategori'), aset_kategori(true)) ? post('kategori') : 'lainnya',
            'jumlah'         => max(1, min(100000, (int) post('jumlah', '1'))),
            'satuan'         => mb_substr(post('satuan') ?: 'unit', 0, 20),
            'estimasi_harga' => aset_angka_atau_null('estimasi_harga'),
            'ruang_id'       => (int) post('ruang_id') ?: null,
            'alasan'         => mb_substr(post_teks('alasan'), 0, 5000),
            'prioritas'      => array_key_exists(post('prioritas'), ASET_PRIORITAS) ? post('prioritas') : 'sedang',
            'pemohon'        => mb_substr(post('pemohon'), 0, 150),
        ];
        $galat = [];
        if ($data['nama_barang'] === '') {
            $galat[] = 'Nama barang wajib diisi.';
        }
        if (!tanggal_sah($tgl)) {
            $galat[] = 'Tanggal pengajuan tidak sah.';
        }
        if ($data['ruang_id'] && !row('SELECT id FROM aset_ruang WHERE id = ?', [$data['ruang_id']])) {
            $galat[] = 'Ruangan tujuan tidak ditemukan.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke('aset_pengajuan', $pid ? ['aksi' => 'lihat', 'id' => $pid] : []);
        }
        if ($lama) {
            update('aset_pengajuan', $data + ['diubah' => date('Y-m-d H:i:s')], 'id = ?', [$pid]);
            flash('Pengajuan <b>' . e($lama['nomor']) . '</b> diperbarui.');
        } else {
            $pid = insert('aset_pengajuan', $data + ['nomor' => aset_nomor_dokumen('aset_pengajuan', 'PBR', 'tanggal', $tgl), 'dibuat_oleh' => uid()]);
            flash('Pengajuan barang dicatat.');
        }
        catat_log('simpan_aset_pengajuan', 'aset_pengajuan', $pid, $data['nama_barang']);
        $balik($pid);
    }

    if (!$lama) {
        tolak_akses();
    }
    $st = $lama['status'];

    if ($op === 'keputusan' && $st === 'diajukan') {
        $putusan = post('putusan') === 'ditolak' ? 'ditolak' : 'disetujui';
        update('aset_pengajuan', [
            'status' => $putusan, 'catatan_keputusan' => mb_substr(post_teks('catatan_keputusan'), 0, 3000),
            'tanggal_keputusan' => aset_tanggal_sah_atau_null(post('tanggal_keputusan')) ?: $sekarang, 'diubah' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$pid]);
        catat_log('putusan_aset_pengajuan', 'aset_pengajuan', $pid, $putusan);
        flash('Pengajuan ' . ($putusan === 'disetujui' ? 'disetujui.' : 'ditolak.'), $putusan === 'disetujui' ? 'ok' : 'warn');
    } elseif ($op === 'dibeli' && $st === 'disetujui') {
        update('aset_pengajuan', [
            'status' => 'dibeli', 'no_kwitansi' => mb_substr(post('no_kwitansi'), 0, 60), 'tanggal_kwitansi' => aset_tanggal_sah_atau_null(post('tanggal_kwitansi')) ?: $sekarang,
            'penyedia' => mb_substr(post('penyedia'), 0, 150), 'nilai_realisasi' => aset_angka_atau_null('nilai_realisasi'), 'diubah' => date('Y-m-d H:i:s'),
        ], 'id = ?', [$pid]);
        catat_log('beli_aset_pengajuan', 'aset_pengajuan', $pid, $lama['nomor']);
        flash('Pembelian dicatat. Unggah foto kwitansi / nota di bawah.');
    } elseif ($op === 'diterima' && in_array($st, ['dibeli', 'disetujui'], true)) {
        $jumlah = max(1, (int) $lama['jumlah']);
        $perUnit = $lama['nilai_realisasi'] !== null ? (float) $lama['nilai_realisasi'] / $jumlah : ($lama['estimasi_harga'] !== null ? (float) $lama['estimasi_harga'] : null);
        $bid = insert('aset_barang', [
            'kode' => aset_kode_barang_baru(), 'nama' => $lama['nama_barang'], 'kategori' => $lama['kategori'], 'ruang_id' => $lama['ruang_id'],
            'jumlah' => $jumlah, 'satuan' => $lama['satuan'], 'tahun' => (int) date('Y'), 'harga' => $perUnit, 'sumber_dana' => $lama['penyedia'] !== '' ? 'Pembelian: ' . $lama['penyedia'] : '',
            'kondisi' => 'baik', 'keterangan' => 'Dari pengajuan ' . $lama['nomor'] . ($lama['no_kwitansi'] !== '' ? ', kwitansi ' . $lama['no_kwitansi'] : ''),
        ]);
        update('aset_pengajuan', ['status' => 'diterima', 'barang_id' => $bid, 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$pid]);
        catat_log('terima_aset_pengajuan', 'aset_pengajuan', $pid, $lama['nomor']);
        flash('Barang diterima dan sudah dicatat di Inventaris.');
    } elseif ($op === 'ajukan_ulang' && $st === 'ditolak') {
        update('aset_pengajuan', ['status' => 'diajukan', 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$pid]);
        flash('Pengajuan dibuka kembali.', 'info');
    } elseif ($op === 'foto_unggah') {
        [$n, $galat] = aset_simpan_foto($_FILES['foto'] ?? [], 'pengajuan_id', $pid, post('keterangan') ?: 'Kwitansi / nota');
        if ($n > 0) {
            flash($n . ' foto ditambahkan.');
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } elseif ($n === 0) {
            flash('Pilih minimal satu foto (JPG, PNG, atau WebP).', 'warn');
        }
    } elseif ($op === 'foto_hapus') {
        aset_hapus_foto_satu((int) post('foto_id'), 'pengajuan_id', $pid);
        flash('Foto dihapus.');
    } elseif ($op === 'hapus') {
        if ($st === 'diterima') {
            flash('Pengajuan yang sudah diterima tidak dapat dihapus.', 'warn');
            $balik($pid);
        }
        aset_hapus_semua_foto('pengajuan_id', $pid);
        q('DELETE FROM aset_foto WHERE pengajuan_id = ?', [$pid]);
        q('DELETE FROM aset_pengajuan WHERE id = ?', [$pid]);
        aset_publik_ubah('pengajuan-publik.json', static function (array &$d) use ($pid): void {
            unset($d[(string) $pid]);
        });
        catat_log('hapus_aset_pengajuan', 'aset_pengajuan', $pid, $lama['nomor']);
        flash('Pengajuan dihapus.');
        kembali_ke('aset_pengajuan');
    } else {
        flash('Tindakan tidak dapat dilakukan pada status saat ini.', 'warn');
    }
    $balik($pid);
}

$modalForm = static function (): void { ?>
<div class="tirai" id="tiraiPengajuan" hidden>
  <div class="modal">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Pengajuan barang</h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <div class="form-baris"><label class="fl" for="pNama">Nama barang <span class="wajib">*</span></label><input type="text" id="pNama" name="nama_barang" maxlength="200" required></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="pTgl">Tanggal pengajuan <span class="wajib">*</span></label><input type="date" id="pTgl" name="tanggal" required></div>
          <div class="form-baris"><label class="fl" for="pKat">Kategori</label><select id="pKat" name="kategori"><?= pilihan(aset_kategori_formulir(), 'lainnya') ?></select></div>
          <div class="form-baris"><label class="fl" for="pJml">Jumlah</label><input type="number" id="pJml" name="jumlah" min="1" max="100000" value="1"></div>
          <div class="form-baris"><label class="fl" for="pSat">Satuan</label><input type="text" id="pSat" name="satuan" maxlength="20" value="unit"></div>
          <div class="form-baris"><label class="fl" for="pHarga">Estimasi harga per unit (Rp)</label><input type="text" id="pHarga" name="estimasi_harga" inputmode="numeric" maxlength="18"></div>
          <div class="form-baris"><label class="fl" for="pPri">Prioritas</label><select id="pPri" name="prioritas"><?php foreach (ASET_PRIORITAS as $k => $d): ?><option value="<?= $k ?>"><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="pRuang">Ruangan tujuan</label><select id="pRuang" name="ruang_id"><?= opsi_ruang(0, '— Belum ditentukan —') ?></select></div>
          <div class="form-baris"><label class="fl" for="pPemohon">Pemohon</label><input type="text" id="pPemohon" name="pemohon" maxlength="150" placeholder="Nama / unit pemohon"></div>
        </div>
        <div class="form-baris"><label class="fl" for="pAlasan">Alasan / kebutuhan</label><textarea id="pAlasan" name="alasan" rows="3" maxlength="5000"></textarea></div>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button>
      </div>
    </form>
  </div>
</div>
<?php };

if ($aksi === 'formulir') {
    $p = row('SELECT p.*, r.nama AS ruang_nama FROM aset_pengajuan p LEFT JOIN aset_ruang r ON r.id = p.ruang_id WHERE p.id = ?', [$id]);
    if (!$p) {
        flash('Pengajuan tidak ditemukan.', 'galat');
        kembali_ke('aset_pengajuan');
    }
    $subjudul = 'Formulir ' . $p['nomor'];
    $pub = aset_pengajuan_publik_info((int) $p['id']);
    $total = $p['estimasi_harga'] !== null ? (float) $p['estimasi_harga'] * (int) $p['jumlah'] : null;
    $kabiro = row("SELECT p.nama FROM pengguna p JOIN peran pr ON pr.id = p.peran_id WHERE pr.kode = 'kabiro' AND p.aktif = 1 ORDER BY p.id LIMIT 1");
    $namaPemohon = $pub['nama'] ?? trim(explode('·', (string) $p['pemohon'])[0]);
    $ttd = static function (string $atas, string $jabatan, string $nama, string $bawah = ''): string {
        return '<div><div>' . $atas . '</div><div>' . e($jabatan) . ',</div><div class="ruang"></div>'
            . ($nama !== '' ? '<b class="ttd-nama">' . e($nama) . '</b>' : '<b>(................................)</b>') . ($bawah !== '' ? '<div>' . e($bawah) . '</div>' : '') . '</div>';
    };
    ?>
    <div class="saring no-cetak">
      <a class="btn btn-garis" href="<?= e(url('aset_pengajuan', ['aksi' => 'lihat', 'id' => $id])) ?>"><?= ikon('kembali', 15) ?> Kembali ke pengajuan</a>
      <button class="btn btn-utama" type="button" data-cetak><?= ikon('cetak', 15) ?> Cetak / simpan PDF</button>
    </div>
    <article class="berita-acara">
      <header class="kop-surat">
        <img class="kop-logo" src="<?= e(base_url('assets/img/kop-logo.png')) ?>" alt="Logo UIS">
        <div class="kop-teks">
          <div class="kop-yayasan">YAYASAN PENDIDIKAN IBNU SINA BATAM (YAPISTA)</div>
          <div class="kop-univ">UNIVERSITAS IBNU SINA (UIS)</div>
          <div class="kop-alamat">Jalan Teuku Umar, Lubuk Baja, Kota Batam-Indonesia Telp. 0778 &ndash; 408 3113</div>
          <div class="kop-alamat">email : info@uis.ac.id / uibnusina@gmail.com &nbsp; website : uis.ac.id</div>
        </div>
      </header>
      <div class="kop-garis"></div>
      <div class="ba-isi">
        <div class="ba-judul"><h1>FORMULIR PENGAJUAN BARANG</h1><div>Nomor: <?= e($p['nomor']) ?></div></div>
        <table class="ba-info">
          <tr><td>Tanggal pengajuan</td><td>:</td><td><?= e(tanggal_id($p['tanggal'])) ?></td></tr>
          <tr><td>Pemohon</td><td>:</td><td><?= e($p['pemohon'] ?: '-') ?></td></tr>
          <?php if (!empty($pub['kontak'])): ?><tr><td>Kontak</td><td>:</td><td><?= e($pub['kontak']) ?></td></tr><?php endif; ?>
          <tr><td>Ruangan tujuan</td><td>:</td><td><?= e($p['ruang_nama'] ?: '-') ?></td></tr>
          <tr><td>Prioritas</td><td>:</td><td><?= e(ASET_PRIORITAS[$p['prioritas']]['label'] ?? $p['prioritas']) ?></td></tr>
        </table>
        <table class="ba-tabel">
          <thead><tr><th style="width:30px">No</th><th>Nama barang</th><th>Kategori</th><th style="width:80px">Jumlah</th><th style="width:120px">Harga satuan</th><th style="width:130px">Jumlah harga</th></tr></thead>
          <tbody><tr><td style="text-align:center">1</td><td><?= e($p['nama_barang']) ?></td><td><?= e(aset_kategori(true)[$p['kategori']] ?? '-') ?></td>
            <td style="text-align:center"><?= angka($p['jumlah']) ?> <?= e($p['satuan']) ?></td><td style="text-align:right"><?= e(aset_rupiah($p['estimasi_harga'])) ?></td><td style="text-align:right"><?= e(aset_rupiah($total)) ?></td></tr></tbody>
        </table>
        <p style="margin:10px 0 4px"><b>Alasan / kebutuhan:</b></p>
        <p style="margin:0;white-space:pre-line"><?= e($p['alasan'] ?: '-') ?></p>
        <?php if ($p['catatan_keputusan']): ?><p style="margin:10px 0 0"><b>Catatan:</b> <?= e($p['catatan_keputusan']) ?></p><?php endif; ?>
        <div class="ba-ttd">
          <?= $ttd('&nbsp;', 'Pemohon', $namaPemohon) ?>
          <?= $ttd(e(APP_KOTA) . ', ' . e(tanggal_id($p['tanggal'])) . '<br>Mengetahui,', 'Kepala Bidang Sarana dan Prasarana', ASET_KABID['nama'], 'NUP. ' . ASET_KABID['nup']) ?>
        </div>
        <div class="ba-ttd-judul">Menyetujui,</div>
        <div class="ba-ttd ba-ttd-3">
          <?= $ttd('&nbsp;', 'Kepala Biro Administrasi Umum dan Keuangan', (string) ($kabiro['nama'] ?? '')) ?>
          <?= $ttd('&nbsp;', 'Wakil Rektor II', ASET_WAREK2['nama'], ASET_WAREK2['nomor']) ?>
          <?= $ttd('&nbsp;', 'Rektor', ASET_REKTOR['nama'], ASET_REKTOR['nomor']) ?>
        </div>
      </div>
    </article>
    <?php
    return;
}

if ($aksi === 'lihat') {
    $p = row('SELECT p.*, r.nama AS ruang_nama FROM aset_pengajuan p LEFT JOIN aset_ruang r ON r.id = p.ruang_id WHERE p.id = ?', [$id]);
    if (!$p) {
        flash('Pengajuan tidak ditemukan.', 'galat');
        kembali_ke('aset_pengajuan');
    }
    $subjudul = $p['nomor'];
    $fotos = aset_foto_daftar('pengajuan_id', $id);
    $st = $p['status'];
    $total = $p['estimasi_harga'] !== null ? (float) $p['estimasi_harga'] * (int) $p['jumlah'] : null;
    $langkah = ['diajukan', 'disetujui', 'dibeli', 'diterima'];
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <a class="btn btn-garis" href="<?= e(url('aset_pengajuan')) ?>"><?= ikon('kembali', 15) ?> Daftar pengajuan</a>
      <a class="btn btn-utama" href="<?= e(url('aset_pengajuan', ['aksi' => 'formulir', 'id' => $id])) ?>"><?= ikon('cetak', 15) ?> Cetak formulir</a>
      <?php if (in_array($st, ['diajukan', 'disetujui'], true)): ?>
        <button class="btn btn-garis" type="button" data-buka="tiraiPengajuan" data-judul="Ubah pengajuan"
          data-isi='<?= e(json_encode(['id' => (int) $p['id'], 'nama_barang' => $p['nama_barang'], 'tanggal' => $p['tanggal'], 'kategori' => $p['kategori'], 'jumlah' => $p['jumlah'], 'satuan' => $p['satuan'],
              'estimasi_harga' => $p['estimasi_harga'] !== null ? (string) (int) round((float) $p['estimasi_harga']) : '', 'prioritas' => $p['prioritas'], 'ruang_id' => $p['ruang_id'] ?? '',
              'pemohon' => $p['pemohon'], 'alasan' => $p['alasan']])) ?>'><?= ikon('sunting', 15) ?> Ubah</button>
      <?php endif; ?>
      <?php if ($st !== 'diterima'): ?>
        <form method="post" data-konfirmasi="Hapus pengajuan ini beserta foto kwitansinya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= $id ?>">
          <button class="btn btn-garis" type="submit"><?= ikon('hapus', 15) ?> Hapus</button></form>
      <?php endif; ?>
    </div>

    <div class="alur-aset">
      <?php foreach ($langkah as $i => $lk):
          $idx = array_search($st === 'ditolak' ? 'diajukan' : $st, $langkah, true);
          $kelas = $st === 'ditolak' && $i === 0 ? 'tolak' : ($i < $idx ? 'lalu' : ($i === $idx ? 'kini' : '')); ?>
        <div class="<?= $kelas ?>"><i><?= $i + 1 ?></i><span><?= e(ASET_STATUS_PENGAJUAN[$lk]['label']) ?></span></div>
      <?php endforeach; ?>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= e($p['nama_barang']) ?></h2><p class="ket"><code><?= e($p['nomor']) ?></code> · diajukan <?= e(tanggal_id($p['tanggal'])) ?></p></div>
        <div><?= lencana_dari(ASET_PRIORITAS, $p['prioritas']) ?> <?= lencana_dari(ASET_STATUS_PENGAJUAN, $st) ?></div></div>
      <div class="kartu-isi">
        <div class="info-aset">
          <div><span>Kategori</span><b><?= e(aset_kategori(true)[$p['kategori']] ?? '-') ?></b></div>
          <div><span>Jumlah</span><b><?= angka($p['jumlah']) ?> <?= e($p['satuan']) ?></b></div>
          <div><span>Estimasi per unit</span><b><?= e(aset_rupiah($p['estimasi_harga'])) ?></b></div>
          <div><span>Estimasi total</span><b><?= e(aset_rupiah($total)) ?></b></div>
          <div><span>Ruangan tujuan</span><b><?= e($p['ruang_nama'] ?: '-') ?></b></div>
          <div><span>Pemohon</span><b><?= e($p['pemohon'] ?: '-') ?></b></div>
          <?php if ($pubInfo = aset_pengajuan_publik_info((int) $p['id'])): ?>
            <div><span>Dikirim via formulir online</span><b><?= e(tanggal_id(substr((string) $pubInfo['waktu'], 0, 10))) ?> <?= e(substr((string) $pubInfo['waktu'], 11, 5)) ?></b></div>
            <div><span>Kontak pemohon</span><b><?= e(($pubInfo['kontak'] ?? '') !== '' ? $pubInfo['kontak'] : '-') ?></b></div>
          <?php endif; ?>
          <?php if ($p['tanggal_keputusan']): ?><div><span>Tanggal keputusan</span><b><?= e(tanggal_id($p['tanggal_keputusan'])) ?></b></div><?php endif; ?>
          <?php if ($p['no_kwitansi'] !== '' || $p['penyedia'] !== '' || $p['nilai_realisasi'] !== null): ?>
            <div><span>No. kwitansi</span><b><?= e($p['no_kwitansi'] ?: '-') ?></b></div>
            <div><span>Tanggal kwitansi</span><b><?= $p['tanggal_kwitansi'] ? e(tanggal_id($p['tanggal_kwitansi'])) : '-' ?></b></div>
            <div><span>Penyedia / toko</span><b><?= e($p['penyedia'] ?: '-') ?></b></div>
            <div><span>Nilai realisasi</span><b><?= e(aset_rupiah($p['nilai_realisasi'])) ?></b></div>
          <?php endif; ?>
          <?php if ($p['barang_id']): ?><div><span>Tercatat di inventaris</span><b><a href="<?= e(url('aset_barang', ['aksi' => 'lihat', 'id' => $p['barang_id']])) ?>">Buka barang</a></b></div><?php endif; ?>
        </div>
        <?php if ($p['alasan']): ?><h3 style="margin:16px 0 4px;font-size:.9rem">Alasan / kebutuhan</h3><p style="margin:0"><?= nl2br(e($p['alasan'])) ?></p><?php endif; ?>
        <?php if ($p['catatan_keputusan']): ?><h3 style="margin:16px 0 4px;font-size:.9rem">Catatan keputusan</h3><p style="margin:0"><?= nl2br(e($p['catatan_keputusan'])) ?></p><?php endif; ?>
      </div>
    </div>

    <?php if ($st === 'diajukan'): ?>
    <div class="kartu no-cetak"><div class="kartu-kepala"><div><h2>Keputusan</h2></div></div><div class="kartu-isi">
      <form method="post" class="unggah-aset"><?= csrf_field() ?><input type="hidden" name="op" value="keputusan"><input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-baris"><label class="fl" for="kpTgl">Tanggal</label><input type="date" id="kpTgl" name="tanggal_keputusan" value="<?= $sekarang ?>"></div>
        <div class="form-baris" style="flex:1;min-width:240px"><label class="fl" for="kpCat">Catatan</label><input type="text" id="kpCat" name="catatan_keputusan" maxlength="3000"></div>
        <button class="btn btn-utama" type="submit" name="putusan" value="disetujui">Setujui</button>
        <button class="btn btn-garis" type="submit" name="putusan" value="ditolak">Tolak</button>
      </form></div></div>
    <?php elseif ($st === 'disetujui'): ?>
    <div class="kartu no-cetak"><div class="kartu-kepala"><div><h2>Catat pembelian</h2></div></div><div class="kartu-isi">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="dibeli"><input type="hidden" name="id" value="<?= $id ?>">
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="bNo">Nomor kwitansi / nota</label><input type="text" id="bNo" name="no_kwitansi" maxlength="60"></div>
          <div class="form-baris"><label class="fl" for="bTgl">Tanggal kwitansi</label><input type="date" id="bTgl" name="tanggal_kwitansi" value="<?= $sekarang ?>"></div>
          <div class="form-baris"><label class="fl" for="bPen">Penyedia / toko</label><input type="text" id="bPen" name="penyedia" maxlength="150"></div>
          <div class="form-baris"><label class="fl" for="bNil">Nilai total pembelian (Rp)</label><input type="text" id="bNil" name="nilai_realisasi" inputmode="numeric" maxlength="18" value="<?= $total !== null ? (int) round($total) : '' ?>"></div>
        </div>
        <button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan pembelian</button>
      </form></div></div>
    <?php elseif ($st === 'dibeli'): ?>
    <div class="kartu no-cetak"><div class="kartu-kepala"><div><h2>Penerimaan barang</h2></div></div><div class="kartu-isi">
      <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="diterima"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-utama" type="submit">Barang diterima, catat ke Inventaris</button></form></div></div>
    <?php elseif ($st === 'ditolak'): ?>
    <div class="no-cetak" style="margin-bottom:14px"><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="ajukan_ulang"><input type="hidden" name="id" value="<?= $id ?>">
      <button class="btn btn-garis" type="submit">Ajukan ulang</button></form></div>
    <?php endif; ?>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2>Foto kwitansi / nota</h2><p class="ket"><?= count($fotos) ?> foto</p></div></div>
      <div class="kartu-isi">
        <?php if (!$fotos): ?><?= sel_kosong('Belum ada foto kwitansi') ?><?php endif; ?>
        <div class="galeri-aset">
          <?php foreach ($fotos as $f): ?>
            <figure><a href="<?= e(aset_foto_url($f['berkas'])) ?>" target="_blank" rel="noopener"><img src="<?= e(aset_foto_url($f['berkas'])) ?>" alt="Kwitansi" loading="lazy"></a>
              <figcaption><?= e($f['keterangan'] ?: '') ?>
                <form method="post" class="no-cetak" data-konfirmasi="Hapus foto ini?"><?= csrf_field() ?><input type="hidden" name="op" value="foto_hapus"><input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="foto_id" value="<?= (int) $f['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus foto"><?= ikon('hapus', 14) ?></button></form></figcaption></figure>
          <?php endforeach; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="unggah-aset no-cetak">
          <?= csrf_field() ?><input type="hidden" name="op" value="foto_unggah"><input type="hidden" name="id" value="<?= $id ?>">
          <div class="form-baris" style="flex:1;min-width:220px"><label class="fl" for="fotoPb">Tambah foto</label><input type="file" id="fotoPb" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
          <button class="btn btn-utama" type="submit"><?= ikon('kamera', 15) ?> Unggah</button>
        </form>
      </div>
    </div>
    <?php $modalForm(); return;
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? 'berjalan');
$fStatus = in_array($fStatus, ['berjalan', 'semua'], true) || array_key_exists($fStatus, ASET_STATUS_PENGAJUAN) ? $fStatus : 'berjalan';
$syarat = ['1=1'];
$par = [];
if ($fStatus === 'berjalan') {
    $syarat[] = "p.status IN ('diajukan','disetujui','dibeli')";
} elseif ($fStatus !== 'semua') {
    $syarat[] = 'p.status = ?';
    $par[] = $fStatus;
}
if ($cari !== '') {
    $syarat[] = '(p.nama_barang LIKE ? OR p.nomor LIKE ? OR p.pemohon LIKE ?)';
    $par = array_merge($par, array_fill(0, 3, '%' . $cari . '%'));
}
$daftar = rows(
    'SELECT p.*, r.nama AS ruang_nama FROM aset_pengajuan p LEFT JOIN aset_ruang r ON r.id = p.ruang_id WHERE ' . implode(' AND ', $syarat)
    . ' ORDER BY p.tanggal DESC, p.id DESC LIMIT 300',
    $par
);
$penginput = aset_penginput('aset_pengajuan', $daftar);
?>

<?php $linkPublik = aset_url_mutlak('index.php?h=ajukan_barang'); ?>
<div class="notif notif-info no-cetak link-publik"><?= ikon('tautan', 17) ?>
  <div><b>Link formulir pengajuan untuk pemohon</b>
    <div class="link-publik-baris"><input type="text" readonly value="<?= e($linkPublik) ?>" id="linkPublik" aria-label="Link formulir pengajuan">
      <button class="btn btn-garis btn-kecil" type="button" id="salinLinkPublik"><?= ikon('tautan', 14) ?> Salin</button>
      <a class="btn btn-garis btn-kecil" href="<?= e($linkPublik) ?>" target="_blank" rel="noopener"><?= ikon('mata', 14) ?> Buka</a></div></div></div>
<script nonce="<?= csp_nonce() ?>">
  (function () {
    var t = document.getElementById('salinLinkPublik'), i = document.getElementById('linkPublik');
    if (!t || !i) return;
    t.addEventListener('click', function () {
      var ok = function () { var a = t.innerHTML; t.textContent = 'Tersalin'; setTimeout(function () { t.innerHTML = a; }, 1600); };
      if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(i.value).then(ok); }
      else { i.select(); try { document.execCommand('copy'); ok(); } catch (e) {} }
    });
  })();
</script>

<form method="get" class="saring">
  <input type="hidden" name="h" value="aset_pengajuan">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nomor, nama barang, atau pemohon"></div>
  <div class="form-baris"><label class="fl">Status</label><select name="status" data-auto-kirim>
    <option value="berjalan" <?= $fStatus === 'berjalan' ? 'selected' : '' ?>>Dalam proses</option><option value="semua" <?= $fStatus === 'semua' ? 'selected' : '' ?>>Semua status</option>
    <?php foreach (ASET_STATUS_PENGAJUAN as $k => $d): ?><option value="<?= $k ?>" <?= $fStatus === $k ? 'selected' : '' ?>><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2>Pengajuan barang</h2><p class="ket"><?= count($daftar) ?> pengajuan</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPengajuan" data-reset data-judul="Ajukan barang"
        data-isi='<?= e(json_encode(['id' => '', 'tanggal' => $sekarang, 'jumlah' => '1', 'satuan' => 'unit', 'kategori' => 'lainnya', 'prioritas' => 'sedang', 'pemohon' => user()['nama'] ?? '', 'ruang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Ajukan barang</button></div>
  </div>
  <?php if (!$daftar): ?><?= sel_kosong('Belum ada pengajuan') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Nomor / tanggal</th><th>Barang</th><th class="angka">Jumlah</th><th class="angka">Estimasi total</th><th>Prioritas</th><th>Pemohon</th><th>Status</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $p): ?>
      <tr>
        <td class="nowrap"><a href="<?= e(url('aset_pengajuan', ['aksi' => 'lihat', 'id' => $p['id']])) ?>"><code><?= e($p['nomor']) ?></code></a><div class="kecil lemah"><?= e(tanggal_pendek($p['tanggal'])) ?></div></td>
        <td><b><?= e($p['nama_barang']) ?></b><div class="kecil lemah"><?= e(aset_kategori(true)[$p['kategori']] ?? '-') ?><?= $p['ruang_nama'] ? ' · ' . e($p['ruang_nama']) : '' ?></div></td>
        <td class="angka"><?= angka($p['jumlah']) ?> <?= e($p['satuan']) ?></td>
        <td class="angka uang"><?= $p['estimasi_harga'] !== null ? e(aset_rupiah((float) $p['estimasi_harga'] * (int) $p['jumlah'])) : '-' ?></td>
        <td><?= lencana_dari(ASET_PRIORITAS, $p['prioritas']) ?></td>
        <td class="kecil"><?= e($p['pemohon'] ?: '-') ?></td>
        <td><?= lencana_dari(ASET_STATUS_PENGAJUAN, $p['status']) ?>
          <?php if ($p['catatan_keputusan'] !== ''): ?><div class="kecil pengajuan-umpan" title="Umpan balik untuk pemohon"><?= ikon('pesan', 12) ?> <?= e(mb_strimwidth($p['catatan_keputusan'], 0, 70, '…')) ?></div><?php endif; ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $p['id']] ?? null) ?></td>
        <td class="no-cetak kolom-aksi"><div class="aksi-baris">
          <a class="btn btn-hantu btn-ikon" href="<?= e(url('aset_pengajuan', ['aksi' => 'lihat', 'id' => $p['id']])) ?>" title="Lihat detail"><?= ikon('mata', 15) ?></a>
          <?php if ($p['status'] === 'diajukan'): ?>
            <button class="btn btn-sukses btn-kecil" type="button" data-buka="tiraiTanggapi" title="Setujui atau tolak, beri umpan balik"
              data-isi='<?= e(json_encode(['id' => (int) $p['id'], 'judul_teks' => $p['nomor'] . ' · ' . $p['nama_barang'] . ' · ' . ($p['pemohon'] ?: '-'), 'catatan_keputusan' => $p['catatan_keputusan'], 'putusan' => 'disetujui'])) ?>'><?= ikon('pesan', 14) ?> Tanggapi</button>
          <?php endif; ?>
          <a class="btn btn-hantu btn-ikon" href="<?= e(url('aset_pengajuan', ['aksi' => 'formulir', 'id' => $p['id']])) ?>" title="Cetak formulir"><?= ikon('cetak', 15) ?></a>
        </div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<div class="tirai" id="tiraiTanggapi" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="keputusan"><input type="hidden" name="id" value="">
      <input type="hidden" name="kembali" value="daftar"><input type="hidden" name="f_status" value="<?= e($fStatus) ?>">
      <div class="modal-kepala"><h2>Tanggapi pengajuan</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil lemah" data-teks="judul_teks"></p>
        <div class="form-baris"><label class="fl">Keputusan</label>
          <div class="pilih-putusan">
            <label class="setuju"><input type="radio" name="putusan" value="disetujui" checked><span><?= ikon('centang', 15) ?> Setujui</span></label>
            <label class="tolak"><input type="radio" name="putusan" value="ditolak"><span><?= ikon('silang', 15) ?> Tolak</span></label>
          </div></div>
        <div class="form-baris"><label class="fl" for="tgTgl">Tanggal keputusan</label><input type="date" id="tgTgl" name="tanggal_keputusan" value="<?= e($sekarang) ?>"></div>
        <div class="form-baris"><label class="fl" for="tgCatatan">Umpan balik untuk pemohon</label>
          <textarea id="tgCatatan" name="catatan_keputusan" rows="3" maxlength="3000" placeholder="Contoh: Disetujui, barang dibeli bulan depan. / Ditolak, stok di gudang masih tersedia."></textarea></div>
      </div>
      <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('kirim', 15) ?> Kirim tanggapan</button></div>
    </form>
  </div>
</div>
<?php $modalForm(); ?>
