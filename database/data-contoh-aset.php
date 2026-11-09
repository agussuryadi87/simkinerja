<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/lib/helpers.php';
require dirname(__DIR__) . '/lib/kartu_kontrol.php';
require dirname(__DIR__) . '/lib/aset.php';

$mode = $argv[1] ?? '';

$hapusContoh = static function (): array {
    $n = ['pengajuan' => 0];
    foreach (array_column(rows("SELECT id FROM aset_pengajuan WHERE alasan LIKE '[CONTOH]%'"), 'id') as $pid) {
        aset_hapus_semua_foto('pengajuan_id', (int) $pid);
        q('DELETE FROM aset_foto WHERE pengajuan_id = ?', [$pid]);
        q('DELETE FROM aset_pengajuan WHERE id = ?', [$pid]);
        $n['pengajuan']++;
    }
    q("DELETE FROM aset_mutasi WHERE keterangan LIKE '[CONTOH]%'");
    q("DELETE FROM aset_opname WHERE catatan LIKE '[CONTOH]%'");
    foreach (array_column(rows("SELECT id FROM aset_kerusakan WHERE uraian LIKE '[CONTOH]%'"), 'id') as $id) {
        aset_hapus_semua_foto('kerusakan_id', (int) $id);
    }
    foreach (array_column(rows("SELECT id FROM aset_barang WHERE kode LIKE 'CTH-%'"), 'id') as $id) {
        aset_hapus_semua_foto('barang_id', (int) $id);
    }
    foreach (array_column(rows("SELECT id FROM aset_ruang WHERE kode LIKE 'CTH-%'"), 'id') as $id) {
        aset_hapus_semua_foto('ruang_id', (int) $id);
    }
    $n['kerusakan'] = q("DELETE FROM aset_kerusakan WHERE uraian LIKE '[CONTOH]%'")->rowCount();
    $n['perawatan'] = q("DELETE FROM aset_perawatan WHERE catatan LIKE '[CONTOH]%'")->rowCount();
    $n['barang'] = q("DELETE FROM aset_barang WHERE kode LIKE 'CTH-%'")->rowCount();
    $n['ruang'] = q("DELETE FROM aset_ruang WHERE kode LIKE 'CTH-%'")->rowCount();
    return $n;
};

if ($mode === 'hapus') {
    $n = $hapusContoh();
    echo 'Dihapus: ' . $n['ruang'] . ' ruangan, ' . $n['barang'] . ' barang, ' . $n['perawatan'] . ' catatan perawatan, ' . $n['kerusakan'] . ' laporan kerusakan, ' . $n['pengajuan'] . " pengajuan (beserta mutasi, penghapusan, dan opname terkait).\n";
    exit(0);
}
if ($mode !== 'masuk') {
    echo "Pemakaian: php database\\data-contoh-aset.php masuk|hapus\n";
    exit(1);
}
if ((int) val("SELECT COUNT(*) FROM aset_ruang WHERE kode LIKE 'CTH-%'", [], 0) > 0) {
    echo "Data contoh sudah ada. Jalankan 'hapus' dahulu jika ingin membuat ulang.\n";
    exit(1);
}

$tgl = static function (int $selisih): string {
    return date('Y-m-d', strtotime(($selisih >= 0 ? '+' : '') . $selisih . ' days'));
};
$sumberFoto = [dirname(__DIR__) . '/assets/img/tim-bauk.jpg', dirname(__DIR__) . '/assets/img/kampus-uis.webp'];
$foto = static function (string $kolom, int $id, int $urut, string $ket) use ($sumberFoto): void {
    $sumber = $sumberFoto[$urut % count($sumberFoto)];
    $ext = strtolower(pathinfo($sumber, PATHINFO_EXTENSION));
    $dir = APP_ROOT . '/uploads/aset';
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
    $awalan = ['ruang_id' => 'r', 'barang_id' => 'b', 'kerusakan_id' => 'k', 'pengajuan_id' => 'p'][$kolom] . $id;
    $nama = $awalan . '-' . bin2hex(random_bytes(8)) . '.' . $ext;
    copy($sumber, $dir . '/' . $nama);
    insert('aset_foto', [$kolom => $id, 'berkas' => $nama, 'keterangan' => $ket]);
};

