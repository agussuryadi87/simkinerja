<?php
$sumber = array_filter($_SERVER['REQUEST_METHOD'] === 'POST' ? $_POST : $_GET, 'is_string');
$fak = (string) ($sumber['fakultas'] ?? 'feb');
if (!isset(RAPB_FAKULTAS[$fak])) {
    $fak = 'feb';
}
$prodiFak = array_keys(array_filter(RAPB_PRODI, static function ($p) use ($fak) { return $p['fakultas'] === $fak; }));
$prodi = (string) ($sumber['prodi'] ?? '');
if (!in_array($prodi, $prodiFak, true)) {
    $prodi = $prodiFak[0];
}
$bulanIni = (int) date('n');
$tahunIni = (int) date('Y');
$taKini = $bulanIni >= 8 ? $tahunIni . '-' . ($tahunIni + 1) : ($tahunIni - 1) . '-' . $tahunIni;
$ta = (string) ($sumber['ta'] ?? $taKini);
if (!in_array($ta, RAPB_TAHUN_AKADEMIK, true)) {
    $ta = in_array($taKini, RAPB_TAHUN_AKADEMIK, true) ? $taKini : RAPB_TAHUN_AKADEMIK[count(RAPB_TAHUN_AKADEMIK) - 1];
}
$basis = ($sumber['basis'] ?? '') === 'realisasi' ? 'realisasi' : 'jumlah';
$prodiRekap = $h === 'rapb_upps' && in_array((string) ($sumber['prodi'] ?? ''), $prodiFak, true) ? (string) $sumber['prodi'] : '';
$kunciRekap = $fak . ($prodiRekap !== '' ? ':' . $prodiRekap : '');
$param = ['fakultas' => $fak, 'prodi' => $h === 'rapb_upps' ? ($prodiRekap !== '' ? $prodiRekap : null) : $prodi, 'ta' => $ta, 'basis' => $h === 'rapb_upps' && $basis === 'realisasi' ? 'realisasi' : null];
$fakInfo = RAPB_FAKULTAS[$fak];
$prodiInfo = RAPB_PRODI[$prodi];
$taDaftarLkps = [rapb_ta_geser($ta, -2), rapb_ta_geser($ta, -1), $ta];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $kunciBerkas = rapb_kunci();
    $op = post('op');
    $oleh = user()['nama'] ?? '';
    if ($op === 'simpan_rapb') {
        $nilai = [];
        $masuk = (array) ($_POST['n'] ?? []);
        foreach (rapb_item_semua() as $kode => $_) {
            $v = (array) ($masuk[$kode] ?? []);
            $baris = [rapb_angka_post($v[0] ?? ''), rapb_angka_post($v[1] ?? ''), rapb_angka_post($v[2] ?? '')];
            if ($baris[0] || $baris[1] || $baris[2]) {
                $nilai[$kode] = $baris;
            }
        }
        $tautan = [];
        foreach ((array) ($_POST['tautan'] ?? []) as $kode => $url) {
            $url = rapb_tautan_sah((string) $url);
            if ($url !== '' && isset(rapb_item_semua()[$kode])) {
                $tautan[$kode] = $url;
            }
        }
        $lamaJejak = rapb_data_prodi($ta, $prodi);
        $per = isset($_POST['realisasi_per']) ? post('realisasi_per') : (rapb_data_prodi($ta, $prodi)['realisasi_per'] ?? '');
        $ok = rapb_simpan_prodi($ta, $prodi, [
            'nilai' => $nilai,
            'tautan' => $tautan,
            'realisasi_per' => tanggal_sah($per) ? $per : '',
            'diubah' => date('Y-m-d H:i:s'),
            'oleh' => $oleh,
        ]);
        if ($ok) {
            rapb_jejak_catat('p|' . $ta . '|' . $prodi, rapb_jejak_peta_prodi($lamaJejak), rapb_jejak_peta_prodi(['nilai' => $nilai, 'tautan' => $tautan]));
            catat_log('simpan_rapb_prodi', 'rapb_format', 0, $prodiInfo['nama'] . ' ' . rapb_ta_label($ta));
            flash('RAPB ' . $prodiInfo['nama'] . ' tahun anggaran ' . rapb_ta_label($ta) . ' tersimpan.');
        } else {
            flash('RAPB tidak dapat disimpan di server. Periksa izin folder uploads.', 'galat');
        }
    } elseif ($op === 'hapus_rapb') {
        if (rapb_data_prodi($ta, $prodi) === null) {
            flash('Belum ada data RAPB ' . $prodiInfo['nama'] . ' tahun anggaran ' . rapb_ta_label($ta) . ' untuk dihapus.', 'warn');
            kembali_ke($h, $param);
        }
        rapb_simpan_prodi($ta, $prodi, null);
        rapb_jejak_hapus('p|' . $ta . '|' . $prodi);
        catat_log('hapus_rapb_prodi', 'rapb_format', 0, $prodiInfo['nama'] . ' ' . rapb_ta_label($ta));
        flash('Data RAPB ' . $prodiInfo['nama'] . ' tahun anggaran ' . rapb_ta_label($ta) . ' dihapus.');
    } elseif ($op === 'salin_rapb') {
        $asal = rapb_data_prodi(rapb_ta_geser($ta, -1), $prodi);
        if (!$asal || empty($asal['nilai'])) {
            flash('Belum ada data RAPB tahun anggaran ' . rapb_ta_label(rapb_ta_geser($ta, -1)) . ' untuk disalin.', 'warn');
        } else {
            $nilai = [];
            foreach ($asal['nilai'] as $kode => $v) {
                if ((float) ($v[0] ?? 0) > 0) {
                    $nilai[$kode] = [(float) $v[0], 0, 0];
                }
            }
            $tujuan = rapb_data_prodi($ta, $prodi) ?? [];
            rapb_simpan_prodi($ta, $prodi, ['nilai' => $nilai, 'tautan' => (array) ($tujuan['tautan'] ?? []), 'realisasi_per' => '', 'diubah' => date('Y-m-d H:i:s'), 'oleh' => $oleh]);
            rapb_jejak_catat('p|' . $ta . '|' . $prodi, rapb_jejak_peta_prodi($tujuan ?: null), rapb_jejak_peta_prodi(['nilai' => $nilai, 'tautan' => (array) ($tujuan['tautan'] ?? [])]));
            catat_log('salin_rapb_prodi', 'rapb_format', 0, $prodiInfo['nama'] . ' ' . rapb_ta_label($ta));
            flash('Jumlah anggaran disalin dari tahun anggaran ' . rapb_ta_label(rapb_ta_geser($ta, -1)) . '. Silakan sesuaikan lalu isi realisasinya.');
            kembali_ke('rapb_prodi', $param + ['mode' => 'ubah']);
        }
    } elseif ($op === 'simpan_rekap') {
        $sus = rapb_rekap_susun($ta, $fak, $basis, $prodiRekap);
        $masuk = (array) ($_POST['rk'] ?? []);
        $manual = [];
        foreach ($sus['hitung'] as $kunci => $otomatis) {
            if (!array_key_exists($kunci, $masuk)) {
                continue;
            }
            $v = rapb_angka_post($masuk[$kunci]);
            if (abs($v - round($otomatis)) >= 0.5) {
                $manual[$kunci] = $v;
            }
        }
        $lamaManual = rapb_rekap_manual($ta, $kunciRekap, $basis);
        if (rapb_rekap_simpan_manual($ta, $kunciRekap, $basis, $manual)) {
            rapb_jejak_catat('r|' . $ta . '|' . $kunciRekap . '|' . $basis, $lamaManual, $manual);
            catat_log('simpan_rekap_upps', 'rapb_format', 0, $fakInfo['singkat'] . ' ' . rapb_ta_label($ta));
            flash('Rekap RAPB ' . $fakInfo['nama'] . ' tahun anggaran ' . rapb_ta_label($ta) . ' tersimpan.');
        } else {
            flash('Rekap tidak dapat disimpan di server.', 'galat');
        }
    } elseif ($op === 'reset_rekap') {
        rapb_jejak_hapus('r|' . $ta . '|' . $kunciRekap . '|' . $basis);
        rapb_rekap_simpan_manual($ta, $kunciRekap, $basis, []);
        catat_log('reset_rekap_upps', 'rapb_format', 0, $fakInfo['singkat'] . ' ' . rapb_ta_label($ta));
        flash('Rekap dikembalikan ke hasil penjumlahan RAPB program studi.');
    } elseif ($op === 'impor_xlsx') {
        $baca = xlsx_baca_unggahan($_FILES['berkas'] ?? [], $h === 'rapb_prodi' ? ['rapb'] : ($h === 'rapb_upps' ? ['rekap', 'upps'] : ['lkps']));
        if (isset($baca['galat'])) {
            flash($baca['galat'], 'galat');
            kembali_ke($h, $param);
        }
        if ($h === 'rapb_prodi') {
            $impor = rapb_impor_prodi($baca['data']);
            if (!$impor) {
                flash('Tidak ada baris kode mata anggaran yang dikenali. Pastikan kolom A berisi kode (misalnya 210101), kolom C Jumlah, kolom F Gasal, dan kolom G Genap.', 'warn');
                kembali_ke($h, $param);
            }
            $lama = rapb_data_prodi($ta, $prodi) ?? [];
            $nilai = (array) ($lama['nilai'] ?? []);
            foreach ($impor as $kode => $v) {
                if ($v[0] || $v[1] || $v[2]) {
                    $nilai[$kode] = $v;
                } else {
                    unset($nilai[$kode]);
                }
            }
            $tautan = array_intersect_key(rapb_impor_tautan($baca['data']) + (array) ($lama['tautan'] ?? []), rapb_item_semua());
            rapb_simpan_prodi($ta, $prodi, ['nilai' => $nilai, 'tautan' => $tautan, 'realisasi_per' => $lama['realisasi_per'] ?? '', 'diubah' => date('Y-m-d H:i:s'), 'oleh' => $oleh]);
            rapb_jejak_catat('p|' . $ta . '|' . $prodi, rapb_jejak_peta_prodi($lama ?: null), rapb_jejak_peta_prodi(['nilai' => $nilai, 'tautan' => $tautan]));
            catat_log('impor_rapb_prodi', 'rapb_format', 0, $prodiInfo['nama'] . ' ' . rapb_ta_label($ta) . ' (' . count($impor) . ' baris)');
            flash(count($impor) . ' mata anggaran berhasil diimpor ke RAPB ' . $prodiInfo['nama'] . ' tahun anggaran ' . rapb_ta_label($ta) . '.');
        } elseif ($h === 'rapb_upps') {
            $sus = rapb_rekap_susun($ta, $fak, $basis, $prodiRekap);
            $impor = rapb_impor_rekap($baca['data'], $sus);
            if (!$impor) {
                flash('Tidak ada baris uraian rekap yang dikenali di file tersebut.', 'warn');
                kembali_ke($h, $param);
            }
            $manual = rapb_rekap_manual($ta, $kunciRekap, $basis);
            foreach ($impor as $kunci => $v) {
                if (abs($v - round($sus['hitung'][$kunci] ?? 0)) >= 0.5) {
                    $manual[$kunci] = $v;
                } else {
                    unset($manual[$kunci]);
                }
            }
            $lamaManualImpor = rapb_rekap_manual($ta, $kunciRekap, $basis);
            rapb_rekap_simpan_manual($ta, $kunciRekap, $basis, $manual);
            rapb_jejak_catat('r|' . $ta . '|' . $kunciRekap . '|' . $basis, $lamaManualImpor, $manual);
            catat_log('impor_rekap_upps', 'rapb_format', 0, $fakInfo['singkat'] . ' ' . rapb_ta_label($ta) . ' (' . count($impor) . ' baris)');
            flash(count($impor) . ' baris rekap berhasil diimpor untuk ' . $fakInfo['nama'] . ' tahun anggaran ' . rapb_ta_label($ta) . '.');
        } else {
            $impor = lkps_impor($baca['data']);
            if (!$impor) {
                flash('Tidak ada baris jenis penggunaan LKPS yang dikenali di file tersebut.', 'warn');
                kembali_ke($h, $param);
            }
            $semua = lkps_data();
            $lamaLkps = $semua;
            $jumlahBaris = 0;
            foreach (['upps' => 'f:' . $fak, 'ps' => 'p:' . $prodi] as $sisi => $unit) {
                foreach ((array) ($impor[$sisi] ?? []) as $baris => $nilaiTs) {
                    $jumlahBaris++;
                    foreach ($taDaftarLkps as $i => $taL) {
                        $v = $nilaiTs[$i] ?? null;
                        if ($v === null) {
                            unset($semua[$taL][$unit][$baris]);
                        } else {
                            $semua[$taL][$unit][$baris] = max(0.0, $v);
                        }
                        if (isset($semua[$taL][$unit]) && !$semua[$taL][$unit]) {
                            unset($semua[$taL][$unit]);
                        }
                    }
                }
            }
            lkps_simpan($semua);
            foreach (['f:' . $fak, 'p:' . $prodi] as $unitJ) {
                rapb_jejak_catat('l|' . $unitJ, rapb_jejak_peta_lkps($lamaLkps, $unitJ, $taDaftarLkps), rapb_jejak_peta_lkps($semua, $unitJ, $taDaftarLkps));
            }
            catat_log('impor_lkps', 'rapb_format', 0, $prodiInfo['nama'] . ' TS ' . rapb_ta_label($ta));
            flash('Data LKPS berhasil diimpor (' . intdiv($jumlahBaris, 2) . ' jenis penggunaan) untuk ' . $prodiInfo['nama'] . ', TS ' . rapb_ta_label($ta) . '.');
        }
    } elseif ($op === 'simpan_lkps') {
        $semua = lkps_data();
        $lamaLkps = $semua;
        $masuk = ['f:' . $fak => (array) ($_POST['upps'] ?? []), 'p:' . $prodi => (array) ($_POST['ps'] ?? [])];
        foreach ($masuk as $unit => $isi) {
            foreach ($taDaftarLkps as $i => $taL) {
                foreach (LKPS_BARIS as $b) {
                    if ($b[0] !== 'item') {
                        continue;
                    }
                    $mentah = trim((string) ($isi[$b[1]][$i] ?? ''));
                    if ($mentah === '') {
                        unset($semua[$taL][$unit][$b[1]]);
                    } else {
                        $semua[$taL][$unit][$b[1]] = rapb_angka_post($mentah);
                    }
                }
                if (isset($semua[$taL][$unit]) && !$semua[$taL][$unit]) {
                    unset($semua[$taL][$unit]);
                }
            }
        }
        if (lkps_simpan($semua)) {
            foreach (['f:' . $fak, 'p:' . $prodi] as $unitJ) {
                rapb_jejak_catat('l|' . $unitJ, rapb_jejak_peta_lkps($lamaLkps, $unitJ, $taDaftarLkps), rapb_jejak_peta_lkps($semua, $unitJ, $taDaftarLkps));
            }
            catat_log('simpan_lkps', 'rapb_format', 0, $prodiInfo['nama'] . ' TS ' . rapb_ta_label($ta));
            flash('Data LKPS penggunaan dana tersimpan.');
        } else {
            flash('Data LKPS tidak dapat disimpan di server.', 'galat');
        }
    } elseif ($op === 'kosongkan_lkps') {
        $semua = lkps_data();
        $lamaLkps = $semua;
        foreach ($taDaftarLkps as $taL) {
            unset($semua[$taL]['p:' . $prodi]);
        }
        lkps_simpan($semua);
        rapb_jejak_catat('l|p:' . $prodi, rapb_jejak_peta_lkps($lamaLkps, 'p:' . $prodi, $taDaftarLkps), []);
        catat_log('kosongkan_lkps', 'rapb_format', 0, $prodiInfo['nama'] . ' TS ' . rapb_ta_label($ta));
        flash('Data LKPS program studi ' . $prodiInfo['nama'] . ' (TS-2 s.d. TS) dikosongkan.');
    }
    kembali_ke($h, $param);
}

