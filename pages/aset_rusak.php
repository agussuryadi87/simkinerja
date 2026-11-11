<?php
$jenis = $h === 'aset_prasarana' ? 'prasarana' : 'sarana';
$namaJenis = $jenis === 'sarana' ? 'Kerusakan Sarana' : 'Kerusakan Prasarana';
$ket = $jenis === 'sarana' ? 'barang dan peralatan (meja, AC, proyektor, komputer, dll.)' : 'bangunan dan fasilitas (atap, dinding, listrik, air, jaringan, dll.)';
$aksi = $_GET['aksi'] ?? '';
$id = (int) ($_GET['id'] ?? 0);
$sekarang = date('Y-m-d');
$penuh = boleh_aset();
$milikCs = static function (?array $k): bool {
    return $k !== null && (int) $k['dibuat_oleh'] === uid() && $k['status'] === 'dilaporkan';
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $kid = (int) post('id');
    $lama = $kid ? row('SELECT * FROM aset_kerusakan WHERE id = ? AND jenis = ?', [$kid, $jenis]) : null;
    if (!$penuh && (in_array($op, ['status', 'hapus', 'foto_hapus'], true) || ($lama && !$milikCs($lama)))) {
        tolak_akses();
    }
    if (!$penuh && $op === 'simpan') {
        $_POST['status'] = 'dilaporkan';
        $_POST['tanggal_selesai'] = '';
        $_POST['biaya'] = '';
        $_POST['tindak_lanjut'] = '';
        if (post('pelapor') === '') {
            $_POST['pelapor'] = (string) (user()['nama'] ?? '');
        }
    }

    if ($op === 'simpan') {
        $barangId = $jenis === 'sarana' ? ((int) post('barang_id') ?: null) : null;
        $ruangId = $jenis === 'prasarana' ? ((int) post('ruang_id') ?: null) : null;
        if ($barangId) {
            $ruangId = (int) val('SELECT ruang_id FROM aset_barang WHERE id = ?', [$barangId], 0) ?: null;
        }
        $status = array_key_exists(post('status'), ASET_STATUS_RUSAK) ? post('status') : 'dilaporkan';
        $tglLapor = post('tanggal_lapor');
        $tglSelesai = aset_tanggal_sah_atau_null(post('tanggal_selesai'));
        if ($status === 'selesai' && !$tglSelesai) {
            $tglSelesai = $sekarang;
        }
        $data = [
            'jenis'           => $jenis,
            'ruang_id'        => $ruangId,
            'barang_id'       => $barangId,
            'bagian'          => $jenis === 'prasarana' ? mb_substr(post('bagian'), 0, 100) : '',
            'judul'           => mb_substr(post('judul'), 0, 200),
            'uraian'          => mb_substr(post_teks('uraian'), 0, 5000),
            'tingkat'         => array_key_exists(post('tingkat'), ASET_TINGKAT) ? post('tingkat') : 'ringan',
            'status'          => $status,
            'tanggal_lapor'   => $tglLapor,
            'pelapor'         => mb_substr(post('pelapor'), 0, 150),
            'tindak_lanjut'   => mb_substr(post_teks('tindak_lanjut'), 0, 5000),
            'tanggal_selesai' => $status === 'selesai' ? $tglSelesai : null,
            'biaya'           => aset_angka_atau_null('biaya'),
        ];
        $galat = [];
        if ($data['judul'] === '') {
            $galat[] = 'Judul / ringkasan kerusakan wajib diisi.';
        }
        if (!tanggal_sah($tglLapor)) {
            $galat[] = 'Tanggal laporan tidak sah.';
        } elseif ($tglSelesai && $tglSelesai < $tglLapor) {
            $galat[] = 'Tanggal selesai tidak boleh sebelum tanggal laporan.';
        }
        if ($jenis === 'sarana' && (!$barangId || !row('SELECT id FROM aset_barang WHERE id = ?', [$barangId]))) {
            $galat[] = 'Pilih barang yang rusak. Catat dahulu barangnya di Inventaris Barang.';
        }
        if ($jenis === 'prasarana' && (!$ruangId || !row('SELECT id FROM aset_ruang WHERE id = ?', [$ruangId]))) {
            $galat[] = 'Pilih ruangan / lokasi yang rusak.';
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            kembali_ke($h, $kid ? ['aksi' => 'lihat', 'id' => $kid] : []);
        }
        if ($lama) {
            update('aset_kerusakan', $data + ['diubah' => date('Y-m-d H:i:s')], 'id = ?', [$kid]);
            catat_log('ubah_aset_rusak', 'aset_kerusakan', $kid, $data['judul']);
            flash('Laporan <b>' . e($lama['nomor']) . '</b> diperbarui.');
        } else {
            $kid = insert('aset_kerusakan', $data + ['nomor' => aset_nomor_kerusakan($jenis), 'dibuat_oleh' => uid()]);
            catat_log('tambah_aset_rusak', 'aset_kerusakan', $kid, $data['judul']);
            flash('Laporan kerusakan dicatat. Anda dapat menambahkan foto bukti di bawah.');
        }
        foreach (array_unique(array_filter([(int) ($lama['barang_id'] ?? 0), (int) $barangId])) as $bid) {
            aset_sinkron_kondisi_barang($bid);
        }
        if (isset($_FILES['foto'])) {
            [, $galatFoto] = aset_simpan_foto($_FILES['foto'], 'kerusakan_id', $kid, $data['judul']);
            if ($galatFoto) {
                flash(implode('<br>', array_map('e', $galatFoto)), 'galat');
            }
        }
        kembali_ke($h, ['aksi' => 'lihat', 'id' => $kid]);
    }

    if (!$lama) {
        tolak_akses();
    }

    if ($op === 'status') {
        $status = array_key_exists(post('status'), ASET_STATUS_RUSAK) ? post('status') : $lama['status'];
        $tglSelesai = $status === 'selesai' ? (aset_tanggal_sah_atau_null(post('tanggal_selesai')) ?: $sekarang) : null;
        if ($tglSelesai && $tglSelesai < $lama['tanggal_lapor']) {
            $tglSelesai = $lama['tanggal_lapor'];
        }
        update('aset_kerusakan', [
            'status' => $status, 'tanggal_selesai' => $tglSelesai, 'diubah' => date('Y-m-d H:i:s'),
            'tindak_lanjut' => mb_substr(post_teks('tindak_lanjut'), 0, 5000),
            'biaya' => aset_angka_atau_null('biaya') ?? $lama['biaya'],
        ], 'id = ?', [$kid]);
        if ($lama['barang_id']) {
            aset_sinkron_kondisi_barang((int) $lama['barang_id']);
        }
        catat_log('status_aset_rusak', 'aset_kerusakan', $kid, $status);
        flash('Status laporan diubah menjadi <b>' . e(ASET_STATUS_RUSAK[$status]['label']) . '</b>.');
        kembali_ke($h, ['aksi' => 'lihat', 'id' => $kid]);
    } elseif ($op === 'foto_unggah') {
        [$n, $galat] = aset_simpan_foto($_FILES['foto'] ?? [], 'kerusakan_id', $kid, post('keterangan'));
        if ($n > 0) {
            flash($n . ' foto ditambahkan.');
        }
        if ($galat) {
            flash(implode('<br>', array_map('e', $galat)), 'galat');
        } elseif ($n === 0) {
            flash('Pilih minimal satu foto (JPG, PNG, atau WebP).', 'warn');
        }
        kembali_ke($h, ['aksi' => 'lihat', 'id' => $kid]);
    } elseif ($op === 'foto_hapus') {
        aset_hapus_foto_satu((int) post('foto_id'), 'kerusakan_id', $kid);
        flash('Foto dihapus.');
        kembali_ke($h, ['aksi' => 'lihat', 'id' => $kid]);
    } elseif ($op === 'hapus') {
        aset_hapus_semua_foto('kerusakan_id', $kid);
        q('DELETE FROM aset_kerusakan WHERE id = ?', [$kid]);
        if ($lama['barang_id']) {
            aset_sinkron_kondisi_barang((int) $lama['barang_id']);
        }
        catat_log('hapus_aset_rusak', 'aset_kerusakan', $kid, $lama['judul']);
        flash('Laporan <b>' . e($lama['nomor']) . '</b> dihapus.');
        kembali_ke($h);
    }
    kembali_ke($h);
}

