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
    db()->commit();
    echo "Dihapus: $k kegiatan contoh.\n";
    exit(0);
}
if ($mode !== 'masuk') {
    echo "Pemakaian: php database\\data-contoh.php masuk|hapus\n";
    exit(1);
}

$periode = row('SELECT id, mulai, selesai FROM periode WHERE aktif = 1');
$hari = date('Y-m-d');
if (!$periode || $hari < $periode['mulai'] || $hari > $periode['selesai']) {
    echo "Hari ini di luar periode aktif; data contoh tidak ditambahkan.\n";
    exit(1);
}
if ((int) val("SELECT COUNT(*) FROM kegiatan WHERE judul LIKE '[CONTOH]%'", [], 0) > 0) {
    echo "Data contoh sudah ada. Jalankan 'hapus' dulu bila ingin membuat ulang.\n";
    exit(1);
}

$bahan = [
    'biro' => [
        ['rutin', 'Koordinasi dan pengendalian kinerja bidang di lingkungan biro', 'Jumlah rapat koordinasi terlaksana', 12, 'rapat', 'Memimpin rapat koordinasi mingguan antarbidang', '1 notulen rapat koordinasi', ''],
        ['rutin', 'Penelaahan dan persetujuan laporan bulanan bidang', 'Laporan ditelaah tepat waktu', 12, 'laporan', 'Menelaah laporan bulanan bidang', 'Catatan telaah laporan', ''],
        ['rutin', 'Pembinaan dan evaluasi kinerja pegawai biro', 'Pertemuan pembinaan terlaksana', 12, 'kegiatan', 'Pembinaan kinerja kepada kepala bidang', 'Catatan hasil pembinaan', ''],
        ['panitia', 'Terlaksananya tugas panitia penyusunan renstra universitas', 'Kehadiran dan kontribusi pada rapat panitia', 4, 'kegiatan', 'Mengikuti rapat panitia penyusunan renstra', 'Masukan untuk draf renstra', 'Panitia Penyusunan Renstra Universitas 2026'],
        ['insidental', 'Penyelesaian tugas insidental dari pimpinan universitas', 'Tugas selesai sesuai tenggat', 3, 'dokumen', 'Menyiapkan bahan paparan untuk pimpinan universitas', '1 berkas bahan paparan', 'Rektor'],
    ],
    'kepegawaian' => [
        ['rutin', 'Tersusunnya rekap presensi pegawai bulanan', 'Rekap diserahkan paling lambat tanggal 5', 12, 'laporan', 'Menyusun rekap presensi pegawai bulan Oktober', '1 dokumen rekap presensi', ''],
        ['rutin', 'Terlayaninya administrasi kepegawaian (cuti, kenaikan pangkat, mutasi)', 'Berkas diproses maksimal 3 hari kerja', 24, 'berkas', 'Memproses berkas usulan cuti pegawai', '3 berkas cuti diproses', ''],
        ['rutin', 'Terpeliharanya basis data kepegawaian', 'Data induk diperbarui setiap bulan', 12, 'kegiatan', 'Memperbarui data induk pegawai pada sistem', 'Data induk terbaru', ''],
        ['panitia', 'Terlaksananya tugas Panitia Rekrutmen Tenaga Kependidikan 2026', 'Kehadiran dan penyelesaian tugas panitia', 4, 'kegiatan', 'Menyeleksi berkas administrasi pelamar', 'Daftar pelamar lolos administrasi', 'Panitia Rekrutmen Tenaga Kependidikan 2026'],
        ['insidental', 'Penyiapan data kepegawaian atas permintaan pimpinan', 'Data diserahkan sesuai tenggat', 3, 'dokumen', 'Menyiapkan data dosen tetap untuk borang akreditasi', '1 berkas data dosen tetap', 'Kepala Biro'],
    ],
    'keuangan' => [
        ['rutin', 'Tersusunnya laporan realisasi anggaran bulanan', 'Laporan diserahkan paling lambat tanggal 10', 12, 'laporan', 'Menyusun laporan realisasi anggaran bulan Oktober', '1 laporan realisasi anggaran', ''],
        ['rutin', 'Terverifikasinya bukti pengeluaran dan transaksi kas', 'Bukti diverifikasi pada hari yang sama', 120, 'transaksi', 'Memverifikasi bukti pengeluaran kas harian', '8 bukti pengeluaran diverifikasi', ''],
        ['rutin', 'Terbayarnya honorarium dan tunjangan tepat waktu', 'Pembayaran sesuai jadwal', 12, 'berkas', 'Memproses daftar pembayaran honorarium bulanan', '1 daftar pembayaran honorarium', ''],
        ['panitia', 'Terlaksananya tugas Panitia Audit Keuangan Tahunan', 'Dokumen pendukung audit lengkap', 4, 'kegiatan', 'Menyiapkan dokumen pendukung audit', 'Berkas pendukung audit', 'Panitia Audit Keuangan Tahunan 2026'],
        ['insidental', 'Penyusunan rekap keuangan atas permintaan pimpinan', 'Rekap diserahkan sesuai tenggat', 3, 'dokumen', 'Menyusun rekap penerimaan dan pengeluaran semester', '1 dokumen rekap keuangan', 'Wakil Rektor II'],
    ],
    'perencanaan' => [
        ['rutin', 'Tersusunnya dokumen perencanaan dan penganggaran unit', 'Dokumen selesai sesuai jadwal penyusunan', 4, 'dokumen', 'Menyusun draf rencana kerja dan anggaran 2027', 'Draf RKA tahap awal', ''],
        ['rutin', 'Terpantaunya capaian program kerja unit', 'Laporan pemantauan setiap bulan', 12, 'laporan', 'Memantau capaian program kerja bulan Oktober', '1 laporan pemantauan program', ''],
        ['rutin', 'Terkelolanya sistem informasi kinerja', 'Data pada sistem rapi dan terbarui', 12, 'kegiatan', 'Memeriksa dan merapikan data pada sistem informasi kinerja', 'Daftar perbaikan data', ''],
        ['panitia', 'Terlaksananya tugas Panitia Penyusunan Renstra', 'Kehadiran dan kontribusi pada rapat panitia', 4, 'kegiatan', 'Menyusun bahan rapat panitia renstra', 'Bahan rapat panitia', 'Panitia Penyusunan Renstra Universitas 2026'],
        ['insidental', 'Penyiapan data perencanaan atas permintaan pimpinan', 'Data diserahkan sesuai tenggat', 3, 'dokumen', 'Menyiapkan data capaian program untuk rapat pimpinan', '1 berkas data capaian program', 'Rektor'],
    ],
    'sarana' => [
        ['rutin', 'Terpeliharanya sarana dan prasarana gedung', 'Pemeriksaan rutin terlaksana', 12, 'kegiatan', 'Memeriksa kondisi ruang kuliah dan fasilitas gedung', 'Catatan hasil pemeriksaan', ''],
        ['rutin', 'Tercatatnya inventarisasi aset dengan benar', 'Daftar inventaris diperbarui setiap bulan', 12, 'laporan', 'Memutakhirkan daftar inventaris aset ruang', '1 daftar inventaris terbaru', ''],
        ['rutin', 'Terlayaninya permintaan perbaikan fasilitas', 'Permintaan ditindaklanjuti maksimal 2 hari', 60, 'permintaan', 'Menindaklanjuti permintaan perbaikan fasilitas', '4 permintaan ditindaklanjuti', ''],
        ['panitia', 'Terlaksananya tugas Panitia Pengadaan Barang dan Jasa', 'Kehadiran dan penyelesaian tugas panitia', 4, 'kegiatan', 'Membandingkan penawaran pengadaan perangkat', 'Tabel perbandingan penawaran', 'Panitia Pengadaan Barang dan Jasa 2026'],
        ['insidental', 'Penyiapan sarana untuk kegiatan mendadak', 'Sarana siap sebelum kegiatan dimulai', 3, 'kegiatan', 'Menyiapkan ruang dan perlengkapan rapat pimpinan', 'Ruang rapat siap digunakan', 'Kepala Biro'],
    ],
    'tu' => [
        ['rutin', 'Terkelolanya persuratan masuk dan keluar', 'Surat dicatat dan didistribusikan pada hari yang sama', 240, 'surat', 'Mencatat dan mendistribusikan surat masuk dan keluar', '15 surat dicatat dan didistribusikan', ''],
        ['rutin', 'Tertibnya pengarsipan dokumen universitas', 'Dokumen diarsipkan sesuai klasifikasi', 120, 'berkas', 'Mengarsipkan dokumen surat dan SK bulan Oktober', '20 berkas diarsipkan', ''],
        ['rutin', 'Terlayaninya kebutuhan administrasi pimpinan', 'Agenda pimpinan tersusun tepat waktu', 12, 'kegiatan', 'Menyiapkan jadwal dan administrasi agenda pimpinan', 'Jadwal agenda pimpinan', ''],
        ['panitia', 'Terlaksananya tugas Panitia Wisuda', 'Kehadiran dan penyelesaian tugas panitia', 4, 'kegiatan', 'Menyiapkan berkas administrasi wisuda', 'Berkas administrasi wisuda', 'Panitia Wisuda Periode II 2026'],
        ['insidental', 'Penyiapan surat dan dokumen mendesak dari pimpinan', 'Dokumen selesai sesuai tenggat', 3, 'dokumen', 'Menyusun surat tugas mendesak', '1 surat tugas diterbitkan', 'Kepala Biro'],
    ],
];
$petaUnit = [1 => 'biro', 2 => 'kepegawaian', 3 => 'keuangan', 4 => 'perencanaan', 5 => 'sarana', 6 => 'tu'];
$jam = [['08:00', '09:30'], ['09:30', '11:00'], ['11:00', '12:00'], ['13:00', '14:30'], ['14:30', '16:00']];
$sekarang = date('Y-m-d H:i:s');

