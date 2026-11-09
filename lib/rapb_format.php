<?php

const RAPB_FAKULTAS = [
    'feb'   => ['singkat' => 'FEB',   'nama' => 'Fakultas Ekonomi dan Bisnis',  'dekan' => 'Dr. Sabri, SE., MM', 'nuptk' => '777 1107 446'],
    'fst'   => ['singkat' => 'FST',   'nama' => 'Fakultas Sains dan Teknologi', 'dekan' => 'Ir. Sanusi, S.T., M.Eng., Ph.D, IPM', 'nuptk' => '777 0517 640'],
    'fikes' => ['singkat' => 'FIKES', 'nama' => 'Fakultas Ilmu Kesehatan',      'dekan' => 'Dr. Hengky Oktarizal, S.KM., M.KM', 'nuptk' => '777 0213 552'],
];

const RAPB_PRODI = [
    'akuntansi-s1'   => ['fakultas' => 'feb',   'nama' => 'Akuntansi (S1)'],
    'manajemen-s1'   => ['fakultas' => 'feb',   'nama' => 'Manajemen (S1)'],
    'manajemen-s2'   => ['fakultas' => 'feb',   'nama' => 'Manajemen (S2)'],
    'si-s1'          => ['fakultas' => 'fst',   'nama' => 'Sistem Informasi (S1)'],
    'ti-s1'          => ['fakultas' => 'fst',   'nama' => 'Teknik Industri (S1)'],
    'tif-s1'         => ['fakultas' => 'fst',   'nama' => 'Teknik Informatika (S1)'],
    'logistik-s1'    => ['fakultas' => 'fst',   'nama' => 'Teknik Logistik (S1)'],
    'perkapalan-s1'  => ['fakultas' => 'fst',   'nama' => 'Teknik Perkapalan (S1)'],
    'k3-s1'          => ['fakultas' => 'fikes', 'nama' => 'Kesehatan dan Keselamatan Kerja (S1)'],
    'kesling-s1'     => ['fakultas' => 'fikes', 'nama' => 'Kesehatan Lingkungan (S1)'],
    'kesmas-s2'      => ['fakultas' => 'fikes', 'nama' => 'Kesehatan Masyarakat (S2)'],
];

const RAPB_TAHUN_AKADEMIK = ['2023-2024', '2024-2025', '2025-2026', '2026-2027'];

const RAPB_PENDAPATAN = [
    ['p_mhs', '210100', 'Penerimaan Dana Mahasiswa', 'PENERIMAAN DANA MAHASISWA', [
        ['210101', 'Penerimaan Dana Formulir', 'Formulir'],
        ['210102', 'Penerimaan Dana Uang Pangkal', 'Uang Pangkal'],
        ['210103', 'Penerimaan Dana Uang HER Registrasi', 'Uang HER Registrasi'],
        ['210104', 'Penerimaan Dana RPL', 'RPL'],
        ['210105', 'Penerimaan Dana Matrikulasi', 'Matrikulasi'],
        ['210106', 'Penerimaan Dana SPP', 'SPP'],
        ['210107', 'Penerimaan Dana Ujian', 'Ujian'],
        ['210108', 'Penerimaan Dana KKL/KPM', 'KKL/KPM'],
        ['210109', 'Penerimaan Dana Seminar Proposal/Sidang Tugas Akhir/Thesis', 'Seminar Proposal/Sidang Tugas Akhir/Thesis'],
        ['210110', 'Penerimaan Dana Semester Antara', 'Semester Antara'],
        ['210111', 'Penerimaan Dana Magang', 'Magang'],
        ['210112', 'Penerimaan Dana PBL', 'PBL'],
        ['210113', 'Penerimaan Dana Perlatihan Sertifikasi Mahasiswa', 'Perlatihan Sertifikasi Mahasiswa'],
        ['210114', 'Penerimaan Dana Praktikum', 'Praktikum'],
        ['210115', 'Penerimaan Dana Webinar International', 'Webinar International'],
        ['210116', 'Penerimaan Dana Jilid', 'Jilid'],
        ['210117', 'Penerimaan Dana TOEFL', 'TOEFL'],
        ['210118', 'Penerimaan Dana Yudisium', 'Yudisium'],
        ['210119', 'Penerimaan Dana Wisuda', 'Wisuda'],
    ]],
    ['p_yys', '220000', 'Penerimaan Dana Yayasan', 'PENERIMAAN DANA YAYASAN', [
        ['220101', 'Penerimaan Dana Bantuan Pembangunan Gedung dan Fasilitas', 'Dana Bantuan Pembangunan Gedung dan Fasilitas'],
        ['220102', 'Penerimaan Dana Beasiswa Mahasiswa', 'Dana Beasiswa Mahasiswa - Yayasan'],
    ]],
    ['p_pem', '230000', 'Penerimaan Dana Pemerintah (LLDIKTI/Kemdiktisaintek/Pihak Luar)', 'PENERIMAAN DANA PEMERINTAH (LLDIKTI/KEMDIKTISAINTEK/PIHAK LUAR)', [
        ['230101', 'Penerimaan Dana Beasiswa Mahasiswa - KIP Kuliah', 'Dana Beasiswa Mahasiswa - KIP Kuliah'],
        ['230102', 'Penerimaan Dana Beasiswa Mahasiswa - Prestasi Peningkatan Akademik (PPA)', 'Dana Beasiswa Mahasiswa - Prestasi Peningkatan Akademik (PPA)'],
        ['230103', 'Penerimaan Dana Beasiswa Mahasiswa - Baznas', 'Dana Beasiswa Mahasiswa - Baznas'],
        ['230104', 'Penerimaan Dana Hibah Penelitian', 'Dana Hibah Penelitian'],
        ['230105', 'Penerimaan Dana Hibah Pengabdian', 'Dana Hibah Pengabdian'],
        ['230106', 'Penerimaan Dana Beasiswa Mahasiswa - Bantuan SPP Semester', 'Dana Beasiswa Mahasiswa - Bantuan SPP Semester'],
        ['230107', 'Penerimaan Dana Hibah Perguruan Tinggi', 'Dana Hibah Perguruan Tinggi'],
    ]],
    ['p_pemda', '240000', 'Penerimaan Dana Pemda Provinsi', 'PENERIMAAN DANA PEMDA PROVINSI', [
        ['240101', 'Penerimaan Dana Hibah', 'Dana Hibah Pemda Provinsi'],
        ['240102', 'Penerimaan Dana Beasiswa Pemda', 'Dana Beasiswa Pemda'],
    ]],
    ['p_usaha', '250000', 'Penerimaan Dana Unit Usaha', 'PENERIMAAN DANA UNIT USAHA', [
        ['250101', 'Penerimaan Dana Penyewaan Labor', 'Dana Penyewaan Labor'],
        ['250102', 'Penerimaan Dana Penyewaan Sound System', 'Dana Penyewaan Sound System'],
        ['250103', 'Penerimaan Dana Penyewaan Ruang Kelas', 'Dana Penyewaan Ruang Kelas'],
        ['250104', 'Penerimaan Dana Penyewaan Aula', 'Dana Penyewaan Aula'],
        ['250105', 'Penerimaan Dana Agen Elpiji', 'Dana Agen Elpiji'],
        ['250106', 'Penerimaan Dana Klinik', 'Dana Klinik'],
        ['250107', 'Penerimaan Dana Koperasi', 'Dana Koperasi'],
        ['250108', 'Penerimaan Dana Percetakan', 'Dana Percetakan'],
    ]],
];

