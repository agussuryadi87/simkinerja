<?php
$pid = pid();
$saya = uid();
$periode = periode_dipilih();
$terbuka = periode_terbuka();
$aksi = $_GET['aksi'] ?? '';

$ambil = static function (int $id) use ($pid, $saya): ?array {
    return row('SELECT * FROM kegiatan WHERE id = ? AND periode_id = ? AND pengguna_id = ?', [$id, $pid, $saya]);
};
$bolehUbah = static function (?array $k) use ($terbuka): bool {
    return $k !== null && $terbuka && in_array($k['status'], ['diajukan', 'revisi'], true);
};
$bolehSunting = static function (?array $k) use ($terbuka): bool {
    return $k !== null && $terbuka;
};


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $op = post('op');
    if (!$terbuka) {
        flash('Periode ini sudah ditutup.', 'galat');
        kembali_ke('kegiatan');
    }

    if ($op === 'simpan') {
        $id = (int) post('id');
        $lama = $id ? $ambil($id) : null;
        if ($id && !$bolehSunting($lama)) {
            flash('Kegiatan tidak ditemukan.', 'galat');
            kembali_ke('kegiatan');
        }
        $draft = post('draft') === '1';
        if ($draft && $id && !kegiatan_draft($id)) {
            $draft = false;
        }
        $kehadiran = array_key_exists(post('kehadiran'), KEHADIRAN_KERJA) ? post('kehadiran') : 'masuk';
        $absen = $kehadiran !== 'masuk';
        $jenis = !$absen && array_key_exists(post('jenis'), JENIS_KERJA) ? post('jenis') : 'rutin';
        $tanggal = post('tanggal');
        $mulai = $absen ? '' : normalisasi_jam(post('jam_mulai'));
        $selesai = $absen ? '' : normalisasi_jam(post('jam_selesai'));
        $galat = [];

        if (!tanggal_sah($tanggal)) {
            $galat[] = 'Tanggal tidak sah.';
        } elseif ($tanggal < $periode['mulai'] || $tanggal > $periode['selesai']) {
            $galat[] = 'Tanggal harus berada dalam periode ' . tanggal_id($periode['mulai']) . ' s.d. ' . tanggal_id($periode['selesai']) . '.';
        } elseif ($tanggal > date('Y-m-d')) {
            $galat[] = 'Kegiatan yang belum dilaksanakan belum dapat dicatat.';
        }
        if ($draft && !$absen) {
            if (($mulai !== '' && !jam_sah($mulai)) || ($selesai !== '' && !jam_sah($selesai))) {
                $galat[] = 'Format jam tidak sah.';
            } elseif ($mulai !== '' && $selesai !== '' && $selesai <= $mulai) {
                $galat[] = 'Jam selesai harus setelah jam mulai.';
            }
            if (!tautan_sah(post('tautan_bukti'))) {
                $galat[] = 'Tautan bukti harus berupa alamat http:// atau https://.';
            }
        } elseif (!$absen) {
            if ($mulai === '' || $selesai === '') {
                $galat[] = 'Jam mulai dan jam selesai wajib diisi.';
            } elseif (!jam_sah($mulai) || !jam_sah($selesai)) {
                $galat[] = 'Format jam tidak sah.';
            } elseif ($selesai <= $mulai) {
                $galat[] = 'Jam selesai harus setelah jam mulai.';
            }
            if (post('judul') === '') {
                $galat[] = 'Nama kegiatan wajib diisi.';
            }
            if ($jenis === 'panitia' && post('kepanitiaan') === '') {
                $galat[] = 'Nama kepanitiaan wajib diisi untuk kerja panitia.';
            }
            if ($jenis === 'insidental' && post('pemberi_tugas') === '') {
                $galat[] = 'Pemberi tugas wajib diisi untuk kerja insidental.';
            }
            if (!tautan_sah(post('tautan_bukti'))) {
                $galat[] = 'Tautan bukti harus berupa alamat http:// atau https://.';
            }
        }

        $fotoBaru = null;
        if (!$galat && !$absen) {
            foreach (['foto_kamera', 'foto_galeri'] as $medan) {
                $unggah = unggah_foto_kegiatan($_FILES[$medan] ?? [], $saya);
                if (isset($unggah['galat'])) {
                    $galat[] = $unggah['galat'];
                    break;
                }
                if (isset($unggah['file'])) {
                    $fotoBaru = $unggah['file'];
                    break;
                }
            }
        }

        if ($galat) {
            $_SESSION['isian_kegiatan'] = $_POST;
            flash(implode('<br>', array_map('e', $galat)), 'galat');
            if (post('dari') === 'hari') {
                kembali_ke('kegiatan');
            }
            kembali_ke('kegiatan', ['aksi' => $id ? 'ubah' : 'baru', 'id' => $id ?: null]);
        }

        $data = [
            'jenis'         => $jenis,
            'kehadiran'     => $kehadiran,
            'tanggal'       => $tanggal,
            'jam_mulai'     => $mulai !== '' ? $mulai : null,
            'jam_selesai'   => $selesai !== '' ? $selesai : null,
            'judul'         => mb_substr(post('judul'), 0, 255),
            'uraian'        => mb_substr(post('uraian'), 0, 5000),
            'hasil'         => mb_substr(post('hasil'), 0, 500),
            'volume'        => max(0, post_angka('volume', 1)),
            'satuan'        => satuan_dari_formulir(),
            'kepanitiaan'   => $jenis === 'panitia' ? mb_substr(post('kepanitiaan'), 0, 255) : '',
            'peran_panitia' => $jenis === 'panitia' ? mb_substr(post('peran_panitia'), 0, 100) : '',
            'nomor_sk'      => in_array($jenis, ['panitia', 'insidental'], true) ? mb_substr(post('nomor_sk'), 0, 100) : '',
            'pemberi_tugas' => $jenis === 'insidental' ? mb_substr(post('pemberi_tugas'), 0, 150) : '',
            'tautan_bukti'  => mb_substr(post('tautan_bukti'), 0, 1000),
        ];
        if ($absen) {
            $data = array_merge($data, [
                'judul' => $kehadiran === 'dinas' ? 'Tugas dinas luar' : 'Tidak masuk kerja', 'uraian' => '', 'hasil' => '', 'volume' => 0, 'satuan' => '',
                'kepanitiaan' => '', 'peran_panitia' => '', 'nomor_sk' => '', 'pemberi_tugas' => '', 'tautan_bukti' => '',
            ]);
        }
        $langsung = !$draft && !punya_atasan($saya);
        $data['status'] = $langsung ? 'disetujui' : 'diajukan';
        if ($draft) {
            $data['catatan_atasan'] = '';
        }
        if ($langsung) {
            $data['diverifikasi_oleh'] = $saya;
            $data['diverifikasi_pada'] = date('Y-m-d H:i:s');
        } else {
            $data['diverifikasi_oleh'] = null;
            $data['diverifikasi_pada'] = null;
        }

        try {
            $dariDraft = $id && kegiatan_draft($id);
            if ($id) {
                update('kegiatan', $data, 'id = ?', [$id]);
            } else {
                $id = insert('kegiatan', $data + ['periode_id' => $pid, 'pengguna_id' => $saya]);
            }
            atur_kegiatan_draft((int) $id, $draft);
            $namaK = $data['judul'] !== '' ? $data['judul'] : 'tanpa judul';
            if ($draft) {
                catat_log('draft_kegiatan', 'kegiatan', (int) $id, $data['judul']);
                flash('Draft kegiatan <b>' . e($namaK) . '</b> tersimpan. Draft belum dikirim ke atasan dan belum dinilai; buka kembali melalui tombol ubah lalu kirim ke atasan bila sudah lengkap.', 'info');
            } elseif ($dariDraft || !post('id')) {
                catat_log('tambah_kegiatan', 'kegiatan', (int) $id, $data['judul']);
                flash('Kegiatan <b>' . e($namaK) . '</b> tercatat' . ($langsung ? '.' : ' dan menunggu verifikasi atasan.'));
            } else {
                catat_log('ubah_kegiatan', 'kegiatan', (int) $id, $data['judul']);
                flash('Kegiatan diperbarui' . ($langsung ? '.' : ' dan dikirim ulang ke atasan untuk diverifikasi.'));
            }
            if ($fotoBaru !== null) {
                pasang_foto_kegiatan($fotoBaru, (int) $id);
            } elseif ($absen || post('hapus_foto') === '1') {
                hapus_foto_kegiatan((int) $id);
            }
        } catch (PDOException $ex) {
            error_log('Gagal menyimpan kegiatan: ' . $ex->getMessage());
            buang_foto_sementara($fotoBaru);
            $_SESSION['isian_kegiatan'] = $_POST;
            flash('Kegiatan belum tersimpan karena terjadi kendala pada basis data. Silakan coba lagi atau hubungi admin (rincian tercatat di logs/galat.log).', 'galat');
            if (post('dari') === 'hari') {
                kembali_ke('kegiatan');
            }
            kembali_ke('kegiatan', ['aksi' => $id ? 'ubah' : 'baru', 'id' => $id ?: null]);
        }
        if (post('dari') === 'hari') {
            kembali_ke('kegiatan');
        }
        if (post('lagi') === '1') {
            kembali_ke('kegiatan', ['aksi' => 'baru', 'tgl' => $tanggal]);
        }
        kembali_ke('kegiatan', ['bln' => substr($tanggal, 0, 7)]);
    }

    if ($op === 'hapus') {
        $k = $ambil((int) post('id'));
        if (!$bolehSunting($k)) {
            flash('Kegiatan tidak ditemukan.', 'galat');
            kembali_ke('kegiatan');
        }
        q('DELETE FROM kegiatan WHERE id = ?', [$k['id']]);
        hapus_foto_kegiatan((int) $k['id']);
        atur_kegiatan_draft((int) $k['id'], false);
        catat_log('hapus_kegiatan', 'kegiatan', (int) $k['id'], $k['judul']);
        flash('Kegiatan dihapus.');
        if (post('dari') === 'hari') {
            kembali_ke('kegiatan');
        }
        kembali_ke('kegiatan', ['bln' => substr($k['tanggal'], 0, 7)]);
    }
    kembali_ke('kegiatan');
}

