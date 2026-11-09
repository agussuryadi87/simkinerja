<?php

const JAD_TAHUN = [2022, 2023, 2024, 2025, 2026];
const JAD_JAFUNG = ['Tenaga Pendidik / Belum Jafung', 'Asisten Ahli', 'Lektor', 'Lektor Kepala', 'Guru Besar'];
const JAD_KETERANGAN = ['Karyawan + Dosen', 'Karyawan'];

const JAD_ELIGIBLE = [
    'gb'     => 'Eligible Guru Besar (GB)',
    'lk'     => 'Eligible Lektor Kepala (LK)',
    'lektor' => 'Eligible Lektor',
];

function boleh_jad(): bool
{
    return uid() > 0 && can('modul_jad');
}

function wajib_jad(): void
{
    wajib_masuk();
    if (!boleh_jad()) {
        tolak_akses();
    }
}

function jad_nav(): array
{
    return [
        'ringkasan' => ['Grafik & Rekap', ['jad' => ['Grafik & Rekap', 'jad', []]]],
        'data'      => ['Data Dosen', ['jad_data' => ['Data Dosen', 'jad_data', []]]],
        'karyawan'  => ['Data Karyawan', ['jad_karyawan' => ['Data Karyawan', 'jad_karyawan', []]]],
        'eligible'  => ['Dosen Eligible', ['jad_eligible' => ['Dosen Eligible', 'jad_eligible', []]]],
        'dokumen'   => ['Dokumen', ['jad_dokumen' => ['Dokumen', 'jad_dokumen', []]]],
    ];
}

function jad_halaman_aktif(): array
{
    return ['jad', 'jad_data', 'jad_karyawan', 'jad_eligible', 'jad_dokumen'];
}

function jad_kunci_aktif(string $h): string
{
    return $h;
}
function jad_navigasi(string $h): string
{
    $aktif = jad_kunci_aktif($h);
    $o = '<div class="aset-bilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar gambar-dosen"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav" aria-label="Menu Kepegawaian &amp; BKD">';
    foreach (jad_nav() as [$label, $item]) {
        $pertama = reset($item);
        $dalam = array_key_exists($aktif, $item);
        $o .= '<div class="aset-grup' . (count($item) > 1 ? ' ada-menu' : '') . '"><a href="' . e(url($pertama[1], $pertama[2])) . '"' . ($dalam ? ' class="aktif" aria-current="page"' : '') . '>' . e($label) . '</a>';
        if (count($item) > 1) {
            $o .= '<div class="aset-menu">';
            foreach ($item as $kunci => [$nama, $halaman, $param]) {
                $o .= '<a href="' . e(url($halaman, $param)) . '"' . ($kunci === $aktif ? ' class="aktif"' : '') . '>' . e($nama) . '</a>';
            }
            $o .= '</div>';
        }
        $o .= '</div>';
    }
    return $o . '</nav></div>';
}

function jad_rekap(): array
{
    $hasil = [];
    foreach (JAD_TAHUN as $t) {
        $hasil[$t] = ['total' => 0, 's2' => 0, 's3' => 0, 'serdos' => 0, 'belum' => 0, 'jafung' => array_fill_keys(JAD_JAFUNG, 0)];
    }
    foreach (rows(
        "SELECT tahun, COUNT(*) AS total, SUM(pendidikan = 'S2') AS s2, SUM(pendidikan = 'S3') AS s3, SUM(tersertifikasi = 1) AS serdos
           FROM dosen_data GROUP BY tahun"
    ) as $r) {
        $t = (int) $r['tahun'];
        if (isset($hasil[$t])) {
            $hasil[$t]['total'] = (int) $r['total'];
            $hasil[$t]['s2'] = (int) $r['s2'];
            $hasil[$t]['s3'] = (int) $r['s3'];
            $hasil[$t]['serdos'] = (int) $r['serdos'];
            $hasil[$t]['belum'] = (int) $r['total'] - (int) $r['serdos'];
        }
    }
    foreach (rows('SELECT tahun, jafung, COUNT(*) AS n FROM dosen_data GROUP BY tahun, jafung') as $r) {
        $t = (int) $r['tahun'];
        if (isset($hasil[$t]) && isset($hasil[$t]['jafung'][$r['jafung']])) {
            $hasil[$t]['jafung'][$r['jafung']] = (int) $r['n'];
        }
    }
    return $hasil;
}

