<?php

const KEU_STATUS_RAPB = [
    'draf'      => ['label' => 'Draf',      'warna' => 'abu'],
    'diajukan'  => ['label' => 'Diajukan',  'warna' => 'biru'],
    'disetujui' => ['label' => 'Disetujui', 'warna' => 'hijau'],
    'ditolak'   => ['label' => 'Ditolak',   'warna' => 'merah'],
];

const KEU_JENIS_ITEM = [
    'pendapatan' => ['label' => 'Pendapatan', 'warna' => 'hijau'],
    'belanja'    => ['label' => 'Belanja',    'warna' => 'merah'],
];

const KEU_RISIKO = [
    'rendah' => ['label' => 'Rendah', 'warna' => 'hijau'],
    'sedang' => ['label' => 'Sedang', 'warna' => 'emas'],
    'tinggi' => ['label' => 'Tinggi', 'warna' => 'merah'],
];

const KEU_STATUS_TEMUAN = [
    'baru'     => ['label' => 'Belum ditindaklanjuti', 'warna' => 'merah'],
    'berjalan' => ['label' => 'Dalam proses',          'warna' => 'biru'],
    'selesai'  => ['label' => 'Selesai',               'warna' => 'hijau'],
];

const KEU_OPINI = [
    ''    => 'Belum ada',
    'wtp' => 'Wajar Tanpa Pengecualian (WTP)',
    'wdp' => 'Wajar Dengan Pengecualian (WDP)',
    'tw'  => 'Tidak Wajar (TW)',
    'tmp' => 'Tidak Menyatakan Pendapat (TMP)',
];

function boleh_keu(): bool
{
    return uid() > 0 && can('modul_keu');
}

function wajib_keu(): void
{
    wajib_masuk();
    if (!boleh_keu()) {
        tolak_akses();
    }
}

function keu_halaman_aktif(): array
{
    return ['rapb', 'rapb_prodi', 'rapb_upps', 'rapb_lkps', 'audit', 'keu_dokumen'];
}

const KEU_DOK_JENIS = ['pdf' => 'PDF', 'doc' => 'Word', 'docx' => 'Word', 'xls' => 'Excel', 'xlsx' => 'Excel', 'jpg' => 'Gambar', 'jpeg' => 'Gambar', 'png' => 'Gambar'];

function keu_dok_dir(): string
{
    return APP_ROOT . '/uploads/audit';
}

function keu_dok_sah(?string $nama): bool
{
    return $nama !== null && (bool) preg_match('/^[at]\d+-[a-f0-9]{12}-[a-z0-9-]{1,60}\.(pdf|docx?|xlsx?|jpe?g|png)$/', $nama);
}

function keu_dok_audit_id(string $nama): int
{
    return (int) substr($nama, 1, strpos($nama, '-') - 1);
}

function keu_dok_slug(string $nama): string
{
    $nama = strtolower(pathinfo($nama, PATHINFO_FILENAME));
    $nama = trim((string) preg_replace('/[^a-z0-9]+/', '-', $nama), '-');
    return substr($nama, 0, 60) ?: 'dokumen';
}

function keu_dok_daftar(int $auditId, string $awalan = 'a'): array
{
    $hasil = [];
    foreach (@glob(keu_dok_dir() . '/' . $awalan . $auditId . '-*') ?: [] as $jalur) {
        $nama = basename($jalur);
        if (!keu_dok_sah($nama)) {
            continue;
        }
        $bagian = explode('-', pathinfo($nama, PATHINFO_FILENAME), 3);
        $hasil[] = ['file' => $nama, 'judul' => ucwords(str_replace('-', ' ', $bagian[2] ?? 'dokumen')),
                    'ext' => strtolower(pathinfo($nama, PATHINFO_EXTENSION)), 'ukuran' => (int) @filesize($jalur), 'waktu' => (int) @filemtime($jalur)];
    }
    usort($hasil, static function ($a, $b) { return $b['waktu'] <=> $a['waktu']; });
    return $hasil;
}

