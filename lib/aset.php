<?php

const ASET_JENIS_RUANG = [
    'ruang_kelas' => 'Ruang kelas', 'laboratorium' => 'Laboratorium', 'kantor' => 'Ruang kantor', 'rapat' => 'Ruang rapat',
    'aula' => 'Aula / auditorium', 'perpustakaan' => 'Perpustakaan', 'toilet' => 'Toilet', 'gudang' => 'Gudang',
    'mushola' => 'Mushola', 'lainnya' => 'Lainnya',
];

const ASET_KATEGORI = [
    'furnitur' => 'Furnitur', 'elektronik' => 'Elektronik', 'ac' => 'Pendingin ruangan (AC)', 'audio_visual' => 'Audio visual / proyektor',
    'komputer' => 'Komputer & jaringan', 'alat_kantor' => 'Alat kantor', 'laboratorium' => 'Peralatan laboratorium',
    'kendaraan' => 'Kendaraan', 'kebersihan' => 'Alat kebersihan', 'lainnya' => 'Lainnya',
];

const ASET_KONDISI = [
    'baik'         => ['label' => 'Baik',         'warna' => 'hijau'],
    'rusak_ringan' => ['label' => 'Rusak ringan', 'warna' => 'kuning'],
    'rusak_berat'  => ['label' => 'Rusak berat',  'warna' => 'merah'],
    'hilang'       => ['label' => 'Hilang',       'warna' => 'abu'],
];

const ASET_TINGKAT = [
    'ringan' => ['label' => 'Ringan', 'warna' => 'kuning'],
    'sedang' => ['label' => 'Sedang', 'warna' => 'jingga'],
    'berat'  => ['label' => 'Berat',  'warna' => 'merah'],
];

const ASET_STATUS_RUSAK = [
    'dilaporkan' => ['label' => 'Dilaporkan', 'warna' => 'merah'],
    'diproses'   => ['label' => 'Diproses',   'warna' => 'biru'],
    'selesai'    => ['label' => 'Selesai',    'warna' => 'hijau'],
    'ditolak'    => ['label' => 'Ditolak',    'warna' => 'abu'],
];

const ASET_BAGIAN_PRASARANA = [
    'Atap / plafon', 'Dinding / cat', 'Lantai', 'Pintu / jendela', 'Instalasi listrik', 'Instalasi air / sanitasi',
    'Jaringan internet', 'Taman / halaman', 'Jalan / parkir', 'Lainnya',
];

const ASET_KEGIATAN_KEBERSIHAN = [
    'Sapu dan pel lantai', 'Pembersihan toilet', 'Pembersihan kaca dan jendela', 'Pembersihan meja dan kursi',
    'Pembuangan sampah', 'Pembersihan menyeluruh (general cleaning)',
];

const ASET_KEGIATAN_AC = [
    'Cuci filter / indoor', 'Cuci unit outdoor', 'Pengecekan dan isi freon', 'Pengecekan rutin', 'Perbaikan', 'Penggantian komponen',
];

const ASET_ULANG = [
    '' => 'Tidak berulang', '7' => 'Tiap 7 hari', '14' => 'Tiap 14 hari', '30' => 'Tiap 1 bulan', '90' => 'Tiap 3 bulan', '180' => 'Tiap 6 bulan',
];

function boleh_aset(): bool
{
    return uid() > 0 && can('modul_aset');
}

function wajib_aset(): void
{
    wajib_masuk();
    if (!boleh_aset()) {
        tolak_akses();
    }
}

function aset_rupiah($n): string
{
    return $n === null || $n === '' ? '-' : 'Rp ' . angka((float) $n);
}

function aset_tanggal_sah_atau_null(string $tgl): ?string
{
    return $tgl !== '' && tanggal_sah($tgl) ? $tgl : null;
}

function aset_angka_atau_null(string $kunci): ?float
{
    $v = str_replace(['.', ','], ['', '.'], post($kunci));
    return $v !== '' && is_numeric($v) && (float) $v >= 0 ? (float) $v : null;
}

function opsi_ruang($terpilih = 0, string $kosong = '— Pilih ruangan —', bool $hanyaAktif = true): string
{
    $h = '<option value="">' . e($kosong) . '</option>';
    foreach (rows('SELECT id, kode, nama, aktif FROM aset_ruang ' . ($hanyaAktif ? 'WHERE aktif = 1 OR id = ' . (int) $terpilih : '') . ' ORDER BY nama') as $r) {
        $h .= '<option value="' . (int) $r['id'] . '"' . ((int) $terpilih === (int) $r['id'] ? ' selected' : '') . '>'
            . e($r['nama'] . ' (' . $r['kode'] . ')') . '</option>';
    }
    return $h;
}