if ($aksi === 'baru' || $aksi === 'ubah') {
    if (!$terbuka) {
        flash('Periode ini sudah ditutup.', 'warn');
        kembali_ke('kegiatan');
    }
    $k = null;
    if ($aksi === 'ubah') {
        $k = $ambil((int) ($_GET['id'] ?? 0));
        if (!$bolehSunting($k)) {
            flash('Kegiatan tidak ditemukan.', 'warn');
            kembali_ke('kegiatan');
        }
    } elseif (!empty($_GET['salin'])) {
        $k = $ambil((int) $_GET['salin']);
        if ($k) {
            unset($k['id']);
            $k['tanggal'] = date('Y-m-d');
            $k['status'] = 'diajukan';
            $k['catatan_atasan'] = '';
        }
    }
    $isian = $_SESSION['isian_kegiatan'] ?? null;
    unset($_SESSION['isian_kegiatan']);
    $v = static function (string $f, $bawaan = '') use ($isian, $k) {
        if ($isian !== null && array_key_exists($f, $isian)) {
            return $isian[$f];
        }
        return $k[$f] ?? $bawaan;
    };
    $tglBawaan = $_GET['tgl'] ?? date('Y-m-d');
    if (!tanggal_sah((string) $tglBawaan)) {
        $tglBawaan = date('Y-m-d');
    }
    $jenisKini = $v('jenis', 'rutin');
    $adaDraft = $aksi === 'ubah' && kegiatan_draft((int) $k['id']);
    $subjudul = $adaDraft ? 'Lanjutkan draft kegiatan' : ($aksi === 'ubah' ? 'Ubah catatan kegiatan' : 'Catat kegiatan baru');
    ?>
    <form method="post" action="<?= e(url('kegiatan')) ?>" class="kartu kegiatan-ramping" data-wadah-jenis enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="simpan">
      <input type="hidden" name="id" value="<?= (int) ($k['id'] ?? 0) ?>">
      <div class="kartu-kepala kepala-nusantara"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="lapis awan"></div><div class="lapis burung"></div><div class="lapis gunung"></div><div class="lapis candi"></div><div class="lapis laut"></div><div class="perahu"><img src="<?= e(base_url('assets/img/nusantara/pinisi.svg')) ?>" alt=""></div><div class="lapis laut2"></div><div class="lapis batik"></div></div>
        <div><h2><?= ikon('catatan', 18) ?> <?= $aksi === 'ubah' ? 'Ubah kegiatan' : 'Catat kegiatan' ?></h2>
          </div>
        <div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url('kegiatan')) ?>"><?= ikon('kembali', 15) ?> Kembali</a></div>
      </div>
      <div class="kartu-isi">
        <?php if ($adaDraft): ?>
          <div class="notif notif-info"><?= ikon('info', 17) ?><div>Ini masih <b>draft</b>: belum dikirim ke atasan dan belum dinilai. Lengkapi isian lalu klik <b>Simpan dan Kirim ke atasan</b>, atau <b>Simpan draft</b> untuk melanjutkan nanti.</div></div>
        <?php endif; ?>
        <?php if (!empty($k['catatan_atasan']) && ($k['status'] ?? '') === 'revisi'): ?>
          <div class="notif notif-warn"><?= ikon('pesan', 17) ?><div><b>Catatan atasan:</b> <?= e($k['catatan_atasan']) ?></div></div>
        <?php endif; ?>

        <div class="form-baris">
          <label class="fl">Kehadiran <span class="wajib">*</span></label>
          <?= pilih_kehadiran((string) $v('kehadiran', 'masuk')) ?>
        </div>
        <div class="notif notif-info" data-hanya-absen hidden><?= ikon('info', 17) ?><div>Cukup pilih tanggal lalu simpan. Isian pekerjaan tidak perlu diisi.</div></div>

        <div class="form-baris" data-hanya-kerja>
          <label class="fl">Jenis pekerjaan <span class="wajib">*</span></label>
          <div class="pilih-jenis">
            <?php foreach (JENIS_KERJA as $kj => $j): ?>
              <label><input type="radio" name="jenis" value="<?= $kj ?>" <?= $kj === $jenisKini ? 'checked' : '' ?>>
                <b><i class="titik titik-<?= $kj ?>"></i><?= e($j['label']) ?></b><span><?= e($j['ket']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-grid-3">
          <div class="form-baris">
            <label class="fl" for="kTanggal">Tanggal <span class="wajib">*</span></label>
            <input type="date" id="kTanggal" name="tanggal" required value="<?= e($v('tanggal', $tglBawaan)) ?>"
                   min="<?= e($periode['mulai']) ?>" max="<?= e(min($periode['selesai'], date('Y-m-d'))) ?>">
          </div>
          <div class="form-baris" data-hanya-kerja>
            <label class="fl" for="kMulai">Jam mulai <span class="wajib">*</span></label>
            <input type="text" id="kMulai" name="jam_mulai" required data-jam inputmode="numeric" maxlength="5" placeholder="08.00" autocomplete="off" value="<?= e(jam_isian($v('jam_mulai'))) ?>">
          </div>
          <div class="form-baris" data-hanya-kerja>
            <label class="fl" for="kSelesai">Jam selesai <span class="wajib">*</span></label>
            <input type="text" id="kSelesai" name="jam_selesai" required data-jam inputmode="numeric" maxlength="5" placeholder="16.00" autocomplete="off" value="<?= e(jam_isian($v('jam_selesai'))) ?>">
          </div>
        </div>

        <div data-hanya-kerja>
        <div class="form-baris">
          <label class="fl" for="kJudul">Nama kegiatan <span class="wajib">*</span></label>
          <input type="text" id="kJudul" name="judul" maxlength="255" required value="<?= e($v('judul')) ?>"
                 placeholder="Contoh: Menyusun rekap absensi pegawai bulan Oktober">
        </div>

        <div class="form-grid" data-jenis="panitia">
          <div class="form-baris">
            <label class="fl" for="kPanitia">Nama kepanitiaan <span class="wajib">*</span></label>
            <input type="text" id="kPanitia" name="kepanitiaan" maxlength="255" value="<?= e($v('kepanitiaan')) ?>"
                   placeholder="Contoh: Panitia Wisuda Periode II 2026">
          </div>
          <div class="form-baris">
            <label class="fl" for="kPeranPanitia">Peran dalam panitia</label>
            <input type="text" id="kPeranPanitia" name="peran_panitia" maxlength="100" value="<?= e($v('peran_panitia')) ?>"
                   placeholder="Ketua, sekretaris, anggota seksi…">
          </div>
        </div>

        <div class="form-grid" data-jenis="insidental">
          <div class="form-baris">
            <label class="fl" for="kPemberi">Pemberi tugas <span class="wajib">*</span></label>
            <input type="text" id="kPemberi" name="pemberi_tugas" maxlength="150" value="<?= e($v('pemberi_tugas')) ?>"
                   placeholder="Contoh: Kepala Biro / Wakil Rektor II">
          </div>
          <div class="form-baris">
            <label class="fl">&nbsp;</label>
            
          </div>
        </div>

        <div class="form-baris" data-jenis="panitia insidental">
          <label class="fl" for="kSk">Nomor SK / surat tugas</label>
          <input type="text" id="kSk" name="nomor_sk" maxlength="100" value="<?= e($v('nomor_sk')) ?>" placeholder="Bila ada">
        </div>

        <div class="form-baris">
          <label class="fl" for="kUraian">Uraian pekerjaan</label>
          <textarea id="kUraian" name="uraian" rows="3" maxlength="5000"
                    placeholder="Rincian langkah yang dikerjakan"><?= e($v('uraian')) ?></textarea>
        </div>

        <div class="hasil-satuan">
          <div class="form-baris">
            <label class="fl" for="kHasil">Hasil / keluaran</label>
            <input type="text" id="kHasil" name="hasil" maxlength="500" value="<?= e($v('hasil')) ?>" placeholder="Contoh: Rekap absensi diserahkan ke Kabid Kepegawaian">
          </div>
          <div class="form-baris">
            <label class="fl" for="kSatuan">Satuan</label>
            <select id="kSatuan" name="satuan" data-satuan-lain><?= pilihan_satuan((string) $v('satuan', 'kegiatan')) ?></select>
            <input type="text" name="satuan_lain" maxlength="40" placeholder="Ketik satuan, mis. nota dinas" style="margin-top:6px" hidden>
          </div>
        </div>

        <div class="form-baris">
          <label class="fl" for="kBukti">Tautan bukti dukung</label>
          <input type="url" id="kBukti" name="tautan_bukti" maxlength="1000" value="<?= e($v('tautan_bukti')) ?>"
                 placeholder="https://drive.google.com/… (foto, dokumen, notulen)">
        </div>
        <?= medan_foto_kegiatan(isset($k['id']) ? foto_kegiatan((int) $k['id']) : null) ?>
        </div>
      </div>
      <div class="kartu-kaki">
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> <?= $adaDraft ? 'Simpan dan Kirim ke atasan' : 'Simpan' ?></button>
        <?php if ($aksi === 'baru'): ?>
          <button type="submit" class="btn btn-garis" name="lagi" value="1"><?= ikon('tambah', 15) ?> Simpan &amp; catat lagi</button>
        <?php endif; ?>
        <?php if ($aksi === 'baru' || $adaDraft): ?>
          <button type="submit" class="btn btn-garis" name="draft" value="1" formnovalidate title="Simpan sementara; belum dikirim ke atasan dan belum dinilai"><?= ikon('catatan', 15) ?> Simpan draft</button>
        <?php endif; ?>
      </div>
    </form>
    <?php
    return;
}

