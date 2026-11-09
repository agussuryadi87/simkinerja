<?php

function periode_aktif(): ?array
{
    static $p = false;
    if ($p === false) {
        $p = row('SELECT * FROM periode WHERE aktif = 1 ORDER BY mulai DESC LIMIT 1')
          ?? row('SELECT * FROM periode ORDER BY mulai DESC LIMIT 1');
    }
    return $p;
}

function semua_periode(): array
{
    static $d = null;
    return $d ??= rows('SELECT * FROM periode ORDER BY mulai DESC');
}

function periode_dipilih(): ?array
{
    static $p = false;
    if ($p !== false) {
        return $p;
    }
    if (isset($_GET['periode'])) {
        $_SESSION['periode_id'] = (int) $_GET['periode'];
    }
    $id = (int) ($_SESSION['periode_id'] ?? 0);
    foreach (semua_periode() as $r) {
        if ((int) $r['id'] === $id) {
            return $p = $r;
        }
    }
    unset($_SESSION['periode_id']);
    return $p = periode_aktif();
}

function pid(): int
{
    return (int) (periode_dipilih()['id'] ?? 0);
}

function periode_terbuka(): bool
{
    $p = periode_dipilih();
    return $p && $p['tahap'] !== 'selesai';
}

function pegawai_get(int $id): ?array
{
    return row(
        'SELECT p.*, un.nama AS unit_nama, un.kode AS unit_kode, pr.nama AS peran_nama, at.nama AS atasan_nama
           FROM pengguna p
           JOIN peran pr ON pr.id = p.peran_id
           LEFT JOIN unit un ON un.id = p.unit_id
           LEFT JOIN pengguna at ON at.id = p.atasan_id
          WHERE p.id = ?',
        [$id]
    );
}

function pegawai_tim(): array
{
    $ids = tim_ids();
    if (!$ids) {
        return [];
    }
    return rows(
        'SELECT p.id, p.nama, p.jabatan, p.nip, p.unit_id, p.atasan_id, un.nama AS unit_nama, un.urut AS unit_urut,
                pr.nama AS peran_nama, pr.urut AS peran_urut, pr.kode AS peran_kode
           FROM pengguna p
           JOIN peran pr ON pr.id = p.peran_id
           LEFT JOIN unit un ON un.id = p.unit_id
          WHERE p.id IN (' . in_placeholder($ids) . ')
          ORDER BY pr.urut, p.nama',
        $ids
    );
}

function pilihan_pegawai(bool $termasukSaya = true): array
{
    $daftar = [];
    if ($termasukSaya) {
        $u = user();
        $daftar[] = ['id' => (int) $u['id'], 'nama' => $u['nama'] . ' (saya)', 'unit_nama' => $u['unit_nama'] ?? ''];
    }
    $anggota = [];
    foreach (pegawai_tim() as $p) {
        $anggota[] = ['id' => (int) $p['id'], 'nama' => $p['nama'], 'unit_nama' => $p['unit_nama'] ?? '', 'peran_kode' => $p['peran_kode']];
    }
    usort($anggota, 'bandingkan_pilihan_pegawai');
    return array_merge($daftar, $anggota);
}

function bandingkan_pilihan_pegawai(array $a, array $b): int
{
    $ka = ($a['peran_kode'] ?? '') === 'kabiro' ? 0 : 1;
    $kb = ($b['peran_kode'] ?? '') === 'kabiro' ? 0 : 1;
    return ($ka <=> $kb) ?: strcasecmp($a['nama'], $b['nama']);
}

function pegawai_dari_alamat(): int
{
    $minta = (int) ($_GET['p'] ?? 0);
    if ($minta > 0 && boleh_lihat_pegawai($minta)) {
        return $minta;
    }
    return uid();
}

