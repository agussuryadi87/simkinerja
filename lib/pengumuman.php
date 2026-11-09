<?php

const PENGUMUMAN_PERAN = ['admin', 'kabiro', 'kabid'];

const PENGUMUMAN_SASARAN = [
    'semua'    => 'Semua pengguna',
    'pimpinan' => 'Kabid ke atas',
    'staf'     => 'Staf',
    'cs'       => 'Petugas CS',
];

function boleh_umumkan(): bool
{
    return in_array((string) (user()['peran_kode'] ?? ''), PENGUMUMAN_PERAN, true);
}

function pengumuman_semua(): array
{
    $d = aset_publik_baca('pengumuman.json');
    $daftar = array_values(array_filter((array) ($d['daftar'] ?? []), 'is_array'));
    usort($daftar, static function (array $a, array $b): int {
        return [(int) !empty($b['penting']), (string) $b['waktu']] <=> [(int) !empty($a['penting']), (string) $a['waktu']];
    });
    return $daftar;
}

function pengumuman_untuk_saya(array $p): bool
{
    $kode = (string) (user()['peran_kode'] ?? '');
    if (!empty($p['sampai']) && $p['sampai'] < date('Y-m-d')) {
        return false;
    }
    switch ($p['sasaran'] ?? 'semua') {
        case 'pimpinan':
            return in_array($kode, PENGUMUMAN_PERAN, true) || (int) ($p['oleh']['id'] ?? 0) === uid();
        case 'staf':
            return $kode === 'staf' || (int) ($p['oleh']['id'] ?? 0) === uid() || in_array($kode, PENGUMUMAN_PERAN, true);
        case 'cs':
            return $kode === 'cs' || (int) ($p['oleh']['id'] ?? 0) === uid() || in_array($kode, PENGUMUMAN_PERAN, true);
    }
    return true;
}

function pengumuman_saya(): array
{
    return array_values(array_filter(pengumuman_semua(), 'pengumuman_untuk_saya'));
}

function pengumuman_dilihat(int $pid): string
{
    return (string) (aset_publik_baca('pengumuman-dilihat.json')[(string) $pid] ?? '');
}

function pengumuman_tandai_dilihat(int $pid): void
{
    aset_publik_ubah('pengumuman-dilihat.json', static function (array &$d) use ($pid): void {
        $d[(string) $pid] = date('Y-m-d H:i:s');
    });
}

function pengumuman_notifikasi(int $pid): array
{
    $batas = pengumuman_dilihat($pid);
    $hasil = [];
    $awal = date('Y-m-d H:i:s', strtotime('-30 days'));
    foreach (pengumuman_saya() as $p) {
        if (count($hasil) >= 5) {
            break;
        }
        if ((string) $p['waktu'] < $awal && empty($p['penting'])) {
            continue;
        }
        $hasil[] = [
            'id'     => 'pg-' . $p['id'],
            'judul'  => (!empty($p['penting']) ? 'PENTING · ' : '') . $p['judul'],
            'waktu'  => tanggal_pendek(substr($p['waktu'], 0, 10)) . ' · ' . substr($p['waktu'], 11, 5),
            'tempat' => 'dari ' . ($p['oleh']['nama'] ?? '-'),
            'baru'   => (int) ($p['oleh']['id'] ?? 0) !== $pid && (string) $p['waktu'] > $batas,
            'url'    => url('pengumuman') . '#pg-' . $p['id'],
            'jenis'  => 'pengumuman',
        ];
    }
    return $hasil;
}

function notifikasi_lonceng(int $pid): array
{
    $notif = ['baru' => 0, 'daftar' => []];
    try {
        $notif = rapat_notifikasi($pid);
    } catch (Throwable $e) {
    }
    $tambahan = [];
    try {
        $tambahan = array_merge(pengumuman_notifikasi($pid), kk_notifikasi($pid));
    } catch (Throwable $e) {
    }
    if ($tambahan) {
        $notif['daftar'] = array_merge($tambahan, $notif['daftar']);
        $notif['baru'] += count(array_filter($tambahan, static function ($n) { return $n['baru']; }));
    }
    return $notif;
}
