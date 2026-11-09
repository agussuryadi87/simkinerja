(function () {
  'use strict';
  var skrip = document.currentScript, alamatCari = skrip.getAttribute('data-cari');
  var video = document.getElementById('pindaiVideo'), tirai = document.getElementById('pindaiTirai'), pesan = document.getElementById('pindaiPesan');
  var tMulai = document.getElementById('pindaiMulai'), tGanti = document.getElementById('pindaiGanti'), tSenter = document.getElementById('pindaiSenter');
  var tBerhenti = document.getElementById('pindaiBerhenti'), inFoto = document.getElementById('pindaiFoto'), layar = document.getElementById('pindaiLayar');
  var formManual = document.getElementById('pindaiManual'), inKode = document.getElementById('pindaiKode');
  var kotakHasil = document.getElementById('pindaiHasil'), status = document.getElementById('pindaiStatus');
  var riwayatBungkus = document.getElementById('pindaiRiwayatBungkus'), riwayatDaftar = document.getElementById('pindaiRiwayat');
  if (!video || !alamatCari) return;

  var aliran = null, jalan = false, sibuk = false, kameraBelakang = true, senterNyala = false, terakhir = { teks: '', waktu: 0 };
  var kanvas = document.createElement('canvas'), ctx = kanvas.getContext('2d', { willReadFrequently: true });
  var detektor = null;
  if ('BarcodeDetector' in window) {
    try {
      window.BarcodeDetector.getSupportedFormats().then(function (f) {
        if (f.indexOf('qr_code') !== -1) detektor = new window.BarcodeDetector({ formats: ['qr_code'] });
      }).catch(function () {});
    } catch (e) {}
  }

  function el(tag, kelas, teks) {
    var e = document.createElement(tag);
    if (kelas) e.className = kelas;
    if (teks !== undefined) e.textContent = teks;
    return e;
  }

  function tampilTirai(teks, tombol) {
    tirai.hidden = false;
    pesan.textContent = teks;
    tMulai.hidden = !tombol;
    layar.classList.remove('aktif');
  }

  function bunyi() {
    try {
      if (navigator.vibrate) navigator.vibrate(60);
      var AC = window.AudioContext || window.webkitAudioContext;
      if (!AC) return;
      var a = new AC(), o = a.createOscillator(), g = a.createGain();
      o.frequency.value = 1150; g.gain.value = 0.08;
      o.connect(g); g.connect(a.destination); o.start();
      setTimeout(function () { o.stop(); a.close(); }, 110);
    } catch (e) {}
  }

  function berhenti() {
    jalan = false;
    if (aliran) aliran.getTracks().forEach(function (t) { t.stop(); });
    aliran = null;
    video.srcObject = null;
    tGanti.hidden = true; tSenter.hidden = true; tBerhenti.hidden = true;
    senterNyala = false;
    tampilTirai('Kamera dimatikan. Ketuk tombol di bawah untuk memindai lagi.', true);
  }

  function mulai() {
    if (!window.isSecureContext || !navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
      tampilTirai('Kamera langsung hanya dapat dipakai bila aplikasi dibuka melalui alamat https://. Gunakan "Pindai dari foto" atau ketik kode di bawah.', false);
      return;
    }
    tampilTirai('Meminta izin kamera…', false);
    if (aliran) aliran.getTracks().forEach(function (t) { t.stop(); });
    navigator.mediaDevices.getUserMedia({
      audio: false,
      video: { facingMode: kameraBelakang ? { ideal: 'environment' } : 'user', width: { ideal: 1280 }, height: { ideal: 720 } }
    }).then(function (s) {
      aliran = s;
      video.srcObject = s;
      video.setAttribute('playsinline', '');
      return video.play();
    }).then(function () {
      tirai.hidden = true;
      layar.classList.add('aktif');
      tBerhenti.hidden = false;
      navigator.mediaDevices.enumerateDevices().then(function (d) {
        tGanti.hidden = d.filter(function (x) { return x.kind === 'videoinput'; }).length < 2;
      }).catch(function () {});
      var trek = aliran.getVideoTracks()[0], kemampuan = trek && trek.getCapabilities ? trek.getCapabilities() : {};
      tSenter.hidden = !kemampuan.torch;
      jalan = true;
      putaran();
    }).catch(function (err) {
      var nama = err && err.name;
      tampilTirai(nama === 'NotAllowedError' ? 'Izin kamera ditolak. Izinkan akses kamera untuk situs ini di pengaturan browser, lalu coba lagi.'
        : nama === 'NotFoundError' ? 'Kamera tidak ditemukan pada perangkat ini. Gunakan "Pindai dari foto" atau ketik kode.'
        : 'Kamera tidak dapat dibuka (' + (nama || 'galat') + '). Tutup aplikasi lain yang memakai kamera lalu coba lagi.', true);
    });
  }

  function bacaBingkai(sumber, lebar, tinggi) {
    var maks = 720, skala = Math.min(1, maks / Math.max(lebar, tinggi));
    kanvas.width = Math.round(lebar * skala);
    kanvas.height = Math.round(tinggi * skala);
    ctx.drawImage(sumber, 0, 0, kanvas.width, kanvas.height);
    var potong = Math.round(Math.min(kanvas.width, kanvas.height) * 0.9);
    var x0 = Math.round((kanvas.width - potong) / 2), y0 = Math.round((kanvas.height - potong) / 2);
    var hasil = window.QrBaca.baca(ctx.getImageData(x0, y0, potong, potong));
    if (hasil === null && (potong < kanvas.width || potong < kanvas.height)) hasil = window.QrBaca.baca(ctx.getImageData(0, 0, kanvas.width, kanvas.height));
    return hasil;
  }

  function putaran() {
    if (!jalan) return;
    if (sibuk || video.readyState < 2 || !video.videoWidth) { setTimeout(putaran, 120); return; }
    sibuk = true;
    var selesai = function (teks) {
      sibuk = false;
      if (teks) temukan(teks);
      if (jalan) setTimeout(putaran, teks ? 1200 : 160);
    };
    if (detektor) {
      detektor.detect(video).then(function (k) { selesai(k && k.length ? k[0].rawValue : null); }).catch(function () {
        detektor = null;
        selesai(null);
      });
    } else {
      var teks = null;
      try { teks = bacaBingkai(video, video.videoWidth, video.videoHeight); } catch (e) {}
      selesai(teks);
    }
  }

  function temukan(teks) {
    var kini = Date.now();
    if (teks === terakhir.teks && kini - terakhir.waktu < 4000) return;
    terakhir = { teks: teks, waktu: kini };
    bunyi();
    layar.classList.add('kena');
    setTimeout(function () { layar.classList.remove('kena'); }, 500);
    cari(teks);
  }

  function cari(teks) {
    status.textContent = 'Mencari data…';
    fetch(alamatCari + '&cari=' + encodeURIComponent(teks), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) {
        if (r.redirected || !r.ok) throw new Error(r.status === 403 ? 'akses' : 'sesi');
        return r.json();
      })
      .then(function (d) { tampilHasil(d, teks); })
      .catch(function (e) {
        status.textContent = 'Gagal memuat data.';
        kotakHasil.replaceChildren(pesanGagal(e.message === 'akses' ? 'Anda tidak memiliki akses ke data Sarana dan Prasarana.' : 'Sesi login berakhir. Muat ulang halaman lalu masuk kembali.'));
      });
  }

  function pesanGagal(teks, isi) {
    var d = el('div', 'pindai-gagal');
    d.appendChild(el('b', '', teks));
    if (isi) d.appendChild(el('code', '', isi));
    return d;
  }

  function tampilHasil(d, teks) {
    if (!d || !d.ok) {
      status.textContent = 'Kode tidak dikenali.';
      kotakHasil.replaceChildren(pesanGagal('QR / kode ini tidak terdaftar sebagai barang atau ruangan.', teks.length > 120 ? teks.slice(0, 120) + '…' : teks));
      return;
    }
    status.textContent = (d.jenis === 'ruang' ? 'Ruangan' : 'Barang') + ' ditemukan · ' + new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
    var kartu = el('div', 'pindai-temu');
    var kepala = el('div', 'pindai-temu-kepala');
    if (d.foto) {
      var img = el('img', 'pindai-temu-foto');
      img.src = d.foto; img.alt = ''; img.loading = 'lazy';
      kepala.appendChild(img);
    }
    var judul = el('div');
    judul.appendChild(el('b', '', d.nama));
    judul.appendChild(el('code', '', d.kode));
    var lencana = el('div', 'pindai-temu-lencana');
    if (d.kondisi) lencana.appendChild(el('span', 'lc lc-' + d.kondisi.warna, d.kondisi.label));
    if (d.status) lencana.appendChild(el('span', 'lc lc-abu', d.status));
    if (lencana.childNodes.length) judul.appendChild(lencana);
    kepala.appendChild(judul);
    kartu.appendChild(kepala);
    var info = el('dl', 'pindai-temu-info');
    (d.info || []).forEach(function (p) {
      var baris = el('div');
      baris.appendChild(el('dt', '', p[0]));
      baris.appendChild(el('dd', '', p[1]));
      info.appendChild(baris);
    });
    kartu.appendChild(info);
    if (d.url) {
      var aksi = el('div', 'pindai-temu-aksi');
      var buka = el('a', 'btn btn-utama', 'Buka detail lengkap');
      buka.href = d.url;
      aksi.appendChild(buka);
      kartu.appendChild(aksi);
    }
    kotakHasil.replaceChildren(kartu);
    simpanRiwayat(d);
    if (window.matchMedia('(max-width: 900px)').matches) kotakHasil.scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function bacaRiwayat() {
    try { return JSON.parse(sessionStorage.getItem('simkin.pindai') || '[]'); } catch (e) { return []; }
  }
  function simpanRiwayat(d) {
    var daftar = bacaRiwayat().filter(function (x) { return x.kode !== d.kode; });
    daftar.unshift({ nama: d.nama, kode: d.kode, url: d.url, teks: d.kode });
    daftar = daftar.slice(0, 8);
    try { sessionStorage.setItem('simkin.pindai', JSON.stringify(daftar)); } catch (e) {}
    gambarRiwayat();
  }
  function gambarRiwayat() {
    var daftar = bacaRiwayat();
    riwayatBungkus.hidden = !daftar.length;
    riwayatDaftar.replaceChildren();
    daftar.forEach(function (x) {
      var li = el('li'), a = el('a');
      if (x.url) { a.href = x.url; } else { a.href = '#'; a.addEventListener('click', function (ev) { ev.preventDefault(); cari(x.kode); }); }
      a.appendChild(el('b', '', x.nama));
      a.appendChild(el('code', '', x.kode));
      li.appendChild(a);
      riwayatDaftar.appendChild(li);
    });
  }

  tMulai.addEventListener('click', mulai);
  tBerhenti.addEventListener('click', berhenti);
  tGanti.addEventListener('click', function () { kameraBelakang = !kameraBelakang; mulai(); });
  tSenter.addEventListener('click', function () {
    var trek = aliran && aliran.getVideoTracks()[0];
    if (!trek) return;
    senterNyala = !senterNyala;
    trek.applyConstraints({ advanced: [{ torch: senterNyala }] }).then(function () {
      tSenter.setAttribute('aria-pressed', senterNyala ? 'true' : 'false');
      tSenter.classList.toggle('aktif', senterNyala);
    }).catch(function () { senterNyala = false; tSenter.hidden = true; });
  });
  inFoto.addEventListener('change', function () {
    var f = inFoto.files && inFoto.files[0];
    if (!f) return;
    status.textContent = 'Membaca foto…';
    inFoto.value = '';
    var olah = function (sumber, lebar, tinggi) {
      var teks = null;
      try { teks = bacaBingkai(sumber, lebar, tinggi); } catch (e) {}
      if (teks) { terakhir = { teks: '', waktu: 0 }; temukan(teks); }
      else {
        status.textContent = 'QR tidak terbaca.';
        kotakHasil.replaceChildren(pesanGagal('QR tidak terbaca dari foto. Foto lebih dekat, pastikan label terang dan tidak buram, atau ketik kodenya.'));
      }
    };
    var gagal = function () { status.textContent = 'Foto tidak dapat dibuka.'; };
    var lewatPembaca = function () {
      var r = new FileReader();
      r.onload = function () {
        var img = new Image();
        img.onload = function () { olah(img, img.naturalWidth, img.naturalHeight); };
        img.onerror = gagal;
        img.src = r.result;
      };
      r.onerror = gagal;
      r.readAsDataURL(f);
    };
    if (window.createImageBitmap) {
      createImageBitmap(f).then(function (bmp) { olah(bmp, bmp.width, bmp.height); if (bmp.close) bmp.close(); }).catch(lewatPembaca);
    } else {
      lewatPembaca();
    }
  });
  formManual.addEventListener('submit', function (ev) {
    ev.preventDefault();
    var k = inKode.value.trim();
    if (k) cari(k);
  });
  document.addEventListener('visibilitychange', function () { if (document.hidden && jalan) berhenti(); });
  window.addEventListener('pagehide', function () { if (aliran) aliran.getTracks().forEach(function (t) { t.stop(); }); });
  gambarRiwayat();
  if (!window.isSecureContext) {
    tampilTirai('Kamera langsung hanya dapat dipakai bila aplikasi dibuka melalui alamat https://. Gunakan "Pindai dari foto" atau ketik kode di bawah.', false);
  }
})();