function jad_svg_batang(array $label, array $seri, string $aria, int $tinggiSvg = 130, string $warnaTren = '', int $lebarSvg = 640): string
{
    $n = count($label);
    $tot = [];
    for ($i = 0; $i < $n; $i++) {
        $tot[$i] = array_sum(array_map(static function ($s) use ($i) { return $s[2][$i] ?? 0; }, $seri));
    }
    $maks = max(array_merge($tot, [1]));
    $kasar = $maks / 4;
    $pangkat = 10 ** floor(log10(max($kasar, 1)));
    $langkah = $pangkat;
    foreach ([1, 2, 2.5, 5, 10] as $k) {
        if ($k * $pangkat >= $kasar) { $langkah = $k * $pangkat; break; }
    }
    $skala = $langkah * 4;
    $W = $lebarSvg;
    $H = 230;
    $x0 = 44;
    $x1 = $W - 12;
    $yAtas = 30;
    $yBawah = $H - 30;
    $tinggi = $yBawah - $yAtas;
    $slot = ($x1 - $x0) / max($n, 1);
    $lebar = min(58, $slot * 0.48);
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="grafik-jad" role="img" aria-label="' . e($aria) . '">';
    for ($g = 0; $g <= 4; $g++) {
        $y = round($yBawah - $tinggi * $g / 4, 1);
        $svg .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . $y . '" y2="' . $y . '" class="' . ($g === 0 ? 'gj-dasar' : 'gj-kisi') . '"/>'
              . '<text x="' . ($x0 - 10) . '" y="' . ($y + 4) . '" text-anchor="end" class="gj-sumbu">' . angka((int) round($langkah * $g)) . '</text>';
    }
    $titik = [];
    $teks = '';
    for ($i = 0; $i < $n; $i++) {
        $cx = $x0 + $slot * ($i + 0.5);
        $y = $yBawah;
        $segmen = [];
        foreach ($seri as [$nama, $warna, $nilai]) {
            $v = $nilai[$i] ?? 0;
            if ($v <= 0) { continue; }
            $h = $v / $skala * $tinggi;
            $segmen[] = [$y - $h, $h, $warna, $nama, $v];
            $y -= $h;
        }
        $kiri = round($cx - $lebar / 2, 1);
        foreach ($segmen as $k => [$sy, $sh, $warna, $nama, $v]) {
            $atas = $k === count($segmen) - 1;
            $r = $atas ? min(5, $sh) : 0;
            $yy = round($sy, 1);
            $hh = round($sh, 1);
            $d = 'M' . $kiri . ' ' . round($yy + $hh, 1) . ' V' . round($yy + $r, 1)
               . ($r ? ' Q' . $kiri . ' ' . $yy . ' ' . round($kiri + $r, 1) . ' ' . $yy . ' H' . round($kiri + $lebar - $r, 1) . ' Q' . round($kiri + $lebar, 1) . ' ' . $yy . ' ' . round($kiri + $lebar, 1) . ' ' . round($yy + $r, 1) : ' H' . round($kiri + $lebar, 1))
               . ' V' . round($yy + $hh, 1) . ' Z';
            $svg .= '<path d="' . $d . '" fill="' . e(is_array($warna) ? ($warna[$i] ?? '#1f5fbf') : $warna) . '"' . ($k > 0 ? ' class="gj-seg"' : '') . '><title>' . e($label[$i] . ' · ' . $nama . ': ' . angka((int) $v)) . '</title></path>';
        }
        $titik[] = [round($cx, 1), round($y, 1)];
        $teks .= '<text x="' . round($cx, 1) . '" y="' . ($yBawah + 20) . '" text-anchor="middle" class="gj-label">' . e($label[$i]) . '</text>';
    }
    if ($warnaTren !== '' && count($titik) > 1) {
        $jalur = '';
        foreach ($titik as $i => [$px, $py]) {
            $jalur .= ($i === 0 ? 'M' : ' L') . $px . ' ' . round($py - 4, 1);
        }
        $svg .= '<path d="' . $jalur . '" fill="none" stroke="' . e($warnaTren) . '" stroke-width="2" stroke-dasharray="5 4" stroke-linecap="round" stroke-linejoin="round" class="gj-tren"/>';
        foreach ($titik as [$px, $py]) {
            $svg .= '<circle cx="' . $px . '" cy="' . round($py - 4, 1) . '" r="4" fill="#fff" stroke="' . e($warnaTren) . '" stroke-width="2"/>';
        }
    }
    foreach ($titik as $i => [$px, $py]) {
        $teks .= '<text x="' . $px . '" y="' . round($py - 13, 1) . '" text-anchor="middle" class="gj-nilai">' . angka($tot[$i]) . '</text>';
    }
    return $svg . $teks . '</svg>';
}
const JAD_WARNA_DONAT = ['#5b8fd6', '#2a9d8f', '#8e6bbf', '#e08a3c', '#1f4e9a', '#d1495b', '#66a182', '#edae49', '#00798c', '#7a8699'];
function jad_donat(array $seg, string $angka, string $ket, string $judul, string $aria): string
{
    $seg = array_values(array_filter($seg, static function ($s) { return $s[2] > 0; }));
    $total = array_sum(array_map(static function ($s) { return $s[2]; }, $seg));
    $r = 78;
    $keliling = 2 * M_PI * $r;
    $svg = '<svg viewBox="0 0 220 220" class="jd-grafik-donat" role="img" aria-label="' . e($aria) . '">'
         . '<circle cx="110" cy="110" r="' . $r . '" fill="none" stroke="#eef1f5" stroke-width="28"/>';
    $jalan = 0.0;
    $celah = count($seg) > 1 ? 2.0 : 0.0;
    foreach ($seg as [$nama, $warna, $nilai]) {
        $pjg = $total ? $nilai / $total * $keliling : 0;
        $tampil = max($pjg - $celah, 0.5);
        $svg .= '<circle cx="110" cy="110" r="' . $r . '" fill="none" stroke="' . e($warna) . '" stroke-width="28" stroke-dasharray="' . round($tampil, 2) . ' ' . round($keliling - $tampil, 2) . '" stroke-dashoffset="' . round(-$jalan, 2) . '" transform="rotate(-90 110 110)"><title>' . e($nama . ': ' . angka((int) $nilai) . ($total ? ' (' . number_format($nilai / $total * 100, 1, ',', '.') . '%)' : '')) . '</title></circle>';
        $jalan += $pjg;
    }
    $svg .= '<text x="110" y="112" text-anchor="middle" class="gd-angka">' . e($angka) . '</text>'
          . '<text x="110" y="134" text-anchor="middle" class="gd-ket">' . e($ket) . '</text></svg>';
    $leg = '<ul class="jd-legenda">';
    foreach ($seg as [$nama, $warna, $nilai]) {
        $leg .= '<li><i style="background:' . e($warna) . '"></i><span>' . e($nama) . '</span><b>' . angka((int) $nilai) . '</b><em>' . ($total ? number_format($nilai / $total * 100, 1, ',', '.') . '%' : '-') . '</em></li>';
    }
    $leg .= '</ul>';
    return '<div class="jd-donat"><div class="jd-judul">' . e($judul) . '</div>' . $svg . $leg . '</div>';
}
function jad_jafung_baku(string $v): string
{
    $k = mb_strtolower(trim($v));
    if ($k === 'lektor kepala') {
        return 'Lektor Kepala';
    }
    if ($k === 'lektor') {
        return 'Lektor';
    }
    if ($k === 'asisten ahli') {
        return 'Asisten Ahli';
    }
    if ($k === 'guru besar' || $k === 'profesor') {
        return 'Guru Besar';
    }
    return 'Tenaga Pendidik / Belum Jafung';
}

