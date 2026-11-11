<?php
$subjudul = '';
$dir = APP_ROOT . '/uploads/aset';
$kolomTujuan = ['b' => ['barang_id', 'aset_barang'], 'r' => ['ruang_id', 'aset_ruang'], 'k' => ['kerusakan_id', 'aset_kerusakan'], 'p' => ['pengajuan_id', 'aset_pengajuan']];

$yatim = static function () use ($dir, $kolomTujuan): array {
    $terdaftar = array_flip(array_column(rows('SELECT berkas FROM aset_foto'), 'berkas'));
    $hasil = [];
    foreach (@scandir($dir) ?: [] as $nama) {
        if (!aset_berkas_sah($nama) || isset($terdaftar[$nama])) {
            continue;
        }
        $awal = $nama[0] === 'k' || $nama[0] === 'b' || $nama[0] === 'r' || $nama[0] === 'p' ? $nama[0] : '';
        if ($awal === '' || !preg_match('/^[a-z]{1}(\d+)-/', $nama, $m)) {
            continue;
        }
        [$kolom, $tabel] = $kolomTujuan[$awal];
        $hasil[] = ['berkas' => $nama, 'kolom' => $kolom, 'tabel' => $tabel, 'id' => (int) $m[1], 'ada' => (int) val("SELECT COUNT(*) FROM $tabel WHERE id = ?", [(int) $m[1]], 0) > 0];
    }
    return $hasil;
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if ($op === 'tautkan') {
        $n = 0;
        foreach ($yatim() as $y) {
            if ($y['ada']) {
                insert('aset_foto', [$y['kolom'] => $y['id'], 'berkas' => $y['berkas'], 'keterangan' => 'Ditautkan otomatis']);
                $n++;
            }
        }
        catat_log('tautkan_foto_aset', 'aset_foto', 0, (string) $n);
        flash($n > 0 ? $n . ' berkas foto yang belum tercatat berhasil ditautkan ke datanya.' : 'Tidak ada berkas yatim yang cocok dengan data.', $n > 0 ? 'ok' : 'info');
    } elseif ($op === 'bersih') {
        $n = 0;
        foreach (rows('SELECT id, berkas FROM aset_foto') as $f) {
            if (!is_file($dir . '/' . $f['berkas'])) {
                q('DELETE FROM aset_foto WHERE id = ?', [$f['id']]);
                $n++;
            }
        }
        catat_log('bersih_foto_aset', 'aset_foto', 0, (string) $n);
        flash($n . ' catatan foto tanpa berkas dibersihkan. Unggah ulang fotonya dari halaman barang atau ruangan.', $n > 0 ? 'ok' : 'info');
    } elseif ($op === 'uji_tulis') {
        $uji = $dir . '/.uji-' . bin2hex(random_bytes(4));
        $ok = is_dir($dir) && @file_put_contents($uji, 'x') === 1 && @unlink($uji);
        flash($ok ? 'Folder foto dapat ditulis oleh server.' : 'Folder foto TIDAK dapat ditulis. Atur izin folder uploads/aset ke 755 atau 775 di hosting.', $ok ? 'ok' : 'galat');
    }
    kembali_ke('aset_foto_cek');
}

