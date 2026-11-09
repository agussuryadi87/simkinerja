<?php

const XLSX_GAYA_BIASA = 0;
const XLSX_GAYA_JUDUL = 1;
const XLSX_GAYA_SUBJUDUL = 2;
const XLSX_GAYA_KEPALA = 3;
const XLSX_GAYA_TEKS = 4;
const XLSX_GAYA_ANGKA = 5;
const XLSX_GAYA_TEKS_TEBAL = 6;
const XLSX_GAYA_ANGKA_TEBAL = 7;
const XLSX_GAYA_TEKS_TOTAL = 8;
const XLSX_GAYA_ANGKA_TOTAL = 9;
const XLSX_GAYA_TENGAH = 10;
const XLSX_GAYA_RUMUS = 11;
const XLSX_GAYA_TEKS_KANAN = 12;

function xlsx_kolom(int $n): string
{
    $s = '';
    while ($n > 0) {
        $m = ($n - 1) % 26;
        $s = chr(65 + $m) . $s;
        $n = intdiv($n - 1, 26);
    }
    return $s;
}

function xlsx_indeks_kolom(string $huruf): int
{
    $n = 0;
    foreach (str_split(strtoupper($huruf)) as $c) {
        $n = $n * 26 + (ord($c) - 64);
    }
    return $n;
}