function jad_pilihan_jafung(): array
{
    return ['-' => '- (belum memiliki jabatan fungsional)', 'Tenaga Pendidik' => 'Tenaga Pendidik', 'Asisten Ahli' => 'Asisten Ahli', 'Lektor' => 'Lektor', 'Lektor Kepala' => 'Lektor Kepala', 'Guru Besar' => 'Guru Besar'];
}

function jad_karyawan_berkas(): string
{
    return APP_ROOT . '/uploads/kepegawaian/karyawan.json';
}

function jad_karyawan_baca(): array
{
    $f = jad_karyawan_berkas();
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    if (!is_array($d)) {
        $d = [];
    }
    return $d + ['urut' => 0, 'urut_posisi' => 2, 'posisi' => [['id' => 1, 'nama' => 'Rektorat'], ['id' => 2, 'nama' => 'Fakultas FEB']], 'karyawan' => []];
}

function jad_karyawan_ubah(callable $ubah)
{
    $dir = dirname(jad_karyawan_berkas());
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return null;
    }
    $kunci = @fopen($dir . '/.kunci', 'c');
    if (!$kunci || !flock($kunci, LOCK_EX)) {
        return null;
    }
    $data = jad_karyawan_baca();
    $hasil = $ubah($data);
    $f = jad_karyawan_berkas();
    $sementara = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = @file_put_contents($sementara, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    if ($ok && !@rename($sementara, $f)) {
        @unlink($f);
        $ok = @rename($sementara, $f);
    }
    if (!$ok) {
        @unlink($sementara);
    }
    flock($kunci, LOCK_UN);
    fclose($kunci);
    return $ok ? $hasil : null;
}

