<?php

const KK_HALAMAN = ['aset_kk_rekap' => 'Monitoring CS', 'aset_kk_ruang' => 'Kebersihan Ruangan', 'aset_kk_ac' => 'Pemeliharaan AC', 'aset_kk_sarana' => 'Inventaris Sarana'];

const KK_HALAMAN_CS = ['aset_kk_ruang', 'aset_kk_ac', 'aset_kk_sarana', 'aset_kk_rekap', 'aset_kebersihan', 'aset_sarana', 'aset_prasarana', 'aset_pindai'];

const KK_NAV_CS = [
    'Kartu Kontrol' => ['aset_kk_ruang' => 'Kebersihan Ruangan', 'aset_kk_ac' => 'Pemeliharaan AC', 'aset_kk_sarana' => 'Inventaris Sarana', 'aset_kk_rekap' => 'Pekerjaan Saya'],
    'Lapor Kerusakan' => ['aset_sarana' => 'Kerusakan Sarana', 'aset_prasarana' => 'Kerusakan Prasarana'],
    'Pindai QR'     => ['aset_pindai' => 'Pindai QR'],
];

const KK_URAIAN_RUANG = [
    1 => 'Kebersihan dan Kerapian Ruangan',
    2 => 'Kebersihan dan Kerapian Perabotan',
    3 => 'Kebersihan dan Kerapian Papan Tulis',
    4 => 'Kerapian Penataan Meja',
    5 => 'Kerapian Penataan Kursi',
    6 => 'Kerapian Penataan Lemari',
    7 => 'Tersedia & Bersihnya Tempat Sampah',
    8 => 'Kebersihan Jendela dan Ventilasi',
    9 => 'Kebersihan Langit-langit',
];

const KK_NILAI = ['A' => 'Sangat Memuaskan', 'B' => 'Memuaskan', 'C' => 'Tidak Memuaskan'];

const KK_KOLOM_SARANA = ['q' => 'QTY', 'k' => 'Kondisi (B/R)', 'p' => 'Dipinjam', 'm' => 'Mutasi', 'u' => 'User', 's' => 'Sign'];

const KK_ROMAWI = [1 => 'I', 2 => 'II', 3 => 'III', 4 => 'IV'];

function kk_boleh(): bool
{
    return uid() > 0 && (can('modul_aset') || can('aset_kontrol'));
}

function kk_pengelola(): bool
{
    return uid() > 0 && can('modul_aset');
}

function kk_boleh_tugaskan(): bool
{
    $u = user();
    if (uid() <= 0 || !$u) {
        return false;
    }
    if ((string) ($u['peran_kode'] ?? '') === 'admin') {
        return true;
    }
    return (string) ($u['peran_kode'] ?? '') === 'kabid'
        && (mb_stripos((string) ($u['jabatan'] ?? ''), 'sarana') !== false || mb_stripos((string) ($u['unit_nama'] ?? ''), 'sarana') !== false);
}

function wajib_kk(): void
{
    wajib_masuk();
    if (!kk_boleh()) {
        tolak_akses();
    }
}

function kk_baca(string $jenis, int $tahun): array
{
    return aset_publik_baca('kk-' . preg_replace('/[^a-z]/', '', $jenis) . '-' . $tahun . '.json');
}

function kk_ubah(string $jenis, int $tahun, callable $ubah)
{
    return aset_publik_ubah('kk-' . preg_replace('/[^a-z]/', '', $jenis) . '-' . $tahun . '.json', $ubah);
}

function kk_minggu_ini(): int
{
    return min(4, (int) ceil((int) date('j') / 7));
}

function kk_tanda(): array
{
    return ['id' => uid(), 'nama' => (string) (user()['nama'] ?? ''), 'waktu' => date('Y-m-d H:i:s')];
}

function kk_waktu(?array $t): string
{
    if (!$t || empty($t['waktu'])) {
        return '';
    }
    return tanggal_pendek(substr($t['waktu'], 0, 10)) . ' ' . substr($t['waktu'], 11, 5);
}

function kk_navigasi(string $aktif): string
{
    $h = '<div class="aset-bilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav" aria-label="Menu petugas CS">';
    $sub = [];
    foreach (KK_NAV_CS as $label => $halaman) {
        $dalam = array_key_exists($aktif, $halaman);
        $h .= '<div class="aset-grup' . (count($halaman) > 1 ? ' ada-menu' : '') . '"><a href="' . e(url((string) array_key_first($halaman))) . '"' . ($dalam ? ' class="aktif" aria-current="page"' : '') . '>' . e($label) . '</a>';
        if (count($halaman) > 1) {
            $h .= '<div class="aset-menu">';
            foreach ($halaman as $kunci => $nama) {
                $h .= aset_tautan_menu($kunci, $nama, $aktif);
            }
            $h .= '</div>';
            if ($dalam) {
                $sub = $halaman;
            }
        }
        $h .= '</div>';
    }
    $h .= '</nav>';
    if ($sub) {
        $h .= '<nav class="aset-sub" aria-label="Halaman grup">';
        foreach ($sub as $kunci => $nama) {
            $h .= '<a href="' . e(url($kunci)) . '"' . ($kunci === $aktif ? ' class="aktif"' : '') . '>' . e($nama) . '</a>';
        }
        $h .= '</nav>';
    }
    return $h . '</div>';
}

function kk_kop(): string
{
    return '<header class="kop-surat"><img class="kop-logo" src="' . e(base_url('assets/img/kop-logo.png')) . '" alt="Logo UIS"><div class="kop-teks">'
        . '<div class="kop-yayasan">YAYASAN PENDIDIKAN IBNU SINA BATAM (YAPISTA)</div><div class="kop-univ">UNIVERSITAS IBNU SINA (UIS)</div>'
        . '<div class="kop-alamat">Jalan Teuku Umar, Lubuk Baja, Kota Batam-Indonesia Telp. 0778 &ndash; 408 3113</div>'
        . '<div class="kop-alamat">Email : info@uis.ac.id / uibnusina@gmail.com &nbsp; Website : uis.ac.id</div></div></header><div class="kop-garis"></div>';
}