function opsi_barang($terpilih = 0, string $kosong = '— Pilih barang —', string $kategori = ''): string
{
    $h = '<option value="">' . e($kosong) . '</option>';
    $baris = rows(
        'SELECT b.id, b.kode, b.nama, r.nama AS ruang FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id
          WHERE b.status = \'aktif\'' . ($kategori !== '' ? ' AND b.kategori = ?' : '') . ' ORDER BY b.nama, b.kode',
        $kategori !== '' ? [$kategori] : []
    );
    foreach ($baris as $r) {
        $h .= '<option value="' . (int) $r['id'] . '"' . ((int) $terpilih === (int) $r['id'] ? ' selected' : '') . '>'
            . e($r['nama'] . ' — ' . $r['kode'] . ($r['ruang'] ? ' · ' . $r['ruang'] : '')) . '</option>';
    }
    return $h;
}

function aset_foto_daftar(string $kolom, int $id): array
{
    if (!in_array($kolom, ['ruang_id', 'barang_id', 'kerusakan_id', 'pengajuan_id'], true)) {
        return [];
    }
    return rows("SELECT * FROM aset_foto WHERE $kolom = ? ORDER BY id", [$id]);
}

function aset_foto_url(?string $berkas): string
{
    return url_berkas('aset', (string) $berkas);
}

function aset_penginput(string $objek, array $daftar): array
{
    $ids = array_values(array_filter(array_map('intval', array_column($daftar, 'id'))));
    if (!$ids) {
        return [];
    }
    $hasil = [];
    $oleh = array_values(array_unique(array_filter(array_map('intval', array_column($daftar, 'dibuat_oleh')))));
    $nama = [];
    if ($oleh) {
        foreach (rows('SELECT id, nama FROM pengguna WHERE id IN (' . in_placeholder($oleh) . ')', $oleh) as $p) {
            $nama[(int) $p['id']] = $p['nama'];
        }
    }
    $publik = $objek === 'aset_pengajuan' ? aset_publik_baca('pengajuan-publik.json') : [];
    foreach ($daftar as $b) {
        if (!empty($b['dibuat_oleh'])) {
            $hasil[(int) $b['id']]['tambah'] = ['nama' => $nama[(int) $b['dibuat_oleh']] ?? 'Pengguna terhapus', 'waktu' => (string) ($b['dibuat'] ?? '')];
        } elseif (isset($publik[(string) $b['id']])) {
            $pb = $publik[(string) $b['id']];
            $hasil[(int) $b['id']]['tambah'] = ['nama' => (string) ($pb['nama'] ?? 'Pemohon'), 'waktu' => (string) ($pb['waktu'] ?? ($b['dibuat'] ?? '')), 'online' => true];
        }
    }
    foreach (rows(
        "SELECT l.objek_id, l.aksi, l.waktu, p.nama FROM log_aktivitas l LEFT JOIN pengguna p ON p.id = l.pengguna_id
          WHERE l.objek = ? AND l.objek_id IN (" . in_placeholder($ids) . ") AND (l.aksi LIKE 'tambah%' OR l.aksi LIKE 'ubah%')
          ORDER BY l.id",
        array_merge([$objek], $ids)
    ) as $r) {
        $id = (int) $r['objek_id'];
        $info = ['nama' => (string) ($r['nama'] ?? 'Pengguna terhapus'), 'waktu' => (string) $r['waktu']];
        if (strpos($r['aksi'], 'tambah') === 0) {
            $hasil[$id]['tambah'] = $hasil[$id]['tambah'] ?? $info;
        } else {
            $hasil[$id]['ubah'] = $info;
        }
    }
    return $hasil;
}

function aset_sel_penginput(?array $p): string
{
    $waktu = static function (string $w): string {
        return strlen($w) >= 16 ? tanggal_pendek(substr($w, 0, 10)) . ' ' . substr($w, 11, 5) : '';
    };
    $o = isset($p['tambah'])
        ? '<b>' . e($p['tambah']['nama']) . '</b><div class="kecil lemah">' . e($waktu($p['tambah']['waktu'])) . '</div>'
          . (!empty($p['tambah']['online']) ? '<div class="kecil lemah">via formulir online</div>' : '')
        : '<span class="lemah kecil">Data awal</span>';
    if (isset($p['ubah'])) {
        $o .= '<div class="kecil lemah">Diubah: ' . e($p['ubah']['nama']) . ', ' . e($waktu($p['ubah']['waktu'])) . '</div>';
    }
    return $o;
}

const ASET_KABID = ['nama' => 'dr. Andi Khairunnisa', 'nup' => '777 0313 556'];
const ASET_KABIRO = ['nama' => 'Andi Hidayatul Fadlilah, SE., M.Si., AK.', 'nomor' => 'NUP. 777 1111 520'];
const ASET_WAREK2 = ['nama' => 'Dr. Andi Auliya Ramadhany, S.E., M.Ak', 'nomor' => 'NUP. 777 0913 593'];
const ASET_REKTOR = ['nama' => 'Dr. Ir. Larisang, S.T., MT., IPU', 'nomor' => 'NIP. 196505132005011001'];
const ASET_NAV_SEMBUNYI = ['rawat_lama'];