function unit_semua(bool $hanyaAktif = false): array
{
    $baris = rows('SELECT * FROM unit' . ($hanyaAktif ? ' WHERE aktif = 1' : '') . ' ORDER BY urut, nama');
    $anak = [];
    foreach ($baris as $r) {
        $anak[(int) ($r['induk_id'] ?? 0)][] = $r;
    }
    $hasil = [];
    $susun = static function (int $induk, int $tingkat) use (&$susun, &$hasil, $anak) {
        foreach ($anak[$induk] ?? [] as $r) {
            $r['tingkat'] = $tingkat;
            $hasil[] = $r;
            $susun((int) $r['id'], $tingkat + 1);
        }
    };
    $susun(0, 0);
    $ada = array_column($hasil, 'id');
    foreach ($baris as $r) {
        if (!in_array($r['id'], $ada, true)) {
            $r['tingkat'] = 0;
            $hasil[] = $r;
        }
    }
    return $hasil;
}

function opsi_unit($terpilih, string $kosong = 'Tanpa unit'): string
{
    $h = '<option value="">' . e($kosong) . '</option>';
    foreach (unit_semua() as $un) {
        $h .= '<option value="' . (int) $un['id'] . '"' . ((int) $terpilih === (int) $un['id'] ? ' selected' : '') . '>'
            . str_repeat('&nbsp;&nbsp;&nbsp;', (int) $un['tingkat']) . e($un['nama']) . '</option>';
    }
    return $h;
}

function tw_rentang(?array $periode, int $tw): array
{
    if (!$periode) {
        return [null, null];
    }
    $awal = new DateTime(substr($periode['mulai'], 0, 7) . '-01');
    $awal->modify('+' . (3 * ($tw - 1)) . ' months');
    $akhir = clone $awal;
    $akhir->modify('+3 months')->modify('-1 day');
    return [$awal->format('Y-m-d'), $akhir->format('Y-m-d')];
}

function tw_label(?array $periode, int $tw, bool $panjang = false): string
{
    [$mulai, $selesai] = tw_rentang($periode, $tw);
    if (!$mulai) {
        return 'TW' . $tw;
    }
    $b1 = (int) substr($mulai, 5, 2);
    $b3 = (int) substr($selesai, 5, 2);
    if ($panjang) {
        return 'TW' . $tw . ' (' . NAMA_BULAN[$b1] . ' ' . substr($mulai, 0, 4) . ' s.d. ' . NAMA_BULAN[$b3] . ' ' . substr($selesai, 0, 4) . ')';
    }
    return 'TW' . $tw . ' (' . mb_substr(NAMA_BULAN[$b1], 0, 3) . '-' . mb_substr(NAMA_BULAN[$b3], 0, 3) . ')';
}

function tw_dari_tanggal(?array $periode, string $tanggal): int
{
    if (!$periode) {
        return 1;
    }
    $a = new DateTime(substr($periode['mulai'], 0, 7) . '-01');
    $b = new DateTime(substr($tanggal, 0, 7) . '-01');
    $selisih = ((int) $b->format('Y') - (int) $a->format('Y')) * 12 + ((int) $b->format('n') - (int) $a->format('n'));
    return max(1, min(4, intdiv(max(0, $selisih), 3) + 1));
}

function tw_berjalan(?array $periode = null): int
{
    $periode = $periode ?? periode_dipilih();
    if (!$periode) {
        return 1;
    }
    $hari = date('Y-m-d');
    if ($hari < $periode['mulai']) {
        return 1;
    }
    if ($hari > $periode['selesai']) {
        return 4;
    }
    return tw_dari_tanggal($periode, $hari);
}

function tw_dipilih(): int
{
    $tw = (int) ($_GET['tw'] ?? 0);
    return $tw >= 1 && $tw <= 4 ? $tw : tw_berjalan();
}

function pilihan_rentang(?array $periode): array
{
    $hasil = ['semua' => 'Sepanjang periode'];
    if (!$periode) {
        return $hasil;
    }
    for ($t = 1; $t <= 4; $t++) {
        $hasil['tw' . $t] = tw_label($periode, $t);
    }
    $awal = new DateTime(substr($periode['mulai'], 0, 7) . '-01');
    $akhir = new DateTime(substr($periode['selesai'], 0, 7) . '-01');
    while ($awal <= $akhir) {
        $hasil[$awal->format('Y-m')] = NAMA_BULAN[(int) $awal->format('n')] . ' ' . $awal->format('Y');
        $awal->modify('+1 month');
    }
    return $hasil;
}

