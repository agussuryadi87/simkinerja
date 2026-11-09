<?php
$jenisKK = ['aset_kk_ruang' => 'ruang', 'aset_kk_ac' => 'ac', 'aset_kk_sarana' => 'sarana', 'aset_kk_rekap' => 'rekap'][$h];
$pengelola = kk_pengelola();
$bolehTugaskan = kk_boleh_tugaskan();
$saya = uid();
$tahunKini = (int) date('Y');
$sumber = $_SERVER['REQUEST_METHOD'] === 'POST' ? array_filter($_POST, 'is_string') : $_GET;
$tahun = (int) ($sumber['tahun'] ?? $tahunKini);
$tahun = $tahun >= 2020 && $tahun <= $tahunKini + 1 ? $tahun : $tahunKini;
$bulan = (int) ($sumber['bulan'] ?? date('n'));
$bulan = $bulan >= 1 && $bulan <= 12 ? $bulan : (int) date('n');
$ruangId = (int) ($sumber['ruang'] ?? 0);
$barangId = (int) ($sumber['barang'] ?? 0);
$ruangan = rows('SELECT * FROM aset_ruang WHERE aktif = 1 ORDER BY nama');
$petaRuang = array_column($ruangan, null, 'id');
if ($ruangId && !isset($petaRuang[$ruangId])) {
    $r = row('SELECT * FROM aset_ruang WHERE id = ?', [$ruangId]);
    $r ? $petaRuang[$ruangId] = $r : $ruangId = 0;
}
$unitAc = $jenisKK === 'ac' ? array_column(kk_unit_ac(), null, 'id') : [];
if ($jenisKK === 'ac' && $barangId && !isset($unitAc[$barangId])) {
    $barangId = 0;
}
$mgTampil = isset($sumber['mg']) ? (int) $sumber['mg'] : (!$pengelola && $bulan === (int) date('n') && $tahun === $tahunKini ? kk_minggu_ini() : 0);
$mgTampil = $mgTampil >= 0 && $mgTampil <= 4 ? $mgTampil : 0;
$balik = static function (array $tambah = []) use ($h, $tahun, $bulan, $ruangId, $barangId, $mgTampil, $sumber): void {
    kembali_ke($h, $tambah + array_filter(['tahun' => $tahun, 'bulan' => $bulan, 'ruang' => $ruangId ?: null, 'barang' => $barangId ?: null,
        'mg' => isset($sumber['mg']) ? (string) $mgTampil : null], static function ($v) { return $v !== null; }));
};
$penugasan = kk_penugasan($jenisKK === 'rekap' ? 'ruang' : $jenisKK);
$ruangSaya = kk_ruang_saya($penugasan);
$petugasCs = kk_petugas();
$hanyaTugas = !$pengelola && $jenisKK !== 'rekap';
if ($hanyaTugas) {
    $izinkan = array_flip($ruangSaya);
    if ($jenisKK === 'ac') {
        $unitAc = array_intersect_key($unitAc, $izinkan);
        if ($barangId && !isset($unitAc[$barangId])) {
            $barangId = 0;
        }
    } else {
        $ruangan = array_values(array_filter($ruangan, static function ($r) use ($izinkan) { return isset($izinkan[(int) $r['id']]); }));
        $petaRuang = array_intersect_key($petaRuang, $izinkan);
        if ($ruangId && !isset($petaRuang[$ruangId])) {
            $ruangId = 0;
        }
    }
}
$kosongTugas = 'Belum ada ' . ($jenisKK === 'ac' ? 'unit AC' : 'ruangan') . ' yang ditugaskan kepada Anda. Hubungi Ka. Bid Sarana dan Prasarana.';