function jad_posisi_peta(array $d): array
{
    $peta = [];
    foreach ((array) ($d['posisi'] ?? []) as $p) {
        $peta[(int) $p['id']] = (string) $p['nama'];
    }
    return $peta;
}

function jad_boleh_master(): bool
{
    return (string) (user()['peran_kode'] ?? '') === 'admin';
}

const JAD_SINGKATAN = ['FEB', 'FST', 'FT', 'BAAK', 'BKAK', 'LPMI', 'LPPM', 'LPTI', 'UPMI', 'UPPM', 'SDM', 'ICT', 'IT', 'TU', 'UIS', 'HKI', 'PKM', 'K3', 'S1', 'S2', 'S3', 'D3', 'TF', 'SI', 'TI', 'TL', 'TP', 'CS', 'BKD', 'BAUK', 'RAPB', 'LKPS', 'I', 'II', 'III', 'IV', 'V', 'VI'];
const JAD_KHUSUS = ['FIKES' => 'FIKes'];
const JAD_KECIL = ['DAN', 'DI', 'KE', 'ATAU', 'YANG', 'DARI', 'UNTUK'];
const JAD_GELAR_DEPAN = ['dr.', 'dr', 'drg.', 'dra.', 'drs.', 'ir.', 'ir', 'hj.', 'h.', 'prof.', 'apt.', 'ns.'];

function jad_kapital_kata(string $w): string
{
    return implode('-', array_map(static function ($bagian) {
        return mb_strtoupper(mb_substr($bagian, 0, 1)) . mb_strtolower(mb_substr($bagian, 1));
    }, explode('-', $w)));
}

