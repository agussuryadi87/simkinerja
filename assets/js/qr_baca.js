(function (global) {
  'use strict';

  var EXP = new Array(512), LOG = new Array(256);
  (function () {
    var x = 1;
    for (var i = 0; i < 255; i++) {
      EXP[i] = x;
      LOG[x] = i;
      x <<= 1;
      if (x & 0x100) x ^= 0x11D;
    }
    for (i = 255; i < 512; i++) EXP[i] = EXP[i - 255];
  })();

  function gKali(a, b) { return a && b ? EXP[LOG[a] + LOG[b]] : 0; }
  function gBagi(a, b) { return a ? EXP[(LOG[a] + 255 - LOG[b]) % 255] : 0; }
  function gPangkat(x, n) { return EXP[(((LOG[x] * n) % 255) + 255) % 255]; }
  function gBalik(x) { return EXP[255 - LOG[x]]; }
  function pSkala(p, x) { return p.map(function (c) { return gKali(c, x); }); }
  function pTambah(p, q) {
    var n = Math.max(p.length, q.length), r = new Array(n).fill(0), i;
    for (i = 0; i < p.length; i++) r[i + n - p.length] = p[i];
    for (i = 0; i < q.length; i++) r[i + n - q.length] ^= q[i];
    return r;
  }
  function pKali(p, q) {
    var r = new Array(p.length + q.length - 1).fill(0);
    for (var j = 0; j < q.length; j++) for (var i = 0; i < p.length; i++) r[i + j] ^= gKali(p[i], q[j]);
    return r;
  }
  function pNilai(p, x) {
    var y = p[0];
    for (var i = 1; i < p.length; i++) y = gKali(y, x) ^ p[i];
    return y;
  }
  function pBagiSisa(a, b) {
    var out = a.slice();
    for (var i = 0; i < a.length - (b.length - 1); i++) {
      var k = out[i];
      if (k !== 0) for (var j = 1; j < b.length; j++) if (b[j] !== 0) out[i + j] ^= gKali(b[j], k);
    }
    return out.slice(out.length - (b.length - 1));
  }

  function rsPerbaiki(pesan, nsym) {
    var sindrom = [0], adaGalat = false, i, j;
    for (i = 0; i < nsym; i++) {
      var s = pNilai(pesan, gPangkat(2, i));
      sindrom.push(s);
      if (s) adaGalat = true;
    }
    if (!adaGalat) return pesan;
    var lok = [1], lama = [1];
    for (i = 0; i < nsym; i++) {
      var K = i + 1, delta = sindrom[K];
      for (j = 1; j < lok.length; j++) delta ^= gKali(lok[lok.length - 1 - j], sindrom[K - j]);
      lama = lama.concat([0]);
      if (delta !== 0) {
        if (lama.length > lok.length) {
          var baru = pSkala(lama, delta);
          lama = pSkala(lok, gBalik(delta));
          lok = baru;
        }
        lok = pTambah(lok, pSkala(lama, delta));
      }
    }
    while (lok.length && lok[0] === 0) lok.shift();
    var jumlah = lok.length - 1;
    if (jumlah * 2 > nsym) return null;
    var balikLok = lok.slice().reverse(), posisi = [], n = pesan.length;
    for (i = 0; i < n; i++) if (pNilai(balikLok, gPangkat(2, i)) === 0) posisi.push(n - 1 - i);
    if (posisi.length !== jumlah) return null;
    var koef = posisi.map(function (p) { return n - 1 - p; });
    var eLok = [1];
    koef.forEach(function (c) { eLok = pKali(eLok, pTambah([1], [gPangkat(2, c), 0])); });
    var sBalik = sindrom.slice().reverse();
    var pembagi = [1].concat(new Array(eLok.length).fill(0));
    var eval_ = pBagiSisa(pKali(sBalik, eLok), pembagi);
    var X = koef.map(function (c) { return gPangkat(2, -(255 - c)); });
    var E = new Array(n).fill(0);
    for (i = 0; i < X.length; i++) {
      var xiInv = gBalik(X[i]), turunan = 1;
      for (j = 0; j < X.length; j++) if (j !== i) turunan = gKali(turunan, 1 ^ gKali(xiInv, X[j]));
      if (turunan === 0) return null;
      var y = gKali(X[i], pNilai(eval_, xiInv));
      E[posisi[i]] = gBagi(y, turunan);
    }
    var hasil = pTambah(pesan, E);
    for (i = 0; i < nsym; i++) if (pNilai(hasil, gPangkat(2, i)) !== 0) return null;
    return hasil;
  }

  var BLOK_M = {
    1: [10, [[1, 16]]], 2: [16, [[1, 28]]], 3: [26, [[1, 44]]], 4: [18, [[2, 32]]], 5: [24, [[2, 43]]],
    6: [16, [[4, 27]]], 7: [18, [[4, 31]]], 8: [22, [[2, 38], [2, 39]]], 9: [22, [[3, 36], [2, 37]]], 10: [26, [[4, 43], [1, 44]]]
  };
  var JAJAR = {
    1: [], 2: [6, 18], 3: [6, 22], 4: [6, 26], 5: [6, 30], 6: [6, 34], 7: [6, 22, 38], 8: [6, 24, 42], 9: [6, 26, 46], 10: [6, 28, 50]
  };
  var FORMAT = [];
  (function () {
    for (var d = 0; d < 32; d++) {
      var s = d;
      for (var i = 0; i < 10; i++) s = (s << 1) ^ ((s >> 9) * 0x537);
      FORMAT.push([((d << 10) | s) ^ 0x5412, d]);
    }
  })();
  function bitHitung(x) { var n = 0; while (x) { n += x & 1; x >>= 1; } return n; }

  function balikMask(mask, x, y) {
    switch (mask) {
      case 0: return (x + y) % 2 === 0;
      case 1: return y % 2 === 0;
      case 2: return x % 3 === 0;
      case 3: return (x + y) % 3 === 0;
      case 4: return (Math.floor(x / 3) + Math.floor(y / 2)) % 2 === 0;
      case 5: return (x * y) % 2 + (x * y) % 3 === 0;
      case 6: return ((x * y) % 2 + (x * y) % 3) % 2 === 0;
      default: return (((x + y) % 2) + (x * y) % 3) % 2 === 0;
    }
  }

  function dekodeMatriks(m) {
    var n = m.length, v = (n - 17) / 4;
    if (!BLOK_M[v]) return null;
    var f1 = 0, f2 = 0, i, j;
    var p1 = [[0, 8], [1, 8], [2, 8], [3, 8], [4, 8], [5, 8], [7, 8], [8, 8], [8, 7], [8, 5], [8, 4], [8, 3], [8, 2], [8, 1], [8, 0]];
    for (i = 0; i < 15; i++) f1 |= m[p1[i][0]][p1[i][1]] << i;
    for (i = 0; i < 8; i++) f2 |= m[8][n - 1 - i] << i;
    for (i = 8; i < 15; i++) f2 |= m[n - 15 + i][8] << i;
    var terbaik = null, jarak = 99;
    FORMAT.forEach(function (f) {
      var d = Math.min(bitHitung(f[0] ^ f1), bitHitung(f[0] ^ f2));
      if (d < jarak) { jarak = d; terbaik = f[1]; }
    });
    if (terbaik === null || jarak > 3) return null;
    if ((terbaik >> 3) !== 0) return null;
    var mask = terbaik & 7;
    var fungsi = [];
    for (i = 0; i < n; i++) fungsi.push(new Uint8Array(n));
    function tandai(r0, c0, h, w) {
      for (var r = r0; r < r0 + h; r++) for (var c = c0; c < c0 + w; c++) if (r >= 0 && c >= 0 && r < n && c < n) fungsi[r][c] = 1;
    }
    tandai(0, 0, 9, 9); tandai(0, n - 8, 9, 8); tandai(n - 8, 0, 8, 9); tandai(6, 0, 1, n); tandai(0, 6, n, 1);
    var al = JAJAR[v];
    for (i = 0; i < al.length; i++) for (j = 0; j < al.length; j++) {
      var r = al[i], c = al[j];
      if ((r < 9 && c < 9) || (r < 9 && c > n - 9) || (r > n - 9 && c < 9)) continue;
      tandai(r - 2, c - 2, 5, 5);
    }
    if (v >= 7) { tandai(0, n - 11, 6, 3); tandai(n - 11, 0, 3, 6); }
    var bit = [], naik = true;
    for (var kol = n - 1; kol > 0; kol -= 2) {
      if (kol === 6) kol--;
      for (var t = 0; t < n; t++) {
        var y = naik ? n - 1 - t : t;
        for (var k = 0; k < 2; k++) {
          var x = kol - k;
          if (fungsi[y][x]) continue;
          var b = m[y][x];
          if (balikMask(mask, x, y)) b ^= 1;
          bit.push(b);
        }
      }
      naik = !naik;
    }
    var kata = [];
    for (i = 0; i + 8 <= bit.length; i += 8) {
      var w = 0;
      for (j = 0; j < 8; j++) w = (w << 1) | bit[i + j];
      kata.push(w);
    }
    var ecc = BLOK_M[v][0], bloks = [];
    BLOK_M[v][1].forEach(function (g) { for (var q = 0; q < g[0]; q++) bloks.push(g[1]); });
    var data = bloks.map(function () { return []; }), ec = bloks.map(function () { return []; }), p = 0;
    var maks = Math.max.apply(null, bloks);
    for (i = 0; i < maks; i++) bloks.forEach(function (len, bi) { if (i < len) data[bi].push(kata[p++]); });
    for (i = 0; i < ecc; i++) bloks.forEach(function (len, bi) { ec[bi].push(kata[p++]); });
    var semua = [];
    for (i = 0; i < bloks.length; i++) {
      var benar = rsPerbaiki(data[i].concat(ec[i]), ecc);
      if (!benar) return null;
      semua = semua.concat(benar.slice(0, bloks[i]));
    }
    var aliran = '';
    semua.forEach(function (cw) { aliran += ('00000000' + cw.toString(2)).slice(-8); });
    var mode = parseInt(aliran.substr(0, 4), 2);
    if (mode !== 4) return null;
    var lebarJml = v < 10 ? 8 : 16, jml = parseInt(aliran.substr(4, lebarJml), 2), bita = [];
    if (4 + lebarJml + jml * 8 > aliran.length) return null;
    for (i = 0; i < jml; i++) bita.push(parseInt(aliran.substr(4 + lebarJml + i * 8, 8), 2));
    try {
      return decodeURIComponent(bita.map(function (b) { return '%' + ('0' + b.toString(16)).slice(-2); }).join(''));
    } catch (e) {
      return String.fromCharCode.apply(null, bita);
    }
  }

  function binerkan(lum, w, h) {
    var B = 8, bw = Math.ceil(w / B), bh = Math.ceil(h / B), ambang = new Float32Array(bw * bh), bx, by, x, y;
    for (by = 0; by < bh; by++) for (bx = 0; bx < bw; bx++) {
      var min = 255, max = 0, jum = 0, cnt = 0;
      for (y = by * B; y < Math.min(h, by * B + B); y++) for (x = bx * B; x < Math.min(w, bx * B + B); x++) {
        var l = lum[y * w + x];
        jum += l; cnt++;
        if (l < min) min = l;
        if (l > max) max = l;
      }
      var rata = jum / cnt;
      if (max - min <= 24) {
        rata = min / 2;
        if (by > 0 && bx > 0) {
          var tetangga = (ambang[(by - 1) * bw + bx] + 2 * ambang[by * bw + bx - 1] + ambang[(by - 1) * bw + bx - 1]) / 4;
          if (min < tetangga) rata = tetangga;
        }
      }
      ambang[by * bw + bx] = rata;
    }
    var bit = new Uint8Array(w * h);
    for (by = 0; by < bh; by++) for (bx = 0; bx < bw; bx++) {
      var tot = 0, c = 0;
      for (var dy = -2; dy <= 2; dy++) for (var dx = -2; dx <= 2; dx++) {
        var yy = by + dy, xx = bx + dx;
        if (yy >= 0 && xx >= 0 && yy < bh && xx < bw) { tot += ambang[yy * bw + xx]; c++; }
      }
      var t = tot / c;
      for (y = by * B; y < Math.min(h, by * B + B); y++) for (x = bx * B; x < Math.min(w, bx * B + B); x++) bit[y * w + x] = lum[y * w + x] <= t ? 1 : 0;
    }
    return bit;
  }

  function rasioPola(r, toleransi) {
    var total = r[0] + r[1] + r[2] + r[3] + r[4];
    if (total < 7) return 0;
    var m = total / 7, d = m * toleransi;
    return Math.abs(r[0] - m) < d && Math.abs(r[1] - m) < d && Math.abs(r[2] - 3 * m) < 3 * d && Math.abs(r[3] - m) < d && Math.abs(r[4] - m) < d ? total : 0;
  }

  function cekSilang(bit, w, h, cx, cy, dx, dy, batas) {
    function di(x, y) { return x >= 0 && y >= 0 && x < w && y < h ? bit[y * w + x] : -1; }
    var r = [0, 0, 0, 0, 0], x = cx, y = cy;
    while (di(x, y) === 1 && r[2] <= batas) { r[2]++; x -= dx; y -= dy; }
    if (di(x, y) === -1) return null;
    while (di(x, y) === 0 && r[1] <= batas) { r[1]++; x -= dx; y -= dy; }
    if (di(x, y) === -1 || r[1] > batas) return null;
    while (di(x, y) === 1 && r[0] <= batas) { r[0]++; x -= dx; y -= dy; }
    if (r[0] > batas) return null;
    x = cx + dx; y = cy + dy;
    while (di(x, y) === 1 && r[2] <= batas * 3) { r[2]++; x += dx; y += dy; }
    if (di(x, y) === -1) return null;
    while (di(x, y) === 0 && r[3] <= batas) { r[3]++; x += dx; y += dy; }
    if (di(x, y) === -1 || r[3] > batas) return null;
    while (di(x, y) === 1 && r[4] <= batas) { r[4]++; x += dx; y += dy; }
    if (r[4] > batas) return null;
    var total = rasioPola(r, 0.7);
    if (!total) return null;
    var akhirTengah = (dx ? x : y) - r[4] - r[3];
    return { pusat: akhirTengah - r[2] / 2, total: total };
  }

  function cariPenanda(bit, w, h) {
    var kandidat = [];
    for (var y = 0; y < h; y += 1) {
      var runs = [], warna = bit[y * w], awal = 0;
      for (var x = 1; x <= w; x++) {
        var b = x < w ? bit[y * w + x] : -1;
        if (b !== warna) { runs.push([warna, x - awal, awal]); warna = b; awal = x; }
      }
      for (var i = 0; i + 4 < runs.length; i++) {
        if (runs[i][0] !== 1) continue;
        var r = [runs[i][1], runs[i + 1][1], runs[i + 2][1], runs[i + 3][1], runs[i + 4][1]];
        var total = rasioPola(r, 0.5);
        if (!total) continue;
        var cx = Math.round(runs[i + 2][2] + runs[i + 2][1] / 2);
        var v = cekSilang(bit, w, h, cx, y, 0, 1, Math.ceil(total / 2));
        if (!v) continue;
        var cyy = Math.round(v.pusat);
        var hz = cekSilang(bit, w, h, cx, cyy, 1, 0, Math.ceil(total / 2));
        if (!hz) continue;
        var px = hz.pusat, py = v.pusat, ukuran = (hz.total + v.total + total) / 21;
        var gabung = false;
        for (var k = 0; k < kandidat.length; k++) {
          var c = kandidat[k];
          if (Math.abs(c.x - px) <= ukuran * 2 && Math.abs(c.y - py) <= ukuran * 2 && Math.abs(c.ukuran - ukuran) <= Math.max(c.ukuran, ukuran) * 0.6) {
            c.x = (c.x * c.n + px) / (c.n + 1); c.y = (c.y * c.n + py) / (c.n + 1); c.ukuran = (c.ukuran * c.n + ukuran) / (c.n + 1); c.n++;
            gabung = true;
            break;
          }
        }
        if (!gabung) kandidat.push({ x: px, y: py, ukuran: ukuran, n: 1 });
      }
    }
    return kandidat;
  }

  function jarak(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }

  function pilihTiga(kandidat) {
    var daftar = kandidat.filter(function (c) { return c.n >= 2; });
    if (daftar.length < 3) daftar = kandidat.slice();
    daftar.sort(function (a, b) { return b.n - a.n; });
    daftar = daftar.slice(0, 12);
    var hasil = [];
    for (var i = 0; i < daftar.length; i++) for (var j = i + 1; j < daftar.length; j++) for (var k = j + 1; k < daftar.length; k++) {
      var t = [daftar[i], daftar[j], daftar[k]];
      var u = t.map(function (c) { return c.ukuran; }), uRata = (u[0] + u[1] + u[2]) / 3;
      if (Math.max.apply(null, u) > uRata * 1.6 || Math.min.apply(null, u) < uRata / 1.6) continue;
      var d01 = jarak(t[0], t[1]), d02 = jarak(t[0], t[2]), d12 = jarak(t[1], t[2]), sudut, a, b, hyp;
      if (d12 >= d01 && d12 >= d02) { sudut = t[0]; a = t[1]; b = t[2]; hyp = d12; }
      else if (d02 >= d01) { sudut = t[1]; a = t[0]; b = t[2]; hyp = d02; }
      else { sudut = t[2]; a = t[0]; b = t[1]; hyp = d01; }
      var la = jarak(sudut, a), lb = jarak(sudut, b);
      if (la < uRata * 10 || lb < uRata * 10) continue;
      var skor = Math.abs(la - lb) / Math.max(la, lb) + Math.abs(hyp * hyp - la * la - lb * lb) / (hyp * hyp) + Math.abs(u[0] - u[1]) / uRata + Math.abs(u[1] - u[2]) / uRata;
      var silang = (a.x - sudut.x) * (b.y - sudut.y) - (a.y - sudut.y) * (b.x - sudut.x);
      hasil.push({ skor: skor, kiriAtas: sudut, kananAtas: silang > 0 ? a : b, kiriBawah: silang > 0 ? b : a, ukuran: uRata });
    }
    hasil.sort(function (p, q) { return p.skor - q.skor; });
    return hasil.slice(0, 3);
  }

  function T(a11, a21, a31, a12, a22, a32, a13, a23, a33) {
    return { a11: a11, a21: a21, a31: a31, a12: a12, a22: a22, a32: a32, a13: a13, a23: a23, a33: a33 };
  }
  function persegiKeSegiempat(x0, y0, x1, y1, x2, y2, x3, y3) {
    var dx3 = x0 - x1 + x2 - x3, dy3 = y0 - y1 + y2 - y3;
    if (dx3 === 0 && dy3 === 0) return T(x1 - x0, x2 - x1, x0, y1 - y0, y2 - y1, y0, 0, 0, 1);
    var dx1 = x1 - x2, dx2 = x3 - x2, dy1 = y1 - y2, dy2 = y3 - y2, den = dx1 * dy2 - dx2 * dy1;
    var a13 = (dx3 * dy2 - dx2 * dy3) / den, a23 = (dx1 * dy3 - dx3 * dy1) / den;
    return T(x1 - x0 + a13 * x1, x3 - x0 + a23 * x3, x0, y1 - y0 + a13 * y1, y3 - y0 + a23 * y3, y0, a13, a23, 1);
  }
  function adjoin(t) {
    return T(t.a22 * t.a33 - t.a23 * t.a32, t.a23 * t.a31 - t.a21 * t.a33, t.a21 * t.a32 - t.a22 * t.a31,
      t.a13 * t.a32 - t.a12 * t.a33, t.a11 * t.a33 - t.a13 * t.a31, t.a12 * t.a31 - t.a11 * t.a32,
      t.a12 * t.a23 - t.a13 * t.a22, t.a13 * t.a21 - t.a11 * t.a23, t.a11 * t.a22 - t.a12 * t.a21);
  }
  function kali(t, o) {
    return T(t.a11 * o.a11 + t.a21 * o.a12 + t.a31 * o.a13, t.a11 * o.a21 + t.a21 * o.a22 + t.a31 * o.a23, t.a11 * o.a31 + t.a21 * o.a32 + t.a31 * o.a33,
      t.a12 * o.a11 + t.a22 * o.a12 + t.a32 * o.a13, t.a12 * o.a21 + t.a22 * o.a22 + t.a32 * o.a23, t.a12 * o.a31 + t.a22 * o.a32 + t.a32 * o.a33,
      t.a13 * o.a11 + t.a23 * o.a12 + t.a33 * o.a13, t.a13 * o.a21 + t.a23 * o.a22 + t.a33 * o.a23, t.a13 * o.a31 + t.a23 * o.a32 + t.a33 * o.a33);
  }
  function terapkan(t, x, y) {
    var d = t.a13 * x + t.a23 * y + t.a33;
    return [(t.a11 * x + t.a21 * y + t.a31) / d, (t.a12 * x + t.a22 * y + t.a32) / d];
  }
  function segiempatKeSegiempat(s, d) {
    return kali(persegiKeSegiempat(d[0], d[1], d[2], d[3], d[4], d[5], d[6], d[7]), adjoin(persegiKeSegiempat(s[0], s[1], s[2], s[3], s[4], s[5], s[6], s[7])));
  }

  function cariPenjajaran(bit, w, h, ex, ey, m) {
    var r = Math.max(4, Math.round(m * 5)), terbaik = null, jarakTerbaik = Infinity;
    function di(x, y) { return x >= 0 && y >= 0 && x < w && y < h ? bit[y * w + x] : 0; }
    for (var y = Math.round(ey - r); y <= ey + r; y++) {
      if (y < 0 || y >= h) continue;
      var x0 = Math.max(0, Math.round(ex - r)), x1 = Math.min(w - 1, Math.round(ex + r)), runs = [], warna = di(x0, y), awal = x0;
      for (var x = x0 + 1; x <= x1 + 1; x++) {
        var b = x <= x1 ? di(x, y) : -1;
        if (b !== warna) { runs.push([warna, x - awal, awal]); warna = b; awal = x; }
      }
      for (var i = 1; i + 1 < runs.length; i++) {
        if (runs[i][0] !== 1 || runs[i - 1][0] !== 0 || runs[i + 1][0] !== 0) continue;
        var ok = [runs[i - 1][1], runs[i][1], runs[i + 1][1]].every(function (l) { return l > m * 0.4 && l < m * 1.8; });
        if (!ok) continue;
        var cx = Math.round(runs[i][2] + runs[i][1] / 2), atas = 0, bawah = 0, yy = y;
        while (di(cx, yy) === 1 && atas < m * 2) { atas++; yy--; }
        var putihA = 0; while (di(cx, yy) === 0 && putihA < m * 2) { putihA++; yy--; }
        yy = y + 1;
        while (di(cx, yy) === 1 && bawah < m * 2) { bawah++; yy++; }
        var putihB = 0; while (di(cx, yy) === 0 && putihB < m * 2) { putihB++; yy++; }
        var tinggi = atas + bawah;
        if (tinggi < m * 0.4 || tinggi > m * 1.8 || putihA < m * 0.4 || putihB < m * 0.4 || putihA > m * 1.8 || putihB > m * 1.8) continue;
        var cy = y - atas + tinggi / 2, dj = Math.hypot(cx - ex, cy - ey);
        if (dj < jarakTerbaik) { jarakTerbaik = dj; terbaik = { x: cx, y: cy }; }
      }
    }
    return terbaik;
  }

  function sampel(bit, w, h, t, dim) {
    var m = [];
    for (var y = 0; y < dim; y++) {
      var baris = [];
      for (var x = 0; x < dim; x++) {
        var p = terapkan(t, x + 0.5, y + 0.5), px = Math.round(p[0]), py = Math.round(p[1]);
        baris.push(px >= 0 && py >= 0 && px < w && py < h ? bit[py * w + px] : 0);
      }
      m.push(baris);
    }
    return m;
  }

  function cobaTiga(bit, w, h, tiga) {
    var TL = tiga.kiriAtas, TR = tiga.kananAtas, BL = tiga.kiriBawah;
    var mod = (TL.ukuran + TR.ukuran + BL.ukuran) / 3;
    var dim0 = Math.round((jarak(TL, TR) + jarak(TL, BL)) / 2 / mod) + 7;
    var sisa = dim0 % 4, dimDasar = sisa === 1 ? dim0 : sisa === 0 ? dim0 + 1 : sisa === 2 ? dim0 - 1 : dim0 + 2;
    var daftarDim = [dimDasar, dimDasar - 4, dimDasar + 4].filter(function (d) { return d >= 21 && d <= 57; });
    for (var a = 0; a < daftarDim.length; a++) {
      var dim = daftarDim[a];
      var BRx = TR.x + BL.x - TL.x, BRy = TR.y + BL.y - TL.y;
      var titik = [{ src: [dim - 3.5, dim - 3.5], dst: [BRx, BRy] }];
      if (dim > 21) {
        var afin = segiempatKeSegiempat([3.5, 3.5, dim - 3.5, 3.5, dim - 3.5, dim - 3.5, 3.5, dim - 3.5], [TL.x, TL.y, TR.x, TR.y, BRx, BRy, BL.x, BL.y]);
        var est = terapkan(afin, dim - 6.5, dim - 6.5);
        var ap = cariPenjajaran(bit, w, h, est[0], est[1], mod);
        if (ap) titik.unshift({ src: [dim - 6.5, dim - 6.5], dst: [ap.x, ap.y] });
      }
      for (var b = 0; b < titik.length; b++) {
        var s = titik[b].src, d = titik[b].dst;
        var t = segiempatKeSegiempat([3.5, 3.5, dim - 3.5, 3.5, s[0], s[1], 3.5, dim - 3.5], [TL.x, TL.y, TR.x, TR.y, d[0], d[1], BL.x, BL.y]);
        var hasil = dekodeMatriks(sampel(bit, w, h, t, dim));
        if (hasil !== null) return hasil;
      }
    }
    return null;
  }

  function baca(imageData) {
    var w = imageData.width, h = imageData.height, px = imageData.data, lum = new Uint8ClampedArray(w * h);
    for (var i = 0, j = 0; i < lum.length; i++, j += 4) lum[i] = (px[j] * 77 + px[j + 1] * 150 + px[j + 2] * 29) >> 8;
    var bit = binerkan(lum, w, h);
    var tigaan = pilihTiga(cariPenanda(bit, w, h));
    for (var k = 0; k < tigaan.length; k++) {
      var hasil = cobaTiga(bit, w, h, tigaan[k]);
      if (hasil !== null) return hasil;
    }
    return null;
  }

  global.QrBaca = { baca: baca, _rs: rsPerbaiki, _matriks: dekodeMatriks };
})(window);