$saringAktif = ($_GET['tampil'] ?? '') === 'riwayat'
    || isset($_GET['bln']) || isset($_GET['jenis']) || isset($_GET['status']) || isset($_GET['cari']);

    $hariIni = date('Y-m-d');
    $isian = $_SESSION['isian_kegiatan'] ?? null;
    unset($_SESSION['isian_kegiatan']);
    $v = static function (string $f, $bawaan = '') use ($isian) {
        return $isian !== null && array_key_exists($f, $isian) ? $isian[$f] : $bawaan;
    };
    $tglBawaan = $hariIni;
    if ($periode) {
        $tglBawaan = max($periode['mulai'], min($hariIni, $periode['selesai']));
    }
    $jenisKini = $v('jenis', 'rutin');
    $hariIniList = rows(
        'SELECT k.* FROM kegiatan k WHERE k.periode_id = ? AND k.pengguna_id = ? AND k.tanggal = ?
          ORDER BY k.jam_mulai IS NULL, k.jam_mulai, k.id',
        [$pid, $saya, $hariIni]
    );
    $jamHariIni = 0.0;
    $draftHariIni = 0;
    foreach ($hariIniList as $k) {
        if (kegiatan_draft((int) $k['id'])) {
            $draftHariIni++;
            continue;
        }
        $jamHariIni += durasi_jam($k['jam_mulai'], $k['jam_selesai']);
    }
    $subjudul = tanggal_id($hariIni, true);
    ?>
    <div class="catat-dua">
    <?php if (!$periode || !$terbuka): ?>
      <div class="notif notif-warn"><?= ikon('seru', 17) ?><div>Periode kinerja ini sudah ditutup sehingga kegiatan baru tidak dapat dicatat.
        <a href="#riwayat">Lihat riwayat catatan</a></div></div>
    <?php else: ?>

    <form method="post" action="<?= e(url('kegiatan')) ?>" class="kartu kegiatan-ramping" data-wadah-jenis enctype="multipart/form-data">
      <?= csrf_field() ?>
      <input type="hidden" name="op" value="simpan">
      <input type="hidden" name="dari" value="hari">
      <div class="kartu-kepala kepala-nusantara"><div class="sambut-latar" aria-hidden="true"><div class="matahari"></div><div class="lapis awan"></div><div class="lapis burung"></div><div class="lapis gunung"></div><div class="lapis candi"></div><div class="lapis laut"></div><div class="perahu"><img src="<?= e(base_url('assets/img/nusantara/pinisi.svg')) ?>" alt=""></div><div class="lapis laut2"></div><div class="lapis batik"></div></div>
        <div><h2><?= ikon('catatan', 18) ?> Catat kegiatan</h2></div>
      </div>
      <div class="kartu-isi">
        <section class="langkah">
        <div class="langkah-kepala"><i>1</i><b>Kehadiran <span class="wajib">*</span></b></div>
        <div class="form-baris">
          <?= pilih_kehadiran((string) $v('kehadiran', 'masuk')) ?>
        </div>
        <div class="notif notif-info" data-hanya-absen hidden><?= ikon('info', 17) ?><div>Cukup pilih tanggal di bawah lalu simpan. Isian pekerjaan tidak perlu diisi.</div></div>
        </section>

        <section class="langkah" data-hanya-kerja>
        <div class="langkah-kepala"><i>2</i><b>Jenis pekerjaan <span class="wajib">*</span></b></div>
        <div class="form-baris">
          <div class="pilih-jenis">
            <?php foreach (JENIS_KERJA as $kj => $j): ?>
              <label class="jenis-<?= $kj ?>"><input type="radio" name="jenis" value="<?= $kj ?>" <?= $kj === $jenisKini ? 'checked' : '' ?>>
                <b><i class="titik titik-<?= $kj ?>"></i><?= e($j['label']) ?></b><span><?= e($j['ket']) ?></span></label>
            <?php endforeach; ?>
          </div>
        </div>
        </section>

        <section class="langkah" data-hanya-kerja>
        <div class="langkah-kepala"><i>3</i><b>Uraian singkat <span class="wajib">*</span></b></div>
        <div class="form-baris">
          <label class="fl" for="hJudul">Apa yang Anda kerjakan?</label>
          <input type="text" id="hJudul" name="judul" maxlength="255" required value="<?= e($v('judul')) ?>"
                 placeholder="Contoh: Menyusun rekap absensi pegawai bulan Oktober">
        </div>

        <div class="form-grid" data-jenis="panitia">
          <div class="form-baris">
            <label class="fl" for="hPanitia">Nama kepanitiaan <span class="wajib">*</span></label>
            <input type="text" id="hPanitia" name="kepanitiaan" maxlength="255" value="<?= e($v('kepanitiaan')) ?>" placeholder="Contoh: Panitia Wisuda Periode II 2026">
          </div>
          <div class="form-baris">
            <label class="fl" for="hPeranPanitia">Peran dalam panitia</label>
            <input type="text" id="hPeranPanitia" name="peran_panitia" maxlength="100" value="<?= e($v('peran_panitia')) ?>" placeholder="Ketua, sekretaris, anggota seksi…">
          </div>
        </div>
        <div class="form-baris" data-jenis="insidental">
          <label class="fl" for="hPemberi">Pemberi tugas <span class="wajib">*</span></label>
          <input type="text" id="hPemberi" name="pemberi_tugas" maxlength="150" value="<?= e($v('pemberi_tugas')) ?>" placeholder="Contoh: Kepala Biro / Wakil Rektor II">
        </div>
        </section>

        <section class="langkah">
        <div class="langkah-kepala"><i data-no-absen="2">4</i><b>Waktu pelaksanaan</b></div>
        <div class="form-grid-3">
          <div class="form-baris">
            <label class="fl" for="hTanggal">Tanggal <span class="wajib">*</span></label>
            <input type="date" id="hTanggal" name="tanggal" required value="<?= e($v('tanggal', $tglBawaan)) ?>"
                   min="<?= e($periode['mulai']) ?>" max="<?= e(min($periode['selesai'], $hariIni)) ?>">
          </div>
          <div class="form-baris" data-hanya-kerja>
            <label class="fl" for="hMulai">Jam mulai <span class="wajib">*</span></label>
            <input type="text" id="hMulai" name="jam_mulai" required data-jam inputmode="numeric" maxlength="5" placeholder="08.00" autocomplete="off" value="<?= e(jam_isian($v('jam_mulai'))) ?>">
          </div>
          <div class="form-baris" data-hanya-kerja>
            <label class="fl" for="hSelesai">Jam selesai <span class="wajib">*</span></label>
            <input type="text" id="hSelesai" name="jam_selesai" required data-jam inputmode="numeric" maxlength="5" placeholder="16.00" autocomplete="off" value="<?= e(jam_isian($v('jam_selesai'))) ?>">
          </div>
        </div>
        </section>

        <section class="langkah langkah-akhir" data-hanya-kerja>
        <div class="langkah-kepala"><i>5</i><b>Rincian tambahan <small>opsional</small></b></div>
        <details class="rincian"<?= $isian !== null && ($v('uraian') !== '' || $v('hasil') !== '' || $v('tautan_bukti') !== '') ? ' open' : '' ?>>
          <summary>Uraian, nomor SK, foto, dan bukti dukung <span class="rincian-petunjuk">(klik untuk membuka)</span></summary>
          <div class="form-baris">
            <label class="fl" for="hUraian">Uraian pekerjaan</label>
            <textarea id="hUraian" name="uraian" rows="3" maxlength="5000" placeholder="Rincian langkah yang dikerjakan"><?= e($v('uraian')) ?></textarea>
          </div>
          <div class="hasil-satuan">
            <div class="form-baris">
              <label class="fl" for="hHasil">Hasil / keluaran</label>
              <input type="text" id="hHasil" name="hasil" maxlength="500" value="<?= e($v('hasil')) ?>" placeholder="Contoh: Rekap absensi diserahkan ke Kabid Kepegawaian">
            </div>
            <div class="form-baris">
              <label class="fl" for="hSatuan">Satuan</label>
              <select id="hSatuan" name="satuan" data-satuan-lain><?= pilihan_satuan((string) $v('satuan', 'kegiatan')) ?></select>
              <input type="text" name="satuan_lain" maxlength="40" placeholder="Ketik satuan, mis. nota dinas" style="margin-top:6px" hidden>
            </div>
          </div>
          <div class="form-baris" data-jenis="panitia insidental">
            <label class="fl" for="hSk">Nomor SK / surat tugas</label>
            <input type="text" id="hSk" name="nomor_sk" maxlength="100" value="<?= e($v('nomor_sk')) ?>" placeholder="Bila ada">
          </div>
          <div class="form-baris">
            <label class="fl" for="hBukti">Tautan bukti dukung</label>
            <input type="url" id="hBukti" name="tautan_bukti" maxlength="1000" value="<?= e($v('tautan_bukti')) ?>" placeholder="https://drive.google.com/… (foto, dokumen, notulen)">
          </div>
          <?= medan_foto_kegiatan(null) ?>
        </details>
        </section>
      </div>
      <div class="kartu-kaki">
        <button type="submit" class="btn btn-utama"><?= ikon('simpan', 15) ?> Simpan dan Kirim ke atasan</button>
        <button type="submit" class="btn btn-garis" name="draft" value="1" formnovalidate title="Simpan sementara; belum dikirim ke atasan dan belum dinilai"><?= ikon('catatan', 15) ?> Simpan draft</button>
      </div>
    </form>
    <?php endif; ?>

    </div>
    <?php