function rentang_sah(?array $periode, string $nilai, string $bawaan = 'semua'): string
{
    return array_key_exists($nilai, pilihan_rentang($periode)) ? $nilai : $bawaan;
}

function syarat_rentang(?array $periode, string $nilai, string $kolom = 'k.tanggal'): array
{
    if (preg_match('/^tw([1-4])$/', $nilai, $m)) {
        [$mulai, $selesai] = tw_rentang($periode, (int) $m[1]);
        return [" AND $kolom BETWEEN ? AND ?", [$mulai, $selesai]];
    }
    if (preg_match('/^\d{4}-\d{2}$/', $nilai)) {
        return [" AND DATE_FORMAT($kolom, '%Y-%m') = ?", [$nilai]];
    }
    return ['', []];
}

function hari_kerja_antara(string $mulai, string $selesai): int
{
    $a = new DateTime($mulai);
    $b = new DateTime($selesai);
    $n = 0;
    for (; $a <= $b; $a->modify('+1 day')) {
        if ((int) $a->format('N') <= 5) {
            $n++;
        }
    }
    return $n;
}

function capaian_harian_rentang(int $periodeId, int $pegawaiId, string $mulai, string $selesai): array
{
    $hasil = ['persen' => null, 'hari_kerja' => 0, 'hari_tercatat' => 0, 'kegiatan_ok' => 0, 'kegiatan_total' => 0, 'jam' => 0.0];
    $akhir = min($selesai, date('Y-m-d'));
    $hasil['hari_kerja'] = $mulai <= $akhir ? hari_kerja_antara($mulai, $akhir) : 0;
    $r = row(
        "SELECT COUNT(DISTINCT CASE WHEN status = 'disetujui' AND WEEKDAY(tanggal) < 5 THEN tanggal END) AS hari,
                COALESCE(SUM(status = 'disetujui'), 0) AS ok, COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN status = 'disetujui' AND jam_mulai IS NOT NULL AND jam_selesai > jam_mulai
                                  THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) ELSE 0 END), 0) AS detik
           FROM kegiatan WHERE periode_id = ? AND pengguna_id = ? AND tanggal BETWEEN ? AND ?" . sql_bukan_draft(),
        [$periodeId, $pegawaiId, $mulai, $selesai]
    ) ?? [];
    $hasil['hari_tercatat'] = (int) ($r['hari'] ?? 0);
    $hasil['kegiatan_ok'] = (int) ($r['ok'] ?? 0);
    $hasil['kegiatan_total'] = (int) ($r['total'] ?? 0);
    $hasil['jam'] = (float) ($r['detik'] ?? 0) / 3600;
    if ($hasil['hari_kerja'] > 0) {
        $hasil['persen'] = min(100.0, $hasil['hari_tercatat'] / $hasil['hari_kerja'] * 100);
    }
    return $hasil;
}

function capaian_harian(?array $periode, int $pegawaiId, int $tw): array
{
    if (!$periode) {
        return ['persen' => null, 'hari_kerja' => 0, 'hari_tercatat' => 0, 'kegiatan_ok' => 0, 'kegiatan_total' => 0, 'jam' => 0.0];
    }
    if (capaian_dikosongkan((int) $periode['id'], $pegawaiId, $tw)) {
        return ['persen' => null, 'hari_kerja' => 0, 'hari_tercatat' => 0, 'kegiatan_ok' => 0, 'kegiatan_total' => 0, 'jam' => 0.0, 'dikosongkan' => true];
    }
    [$mulai, $selesai] = tw_rentang($periode, $tw);
    return capaian_harian_rentang((int) $periode['id'], $pegawaiId, $mulai, $selesai);
}