$dataModal = static function (array $k): string {
    return e(json_encode([
        'id' => (int) $k['id'], 'barang_id' => $k['barang_id'], 'ruang_id' => $k['ruang_id'], 'bagian' => $k['bagian'], 'judul' => $k['judul'],
        'uraian' => $k['uraian'], 'tingkat' => $k['tingkat'], 'status' => $k['status'], 'tanggal_lapor' => $k['tanggal_lapor'], 'pelapor' => $k['pelapor'],
        'tindak_lanjut' => $k['tindak_lanjut'], 'tanggal_selesai' => $k['tanggal_selesai'] ?? '',
        'biaya' => $k['biaya'] !== null ? (string) (int) round((float) $k['biaya']) : '',
    ]));
};

$modalLaporan = static function () use ($jenis, $namaJenis, $penuh): void { ?>
<div class="tirai" id="tiraiRusak" hidden>
  <div class="modal">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="id" value="">
      <div class="modal-kepala"><h2>Laporan <?= e(strtolower($namaJenis)) ?></h2>
        <button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <?php if ($jenis === 'sarana'): ?>
          <div class="form-baris"><label class="fl" for="kBarang">Barang yang rusak <span class="wajib">*</span></label>
            <select id="kBarang" name="barang_id" required><?= opsi_barang(0, '— Pilih barang —') ?></select></div>
        <?php else: ?>
          <div class="form-grid">
            <div class="form-baris"><label class="fl" for="kRuang">Ruangan / lokasi <span class="wajib">*</span></label>
              <select id="kRuang" name="ruang_id" required><?= opsi_ruang(0, '— Pilih ruangan —') ?></select></div>
            <div class="form-baris"><label class="fl" for="kBagian">Bagian yang rusak</label>
              <select id="kBagian" name="bagian"><option value="">— Pilih bagian —</option><?= pilihan(array_combine(ASET_BAGIAN_PRASARANA, ASET_BAGIAN_PRASARANA), '') ?></select></div>
          </div>
        <?php endif; ?>
        <div class="form-baris"><label class="fl" for="kJudul">Ringkasan kerusakan <span class="wajib">*</span></label>
          <input type="text" id="kJudul" name="judul" maxlength="200" required placeholder="<?= $jenis === 'sarana' ? 'mis. AC tidak dingin dan bocor air' : 'mis. Plafon bocor saat hujan' ?>"></div>
        <div class="form-baris"><label class="fl" for="kUraian">Uraian / kronologi</label><textarea id="kUraian" name="uraian" rows="3" maxlength="5000"></textarea></div>
        <div class="form-grid">
          <div class="form-baris"><label class="fl" for="kTingkat">Tingkat kerusakan</label>
            <select id="kTingkat" name="tingkat"><?php foreach (ASET_TINGKAT as $k => $d): ?><option value="<?= $k ?>"><?= e($d['label']) ?></option><?php endforeach; ?></select></div>
          <?php if ($penuh): ?><div class="form-baris"><label class="fl" for="kStatus">Status penanganan</label>
            <select id="kStatus" name="status"><?php foreach (ASET_STATUS_RUSAK as $k => $d): ?><option value="<?= $k ?>"><?= e($d['label']) ?></option><?php endforeach; ?></select></div><?php endif; ?>
          <div class="form-baris"><label class="fl" for="kTgl">Tanggal dilaporkan <span class="wajib">*</span></label><input type="date" id="kTgl" name="tanggal_lapor" required></div>
          <div class="form-baris"><label class="fl" for="kPelapor">Pelapor</label><input type="text" id="kPelapor" name="pelapor" maxlength="150" placeholder="Nama / unit pelapor"></div>
          <?php if ($penuh): ?><div class="form-baris"><label class="fl" for="kSelesai">Tanggal selesai diperbaiki</label><input type="date" id="kSelesai" name="tanggal_selesai"></div>
          <div class="form-baris"><label class="fl" for="kBiaya">Biaya perbaikan (Rp)</label><input type="text" id="kBiaya" name="biaya" inputmode="numeric" maxlength="18"></div><?php endif; ?>
        </div>
        <?php if ($penuh): ?><div class="form-baris"><label class="fl" for="kTindak">Tindak lanjut / penanganan</label><textarea id="kTindak" name="tindak_lanjut" rows="2" maxlength="5000"></textarea></div><?php endif; ?>
        <div class="form-baris"><label class="fl" for="kFoto">Foto kerusakan (opsional, boleh lebih dari satu)</label>
          <input type="file" id="kFoto" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
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
    $k = row(
        'SELECT k.*, b.nama AS barang_nama, b.kode AS barang_kode, r.nama AS ruang_nama, r.kode AS ruang_kode
           FROM aset_kerusakan k LEFT JOIN aset_barang b ON b.id = k.barang_id LEFT JOIN aset_ruang r ON r.id = k.ruang_id
          WHERE k.id = ? AND k.jenis = ?',
        [$id, $jenis]
    );
    if (!$k) {
        flash('Laporan tidak ditemukan.', 'galat');
        kembali_ke($h);
    }
    $subjudul = $k['nomor'];
    $fotos = aset_foto_daftar('kerusakan_id', $id);
    $lamaHari = (int) ((strtotime($k['tanggal_selesai'] ?: $sekarang) - strtotime($k['tanggal_lapor'])) / 86400);
    ?>
    <div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
      <a class="btn btn-garis" href="<?= e(url($h)) ?>"><?= ikon('kembali', 15) ?> Daftar laporan</a>
      <?php if ($penuh || $milikCs($k)): ?><button class="btn btn-garis" type="button" data-buka="tiraiRusak" data-judul="Ubah laporan" data-isi='<?= $dataModal($k) ?>'><?= ikon('sunting', 15) ?> Ubah laporan</button><?php endif; ?>
      <?php if ($penuh): ?><form method="post" data-konfirmasi="Hapus laporan ini beserta foto buktinya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= $id ?>">
        <button class="btn btn-garis" type="submit"><?= ikon('hapus', 15) ?> Hapus</button></form><?php endif; ?>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= e($k['judul']) ?></h2><p class="ket"><code><?= e($k['nomor']) ?></code> · dilaporkan <?= e(tanggal_id($k['tanggal_lapor'])) ?></p></div>
        <div><?= lencana_dari(ASET_TINGKAT, $k['tingkat']) ?> <?= lencana_dari(ASET_STATUS_RUSAK, $k['status']) ?></div></div>
      <div class="kartu-isi">
        <div class="info-aset">
          <?php if ($jenis === 'sarana'): ?>
            <div><span>Barang</span><b><?= $k['barang_id'] ? ($penuh ? '<a href="' . e(url('aset_barang', ['aksi' => 'lihat', 'id' => $k['barang_id']])) . '">' . e($k['barang_nama']) . '</a>' : e($k['barang_nama'])) : '-' ?></b></div>
            <div><span>Kode inventaris</span><b><?= e($k['barang_kode'] ?: '-') ?></b></div>
          <?php else: ?>
            <div><span>Bagian rusak</span><b><?= e($k['bagian'] ?: '-') ?></b></div>
          <?php endif; ?>
          <div><span>Lokasi</span><b><?= $k['ruang_id'] ? ($penuh ? '<a href="' . e(url('aset_ruang', ['aksi' => 'lihat', 'id' => $k['ruang_id']])) . '">' . e($k['ruang_nama']) . '</a>' : e($k['ruang_nama'])) : '-' ?></b></div>
          <div><span>Pelapor</span><b><?= e($k['pelapor'] ?: '-') ?></b></div>
          <div><span>Tanggal selesai</span><b><?= $k['tanggal_selesai'] ? e(tanggal_id($k['tanggal_selesai'])) : '-' ?></b></div>
          <div><span><?= $k['status'] === 'selesai' ? 'Lama penanganan' : 'Sudah berjalan' ?></span><b><?= $lamaHari ?> hari</b></div>
          <div><span>Biaya perbaikan</span><b><?= e(aset_rupiah($k['biaya'])) ?></b></div>
        </div>
        <?php if ($k['uraian']): ?><h3 style="margin:16px 0 4px;font-size:.9rem">Uraian</h3><p style="margin:0"><?= nl2br(e($k['uraian'])) ?></p><?php endif; ?>
        <?php if ($k['tindak_lanjut']): ?><h3 style="margin:16px 0 4px;font-size:.9rem">Tindak lanjut</h3><p style="margin:0"><?= nl2br(e($k['tindak_lanjut'])) ?></p><?php endif; ?>
      </div>
    </div>

    <?php if ($penuh): ?>
    <div class="kartu no-cetak">
      <div class="kartu-kepala"><div><h2>Perbarui penanganan</h2></div></div>
      <div class="kartu-isi">
        <form method="post" class="unggah-aset">
          <?= csrf_field() ?><input type="hidden" name="op" value="status"><input type="hidden" name="id" value="<?= $id ?>">
          <div class="form-baris"><label class="fl" for="stSt">Status</label>
            <select id="stSt" name="status"><?php foreach (ASET_STATUS_RUSAK as $sk => $sd): ?><option value="<?= $sk ?>" <?= $k['status'] === $sk ? 'selected' : '' ?>><?= e($sd['label']) ?></option><?php endforeach; ?></select></div>
          <div class="form-baris"><label class="fl" for="stTgl">Tanggal selesai</label><input type="date" id="stTgl" name="tanggal_selesai" value="<?= e((string) $k['tanggal_selesai']) ?>"></div>
          <div class="form-baris"><label class="fl" for="stBiaya">Biaya (Rp)</label><input type="text" id="stBiaya" name="biaya" inputmode="numeric" maxlength="18" value="<?= $k['biaya'] !== null ? (int) round((float) $k['biaya']) : '' ?>"></div>
          <div class="form-baris" style="flex:2;min-width:240px"><label class="fl" for="stTindak">Tindak lanjut</label><input type="text" id="stTindak" name="tindak_lanjut" maxlength="5000" value="<?= e($k['tindak_lanjut']) ?>"></div>
          <button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan</button>
        </form>
      </div>
    </div>
    <?php endif; ?>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('kamera', 18) ?> Foto bukti kerusakan</h2><p class="ket"><?= count($fotos) ?> foto</p></div></div>
      <div class="kartu-isi">
        <?php if (!$fotos): ?><?= sel_kosong('Belum ada foto bukti') ?><?php endif; ?>
        <div class="galeri-aset">
          <?php foreach ($fotos as $f): ?>
            <figure><a href="<?= e(aset_foto_url($f['berkas'])) ?>" target="_blank" rel="noopener"><img src="<?= e(aset_foto_url($f['berkas'])) ?>" alt="Foto kerusakan" loading="lazy"></a>
              <figcaption><?= e($f['keterangan'] ?: '') ?>
                <?php if ($penuh): ?><form method="post" class="no-cetak" data-konfirmasi="Hapus foto ini?"><?= csrf_field() ?><input type="hidden" name="op" value="foto_hapus"><input type="hidden" name="id" value="<?= $id ?>">
                  <input type="hidden" name="foto_id" value="<?= (int) $f['id'] ?>"><button class="btn btn-hantu btn-ikon" type="submit" title="Hapus foto"><?= ikon('hapus', 14) ?></button></form><?php endif; ?></figcaption></figure>
          <?php endforeach; ?>
        </div>
        <?php if ($penuh || $milikCs($k)): ?>
        <form method="post" enctype="multipart/form-data" class="unggah-aset no-cetak">
          <?= csrf_field() ?><input type="hidden" name="op" value="foto_unggah"><input type="hidden" name="id" value="<?= $id ?>">
          <div class="form-baris" style="flex:1;min-width:220px"><label class="fl" for="fotoRsk">Tambah foto (boleh banyak)</label>
            <input type="file" id="fotoRsk" name="foto[]" accept="image/jpeg,image/png,image/webp" multiple></div>
          <div class="form-baris" style="flex:1;min-width:200px"><label class="fl" for="fotoRskKet">Keterangan</label><input type="text" id="fotoRskKet" name="keterangan" maxlength="200"></div>
          <button class="btn btn-utama" type="submit"><?= ikon('kamera', 15) ?> Unggah</button>
        </form>
        <?php endif; ?>
      </div>
    </div>
    <?php $modalLaporan(); return;
}

