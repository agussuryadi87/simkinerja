<?php

const QR_BLOK_M = [
    1 => [10, [[1, 16]]],
    2 => [16, [[1, 28]]],
    3 => [26, [[1, 44]]],
    4 => [18, [[2, 32]]],
    5 => [24, [[2, 43]]],
    6 => [16, [[4, 27]]],
    7 => [18, [[4, 31]]],
    8 => [22, [[2, 38], [2, 39]]],
    9 => [22, [[3, 36], [2, 37]]],
    10 => [26, [[4, 43], [1, 44]]],
];

const QR_PENJAJARAN = [
    1 => [], 2 => [6, 18], 3 => [6, 22], 4 => [6, 26], 5 => [6, 30],
    6 => [6, 34], 7 => [6, 22, 38], 8 => [6, 24, 42], 9 => [6, 26, 46], 10 => [6, 28, 50],
];

function qr_gf_kali(int $x, int $y): int
{
    $z = 0;
    for ($i = 7; $i >= 0; $i--) {
        $z = (($z << 1) ^ (($z >> 7) * 0x11D)) & 0x1FF;
        $z ^= (($y >> $i) & 1) * $x;
    }
    return $z & 0xFF;
}

function qr_rs_pembagi(int $derajat): array
{
    $hasil = array_fill(0, $derajat, 0);
    $hasil[$derajat - 1] = 1;
    $akar = 1;
    for ($i = 0; $i < $derajat; $i++) {
        for ($j = 0; $j < $derajat; $j++) {
            $hasil[$j] = qr_gf_kali($hasil[$j], $akar);
            if ($j + 1 < $derajat) {
                $hasil[$j] ^= $hasil[$j + 1];
            }
        }
        $akar = qr_gf_kali($akar, 0x02);
    }
    return $hasil;
}

function qr_rs_sisa(array $data, array $pembagi): array
{
    $hasil = array_fill(0, count($pembagi), 0);
    foreach ($data as $b) {
        $faktor = $b ^ array_shift($hasil);
        $hasil[] = 0;
        foreach ($pembagi as $i => $p) {
            $hasil[$i] ^= qr_gf_kali($p, $faktor);
        }
    }
    return $hasil;
}