const JAM_KERJA_HARIAN = 6;
const HARI_KERJA_SEPEKAN = 5;
const PEKAN_SEBULAN = 4;
const JAM_KERJA_BULANAN = JAM_KERJA_HARIAN * HARI_KERJA_SEPEKAN * PEKAN_SEBULAN;

function bulan_dalam_rentang(string $dari, string $sampai): int
{
    $akhir = min($sampai, date('Y-m-d'));
    if ($dari > $akhir) {
        return 0;
    }
    $a = (int) substr($dari, 0, 4) * 12 + (int) substr($dari, 5, 2);
    $b = (int) substr($akhir, 0, 4) * 12 + (int) substr($akhir, 5, 2);
    return $b - $a + 1;
}

function target_jam_kerja(string $dari, string $sampai): array
{
    $bulan = bulan_dalam_rentang($dari, $sampai);
    return ['bulan' => $bulan, 'jam' => $bulan * JAM_KERJA_BULANAN];
}

function keterangan_jam_kerja(float $jam, float $target): array
{
    if ($target <= 0) {
        return ['label' => '-', 'warna' => 'abu', 'persen' => null];
    }
    $persen = $jam / $target * 100;
    if ($persen >= 100) {
        $hasil = ['label' => 'Sangat Baik', 'warna' => 'hijau'];
    } elseif ($persen >= 76) {
        $hasil = ['label' => 'Baik', 'warna' => 'biru'];
    } elseif ($persen >= 61) {
        $hasil = ['label' => 'Cukup', 'warna' => 'emas'];
    } elseif ($persen >= 51) {
        $hasil = ['label' => 'Kurang', 'warna' => 'jingga'];
    } else {
        $hasil = ['label' => 'Sangat Kurang', 'warna' => 'merah'];
    }
    return $hasil + ['persen' => $persen];
}

function capaian_evaluasi(?array $periode, int $pegawaiId, int $tw, ?array $ev = null): array
{
    $hasil = capaian_harian($periode, $pegawaiId, $tw);
    if ($ev && $ev['status'] === 'final' && $ev['nilai_capaian'] !== null) {
        $hasil['persen'] = (float) $ev['nilai_capaian'];
        $hasil['final'] = true;
    }
    return $hasil;
}

function capaian_rentang_evaluasi(?array $periode, int $pegawaiId, string $dari, string $sampai, array $evTw = []): array
{
    $hasil = ['persen' => null, 'hari_kerja' => 0, 'hari_tercatat' => 0, 'kegiatan_ok' => 0, 'kegiatan_total' => 0, 'jam' => 0.0];
    if (!$periode) {
        return $hasil;
    }
    $hariIni = date('Y-m-d');
    $bagian = 0;
    $kosong = 0;
    $bobot = 0.0;
    for ($t = 1; $t <= 4; $t++) {
        [$m, $s] = tw_rentang($periode, $t);
        $awal = max($m, $dari);
        $akhir = min($s, $sampai);
        if ($awal > $akhir || $awal > $hariIni) {
            continue;
        }
        $bagian++;
        if (capaian_dikosongkan((int) $periode['id'], $pegawaiId, $t)) {
            $kosong++;
            continue;
        }
        $r = capaian_harian_rentang((int) $periode['id'], $pegawaiId, $awal, $akhir);
        foreach (['hari_kerja', 'hari_tercatat', 'kegiatan_ok', 'kegiatan_total', 'jam'] as $k) {
            $hasil[$k] += $r[$k];
        }
        $ev = $evTw[$t] ?? null;
        if ($ev && $ev['status'] === 'final' && $ev['nilai_capaian'] !== null) {
            $bobot += (float) $ev['nilai_capaian'] * $r['hari_kerja'];
            $hasil['final'] = true;
        } else {
            $bobot += min(100.0, $r['hari_kerja'] > 0 ? $r['hari_tercatat'] / $r['hari_kerja'] * 100 : 0) * $r['hari_kerja'];
        }
    }
    if ($bagian > 0 && $kosong === $bagian) {
        $hasil['dikosongkan'] = true;
    } elseif ($hasil['hari_kerja'] > 0) {
        $hasil['persen'] = min(100.0, $bobot / $hasil['hari_kerja']);
    }
    return $hasil;
}

