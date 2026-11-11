<?php

function e($v): string
{
    return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

function angka($n, int $desimal = 0): string
{
    return number_format((float) $n, $desimal, ',', '.');
}

function angka_ringkas($n, int $desimalMaks = 2): string
{
    $v = round((float) $n, $desimalMaks);
    if (abs($v - round($v)) < 0.0000001) {
        return number_format($v, 0, ',', '.');
    }
    return rtrim(rtrim(number_format($v, $desimalMaks, ',', '.'), '0'), ',');
}

const NAMA_BULAN = ['', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                    'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'];

const NAMA_HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];

function tanggal_id(?string $tgl, bool $hari = false): string
{
    if (!$tgl) {
        return '-';
    }
    $ts = strtotime($tgl);
    if (!$ts) {
        return '-';
    }
    $teks = date('j', $ts) . ' ' . NAMA_BULAN[(int) date('n', $ts)] . ' ' . date('Y', $ts);
    return $hari ? NAMA_HARI[(int) date('w', $ts)] . ', ' . $teks : $teks;
}

function tanggal_pendek(?string $tgl): string
{
    if (!$tgl) {
        return '-';
    }
    $ts = strtotime($tgl);
    return $ts ? date('j', $ts) . ' ' . mb_substr(NAMA_BULAN[(int) date('n', $ts)], 0, 3) . ' ' . date('Y', $ts) : '-';
}

function waktu_relatif(?string $waktu): string
{
    if (!$waktu) {
        return '-';
    }
    $selisih = time() - strtotime($waktu);
    if ($selisih < 60) return 'baru saja';
    if ($selisih < 3600) return floor($selisih / 60) . ' menit lalu';
    if ($selisih < 86400) return floor($selisih / 3600) . ' jam lalu';
    if ($selisih < 604800) return floor($selisih / 86400) . ' hari lalu';
    return tanggal_pendek($waktu);
}

function jam_pendek(?string $jam): string
{
    return $jam ? substr($jam, 0, 5) : '';
}

function durasi_jam(?string $mulai, ?string $selesai): float
{
    if (!$mulai || !$selesai) {
        return 0.0;
    }
    $d = strtotime('1970-01-01 ' . $selesai . ' UTC') - strtotime('1970-01-01 ' . $mulai . ' UTC');
    return $d > 0 ? $d / 3600 : 0.0;
}

function redirect(string $url): void
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $url = str_replace(["\r", "\n", "\0"], '', trim($url));
    if (preg_match('~^(//|/\\\\|\\\\)~', $url) || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) {
        $url = base_url('index.php');
    }
    header('Location: ' . $url);
    exit;
}

function url(string $halaman, array $param = []): string
{
    $q = array_merge(['h' => $halaman], array_filter($param, static function ($v) {
        return $v !== null && $v !== '';
    }));
    return base_url('index.php?' . http_build_query($q));
}

function kembali_ke(string $halaman, array $param = []): void
{
    redirect(url($halaman, $param));
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(csrf_token()) . '">';
}

function csrf_check(): void
{
    $kirim = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $sah = (string) ($_SESSION['csrf'] ?? '');
    $panjang = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if (!$_POST && !$_FILES && $panjang > 0 && $panjang > ukuran_ini((string) ini_get('post_max_size'))) {
        flash('Ukuran berkas yang dikirim terlalu besar untuk server (maksimal ' . round(ukuran_ini((string) ini_get('post_max_size')) / 1048576) . ' MB sekaligus). Pilih foto yang lebih kecil lalu coba lagi.', 'galat');
        redirect((string) ($_SERVER['REQUEST_URI'] ?? base_url('index.php')));
    }
    if ($sah === '' || !is_string($kirim) || !hash_equals($sah, $kirim)) {
        http_response_code(403);
        exit('<p style="font-family:Segoe UI,sans-serif;padding:40px;line-height:1.6">'
           . 'Sesi Anda sudah kedaluwarsa atau halaman dibuka terlalu lama. '
           . '<a href="' . e(base_url('index.php')) . '">Kembali ke beranda</a>, lalu ulangi.</p>');
    }
}

