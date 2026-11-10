/*
 * Jeda · pembaca file di browser (Cek File).
 *
 * File dibaca DI PERANGKAT PENGGUNA. Isinya tidak pernah diunggah. Modul ini hanya menghasilkan ringkasan:
 * jenis isi (dari byte awal), beberapa penanda (mis. "PDF memuat JavaScript"), dan sidik jari SHA-256.
 * Penilaian risiko dilakukan server (App\Services\Cek\FileAnalysisService).
 *
 * Fungsi-fungsinya murni (menerima byte, bukan File) supaya bisa diuji di Node.
 */
(function (root, factory) {
  const api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else root.JedaFileInspect = api;
})(typeof self !== 'undefined' ? self : this, function () {
  'use strict';

  const MAX_FILE_BYTES_DEFAULT = 50 * 1024 * 1024;       // sampai ukuran ini file dibaca penuh ke memori
  const MAX_LARGE_BYTES_DEFAULT = 2 * 1024 * 1024 * 1024; // di atasnya hanya nama file; di antaranya dibaca sebagian
  const STREAM_CHUNK = 4 * 1024 * 1024;                   // ukuran potongan saat file besar dibaca bertahap
  const SCAN_TEXT_BYTES = 8 * 1024 * 1024; // PDF/HTML/RTF dipindai maksimal 8 MB pertama
  const MAX_ZIP_ENTRIES = 5000;
  const MAX_CENTRAL_DIR = 8 * 1024 * 1024;

  const startsWith = (b, sig, offset = 0) => sig.every((v, i) => b[offset + i] === v);
  const latin1 = (bytes) => { let s = ''; for (let i = 0; i < bytes.length; i += 0x8000) s += String.fromCharCode.apply(null, bytes.subarray(i, i + 0x8000)); return s; };
  const u16 = (b, o) => b[o] | (b[o + 1] << 8);
  const u32 = (b, o) => (b[o] | (b[o + 1] << 8) | (b[o + 2] << 16) | (b[o + 3] << 24)) >>> 0;

  // ---------------------------------------------------------------- jenis isi dari byte awal

  function looksLikeText(head) {
    if (head.length === 0) return false;
    let odd = 0;
    for (let i = 0; i < head.length; i++) {
      const c = head[i];
      if (c === 0) return false;
      if (c < 9 || (c > 13 && c < 32)) odd++;
    }
    return odd / head.length < 0.05;
  }

  /**
   * Awalan "MZ" saja tidak cukup: teks biasa yang kebetulan diawali "MZ" bukan program.
   * Program Windows memiliki penunjuk (e_lfanew di offset 0x3C) ke tanda "PE\0\0". Program DOS lama tidak punya
   * tanda itu, tetapi isinya biner. Jadi: tanda PE valid => program; tampak seperti teks => bukan program;
   * selain itu (biner berawalan MZ) tetap dianggap program agar penyamaran tidak lolos.
   */
  function looksLikePe(head) {
    if (head.length >= 0x40) {
      const lfanew = u32(head, 0x3c);
      if (lfanew >= 0x40 && lfanew + 4 <= head.length) {
        if (startsWith(head, [0x50, 0x45, 0x00, 0x00], lfanew)) return true;
      } else if (lfanew >= 0x40 && lfanew < 0x100000) {
        return true; // penunjuk wajar tetapi di luar byte awal yang dibaca (stub DOS panjang)
      }
    }
    return !looksLikeText(head.subarray(0, Math.min(head.length, 512)));
  }

  /** @returns {string} salah satu nilai config('jeda_cek.file_types') kecuali zip yang dirinci lewat inspectZip */
  function detectType(head) {
    if (head.length >= 2 && startsWith(head, [0x4d, 0x5a]) && looksLikePe(head)) return 'pe';
    if (startsWith(head, [0x7f, 0x45, 0x4c, 0x46])) return 'elf';
    if (startsWith(head, [0xcf, 0xfa, 0xed, 0xfe]) || startsWith(head, [0xfe, 0xed, 0xfa, 0xcf]) || startsWith(head, [0xce, 0xfa, 0xed, 0xfe]) || startsWith(head, [0xfe, 0xed, 0xfa, 0xce])) return 'macho';
    if (startsWith(head, [0xca, 0xfe, 0xba, 0xbe])) {
      // Mach-O "fat" punya jumlah arsitektur kecil di byte 4-7; kelas Java punya versi (minor+major) >= 45.
      const n = head.length >= 8 ? ((head[4] << 24) | (head[5] << 16) | (head[6] << 8) | head[7]) >>> 0 : 0;
      return n > 0 && n < 45 ? 'macho' : 'java';
    }
    if (startsWith(head, [0x4c, 0x00, 0x00, 0x00, 0x01, 0x14, 0x02, 0x00])) return 'lnk';
    if (startsWith(head, [0x23, 0x21])) return 'script';
    if (latin1(head.subarray(0, 1024)).includes('%PDF-')) return 'pdf';
    if (startsWith(head, [0x89, 0x50, 0x4e, 0x47])) return 'png';
    if (startsWith(head, [0xff, 0xd8, 0xff])) return 'jpeg';
    if (startsWith(head, [0x47, 0x49, 0x46, 0x38])) return 'gif';
    if (startsWith(head, [0x52, 0x49, 0x46, 0x46]) && startsWith(head, [0x57, 0x45, 0x42, 0x50], 8)) return 'webp';
    if (startsWith(head, [0x42, 0x4d]) && head.length > 14 && !looksLikeText(head.subarray(0, 64))) return 'bmp';
    if (startsWith(head, [0xd0, 0xcf, 0x11, 0xe0, 0xa1, 0xb1, 0x1a, 0xe1])) return 'ole';
    if (startsWith(head, [0x50, 0x4b, 0x03, 0x04]) || startsWith(head, [0x50, 0x4b, 0x05, 0x06])) return 'zip';
    if (startsWith(head, [0x52, 0x61, 0x72, 0x21, 0x1a, 0x07])) return 'rar';
    if (startsWith(head, [0x37, 0x7a, 0xbc, 0xaf, 0x27, 0x1c])) return '7z';
    if (startsWith(head, [0x1f, 0x8b])) return 'gzip';
    if (startsWith(head, [0x7b, 0x5c, 0x72, 0x74, 0x66])) return 'rtf';

    if (looksLikeText(head)) {
      let text = latin1(head.subarray(0, 4096)).replace(/^\xef\xbb\xbf/, '').trimStart().toLowerCase();
      if (/^<svg\b/.test(text) || (/^<\?xml/.test(text) && text.includes('<svg'))) return 'svg';
      if (/^(<!doctype html|<html|<head|<body|<script|<form|<meta|<title|<iframe)/.test(text)) return 'html';
      return 'text';
    }
    return 'unknown';
  }

  // ---------------------------------------------------------------- ZIP (tanpa dekompresi)

  /**
   * Membaca daftar isi ZIP dari central directory. Hanya nama dan penanda enkripsi yang dibaca,
   * tidak ada yang diekstrak atau dijalankan.
   * @param {Uint8Array} tail  byte terakhir file (minimal 64 KB atau seluruh file)
   * @param {number} tailStart posisi tail di dalam file
   * @param {(start:number,end:number)=>Uint8Array|Promise<Uint8Array>} readRange
   */
  async function readZipEntries(tail, tailStart, readRange) {
    let eocd = -1;
    for (let i = tail.length - 22; i >= 0; i--) {
      if (tail[i] === 0x50 && tail[i + 1] === 0x4b && tail[i + 2] === 0x05 && tail[i + 3] === 0x06) { eocd = i; break; }
    }
    if (eocd < 0) return { unreadable: true, entries: [] };

    const total = u16(tail, eocd + 10);
    const cdSize = u32(tail, eocd + 12);
    const cdOffset = u32(tail, eocd + 16);
    if (total === 0) return { unreadable: false, entries: [] };
    if (total === 0xffff || cdSize === 0xffffffff || cdOffset === 0xffffffff || cdSize > MAX_CENTRAL_DIR) return { unreadable: true, entries: [] };

    let cd;
    if (cdOffset >= tailStart && cdOffset + cdSize <= tailStart + tail.length) cd = tail.subarray(cdOffset - tailStart, cdOffset - tailStart + cdSize);
    else cd = await readRange(cdOffset, cdOffset + cdSize);

    const entries = [];
    let pos = 0;
    while (pos + 46 <= cd.length && entries.length < MAX_ZIP_ENTRIES) {
      if (u32(cd, pos) !== 0x02014b50) break;
      const flags = u16(cd, pos + 8);
      const method = u16(cd, pos + 10);
      const compSize = u32(cd, pos + 20);
      const uncompSize = u32(cd, pos + 24);
      const localOffset = u32(cd, pos + 42);
      const nameLen = u16(cd, pos + 28);
      const extraLen = u16(cd, pos + 30);
      const commentLen = u16(cd, pos + 32);
      if (pos + 46 + nameLen > cd.length) break;
      const raw = cd.subarray(pos + 46, pos + 46 + nameLen);
      let name;
      try { name = new TextDecoder('utf-8', { fatal: false }).decode(raw); } catch { name = latin1(raw); }
      entries.push({ name: name.replace(/\\/g, '/'), encrypted: (flags & 1) === 1, method, compSize, uncompSize, localOffset });
      pos += 46 + nameLen + extraLen + commentLen;
    }
    return { unreadable: false, entries };
  }

  function baseName(path) { const parts = path.split('/'); return parts[parts.length - 1] || ''; }
  function extList(name) { const p = name.toLowerCase().replace(/[ .]+$/, '').split('.'); return p.length > 1 ? p.slice(1) : []; }

  /**
   * Menyimpulkan jenis dan penanda dari daftar isi ZIP.
   * @param {{name:string,encrypted:boolean}[]} entries
   * @param {{executable:string[],decoy:string[]}} cfg
   */
  function summarizeZip(entries, cfg) {
    const exec = new Set([...(cfg.executable || []), 'apk']);
    const decoy = new Set(cfg.decoy || []);
    const names = entries.map((e) => e.name.toLowerCase());
    const out = { type: 'zip', flags: {} };

    const hasManifest = names.includes('androidmanifest.xml');
    const hasDex = names.some((n) => n.endsWith('.dex'));
    if (hasManifest && hasDex) out.type = 'apk';
    else if (names.includes('meta-inf/manifest.mf') && names.some((n) => n.endsWith('.class'))) out.type = 'jar';
    else if (names.includes('[content_types].xml') && names.some((n) => /^(word|xl|ppt)\//.test(n))) out.type = 'ooxml';

    if (names.some((n) => n.endsWith('vbaproject.bin'))) out.flags.has_macro = true;
    if (entries.some((e) => e.encrypted)) out.flags.archive_encrypted = true;

    // Pemeriksaan isi arsip hanya untuk arsip biasa (bukan APK/JAR/dokumen Office, yang isinya memang berstruktur).
    if (out.type === 'zip') {
      for (const e of entries) {
        if (e.name.endsWith('/')) continue;
        const exts = extList(baseName(e.name));
        const last = exts[exts.length - 1];
        if (last && exec.has(last)) {
          out.flags.archive_executable = true;
          if (exts.length >= 2 && decoy.has(exts[exts.length - 2])) out.flags.archive_double_extension = true;
        }
      }
    }
    return out;
  }

  // ---------------------------------------------------------------- pemindai teks sederhana

  function scanPdf(text) {
    const f = {};
    // Nama PDF boleh ditulis dengan escape heksadesimal (/#4aavaScript). Dinormalkan dulu agar tidak lolos begitu saja.
    const t = text.replace(/#([0-9a-fA-F]{2})/g, (_, h) => String.fromCharCode(parseInt(h, 16)));
    // "/JS" saja terlalu pendek: urutan byte itu muncul kebetulan di data terkompresi (±3% per MB). Nilai /JS yang
    // sungguhan selalu diikuti string "(", string heks "<", atau rujukan objek "12 0 R".
    if (/\/JavaScript\b/.test(t) || /\/JS\s*(?:[(<]|\d+\s+\d+\s+R\b)/.test(t)) f.pdf_javascript = true;
    if (/\/Launch\b/.test(t)) f.pdf_launch = true;
    if (/\/(OpenAction|AA)\b/.test(t)) f.pdf_openaction = true;
    if (/\/EmbeddedFile\b/.test(t)) f.pdf_embedded = true;
    return f;
  }

  function scanHtml(text, isSvg) {
    const f = {};
    const t = text.toLowerCase();
    if (isSvg) {
      if (/<script\b/.test(t) || /\son[a-z]+\s*=/.test(t)) f.svg_script = true;
      return f;
    }
    if (/<input\b[^>]*type\s*=\s*["']?password/.test(t)) f.html_password_form = true;
    if (/<form\b[^>]*action\s*=\s*["']?https?:\/\//.test(t)) f.html_external_form = true;
    return f;
  }

  function scanRtf(text) {
    return /\\(objdata|objemb|objautlink)\b/i.test(text) ? { rtf_object: true } : {};
  }

  /** Makro di dokumen Office lama (OLE): nama aliran "VBA" / "_VBA_PROJECT" tertulis UTF-16 di direktori. */
  function scanOle(bytes) {
    const needle = (s) => Array.from(s).flatMap((c) => [c.charCodeAt(0), 0]);
    const find = (pat) => {
      const n = needle(pat);
      outer: for (let i = 0; i + n.length <= bytes.length; i++) {
        if (bytes[i] !== n[0]) continue;
        for (let j = 1; j < n.length; j++) if (bytes[i + j] !== n[j]) continue outer;
        return true;
      }
      return false;
    };
    return find('_VBA_PROJECT') || find('VBA') ? { has_macro: true } : {};
  }

  // ---------------------------------------------------------------- APK: izin dari AndroidManifest.xml (AXML)

  const MAX_APK_PERMISSIONS = 500; // sama dengan CheckFileRequest::MAX_APK_PERMISSIONS di server

  /**
   * Izin yang dikirim ke server: nama yang tidak valid dibuang, dan bila lebih dari batas, izin yang dikenali
   * mesin penilaian (cfg.apkKnown) didahulukan supaya izin pengisi tidak mendorong izin berbahaya keluar.
   */
  function pickPermissions(perms, known) {
    const knownSet = new Set(known || []);
    const valid = Array.from(new Set(perms)).filter((p) => typeof p === 'string' && p.length <= 120 && /^[A-Za-z0-9_.]+$/.test(p));
    const first = valid.filter((p) => knownSet.has(p));
    const rest = valid.filter((p) => !knownSet.has(p));
    return first.concat(rest).slice(0, MAX_APK_PERMISSIONS);
  }

  const MAX_MANIFEST_BYTES = 4 * 1024 * 1024; // batas sesudah dekompresi (anti zip-bomb)

  /** Dekompresi deflate mentah dengan batas keluaran. Mengembalikan null jika tidak didukung/terlalu besar. */
  async function inflateRaw(data, limit, format = 'deflate-raw') {
    if (typeof DecompressionStream === 'undefined') return null;
    try {
      const stream = new Blob([data]).stream().pipeThrough(new DecompressionStream(format));
      const reader = stream.getReader();
      const chunks = [];
      let total = 0;
      for (;;) {
        const { done, value } = await reader.read();
        if (done) break;
        total += value.length;
        if (total > limit) { await reader.cancel(); return null; }
        chunks.push(value);
      }
      const out = new Uint8Array(total);
      let o = 0;
      for (const c of chunks) { out.set(c, o); o += c.length; }
      return out;
    } catch { return null; }
  }

  /** Mengambil isi satu entri ZIP (tanpa mengekstrak ke disk). Null bila terenkripsi, tidak didukung, atau terlalu besar. */
  async function readZipEntry(bytes, entry, limit) {
    if (entry.encrypted || entry.uncompSize > limit) return null;
    const o = entry.localOffset;
    if (o + 30 > bytes.length || u32(bytes, o) !== 0x04034b50) return null;
    const start = o + 30 + u16(bytes, o + 26) + u16(bytes, o + 28);
    const raw = bytes.subarray(start, start + entry.compSize);
    if (raw.length !== entry.compSize) return null;
    if (entry.method === 0) return raw;
    if (entry.method === 8) return inflateRaw(raw, limit);
    return null;
  }

  /**
   * Membaca AndroidManifest.xml biner (AXML): nama paket dan semua izin.
   * Izin dikumpulkan dari <uses-permission android:name> dan dari atribut android:permission
   * (mis. layanan aksesibilitas dan device admin dideklarasikan lewat atribut ini).
   * @returns {{package:string|null, permissions:string[]}|null} null jika bukan AXML yang valid
   */
  function parseAxml(b) {
    if (b.length < 8 || u16(b, 0) !== 0x0003) return null;
    const total = Math.min(u32(b, 4), b.length);
    let pos = u16(b, 2); // headerSize
    let strings = [];
    const perms = new Set();
    let pkg = null;
    let sawManifest = false;

    const readStrings = (at) => {
      const count = u32(b, at + 8);
      const flags = u32(b, at + 16);
      const strStart = at + u32(b, at + 20);
      const utf8 = (flags & 0x100) !== 0;
      const out = [];
      for (let i = 0; i < count && i < 20000; i++) {
        let p = strStart + u32(b, at + 28 + i * 4);
        if (p >= b.length) { out.push(''); continue; }
        if (utf8) {
          let len = b[p++]; if (len & 0x80) len = ((len & 0x7f) << 8) | b[p++]; // panjang karakter (dilewati)
          let blen = b[p++]; if (blen & 0x80) blen = ((blen & 0x7f) << 8) | b[p++];
          out.push(new TextDecoder('utf-8').decode(b.subarray(p, p + blen)));
        } else {
          let len = u16(b, p); p += 2; if (len & 0x8000) { len = ((len & 0x7fff) << 16) | u16(b, p); p += 2; }
          let s = ''; for (let k = 0; k < len && p + 2 * k + 1 < b.length; k++) s += String.fromCharCode(u16(b, p + 2 * k));
          out.push(s);
        }
      }
      return out;
    };

    while (pos + 8 <= total) {
      const type = u16(b, pos), size = u32(b, pos + 4);
      if (size < 8 || pos + size > b.length) break;
      if (type === 0x0001) strings = readStrings(pos);
      else if (type === 0x0102) { // START_ELEMENT
        const nameIdx = u32(b, pos + 20);
        const attrStart = u16(b, pos + 24), attrSize = u16(b, pos + 26), attrCount = u16(b, pos + 28);
        const elem = strings[nameIdx] || '';
        if (elem === 'manifest') sawManifest = true;
        for (let i = 0; i < attrCount && i < 200; i++) {
          const a = pos + 16 + attrStart + i * (attrSize || 20);
          if (a + 20 > b.length) break;
          const attrName = strings[u32(b, a + 4)] || '';
          const rawIdx = u32(b, a + 8);
          const dataType = b[a + 15];
          const value = rawIdx !== 0xffffffff ? strings[rawIdx] : (dataType === 0x03 ? strings[u32(b, a + 16)] : undefined);
          if (typeof value !== 'string') continue;
          if (elem === 'manifest' && attrName === 'package') pkg = value;
          else if (elem === 'uses-permission' || elem === 'uses-permission-sdk-23') { if (attrName === 'name') perms.add(value); }
          else if (attrName === 'permission' && /^[A-Za-z0-9_.]+$/.test(value)) perms.add(value);
        }
      }
      pos += size;
    }
    // Tanpa string pool dan elemen <manifest> berarti bukan AXML yang valid (atau sengaja dirusak).
    if (strings.length === 0 || !sawManifest) return null;
    return { package: pkg, permissions: Array.from(perms) };
  }

  /** Membaca paket + izin dari APK. null bila manifest tidak bisa dibaca (dilaporkan sebagai belum lengkap, bukan aman). */
  async function readApkInfo(bytes, entries) {
    const entry = entries.find((e) => e.name.toLowerCase() === 'androidmanifest.xml');
    if (!entry) return null;
    const manifest = await readZipEntry(bytes, entry, MAX_MANIFEST_BYTES);
    return manifest ? parseAxml(manifest) : null;
  }

  // ---------------------------------------------------------------- hash

  async function sha256Hex(buffer) {
    const subtle = (typeof crypto !== 'undefined' && crypto.subtle) || null;
    if (!subtle) return null; // butuh HTTPS atau localhost
    const digest = await subtle.digest('SHA-256', buffer);
    return Array.from(new Uint8Array(digest)).map((b) => b.toString(16).padStart(2, '0')).join('');
  }

  // ---------------------------------------------------------------- file uji antivirus EICAR

  // String uji standar EICAR (68 karakter) sengaja DISUSUN dari potongan saat dijalankan. Kalau ditulis utuh di file
  // ini, antivirus di komputer pengembang akan menandai file kode ini sendiri sebagai virus.
  const EICAR_PARTS = ['X5O!P%@AP[4\\PZX54(P^)7CC)7}$', 'EI' + 'CAR', '-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'];
  const EICAR_BYTES = Uint8Array.from(EICAR_PARTS.join(''), (c) => c.charCodeAt(0));

  // ---------------------------------------------------------------- EICAR yang tersembunyi di PDF

  const MAX_PDF_STREAMS = 500;
  const MAX_PDF_STREAM_BYTES = 8 * 1024 * 1024;     // batas per stream sesudah dekompresi (anti zip-bomb)
  const MAX_PDF_DECODED_TOTAL = 32 * 1024 * 1024;   // batas total semua stream sesudah dekompresi

  /** Filter ASCII85 PDF. Null jika ada karakter yang tidak sah. */
  function decodeAscii85(data) {
    const out = [];
    let group = [];
    let i = 0;
    if (data[0] === 0x3c && data[1] === 0x7e) i = 2; // awalan "<~" (opsional)
    for (; i < data.length; i++) {
      const c = data[i];
      if (c === 0x7e) break; // "~>" penutup
      if (c <= 0x20) continue; // spasi dan baris baru diabaikan
      if (c === 0x7a && group.length === 0) { out.push(0, 0, 0, 0); continue; } // "z" = empat byte nol
      if (c < 0x21 || c > 0x75) return null;
      group.push(c - 33);
      if (group.length === 5) {
        const v = group.reduce((a, d) => a * 85 + d, 0);
        out.push(Math.floor(v / 16777216) & 255, (v >>> 16) & 255, (v >>> 8) & 255, v & 255);
        group = [];
      }
    }
    if (group.length === 1) return null;
    if (group.length > 1) {
      const n = group.length;
      while (group.length < 5) group.push(84); // dilengkapi dengan "u"
      const v = group.reduce((a, d) => a * 85 + d, 0);
      out.push(...[Math.floor(v / 16777216) & 255, (v >>> 16) & 255, (v >>> 8) & 255, v & 255].slice(0, n - 1));
    }
    return Uint8Array.from(out);
  }

  /** Filter ASCIIHex PDF. */
  function decodeAsciiHex(data) {
    const out = [];
    let hi = -1;
    for (let i = 0; i < data.length; i++) {
      const c = data[i];
      if (c === 0x3e) break; // ">"
      const d = c >= 0x30 && c <= 0x39 ? c - 0x30 : c >= 0x41 && c <= 0x46 ? c - 0x37 : c >= 0x61 && c <= 0x66 ? c - 0x57 : -1;
      if (d < 0) { if (c <= 0x20) continue; return null; }
      if (hi < 0) hi = d; else { out.push(hi * 16 + d); hi = -1; }
    }
    if (hi >= 0) out.push(hi * 16);
    return Uint8Array.from(out);
  }

  /** Membuka escape string PDF: \\ \( \) \n \r \t \b \f, \ddd (oktal), dan sambungan baris. */
  function unescapePdfString(bytes) {
    const out = new Uint8Array(bytes.length);
    const map = { 0x6e: 10, 0x72: 13, 0x74: 9, 0x62: 8, 0x66: 12 };
    let o = 0;
    for (let i = 0; i < bytes.length; i++) {
      const c = bytes[i];
      if (c !== 0x5c || i + 1 >= bytes.length) { out[o++] = c; continue; }
      const n = bytes[++i];
      if (n >= 0x30 && n <= 0x37) {
        let v = n - 0x30; let k = 1;
        while (k < 3 && i + 1 < bytes.length && bytes[i + 1] >= 0x30 && bytes[i + 1] <= 0x37) { v = v * 8 + bytes[++i] - 0x30; k++; }
        out[o++] = v & 255;
      } else if (n === 0x0d) { if (bytes[i + 1] === 0x0a) i++; } // sambungan baris
      else if (n === 0x0a) { /* sambungan baris */ }
      else out[o++] = map[n] !== undefined ? map[n] : n;
    }
    return out.subarray(0, o);
  }

  /**
   * EICAR yang disembunyikan di PDF: di dalam stream terkompresi (ASCII85/ASCIIHex/Flate, boleh berantai) atau
   * ditulis sebagai string PDF dengan escape. Pemindaian byte mentah (containsEicar) tidak melihat keduanya.
   * Hanya mencari EICAR (file uji, bukan malware). Aman terhadap PDF rusak atau bomb: tiap stream, jumlah stream,
   * dan total hasil dekompresi dibatasi. Filter yang tidak dikenal (mis. gambar JPEG, LZW) dilewati.
   * @param {Uint8Array} bytes
   * @returns {Promise<boolean>}
   */
  async function pdfHiddenEicar(bytes) {
    if (containsEicar(unescapePdfString(bytes))) return true;

    const text = latin1(bytes);
    const re = /stream\r?\n/g;
    let m; let streams = 0; let decodedTotal = 0;
    while ((m = re.exec(text)) !== null) {
      if (m.index >= 3 && text.startsWith('end', m.index - 3)) continue; // "endstream"
      const start = re.lastIndex;
      const end = text.indexOf('endstream', start);
      if (end < 0) continue;
      if (++streams > MAX_PDF_STREAMS) break;

      const head = text.slice(Math.max(0, m.index - 1500), m.index);
      const dict = head.slice(head.lastIndexOf('obj') + 3);
      const fm = /\/Filter\s*(\[[^\]]*\]|\/[A-Za-z0-9]+)/.exec(dict);
      if (!fm) continue; // tanpa filter: isinya sudah dicakup pemindaian mentah di atas
      const names = Array.from(fm[1].matchAll(/\/([A-Za-z0-9]+)/g), (x) => x[1]);

      let data = bytes.subarray(start, end);
      while (data.length && (data[data.length - 1] === 0x0a || data[data.length - 1] === 0x0d)) data = data.subarray(0, data.length - 1);
      for (const name of names) {
        if (!data) break;
        if (name === 'ASCII85Decode' || name === 'A85') data = decodeAscii85(data);
        else if (name === 'ASCIIHexDecode' || name === 'AHx') data = decodeAsciiHex(data);
        else if (name === 'FlateDecode' || name === 'Fl') data = await inflateRaw(data, MAX_PDF_STREAM_BYTES, 'deflate');
        else data = null; // filter lain: dilewati
      }
      if (!data) continue;
      decodedTotal += data.length;
      if (decodedTotal > MAX_PDF_DECODED_TOTAL) break;
      if (containsEicar(data) || containsEicar(unescapePdfString(data))) return true;
    }
    return false;
  }

  /**
   * Apakah byte berisi string uji EICAR di mana pun (bukan hanya di awal)? Itu bukan virus sungguhan, tetapi
   * file uji yang sengaja dikenali semua antivirus. Hanya mencocokkan teks polos: EICAR yang dikompres
   * di dalam ZIP tidak terdeteksi di sini. Untuk PDF, lihat pdfHiddenEicar (stream terkompresi dan escape string).
   */
  function containsEicar(bytes) {
    const needle = EICAR_BYTES;
    const first = needle[0];
    const last = bytes.length - needle.length;
    for (let i = bytes.indexOf(first); i !== -1 && i <= last; i = bytes.indexOf(first, i + 1)) {
      let j = 1;
      while (j < needle.length && bytes[i + j] === needle[j]) j++;
      if (j === needle.length) return true;
    }
    return false;
  }

  // ---------------------------------------------------------------- file besar: dibaca bertahap

  const SHA256_K = new Uint32Array([
    0x428a2f98, 0x71374491, 0xb5c0fbcf, 0xe9b5dba5, 0x3956c25b, 0x59f111f1, 0x923f82a4, 0xab1c5ed5,
    0xd807aa98, 0x12835b01, 0x243185be, 0x550c7dc3, 0x72be5d74, 0x80deb1fe, 0x9bdc06a7, 0xc19bf174,
    0xe49b69c1, 0xefbe4786, 0x0fc19dc6, 0x240ca1cc, 0x2de92c6f, 0x4a7484aa, 0x5cb0a9dc, 0x76f988da,
    0x983e5152, 0xa831c66d, 0xb00327c8, 0xbf597fc7, 0xc6e00bf3, 0xd5a79147, 0x06ca6351, 0x14292967,
    0x27b70a85, 0x2e1b2138, 0x4d2c6dfc, 0x53380d13, 0x650a7354, 0x766a0abb, 0x81c2c92e, 0x92722c85,
    0xa2bfe8a1, 0xa81a664b, 0xc24b8b70, 0xc76c51a3, 0xd192e819, 0xd6990624, 0xf40e3585, 0x106aa070,
    0x19a4c116, 0x1e376c08, 0x2748774c, 0x34b0bcb5, 0x391c0cb3, 0x4ed8aa4a, 0x5b9cca4f, 0x682e6ff3,
    0x748f82ee, 0x78a5636f, 0x84c87814, 0x8cc70208, 0x90befffa, 0xa4506ceb, 0xbef9a3f7, 0xc67178f2,
  ]);

  /**
   * SHA-256 yang bisa diberi data sedikit demi sedikit. SubtleCrypto hanya bisa menghash satu buffer utuh,
   * sehingga file ratusan MB harus dimuat ke memori. Dengan ini file berapa pun cukup dibaca per potongan.
   * Tanpa library luar (CSP hanya mengizinkan skrip dari situs sendiri).
   */
  function createSha256() {
    const H = new Uint32Array([0x6a09e667, 0xbb67ae85, 0x3c6ef372, 0xa54ff53a, 0x510e527f, 0x9b05688c, 0x1f83d9ab, 0x5be0cd19]);
    const W = new Uint32Array(64);
    const buf = new Uint8Array(64);
    let bufLen = 0;
    let total = 0;

    function compress(b, o) {
      for (let i = 0; i < 16; i++) {
        const j = o + i * 4;
        W[i] = ((b[j] << 24) | (b[j + 1] << 16) | (b[j + 2] << 8) | b[j + 3]) >>> 0;
      }
      for (let i = 16; i < 64; i++) {
        const x = W[i - 15];
        const y = W[i - 2];
        const s0 = ((x >>> 7) | (x << 25)) ^ ((x >>> 18) | (x << 14)) ^ (x >>> 3);
        const s1 = ((y >>> 17) | (y << 15)) ^ ((y >>> 19) | (y << 13)) ^ (y >>> 10);
        W[i] = (W[i - 16] + s0 + W[i - 7] + s1) >>> 0;
      }
      let a = H[0], bb = H[1], c = H[2], d = H[3], e = H[4], f = H[5], g = H[6], h = H[7];
      for (let i = 0; i < 64; i++) {
        const S1 = ((e >>> 6) | (e << 26)) ^ ((e >>> 11) | (e << 21)) ^ ((e >>> 25) | (e << 7));
        const ch = (e & f) ^ (~e & g);
        const t1 = (h + S1 + ch + SHA256_K[i] + W[i]) >>> 0;
        const S0 = ((a >>> 2) | (a << 30)) ^ ((a >>> 13) | (a << 19)) ^ ((a >>> 22) | (a << 10));
        const maj = (a & bb) ^ (a & c) ^ (bb & c);
        const t2 = (S0 + maj) >>> 0;
        h = g; g = f; f = e; e = (d + t1) >>> 0; d = c; c = bb; bb = a; a = (t1 + t2) >>> 0;
      }
      H[0] = (H[0] + a) >>> 0; H[1] = (H[1] + bb) >>> 0; H[2] = (H[2] + c) >>> 0; H[3] = (H[3] + d) >>> 0;
      H[4] = (H[4] + e) >>> 0; H[5] = (H[5] + f) >>> 0; H[6] = (H[6] + g) >>> 0; H[7] = (H[7] + h) >>> 0;
    }

    function feed(data) {
      let i = 0;
      if (bufLen > 0) {
        const take = Math.min(64 - bufLen, data.length);
        buf.set(data.subarray(0, take), bufLen);
        bufLen += take;
        i = take;
        if (bufLen === 64) { compress(buf, 0); bufLen = 0; }
      }
      for (; i + 64 <= data.length; i += 64) compress(data, i);
      if (i < data.length) { buf.set(data.subarray(i), 0); bufLen = data.length - i; }
    }

    return {
      update(data) { total += data.length; feed(data); },
      hex() {
        const padLen = (bufLen < 56 ? 56 : 120) - bufLen;
        const tail = new Uint8Array(padLen + 8);
        tail[0] = 0x80;
        const hi = Math.floor(total / 0x20000000) >>> 0; // total * 8 dibagi 2^32
        const lo = (total << 3) >>> 0;
        for (let i = 0; i < 4; i++) { tail[padLen + i] = (hi >>> (24 - 8 * i)) & 0xff; tail[padLen + 4 + i] = (lo >>> (24 - 8 * i)) & 0xff; }
        feed(tail);
        return Array.from(H).map((v) => v.toString(16).padStart(8, '0')).join('');
      },
    };
  }

  const readFileRange = async (file, start, end) => new Uint8Array(await file.slice(start, end).arrayBuffer());

  /**
   * Satu kali jalan membaca seluruh file per potongan: menghitung SHA-256 dan mencari teks EICAR,
   * tanpa pernah memuat file utuh ke memori.
   * @returns {Promise<{sha256:string, eicar:boolean}>}
   */
  async function streamScan(file, onProgress) {
    const sha = createSha256();
    const n = EICAR_BYTES.length;
    let carry = new Uint8Array(0);
    let eicar = false;
    for (let pos = 0; pos < file.size; pos += STREAM_CHUNK) {
      const chunk = await readFileRange(file, pos, Math.min(pos + STREAM_CHUNK, file.size));
      sha.update(chunk);
      if (!eicar) {
        // Teks bisa terbelah di batas dua potongan, jadi sambungan kecil di tepi ikut diperiksa.
        const edge = new Uint8Array(carry.length + Math.min(chunk.length, n - 1));
        edge.set(carry);
        edge.set(chunk.subarray(0, n - 1), carry.length);
        eicar = containsEicar(edge) || containsEicar(chunk);
        carry = chunk.slice(Math.max(0, chunk.length - (n - 1)));
      }
      if (onProgress) onProgress({ done: Math.min(pos + STREAM_CHUNK, file.size), total: file.size });
    }
    return { sha256: sha.hex(), eicar };
  }

  /** Seperti readZipEntry, tetapi hanya membaca bagian entri itu dari file (bukan seluruh file). */
  async function readZipEntryRange(file, entry, limit) {
    if (entry.encrypted || entry.uncompSize > limit || entry.compSize > limit + 65536) return null;
    const o = entry.localOffset;
    if (o + 30 > file.size) return null;
    const header = await readFileRange(file, o, o + 30);
    if (u32(header, 0) !== 0x04034b50) return null;
    const start = o + 30 + u16(header, 26) + u16(header, 28);
    if (start + entry.compSize > file.size) return null;
    const raw = await readFileRange(file, start, start + entry.compSize);
    if (entry.method === 0) return raw;
    if (entry.method === 8) return inflateRaw(raw, limit);
    return null;
  }

  async function readApkInfoRange(file, entries) {
    const entry = entries.find((e) => e.name.toLowerCase() === 'androidmanifest.xml');
    if (!entry) return null;
    const manifest = await readZipEntryRange(file, entry, MAX_MANIFEST_BYTES);
    return manifest ? parseAxml(manifest) : null;
  }

  /**
   * Analisis file besar tanpa memuatnya utuh: jenis dari byte awal, daftar isi arsip dan manifest APK dari
   * potongan yang dibutuhkan, tanda di awal file (PDF/HTML/RTF/Office lama), lalu satu putaran bertahap untuk
   * sidik jari dan EICAR. Tanda di bagian tengah file (mis. JavaScript di tengah PDF) bisa terlewat: partial=true.
   */
  async function inspectLarge(file, cfg = {}, onProgress = null) {
    const head = await readFileRange(file, 0, Math.min(file.size, 4096));
    let type = detectType(head);
    const flags = {};
    let apk = null;
    let partial = false;

    if (type === 'zip') {
      const tailStart = Math.max(0, file.size - 65557);
      const tail = await readFileRange(file, tailStart, file.size);
      const z = await readZipEntries(tail, tailStart, (s, e) => readFileRange(file, s, e));
      if (z.unreadable) flags.archive_unreadable = true;
      else {
        const sum = summarizeZip(z.entries, cfg);
        type = sum.type;
        Object.assign(flags, sum.flags);
        if (type === 'apk') {
          const info = await readApkInfoRange(file, z.entries).catch(() => null);
          if (info) apk = info; else flags.apk_unreadable = true;
        }
      }
    } else if (['pdf', 'html', 'svg', 'rtf', 'ole'].includes(type)) {
      const part = await readFileRange(file, 0, Math.min(file.size, SCAN_TEXT_BYTES));
      partial = true;
      if (type === 'pdf') {
        Object.assign(flags, scanPdf(latin1(part)));
        if (await pdfHiddenEicar(part).catch(() => false)) flags.eicar_test = true;
      } else if (type === 'html') Object.assign(flags, scanHtml(latin1(part), false));
      else if (type === 'svg') Object.assign(flags, scanHtml(latin1(part), true));
      else if (type === 'rtf') Object.assign(flags, scanRtf(latin1(part)));
      else Object.assign(flags, scanOle(part));
    }

    const scan = await streamScan(file, onProgress);
    if (scan.eicar) flags.eicar_test = true;
    return { detected_type: type, flags, apk, partial, sha256: scan.sha256 };
  }

  // ---------------------------------------------------------------- orkestrasi

  /**
   * Analisis dari byte yang sudah dibaca. Murni (tanpa File/DOM) agar mudah diuji.
   * @returns {{detected_type:string, flags:Object<string,boolean>}}
   */
  async function analyzeBytes(bytes, cfg = {}) {
    let type = detectType(bytes.subarray(0, 4096));
    const flags = {};
    let apk = null;
    const text = () => latin1(bytes.subarray(0, Math.min(bytes.length, SCAN_TEXT_BYTES)));

    if (type === 'zip') {
      const tailStart = Math.max(0, bytes.length - 65557);
      const z = await readZipEntries(bytes.subarray(tailStart), tailStart, (s, e) => bytes.subarray(s, e));
      if (z.unreadable) flags.archive_unreadable = true;
      else {
        const sum = summarizeZip(z.entries, cfg);
        type = sum.type;
        Object.assign(flags, sum.flags);
        if (type === 'apk') {
          const info = await readApkInfo(bytes, z.entries).catch(() => null);
          if (info) apk = info; else flags.apk_unreadable = true;
        }
      }
    } else if (type === 'pdf') Object.assign(flags, scanPdf(text()));
    else if (type === 'html') Object.assign(flags, scanHtml(text(), false));
    else if (type === 'svg') Object.assign(flags, scanHtml(text(), true));
    else if (type === 'rtf') Object.assign(flags, scanRtf(text()));
    else if (type === 'ole') Object.assign(flags, scanOle(bytes));

    if (containsEicar(bytes)) flags.eicar_test = true;
    if (type === 'pdf' && !flags.eicar_test && (await pdfHiddenEicar(bytes).catch(() => false))) flags.eicar_test = true;

    // Pemindai teks hanya membaca SCAN_TEXT_BYTES pertama, jadi file yang lebih panjang dilaporkan dibaca sebagian.
    const partial = ['pdf', 'html', 'svg', 'rtf'].includes(type) && bytes.length > SCAN_TEXT_BYTES;

    return { detected_type: type, flags, apk, partial };
  }

  /**
   * Membaca satu File di perangkat dan mengembalikan ringkasan untuk dikirim ke server.
   *  - sampai cfg.maxBytes (50 MB): dibaca penuh;
   *  - sampai cfg.maxLargeBytes (2 GB): dibaca bertahap dan sebagian (lihat inspectLarge);
   *  - lebih besar lagi: tidak dibaca (content_scanned=false), hanya nama dan ukuran.
   * @param {File|Blob} file
   * @param {{executable?:string[],decoy?:string[],maxBytes?:number,maxLargeBytes?:number}} cfg
   * @param {((p:{done:number,total:number})=>void)|null} onProgress dipanggil saat file besar dihash
   */
  async function inspect(file, cfg = {}, onProgress = null) {
    const maxBytes = cfg.maxBytes || MAX_FILE_BYTES_DEFAULT;
    const maxLarge = Math.max(maxBytes, cfg.maxLargeBytes || MAX_LARGE_BYTES_DEFAULT);
    const result = { filename: file.name || '', size: file.size, sha256: null, detected_type: null, content_scanned: false, flags: {} };
    if (file.size > maxLarge) return result;

    try {
      let analysis;
      if (file.size > maxBytes) {
        analysis = await inspectLarge(file, cfg, onProgress);
        result.sha256 = analysis.sha256;
      } else {
        const buffer = await file.arrayBuffer();
        result.sha256 = await sha256Hex(buffer).catch(() => null);
        analysis = await analyzeBytes(new Uint8Array(buffer), cfg);
      }
      result.detected_type = analysis.detected_type;
      result.flags = analysis.flags;
      if (analysis.partial) result.content_partial = true;
      if (analysis.apk) {
        result.apk_package = analysis.apk.package;
        result.apk_permissions = pickPermissions(analysis.apk.permissions, cfg.apkKnown);
      }
      result.content_scanned = true;
    } catch {
      result.content_scanned = false; // gagal membaca: dilaporkan sebagai pemeriksaan belum lengkap, bukan aman
    }
    return result;
  }

  return { inspect, inspectLarge, createSha256, streamScan, analyzeBytes, containsEicar, parseAxml, detectType, readZipEntries, summarizeZip, scanPdf, pdfHiddenEicar, pickPermissions, scanHtml, scanRtf, scanOle, sha256Hex };
});