db()->beginTransaction();

$ruang = static function (string $kode, string $nama, string $gedung, string $lantai, string $jenis, int $kap, float $luas, string $pj): int {
    return insert('aset_ruang', ['kode' => $kode, 'nama' => $nama, 'gedung' => $gedung, 'lantai' => $lantai, 'jenis' => $jenis, 'kapasitas' => $kap, 'luas' => $luas,
        'penanggung_jawab' => $pj, 'keterangan' => '[CONTOH]']);
};
$r1 = $ruang('CTH-A101', 'Ruang Kelas A101', 'Gedung A', '1', 'ruang_kelas', 40, 48, 'Bidang Sarana dan Prasarana');
$r2 = $ruang('CTH-LAB1', 'Laboratorium Komputer', 'Gedung B', '2', 'laboratorium', 30, 72, 'Bidang Sarana dan Prasarana');
$r3 = $ruang('CTH-R01', 'Ruang Rapat Rektorat', 'Gedung Rektorat', '3', 'rapat', 20, 36, 'Biro Administrasi Umum dan Keuangan');
$r4 = $ruang('CTH-PRP', 'Perpustakaan Pusat', 'Gedung C', '1', 'perpustakaan', 80, 180, 'Unit Perpustakaan');
$r5 = $ruang('CTH-AUL', 'Aula Ibnu Sina', 'Gedung Rektorat', '1', 'aula', 300, 420, 'Bidang Sarana dan Prasarana');
$foto('ruang_id', $r1, 0, 'Tampak depan ruangan');
$foto('ruang_id', $r1, 1, 'Area tempat duduk');
$foto('ruang_id', $r2, 1, 'Deretan komputer');
$foto('ruang_id', $r3, 0, 'Meja rapat');
$foto('ruang_id', $r4, 1, 'Rak koleksi');
$foto('ruang_id', $r5, 0, 'Panggung aula');
$foto('ruang_id', $r5, 1, 'Area kursi peserta');

$barang = static function (string $kode, string $nama, string $kat, string $merek, string $model, string $seri, int $ruang, int $jml, string $satuan, int $tahun, float $harga, string $dana, string $posisi = '') : int {
    return insert('aset_barang', ['kode' => $kode, 'nama' => $nama, 'kategori' => $kat, 'merek' => $merek, 'model' => $model, 'no_seri' => $seri, 'ruang_id' => $ruang, 'posisi' => $posisi,
        'jumlah' => $jml, 'satuan' => $satuan, 'tahun' => $tahun, 'harga' => $harga, 'sumber_dana' => $dana, 'keterangan' => '[CONTOH]']);
};
$b1 = $barang('CTH-AC-001', 'AC Split 1,5 PK', 'ac', 'Daikin', 'FTV35', 'DK35-2301', $r1, 1, 'unit', 2023, 6500000, 'Yayasan', 'Dinding belakang');
$b2 = $barang('CTH-AC-002', 'AC Cassette 2 PK', 'ac', 'Panasonic', 'CS-D18', 'PN18-2208', $r3, 1, 'unit', 2022, 11800000, 'Yayasan', 'Plafon tengah');
$b3 = $barang('CTH-PRJ-001', 'Proyektor', 'audio_visual', 'Epson', 'EB-X06', 'EP06-2105', $r2, 1, 'unit', 2021, 5400000, 'Hibah', 'Plafon depan');
$b4 = $barang('CTH-MJ-001', 'Meja Dosen', 'furnitur', 'Olympic', 'MD-120', '', $r1, 2, 'buah', 2022, 1250000, 'Yayasan', 'Depan kelas');
$b5 = $barang('CTH-PC-001', 'Komputer Desktop', 'komputer', 'Lenovo', 'ThinkCentre M70', 'LN70-3392', $r2, 30, 'unit', 2024, 9750000, 'Yayasan', 'Meja praktikum');
foreach ([[$b1, 0], [$b2, 1], [$b3, 0], [$b4, 1], [$b5, 0]] as [$bid, $u]) {
    $foto('barang_id', $bid, $u, 'Foto barang');
}