const RAPB_BELANJA = [
    ['h', '110000', 'Pendidikan:'],
    ['g', 'b_akad', '110100', 'Pelaksanaan Kegiatan Akademik', [
        ['110101', 'Ujian Tengah Semester'], ['110102', 'Ujian Akhir Semester'],
        ['110103', 'Kuliah Kerja Lapangan/Kuliah Pengabdian Masyarakat'], ['110104', 'Ujian Seminar Proposal'],
        ['110105', 'Ujian Sidang Tugas Akhir'], ['110106', 'Ujian Thesis'], ['110107', 'KRS'],
        ['110108', 'Pelepasan Lulusan (Pembekalan)'], ['110109', 'Wisuda'], ['110110', 'Semester Antara'],
        ['110111', 'RPL'], ['110112', 'Magang'], ['110113', 'Matrikulasi'], ['110114', 'PBL'],
        ['110115', 'Perlatihan Sertifikasi Mahasiswa'], ['110116', 'Praktikum'], ['110117', 'Webinar International'],
        ['110118', 'Jilid'], ['110119', 'TOEFL'], ['110120', 'Yudisium'],
    ]],
    ['g', 'b_layan', '110200', 'Pelaksanaan Pelayanan Akademik', [
        ['110201', 'Pembuatan Transkrip Nilai'], ['110202', 'Pembuatan Ijazah/Sertifikat'],
    ]],
    ['g', 'b_dosen', '110300', 'Pembayaran Gaji Dosen', [
        ['110301', 'Pembayaran Gaji Dosen Tetap'],
    ]],
    ['g', 'b_karyawan', '110400', 'Pembayaran Gaji Karyawan', [
        ['110401', 'Pembayaran Gaji Pokok'], ['110402', 'Pembayaran Tunjangan Jabatan'],
        ['110403', 'Pembayaran Tunjangan Fungsional'], ['110404', 'Pembayaran Tunjangan Transportasi'],
        ['110405', 'Pembayaran Tunjangan Keluarga (Suami/Istri)'], ['110406', 'Pembayaran Tunjangan Keluarga (Anak)'],
        ['110407', 'Pembayaran Tunjangan Rumah'], ['110408', 'Pembayaran Tunjangan Insentif'],
    ]],
    ['g', 'b_fasakad', '110500', 'Pemeliharaan Fasilitas Akademik Rutin', [
        ['110501', 'Pemeliharaan Sarana Belajar + Alat Lab'],
    ]],
    ['g', 'b_dinas', '110600', 'Perjalanan Dinas Rutin', [
        ['110601', 'Perjalanan Dinas Dalam Daerah (Lokal & Reguler)'], ['110602', 'Perjalanan Luar Daerah (Nasional)'],
        ['110603', 'Perjalanan Luar Negeri (Internasional)'],
    ]],
    ['g', 'b_barjas', '110700', 'Pengadaan Barang/Jasa Rutin', [
        ['110701', 'Pengadaan Alat Tulis Kantor'], ['110702', 'Pengadaan Barang Cetakan (Koran & Majalah)'],
        ['110703', 'Pengadaan Pos & Telekomunikasi'], ['110704', 'Pembayaran Utilitas Listrik'],
        ['110705', 'Pembayaran Utilitas Telpon'], ['110706', 'Pembayaran Utilitas Air PAM & Air Minum'],
        ['110707', 'PBB dan Pajak Bank'], ['110708', 'BBM, Tol dan Parkir'],
    ]],
    ['g', 'b_fasrutin', '110800', 'Pemeliharaan Fasilitas Rutin', [
        ['110801', 'Pemeliharaan Gedung'], ['110802', 'Pemeliharaan Peralatan Kantor'],
        ['110803', 'Pemeliharaan Sarana'], ['110804', 'Pemeliharaan Kendaraan'],
    ]],
    ['g', 'b_siakad', '110900', 'Pemeliharaan Home Page, Jaringan dan Siakad', [
        ['110901', 'Langganan Internet Indihome'], ['110902', 'Langganan Siakad Sevima dan Edlink Class Room'],
        ['110903', 'Langganan Siakad Finance Cloud'], ['110904', 'Langganan Internet Connecting Network (CIC)'],
    ]],
    ['g', 'b_mhs', '111000', 'Program Peningkatan Kualitas Kemahasiswaan', [
        ['111001', 'Pembinaan Mahasiswa'], ['111002', 'Reward Mahasiswa Berprestasi'],
    ]],
    ['t', 'Total Pendidikan', 'rutin'],
    ['T', 'TOTAL ANGGARAN BELANJA RUTIN', 'rutin'],
    ['h', '120000', 'Penelitian'],
    ['g', 'b_penelitian', '120100', 'Pelaksanaan Kegiatan Penelitian', [
        ['120101', 'Hibah Dosen Penelitian (Internal)'], ['120102', 'Hibah Dosen Penelitian (Pihak Luar)'],
    ]],
    ['t', 'Total Penelitian', 'penelitian'],
    ['h', '130000', 'Pengabdian Kepada Masyarakat'],
    ['g', 'b_pkm', '130100', 'Pelaksanaan Pengabdian Masyarakat', [
        ['130101', 'Hibah Dosen Pengabdian Masyarakat (Internal)'], ['130102', 'Hibah Dosen Pengabdian Masyarakat (Pihak Luar)'],
    ]],
    ['t', 'Total Pengabdian Kepada Masyarakat', 'pkm'],
    ['h', '140000', 'Investasi Sarana dan Prasarana'],
    ['g', 'b_sarbel', '140100', 'Pengadaan Sarana Pembelajaran', [
        ['140101', 'Pengadaan Komputer Jurusan & Administrasi'], ['140102', 'Pengadaan Infocus & Ruang Kuliah Multimedia'],
        ['140103', 'Pengadaan Sound System Aula'], ['140104', 'Pengadaan AC'], ['140105', 'Pengadaan Alat Laboratorium'],
        ['140106', 'Pengadaan Buku Perpustakaan'], ['140107', 'Pengadaan Meja & Kursi Perkuliahan'],
        ['140108', 'Pengadaan White Board/Papan Tulis'],
    ]],
    ['g', 'b_sarkan', '140200', 'Pengadaan Sarana Kantor', [
        ['140201', 'Pengadaan Alat Kantor-Komputer, Furnitur dll'],
    ]],
    ['t', 'Total Investasi Sarana', 'sarana'],
    ['g', 'b_prasarana', '140300', 'Pengadaan Prasarana', [
        ['140301', 'Pengadaan Prasarana Olahraga'], ['140302', 'Pengadaan Prasarana Laboratorium'],
        ['140303', 'Pengadaan Prasarana Perpustakaan'], ['140304', 'Pengadaan Prasarana Ruang Kuliah'],
        ['140305', 'Pengadaan Prasarana Kantor'], ['140306', 'Pengadaan Prasarana Aula'],
        ['140307', 'Pengadaan Prasarana Klinik'], ['140308', 'Pengadaan Musholla'],
    ]],
    ['t', 'Total Investasi Prasarana', 'prasarana'],
    ['h', '150000', 'Investasi SDM'],
    ['g', 'b_sdm', '150100', 'Peningkatan Kualitas Sumber Daya Pelayanan', [
        ['150101', 'Kursus bagi Staff'], ['150102', 'Pelatihan bagi Staff'],
        ['150103', 'Studi Lanjut Karyawan S2/S3'], ['150104', 'Seminar (Pembicara)'],
    ]],
    ['g', 'b_kesmhs', '150200', 'Peningkatan Kesejahteraan Mahasiswa', [
        ['150201', 'Pemberian Beasiswa Kemendikbud (KIP Kuliah dan Bantuan SPP)'], ['150202', 'Pemberian Beasiswa Yapista (Bantuan SPP)'],
        ['150203', 'Pemberian Beasiswa Pemda'], ['150204', 'Pemberian Beasiswa Baznas'],
    ]],
    ['t', 'Total Investasi SDM', 'sdm'],
    ['T', 'TOTAL ANGGARAN BELANJA NON RUTIN', 'nonrutin'],
    ['T', 'TOTAL ANGGARAN BELANJA RUTIN DAN NON RUTIN', 'belanja'],
];