$bln = rentang_sah($periode, (string) ($_GET['bln'] ?? date('Y-m')));
$jenis = array_key_exists($_GET['jenis'] ?? '', JENIS_KERJA) ? $_GET['jenis'] : '';
$status = array_key_exists($_GET['status'] ?? '', STATUS_KEGIATAN) || ($_GET['status'] ?? '') === 'draft' ? $_GET['status'] : '';
$cari = trim((string) ($_GET['cari'] ?? ''));

$syarat = 'k.periode_id = ? AND k.pengguna_id = ?';
$param = [$pid, $saya];
[$sqlRentang, $paramRentang] = syarat_rentang($periode, $bln);
$syarat .= $sqlRentang;
$param = array_merge($param, $paramRentang);
if ($jenis) {
    $syarat .= ' AND k.jenis = ?';
    $param[] = $jenis;
}
if ($status === 'draft') {
    $syarat .= sql_hanya_draft('k.id');
} elseif ($status) {
    $syarat .= ' AND k.status = ?' . sql_bukan_draft('k.id');
    $param[] = $status;
}
if ($cari !== '') {
    $syarat .= ' AND (k.judul LIKE ? OR k.uraian LIKE ? OR k.kepanitiaan LIKE ?)';
    $like = '%' . $cari . '%';
    array_push($param, $like, $like, $like);
}
$daftar = rows(
    "SELECT k.*, v.nama AS verifikator
       FROM kegiatan k LEFT JOIN pengguna v ON v.id = k.diverifikasi_oleh
      WHERE $syarat ORDER BY k.tanggal DESC, k.jam_mulai DESC, k.id DESC",
    $param
);