if (($_GET['unduh'] ?? '') === 'xlsx') {
    if ($h === 'rapb_prodi') {
        xlsx_kirim('RAPB-' . $prodi . '-' . $ta . '.xlsx', [rapb_ekspor_prodi($ta, $prodi)]);
    } elseif ($h === 'rapb_upps') {
        $susE = rapb_rekap_susun($ta, $fak, $basis, $prodiRekap);
        $manualE = rapb_rekap_manual($ta, $kunciRekap, $basis);
        $nilaiE = [];
        foreach ($susE['hitung'] as $k => $v) {
            $nilaiE[$k] = array_key_exists($k, $manualE) ? (float) $manualE[$k] : $v;
        }
        xlsx_kirim('Rekap-RAPB-UPPS-' . $fakInfo['singkat'] . ($prodiRekap !== '' ? '-' . $prodiRekap : '') . '-' . $ta . ($basis === 'realisasi' ? '-realisasi' : '') . '.xlsx', [rapb_ekspor_rekap($ta, $fak, $basis, $susE, $nilaiE, $prodiRekap)]);
    } else {
        xlsx_kirim('LKPS-Penggunaan-Dana-' . $prodi . '-TS-' . $ta . '.xlsx', [lkps_ekspor($fak, $prodi, $taDaftarLkps, lkps_data())]);
    }
}