const RAPB_KELOMPOK_TOTAL = [
    'rutin'      => ['b_akad', 'b_layan', 'b_dosen', 'b_karyawan', 'b_fasakad', 'b_dinas', 'b_barjas', 'b_fasrutin', 'b_siakad', 'b_mhs'],
    'penelitian' => ['b_penelitian'],
    'pkm'        => ['b_pkm'],
    'sarana'     => ['b_sarbel', 'b_sarkan'],
    'prasarana'  => ['b_prasarana'],
    'sdm'        => ['b_sdm', 'b_kesmhs'],
    'nonrutin'   => ['b_penelitian', 'b_pkm', 'b_sarbel', 'b_sarkan', 'b_prasarana', 'b_sdm', 'b_kesmhs'],
    'belanja'    => ['b_akad', 'b_layan', 'b_dosen', 'b_karyawan', 'b_fasakad', 'b_dinas', 'b_barjas', 'b_fasrutin', 'b_siakad', 'b_mhs',
                     'b_penelitian', 'b_pkm', 'b_sarbel', 'b_sarkan', 'b_prasarana', 'b_sdm', 'b_kesmhs'],
    'pendapatan' => ['p_mhs', 'p_yys', 'p_pem', 'p_pemda', 'p_usaha'],
];

const RAPB_REKAP_BELANJA = [
    ['sub', 'A', 'ANGGARAN BELANJA RUTIN'],
    ['label', 'Pendidikan:'],
    ['item', '1', 'Pelaksanaan Kegiatan Akademik', ['b_akad']],
    ['item', '2', 'Pelaksanaan Pelayanan Akademik', ['b_layan']],
    ['item', '3', 'Pembayaran Gaji Dosen', ['b_dosen']],
    ['item', '4', 'Pembayaran Gaji Karyawan', ['b_karyawan']],
    ['item', '5', 'Pemeliharaan Fasilitas Akademik Rutin', ['b_fasakad']],
    ['item', '6', 'Perjalanan Dinas Rutin', ['b_dinas']],
    ['item', '7', 'Pengadaan Barang/Jasa Rutin', ['b_barjas']],
    ['item', '8', 'Pemeliharaan Fasilitas Rutin', ['b_fasrutin']],
    ['item', '9', 'Pemeliharaan Home Page, Jaringan dan Siakad', ['b_siakad']],
    ['item', '10', 'Program Peningkatan Kualitas Kemahasiswaan', ['b_mhs']],
    ['jumlah', RAPB_KELOMPOK_TOTAL['rutin']],
    ['total', 'Total Anggaran Belanja Rutin', RAPB_KELOMPOK_TOTAL['rutin']],
    ['kosong'],
    ['sub', 'B', 'ANGGARAN BELANJA NON RUTIN'],
    ['label', 'Penelitian:'],
    ['item', '1', 'Pelaksanaan Kegiatan Penelitian', ['b_penelitian']],
    ['jumlah', ['b_penelitian']],
    ['label', 'Pengabdian Kepada Masyarakat:'],
    ['item', '1', 'Pelaksanaan Pengabdian Masyarakat', ['b_pkm']],
    ['jumlah', ['b_pkm']],
    ['label', 'Investasi Sarana:'],
    ['item', '1', 'Pengadaan Sarana Pembelajaran', ['b_sarbel']],
    ['item', '2', 'Pengadaan Sarana Kantor', ['b_sarkan']],
    ['jumlah', ['b_sarbel', 'b_sarkan']],
    ['label', 'Investasi Prasarana:'],
    ['item', '1', 'Pengadaan Prasarana', ['b_prasarana']],
    ['jumlah', ['b_prasarana']],
    ['label', 'Investasi SDM:'],
    ['item', '1', 'Peningkatan Kualitas Sumber Daya Pelayanan', ['b_sdm']],
    ['item', '2', 'Peningkatan Kesejahteraan Mahasiswa', ['b_kesmhs']],
    ['jumlah', ['b_sdm', 'b_kesmhs']],
    ['total', 'Total Anggaran Belanja Non Rutin', RAPB_KELOMPOK_TOTAL['nonrutin']],
];

const LKPS_BARIS = [
    ['judul', '1', 'Biaya Operasional Pendidikan'],
    ['item', 'a', 'a. Biaya Dosen (Gaji, Honor)'],
    ['item', 'b', 'b. Biaya Tenaga Kependidikan (Gaji, Honor)'],
    ['item', 'c', 'c. Biaya Operasional Pembelajaran (Bahan dan Peralatan Habis Pakai)'],
    ['item', 'd', 'd. Biaya Operasional Tidak Langsung (Listrik, Gas, Air, Pemeliharaan Gedung, Pemeliharaan Sarana, Uang Lembur, Telekomunikasi, Konsumsi, Transport Lokal, Pajak, Asuransi, dll.)'],
    ['item', 'e', 'e. Biaya Operasional Pendidikan diluar Perguruan Tinggi (Praktik Keinsinyuran, dll)*'],
    ['item', 'f', 'f. Biaya Investasi (SDM, Sarana dan Prasarana)'],
    ['item', 'mhs', 'Biaya operasional kemahasiswaan (penalaran, minat, bakat, dan kesejahteraan).', '2'],
    ['jumlah', 'j1', 'Jumlah', ['a', 'b', 'c', 'd', 'e', 'f', 'mhs']],
    ['item', 'penelitian', 'Biaya Penelitian', '3'],
    ['item', 'pkm', 'Biaya PkM', '4'],
    ['jumlah', 'j2', 'Jumlah', ['penelitian', 'pkm']],
    ['total', 'tot', 'Total', ['j1', 'j2']],
];

function rapb_ta_label(string $ta): string
{
    return str_replace('-', '/', $ta);
}

function rapb_ta_geser(string $ta, int $n): string
{
    $a = (int) substr($ta, 0, 4) + $n;
    return $a . '-' . ($a + 1);
}

function rapb_ta_sah(string $ta): bool
{
    return (bool) preg_match('/^(20\d\d)-(20\d\d)$/', $ta, $m) && (int) $m[2] === (int) $m[1] + 1;
}

