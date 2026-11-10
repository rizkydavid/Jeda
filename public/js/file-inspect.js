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

  const MAX_FILE_BYTES_DEFAULT = 50 * 1024 * 1024;
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
    if (/\/(JavaScript|JS)\b/.test(t)) f.pdf_javascript = true;
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

  const MAX_MANIFEST_BYTES = 4 * 1024 * 1024; // batas sesudah dekompresi (anti zip-bomb)

  /** Dekompresi deflate mentah dengan batas keluaran. Mengembalikan null jika tidak didukung/terlalu besar. */
  async function inflateRaw(data, limit) {
    if (typeof DecompressionStream === 'undefined') return null;
    try {
      const stream = new Blob([data]).stream().pipeThrough(new DecompressionStream('deflate-raw'));
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

    return { detected_type: type, flags, apk };
  }

  /**
   * Membaca satu File di perangkat dan mengembalikan ringkasan untuk dikirim ke server.
   * File lebih besar dari batas tidak dibaca (content_scanned=false).
   * @param {File|Blob} file
   * @param {{executable?:string[],decoy?:string[],maxBytes?:number}} cfg
   */
  async function inspect(file, cfg = {}) {
    const maxBytes = cfg.maxBytes || MAX_FILE_BYTES_DEFAULT;
    const result = { filename: file.name || '', size: file.size, sha256: null, detected_type: null, content_scanned: false, flags: {} };
    if (file.size > maxBytes) return result;

    try {
      const buffer = await file.arrayBuffer();
      result.sha256 = await sha256Hex(buffer).catch(() => null);
      const analysis = await analyzeBytes(new Uint8Array(buffer), cfg);
      result.detected_type = analysis.detected_type;
      result.flags = analysis.flags;
      if (analysis.apk) {
        result.apk_package = analysis.apk.package;
        result.apk_permissions = analysis.apk.permissions.slice(0, 150);
      }
      result.content_scanned = true;
    } catch {
      result.content_scanned = false; // gagal membaca: dilaporkan sebagai pemeriksaan belum lengkap, bukan aman
    }
    return result;
  }

  return { inspect, analyzeBytes, parseAxml, detectType, readZipEntries, summarizeZip, scanPdf, scanHtml, scanRtf, scanOle, sha256Hex };
});