$tombolExcel = '<a class="btn btn-garis btn-kecil" href="' . e(url($h, $param + ['unduh' => 'xlsx'])) . '">' . ikon('unduh', 14) . ' Ekspor Excel</a>'
    . '<button class="btn btn-garis btn-kecil" type="button" data-buka="tiraiImpor">' . ikon('unggah', 14) . ' Impor Excel</button>';
$ketImpor = [
    'rapb_prodi' => 'Data diimpor ke RAPB <b>' . e($prodiInfo['nama']) . '</b>, tahun anggaran <b>' . e(rapb_ta_label($ta)) . '</b>. Kolom yang dibaca: A Kode mata anggaran, C Jumlah, F Gasal, G Genap (sama dengan format file RAPB dan hasil Ekspor Excel). Mata anggaran yang ada di file akan menimpa data sebelumnya.',
    'rapb_upps'  => 'Data diimpor ke Rekap RAPB UPPS <b>' . e($fakInfo['nama']) . '</b>, tahun anggaran <b>' . e(rapb_ta_label($ta)) . '</b>. Baris dikenali dari uraiannya (kolom B dan E) dan nilainya dari kolom C dan F, sama dengan format Rekap RAPB UPPS dan hasil Ekspor Excel.',
    'rapb_lkps'  => 'Data diimpor ke LKPS <b>' . e($prodiInfo['nama']) . '</b> (UPPS ' . e($fakInfo['singkat']) . '), TS = <b>' . e(rapb_ta_label($ta)) . '</b>. Baris dikenali dari jenis penggunaan (kolom B). Kolom C-E dibaca sebagai UPPS TS-2, TS-1, TS dan kolom G-I sebagai Program Studi TS-2, TS-1, TS.',
][$h];

$ubah = $h === 'rapb_prodi' || ($_GET['mode'] ?? '') === 'ubah';
$subjudul = $h === 'rapb_upps' ? $fakInfo['nama'] . ' · TA ' . rapb_ta_label($ta) : $prodiInfo['nama'] . ' · TA ' . rapb_ta_label($ta);