function xlsx_esc(string $s): string
{
    $s = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/', '', $s);
    return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function xlsx_gaya(): string
{
    $b = '<border><left style="thin"><color rgb="FF5A5A5A"/></left><right style="thin"><color rgb="FF5A5A5A"/></right><top style="thin"><color rgb="FF5A5A5A"/></top><bottom style="thin"><color rgb="FF5A5A5A"/></bottom><diagonal/></border>';
    $xf = static function (int $fmt, int $font, int $border, string $rata = '', bool $bungkus = false): string {
        return '<xf numFmtId="' . $fmt . '" fontId="' . $font . '" fillId="0" borderId="' . $border . '" xfId="0"'
             . ($fmt ? ' applyNumberFormat="1"' : '') . ' applyFont="1" applyBorder="1" applyAlignment="1"><alignment'
             . ($rata ? ' horizontal="' . $rata . '"' : '') . ' vertical="center"' . ($bungkus ? ' wrapText="1"' : '') . '/></xf>';
    };
    return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
        . '<styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
        . '<numFmts count="1"><numFmt numFmtId="164" formatCode="&quot;Rp&quot; #,##0;(&quot;Rp&quot; #,##0);&quot;Rp&quot; 0"/></numFmts>'
        . '<fonts count="4"><font><sz val="10"/><name val="Arial"/></font><font><b/><sz val="10"/><name val="Arial"/></font>'
        . '<font><b/><sz val="14"/><name val="Arial"/></font><font><b/><sz val="11"/><name val="Arial"/></font></fonts>'
        . '<fills count="2"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill></fills>'
        . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border>' . $b . '</borders>'
        . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
        . '<cellXfs count="13">'
        . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'
        . $xf(0, 2, 0, 'center')
        . $xf(0, 3, 0, 'center')
        . $xf(0, 1, 1, 'center', true)
        . $xf(0, 0, 1, '', true)
        . $xf(164, 0, 1)
        . $xf(0, 1, 1, '', true)
        . $xf(164, 1, 1)
        . $xf(0, 1, 1, '', true)
        . $xf(164, 1, 1)
        . $xf(0, 0, 1, 'center')
        . $xf(164, 0, 1)
        . $xf(0, 1, 1, 'right')
        . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
}
function xlsx_lembar_xml(array $l): string
{
    $o = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
       . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
       . '<sheetPr><pageSetUpPr fitToPage="1"/></sheetPr>';
    if (!empty($l['bekukan'])) {
        $o .= '<sheetViews><sheetView workbookViewId="0"><pane ySplit="' . (int) $l['bekukan'] . '" topLeftCell="A' . ((int) $l['bekukan'] + 1) . '" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>';
    }
    if (!empty($l['lebar'])) {
        $o .= '<cols>';
        foreach ($l['lebar'] as $kol => $w) {
            $i = xlsx_indeks_kolom($kol);
            $sembunyi = in_array($kol, $l['sembunyi'] ?? [], true);
            $o .= '<col min="' . $i . '" max="' . $i . '" width="' . (float) $w . '" customWidth="1"' . ($sembunyi ? ' hidden="1"' : '') . '/>';
        }
        $o .= '</cols>';
    }
    $o .= '<sheetData>';
    $grid = $l['grid'] ?? null;
    foreach ($l['baris'] as $r => $sel) {
        $nomor = $r + 1;
        if ($grid && $r >= $grid[0] && $r <= $grid[1]) {
            for ($c = 0; $c < $grid[2]; $c++) {
                if (!isset($sel[$c])) {
                    $sel[$c] = ['v' => '', 's' => XLSX_GAYA_TEKS];
                }
            }
            ksort($sel);
        }
        $tinggi = $l['tinggi'][$r] ?? null;
        $o .= '<row r="' . $nomor . '"' . ($tinggi ? ' ht="' . (float) $tinggi . '" customHeight="1"' : '') . '>';
        foreach ($sel as $c => $isi) {
            if ($isi === null) {
                continue;
            }
            $ref = xlsx_kolom($c + 1) . $nomor;
            if (!is_array($isi)) {
                $isi = ['v' => $isi];
            }
            $s = (int) ($isi['s'] ?? 0);
            $v = $isi['v'] ?? null;
            if (isset($isi['f'])) {
                $o .= '<c r="' . $ref . '" s="' . $s . '"><f>' . xlsx_esc(ltrim($isi['f'], '=')) . '</f>' . ($v !== null && $v !== '' ? '<v>' . xlsx_angka_tulis((float) $v) . '</v>' : '') . '</c>';
            } elseif ($v === null || $v === '') {
                $o .= '<c r="' . $ref . '" s="' . $s . '"/>';
            } elseif (is_int($v) || is_float($v)) {
                $o .= '<c r="' . $ref . '" s="' . $s . '"><v>' . xlsx_angka_tulis((float) $v) . '</v></c>';
            } else {
                $o .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . xlsx_esc((string) $v) . '</t></is></c>';
            }
        }
        $o .= '</row>';
    }
    $o .= '</sheetData>';
    if (!empty($l['gabung'])) {
        $o .= '<mergeCells count="' . count($l['gabung']) . '">';
        foreach ($l['gabung'] as $g) {
            $o .= '<mergeCell ref="' . xlsx_esc($g) . '"/>';
        }
        $o .= '</mergeCells>';
    }
    $o .= '<pageMargins left="0.4" right="0.4" top="0.5" bottom="0.5" header="0.3" footer="0.3"/>'
        . '<pageSetup paperSize="9" orientation="' . (($l['lanskap'] ?? false) ? 'landscape' : 'portrait') . '" fitToWidth="1" fitToHeight="0"/>';
    return $o . '</worksheet>';
}

function xlsx_buat(string $berkas, array $daftarLembar): bool
{
    $zip = new ZipArchive();
    if ($zip->open($berkas, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        return false;
    }
    $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
        . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
        . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
        . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>';
    $wb = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets>';
    $rels = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">';
    $judulCetak = [];
    foreach (array_values($daftarLembar) as $i => $l) {
        $n = $i + 1;
        $ct .= '<Override PartName="/xl/worksheets/sheet' . $n . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
        $wb .= '<sheet name="' . xlsx_esc(mb_substr($l['nama'], 0, 31)) . '" sheetId="' . $n . '" r:id="rId' . $n . '"/>';
        if (!empty($l['judul_cetak'])) {
            $judulCetak[] = '<definedName name="_xlnm.Print_Titles" localSheetId="' . $i . '">' . xlsx_esc("'" . str_replace("'", "''", mb_substr($l['nama'], 0, 31)) . "'!$" . $l['judul_cetak'][0] . ':$' . $l['judul_cetak'][1]) . '</definedName>';
        }
        $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $n . '.xml"/>';
        $zip->addFromString('xl/worksheets/sheet' . $n . '.xml', xlsx_lembar_xml($l));
    }
    $n = count($daftarLembar) + 1;
    $rels .= '<Relationship Id="rId' . $n . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/></Relationships>';
    $wb .= '</sheets>' . ($judulCetak ? '<definedNames>' . implode('', $judulCetak) . '</definedNames>' : '') . '<calcPr calcId="191029" fullCalcOnLoad="1"/></workbook>';
    $zip->addFromString('[Content_Types].xml', $ct . '</Types>');
    $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
    $zip->addFromString('xl/workbook.xml', $wb);
    $zip->addFromString('xl/_rels/workbook.xml.rels', $rels);
    $zip->addFromString('xl/styles.xml', xlsx_gaya());
    return $zip->close();
}

function xlsx_kirim(string $namaFile, array $daftarLembar): void
{
    $sementara = tempnam(sys_get_temp_dir(), 'xlsx');
    if (!$sementara || !xlsx_buat($sementara, $daftarLembar)) {
        http_response_code(500);
        exit('File Excel tidak dapat dibuat.');
    }
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]+/', '-', $namaFile) . '"');
    header('Content-Length: ' . filesize($sementara));
    header('Cache-Control: no-store');
    readfile($sementara);
    @unlink($sementara);
    exit;
}

function xlsx_baca(string $berkas, array $namaLembarDisukai = []): ?array
{
    $zip = new ZipArchive();
    if ($zip->open($berkas) !== true) {
        return null;
    }
    $bersama = [];
    $ss = $zip->getFromName('xl/sharedStrings.xml');
    if ($ss !== false) {
        $x = @simplexml_load_string($ss);
        if ($x) {
            foreach ($x->si as $si) {
                if (isset($si->t)) {
                    $bersama[] = (string) $si->t;
                } else {
                    $t = '';
                    foreach ($si->r as $r) {
                        $t .= (string) $r->t;
                    }
                    $bersama[] = $t;
                }
            }
        }
    }
    $target = null;
    $wb = @simplexml_load_string((string) $zip->getFromName('xl/workbook.xml'));
    $rel = @simplexml_load_string((string) $zip->getFromName('xl/_rels/workbook.xml.rels'));
    if ($wb && $rel) {
        $peta = [];
        foreach ($rel->Relationship as $r) {
            $peta[(string) $r['Id']] = (string) $r['Target'];
        }
        $lembar = [];
        foreach ($wb->sheets->sheet as $s) {
            $rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
            $lembar[mb_strtolower(trim((string) $s['name']))] = $peta[$rid] ?? null;
        }
        foreach ($namaLembarDisukai as $suka) {
            foreach ($lembar as $nama => $t) {
                if ($t && strpos($nama, mb_strtolower($suka)) !== false) {
                    $target = $t;
                    break 2;
                }
            }
        }
        if (!$target && $lembar) {
            $target = reset($lembar);
        }
    }
    $target = $target ?: 'worksheets/sheet1.xml';
    $target = ltrim(preg_replace('#^/?xl/#', '', $target), '/');
    $isi = $zip->getFromName('xl/' . $target);
    $zip->close();
    if ($isi === false) {
        return null;
    }
    $x = @simplexml_load_string($isi);
    if (!$x || !isset($x->sheetData)) {
        return null;
    }
    $hasil = [];
    foreach ($x->sheetData->row as $row) {
        $r = (int) $row['r'];
        foreach ($row->c as $c) {
            if (!preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m)) {
                continue;
            }
            $t = (string) $c['t'];
            if ($t === 's') {
                $v = $bersama[(int) $c->v] ?? '';
            } elseif ($t === 'inlineStr') {
                $v = (string) $c->is->t;
                if ($v === '' && isset($c->is->r)) {
                    foreach ($c->is->r as $rr) {
                        $v .= (string) $rr->t;
                    }
                }
            } else {
                $v = isset($c->v) ? (string) $c->v : '';
            }
            $hasil[(int) $m[2]][$m[1]] = $v;
        }
    }
    return $hasil;
}