$pilihTahun = [];
for ($t = $tahunKini + 1; $t >= max(2024, $tahunKini - 3); $t--) {
    $pilihTahun[(string) $t] = (string) $t;
}
$pilihBulan = [];
foreach (NAMA_BULAN as $i => $nm) {
    if ($i >= 1 && $i <= 12) {
        $pilihBulan[(string) $i] = $nm;
    }
}
$kunciBulan = (string) $bulan;
if ($jenisKK === 'rekap') {
    $semuaTugas = kk_penugasan_semua();
    $dRuang = kk_baca('ruang', $tahun);
    $dSarana = kk_baca('sarana', $tahun);
    $dAc = kk_baca('ac', $tahun);
    $semuaAc = array_column(kk_unit_ac(), null, 'id');
    $jmlBarang = [];
    foreach (rows("SELECT ruang_id, COUNT(*) AS n FROM aset_barang WHERE status = 'aktif' AND ruang_id IS NOT NULL GROUP BY ruang_id") as $x) {
        $jmlBarang[(int) $x['ruang_id']] = (int) $x['n'];
    }
    $daftarPetugas = $pengelola ? $petugasCs : array_intersect_key($petugasCs, [$saya => true]);
    if (!$pengelola && !$daftarPetugas) {
        $daftarPetugas = [$saya => (string) (user()['nama'] ?? '')];
    }
    $awalBulan = sprintf('%04d-%02d', $tahun, $bulan);
    $hariIni = date('Y-m-d');
    $bulanBerjalan = $bulan === (int) date('n') && $tahun === $tahunKini;
    $mingguKini = kk_minggu_ini();
    $aktivitas = kk_aktivitas($tahun, $pengelola ? $petugasCs : $daftarPetugas, 400);
    $terakhirPid = [];
    $aktifHariIni = [];
    foreach ($aktivitas as $a) {
        $terakhirPid[$a['pid']] = max($terakhirPid[$a['pid']] ?? '', $a['waktu']);
        if (strpos($a['waktu'], $hariIni) === 0) { $aktifHariIni[$a['pid']] = true; }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_check();
        $op = post('op');
        if ($op === 'paraf_massal' && $pengelola) {
            $catatan = kk_teks('catatan', 500);
            $kb = (string) $bulan;
            $n = 0;
            foreach (['ruang', 'sarana'] as $jenis) {
                $n += (int) kk_ubah($jenis, $tahun, static function (array &$d) use ($jenis, $bulan, $kb, $catatan): int {
                    $c = 0;
                    foreach ($d as $rid => $r) {
                        if (!empty($r['kontrol'][$kb])) { continue; }
                        $ada = false;
                        if ($jenis === 'ruang') {
                            foreach ((array) ($r['nilai'] ?? []) as $perSel) {
                                foreach (array_keys((array) $perSel) as $sel) {
                                    if (strpos((string) $sel, $bulan . '-') === 0) { $ada = true; break 2; }
                                }
                            }
                        } else {
                            foreach ((array) ($r['isi'] ?? []) as $perBulan) {
                                if (!empty($perBulan[$kb])) { $ada = true; break; }
                            }
                        }
                        if ($ada) {
                            $d[$rid]['kontrol'][$kb] = kk_tanda() + ['catatan' => $catatan];
                            $c++;
                        }
                    }
                    return $c;
                });
            }
            $n += (int) kk_ubah('ac', $tahun, static function (array &$d) use ($awalBulan): int {
                $c = 0;
                foreach ($d as $bid => $u) {
                    foreach ((array) ($u['baris'] ?? []) as $i => $b) {
                        if (empty($b['kontrol']) && strpos((string) $b['tgl'], $awalBulan) === 0) {
                            $d[$bid]['baris'][$i]['kontrol'] = kk_tanda();
                            $c++;
                        }
                    }
                }
                return $c;
            });
            catat_log('paraf_massal_kk', 'aset_ruang', 0, NAMA_BULAN[$bulan] . ' ' . $tahun . ': ' . $n);
            flash($n ? $n . ' kartu/pemeriksaan bulan ' . NAMA_BULAN[$bulan] . ' ' . $tahun . ' diparaf sekaligus.' : 'Tidak ada isian bulan ini yang menunggu paraf.', $n ? 'ok' : 'info');
        } elseif ($op === 'umpan' && $pengelola) {
            $target = (int) post('pegawai_id');
            $isi = mb_substr(trim(post_teks('isi')), 0, 4000);
            if (!isset($petugasCs[$target]) || $isi === '' || !pid()) {
                flash('Tulis isi umpan balik untuk petugas.', 'galat');
            } else {
                $uid = insert('umpan_balik', ['periode_id' => pid(), 'pegawai_id' => $target, 'pemberi_id' => $saya,
                    'jenis' => array_key_exists(post('jenis'), JENIS_UMPAN) ? post('jenis') : 'arahan', 'isi' => $isi]);
                catat_log('umpan_balik', 'umpan_balik', $uid, 'Monitoring CS');
                flash('Umpan balik terkirim ke <b>' . e($petugasCs[$target]) . '</b>.');
            }
        } else {
            tolak_akses();
        }
        kembali_ke($h, ['tahun' => $tahun, 'bulan' => $bulan]);
    }

    $rekapCs = [];
    foreach ($daftarPetugas as $pid => $nama) {
        $x = ['nama' => $nama, 'ruang' => [], 'sarana' => [], 'ac' => [], 'r_isi' => 0, 'r_target' => 0, 'r_paraf' => 0, 's_isi' => 0, 's_target' => 0, 's_paraf' => 0,
              'a_cek' => 0, 'kontribusi' => 0, 'belum_paraf' => 0];
        foreach ($semuaTugas['ruang'] as $rid => $ids) {
            if (!in_array($pid, $ids, true) || !isset($petaRuang[$rid])) { continue; }
            $minggu = [];
            for ($m = 1; $m <= 4; $m++) {
                $minggu[$m] = false;
                foreach (KK_URAIAN_RUANG as $no => $_) {
                    if (!empty($dRuang[(string) $rid]['nilai'][(string) $no][$bulan . '-' . $m])) { $minggu[$m] = true; break; }
                }
            }
            $x['ruang'][$rid] = ['nama' => $petaRuang[$rid]['nama'], 'minggu' => $minggu, 'paraf' => !empty($dRuang[(string) $rid]['kontrol'][$kunciBulan])];
            $x['r_target'] += 4;
            $x['r_isi'] += count(array_filter($minggu));
            $x['r_paraf'] += $x['ruang'][$rid]['paraf'] ? 1 : 0;
        }
        foreach ($semuaTugas['sarana'] as $rid => $ids) {
            if (!in_array($pid, $ids, true) || !isset($petaRuang[$rid])) { continue; }
            $isi = 0;
            foreach ((array) ($dSarana[(string) $rid]['isi'] ?? []) as $perBulan) {
                if (!empty($perBulan[$kunciBulan])) { $isi++; }
            }
            $x['sarana'][$rid] = ['nama' => $petaRuang[$rid]['nama'], 'isi' => $isi, 'total' => $jmlBarang[$rid] ?? 0, 'paraf' => !empty($dSarana[(string) $rid]['kontrol'][$kunciBulan])];
            $x['s_target'] += $jmlBarang[$rid] ?? 0;
            $x['s_isi'] += $isi;
            $x['s_paraf'] += $x['sarana'][$rid]['paraf'] ? 1 : 0;
        }
        foreach ($semuaTugas['ac'] as $bid => $ids) {
            if (!in_array($pid, $ids, true) || !isset($semuaAc[$bid])) { continue; }
            $cek = 0;
            foreach ((array) ($dAc[(string) $bid]['baris'] ?? []) as $b) {
                if (strpos($b['tgl'], $awalBulan) === 0) { $cek++; }
            }
            $x['ac'][$bid] = ['nama' => $semuaAc[$bid]['nama'] . ' · ' . ($semuaAc[$bid]['ruang_nama'] ?: '-'), 'cek' => $cek];
            $x['a_cek'] += $cek;
        }
        foreach ($aktivitas as $a) {
            if ($a['pid'] === $pid && strpos($a['waktu'], $awalBulan) === 0) { $x['kontribusi']++; }
        }
        foreach ($dAc as $u) {
            foreach ((array) ($u['baris'] ?? []) as $b) {
                if ((int) ($b['oleh']['id'] ?? 0) === $pid && empty($b['kontrol'])) { $x['belum_paraf']++; }
            }
        }
        $rekapCs[$pid] = $x;
    }

    $peringatan = ['ruang' => [], 'ac' => [], 'cs' => [], 'tanpa_pj' => []];
    if ($bulanBerjalan) {
        foreach ($petaRuang as $rid => $r) {
            if (!$r['aktif']) { continue; }
            $ada = false;
            foreach (KK_URAIAN_RUANG as $no => $_) {
                if (!empty($dRuang[(string) $rid]['nilai'][(string) $no][$bulan . '-' . $mingguKini])) { $ada = true; break; }
            }
            if (!$ada) { $peringatan['ruang'][$rid] = $r['nama'] . ' · PJ: ' . (kk_nama_petugas($semuaTugas['ruang'][$rid] ?? []) ?: 'belum ada'); }
        }
    }
    foreach ($semuaAc as $bid => $u) {
        $cek = 0;
        foreach ((array) ($dAc[(string) $bid]['baris'] ?? []) as $b) {
            if (strpos($b['tgl'], $awalBulan) === 0) { $cek++; }
        }
        if (!$cek) { $peringatan['ac'][$bid] = $u['nama'] . ' · ' . ($u['ruang_nama'] ?: '-') . ' · PJ: ' . (kk_nama_petugas($semuaTugas['ac'][$bid] ?? []) ?: 'belum ada'); }
    }
    if ($pengelola) {
        $batasDiam = date('Y-m-d H:i:s', strtotime('-3 days'));
        foreach ($petugasCs as $pid => $nm) {
            $akhir = $terakhirPid[$pid] ?? '';
            if ($akhir < $batasDiam) { $peringatan['cs'][$pid] = $nm . ' · ' . ($akhir !== '' ? 'terakhir ' . kk_waktu(['waktu' => $akhir]) : 'belum pernah mengisi'); }
        }
        foreach ($petaRuang as $rid => $r) {
            if (!$r['aktif']) { continue; }
            $kurang = [];
            if (empty($semuaTugas['ruang'][$rid])) { $kurang[] = 'kebersihan'; }
            if (empty($semuaTugas['sarana'][$rid]) && ($jmlBarang[$rid] ?? 0) > 0) { $kurang[] = 'inventaris'; }
            if ($kurang) { $peringatan['tanpa_pj'][$rid] = $r['nama'] . ' · ' . implode(' & ', $kurang); }
        }
        foreach ($semuaAc as $bid => $u) {
            if (empty($semuaTugas['ac'][$bid])) { $peringatan['tanpa_pj']['ac' . $bid] = $u['nama'] . ' (' . $u['kode'] . ') · AC'; }
        }
    }
    $menungguParaf = 0;
    foreach ($dRuang as $r) {
        if (empty($r['kontrol'][$kunciBulan])) {
            foreach ((array) ($r['nilai'] ?? []) as $perSel) {
                foreach (array_keys((array) $perSel) as $sel) {
                    if (strpos((string) $sel, $bulan . '-') === 0) { $menungguParaf++; break 2; }
                }
            }
        }
    }
    foreach ($dSarana as $r) {
        if (empty($r['kontrol'][$kunciBulan])) {
            foreach ((array) ($r['isi'] ?? []) as $perBulan) {
                if (!empty($perBulan[$kunciBulan])) { $menungguParaf++; break; }
            }
        }
    }
    foreach ($dAc as $u) {
        foreach ((array) ($u['baris'] ?? []) as $b) {
            if (empty($b['kontrol']) && strpos($b['tgl'], $awalBulan) === 0) { $menungguParaf++; }
        }
    }
    $rusakBaru = (int) val("SELECT COUNT(*) FROM aset_kerusakan WHERE status = 'dilaporkan'", [], 0);

    $persen = static function (int $a, int $b): string {
        if ($b <= 0) { return ''; }
        $p = (int) round($a / $b * 100);
        return lencana($a . '/' . $b, $p >= 100 ? 'hijau' : ($p >= 50 ? 'emas' : 'jingga'));
    };

    if (in_array($_GET['aksi'] ?? '', ['excel', 'cetak'], true) && $pengelola) {
        $judul = 'REKAP PEKERJAAN PETUGAS CS · ' . mb_strtoupper(NAMA_BULAN[$bulan]) . ' ' . $tahun;
        if ($_GET['aksi'] === 'excel') {
            $b = kk_kepala_excel($judul);
            $k = XLSX_GAYA_KEPALA;
            $b[8] = [0 => ['v' => 'No', 's' => $k], 1 => ['v' => 'Petugas', 's' => $k], 2 => ['v' => 'Ruangan (kebersihan)', 's' => $k], 3 => ['v' => 'Minggu dinilai', 's' => $k],
                     4 => ['v' => 'Ruangan (inventaris)', 's' => $k], 5 => ['v' => 'Sarana diperiksa', 's' => $k], 6 => ['v' => 'Unit AC', 's' => $k], 7 => ['v' => 'Pemeriksaan AC', 's' => $k],
                     8 => ['v' => 'Isian bulan ini', 's' => $k], 9 => ['v' => 'Terakhir aktif', 's' => $k]];
            $r = 9;
            $no = 0;
            foreach ($rekapCs as $pid => $x) {
                $no++;
                $b[$r++] = [0 => ['v' => $no, 's' => XLSX_GAYA_TENGAH], 1 => $x['nama'], 2 => implode(', ', array_column($x['ruang'], 'nama')), 3 => ['v' => $x['r_isi'] . '/' . $x['r_target'], 's' => XLSX_GAYA_TENGAH],
                            4 => implode(', ', array_column($x['sarana'], 'nama')), 5 => ['v' => $x['s_isi'] . '/' . $x['s_target'], 's' => XLSX_GAYA_TENGAH], 6 => implode(', ', array_column($x['ac'], 'nama')),
                            7 => ['v' => $x['a_cek'], 's' => XLSX_GAYA_TENGAH], 8 => ['v' => $x['kontribusi'], 's' => XLSX_GAYA_TENGAH], 9 => isset($terakhirPid[$pid]) ? kk_waktu(['waktu' => $terakhirPid[$pid]]) : '-'];
            }
            kk_ttd_excel($b, $r + 1, 7);
            ksort($b);
            xlsx_kirim('Rekap-Petugas-CS-' . $tahun . '-' . sprintf('%02d', $bulan) . '.xlsx', [['nama' => 'Rekap Petugas CS', 'baris' => $b, 'lanskap' => true, 'grid' => [8, $r - 1, 10],
                'lebar' => ['A' => 5, 'B' => 22, 'C' => 30, 'D' => 12, 'E' => 30, 'F' => 13, 'G' => 30, 'H' => 13, 'I' => 12, 'J' => 18]]]);
        }
        while (ob_get_level() > 0) { ob_end_clean(); }
        $css = base_url('assets/css/app.css') . '?v=' . (int) @filemtime(APP_ROOT . '/assets/css/app.css'); ?>
<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rekap Petugas CS <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></title><link rel="stylesheet" href="<?= e($css) ?>"></head>
<body class="kk-cetak-hal"><div class="label-alat no-cetak"><div><b>Rekap pekerjaan petugas CS</b> · <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></div><div class="label-alat-tombol"><button class="btn btn-utama btn-kecil" type="button" id="tombolCetakKK"><?= ikon('cetak', 15) ?> Cetak</button></div></div>
<section class="kk-lembar"><?= kk_kop() ?><h1 class="kk-judul"><?= e($judul) ?></h1>
<table class="kk-grid kk-grid-ac"><thead><tr><th class="kk-no">No</th><th>Petugas</th><th>Ruangan kebersihan</th><th>Minggu dinilai</th><th>Ruangan inventaris</th><th>Sarana diperiksa</th><th>Unit AC</th><th>Pemeriksaan AC</th><th>Isian</th><th>Terakhir aktif</th></tr></thead><tbody>
<?php $no = 0; foreach ($rekapCs as $pid => $x): $no++; ?><tr><td class="kk-no"><?= $no ?></td><td><?= e($x['nama']) ?></td><td><?= e(implode(', ', array_column($x['ruang'], 'nama')) ?: '-') ?></td><td style="text-align:center"><?= $x['r_target'] ? $x['r_isi'] . '/' . $x['r_target'] : '-' ?></td>
<td><?= e(implode(', ', array_column($x['sarana'], 'nama')) ?: '-') ?></td><td style="text-align:center"><?= $x['s_target'] ? $x['s_isi'] . '/' . $x['s_target'] : '-' ?></td><td><?= e(implode(', ', array_column($x['ac'], 'nama')) ?: '-') ?></td>
<td style="text-align:center"><?= $x['ac'] ? $x['a_cek'] : '-' ?></td><td style="text-align:center"><?= $x['kontribusi'] ?></td><td><?= isset($terakhirPid[$pid]) ? e(kk_waktu(['waktu' => $terakhirPid[$pid]])) : '-' ?></td></tr><?php endforeach; ?>
</tbody></table><div class="kk-bawah"><div></div><?= kk_ttd() ?></div></section>
<script nonce="<?= csp_nonce() ?>">document.getElementById('tombolCetakKK').addEventListener('click', function () { window.print(); });</script></body></html>
<?php
        exit;
    }
    $subjudul = $pengelola ? 'Monitoring petugas CS' : 'Pekerjaan saya';
    $jumlahPeringatan = count($peringatan['ruang']) + count($peringatan['ac']) + count($peringatan['cs']) + count($peringatan['tanpa_pj']);
    $labelJenis = ['Kebersihan' => 'hijau', 'Inventaris' => 'emas', 'AC' => 'ungu', 'Catatan harian' => 'abu', 'Lapor kerusakan' => 'jingga'];
    ?>
    <form method="get" class="saring no-cetak">
      <input type="hidden" name="h" value="<?= e($h) ?>">
      <div class="form-baris"><label class="fl">Bulan</label><select name="bulan" data-auto-kirim><?= pilihan($pilihBulan, (string) $bulan) ?></select></div>
      <div class="form-baris"><label class="fl">Tahun</label><select name="tahun" data-auto-kirim><?= pilihan($pilihTahun, (string) $tahun) ?></select></div>
      <?php if ($pengelola): ?>
      <div class="kk-alat">
        <a class="btn btn-garis" href="<?= e(url($h, ['aksi' => 'cetak', 'tahun' => $tahun, 'bulan' => $bulan])) ?>" target="_blank" rel="noopener"><?= ikon('cetak', 15) ?> Cetak rekap</a>
        <a class="btn btn-garis" href="<?= e(url($h, ['aksi' => 'excel', 'tahun' => $tahun, 'bulan' => $bulan])) ?>"><?= ikon('unduh', 15) ?> Excel</a>
        <button class="btn btn-sukses" type="button" data-buka="tiraiParafMassal"<?= $menungguParaf ? '' : ' disabled' ?>><?= ikon('centang', 15) ?> Paraf massal (<?= $menungguParaf ?>)</button>
      </div>
      <?php endif; ?>
    </form>

    <?php if ($pengelola): ?>
    <div class="grid-stat kk-stat">
      <div class="stat"><span class="ik"><?= ikon('grup', 22) ?></span><div><div class="label">CS aktif hari ini</div><div class="nilai"><?= count($aktifHariIni) ?> <small>dari <?= count($petugasCs) ?></small></div></div></div>
      <div class="stat biru"><span class="ik"><?= ikon('kebersihan', 22) ?></span><div><div class="label">Belum dinilai minggu ini</div><div class="nilai"><?= $bulanBerjalan ? count($peringatan['ruang']) : '-' ?> <small>ruangan</small></div></div></div>
      <a class="stat emas" href="<?= e(url('aset_sarana')) ?>" style="text-decoration:none"><span class="ik"><?= ikon('segitiga', 22) ?></span><div><div class="label">Laporan kerusakan baru</div><div class="nilai"><?= $rusakBaru ?> <small>laporan</small></div></div></a>
      <div class="stat ungu"><span class="ik"><?= ikon('centang', 22) ?></span><div><div class="label">Menunggu paraf</div><div class="nilai"><?= $menungguParaf ?> <small>kartu/cek</small></div></div></div>
    </div>
    <?php endif; ?>

    <?php if ($jumlahPeringatan): ?>
    <details class="kartu kk-peringatan"<?= $pengelola ? ' open' : '' ?>>
      <summary><?= ikon('seru', 17) ?> <b>Perlu perhatian</b> <?= lencana((string) $jumlahPeringatan, 'emas') ?></summary>
      <div class="kk-peringatan-isi">
        <?php if ($peringatan['ruang']): ?><div><h4>Ruangan belum dinilai minggu <?= KK_ROMAWI[$mingguKini] ?></h4><ul><?php foreach ($peringatan['ruang'] as $rid => $t): ?><li><a href="<?= e(url('aset_kk_ruang', ['ruang' => $rid, 'tahun' => $tahun, 'bulan' => $bulan])) ?>"><?= e($t) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($peringatan['ac']): ?><div><h4>AC belum diperiksa bulan ini</h4><ul><?php foreach ($peringatan['ac'] as $bid => $t): ?><li><a href="<?= e(url('aset_kk_ac', ['barang' => $bid, 'tahun' => $tahun])) ?>"><?= e($t) ?></a></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($peringatan['cs']): ?><div><h4>CS tidak aktif ≥ 3 hari</h4><ul><?php foreach ($peringatan['cs'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
        <?php if ($peringatan['tanpa_pj']): ?><div><h4>Belum ada PJ</h4><ul><?php foreach ($peringatan['tanpa_pj'] as $t): ?><li><?= e($t) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
      </div>
    </details>
    <?php endif; ?>

    <div class="kk-monitor">
      <div class="kartu">
        <div class="kartu-kepala"><div><h2><?= ikon('grup', 18) ?> <?= $pengelola ? 'Pekerjaan per petugas' : 'Pekerjaan saya' ?> · <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></h2>
          <p class="ket">Klik nama petugas untuk melihat rinciannya.</p></div></div>
        <?php if (!$rekapCs): ?><?= sel_kosong('Belum ada petugas CS') ?><?php else: ?>
        <div class="kk-daftar-cs">
        <?php foreach ($rekapCs as $pid => $x): ?>
          <details class="kk-cs"<?= !$pengelola ? ' open' : '' ?>>
            <summary>
              <span class="orang"><?= avatar($x['nama'], 'kecil') ?><b><?= e($x['nama']) ?></b></span>
              <span class="kk-cs-angka" title="Minggu kebersihan dinilai"><?= ikon('kebersihan', 13) ?> <?= $persen($x['r_isi'], $x['r_target']) ?: '<i class="lemah">-</i>' ?></span>
              <span class="kk-cs-angka" title="Sarana diperiksa"><?= ikon('catatan', 13) ?> <?= $persen($x['s_isi'], $x['s_target']) ?: '<i class="lemah">-</i>' ?></span>
              <span class="kk-cs-angka" title="Pemeriksaan AC"><?= ikon('ac', 13) ?> <?= $x['ac'] ? lencana((string) $x['a_cek'], $x['a_cek'] ? 'hijau' : 'abu') : '<i class="lemah">-</i>' ?></span>
              <span class="kk-cs-akhir <?= isset($aktifHariIni[$pid]) ? 'aktif' : '' ?>"><?= isset($terakhirPid[$pid]) ? e(kk_waktu(['waktu' => $terakhirPid[$pid]])) : 'belum aktif' ?></span>
            </summary>
            <div class="kk-cs-isi">
              <div class="kk-cs-kolom">
                <h4>Kebersihan ruangan</h4>
                <?php if (!$x['ruang']): ?><p class="lemah kecil">Belum ditugaskan.</p><?php endif; ?>
                <?php foreach ($x['ruang'] as $rid => $rr): ?><a class="kk-cs-baris" href="<?= e(url('aset_kk_ruang', ['ruang' => $rid, 'tahun' => $tahun, 'bulan' => $bulan])) ?>"><span><?= e($rr['nama']) ?></span>
                  <span class="kk-minggu-titik"><?php foreach ($rr['minggu'] as $m => $ok): ?><i class="<?= $ok ? 'ok' : '' ?>" title="Minggu <?= KK_ROMAWI[$m] ?>"><?= KK_ROMAWI[$m] ?></i><?php endforeach; ?></span><?= $rr['paraf'] ? lencana('Diparaf', 'hijau') : '' ?></a><?php endforeach; ?>
                <h4>Inventaris sarana</h4>
                <?php if (!$x['sarana']): ?><p class="lemah kecil">Belum ditugaskan.</p><?php endif; ?>
                <?php foreach ($x['sarana'] as $rid => $rr): ?><a class="kk-cs-baris" href="<?= e(url('aset_kk_sarana', ['ruang' => $rid, 'tahun' => $tahun, 'bulan' => $bulan])) ?>"><span><?= e($rr['nama']) ?></span><?= $persen($rr['isi'], $rr['total']) ?><?= $rr['paraf'] ? lencana('Diparaf', 'hijau') : '' ?></a><?php endforeach; ?>
                <h4>Pemeliharaan AC</h4>
                <?php if (!$x['ac']): ?><p class="lemah kecil">Belum ditugaskan.</p><?php endif; ?>
                <?php foreach ($x['ac'] as $bid => $aa): ?><a class="kk-cs-baris" href="<?= e(url('aset_kk_ac', ['barang' => $bid, 'tahun' => $tahun])) ?>"><span><?= e($aa['nama']) ?></span><?= lencana($aa['cek'] . ' cek', $aa['cek'] ? 'hijau' : 'abu') ?></a><?php endforeach; ?>
                <?php if ($x['belum_paraf']): ?><p class="kecil"><?= lencana($x['belum_paraf'] . ' pemeriksaan AC belum diparaf', 'emas') ?></p><?php endif; ?>
              </div>
              <div class="kk-cs-kolom">
                <h4>Aktivitas terakhir</h4>
                <?php $nAkt = 0; foreach ($aktivitas as $a): if ($a['pid'] !== $pid) { continue; } if (++$nAkt > 8) { break; } ?>
                  <a class="kk-akt" href="<?= e($a['url']) ?>"><span class="kk-akt-waktu"><?= e(kk_waktu($a)) ?></span><?= lencana($a['jenis'], $labelJenis[$a['jenis']] ?? 'abu') ?> <span><?= e($a['teks']) ?></span></a>
                <?php endforeach; ?>
                <?php if (!$nAkt): ?><p class="lemah kecil">Belum ada aktivitas.</p><?php endif; ?>
                <?php if ($pengelola): ?>
                <form method="post" class="kk-umpan">
                  <?= csrf_field() ?><input type="hidden" name="op" value="umpan"><input type="hidden" name="pegawai_id" value="<?= (int) $pid ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="bulan" value="<?= $bulan ?>">
                  <h4>Umpan balik untuk <?= e($x['nama']) ?></h4>
                  <div class="kk-umpan-baris"><select name="jenis" aria-label="Jenis umpan balik"><?php foreach (JENIS_UMPAN as $kj => $jj): ?><option value="<?= $kj ?>"<?= $kj === 'arahan' ? ' selected' : '' ?>><?= e($jj['label']) ?></option><?php endforeach; ?></select>
                    <input type="text" name="isi" maxlength="4000" required placeholder="Tulis apresiasi atau arahan…" aria-label="Isi umpan balik">
                    <button class="btn btn-utama btn-kecil" type="submit"><?= ikon('kirim', 14) ?> Kirim</button></div>
                </form>
                <?php endif; ?>
              </div>
            </div>
          </details>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>

    </div>

    <?php if ($pengelola): ?>
    <div class="tirai" id="tiraiParafMassal" hidden>
      <div class="modal sempit">
        <form method="post">
          <?= csrf_field() ?><input type="hidden" name="op" value="paraf_massal"><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="bulan" value="<?= $bulan ?>">
          <div class="modal-kepala"><h2>Paraf massal <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
          <div class="modal-isi">
            <p><b><?= $menungguParaf ?></b> kartu kebersihan, kartu inventaris, dan pemeriksaan AC bulan ini yang sudah diisi akan diparaf sekaligus. Kartu yang sudah diparaf terkunci untuk petugas.</p>
            <div class="form-baris"><label class="fl" for="pmCatatan">Catatan (opsional)</label><input type="text" id="pmCatatan" name="catatan" maxlength="500"></div>
          </div>
          <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-sukses"><?= ikon('centang', 15) ?> Paraf semua</button></div>
        </form>
      </div>
    </div>
    <?php endif; ?>
    <?php
    return;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    $kunciBulan = (string) $bulan;

    $objekTugas = $jenisKK === 'ac' ? $barangId : $ruangId;
    if ($op === 'tugaskan' && $bolehTugaskan && $objekTugas && $jenisKK !== 'rekap') {
        $pilih = array_values(array_intersect(array_map('intval', (array) ($_POST['petugas'] ?? [])), array_keys($petugasCs)));
        kk_simpan_penugasan($jenisKK, $objekTugas, $pilih);
        catat_log('tugaskan_kk_' . $jenisKK, $jenisKK === 'ac' ? 'aset_barang' : 'aset_ruang', $objekTugas, implode(',', $pilih));
        flash('PJ ' . e(KK_HALAMAN[$h]) . ' untuk <b>' . e($jenisKK === 'ac' ? $unitAc[$barangId]['nama'] : $petaRuang[$ruangId]['nama']) . '</b> disimpan.');
        kembali_ke($h, array_filter(['tahun' => $tahun, 'bulan' => $bulan]));
    }

    if ($op === 'impor') {
        $baca = xlsx_baca_unggahan($_FILES['berkas'] ?? [], [$jenisKK === 'ruang' ? 'ruangan' : ($jenisKK === 'ac' ? ' ac' : 'sarana'), 'kartu']);
        if (isset($baca['galat'])) {
            flash($baca['galat'], 'galat');
            $balik();
        }
        $hasil = kk_impor($jenisKK, $baca['data'], $tahun, $ruangId, $barangId, $pengelola, post('sisi') === 'kanan');
        flash($hasil[1], $hasil[0]);
        if ($hasil[0] !== 'galat') {
            catat_log('impor_kk_' . $jenisKK, $jenisKK === 'ac' ? 'aset_barang' : 'aset_ruang', $jenisKK === 'ac' ? $barangId : $ruangId, $hasil[1]);
        }
        $balik();
    }

    if ($jenisKK === 'ruang' && $ruangId) {
        if ($op === 'simpan') {
            $masuk = (array) ($_POST['n'] ?? []);
            $mingguKirim = array_values(array_intersect([1, 2, 3, 4], array_map('intval', (array) ($_POST['minggu'] ?? []))));
            $hasil = kk_ubah('ruang', $tahun, static function (array &$d) use ($ruangId, $bulan, $kunciBulan, $masuk, $pengelola, $mingguKirim): string {
                $r = $d[(string) $ruangId] ?? [];
                if (!empty($r['kontrol'][$kunciBulan]) && !$pengelola) {
                    return 'kunci';
                }
                $ubah = 0;
                foreach ($mingguKirim as $m) {
                    $sel = $bulan . '-' . $m;
                    $beda = false;
                    foreach (KK_URAIAN_RUANG as $no => $_) {
                        $v = (string) ($masuk[$no][$m] ?? '');
                        $v = isset(KK_NILAI[$v]) ? $v : '';
                        $lama = (string) ($r['nilai'][(string) $no][$sel] ?? '');
                        if ($v !== $lama) {
                            $beda = true;
                            if ($v === '') {
                                unset($r['nilai'][(string) $no][$sel]);
                            } else {
                                $r['nilai'][(string) $no][$sel] = $v;
                            }
                        }
                    }
                    if ($beda) {
                        $ubah++;
                        $r['oleh'][$sel] = kk_tanda();
                    }
                }
                $d[(string) $ruangId] = $r;
                return $ubah ? 'ok' : 'sama';
            });
            if ($hasil === 'kunci') {
                flash('Kartu bulan ini sudah diparaf Ka. Bid Sarpras sehingga tidak dapat diubah lagi.', 'warn');
            } elseif ($hasil === 'ok') {
                catat_log('isi_kk_ruang', 'aset_ruang', $ruangId, NAMA_BULAN[$bulan] . ' ' . $tahun);
                flash('Kartu kontrol ruangan tersimpan.');
            } elseif ($hasil === 'sama') {
                flash('Tidak ada nilai yang berubah.', 'info');
            } else {
                flash('Kartu kontrol tidak dapat disimpan di server.', 'galat');
            }
            $balik();
        }
        if (($op === 'kontrol' || $op === 'batal_kontrol') && $pengelola) {
            $catatan = kk_teks('catatan', 500);
            kk_ubah('ruang', $tahun, static function (array &$d) use ($ruangId, $kunciBulan, $op, $catatan): void {
                if ($op === 'kontrol') {
                    $d[(string) $ruangId]['kontrol'][$kunciBulan] = kk_tanda() + ['catatan' => $catatan];
                } else {
                    unset($d[(string) $ruangId]['kontrol'][$kunciBulan]);
                }
            });
            catat_log($op . '_kk_ruang', 'aset_ruang', $ruangId, NAMA_BULAN[$bulan] . ' ' . $tahun);
            flash($op === 'kontrol' ? 'Kartu bulan ' . NAMA_BULAN[$bulan] . ' diparaf (dikontrol).' : 'Paraf kontrol dibatalkan.', $op === 'kontrol' ? 'ok' : 'info');
            $balik();
        }
    }

    if ($jenisKK === 'ac' && $barangId) {
        if ($op === 'kepala') {
            $isi = ['mitra' => kk_teks('mitra', 150), 'jenis_ac' => kk_teks('jenis_ac', 100), 'kapasitas' => kk_teks('kapasitas', 50)];
            kk_ubah('ac', $tahun, static function (array &$d) use ($barangId, $isi): void {
                $d[(string) $barangId] = $isi + ($d[(string) $barangId] ?? []);
            });
            flash('Data kartu AC tersimpan.');
            $balik();
        }
        if ($op === 'tambah') {
            $tgl = post('tgl');
            $baris = [
                'id' => bin2hex(random_bytes(6)), 'tgl' => $tgl, 'deskripsi' => kk_teks('deskripsi', 200), 'arus' => kk_teks('arus', 30),
                'tegangan' => kk_teks('tegangan', 30), 'tekanan' => kk_teks('tekanan', 30), 'remaks' => kk_teks('remaks', 300), 'oleh' => kk_tanda(),
            ];
            if (!tanggal_sah($tgl) || (int) substr($tgl, 0, 4) !== $tahun || $baris['deskripsi'] === '') {
                flash('Isi tanggal (di tahun ' . $tahun . ') dan deskripsi pekerjaan.', 'galat');
                $balik();
            }
            kk_ubah('ac', $tahun, static function (array &$d) use ($barangId, $baris): void {
                $d[(string) $barangId]['baris'][] = $baris;
                usort($d[(string) $barangId]['baris'], static function ($a, $b) { return strcmp($a['tgl'], $b['tgl']); });
            });
            catat_log('isi_kk_ac', 'aset_barang', $barangId, $baris['deskripsi']);
            flash('Pemeriksaan AC dicatat.');
            $balik();
        }
        if ($op === 'hapus') {
            $bid = post('baris');
            $hasil = kk_ubah('ac', $tahun, static function (array &$d) use ($barangId, $bid, $pengelola, $saya): bool {
                foreach ((array) ($d[(string) $barangId]['baris'] ?? []) as $i => $b) {
                    if (($b['id'] ?? '') === $bid && ($pengelola || ((int) ($b['oleh']['id'] ?? 0) === $saya && empty($b['kontrol'])))) {
                        array_splice($d[(string) $barangId]['baris'], $i, 1);
                        return true;
                    }
                }
                return false;
            });
            flash($hasil ? 'Baris pemeriksaan dihapus.' : 'Baris tidak dapat dihapus (sudah diparaf atau bukan isian Anda).', $hasil ? 'ok' : 'warn');
            $balik();
        }
        if ($op === 'kontrol' && $pengelola) {
            $n = kk_ubah('ac', $tahun, static function (array &$d) use ($barangId): int {
                $n = 0;
                foreach ((array) ($d[(string) $barangId]['baris'] ?? []) as $i => $b) {
                    if (empty($b['kontrol'])) {
                        $d[(string) $barangId]['baris'][$i]['kontrol'] = kk_tanda();
                        $n++;
                    }
                }
                return $n;
            });
            flash((int) $n . ' baris pemeriksaan diparaf.');
            $balik();
        }
    }

    if ($jenisKK === 'sarana' && $ruangId) {
        if ($op === 'simpan') {
            $masuk = (array) ($_POST['s'] ?? []);
            $data = kk_baca('sarana', $tahun);
            $sah = kk_barang_ruang($ruangId, array_keys((array) ($data[(string) $ruangId]['isi'] ?? [])));
            $hasil = kk_ubah('sarana', $tahun, static function (array &$d) use ($ruangId, $kunciBulan, $masuk, $sah, $pengelola): string {
                $r = $d[(string) $ruangId] ?? [];
                if (!empty($r['kontrol'][$kunciBulan]) && !$pengelola) {
                    return 'kunci';
                }
                $ubah = 0;
                foreach ($masuk as $id => $isi) {
                    if (!isset($sah[(int) $id]) || !is_array($isi)) {
                        continue;
                    }
                    $baru = [
                        'q' => preg_match('/^\d{1,6}$/', trim((string) ($isi['q'] ?? ''))) ? trim((string) $isi['q']) : '',
                        'k' => in_array($isi['k'] ?? '', ['B', 'R'], true) ? $isi['k'] : '',
                        'p' => preg_match('/^\d{1,6}$/', trim((string) ($isi['p'] ?? ''))) ? trim((string) $isi['p']) : '',
                        'm' => mb_substr(trim((string) ($isi['m'] ?? '')), 0, 120),
                        'u' => mb_substr(trim((string) ($isi['u'] ?? '')), 0, 120),
                    ];
                    $lama = (array) ($r['isi'][(string) (int) $id][$kunciBulan] ?? []);
                    $sebelum = array_intersect_key($lama, $baru) + array_fill_keys(array_keys($baru), '');
                    if ($baru == $sebelum && $lama) {
                        continue;
                    }
                    if (implode('', $baru) === '') {
                        unset($r['isi'][(string) (int) $id][$kunciBulan]);
                    } else {
                        $r['isi'][(string) (int) $id][$kunciBulan] = $baru + ['s' => kk_tanda()];
                    }
                    $ubah++;
                }
                $d[(string) $ruangId] = $r;
                return $ubah ? 'ok' : 'sama';
            });
            if ($hasil === 'kunci') {
                flash('Kartu bulan ini sudah diparaf Ka. Bid Sarpras sehingga tidak dapat diubah lagi.', 'warn');
            } elseif ($hasil === 'ok') {
                catat_log('isi_kk_sarana', 'aset_ruang', $ruangId, NAMA_BULAN[$bulan] . ' ' . $tahun);
                flash('Kartu kontrol inventaris sarana tersimpan.');
            } elseif ($hasil === 'sama') {
                flash('Tidak ada isian yang berubah.', 'info');
            } else {
                flash('Kartu kontrol tidak dapat disimpan di server.', 'galat');
            }
            $balik();
        }
        if (($op === 'kontrol' || $op === 'batal_kontrol') && $pengelola) {
            $catatan = kk_teks('catatan', 500);
            kk_ubah('sarana', $tahun, static function (array &$d) use ($ruangId, $kunciBulan, $op, $catatan): void {
                if ($op === 'kontrol') {
                    $d[(string) $ruangId]['kontrol'][$kunciBulan] = kk_tanda() + ['catatan' => $catatan];
                } else {
                    unset($d[(string) $ruangId]['kontrol'][$kunciBulan]);
                }
            });
            flash($op === 'kontrol' ? 'Kartu bulan ' . NAMA_BULAN[$bulan] . ' diparaf (dikontrol).' : 'Paraf kontrol dibatalkan.', $op === 'kontrol' ? 'ok' : 'info');
            $balik();
        }
    }
    tolak_akses();
}

if (($_GET['aksi'] ?? '') === 'excel') {
    $dataX = kk_baca($jenisKK, $tahun);
    if ($jenisKK === 'ac') {
        $lembar = [];
        foreach ($barangId ? [$unitAc[$barangId]] : array_values($unitAc) as $u) {
            $l = kk_ekspor_ac($u, $tahun, $dataX[(string) $u['id']] ?? []);
            $l['nama'] = $barangId ? 'KARTU KONTROL AC' : mb_substr('AC ' . $u['kode'], 0, 31);
            $lembar[] = $l;
        }
        if (!$lembar) {
            flash('Belum ada unit AC di inventaris.', 'warn');
            $balik();
        }
        xlsx_kirim('Kartu-Pemeliharaan-AC-' . ($barangId ? $unitAc[$barangId]['kode'] . '-' : '') . $tahun . '.xlsx', $lembar);
    }
    if (!$ruangId) {
        flash('Pilih ruangan dahulu untuk mengunduh kartunya dalam format Excel.', 'warn');
        $balik();
    }
    $r = $petaRuang[$ruangId];
    $pj = kk_pj_ruang($r, $penugasan);
    $d = $dataX[(string) $ruangId] ?? [];
    $lembar = $jenisKK === 'ruang' ? kk_ekspor_ruang($r, $pj, $tahun, $d) : kk_ekspor_sarana($r, $pj, $tahun, $d, kk_barang_ruang($ruangId, array_keys((array) ($d['isi'] ?? []))));
    xlsx_kirim(($jenisKK === 'ruang' ? 'Kartu-Kontrol-Ruangan-' : 'Kartu-Kontrol-Sarana-') . $r['kode'] . '-' . $tahun . '.xlsx', [$lembar]);
}

if (($_GET['aksi'] ?? '') === 'cetak') {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    require APP_ROOT . '/views/kartu_kontrol_cetak.php';
    exit;
}

$data = kk_baca($jenisKK, $tahun);
$subjudul = KK_HALAMAN[$h];
$tautanCetak = url($h, array_filter(['aksi' => 'cetak', 'tahun' => $tahun, 'ruang' => $ruangId ?: null, 'barang' => $barangId ?: null]));
$kunciBulan = (string) $bulan;
?>
<form method="get" class="saring no-cetak">
  <input type="hidden" name="h" value="<?= e($h) ?>">
  <?php if ($jenisKK === 'ac'): ?>
    <div class="form-baris" style="flex:1"><label class="fl">Unit AC</label><select name="barang" data-auto-kirim><option value="">— Ringkasan semua unit AC —</option>
      <?php foreach ($unitAc as $u): ?><option value="<?= (int) $u['id'] ?>"<?= (int) $u['id'] === $barangId ? ' selected' : '' ?>><?= e($u['nama'] . ' · ' . $u['kode'] . ($u['ruang_nama'] ? ' · ' . $u['ruang_nama'] : '')) ?></option><?php endforeach; ?></select></div>
  <?php else: ?>
    <div class="form-baris" style="flex:1"><label class="fl">Ruangan</label><select name="ruang" data-auto-kirim><option value="">— Ringkasan semua ruangan —</option>
      <?php foreach ($petaRuang as $r): ?><option value="<?= (int) $r['id'] ?>"<?= (int) $r['id'] === $ruangId ? ' selected' : '' ?>><?= e($r['nama'] . ' (' . $r['kode'] . ')') ?></option><?php endforeach; ?></select></div>
    <div class="form-baris"><label class="fl">Bulan</label><select name="bulan" data-auto-kirim><?= pilihan($pilihBulan, (string) $bulan) ?></select></div>
  <?php endif; ?>
  <div class="form-baris"><label class="fl">Tahun</label><select name="tahun" data-auto-kirim><?= pilihan($pilihTahun, (string) $tahun) ?></select></div>
  <div class="kk-alat">
    <a class="btn btn-garis" href="<?= e($tautanCetak) ?>" target="_blank" rel="noopener"><?= ikon('cetak', 15) ?> Cetak</a>
    <a class="btn btn-garis" href="<?= e(url($h, array_filter(['aksi' => 'excel', 'tahun' => $tahun, 'ruang' => $ruangId ?: null, 'barang' => $barangId ?: null]))) ?>"><?= ikon('unduh', 15) ?> Unduh Excel</a>
    <?php if (($jenisKK === 'ac' && $barangId) || ($jenisKK !== 'ac' && $ruangId)): ?>
      <button class="btn btn-garis" type="button" data-buka="tiraiImporKK"><?= ikon('unggah', 15) ?> Unggah Excel</button>
    <?php endif; ?>
  </div>
</form>

<?php if ($ruangSaya): ?>
  <div class="kk-saya no-cetak"><b><?= $jenisKK === 'ac' ? 'Unit AC tugas saya:' : 'Ruangan tugas saya:' ?></b>
    <?php foreach ($ruangSaya as $rs): if ($jenisKK === 'ac' ? !isset($unitAc[$rs]) : !isset($petaRuang[$rs])) { continue; } ?>
      <a class="<?= $rs === ($jenisKK === 'ac' ? $barangId : $ruangId) ? 'aktif' : '' ?>" href="<?= e(url($h, [($jenisKK === 'ac' ? 'barang' : 'ruang') => $rs, 'tahun' => $tahun, 'bulan' => $bulan])) ?>"><?= e($jenisKK === 'ac' ? $unitAc[$rs]['nama'] . ' · ' . ($unitAc[$rs]['ruang_nama'] ?: '-') : $petaRuang[$rs]['nama']) ?></a>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php if (($jenisKK === 'ac' && $barangId) || ($jenisKK !== 'ac' && $ruangId)): ?>
<div class="tirai" id="tiraiImporKK" hidden>
  <div class="modal sempit">
    <form method="post" enctype="multipart/form-data">
      <?= csrf_field() ?><input type="hidden" name="op" value="impor"><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="bulan" value="<?= $bulan ?>">
      <input type="hidden" name="ruang" value="<?= $ruangId ?>"><input type="hidden" name="barang" value="<?= $barangId ?>">
      <div class="modal-kepala"><h2>Unggah kartu dari Excel</h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil">Gunakan file <b>Kartu Kontrol</b> (format sama dengan tombol <i>Unduh Excel</i> atau file KARTU KONTROL.xlsx). Isian akan dimasukkan ke
          <b><?= e($jenisKK === 'ac' ? $unitAc[$barangId]['nama'] . ' (' . $unitAc[$barangId]['kode'] . ')' : $petaRuang[$ruangId]['nama']) ?></b> tahun <b><?= $tahun ?></b>.</p>
        <ul class="kecil kk-aturan">
          <?php if ($jenisKK === 'ruang'): ?><li>Nilai yang dibaca hanya A, B, atau C pada kolom minggu I–IV tiap bulan.</li>
          <?php elseif ($jenisKK === 'sarana'): ?><li>Baris dicocokkan lewat kolom <b>CODE</b> (kode inventaris). Kolom S diisi otomatis dengan nama Anda.</li>
          <?php else: ?><li>Baris dibaca dari kolom TGL sampai REMAKS; hanya tanggal di tahun <?= $tahun ?>. Baris yang sama tidak digandakan.</li><?php endif; ?>
          <li>Sel kosong di file tidak menghapus isian yang sudah ada.</li>
          <li>Bulan yang sudah diparaf Ka. Bid Sarpras tidak diubah.</li>
        </ul>
        <?php if ($jenisKK === 'ac'): ?>
          <div class="form-baris"><label class="fl" for="kkSisi">Kartu di file</label><select id="kkSisi" name="sisi"><option value="kiri">Kartu kiri (kolom A–L)</option><option value="kanan">Kartu kanan (kolom N–X)</option></select></div>
        <?php endif; ?>
        <div class="form-baris"><label class="fl" for="kkBerkas">File Excel (.xlsx)</label><input type="file" id="kkBerkas" name="berkas" accept=".xlsx,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet" required></div>
      </div>
      <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('unggah', 15) ?> Unggah &amp; impor</button></div>
    </form>
  </div>
</div>
<?php endif; ?>

<?php if ($jenisKK === 'ruang'): ?>
  <?php if (!$ruangId): ?>
    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('gedung', 18) ?> Ringkasan kartu kebersihan · <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></h2></div></div>
      <?php if (!$ruangan): ?><?= sel_kosong($hanyaTugas ? $kosongTugas : 'Belum ada ruangan aktif') ?><?php else: ?>
      <div class="tabel-bungkus"><table class="tabel tabel-tim-tengah">
        <thead><tr><th class="kolom-no">No</th><th>Ruangan</th><th>PJ ruangan</th><?php foreach (KK_ROMAWI as $rm): ?><th>Minggu <?= $rm ?></th><?php endforeach; ?><th>Terakhir diisi</th><th>Kontrol Kabid</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($ruangan as $i => $r): $d = $data[(string) $r['id']] ?? []; $terakhir = null; ?>
          <tr><td class="kolom-no"><?= $i + 1 ?></td><td><b><?= e($r['nama']) ?></b><div class="kecil lemah"><?= e($r['kode']) ?></div></td><td class="kecil"><?= e(kk_pj_ruang($r, $penugasan) ?: '-') ?><?php if ($bolehTugaskan): ?><button class="btn btn-hantu btn-kecil kk-tugas" type="button" data-buka="tiraiTugasKK" data-isi='<?= e(json_encode(['ruang' => (int) $r['id'], 'objek_teks' => $r['nama'], 'petugas_pilih' => implode(',', $penugasan[(int) $r['id']] ?? [])])) ?>'><?= ikon('sunting', 13) ?> Tugaskan</button><?php endif; ?></td>
            <?php for ($m = 1; $m <= 4; $m++): $sel = $bulan . '-' . $m; $n = 0;
                foreach (KK_URAIAN_RUANG as $no => $_) { if (!empty($d['nilai'][(string) $no][$sel])) { $n++; } }
                $o = $d['oleh'][$sel] ?? null; if ($o && (!$terakhir || $o['waktu'] > $terakhir['waktu'])) { $terakhir = $o; } ?>
              <td class="tengah"><?= $n === 0 ? '<span class="lemah">-</span>' : ($n === count(KK_URAIAN_RUANG) ? lencana($n . '/' . $n, 'hijau') : lencana($n . '/' . count(KK_URAIAN_RUANG), 'emas')) ?></td>
            <?php endfor; ?>
            <td class="kecil"><?= $terakhir ? e($terakhir['nama']) . '<div class="lemah">' . e(kk_waktu($terakhir)) . '</div>' : '<span class="lemah">-</span>' ?></td>
            <td><?= !empty($d['kontrol'][$kunciBulan]) ? lencana('Diparaf', 'hijau') : lencana('Belum', 'abu') ?></td>
            <td class="kolom-aksi"><a class="btn btn-utama btn-kecil" href="<?= e(url($h, ['ruang' => $r['id'], 'bulan' => $bulan, 'tahun' => $tahun])) ?>">Buka</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  <?php else: $r = $petaRuang[$ruangId]; $d = $data[(string) $ruangId] ?? []; $kontrol = $d['kontrol'][$kunciBulan] ?? null; $kunci = $kontrol && !$pengelola; ?>
    <?= kk_tab_bulan($h, $tahun, $bulan, ['ruang' => $ruangId, 'mg' => $mgTampil], (array) ($d['kontrol'] ?? [])) ?>
    <form method="post" class="kartu">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="ruang" value="<?= $ruangId ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="bulan" value="<?= $bulan ?>"><input type="hidden" name="mg" value="<?= $mgTampil ?>">
      <div class="kartu-kepala"><div><h2><?= e($r['nama']) ?> · <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></h2>
        <p class="ket">PJ ruangan: <?= e(kk_pj_ruang($r, $penugasan) ?: '-') ?><?= $kontrol ? ' · ' . lencana('Diparaf ' . $kontrol['nama'], 'hijau') : '' ?></p></div>
        <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url($h, ['bulan' => $bulan, 'tahun' => $tahun])) ?>"><?= ikon('kembali', 14) ?> Ringkasan</a></div></div>
      <?php $mgList = $mgTampil ? [$mgTampil] : [1, 2, 3, 4]; $kini = $bulan === (int) date('n') && $tahun === $tahunKini ? kk_minggu_ini() : 0; ?>
      <nav class="kk-minggu no-cetak" aria-label="Pilih minggu">
        <span>Tampilkan:</span>
        <a href="<?= e(url($h, ['ruang' => $ruangId, 'tahun' => $tahun, 'bulan' => $bulan, 'mg' => 0])) ?>" class="<?= $mgTampil === 0 ? 'aktif' : '' ?>">Semua minggu</a>
        <?php foreach (KK_ROMAWI as $m => $rm): ?><a href="<?= e(url($h, ['ruang' => $ruangId, 'tahun' => $tahun, 'bulan' => $bulan, 'mg' => $m])) ?>" class="<?= $mgTampil === $m ? 'aktif' : '' ?>">Minggu <?= $rm ?><?= $m === $kini ? ' · kini' : '' ?></a><?php endforeach; ?>
      </nav>
      <?php foreach ($mgList as $m): ?><input type="hidden" name="minggu[]" value="<?= $m ?>"><?php endforeach; ?>
      <?php if ($mgTampil): $m = $mgTampil; $o = $d['oleh'][$bulan . '-' . $m] ?? null; ?>
        <div class="kartu-isi kk-satu-minggu">
          <p class="kecil lemah">Minggu <?= KK_ROMAWI[$m] ?> · <?= $o ? 'terakhir diisi ' . e($o['nama']) . ', ' . e(kk_waktu($o)) : 'belum diisi' ?></p>
          <?php if (!$kunci): ?><button class="btn btn-garis btn-kecil kk-semua-a" type="button" data-semua-nilai="A">Isi semua A</button><?php endif; ?>
          <?php foreach (KK_URAIAN_RUANG as $no => $uraian): $v = (string) ($d['nilai'][(string) $no][$bulan . '-' . $m] ?? ''); ?>
            <fieldset class="kk-butir"<?= $kunci ? ' disabled' : '' ?>><legend><?= $no ?>. <?= e($uraian) ?></legend>
              <div class="kk-pilih">
                <?php foreach (KK_NILAI as $kn => $ket): ?><label class="kk-<?= strtolower($kn) ?>"><input type="radio" name="n[<?= $no ?>][<?= $m ?>]" value="<?= $kn ?>"<?= $v === $kn ? ' checked' : '' ?>><span><b><?= $kn ?></b> <?= e($ket) ?></span></label><?php endforeach; ?>
                <label class="kk-x"><input type="radio" name="n[<?= $no ?>][<?= $m ?>]" value=""<?= $v === '' ? ' checked' : '' ?>><span>Kosong</span></label>
              </div></fieldset>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
      <div class="tabel-bungkus"><table class="tabel kk-tabel">
        <thead><tr><th class="kolom-no">No</th><th>Uraian</th><?php foreach (KK_ROMAWI as $m => $rm): ?><th class="tengah">Minggu <?= $rm ?><?= $m === $kini ? ' <span class="kk-kini">kini</span>' : '' ?></th><?php endforeach; ?></tr></thead>
        <tbody>
        <?php foreach (KK_URAIAN_RUANG as $no => $uraian): ?>
          <tr><td class="kolom-no"><?= $no ?></td><td><?= e($uraian) ?></td>
            <?php for ($m = 1; $m <= 4; $m++): $v = (string) ($d['nilai'][(string) $no][$bulan . '-' . $m] ?? ''); ?>
              <td class="tengah"><select class="kk-nilai kk-<?= e(strtolower($v ?: 'x')) ?>" name="n[<?= $no ?>][<?= $m ?>]"<?= $kunci ? ' disabled' : '' ?> aria-label="<?= e($uraian) ?> minggu <?= KK_ROMAWI[$m] ?>">
                <option value="">-</option><?php foreach (KK_NILAI as $kn => $ket): ?><option value="<?= $kn ?>"<?= $v === $kn ? ' selected' : '' ?>><?= $kn ?></option><?php endforeach; ?></select></td>
            <?php endfor; ?></tr>
        <?php endforeach; ?>
        <tr class="kk-oleh"><td></td><td class="kecil lemah">Diisi oleh</td>
          <?php for ($m = 1; $m <= 4; $m++): $o = $d['oleh'][$bulan . '-' . $m] ?? null; ?><td class="tengah kecil"><?= $o ? e($o['nama']) . '<div class="lemah">' . e(kk_waktu($o)) . '</div>' : '<span class="lemah">-</span>' ?></td><?php endfor; ?></tr>
        </tbody></table></div>
      <?php endif; ?>
      <div class="kartu-kaki kk-kaki">
        <div class="kk-legenda"><?php foreach (KK_NILAI as $kn => $ket): ?><span><b class="kk-<?= strtolower($kn) ?>"><?= $kn ?></b> <?= e($ket) ?></span><?php endforeach; ?></div>
        <?php if (!$kunci): ?><button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan kartu</button><?php endif; ?>
      </div>
    </form>
    <?= kk_panel_kontrol($h, $pengelola, $kontrol, ['ruang' => $ruangId, 'tahun' => $tahun, 'bulan' => $bulan]) ?>
  <?php endif; ?>

<?php elseif ($jenisKK === 'ac'): ?>
  <?php if (!$barangId): ?>
    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('ac', 18) ?> Ringkasan kartu pemeliharaan AC · <?= $tahun ?></h2></div></div>
      <?php if (!$unitAc): ?><?= sel_kosong($hanyaTugas ? $kosongTugas : 'Belum ada unit AC di inventaris') ?><?php else: ?>
      <div class="tabel-bungkus"><table class="tabel tabel-tim-tengah">
        <thead><tr><th class="kolom-no">No</th><th>Unit AC</th><th>Ruangan</th><th>PJ (petugas)</th><th>Merk / type</th><th>Pemeriksaan</th><th>Terakhir</th><th>Belum diparaf</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        <?php $no = 0; foreach ($unitAc as $u): $no++; $brs = (array) ($data[(string) $u['id']]['baris'] ?? []); $akhir = $brs ? end($brs) : null;
            $belum = count(array_filter($brs, static function ($b) { return empty($b['kontrol']); })); ?>
          <tr><td class="kolom-no"><?= $no ?></td><td><b><?= e($u['nama']) ?></b><div class="kecil lemah"><?= e($u['kode']) ?></div></td><td class="kecil"><?= e($u['ruang_nama'] ?: '-') ?></td>
            <td class="kecil"><?= e(kk_nama_petugas($penugasan[(int) $u['id']] ?? []) ?: '-') ?><?php if ($bolehTugaskan): ?><button class="btn btn-hantu btn-kecil kk-tugas" type="button" data-buka="tiraiTugasKK" data-isi='<?= e(json_encode(['barang' => (int) $u['id'], 'objek_teks' => $u['nama'] . ' (' . $u['kode'] . ')', 'petugas_pilih' => implode(',', $penugasan[(int) $u['id']] ?? [])])) ?>'><?= ikon('sunting', 13) ?> Tugaskan</button><?php endif; ?></td>
            <td class="kecil"><?= e(trim($u['merek'] . ' ' . $u['model']) ?: '-') ?></td><td class="tengah"><?= count($brs) ?></td>
            <td class="kecil"><?= $akhir ? e(tanggal_pendek($akhir['tgl'])) . '<div class="lemah">' . e($akhir['deskripsi']) . '</div>' : '<span class="lemah">-</span>' ?></td>
            <td class="tengah"><?= $belum ? lencana((string) $belum, 'emas') : '<span class="lemah">-</span>' ?></td>
            <td class="kolom-aksi"><a class="btn btn-utama btn-kecil" href="<?= e(url($h, ['barang' => $u['id'], 'tahun' => $tahun])) ?>">Buka</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  <?php else: $u = $unitAc[$barangId]; $d = $data[(string) $barangId] ?? []; $brs = (array) ($d['baris'] ?? []);
      $belum = count(array_filter($brs, static function ($b) { return empty($b['kontrol']); })); ?>
    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= e($u['nama']) ?> · <?= $tahun ?></h2><p class="ket"><?= e($u['kode']) ?> · <?= e($u['ruang_nama'] ?: 'Belum ditempatkan') ?> · PJ: <?= e(kk_nama_petugas($penugasan[$barangId] ?? []) ?: '-') ?></p></div>
        <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url($h, ['tahun' => $tahun])) ?>"><?= ikon('kembali', 14) ?> Ringkasan</a></div></div>
      <form method="post" class="kartu-isi">
        <?= csrf_field() ?><input type="hidden" name="op" value="kepala"><input type="hidden" name="barang" value="<?= $barangId ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>">
        <div class="info-aset">
          <div><span>Merk</span><b><?= e($u['merek'] ?: '-') ?></b></div>
          <div><span>Type</span><b><?= e($u['model'] ?: '-') ?></b></div>
          <div><span>No. seri</span><b><?= e($u['no_seri'] ?: '-') ?></b></div>
          <div><span>Ruangan</span><b><?= e($u['ruang_nama'] ?: '-') ?></b></div>
        </div>
        <div class="form-grid-3" style="margin-top:12px">
          <div class="form-baris"><label class="fl" for="kkMitra">Mitra</label><input type="text" id="kkMitra" name="mitra" maxlength="150" value="<?= e($d['mitra'] ?? '') ?>" placeholder="Nama mitra / vendor servis"></div>
          <div class="form-baris"><label class="fl" for="kkJenis">Jenis AC</label><input type="text" id="kkJenis" name="jenis_ac" maxlength="100" value="<?= e($d['jenis_ac'] ?? '') ?>" placeholder="Split / cassette / standing"></div>
          <div class="form-baris"><label class="fl" for="kkKap">Kapasitas</label><input type="text" id="kkKap" name="kapasitas" maxlength="50" value="<?= e($d['kapasitas'] ?? '') ?>" placeholder="Contoh: 1,5 PK"></div>
        </div>
        <button class="btn btn-garis btn-kecil" type="submit"><?= ikon('simpan', 14) ?> Simpan data kartu</button>
      </form>
    </div>

    <div class="kartu">
      <div class="kartu-kepala"><div><h2>Catatan pemeriksaan</h2><p class="ket"><?= count($brs) ?> pemeriksaan<?= $belum ? ' · ' . $belum . ' belum diparaf' : '' ?></p></div>
        <?php if ($pengelola && $belum): ?><div class="aksi"><form method="post" data-konfirmasi="Paraf <?= $belum ?> pemeriksaan yang belum diparaf?" data-gaya="utama"><?= csrf_field() ?>
          <input type="hidden" name="op" value="kontrol"><input type="hidden" name="barang" value="<?= $barangId ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>">
          <button class="btn btn-sukses btn-kecil" type="submit"><?= ikon('centang', 14) ?> Paraf semua</button></form></div><?php endif; ?></div>
      <?php if (!$brs): ?><?= sel_kosong('Belum ada pemeriksaan tahun ini') ?><?php else: ?>
      <div class="tabel-bungkus"><table class="tabel kk-tabel">
        <thead><tr><th class="kolom-no">No</th><th>Tanggal</th><th>Description</th><th>Arus</th><th>Tegangan</th><th>Tekanan</th><th>Remaks</th><th>Petugas</th><th>Kontrol</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($brs as $i => $b): ?>
          <tr><td class="kolom-no"><?= $i + 1 ?></td><td class="nowrap"><?= e(tanggal_pendek($b['tgl'])) ?></td><td><?= e($b['deskripsi']) ?></td>
            <td class="tengah"><?= e($b['arus'] ?: '-') ?></td><td class="tengah"><?= e($b['tegangan'] ?: '-') ?></td><td class="tengah"><?= e($b['tekanan'] ?: '-') ?></td>
            <td class="kecil"><?= e($b['remaks'] ?: '-') ?></td><td class="kecil"><?= e($b['oleh']['nama'] ?? '-') ?></td>
            <td><?= !empty($b['kontrol']) ? lencana('Diparaf', 'hijau') : lencana('Belum', 'abu') ?></td>
            <td class="kolom-aksi"><?php if ($pengelola || ((int) ($b['oleh']['id'] ?? 0) === $saya && empty($b['kontrol']))): ?>
              <form method="post" data-konfirmasi="Hapus baris pemeriksaan ini?"><?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="barang" value="<?= $barangId ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="baris" value="<?= e($b['id']) ?>">
                <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form><?php endif; ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
      <form method="post" class="kartu-isi kk-tambah">
        <?= csrf_field() ?><input type="hidden" name="op" value="tambah"><input type="hidden" name="barang" value="<?= $barangId ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>">
        <h3>Tambah pemeriksaan</h3>
        <div class="form-grid-3">
          <div class="form-baris"><label class="fl" for="kkTgl">Tanggal <span class="wajib">*</span></label><input type="date" id="kkTgl" name="tgl" required value="<?= e($tahun === $tahunKini ? date('Y-m-d') : $tahun . '-01-01') ?>" min="<?= $tahun ?>-01-01" max="<?= $tahun ?>-12-31"></div>
          <div class="form-baris" style="grid-column:span 2"><label class="fl" for="kkDesk">Description <span class="wajib">*</span></label><input type="text" id="kkDesk" name="deskripsi" maxlength="200" required list="kkSaranAc" placeholder="Cuci filter, cek freon, perbaikan…">
            <datalist id="kkSaranAc"><?php foreach (ASET_KEGIATAN_AC as $s): ?><option value="<?= e($s) ?>"><?php endforeach; ?></datalist></div>
          <div class="form-baris"><label class="fl" for="kkArus">Arus (Ampere)</label><input type="text" id="kkArus" name="arus" maxlength="30" inputmode="decimal" placeholder="mis. 4,5 A"></div>
          <div class="form-baris"><label class="fl" for="kkTeg">Tegangan (Volt)</label><input type="text" id="kkTeg" name="tegangan" maxlength="30" inputmode="decimal" placeholder="mis. 220 V"></div>
          <div class="form-baris"><label class="fl" for="kkTek">Tekanan (psi)</label><input type="text" id="kkTek" name="tekanan" maxlength="30" inputmode="decimal" placeholder="mis. 70 psi"></div>
        </div>
        <div class="form-baris"><label class="fl" for="kkRem">Remaks</label><input type="text" id="kkRem" name="remaks" maxlength="300" placeholder="Kondisi / catatan hasil pemeriksaan"></div>
        <button class="btn btn-utama" type="submit"><?= ikon('tambah', 15) ?> Simpan pemeriksaan</button>
      </form>
    </div>
  <?php endif; ?>

