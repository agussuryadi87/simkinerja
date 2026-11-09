<?php

define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'simkinerja_bauk');
define('DB_USER', getenv('SIMKIN_DB_USER') ?: 'simkinerja_app');
define('DB_PASS', getenv('SIMKIN_DB_PASS') ?: '8GVxzNgAqw2onejTF6slSyO4QEpc0a13');
define('DB_CHARSET', 'utf8mb4');

define('APP_NAME',  'SIM Kinerja');
define('APP_LONG',  'Sistem Informasi Kinerja');
define('APP_UNIT',  'Biro Administrasi Umum dan Keuangan');
define('APP_UNIT_PENDEK', 'BAUK');
define('APP_INST',  'Universitas Ibnu Sina');
define('APP_INST_PENDEK', 'UIS');
define('APP_KOTA',  'Batam');
define('APP_VER',   '1.0.0');

define('DEFAULT_PASSWORD', 'password123$');

define('MAKS_GAGAL_MASUK', 8);
define('MAKS_GAGAL_MASUK_IP', 40);
define('JEDA_MASUK_MENIT', 15);

define('BOBOT_CAPAIAN', 0.70);
define('BOBOT_PERILAKU', 0.30);
define('BATAS_CAPAIAN', 1.20);

date_default_timezone_set('Asia/Jakarta');
mb_internal_encoding('UTF-8');

define('APP_DEBUG', false);

if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', '0');
    if (!is_dir(__DIR__ . '/logs')) {
        @mkdir(__DIR__ . '/logs', 0775, true);
    }
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/logs/galat.log');
}

define('APP_ROOT', __DIR__);

function csp_nonce(): string
{
    static $nonce = null;
    if ($nonce === null) {
        $nonce = rtrim(strtr(base64_encode(random_bytes(16)), '+/', '-_'), '=');
    }
    return $nonce;
}

function kirim_header_aman(): void
{
    if (PHP_SAPI === 'cli' || headers_sent()) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true) && ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    header_remove('X-Powered-By');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: same-origin');
    header('Permissions-Policy: camera=(self),microphone=(), geolocation=(), payment=(), usb=(), serial=(), bluetooth=(), magnetometer=(), gyroscope=(), accelerometer=(), interest-cohort=()');
    header('Cross-Origin-Opener-Policy: same-origin');
    header('Cross-Origin-Resource-Policy: same-origin');
    header('X-Permitted-Cross-Domain-Policies: none');
    header('X-Download-Options: noopen');
    if ($https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; font-src 'self' data:; "
         . "style-src 'self' 'unsafe-inline'; script-src 'self' 'nonce-" . csp_nonce() . "'; connect-src 'self'; "
         . "object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'self'; manifest-src 'self'"
         . ($https ? '; upgrade-insecure-requests' : ''));
    header('Cache-Control: no-store, max-age=0');
    header('Pragma: no-cache');
}

kirim_header_aman();

function base_url(string $path = ''): string
{
    static $base = null;
    if ($base === null) {
        $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
        $appDir  = realpath(APP_ROOT);
        if ($docRoot !== false && $appDir !== false) {
            $docRoot = rtrim(str_replace('\\', '/', $docRoot), '/');
            $appDir  = rtrim(str_replace('\\', '/', $appDir), '/');
            if ($docRoot !== '' && stripos($appDir, $docRoot) === 0) {
                $base = rtrim(substr($appDir, strlen($docRoot)), '/');
            }
        }
        if ($base === null) {
            $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
            $base = ($script === '/' || $script === '.') ? '' : rtrim($script, '/');
        }
    }
    return $base . '/' . ltrim($path, '/');
}

const JENIS_KERJA = [
    'rutin'      => ['label' => 'Kerja Rutin',      'pendek' => 'Rutin',      'warna' => 'hijau',
                     'ket' => 'Tugas pokok dan fungsi jabatan yang dikerjakan berulang'],
    'panitia'    => ['label' => 'Kerja Kepanitiaan', 'pendek' => 'Panitia',    'warna' => 'biru',
                     'ket' => 'Penugasan dalam kepanitiaan berdasarkan SK atau surat tugas'],
    'insidental' => ['label' => 'Kerja Insidental', 'pendek' => 'Insidental', 'warna' => 'merah',
                     'ket' => 'Tugas tambahan mendadak di luar rencana dari pimpinan'],
];

const KEHADIRAN_KERJA = [
    'masuk' => ['label' => 'Masuk kerja',  'warna' => 'hijau'],
    'sakit' => ['label' => 'Sakit',        'warna' => 'merah'],
    'izin'  => ['label' => 'Izin',         'warna' => 'biru'],
    'cuti'  => ['label' => 'Cuti',         'warna' => 'emas'],
    'dinas' => ['label' => 'Dinas luar',   'warna' => 'ungu'],
];

