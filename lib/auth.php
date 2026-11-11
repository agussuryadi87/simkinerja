<?php

function ip_pengunjung(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    if (in_array($ip, ['127.0.0.1', '::1'], true) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = trim((string) $_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '';
}

function pakai_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
}

function sidik_klien(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? '') . '|' . APP_ROOT);
}

function akhiri_sesi_aktif(): void
{
    $_SESSION = [];
    session_regenerate_id(true);
}

const LAMA_INGAT = 604800;
const BATAS_DIAM_DETIK = 3600;
const BATAS_SESI_MUTLAK = 43200;
const ROTASI_SESI_DETIK = 900;

function mulai_sesi(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }
    $ingat = !empty($_COOKIE['SIMKIN_INGAT']);
    ini_set('session.gc_maxlifetime', (string) LAMA_INGAT);
    session_set_cookie_params([
        'lifetime' => $ingat ? LAMA_INGAT : 0,
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => pakai_https(),
    ]);
    session_name('SIMKINBAUK');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
    ini_set('session.sid_length', '48');
    ini_set('session.sid_bits_per_character', '6');
    session_start();

    if (isset($_SESSION['uid'])) {
        $sekarang = time();
        if (!isset($_SESSION['sidik'])) {
            $_SESSION['sidik'] = sidik_klien();
        }
        if (!hash_equals((string) $_SESSION['sidik'], sidik_klien())) {
            akhiri_sesi_aktif();
        }
    }
    if (isset($_SESSION['uid'])) {
        $sekarang = time();
        $terakhir = (int) ($_SESSION['aktif_terakhir'] ?? 0);
        if (!$ingat && $terakhir > 0 && $sekarang - $terakhir > BATAS_DIAM_DETIK) {
            akhiri_sesi_aktif();
        } else {
            $mulai = (int) ($_SESSION['mulai'] ?? 0);
            if ($mulai === 0) {
                $_SESSION['mulai'] = $mulai = $sekarang;
            }
            if ($sekarang - $mulai > ($ingat ? LAMA_INGAT : BATAS_SESI_MUTLAK)) {
                akhiri_sesi_aktif();
            } elseif ($sekarang - (int) ($_SESSION['rotasi'] ?? 0) > ROTASI_SESI_DETIK) {
                session_regenerate_id(true);
                $_SESSION['rotasi'] = $sekarang;
                if ($ingat) {
                    ingat_saya(true);
                }
            }
        }
    }
    $_SESSION['aktif_terakhir'] = time();
}

function ingat_saya(bool $aktif): void
{
    $opsi = [
        'expires'  => $aktif ? time() + LAMA_INGAT : time() - 3600,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => pakai_https(),
    ];
    setcookie('SIMKIN_INGAT', $aktif ? '1' : '', $opsi);
    if ($aktif) {
        setcookie(session_name(), session_id(), $opsi);
    }
}

function user(bool $segarkan = false): ?array
{
    static $u = false;
    if ($segarkan) {
        $u = false;
    }
    if ($u !== false) {
        return $u;
    }
    $id = (int) ($_SESSION['uid'] ?? 0);
    if ($id <= 0) {
        return $u = null;
    }
    $u = row(
        'SELECT p.id, p.username, p.nama, p.nip, p.jabatan, p.email, p.unit_id, p.peran_id, p.atasan_id,
                p.sandi_bawaan, un.nama AS unit_nama, pr.nama AS peran_nama, pr.kode AS peran_kode,
                pr.lingkup, at.nama AS atasan_nama
           FROM pengguna p
           JOIN peran pr ON pr.id = p.peran_id
           LEFT JOIN unit un ON un.id = p.unit_id
           LEFT JOIN pengguna at ON at.id = p.atasan_id
          WHERE p.id = ? AND p.aktif = 1',
        [$id]
    );
    if (!$u) {
        unset($_SESSION['uid']);
    }
    return $u;
}

function uid(): int
{
    return (int) (user()['id'] ?? 0);
}

function sudah_masuk(): bool
{
    return user() !== null;
}

function izin_saya(): array
{
    static $izin = null;
    if ($izin === null) {
        $u = user();
        $izin = $u ? izin_pengguna((int) $u['id'], (int) $u['peran_id']) : [];
    }
    return $izin;
}