function kk_ttd(): string
{
    return '<div class="kk-ttd"><div>' . e(APP_KOTA) . ', ' . e(tanggal_id(date('Y-m-d'))) . '</div><div>Ka. Bid Sarpras</div><div class="ruang"></div>'
        . '<b class="ttd-nama">' . e(ASET_KABID['nama']) . '</b><div>NUP. ' . e(ASET_KABID['nup']) . '</div></div>';
}

function kk_unit_ac(): array
{
    return rows("SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id
                  WHERE b.kategori = 'ac' AND b.status = 'aktif' ORDER BY r.nama, b.nama, b.kode");
}

function kk_barang_ruang(int $ruangId, array $tambahan = []): array
{
    $hasil = [];
    foreach (rows("SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id
                    WHERE b.ruang_id = ? AND b.status = 'aktif' ORDER BY b.nama, b.kode", [$ruangId]) as $b) {
        $hasil[(int) $b['id']] = $b + ['pindah' => false];
    }
    $sisa = array_values(array_diff(array_map('intval', $tambahan), array_keys($hasil)));
    if ($sisa) {
        foreach (rows('SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE b.id IN (' . in_placeholder($sisa) . ') ORDER BY b.nama', $sisa) as $b) {
            $hasil[(int) $b['id']] = $b + ['pindah' => true];
        }
    }
    return $hasil;
}

function kk_saran_sarana(array $barang, int $ruangId, int $tahun, int $bulan): array
{
    $hasil = [];
    if (!$barang) {
        return $hasil;
    }
    $ids = array_keys($barang);
    $pinjam = [];
    foreach (rows("SELECT barang_id, SUM(jumlah) AS n FROM aset_mutasi WHERE jenis = 'pinjam' AND status = 'dipinjam' AND barang_id IN (" . in_placeholder($ids) . ') GROUP BY barang_id', $ids) as $r) {
        $pinjam[(int) $r['barang_id']] = (int) $r['n'];
    }
    $mutasi = [];
    $awal = sprintf('%04d-%02d-01', $tahun, $bulan);
    $akhir = date('Y-m-t', strtotime($awal));
    foreach (rows("SELECT m.*, dr.nama AS dari_nama, kr.nama AS ke_nama FROM aset_mutasi m LEFT JOIN aset_ruang dr ON dr.id = m.dari_ruang_id LEFT JOIN aset_ruang kr ON kr.id = m.ke_ruang_id
                    WHERE m.jenis = 'pindah' AND m.tanggal BETWEEN ? AND ? AND m.barang_id IN (" . in_placeholder($ids) . ') ORDER BY m.tanggal, m.id', array_merge([$awal, $akhir], $ids)) as $m) {
        $mutasi[(int) $m['barang_id']] = (int) $m['ke_ruang_id'] === $ruangId ? 'Masuk dari ' . ($m['dari_nama'] ?: '-') : 'Keluar ke ' . ($m['ke_nama'] ?: '-');
    }
    foreach ($barang as $id => $b) {
        $hasil[$id] = [
            'q' => (string) (int) $b['jumlah'],
            'k' => $b['kondisi'] === 'baik' ? 'B' : 'R',
            'p' => isset($pinjam[$id]) ? (string) $pinjam[$id] : '0',
            'm' => $mutasi[$id] ?? '',
            'u' => '',
        ];
    }
    return $hasil;
}

function kk_tab_bulan(string $h, int $tahun, int $bulan, array $param, array $kontrol): string
{
    $o = '<nav class="kk-bulan no-cetak" aria-label="Pilih bulan">';
    for ($b = 1; $b <= 12; $b++) {
        $o .= '<a href="' . e(url($h, $param + ['tahun' => $tahun, 'bulan' => $b])) . '" class="' . ($b === $bulan ? 'aktif' : '') . (!empty($kontrol[(string) $b]) ? ' ok' : '') . '"'
            . (!empty($kontrol[(string) $b]) ? ' title="Sudah diparaf"' : '') . '>' . e(mb_substr(NAMA_BULAN[$b], 0, 3)) . '</a>';
    }
    return $o . '</nav>';
}

function kk_panel_kontrol(string $h, bool $pengelola, ?array $kontrol, array $param): string
{
    $tersembunyi = csrf_field();
    foreach ($param as $k => $v) {
        $tersembunyi .= '<input type="hidden" name="' . e($k) . '" value="' . e((string) $v) . '">';
    }
    $o = '<div class="kartu kk-kontrol"><div class="kartu-kepala"><div><h2>' . ikon('centang', 18) . ' Kontrol Ka. Bid Sarpras</h2>';
    if ($kontrol) {
        $o .= '<p class="ket">Diparaf oleh <b>' . e($kontrol['nama']) . '</b> pada ' . e(kk_waktu($kontrol)) . '.</p>'
            . (($kontrol['catatan'] ?? '') !== '' ? '<p class="kk-catatan">Catatan: ' . e($kontrol['catatan']) . '</p>' : '') . '</div>';
        if ($pengelola) {
            $o .= '<div class="aksi"><form method="post" data-konfirmasi="Batalkan paraf kontrol bulan ini? Petugas dapat mengubah isian lagi.">' . $tersembunyi
                . '<input type="hidden" name="op" value="batal_kontrol"><button class="btn btn-garis btn-kecil" type="submit">' . ikon('ulang', 14) . ' Batalkan paraf</button></form></div>';
        }
        return $o . '</div></div>';
    }
    $o .= '<p class="ket">Belum diparaf.</p></div></div>';
    if ($pengelola) {
        $o .= '<form method="post" class="kartu-isi unggah-aset">' . $tersembunyi . '<input type="hidden" name="op" value="kontrol">'
            . '<div class="form-baris" style="flex:1;min-width:240px"><label class="fl">Catatan kontrol (opsional)</label><input type="text" name="catatan" maxlength="500" placeholder="Contoh: Papan tulis minggu II kurang bersih"></div>'
            . '<button class="btn btn-sukses" type="submit">' . ikon('centang', 15) . ' Paraf kontrol</button></form>';
    }
    return $o . '</div>';
}

function kk_petugas(): array
{
    static $daftar = null;
    if ($daftar === null) {
        $daftar = [];
        $izin = semua_penyesuaian_izin();
        foreach (rows("SELECT p.id, p.nama, p.jabatan, pr.kode AS peran_kode FROM pengguna p LEFT JOIN peran pr ON pr.id = p.peran_id WHERE p.aktif = 1 ORDER BY p.nama") as $p) {
            if ($p['peran_kode'] === 'cs' || !empty($izin[(string) $p['id']]['aset_kontrol']) || stripos((string) $p['jabatan'], 'cleaning') !== false) {
                $daftar[(int) $p['id']] = $p['nama'];
            }
        }
    }
    return $daftar;
}

function kk_penugasan_semua(): array
{
    $hasil = ['ruang' => [], 'sarana' => [], 'ac' => []];
    foreach (aset_publik_baca('kk-penugasan.json') as $kunci => $isi) {
        if (isset($hasil[$kunci]) && is_array($isi)) {
            foreach ($isi as $id => $ids) {
                $hasil[$kunci][(int) $id] = array_values(array_map('intval', (array) $ids));
            }
        } elseif (ctype_digit((string) $kunci)) {
            $hasil['ruang'][(int) $kunci] = array_values(array_map('intval', (array) $isi));
        }
    }
    return $hasil;
}

function kk_penugasan(string $jenis = 'ruang'): array
{
    return kk_penugasan_semua()[$jenis] ?? [];
}

function kk_simpan_penugasan(string $jenis, int $id, array $petugas): void
{
    aset_publik_ubah('kk-penugasan.json', static function (array &$d) use ($jenis, $id, $petugas): void {
        foreach (array_keys($d) as $k) {
            if (ctype_digit((string) $k)) {
                $d['ruang'][(string) $k] = $d[$k];
                unset($d[$k]);
            }
        }
        if ($petugas) {
            $d[$jenis][(string) $id] = $petugas;
        } else {
            unset($d[$jenis][(string) $id]);
        }
    });
}

function kk_nama_petugas(array $ids): string
{
    $petugas = kk_petugas();
    $nama = [];
    foreach ($ids as $id) {
        if (isset($petugas[$id])) {
            $nama[] = $petugas[$id];
        }
    }
    return implode(', ', $nama);
}

function kk_pj_ruang(array $ruang, array $penugasan): string
{
    $nama = [];
    $petugas = kk_petugas();
    foreach ($penugasan[(int) $ruang['id']] ?? [] as $id) {
        if (isset($petugas[$id])) {
            $nama[] = $petugas[$id];
        }
    }
    return $nama ? implode(', ', $nama) : (string) ($ruang['penanggung_jawab'] ?? '');
}

function kk_ruang_saya(array $penugasan): array
{
    $saya = uid();
    $hasil = [];
    foreach ($penugasan as $ruang => $ids) {
        if (in_array($saya, $ids, true)) {
            $hasil[] = $ruang;
        }
    }
    return $hasil;
}

function kk_tanggal_excel(string $v): string
{
    $v = trim($v);
    if ($v === '') {
        return '';
    }
    if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
        return date('Y-m-d', (int) round(((float) $v - 25569) * 86400));
    }
    if (preg_match('#^(\d{1,2})[/\-.](\d{1,2})[/\-.](\d{2,4})$#', $v, $m)) {
        $th = (int) $m[3] < 100 ? 2000 + (int) $m[3] : (int) $m[3];
        return checkdate((int) $m[2], (int) $m[1], $th) ? sprintf('%04d-%02d-%02d', $th, $m[2], $m[1]) : '';
    }
    return tanggal_sah($v) ? $v : '';
}

function kk_kolom_minggu(int $bulan, int $minggu): string
{
    return xlsx_kolom(5 + ($bulan - 1) * 4 + ($minggu - 1));
}

function kk_kolom_sarana(int $bulan, int $urut): string
{
    return xlsx_kolom(6 + ($bulan - 1) * 6 + $urut);
}

function kk_kepala_excel(string $judul1, string $judul2 = ''): array
{
    $b = [];
    $b[0] = [0 => ['v' => 'YAYASAN PENDIDIKAN IBNU SINA BATAM (YAPISTA)', 's' => XLSX_GAYA_SUBJUDUL]];
    $b[1] = [0 => ['v' => 'UNIVERSITAS IBNU SINA (UIS)', 's' => XLSX_GAYA_JUDUL]];
    $b[2] = [0 => 'Jalan Teuku Umar, Lubuk Baja, Kota Batam-Indonesia Telp. 0778 – 408 3113'];
    $b[3] = [0 => ' Email : info@uis.ac.id / uibnusina@gmail.com'];
    $b[4] = [0 => 'Website : uis.ac.id'];
    $b[6] = [0 => ['v' => $judul1, 's' => XLSX_GAYA_JUDUL]];
    if ($judul2 !== '') {
        $b[7] = [0 => ['v' => $judul2, 's' => XLSX_GAYA_JUDUL]];
    }
    return $b;
}

function kk_ttd_excel(array &$b, int $baris, int $kolom): void
{
    $b[$baris][$kolom] = APP_KOTA . ', ' . tanggal_id(date('Y-m-d'));
    $b[$baris + 1][$kolom] = 'Ka. Bid Sarpras';
    $b[$baris + 5][$kolom] = ['v' => ASET_KABID['nama'], 's' => XLSX_GAYA_TEKS_TEBAL];
    $b[$baris + 6][$kolom] = 'Nup. ' . ASET_KABID['nup'];
}

function kk_ekspor_ruang(array $ruang, string $pj, int $tahun, array $d): array
{
    $b = kk_kepala_excel('Kartu Kontrol');
    $b[7] = [0 => 'Ruangan', 2 => ':', 3 => $ruang['nama']];
    $b[8] = [0 => 'PJ Ruangan', 2 => ':', 3 => $pj];
    $b[9] = [0 => 'Tahun', 2 => ':', 3 => (string) $tahun];
    $k = XLSX_GAYA_KEPALA;
    $b[11] = [0 => ['v' => 'No', 's' => $k], 1 => ['v' => 'Uraian', 's' => $k], 4 => ['v' => 'Bulan', 's' => $k]];
    $gabung = ['A12:A14', 'B12:D14', 'E12:AZ12'];
    for ($bl = 1; $bl <= 12; $bl++) {
        $awal = 4 + ($bl - 1) * 4;
        $b[12][$awal] = ['v' => NAMA_BULAN[$bl], 's' => $k];
        $gabung[] = xlsx_kolom($awal + 1) . '13:' . xlsx_kolom($awal + 4) . '13';
        foreach (KK_ROMAWI as $m => $rm) {
            $b[13][$awal + $m - 1] = ['v' => $rm, 's' => $k];
        }
    }
    foreach (KK_URAIAN_RUANG as $no => $uraian) {
        $r = 13 + $no;
        $b[$r][0] = ['v' => $no, 's' => XLSX_GAYA_TENGAH];
        $b[$r][1] = ['v' => $uraian, 's' => XLSX_GAYA_TEKS];
        $gabung[] = 'B' . ($r + 1) . ':D' . ($r + 1);
        for ($bl = 1; $bl <= 12; $bl++) {
            for ($m = 1; $m <= 4; $m++) {
                $b[$r][4 + ($bl - 1) * 4 + $m - 1] = ['v' => (string) ($d['nilai'][(string) $no][$bl . '-' . $m] ?? ''), 's' => XLSX_GAYA_TENGAH];
            }
        }
    }
    $b[24] = [0 => ['v' => 'Indikator Nilai ', 's' => XLSX_GAYA_TEKS_TEBAL]];
    $i = 25;
    foreach (KK_NILAI as $kn => $ket) {
        $b[$i++] = [1 => $kn, 2 => ':', 3 => $ket];
    }
    kk_ttd_excel($b, 24, 36);
    ksort($b);
    $lebar = ['A' => 4, 'B' => 12, 'C' => 2, 'D' => 18];
    for ($c = 5; $c <= 52; $c++) {
        $lebar[xlsx_kolom($c)] = 3.6;
    }
    return ['nama' => 'KARTU KONTROL RUANGAN', 'baris' => $b, 'lebar' => $lebar, 'gabung' => $gabung, 'lanskap' => true, 'grid' => [11, 22, 52]];
}

function kk_ekspor_sarana(array $ruang, string $pj, int $tahun, array $d, array $barang): array
{
    $b = kk_kepala_excel('KARTU KONTROL', 'INVENTARIS SARANA');
    $b[9] = [0 => 'Ruangan', 2 => ':', 3 => $ruang['nama']];
    $b[10] = [0 => 'PJ Ruangan', 2 => ':', 3 => $pj];
    $b[11] = [0 => 'Tahun', 2 => ':', 3 => (string) $tahun];
    $k = XLSX_GAYA_KEPALA;
    $b[13] = [0 => ['v' => 'No', 's' => $k], 1 => ['v' => 'NAMA SARANA', 's' => $k], 4 => ['v' => 'CODE', 's' => $k], 5 => ['v' => 'Bulan', 's' => $k]];
    $gabung = ['A14:A16', 'B14:D16', 'E14:E16', 'F14:BY14'];
    for ($bl = 1; $bl <= 12; $bl++) {
        $awal = 5 + ($bl - 1) * 6;
        $b[14][$awal] = ['v' => NAMA_BULAN[$bl], 's' => $k];
        $gabung[] = xlsx_kolom($awal + 1) . '15:' . xlsx_kolom($awal + 6) . '15';
        foreach (array_keys(KK_KOLOM_SARANA) as $u => $kk) {
            $b[15][$awal + $u] = ['v' => strtoupper($kk), 's' => $k];
        }
    }
    $r = 16;
    $no = 0;
    foreach ($barang as $id => $brg) {
        $no++;
        $b[$r][0] = ['v' => $no, 's' => XLSX_GAYA_TENGAH];
        $b[$r][1] = ['v' => $brg['nama'], 's' => XLSX_GAYA_TEKS];
        $b[$r][4] = ['v' => $brg['kode'], 's' => XLSX_GAYA_TEKS];
        $gabung[] = 'B' . ($r + 1) . ':D' . ($r + 1);
        for ($bl = 1; $bl <= 12; $bl++) {
            $isi = $d['isi'][(string) $id][(string) $bl] ?? [];
            foreach (array_keys(KK_KOLOM_SARANA) as $u => $kk) {
                $v = $kk === 's' ? (!empty($isi['s']['nama']) ? kk_inisial($isi['s']['nama']) : '') : (string) ($isi[$kk] ?? '');
                $b[$r][5 + ($bl - 1) * 6 + $u] = ['v' => $v, 's' => XLSX_GAYA_TENGAH];
            }
        }
        $r++;
    }
    $akhirGrid = max($r - 1, 16);
    $r = max($r + 1, 29);
    $b[$r] = [0 => ['v' => 'KETERANGAN', 's' => XLSX_GAYA_TEKS_TEBAL]];
    $i = $r + 1;
    foreach (KK_KOLOM_SARANA as $kk => $ket) {
        $b[$i++] = [1 => strtoupper($kk), 2 => ':', 3 => mb_strtoupper($ket)];
    }
    kk_ttd_excel($b, $r, 59);
    ksort($b);
    $lebar = ['A' => 4, 'B' => 12, 'C' => 2, 'D' => 14, 'E' => 13];
    for ($c = 6; $c <= 77; $c++) {
        $lebar[xlsx_kolom($c)] = 3.2;
    }
    return ['nama' => 'KARTU KONTROL SARANA', 'baris' => $b, 'lebar' => $lebar, 'gabung' => $gabung, 'lanskap' => true, 'grid' => [13, $akhirGrid, 77]];
}

function kk_ekspor_ac(array $unit, int $tahun, array $d): array
{
    $b = kk_kepala_excel('KARTU PEMELIHARAAN', 'AIR CONDITIONER TAHUN ' . $tahun);
    $kepala = [['MITRA', $d['mitra'] ?? ''], ['JENIS AC', $d['jenis_ac'] ?? ''], ['MERK', $unit['merek']], ['TYPE', $unit['model']],
               ['KAPASITAS', $d['kapasitas'] ?? ''], ['NO SERI', $unit['no_seri']], ['RUANGAN', $unit['ruang_nama'] ?? '']];
    foreach ($kepala as $i => [$label, $nilai]) {
        $b[10 + $i] = [0 => $label, 2 => ':', 3 => (string) $nilai];
    }
    $k = XLSX_GAYA_KEPALA;
    $b[18] = [0 => ['v' => 'NO', 's' => $k], 1 => ['v' => 'TGL', 's' => $k], 4 => ['v' => 'DESCRIPTION', 's' => $k], 5 => ['v' => 'PENGECEKAN', 's' => $k], 8 => ['v' => 'REMAKS', 's' => $k]];
    $b[19] = [5 => ['v' => 'ARUS', 's' => $k], 6 => ['v' => 'TEGANGAN', 's' => $k], 7 => ['v' => 'TEKANAN', 's' => $k]];
    $gabung = ['A19:A20', 'B19:D20', 'E19:E20', 'F19:H19', 'I19:L20'];
    $baris = (array) ($d['baris'] ?? []);
    $total = max(12, count($baris));
    for ($i = 0; $i < $total; $i++) {
        $r = 20 + $i;
        $x = $baris[$i] ?? null;
        $b[$r] = [0 => ['v' => $i + 1, 's' => XLSX_GAYA_TENGAH], 1 => ['v' => $x ? date('d/m/Y', strtotime($x['tgl'])) : '', 's' => XLSX_GAYA_TENGAH],
                  4 => ['v' => $x['deskripsi'] ?? '', 's' => XLSX_GAYA_TEKS], 5 => ['v' => $x['arus'] ?? '', 's' => XLSX_GAYA_TENGAH],
                  6 => ['v' => $x['tegangan'] ?? '', 's' => XLSX_GAYA_TENGAH], 7 => ['v' => $x['tekanan'] ?? '', 's' => XLSX_GAYA_TENGAH], 8 => ['v' => $x['remaks'] ?? '', 's' => XLSX_GAYA_TEKS]];
        $gabung[] = 'B' . ($r + 1) . ':D' . ($r + 1);
        $gabung[] = 'I' . ($r + 1) . ':L' . ($r + 1);
    }
    kk_ttd_excel($b, 20 + $total + 1, 6);
    ksort($b);
    return ['nama' => 'KARTU KONTROL AC', 'baris' => $b, 'lebar' => ['A' => 5, 'B' => 4, 'C' => 2, 'D' => 8, 'E' => 26, 'F' => 9, 'G' => 11, 'H' => 10, 'I' => 6, 'J' => 6, 'K' => 6, 'L' => 6],
            'gabung' => $gabung, 'lanskap' => false, 'grid' => [18, 19 + $total, 12]];
}

function kk_baris_angka(array $data, string $kolom, int $dari, int $sampai): array
{
    $hasil = [];
    foreach ($data as $r => $sel) {
        if ($r >= $dari && $r <= $sampai && preg_match('/^\d+$/', trim((string) ($sel[$kolom] ?? '')))) {
            $hasil[(int) trim((string) $sel[$kolom])] = $r;
        }
    }
    return $hasil;
}

function kk_cari_baris(array $data, string $kolom, string $teks): int
{
    foreach ($data as $r => $sel) {
        if (mb_strtolower(trim((string) ($sel[$kolom] ?? ''))) === mb_strtolower($teks)) {
            return (int) $r;
        }
    }
    return 0;
}

function kk_bersih_isian($v): string
{
    $v = trim((string) $v);
    return preg_match('/^[.\s:…-]*$/u', $v) ? '' : $v;
}

function kk_sama_nama(string $a, string $b): bool
{
    $n = static function (string $s): string { return preg_replace('/[^a-z0-9]/', '', mb_strtolower($s)); };
    return $n($a) === $n($b);
}

function kk_impor(string $jenis, array $data, int $tahun, int $ruangId, int $barangId, bool $pengelola, bool $kanan = false): array
{
    if ($jenis === 'ac') {
        if (!$barangId) {
            return ['galat', 'Pilih unit AC dahulu, lalu unggah kartunya.'];
        }
        $kl = $kanan ? ['A' => 'N', 'D' => 'Q', 'B' => 'O', 'E' => 'R', 'F' => 'S', 'G' => 'T', 'H' => 'U', 'I' => 'V'] : ['A' => 'A', 'D' => 'D', 'B' => 'B', 'E' => 'E', 'F' => 'F', 'G' => 'G', 'H' => 'H', 'I' => 'I'];
        $kepala = [];
        foreach (['MITRA' => 'mitra', 'JENIS AC' => 'jenis_ac', 'KAPASITAS' => 'kapasitas'] as $label => $kunci) {
            $r = kk_cari_baris($data, $kl['A'], $label);
            if ($r && ($v = kk_bersih_isian($data[$r][$kl['D']] ?? '')) !== '') {
                $kepala[$kunci] = mb_substr($v, 0, $kunci === 'mitra' ? 150 : 100);
            }
        }
        $awal = kk_cari_baris($data, $kl['E'], 'DESCRIPTION');
        if (!$awal) {
            return ['galat', 'Format kartu AC tidak dikenali. Gunakan file Kartu Kontrol AC (kolom TGL, DESCRIPTION, ARUS, TEGANGAN, TEKANAN, REMAKS).'];
        }
        $baru = [];
        $lewat = 0;
        for ($r = $awal + 2; $r <= $awal + 80; $r++) {
            $tgl = kk_tanggal_excel((string) ($data[$r][$kl['B']] ?? ''));
            $desk = kk_bersih_isian($data[$r][$kl['E']] ?? '');
            if ($tgl === '' && $desk === '') {
                continue;
            }
            if ($tgl === '' || (int) substr($tgl, 0, 4) !== $tahun || $desk === '') {
                $lewat++;
                continue;
            }
            $baru[] = ['tgl' => $tgl, 'deskripsi' => mb_substr($desk, 0, 200), 'arus' => mb_substr(kk_bersih_isian($data[$r][$kl['F']] ?? ''), 0, 30),
                       'tegangan' => mb_substr(kk_bersih_isian($data[$r][$kl['G']] ?? ''), 0, 30), 'tekanan' => mb_substr(kk_bersih_isian($data[$r][$kl['H']] ?? ''), 0, 30),
                       'remaks' => mb_substr(kk_bersih_isian($data[$r][$kl['I']] ?? ''), 0, 300)];
        }
        $tambah = kk_ubah('ac', $tahun, static function (array &$d) use ($barangId, $kepala, $baru): int {
            $u = $d[(string) $barangId] ?? [];
            $u = $kepala + $u;
            $ada = [];
            foreach ((array) ($u['baris'] ?? []) as $x) {
                $ada[$x['tgl'] . '|' . mb_strtolower($x['deskripsi'])] = true;
            }
            $n = 0;
            foreach ($baru as $x) {
                $kunci = $x['tgl'] . '|' . mb_strtolower($x['deskripsi']);
                if (isset($ada[$kunci])) {
                    continue;
                }
                $u['baris'][] = $x + ['id' => bin2hex(random_bytes(6)), 'oleh' => kk_tanda(), 'impor' => true];
                $ada[$kunci] = true;
                $n++;
            }
            if (!empty($u['baris'])) {
                usort($u['baris'], static function ($a, $b) { return strcmp($a['tgl'], $b['tgl']); });
            }
            $d[(string) $barangId] = $u;
            return $n;
        });
        return ['ok', (int) $tambah . ' pemeriksaan AC diimpor' . ($lewat ? ', ' . $lewat . ' baris dilewati (tanggal kosong/bukan tahun ' . $tahun . ' atau tanpa deskripsi)' : '') . ($kepala ? ', data mitra/jenis/kapasitas diperbarui' : '') . '. Baris yang sudah ada tidak digandakan.'];
    }

    if (!$ruangId) {
        return ['galat', 'Pilih ruangan dahulu, lalu unggah kartunya.'];
    }
    $ruang = row('SELECT * FROM aset_ruang WHERE id = ?', [$ruangId]);
    $barisRuang = kk_cari_baris($data, 'A', 'Ruangan');
    $namaFile = $barisRuang ? kk_bersih_isian($data[$barisRuang]['D'] ?? ($data[$barisRuang]['C'] ?? '')) : '';
    $namaFile = ltrim($namaFile, ': ');
    if ($namaFile !== '' && !kk_sama_nama($namaFile, $ruang['nama']) && !kk_sama_nama($namaFile, $ruang['kode'])) {
        return ['galat', 'File ini berisi kartu ruangan "' . $namaFile . '", bukan ' . $ruang['nama'] . '. Pilih ruangan yang sesuai, atau kosongkan nama ruangan di file.'];
    }

    if ($jenis === 'ruang') {
        $barisNo = kk_baris_angka($data, 'A', kk_cari_baris($data, 'B', 'Uraian') ?: 1, 200);
        $nilai = [];
        $salah = 0;
        foreach (KK_URAIAN_RUANG as $no => $_) {
            $r = $barisNo[$no] ?? 0;
            if (!$r) {
                continue;
            }
            for ($b = 1; $b <= 12; $b++) {
                for ($m = 1; $m <= 4; $m++) {
                    $v = strtoupper(trim((string) ($data[$r][kk_kolom_minggu($b, $m)] ?? '')));
                    if ($v === '') {
                        continue;
                    }
                    if (!isset(KK_NILAI[$v])) {
                        $salah++;
                        continue;
                    }
                    $nilai[$b][$m][$no] = $v;
                }
            }
        }
        if (!$nilai) {
            return ['warn', 'Tidak ada nilai A/B/C yang terbaca. Pastikan memakai format Kartu Kontrol Ruangan (nomor 1-9 di kolom A, nilai mulai kolom E).'];
        }
        $hasil = kk_ubah('ruang', $tahun, static function (array &$d) use ($ruangId, $nilai, $pengelola): array {
            $r = $d[(string) $ruangId] ?? [];
            $isi = 0;
            $kunci = 0;
            foreach ($nilai as $b => $perMinggu) {
                if (!empty($r['kontrol'][(string) $b]) && !$pengelola) {
                    $kunci++;
                    continue;
                }
                foreach ($perMinggu as $m => $perNo) {
                    $beda = false;
                    foreach ($perNo as $no => $v) {
                        if (($r['nilai'][(string) $no][$b . '-' . $m] ?? '') !== $v) {
                            $r['nilai'][(string) $no][$b . '-' . $m] = $v;
                            $beda = true;
                            $isi++;
                        }
                    }
                    if ($beda) {
                        $r['oleh'][$b . '-' . $m] = kk_tanda() + ['impor' => true];
                    }
                }
            }
            $d[(string) $ruangId] = $r;
            return [$isi, $kunci];
        });
        return ['ok', ($hasil[0] ?? 0) . ' nilai diimpor ke kartu ' . $ruang['nama'] . ' tahun ' . $tahun . '.' . (!empty($hasil[1]) ? ' ' . $hasil[1] . ' bulan dilewati karena sudah diparaf.' : '') . ($salah ? ' ' . $salah . ' sel diabaikan karena bukan A/B/C.' : '')];
    }

    $kepala = kk_cari_baris($data, 'E', 'CODE');
    if (!$kepala) {
        return ['galat', 'Format kartu inventaris sarana tidak dikenali. Gunakan file Kartu Kontrol Sarana (kolom CODE di kolom E, Q-K-P-M-U-S mulai kolom F).'];
    }
    $simpan = kk_baca('sarana', $tahun);
    $sah = kk_barang_ruang($ruangId, array_keys((array) ($simpan[(string) $ruangId]['isi'] ?? [])));
    $kodeKeId = [];
    foreach ($sah as $id => $b) {
        $kodeKeId[mb_strtolower(trim($b['kode']))] = $id;
    }
    $isian = [];
    $tidakDikenal = [];
    foreach ($data as $r => $sel) {
        if ($r <= $kepala + 2) {
            continue;
        }
        $kode = mb_strtolower(trim((string) ($sel['E'] ?? '')));
        if ($kode === '') {
            continue;
        }
        if (!isset($kodeKeId[$kode])) {
            $tidakDikenal[] = trim((string) $sel['E']);
            continue;
        }
        for ($b = 1; $b <= 12; $b++) {
            $q = trim((string) ($sel[kk_kolom_sarana($b, 0)] ?? ''));
            $k = strtoupper(trim((string) ($sel[kk_kolom_sarana($b, 1)] ?? '')));
            $p = trim((string) ($sel[kk_kolom_sarana($b, 2)] ?? ''));
            $mu = trim((string) ($sel[kk_kolom_sarana($b, 3)] ?? ''));
            $u = trim((string) ($sel[kk_kolom_sarana($b, 4)] ?? ''));
            if ($q . $k . $p . $mu . $u === '') {
                continue;
            }
            $isian[$kodeKeId[$kode]][$b] = [
                'q' => preg_match('/^\d{1,6}$/', $q) ? $q : '',
                'k' => in_array($k, ['B', 'BAIK'], true) ? 'B' : (in_array($k, ['R', 'RUSAK'], true) ? 'R' : ''),
                'p' => preg_match('/^\d{1,6}$/', $p) ? $p : '',
                'm' => mb_substr($mu, 0, 120),
                'u' => mb_substr($u, 0, 120),
            ];
        }
    }
    if (!$isian) {
        return ['warn', 'Tidak ada isian yang terbaca' . ($tidakDikenal ? '. Kode berikut tidak terdaftar di ruangan ini: ' . implode(', ', array_slice(array_unique($tidakDikenal), 0, 8)) : '') . '.'];
    }
    $hasil = kk_ubah('sarana', $tahun, static function (array &$d) use ($ruangId, $isian, $pengelola): array {
        $r = $d[(string) $ruangId] ?? [];
        $n = 0;
        $kunci = [];
        foreach ($isian as $id => $perBulan) {
            foreach ($perBulan as $b => $v) {
                if (!empty($r['kontrol'][(string) $b]) && !$pengelola) {
                    $kunci[$b] = true;
                    continue;
                }
                $lama = (array) ($r['isi'][(string) $id][(string) $b] ?? []);
                if ($lama && array_intersect_key($lama, $v) == $v) {
                    continue;
                }
                $r['isi'][(string) $id][(string) $b] = $v + ['s' => kk_tanda() + ['impor' => true]];
                $n++;
            }
        }
        $d[(string) $ruangId] = $r;
        return [$n, count($kunci)];
    });
    return ['ok', ($hasil[0] ?? 0) . ' isian sarana-bulan diimpor ke kartu ' . $ruang['nama'] . ' tahun ' . $tahun . '.'
        . (!empty($hasil[1]) ? ' ' . $hasil[1] . ' bulan dilewati karena sudah diparaf.' : '')
        . ($tidakDikenal ? ' Kode tidak terdaftar di ruangan ini dan dilewati: ' . implode(', ', array_slice(array_unique($tidakDikenal), 0, 8)) . '.' : '')];
}

function kk_aktivitas(int $tahun, array $petugas, int $batas = 40): array
{
    $hasil = [];
    $ruang = array_column(rows('SELECT id, nama FROM aset_ruang'), 'nama', 'id');
    foreach (kk_baca('ruang', $tahun) as $rid => $r) {
        foreach ((array) ($r['oleh'] ?? []) as $sel => $o) {
            $pid = (int) ($o['id'] ?? 0);
            if (!isset($petugas[$pid])) { continue; }
            [$b, $m] = array_map('intval', explode('-', (string) $sel) + [0, 0]);
            $hasil[] = ['waktu' => (string) $o['waktu'], 'pid' => $pid, 'jenis' => 'Kebersihan', 'teks' => 'Menilai kebersihan ' . ($ruang[$rid] ?? 'ruangan') . ' · ' . (NAMA_BULAN[$b] ?? '') . ' minggu ' . (KK_ROMAWI[$m] ?? ''),
                        'url' => url('aset_kk_ruang', ['ruang' => $rid, 'tahun' => $tahun, 'bulan' => $b])];
        }
    }
    $sarana = [];
    foreach (kk_baca('sarana', $tahun) as $rid => $r) {
        foreach ((array) ($r['isi'] ?? []) as $perBulan) {
            foreach ((array) $perBulan as $b => $isi) {
                $pid = (int) ($isi['s']['id'] ?? 0);
                if (!isset($petugas[$pid])) { continue; }
                $k = $rid . '|' . $b . '|' . $pid;
                $sarana[$k] = ['n' => ($sarana[$k]['n'] ?? 0) + 1, 'waktu' => max($sarana[$k]['waktu'] ?? '', (string) $isi['s']['waktu']), 'rid' => $rid, 'b' => (int) $b, 'pid' => $pid];
            }
        }
    }
    foreach ($sarana as $x) {
        $hasil[] = ['waktu' => $x['waktu'], 'pid' => $x['pid'], 'jenis' => 'Inventaris', 'teks' => 'Memeriksa ' . $x['n'] . ' sarana ' . ($ruang[$x['rid']] ?? 'ruangan') . ' · ' . (NAMA_BULAN[$x['b']] ?? ''),
                    'url' => url('aset_kk_sarana', ['ruang' => $x['rid'], 'tahun' => $tahun, 'bulan' => $x['b']])];
    }
    $unit = array_column(rows("SELECT id, nama, kode FROM aset_barang WHERE kategori = 'ac'"), null, 'id');
    foreach (kk_baca('ac', $tahun) as $bid => $u) {
        foreach ((array) ($u['baris'] ?? []) as $b) {
            $pid = (int) ($b['oleh']['id'] ?? 0);
            if (!isset($petugas[$pid])) { continue; }
            $hasil[] = ['waktu' => (string) $b['oleh']['waktu'], 'pid' => $pid, 'jenis' => 'AC', 'teks' => 'Pemeriksaan ' . ($unit[$bid]['nama'] ?? 'AC') . ': ' . $b['deskripsi'],
                        'url' => url('aset_kk_ac', ['barang' => $bid, 'tahun' => $tahun])];
        }
    }
    if ($petugas) {
        $ids = array_keys($petugas);
        $ph = in_placeholder($ids);
        foreach (rows("SELECT id, pengguna_id, judul, dibuat FROM kegiatan WHERE pengguna_id IN ($ph) AND dibuat >= ?" . sql_bukan_draft() . " ORDER BY dibuat DESC LIMIT 200", array_merge($ids, [($tahun - 1) . '-12-01'])) as $k) {
            $hasil[] = ['waktu' => (string) $k['dibuat'], 'pid' => (int) $k['pengguna_id'], 'jenis' => 'Catatan harian', 'teks' => $k['judul'] !== '' ? $k['judul'] : '(belum diberi judul)',
                        'url' => url('pantau', ['tab' => 'pegawai', 'p' => $k['pengguna_id']])];
        }
        foreach (rows("SELECT id, jenis, judul, dibuat, dibuat_oleh FROM aset_kerusakan WHERE dibuat_oleh IN ($ph) ORDER BY dibuat DESC LIMIT 100", $ids) as $k) {
            $hasil[] = ['waktu' => (string) $k['dibuat'], 'pid' => (int) $k['dibuat_oleh'], 'jenis' => 'Lapor kerusakan', 'teks' => $k['judul'],
                        'url' => url($k['jenis'] === 'prasarana' ? 'aset_prasarana' : 'aset_sarana', ['aksi' => 'lihat', 'id' => $k['id']])];
        }
    }
    usort($hasil, static function ($a, $b) { return strcmp($b['waktu'], $a['waktu']); });
    return array_slice($hasil, 0, $batas);
}

function kk_notif_dilihat(int $pid): string
{
    return (string) (aset_publik_baca('kk-notif-dilihat.json')[(string) $pid] ?? '');
}

function kk_tandai_dilihat(int $pid): void
{
    aset_publik_ubah('kk-notif-dilihat.json', static function (array &$d) use ($pid): void {
        $d[(string) $pid] = date('Y-m-d H:i:s');
    });
}

function kk_notifikasi(int $pid): array
{
    if ($pid <= 0 || !kk_boleh()) {
        return [];
    }
    $dilihat = kk_notif_dilihat($pid);
    $batasWaktu = date('Y-m-d H:i:s', strtotime('-14 days'));
    $daftar = [];
    if (kk_pengelola()) {
        $cs = kk_petugas();
        if ($cs) {
            $ids = array_keys($cs);
            foreach (rows('SELECT k.id, k.jenis, k.judul, k.dibuat, k.dibuat_oleh, r.nama AS ruang_nama FROM aset_kerusakan k LEFT JOIN aset_ruang r ON r.id = k.ruang_id
                            WHERE k.status = \'dilaporkan\' AND k.dibuat >= ? AND k.dibuat_oleh IN (' . in_placeholder($ids) . ') ORDER BY k.dibuat DESC LIMIT 6', array_merge([$batasWaktu], $ids)) as $k) {
                $daftar[] = ['id' => 'kk-rusak-' . $k['id'], 'judul' => 'Laporan kerusakan: ' . $k['judul'], 'waktu' => kk_waktu(['waktu' => $k['dibuat']]),
                             'tempat' => ($cs[(int) $k['dibuat_oleh']] ?? 'CS') . ($k['ruang_nama'] ? ' · ' . $k['ruang_nama'] : ''), 'baru' => $k['dibuat'] > $dilihat,
                             'url' => url($k['jenis'] === 'prasarana' ? 'aset_prasarana' : 'aset_sarana', ['aksi' => 'lihat', 'id' => $k['id']]), 'jenis' => 'kartu'];
            }
        }
    } else {
        $tahun = (int) date('Y');
        $tugas = kk_penugasan_semua();
        $ruang = array_column(rows('SELECT id, nama FROM aset_ruang'), 'nama', 'id');
        foreach (['ruang' => 'aset_kk_ruang', 'sarana' => 'aset_kk_sarana'] as $jenis => $hal) {
            foreach (kk_baca($jenis, $tahun) as $rid => $r) {
                if (!in_array($pid, $tugas[$jenis][(int) $rid] ?? [], true)) { continue; }
                foreach ((array) ($r['kontrol'] ?? []) as $b => $k) {
                    if (($k['waktu'] ?? '') < $batasWaktu) { continue; }
                    $daftar[] = ['id' => 'kk-' . $jenis . '-' . $rid . '-' . $b, 'judul' => 'Kartu ' . ($jenis === 'ruang' ? 'kebersihan' : 'inventaris') . ' ' . ($ruang[$rid] ?? '') . ' ' . (NAMA_BULAN[(int) $b] ?? '') . ' diparaf',
                                 'waktu' => kk_waktu($k), 'tempat' => (($k['catatan'] ?? '') !== '' ? 'Catatan: ' . $k['catatan'] : 'oleh ' . ($k['nama'] ?? 'Kabid')), 'baru' => $k['waktu'] > $dilihat,
                                 'url' => url($hal, ['ruang' => $rid, 'tahun' => $tahun, 'bulan' => $b]), 'jenis' => 'kartu'];
                }
            }
        }
    }
    usort($daftar, static function ($a, $b) { return ($b['baru'] <=> $a['baru']); });
    return array_slice($daftar, 0, 6);
}

function kk_inisial(string $nama): string
{
    $kata = preg_split('/[\s.,]+/u', trim($nama), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $hasil = '';
    foreach (array_slice($kata, 0, 3) as $k) {
        $hasil .= mb_strtoupper(mb_substr($k, 0, 1));
    }
    return $hasil;
}

function kk_teks(string $kunci, int $maks): string
{
    return mb_substr(trim(post($kunci)), 0, $maks);
}