function rapb_item_semua(): array
{
    static $hasil = null;
    if ($hasil !== null) {
        return $hasil;
    }
    $hasil = [];
    foreach (RAPB_PENDAPATAN as [$g, , , , $items]) {
        foreach ($items as [$kode, $nama]) {
            $hasil[$kode] = ['grup' => $g, 'nama' => $nama];
        }
    }
    foreach (RAPB_BELANJA as $b) {
        if ($b[0] === 'g') {
            foreach ($b[4] as [$kode, $nama]) {
                $hasil[$kode] = ['grup' => $b[1], 'nama' => $nama];
            }
        }
    }
    return $hasil;
}

function rapb_dir(): string
{
    return APP_ROOT . '/uploads/rapb_format';
}

function rapb_baca_json(string $nama): array
{
    $berkas = rapb_dir() . '/' . $nama;
    $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
    return is_array($isi) ? $isi : [];
}

function rapb_kunci()
{
    $dir = rapb_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return null;
    }
    $h = @fopen($dir . '/.kunci', 'c');
    if ($h && !flock($h, LOCK_EX)) {
        fclose($h);
        return null;
    }
    return $h ?: null;
}

function rapb_tulis_json(string $nama, array $data): bool
{
    $dir = rapb_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $berkas = $dir . '/' . $nama;
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    return true;
}

function rapb_data_ta(string $ta): array
{
    return rapb_ta_sah($ta) ? rapb_baca_json('rapb-' . $ta . '.json') : [];
}

function rapb_data_prodi(string $ta, string $prodi): ?array
{
    $semua = rapb_data_ta($ta);
    return isset($semua[$prodi]) && is_array($semua[$prodi]) ? $semua[$prodi] : null;
}

function rapb_simpan_prodi(string $ta, string $prodi, ?array $data): bool
{
    if (!rapb_ta_sah($ta) || !isset(RAPB_PRODI[$prodi])) {
        return false;
    }
    $semua = rapb_data_ta($ta);
    if ($data === null) {
        unset($semua[$prodi]);
    } else {
        $semua[$prodi] = $data;
    }
    return rapb_tulis_json('rapb-' . $ta . '.json', $semua);
}

function rapb_nilai_item(?array $data, string $kode): array
{
    $v = $data['nilai'][$kode] ?? [0, 0, 0];
    $jumlah = (float) ($v[0] ?? 0);
    $gasal = (float) ($v[1] ?? 0);
    $genap = (float) ($v[2] ?? 0);
    return ['jumlah' => $jumlah, 'gasal' => $gasal, 'genap' => $genap, 'realisasi' => $gasal + $genap, 'sisa' => $jumlah - $gasal - $genap];
}

function rapb_hitung_grup(?array $data): array
{
    $kosong = ['jumlah' => 0.0, 'gasal' => 0.0, 'genap' => 0.0, 'realisasi' => 0.0, 'sisa' => 0.0];
    $grup = [];
    foreach (rapb_item_semua() as $kode => $info) {
        $n = rapb_nilai_item($data, $kode);
        $grup[$info['grup']] = $grup[$info['grup']] ?? $kosong;
        foreach ($n as $k => $v) {
            $grup[$info['grup']][$k] += $v;
        }
    }
    return $grup;
}

function rapb_jumlah_grup(array $grup, array $daftar): array
{
    $hasil = ['jumlah' => 0.0, 'gasal' => 0.0, 'genap' => 0.0, 'realisasi' => 0.0, 'sisa' => 0.0];
    foreach ($daftar as $g) {
        foreach ($hasil as $k => $_) {
            $hasil[$k] += $grup[$g][$k] ?? 0;
        }
    }
    return $hasil;
}

function rapb_gabung_fakultas(string $ta, string $fakultas, string $hanyaProdi = ''): array
{
    $gabung = ['nilai' => []];
    $terisi = [];
    foreach (rapb_data_ta($ta) as $prodi => $data) {
        if ((RAPB_PRODI[$prodi]['fakultas'] ?? '') !== $fakultas || !is_array($data) || ($hanyaProdi !== '' && $prodi !== $hanyaProdi)) {
            continue;
        }
        $terisi[] = $prodi;
        foreach ((array) ($data['nilai'] ?? []) as $kode => $v) {
            $lama = $gabung['nilai'][$kode] ?? [0, 0, 0];
            $gabung['nilai'][$kode] = [$lama[0] + (float) ($v[0] ?? 0), $lama[1] + (float) ($v[1] ?? 0), $lama[2] + (float) ($v[2] ?? 0)];
        }
    }
    return ['data' => $gabung, 'terisi' => $terisi];
}

function rapb_rekap_manual(string $ta, string $fak, string $basis): array
{
    $semua = rapb_ta_sah($ta) ? rapb_baca_json('rekap-' . $ta . '.json') : [];
    return (array) ($semua[$fak][$basis] ?? []);
}

function rapb_rekap_simpan_manual(string $ta, string $fak, string $basis, array $nilai): bool
{
    if (!rapb_ta_sah($ta)) {
        return false;
    }
    $semua = rapb_baca_json('rekap-' . $ta . '.json');
    if ($nilai) {
        $semua[$fak][$basis] = $nilai;
    } else {
        unset($semua[$fak][$basis]);
        if (empty($semua[$fak])) {
            unset($semua[$fak]);
        }
    }
    return rapb_tulis_json('rekap-' . $ta . '.json', $semua);
}

function rapb_rekap_susun(string $ta, string $fak, string $basis, string $hanyaProdi = ''): array
{
    $gab = rapb_gabung_fakultas($ta, $fak, $hanyaProdi);
    $grup = rapb_hitung_grup($gab['data']);
    $huruf = range('A', 'Z');
    $kiri = [['bab', 'I', 'PENERIMAAN'], ['kosong']];
    $kunciKiri = [];
    foreach (RAPB_PENDAPATAN as $i => [, , , $judul, $items]) {
        $kiri[] = ['sub', $huruf[$i], $judul];
        $kunci = [];
        foreach ($items as $n => [$kode, , $label]) {
            $k = 'p' . $kode;
            $kiri[] = ['item', (string) ($n + 1), $label, $k, rapb_nilai_item($gab['data'], $kode)[$basis]];
            $kunci[] = $k;
        }
        $kiri[] = ['jumlah', 'Jumlah', $kunci];
        $kiri[] = ['kosong'];
        $kunciKiri = array_merge($kunciKiri, $kunci);
    }
    $kanan = [['bab', 'II', 'PENGELUARAN'], ['kosong']];
    $itemKanan = [];
    foreach (RAPB_REKAP_BELANJA as $r) {
        if ($r[0] === 'item') {
            $k = 'g' . implode('-', $r[3]);
            $kanan[] = ['item', $r[1], $r[2], $k, rapb_jumlah_grup($grup, $r[3])[$basis]];
            $itemKanan[$k] = $r[3];
        } elseif ($r[0] === 'jumlah' || $r[0] === 'total') {
            $daftar = $r[0] === 'jumlah' ? $r[1] : $r[2];
            $cakup = array_keys(array_filter($itemKanan, static function ($gs) use ($daftar) { return !array_diff($gs, $daftar); }));
            $kanan[] = [$r[0], $r[0] === 'jumlah' ? 'Jumlah' : $r[1], $cakup];
        } else {
            $kanan[] = $r;
        }
    }
    $hitung = [];
    foreach (array_merge($kiri, $kanan) as $r) {
        if ($r[0] === 'item') {
            $hitung[$r[3]] = (float) $r[4];
        }
    }
    return ['kiri' => $kiri, 'kanan' => $kanan, 'kunci_kiri' => $kunciKiri, 'kunci_kanan' => array_keys($itemKanan), 'hitung' => $hitung, 'terisi' => $gab['terisi']];
}