$cari = trim((string) ($_GET['cari'] ?? ''));
$fStatus = (string) ($_GET['status'] ?? 'terbuka');
$fStatus = in_array($fStatus, ['terbuka', 'semua'], true) || array_key_exists($fStatus, ASET_STATUS_RUSAK) ? $fStatus : 'terbuka';
$fTingkat = array_key_exists($_GET['tingkat'] ?? '', ASET_TINGKAT) ? $_GET['tingkat'] : '';

$stat = row(
    "SELECT SUM(status IN ('dilaporkan','diproses')) AS terbuka, SUM(status IN ('dilaporkan','diproses') AND tingkat = 'berat') AS berat,
            SUM(status = 'selesai' AND tanggal_selesai >= ?) AS selesai_bulan, COALESCE(SUM(CASE WHEN status = 'selesai' THEN biaya END), 0) AS biaya
       FROM aset_kerusakan WHERE jenis = ?",
    [date('Y-m-01'), $jenis]
) ?? [];
$syarat = ['k.jenis = ?'];
$par = [$jenis];
if ($fStatus === 'terbuka') {
    $syarat[] = "k.status IN ('dilaporkan','diproses')";
} elseif ($fStatus !== 'semua') {
    $syarat[] = 'k.status = ?';
    $par[] = $fStatus;
}
if ($fTingkat !== '') {
    $syarat[] = 'k.tingkat = ?';
    $par[] = $fTingkat;
}
if ($cari !== '') {
    $syarat[] = '(k.judul LIKE ? OR k.nomor LIKE ? OR b.nama LIKE ? OR r.nama LIKE ? OR k.pelapor LIKE ?)';
    $par = array_merge($par, array_fill(0, 5, '%' . $cari . '%'));
}
$daftar = rows(
    "SELECT k.*, b.nama AS barang_nama, b.kode AS barang_kode, r.nama AS ruang_nama,
            (SELECT COUNT(*) FROM aset_foto f WHERE f.kerusakan_id = k.id) AS jml_foto
       FROM aset_kerusakan k LEFT JOIN aset_barang b ON b.id = k.barang_id LEFT JOIN aset_ruang r ON r.id = k.ruang_id
      WHERE " . implode(' AND ', $syarat) . " ORDER BY (k.status IN ('selesai','ditolak')), FIELD(k.tingkat, 'berat','sedang','ringan'), k.tanggal_lapor DESC, k.id DESC LIMIT 300",
    $par
);
$penginput = aset_penginput('aset_kerusakan', $daftar);
?>
<form method="get" class="saring">
  <input type="hidden" name="h" value="<?= e($h) ?>">
  <div class="form-baris" style="flex:1"><label class="fl">Cari</label><input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nomor, ringkasan, <?= $jenis === 'sarana' ? 'barang' : 'ruangan' ?>, atau pelapor"></div>
  <div class="form-baris"><label class="fl">Status</label><select name="status" data-auto-kirim>
    <option value="terbuka" <?= $fStatus === 'terbuka' ? 'selected' : '' ?>>Belum selesai</option><option value="semua" <?= $fStatus === 'semua' ? 'selected' : '' ?>>Semua status</option>
    <?php foreach (ASET_STATUS_RUSAK as $sk => $sd): ?><option value="<?= $sk ?>" <?= $fStatus === $sk ? 'selected' : '' ?>><?= e($sd['label']) ?></option><?php endforeach; ?></select></div>
  <div class="form-baris"><label class="fl">Tingkat</label><select name="tingkat" data-auto-kirim><option value="">Semua tingkat</option>
    <?php foreach (ASET_TINGKAT as $tk => $td): ?><option value="<?= $tk ?>" <?= $fTingkat === $tk ? 'selected' : '' ?>><?= e($td['label']) ?></option><?php endforeach; ?></select></div>
  <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