$saringan = static function (bool $denganProdi, bool $denganBasis = false, bool $denganSemua = false) use ($h, $fak, $prodi, $prodiRekap, $prodiFak, $ta, $basis): string {
    $o = '<form method="get" class="rapb-saring no-cetak"><input type="hidden" name="h" value="' . e($h) . '">'
       . '<div class="form-baris"><label class="fl" for="rfFak">Fakultas</label><select id="rfFak" name="fakultas" data-auto-kirim>';
    foreach (RAPB_FAKULTAS as $k => $f) {
        $o .= '<option value="' . e($k) . '"' . ($k === $fak ? ' selected' : '') . '>' . e($f['singkat'] . ' - ' . $f['nama']) . '</option>';
    }
    $o .= '</select></div>';
    if ($denganProdi) {
        $o .= '<div class="form-baris"><label class="fl" for="rfProdi">Program studi</label><select id="rfProdi" name="prodi" data-auto-kirim>';
        $pilih = $denganSemua ? $prodiRekap : $prodi;
        if ($denganSemua) {
            $o .= '<option value=""' . ($pilih === '' ? ' selected' : '') . '>Semua program studi</option>';
        }
        foreach ($prodiFak as $k) {
            $o .= '<option value="' . e($k) . '"' . ($k === $pilih ? ' selected' : '') . '>' . e(RAPB_PRODI[$k]['nama']) . '</option>';
        }
        $o .= '</select></div>';
    }
    $o .= '<div class="form-baris"><label class="fl" for="rfTa">' . ($h === 'rapb_lkps' ? 'Tahun akademik (TS)' : 'Tahun anggaran') . '</label><select id="rfTa" name="ta" data-auto-kirim>';
    foreach (RAPB_TAHUN_AKADEMIK as $t) {
        $o .= '<option value="' . e($t) . '"' . ($t === $ta ? ' selected' : '') . '>' . e(rapb_ta_label($t)) . '</option>';
    }
    $o .= '</select></div>';
    if ($denganBasis) {
        $o .= '<div class="form-baris"><label class="fl" for="rfBasis">Nilai yang direkap</label><select id="rfBasis" name="basis" data-auto-kirim>'
            . '<option value="jumlah"' . ($basis === 'jumlah' ? ' selected' : '') . '>Jumlah anggaran (rencana)</option>'
            . '<option value="realisasi"' . ($basis === 'realisasi' ? ' selected' : '') . '>Realisasi</option></select></div>';
    }
    return $o . '<noscript><button class="btn btn-garis" type="submit">Terapkan</button></noscript></form>';
};

echo rapb_subnav($h);
?>