function rapb_uang(?float $n, bool $rp = false): string
{
    if ($n === null || abs($n) < 0.5) {
        return '-';
    }
    $teks = ($rp ? 'Rp ' : '') . number_format(abs($n), 0, ',', '.');
    return $n < 0 ? '(' . $teks . ')' : $teks;
}

function rapb_rp(float $n, bool $denganRp = true): string
{
    $teks = number_format(abs(round($n)), 0, ',', '.');
    if ($denganRp) {
        $teks = 'Rp ' . $teks;
    }
    return $n <= -0.5 ? '(' . $teks . ')' : $teks;
}

function rapb_tautan_sah(string $url): string
{
    $url = trim($url);
    if ($url === '' || preg_match('~[\s"\'<>`\\\\]~', $url)) {
        return '';
    }
    if (preg_match('~^[a-z][a-z0-9+.\-]*:(?!\d)~i', $url) && !preg_match('~^https?://~i', $url)) {
        return '';
    }
    if (!preg_match('~^https?://~i', $url)) {
        $url = 'https://' . $url;
    }
    return mb_strlen($url) <= 500 && filter_var($url, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $url) ? $url : '';
}

function rapb_impor_tautan(array $data): array
{
    $kenal = rapb_item_semua();
    $ganda = ['130101' => '130102', '150203' => '150204'];
    $dilihat = [];
    $hasil = [];
    foreach ($data as $sel) {
        $kode = preg_replace('/\D/', '', (string) ($sel['A'] ?? ''));
        if (strlen($kode) !== 6) {
            continue;
        }
        if (isset($dilihat[$kode]) && isset($ganda[$kode])) {
            $kode = $ganda[$kode];
        }
        $dilihat[$kode] = true;
        $url = rapb_tautan_sah((string) ($sel['H'] ?? ''));
        if ($url !== '' && isset($kenal[$kode])) {
            $hasil[$kode] = $url;
        }
    }
    return $hasil;
}

function rapb_angka_post($nilai): float
{
    $v = str_replace(['.', ' ', 'Rp', 'rp'], '', trim((string) $nilai));
    $v = str_replace(',', '.', $v);
    return is_numeric($v) ? round(max(0.0, (float) $v), 2) : 0.0;
}

function lkps_data(): array
{
    return rapb_baca_json('lkps.json');
}

function lkps_simpan(array $data): bool
{
    foreach ($data as $ta => $unit) {
        $unit = array_filter((array) $unit, static function ($v) { return is_array($v) && $v; });
        if ($unit) {
            $data[$ta] = $unit;
        } else {
            unset($data[$ta]);
        }
    }
    return rapb_tulis_json('lkps.json', $data);
}

function lkps_nilai(array $semua, string $ta, string $unit, string $baris): ?float
{
    $v = $semua[$ta][$unit][$baris] ?? null;
    return $v === null || $v === '' ? null : (float) $v;
}

function lkps_hitung(array $semua, array $daftarTa, string $unit): array
{
    $hasil = [];
    foreach (LKPS_BARIS as $b) {
        if ($b[0] === 'item') {
            foreach ($daftarTa as $i => $ta) {
                $hasil[$b[1]][$i] = lkps_nilai($semua, $ta, $unit, $b[1]);
            }
        }
    }
    foreach (LKPS_BARIS as $b) {
        if ($b[0] === 'jumlah' || $b[0] === 'total') {
            foreach ($daftarTa as $i => $_) {
                $s = 0.0;
                foreach ($b[3] as $k) {
                    $s += (float) ($hasil[$k][$i] ?? 0);
                }
                $hasil[$b[1]][$i] = $s;
            }
        }
    }
    foreach ($hasil as $k => $nilai) {
        $isi = array_filter($nilai, static function ($v) { return $v !== null; });
        $hasil[$k]['rata'] = $isi ? array_sum($isi) / count($isi) : 0.0;
    }
    $hasil['j2']['rata'] = ($hasil['penelitian']['rata'] ?? 0) + ($hasil['pkm']['rata'] ?? 0);
    $hasil['tot']['rata'] = ($hasil['j1']['rata'] ?? 0) + $hasil['j2']['rata'];
    return $hasil;
}

function rapb_xlsx_judul(array $teks, int $kolom): array
{
    $baris = [];
    $gabung = [];
    foreach (array_values(array_filter($teks, 'strlen')) as $i => $t) {
        $baris[] = [['v' => $t, 's' => $i === 0 ? XLSX_GAYA_JUDUL : XLSX_GAYA_SUBJUDUL]];
        $gabung[] = 'A' . ($i + 1) . ':' . xlsx_kolom($kolom) . ($i + 1);
    }
    $baris[] = [];
    return [$baris, $gabung];
}

