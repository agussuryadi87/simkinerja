<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/db.php';

$mode = $argv[1] ?? '';

if ($mode === 'hapus') {
    db()->beginTransaction();
    $k = q("DELETE FROM kegiatan WHERE judul LIKE '[CONTOH]%'")->rowCount();
    $u = q("DELETE FROM umpan_balik WHERE isi LIKE '[CONTOH]%'")->rowCount();
    $e = q("DELETE FROM evaluasi WHERE kekuatan LIKE '[CONTOH]%'")->rowCount();
    $g = q("DELETE FROM pengembangan WHERE uraian LIKE '[CONTOH]%'")->rowCount();
    db()->commit();
    echo "Dihapus: $k kegiatan, $u umpan balik, $e evaluasi, $g pengembangan.\n";
    exit(0);
}
if ($mode !== 'masuk') {
    echo "Pemakaian: php database\\data-contoh-alur.php masuk|hapus\n";
    exit(1);
}

$periode = row('SELECT id, mulai, selesai FROM periode WHERE aktif = 1');
if (!$periode) {
    echo "Tidak ada periode aktif.\n";
    exit(1);
}
if ((int) val("SELECT COUNT(*) FROM kegiatan WHERE judul LIKE '[CONTOH]%'", [], 0) > 0) {
    echo "Data contoh sudah ada. Jalankan 'hapus' dulu bila ingin membuat ulang.\n";
    exit(1);
}

$bahan = [
    1 => [
        ['Memimpin rapat koordinasi mingguan antarbidang', 'Notulen rapat koordinasi', 'notulen', ''],
        ['Menelaah laporan bulanan bidang', 'Catatan telaah laporan', 'laporan', ''],
        ['Pembinaan kinerja kepada kepala bidang', 'Catatan hasil pembinaan', 'pertemuan', ''],
        ['Mengikuti rapat panitia penyusunan renstra', 'Masukan untuk draf renstra', 'rapat', 'Panitia Penyusunan Renstra Universitas 2026'],
        ['Menyiapkan bahan paparan untuk pimpinan universitas', 'Berkas bahan paparan', 'dokumen', 'Rektor'],
    ],
    2 => [
        ['Menyusun rekap presensi pegawai bulan Oktober', 'Rekap presensi pegawai', 'dokumen', ''],
        ['Memproses berkas usulan cuti pegawai', 'Berkas cuti diproses', 'berkas', ''],
        ['Memperbarui data induk pegawai pada sistem', 'Data induk terbaru', 'data', ''],
        ['Menyeleksi berkas administrasi pelamar', 'Daftar pelamar lolos administrasi', 'berkas', 'Panitia Rekrutmen Tenaga Kependidikan 2026'],
        ['Menyiapkan data dosen tetap untuk borang akreditasi', 'Berkas data dosen tetap', 'dokumen', 'Kepala Biro'],
    ],
    3 => [
        ['Menyusun laporan realisasi anggaran bulan Oktober', 'Laporan realisasi anggaran', 'laporan', ''],
        ['Memverifikasi bukti pengeluaran kas harian', 'Bukti pengeluaran diverifikasi', 'bukti kas', ''],
        ['Memproses daftar pembayaran honorarium bulanan', 'Daftar pembayaran honorarium', 'berkas', ''],
        ['Menyiapkan dokumen pendukung audit', 'Berkas pendukung audit', 'berkas', 'Panitia Audit Keuangan Tahunan 2026'],
        ['Menyusun rekap penerimaan dan pengeluaran semester', 'Rekap keuangan semester', 'rekap', 'Wakil Rektor II'],
    ],
    4 => [
        ['Menyusun draf rencana kerja dan anggaran 2027', 'Draf RKA tahap awal', 'dokumen', ''],
        ['Memantau capaian program kerja bulan Oktober', 'Laporan pemantauan program', 'laporan', ''],
        ['Merapikan data pada sistem informasi kinerja', 'Daftar perbaikan data', 'data', ''],
        ['Menyusun bahan rapat panitia renstra', 'Bahan rapat panitia', 'dokumen', 'Panitia Penyusunan Renstra Universitas 2026'],
        ['Menyiapkan data capaian program untuk rapat pimpinan', 'Berkas data capaian program', 'dokumen', 'Rektor'],
    ],
    5 => [
        ['Memeriksa kondisi ruang kuliah dan fasilitas gedung', 'Catatan hasil pemeriksaan', 'ruangan', ''],
        ['Memutakhirkan daftar inventaris aset ruang', 'Daftar inventaris terbaru', 'inventaris', ''],
        ['Menindaklanjuti permintaan perbaikan fasilitas', 'Permintaan ditindaklanjuti', 'permintaan', ''],
        ['Membandingkan penawaran pengadaan perangkat', 'Tabel perbandingan penawaran', 'dokumen', 'Panitia Pengadaan Barang dan Jasa 2026'],
        ['Menyiapkan ruang dan perlengkapan rapat pimpinan', 'Ruang rapat siap digunakan', 'ruangan', 'Kepala Biro'],
    ],
    6 => [
        ['Mencatat dan mendistribusikan surat masuk dan keluar', 'Surat dicatat dan didistribusikan', 'surat', ''],
        ['Mengarsipkan dokumen surat dan SK bulan Oktober', 'Berkas diarsipkan', 'arsip', ''],
        ['Menyiapkan jadwal dan administrasi agenda pimpinan', 'Jadwal agenda pimpinan', 'dokumen', ''],
        ['Menyiapkan berkas administrasi wisuda', 'Berkas administrasi wisuda', 'berkas', 'Panitia Wisuda Periode II 2026'],
        ['Menyusun surat tugas mendesak', 'Surat tugas diterbitkan', 'surat', 'Kepala Biro'],
    ],
];