<?php if ($h === 'rapb_prodi'): ?>
<?php
$data = rapb_data_prodi($ta, $prodi);
$grup = rapb_hitung_grup($data);
$adaTaLalu = (bool) rapb_data_prodi(rapb_ta_geser($ta, -1), $prodi);
$per = $data['realisasi_per'] ?? '';
$taLabel = rapb_ta_label($ta);
$sel = static function (?float $n, string $kelas = ''): string {
    return '<td class="uang' . ($kelas ? ' ' . $kelas : '') . '">' . rapb_uang($n, true) . '</td>';
};
$jejakProdi = rapb_jejak_ruang('p|' . $ta . '|' . $prodi);
$barisItem = static function (string $kode, string $nama, string $grupKode) use ($data, $ubah, $sel, $jejakProdi): string {
    $n = rapb_nilai_item($data, $kode);
    $o = '<tr class="r-item lipat" data-grup="' . e($grupKode) . '"><td class="r-kode">' . e($kode) . '</td><td class="r-nama">' . e($nama) . '</td>';
    if ($ubah) {
        $inp = static function (int $i, float $v) use ($kode): string {
            $label = ['Jumlah', 'Gasal', 'Genap'][$i];
            return '<td class="r-isi"><label class="rp-isi"><i>Rp</i><input type="text" inputmode="numeric" name="n[' . e($kode) . '][' . $i . ']" value="' . ($v ? number_format($v, 0, ',', '.') : '') . '" placeholder="0" data-uang data-kolom="' . $i . '" aria-label="' . $label . ' ' . e($kode) . '"></label></td>';
        };
        $hasil = static function (string $kunci, float $v): string {
            return '<td class="r-isi r-hasil' . ($v <= -0.5 ? ' minus' : '') . '" data-keluar="' . $kunci . '"><span class="rp-isi rp-hasil" title="Dihitung otomatis"><i>Rp</i><output>' . rapb_rp($v, false) . '</output></span></td>';
        };
        $o .= $inp(0, $n['jumlah']) . $hasil('realisasi', $n['realisasi']) . $hasil('sisa', $n['sisa']) . $inp(1, $n['gasal']) . $inp(2, $n['genap']);
    } else {
        $o .= $sel($n['jumlah']) . $sel($n['realisasi']) . $sel($n['sisa'], $n['sisa'] < 0 ? 'minus' : '') . $sel($n['gasal']) . $sel($n['genap']);
    }
    $link = (string) ($data['tautan'][$kode] ?? '');
    $o .= '<td class="r-link no-cetak"><div class="link-isi"><input type="text" inputmode="url" autocomplete="off" spellcheck="false" name="tautan[' . e($kode) . ']" value="' . e($link) . '" placeholder="https://..." maxlength="500" data-tautan aria-label="Link dokumen ' . e($kode) . '">'
        . ($link !== '' ? '<a class="link-buka" href="' . e($link) . '" target="_blank" rel="noopener" title="Buka dokumen">' . ikon('tautan', 14) . '</a>' : '') . '</div>'
        . '<span class="link-cetak">' . e($link) . '</span></td>';
    $adaIsi = $link !== '' || isset($data['nilai'][$kode]);
    return $o . rapb_jejak_sel($jejakProdi[$kode] ?? null, $adaIsi) . '</tr>';
};
$barisTotal = static function (string $label, array $grupDaftar, string $kelas) use ($grup): string {
    $t = rapb_jumlah_grup($grup, $grupDaftar);
    $o = '<tr class="' . $kelas . '" data-total="' . e(implode(' ', $grupDaftar)) . '"><td colspan="2">' . e($label) . '</td>';
    foreach (['jumlah', 'realisasi', 'sisa', 'gasal', 'genap'] as $k) {
        $o .= '<td class="uang" data-keluar="' . $k . '">' . rapb_rp($t[$k]) . '</td>';
    }
    return $o . '<td class="r-link no-cetak"></td><td class="no-cetak"></td></tr>';
};
$barisGrup = static function (string $gkey, string $kode, string $nama, int $jumlahItem) use ($grup): string {
    $t = rapb_jumlah_grup($grup, [$gkey]);
    $o = '<tr class="r-grup" data-total="' . e($gkey) . '" data-lipat="' . e($gkey) . '" tabindex="0" role="button" aria-expanded="false" title="Klik untuk membuka/menutup rincian">'
       . '<td class="r-kode">' . e($kode) . '</td><td class="r-grup-nama"><span class="r-panah" aria-hidden="true"></span>' . e($nama) . ' <span class="r-hitung">' . $jumlahItem . ' mata anggaran</span></td>';
    foreach (['jumlah', 'realisasi', 'sisa', 'gasal', 'genap'] as $k) {
        $o .= '<td class="uang" data-keluar="' . $k . '">' . rapb_rp($t[$k]) . '</td>';
    }
    return $o . '<td class="r-link no-cetak"></td><td class="no-cetak"></td></tr>';
};
?>
<div class="kartu rapb-alat no-cetak">
  <div class="kartu-isi">
    <?= $saringan(true) ?>
    <div class="rapb-aksi">
      <div class="aksi-baris">
          <?php if (!$data && $adaTaLalu): ?>
            <form method="post" class="sebaris" data-konfirmasi="Salin jumlah anggaran dari tahun anggaran <?= e(rapb_ta_label(rapb_ta_geser($ta, -1))) ?>?"><?= csrf_field() ?>
              <input type="hidden" name="op" value="salin_rapb"><input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="prodi" value="<?= e($prodi) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>">
              <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('ulang', 14) ?> Salin dari <?= e(rapb_ta_label(rapb_ta_geser($ta, -1))) ?></button></form>
          <?php endif; ?>
          <button class="btn btn-utama btn-kecil" type="submit" form="formRapb"><?= ikon('simpan', 14) ?> Simpan anggaran</button>
          <button class="btn btn-garis btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak</button>
          <?= $tombolExcel ?>
          <?php if ($data): ?>
            <form method="post" class="sebaris" data-konfirmasi="Hapus seluruh data RAPB <?= e($prodiInfo['nama']) ?> tahun anggaran <?= e($taLabel) ?>?"><?= csrf_field() ?>
              <input type="hidden" name="op" value="hapus_rapb"><input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="prodi" value="<?= e($prodi) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>">
              <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus data RAPB"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<form method="post" id="formRapb"<?= $ubah ? ' data-rapb-hitung' : '' ?>>
  <?= csrf_field() ?><input type="hidden" name="op" value="simpan_rapb">
  <input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="prodi" value="<?= e($prodi) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>">
  <div class="kartu lembar-rapb">
    <?= rapb_kop('RENCANA ANGGARAN PENDAPATAN DAN BELANJA', [mb_strtoupper($fakInfo['nama']) . ' (PRODI: ' . mb_strtoupper($prodiInfo['nama']) . ')', 'TAHUN ANGGARAN ' . $taLabel]) ?>
    <div class="rapb-lipat-alat no-cetak">
      <button class="btn btn-garis btn-kecil" type="button" data-lipat-semua="buka"><?= ikon('tambah', 13) ?> Buka semua</button>
      <button class="btn btn-garis btn-kecil" type="button" data-lipat-semua="tutup"><?= ikon('kurang', 13) ?: ikon('tutup', 13) ?> Tutup semua</button>
    </div>
    <div class="tabel-bungkus"><table class="tabel tabel-rapb-format">
      <thead>
        <tr><th>Kode Mata Anggaran</th><th>Nama Mata Anggaran</th><th>Jumlah (Rp)</th><th>Realisasi<span class="sub-judul"><?= $per ? 'per ' . e(tanggal_pendek($per)) : 'Gasal + Genap' ?></span></th>
          <th>Sisa Anggaran</th><th>Gasal<span class="sub-judul"><?= e($taLabel) ?></span></th><th>Genap<span class="sub-judul"><?= e($taLabel) ?></span></th><th class="kolom-link no-cetak">Link Dokumen</th><th class="no-cetak kolom-jejak">Diinput oleh</th></tr>
      </thead>
      <tbody>
        <tr class="r-bab"><td colspan="9">PENDAPATAN</td></tr>
        <?php foreach (RAPB_PENDAPATAN as [$g, $kodeG, $namaG, , $items]): ?>
          <?= $barisGrup($g, $kodeG, $namaG, count($items)) ?>
          <?php foreach ($items as [$kode, $nama]): ?><?= $barisItem($kode, $nama, $g) ?><?php endforeach; ?>
        <?php endforeach; ?>
        <?= $barisTotal('TOTAL PENDAPATAN', RAPB_KELOMPOK_TOTAL['pendapatan'], 'r-total-besar') ?>
        <tr class="r-bab"><td colspan="9">BELANJA</td></tr>
        <?php foreach (RAPB_BELANJA as $b): ?>
          <?php if ($b[0] === 'h'): ?>
            <tr class="r-kepala"><td class="r-kode"><?= e($b[1]) ?></td><td colspan="8"><?= e($b[2]) ?></td></tr>
          <?php elseif ($b[0] === 'g'): ?>
            <?= $barisGrup($b[1], $b[2], $b[3], count($b[4])) ?>
            <?php foreach ($b[4] as [$kode, $nama]): ?><?= $barisItem($kode, $nama, $b[1]) ?><?php endforeach; ?>
          <?php elseif ($b[0] === 't'): ?>
            <?= $barisTotal($b[1], RAPB_KELOMPOK_TOTAL[$b[2]], 'r-total') ?>
          <?php else: ?>
            <?= $barisTotal($b[1], RAPB_KELOMPOK_TOTAL[$b[2]], 'r-total-besar') ?>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="rapb-ttd">
      <div>Batam, ..............................</div>
      <div><?= e($fakInfo['nama']) ?></div>
      <div>Dekan,</div>
      <div class="rapb-ttd-ruang"></div>
      <div class="rapb-ttd-nama"><?= e($fakInfo['dekan']) ?></div>
      <div class="rapb-ttd-nuptk">NUPTK. <?= e($fakInfo['nuptk']) ?></div>
    </div>
    <?php if ($ubah): ?>
      <div class="rapb-kaki-form rapb-kaki-lekat no-cetak">
        <a class="btn btn-garis" href="<?= e(url('rapb_prodi', $param)) ?>">Batalkan perubahan</a>
        <button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan anggaran</button>
      </div>
    <?php endif; ?>
  </div>
</form>