$hapus = static function (string $kode, string $nama, string $kat, int $ruang, int $jml, int $tahun, float $harga, string $alasan, int $selisih, string $ket) use ($tgl): void {
    $bid = insert('aset_barang', ['kode' => $kode, 'nama' => $nama, 'kategori' => $kat, 'ruang_id' => $ruang, 'jumlah' => $jml, 'satuan' => 'unit', 'tahun' => $tahun, 'harga' => $harga,
        'kondisi' => $alasan === 'hilang' ? 'hilang' : 'rusak_berat', 'status' => 'dihapuskan', 'keterangan' => '[CONTOH] ' . $ket]);
    $umur = aset_umur_kategori()[$kat] ?? null;
    $tanggal = $tgl($selisih);
    insert('aset_penghapusan', ['nomor' => aset_nomor_dokumen('aset_penghapusan', 'PH', 'tanggal', $tanggal), 'barang_id' => $bid, 'tanggal' => $tanggal, 'alasan' => $alasan,
        'nilai_buku' => aset_nilai_buku($harga, $jml, $tahun, $umur), 'keterangan' => $ket]);
};
$hapus('CTH-HPS-001', 'Kursi Kuliah Patah', 'furnitur', $r1, 6, 2016, 280000, 'rusak_berat', -60, 'Rangka patah, tidak dapat diperbaiki.');
$hapus('CTH-HPS-002', 'Printer Laser Lama', 'komputer', $r3, 1, 2015, 3200000, 'usang', -48, 'Suku cadang sudah tidak tersedia.');
$hapus('CTH-HPS-003', 'Kipas Angin Berdiri', 'elektronik', $r5, 2, 2018, 450000, 'hilang', -35, 'Hilang setelah kegiatan wisuda.');
$hapus('CTH-HPS-004', 'Lemari Arsip Kayu', 'furnitur', $r4, 1, 2014, 1800000, 'dijual', -22, 'Dijual sebagai barang bekas.');
$hapus('CTH-HPS-005', 'Monitor Tabung', 'komputer', $r2, 8, 2013, 1500000, 'dihibahkan', -12, 'Dihibahkan ke sekolah mitra.');

$rawat = static function (string $jenis, ?int $ruang, ?int $brg, string $kegiatan, int $selisih, string $status, string $petugas, ?int $ulang, ?float $biaya = null) use ($tgl): void {
    insert('aset_perawatan', ['jenis' => $jenis, 'ruang_id' => $ruang, 'barang_id' => $brg, 'kegiatan' => $kegiatan, 'tanggal' => $tgl($selisih),
        'status' => $status, 'petugas' => $petugas, 'ulang_hari' => $ulang, 'biaya' => $biaya, 'catatan' => '[CONTOH]',
        'selesai_pada' => $status === 'selesai' ? $tgl($selisih) : null]);
};
$rawat('kebersihan', $r1, null, 'Sapu dan pel lantai', -2, 'selesai', 'Pak Rahmat', 7);
$rawat('kebersihan', $r2, null, 'Pembersihan menyeluruh (general cleaning)', -3, 'terjadwal', 'Tim kebersihan', 30);
$rawat('kebersihan', $r3, null, 'Pembersihan meja dan kursi', 2, 'terjadwal', 'Bu Sari', 7);
$rawat('kebersihan', $r4, null, 'Pembersihan kaca dan jendela', -6, 'selesai', 'Pak Rahmat', 14);
$rawat('kebersihan', $r5, null, 'Pembersihan menyeluruh (general cleaning)', 5, 'terjadwal', 'Tim kebersihan', 30);
$rawat('ac', null, $b1, 'Cuci filter / indoor', -30, 'selesai', 'Teknisi CV Dingin Jaya', 90, 150000);
$rawat('ac', null, $b1, 'Pengecekan rutin', 5, 'terjadwal', 'Teknisi CV Dingin Jaya', 90);
$rawat('ac', null, $b2, 'Cuci unit outdoor', -4, 'terjadwal', 'Teknisi CV Dingin Jaya', 180);
$rawat('ac', null, $b2, 'Pengecekan dan isi freon', -75, 'selesai', 'Teknisi CV Dingin Jaya', 180, 350000);
$rawat('ac', null, $b1, 'Penggantian komponen', -120, 'selesai', 'Teknisi Service Prima', null, 275000);