function aset_ttd_kabid(): string
{
    return '<b class="ttd-nama">' . e(ASET_KABID['nama']) . '</b><div>NUP. ' . e(ASET_KABID['nup']) . '</div>';
}

function aset_cari_pindaian(string $teks): ?array
{
    $teks = trim(mb_substr($teks, 0, 500));
    if ($teks === '') {
        return null;
    }
    $barang = null;
    if (preg_match('~[?&]h=aset_barang(&|$)~', $teks) && preg_match('~[?&]id=(\d{1,10})(&|$)~', $teks, $m)) {
        $barang = row('SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE b.id = ?', [(int) $m[1]]);
    } else {
        $barang = row('SELECT b.*, r.nama AS ruang_nama FROM aset_barang b LEFT JOIN aset_ruang r ON r.id = b.ruang_id WHERE b.kode = ? OR b.no_seri = ? ORDER BY b.kode = ? DESC LIMIT 1', [$teks, $teks, $teks]);
    }
    if ($barang) {
        $foto = val('SELECT berkas FROM aset_foto WHERE barang_id = ? ORDER BY id LIMIT 1', [$barang['id']], null);
        $kondisi = ASET_KONDISI[$barang['kondisi']] ?? ASET_KONDISI['baik'];
        return [
            'jenis'   => 'barang',
            'id'      => (int) $barang['id'],
            'nama'    => $barang['nama'],
            'kode'    => $barang['kode'],
            'info'    => array_values(array_filter([
                ['Kategori', aset_kategori(true)[$barang['kategori']] ?? '-'],
                ['Ruangan', $barang['ruang_nama'] ?: 'Belum ditempatkan'],
                $barang['posisi'] !== '' ? ['Posisi', $barang['posisi']] : null,
                ['Merek / tipe', trim($barang['merek'] . ' ' . $barang['model']) ?: '-'],
                ['Jumlah', angka($barang['jumlah']) . ' ' . $barang['satuan']],
                ['Tahun', $barang['tahun'] ? (string) $barang['tahun'] : '-'],
            ])),
            'kondisi' => ['label' => $kondisi['label'], 'warna' => $kondisi['warna']],
            'status'  => $barang['status'] === 'dihapuskan' ? 'Dihapuskan dari inventaris' : '',
            'foto'    => $foto ? aset_foto_url($foto) : '',
            'url'     => boleh_aset() ? url('aset_barang', ['aksi' => 'lihat', 'id' => $barang['id']]) : '',
        ];
    }
    $ruang = row('SELECT * FROM aset_ruang WHERE kode = ? LIMIT 1', [$teks]);
    if ($ruang) {
        return [
            'jenis'   => 'ruang',
            'id'      => (int) $ruang['id'],
            'nama'    => $ruang['nama'],
            'kode'    => $ruang['kode'],
            'info'    => [['Jenis', ASET_JENIS_RUANG[$ruang['jenis']] ?? '-'], ['Lokasi', trim($ruang['gedung'] . ($ruang['lantai'] !== '' ? ' lt. ' . $ruang['lantai'] : '')) ?: '-'],
                          ['Barang aktif', angka(val("SELECT COUNT(*) FROM aset_barang WHERE ruang_id = ? AND status = 'aktif'", [$ruang['id']], 0)) . ' jenis']],
            'kondisi' => null,
            'status'  => $ruang['aktif'] ? '' : 'Ruangan nonaktif',
            'foto'    => '',
            'url'     => boleh_aset() ? url('aset_ruang', ['aksi' => 'lihat', 'id' => $ruang['id']]) : '',
        ];
    }
    return null;
}

function aset_url_mutlak(string $jalur): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
    if (!preg_match('/^[a-z0-9.\-]+(:\d{1,5})?$/i', $host)) {
        $host = 'localhost';
    }
    return (pakai_https() ? 'https' : 'http') . '://' . $host . base_url($jalur);
}

function aset_url_qr(int $id): string
{
    return aset_url_mutlak('index.php?h=aset_barang&aksi=lihat&id=' . $id);
}

function aset_publik_baca(string $nama): array
{
    $berkas = APP_ROOT . '/uploads/aset/' . $nama;
    $isi = is_file($berkas) ? json_decode((string) @file_get_contents($berkas), true) : null;
    return is_array($isi) ? $isi : [];
}

function aset_publik_ubah(string $nama, callable $ubah)
{
    $dir = APP_ROOT . '/uploads/aset';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return null;
    }
    $kunci = @fopen($dir . '/.kunci-publik', 'c');
    if (!$kunci || !flock($kunci, LOCK_EX)) {
        return null;
    }
    $data = aset_publik_baca($nama);
    $hasil = $ubah($data);
    $berkas = $dir . '/' . $nama;
    $sementara = $berkas . '.' . bin2hex(random_bytes(4)) . '.tmp';
    $ok = @file_put_contents($sementara, json_encode((object) $data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) !== false;
    if ($ok && !@rename($sementara, $berkas)) {
        @unlink($berkas);
        $ok = @rename($sementara, $berkas);
    }
    if (!$ok) {
        @unlink($sementara);
    }
    flock($kunci, LOCK_UN);
    fclose($kunci);
    return $ok ? $hasil : null;
}