<?php elseif ($h === 'rapb_upps'): ?>
<?php
$sus = rapb_rekap_susun($ta, $fak, $basis, $prodiRekap);
$gab = ['terisi' => $sus['terisi']];
$manual = rapb_rekap_manual($ta, $kunciRekap, $basis);
$jejakRekap = rapb_jejak_ruang('r|' . $ta . '|' . $kunciRekap . '|' . $basis);
$selRekap = static function (?array $kr, ?array $kn) use ($jejakRekap): string {
    $kunci = [];
    foreach ([$kr, $kn] as $r) {
        if ($r && $r[0] === 'item') {
            $kunci[] = $r[3];
        }
    }
    if (!$kunci) {
        return '<td class="no-cetak kecil sel-penginput"></td>';
    }
    $terbaru = null;
    foreach ($kunci as $k) {
        $e = $jejakRekap[$k] ?? null;
        $w = $e ? (string) (($e['ubah']['waktu'] ?? '') ?: ($e['tambah']['waktu'] ?? '')) : '';
        if ($e && ($terbaru === null || $w > $terbaru[0])) {
            $terbaru = [$w, $e];
        }
    }
    return $terbaru ? '<td class="no-cetak kecil sel-penginput">' . aset_sel_penginput($terbaru[1]) . '</td>' : '<td class="no-cetak kecil sel-penginput"><span class="lemah kecil">Otomatis</span></td>';
};
$nilai = [];
foreach ($sus['hitung'] as $k => $v) {
    $nilai[$k] = array_key_exists($k, $manual) ? (float) $manual[$k] : $v;
}
$jumlahKunci = static function (array $kunci) use ($nilai): float {
    $s = 0.0;
    foreach ($kunci as $k) {
        $s += $nilai[$k] ?? 0;
    }
    return $s;
};
$kotakJumlah = static function (array $kunci, string $kelas) use ($jumlahKunci): string {
    return '<td class="r-isi r-hasil ' . $kelas . '" data-rekap-jumlah="' . e(implode(' ', $kunci)) . '"><span class="rp-isi rp-hasil" title="Dihitung otomatis"><i>Rp</i><output>' . rapb_rp($jumlahKunci($kunci), false) . '</output></span></td>';
};
$kiri = $sus['kiri'];
$kanan = $sus['kanan'];
$barisMaks = max(count($kiri), count($kanan));
$sisi = static function (?array $r) use ($nilai, $manual, $kotakJumlah): string {
    if (!$r || $r[0] === 'kosong') {
        return '<td colspan="3" class="u-kosong"></td>';
    }
    switch ($r[0]) {
        case 'bab':
            return '<td class="u-no u-bab">' . e($r[1]) . '</td><td class="u-bab">' . e($r[2]) . '</td><td></td>';
        case 'sub':
            return '<td class="u-no u-sub">' . e($r[1]) . '</td><td class="u-sub">' . e($r[2]) . '</td><td></td>';
        case 'label':
            return '<td colspan="2" class="u-label">' . e($r[1]) . '</td><td></td>';
        case 'item':
            $k = $r[3];
            $v = $nilai[$k] ?? 0;
            $diubah = array_key_exists($k, $manual);
            return '<td class="u-no">' . e($r[1]) . '</td><td>' . e($r[2]) . '</td>'
                 . '<td class="r-isi"><label class="rp-isi' . ($diubah ? ' manual' : '') . '" title="' . ($diubah ? 'Diubah manual. Hasil RAPB prodi: ' . e(rapb_rp($r[4])) : 'Otomatis dari RAPB program studi, bisa diubah') . '"><i>Rp</i>'
                 . '<input type="text" inputmode="numeric" name="rk[' . e($k) . ']" value="' . (abs($v) < 0.5 ? '' : number_format($v, 0, ',', '.')) . '" placeholder="0" data-uang data-rekap="' . e($k) . '" aria-label="' . e($r[2]) . '"></label></td>';
        case 'jumlah':
            return '<td colspan="2" class="u-jumlah">' . e($r[1]) . '</td>' . $kotakJumlah($r[2], 'u-jumlah');
        default:
            return '<td colspan="2" class="u-total">' . e($r[1]) . '</td>' . $kotakJumlah($r[2], 'u-total');
    }
};
?>
<div class="kartu rapb-alat no-cetak">
  <div class="kartu-isi">
    <?= $saringan(true, true, true) ?>
    <div class="rapb-aksi">
      <div class="aksi-baris">
        <button class="btn btn-utama btn-kecil" type="submit" form="formRekap"><?= ikon('simpan', 14) ?> Simpan rekap</button>
        <button class="btn btn-garis btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak rekap</button>
        <?= $tombolExcel ?>
        <?php if ($manual): ?>
          <form method="post" class="sebaris" data-konfirmasi="Kembalikan semua angka rekap ke hasil penjumlahan RAPB program studi? Angka yang diubah manual akan hilang."><?= csrf_field() ?>
            <input type="hidden" name="op" value="reset_rekap"><input type="hidden" name="prodi" value="<?= e($prodiRekap) ?>"><input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>"><input type="hidden" name="basis" value="<?= e($basis) ?>">
            <button class="btn btn-garis btn-kecil" type="submit" title="Kembalikan ke hasil RAPB prodi"><?= ikon('ulang', 14) ?> Kembalikan otomatis</button></form>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<form method="post" id="formRekap" data-rapb-hitung data-rekap-hitung>
  <?= csrf_field() ?><input type="hidden" name="op" value="simpan_rekap"><input type="hidden" name="prodi" value="<?= e($prodiRekap) ?>">
  <input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>"><input type="hidden" name="basis" value="<?= e($basis) ?>">
<div class="kartu lembar-rapb">
  <?= rapb_kop('RENCANA ANGGARAN PENDAPATAN DAN BELANJA', [mb_strtoupper($fakInfo['nama']) . ($prodiRekap !== '' ? ' (PRODI: ' . mb_strtoupper(RAPB_PRODI[$prodiRekap]['nama']) . ')' : ''), 'TAHUN ANGGARAN ' . rapb_ta_label($ta), $basis === 'realisasi' ? '(BERDASARKAN REALISASI)' : '']) ?>
  <div class="tabel-bungkus"><table class="tabel tabel-rekap-upps">
    <thead><tr><th>No.</th><th>U R A I A N</th><th>JUMLAH</th><th>No.</th><th>U R A I A N</th><th>JUMLAH</th><th class="no-cetak kolom-jejak">Diinput oleh</th></tr></thead>
    <tbody>
      <?php for ($i = 0; $i < $barisMaks; $i++): ?>
        <tr><?= $sisi($kiri[$i] ?? null) ?><?= preg_replace('/^<td/', '<td data-sisi-kanan', $sisi($kanan[$i] ?? null)) ?><?= $selRekap($kiri[$i] ?? null, $kanan[$i] ?? null) ?></tr>
      <?php endfor; ?>
    </tbody>
    <tfoot><tr><td colspan="2" class="u-gabung">T o t a l &nbsp; P e n d a p a t a n</td><?= $kotakJumlah($sus['kunci_kiri'], 'u-total') ?>
      <td colspan="2" class="u-gabung" data-sisi-kanan>T o t a l &nbsp; B e l a n j a</td><?= $kotakJumlah($sus['kunci_kanan'], 'u-total') ?><td class="no-cetak"></td></tr></tfoot>
  </table></div>
  <div class="rapb-ttd">
    <div>Batam, ..............................</div>
    <div><?= e($fakInfo['nama']) ?></div>
    <div>Dekan,</div>
    <div class="rapb-ttd-ruang"></div>
    <div class="rapb-ttd-nama"><?= e($fakInfo['dekan']) ?></div>
    <div class="rapb-ttd-nuptk">NUPTK. <?= e($fakInfo['nuptk']) ?></div>
  </div>
  <div class="rapb-kaki-form rapb-kaki-lekat no-cetak">
    <a class="btn btn-garis" href="<?= e(url('rapb_upps', $param)) ?>">Batalkan perubahan</a>
    <button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan rekap</button>
  </div>