function ukuran_ini(string $nilai): int
{
    $nilai = trim($nilai);
    if ($nilai === '' || $nilai === '0' || $nilai === '-1') {
        return PHP_INT_MAX;
    }
    $angka = (int) $nilai;
    switch (strtolower(substr($nilai, -1))) {
        case 'g': return $angka * 1073741824;
        case 'm': return $angka * 1048576;
        case 'k': return $angka * 1024;
    }
    return $angka;
}

function sandi_acak(int $panjang = 12): string
{
    $huruf = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $angka = '23456789';
    $semua = $huruf . $angka;
    $hasil = [$huruf[random_int(0, strlen($huruf) - 1)], $angka[random_int(0, strlen($angka) - 1)]];
    while (count($hasil) < $panjang) {
        $hasil[] = $semua[random_int(0, strlen($semua) - 1)];
    }
    for ($i = count($hasil) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$hasil[$i], $hasil[$j]] = [$hasil[$j], $hasil[$i]];
    }
    return implode('', $hasil);
}

function csv_aman($nilai)
{
    if (is_string($nilai) && $nilai !== '' && preg_match('/^[=+\-@\t\r]/', $nilai)) {
        return "'" . $nilai;
    }
    return $nilai;
}

function flash(string $pesan, string $tipe = 'ok'): void
{
    $_SESSION['flash'][] = ['pesan' => $pesan, 'tipe' => $tipe];
}

function ambil_flash(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

function post_teks(string $kunci): string
{
    $v = $_POST[$kunci] ?? '';
    return is_string($v) ? $v : '';
}

function post(string $kunci, $bawaan = ''): string
{
    $v = $_POST[$kunci] ?? $bawaan;
    return is_string($v) ? trim($v) : (string) $bawaan;
}

function post_angka(string $kunci, float $bawaan = 0): float
{
    $v = str_replace(',', '.', post($kunci));
    return is_numeric($v) ? (float) $v : $bawaan;
}

function tanggal_sah(string $tgl): bool
{
    $d = DateTime::createFromFormat('Y-m-d', $tgl);
    return $d && $d->format('Y-m-d') === $tgl;
}

function normalisasi_jam(string $jam): string
{
    $j = str_replace(['.', ',', ' '], ':', trim($jam));
    if ($j === '') {
        return '';
    }
    if (preg_match('/^(\d{1,2}):(\d{1,2})$/', $j, $m)) {
        return sprintf('%02d:%02d', (int) $m[1], (int) $m[2]);
    }
    if (preg_match('/^\d{3,4}$/', $j)) {
        $j = str_pad($j, 4, '0', STR_PAD_LEFT);
        return substr($j, 0, 2) . ':' . substr($j, 2, 2);
    }
    if (preg_match('/^\d{1,2}$/', $j)) {
        return sprintf('%02d:00', (int) $j);
    }
    return $jam;
}

function jam_isian($jam): string
{
    return str_replace(':', '.', jam_pendek($jam === null ? null : (string) $jam));
}

function jam_sah(string $jam): bool
{
    return (bool) preg_match('/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/', $jam);
}

function tautan_sah(string $url): bool
{
    return $url === '' || (filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url));
}

function lencana(string $label, string $warna = 'abu'): string
{
    return '<span class="lc lc-' . e($warna) . '">' . e($label) . '</span>';
}

function lencana_dari(array $peta, string $kunci): string
{
    $d = $peta[$kunci] ?? ['label' => $kunci, 'warna' => 'abu'];
    return lencana($d['label'], $d['warna']);
}

function lencana_jenis(string $jenis): string
{
    $d = JENIS_KERJA[$jenis] ?? ['pendek' => $jenis, 'warna' => 'abu'];
    return '<span class="lc lc-' . e($d['warna']) . ' lc-titik">' . e($d['pendek']) . '</span>';
}

