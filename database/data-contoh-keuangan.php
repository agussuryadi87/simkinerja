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
    $r = q("DELETE FROM keu_rapb WHERE nama LIKE '[CONTOH]%'")->rowCount();
    $a = q("DELETE FROM keu_audit WHERE judul LIKE '[CONTOH]%'")->rowCount();
    db()->commit();
    echo "Dihapus: $r RAPB dan $a audit contoh.\n";
    exit(0);
}
if ($mode !== 'masuk') {
    echo "Pemakaian: php database\\data-contoh-keuangan.php masuk|hapus\n";
    exit(1);
}
if ((int) val("SELECT COUNT(*) FROM keu_rapb WHERE nama LIKE '[CONTOH]%'", [], 0) > 0) {
    echo "Data contoh sudah ada. Jalankan 'hapus' dulu bila ingin membuat ulang.\n";
    exit(1);
}

$tahun = (int) date('Y');
$pembuat = (int) val("SELECT id FROM pengguna WHERE jabatan LIKE 'Kepala Bidang Keuangan%' LIMIT 1", [], 1);

$rapb = [
    1 => ['RAPB Biro Administrasi Umum dan Keuangan', 'disetujui', '', [
        ['pendapatan', '4.1.01', 'Alokasi anggaran operasional dari universitas', 12, 'bulan', 85000000, 510000000],
        ['belanja', '5.1.01', 'Honorarium dan tunjangan pimpinan biro', 12, 'bulan', 18000000, 108000000],
        ['belanja', '5.2.01', 'Perjalanan dinas dan koordinasi', 8, 'kegiatan', 6500000, 31000000],
        ['belanja', '5.3.02', 'Rapat koordinasi dan konsumsi', 24, 'kegiatan', 1800000, 19800000],
    ]],
    2 => ['RAPB Bidang Kepegawaian', 'disetujui', '', [
        ['belanja', '5.1.02', 'Pelatihan dan pengembangan pegawai', 6, 'kegiatan', 12000000, 36000000],
        ['belanja', '5.2.03', 'Rekrutmen tenaga kependidikan', 1, 'paket', 45000000, 28000000],
        ['belanja', '5.3.01', 'Pengelolaan data dan sistem kepegawaian', 12, 'bulan', 3500000, 21000000],
    ]],
    3 => ['RAPB Bidang Keuangan', 'diajukan', '', [
        ['pendapatan', '4.2.01', 'Penerimaan uang kuliah tunggal', 1200, 'mahasiswa', 3200000, 0],
        ['pendapatan', '4.2.02', 'Penerimaan dana kerja sama dan hibah', 1, 'paket', 250000000, 0],
        ['belanja', '5.1.03', 'Honorarium tim keuangan', 12, 'bulan', 9000000, 0],
        ['belanja', '5.3.03', 'Penyusunan laporan dan dokumen audit', 1, 'paket', 40000000, 0],
        ['belanja', '5.4.01', 'Langganan aplikasi akuntansi', 12, 'bulan', 2500000, 0],
    ]],
    4 => ['RAPB Bidang Perencanaan dan Pengembangan', 'draf', '', [
        ['belanja', '5.1.04', 'Penyusunan renstra dan rencana kerja anggaran', 1, 'paket', 55000000, 0],
        ['belanja', '5.3.04', 'Pemantauan dan evaluasi program kerja', 4, 'kegiatan', 7500000, 0],
    ]],
    5 => ['RAPB Bidang Sarana dan Prasarana', 'disetujui', '', [
        ['belanja', '5.5.01', 'Perawatan gedung dan fasilitas', 12, 'bulan', 22000000, 132000000],
        ['belanja', '5.5.02', 'Pengadaan perangkat dan perlengkapan ruang', 1, 'paket', 180000000, 96500000],
        ['belanja', '5.5.03', 'Perawatan AC dan kebersihan', 12, 'bulan', 8500000, 51000000],
        ['belanja', '5.5.04', 'Perbaikan kerusakan sarana dan prasarana', 6, 'kegiatan', 15000000, 38000000],
    ]],
    6 => ['RAPB Tata Usaha Universitas', 'ditolak', 'Rincian belanja administrasi perlu dilengkapi dasar perhitungan dan rujukan harga.', [
        ['belanja', '5.6.01', 'Persuratan dan pengarsipan', 12, 'bulan', 4500000, 0],
        ['belanja', '5.6.02', 'Kegiatan wisuda dan acara universitas', 1, 'paket', 120000000, 0],
    ]],
];