function berkas_capaian_kosong(): string
{
    return APP_ROOT . '/uploads/evaluasi/capaian-kosong.json';
}

function semua_capaian_kosong(bool $muatUlang = false): array
{
    static $data = null;
    if ($data === null || $muatUlang) {
        $berkas = berkas_capaian_kosong();
        $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
        $data = is_array($isi) ? $isi : [];
    }
    return $data;
}

function capaian_dikosongkan(int $periodeId, int $pegawaiId, int $tw): bool
{
    return isset(semua_capaian_kosong()[$periodeId . '-' . $pegawaiId . '-' . $tw]);
}

function atur_capaian_kosong(int $periodeId, int $pegawaiId, int $tw, bool $kosong): bool
{
    $semua = semua_capaian_kosong(true);
    $kunci = $periodeId . '-' . $pegawaiId . '-' . $tw;
    if ($kosong) {
        $semua[$kunci] = date('Y-m-d H:i:s');
    } else {
        unset($semua[$kunci]);
    }
    $berkas = berkas_capaian_kosong();
    if (!is_dir(dirname($berkas)) && !@mkdir(dirname($berkas), 0775, true)) {
        return false;
    }
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode($semua, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    semua_capaian_kosong(true);
    return true;
}
function berkas_kegiatan_draft(): string
{
    return APP_ROOT . '/uploads/kegiatan/draft.json';
}

function kegiatan_draft_semua(bool $muatUlang = false): array
{
    static $data = null;
    if ($data === null || $muatUlang) {
        $berkas = berkas_kegiatan_draft();
        $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
        $data = is_array($isi) ? $isi : [];
    }
    return $data;
}

function kegiatan_draft_ids(): array
{
    return array_values(array_filter(array_map('intval', array_keys(kegiatan_draft_semua()))));
}

function kegiatan_draft(int $id): bool
{
    return isset(kegiatan_draft_semua()[(string) $id]);
}

function atur_kegiatan_draft(int $id, bool $draft): bool
{
    $semua = kegiatan_draft_semua(true);
    if ($draft === isset($semua[(string) $id])) {
        return true;
    }
    if ($draft) {
        $semua[(string) $id] = date('Y-m-d H:i:s');
    } else {
        unset($semua[(string) $id]);
    }
    $berkas = berkas_kegiatan_draft();
    if (!is_dir(dirname($berkas)) && !@mkdir(dirname($berkas), 0775, true)) {
        return false;
    }
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    if (@file_put_contents($sementara, json_encode((object) $semua, JSON_PRETTY_PRINT), LOCK_EX) === false) {
        return false;
    }
    if (!@rename($sementara, $berkas)) {
        @unlink($berkas);
        if (!@rename($sementara, $berkas)) {
            @unlink($sementara);
            return false;
        }
    }
    kegiatan_draft_semua(true);
    return true;
}

function sql_bukan_draft(string $kolom = 'id'): string
{
    $ids = kegiatan_draft_ids();
    return $ids ? ' AND ' . $kolom . ' NOT IN (' . implode(',', $ids) . ')' : '';
}

function sql_hanya_draft(string $kolom = 'id'): string
{
    $ids = kegiatan_draft_ids();
    return ' AND ' . $kolom . ' IN (' . ($ids ? implode(',', $ids) : '0') . ')';
}

function kegiatan_rincian(array $k, string $namaPegawai = ''): string
{
    $draft = kegiatan_draft((int) $k['id']);
    $durasi = durasi_jam($k['jam_mulai'], $k['jam_selesai']);
    $foto = foto_kegiatan((int) $k['id']);
    $verifikator = !$draft && $k['status'] === 'disetujui' && $k['diverifikasi_oleh']
        ? (string) val('SELECT nama FROM pengguna WHERE id = ?', [(int) $k['diverifikasi_oleh']], '') : '';
    $panitia = trim($k['kepanitiaan'] . ($k['peran_panitia'] !== '' ? ' · ' . $k['peran_panitia'] : ''), ' ·');
    $data = [
        'lk_nama'       => $namaPegawai,
        'lk_judul'      => $k['judul'] !== '' ? $k['judul'] : '(belum diberi judul)',
        'lk_tanggal'    => tanggal_id($k['tanggal'], true),
        'lk_jam'        => $k['jam_mulai'] ? jam_pendek($k['jam_mulai']) . ' - ' . jam_pendek($k['jam_selesai']) . ($durasi > 0 ? ' (' . angka_ringkas($durasi, 1) . ' jam)' : '') : '',
        'lk_kehadiran'  => KEHADIRAN_KERJA[$k['kehadiran'] ?? 'masuk']['label'] ?? 'Masuk kerja',
        'lk_jenis'      => ($k['kehadiran'] ?? 'masuk') === 'masuk' ? (JENIS_KERJA[$k['jenis']]['label'] ?? $k['jenis']) : '',
        'lk_status'     => $draft ? 'Draft (belum dikirim, jangan dinilai dulu)' : (STATUS_KEGIATAN[$k['status']]['label'] ?? $k['status']),
        'lk_panitia'    => $panitia,
        'lk_pemberi'    => (string) $k['pemberi_tugas'],
        'lk_sk'         => (string) $k['nomor_sk'],
        'lk_uraian'     => (string) $k['uraian'],
        'lk_hasil'      => $k['hasil'] !== '' ? $k['hasil'] . ($k['satuan'] !== '' ? ' (' . angka_ringkas((float) $k['volume'], 2) . ' ' . $k['satuan'] . ')' : '') : '',
        'lk_bukti'      => (string) $k['tautan_bukti'],
        'lk_foto'       => $foto ? url_berkas('kegiatan', $foto) : '',
        'lk_catatan'    => (string) $k['catatan_atasan'],
        'lk_verifikator' => $verifikator !== '' ? $verifikator . ($k['diverifikasi_pada'] ? ', ' . tanggal_pendek(substr($k['diverifikasi_pada'], 0, 10)) : '') : '',
    ];
    return '<button class="btn btn-hantu btn-ikon" type="button" title="Lihat rincian" aria-label="Lihat rincian kegiatan" data-buka="tiraiLihatK" data-isi=\''
        . e(json_encode($data, JSON_UNESCAPED_UNICODE)) . '\'>' . ikon('mata', 15) . '</button>';
}

function modal_rincian_kegiatan(): string
{
    $baris = [
        'lk_nama' => 'Pegawai', 'lk_tanggal' => 'Tanggal', 'lk_jam' => 'Jam', 'lk_kehadiran' => 'Kehadiran', 'lk_jenis' => 'Jenis pekerjaan',
        'lk_status' => 'Status', 'lk_panitia' => 'Kepanitiaan', 'lk_pemberi' => 'Pemberi tugas', 'lk_sk' => 'Nomor SK / surat tugas',
        'lk_hasil' => 'Hasil / keluaran', 'lk_verifikator' => 'Diverifikasi oleh',
    ];
    $h = '<div class="tirai" id="tiraiLihatK" hidden><div class="modal rincian-kegiatan"><div class="modal-kepala"><h2>Rincian kegiatan</h2>'
       . '<button type="button" class="tutup" data-tutup aria-label="Tutup">' . ikon('tutup', 16) . '</button></div><div class="modal-isi">'
       . '<h3 class="rk-judul" data-teks="lk_judul"></h3><dl class="rk-daftar">';
    foreach ($baris as $kunci => $label) {
        $h .= '<div data-baris="' . $kunci . '"><dt>' . e($label) . '</dt><dd data-teks="' . $kunci . '"></dd></div>';
    }
    $h .= '</dl><div class="rk-blok" data-baris="lk_uraian"><b>Uraian pekerjaan</b><p data-teks="lk_uraian"></p></div>'
        . '<div class="rk-blok rk-catatan" data-baris="lk_catatan"><b>Catatan atasan</b><p data-teks="lk_catatan"></p></div>'
        . '<div class="rk-blok" data-baris="lk_bukti"><b>Tautan bukti dukung</b><p><a data-tautan="lk_bukti" target="_blank" rel="noopener"><span data-teks="lk_bukti"></span></a></p></div>'
        . '<div class="rk-blok" data-baris="lk_foto"><b>Foto bukti</b><a data-tautan="lk_foto" target="_blank" rel="noopener"><img class="rk-foto" data-gambar="lk_foto" alt="Foto bukti kegiatan"></a></div>'
        . '</div><div class="modal-kaki"><button type="button" class="btn btn-utama" data-tutup>Tutup</button></div></div></div>';
    return $h;
}

function lencana_status_kegiatan(array $k): string
{
    return kegiatan_draft((int) ($k['id'] ?? 0)) ? lencana('Draft', 'abu') : lencana_dari(STATUS_KEGIATAN, (string) $k['status']);
}

function nilai_perilaku(array $ev): ?float
{
    $isi = [];
    foreach (array_keys(ASPEK_PERILAKU) as $k) {
        if (!empty($ev[$k])) {
            $isi[] = (int) $ev[$k];
        }
    }
    if (!$isi) {
        return null;
    }
    return array_sum($isi) / count($isi) * 20;
}

function nilai_akhir(?float $capaian, ?float $perilaku): ?float
{
    if ($capaian === null && $perilaku === null) {
        return null;
    }
    return min(($capaian ?? 0) * BOBOT_CAPAIAN + ($perilaku ?? 0) * BOBOT_PERILAKU, 120);
}

function evaluasi_get(int $periodeId, int $pegawaiId, int $tw): ?array
{
    return row(
        'SELECT ev.*, pn.nama AS penilai_nama
           FROM evaluasi ev LEFT JOIN pengguna pn ON pn.id = ev.penilai_id
          WHERE ev.periode_id = ? AND ev.pegawai_id = ? AND ev.tw = ?',
        [$periodeId, $pegawaiId, $tw]
    );
}

function evaluasi_tw_pegawai(int $periodeId, array $pegawaiIds): array
{
    if (!$pegawaiIds) {
        return [];
    }
    $hasil = [];
    foreach (rows(
        'SELECT * FROM evaluasi WHERE periode_id = ? AND pegawai_id IN (' . in_placeholder($pegawaiIds) . ')',
        array_merge([$periodeId], $pegawaiIds)
    ) as $r) {
        $hasil[(int) $r['pegawai_id']][(int) $r['tw']] = $r;
    }
    return $hasil;
}

function nilai_tahunan(array $evaluasiTw): ?float
{
    $nilai = [];
    foreach ($evaluasiTw as $ev) {
        if ($ev['status'] === 'final' && $ev['nilai_akhir'] !== null) {
            $nilai[] = (float) $ev['nilai_akhir'];
        }
    }
    return $nilai ? array_sum($nilai) / count($nilai) : null;
}

function evaluasi_terakhir(int $periodeId, int $pegawaiId): ?array
{
    return row(
        "SELECT ev.*, pn.nama AS penilai_nama FROM evaluasi ev LEFT JOIN pengguna pn ON pn.id = ev.penilai_id
          WHERE ev.periode_id = ? AND ev.pegawai_id = ? AND ev.status = 'final'
          ORDER BY ev.tw DESC LIMIT 1",
        [$periodeId, $pegawaiId]
    );
}

function ringkasan_kegiatan(int $periodeId, array $pegawaiIds, ?string $dari = null, ?string $sampai = null): array
{
    if (!$pegawaiIds) {
        return [];
    }
    $syarat = 'periode_id = ? AND pengguna_id IN (' . in_placeholder($pegawaiIds) . ')';
    $param = array_merge([$periodeId], $pegawaiIds);
    if ($dari) {
        $syarat .= ' AND tanggal >= ?';
        $param[] = $dari;
    }
    if ($sampai) {
        $syarat .= ' AND tanggal <= ?';
        $param[] = $sampai;
    }
    $hasil = [];
    foreach ($pegawaiIds as $id) {
        $hasil[$id] = ['total' => 0, 'rutin' => 0, 'panitia' => 0, 'insidental' => 0,
                       'diajukan' => 0, 'revisi' => 0, 'disetujui' => 0, 'draft' => 0, 'jam' => 0.0, 'terakhir' => null];
    }
    foreach (rows("SELECT pengguna_id, COUNT(*) AS n FROM kegiatan WHERE $syarat" . sql_hanya_draft() . ' GROUP BY pengguna_id', $param) as $r) {
        $hasil[(int) $r['pengguna_id']]['draft'] = (int) $r['n'];
    }
    $syarat .= sql_bukan_draft();
    foreach (rows(
        "SELECT pengguna_id, jenis, status, COUNT(*) AS n, MAX(tanggal) AS terakhir,
                SUM(CASE WHEN jam_mulai IS NOT NULL AND jam_selesai > jam_mulai
                         THEN TIME_TO_SEC(TIMEDIFF(jam_selesai, jam_mulai)) ELSE 0 END) AS detik
           FROM kegiatan WHERE $syarat
          GROUP BY pengguna_id, jenis, status",
        $param
    ) as $r) {
        $id = (int) $r['pengguna_id'];
        $n = (int) $r['n'];
        $hasil[$id]['total'] += $n;
        $hasil[$id][$r['jenis']] += $n;
        $hasil[$id][$r['status']] += $n;
        $hasil[$id]['jam'] += (float) $r['detik'] / 3600;
        if ($r['terakhir'] > $hasil[$id]['terakhir']) {
            $hasil[$id]['terakhir'] = $r['terakhir'];
        }
    }
    return $hasil;
}

function punya_atasan(int $pegawaiId): bool
{
    return (int) val('SELECT COUNT(*) FROM pengguna WHERE id = ? AND atasan_id IS NOT NULL', [$pegawaiId], 0) > 0;
}

function boleh_verifikasi(int $pegawaiId): bool
{
    return can('pantau') && di_tim($pegawaiId);
}

function antrean_verifikasi(): array
{
    $ids = can('pantau') ? tim_ids() : [];
    if (!$ids) {
        return ['kegiatan' => 0];
    }
    $param = array_merge([pid()], $ids);
    $ph = in_placeholder($ids);
    return [
        'kegiatan' => (int) val("SELECT COUNT(*) FROM kegiatan WHERE periode_id = ? AND status = 'diajukan' AND pengguna_id IN ($ph)" . sql_bukan_draft(), $param, 0),
    ];
}

function umpan_balik_belum_dibaca(): int
{
    return (int) val('SELECT COUNT(*) FROM umpan_balik WHERE pegawai_id = ? AND dibaca = 0', [uid()], 0);
}

function kegiatan_per_bulan(int $periodeId, int $pegawaiId): array
{
    $p = periode_dipilih();
    $hasil = [];
    if (!$p) {
        return $hasil;
    }
    $awal = new DateTime(substr($p['mulai'], 0, 7) . '-01');
    $akhir = new DateTime(substr($p['selesai'], 0, 7) . '-01');
    while ($awal <= $akhir) {
        $hasil[$awal->format('Y-m')] = ['rutin' => 0, 'panitia' => 0, 'insidental' => 0];
        $awal->modify('+1 month');
    }
    foreach (rows(
        "SELECT DATE_FORMAT(tanggal, '%Y-%m') AS bln, jenis, COUNT(*) AS n
           FROM kegiatan WHERE periode_id = ? AND pengguna_id = ?" . sql_bukan_draft() . "
          GROUP BY bln, jenis",
        [$periodeId, $pegawaiId]
    ) as $r) {
        if (isset($hasil[$r['bln']])) {
            $hasil[$r['bln']][$r['jenis']] = (int) $r['n'];
        }
    }
    return $hasil;
}