<?php else: ?>
  <?php if (!$ruangId): ?>
    <div class="kartu">
      <div class="kartu-kepala"><div><h2><?= ikon('aset', 18) ?> Ringkasan kartu inventaris sarana · <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></h2></div></div>
      <?php if (!$ruangan): ?><?= sel_kosong($hanyaTugas ? $kosongTugas : 'Belum ada ruangan aktif') ?><?php else:
          $jmlBarang = [];
          foreach (rows("SELECT ruang_id, COUNT(*) AS n FROM aset_barang WHERE status = 'aktif' AND ruang_id IS NOT NULL GROUP BY ruang_id") as $x) { $jmlBarang[(int) $x['ruang_id']] = (int) $x['n']; } ?>
      <div class="tabel-bungkus"><table class="tabel tabel-tim-tengah">
        <thead><tr><th class="kolom-no">No</th><th>Ruangan</th><th>PJ ruangan</th><th>Sarana</th><th>Diperiksa bulan ini</th><th>Terakhir diisi</th><th>Kontrol Kabid</th><th class="kolom-aksi">Aksi</th></tr></thead>
        <tbody>
        <?php foreach ($ruangan as $i => $r): $d = $data[(string) $r['id']] ?? []; $n = 0; $terakhir = null;
            foreach ((array) ($d['isi'] ?? []) as $isi) { if (!empty($isi[$kunciBulan])) { $n++; $s = $isi[$kunciBulan]['s'] ?? null; if ($s && (!$terakhir || $s['waktu'] > $terakhir['waktu'])) { $terakhir = $s; } } }
            $tot = $jmlBarang[(int) $r['id']] ?? 0; ?>
          <tr><td class="kolom-no"><?= $i + 1 ?></td><td><b><?= e($r['nama']) ?></b><div class="kecil lemah"><?= e($r['kode']) ?></div></td><td class="kecil"><?= e(kk_pj_ruang($r, $penugasan) ?: '-') ?><?php if ($bolehTugaskan): ?><button class="btn btn-hantu btn-kecil kk-tugas" type="button" data-buka="tiraiTugasKK" data-isi='<?= e(json_encode(['ruang' => (int) $r['id'], 'objek_teks' => $r['nama'], 'petugas_pilih' => implode(',', $penugasan[(int) $r['id']] ?? [])])) ?>'><?= ikon('sunting', 13) ?> Tugaskan</button><?php endif; ?></td>
            <td class="tengah"><?= $tot ?></td><td class="tengah"><?= $n === 0 ? '<span class="lemah">-</span>' : lencana($n . '/' . max($tot, $n), $n >= $tot ? 'hijau' : 'emas') ?></td>
            <td class="kecil"><?= $terakhir ? e($terakhir['nama']) . '<div class="lemah">' . e(kk_waktu($terakhir)) . '</div>' : '<span class="lemah">-</span>' ?></td>
            <td><?= !empty($d['kontrol'][$kunciBulan]) ? lencana('Diparaf', 'hijau') : lencana('Belum', 'abu') ?></td>
            <td class="kolom-aksi"><a class="btn btn-utama btn-kecil" href="<?= e(url($h, ['ruang' => $r['id'], 'bulan' => $bulan, 'tahun' => $tahun])) ?>">Buka</a></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <?php endif; ?>
    </div>
  <?php else: $r = $petaRuang[$ruangId]; $d = $data[(string) $ruangId] ?? []; $kontrol = $d['kontrol'][$kunciBulan] ?? null; $kunci = $kontrol && !$pengelola;
      $barang = kk_barang_ruang($ruangId, array_keys((array) ($d['isi'] ?? []))); $saran = kk_saran_sarana($barang, $ruangId, $tahun, $bulan); ?>
    <?= kk_tab_bulan($h, $tahun, $bulan, ['ruang' => $ruangId], (array) ($d['kontrol'] ?? [])) ?>
    <form method="post" class="kartu">
      <?= csrf_field() ?><input type="hidden" name="op" value="simpan"><input type="hidden" name="ruang" value="<?= $ruangId ?>"><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="bulan" value="<?= $bulan ?>">
      <div class="kartu-kepala"><div><h2><?= e($r['nama']) ?> · <?= e(NAMA_BULAN[$bulan] . ' ' . $tahun) ?></h2>
        <p class="ket">PJ ruangan: <?= e(kk_pj_ruang($r, $penugasan) ?: '-') ?> · <?= count($barang) ?> sarana<?= $kontrol ? ' · ' . lencana('Diparaf ' . $kontrol['nama'], 'hijau') : '' ?></p></div>
        <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url($h, ['bulan' => $bulan, 'tahun' => $tahun])) ?>"><?= ikon('kembali', 14) ?> Ringkasan</a></div></div>
      <?php if (!$barang): ?><?= sel_kosong('Belum ada sarana tercatat di ruangan ini. Catat dahulu di Inventaris Barang.') ?><?php else: ?>
      <div class="tabel-bungkus"><table class="tabel kk-tabel kk-sarana">
        <thead><tr><th class="kolom-no">No</th><th>Nama sarana</th><th>Code</th><th title="QTY">Q</th><th title="Kondisi (Baik/Rusak)">K</th><th title="Dipinjam">P</th><th title="Mutasi">M</th><th title="User">U</th><th title="Sign">S</th></tr></thead>
        <tbody>
        <?php $no = 0; foreach ($barang as $id => $b): $no++; $isi = $d['isi'][(string) $id][$kunciBulan] ?? null; $v = $isi ?: $saran[$id]; ?>
          <tr class="<?= $isi ? '' : 'kk-saran' ?>"><td class="kolom-no"><?= $no ?></td>
            <td><b><?= e($b['nama']) ?></b><?= $b['pindah'] ? '<div class="kecil lemah">kini di ' . e($b['ruang_nama'] ?: 'tanpa ruangan') . '</div>' : '' ?></td>
            <td class="kecil nowrap"><code><?= e($b['kode']) ?></code></td>
            <td><input class="kk-kecil" type="text" inputmode="numeric" maxlength="6" name="s[<?= $id ?>][q]" value="<?= e($v['q'] ?? '') ?>"<?= $kunci ? ' disabled' : '' ?> aria-label="QTY <?= e($b['nama']) ?>"></td>
            <td><select name="s[<?= $id ?>][k]"<?= $kunci ? ' disabled' : '' ?> aria-label="Kondisi <?= e($b['nama']) ?>"><option value="">-</option><option value="B"<?= ($v['k'] ?? '') === 'B' ? ' selected' : '' ?>>B</option><option value="R"<?= ($v['k'] ?? '') === 'R' ? ' selected' : '' ?>>R</option></select></td>
            <td><input class="kk-kecil" type="text" inputmode="numeric" maxlength="6" name="s[<?= $id ?>][p]" value="<?= e($v['p'] ?? '') ?>"<?= $kunci ? ' disabled' : '' ?> aria-label="Dipinjam <?= e($b['nama']) ?>"></td>
            <td><input type="text" maxlength="120" name="s[<?= $id ?>][m]" value="<?= e($v['m'] ?? '') ?>"<?= $kunci ? ' disabled' : '' ?> aria-label="Mutasi <?= e($b['nama']) ?>"></td>
            <td><input type="text" maxlength="120" name="s[<?= $id ?>][u]" value="<?= e($v['u'] ?? '') ?>"<?= $kunci ? ' disabled' : '' ?> aria-label="User <?= e($b['nama']) ?>"></td>
            <td class="kecil"><?= $isi && !empty($isi['s']) ? e($isi['s']['nama']) . '<div class="lemah">' . e(kk_waktu($isi['s'])) . '</div>' : '<span class="lemah">belum</span>' ?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
      <div class="kartu-kaki kk-kaki">
        <div class="kk-legenda"><?php foreach (KK_KOLOM_SARANA as $kk => $ket): ?><span><b><?= strtoupper($kk) ?></b> <?= e($ket) ?></span><?php endforeach; ?></div>
        <?php if (!$kunci): ?><button class="btn btn-utama" type="submit"><?= ikon('simpan', 15) ?> Simpan kartu</button><?php endif; ?>
      </div>
      <?php endif; ?>
    </form>
    <?= kk_panel_kontrol($h, $pengelola, $kontrol, ['ruang' => $ruangId, 'tahun' => $tahun, 'bulan' => $bulan]) ?>
  <?php endif; ?>