$ring = ['total' => 0, 'jam' => 0.0, 'rutin' => 0, 'panitia' => 0, 'insidental' => 0, 'revisi' => 0, 'draft' => 0];
foreach ($daftar as $k) {
    if (kegiatan_draft((int) $k['id'])) {
        $ring['draft']++;
        continue;
    }
    $ring['total']++;
    $ring[$k['jenis']]++;
    $ring['jam'] += durasi_jam($k['jam_mulai'], $k['jam_selesai']);
    if ($k['status'] === 'revisi') {
        $ring['revisi']++;
    }
}

$bulanPilihan = pilihan_rentang($periode);
?>

<div id="riwayat" class="jangkar-riwayat"></div>
<?php if ($ring['revisi'] > 0): ?>
  <div class="notif notif-warn kegiatan-ramping"><?= ikon('seru', 17) ?><div><b><?= $ring['revisi'] ?> kegiatan perlu diperbaiki</b> sesuai catatan atasan.
    <a href="<?= e(url('kegiatan', ['bln' => $bln, 'status' => 'revisi'])) ?>#riwayat">Tampilkan</a></div></div>
<?php endif; ?>


<div class="kartu kegiatan-ramping">
  <div class="kartu-kepala">
    <div><h2><?= ikon('catatan', 18) ?> Catatan kegiatan saya</h2></div>
    <?php if ($saringAktif): ?><div class="aksi"><a class="btn btn-garis btn-kecil" href="<?= e(url('kegiatan')) ?>#riwayat"><?= ikon('ulang', 14) ?> Atur ulang saringan</a></div><?php endif; ?>
  </div>
  <div class="kartu-isi" style="padding-bottom:0">
    <form method="get" class="saring" action="<?= e(base_url('index.php')) ?>#riwayat">
      <input type="hidden" name="h" value="kegiatan">
      <div class="form-baris"><label class="fl">Bulan / triwulan</label>
        <select name="bln" data-auto-kirim><?= pilihan($bulanPilihan, $bln) ?></select></div>
      <div class="form-baris"><label class="fl">Jenis</label>
        <select name="jenis" data-auto-kirim><option value="">Semua jenis</option>
          <?php foreach (JENIS_KERJA as $kj => $j): ?><option value="<?= $kj ?>" <?= $jenis === $kj ? 'selected' : '' ?>><?= e($j['label']) ?></option><?php endforeach; ?></select></div>
      <div class="form-baris"><label class="fl">Status</label>
        <select name="status" data-auto-kirim><option value="">Semua status</option>
          <?php foreach (STATUS_KEGIATAN as $ks => $st): ?><option value="<?= $ks ?>" <?= $status === $ks ? 'selected' : '' ?>><?= e($st['label']) ?></option><?php endforeach; ?>
          <option value="draft" <?= $status === 'draft' ? 'selected' : '' ?>>Draft</option></select></div>
      <div class="form-baris" style="flex:1"><label class="fl">Cari</label>
        <input type="search" name="cari" value="<?= e($cari) ?>" placeholder="Nama kegiatan, uraian, kepanitiaan…"></div>
      <button class="btn btn-garis" type="submit"><?= ikon('cari', 15) ?> Cari</button>
    </form>
  </div>

  <?php if (!$daftar): ?>
    <?= sel_kosong('Belum ada kegiatan', '') ?>
  <?php else: ?>
  <div class="tabel-bungkus">
    <table class="tabel">
      <thead><tr><th>Tanggal</th><th>Kegiatan</th><th>Jenis</th><th>Satuan</th><th>Status</th><th class="kolom-aksi">Aksi</th></tr></thead>
      <tbody>
      <?php foreach ($daftar as $k): ?>
        <tr>
          <td class="nowrap"><b><?= e(tanggal_pendek($k['tanggal'])) ?></b> <span class="lc lc-abu" style="padding:0 6px;font-size:.66rem">TW<?= tw_dari_tanggal($periode, $k['tanggal']) ?></span>
            <span class="sub"><?= e(NAMA_HARI[(int) date('w', strtotime($k['tanggal']))]) ?><?= $k['jam_mulai'] ? ' · ' . e(jam_pendek($k['jam_mulai'])) . '-' . e(jam_pendek($k['jam_selesai'])) : '' ?></span></td>
          <td>
            <b><?= $k['judul'] !== '' ? e($k['judul']) : '<i class="lemah">(belum diberi judul)</i>' ?></b> <?= lencana_kehadiran($k['kehadiran'] ?? null) ?>
            <?php if ($k['jenis'] === 'panitia' && $k['kepanitiaan']): ?><span class="sub"><?= ikon('grup', 13) ?> <?= e($k['kepanitiaan']) ?><?= $k['peran_panitia'] ? ' · ' . e($k['peran_panitia']) : '' ?></span><?php endif; ?>
            <?php if ($k['jenis'] === 'insidental' && $k['pemberi_tugas']): ?><span class="sub"><?= ikon('petir', 13) ?> Ditugaskan oleh <?= e($k['pemberi_tugas']) ?></span><?php endif; ?>
            <?php if ($k['hasil']): ?><span class="sub">Hasil: <?= e($k['hasil']) ?></span><?php endif; ?>
            <?php if ($k['tautan_bukti']): ?><span class="sub"><a href="<?= e($k['tautan_bukti']) ?>" target="_blank" rel="noopener"><?= ikon('tautan', 13) ?> Bukti dukung</a></span><?php endif; ?><?php if ($fotoK = foto_kegiatan((int) $k['id'])): ?><span class="sub"><a href="<?= e(url_berkas('kegiatan', $fotoK)) ?>" target="_blank" rel="noopener"><?= ikon('kamera', 13) ?> Foto bukti</a></span><?php endif; ?>
            <?php if ($k['catatan_atasan']): ?><span class="sub" style="color:<?= $k['status'] === 'revisi' ? 'var(--merah)' : 'var(--merek-700)' ?>"><?= ikon('pesan', 13) ?> <?= e($k['catatan_atasan']) ?></span><?php endif; ?>
          </td>
          <td><?= lencana_jenis($k['jenis']) ?></td>
          <td><?= e($k['satuan']) ?></td>
          <td><?= lencana_status_kegiatan($k) ?>
            <?php if ($k['status'] === 'disetujui' && $k['verifikator'] && !kegiatan_draft((int) $k['id'])): ?><span class="sub">oleh <?= e($k['verifikator']) ?></span><?php endif; ?></td>
          <td class="kolom-aksi">
            <div class="aksi-baris"><?= kegiatan_rincian($k) ?>
              <?php if ($terbuka): ?>
                <a class="btn btn-hantu btn-ikon" title="Salin sebagai kegiatan baru" href="<?= e(url('kegiatan', ['aksi' => 'baru', 'salin' => $k['id']])) ?>"><?= ikon('ulang', 15) ?></a>
              <?php endif; ?>
              <?php if ($bolehSunting($k)): ?>
                <a class="btn btn-hantu btn-ikon" title="<?= kegiatan_draft((int) $k['id']) ? 'Lanjutkan draft' : ($k['status'] === 'disetujui' ? 'Ubah (dikirim ulang untuk diverifikasi)' : 'Ubah') ?>" href="<?= e(url('kegiatan', ['aksi' => 'ubah', 'id' => $k['id']])) ?>"><?= ikon('sunting', 15) ?></a>
                <form method="post" data-konfirmasi="Hapus kegiatan ini?">
                  <?= csrf_field() ?><input type="hidden" name="op" value="hapus"><input type="hidden" name="id" value="<?= (int) $k['id'] ?>">
                  <button class="btn btn-hantu btn-ikon" type="submit" title="Hapus"><?= ikon('hapus', 15) ?></button></form>
              <?php endif; ?>
            </div>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>
<?= modal_rincian_kegiatan() ?>