const IZIN_BAWAAN_BARU = [
    'menu_diatur'         => ['menu_umpan', 'menu_evaluasi', 'menu_pengembangan', 'menu_laporan', 'menu_rapat', 'menu_hibah'],
    'menu_diatur_absensi' => ['menu_absensi'],
];

function berkas_izin_pegawai(): string
{
    return APP_ROOT . '/uploads/akses/izin-pegawai.json';
}

function semua_penyesuaian_izin(bool $muatUlang = false): array
{
    static $data = null;
    if ($data === null || $muatUlang) {
        $berkas = berkas_izin_pegawai();
        $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
        $data = is_array($isi) ? $isi : [];
    }
    return $data;
}

function izin_penyesuaian(int $penggunaId): array
{
    $hasil = [];
    foreach ((array) (semua_penyesuaian_izin()[(string) $penggunaId] ?? []) as $kode => $boleh) {
        $hasil[(string) $kode] = (int) $boleh === 1;
    }
    return $hasil;
}

function simpan_penyesuaian_izin(int $penggunaId, array $peta): bool
{
    $semua = semua_penyesuaian_izin(true);
    if ($peta) {
        $semua[(string) $penggunaId] = array_map(static function ($b) { return $b ? 1 : 0; }, $peta);
    } else {
        unset($semua[(string) $penggunaId]);
    }
    $berkas = berkas_izin_pegawai();
    if (!is_dir(dirname($berkas)) && !@mkdir(dirname($berkas), 0775, true)) {
        return false;
    }
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode($semua, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    semua_penyesuaian_izin(true);
    return true;
}

function izin_peran(int $peranId): array
{
    static $cache = [];
    if (!isset($cache[$peranId])) {
        $izin = array_column(rows('SELECT izin FROM peran_izin WHERE peran_id = ?', [$peranId]), 'izin');
        foreach (IZIN_BAWAAN_BARU as $tanda => $kode) {
            if (!in_array($tanda, $izin, true)) {
                $izin = array_merge($izin, $kode);
            }
        }
        $cache[$peranId] = array_values(array_unique(array_diff($izin, array_keys(IZIN_BAWAAN_BARU))));
    }
    return $cache[$peranId];
}

function izin_pengguna(int $penggunaId, int $peranId, ?array $penyesuaian = null): array
{
    $izin = izin_peran($peranId);
    $kodePeranIzin = (string) val('SELECT kode FROM peran WHERE id = ?', [$peranId], '');
    if ($kodePeranIzin === 'cs') {
        $izin = array_values(array_diff($izin, ['modul_aset']));
        $izin[] = 'aset_kontrol';
    }
    $bendera = row('SELECT akses_aset, akses_jad, akses_keu FROM pengguna WHERE id = ?', [$penggunaId]) ?: [];
    foreach (['akses_aset' => 'modul_aset', 'akses_jad' => 'modul_jad', 'akses_keu' => 'modul_keu'] as $kolom => $kode) {
        if ((int) ($bendera[$kolom] ?? 0) === 1) {
            $izin[] = $kode;
        }
    }
    if ($kodePeranIzin === 'cs') {
        $izin[] = 'kegiatan_isi';
    }
    foreach ($penyesuaian ?? izin_penyesuaian($penggunaId) as $kode => $boleh) {
        if ($boleh) {
            $izin[] = $kode;
        } else {
            $izin = array_diff($izin, [$kode]);
        }
    }
    return array_values(array_unique($izin));
}

function can(string $aksi): bool
{
    return in_array($aksi, izin_saya(), true);
}

function can_salah_satu(array $daftar): bool
{
    foreach ($daftar as $a) {
        if (can($a)) {
            return true;
        }
    }
    return false;
}

function wajib_masuk(): void
{
    if (!sudah_masuk()) {
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET' && ($_GET['h'] ?? '') !== 'notifikasi') {
            $_SESSION['tujuan'] = $_SERVER['REQUEST_URI'] ?? base_url('index.php');
        }
        redirect(base_url('index.php?h=login'));
    }
}