function rapb_ekspor_prodi(string $ta, string $prodi): array
{
    $data = rapb_data_prodi($ta, $prodi);
    $fak = RAPB_FAKULTAS[RAPB_PRODI[$prodi]['fakultas']];
    $label = rapb_ta_label($ta);
    [$baris, $gabung] = rapb_xlsx_judul(['RENCANA ANGGARAN PENDAPATAN DAN BELANJA', 'UNIVERSITAS IBNU SINA', mb_strtoupper($fak['nama']) . ' (PRODI: ' . mb_strtoupper(RAPB_PRODI[$prodi]['nama']) . ')', 'TAHUN ANGGARAN ' . $label], 8);
    $per = $data['realisasi_per'] ?? '';
    $baris[] = array_map(static function ($t) { return ['v' => $t, 's' => XLSX_GAYA_KEPALA]; },
        ['Kode Mata Anggaran', 'Nama Mata Anggaran', 'Jumlah (Rp)', 'Realisasi' . ($per ? ' per ' . tanggal_pendek($per) : ' (Gasal + Genap)'), 'Sisa Anggaran', 'Gasal ' . $label, 'Genap ' . $label, 'Link Dokumen']);
    $barisKepala = count($baris);
    $rentangGrup = [];
    $tambahItem = static function (string $kode, string $nama) use (&$baris, $data): int {
        $n = rapb_nilai_item($data, $kode);
        $r = count($baris) + 1;
        $baris[] = [['v' => $kode, 's' => XLSX_GAYA_TENGAH], ['v' => $nama, 's' => XLSX_GAYA_TEKS], ['v' => $n['jumlah'], 's' => XLSX_GAYA_ANGKA],
            ['f' => "F$r+G$r", 'v' => $n['realisasi'], 's' => XLSX_GAYA_RUMUS], ['f' => "C$r-D$r", 'v' => $n['sisa'], 's' => XLSX_GAYA_RUMUS],
            ['v' => $n['gasal'], 's' => XLSX_GAYA_ANGKA], ['v' => $n['genap'], 's' => XLSX_GAYA_ANGKA], ['v' => (string) ($data['tautan'][$kode] ?? ''), 's' => XLSX_GAYA_TEKS]];
        return $r;
    };
    $grup = rapb_hitung_grup($data);
    $tambahTotal = static function (string $label, array $daftar, bool $besar) use (&$baris, &$rentangGrup, $grup) {
        $r = count($baris) + 1;
        $t = rapb_jumlah_grup($grup, $daftar);
        $sel = [['v' => $label, 's' => $besar ? XLSX_GAYA_TEKS_TOTAL : XLSX_GAYA_TEKS_TEBAL], ['v' => '', 's' => $besar ? XLSX_GAYA_TEKS_TOTAL : XLSX_GAYA_TEKS_TEBAL]];
        foreach (['C' => 'jumlah', 'D' => 'realisasi', 'E' => 'sisa', 'F' => 'gasal', 'G' => 'genap'] as $kol => $k) {
            $bagian = [];
            foreach ($daftar as $g) {
                if (isset($rentangGrup[$g])) {
                    $bagian[] = $kol . $rentangGrup[$g][0] . ':' . $kol . $rentangGrup[$g][1];
                }
            }
            $sel[] = ['f' => $bagian ? 'SUM(' . implode(',', $bagian) . ')' : '0', 'v' => $t[$k], 's' => $besar ? XLSX_GAYA_ANGKA_TOTAL : XLSX_GAYA_ANGKA_TEBAL];
        }
        $baris[] = $sel;
        return $r;
    };
    $gabungTotal = [];
    $isiGrup = static function (int $idx, string $g, int $awal, int $akhir) use (&$baris, $grup): void {
        $t = rapb_jumlah_grup($grup, [$g]);
        foreach (['C' => 'jumlah', 'D' => 'realisasi', 'E' => 'sisa', 'F' => 'gasal', 'G' => 'genap'] as $kol => $k) {
            $baris[$idx][] = ['f' => "SUM($kol$awal:$kol$akhir)", 'v' => $t[$k], 's' => XLSX_GAYA_ANGKA_TEBAL];
        }
    };
    $baris[] = [['v' => 'PENDAPATAN', 's' => XLSX_GAYA_TEKS_TEBAL]];
    $gabungTotal[] = 'A' . count($baris) . ':H' . count($baris);
    foreach (RAPB_PENDAPATAN as [$g, $kodeG, $namaG, , $items]) {
        $idxGrup = count($baris);
        $baris[] = [['v' => $kodeG, 's' => XLSX_GAYA_TENGAH], ['v' => $namaG, 's' => XLSX_GAYA_TEKS_TEBAL]];
        $awal = null;
        foreach ($items as [$kode, $nama]) {
            $r = $tambahItem($kode, $nama);
            $awal = $awal ?? $r;
            $akhir = $r;
        }
        $rentangGrup[$g] = [$awal, $akhir];
        $isiGrup($idxGrup, $g, $awal, $akhir);
    }
    $r = $tambahTotal('TOTAL PENDAPATAN', RAPB_KELOMPOK_TOTAL['pendapatan'], true);
    $gabungTotal[] = "A$r:B$r";
    $baris[] = [['v' => 'BELANJA', 's' => XLSX_GAYA_TEKS_TEBAL]];
    $gabungTotal[] = 'A' . count($baris) . ':H' . count($baris);
    foreach (RAPB_BELANJA as $b) {
        if ($b[0] === 'h') {
            $baris[] = [['v' => $b[1], 's' => XLSX_GAYA_TENGAH], ['v' => $b[2], 's' => XLSX_GAYA_TEKS_TEBAL]];
        } elseif ($b[0] === 'g') {
            $idxGrup = count($baris);
            $baris[] = [['v' => $b[2], 's' => XLSX_GAYA_TENGAH], ['v' => $b[3], 's' => XLSX_GAYA_TEKS_TEBAL]];
            $awal = null;
            foreach ($b[4] as [$kode, $nama]) {
                $r = $tambahItem($kode, $nama);
                $awal = $awal ?? $r;
                $akhir = $r;
            }
            $rentangGrup[$b[1]] = [$awal, $akhir];
            $isiGrup($idxGrup, $b[1], $awal, $akhir);
        } else {
            $r = $tambahTotal($b[1], RAPB_KELOMPOK_TOTAL[$b[2]], $b[0] === 'T');
            $gabungTotal[] = "A$r:B$r";
        }
    }
    $akhirTabel = count($baris) - 1;
    $baris[] = [];
    foreach (['Batam, ..............................', $fak['nama'], 'Dekan,', '', '', '', $fak['dekan'], 'NUPTK. ' . $fak['nuptk']] as $teks) {
        $baris[] = [null, null, null, null, null, ['v' => $teks, 's' => $teks === $fak['dekan'] ? XLSX_GAYA_SUBJUDUL : XLSX_GAYA_BIASA]];
    }
    return ['nama' => 'RAPB', 'baris' => $baris, 'gabung' => array_merge($gabung, $gabungTotal), 'bekukan' => $barisKepala, 'grid' => [$barisKepala - 1, $akhirTabel, 8], 'judul_cetak' => [$barisKepala, $barisKepala],
            'lebar' => ['A' => 14, 'B' => 58, 'C' => 20, 'D' => 20, 'E' => 20, 'F' => 20, 'G' => 20, 'H' => 44], 'tinggi' => [$barisKepala - 1 => 30]];
}

function rapb_impor_prodi(array $data): array
{
    $kenal = rapb_item_semua();
    $ganda = ['130101' => '130102', '150203' => '150204'];
    $dilihat = [];
    $nilai = [];
    foreach ($data as $sel) {
        $kode = preg_replace('/\D/', '', (string) ($sel['A'] ?? ''));
        if (strlen($kode) !== 6) {
            continue;
        }
        if (isset($dilihat[$kode]) && isset($ganda[$kode])) {
            $kode = $ganda[$kode];
        }
        $dilihat[$kode] = true;
        if (!isset($kenal[$kode])) {
            continue;
        }
        $j = (float) (xlsx_angka($sel['C'] ?? null) ?? 0);
        $ga = (float) (xlsx_angka($sel['F'] ?? null) ?? 0);
        $ge = (float) (xlsx_angka($sel['G'] ?? null) ?? 0);
        $nilai[$kode] = [max(0.0, $j), max(0.0, $ga), max(0.0, $ge)];
    }
    return $nilai;
}