</form>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('segitiga', 18) ?> Laporan <?= e(strtolower($namaJenis)) ?></h2><p class="ket"><?= count($daftar) ?> laporan</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiRusak" data-reset data-judul="Buat laporan kerusakan"
        data-isi='<?= e(json_encode(['id' => '', 'tingkat' => 'ringan', 'status' => 'dilaporkan', 'tanggal_lapor' => $sekarang, 'pelapor' => '', 'barang_id' => '', 'ruang_id' => ''])) ?>'><?= ikon('tambah', 15) ?> Buat laporan</button></div>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong($fStatus === 'terbuka' ? 'Tidak ada kerusakan yang belum selesai' : 'Belum ada laporan') ?>
  <?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Nomor / tanggal</th><th><?= $jenis === 'sarana' ? 'Barang' : 'Lokasi / bagian' ?></th><th>Kerusakan</th><th>Tingkat</th><th>Status</th><th>Diinput oleh</th><th class="no-cetak kolom-aksi">Aksi</th></tr></thead>
    <tbody>
    <?php foreach ($daftar as $k): ?>
      <tr>
        <td class="nowrap"><code><?= e($k['nomor']) ?></code><div class="kecil lemah"><?= e(tanggal_pendek($k['tanggal_lapor'])) ?></div></td>
        <td><?php if ($jenis === 'sarana'): ?><b><?= e($k['barang_nama'] ?? '-') ?></b><div class="kecil lemah"><?= e(trim(($k['barang_kode'] ?? '') . ($k['ruang_nama'] ? ' · ' . $k['ruang_nama'] : ''), ' ·')) ?></div>
            <?php else: ?><b><?= e($k['ruang_nama'] ?? '-') ?></b><div class="kecil lemah"><?= e($k['bagian'] ?: '-') ?></div><?php endif; ?></td>
        <td><a href="<?= e(url($h, ['aksi' => 'lihat', 'id' => $k['id']])) ?>"><b><?= e($k['judul']) ?></b></a><?= $k['jml_foto'] ? '<div class="kecil lemah">' . (int) $k['jml_foto'] . ' foto</div>' : '' ?></td>
        <td><?= lencana_dari(ASET_TINGKAT, $k['tingkat']) ?></td>
        <td><?= lencana_dari(ASET_STATUS_RUSAK, $k['status']) ?></td>
        <td><?= aset_sel_penginput($penginput[(int) $k['id']] ?? null) ?></td>
        <td class="no-cetak"><div class="aksi-baris"><a class="btn btn-hantu btn-ikon" href="<?= e(url($h, ['aksi' => 'lihat', 'id' => $k['id']])) ?>" title="Buka"><?= ikon('mata', 15) ?></a>
          <?php if ($penuh || $milikCs($k)): ?><button class="btn btn-hantu btn-ikon" type="button" title="Ubah" data-buka="tiraiRusak" data-judul="Ubah laporan" data-isi='<?= $dataModal($k) ?>'><?= ikon('sunting', 15) ?></button><?php endif; ?>
          <?php if ($penuh): ?><form method="post" data-konfirmasi="Hapus laporan <?= e($k['nomor']) ?> beserta foto buktinya?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
            <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form><?php endif; ?></div></td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
</div>
<?php $modalLaporan(); ?>