const TAHAP_SIKLUS = [
    'pelaksanaan'  => ['no' => 1, 'label' => 'Pencatatan & Verifikasi', 'sub' => 'Monitoring',
                       'ket' => 'Pegawai mencatat kegiatan harian; kepala bidang memverifikasi staf, kepala biro memverifikasi kepala bidang.'],
    'evaluasi'     => ['no' => 2, 'label' => 'Evaluasi',     'sub' => 'Reviewing',
                       'ket' => 'Capaian pencatatan harian digabung dengan penilaian perilaku kerja.'],
    'pengembangan' => ['no' => 3, 'label' => 'Penghargaan & Pengembangan', 'sub' => 'Rewarding / Developing',
                       'ket' => 'Hasil evaluasi menjadi dasar penghargaan, promosi, atau pelatihan.'],
];

const STATUS_KEGIATAN = [
    'diajukan'  => ['label' => 'Menunggu Verifikasi', 'warna' => 'biru'],
    'revisi'    => ['label' => 'Perlu Perbaikan',     'warna' => 'merah'],
    'disetujui' => ['label' => 'Terverifikasi',       'warna' => 'hijau'],
];

const JENIS_UMPAN = [
    'apresiasi' => ['label' => 'Apresiasi', 'warna' => 'hijau'],
    'arahan'    => ['label' => 'Arahan',    'warna' => 'biru'],
    'koreksi'   => ['label' => 'Koreksi',   'warna' => 'emas'],
];

const ASPEK_PERILAKU = [
    'pk_pelayanan'   => ['Berorientasi Pelayanan', 'Memahami dan memenuhi kebutuhan pengguna layanan dengan ramah dan solutif'],
    'pk_akuntabel'   => ['Akuntabel', 'Jujur, bertanggung jawab, cermat, disiplin, dan berintegritas'],
    'pk_kompeten'    => ['Kompeten', 'Terus belajar dan mengembangkan kapabilitas untuk kinerja terbaik'],
    'pk_harmonis'    => ['Harmonis', 'Saling peduli dan menghargai perbedaan, membangun suasana kerja kondusif'],
    'pk_loyal'       => ['Loyal', 'Berdedikasi dan mengutamakan kepentingan institusi'],
    'pk_adaptif'     => ['Adaptif', 'Cepat menyesuaikan diri, kreatif, dan proaktif menghadapi perubahan'],
    'pk_kolaboratif' => ['Kolaboratif', 'Membangun kerja sama yang sinergis lintas unit'],
];

const SKALA_PERILAKU = [
    1 => 'Sangat Kurang',
    2 => 'Kurang',
    3 => 'Sesuai Harapan',
    4 => 'Di Atas Harapan',
    5 => 'Sangat Baik',
];

const JENIS_PENGEMBANGAN = [
    'penghargaan' => ['label' => 'Penghargaan', 'warna' => 'emas'],
    'promosi'     => ['label' => 'Promosi Jabatan', 'warna' => 'hijau'],
    'pelatihan'   => ['label' => 'Pelatihan / Diklat', 'warna' => 'biru'],
    'pembinaan'   => ['label' => 'Pembinaan', 'warna' => 'ungu'],
    'mutasi'      => ['label' => 'Rotasi / Mutasi', 'warna' => 'abu'],
];

const STATUS_PENGEMBANGAN = [
    'usulan'    => ['label' => 'Usulan',    'warna' => 'abu'],
    'disetujui' => ['label' => 'Disetujui', 'warna' => 'biru'],
    'berjalan'  => ['label' => 'Berjalan',  'warna' => 'emas'],
    'selesai'   => ['label' => 'Selesai',   'warna' => 'hijau'],
    'batal'     => ['label' => 'Batal',     'warna' => 'merah'],
];

const SATUAN_UMUM = [
    'Kegiatan & layanan' => ['kegiatan', 'rapat', 'pertemuan', 'koordinasi', 'monitoring', 'layanan', 'konsultasi', 'kunjungan', 'sosialisasi', 'pelatihan', 'acara'],
    'Dokumen & administrasi' => ['dokumen', 'berkas', 'surat', 'laporan', 'naskah', 'notulen', 'formulir', 'usulan', 'proposal', 'arsip', 'SK', 'lembar', 'halaman'],
    'Data & sistem' => ['data', 'entri data', 'rekap', 'akun', 'aplikasi', 'modul', 'dashboard'],
    'Keuangan' => ['transaksi', 'pembayaran', 'kwitansi', 'tagihan', 'anggaran', 'bukti kas'],
    'Kepegawaian' => ['orang', 'pegawai', 'berkas pegawai', 'permohonan', 'usulan pegawai'],
    'Sarana & aset' => ['unit', 'paket', 'barang', 'ruangan', 'inventaris', 'perbaikan', 'perawatan', 'permintaan', 'set'],
    'Waktu & ukuran' => ['jam', 'hari', 'minggu', 'bulan', 'kali', 'persen (%)'],
];

