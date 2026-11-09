<?php

function rapat_get(int $id): ?array
{
    return row(
        'SELECT r.*, un.nama AS unit_nama, nt.nama AS notulis_nama, pb.nama AS pembuat_nama
           FROM rapat r
           LEFT JOIN unit un ON un.id = r.unit_id
           LEFT JOIN pengguna nt ON nt.id = r.notulis_id
           LEFT JOIN pengguna pb ON pb.id = r.dibuat_oleh
          WHERE r.id = ?',
        [$id]
    );
}

function rapat_peserta(int $rapatId): array
{
    return rows(
        'SELECT * FROM rapat_peserta WHERE rapat_id = ? ORDER BY eksternal, unit_nama, nama',
        [$rapatId]
    );
}

function rapat_rtl_daftar(int $rapatId): array
{
    return rows(
        'SELECT t.*, un.nama AS unit_nama FROM rapat_rtl t LEFT JOIN unit un ON un.id = t.unit_id
          WHERE t.rapat_id = ? ORDER BY t.id',
        [$rapatId]
    );
}

function rapat_dokumentasi_daftar(int $rapatId): array
{
    return rows('SELECT * FROM rapat_dokumentasi WHERE rapat_id = ? ORDER BY id', [$rapatId]);
}

function rapat_ikut_serta(int $rapatId, int $penggunaId): bool
{
    return (int) val('SELECT COUNT(*) FROM rapat_peserta WHERE rapat_id = ? AND pengguna_id = ?', [$rapatId, $penggunaId], 0) > 0;
}

function rapat_boleh_lihat(array $r): bool
{
    return can('rapat_lihat_semua')
        || (int) $r['dibuat_oleh'] === uid()
        || (int) $r['notulis_id'] === uid()
        || rapat_ikut_serta((int) $r['id'], uid());
}

function rapat_boleh_ubah(array $r): bool
{
    if (can('rapat_kelola')) {
        return true;
    }
    if ($r['status'] === 'final') {
        return false;
    }
    return (int) $r['dibuat_oleh'] === uid() || (int) $r['notulis_id'] === uid();
}

function rapat_boleh_ubah_rtl(array $rtl, array $r): bool
{
    if (rapat_boleh_ubah($r) || can('rapat_kelola')) {
        return true;
    }
    $u = user();
    return $u && $rtl['unit_id'] !== null && (int) $u['unit_id'] === (int) $rtl['unit_id'];
}

function rapat_daftar_terlihat(string $cari = '', string $jenis = '', int $unitId = 0, int $limit = 0): array
{
    $syarat = '1=1';
    $param = [];
    if (!can('rapat_lihat_semua')) {
        $syarat .= ' AND (r.dibuat_oleh = ? OR r.notulis_id = ? OR r.id IN (SELECT rapat_id FROM rapat_peserta WHERE pengguna_id = ?))';
        array_push($param, uid(), uid(), uid());
    }
    if ($cari !== '') {
        $syarat .= ' AND (r.judul LIKE ? OR r.agenda LIKE ? OR r.tempat LIKE ?)';
        $like = '%' . $cari . '%';
        array_push($param, $like, $like, $like);
    }
    if ($jenis !== '' && array_key_exists($jenis, JENIS_RAPAT)) {
        $syarat .= ' AND r.jenis = ?';
        $param[] = $jenis;
    }
    if ($unitId > 0) {
        $syarat .= ' AND r.id IN (SELECT pp.rapat_id FROM rapat_peserta pp JOIN pengguna pg ON pg.id = pp.pengguna_id WHERE pg.unit_id = ?)';
        $param[] = $unitId;
    }
    return rows(
        "SELECT r.*, un.nama AS unit_nama,
                (SELECT COUNT(*) FROM rapat_peserta p WHERE p.rapat_id = r.id) AS jml_peserta,
                (SELECT COUNT(*) FROM rapat_peserta p WHERE p.rapat_id = r.id AND p.kehadiran = 'hadir') AS jml_hadir,
                (SELECT COUNT(*) FROM rapat_rtl t WHERE t.rapat_id = r.id) AS jml_rtl,
                (SELECT COUNT(*) FROM rapat_rtl t WHERE t.rapat_id = r.id AND t.status <> 'selesai') AS rtl_terbuka,
                (SELECT COUNT(*) FROM rapat_dokumentasi d WHERE d.rapat_id = r.id) AS jml_dok
           FROM rapat r LEFT JOIN unit un ON un.id = r.unit_id
          WHERE $syarat ORDER BY r.tanggal DESC, r.jam_mulai DESC, r.id DESC" . ($limit > 0 ? ' LIMIT ' . (int) $limit : ''),
        $param
    );
}

function rapat_tambah_peserta_internal(int $rapatId, array $penggunaIds): int
{
    $n = 0;
    foreach (array_unique(array_map('intval', $penggunaIds)) as $pid) {
        if ($pid <= 0) {
            continue;
        }
        $p = pegawai_get($pid);
        if (!$p || !(int) $p['aktif']) {
            continue;
        }
        $n += q(
            'INSERT IGNORE INTO rapat_peserta (rapat_id, pengguna_id, nama, jabatan, unit_nama, dilihat_pada) VALUES (?, ?, ?, ?, ?, ?)',
            [$rapatId, $pid, $p['nama'], $p['jabatan'] ?: $p['peran_nama'], $p['unit_nama'] ?? '', $pid === uid() ? date('Y-m-d H:i:s') : null]
        )->rowCount();
    }
    return $n;
}