function inisial(string $nama): string
{
    $kata = preg_split('/\s+/', trim(preg_replace('/[^\p{L}\s]/u', ' ', $nama)));
    $kata = array_values(array_filter($kata, static function ($k) {
        return $k !== '' && !in_array(mb_strtolower($k), ['ka', 'kabid', 'dan', 'universitas'], true);
    }));
    if (!$kata) {
        return mb_strtoupper(mb_substr($nama, 0, 2));
    }
    $h = mb_substr($kata[0], 0, 1) . (isset($kata[1]) ? mb_substr($kata[1], 0, 1) : mb_substr($kata[0], 1, 1));
    return mb_strtoupper($h);
}

const FOTO_MAKS_BYTE = 20971520;

function foto_pengguna_peta(): array
{
    static $peta = null;
    if ($peta === null) {
        $peta = [];
        try {
            foreach (rows("SELECT nama, foto FROM pengguna WHERE foto IS NOT NULL AND foto <> ''") as $r) {
                $peta[$r['nama']] = $r['foto'];
            }
        } catch (Throwable $e) {
        }
    }
    return $peta;
}

function foto_nama_sah(?string $nama): bool
{
    return $nama !== null && (bool) preg_match('/^\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nama);
}

function hapus_berkas_foto(?string $nama): void
{
    if (foto_nama_sah($nama)) {
        @unlink(APP_ROOT . '/uploads/foto/' . $nama);
    }
}

function validasi_foto(string $jalur, int $ukuran, int $maks = FOTO_MAKS_BYTE): array
{
    if ($ukuran <= 0 || $ukuran > $maks) {
        return ['galat' => 'Ukuran foto maksimal ' . round($maks / 1048576) . ' MB.'];
    }
    $info = @getimagesize($jalur);
    $jenis = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];
    if (!$info || !isset($jenis[$info[2]])) {
        return ['galat' => 'Format foto harus JPG, PNG, atau WEBP.'];
    }
    if ($info[0] < 64 || $info[1] < 64 || $info[0] > 8000 || $info[1] > 8000) {
        return ['galat' => 'Dimensi foto harus antara 64 dan 8000 piksel.'];
    }
    return ['ekstensi' => $jenis[$info[2]]];
}

function proses_foto_unggah(array $berkas, int $penggunaId): array
{
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return ['galat' => 'Pilih berkas foto terlebih dahulu.'];
    }
    if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'] ?? '')) {
        return ['galat' => $kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran foto terlalu besar.' : 'Foto gagal diunggah. Silakan coba lagi.'];
    }
    $cek = validasi_foto($berkas['tmp_name'], (int) $berkas['size']);
    if (isset($cek['galat'])) {
        return $cek;
    }
    $dir = APP_ROOT . '/uploads/foto';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['galat' => 'Folder penyimpanan foto tidak dapat dibuat.'];
    }
    $nama = $penggunaId . '-' . bin2hex(random_bytes(8)) . '.' . $cek['ekstensi'];
    if (!move_uploaded_file($berkas['tmp_name'], $dir . '/' . $nama)) {
        return ['galat' => 'Foto tidak dapat disimpan di server.'];
    }
    return ['file' => $nama];
}