function qr_matriks(string $teks): ?array
{
    $bita = array_values(unpack('C*', $teks) ?: []);
    $versi = 0;
    foreach (QR_BLOK_M as $v => [, $grup]) {
        $kapasitas = 0;
        foreach ($grup as [$n, $k]) {
            $kapasitas += $n * $k;
        }
        $panjangBit = 4 + ($v < 10 ? 8 : 16) + 8 * count($bita);
        if ($panjangBit <= $kapasitas * 8) {
            $versi = $v;
            break;
        }
    }
    if ($versi === 0) {
        return null;
    }
    [$ecc, $grup] = QR_BLOK_M[$versi];
    $kapasitas = 0;
    foreach ($grup as [$n, $k]) {
        $kapasitas += $n * $k;
    }

    $bit = [];
    $tambah = static function (int $nilai, int $panjang) use (&$bit): void {
        for ($i = $panjang - 1; $i >= 0; $i--) {
            $bit[] = ($nilai >> $i) & 1;
        }
    };
    $tambah(0b0100, 4);
    $tambah(count($bita), $versi < 10 ? 8 : 16);
    foreach ($bita as $b) {
        $tambah($b, 8);
    }
    $tambah(0, min(4, $kapasitas * 8 - count($bit)));
    $tambah(0, (8 - count($bit) % 8) % 8);
    for ($isi = 0xEC; count($bit) < $kapasitas * 8; $isi ^= 0xEC ^ 0x11) {
        $tambah($isi, 8);
    }
    $kata = [];
    foreach (array_chunk($bit, 8) as $c) {
        $kata[] = bindec(implode('', $c));
    }

    $pembagi = qr_rs_pembagi($ecc);
    $blokData = [];
    $blokEcc = [];
    $pos = 0;
    foreach ($grup as [$n, $k]) {
        for ($i = 0; $i < $n; $i++) {
            $d = array_slice($kata, $pos, $k);
            $pos += $k;
            $blokData[] = $d;
            $blokEcc[] = qr_rs_sisa($d, $pembagi);
        }
    }
    $akhir = [];
    $maks = max(array_map('count', $blokData));
    for ($i = 0; $i < $maks; $i++) {
        foreach ($blokData as $d) {
            if ($i < count($d)) {
                $akhir[] = $d[$i];
            }
        }
    }
    for ($i = 0; $i < $ecc; $i++) {
        foreach ($blokEcc as $e) {
            $akhir[] = $e[$i];
        }
    }

    $ukuran = $versi * 4 + 17;
    $m = array_fill(0, $ukuran, array_fill(0, $ukuran, 0));
    $f = array_fill(0, $ukuran, array_fill(0, $ukuran, false));
    $pasang = static function (int $x, int $y, int $gelap) use (&$m, &$f): void {
        $m[$y][$x] = $gelap;
        $f[$y][$x] = true;
    };

    for ($i = 0; $i < $ukuran; $i++) {
        $pasang(6, $i, $i % 2 === 0 ? 1 : 0);
        $pasang($i, 6, $i % 2 === 0 ? 1 : 0);
    }
    foreach ([[3, 3], [$ukuran - 4, 3], [3, $ukuran - 4]] as [$cx, $cy]) {
        for ($dy = -4; $dy <= 4; $dy++) {
            for ($dx = -4; $dx <= 4; $dx++) {
                $x = $cx + $dx;
                $y = $cy + $dy;
                if ($x >= 0 && $x < $ukuran && $y >= 0 && $y < $ukuran) {
                    $jarak = max(abs($dx), abs($dy));
                    $pasang($x, $y, $jarak !== 2 && $jarak !== 4 ? 1 : 0);
                }
            }
        }
    }
    $posisi = QR_PENJAJARAN[$versi];
    $jml = count($posisi);
    for ($i = 0; $i < $jml; $i++) {
        for ($j = 0; $j < $jml; $j++) {
            if (($i === 0 && $j === 0) || ($i === 0 && $j === $jml - 1) || ($i === $jml - 1 && $j === 0)) {
                continue;
            }
            for ($dy = -2; $dy <= 2; $dy++) {
                for ($dx = -2; $dx <= 2; $dx++) {
                    $pasang($posisi[$i] + $dx, $posisi[$j] + $dy, max(abs($dx), abs($dy)) !== 1 ? 1 : 0);
                }
            }
        }
    }
    $format = static function (int $mask) use (&$pasang, $ukuran): void {
        $data = (0 << 3) | $mask;
        $sisa = $data;
        for ($i = 0; $i < 10; $i++) {
            $sisa = ($sisa << 1) ^ (($sisa >> 9) * 0x537);
        }
        $b = (($data << 10) | $sisa) ^ 0x5412;
        $ambil = static function (int $i) use ($b): int {
            return ($b >> $i) & 1;
        };
        for ($i = 0; $i <= 5; $i++) {
            $pasang(8, $i, $ambil($i));
        }
        $pasang(8, 7, $ambil(6));
        $pasang(8, 8, $ambil(7));
        $pasang(7, 8, $ambil(8));
        for ($i = 9; $i < 15; $i++) {
            $pasang(14 - $i, 8, $ambil($i));
        }
        for ($i = 0; $i < 8; $i++) {
            $pasang($ukuran - 1 - $i, 8, $ambil($i));
        }
        for ($i = 8; $i < 15; $i++) {
            $pasang(8, $ukuran - 15 + $i, $ambil($i));
        }
        $pasang(8, $ukuran - 8, 1);
    };
    $format(0);
    if ($versi >= 7) {
        $sisa = $versi;
        for ($i = 0; $i < 12; $i++) {
            $sisa = ($sisa << 1) ^ (($sisa >> 11) * 0x1F25);
        }
        $b = ($versi << 12) | $sisa;
        for ($i = 0; $i < 18; $i++) {
            $g = ($b >> $i) & 1;
            $a = $ukuran - 11 + $i % 3;
            $c = intdiv($i, 3);
            $pasang($a, $c, $g);
            $pasang($c, $a, $g);
        }
    }

    $i = 0;
    $total = count($akhir) * 8;
    for ($kanan = $ukuran - 1; $kanan >= 1; $kanan -= 2) {
        if ($kanan === 6) {
            $kanan = 5;
        }
        for ($vert = 0; $vert < $ukuran; $vert++) {
            for ($j = 0; $j < 2; $j++) {
                $x = $kanan - $j;
                $naik = (($kanan + 1) & 2) === 0;
                $y = $naik ? $ukuran - 1 - $vert : $vert;
                if (!$f[$y][$x] && $i < $total) {
                    $m[$y][$x] = ($akhir[$i >> 3] >> (7 - ($i & 7))) & 1;
                    $i++;
                }
            }
        }
    }

    $terbaik = null;
    $skorTerbaik = PHP_INT_MAX;
    $dasar = $m;
    for ($mask = 0; $mask < 8; $mask++) {
        $m = $dasar;
        for ($y = 0; $y < $ukuran; $y++) {
            for ($x = 0; $x < $ukuran; $x++) {
                if ($f[$y][$x]) {
                    continue;
                }
                switch ($mask) {
                    case 0: $balik = ($x + $y) % 2 === 0; break;
                    case 1: $balik = $y % 2 === 0; break;
                    case 2: $balik = $x % 3 === 0; break;
                    case 3: $balik = ($x + $y) % 3 === 0; break;
                    case 4: $balik = (intdiv($x, 3) + intdiv($y, 2)) % 2 === 0; break;
                    case 5: $balik = $x * $y % 2 + $x * $y % 3 === 0; break;
                    case 6: $balik = ($x * $y % 2 + $x * $y % 3) % 2 === 0; break;
                    default: $balik = (($x + $y) % 2 + $x * $y % 3) % 2 === 0;
                }
                if ($balik) {
                    $m[$y][$x] ^= 1;
                }
            }
        }
        $format($mask);
        $skor = qr_skor($m, $ukuran);
        if ($skor < $skorTerbaik) {
            $skorTerbaik = $skor;
            $terbaik = $m;
        }
    }
    return $terbaik;
}