function rapb_ekspor_rekap(string $ta, string $fak, string $basis, array $sus, array $nilai, string $prodi = ''): array
{
    $fakInfo = RAPB_FAKULTAS[$fak];
    [$baris, $gabung] = rapb_xlsx_judul(['RENCANA ANGGARAN PENDAPATAN DAN BELANJA', mb_strtoupper($fakInfo['nama']) . ($prodi !== '' ? ' (PRODI: ' . mb_strtoupper(RAPB_PRODI[$prodi]['nama']) . ')' : ''), 'TAHUN ANGGARAN ' . rapb_ta_label($ta),
        $basis === 'realisasi' ? '(BERDASARKAN REALISASI)' : ''], 6);
    $baris[] = array_map(static function ($t) { return ['v' => $t, 's' => XLSX_GAYA_KEPALA]; }, ['No.', 'U R A I A N', 'JUMLAH', 'No.', 'U R A I A N', 'JUMLAH', 'kunci_kiri', 'kunci_kanan']);
    $barisKepala = count($baris);
    $mulai = count($baris) + 1;
    $maks = max(count($sus['kiri']), count($sus['kanan']));
    $posisi = ['kiri' => [], 'kanan' => []];
    for ($i = 0; $i < $maks; $i++) {
        foreach (['kiri' => 'C', 'kanan' => 'F'] as $sisi => $kol) {
            $r = $sus[$sisi][$i] ?? null;
            if ($r && $r[0] === 'item') {
                $posisi[$sisi][$r[3]] = $kol . ($mulai + $i);
            }
        }
    }
    $sisiSel = static function (?array $r, string $sisi) use ($nilai, $posisi): array {
        $kosong = [['v' => '', 's' => XLSX_GAYA_TEKS], ['v' => '', 's' => XLSX_GAYA_TEKS], ['v' => '', 's' => XLSX_GAYA_TEKS]];
        if (!$r || $r[0] === 'kosong') {
            return [$kosong, ''];
        }
        switch ($r[0]) {
            case 'bab':
            case 'sub':
                return [[['v' => $r[1], 's' => XLSX_GAYA_TEKS_TEBAL], ['v' => $r[2], 's' => XLSX_GAYA_TEKS_TEBAL], ['v' => '', 's' => XLSX_GAYA_TEKS_TEBAL]], ''];
            case 'label':
                return [[['v' => $r[1], 's' => XLSX_GAYA_TEKS], ['v' => '', 's' => XLSX_GAYA_TEKS], ['v' => '', 's' => XLSX_GAYA_TEKS]], ''];
            case 'item':
                return [[['v' => $r[1], 's' => XLSX_GAYA_TENGAH], ['v' => $r[2], 's' => XLSX_GAYA_TEKS], ['v' => (float) ($nilai[$r[3]] ?? 0), 's' => XLSX_GAYA_ANGKA]], $r[3]];
            default:
                $ref = array_values(array_filter(array_map(static function ($k) use ($posisi, $sisi) { return $posisi[$sisi][$k] ?? null; }, $r[2])));
                $tot = 0.0;
                foreach ($r[2] as $k) {
                    $tot += $nilai[$k] ?? 0;
                }
                $gaya = $r[0] === 'total' ? XLSX_GAYA_ANGKA_TOTAL : XLSX_GAYA_ANGKA_TEBAL;
                $gayaTeks = $r[0] === 'total' ? XLSX_GAYA_TEKS_TOTAL : XLSX_GAYA_TEKS_KANAN;
                return [[['v' => $r[1], 's' => $gayaTeks], ['v' => '', 's' => $gayaTeks],
                    ['f' => $ref ? 'SUM(' . implode(',', $ref) . ')' : '0', 'v' => $tot, 's' => $gaya]], ''];
        }
    };
    for ($i = 0; $i < $maks; $i++) {
        $r = count($baris) + 1;
        foreach (['kiri' => ['A', 'B', 'C'], 'kanan' => ['D', 'E', 'F']] as $sisi => [$k1, $k2, $k3]) {
            $jenis = $sus[$sisi][$i][0] ?? 'kosong';
            if ($jenis === 'kosong') {
                $gabung[] = "$k1$r:$k3$r";
            } elseif (in_array($jenis, ['label', 'jumlah', 'total'], true)) {
                $gabung[] = "$k1$r:$k2$r";
            }
        }
        [$ki, $kk] = $sisiSel($sus['kiri'][$i] ?? null, 'kiri');
        [$ka, $kn] = $sisiSel($sus['kanan'][$i] ?? null, 'kanan');
        $baris[] = array_merge($ki, $ka, [$kk, $kn]);
    }
    $refKiri = array_values($posisi['kiri']);
    $refKanan = array_values($posisi['kanan']);
    $totKiri = array_sum(array_map(static function ($k) use ($nilai) { return $nilai[$k] ?? 0; }, $sus['kunci_kiri']));
    $totKanan = array_sum(array_map(static function ($k) use ($nilai) { return $nilai[$k] ?? 0; }, $sus['kunci_kanan']));
    $rTotal = count($baris) + 1;
    $gabung[] = "A$rTotal:B$rTotal";
    $gabung[] = "D$rTotal:E$rTotal";
    $baris[] = [['v' => 'T o t a l   P e n d a p a t a n', 's' => XLSX_GAYA_TEKS_TOTAL], ['v' => '', 's' => XLSX_GAYA_TEKS_TOTAL], ['f' => 'SUM(' . implode(',', $refKiri) . ')', 'v' => $totKiri, 's' => XLSX_GAYA_ANGKA_TOTAL],
        ['v' => 'T o t a l   B e l a n j a', 's' => XLSX_GAYA_TEKS_TOTAL], ['v' => '', 's' => XLSX_GAYA_TEKS_TOTAL], ['f' => 'SUM(' . implode(',', $refKanan) . ')', 'v' => $totKanan, 's' => XLSX_GAYA_ANGKA_TOTAL]];
    $baris[] = [];
    $r = count($baris) + 1;
    foreach (['Batam, ..............................', $fakInfo['nama'], 'Dekan,', '', '', '', $fakInfo['dekan'], 'NUPTK. ' . $fakInfo['nuptk']] as $t) {
        $baris[] = [null, null, null, null, ['v' => $t, 's' => $t === $fakInfo['dekan'] ? XLSX_GAYA_SUBJUDUL : XLSX_GAYA_BIASA]];
    }
    return ['nama' => 'REKAP RAPB UPPS', 'baris' => $baris, 'gabung' => $gabung, 'bekukan' => $barisKepala, 'sembunyi' => ['G', 'H'], 'grid' => [$barisKepala - 1, $rTotal - 1, 6], 'judul_cetak' => [$barisKepala, $barisKepala],
            'lebar' => ['A' => 6, 'B' => 46, 'C' => 20, 'D' => 6, 'E' => 46, 'F' => 20, 'G' => 12, 'H' => 12], 'tinggi' => [$barisKepala - 1 => 24]];
}

function rapb_impor_rekap(array $data, array $sus): array
{
    $label = ['kiri' => [], 'kanan' => []];
    foreach (['kiri', 'kanan'] as $sisi) {
        foreach ($sus[$sisi] as $r) {
            if ($r[0] === 'item') {
                $label[$sisi][xlsx_teks_banding($r[2])] = $r[3];
            }
        }
    }
    $sah = array_flip(array_merge($sus['kunci_kiri'], $sus['kunci_kanan']));
    $hasil = [];
    foreach ($data as $sel) {
        foreach ([['G', 'B', 'C', 'kiri'], ['H', 'E', 'F', 'kanan']] as [$kKunci, $kLabel, $kNilai, $sisi]) {
            $kunci = trim((string) ($sel[$kKunci] ?? ''));
            if (!isset($sah[$kunci])) {
                $kunci = $label[$sisi][xlsx_teks_banding((string) ($sel[$kLabel] ?? ''))] ?? '';
            }
            if ($kunci === '' || !isset($sah[$kunci])) {
                continue;
            }
            $v = xlsx_angka($sel[$kNilai] ?? null);
            $hasil[$kunci] = max(0.0, (float) ($v ?? 0));
        }
    }
    return $hasil;
}