$fotos = rows(
    "SELECT f.*, b.kode AS barang_kode, b.nama AS barang_nama, r.nama AS ruang_nama, k.nomor AS rusak_nomor, p.nomor AS pengajuan_nomor
       FROM aset_foto f LEFT JOIN aset_barang b ON b.id = f.barang_id LEFT JOIN aset_ruang r ON r.id = f.ruang_id
       LEFT JOIN aset_kerusakan k ON k.id = f.kerusakan_id LEFT JOIN aset_pengajuan p ON p.id = f.pengajuan_id
      ORDER BY f.id DESC LIMIT 400"
);
$yatimDaftar = $yatim();
$hilang = 0;
$info = static function (string $berkas) use ($dir): array {
    $jalur = $dir . '/' . $berkas;
    if (!is_file($jalur)) {
        return ['ada' => false, 'ukuran' => 0, 'mime' => '', 'baca' => false, 'dimensi' => ''];
    }
    $g = @getimagesize($jalur);
    return ['ada' => true, 'ukuran' => (int) @filesize($jalur), 'mime' => $g['mime'] ?? '', 'baca' => is_readable($jalur), 'dimensi' => $g ? $g[0] . '×' . $g[1] : ''];
};
$ukur = static function (int $b): string {
    return $b >= 1048576 ? number_format($b / 1048576, 1, ',', '.') . ' MB' : number_format($b / 1024, 0, ',', '.') . ' KB';
};
$ini = static function (string $k): string {
    $v = ini_get($k);
    return $v === false || $v === '' ? '-' : (string) $v;
};
$bebas = @disk_free_space($dir);
$tulis = is_dir($dir) && is_writable($dir);
$kompres = in_array(strtolower($ini('zlib.output_compression')), ['1', 'on', 'true'], true);
$lingkungan = [
    ['Versi PHP', PHP_VERSION, true],
    ['Folder foto (uploads/aset)', is_dir($dir) ? ($tulis ? 'ada, dapat ditulis' : 'ada, TIDAK dapat ditulis') : 'belum ada', $tulis],
    ['Batas unggah per file (upload_max_filesize)', $ini('upload_max_filesize'), true],
    ['Batas total kiriman (post_max_size)', $ini('post_max_size'), true],
    ['Jumlah file per kiriman (max_file_uploads)', $ini('max_file_uploads'), true],
    ['Unggah file aktif (file_uploads)', $ini('file_uploads') === '1' || strtolower($ini('file_uploads')) === 'on' ? 'ya' : 'tidak', $ini('file_uploads') === '1' || strtolower($ini('file_uploads')) === 'on'],
    ['Kompresi keluaran PHP (zlib.output_compression)', $kompres ? 'aktif (sudah ditangani sistem)' : 'nonaktif', true],
    ['Ekstensi fileinfo', extension_loaded('fileinfo') ? 'aktif' : 'tidak aktif', extension_loaded('fileinfo')],
    ['Ekstensi GD', extension_loaded('gd') ? 'aktif' : 'tidak aktif', true],
    ['Ruang disk kosong', $bebas !== false ? $ukur((int) $bebas) : '-', $bebas === false || $bebas > 20971520],
];
?>
<div class="no-cetak" style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:14px">
  <a class="btn btn-garis" href="<?= e(url('aset_barang')) ?>"><?= ikon('kembali', 15) ?> Inventaris barang</a>
</div>

<div class="kartu">
  <div class="kartu-kepala"><div><h2>Pemeriksaan server foto</h2><p class="ket">Kondisi server tempat sistem ini berjalan (lokal atau hosting).</p></div>
    <div class="aksi no-cetak"><form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="uji_tulis"><button class="btn btn-garis btn-kecil" type="submit">Uji tulis folder</button></form></div></div>
  <div class="tabel-bungkus"><table class="tabel">
    <tbody>
    <?php foreach ($lingkungan as [$nama, $nilai, $baik]): ?>
      <tr><td><?= e($nama) ?></td><td><b><?= e($nilai) ?></b></td><td><?= $baik ? lencana('Baik', 'hijau') : lencana('Perlu dicek', 'merah') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
</div>

<div class="kartu">
  <div class="kartu-kepala"><div><h2>Daftar foto di sistem ini</h2><p class="ket"><?= count($fotos) ?> catatan foto · uji muat dilakukan langsung dari peramban Anda</p></div>
    <div class="aksi no-cetak"><button class="btn btn-utama btn-kecil" type="button" id="ujiMuat"><?= ikon('ulang', 14) ?> Uji muat semua foto</button></div></div>
  <?php if (!$fotos): ?><?= sel_kosong('Belum ada catatan foto') ?><?php else: ?>
  <div class="tabel-bungkus"><table class="tabel" id="tabelFoto">
    <thead><tr><th>Untuk</th><th>Berkas</th><th>Di server</th><th>Ukuran</th><th>Hasil uji muat</th></tr></thead>
    <tbody>
    <?php foreach ($fotos as $f):
        $i = $info($f['berkas']);
        $hilang += $i['ada'] ? 0 : 1;
        $untuk = $f['barang_id'] ? 'Barang ' . ($f['barang_kode'] ?: '#' . $f['barang_id']) : ($f['ruang_id'] ? 'Ruangan ' . ($f['ruang_nama'] ?: '#' . $f['ruang_id']) : ($f['kerusakan_id'] ? 'Kerusakan ' . ($f['rusak_nomor'] ?: '#' . $f['kerusakan_id']) : 'Pengajuan ' . ($f['pengajuan_nomor'] ?: '#' . $f['pengajuan_id']))); ?>
      <tr data-url="<?= e(aset_foto_url($f['berkas'])) ?>" data-ukuran="<?= (int) $i['ukuran'] ?>">
        <td><?= e($untuk) ?></td>
        <td><code><?= e($f['berkas']) ?></code></td>
        <td><?= $i['ada'] ? lencana($i['baca'] ? 'Ada' : 'Tidak terbaca', $i['baca'] ? 'hijau' : 'merah') . ($i['dimensi'] ? ' <span class="kecil lemah">' . e($i['dimensi']) . '</span>' : '') : lencana('Berkas hilang', 'merah') ?></td>
        <td class="kecil"><?= $i['ada'] ? e($ukur($i['ukuran'])) : '-' ?></td>
        <td class="hasil-uji kecil lemah">belum diuji</td>
      </tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <?php endif; ?>
  <?php if ($hilang > 0): ?>
    <div class="kartu-isi no-cetak"><p><b><?= $hilang ?></b> catatan foto tidak punya berkasnya di server (misalnya data disalin tanpa folder uploads/aset).</p>
      <form method="post" data-konfirmasi="Hapus catatan foto yang berkasnya hilang? Foto perlu diunggah ulang."><?= csrf_field() ?><input type="hidden" name="op" value="bersih"><button class="btn btn-garis" type="submit">Bersihkan catatan tanpa berkas</button></form></div>
  <?php endif; ?>