function qr_skor(array $m, int $n): int
{
    $skor = 0;
    $gelap = 0;
    $baris = [];
    for ($y = 0; $y < $n; $y++) {
        $baris[] = implode('', $m[$y]);
        $gelap += array_sum($m[$y]);
    }
    $kolom = [];
    for ($x = 0; $x < $n; $x++) {
        $k = '';
        for ($y = 0; $y < $n; $y++) {
            $k .= $m[$y][$x];
        }
        $kolom[] = $k;
    }
    foreach (array_merge($baris, $kolom) as $s) {
        preg_match_all('/0{5,}|1{5,}/', $s, $run);
        foreach ($run[0] as $r) {
            $skor += 3 + strlen($r) - 5;
        }
        $skor += 40 * (preg_match_all('/(?=10111010000|00001011101)/', $s));
    }
    for ($y = 0; $y < $n - 1; $y++) {
        for ($x = 0; $x < $n - 1; $x++) {
            $c = $m[$y][$x];
            if ($c === $m[$y][$x + 1] && $c === $m[$y + 1][$x] && $c === $m[$y + 1][$x + 1]) {
                $skor += 3;
            }
        }
    }
    $total = $n * $n;
    $k = (int) ceil(abs($gelap * 20 - $total * 10) / $total) - 1;
    return $skor + max(0, $k) * 10;
}

function qr_svg(string $teks, int $tepi = 4): string
{
    $m = qr_matriks($teks);
    if ($m === null) {
        return '';
    }
    $n = count($m);
    $sisi = $n + $tepi * 2;
    $jalur = '';
    for ($y = 0; $y < $n; $y++) {
        for ($x = 0; $x < $n; $x++) {
            if ($m[$y][$x]) {
                $jalur .= 'M' . ($x + $tepi) . ' ' . ($y + $tepi) . 'h1v1h-1z';
            }
        }
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 ' . $sisi . ' ' . $sisi . '" shape-rendering="crispEdges" role="img" aria-label="Kode QR">'
        . '<rect width="100%" height="100%" fill="#fff"/><path d="' . $jalur . '" fill="#000"/></svg>';
}
