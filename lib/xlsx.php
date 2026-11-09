<?php

function baca_xlsx(string $berkas): array
{
    $z = new ZipArchive();
    if ($z->open($berkas) !== true) {
        throw new RuntimeException('Berkas Excel tidak dapat dibuka: ' . $berkas);
    }
    $ss = [];
    if (($x = $z->getFromName('xl/sharedStrings.xml')) !== false) {
        foreach (simplexml_load_string($x)->si as $si) {
            $t = '';
            if (isset($si->t)) {
                $t = (string) $si->t;
            } else {
                foreach ($si->r as $r) {
                    $t .= (string) $r->t;
                }
            }
            $ss[] = $t;
        }
    }
    $wb = simplexml_load_string($z->getFromName('xl/workbook.xml'));
    $rel = simplexml_load_string($z->getFromName('xl/_rels/workbook.xml.rels'));
    $target = [];
    foreach ($rel->Relationship as $r) {
        $target[(string) $r['Id']] = (string) $r['Target'];
    }
    $hasil = [];
    foreach ($wb->sheets->sheet as $sh) {
        $rid = (string) $sh->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')['id'];
        $path = ltrim($target[$rid], '/');
        $path = strpos($path, 'xl/') === 0 ? $path : 'xl/' . $path;
        $xml = simplexml_load_string($z->getFromName($path));
        $baris = [];
        foreach ($xml->sheetData->row as $row) {
            foreach ($row->c as $c) {
                if (!preg_match('/^([A-Z]+)(\d+)$/', (string) $c['r'], $m)) {
                    continue;
                }
                $v = isset($c->v) ? (string) $c->v : (isset($c->is) ? (string) $c->is->t : '');
                if ((string) $c['t'] === 's') {
                    $v = $ss[(int) $v] ?? '';
                }
                if (trim($v) !== '') {
                    $baris[(int) $m[2]][$m[1]] = $v;
                }
            }
        }
        $hasil[(string) $sh['name']] = $baris;
    }
    return $hasil;
}

function xlsx_tanggal($serial): ?string
{
    if (!is_numeric($serial) || (float) $serial < 20000 || (float) $serial > 80000) {
        return null;
    }
    return date('Y-m-d', (int) round(((float) $serial - 25569) * 86400));
}