<?php endif; ?>

<?php if ($bolehTugaskan && !($jenisKK === 'ac' ? $barangId : $ruangId)): ?>
<div class="tirai" id="tiraiTugasKK" hidden>
  <div class="modal sempit">
    <form method="post">
      <?= csrf_field() ?><input type="hidden" name="op" value="tugaskan"><input type="hidden" name="<?= $jenisKK === 'ac' ? 'barang' : 'ruang' ?>" value=""><input type="hidden" name="tahun" value="<?= $tahun ?>"><input type="hidden" name="bulan" value="<?= $bulan ?>">
      <div class="modal-kepala"><h2>PJ <?= e(KK_HALAMAN[$h]) ?></h2><button type="button" class="tutup" data-tutup aria-label="Tutup"><?= ikon('tutup', 16) ?></button></div>
      <div class="modal-isi">
        <p class="kecil"><?= $jenisKK === 'ac' ? 'Unit AC' : 'Ruangan' ?>: <b data-teks="objek_teks"></b></p>
        <?php if (!$petugasCs): ?><p class="kecil lemah">Belum ada pengguna dengan izin Kartu Kontrol CS.</p><?php endif; ?>
        <div class="kk-pilih-petugas" data-ganda="petugas_pilih">
          <?php foreach ($petugasCs as $pid => $nm): ?><label><input type="checkbox" name="petugas[]" value="<?= (int) $pid ?>"> <?= e($nm) ?></label><?php endforeach; ?>
        </div>
      </div>
      <div class="modal-kaki"><button type="button" class="btn btn-garis" data-tutup>Batal</button><button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan</button></div>
    </form>
  </div>
</div>
<?php endif; ?>