function tolak_akses(): void
{
    http_response_code(403);
    exit('<div style="font-family:Segoe UI,sans-serif;max-width:560px;margin:80px auto;padding:28px;'
       . 'border:1px solid #e3e6ee;border-radius:12px;background:#fff">'
       . '<h2 style="margin:0 0 8px;color:#033e3e">Akses tidak diizinkan</h2>'
       . '<p style="color:#474f63;line-height:1.6;margin:0 0 16px">Peran Anda tidak memiliki hak akses untuk halaman ini.</p>'
       . '<a href="' . htmlspecialchars(base_url('index.php'), ENT_QUOTES) . '" style="color:#8a6a20;font-weight:600">'
       . 'Kembali ke beranda</a></div>');
}

function wajib_bisa(string $aksi): void
{
    wajib_masuk();
    if (!can($aksi)) {
        tolak_akses();
    }
}

function unit_turunan(int $unitId): array
{
    static $peta = null;
    if ($peta === null) {
        $peta = [];
        foreach (rows('SELECT id, induk_id FROM unit') as $r) {
            $peta[(int) ($r['induk_id'] ?? 0)][] = (int) $r['id'];
        }
    }
    $hasil = [$unitId];
    $antre = [$unitId];
    while ($antre) {
        $id = array_shift($antre);
        foreach ($peta[$id] ?? [] as $anak) {
            if (!in_array($anak, $hasil, true)) {
                $hasil[] = $anak;
                $antre[] = $anak;
            }
        }
    }
    return $hasil;
}

function tim_ids(): array
{
    static $ids = null;
    if ($ids !== null) {
        return $ids;
    }
    $u = user();
    if (!$u) {
        return $ids = [];
    }
    $saya = (int) $u['id'];
    switch ($u['lingkup']) {
        case 'semua':
            $daftar = rows('SELECT id FROM pengguna WHERE aktif = 1 AND id <> ?', [$saya]);
            break;
        case 'unit':
            $unit = $u['unit_id'] ? unit_turunan((int) $u['unit_id']) : [];
            $daftar = rows(
                'SELECT id FROM pengguna WHERE aktif = 1 AND id <> ?
                    AND (atasan_id = ? OR unit_id IN (' . in_placeholder($unit) . '))',
                array_merge([$saya, $saya], $unit)
            );
            break;
        default:
            $daftar = rows('SELECT id FROM pengguna WHERE aktif = 1 AND atasan_id = ?', [$saya]);
    }
    return $ids = array_map('intval', array_column($daftar, 'id'));
}

function di_tim(int $pegawaiId): bool
{
    return in_array($pegawaiId, tim_ids(), true);
}

function boleh_lihat_pegawai(int $pegawaiId): bool
{
    if ($pegawaiId === uid()) {
        return true;
    }
    return di_tim($pegawaiId)
        && can_salah_satu(['pantau', 'evaluasi_nilai', 'pengembangan_kelola', 'laporan_lihat']);
}

function normalisasi_username(string $username): string
{
    return mb_strtolower(preg_replace('/\s+/u', ' ', trim($username)));
}

function rapikan_username(string $username): string
{
    return preg_replace('/\s+/u', ' ', trim($username));
}

function sandi_bentrok(int $idPengguna, string $username, string $sandi): bool
{
    foreach (rows('SELECT password_hash FROM pengguna WHERE username = ? AND id <> ? AND aktif = 1', [$username, $idPengguna]) as $r) {
        if (password_verify($sandi, $r['password_hash'])) {
            return true;
        }
    }
    return false;
}

function peran_boleh_diberikan(int $peranId): bool
{
    if (can('peran_kelola')) {
        return true;
    }
    return !array_diff(izin_peran($peranId), izin_saya());
}

function boleh_kelola_akun(int $penggunaId): bool
{
    if (can('peran_kelola')) {
        return true;
    }
    $peranId = (int) val('SELECT peran_id FROM pengguna WHERE id = ?', [$penggunaId], 0);
    return $peranId > 0 && peran_boleh_diberikan($peranId) && !array_diff(izin_pengguna($penggunaId, $peranId), izin_saya());
}

function akun_terkunci(int $penggunaId): bool
{
    return (string) val('SELECT r.kode FROM pengguna p JOIN peran r ON r.id = p.peran_id WHERE p.id = ?', [$penggunaId], '') === 'admin';
}

function peran_punya_izin(int $peranId, string $izin): bool
{
    return in_array($izin, izin_peran($peranId), true);
}