function aset_publik_boleh_kirim(string $ip, int $batas = 5, int $detik = 3600): bool
{
    $kunciIp = hash('sha256', $ip . '|' . APP_ROOT);
    $boleh = aset_publik_ubah('pengajuan-batas.json', static function (array &$data) use ($kunciIp, $batas, $detik): bool {
        $kini = time();
        foreach ($data as $k => $waktu) {
            $data[$k] = array_values(array_filter((array) $waktu, static function ($t) use ($kini, $detik) { return (int) $t > $kini - $detik; }));
            if (!$data[$k]) {
                unset($data[$k]);
            }
        }
        if (count($data[$kunciIp] ?? []) >= $batas) {
            return false;
        }
        $data[$kunciIp][] = $kini;
        return true;
    });
    return $boleh === true;
}

const ASET_PENGAJUAN_ITEM_BERKAS = 'pengajuan-item.json';
const ASET_PENGAJUAN_ITEM_MAKS = 50;

function aset_pengajuan_item_tambahan_semua(): array
{
    return aset_publik_baca(ASET_PENGAJUAN_ITEM_BERKAS);
}

function aset_pengajuan_item_normal(array $i): array
{
    return [
        'nama_barang'    => (string) ($i['nama_barang'] ?? ''),
        'kategori'       => (string) ($i['kategori'] ?? 'lainnya'),
        'jumlah'         => max(1, (int) ($i['jumlah'] ?? 1)),
        'satuan'         => (string) (($i['satuan'] ?? '') !== '' ? $i['satuan'] : 'unit'),
        'estimasi_harga' => isset($i['estimasi_harga']) && $i['estimasi_harga'] !== null && $i['estimasi_harga'] !== '' ? (float) $i['estimasi_harga'] : null,
    ];
}

function aset_pengajuan_semua_item(array $p, ?array $semuaTambahan = null): array
{
    $semuaTambahan = $semuaTambahan ?? aset_pengajuan_item_tambahan_semua();
    $hasil = [aset_pengajuan_item_normal($p)];
    foreach ((array) ($semuaTambahan[(string) $p['id']] ?? []) as $i) {
        if (is_array($i) && trim((string) ($i['nama_barang'] ?? '')) !== '') {
            $hasil[] = aset_pengajuan_item_normal($i);
        }
    }
    return $hasil;
}

function aset_pengajuan_total_item(array $items): ?float
{
    $total = 0.0;
    $ada = false;
    foreach ($items as $i) {
        if ($i['estimasi_harga'] !== null) {
            $total += $i['estimasi_harga'] * $i['jumlah'];
            $ada = true;
        }
    }
    return $ada ? $total : null;
}

function aset_pengajuan_item_dari_post(): array
{
    $nama = $_POST['item_nama'] ?? [];
    if (!is_array($nama)) {
        return [];
    }
    $kategori = aset_kategori(true);
    $hasil = [];
    foreach ($nama as $i => $n) {
        $n = mb_substr(trim((string) $n), 0, 200);
        if ($n === '' || count($hasil) >= ASET_PENGAJUAN_ITEM_MAKS) {
            continue;
        }
        $k = (string) ($_POST['item_kategori'][$i] ?? 'lainnya');
        $harga = str_replace(['.', ','], ['', '.'], trim((string) ($_POST['item_harga'][$i] ?? '')));
        $hasil[] = [
            'nama_barang'    => $n,
            'kategori'       => array_key_exists($k, $kategori) ? $k : 'lainnya',
            'jumlah'         => max(1, min(100000, (int) ($_POST['item_jumlah'][$i] ?? 1))),
            'satuan'         => mb_substr(trim((string) ($_POST['item_satuan'][$i] ?? '')) ?: 'unit', 0, 20),
            'estimasi_harga' => $harga !== '' && is_numeric($harga) && (float) $harga >= 0 ? (float) $harga : null,
        ];
    }
    return $hasil;
}

function aset_pengajuan_item_simpan(int $id, array $tambahan): void
{
    aset_publik_ubah(ASET_PENGAJUAN_ITEM_BERKAS, static function (array &$data) use ($id, $tambahan): void {
        if ($tambahan) {
            $data[(string) $id] = array_values($tambahan);
        } else {
            unset($data[(string) $id]);
        }
    });
}

function aset_pengajuan_isi(array $p, ?array $semuaTambahan = null): array
{
    $items = [];
    foreach (aset_pengajuan_semua_item($p, $semuaTambahan) as $i) {
        $items[] = [
            'nama_barang' => $i['nama_barang'], 'kategori' => $i['kategori'], 'jumlah' => $i['jumlah'], 'satuan' => $i['satuan'],
            'harga' => $i['estimasi_harga'] !== null ? (string) (int) round($i['estimasi_harga']) : '',
        ];
    }
    return ['id' => (int) $p['id'], 'tanggal' => $p['tanggal'], 'prioritas' => $p['prioritas'], 'ruang_id' => $p['ruang_id'] ?? '',
        'pemohon' => $p['pemohon'], 'alasan' => $p['alasan'], 'items' => $items];
}