function xlsx_baca_csv(string $berkas): ?array
{
    $teks = @file_get_contents($berkas);
    if ($teks === false) {
        return null;
    }
    $teks = preg_replace('/^\xEF\xBB\xBF/', '', $teks);
    $baris1 = strtok($teks, "\n");
    $pemisah = substr_count((string) $baris1, ';') >= substr_count((string) $baris1, ',') ? ';' : ',';
    $hasil = [];
    $f = fopen('php://memory', 'r+');
    fwrite($f, $teks);
    rewind($f);
    $n = 0;
    while (($data = fgetcsv($f, 0, $pemisah)) !== false) {
        $n++;
        foreach ($data as $i => $v) {
            $hasil[$n][xlsx_kolom($i + 1)] = $v;
        }
    }
    fclose($f);
    return $hasil;
}

function xlsx_baca_unggahan(array $berkas, array $namaLembarDisukai = []): array
{
    $kode = $berkas['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($kode === UPLOAD_ERR_NO_FILE) {
        return ['galat' => 'Pilih file Excel (.xlsx) atau CSV terlebih dahulu.'];
    }
    if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file($berkas['tmp_name'] ?? '')) {
        return ['galat' => 'File gagal diunggah' . ($kode === UPLOAD_ERR_INI_SIZE ? ' (terlalu besar)' : '') . '.'];
    }
    if ((int) $berkas['size'] > 5 * 1024 * 1024) {
        return ['galat' => 'Ukuran file maksimal 5 MB.'];
    }
    $ext = strtolower(pathinfo((string) $berkas['name'], PATHINFO_EXTENSION));
    if ($ext === 'xls') {
        return ['galat' => 'Format .xls lama belum didukung. Buka file di Excel lalu Simpan Sebagai "Excel Workbook (.xlsx)", kemudian impor ulang.'];
    }
    if ($ext === 'csv') {
        $data = xlsx_baca_csv($berkas['tmp_name']);
    } elseif ($ext === 'xlsx') {
        $awal = (string) @file_get_contents($berkas['tmp_name'], false, null, 0, 4);
        $data = strpos($awal, "PK") === 0 ? xlsx_baca($berkas['tmp_name'], $namaLembarDisukai) : null;
    } else {
        return ['galat' => 'Format file harus .xlsx atau .csv.'];
    }
    if (!$data) {
        return ['galat' => 'Isi file tidak dapat dibaca. Pastikan file Excel tidak rusak dan tidak terkunci kata sandi.'];
    }
    return ['data' => $data];
}

