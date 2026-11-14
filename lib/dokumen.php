<?php

const DOKUMEN_MODUL = [
    'keu_dokumen'   => ['kode' => 'keu',   'nama' => 'Bidang Keuangan'],
    'jad_dokumen'   => ['kode' => 'jad',   'nama' => 'Kepegawaian & BKD'],
    'aset_dokumen'  => ['kode' => 'aset',  'nama' => 'Sarana dan Prasarana'],
    'rapat_dokumen' => ['kode' => 'rapat', 'nama' => 'Administrasi Surat'],
    'hibah_dokumen' => ['kode' => 'hibah', 'nama' => 'Perencanaan dan Hibah'],
];

const DOKUMEN_KATEGORI = [
    'kebijakan'  => 'Kebijakan & Pedoman',
    'sop'        => 'Standar Operasional Prosedur (SOP)',
    'panduan'    => 'Panduan & Petunjuk Teknis',
    'sk'         => 'Surat Keputusan',
    'peraturan'  => 'Peraturan & Regulasi',
    'laporan'    => 'Laporan',
    'formulir'   => 'Formulir & Template',
    'akreditasi' => 'Bukti & Instrumen Akreditasi',
    'surat'      => 'Surat & Korespondensi',
    'lainnya'    => 'Lainnya',
];

const DOKUMEN_JENIS = [
    'pdf' => 'PDF', 'doc' => 'Word', 'docx' => 'Word', 'xls' => 'Excel', 'xlsx' => 'Excel',
    'ppt' => 'PowerPoint', 'pptx' => 'PowerPoint', 'jpg' => 'Gambar', 'jpeg' => 'Gambar', 'png' => 'Gambar',
];

function dokumen_modul_dari_kode(string $kode): ?string
{
    foreach (DOKUMEN_MODUL as $halaman => $m) {
        if ($m['kode'] === $kode) {
            return $halaman;
        }
    }
    return null;
}

function dokumen_boleh(string $kode): bool
{
    switch ($kode) {
        case 'keu':   return boleh_keu();
        case 'jad':   return boleh_jad();
        case 'aset':  return boleh_aset();
        case 'rapat': return can('menu_rapat');
        case 'hibah': return can('menu_hibah');
    }
    return false;
}

function dokumen_dir(string $kode): string
{
    return APP_ROOT . '/uploads/dokumen/' . preg_replace('/[^a-z]/', '', $kode);
}

function dokumen_semua(string $kode, bool $muatUlang = false): array
{
    static $cache = [];
    if (!isset($cache[$kode]) || $muatUlang) {
        $berkas = dokumen_dir($kode) . '/indeks.json';
        $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
        $cache[$kode] = is_array($isi) ? array_values(array_filter($isi, 'is_array')) : [];
    }
    return $cache[$kode];
}