function keu_dok_hitung(string $awalan = 'a'): array
{
    $hasil = [];
    foreach (@scandir(keu_dok_dir()) ?: [] as $nama) {
        if (keu_dok_sah($nama) && $nama[0] === $awalan) {
            $a = keu_dok_audit_id($nama);
            $hasil[$a] = ($hasil[$a] ?? 0) + 1;
        }
    }
    return $hasil;
}

function keu_dok_cek(string $jalur, string $ext): bool
{
    $awal = (string) @file_get_contents($jalur, false, null, 0, 8);
    if ($ext === 'pdf') {
        return strncmp($awal, '%PDF', 4) === 0;
    }
    if ($ext === 'docx' || $ext === 'xlsx') {
        return strncmp($awal, "PK\x03\x04", 4) === 0;
    }
    if ($ext === 'doc' || $ext === 'xls') {
        return strncmp($awal, "\xD0\xCF\x11\xE0", 4) === 0;
    }
    $info = @getimagesize($jalur);
    return $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
}

function keu_dok_simpan(array $berkas, int $auditId, string $judul = '', string $awalan = 'a'): array
{
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'] ?? '')) {
        return ['galat' => $kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE ? 'Dokumen terlalu besar.' : 'Dokumen gagal diunggah.'];
    }
    if ((int) $berkas['size'] > FOTO_MAKS_BYTE) {
        return ['galat' => 'Ukuran dokumen maksimal ' . round(FOTO_MAKS_BYTE / 1048576) . ' MB.'];
    }
    $ext = strtolower(pathinfo((string) $berkas['name'], PATHINFO_EXTENSION));
    if (!isset(KEU_DOK_JENIS[$ext]) || !keu_dok_cek($berkas['tmp_name'], $ext)) {
        return ['galat' => 'Dokumen harus berupa PDF, Word, Excel, JPG, atau PNG yang sah.'];
    }
    if (!is_dir(keu_dok_dir()) && !@mkdir(keu_dok_dir(), 0775, true)) {
        return ['galat' => 'Folder dokumen tidak dapat dibuat.'];
    }
    $nama = ($awalan === 't' ? 't' : 'a') . $auditId . '-' . bin2hex(random_bytes(6)) . '-' . keu_dok_slug($judul !== '' ? $judul : (string) $berkas['name']) . '.' . $ext;
    if (!move_uploaded_file($berkas['tmp_name'], keu_dok_dir() . '/' . $nama)) {
        return ['galat' => 'Dokumen tidak dapat disimpan di server.'];
    }
    return ['file' => $nama];
}

function keu_dok_ganti_nama(string $nama, string $judul): ?string
{
    if (!keu_dok_sah($nama) || !is_file(keu_dok_dir() . '/' . $nama)) {
        return null;
    }
    $bagian = explode('-', pathinfo($nama, PATHINFO_FILENAME), 3);
    $baru = $bagian[0] . '-' . $bagian[1] . '-' . keu_dok_slug($judul) . '.' . pathinfo($nama, PATHINFO_EXTENSION);
    return $baru === $nama || @rename(keu_dok_dir() . '/' . $nama, keu_dok_dir() . '/' . $baru) ? $baru : null;
}

function keu_dok_hapus(string $nama): void
{
    if (keu_dok_sah($nama)) {
        @unlink(keu_dok_dir() . '/' . $nama);
    }
}

function keu_dok_hapus_milik(int $id, string $awalan): void
{
    foreach (keu_dok_daftar($id, $awalan) as $d) {
        keu_dok_hapus($d['file']);
    }
}

function keu_tautan_semua(bool $muatUlang = false): array
{
    static $data = null;
    if ($data === null || $muatUlang) {
        $berkas = keu_dok_dir() . '/tautan.json';
        $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
        $data = is_array($isi) ? $isi : [];
    }
    return $data;
}

function keu_tautan(string $kunci): string
{
    return (string) (keu_tautan_semua()[$kunci] ?? '');
}

