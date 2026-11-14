<?php

require __DIR__ . '/config.php';
require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/helpers.php';
require __DIR__ . '/lib/ikon.php';
require __DIR__ . '/lib/auth.php';
require __DIR__ . '/lib/model.php';
require __DIR__ . '/lib/rapat.php';
require __DIR__ . '/lib/kartu_kontrol.php';
require __DIR__ . '/lib/aset.php';
require __DIR__ . '/lib/pengumuman.php';
require __DIR__ . '/lib/qr_sederhana.php';
require __DIR__ . '/lib/jad.php';
require __DIR__ . '/lib/keu.php';
require __DIR__ . '/lib/dokumen.php';
require __DIR__ . '/lib/hibah.php';
require __DIR__ . '/lib/xlsx_sederhana.php';
require __DIR__ . '/lib/rapb_format.php';

mulai_sesi();
$_GET = array_filter($_GET, 'is_string');

$daftarHalaman = [
    'dashboard'    => ['Beranda',                        null],
    'kegiatan'     => ['Catatan Harian',                 'kegiatan_isi'],
    'pantau'       => ['Pemantauan Tim',                 'pantau'],
    'umpan'        => ['Umpan Balik',                    'menu_umpan'],
    'evaluasi'     => ['Evaluasi Kinerja',               'menu_evaluasi'],
    'pengembangan' => ['Penghargaan & Pengembangan',     'menu_pengembangan'],
    'laporan'      => ['Laporan Kinerja',                'menu_laporan'],
    'absensi'      => ['Laporan Absensi',                'menu_absensi'],
    'rapat'        => ['Administrasi Surat',        'menu_rapat'],
    'rapat_dokumen' => ['Dokumen Administrasi Surat', 'menu_rapat'],
    'keu_dokumen'  => ['Dokumen Bidang Keuangan',        null],
    'jad_dokumen'  => ['Dokumen Kepegawaian & BKD',      null],
    'jad'          => ['Rekap Perkembangan Data Dosen',           null],
    'jad_data'     => ['Data Dosen',                              null],
    'jad_karyawan' => ['Data Karyawan',                           null],
    'jad_eligible' => ['Dosen Eligible',                          null],
    'hibah'        => ['Perencanaan dan Hibah',       'menu_hibah'],
    'hibah_rekap'  => ['Rekap Hibah',                 'menu_hibah'],
    'hibah_dokumen' => ['Dokumen Perencanaan dan Hibah', 'menu_hibah'],
    'rapb'         => ['RAPB',                                    null],
    'rapb_prodi'   => ['RAPB Program Studi',                      null],
    'rapb_upps'    => ['Rekap RAPB UPPS',                         null],
    'rapb_lkps'    => ['LKPS Penggunaan Dana',                    null],
    'audit'        => ['Audit Independen',                        null],
    'aset'            => ['Sarana dan Prasarana',      null],
    'aset_ruang'      => ['Ruangan & Foto',             null],
    'aset_barang'     => ['Inventaris Barang',          null],
    'aset_pindai'     => ['Pindai QR Barang',           null],
    'aset_foto_cek'   => ['Cek Foto Aset',              null],
    'aset_kk_ruang'   => ['Kartu Kontrol Kebersihan Ruangan', null],
    'aset_kk_ac'      => ['Kartu Pemeliharaan AC',      null],
    'aset_kk_sarana'  => ['Kartu Kontrol Inventaris Sarana', null],
    'aset_kk_rekap'   => ['Monitoring Petugas CS',     null],
    'aset_kebersihan' => ['Maintenance Kebersihan',     null],
    'aset_ac'         => ['Maintenance AC',             null],
    'aset_sarana'     => ['Kerusakan Sarana',           null],
    'aset_prasarana'  => ['Kerusakan Prasarana',        null],
    'aset_kategori'   => ['Kategori Aset',              null],
    'aset_pengajuan'  => ['Pengajuan Barang',           null],
    'aset_mutasi'     => ['Mutasi & Peminjaman',        null],
    'aset_penghapusan' => ['Penghapusan Aset',          null],
    'aset_opname'     => ['Stock Opname',               null],
    'aset_laporan'    => ['Laporan Aset',               null],
    'aset_dokumen'    => ['Dokumen Sarana dan Prasarana', null],
    'pengguna'     => ['Pengguna',                      'pengguna_kelola'],
    'peran'        => ['Peran & Hak Akses',              'peran_kelola'],
    'unit'         => ['Unit Kerja',                     'unit_kelola'],
    'periode'      => ['Periode & Siklus Kinerja',       'periode_kelola'],
    'profil'       => ['Profil Saya',                    null],
    'pengumuman'   => ['Pengumuman',                     null],
];