function dokumen_simpan_indeks(string $kode, array $daftar): bool
{
    $dir = dokumen_dir($kode);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $berkas = $dir . '/indeks.json';
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode(array_values($daftar), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    dokumen_semua($kode, true);
    return true;
}

function dokumen_cari(string $kode, string $id): ?array
{
    foreach (dokumen_semua($kode) as $d) {
        if (($d['id'] ?? '') === $id) {
            return $d;
        }
    }
    return null;
}

function dokumen_nama_sah(?string $nama): bool
{
    return $nama !== null && (bool) preg_match('/^d[a-f0-9]{16}\.(pdf|docx?|xlsx?|pptx?|jpe?g|png)$/', $nama);
}

function dokumen_cek_isi(string $jalur, string $ext): bool
{
    $awal = (string) @file_get_contents($jalur, false, null, 0, 8);
    if ($ext === 'pdf') {
        return strncmp($awal, '%PDF', 4) === 0;
    }
    if (in_array($ext, ['docx', 'xlsx', 'pptx'], true)) {
        return strncmp($awal, "PK\x03\x04", 4) === 0;
    }
    if (in_array($ext, ['doc', 'xls', 'ppt'], true)) {
        return strncmp($awal, "\xD0\xCF\x11\xE0", 4) === 0;
    }
    $info = @getimagesize($jalur);
    return $info && in_array($info[2], [IMAGETYPE_JPEG, IMAGETYPE_PNG], true);
}

function dokumen_unggah(string $kode, array $berkas): array
{
    $err = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($err === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    if ($err !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'] ?? '')) {
        return ['galat' => $err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE ? 'Berkas terlalu besar.' : 'Berkas gagal diunggah.'];
    }
    if ((int) $berkas['size'] > FOTO_MAKS_BYTE) {
        return ['galat' => 'Ukuran berkas maksimal ' . round(FOTO_MAKS_BYTE / 1048576) . ' MB.'];
    }
    $ext = strtolower(pathinfo((string) $berkas['name'], PATHINFO_EXTENSION));
    if (!isset(DOKUMEN_JENIS[$ext]) || !dokumen_cek_isi($berkas['tmp_name'], $ext)) {
        return ['galat' => 'Berkas harus berupa PDF, Word, Excel, PowerPoint, JPG, atau PNG yang sah.'];
    }
    $dir = dokumen_dir($kode);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['galat' => 'Folder dokumen tidak dapat dibuat.'];
    }
    $nama = 'd' . bin2hex(random_bytes(8)) . '.' . $ext;
    if (!move_uploaded_file($berkas['tmp_name'], $dir . '/' . $nama)) {
        return ['galat' => 'Berkas tidak dapat disimpan di server.'];
    }
    return ['file' => $nama, 'ext' => $ext, 'ukuran' => (int) $berkas['size'], 'asli' => mb_substr((string) $berkas['name'], 0, 200)];
}

function dokumen_hapus_berkas(string $kode, ?string $nama): void
{
    if (dokumen_nama_sah($nama)) {
        @unlink(dokumen_dir($kode) . '/' . $nama);
    }
}

function dokumen_cocok(array $d, string $kata): bool
{
    if ($kata === '') {
        return true;
    }
    $teks = mb_strtolower(implode(' ', [
        $d['judul'] ?? '', $d['nomor'] ?? '', $d['penerbit'] ?? '', $d['kata_kunci'] ?? '', $d['keterangan'] ?? '',
        DOKUMEN_KATEGORI[$d['kategori'] ?? ''] ?? '', (string) ($d['tahun'] ?? ''), $d['berkas_asli'] ?? '',
    ]));
    foreach (preg_split('/\s+/u', mb_strtolower(trim($kata))) as $bagian) {
        if ($bagian !== '' && mb_strpos($teks, $bagian) === false) {
            return false;
        }
    }
    return true;
}

function dokumen_kirim(string $kode, string $nama): bool
{
    if (!dokumen_nama_sah($nama) || !dokumen_modul_dari_kode($kode) || !dokumen_boleh($kode)) {
        return false;
    }
    $milik = false;
    foreach (dokumen_semua($kode) as $d) {
        if (($d['berkas'] ?? '') === $nama) {
            $milik = $d;
            break;
        }
    }
    $jalur = dokumen_dir($kode) . '/' . $nama;
    if (!$milik || !is_file($jalur)) {
        return false;
    }
    $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
    $mime = [
        'pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png',
        'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel', 'ppt' => 'application/vnd.ms-powerpoint',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    ][$ext] ?? 'application/octet-stream';
    $unduh = ($_GET['unduh'] ?? '') === '1' || !in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
    $namaUnduh = trim((string) preg_replace('/[^A-Za-z0-9 ._-]+/', '', (string) ($milik['judul'] ?? 'dokumen'))) ?: 'dokumen';
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($jalur));
    header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . mb_substr($namaUnduh, 0, 120) . '.' . $ext . '"');
    header('Cache-Control: private, max-age=600');
    header('X-Content-Type-Options: nosniff');
    readfile($jalur);
    exit;
}