function aset_pengajuan_publik_info(int $id): ?array
{
    $d = aset_publik_baca('pengajuan-publik.json');
    return isset($d[(string) $id]) && is_array($d[(string) $id]) ? $d[(string) $id] : null;
}

function aset_berkas_sah(?string $nama): bool
{
    return $nama !== null && preg_match('/^[a-z]{1,2}\d+-[a-f0-9]{16}\.(jpg|png|webp)$/', $nama) === 1;
}

function aset_hapus_berkas(?string $nama): void
{
    if (aset_berkas_sah($nama)) {
        @unlink(APP_ROOT . '/uploads/aset/' . $nama);
    }
}

function aset_simpan_foto(array $berkasPost, string $kolom, int $id, string $keterangan = ''): array
{
    if (!in_array($kolom, ['ruang_id', 'barang_id', 'kerusakan_id', 'pengajuan_id'], true)) {
        return [0, ['Tujuan foto tidak sah.']];
    }
    $awalan = ['ruang_id' => 'r', 'barang_id' => 'b', 'kerusakan_id' => 'k', 'pengajuan_id' => 'p'][$kolom] . $id;
    $dir = APP_ROOT . '/uploads/aset';
    if (!is_dir($dir) && !@mkdir($dir, 0775, true)) {
        return [0, ['Folder foto tidak dapat dibuat.']];
    }
    $n = 0;
    $galat = [];
    foreach (rapat_normalisasi_berkas($berkasPost) as $b) {
        $kode = $b['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($kode === UPLOAD_ERR_NO_FILE) {
            continue;
        }
        if ($kode !== UPLOAD_ERR_OK || !is_uploaded_file($b['tmp_name'] ?? '')) {
            $galat[] = 'Satu foto gagal diunggah.';
            continue;
        }
        $cek = validasi_foto($b['tmp_name'], (int) $b['size']);
        if (isset($cek['galat'])) {
            $galat[] = $cek['galat'];
            continue;
        }
        $nama = $awalan . '-' . bin2hex(random_bytes(8)) . '.' . $cek['ekstensi'];
        if (!move_uploaded_file($b['tmp_name'], $dir . '/' . $nama)) {
            $galat[] = 'Foto tidak dapat disimpan di server.';
            continue;
        }
        insert('aset_foto', [$kolom => $id, 'berkas' => $nama, 'keterangan' => mb_substr($keterangan, 0, 200)]);
        $n++;
    }
    return [$n, array_values(array_unique($galat))];
}

function aset_hapus_semua_foto(string $kolom, int $id): void
{
    foreach (aset_foto_daftar($kolom, $id) as $f) {
        aset_hapus_berkas($f['berkas']);
    }
}

function aset_hapus_foto_satu(int $fotoId, string $kolom, int $id): bool
{
    if (!in_array($kolom, ['ruang_id', 'barang_id', 'kerusakan_id', 'pengajuan_id'], true)) {
        return false;
    }
    $f = row("SELECT id, berkas FROM aset_foto WHERE id = ? AND $kolom = ?", [$fotoId, $id]);
    if (!$f) {
        return false;
    }
    aset_hapus_berkas($f['berkas']);
    q('DELETE FROM aset_foto WHERE id = ?', [$fotoId]);
    return true;
}

function aset_nomor_kerusakan(string $jenis): string
{
    $awal = $jenis === 'sarana' ? 'KRS' : 'KRP';
    $tahun = date('Y');
    $n = (int) val('SELECT COUNT(*) FROM aset_kerusakan WHERE jenis = ? AND YEAR(tanggal_lapor) = ?', [$jenis, $tahun], 0) + 1;
    do {
        $nomor = sprintf('%s/%s/%04d', $awal, $tahun, $n++);
    } while ((int) val('SELECT COUNT(*) FROM aset_kerusakan WHERE nomor = ?', [$nomor], 0) > 0);
    return $nomor;
}

function aset_sinkron_kondisi_barang(int $barangId): void
{
    $b = row('SELECT id, kondisi FROM aset_barang WHERE id = ?', [$barangId]);
    if (!$b || $b['kondisi'] === 'hilang') {
        return;
    }
    $terbuka = rows("SELECT tingkat FROM aset_kerusakan WHERE barang_id = ? AND status IN ('dilaporkan','diproses')", [$barangId]);
    $kondisi = 'baik';
    foreach ($terbuka as $k) {
        if ($k['tingkat'] === 'berat') {
            $kondisi = 'rusak_berat';
            break;
        }
        $kondisi = 'rusak_ringan';
    }
    if ($kondisi !== $b['kondisi']) {
        update('aset_barang', ['kondisi' => $kondisi, 'diubah' => date('Y-m-d H:i:s')], 'id = ?', [$barangId]);
    }
}