</div>

<div class="kartu">
  <div class="kartu-kepala"><div><h2>Berkas foto yang belum tercatat</h2><p class="ket"><?= count($yatimDaftar) ?> berkas ada di folder tetapi tidak muncul di sistem</p></div></div>
  <?php if (!$yatimDaftar): ?><?= sel_kosong('Semua berkas di folder sudah tercatat') ?><?php else: ?>
  <div class="tabel-bungkus"><table class="tabel">
    <thead><tr><th>Berkas</th><th>Untuk</th><th>Data tujuan</th></tr></thead>
    <tbody>
    <?php foreach ($yatimDaftar as $y): ?>
      <tr><td><code><?= e($y['berkas']) ?></code></td><td class="kecil"><?= e($y['tabel']) ?> #<?= (int) $y['id'] ?></td><td><?= $y['ada'] ? lencana('Data ditemukan', 'hijau') : lencana('Data tidak ada', 'abu') ?></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
  <div class="kartu-isi no-cetak"><p class="kecil lemah">Berkas ini biasanya hasil menyalin folder foto lewat FTP tanpa data databasenya. Tautkan ulang agar tampil, hanya untuk yang datanya ditemukan dengan nomor ID yang sama.</p>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="op" value="tautkan"><button class="btn btn-utama" type="submit">Tautkan berkas yang datanya ditemukan</button></form></div>
  <?php endif; ?>
</div>

<script nonce="<?= csp_nonce() ?>">
  (function () {
    var tombol = document.getElementById('ujiMuat');
    if (!tombol) return;
    function uji(tr) {
      var sel = tr.querySelector('.hasil-uji'), url = tr.getAttribute('data-url'), ukuran = parseInt(tr.getAttribute('data-ukuran'), 10) || 0;
      sel.textContent = 'menguji…';
      return fetch(url, { credentials: 'same-origin', cache: 'no-store' }).then(function (r) {
        var tipe = r.headers.get('content-type') || '', enc = r.headers.get('content-encoding') || '';
        if (!r.ok) { sel.innerHTML = '<span style="color:#b42318">Gagal: HTTP ' + r.status + (r.status === 404 ? ' (berkas tidak ditemukan / tidak boleh diakses)' : '') + '</span>'; return; }
        if (tipe.indexOf('image/') !== 0) { sel.innerHTML = '<span style="color:#b42318">Gagal: server mengirim ' + (tipe || 'tipe tidak dikenal') + ', bukan gambar</span>'; return; }
        return r.blob().then(function (b) {
          if (ukuran && b.size !== ukuran && !enc) { sel.innerHTML = '<span style="color:#b42318">Gagal: ukuran diterima ' + b.size + ' B, seharusnya ' + ukuran + ' B</span>'; return; }
          return new Promise(function (selesai) {
            var im = new Image(), u = URL.createObjectURL(b);
            im.onload = function () { sel.innerHTML = '<span style="color:#147a3d">Tampil (' + im.naturalWidth + '×' + im.naturalHeight + ')' + (enc ? ' · ' + enc : '') + '</span>'; URL.revokeObjectURL(u); selesai(); };
            im.onerror = function () { sel.innerHTML = '<span style="color:#b42318">Gagal: data diterima tetapi bukan gambar valid (rusak)</span>'; URL.revokeObjectURL(u); selesai(); };
            im.src = u;
          });
        });
      }).catch(function (e) { sel.innerHTML = '<span style="color:#b42318">Gagal: ' + (e && e.message ? e.message : 'koneksi terputus') + '</span>'; });
    }
    tombol.addEventListener('click', function () {
      var baris = Array.prototype.slice.call(document.querySelectorAll('#tabelFoto tbody tr')), i = 0;
      tombol.disabled = true;
      (function lanjut() {
        if (i >= baris.length) { tombol.disabled = false; return; }
        uji(baris[i++]).then(lanjut, lanjut);
      })();
    });
  })();
</script>