$rusak = static function (string $jenis, ?int $ruang, ?int $brg, string $bagian, string $judul, string $tingkat, string $status, int $lapor, ?int $selesai = null, ?float $biaya = null, string $tindak = '') use ($tgl): int {
    return insert('aset_kerusakan', ['nomor' => aset_nomor_kerusakan($jenis), 'jenis' => $jenis, 'ruang_id' => $ruang, 'barang_id' => $brg, 'bagian' => $bagian,
        'judul' => $judul, 'uraian' => '[CONTOH] ' . $judul . '.', 'tingkat' => $tingkat, 'status' => $status, 'tanggal_lapor' => $tgl($lapor), 'pelapor' => 'Dosen pengampu',
        'tindak_lanjut' => $tindak, 'tanggal_selesai' => $selesai !== null ? $tgl($selesai) : null, 'biaya' => $biaya]);
};
$k1 = $rusak('sarana', $r2, $b3, '', 'Proyektor tidak menampilkan gambar', 'berat', 'diproses', -6, null, null, 'Unit dikirim ke service center.');
$k2 = $rusak('sarana', $r3, $b2, '', 'AC tidak dingin dan menetes', 'sedang', 'dilaporkan', -2);
$k3 = $rusak('sarana', $r1, $b1, '', 'Remote AC tidak berfungsi', 'ringan', 'selesai', -20, -18, 85000, 'Remote diganti baru.');
$k4 = $rusak('sarana', $r2, $b5, '', 'Tiga unit komputer tidak bisa menyala', 'sedang', 'diproses', -4, null, null, 'Pengecekan power supply oleh teknisi.');
$k5 = $rusak('sarana', $r1, $b4, '', 'Laci meja dosen macet', 'ringan', 'selesai', -15, -12, 60000, 'Rel laci diganti.');
$p1 = $rusak('prasarana', $r1, null, 'Atap / plafon', 'Plafon bocor saat hujan', 'sedang', 'diproses', -5, null, null, 'Menunggu tukang untuk perbaikan atap.');
$p2 = $rusak('prasarana', $r2, null, 'Instalasi listrik', 'Stop kontak meja praktikum korsleting', 'berat', 'dilaporkan', -1);
$p3 = $rusak('prasarana', $r3, null, 'Dinding / cat', 'Cat dinding mengelupas', 'ringan', 'selesai', -25, -15, 450000, 'Dicat ulang.');
$p4 = $rusak('prasarana', $r4, null, 'Jaringan internet', 'Wi-Fi sering terputus di area baca', 'sedang', 'dilaporkan', -3);
$p5 = $rusak('prasarana', $r5, null, 'Pintu / jendela', 'Engsel pintu utama aula lepas', 'berat', 'diproses', -7, null, null, 'Pemesanan engsel baru.');
foreach ([[$k1, 0], [$k4, 1], [$p1, 1], [$p2, 0], [$p5, 1]] as [$kid, $u]) {
    $foto('kerusakan_id', $kid, $u, 'Foto bukti kerusakan');
}