function lkps_ekspor(string $fak, string $prodi, array $daftarTa, array $semua): array
{
    $fakInfo = RAPB_FAKULTAS[$fak];
    [$baris, $gabung] = rapb_xlsx_judul(['PENGGUNAAN DANA', mb_strtoupper($fakInfo['nama']) . ' (PRODI: ' . mb_strtoupper(RAPB_PRODI[$prodi]['nama']) . ')', 'TS = TAHUN AKADEMIK ' . rapb_ta_label($daftarTa[2])], 10);
    $k = static function ($t) { return ['v' => $t, 's' => XLSX_GAYA_KEPALA]; };
    $a = count($baris) + 1;
    $baris[] = [$k('No.'), $k('Jenis Penggunaan'), $k('Unit Pengelola Program Studi (Rupiah)'), $k(''), $k(''), $k(''), $k('Program Studi (Rupiah)'), $k(''), $k(''), $k(''), $k('kunci')];
    $sub = [$k(''), $k('')];
    for ($s = 0; $s < 2; $s++) {
        foreach (['TS-2', 'TS-1', 'TS'] as $i => $lbl) {
            $sub[] = $k($lbl . ' ' . rapb_ta_label($daftarTa[$i]));
        }
        $sub[] = $k('Rata-rata');
    }
    $baris[] = $sub;
    $baris[] = array_map($k, ['1', '2', '3', '4', '5', '6', '3', '4', '5', '6']);
    $gabung = array_merge($gabung, ["A$a:A" . ($a + 1), "B$a:B" . ($a + 1), "C$a:F$a", "G$a:J$a"]);
    $barisKepala = count($baris);
    $posisi = [];
    $nomorJudul = null;
    foreach (LKPS_BARIS as $b) {
        $r = count($baris) + 1;
        if ($b[0] === 'judul') {
            $baris[] = [['v' => $b[1], 's' => XLSX_GAYA_TENGAH], ['v' => $b[2], 's' => XLSX_GAYA_TEKS_TEBAL]];
            $gabung[] = "B$r:J$r";
            continue;
        }
        if ($b[0] === 'item') {
            $posisi[$b[1]] = $r;
            $sel = [['v' => $b[3] ?? '', 's' => XLSX_GAYA_TENGAH], ['v' => $b[2], 's' => XLSX_GAYA_TEKS]];
            foreach (['f:' . $fak => ['C', 'D', 'E', 'F'], 'p:' . $prodi => ['G', 'H', 'I', 'J']] as $unit => $kol) {
                foreach ($daftarTa as $i => $taL) {
                    $v = lkps_nilai($semua, $taL, $unit, $b[1]);
                    $sel[] = ['v' => $v === null ? '' : $v, 's' => XLSX_GAYA_ANGKA];
                }
                $sel[] = ['f' => 'IFERROR(AVERAGE(' . $kol[0] . $r . ':' . $kol[2] . $r . '),0)', 's' => XLSX_GAYA_RUMUS];
            }
            $sel[] = ['v' => $b[1], 's' => XLSX_GAYA_BIASA];
            $baris[] = $sel;
            continue;
        }
        $posisi[$b[1]] = $r;
        $gaya = $b[0] === 'total' ? [XLSX_GAYA_TEKS_TOTAL, XLSX_GAYA_ANGKA_TOTAL] : [XLSX_GAYA_TEKS_TEBAL, XLSX_GAYA_ANGKA_TEBAL];
        $sel = [['v' => $b[2], 's' => $gaya[0]], ['v' => '', 's' => $gaya[0]]];
        foreach (['C', 'D', 'E', 'F', 'G', 'H', 'I', 'J'] as $kol) {
            $ref = array_map(static function ($x) use ($posisi, $kol) { return $kol . $posisi[$x]; }, array_filter($b[3], static function ($x) use ($posisi) { return isset($posisi[$x]); }));
            $rata = in_array($kol, ['F', 'J'], true);
            $rumus = $rata && $b[1] === 'j1' ? 'IFERROR(AVERAGE(' . chr(ord($kol) - 3) . $r . ':' . chr(ord($kol) - 1) . $r . '),0)' : 'SUM(' . implode(',', $ref) . ')';
            $sel[] = ['f' => $rumus, 's' => $gaya[1]];
        }
        $baris[] = $sel;
        $gabung[] = "A$r:B$r";
    }
    return ['nama' => 'LKPS', 'baris' => $baris, 'gabung' => $gabung, 'bekukan' => $barisKepala, 'sembunyi' => ['K'], 'lanskap' => true, 'grid' => [$barisKepala - 3, count($baris) - 1, 10], 'judul_cetak' => [$barisKepala - 2, $barisKepala],
            'lebar' => ['A' => 6, 'B' => 50, 'C' => 17, 'D' => 17, 'E' => 17, 'F' => 17, 'G' => 17, 'H' => 17, 'I' => 17, 'J' => 17, 'K' => 10], 'tinggi' => [$barisKepala - 3 => 20, $barisKepala - 2 => 20, $barisKepala - 1 => 14]];
}

function lkps_impor(array $data): array
{
    $label = [];
    foreach (LKPS_BARIS as $b) {
        if ($b[0] === 'item') {
            $label[$b[1]] = substr(xlsx_teks_banding($b[2]), 0, 22);
        }
    }
    $hasil = [];
    foreach ($data as $sel) {
        $kunci = trim((string) ($sel['K'] ?? ''));
        if (!isset($label[$kunci])) {
            $teks = substr(xlsx_teks_banding((string) ($sel['B'] ?? '')), 0, 22);
            $kunci = $teks === '' ? '' : (string) array_search($teks, $label, true);
        }
        if ($kunci === '' || !isset($label[$kunci])) {
            continue;
        }
        foreach (['upps' => ['C', 'D', 'E'], 'ps' => ['G', 'H', 'I']] as $sisi => $kol) {
            foreach ($kol as $i => $c) {
                $hasil[$sisi][$kunci][$i] = xlsx_angka($sel[$c] ?? null);
            }
        }
    }
    return $hasil;
}

function rapb_kop(string $judul, array $baris): string
{
    $o = '<header class="kop-surat"><img class="kop-logo" src="' . e(base_url('assets/img/kop-logo.png')) . '" alt="Logo UIS">'
       . '<div class="kop-teks"><div class="kop-yayasan">YAYASAN PENDIDIKAN IBNU SINA BATAM (YAPISTA)</div>'
       . '<div class="kop-univ">UNIVERSITAS IBNU SINA (UIS)</div>'
       . '<div class="kop-alamat">Jalan Teuku Umar, Lubuk Baja, Kota Batam-Indonesia Telp. 0778 &ndash; 408 3113</div>'
       . '<div class="kop-alamat">email : info@uis.ac.id / uibnusina@gmail.com &nbsp; website : uis.ac.id</div></div></header>'
       . '<div class="kop-garis"></div><div class="rapb-judul-lembar"><b>' . e($judul) . '</b>';
    foreach (array_filter($baris, 'strlen') as $b) {
        $o .= '<span>' . e($b) . '</span>';
    }
    return $o . '</div>';
}

function rapb_subnav(string $aktif): string
{
    $o = '<div class="aset-bilah rapb-subbilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav rapb-subnav" aria-label="Sub menu RAPB">';
    foreach (['rapb_prodi' => ['RAPB Program Studi', 'dokumen'], 'rapb_upps' => ['Rekap RAPB UPPS', 'rekap'], 'rapb_lkps' => ['LKPS Penggunaan Dana', 'acuan']] as $h => [$label, $ikon]) {
        $o .= '<div class="aset-grup"><a href="' . e(url($h)) . '"' . ($h === $aktif ? ' class="aktif" aria-current="page"' : '') . '>' . ikon($ikon, 15) . ' ' . e($label) . '</a></div>';
    }
    return $o . '</nav></div>';
}