function admin_lain_tersisa(int $kecualiId): bool
{
    foreach (rows('SELECT id, peran_id FROM pengguna WHERE aktif = 1 AND id <> ?', [$kecualiId]) as $p) {
        if (in_array('pengguna_kelola', izin_pengguna((int) $p['id'], (int) $p['peran_id']), true)) {
            return true;
        }
    }
    return false;
}

function jeda_percobaan(string $username = ''): ?int
{
    $menit = JEDA_MASUK_MENIT;
    $sisaDari = static function (string $kolom, string $nilai) use ($menit): int {
        $terakhir = (int) val(
            "SELECT UNIX_TIMESTAMP(MAX(waktu)) FROM log_aktivitas WHERE aksi = 'login_gagal' AND $kolom = ?",
            [$nilai], 0
        );
        return ($menit * 60) - (time() - $terakhir);
    };
    $sisa = 0;

    $gagalIp = (int) val(
        "SELECT COUNT(*) FROM log_aktivitas
          WHERE aksi = 'login_gagal' AND ip = ? AND waktu > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
        [ip_pengunjung(), $menit], 0
    );
    if ($gagalIp >= MAKS_GAGAL_MASUK_IP) {
        $sisa = max($sisa, $sisaDari('ip', ip_pengunjung()));
    }

    $username = mb_substr(normalisasi_username($username), 0, 60);
    if ($username !== '') {
        $gagalUser = (int) val(
            "SELECT COUNT(*) FROM log_aktivitas
              WHERE aksi = 'login_gagal' AND keterangan = ? AND waktu > DATE_SUB(NOW(), INTERVAL ? MINUTE)",
            [$username, $menit], 0
        );
        if ($gagalUser >= MAKS_GAGAL_MASUK) {
            $sisa = max($sisa, $sisaDari('keterangan', $username));
        }
    }
    return $sisa > 0 ? $sisa : null;
}

function login(string $username, string $password): string
{
    $username = normalisasi_username($username);
    if (jeda_percobaan($username) !== null) {
        return 'terhambat';
    }
    $kandidat = rows('SELECT id, password_hash FROM pengguna WHERE username = ? AND aktif = 1', [$username]);
    if (!$kandidat) {
        password_verify($password, '$2y$10$Qeek5ngOKqX7PuThEH56ROHXagX7VU2gy7ffjwKLJA.8T1HVNWvc.');
    }
    $cocok = [];
    foreach ($kandidat as $k) {
        if (password_verify($password, $k['password_hash'])) {
            $cocok[] = $k;
        }
    }
    if (!$cocok) {
        catat_log('login_gagal', 'pengguna', 0, mb_substr($username, 0, 60));
        return 'salah';
    }
    if (count($cocok) > 1) {
        catat_log('login_ambigu', 'pengguna', 0, mb_substr($username, 0, 60));
        return 'ambigu';
    }
    $u = $cocok[0];
    session_regenerate_id(true);
    unset($_SESSION['csrf']);
    $_SESSION['uid'] = (int) $u['id'];
    $_SESSION['sidik'] = sidik_klien();
    $_SESSION['mulai'] = $_SESSION['rotasi'] = time();
    user(true);
    q('UPDATE pengguna SET login_terakhir = NOW() WHERE id = ?', [$u['id']]);
    q("DELETE FROM log_aktivitas WHERE aksi = 'login_gagal' AND keterangan = ?", [$username]);
    catat_log('login', 'pengguna', (int) $u['id'], 'Masuk ke sistem');
    return 'ok';
}

function logout(): void
{
    if (sudah_masuk()) {
        catat_log('logout', 'pengguna', uid(), 'Keluar dari sistem');
    }
    ingat_saya(false);
    $_SESSION = [];
    session_destroy();
}

function catat_log(string $aksi, string $objek = '', int $objekId = 0, string $ket = ''): void
{
    try {
        insert('log_aktivitas', [
            'pengguna_id' => uid() ?: null,
            'aksi'        => $aksi,
            'objek'       => $objek,
            'objek_id'    => $objekId ?: null,
            'keterangan'  => mb_substr($ket, 0, 500),
            'ip'          => ip_pengunjung(),
        ]);
    } catch (Throwable $e) {
    }
}