$hariKerjaMundur = static function (int $n): string {
    $d = new DateTime('today');
    while ($n > 0) {
        $d->modify('-1 day');
        if ((int) $d->format('N') <= 5) {
            $n--;
        }
    }
    return $d->format('Y-m-d');
};
$tglSlot = [
    $hariKerjaMundur(2),
    $hariKerjaMundur(1),
    date('N') <= 5 ? date('Y-m-d') : $hariKerjaMundur(0 + 1),
];
$jamSlot = [['08:00', '10:00'], ['10:30', '12:00'], ['13:00', '15:00']];
$catatanRevisi = [
    'Mohon lengkapi hasil dan bukti dukung kegiatan ini sebelum diverifikasi.',
    'Jam pelaksanaan belum sesuai dengan uraian. Mohon diperbaiki.',
];

$pengguna = rows('SELECT id, nama, unit_id, atasan_id FROM pengguna WHERE aktif = 1 AND unit_id IS NOT NULL ORDER BY id');
$sekarang = date('Y-m-d H:i:s');
$pid = (int) $periode['id'];

db()->beginTransaction();
$jumK = $jumU = $jumE = $jumG = 0;
foreach ($pengguna as $i => $p) {
    $uid = (int) $p['id'];
    $daftar = $bahan[(int) $p['unit_id']] ?? $bahan[1];
    $pilih = [$daftar[$i % 3], $daftar[3], $daftar[4]];
    $jenisSlot = [['rutin'], ['panitia'], ['insidental']];
    $atasan = $p['atasan_id'] === null ? null : (int) $p['atasan_id'];

    foreach ($pilih as $s => [$judul, $hasil, $satuan, $ket]) {
        $jenis = $jenisSlot[$s][0];
        if ($atasan === null || $s === 0 || ($s === 1 && $i % 2 === 0)) {
            $status = 'disetujui';
        } elseif ($s === 2 && $i % 5 === 0) {
            $status = 'revisi';
        } else {
            $status = 'diajukan';
        }
        $verif = $status === 'disetujui';
        insert('kegiatan', [
            'periode_id'        => $pid,
            'pengguna_id'       => $uid,
            'tanggal'           => $tglSlot[$s],
            'jam_mulai'         => $jamSlot[$s][0],
            'jam_selesai'       => $jamSlot[$s][1],
            'jenis'             => $jenis,
            'judul'             => '[CONTOH] ' . $judul,
            'uraian'            => 'Data contoh untuk memahami alur sistem.',
            'hasil'             => $hasil,
            'volume'            => 1,
            'satuan'            => $satuan,
            'kepanitiaan'       => $jenis === 'panitia' ? $ket : '',
            'peran_panitia'     => $jenis === 'panitia' ? 'Anggota' : '',
            'nomor_sk'          => $jenis === 'panitia' ? 'SK/CONTOH/2026' : '',
            'pemberi_tugas'     => $jenis === 'insidental' ? $ket : '',
            'status'            => $status,
            'catatan_atasan'    => $status === 'revisi' ? $catatanRevisi[$i % 2] : '',
            'diverifikasi_oleh' => $verif ? ($atasan ?? $uid) : null,
            'diverifikasi_pada' => $verif ? $sekarang : null,
        ]);
        $jumK++;
    }

    if ($atasan === null) {
        continue;
    }

    $jenisUmpan = ['apresiasi', 'arahan', 'koreksi'][$i % 3];
    $isiUmpan = [
        'apresiasi' => 'Terima kasih, pekerjaan rutin bulan ini selesai tepat waktu dan rapi. Pertahankan.',
        'arahan'    => 'Mohon setiap catatan kegiatan dilengkapi tautan bukti dukung agar mudah diverifikasi.',
        'koreksi'   => 'Beberapa catatan jam pelaksanaan belum sesuai jam kerja. Mohon diperbaiki sebelum evaluasi.',
    ][$jenisUmpan];
    $dibaca = $i % 2 === 0 ? 1 : 0;
    insert('umpan_balik', [
        'periode_id' => $pid, 'pegawai_id' => $uid, 'pemberi_id' => $atasan, 'jenis' => $jenisUmpan,
        'isi' => '[CONTOH] ' . $isiUmpan,
        'tanggapan' => ($dibaca && $jenisUmpan === 'arahan') ? 'Siap, akan saya lengkapi paling lambat Jumat.' : null,
        'dibaca' => $dibaca, 'dibuat' => date('Y-m-d H:i:s', strtotime('-' . (1 + $i % 5) . ' days')),
    ]);
    $jumU++;

    $skor = [4, 4, 3, 4, 5, 4, 4];
    $skor[$i % 7] = 5;
    $skor[($i + 3) % 7] = 3 + ($i % 3);
    $perilaku = array_sum($skor) / 7 * 20;
    $capaian = 62 + ($i * 7) % 33;
    $akhir = round($capaian * BOBOT_CAPAIAN + $perilaku * BOBOT_PERILAKU, 2);
    $label = $akhir >= 90 ? 'Sangat Baik' : ($akhir >= 76 ? 'Baik' : ($akhir >= 61 ? 'Cukup' : ($akhir >= 51 ? 'Kurang' : 'Sangat Kurang')));
    $final = $i % 2 === 0;
    $sudah = (int) val('SELECT COUNT(*) FROM evaluasi WHERE periode_id = ? AND pegawai_id = ? AND tw = 1', [$pid, $uid], 0);
    if (!$sudah) {
        insert('evaluasi', [
            'periode_id' => $pid, 'pegawai_id' => $uid, 'tw' => 1, 'penilai_id' => $atasan,
            'pk_pelayanan' => $skor[0], 'pk_akuntabel' => $skor[1], 'pk_kompeten' => $skor[2], 'pk_harmonis' => $skor[3],
            'pk_loyal' => $skor[4], 'pk_adaptif' => $skor[5], 'pk_kolaboratif' => $skor[6],
            'nilai_capaian' => $capaian, 'nilai_perilaku' => round($perilaku, 2), 'nilai_akhir' => $akhir, 'predikat' => $label,
            'kekuatan' => '[CONTOH] Disiplin, teliti, dan responsif terhadap permintaan atasan.',
            'perbaikan' => 'Perlu melengkapi bukti dukung pada setiap kegiatan.',
            'status' => $final ? 'final' : 'draft', 'difinalkan_pada' => $final ? $sekarang : null,
        ]);
        $jumE++;
    }
}