$pengajuan = static function (string $nama, string $kat, int $jml, string $satuan, float $harga, int $ruang, string $pri, string $status, int $selisih, array $tambah = []) use ($tgl): int {
    $tanggal = $tgl($selisih);
    return insert('aset_pengajuan', $tambah + ['nomor' => aset_nomor_dokumen('aset_pengajuan', 'PBR', 'tanggal', $tanggal), 'tanggal' => $tanggal, 'nama_barang' => $nama, 'kategori' => $kat,
        'jumlah' => $jml, 'satuan' => $satuan, 'estimasi_harga' => $harga, 'ruang_id' => $ruang, 'alasan' => '[CONTOH] Kebutuhan operasional ruangan.', 'prioritas' => $pri,
        'pemohon' => 'Bidang Sarana dan Prasarana', 'status' => $status]);
};
$pengajuan('Kursi kuliah', 'furnitur', 30, 'buah', 350000, $r1, 'tinggi', 'diajukan', -3);
$pengajuan('Stabilizer listrik', 'elektronik', 2, 'unit', 850000, $r2, 'sedang', 'disetujui', -8, ['tanggal_keputusan' => $tgl(-6), 'catatan_keputusan' => 'Disetujui sesuai anggaran.']);
$pb = $pengajuan('Lemari arsip besi', 'furnitur', 3, 'unit', 2750000, $r3, 'rendah', 'dibeli', -14, ['tanggal_keputusan' => $tgl(-12), 'catatan_keputusan' => 'Disetujui.',
    'no_kwitansi' => 'KW-0187', 'tanggal_kwitansi' => $tgl(-5), 'penyedia' => 'Toko Mebel Sejahtera', 'nilai_realisasi' => 8100000]);
$foto('pengajuan_id', $pb, 0, 'Kwitansi pembelian');
$pd = $pengajuan('Kamera pengawas (CCTV)', 'elektronik', 4, 'unit', 1450000, $r5, 'sedang', 'diterima', -30, ['tanggal_keputusan' => $tgl(-28), 'catatan_keputusan' => 'Disetujui.',
    'no_kwitansi' => 'KW-0142', 'tanggal_kwitansi' => $tgl(-20), 'penyedia' => 'CV Sentra Elektronik', 'nilai_realisasi' => 5600000]);
$foto('pengajuan_id', $pd, 1, 'Kwitansi pembelian');
$pengajuan('Karpet aula', 'lainnya', 1, 'roll', 12000000, $r5, 'rendah', 'ditolak', -18, ['tanggal_keputusan' => $tgl(-15), 'catatan_keputusan' => 'Ditunda, anggaran semester ini terbatas.']);

$mutasi = static function (string $jenis, int $brg, ?int $dari, ?int $ke, int $selisih, array $tambah = []) use ($tgl): void {
    insert('aset_mutasi', $tambah + ['jenis' => $jenis, 'barang_id' => $brg, 'dari_ruang_id' => $dari, 'ke_ruang_id' => $ke, 'tanggal' => $tgl($selisih), 'keterangan' => '[CONTOH]']);
};
$mutasi('pindah', $b3, $r1, $r2, -90, ['status' => 'selesai']);
$mutasi('pindah', $b1, $r2, $r1, -75, ['status' => 'selesai']);
$mutasi('pindah', $b4, $r3, $r1, -50, ['status' => 'selesai']);
$mutasi('pindah', $b2, $r1, $r3, -40, ['status' => 'selesai']);
$mutasi('pindah', $b5, $r4, $r2, -30, ['status' => 'selesai']);
$mutasi('pinjam', $b3, $r2, null, -9, ['status' => 'dipinjam', 'peminjam' => 'Dr. Rina Marlina', 'unit_peminjam' => 'Fakultas Teknik', 'rencana_kembali' => $tgl(-4), 'jumlah' => 1]);
$mutasi('pinjam', $b5, $r2, null, -2, ['status' => 'dipinjam', 'peminjam' => 'Pak Hendra', 'unit_peminjam' => 'Bidang Kemahasiswaan', 'rencana_kembali' => $tgl(3), 'jumlah' => 5]);
$mutasi('pinjam', $b1, $r1, null, -20, ['status' => 'dikembalikan', 'peminjam' => 'Pak Dedi', 'unit_peminjam' => 'Tata Usaha', 'rencana_kembali' => $tgl(-15), 'tanggal_kembali' => $tgl(-16), 'kondisi_kembali' => 'baik', 'jumlah' => 1]);
$mutasi('pinjam', $b4, $r1, null, -28, ['status' => 'dikembalikan', 'peminjam' => 'Bu Ani', 'unit_peminjam' => 'Bidang Keuangan', 'rencana_kembali' => $tgl(-21), 'tanggal_kembali' => $tgl(-22), 'kondisi_kembali' => 'baik', 'jumlah' => 1]);
$mutasi('pinjam', $b5, $r2, null, -45, ['status' => 'dikembalikan', 'peminjam' => 'Pak Rudi', 'unit_peminjam' => 'Fakultas Ekonomi', 'rencana_kembali' => $tgl(-40), 'tanggal_kembali' => $tgl(-38), 'kondisi_kembali' => 'rusak_ringan', 'jumlah' => 10]);

