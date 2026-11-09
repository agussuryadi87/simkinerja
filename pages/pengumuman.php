<?php
$saya = uid();
$bolehTulis = boleh_umumkan();
$admin = in_array((string) (user()['peran_kode'] ?? ''), ['admin', 'kabiro'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $balik = (string) ($_POST['balik'] ?? '');
    $tujuan = preg_match('/^h=[a-z_]+(&[A-Za-z0-9_\[\]%=.+\-]*)*$/', $balik) ? base_url('index.php?' . $balik) : url('pengumuman');
    if ($op === 'kirim' && $bolehTulis) {
        $judul = mb_substr(trim(post_teks('judul')), 0, 150);
        $isi = mb_substr(trim(post_teks('isi')), 0, 3000);
        $sasaran = array_key_exists(post('sasaran'), PENGUMUMAN_SASARAN) ? post('sasaran') : 'semua';
        $sampai = preg_match('/^\d{4}-\d{2}-\d{2}$/', post('sampai')) ? post('sampai') : '';
        if ($judul === '' || $isi === '') {
            flash('Judul dan isi pengumuman wajib diisi.', 'galat');
            redirect($tujuan);
        }
        $id = aset_publik_ubah('pengumuman.json', static function (array &$d) use ($judul, $isi, $sasaran, $sampai): int {
            $id = (int) ($d['urut'] ?? 0) + 1;
            $d['urut'] = $id;
            $d['daftar'][] = ['id' => $id, 'judul' => $judul, 'isi' => $isi, 'sasaran' => $sasaran, 'sampai' => $sampai,
                'penting' => post('penting') === '1', 'waktu' => date('Y-m-d H:i:s'),
                'oleh' => ['id' => uid(), 'nama' => (string) (user()['nama'] ?? ''), 'peran' => (string) (user()['peran_nama'] ?? '')]];
            return $id;
        });
        if (!$id) {
            flash('Pengumuman gagal disimpan. Coba lagi.', 'galat');
            redirect($tujuan);
        }
        catat_log('kirim_pengumuman', 'pengumuman', (int) $id, $judul);
        flash('Pengumuman <b>' . e($judul) . '</b> terkirim ke ' . e(mb_strtolower(PENGUMUMAN_SASARAN[$sasaran])) . '.');
        redirect($tujuan);
    }
    if ($op === 'hapus' && $bolehTulis) {
        $id = (int) post('id');
        $hapus = aset_publik_ubah('pengumuman.json', static function (array &$d) use ($id, $saya, $admin): bool {
            foreach ((array) ($d['daftar'] ?? []) as $i => $p) {
                if ((int) ($p['id'] ?? 0) === $id && ($admin || (int) ($p['oleh']['id'] ?? 0) === $saya)) {
                    unset($d['daftar'][$i]);
                    $d['daftar'] = array_values($d['daftar']);
                    return true;
                }
            }
            return false;
        });
        if ($hapus) {
            catat_log('hapus_pengumuman', 'pengumuman', $id);
        }
        flash($hapus ? 'Pengumuman dihapus.' : 'Pengumuman tidak ditemukan atau bukan milik Anda.', $hapus ? 'ok' : 'galat');
        kembali_ke('pengumuman');
    }
    tolak_akses();
}

$daftar = $bolehTulis ? array_values(array_filter(pengumuman_semua(), static function (array $p) use ($saya, $admin): bool {
    return pengumuman_untuk_saya($p) || $admin || (int) ($p['oleh']['id'] ?? 0) === $saya;
})) : pengumuman_saya();
$batasLihat = pengumuman_dilihat($saya);
pengumuman_tandai_dilihat($saya);
$subjudul = 'Pesan dari pimpinan untuk seluruh pegawai';
?>

<div class="kartu">
  <div class="kartu-kepala">
    <div><h2><?= ikon('lonceng', 18) ?> Pengumuman</h2><p class="ket"><?= count($daftar) ?> pengumuman</p></div>
    <?php if ($bolehTulis): ?>
      <div class="aksi"><button class="btn btn-utama btn-kecil" type="button" data-buka="tiraiPengumuman"><?= ikon('tambah', 15) ?> Tulis pengumuman</button></div>
    <?php endif; ?>
  </div>
  <?php if (!$daftar): ?>
    <?= sel_kosong('Belum ada pengumuman') ?>
  <?php else: ?>
  <div class="kartu-isi">
    <?php foreach ($daftar as $p): $kadaluarsa = !empty($p['sampai']) && $p['sampai'] < date('Y-m-d'); ?>
      <div class="pesan-kartu pengumuman-kartu<?= (string) $p['waktu'] > $batasLihat && (int) ($p['oleh']['id'] ?? 0) !== $saya ? ' baru' : '' ?><?= !empty($p['penting']) ? ' penting' : '' ?>" id="pg-<?= (int) $p['id'] ?>">
        <div class="kepala">
          <?= avatar((string) ($p['oleh']['nama'] ?? '-'), 'kecil') ?>
          <b class="kecil"><?= e($p['oleh']['nama'] ?? '-') ?></b>
          <?php if (!empty($p['penting'])): ?><?= lencana('Penting', 'emas') ?><?php endif; ?>
          <?= lencana(PENGUMUMAN_SASARAN[$p['sasaran'] ?? 'semua'] ?? 'Semua pengguna', 'abu') ?>
          <?php if ($kadaluarsa): ?><?= lencana('Berakhir', 'abu') ?><?php endif; ?>
          <span class="wkt"><?= e(tanggal_id(substr($p['waktu'], 0, 10))) ?> · <?= e(substr($p['waktu'], 11, 5)) ?><?= !empty($p['sampai']) ? ' · berlaku s.d. ' . e(tanggal_pendek($p['sampai'])) : '' ?></span>
        </div>
        <h3 class="pengumuman-judul"><?= e($p['judul']) ?></h3>
        <div class="isi-pesan"><?= e($p['isi']) ?></div>
        <?php if ($bolehTulis && ($admin || (int) ($p['oleh']['id'] ?? 0) === $saya)): ?>
          <form method="post" class="mt8" data-konfirmasi="Hapus pengumuman ini? Pengumuman akan hilang dari lonceng semua pengguna."><?= csrf_field() ?>
            <input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
            <button class="btn btn-hantu btn-kecil" type="submit"><?= ikon('hapus', 14) ?> Hapus</button></form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>
</div>