function aset_lencana_kondisi(string $kondisi): string
{
    $d = ASET_KONDISI[$kondisi] ?? ASET_KONDISI['baik'];
    return lencana($d['label'], $d['warna']);
}

const ASET_NAV = [
    'ringkasan'    => ['Grafik & Rekap',    'aset',            ['aset' => 'Grafik & Rekap']],
    'data'         => ['Data Aset',         'aset_barang',     ['aset_barang' => 'Inventaris Barang', 'aset_ruang' => 'Ruangan & Foto', 'aset_kategori' => 'Kategori', 'aset_pindai' => 'Pindai QR']],
    'transaksi'    => ['Transaksi',         'aset_pengajuan',  ['aset_pengajuan' => 'Pengajuan Barang', 'aset_mutasi' => 'Mutasi & Peminjaman', 'aset_penghapusan' => 'Penghapusan', 'aset_opname' => 'Stock Opname']],
    'kontrol'      => ['Pemeliharaan & CS', 'aset_kk_rekap',   KK_HALAMAN + ['aset_sarana' => 'Kerusakan Sarana', 'aset_prasarana' => 'Kerusakan Prasarana']],
    'arsip'        => ['Laporan & Dokumen', 'aset_laporan',    ['aset_laporan' => 'Laporan', 'aset_dokumen' => 'Dokumen']],
    'rawat_lama'   => ['Pemeliharaan Lama', 'aset_kebersihan', ['aset_kebersihan' => 'Kebersihan', 'aset_ac' => 'AC']],
];

const ASET_IKON_HALAMAN = [
    'aset_ruang' => 'gedung', 'aset_barang' => 'aset', 'aset_kategori' => 'filter',
    'aset_pengajuan' => 'kirim', 'aset_mutasi' => 'ulang', 'aset_penghapusan' => 'hapus', 'aset_opname' => 'centang',
    'aset_kebersihan' => 'kebersihan', 'aset_ac' => 'ac', 'aset_sarana' => 'alat', 'aset_prasarana' => 'segitiga',
    'aset_kk_ruang' => 'kebersihan', 'aset_kk_ac' => 'ac', 'aset_kk_sarana' => 'catatan', 'aset_kk_rekap' => 'grup',
];

function aset_tautan_menu(string $kunci, string $nama, string $aktif): string
{
    return '<a href="' . e(url($kunci)) . '"' . ($kunci === $aktif ? ' class="aktif"' : '') . '><span class="aset-menu-ikon">'
        . ikon(ASET_IKON_HALAMAN[$kunci] ?? 'panel', 15) . '</span>' . e($nama) . '</a>';
}