function foto_kegiatan_sah(?string $nama): bool
{
    return $nama !== null && (bool) preg_match('/^k\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nama);
}

function peta_foto_kegiatan(bool $muatUlang = false): array
{
    static $peta = null;
    if ($peta === null || $muatUlang) {
        $peta = [];
        foreach (@scandir(APP_ROOT . '/uploads/kegiatan') ?: [] as $nama) {
            if (foto_kegiatan_sah($nama)) {
                $peta[(int) substr($nama, 1, strpos($nama, '-') - 1)] = $nama;
            }
        }
    }
    return $peta;
}

function foto_kegiatan(int $kegiatanId): ?string
{
    return peta_foto_kegiatan()[$kegiatanId] ?? null;
}

function hapus_foto_kegiatan(int $kegiatanId): void
{
    foreach (@glob(APP_ROOT . '/uploads/kegiatan/k' . $kegiatanId . '-*') ?: [] as $jalur) {
        if (foto_kegiatan_sah(basename($jalur))) {
            @unlink($jalur);
        }
    }
    peta_foto_kegiatan(true);
}

const FOTO_KEGIATAN_MAKS = 3;
const FOTO_KEGIATAN_MAKS_BYTE = 52428800;

function foto_kegiatan_semua(int $kegiatanId): array
{
    $hasil = [];
    foreach (@glob(APP_ROOT . '/uploads/kegiatan/k' . $kegiatanId . '-*') ?: [] as $jalur) {
        if (foto_kegiatan_sah(basename($jalur))) {
            $hasil[] = basename($jalur);
        }
    }
    sort($hasil);
    return $hasil;
}

function pasang_foto_kegiatan(array $sementara, int $kegiatanId, array $hapus = []): void
{
    $dir = APP_ROOT . '/uploads/kegiatan';
    $ada = foto_kegiatan_semua($kegiatanId);
    foreach ($hapus as $nama) {
        if (in_array($nama, $ada, true)) {
            @unlink($dir . '/' . $nama);
        }
    }
    $sisa = foto_kegiatan_semua($kegiatanId);
    foreach ($sementara as $s) {
        if (!is_string($s) || !preg_match('/^s\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $s, $m) || !is_file($dir . '/' . $s)) {
            continue;
        }
        if (count($sisa) >= FOTO_KEGIATAN_MAKS) {
            @unlink($dir . '/' . $s);
            continue;
        }
        $baru = 'k' . $kegiatanId . '-' . bin2hex(random_bytes(8)) . '.' . $m[1];
        if (@rename($dir . '/' . $s, $dir . '/' . $baru)) {
            $sisa[] = $baru;
        }
    }
    peta_foto_kegiatan(true);
}

function buang_foto_sementara($sementara): void
{
    foreach ((array) $sementara as $s) {
        if (is_string($s) && preg_match('/^s\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $s)) {
            @unlink(APP_ROOT . '/uploads/kegiatan/' . $s);
        }
    }
}

function unggah_foto_kegiatan(array $berkas, int $penggunaId): array
{
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return [];
    }
    if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'] ?? '')) {
        return ['galat' => $kode === UPLOAD_ERR_INI_SIZE || $kode === UPLOAD_ERR_FORM_SIZE
            ? 'Ukuran foto bukti terlalu besar.' : 'Foto bukti gagal diunggah. Silakan coba lagi.'];
    }
    $cek = validasi_foto($berkas['tmp_name'], (int) $berkas['size'], FOTO_KEGIATAN_MAKS_BYTE);
    if (isset($cek['galat'])) {
        return ['galat' => 'Foto bukti: ' . $cek['galat']];
    }
    $dir = APP_ROOT . '/uploads/kegiatan';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return ['galat' => 'Folder penyimpanan foto bukti tidak dapat dibuat.'];
    }
    $nama = 's' . $penggunaId . '-' . bin2hex(random_bytes(8)) . '.' . $cek['ekstensi'];
    if (!move_uploaded_file($berkas['tmp_name'], $dir . '/' . $nama)) {
        return ['galat' => 'Foto bukti tidak dapat disimpan di server.'];
    }
    return ['file' => $nama];
}