function xlsx_angka_tulis(float $n): string
{
    if (!is_finite($n)) {
        return '0';
    }
    if (abs($n - round($n)) < 0.000001) {
        return sprintf('%.0f', round($n));
    }
    return rtrim(rtrim(sprintf('%.6F', $n), '0'), '.');
}

function xlsx_angka($v): ?float
{
    if ($v === null) {
        return null;
    }
    $v = trim((string) $v);
    if ($v === '' || $v === '-' || $v === '–') {
        return null;
    }
    if (preg_match('/^-?\d{1,3}(\.\d{3})+$/', $v)) {
        return (float) str_replace('.', '', $v);
    }
    if (is_numeric($v)) {
        return round((float) $v, 2);
    }
    $negatif = (bool) preg_match('/^\(.*\)$|^-/', $v);
    $v = preg_replace('/[^0-9,.]/', '', $v);
    if ($v === '') {
        return null;
    }
    if (preg_match('/,\d{1,2}$/', $v)) {
        $v = str_replace(',', '.', str_replace('.', '', $v));
    } else {
        $v = str_replace([',', '.'], '', $v);
    }
    return is_numeric($v) ? ($negatif ? -1 : 1) * round((float) $v, 2) : null;
}

function xlsx_teks_banding(string $s): string
{
    return preg_replace('/[^a-z0-9]+/', '', mb_strtolower($s));
}