function aset_navigasi(string $aktif): string
{
    $h = '<div class="aset-bilah no-cetak"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="gambar"></div><div class="lapis batik"></div></div>'
       . '<nav class="aset-nav" aria-label="Menu Sarana dan Prasarana">';
    $sub = [];
    foreach (ASET_NAV as $kunciGrup => [$label, $pertama, $halaman]) {
        $dalam = array_key_exists($aktif, $halaman);
        if (in_array($kunciGrup, ASET_NAV_SEMBUNYI, true) && !$dalam) {
            continue;
        }
        $h .= '<div class="aset-grup' . (count($halaman) > 1 ? ' ada-menu' : '') . '"><a href="' . e(url($pertama)) . '"' . ($dalam ? ' class="aktif" aria-current="page"' : '') . '>' . e($label) . '</a>';
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
function aset_svg_donat(array $irisan, string $tengah, string $subTengah = ''): string
{
    $r = 54;
    $keliling = 2 * M_PI * $r;
    $total = array_sum(array_column($irisan, 1));
    $svg = '<svg viewBox="0 0 160 160" class="grafik-donat" role="img" aria-label="Diagram kondisi barang"><g transform="rotate(-90 80 80)">'
         . '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="#eceeea" stroke-width="20"/>';
    $geser = 0.0;
    if ($total > 0) {
        foreach ($irisan as [, $nilai, $warna]) {
            if ($nilai <= 0) {
                continue;
            }
            $panjang = $nilai / $total * $keliling;
            $garis = max($panjang - 2, 0.5);
            $svg .= '<circle cx="80" cy="80" r="' . $r . '" fill="none" stroke="' . $warna . '" stroke-width="20" stroke-dasharray="'
                  . round($garis, 2) . ' ' . round($keliling - $garis, 2) . '" stroke-dashoffset="' . round(-$geser, 2) . '"/>';
            $geser += $panjang;
        }
    }
    return $svg . '</g><text x="80" y="' . ($subTengah !== '' ? 82 : 88) . '" text-anchor="middle" class="donat-angka">' . e($tengah) . '</text>'
         . ($subTengah !== '' ? '<text x="80" y="100" text-anchor="middle" class="donat-ket">' . e($subTengah) . '</text>' : '') . '</svg>';
}

function aset_svg_batang(array $label, array $seri): string
{
    $n = count($label);
    $tot = [];
    for ($i = 0; $i < $n; $i++) {
        $tot[$i] = array_sum(array_map(static function ($s) use ($i) { return $s[2][$i] ?? 0; }, $seri));
    }
    $maks = max(array_merge($tot, [1]));
    $skala = $maks <= 4 ? 4 : (int) (ceil($maks / 4) * 4);
    $x0 = 30;
    $x1 = 548;
    $yAtas = 14;
    $yBawah = 186;
    $tinggi = $yBawah - $yAtas;
    $slot = ($x1 - $x0) / max($n, 1);
    $lebar = min(38, $slot * 0.5);
    $svg = '<svg viewBox="0 0 560 214" class="grafik-batang" role="img" aria-label="Diagram laporan kerusakan per bulan">';
    foreach ([0, 0.5, 1] as $f) {
        $y = $yBawah - $tinggi * $f;
        $svg .= '<line x1="' . $x0 . '" x2="' . $x1 . '" y1="' . round($y, 1) . '" y2="' . round($y, 1) . '" stroke="' . ($f == 0 ? '#c9cfca' : '#eceeea') . '"/>'
              . '<text x="' . ($x0 - 6) . '" y="' . round($y + 3.5, 1) . '" text-anchor="end" class="sumbu-teks">' . round($skala * $f) . '</text>';
    }
    for ($i = 0; $i < $n; $i++) {
        $cx = $x0 + $slot * ($i + 0.5);
        $y = $yBawah;
        foreach ($seri as [, $warna, $nilai]) {
            $v = $nilai[$i] ?? 0;
            if ($v <= 0) {
                continue;
            }
            $h = $v / $skala * $tinggi;
            $y -= $h;
            $svg .= '<rect x="' . round($cx - $lebar / 2, 1) . '" y="' . round($y, 1) . '" width="' . round($lebar, 1) . '" height="' . round($h, 1) . '" fill="' . $warna . '"/>';
        }
        if ($tot[$i] > 0) {
            $svg .= '<text x="' . round($cx, 1) . '" y="' . round($y - 5, 1) . '" text-anchor="middle" class="nilai-teks">' . $tot[$i] . '</text>';
        }
        $svg .= '<text x="' . round($cx, 1) . '" y="204" text-anchor="middle" class="sumbu-teks">' . e($label[$i]) . '</text>';
    }
    return $svg . '</svg>';
}

function aset_halaman_aktif(): array
{
    $h = [];
    foreach (ASET_NAV as [, , $halaman]) {
        $h = array_merge($h, array_keys($halaman));
    }
    return $h;
}

const ASET_STATUS_PENGAJUAN = [
    'diajukan'  => ['label' => 'Diajukan',  'warna' => 'kuning'],
    'disetujui' => ['label' => 'Disetujui', 'warna' => 'biru'],
    'ditolak'   => ['label' => 'Ditolak',   'warna' => 'merah'],
    'dibeli'    => ['label' => 'Dibeli',    'warna' => 'jingga'],
    'diterima'  => ['label' => 'Diterima',  'warna' => 'hijau'],
];

const ASET_PRIORITAS = [
    'rendah' => ['label' => 'Rendah', 'warna' => 'abu'],
    'sedang' => ['label' => 'Sedang', 'warna' => 'kuning'],
    'tinggi' => ['label' => 'Tinggi', 'warna' => 'merah'],
];

const ASET_ALASAN_HAPUS = [
    'rusak_berat' => 'Rusak berat', 'hilang' => 'Hilang', 'usang' => 'Usang / tidak layak pakai',
    'dijual' => 'Dijual', 'dihibahkan' => 'Dihibahkan', 'lainnya' => 'Lainnya',
];

const ASET_HAPUS_MEDAN = [
    'dijual'      => ['pihak' => 'Nama pembeli', 'harga_jual' => 'Harga jual (Rp)', 'dokumen' => 'No. kuitansi / berita acara jual', 'rincian' => 'Keterangan penjualan'],
    'dihibahkan'  => ['pihak' => 'Penerima hibah', 'dokumen' => 'No. berita acara hibah', 'rincian' => 'Keperluan / catatan hibah'],
    'hilang'      => ['pihak' => 'Pelapor / penanggung jawab', 'dokumen' => 'No. laporan kehilangan', 'rincian' => 'Kronologi dan lokasi terakhir'],
    'rusak_berat' => ['dokumen' => 'No. berita acara kerusakan', 'rincian' => 'Rincian kerusakan'],
    'usang'       => ['dokumen' => 'No. berita acara', 'rincian' => 'Kondisi dan usia pakai'],
    'lainnya'     => ['dokumen' => 'No. dokumen pendukung', 'rincian' => 'Penjelasan alasan'],
];

const ASET_HAPUS_BERKAS = 'penghapusan-detail.json';

function aset_hapus_detail_semua(): array
{
    return aset_publik_baca(ASET_HAPUS_BERKAS);
}

function aset_hapus_detail_dari_post(string $alasan): array
{
    $d = [];
    foreach (array_keys(ASET_HAPUS_MEDAN[$alasan] ?? []) as $kunci) {
        if ($kunci === 'harga_jual') {
            $h = aset_angka_atau_null('harga_jual');
            $d['harga_jual'] = $h;
        } else {
            $d[$kunci] = mb_substr($kunci === 'rincian' ? post_teks($kunci) : post($kunci), 0, $kunci === 'rincian' ? 1000 : 150);
        }
    }
    return $d;
}

function aset_hapus_detail_simpan(int $id, array $detail): void
{
    aset_publik_ubah(ASET_HAPUS_BERKAS, static function (array &$data) use ($id, $detail): void {
        if ($detail) {
            $data[(string) $id] = $detail;
        } else {
            unset($data[(string) $id]);
        }
    });
}

function aset_hapus_detail_buang(int $id): void
{
    aset_hapus_detail_simpan($id, []);
}

function aset_hapus_detail_isi(array $detail): array
{
    return [
        'pihak'      => (string) ($detail['pihak'] ?? ''),
        'dokumen'    => (string) ($detail['dokumen'] ?? ''),
        'rincian'    => (string) ($detail['rincian'] ?? ''),
        'harga_jual' => isset($detail['harga_jual']) && $detail['harga_jual'] !== null ? (string) (int) round((float) $detail['harga_jual']) : '',
    ];
}

function aset_hapus_ringkas(string $alasan, array $detail): string
{
    $h = '';
    foreach (ASET_HAPUS_MEDAN[$alasan] ?? [] as $kunci => $label) {
        if ($kunci === 'harga_jual' || trim((string) ($detail[$kunci] ?? '')) === '') {
            continue;
        }
        $h .= '<div class="kecil"><span class="lemah">' . e($label) . ':</span> ' . e((string) $detail[$kunci]) . '</div>';
    }
    return $h !== '' ? $h : '<span class="lemah">-</span>';
}

function aset_kategori(bool $semua = false): array
{
    static $cache = [];
    $kunci = $semua ? 'semua' : 'aktif';
    if (!isset($cache[$kunci])) {
        $peta = [];
        try {
            foreach (rows('SELECT kode, nama FROM aset_kategori' . ($semua ? '' : ' WHERE aktif = 1') . ' ORDER BY nama') as $r) {
                $peta[$r['kode']] = $r['nama'];
            }
        } catch (Throwable $e) {
        }
        $cache[$kunci] = $peta ?: ASET_KATEGORI;
    }
    return $cache[$kunci];
}

function aset_kategori_formulir(): array
{
    $aktif = aset_kategori();
    $hasil = $aktif;
    foreach (aset_kategori(true) as $kode => $nama) {
        if (!isset($aktif[$kode])) {
            $hasil[$kode] = $nama . ' (nonaktif)';
        }
    }
    return $hasil;
}

function aset_nomor_dokumen(string $tabel, string $awal, string $kolomTanggal = 'tanggal', ?string $tanggal = null): string
{
    if (!in_array($tabel, ['aset_pengajuan', 'aset_penghapusan', 'aset_opname'], true)) {
        return $awal . '/' . date('Y') . '/0001';
    }
    $tahun = $tanggal && tanggal_sah($tanggal) ? substr($tanggal, 0, 4) : date('Y');
    $n = (int) val("SELECT COUNT(*) FROM `$tabel` WHERE YEAR(`$kolomTanggal`) = ?", [$tahun], 0) + 1;
    do {
        $nomor = sprintf('%s/%s/%04d', $awal, $tahun, $n++);
    } while ((int) val("SELECT COUNT(*) FROM `$tabel` WHERE nomor = ?", [$nomor], 0) > 0);
    return $nomor;
}

function aset_nilai_buku($harga, $jumlah, $tahun, $umur): ?float
{
    if ($harga === null || $harga === '') {
        return null;
    }
    $total = (float) $harga * max(1, (int) $jumlah);
    if (!$tahun || !$umur) {
        return $total;
    }
    $lewat = max(0, (int) date('Y') - (int) $tahun);
    return max(0.0, $total * (1 - min($lewat, (int) $umur) / (int) $umur));
}

function aset_umur_kategori(): array
{
    static $umur = null;
    if ($umur === null) {
        $umur = [];
        try {
            foreach (rows('SELECT kode, umur_tahun FROM aset_kategori') as $r) {
                $umur[$r['kode']] = $r['umur_tahun'] !== null ? (int) $r['umur_tahun'] : null;
            }
        } catch (Throwable $e) {
        }
    }
    return $umur;
}

function aset_kode_barang_baru(): string
{
    $tahun = date('Y');
    $n = (int) val('SELECT COUNT(*) FROM aset_barang', [], 0) + 1;
    do {
        $kode = sprintf('INV-%s-%04d', $tahun, $n++);
    } while ((int) val('SELECT COUNT(*) FROM aset_barang WHERE kode = ?', [$kode], 0) > 0);
    return $kode;
}