db()->beginTransaction();
$jumlahK = 0;
$pengguna = rows('SELECT id, nama, unit_id, atasan_id FROM pengguna WHERE aktif = 1 ORDER BY id');
foreach ($pengguna as $p) {
    $kunci = $petaUnit[(int) ($p['unit_id'] ?? 1)] ?? 'biro';
    $langsung = $p['atasan_id'] === null;
    foreach ($bahan[$kunci] as $i => [$jenis, $uraian, $indikator, $target, $satuan, $kegiatan, $hasil, $ket]) {
        insert('kegiatan', [
            'periode_id'        => $periode['id'],
            'pengguna_id'       => $p['id'],
            'tanggal'           => $hari,
            'jam_mulai'         => $jam[$i][0],
            'jam_selesai'       => $jam[$i][1],
            'jenis'             => $jenis,
            'judul'             => '[CONTOH] ' . $kegiatan,
            'uraian'            => 'Data contoh untuk uji coba sistem. Hapus sebelum data asli dipakai untuk evaluasi.',
            'hasil'             => $hasil,
            'volume'            => 1,
            'satuan'            => $satuan,
            'kepanitiaan'       => $jenis === 'panitia' ? $ket : '',
            'peran_panitia'     => $jenis === 'panitia' ? 'Anggota' : '',
            'nomor_sk'          => $jenis === 'panitia' ? 'SK/CONTOH/2026' : '',
            'pemberi_tugas'     => $jenis === 'insidental' ? $ket : '',
            'status'            => $langsung ? 'disetujui' : 'diajukan',
            'diverifikasi_oleh' => $langsung ? $p['id'] : null,
            'diverifikasi_pada' => $langsung ? $sekarang : null,
        ]);
        $jumlahK++;
    }
}
db()->commit();
echo 'Ditambahkan untuk ' . count($pengguna) . " pengguna: $jumlahK catatan kegiatan (tanggal $hari).\n";
