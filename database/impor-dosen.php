<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require dirname(__DIR__) . '/config.php';
require dirname(__DIR__) . '/lib/db.php';
require dirname(__DIR__) . '/lib/xlsx.php';

$berkas = $argv[1] ?? 'D:/DOKUMEN REKTOR/sdm/Rekap_Perkembangan_Dosen_2022-2026.xlsx';
if (!is_file($berkas)) {
    fwrite(STDERR, "Berkas tidak ditemukan: $berkas\n");
    exit(1);
}
$data = baca_xlsx($berkas);

$bersih = static function ($v): string {
    $v = preg_replace('/\s+/u', ' ', str_replace("\xC2\xA0", ' ', (string) $v));
    return trim($v);
};
$angkaTeks = static function ($v) use ($bersih): string {
    $v = $bersih($v);
    return preg_match('/^\d+\.0+$/', $v) ? substr($v, 0, strpos($v, '.')) : $v;
};
$jafungBaku = static function (string $v): string {
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
};
$prodiBaku = static function (string $v) use ($bersih): string {
    return $bersih($v);
};

db()->exec('TRUNCATE TABLE dosen_data');
db()->exec('TRUNCATE TABLE dosen_eligible');
db()->beginTransaction();

$ringkas = [];
foreach (['2022', '2023', '2024', '2025', '2026'] as $tahun) {
    $baris = $data[$tahun] ?? [];
    $formatBaru = (int) $tahun >= 2025;
    $hdrBaris = $formatBaru ? 2 : 1;
    $hdr = [];
    foreach ($baris[$hdrBaris] ?? [] as $kol => $nama) {
        $hdr[mb_strtolower(trim($nama))] = $kol;
    }
    $ambil = static function (array $r, array $nama) use ($hdr): string {
        foreach ($nama as $n) {
            if (isset($hdr[$n]) && isset($r[$hdr[$n]])) {
                return (string) $r[$hdr[$n]];
            }
        }
        return '';
    };
    $n = 0;
    foreach ($baris as $no => $r) {
        if ($no <= $hdrBaris) {
            continue;
        }
        $nama = $bersih($ambil($r, ['nama', 'nama dosen']));
        if ($nama === '') {
            continue;
        }
        $jafungAsli = $bersih($ambil($r, ['jafung', 'jabatan fungsional']));
        if ($formatBaru) {
            $noSert = $bersih($ambil($r, ['no. sertifikat']));
            $tersertifikasi = $noSert !== '' && stripos($noSert, 'belum') === false ? 1 : 0;
            $statusSerdos = $tersertifikasi ? 'Tersertifikasi' : 'Belum tersertifikasi';
        } else {
            $noSert = '';
            $statusSerdos = $bersih($ambil($r, ['status serdos']));
            $tersertifikasi = stripos($statusSerdos, 'tersertifikasi') !== false && stripos($statusSerdos, 'belum') === false ? 1 : 0;
        }
        $thSerdos = $bersih($ambil($r, ['tahun serdos']));
        $ak = $bersih($ambil($r, ['angka kredit']));
        insert('dosen_data', [
            'tahun' => (int) $tahun, 'no_urut' => (int) $ambil($r, ['no', 'no.']) ?: null,
            'nidn' => $angkaTeks($ambil($r, ['nidn'])), 'nuptk' => $angkaTeks($ambil($r, ['nuptk'])), 'uisa' => $bersih($ambil($r, ['uisa'])),
            'nama' => $nama, 'pendidikan' => $bersih($ambil($r, ['jenjang pendidikan', 'pendidikan'])),
            'prodi' => $prodiBaku($ambil($r, ['program studi', 'prodi'])),
            'jafung' => $jafungBaku($jafungAsli), 'jafung_asli' => $jafungAsli,
            'status_dosen' => $bersih($ambil($r, ['status'])), 'no_sertifikat' => $noSert, 'status_serdos' => $statusSerdos,
            'tahun_serdos' => ctype_digit($thSerdos) ? (int) $thSerdos : null, 'tersertifikasi' => $tersertifikasi,
            'angka_kredit' => ctype_digit($ak) ? (int) $ak : null, 'tahun_pangkat' => $angkaTeks($ambil($r, ['tahun pangkat'])),
            'tmt_jafung' => xlsx_tanggal($ambil($r, ['tmt jafung'])),
            'status_keaktifan' => $bersih($ambil($r, ['status keaktifan'])), 'tipe' => $bersih($ambil($r, ['tipe'])),
        ]);
        $n++;
    }
    $ringkas[$tahun] = $n;
}

$kat = null;
$nEl = ['gb' => 0, 'lk' => 0, 'lektor' => 0];
foreach ($data['Eligible'] ?? [] as $no => $r) {
    $a = $bersih($r['A'] ?? '');
    if (stripos($a, 'SISTER') === 0) {
        $kat = preg_match('/Eligible\s+GB/i', $a) ? 'gb' : (preg_match('/Eligible\s+LK/i', $a) ? 'lk' : 'lektor');
        continue;
    }
    if ($kat === null || !ctype_digit($a) || $bersih($r['B'] ?? '') === '') {
        continue;
    }
    insert('dosen_eligible', [
        'kategori' => $kat, 'no_urut' => (int) $a, 'nama' => $bersih($r['B']), 'nidn' => $angkaTeks($r['C'] ?? ''), 'nip' => $bersih($r['D'] ?? ''),
        'nuptk' => $angkaTeks($r['E'] ?? ''), 'status_kepegawaian' => $bersih($r['F'] ?? ''), 'ikatan_kerja' => $bersih($r['G'] ?? ''),
        'perguruan_tinggi' => $bersih($r['H'] ?? ''), 'prodi' => $bersih($r['I'] ?? ''), 'jabfung' => $bersih($r['J'] ?? ''), 'tmt_jabfung' => $bersih($r['K'] ?? ''),
        'golongan' => $bersih($r['L'] ?? ''), 'tmt_golongan' => $bersih($r['M'] ?? ''), 'rumpun_ilmu' => $bersih($r['N'] ?? ''),
        'pohon_ilmu' => $bersih($r['O'] ?? ''), 'cabang_ilmu' => $bersih($r['P'] ?? ''),
    ]);
    $nEl[$kat]++;
}
db()->commit();

echo 'Dosen per tahun: ' . json_encode($ringkas) . "\n";
echo 'Eligible: ' . json_encode($nEl) . "\n";