function jad_huruf_jabatan(string $s): string
{
    $kata = preg_split('/\s+/u', trim($s), -1, PREG_SPLIT_NO_EMPTY);
    foreach ($kata as $i => $w) {
        $inti = rtrim($w, '.,');
        $ekor = mb_substr($w, mb_strlen($inti));
        $besar = mb_strtoupper($inti);
        if (isset(JAD_KHUSUS[$besar])) {
            $kata[$i] = JAD_KHUSUS[$besar] . $ekor;
        } elseif (in_array($besar, JAD_SINGKATAN, true)) {
            $kata[$i] = $besar . $ekor;
        } elseif ($i > 0 && in_array($besar, JAD_KECIL, true)) {
            $kata[$i] = mb_strtolower($inti) . $ekor;
        } else {
            $kata[$i] = jad_kapital_kata($inti) . $ekor;
        }
    }
    return implode(' ', $kata);
}

function jad_huruf_nama(string $s): string
{
    $s = trim($s);
    $koma = mb_strpos($s, ',');
    $bagianNama = $koma === false ? $s : mb_substr($s, 0, $koma);
    $gelar = $koma === false ? '' : mb_substr($s, $koma);
    $kata = explode(' ', trim(preg_replace('/\s+/u', ' ', $bagianNama)));
    $awal = true;
    foreach ($kata as $i => $w) {
        if ($awal && in_array(mb_strtolower($w), JAD_GELAR_DEPAN, true)) {
            continue;
        }
        $awal = false;
        $kata[$i] = jad_kapital_kata($w);
    }
    return implode(' ', $kata) . $gelar;
}

const JAD_KELOMPOK_JABATAN = ['Pimpinan', 'Kepala / Kabid / Koordinator', 'Staf', 'Cleaning Service', 'Belum diisi'];

function jad_kelompok_jabatan(string $jabatan): string
{
    $j = mb_strtolower(trim($jabatan));
    if ($j === '') {
        return 'Belum diisi';
    }
    if ($j === 'cs' || strpos($j, 'cleaning') !== false) {
        return 'Cleaning Service';
    }
    if (preg_match('/^(rektor|warek|wakil rektor|dekan|wakil dekan|wadek|ka\.?\s*biro|ka\.?\s*lp)/u', $j)) {
        return 'Pimpinan';
    }
    if (preg_match('/^(kabid|ka\.|kepala|ketua|sekprodi|koordinator|gugus)/u', $j)) {
        return 'Kepala / Kabid / Koordinator';
    }
    return 'Staf';
}

function jad_rekap_karyawan(): array
{
    $d = jad_karyawan_baca();
    $peta = jad_posisi_peta($d) + [0 => 'Belum ditentukan'];
    $kosong = array_fill_keys(array_merge(JAD_KETERANGAN, ['Belum diisi']), 0);
    $posisi = [];
    foreach ($peta as $id => $nm) {
        $posisi[$id] = ['nama' => $nm, 'ket' => $kosong, 'total' => 0];
    }
    $kelompok = array_fill_keys(JAD_KELOMPOK_JABATAN, $kosong + ['total' => 0]);
    $ket = $kosong;
    foreach ($d['karyawan'] as $k) {
        $pid = isset($peta[(int) $k['posisi_id']]) ? (int) $k['posisi_id'] : 0;
        $kt = in_array($k['keterangan'], JAD_KETERANGAN, true) ? $k['keterangan'] : 'Belum diisi';
        $posisi[$pid]['ket'][$kt]++;
        $posisi[$pid]['total']++;
        $kl = jad_kelompok_jabatan((string) $k['jabatan']);
        $kelompok[$kl][$kt]++;
        $kelompok[$kl]['total']++;
        $ket[$kt]++;
    }
    if (!$posisi[0]['total']) {
        unset($posisi[0]);
    }
    return ['total' => count($d['karyawan']), 'posisi' => $posisi, 'kelompok' => array_filter($kelompok, static function ($r) { return $r['total'] > 0; }), 'ket' => $ket];
}

