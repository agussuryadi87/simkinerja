(function () {
  'use strict';

  var $ = function (s, r) { return (r || document).querySelector(s); };
  var $$ = function (s, r) { return Array.prototype.slice.call((r || document).querySelectorAll(s)); };

  function bilahAtas() {
    $$('.tabel-bungkus').forEach(function (bungkus) {
      if (!bungkus.querySelector('.tabel-dosen, .tabel-padat')) { return; }
      var ada = bungkus.previousElementSibling && bungkus.previousElementSibling.classList.contains('geser-atas') ? bungkus.previousElementSibling : null;
      var tabel = bungkus.querySelector('table');
      var lebar = bungkus.scrollWidth > bungkus.clientWidth + 2;
      if (!lebar) { if (ada) { ada.hidden = true; } return; }
      if (!ada) {
        ada = document.createElement('div');
        ada.className = 'geser-atas no-cetak';
        ada.setAttribute('aria-hidden', 'true');
        ada.appendChild(document.createElement('div'));
        bungkus.parentNode.insertBefore(ada, bungkus);
        var kunci = false;
        ada.addEventListener('scroll', function () { if (kunci) { kunci = false; return; } kunci = true; bungkus.scrollLeft = ada.scrollLeft; });
        bungkus.addEventListener('scroll', function () { if (kunci) { kunci = false; return; } kunci = true; ada.scrollLeft = bungkus.scrollLeft; });
      }
      ada.hidden = false;
      ada.firstChild.style.width = (tabel ? tabel.scrollWidth : bungkus.scrollWidth) + 'px';
    });
  }
  document.addEventListener('click', function (e) {
    var t = e.target.closest ? e.target.closest('[data-tutup-notif]') : null;
    if (t && t.parentNode) { t.parentNode.remove(); }
  });
  document.addEventListener('change', function (e) {
    var s = e.target;
    if (!s.matches || !s.matches('select[data-pilih-tw]') || !s.form) { return; }
    ['dari', 'sampai'].forEach(function (n) { if (s.form.elements[n]) { s.form.elements[n].value = ''; } });
    s.form.submit();
  });
  window.addEventListener('load', bilahAtas);
  window.addEventListener('resize', bilahAtas);

  document.addEventListener('change', function (e) {
    var s = e.target;
    if (!s.matches || !s.matches('select[data-satuan-lain]')) { return; }
    var lain = s.parentNode.querySelector('input[name="satuan_lain"]');
    if (!lain) { return; }
    lain.hidden = s.value !== '__lain';
    if (!lain.hidden) { lain.focus(); }
  });

  var sisi = $('#sisi');
  var tombolMenu = $('#tombolMenu');
  if (tombolMenu && sisi) {
    tombolMenu.addEventListener('click', function () { sisi.classList.toggle('buka'); });
    var tiraiSisi = $('#tiraiSisi');
    if (tiraiSisi) tiraiSisi.addEventListener('click', function () { sisi.classList.remove('buka'); });
  }

  var tombolCiut = $('#tombolCiut');
  if (tombolCiut) {
    tombolCiut.addEventListener('click', function () {
      var tl = $('#tataLetak');
      tl.classList.toggle('ringkas');
      try { localStorage.setItem('simkin.ciut', tl.classList.contains('ringkas') ? '1' : '0'); } catch (e) {}
    });
  }

  var menuPengguna = $('#penggunaMenu');
  var chip = $('#penggunaChip');
  if (menuPengguna && chip) {
    chip.addEventListener('click', function (ev) {
      ev.stopPropagation();
      var buka = menuPengguna.classList.toggle('buka');
      chip.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });
    document.addEventListener('click', function (ev) {
      if (!menuPengguna.contains(ev.target)) {
        menuPengguna.classList.remove('buka');
        chip.setAttribute('aria-expanded', 'false');
      }
    });
  }

  function bukaModal(tirai) {
    if (!tirai) return;
    tirai.hidden = false;
    document.body.style.overflow = 'hidden';
    var fokus = tirai.querySelector('[autofocus], input:not([type=hidden]), textarea, select');
    if (fokus) setTimeout(function () { fokus.focus(); }, 40);
  }

  function tutupModal(tirai) {
    if (!tirai) return;
    tirai.hidden = true;
    kotor = false;
    if (!$('.tirai:not([hidden])')) document.body.style.overflow = '';
  }

  function isiForm(tirai, data) {
    Object.keys(data).forEach(function (k) {
      var el = tirai.querySelector('[name="' + k + '"]:not([type=hidden])') || tirai.querySelector('[name="' + k + '"]');
      if (el) {
        if (el.type === 'checkbox') {
          el.checked = !!data[k] && data[k] !== '0';
        } else if (el.type === 'radio') {
          $$('[name="' + k + '"]', tirai).forEach(function (r) { r.checked = String(r.value) === String(data[k]); });
        } else {
          el.value = data[k] === null ? '' : data[k];
        }
        el.dispatchEvent(new Event('change', { bubbles: true }));
      }
      var teks = tirai.querySelector('[data-teks="' + k + '"]');
      if (teks) teks.textContent = data[k] === null ? '' : data[k];
      var kosong = data[k] === null || data[k] === '';
      $$('[data-tautan="' + k + '"]', tirai).forEach(function (a) { if (kosong) a.removeAttribute('href'); else a.href = data[k]; });
      $$('[data-gambar="' + k + '"]', tirai).forEach(function (g) { if (kosong) g.removeAttribute('src'); else g.src = data[k]; });
      $$('[data-baris="' + k + '"]', tirai).forEach(function (b) { b.hidden = kosong; });
      $$('[data-ganda="' + k + '"] input[type="checkbox"]', tirai).forEach(function (cb) {
        cb.checked = (',' + (data[k] === null ? '' : data[k]) + ',').indexOf(',' + cb.value + ',') !== -1;
      });
    });
  }

  document.addEventListener('click', function (ev) {
    var semuaNilai = ev.target.closest('[data-semua-nilai]');
    if (semuaNilai && semuaNilai.form) {
      $$('fieldset:not([disabled]) input[type="radio"][value="' + semuaNilai.getAttribute('data-semua-nilai') + '"]', semuaNilai.form).forEach(function (r) { r.checked = true; });
      return;
    }
    var pembuka = ev.target.closest('[data-buka]');
    if (pembuka) {
      ev.preventDefault();
      var tirai = document.getElementById(pembuka.getAttribute('data-buka'));
      if (tirai) {
        var form = tirai.querySelector('form');
        if (form && pembuka.hasAttribute('data-reset')) form.reset();
        if (pembuka.getAttribute('data-isi')) {
          try { isiForm(tirai, JSON.parse(pembuka.getAttribute('data-isi'))); } catch (e) {}
        }
        var judul = pembuka.getAttribute('data-judul');
        var judulEl = tirai.querySelector('.modal-kepala h2');
        if (judul && judulEl) judulEl.textContent = judul;
        bukaModal(tirai);
      }
      return;
    }
    var penutup = ev.target.closest('[data-tutup]');
    if (penutup) {
      tutupModal(penutup.closest('.tirai'));
      return;
    }
  });

  document.addEventListener('keydown', function (ev) {
    if (ev.key !== 'Enter') return;
    var t = ev.target;
    if (t.tagName === 'INPUT' && t.type !== 'submit' && t.type !== 'button' && t.closest('.tirai')) {
      ev.preventDefault();
    }
  });

  var kotor = false;
  document.addEventListener('input', function (ev) {
    if (ev.target.closest && ev.target.closest('.tirai:not(#tiraiKonfirmasi)')) kotor = true;
  });
  window.addEventListener('beforeunload', function (ev) {
    if (kotor && $('.tirai:not([hidden]):not(#tiraiKonfirmasi)')) {
      ev.preventDefault();
      ev.returnValue = '';
    }
  });

  var tiraiKonf = $('#tiraiKonfirmasi');
  var aksiTunda = null;
  if (tiraiKonf) {
    $('#konfYa').addEventListener('click', function () {
      var a = aksiTunda;
      aksiTunda = null;
      tutupModal(tiraiKonf);
      if (a) a();
    });
  }

  document.addEventListener('submit', function (ev) {
    var form = ev.target;
    kotor = false;
    var pengirim = ev.submitter || null;
    var pesan = (pengirim && pengirim.getAttribute('data-konfirmasi')) || form.getAttribute('data-konfirmasi');
    if (!pesan || form.dataset.terkonfirmasi === '1' || !tiraiKonf) return;
    ev.preventDefault();
    $('#konfPesan').textContent = pesan;
    var ya = $('#konfYa');
    var gaya = (pengirim && pengirim.getAttribute('data-gaya')) || form.getAttribute('data-gaya');
    ya.className = 'btn ' + (gaya === 'utama' ? 'btn-utama' : 'btn-bahaya');
    aksiTunda = function () {
      form.dataset.terkonfirmasi = '1';
      if (pengirim && pengirim.name) {
        var tersembunyi = document.createElement('input');
        tersembunyi.type = 'hidden';
        tersembunyi.name = pengirim.name;
        tersembunyi.value = pengirim.value;
        form.appendChild(tersembunyi);
      }
      form.submit();
    };
    bukaModal(tiraiKonf);
  }, true);

  function aturJenis(wadah) {
    var pilih = wadah.querySelector('input[name="jenis"]:checked') || wadah.querySelector('select[name="jenis"]');
    var jenis = pilih ? pilih.value : '';
    $$('[data-jenis]', wadah).forEach(function (el) {
      var cocok = el.getAttribute('data-jenis').split(' ').indexOf(jenis) !== -1;
      el.hidden = !cocok;
      $$('input, select, textarea', el).forEach(function (i) { i.disabled = !cocok; });
    });
  }
  function aturHadir(wadah) {
    var pilih = wadah.querySelector('input[name="kehadiran"]:checked') || wadah.querySelector('select[name="kehadiran"]');
    var kerja = !pilih || pilih.value === 'masuk';
    $$('[data-hanya-kerja]', wadah).forEach(function (el) {
      el.hidden = !kerja;
      $$('input, select, textarea', el).forEach(function (i) { i.disabled = !kerja; });
    });
    $$('[data-hanya-absen]', wadah).forEach(function (el) { el.hidden = kerja; });
    $$('[data-no-absen]', wadah).forEach(function (el) {
      if (!el.hasAttribute('data-no-kerja')) el.setAttribute('data-no-kerja', el.textContent);
      el.textContent = kerja ? el.getAttribute('data-no-kerja') : el.getAttribute('data-no-absen');
    });
    if (kerja) aturJenis(wadah);
  }
  $$('[data-wadah-jenis]').forEach(function (w) {
    w.addEventListener('change', function (ev) {
      if (ev.target.name === 'jenis') aturJenis(w);
      if (ev.target.name === 'kehadiran') aturHadir(w);
    });
    aturJenis(w);
    aturHadir(w);
  });

  function kecilkanFoto(berkas, sisiMaks, mutu) {
    return new Promise(function (selesai) {
      if (!/^image\/(jpeg|png|webp)$/.test(berkas.type) || berkas.size < 900 * 1024) { selesai(berkas); return; }
      var pembaca = new FileReader(), img = new Image();
      img.onload = function () {
        var skala = Math.min(1, sisiMaks / Math.max(img.width, img.height));
        var c = document.createElement('canvas');
        c.width = Math.round(img.width * skala);
        c.height = Math.round(img.height * skala);
        c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
        c.toBlob(function (b) {
          selesai(b && b.size < berkas.size ? new File([b], berkas.name.replace(/\.[^.]+$/, '') + '.jpg', { type: 'image/jpeg' }) : berkas);
        }, 'image/jpeg', mutu);
      };
      img.onerror = function () { selesai(berkas); };
      pembaca.onload = function () { img.src = pembaca.result; };
      pembaca.onerror = function () { selesai(berkas); };
      pembaca.readAsDataURL(berkas);
    });
  }
  $$('input[type="file"][accept*="image"]').forEach(function (inp) {
    if (inp.closest('[data-foto-bukti]') || inp.id === 'fotoInput') return;
    var form = inp.form, proses = null;
    inp.addEventListener('change', function () {
      var daftar = Array.prototype.slice.call(inp.files || []);
      if (!daftar.length) return;
      proses = Promise.all(daftar.map(function (f) { return kecilkanFoto(f, 2000, 0.85); })).then(function (hasil) {
        try {
          var dt = new DataTransfer();
          hasil.forEach(function (f) { dt.items.add(f); });
          inp.files = dt.files;
        } catch (e) {}
        proses = null;
      });
    });
    if (form) form.addEventListener('submit', function (ev) {
      if (!proses) return;
      ev.preventDefault();
      var tombol = ev.submitter;
      proses.then(function () { if (form.requestSubmit) form.requestSubmit(tombol || undefined); else form.submit(); });
    });
  });

  $$('input[type="file"][data-kirim-otomatis]').forEach(function (inp) {
    inp.addEventListener('change', function () {
      if (inp.files && inp.files.length && inp.form) inp.form.submit();
    });
  });

  $$('[data-foto-bukti]').forEach(function (medan) {
    var masukan = $$('input[type="file"]', medan);
    var pratinjau = medan.querySelector('.foto-bukti-pratinjau');
    var gambar = pratinjau ? pratinjau.querySelector('img') : null;
    function kosongkan() {
      masukan.forEach(function (i) { i.value = ''; });
      if (pratinjau) pratinjau.hidden = true;
      if (gambar) gambar.removeAttribute('src');
    }
    masukan.forEach(function (inp) {
      inp.addEventListener('change', function () {
        var f = inp.files && inp.files[0];
        masukan.forEach(function (lain) { if (lain !== inp) lain.value = ''; });
        if (!f || !/^image\//.test(f.type)) return;
        var pembaca = new FileReader(), img = new Image();
        img.onload = function () {
          var skala = Math.min(1, 1600 / Math.max(img.width, img.height));
          var c = document.createElement('canvas');
          c.width = Math.round(img.width * skala);
          c.height = Math.round(img.height * skala);
          c.getContext('2d').drawImage(img, 0, 0, c.width, c.height);
          if (gambar) gambar.src = c.toDataURL('image/jpeg', 0.7);
          if (pratinjau) pratinjau.hidden = false;
          c.toBlob(function (b) {
            if (b && b.size < f.size) {
              try {
                var dt = new DataTransfer();
                dt.items.add(new File([b], 'foto-bukti.jpg', { type: 'image/jpeg' }));
                inp.files = dt.files;
              } catch (e) {}
            }
          }, 'image/jpeg', 0.85);
        };
        pembaca.onload = function () { img.src = pembaca.result; };
        pembaca.readAsDataURL(f);
      });
    });
    var batal = medan.querySelector('[data-foto-batal]');
    if (batal) batal.addEventListener('click', kosongkan);
  });

  $$('[data-pilih-semua]').forEach(function (induk) {
    var kelompok = induk.getAttribute('data-pilih-semua');
    induk.addEventListener('change', function () {
      $$('input[data-kelompok="' + kelompok + '"]').forEach(function (c) { c.checked = induk.checked; });
      sinkronHitung(kelompok);
    });
    $$('input[data-kelompok="' + kelompok + '"]').forEach(function (c) {
      c.addEventListener('change', function () { sinkronHitung(kelompok); });
    });
  });
  $$('input[data-jam]').forEach(function (el) {
    el.addEventListener('input', function () {
      var d = el.value.replace(/\D/g, '').slice(0, 4);
      el.value = d.length > 2 ? d.slice(0, 2) + '.' + d.slice(2) : d;
    });
    el.addEventListener('blur', function () {
      var d = el.value.replace(/\D/g, '');
      if (d === '') return;
      if (d.length <= 2) d = ('0' + d).slice(-2) + '00';
      else if (d.length === 3) d = '0' + d;
      var j = Math.min(23, parseInt(d.slice(0, 2), 10)), m = Math.min(59, parseInt(d.slice(2), 10));
      el.value = ('0' + j).slice(-2) + '.' + ('0' + m).slice(-2);
    });
  });

  $$('[data-pilih-unit]').forEach(function (induk) {
    induk.addEventListener('change', function () {
      var nilai = induk.checked;
      $$('input[data-unit="' + induk.getAttribute('data-pilih-unit') + '"]').forEach(function (c) {
        c.checked = nilai;
        c.dispatchEvent(new Event('change', { bubbles: true }));
      });
    });
  });
  function sinkronHitung(kelompok) {
    var n = $$('input[data-kelompok="' + kelompok + '"]:checked').length;
    $$('[data-hitung="' + kelompok + '"]').forEach(function (el) { el.textContent = n; });
    $$('[data-butuh="' + kelompok + '"]').forEach(function (el) { el.disabled = n === 0; });
  }
  $$('[data-pilih-semua]').forEach(function (i) { sinkronHitung(i.getAttribute('data-pilih-semua')); });

  $$('[data-hitung-nilai]').forEach(function (form) {
    var hitung = function () {
      var isi = [];
      $$('.skala input:checked', form).forEach(function (r) { isi.push(parseInt(r.value, 10)); });
      var perilaku = isi.length ? isi.reduce(function (a, b) { return a + b; }, 0) / isi.length * 20 : null;
      var capaian = parseFloat(form.getAttribute('data-capaian'));
      var bc = parseFloat(form.getAttribute('data-bobot-capaian'));
      var bp = parseFloat(form.getAttribute('data-bobot-perilaku'));
      var akhir = isNaN(capaian) && perilaku === null ? null : (isNaN(capaian) ? 0 : capaian) * bc + (perilaku === null ? 0 : perilaku) * bp;
      var fmt = function (n) { return n === null ? '—' : n.toFixed(2).replace('.', ','); };
      var elP = $('[data-out="perilaku"]', form);
      var elA = $('[data-out="akhir"]', form);
      var elPred = $('[data-out="predikat"]', form);
      if (elP) elP.textContent = fmt(perilaku);
      if (elA) elA.textContent = fmt(akhir);
      if (elPred) {
        var p = akhir === null ? ['Belum dinilai', 'abu'] : akhir >= 90 ? ['Sangat Baik', 'hijau'] : akhir >= 76 ? ['Baik', 'biru'] : akhir >= 61 ? ['Cukup', 'emas'] : akhir >= 51 ? ['Kurang', 'jingga'] : ['Sangat Kurang', 'merah'];
        elPred.textContent = p[0];
        elPred.className = 'lc lc-' + p[1];
      }
      var ubah = function (nama, teks) { var el = $('[data-out="' + nama + '"]', form); if (el) el.textContent = teks; };
      ubah('perilaku-tabel', perilaku === null ? '-' : fmt(perilaku));
      ubah('kontribusi-perilaku', perilaku === null ? '-' : fmt(perilaku * bp));
      ubah('akhir-tabel', akhir === null ? '-' : fmt(akhir));
      var label = akhir === null ? '' : akhir >= 90 ? 'Sangat Baik' : akhir >= 76 ? 'Baik' : akhir >= 61 ? 'Cukup' : akhir >= 51 ? 'Kurang' : 'Sangat Kurang';
      $$('.tabel-predikat tr[data-predikat]', form).forEach(function (tr) { tr.classList.toggle('aktif', tr.getAttribute('data-predikat') === label); });
      var elJ = $('[data-out="penjelasan"]', form);
      if (elJ) {
        if (akhir === null) {
          elJ.hidden = true;
        } else {
          var c = isNaN(capaian) ? 0 : capaian, pv = perilaku === null ? 0 : perilaku;
          var teks = 'Nilai akhir ' + fmt(akhir) + ' termasuk predikat <b>' + label + '</b>. ';
          teks += isNaN(capaian)
            ? 'Komponen terlemah: capaian pencatatan masih kosong sehingga belum menyumbang poin (maksimal ' + Math.round(bc * 100) + ' poin).'
            : c <= pv
            ? 'Komponen terlemah: capaian pencatatan baru ' + fmt(c) + '% sehingga hanya menyumbang ' + fmt(c * bc) + ' dari maksimal ' + Math.round(bc * 100) + ' poin.'
            : 'Komponen terlemah: nilai perilaku kerja ' + fmt(pv) + ' hanya menyumbang ' + fmt(pv * bp) + ' dari maksimal ' + Math.round(bp * 100) + ' poin.';
          var batas = [[51, 'Kurang'], [61, 'Cukup'], [76, 'Baik'], [90, 'Sangat Baik']];
          for (var i = 0; i < batas.length; i++) {
            if (akhir < batas[i][0]) {
              var kurang = batas[i][0] - akhir;
              teks += ' Untuk naik ke predikat <b>' + batas[i][1] + '</b> dibutuhkan tambahan ' + fmt(kurang) + ' poin'
                + (c + kurang / bc <= 100 ? ', setara kenaikan capaian pencatatan sekitar ' + (kurang / bc).toFixed(1).replace('.', ',') + '%.' : '.');
              break;
            }
          }
          elJ.innerHTML = teks;
          elJ.hidden = false;
        }
      }
    };
    form.addEventListener('change', hitung);
    hitung();
  });

  $$('[data-tampil-sandi]').forEach(function (tombol) {
    var medan = document.getElementById(tombol.getAttribute('data-tampil-sandi'));
    tombol.addEventListener('click', function () {
      var terlihat = medan.type === 'text';
      medan.type = terlihat ? 'password' : 'text';
      tombol.classList.toggle('terlihat', !terlihat);
      medan.focus();
    });
  });

  var bacaUang = function (teks) {
    var v = String(teks || '').replace(/[^0-9,]/g, '').replace(',', '.');
    var n = parseFloat(v);
    return isNaN(n) ? 0 : n;
  };
  var tulisUang = function (n, kosongStrip) {
    if (Math.abs(n) < 0.5) return kosongStrip ? '-' : '';
    var t = Math.round(Math.abs(n)).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    return n < 0 ? '(' + t + ')' : t;
  };
  $$('input[data-uang]').forEach(function (inp) {
    inp.addEventListener('input', function () {
      var mentah = inp.value.replace(/[^0-9]/g, '');
      inp.value = mentah ? tulisUang(parseInt(mentah, 10), false) : '';
    });
  });
  var kunciLipat = 'simkin.lipat.' + (new URLSearchParams(location.search).get('h') || '');
  var simpanLipat = function () {
    try { sessionStorage.setItem(kunciLipat, JSON.stringify($$('tr[data-lipat].buka').map(function (tr) { return tr.getAttribute('data-lipat'); }))); } catch (e) {}
  };
  var aturLipat = function (tr, buka, tanpaSimpan) {
    var g = tr.getAttribute('data-lipat');
    tr.classList.toggle('buka', buka);
    tr.setAttribute('aria-expanded', buka ? 'true' : 'false');
    $$('tr.r-item[data-grup="' + g + '"]').forEach(function (r) { r.classList.toggle('lipat', !buka); });
    if (!tanpaSimpan) simpanLipat();
  };
  if ($('tr[data-lipat]')) {
    var terbuka = [];
    try { terbuka = JSON.parse(sessionStorage.getItem(kunciLipat) || '[]'); } catch (e) {}
    $$('tr[data-lipat]').forEach(function (tr) {
      if (terbuka.indexOf(tr.getAttribute('data-lipat')) !== -1) aturLipat(tr, true, true);
    });
  }
  $$('tr[data-lipat]').forEach(function (tr) {
    tr.addEventListener('click', function () { aturLipat(tr, !tr.classList.contains('buka')); });
    tr.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); aturLipat(tr, !tr.classList.contains('buka')); }
    });
  });
  $$('[data-lipat-semua]').forEach(function (b) {
    b.addEventListener('click', function () {
      var buka = b.getAttribute('data-lipat-semua') === 'buka';
      $$('tr[data-lipat]').forEach(function (tr) { aturLipat(tr, buka); });
    });
  });
  var tulisRp = function (n, tanpaRp) {
    var t = (tanpaRp ? '' : 'Rp ') + (Math.abs(n) < 0.5 ? '0' : tulisUang(Math.abs(n), false));
    return n <= -0.5 ? '(' + t + ')' : t;
  };
  var isiKeluar = function (el, n) {
    var o = el.querySelector('output');
    if (o) o.textContent = tulisRp(n, true);
    else el.textContent = tulisRp(n);
    el.classList.toggle('minus', n <= -0.5);
  };
  $$('form[data-rapb-hitung]').forEach(function (form) {
    var berubah = false, statusUbah = $('[data-status-ubah]', form);
    form.addEventListener('input', function (ev) {
      if (!ev.target.matches('input[data-uang], input[type="date"], input[data-tautan]') || berubah) return;
      berubah = true;
      if (statusUbah) { statusUbah.textContent = 'Ada perubahan yang belum disimpan.'; statusUbah.classList.add('belum-simpan'); }
    });
    form.addEventListener('submit', function () { berubah = false; });
    window.addEventListener('beforeunload', function (ev) {
      if (!berubah) return;
      ev.preventDefault();
      ev.returnValue = '';
    });
    var hitung = function () {
      var grup = {};
      $$('tr.r-item', form).forEach(function (tr) {
        var nilai = [0, 0, 0];
        $$('input[data-kolom]', tr).forEach(function (inp) { nilai[parseInt(inp.getAttribute('data-kolom'), 10)] = bacaUang(inp.value); });
        var real = nilai[1] + nilai[2], sisa = nilai[0] - real;
        var elR = $('[data-keluar="realisasi"]', tr), elS = $('[data-keluar="sisa"]', tr);
        if (elR) isiKeluar(elR, real);
        if (elS) isiKeluar(elS, sisa);
        var g = tr.getAttribute('data-grup');
        grup[g] = grup[g] || { jumlah: 0, realisasi: 0, sisa: 0, gasal: 0, genap: 0 };
        grup[g].jumlah += nilai[0]; grup[g].gasal += nilai[1]; grup[g].genap += nilai[2];
        grup[g].realisasi += real; grup[g].sisa += sisa;
      });
      $$('tr[data-total]', form).forEach(function (tr) {
        var t = { jumlah: 0, realisasi: 0, sisa: 0, gasal: 0, genap: 0 };
        tr.getAttribute('data-total').split(' ').forEach(function (g) {
          if (!grup[g]) return;
          Object.keys(t).forEach(function (k) { t[k] += grup[g][k]; });
        });
        Object.keys(t).forEach(function (k) {
          var el = $('[data-keluar="' + k + '"]', tr);
          if (el) isiKeluar(el, t[k]);
        });
      });
    };
    form.addEventListener('input', hitung);
    hitung();
  });

  $$('form[data-rekap-hitung]').forEach(function (form) {
    var hitung = function () {
      var nilai = {};
      $$('input[data-rekap]', form).forEach(function (inp) { nilai[inp.getAttribute('data-rekap')] = bacaUang(inp.value); });
      $$('[data-rekap-jumlah]', form).forEach(function (td) {
        var s = 0;
        td.getAttribute('data-rekap-jumlah').split(' ').forEach(function (k) { s += nilai[k] || 0; });
        isiKeluar(td, s);
      });
    };
    form.addEventListener('input', hitung);
    hitung();
  });
  $$('form[data-lkps-hitung]').forEach(function (form) {
    var def = {};
    try { def = JSON.parse(form.getAttribute('data-lkps-hitung') || '{}'); } catch (e) {}
    var rerata = function (daftar) {
      var isi = daftar.filter(function (v) { return v !== null; });
      return isi.length ? isi.reduce(function (a, b) { return a + b; }, 0) / isi.length : 0;
    };
    var hitung = function () {
      ['upps', 'ps'].forEach(function (sisi) {
        var nilai = {}, rata = {};
        (def.item || []).forEach(function (b) {
          nilai[b] = [0, 1, 2].map(function (i) {
            var inp = $('input[data-lkps="' + sisi + '"][data-baris="' + b + '"][data-i="' + i + '"]', form);
            return inp && inp.value.trim() !== '' ? bacaUang(inp.value) : null;
          });
          rata[b] = rerata(nilai[b]);
        });
        Object.keys(def.jumlah || {}).forEach(function (j) {
          nilai[j] = [0, 1, 2].map(function (i) {
            return def.jumlah[j].reduce(function (a, k) { return a + ((nilai[k] || [])[i] || 0); }, 0);
          });
          rata[j] = (nilai[j][0] + nilai[j][1] + nilai[j][2]) / 3;
        });
        if ('j2' in rata) rata.j2 = (rata.penelitian || 0) + (rata.pkm || 0);
        if ('tot' in rata) rata.tot = (rata.j1 || 0) + (rata.j2 || 0);
        $$('[data-lkps-keluar="' + sisi + '"]', form).forEach(function (td) {
          var b = td.getAttribute('data-baris'), i = td.getAttribute('data-i');
          isiKeluar(td, i === 'rata' ? (rata[b] || 0) : ((nilai[b] || [])[parseInt(i, 10)] || 0));
        });
      });
    };
    form.addEventListener('input', hitung);
    hitung();
  });

  $$('[data-cetak]').forEach(function (b) {
    b.addEventListener('click', function () { window.print(); });
  });

  $$('[data-auto-kirim]').forEach(function (el) {
    el.addEventListener('change', function () { el.form.submit(); });
  });

  document.querySelectorAll('.sisi-grup-tarik').forEach(function (grupT) {
    var navT = grupT.nextElementSibling, kunci = 'simkin.grup.' + grupT.getAttribute('data-grup');
    if (!navT || !navT.classList.contains('sisi-nav')) return;
    var aturGrup = function (buka) {
      navT.classList.toggle('tutup', !buka);
      grupT.setAttribute('aria-expanded', buka ? 'true' : 'false');
    };
    try {
      var simpan = localStorage.getItem(kunci);
      if (simpan !== null && grupT.getAttribute('data-aktif') !== '1') aturGrup(simpan === '1');
    } catch (e) {}
    var balikGrup = function () {
      var buka = navT.classList.contains('tutup');
      aturGrup(buka);
      try { localStorage.setItem(kunci, buka ? '1' : '0'); } catch (e) {}
    };
    grupT.addEventListener('click', balikGrup);
    grupT.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); balikGrup(); }
    });
  });

  document.querySelectorAll('.sisi-sub-tombol[data-sub]').forEach(function (subK) {
    var isiK = subK.nextElementSibling, kunciK = 'simkin.sub.' + subK.getAttribute('data-sub');
    if (!isiK) return;
    var aturSub = function (buka) {
      isiK.classList.toggle('tutup', !buka);
      subK.setAttribute('aria-expanded', buka ? 'true' : 'false');
    };
    try {
      var simpanK = localStorage.getItem(kunciK);
      if (simpanK !== null && subK.getAttribute('data-aktif') !== '1') aturSub(simpanK === '1');
    } catch (e) {}
    var balikSub = function () {
      var buka = isiK.classList.contains('tutup');
      aturSub(buka);
      try { localStorage.setItem(kunciK, buka ? '1' : '0'); } catch (e) {}
    };
    subK.addEventListener('click', balikSub);
    subK.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter' || ev.key === ' ') { ev.preventDefault(); balikSub(); }
    });
  });

  document.addEventListener('click', function (ev) {
    var tb = ev.target.closest ? ev.target.closest('[data-hadir-semua]') : null;
    if (!tb) return;
    var form = tb.closest('form');
    if (!form) return;
    var ubah = false;
    form.querySelectorAll('select[name^="kehadiran["]').forEach(function (s) {
      if (s.value !== 'hadir') { s.value = 'hadir'; ubah = true; }
    });
    if (ubah) form.dispatchEvent(new Event('change', { bubbles: true }));
  });

  var akar = document.documentElement;
  var temaKini = function () { return akar.classList.contains('tema-kantor') ? 'kantor' : 'hijau'; };
  var grupTema = $('#pilihTema');
  if (grupTema) {
    var tandaiTema = function () {
      grupTema.querySelectorAll('button[data-tema]').forEach(function (b) { b.classList.toggle('aktif', b.getAttribute('data-tema') === temaKini()); });
    };
    tandaiTema();
    grupTema.addEventListener('click', function (ev) {
      var b = ev.target.closest('button[data-tema]');
      if (!b) return;
      ev.stopPropagation();
      var t = b.getAttribute('data-tema');
      akar.classList.toggle('tema-kantor', t === 'kantor');
      try { localStorage.setItem('simkin.tema', t); } catch (e) {}
      tandaiTema();
    });
  }

  var layarPenuh = $('#macLayarPenuh');
  if (layarPenuh) {
    var dalamBingkai = window.top !== window.self;
    if (dalamBingkai) {
      layarPenuh.title = 'Keluar dari layar penuh';
      layarPenuh.addEventListener('click', function (ev) {
        ev.stopPropagation();
        try { window.parent.postMessage({ simkin: 'keluar-penuh' }, location.origin); } catch (e) {}
      });
    } else {
      layarPenuh.title = 'Layar penuh';
      var wadahPenuh = null, framePenuh = null;
      var keluarPenuh = function () {
        if (!wadahPenuh) return;
        var tujuan = location.href;
        try { tujuan = framePenuh.contentWindow.location.href; } catch (e) {}
        var w = wadahPenuh;
        wadahPenuh = null;
        framePenuh = null;
        if (document.fullscreenElement) { try { document.exitFullscreen(); } catch (e) {} }
        if (tujuan && tujuan !== location.href && tujuan.indexOf(location.origin) === 0) { location.href = tujuan; return; }
        w.remove();
        document.documentElement.classList.remove('bingkai-aktif');
      };
      layarPenuh.addEventListener('click', function (ev) {
        ev.stopPropagation();
        if (wadahPenuh) { keluarPenuh(); return; }
        wadahPenuh = document.createElement('div');
        wadahPenuh.className = 'bingkai-penuh';
        framePenuh = document.createElement('iframe');
        framePenuh.title = document.title;
        framePenuh.src = location.href;
        wadahPenuh.appendChild(framePenuh);
        document.body.appendChild(wadahPenuh);
        document.documentElement.classList.add('bingkai-aktif');
        framePenuh.addEventListener('load', function () {
          try {
            var lok = framePenuh.contentWindow.location;
            if (lok.origin === location.origin) history.replaceState(null, '', lok.href);
            document.title = framePenuh.contentDocument.title;
          } catch (e) {}
        });
        if (wadahPenuh.requestFullscreen) {
          try { var p = wadahPenuh.requestFullscreen(); if (p && p.catch) p.catch(function () {}); } catch (e) {}
        }
      });
      window.addEventListener('message', function (ev) {
        if (ev.origin === location.origin && ev.data && ev.data.simkin === 'keluar-penuh') keluarPenuh();
      });
      document.addEventListener('fullscreenchange', function () {
        if (!document.fullscreenElement && wadahPenuh) keluarPenuh();
      });
    }
  }
  var navMundur = $('#navMundur'), navMaju = $('#navMaju');
  if (navMundur) navMundur.addEventListener('click', function () { history.back(); });
  if (navMaju) navMaju.addEventListener('click', function () { history.forward(); });

  var cariMenu = $('#cariMenu');
  if (cariMenu) {
    var navSisi = Array.prototype.slice.call(document.querySelectorAll('.sisi .sisi-nav'));
    var awalTutup = navSisi.map(function (n) { return n.classList.contains('tutup'); });
    cariMenu.addEventListener('input', function () {
      var q = cariMenu.value.trim().toLowerCase();
      navSisi.forEach(function (nav, i) {
        var ada = 0;
        nav.querySelectorAll('a').forEach(function (a) {
          var cocok = !q || (a.getAttribute('title') || a.textContent).toLowerCase().indexOf(q) !== -1;
          a.style.display = cocok ? '' : 'none';
          if (cocok) ada++;
        });
        nav.classList.toggle('tutup', q ? false : awalTutup[i]);
        nav.style.display = q && !ada ? 'none' : '';
        var judul = nav.previousElementSibling;
        if (judul && judul.classList.contains('sisi-grup')) judul.style.display = q && !ada ? 'none' : '';
      });
    });
    cariMenu.addEventListener('keydown', function (ev) {
      if (ev.key === 'Enter') {
        var pertama = document.querySelector('.sisi .sisi-nav a:not([style*="none"])');
        if (pertama) { ev.preventDefault(); location.href = pertama.href; }
      }
      if (ev.key === 'Escape') { cariMenu.value = ''; cariMenu.dispatchEvent(new Event('input')); }
    });
  }
  var nadaPengumuman = function () {
    try {
      var C = window.AudioContext || window.webkitAudioContext;
      if (!C) return;
      var ctx = new C();
      if (ctx.resume) ctx.resume();
      var utama = ctx.createGain();
      utama.gain.value = 0.9;
      utama.connect(ctx.destination);
      [[784, 0], [659.3, 0.42], [523.3, 0.84]].forEach(function (n) {
        [[1, 'sine', 0.26], [2, 'sine', 0.06], [3, 'triangle', 0.025]].forEach(function (h) {
          var o = ctx.createOscillator(), g = ctx.createGain(), t0 = ctx.currentTime + n[1];
          o.type = h[1];
          o.frequency.value = n[0] * h[0];
          g.gain.setValueAtTime(0.0001, t0);
          g.gain.exponentialRampToValueAtTime(h[2], t0 + 0.015);
          g.gain.exponentialRampToValueAtTime(0.0001, t0 + 1.1);
          o.connect(g);
          g.connect(utama);
          o.start(t0);
          o.stop(t0 + 1.15);
        });
      });
      setTimeout(function () { try { ctx.close(); } catch (e) {} }, 2400);
    } catch (e) {}
  };

  var formPengumuman = document.querySelector('#tiraiPengumuman form');
  if (formPengumuman) {
    formPengumuman.addEventListener('submit', function (ev) {
      if (formPengumuman.dataset.terkirim === '1') return;
      if (formPengumuman.checkValidity && !formPengumuman.checkValidity()) return;
      ev.preventDefault();
      formPengumuman.dataset.terkirim = '1';
      var tombol = formPengumuman.querySelector('button[type="submit"]');
      if (tombol) tombol.disabled = true;
      nadaPengumuman();
      setTimeout(function () { HTMLFormElement.prototype.submit.call(formPengumuman); }, 1300);
    });
  }

  var lonceng = $('#lonceng');
  if (lonceng) {
    var tombolL = $('#loncengTombol'), lencanaL = $('#loncengLencana'), daftarL = $('#loncengDaftar'), bacaL = $('#loncengBaca');
    var urlNotif = lonceng.getAttribute('data-url'), csrfNotif = lonceng.getAttribute('data-csrf');
    var KUNCI_UNDANGAN = 'simkin.undangan.diumumkan';
    var bunyiTertunda = false, adaInteraksi = false;

    var ambilDiumumkan = function () {
      try { return JSON.parse(localStorage.getItem(KUNCI_UNDANGAN) || '[]'); } catch (e) { return []; }
    };
    var simpanDiumumkan = function (a) {
      try { localStorage.setItem(KUNCI_UNDANGAN, JSON.stringify(a.slice(-200))); } catch (e) {}
    };

    var mainkanBunyi = function () {
      var aktif = adaInteraksi || (navigator.userActivation && navigator.userActivation.hasBeenActive);
      if (!aktif) return false;
      var diamKini = ambilDiam();
      if (idBelumDibaca.some(function (id) { return String(id).indexOf('pg-') === 0 && diamKini.indexOf(id) === -1; })) {
        nadaPengumuman();
        return true;
      }
      try {
        var C = window.AudioContext || window.webkitAudioContext;
        if (!C) return true;
        var ctx = new C();
        if (ctx.resume) ctx.resume();
        [[880, 0], [1175, 0.17], [1480, 0.34]].forEach(function (n) {
          var o = ctx.createOscillator(), g = ctx.createGain(), t0 = ctx.currentTime + n[1];
          o.type = 'sine';
          o.frequency.value = n[0];
          g.gain.setValueAtTime(0.0001, t0);
          g.gain.exponentialRampToValueAtTime(0.22, t0 + 0.02);
          g.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.4);
          o.connect(g);
          g.connect(ctx.destination);
          o.start(t0);
          o.stop(t0 + 0.45);
        });
        setTimeout(function () { try { ctx.close(); } catch (e) {} }, 1500);
      } catch (e) {}
      return true;
    };
    var bunyikan = function () { if (!mainkanBunyi()) bunyiTertunda = true; };
    var KUNCI_DIAM = 'simkin.lonceng.didiamkan';
    var idBelumDibaca = [], timerDering = null;
    var ambilDiam = function () {
      try { return JSON.parse(localStorage.getItem(KUNCI_DIAM) || '[]'); } catch (e) { return []; }
    };
    var simpanDiam = function (a) {
      try { localStorage.setItem(KUNCI_DIAM, JSON.stringify(a.slice(-200))); } catch (e) {}
    };
    var aturDering = function () {
      var diam = ambilDiam();
      var harusBunyi = idBelumDibaca.some(function (id) { return diam.indexOf(id) === -1; });
      tombolL.classList.toggle('berdering', harusBunyi);
      tombolL.setAttribute('title', harusBunyi ? 'Ada notifikasi baru. Klik untuk melihat dan menghentikan bunyi' : 'Notifikasi');
      if (harusBunyi && !timerDering) {
        bunyikan();
        timerDering = setInterval(function () { if (!document.hidden) mainkanBunyi(); }, 6000);
      } else if (!harusBunyi && timerDering) {
        clearInterval(timerDering);
        timerDering = null;
        bunyiTertunda = false;
      }
    };
    var diamkan = function () {
      if (!idBelumDibaca.length) return;
      simpanDiam(ambilDiam().concat(idBelumDibaca.filter(function (id) { return ambilDiam().indexOf(id) === -1; })));
      aturDering();
    };
    ['pointerdown', 'keydown'].forEach(function (ev) {
      document.addEventListener(ev, function (e) {
        adaInteraksi = true;
        if (bunyiTertunda && !(e.target && tombolL.contains(e.target))) { bunyiTertunda = false; mainkanBunyi(); }
      }, true);
    });

    var tampilToast = function (daftar) {
      var lama = document.querySelector('.toast-undangan');
      if (lama) lama.remove();
      var kotak = document.createElement('div');
      kotak.className = 'toast-undangan';
      kotak.setAttribute('role', 'status');
      var judul = document.createElement('b');
      var semuaRapat = daftar.every(function (d) { return !d.jenis; });
      var semuaUmum = daftar.every(function (d) { return d.jenis === 'pengumuman'; });
      judul.textContent = semuaUmum ? (daftar.length > 1 ? daftar.length + ' pengumuman baru' : 'Pengumuman baru') : semuaRapat ?(daftar.length > 1 ? daftar.length + ' undangan rapat baru' : 'Undangan rapat baru') : (daftar.length > 1 ? daftar.length + ' notifikasi baru' : 'Notifikasi baru');
      var isi = document.createElement('span');
      isi.textContent = daftar[0].judul + ' · ' + daftar[0].waktu + ' ';
      var tautan = document.createElement('a');
      tautan.href = daftar[0].url;
      tautan.textContent = 'Lihat';
      kotak.appendChild(judul);
      kotak.appendChild(isi);
      kotak.appendChild(tautan);
      document.body.appendChild(kotak);
      setTimeout(function () { if (kotak.parentNode) kotak.remove(); }, 9000);
    };

    var gambarDaftar = function (data) {
      var baru = parseInt(data.baru, 10) || 0;
      lencanaL.textContent = baru > 99 ? '99+' : String(baru);
      lencanaL.hidden = baru === 0;
      bacaL.hidden = baru === 0;
      idBelumDibaca = baru === 0 ? [] : (data.daftar || []).filter(function (d) { return d.baru; }).map(function (d) { return d.id; });
      aturDering();
      daftarL.textContent = '';
      if (!data.daftar || !data.daftar.length) {
        var kosong = document.createElement('p');
        kosong.className = 'lt-kosong';
        kosong.textContent = 'Belum ada notifikasi.';
        daftarL.appendChild(kosong);
        return;
      }
      data.daftar.forEach(function (d) {
        var a = document.createElement('a');
        a.className = 'lt-item' + (d.baru ? ' baru' : '') + (d.jenis === 'pengumuman' ? ' lt-umum' : '');
        a.href = d.url;
        var j = document.createElement('span');
        j.className = 'lt-judul';
        j.textContent = d.judul;
        var k = document.createElement('span');
        k.className = 'lt-ket';
        k.textContent = d.waktu + (d.tempat ? ' · ' + d.tempat : '');
        a.appendChild(j);
        a.appendChild(k);
        daftarL.appendChild(a);
      });
    };

    var prosesData = function (data) {
      gambarDaftar(data);
      var diumumkan = ambilDiumumkan();
      var belum = (data.daftar || []).filter(function (d) { return d.baru && diumumkan.indexOf(d.id) === -1; });
      if (belum.length) {
        belum.forEach(function (d) { diumumkan.push(d.id); });
        simpanDiumumkan(diumumkan);
        tampilToast(belum);
      }
    };

    var muatNotif = function () {
      if (!window.fetch) return;
      fetch(urlNotif, { credentials: 'same-origin', cache: 'no-store', headers: { 'Accept': 'application/json' } })
        .then(function (r) { return r.json(); })
        .then(prosesData)
        .catch(function () {});
    };

    tombolL.addEventListener('click', function (ev) {
      ev.stopPropagation();
      diamkan();
      var buka = lonceng.classList.toggle('buka');
      tombolL.setAttribute('aria-expanded', buka ? 'true' : 'false');
    });
    document.addEventListener('click', function (ev) {
      if (!lonceng.contains(ev.target)) {
        lonceng.classList.remove('buka');
        tombolL.setAttribute('aria-expanded', 'false');
      }
    });
    bacaL.addEventListener('click', function () {
      fetch(urlNotif, {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-CSRF-Token': csrfNotif },
        body: '_csrf=' + encodeURIComponent(csrfNotif)
      }).then(function (r) { return r.json(); }).then(gambarDaftar).catch(function () {});
    });

    muatNotif();
    setInterval(muatNotif, 30000);
    document.addEventListener('visibilitychange', function () { if (!document.hidden) muatNotif(); });
  }

  var jam = $('#jamDigital');
  if (jam) {
    var HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu'];
    var BULAN = ['Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni', 'Juli',
                 'Agustus', 'September', 'Oktober', 'November', 'Desember'];
    var dua = function (n) { return (n < 10 ? '0' : '') + n; };
    var bagian = {
      hari: jam.querySelector('[data-jd="hari"]'),
      jam: jam.querySelector('[data-jd="jam"]'),
      detik: jam.querySelector('[data-jd="detik"]'),
      tanggal: jam.querySelector('[data-jd="tanggal"]')
    };
    var waktuJakarta = function () {
      var kini = new Date();
      return new Date(kini.getTime() + (kini.getTimezoneOffset() + 420) * 60000);
    };
    var perbarui = function () {
      var w = waktuJakarta();
      bagian.hari.textContent = HARI[w.getDay()];
      bagian.jam.textContent = dua(w.getHours()) + ':' + dua(w.getMinutes());
      bagian.detik.textContent = dua(w.getSeconds());
      bagian.tanggal.textContent = w.getDate() + ' ' + BULAN[w.getMonth()] + ' ' + w.getFullYear();
    };
    perbarui();
    setInterval(perbarui, 1000);
  }
})();