$h = $_GET['h'] ?? 'dashboard';

if ($h === 'logout') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        logout();
    }
    redirect(base_url('index.php?h=login'));
}

if ($h === 'login') {
    if (sudah_masuk()) {
        redirect(base_url('index.php'));
    }
    require __DIR__ . '/pages/login.php';
    exit;
}

if ($h === 'info_barang' || ($h === 'aset_barang' && ($_GET['aksi'] ?? '') === 'lihat' && !sudah_masuk())) {
    require __DIR__ . '/pages/info_barang.php';
    exit;
}

if ($h === 'arsip_dosen') {
    require __DIR__ . '/pages/arsip_dosen.php';
    exit;
}

if ($h === 'ajukan_barang') {
    require __DIR__ . '/pages/ajukan_barang.php';
    exit;
}

wajib_masuk();

if (!is_string($h)) {
    $h = 'dashboard';
}

if ($h === 'berkas') {
    kirim_berkas_unggahan((string) ($_GET['j'] ?? ''), (string) ($_GET['n'] ?? ''));
}

if ($h === 'notifikasi') {
    header('Content-Type: application/json; charset=utf-8');
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        rapat_tandai_dilihat(uid());
        if (kk_boleh()) {
            kk_tandai_dilihat(uid());
        }
        pengumuman_tandai_dilihat(uid());
    }
    echo json_encode(notifikasi_lonceng(uid()), JSON_UNESCAPED_UNICODE);
    exit;
}

if ((int) user()['sandi_bawaan'] === 1 && $h !== 'profil') {
    redirect(base_url('index.php?h=profil'));
}

if (!isset($daftarHalaman[$h])) {
    $h = 'dashboard';
}
[$judul, $izin] = $daftarHalaman[$h];
if ($izin !== null) {
    wajib_bisa($izin);
}

$subjudul = '';
$aksiJudul = '';
$berkasHalaman = $h;
if (in_array($h, keu_halaman_aktif(), true)) {
    wajib_keu();
} elseif (in_array($h, jad_halaman_aktif(), true)) {
    wajib_jad();
} elseif (isset(KK_HALAMAN[$h])) {
    wajib_kk();
    $berkasHalaman = 'aset_kartu';
} elseif (strpos($h, 'aset') === 0) {
    if (in_array($h, KK_HALAMAN_CS, true) && !boleh_aset()) {
        wajib_kk();
    } else {
        wajib_aset();
    }
    $berkasHalaman = ['aset_kebersihan' => 'aset_rawat', 'aset_ac' => 'aset_rawat', 'aset_sarana' => 'aset_rusak', 'aset_prasarana' => 'aset_rusak'][$h] ?? $h;
}
if (isset(DOKUMEN_MODUL[$h])) {
    $berkasHalaman = 'dokumen';
}
if (in_array($h, ['rapb_prodi', 'rapb_upps', 'rapb_lkps'], true)) {
    $berkasHalaman = 'rapb_format';
}
ob_start();
require __DIR__ . '/pages/' . $berkasHalaman . '.php';
$konten = ob_get_clean();

require __DIR__ . '/views/layout.php';
