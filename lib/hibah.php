<?php

const HIBAH_STATUS = [
    'diusulkan' => ['label' => 'Diusulkan',    'warna' => 'biru'],
    'direview'  => ['label' => 'Dalam review', 'warna' => 'emas'],
    'disetujui' => ['label' => 'Disetujui',    'warna' => 'hijau'],
    'berjalan'  => ['label' => 'Berjalan',     'warna' => 'ungu'],
    'selesai'   => ['label' => 'Selesai',      'warna' => 'abu'],
    'ditolak'   => ['label' => 'Ditolak',      'warna' => 'merah'],
];

const HIBAH_STATUS_DANAI = ['disetujui', 'berjalan', 'selesai'];

const HIBAH_SUMBER_SARAN = [
    'Kemendiktisaintek', 'LLDIKTI Wilayah X', 'BRIN', 'Internal Universitas', 'Mitra Industri', 'Pemerintah Daerah', 'Lembaga Luar Negeri', 'Lainnya',
];

const HIBAH_TTD = ['jabatan' => 'Kepala Bidang Perencanaan dan Pengembangan', 'nama' => 'Agus Suryadi, S.Kom., M.Kom', 'nup' => '7771019699'];

const HIBAH_WARNA_DONAT = ['diusulkan' => '#3a6ea5', 'direview' => '#d9a441', 'disetujui' => '#2f9e6a', 'berjalan' => '#7a4fd0', 'selesai' => '#8b95a3', 'ditolak' => '#d14b4b'];

function hibah_berkas(): string
{
    return APP_ROOT . '/uploads/hibah/indeks.json';
}

function hibah_semua(bool $muatUlang = false): array
{
    static $cache = null;
    if ($cache === null || $muatUlang) {
        $berkas = hibah_berkas();
        $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
        $cache = is_array($isi) ? array_values(array_filter($isi, 'is_array')) : [];
    }
    return $cache;
}

function hibah_simpan(array $daftar): bool
{
    $dir = dirname(hibah_berkas());
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return false;
    }
    $berkas = hibah_berkas();
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode(array_values($daftar), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    hibah_semua(true);
    return true;
}

function hibah_angka(string $kunci): ?float
{
    $v = trim((string) ($_POST[$kunci] ?? ''));
    if ($v === '') {
        return null;
    }
    $v = str_replace(['.', ','], ['', '.'], $v);
    return is_numeric($v) && (float) $v >= 0 ? (float) $v : null;
}

function hibah_rp($n): string
{
    return $n === null || $n === '' ? '-' : 'Rp ' . angka((float) $n);
}

function hibah_navigasi(string $aktif): string
{
    $o = '<div class="aset-bilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar gambar-rapat"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav" aria-label="Menu Perencanaan dan Hibah">';
    foreach (['hibah' => ['Daftar Hibah', 'hibah'], 'hibah_rekap' => ['Rekap Hibah', 'rekap'], 'hibah_dokumen' => ['Dokumen', 'dokumen']] as $h => [$label, $ikon]) {
        $o .= '<div class="aset-grup"><a href="' . e(url($h)) . '"' . ($h === $aktif ? ' class="aktif" aria-current="page"' : '') . '>' . e($label) . '</a></div>';
    }
    return $o . '</nav></div>';
}

function hibah_cocok(array $d, string $q): bool
{
    if ($q === '') {
        return true;
    }
    $gudang = mb_strtolower(implode(' ', [$d['nama'] ?? '', $d['sumber'] ?? '', $d['ketua'] ?? '', $d['keterangan'] ?? '', (string) ($d['tahun'] ?? '')]));
    foreach (array_filter(preg_split('/\s+/u', mb_strtolower($q))) as $kata) {
        if (mb_strpos($gudang, $kata) === false) {
            return false;
        }
    }
    return true;
}

function hibah_total(array $daftar): array
{
    $t = ['jumlah' => count($daftar), 'diusulkan' => 0.0, 'disetujui' => 0.0, 'didanai' => 0, 'diputuskan' => 0];
    foreach ($daftar as $d) {
        $t['diusulkan'] += (float) ($d['diusulkan'] ?? 0);
        $t['disetujui'] += (float) ($d['disetujui'] ?? 0);
        $st = (string) ($d['status'] ?? 'diusulkan');
        if (in_array($st, HIBAH_STATUS_DANAI, true)) {
            $t['didanai']++;
        }
        if (in_array($st, array_merge(HIBAH_STATUS_DANAI, ['ditolak']), true)) {
            $t['diputuskan']++;
        }
    }
    return $t;
}