function unggah_foto_kegiatan_banyak(array $berkas, int $penggunaId, int $kuota): array
{
    $files = [];
    if (!isset($berkas['name']) || !is_array($berkas['name'])) {
        return ['files' => $files];
    }
    foreach (array_keys($berkas['name']) as $i) {
        if (($berkas['error'][$i] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if (count($files) >= $kuota) {
            break;
        }
        $satu = ['name' => $berkas['name'][$i] ?? '', 'tmp_name' => $berkas['tmp_name'][$i] ?? '', 'error' => $berkas['error'][$i] ?? UPLOAD_ERR_NO_FILE, 'size' => $berkas['size'][$i] ?? 0];
        $h = unggah_foto_kegiatan($satu, $penggunaId);
        if (isset($h['galat'])) {
            buang_foto_sementara($files);
            return ['galat' => $h['galat']];
        }
        if (isset($h['file'])) {
            $files[] = $h['file'];
        }
    }
    return ['files' => $files];
}

function tautan_foto_kegiatan(int $kegiatanId): string
{
    $semua = foto_kegiatan_semua($kegiatanId);
    $h = '';
    foreach ($semua as $i => $nama) {
        $label = count($semua) > 1 ? 'Foto ' . ($i + 1) : 'Foto bukti';
        $h .= '<span class="sub"><a href="' . e(url_berkas('kegiatan', $nama)) . '" target="_blank" rel="noopener">' . ikon('kamera', 13) . ' ' . $label . '</a></span>';
    }
    return $h;
}
function medan_foto_kegiatan(?int $kegiatanId): string
{
    $lama = $kegiatanId ? foto_kegiatan_semua($kegiatanId) : [];
    $h = '<div class="form-baris foto-bukti" data-foto-bukti data-maks="' . FOTO_KEGIATAN_MAKS . '"><label class="fl">Foto bukti kegiatan <span class="foto-hitung" data-foto-hitung></span></label>'
       . '<div class="foto-bukti-tombol">'
       . '<label class="btn btn-garis btn-kecil" data-foto-kamera><input type="file" data-foto-kamera-hp accept="image/*" capture="environment" hidden>' . ikon('kamera', 15) . ' Ambil foto</label>'
       . '<label class="btn btn-garis btn-kecil"><input type="file" data-foto-galeri accept="image/jpeg,image/png,image/webp" multiple hidden>' . ikon('gambar', 15) . ' Pilih dari galeri</label>'
       . '<input type="file" name="foto_unggah[]" data-foto-keluar multiple hidden>'
       . '</div>'
       . '<div class="foto-bukti-daftar" data-foto-daftar>';
    foreach ($lama as $nama) {
        $url = url_berkas('kegiatan', $nama);
        $h .= '<div class="foto-mini lama"><a href="' . e($url) . '" target="_blank" rel="noopener"><img src="' . e($url) . '" alt="Foto bukti tersimpan"></a>'
            . '<label class="cek"><input type="checkbox" name="hapus_foto[]" value="' . e($nama) . '"> Hapus</label></div>';
    }
    return $h . '</div><small class="lemah foto-galat" data-foto-galat hidden></small>'
        . '<small class="lemah">Maksimal ' . FOTO_KEGIATAN_MAKS . ' foto dari kamera atau unggahan, tiap foto hingga 50 MB (JPG, PNG, atau WEBP). Foto otomatis diperkecil sebelum dikirim.</small></div>';
}

function url_berkas(string $jenis, string $nama): string
{
    return base_url('index.php?' . http_build_query(['h' => 'berkas', 'j' => $jenis, 'n' => $nama]));
}

function kirim_berkas_unggahan(string $jenis, string $nama): void
{
    $tolak = static function (): void {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        http_response_code(404);
        header('Content-Type: text/plain; charset=utf-8');
        exit('Berkas tidak ditemukan.');
    };
    if (strpos($jenis, 'dok_') === 0 && function_exists('dokumen_kirim')) {
        dokumen_kirim(substr($jenis, 4), $nama);
        $tolak();
    }
    $boleh = false;
    if ($jenis === 'foto') {
        $boleh = foto_nama_sah($nama);
    } elseif ($jenis === 'aset') {
        $boleh = aset_berkas_sah($nama) && boleh_aset();
    } elseif ($jenis === 'rapat') {
        if (preg_match('/^\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nama)) {
            $rapatId = (int) val('SELECT rapat_id FROM rapat_dokumentasi WHERE berkas = ?', [$nama], 0);
            $rapat = $rapatId ? rapat_get($rapatId) : null;
            $boleh = $rapat !== null && rapat_boleh_lihat($rapat);
        }
    } elseif ($jenis === 'kegiatan') {
        if (foto_kegiatan_sah($nama)) {
            $pemilik = (int) val('SELECT pengguna_id FROM kegiatan WHERE id = ?', [(int) substr($nama, 1, strpos($nama, '-') - 1)], 0);
            $boleh = $pemilik > 0 && boleh_lihat_pegawai($pemilik);
        }
    } elseif ($jenis === 'audit') {
        $boleh = keu_dok_sah($nama) && boleh_keu() && (int) val('SELECT COUNT(*) FROM ' . ($nama[0] === 't' ? 'keu_temuan' : 'keu_audit') . ' WHERE id = ?', [keu_dok_audit_id($nama)], 0) > 0;
    }
    $jalur = APP_ROOT . '/uploads/' . $jenis . '/' . $nama;
    if (!$boleh || !is_file($jalur)) {
        $tolak();
    }
    $ext = strtolower(pathinfo($nama, PATHINFO_EXTENSION));
    $mime = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp'][$ext] ?? null;
    $unduh = false;
    if ($jenis === 'audit') {
        $mime = ['pdf' => 'application/pdf', 'doc' => 'application/msword', 'xls' => 'application/vnd.ms-excel',
                 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'][$ext] ?? $mime;
        $unduh = ($_GET['unduh'] ?? '') === '1' || !in_array($ext, ['pdf', 'jpg', 'jpeg', 'png'], true);
    }
    if ($mime === null) {
        $tolak();
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    @ini_set('zlib.output_compression', '0');
    @ini_set('output_buffering', '0');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
    header_remove('Content-Encoding');
    header_remove('Content-Length');
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . filesize($jalur));
    header('Content-Disposition: ' . ($unduh ? 'attachment' : 'inline') . '; filename="' . $nama . '"');
    header('Cache-Control: private, max-age=3600');
    header('X-Content-Type-Options: nosniff');
    header('Content-Encoding: identity');
    $h = @fopen($jalur, 'rb');
    if ($h) {
        while (!feof($h)) {
            echo fread($h, 65536);
            flush();
        }
        fclose($h);
    } else {
        readfile($jalur);
    }
    exit;
}

function avatar(string $nama, string $kelas = ''): string
{
    $foto = foto_pengguna_peta()[$nama] ?? null;
    if (foto_nama_sah($foto)) {
        return '<img class="avatar foto ' . e($kelas) . '" src="' . e(url_berkas('foto', $foto)) . '" alt="' . e($nama) . '">';
    }
    $warna = ['#033e3e', '#8a6a20', '#0f4a4a', '#8a4a32', '#a8822f', '#246464', '#5c4a2a'];
    $i = abs(crc32($nama)) % count($warna);
    return '<span class="avatar ' . e($kelas) . '" style="background:' . $warna[$i] . '">' . e(inisial($nama)) . '</span>';
}

function meter(float $persen, string $warna = ''): string
{
    $p = max(0, min(100, $persen));
    if ($warna === '') {
        $warna = $persen >= 90 ? 'hijau' : ($persen >= 70 ? 'biru' : ($persen >= 50 ? 'emas' : 'merah'));
    }
    return '<div class="meter meter-' . e($warna) . '"><span style="width:' . round($p, 1) . '%"></span></div>';
}

function predikat_nilai(?float $nilai): array
{
    if ($nilai === null) {
        return ['label' => 'Belum dinilai', 'warna' => 'abu'];
    }
    if ($nilai >= 90) return ['label' => 'Sangat Baik', 'warna' => 'hijau'];
    if ($nilai >= 76) return ['label' => 'Baik', 'warna' => 'biru'];
    if ($nilai >= 61) return ['label' => 'Cukup', 'warna' => 'emas'];
    if ($nilai >= 51) return ['label' => 'Kurang', 'warna' => 'jingga'];
    return ['label' => 'Sangat Kurang', 'warna' => 'merah'];
}

function rekomendasi_predikat(string $predikat): array
{
    switch ($predikat) {
        case 'Sangat Baik':
            return ['penghargaan', 'Layak diusulkan menerima penghargaan atau dipertimbangkan untuk promosi jabatan.'];
        case 'Baik':
            return ['pelatihan', 'Pertahankan kinerja; berikan pelatihan peningkatan kompetensi untuk naik ke predikat Sangat Baik.'];
        case 'Cukup':
            return ['pelatihan', 'Perlu pelatihan teknis dan pendampingan atasan untuk meningkatkan capaian pencatatan kerja.'];
        case 'Kurang':
        case 'Sangat Kurang':
            return ['pembinaan', 'Perlu pembinaan intensif dan rencana perbaikan kinerja dengan target terukur.'];
    }
    return ['pelatihan', ''];
}

function sel_kosong(string $judul, string $ket = '', string $aksi = ''): string
{
    return '<div class="kosong">' . ikon('kosong', 40)
         . '<b>' . e($judul) . '</b>'
         . ($ket !== '' ? '<p>' . e($ket) . '</p>' : '')
         . $aksi . '</div>';
}

function pilih_kehadiran(string $terpilih): string
{
    if (!isset(KEHADIRAN_KERJA[$terpilih])) {
        $terpilih = 'masuk';
    }
    $h = '<div class="pilih-hadir">';
    foreach (KEHADIRAN_KERJA as $k => $j) {
        $h .= '<label class="hadir-' . $k . '"><input type="radio" name="kehadiran" value="' . $k . '"' . ($k === $terpilih ? ' checked' : '') . '><span>' . e($j['label']) . '</span></label>';
    }
    return $h . '</div>';
}

function lencana_kehadiran(?string $kehadiran): string
{
    if (!$kehadiran || $kehadiran === 'masuk' || !isset(KEHADIRAN_KERJA[$kehadiran])) {
        return '';
    }
    return lencana_dari(KEHADIRAN_KERJA, $kehadiran);
}

function pilihan_satuan(string $terpilih): string
{
    $semua = [];
    foreach (SATUAN_UMUM as $daftar) {
        $semua = array_merge($semua, $daftar);
    }
    $h = '';
    if ($terpilih !== '' && !in_array($terpilih, $semua, true)) {
        $h .= '<option value="' . e($terpilih) . '" selected>' . e($terpilih) . '</option>';
    }
    foreach (SATUAN_UMUM as $grup => $daftar) {
        $h .= '<optgroup label="' . e($grup) . '">';
        foreach ($daftar as $s) {
            $h .= '<option value="' . e($s) . '"' . ($s === $terpilih ? ' selected' : '') . '>' . e($s) . '</option>';
        }
        $h .= '</optgroup>';
    }
    return $h . '<option value="__lain">Lainnya (ketik sendiri)…</option>';
}

function satuan_dari_formulir(string $bawaan = 'kegiatan'): string
{
    $s = post('satuan');
    if ($s === '__lain') {
        $s = post('satuan_lain');
    }
    return mb_substr($s !== '' ? $s : $bawaan, 0, 40);
}

function pilihan(array $opsi, $terpilih, bool $kunciSebagaiNilai = true): string
{
    $h = '';
    foreach ($opsi as $k => $v) {
        $nilai = $kunciSebagaiNilai ? $k : $v;
        $h .= '<option value="' . e($nilai) . '"' . ((string) $nilai === (string) $terpilih ? ' selected' : '') . '>'
            . e($v) . '</option>';
    }
    return $h;
}