$opname = static function (int $ruang, int $selisih, string $status, string $petugas, array $baris) use ($tgl): void {
    $tanggal = $tgl($selisih);
    $oid = insert('aset_opname', ['nomor' => aset_nomor_dokumen('aset_opname', 'OPN', 'tanggal', $tanggal), 'tanggal' => $tanggal, 'ruang_id' => $ruang, 'petugas' => $petugas, 'status' => $status,
        'catatan' => '[CONTOH]', 'selesai_pada' => $status === 'selesai' ? $tanggal . ' 15:00:00' : null]);
    foreach ($baris as [$bid, $kode, $nama, $sistem, $fisik, $kond, $cat]) {
        insert('aset_opname_item', ['opname_id' => $oid, 'barang_id' => $bid, 'kode' => $kode, 'nama' => $nama, 'jumlah_sistem' => $sistem, 'jumlah_fisik' => $fisik, 'kondisi_fisik' => $kond, 'catatan' => $cat]);
    }
};
$opname($r1, -75, 'selesai', 'Tim Sarana Prasarana', [[$b1, 'CTH-AC-001', 'AC Split 1,5 PK', 1, 1, 'baik', ''], [$b4, 'CTH-MJ-001', 'Meja Dosen', 2, 2, 'baik', '']]);
$opname($r2, -60, 'selesai', 'Tim Sarana Prasarana', [[$b3, 'CTH-PRJ-001', 'Proyektor', 1, 1, 'baik', ''], [$b5, 'CTH-PC-001', 'Komputer Desktop', 30, 28, 'rusak_ringan', 'Dua unit tidak ditemukan.']]);
$opname($r3, -45, 'selesai', 'Pak Rahmat', [[$b2, 'CTH-AC-002', 'AC Cassette 2 PK', 1, 1, 'baik', '']]);
$opname($r2, -10, 'selesai', 'Tim Sarana Prasarana', [[$b3, 'CTH-PRJ-001', 'Proyektor', 1, 1, 'rusak_berat', 'Tidak menyala.'], [$b5, 'CTH-PC-001', 'Komputer Desktop', 30, 30, 'baik', '']]);
$opname($r1, -1, 'berjalan', 'Tim Sarana Prasarana', [[$b1, 'CTH-AC-001', 'AC Split 1,5 PK', 1, null, null, ''], [$b4, 'CTH-MJ-001', 'Meja Dosen', 2, null, null, '']]);

foreach ([$b1, $b2, $b3, $b4, $b5] as $b) {
    aset_sinkron_kondisi_barang($b);
}
db()->commit();
echo "Data contoh aset dibuat: 5 ruangan, 5 barang aktif + 5 dihapuskan, 5 kebersihan, 5 AC, 5 kerusakan sarana, 5 kerusakan prasarana, 5 pengajuan, 5 pemindahan, 5 peminjaman, 5 opname.\n";
echo "Hapus dengan: php database\\data-contoh-aset.php hapus\n";