(function () {
  var KUNCI = 'simkin.posisi.gulir';
  var halamanDari = function (alamat) {
    try { return new URL(alamat, location.href).searchParams.get('h') || 'dashboard'; } catch (e) { return ''; }
  };
  var catat = function (form) {
    var tujuan = (form && form.getAttribute('action')) || location.href;
    var h = halamanDari(tujuan);
    if (form && form.method && form.method.toLowerCase() === 'get' && form.elements.h) h = form.elements.h.value || h;
    try { sessionStorage.setItem(KUNCI, JSON.stringify({ h: h, y: window.scrollY || window.pageYOffset || 0, t: Date.now() })); } catch (e) {}
  };
  document.addEventListener('submit', function (ev) { if (!ev.defaultPrevented) catat(ev.target); });
  var kirimAsli = HTMLFormElement.prototype.submit;
  HTMLFormElement.prototype.submit = function () { catat(this); return kirimAsli.call(this); };

  var simpan = null;
  try { simpan = JSON.parse(sessionStorage.getItem(KUNCI) || 'null'); sessionStorage.removeItem(KUNCI); } catch (e) {}
  if (!simpan || simpan.h !== halamanDari(location.href) || Date.now() - simpan.t > 30000 || simpan.y < 40) return;
  if ('scrollRestoration' in history) history.scrollRestoration = 'manual';

  var tampilPesan = function () {
    var pesan = Array.prototype.slice.call(document.querySelectorAll('.notif [data-tutup-notif]')).map(function (b) { return b.closest('.notif'); });
    if (!pesan.length) return;
    var wadah = document.createElement('div');
    wadah.className = 'toast-gulir';
    pesan.forEach(function (n) {
      var salin = n.cloneNode(true);
      var t = salin.querySelector('[data-tutup-notif]');
      if (t) t.addEventListener('click', function () { salin.remove(); if (!wadah.children.length) wadah.remove(); });
      wadah.appendChild(salin);
      n.style.display = 'none';
    });
    document.body.appendChild(wadah);
    setTimeout(function () { wadah.classList.add('hilang'); setTimeout(function () { wadah.remove(); }, 400); }, 5000);
  };
  tampilPesan();

  var pulihkan = function () { window.scrollTo({ top: simpan.y, left: 0, behavior: 'instant' }); };
  pulihkan();
  document.addEventListener('DOMContentLoaded', pulihkan);
  window.addEventListener('load', function () { pulihkan(); setTimeout(pulihkan, 60); });
})();

(function () {
  var tandai = function () {
    document.querySelectorAll('table').forEach(function (tb) {
      var kepala = tb.tHead ? tb.tHead.rows[0] : null;
      if (!kepala) { return; }
      var idx = -1;
      Array.prototype.forEach.call(kepala.cells, function (th, i) {
        if (idx < 0 && th.colSpan === 1 && /^no\.?$/i.test(th.textContent.trim())) { idx = i; }
      });
      if (idx < 0) { return; }
      kepala.cells[idx].classList.add('kol-nomor');
      Array.prototype.forEach.call(tb.tBodies, function (bd) {
        Array.prototype.forEach.call(bd.rows, function (tr) {
          if (tr.cells.length === kepala.cells.length && tr.cells[idx]) { tr.cells[idx].classList.add('kol-nomor'); }
        });
      });
    });
  };
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', tandai); } else { tandai(); }
})();