function rapat_navigasi(string $aktif, ?int $jumlahRapat = null, int $rtlTerbuka = 0): string
{
    $item = [
        'daftar'        => ['Daftar Rapat', url('rapat'), $jumlahRapat !== null ? ' <span class="bilah-hitung">' . $jumlahRapat . '</span>' : ''],
        'rtl'           => ['Pemantauan RTL', url('rapat', ['tab' => 'rtl']), $rtlTerbuka > 0 ? ' <span class="bilah-hitung emas">' . $rtlTerbuka . '</span>' : ''],
        'rapat_dokumen' => ['Dokumen', url('rapat_dokumen'), ''],
    ];
    $o = '<div class="aset-bilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar gambar-rapat"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav" aria-label="Menu Tata Usaha Universitas">';
    foreach ($item as $kunci => [$label, $tautan, $tambahan]) {
        $o .= '<div class="aset-grup"><a href="' . e($tautan) . '"' . ($kunci === $aktif ? ' class="aktif" aria-current="page"' : '') . '>' . e($label) . $tambahan . '</a></div>';
    }
    return $o . '</nav></div>';
}

function rapat_normalisasi_berkas(array $berkas): array
{
    $hasil = [];
    if (!isset($berkas['name'])) {
        return $hasil;
    }
    if (!is_array($berkas['name'])) {
        return [$berkas];
    }
    foreach (array_keys($berkas['name']) as $i) {
        $hasil[] = [
            'name'     => $berkas['name'][$i],
            'tmp_name' => $berkas['tmp_name'][$i],
            'error'    => $berkas['error'][$i],
            'size'     => $berkas['size'][$i],
        ];
    }
    return $hasil;
}

function proses_dokumentasi_unggah(array $berkas, int $rapatId): array
{
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return ['abaikan' => true];
    }
    if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'] ?? '')) {
        return ['galat' => 'Satu berkas gagal diunggah (' . ($kode === UPLOAD_ERR_INI_SIZE ? 'terlalu besar' : 'kesalahan unggah') . ').'];
    }
    $cek = validasi_foto($berkas['tmp_name'], (int) $berkas['size']);
    if (isset($cek['galat'])) {
        return ['galat' => $cek['galat']];
    }
    $dir = APP_ROOT . '/uploads/rapat';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['galat' => 'Folder dokumentasi tidak dapat dibuat.'];
    }
    $nama = $rapatId . '-' . bin2hex(random_bytes(8)) . '.' . $cek['ekstensi'];
    if (!move_uploaded_file($berkas['tmp_name'], $dir . '/' . $nama)) {
        return ['galat' => 'Dokumentasi tidak dapat disimpan di server.'];
    }
    return ['file' => $nama];
}

function hapus_berkas_dokumentasi(?string $nama): void
{
    if ($nama !== null && preg_match('/^\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nama)) {
        @unlink(APP_ROOT . '/uploads/rapat/' . $nama);
    }
}

function rapat_notifikasi(int $penggunaId): array
{
    $baris = rows(
        'SELECT r.id, r.judul, r.tanggal, r.jam_mulai, r.tempat, p.dilihat_pada
           FROM rapat_peserta p JOIN rapat r ON r.id = p.rapat_id
          WHERE p.pengguna_id = ? AND (p.dilihat_pada IS NULL OR r.tanggal >= CURDATE())
          ORDER BY (p.dilihat_pada IS NULL) DESC, r.tanggal, r.jam_mulai, r.id LIMIT 8',
        [$penggunaId]
    );
    $baru = (int) val('SELECT COUNT(*) FROM rapat_peserta WHERE pengguna_id = ? AND dilihat_pada IS NULL', [$penggunaId], 0);
    $daftar = [];
    foreach ($baris as $b) {
        $daftar[] = [
            'id'     => (int) $b['id'],
            'judul'  => $b['judul'],
            'waktu'  => tanggal_pendek($b['tanggal']) . ($b['jam_mulai'] ? ' · ' . jam_pendek($b['jam_mulai']) : ''),
            'tempat' => $b['tempat'],
            'baru'   => $b['dilihat_pada'] === null,
            'url'    => url('rapat', ['aksi' => 'lihat', 'id' => (int) $b['id']]),
        ];
    }
    return ['baru' => $baru, 'daftar' => $daftar];
}

function rapat_tandai_dilihat(int $penggunaId, int $rapatId = 0): void
{
    q(
        'UPDATE rapat_peserta SET dilihat_pada = NOW() WHERE pengguna_id = ? AND dilihat_pada IS NULL' . ($rapatId ? ' AND rapat_id = ?' : ''),
        $rapatId ? [$penggunaId, $rapatId] : [$penggunaId]
    );
}

function rapat_tgl_waktu(array $r): string
{
    $teks = tanggal_id($r['tanggal'], true);
    if ($r['jam_mulai']) {
        $teks .= ', pukul ' . jam_pendek($r['jam_mulai']) . ($r['jam_selesai'] ? ' - ' . jam_pendek($r['jam_selesai']) : '') . ' WIB';
    }
    return $teks;
}