</div>
</form>

<div class="kartu no-cetak">
  <div class="kartu-kepala"><h2><?= ikon('rekap', 18) ?> Rincian per program studi</h2></div>
  <div class="tabel-bungkus"><table class="tabel judul-tengah tabel-rekap-tengah">
    <thead><tr><th class="kolom-no">No</th><th>Program studi</th><th>Total pendapatan</th><th>Total belanja</th><th>Selisih</th><th>Realisasi belanja</th><th>Status</th><th class="kolom-aksi">Aksi</th><th>Diinput oleh</th></tr></thead>
    <tbody>
    <?php foreach ($prodiFak as $n => $k): $dp = rapb_data_prodi($ta, $k); $gp = rapb_hitung_grup($dp);
        $tp = rapb_jumlah_grup($gp, RAPB_KELOMPOK_TOTAL['pendapatan']); $tb = rapb_jumlah_grup($gp, RAPB_KELOMPOK_TOTAL['belanja']); $selisih = $tp['jumlah'] - $tb['jumlah']; ?>
      <tr>
        <td class="kolom-no"><?= $n + 1 ?></td>
        <td class="kolom-pegawai"><b><?= e(RAPB_PRODI[$k]['nama']) ?></b></td>
        <td class="uang"><?= rapb_uang($tp['jumlah'], true) ?></td>
        <td class="uang"><?= rapb_uang($tb['jumlah'], true) ?></td>
        <td class="uang<?= $selisih < 0 ? ' minus' : '' ?>"><?= rapb_uang($selisih, true) ?></td>
        <td><?= $tb['jumlah'] > 0 ? angka($tb['realisasi'] / $tb['jumlah'] * 100, 1) . '%' : '-' ?></td>
        <td><?= $dp ? lencana('Sudah diisi', 'hijau') : lencana('Belum diisi', 'abu') ?></td>
        <td><a class="btn btn-garis btn-kecil" href="<?= e(url('rapb_prodi', ['fakultas' => $fak, 'prodi' => $k, 'ta' => $ta])) ?>">Buka <?= ikon('kanan', 13) ?></a></td>
        <td class="kecil sel-penginput"><?php if ($dp && !empty($dp['oleh'])): ?><b><?= e($dp['oleh']) ?></b><div class="kecil lemah"><?= e(tanggal_pendek(substr((string) $dp['diubah'], 0, 10)) . ' ' . substr((string) $dp['diubah'], 11, 5)) ?></div><?php endif; ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</div>