function keu_tautan_simpan(string $kunci, string $url): bool
{
    $semua = keu_tautan_semua(true);
    if ($url === '') {
        unset($semua[$kunci]);
    } else {
        $semua[$kunci] = mb_substr($url, 0, 1000);
    }
    if (!is_dir(keu_dok_dir()) && !@mkdir(keu_dok_dir(), 0775, true)) {
        return false;
    }
    $berkas = keu_dok_dir() . '/tautan.json';
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode($semua, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    keu_tautan_semua(true);
    return true;
}

function rupiah($n): string
{
    return 'Rp ' . angka((float) $n);
}

function keu_navigasi(string $h): string
{
    $o = '<div class="aset-bilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav" aria-label="Menu Bidang Keuangan">';
    foreach (['rapb_prodi' => 'RAPB', 'audit' => 'Audit Independen', 'keu_dokumen' => 'Dokumen'] as $kunci => $label) {
        $aktif = $kunci === $h || ($kunci === 'rapb_prodi' && strpos($h, 'rapb') === 0);
        $o .= '<div class="aset-grup"><a href="' . e(url($kunci)) . '"' . ($aktif ? ' class="aktif" aria-current="page"' : '') . '>' . e($label) . '</a></div>';
    }
    return $o . '</nav></div>';
}

function keu_total_rapb(array $ids): array
{
    $hasil = [];
    foreach ($ids as $id) {
        $hasil[(int) $id] = ['pendapatan' => 0.0, 'belanja' => 0.0, 'real_pendapatan' => 0.0, 'real_belanja' => 0.0, 'item' => 0];
    }
    if (!$ids) {
        return $hasil;
    }
    foreach (rows(
        'SELECT rapb_id, jenis, COUNT(*) AS n, SUM(volume * harga) AS rencana, SUM(realisasi) AS realis FROM keu_rapb_item
          WHERE rapb_id IN (' . in_placeholder($ids) . ') GROUP BY rapb_id, jenis',
        array_values($ids)
    ) as $r) {
        $i = (int) $r['rapb_id'];
        $hasil[$i][$r['jenis']] = (float) $r['rencana'];
        $hasil[$i]['real_' . $r['jenis']] = (float) $r['realis'];
        $hasil[$i]['item'] += (int) $r['n'];
    }
    return $hasil;
}

function keu_svg_batang(array $label, array $seri, string $aria): string
{
    $n = max(count($label), 1);
    $maks = 1.0;
    foreach ($seri as [, , $nilai]) {
        $maks = max($maks, (float) max(array_merge([0], $nilai)));
    }
    $skala = $maks;
    $satuan = 1.0;
    $unit = 'Rp';
    if ($maks >= 1e9) { $satuan = 1e9; $unit = 'M'; } elseif ($maks >= 1e6) { $satuan = 1e6; $unit = 'jt'; }
    $x0 = 44; $x1 = 548; $yA = 16; $yB = 112;
    $slot = ($x1 - $x0) / $n;
    $jumlah = max(count($seri), 1);
    $lebar = min(22, $slot * 0.7 / $jumlah);
    $svg = '<svg viewBox="0 0 560 140" class="grafik-batang" role="img" aria-label="' . e($aria) . '">';
    foreach ([0, 0.5, 1] as $f) {
        $y = $yB - ($yB - $yA) * $f;
        $svg .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" stroke="' . ($f == 0 ? '#c9cfca' : '#eceeea') . '"/>'
              . '<text x="' . ($x0 - 6) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end" class="sumbu-teks">' . round($skala * $f / $satuan, 1) . ($f > 0 ? ' ' . $unit : '') . '</text>';
    }
    for ($i = 0; $i < count($label); $i++) {
        $cx = $x0 + $slot * ($i + 0.5);
        foreach ($seri as $s => [, $warna, $nilai]) {
            $v = (float) ($nilai[$i] ?? 0);
            $h = $v / $skala * ($yB - $yA);
            $x = $cx - ($jumlah * $lebar) / 2 + $s * $lebar;
            $svg .= '<rect x="' . round($x, 1) . '" y="' . round($yB - $h, 1) . '" width="' . round($lebar - 2, 1) . '" height="' . round($h, 1) . '" rx="2" fill="' . $warna . '"/>';
        }
        $svg .= '<text x="' . round($cx, 1) . '" y="130" text-anchor="middle" class="sumbu-teks">' . e($label[$i]) . '</text>';
    }
    return $svg . '</svg>';
}