$pengembangan = [
    ['penghargaan', 'Pegawai teladan triwulan I atas konsistensi pencatatan kinerja', 'selesai', 'Piagam penghargaan diserahkan.'],
    ['pelatihan', 'Pelatihan aplikasi pelaporan keuangan perguruan tinggi', 'berjalan', ''],
    ['pelatihan', 'Pelatihan tata kelola arsip dan persuratan digital', 'disetujui', ''],
    ['pembinaan', 'Pembinaan penyusunan laporan kinerja unit', 'usulan', ''],
    ['promosi', 'Usulan promosi menjadi koordinator seksi', 'usulan', ''],
];
$penerima = array_slice(array_column(array_filter($pengguna, static fn($x) => $x['atasan_id'] !== null), 'id'), 0, 5);
foreach ($pengembangan as $n => [$jenis, $uraian, $status, $hasil]) {
    if (!isset($penerima[$n])) {
        break;
    }
    insert('pengembangan', [
        'periode_id' => $pid, 'pegawai_id' => (int) $penerima[$n], 'jenis' => $jenis, 'uraian' => '[CONTOH] ' . $uraian,
        'target_waktu' => date('Y-m-d', strtotime('+' . (20 + $n * 15) . ' days')), 'status' => $status, 'hasil' => $hasil, 'dibuat_oleh' => 2,
    ]);
    $jumG++;
}
db()->commit();
echo 'Ditambahkan untuk ' . count($pengguna) . " pengguna: $jumK kegiatan, $jumU umpan balik, $jumE evaluasi, $jumG pengembangan.\n";