db()->beginTransaction();
$jumItem = 0;
foreach ($rapb as $unit => [$nama, $status, $catatan, $itemList]) {
    $rid = insert('keu_rapb', ['tahun' => $tahun, 'unit_id' => $unit, 'nama' => '[CONTOH] ' . $nama, 'status' => $status, 'catatan' => $catatan, 'dibuat_oleh' => $pembuat]);
    foreach ($itemList as [$jenis, $kode, $uraian, $vol, $sat, $harga, $real]) {
        insert('keu_rapb_item', ['rapb_id' => $rid, 'jenis' => $jenis, 'kode' => $kode, 'uraian' => $uraian, 'volume' => $vol, 'satuan' => $sat, 'harga' => $harga, 'realisasi' => $real]);
        $jumItem++;
    }
}

$a1 = insert('keu_audit', ['tahun' => $tahun - 1, 'judul' => '[CONTOH] Audit laporan keuangan tahun buku ' . ($tahun - 1), 'auditor' => 'KAP Hendra Prasetyo dan Rekan',
    'nomor_laporan' => '012/KAP-HPR/III/' . $tahun, 'tanggal_laporan' => ($tahun) . '-03-14', 'opini' => 'wdp', 'status' => 'berjalan']);
$a2 = insert('keu_audit', ['tahun' => $tahun, 'judul' => '[CONTOH] Audit kepatuhan pengelolaan aset dan persediaan', 'auditor' => 'KAP Sinar Mandiri',
    'nomor_laporan' => '045/KAP-SM/IX/' . $tahun, 'tanggal_laporan' => $tahun . '-09-22', 'opini' => '', 'status' => 'berjalan']);

$hari = static function (int $selisih): string {
    return date('Y-m-d', strtotime($selisih . ' days'));
};
$temuan = [
    [$a1, 'T-1', 'Bukti pengeluaran kas pada beberapa transaksi belum dilengkapi tanda tangan penerima dan persetujuan atasan.', 'tinggi', 'Menerapkan daftar periksa kelengkapan bukti sebelum pembayaran dan memverifikasi seluruh bukti yang sudah ada.', 3, $hari(-20), 'berjalan', 'Daftar periksa kelengkapan bukti sudah disusun, verifikasi bukti tahun berjalan sedang dilakukan.', null],
    [$a1, 'T-2', 'Pencatatan aset tetap belum sepenuhnya sesuai dengan hasil inventarisasi fisik.', 'sedang', 'Menyelaraskan daftar aset dengan hasil stock opname dan memutakhirkan nilai perolehan.', 5, $hari(30), 'berjalan', 'Stock opname ruang sudah dilakukan, penyelarasan nilai perolehan sedang berjalan.', null],
    [$a1, 'T-3', 'Penerimaan kas dari kerja sama belum disetorkan tepat waktu ke rekening universitas.', 'sedang', 'Menetapkan batas waktu penyetoran maksimal 2 hari kerja dan memantau secara berkala.', 3, $hari(-45), 'selesai', 'Prosedur penyetoran diterbitkan dan disosialisasikan kepada seluruh bidang.', $hari(-50)],
    [$a1, 'T-4', 'Dokumen kontrak pengadaan belum diarsipkan secara terpusat.', 'rendah', 'Membuat sistem pengarsipan kontrak terpusat di Tata Usaha.', 6, $hari(60), 'baru', '', null],
    [$a2, 'T-1', 'Barang inventaris di beberapa ruangan belum diberi label kode aset.', 'rendah', 'Melakukan pelabelan kode aset pada seluruh barang inventaris.', 5, $hari(45), 'berjalan', 'Pelabelan baru selesai untuk dua ruangan.', null],
    [$a2, 'T-2', 'Tidak terdapat prosedur tertulis untuk penghapusan aset rusak berat.', 'tinggi', 'Menyusun prosedur penghapusan aset dan berita acara pemeriksaan kondisi.', 5, $hari(-5), 'baru', '', null],
];
foreach ($temuan as [$aid, $kode, $uraian, $risiko, $rek, $unit, $tenggat, $status, $tl, $selesai]) {
    insert('keu_temuan', ['audit_id' => $aid, 'kode' => $kode, 'uraian' => $uraian, 'risiko' => $risiko, 'rekomendasi' => $rek, 'unit_id' => $unit,
        'tenggat' => $tenggat, 'status' => $status, 'tindak_lanjut' => $tl, 'selesai_pada' => $selesai]);
}
db()->commit();
echo 'Ditambahkan: ' . count($rapb) . " RAPB ($jumItem rincian), 2 audit, " . count($temuan) . " temuan.\n";