<?php else: ?>
<?php
$semuaLkps = lkps_data();
$hUpps = lkps_hitung($semuaLkps, $taDaftarLkps, 'f:' . $fak);
$hPs = lkps_hitung($semuaLkps, $taDaftarLkps, 'p:' . $prodi);
$jejakF = rapb_jejak_ruang('l|f:' . $fak);
$jejakP = rapb_jejak_ruang('l|p:' . $prodi);
$selJejakLkps = static function (string $baris) use ($jejakF, $jejakP, $semuaLkps, $taDaftarLkps, $fak, $prodi): string {
    $ada = false;
    foreach ($taDaftarLkps as $taL) {
        if (isset($semuaLkps[$taL]['f:' . $fak][$baris]) || isset($semuaLkps[$taL]['p:' . $prodi][$baris])) {
            $ada = true;
        }
    }
    if (!$ada) {
        return '<td class="no-cetak kecil sel-penginput"></td>';
    }
    $w = static function (?array $e): string {
        return $e ? (string) (($e['ubah']['waktu'] ?? '') ?: ($e['tambah']['waktu'] ?? '')) : '';
    };
    $f = $jejakF[$baris] ?? null;
    $p = $jejakP[$baris] ?? null;
    $pilih = $f && (!$p || $w($f) > $w($p)) ? $f : $p;
    return '<td class="no-cetak kecil sel-penginput">' . aset_sel_penginput($pilih) . '</td>';
};
$adaLkps = false;
foreach ($taDaftarLkps as $taL) {
    if (!empty($semuaLkps[$taL]['p:' . $prodi]) || !empty($semuaLkps[$taL]['f:' . $fak])) {
        $adaLkps = true;
    }
}
$kotakHasil = static function (string $sisi, string $baris, string $i, float $v, string $kelas = ''): string {
    return '<td class="r-isi r-hasil' . ($kelas ? ' ' . $kelas : '') . '" data-lkps-keluar="' . $sisi . '" data-baris="' . e($baris) . '" data-i="' . $i . '">'
         . '<span class="rp-isi rp-hasil" title="Dihitung otomatis"><i>Rp</i><output>' . rapb_rp($v, false) . '</output></span></td>';
};
$selLkps = static function (array $hasil, string $baris, string $sisi, bool $isian) use ($kotakHasil): string {
    $o = '';
    for ($i = 0; $i < 3; $i++) {
        $v = $hasil[$baris][$i] ?? null;
        if ($isian) {
            $o .= '<td class="r-isi"><label class="rp-isi"><i>Rp</i><input type="text" inputmode="numeric" name="' . $sisi . '[' . e($baris) . '][' . $i . ']" value="' . ($v === null || abs($v) < 0.5 ? '' : number_format($v, 0, ',', '.')) . '" placeholder="0" data-uang data-lkps="' . $sisi . '" data-baris="' . e($baris) . '" data-i="' . $i . '" aria-label="' . ($sisi === 'upps' ? 'UPPS' : 'Program studi') . ' ' . e($baris) . ' ' . ['TS-2', 'TS-1', 'TS'][$i] . '"></label></td>';
        } else {
            $o .= $kotakHasil($sisi, $baris, (string) $i, (float) ($v ?? 0));
        }
    }
    return $o . $kotakHasil($sisi, $baris, 'rata', (float) ($hasil[$baris]['rata'] ?? 0), 'l-rata');
};
$defLkps = [];
foreach (LKPS_BARIS as $b) {
    if ($b[0] === 'item') {
        $defLkps['item'][] = $b[1];
    } elseif ($b[0] === 'jumlah' || $b[0] === 'total') {
        $defLkps['jumlah'][$b[1]] = $b[3];
    }
}
?>
<div class="kartu rapb-alat no-cetak">
  <div class="kartu-isi">
    <?= $saringan(true) ?>
    <div class="rapb-aksi">
      <div class="aksi-baris">
          <button class="btn btn-utama btn-kecil" type="submit" form="formLkps"><?= ikon('simpan', 14) ?> Simpan data</button>
          <button class="btn btn-garis btn-kecil" type="button" data-cetak><?= ikon('cetak', 14) ?> Cetak</button>
          <?= $tombolExcel ?>
          <?php if ($adaLkps): ?>
            <form method="post" class="sebaris" data-konfirmasi="Kosongkan data kolom Program Studi <?= e($prodiInfo['nama']) ?> untuk TS-2 s.d. TS? Data kolom UPPS tidak ikut dihapus."><?= csrf_field() ?>
              <input type="hidden" name="op" value="kosongkan_lkps"><input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="prodi" value="<?= e($prodi) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>">
              <button class="btn btn-hantu btn-ikon" type="submit" title="Kosongkan data program studi"><?= ikon('hapus', 15) ?></button></form>
          <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<form method="post" id="formLkps" data-rapb-hitung data-lkps-hitung='<?= e(json_encode($defLkps)) ?>'>
  <?= csrf_field() ?><input type="hidden" name="op" value="simpan_lkps">
  <input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="prodi" value="<?= e($prodi) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>">
  <div class="kartu lembar-rapb">
    <?= rapb_kop('PENGGUNAAN DANA', [mb_strtoupper($fakInfo['nama']) . ' (PRODI: ' . mb_strtoupper($prodiInfo['nama']) . ')', 'TS = TAHUN AKADEMIK ' . rapb_ta_label($ta)]) ?>
    <div class="tabel-bungkus"><table class="tabel tabel-lkps">
      <thead>
        <tr><th rowspan="2">No.</th><th rowspan="2">Jenis Penggunaan</th><th colspan="4">Unit Pengelola Program Studi (Rupiah)</th><th colspan="4">Program Studi (Rupiah)</th><th rowspan="3" class="no-cetak kolom-jejak">Diinput oleh</th></tr>
        <tr>
          <?php for ($s = 0; $s < 2; $s++): ?>
            <?php foreach (['TS-2', 'TS-1', 'TS'] as $i => $lbl): ?><th><?= $lbl ?><span class="sub-judul"><?= e(rapb_ta_label($taDaftarLkps[$i])) ?></span></th><?php endforeach; ?><th>Rata-rata</th>
          <?php endfor; ?>
        </tr>
        <tr class="l-nomor"><th>1</th><th>2</th><th>3</th><th>4</th><th>5</th><th>6</th><th>3</th><th>4</th><th>5</th><th>6</th></tr>
      </thead>
      <tbody>
        <?php
        $rentangNo = [];
        foreach (LKPS_BARIS as $i => $b) {
            if ($b[0] !== 'judul') {
                continue;
            }
            $n = 1;
            for ($j = $i + 1; isset(LKPS_BARIS[$j]) && LKPS_BARIS[$j][0] === 'item' && empty(LKPS_BARIS[$j][3]); $j++) {
                $n++;
            }
            $rentangNo[$i] = $n;
        }
        $ikutJudul = 0;
        ?>
        <?php foreach (LKPS_BARIS as $i => $b): ?>
          <?php if ($b[0] === 'judul'): $ikutJudul = $rentangNo[$i] - 1; ?>
            <tr class="l-judul"><td class="tengah l-no"<?= $rentangNo[$i] > 1 ? ' rowspan="' . $rentangNo[$i] . '"' : '' ?>><?= e($b[1]) ?></td><td colspan="9"><?= e($b[2]) ?></td><td class="no-cetak"></td></tr>
          <?php elseif ($b[0] === 'item'): $gabung = $ikutJudul > 0 && empty($b[3]); if ($gabung) { $ikutJudul--; } else { $ikutJudul = 0; } ?>
            <tr><?php if (!$gabung): ?><td class="tengah l-no"><?= e($b[3] ?? '') ?></td><?php endif; ?><td class="l-uraian"><?= e($b[2]) ?></td>
              <?= $selLkps($hUpps, $b[1], 'upps', true) ?><?= $selLkps($hPs, $b[1], 'ps', true) ?><?= $selJejakLkps($b[1]) ?></tr>
          <?php else: ?>
            <tr class="<?= $b[0] === 'total' ? 'l-total' : 'l-jumlah' ?>"><td colspan="2"><?= e($b[2]) ?></td>
              <?= $selLkps($hUpps, $b[1], 'upps', false) ?><?= $selLkps($hPs, $b[1], 'ps', false) ?><td class="no-cetak"></td></tr>
          <?php endif; ?>
        <?php endforeach; ?>
      </tbody>
    </table></div>
    <div class="rapb-kaki-form rapb-kaki-lekat no-cetak">
      <a class="btn btn-garis" href="<?= e(url('rapb_lkps', $param)) ?>">Batalkan perubahan</a>
      <button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan data</button>
    </div>
  </div>
</form>
<?php endif; ?>

<div class="tirai" id="tiraiImpor" hidden>
  <div class="modal sempit">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="impor_xlsx">
      <input type="hidden" name="fakultas" value="<?= e($fak) ?>"><input type="hidden" name="prodi" value="<?= e($h === 'rapb_upps' ? $prodiRekap : $prodi) ?>"><input type="hidden" name="ta" value="<?= e($ta) ?>"><input type="hidden" name="basis" value="<?= e($basis) ?>">
      <div class="modal-kepala"><h2><?= ikon('unggah', 17) ?> Impor dari Excel</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil" style="margin-top:0"><?= $ketImpor ?></p>
        <div class="form-baris"><label class="fl" for="berkasImpor">File Excel (.xlsx) atau CSV <span class="wajib">*</span></label>
          <input type="file" id="berkasImpor" name="berkas" accept=".xlsx,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,text/csv" required></div>
        <p class="bantuan mb0">Tips: klik <b>Ekspor Excel</b> untuk mendapatkan format yang tepat, isi angkanya di Excel, simpan, lalu impor kembali di sini. File .xls lama perlu disimpan ulang sebagai .xlsx.</p>
      </div>
      <div class="modal-kaki">
        <button type="button" class="btn btn-garis" data-tutup>Batal</button>
        <button type="submit" class="btn btn-utama"><?= ikon('unggah', 15) ?> Impor data</button>
      </div>
    </form>
  </div>
</div>