const JAD_ARSIP_JENIS = [
    'ijazah'     => ['Ijazah & transkrip nilai', 'S1, S2, dan S3 (bila ada)'],
    'sk_dosen'   => ['SK pengangkatan dosen', 'SK dosen tetap / perjanjian kerja'],
    'sk_jafung'  => ['SK jabatan fungsional', 'Asisten Ahli, Lektor, Lektor Kepala, Guru Besar'],
    'sk_pangkat' => ['SK pangkat / inpassing', 'Golongan terakhir'],
    'serdos'     => ['Sertifikat pendidik (serdos)', 'Bila sudah tersertifikasi'],
    'identitas'  => ['KTP, KK, dan NPWP', 'Dokumen identitas'],
    'cv'         => ['Daftar riwayat hidup (CV)', 'CV terbaru'],
    'lainnya'    => ['Dokumen pendukung lainnya', 'Sertifikat pelatihan, penghargaan, publikasi, dll.'],
];

function jad_arsip_berkas(): string
{
    return APP_ROOT . '/uploads/kepegawaian/arsip-dosen.json';
}

function jad_arsip_baca(): array
{
    $f = jad_arsip_berkas();
    $d = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
    return is_array($d) ? $d + ['dosen' => [], 'batas' => []] : ['dosen' => [], 'batas' => []];
}

function jad_arsip_ubah(callable $ubah)
{
    $f = jad_arsip_berkas();
    $dir = dirname($f);
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return null;
    }
    $kunci = @fopen($dir . '/.kunci-arsip', 'c');
    if (!$kunci || !flock($kunci, LOCK_EX)) {
        return null;
    }
    $data = jad_arsip_baca();
    $hasil = $ubah($data);
    $sementara = $f . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = @file_put_contents($sementara, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) !== false;
    if ($ok && !@rename($sementara, $f)) {
        @unlink($f);
        $ok = @rename($sementara, $f);
    }
    if (!$ok) {
        @unlink($sementara);
    }
    flock($kunci, LOCK_UN);
    fclose($kunci);
    return $ok ? $hasil : null;
}

function jad_arsip_kunci(array $d): string
{
    $nidn = preg_replace('/\D/', '', (string) ($d['nidn'] ?? ''));
    if ($nidn !== '' && strlen($nidn) >= 8) {
        return 'nidn:' . $nidn;
    }
    $nuptk = preg_replace('/\D/', '', (string) ($d['nuptk'] ?? ''));
    if ($nuptk !== '' && strlen($nuptk) >= 8) {
        return 'nuptk:' . $nuptk;
    }
    return 'id:' . (int) ($d['id'] ?? 0);
}

function jad_arsip_url_sah(string $url): bool
{
    if ($url === '' || mb_strlen($url) > 1000 || !filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    $skema = strtolower((string) parse_url($url, PHP_URL_SCHEME));
    return in_array($skema, ['http', 'https'], true) && (string) parse_url($url, PHP_URL_HOST) !== '';
}

function jad_arsip_cari_dosen(string $nomor): ?array
{
    $n = preg_replace('/\D/', '', $nomor);
    if ($n === '' || strlen($n) < 8) {
        return null;
    }
    $tahun = JAD_TAHUN[count(JAD_TAHUN) - 1];
    return row('SELECT * FROM dosen_data WHERE tahun = ? AND (nidn = ? OR nuptk = ?) ORDER BY id LIMIT 1', [$tahun, $n, $n]) ?: null;
}

function jad_arsip_boleh_kirim(string $ip, int $batas = 20, int $detik = 3600): bool
{
    $k = hash('sha256', $ip . '|' . APP_ROOT);
    return jad_arsip_ubah(static function (array &$data) use ($k, $batas, $detik): bool {
        $kini = time();
        foreach ((array) $data['batas'] as $kk => $w) {
            $data['batas'][$kk] = array_values(array_filter((array) $w, static function ($t) use ($kini, $detik) { return (int) $t > $kini - $detik; }));
            if (!$data['batas'][$kk]) {
                unset($data['batas'][$kk]);
            }
        }
        if (count($data['batas'][$k] ?? []) >= $batas) {
            return false;
        }
        $data['batas'][$k][] = $kini;
        return true;
    }) === true;
}