const DAFTAR_IZIN = [
    'Siklus Kinerja' => [
        'kegiatan_isi'        => ['Catatan Harian', 'Mengisi log kegiatan rutin, panitia, dan insidental'],
        'pantau'              => ['Pemantauan Tim', 'Memverifikasi kegiatan harian bawahan dan memberi umpan balik'],
        'menu_umpan'          => ['Umpan Balik', 'Membaca umpan balik dari atasan'],
        'menu_evaluasi'       => ['Evaluasi Kinerja', 'Melihat hasil evaluasi kinerja'],
        'evaluasi_nilai'      => ['Menilai kinerja', 'Mengisi dan memfinalkan evaluasi kinerja pegawai', true],
        'menu_pengembangan'   => ['Penghargaan & Pengembangan', 'Melihat penghargaan, pelatihan, dan promosi'],
        'pengembangan_kelola' => ['Mengelola pengembangan', 'Menetapkan penghargaan, pelatihan, dan promosi', true],
    ],
    'Laporan' => [
        'menu_laporan'  => ['Laporan Kinerja', 'Melihat laporan kinerja sendiri'],
        'laporan_lihat' => ['Rekap laporan tim', 'Rekapitulasi kinerja pegawai dan ekspor data', true],
        'menu_absensi'  => ['Laporan Absensi', 'Rekap kehadiran bulanan dari catatan harian'],
    ],
    'Modul Layanan' => [
        'modul_keu'         => ['Bidang Keuangan', 'RAPB dan Audit Independen'],
        'modul_jad'         => ['Kepegawaian & BKD', 'Rekap perkembangan, data dosen, dan dosen eligible'],
        'modul_aset'        => ['Sarana dan Prasarana', 'Inventaris, ruangan, pemeliharaan, kerusakan, dan laporan aset'],
        'aset_kontrol'      => ['Kartu Kontrol CS', 'Mengisi kartu kontrol kebersihan ruangan, pemeliharaan AC, dan inventaris sarana (petugas CS)'],
        'menu_rapat'        => ['Tata Usaha Universitas', 'Melihat berita acara rapat yang diikuti'],
        'rapat_lihat_semua' => ['Melihat seluruh berita acara', 'Semua berita acara rapat dan pemantauan RTL seluruh bidang', true],
        'rapat_kelola'      => ['Mengelola berita acara rapat', 'Membuat rapat, mengisi notulen dan absensi, mengunggah dokumentasi, serta menetapkan RTL', true],
        'menu_hibah'        => ['Perencanaan dan Hibah', 'Membuka menu pengelolaan hibah'],
    ],
    'Pengaturan' => [
        'pengguna_kelola' => ['Pengguna', 'Menambah akun, mengatur atasan, dan menyetel ulang sandi'],
        'peran_kelola'    => ['Peran & Hak Akses', 'Membuat peran, mengatur izinnya, dan hak akses per pegawai'],
        'unit_kelola'     => ['Unit Kerja', 'Struktur biro dan bidang'],
        'periode_kelola'  => ['Periode & Siklus', 'Membuka periode dan memindahkan tahap siklus kinerja'],
    ],
];

const JENIS_RAPAT = [
    'antar_bidang' => 'Rapat koordinasi antar bidang',
    'pimpinan'     => 'Rapat koordinasi dengan pimpinan',
    'internal'     => 'Rapat internal bidang',
    'lainnya'      => 'Rapat lainnya',
];

const KEHADIRAN_RAPAT = [
    'belum'       => ['label' => 'Belum diisi',   'warna' => 'abu'],
    'hadir'       => ['label' => 'Hadir',         'warna' => 'hijau'],
    'izin'        => ['label' => 'Izin',          'warna' => 'biru'],
    'sakit'       => ['label' => 'Sakit',         'warna' => 'emas'],
    'tanpa_kabar' => ['label' => 'Tanpa kabar',   'warna' => 'merah'],
];

const STATUS_RTL = [
    'baru'     => ['label' => 'Baru',      'warna' => 'abu'],
    'berjalan' => ['label' => 'Berjalan',  'warna' => 'biru'],
    'tertunda' => ['label' => 'Tertunda',  'warna' => 'emas'],
    'selesai'  => ['label' => 'Selesai',   'warna' => 'hijau'],
];

const LINGKUP_DATA = [
    'diri'  => ['Diri sendiri', 'Hanya data milik sendiri'],
    'unit'  => ['Unit / bidang', 'Pegawai di unit sendiri beserta sub-unitnya, dan bawahan langsung'],
    'semua' => ['Seluruh pegawai', 'Semua pegawai di biro'],
];