function hibah_kelompok(array $daftar, string $kunci): array
{
    $hasil = [];
    foreach ($daftar as $d) {
        $k = $kunci === 'tahun' ? (string) ((int) ($d['tahun'] ?? 0) ?: '-') : trim((string) ($d[$kunci] ?? '')) ;
        $k = $k === '' ? '-' : $k;
        $hasil[$k][] = $d;
    }
    return $hasil;
}

function hibah_svg_batang(array $label, array $diusulkan, array $disetujui): string
{
    $n = count($label);
    $maks = max(array_merge($diusulkan, $disetujui, [1]));
    $kasar = $maks / 4;
    $pangkat = 10 ** floor(log10(max($kasar, 1)));
    $langkah = $pangkat;
    foreach ([1, 2, 2.5, 5, 10] as $k) {
        if ($k * $pangkat >= $kasar) {
            $langkah = $k * $pangkat;
            break;
        }
    }
    $skala = $langkah * 4;
    $singkat = static function (float $v): string {
        if ($v >= 1e9) { return rtrim(rtrim(number_format($v / 1e9, 1, ',', '.'), '0'), ',') . ' M'; }
        if ($v >= 1e6) { return rtrim(rtrim(number_format($v / 1e6, 1, ',', '.'), '0'), ',') . ' jt'; }
        if ($v >= 1e3) { return rtrim(rtrim(number_format($v / 1e3, 1, ',', '.'), '0'), ',') . ' rb'; }
        return (string) (int) $v;
    };
    $W = 640;
    $H = 240;
    $x0 = 58;
    $x1 = $W - 12;
    $yAtas = 28;
    $yBawah = $H - 32;
    $tinggi = $yBawah - $yAtas;
    $slot = ($x1 - $x0) / max($n, 1);
    $lebar = min(34, $slot * 0.3);
    $svg = '<svg viewBox="0 0 ' . $W . ' ' . $H . '" class="grafik-jad" role="img" aria-label="Perbandingan dana diusulkan dan disetujui per tahun">';
    for ($g = 0; $g <= 4; $g++) {
        $y = round($yBawah - $tinggi * $g / 4, 1);
        $svg .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . $y . '" y2="' . $y . '" class="' . ($g === 0 ? 'gj-dasar' : 'gj-kisi') . '"/>'
              . '<text x="' . ($x0 - 8) . '" y="' . ($y + 4) . '" text-anchor="end" class="gj-sumbu">' . e($singkat($langkah * $g)) . '</text>';
    }
    for ($i = 0; $i < $n; $i++) {
        $cx = $x0 + $slot * ($i + 0.5);
        foreach ([[0, $diusulkan[$i] ?? 0, '#3a6ea5'], [1, $disetujui[$i] ?? 0, '#2f9e6a']] as [$no, $v, $warna]) {
            $h = $v / $skala * $tinggi;
            $x = $cx + ($no === 0 ? -$lebar - 1 : 1);
            if ($v > 0) {
                $svg .= '<rect x="' . round($x, 1) . '" y="' . round($yBawah - $h, 1) . '" width="' . round($lebar, 1) . '" height="' . round($h, 1) . '" rx="3" fill="' . $warna . '"><title>' . e($label[$i] . ': Rp ' . angka($v)) . '</title></rect>'
                      . '<text x="' . round($x + $lebar / 2, 1) . '" y="' . round($yBawah - $h - 5, 1) . '" text-anchor="middle" class="gj-sumbu">' . e($singkat((float) $v)) . '</text>';
            }
        }
        $svg .= '<text x="' . round($cx, 1) . '" y="' . ($yBawah + 18) . '" text-anchor="middle" class="gj-sumbu">' . e($label[$i]) . '</text>';
    }
    return $svg . '</svg>';
}

function hibah_ttd_cetak(): string
{
    return '<div class="cetak-saja hibah-ttd"><div><div>' . e(APP_KOTA) . ', ' . e(tanggal_id(date('Y-m-d'))) . '</div><div>Mengetahui,</div><div>' . e(HIBAH_TTD['jabatan']) . ',</div><div class="ruang"></div>'
        . '<b class="ttd-nama">' . e(HIBAH_TTD['nama']) . '</b><div>NUP: ' . e(HIBAH_TTD['nup']) . '</div></div></div>';
}
