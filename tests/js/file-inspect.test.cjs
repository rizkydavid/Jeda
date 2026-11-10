// Jalankan dari akar proyek: node --test
//
// Ditulis sebagai .cjs dan memuat file lewat `vm` karena package.json proyek memakai "type": "module"
// (bawaan Laravel), sedangkan public/js/file-inspect.js adalah skrip browser (UMD), bukan ES module.
const test = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const code = fs.readFileSync(path.join(__dirname, '../../public/js/file-inspect.js'), 'utf8');
const sandbox = { module: { exports: {} }, TextDecoder, TextEncoder, console, Blob, DecompressionStream, crypto: globalThis.crypto };
vm.runInNewContext(code, sandbox, { filename: 'file-inspect.js' });
const { detectType } = sandbox.module.exports;

const enc = (text) => new TextEncoder().encode(text);

function fakePe({ lfanew = 0x80, withSignature = true, size = 4096 } = {}) {
  const b = new Uint8Array(size);
  b[0] = 0x4d; b[1] = 0x5a;
  new DataView(b.buffer).setUint32(0x3c, lfanew, true);
  if (withSignature && lfanew + 4 <= size) b.set([0x50, 0x45, 0x00, 0x00], lfanew);
  for (let i = 2; i < 0x3c; i++) b[i] = 0x90 + (i % 7); // isi biner, bukan teks
  return b;
}

test('program Windows asli (tanda PE valid) terdeteksi sebagai pe', () => {
  assert.strictEqual(detectType(fakePe()), 'pe');
  assert.strictEqual(detectType(fakePe({ lfanew: 0x200 })), 'pe');
});

test('stub DOS panjang dengan penunjuk di luar byte awal tetap pe', () => {
  const b = fakePe({ lfanew: 0x2000, withSignature: false });
  assert.strictEqual(detectType(b.subarray(0, 4096)), 'pe');
});

test('teks biasa yang diawali "MZ" BUKAN program', () => {
  assert.strictEqual(detectType(enc('MZ Laporan keuangan 2026 untuk rapat bulan depan. '.repeat(20))), 'text');
  assert.strictEqual(detectType(enc('MZ')), 'text');
  assert.strictEqual(detectType(enc('MZ-2024 catatan\nbaris kedua\n')), 'text');
});

test('biner berawalan MZ tanpa tanda PE (program DOS) tetap dianggap program', () => {
  const b = fakePe({ lfanew: 0, withSignature: false });
  assert.strictEqual(detectType(b), 'pe');
});

test('jenis lain tidak berubah', () => {
  assert.strictEqual(detectType(enc('%PDF-1.7\n')), 'pdf');
  assert.strictEqual(detectType(new Uint8Array([0x7f, 0x45, 0x4c, 0x46, 2, 1, 1, 0])), 'elf');
  assert.strictEqual(detectType(new Uint8Array([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a])), 'png');
  assert.strictEqual(detectType(enc('#!/bin/sh\necho hi\n')), 'script');
});

// ---------------------------------------------------------------- EICAR
// String EICAR disusun dari potongan agar antivirus di komputer tidak menandai file tes ini sebagai virus.
const EICAR = ['X5O!P%@AP[4\\PZX54(P^)7CC)7}$', 'EI' + 'CAR', '-STANDARD-ANTIVIRUS-TEST-FILE!$H+H*'].join('');

const { analyzeBytes, containsEicar } = sandbox.module.exports;

function pdfWith(extra) {
  return enc('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj\n' + extra + '\ntrailer << /Root 1 0 R >>\n%%EOF\n');
}

test('EICAR di dalam PDF terdeteksi, dan jenisnya tetap pdf', async () => {
  const bytes = pdfWith('% ' + EICAR);
  assert.strictEqual(containsEicar(bytes), true);
  const result = await analyzeBytes(bytes, {});
  assert.strictEqual(result.detected_type, 'pdf');
  assert.strictEqual(result.flags.eicar_test, true);
});

test('EICAR di tengah file besar dan di akhir file tetap ketemu', () => {
  const big = new Uint8Array(3 * 1024 * 1024).fill(0x41);
  const tail = enc(EICAR);
  const atEnd = new Uint8Array(big.length + tail.length);
  atEnd.set(big); atEnd.set(tail, big.length);
  assert.strictEqual(containsEicar(atEnd), true);

  const mid = new Uint8Array(big.length + tail.length);
  mid.set(big.subarray(0, 1000000)); mid.set(tail, 1000000); mid.set(big.subarray(1000000), 1000000 + tail.length);
  assert.strictEqual(containsEicar(mid), true);
});

test('bukan EICAR: PDF biasa, teks mirip tetapi terpotong, dan huruf awal berulang', async () => {
  assert.strictEqual(containsEicar(pdfWith('% dokumen biasa')), false);
  assert.strictEqual(containsEicar(enc(EICAR.slice(0, -1))), false);        // kurang satu karakter
  assert.strictEqual(containsEicar(enc(EICAR.replace('EI' + 'CAR', 'EI' + 'CAX'))), false);
  assert.strictEqual(containsEicar(enc('X'.repeat(500))), false);
  assert.strictEqual(containsEicar(new Uint8Array(0)), false);
  const clean = await analyzeBytes(pdfWith('% dokumen biasa'), {});
  assert.strictEqual(clean.flags.eicar_test, undefined);
});


// ---------------------------------------------------------------- SHA-256 bertahap
const { createSha256, inspect } = sandbox.module.exports;
const hexOf = async (bytes) => Buffer.from(await crypto.subtle.digest('SHA-256', bytes)).toString('hex');

test('SHA-256 bertahap cocok dengan SubtleCrypto (vektor baku)', async () => {
  const abc = createSha256(); abc.update(enc('abc'));
  assert.strictEqual(abc.hex(), 'ba7816bf8f01cfea414140de5dae2223b00361a396177a9cb410ff61f20015ad');
  const empty = createSha256();
  assert.strictEqual(empty.hex(), 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855');
});

test('SHA-256 bertahap benar untuk semua panjang di sekitar batas blok, apa pun cara datanya dipotong', async () => {
  for (const len of [0, 1, 55, 56, 57, 63, 64, 65, 119, 120, 127, 128, 129, 300]) {
    const data = crypto.getRandomValues(new Uint8Array(len));
    const expected = await hexOf(data);
    const whole = createSha256(); whole.update(data);
    assert.strictEqual(whole.hex(), expected, `utuh len=${len}`);
    const pieces = createSha256();
    for (let i = 0; i < len; i += 7) pieces.update(data.subarray(i, Math.min(i + 7, len)));
    assert.strictEqual(pieces.hex(), expected, `dipotong 7 byte len=${len}`);
  }
});

test('SHA-256 bertahap untuk data besar dengan potongan tak beraturan', async () => {
  const data = new Uint8Array(5 * 1024 * 1024 + 123);
  for (let i = 0; i < data.length; i += 65536) crypto.getRandomValues(data.subarray(i, Math.min(i + 65536, data.length)));
  const h = createSha256();
  let pos = 0; let step = 1000;
  while (pos < data.length) { h.update(data.subarray(pos, Math.min(pos + step, data.length))); pos += step; step = (step * 3 + 17) % 900000 + 1; }
  assert.strictEqual(h.hex(), await hexOf(data));
});

// ---------------------------------------------------------------- file besar (dibaca sebagian)
const CFG = { maxBytes: 1024 * 1024, maxLargeBytes: 64 * 1024 * 1024, executable: ['exe', 'apk', 'js', 'bat'], decoy: ['pdf', 'jpg', 'doc'] };

/** File tiruan yang mencatat potongan terbesar yang pernah dibaca, supaya terbukti file tidak dimuat utuh. */
function spyFile(bytes, name) {
  const real = new File([bytes], name);
  const spy = { name, size: bytes.length, maxRead: 0, slice(a, b) { spy.maxRead = Math.max(spy.maxRead, b - a); return real.slice(a, b); } };
  return spy;
}

const u16le = (n) => [n & 255, (n >> 8) & 255];
const u32le = (n) => [n & 255, (n >> 8) & 255, (n >> 16) & 255, (n >>> 24) & 255];

/** ZIP minimal tanpa kompresi (method 0). */
function makeZip(entries) {
  const parts = []; const central = []; let offset = 0;
  for (const { name, data } of entries) {
    const nameBytes = enc(name);
    const local = Uint8Array.from([0x50, 0x4b, 0x03, 0x04, 20, 0, 0, 0, 0, 0, 0, 0, 0, 0, ...u32le(0), ...u32le(data.length), ...u32le(data.length), ...u16le(nameBytes.length), 0, 0, ...nameBytes]);
    central.push(Uint8Array.from([0x50, 0x4b, 0x01, 0x02, 20, 0, 20, 0, 0, 0, 0, 0, 0, 0, 0, 0, ...u32le(0), ...u32le(data.length), ...u32le(data.length), ...u16le(nameBytes.length), 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, 0, ...u32le(offset), ...nameBytes])); // extra, komentar, disk, atribut internal (2 byte masing-masing) + atribut eksternal (4)
    parts.push(local, data); offset += local.length + data.length;
  }
  const cdSize = central.reduce((n, c) => n + c.length, 0);
  const eocd = Uint8Array.from([0x50, 0x4b, 0x05, 0x06, 0, 0, 0, 0, ...u16le(entries.length), ...u16le(entries.length), ...u32le(cdSize), ...u32le(offset), 0, 0]);
  return Buffer.concat([...parts, ...central, eocd].map((p) => Buffer.from(p)));
}

/** AndroidManifest.xml biner (AXML) minimal: <manifest package=...> dan satu <uses-permission>. */
function makeAxml(pkg, permissions) {
  const strings = ['package', 'name', 'manifest', 'uses-permission', pkg, ...permissions];
  const encoded = strings.map((t) => { const b = enc(t); return Uint8Array.from([t.length, b.length, ...b, 0]); });
  const offsets = []; let o = 0; for (const e of encoded) { offsets.push(o); o += e.length; }
  const stringsStart = 28 + 4 * strings.length;
  const poolSize = (stringsStart + o + 3) & ~3;
  const pool = new Uint8Array(poolSize);
  pool.set([...u16le(1), ...u16le(28), ...u32le(poolSize), ...u32le(strings.length), ...u32le(0), ...u32le(0x100), ...u32le(stringsStart), ...u32le(0)]);
  offsets.forEach((off, i) => pool.set(u32le(off), 28 + i * 4));
  let p = stringsStart; for (const e of encoded) { pool.set(e, p); p += e.length; }

  const element = (nameIdx, attrs) => {
    const size = 36 + attrs.length * 20;
    const b = new Uint8Array(size);
    b.set([...u16le(0x0102), ...u16le(16), ...u32le(size), ...u32le(1), ...u32le(0xffffffff), ...u32le(0xffffffff), ...u32le(nameIdx), ...u16le(20), ...u16le(20), ...u16le(attrs.length), 0, 0, 0, 0, 0, 0]);
    attrs.forEach(([attrName, valueIdx], i) => {
      b.set([...u32le(0xffffffff), ...u32le(attrName), ...u32le(valueIdx), ...u16le(8), 0, 0x03, ...u32le(valueIdx)], 36 + i * 20);
    });
    return b;
  };
  const body = [pool, element(2, [[0, 4]]), ...permissions.map((_, i) => element(3, [[1, 5 + i]]))];
  const total = 8 + body.reduce((n, c) => n + c.length, 0);
  return Buffer.concat([Buffer.from([...u16le(3), ...u16le(8), ...u32le(total)]), ...body.map((c) => Buffer.from(c))]);
}

test('APK besar: paket dan izin dibaca langsung dari manifest tanpa memuat seluruh file', async () => {
  const manifest = makeAxml('com.contoh.aplikasi', ['android.permission.READ_SMS', 'android.permission.INTERNET']);
  const filler = new Uint8Array(9 * 1024 * 1024).fill(7);
  const apk = makeZip([
    { name: 'AndroidManifest.xml', data: manifest },
    { name: 'classes.dex', data: enc('dex\n035\0 palsu') },
    { name: 'assets/besar.bin', data: filler },
  ]);
  const file = spyFile(apk, 'tagihan.apk');

  const r = await inspect(file, CFG);
  assert.strictEqual(r.content_scanned, true);
  assert.strictEqual(r.detected_type, 'apk');
  assert.strictEqual(r.apk_package, 'com.contoh.aplikasi');
  assert.deepStrictEqual(Array.from(r.apk_permissions).sort(), ['android.permission.INTERNET', 'android.permission.READ_SMS']); // Array.from: hasil dari konteks vm
  assert.strictEqual(r.sha256, await hexOf(apk));
  assert.ok(file.maxRead <= 4 * 1024 * 1024, `potongan terbesar ${file.maxRead} melebihi 4 MB`);
  assert.strictEqual(r.content_partial, undefined, 'daftar isi arsip lengkap, jadi bukan pembacaan sebagian');
});

test('ZIP besar berisi program bertopeng tetap terdeteksi dari daftar isinya', async () => {
  const zip = makeZip([
    { name: 'foto-liburan.jpg.exe', data: enc('MZ palsu') },
    { name: 'padding.bin', data: new Uint8Array(3 * 1024 * 1024) },
  ]);
  const r = await inspect(spyFile(zip, 'arsip.zip'), CFG);
  assert.strictEqual(r.detected_type, 'zip');
  assert.strictEqual(r.flags.archive_executable, true);
  assert.strictEqual(r.flags.archive_double_extension, true);
});

test('EICAR yang terbelah di batas potongan tetap ketemu di file besar', async () => {
  const bytes = new Uint8Array(9 * 1024 * 1024).fill(0x20);
  bytes.set(enc('%PDF-1.4\n'), 0);
  bytes.set(enc(EICAR), 4 * 1024 * 1024 - 30); // sebagian di potongan 1, sebagian di potongan 2
  const r = await inspect(spyFile(bytes, 'laporan.pdf'), CFG);
  assert.strictEqual(r.flags.eicar_test, true);
  assert.strictEqual(r.sha256, await hexOf(bytes));
});

test('PDF besar: tanda di awal file terbaca, dan dilaporkan sebagai pembacaan sebagian', async () => {
  const bytes = new Uint8Array(9 * 1024 * 1024).fill(0x20);
  bytes.set(enc('%PDF-1.4\n1 0 obj << /OpenAction << /S /JavaScript /JS (app.alert(1)) >> >> endobj\n'), 0);
  const r = await inspect(spyFile(bytes, 'besar.pdf'), CFG);
  assert.strictEqual(r.detected_type, 'pdf');
  assert.strictEqual(r.flags.pdf_javascript, true);
  assert.strictEqual(r.flags.pdf_openaction, true);
  assert.strictEqual(r.content_partial, true);
});

test('PDF kecil (di bawah batas baca penuh) bukan pembacaan sebagian', async () => {
  const r = await inspect(new File([pdfWith('% biasa')], 'kecil.pdf'), CFG);
  assert.strictEqual(r.content_partial, undefined);
  assert.strictEqual(r.content_scanned, true);
});

test('file melebihi batas maksimum tidak dibaca sama sekali (hanya nama dan ukuran)', async () => {
  const fake = { name: 'raksasa.iso', size: 100 * 1024 * 1024, slice() { throw new Error('tidak boleh dibaca'); }, arrayBuffer() { throw new Error('tidak boleh dibaca'); } };
  const r = await inspect(fake, CFG);
  assert.strictEqual(r.content_scanned, false);
  assert.strictEqual(r.sha256, null);
  assert.strictEqual(r.detected_type, null);
});

test('progres dilaporkan saat file besar dihash', async () => {
  const bytes = new Uint8Array(9 * 1024 * 1024).fill(1);
  const seen = [];
  await inspect(spyFile(bytes, 'x.bin'), CFG, (p) => seen.push(p.done / p.total));
  assert.ok(seen.length >= 3);
  assert.strictEqual(seen[seen.length - 1], 1);
  assert.deepStrictEqual([...seen].sort((a, b) => a - b), seen, 'progres harus naik');
});

// ---------------------------------------------------------------- PDF: deteksi JavaScript (pola /JS)
const { scanPdf, pickPermissions } = sandbox.module.exports;

test('JavaScript PDF yang sungguhan tetap terdeteksi (semua bentuk penulisan /JS)', async () => {
  const forms = [
    '<< /S /JavaScript /JS (app.alert\\(1\\)) >>',   // string biasa
    '<< /S /JavaScript /JS(app.alert\\(1\\)) >>',    // tanpa spasi
    '<< /S /JavaScript /JS <6170702e616c657274> >>', // string heksadesimal
    '<< /S /JavaScript /JS 12 0 R >>',               // rujukan ke stream skrip
    '<< /JS\n(app.alert\\(1\\)) >>',                 // tanpa /S /JavaScript, baris baru sebelum string
    '<< /S /JavaScript >>',                          // hanya penanda tipe
    '<< /S /J#61vaScript /JS (x) >>',                // nama disamarkan dengan escape heksadesimal
    '<< /J#53 (x) >>',                               // /JS ditulis /J#53
  ];
  for (const form of forms) {
    assert.strictEqual(scanPdf(form).pdf_javascript, true, `harus terdeteksi: ${form}`);
    const r = await analyzeBytes(pdfWith(form), {});
    assert.strictEqual(r.flags.pdf_javascript, true, `analyzeBytes harus mendeteksi: ${form}`);
  }
});

test('teks yang hanya mirip /JS bukan JavaScript PDF', () => {
  const lookalikes = ['/JSON', '/JS', '/JSX (x)', '/JS/Type', '/JS endobj', '/JSR 1 0 R', '/Jsx (x)', '/JS 12', '/JS 12 0 obj', '/js (x)', 'JS (x)', '/Type /Page /JavaScr1pt'];
  for (const text of lookalikes) assert.strictEqual(scanPdf(`<< ${text} >>`).pdf_javascript, undefined, `tidak boleh terdeteksi: ${text}`);
  assert.deepStrictEqual(Object.keys(scanPdf('%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj')), []);
});

test('data acak (stream terkompresi) tidak salah dideteksi sebagai JavaScript PDF', () => {
  // PRNG berbenih tetap: tes harus deterministik. Dengan pola lama /JS\b, data seperti ini salah ditandai ±3% per MB.
  let x = 0x2545f491;
  const next = () => { x ^= x << 13; x >>>= 0; x ^= x >>> 17; x ^= x << 5; x >>>= 0; return x & 0xff; };
  let falsePositives = 0;
  for (let i = 0; i < 200; i++) {
    const b = new Uint8Array(1024 * 1024);
    for (let j = 0; j < b.length; j++) b[j] = next();
    if (scanPdf(Buffer.from(b).toString('latin1')).pdf_javascript) falsePositives++;
  }
  assert.strictEqual(falsePositives, 0);
});

test('"/JS" yang kebetulan muncul di data biner tidak cukup untuk menandai PDF', async () => {
  // Urutan byte yang SENGAJA dibuat memicu pola lama: "/JS" diikuti byte non-huruf.
  const junk = new Uint8Array(4096).fill(0x9c);
  for (let i = 0; i < junk.length - 8; i += 64) junk.set([0x2f, 0x4a, 0x53, 0x01, 0xff, 0x00], i);
  const r = await analyzeBytes(Buffer.concat([Buffer.from(pdfWith('stream\n')), Buffer.from(junk), Buffer.from('\nendstream')]), {});
  assert.strictEqual(r.detected_type, 'pdf');
  assert.strictEqual(r.flags.pdf_javascript, undefined);
});

// ---------------------------------------------------------------- APK: batas dan penyaringan izin
const SMS = 'android.permission.READ_SMS';
const KNOWN = [SMS, 'android.permission.BIND_ACCESSIBILITY_SERVICE', 'android.permission.INTERNET'];

test('pickPermissions: nama tidak valid dibuang, duplikat digabung, jumlah dibatasi 500', () => {
  const out = Array.from(pickPermissions([SMS, SMS, 'bukan izin!', '<script>', '', 42, null, 'a'.repeat(121), 'com.vendor.permission.X'], KNOWN));
  assert.deepStrictEqual(out, [SMS, 'com.vendor.permission.X']);
  const many = Array.from({ length: 800 }, (_, i) => `com.junk.P${i}`);
  assert.strictEqual(pickPermissions(many, KNOWN).length, 500);
});

test('pickPermissions: izin berisiko tetap terkirim walau didorong 700 izin pengisi', () => {
  const junk = Array.from({ length: 700 }, (_, i) => `com.junk.P${i}`);
  const out = Array.from(pickPermissions([...junk, SMS, 'android.permission.BIND_ACCESSIBILITY_SERVICE'], KNOWN));
  assert.strictEqual(out.length, 500);
  assert.deepStrictEqual(out.slice(0, 2), [SMS, 'android.permission.BIND_ACCESSIBILITY_SERVICE']);
});

test('pickPermissions: tanpa daftar izin dikenal tetap berfungsi (hanya dibatasi 500)', () => {
  assert.strictEqual(pickPermissions([SMS, 'x.y'], undefined).length, 2);
  assert.strictEqual(pickPermissions([SMS, 'x.y'], []).length, 2);
});

test('APK dengan 600 izin pengisi: READ_SMS di urutan terakhir tetap sampai ke server', async () => {
  const junk = Array.from({ length: 600 }, (_, i) => `com.junk.P${i}`);
  const apk = makeZip([
    { name: 'AndroidManifest.xml', data: makeAxml('com.contoh.aplikasi', [...junk, SMS]) },
    { name: 'classes.dex', data: enc('dex\n035\0 palsu') },
    { name: 'assets/besar.bin', data: new Uint8Array(2 * 1024 * 1024).fill(7) }, // di atas CFG.maxBytes: jalur file besar
  ]);
  const r = await inspect(spyFile(apk, 'undangan.apk'), { ...CFG, apkKnown: KNOWN });
  assert.strictEqual(r.detected_type, 'apk');
  assert.strictEqual(r.apk_permissions.length, 500);
  assert.strictEqual(r.apk_permissions[0], SMS);
});

// ---------------------------------------------------------------- PDF: EICAR di dalam stream terkompresi / string ber-escape
const zlib = require('node:zlib');
const { pdfHiddenEicar } = sandbox.module.exports;

/** Encoder ASCII85 (hanya untuk tes). */
function a85(buf) {
  let out = '<~';
  for (let i = 0; i < buf.length; i += 4) {
    const chunk = buf.subarray(i, i + 4); const n = chunk.length;
    let v = 0; for (let k = 0; k < 4; k++) v = v * 256 + (chunk[k] || 0);
    if (n === 4 && v === 0) { out += 'z'; continue; }
    let g = ''; for (let k = 0; k < 5; k++) { g = String.fromCharCode(33 + (v % 85)) + g; v = Math.floor(v / 85); }
    out += g.slice(0, n + 1);
  }
  return out + '~>';
}
const pdfEscape = (t) => t.replace(/[\\()]/g, (c) => '\\' + c);
function pdfWithStream(filterNames, payload) {
  const filt = filterNames.length === 0 ? '' : filterNames.length === 1 ? `/Filter /${filterNames[0]} ` : `/Filter [ ${filterNames.map((n) => '/' + n).join(' ')} ] `;
  return Buffer.concat([
    Buffer.from('%PDF-1.3\n1 0 obj\n<< /Type /Catalog >>\nendobj\n9 0 obj\n<< ' + filt + '/Length ' + payload.length + ' >>\nstream\n'),
    Buffer.from(payload), Buffer.from('\nendstream\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n'),
  ]);
}
const content = (text) => Buffer.from(`BT /F3 11 Tf 72 612 Td (${pdfEscape(text)}) Tj ET\n`, 'latin1');
const asBytes = (b) => new Uint8Array(b);

test('PDF nyata dengan EICAR di stream ASCII85+Flate terdeteksi (berkas contoh)', async () => {
  const real = fs.readFileSync(path.join(__dirname, 'fixtures/eicar_dalam_stream.pdf'));
  assert.strictEqual(containsEicar(asBytes(real)), false, 'byte mentahnya memang tidak memuat EICAR');
  const r = await analyzeBytes(asBytes(real), {});
  assert.strictEqual(r.detected_type, 'pdf');
  assert.strictEqual(r.flags.eicar_test, true);
});

test('EICAR di PDF terdeteksi untuk semua kombinasi filter yang didukung', async () => {
  const body = content(EICAR);
  const flate = zlib.deflateSync(body);
  const variants = {
    'Flate': [['FlateDecode'], flate],
    'ASCII85': [['ASCII85Decode'], Buffer.from(a85(body))],
    'ASCIIHex': [['ASCIIHexDecode'], Buffer.from(body.toString('hex') + '>')],
    'ASCII85+Flate': [['ASCII85Decode', 'FlateDecode'], Buffer.from(a85(flate))],
    'ASCIIHex+Flate': [['ASCIIHexDecode', 'FlateDecode'], Buffer.from(flate.toString('hex') + '>')],
    'singkatan A85/Fl': [['A85', 'Fl'], Buffer.from(a85(flate))],
    'tanpa filter, string ber-escape': [[], body],
  };
  for (const [nama, [filters, payload]] of Object.entries(variants)) {
    const pdf = asBytes(pdfWithStream(filters, payload));
    assert.strictEqual(containsEicar(pdf), false, `${nama}: byte mentah tidak boleh sudah cocok`);
    assert.strictEqual(await pdfHiddenEicar(pdf), true, `${nama}: harus terdeteksi`);
    assert.strictEqual((await analyzeBytes(pdf, {})).flags.eicar_test, true, `${nama}: analyzeBytes harus menandai`);
  }
});

test('string EICAR ber-escape di luar stream (tanpa kompresi) terdeteksi, termasuk escape oktal', async () => {
  const pdf = asBytes(Buffer.from('%PDF-1.4\n1 0 obj << /T (' + pdfEscape(EICAR) + ') >> endobj\n%%EOF'));
  assert.strictEqual(await pdfHiddenEicar(pdf), true);
  const octal = pdfEscape(EICAR).replace(/-/g, '\\055'); // "-" ditulis sebagai \055
  assert.ok(octal.includes('\\055'));
  assert.strictEqual(await pdfHiddenEicar(asBytes(Buffer.from('%PDF-1.4\n1 0 obj << /T (' + octal + ') >> endobj\n%%EOF'))), true);
});

test('PDF biasa dan stream tanpa EICAR tidak ditandai', async () => {
  const biasa = content('Laporan keuangan (rahasia) \\ 2026. Tidak ada apa-apa di sini.');
  const cases = [
    pdfWithStream(['FlateDecode'], zlib.deflateSync(biasa)),
    pdfWithStream(['ASCII85Decode', 'FlateDecode'], Buffer.from(a85(zlib.deflateSync(biasa)))),
    pdfWithStream(['ASCIIHexDecode'], Buffer.from(biasa.toString('hex') + '>')),
    pdfWithStream([], biasa),
    pdfWith('% dokumen kosong') && Buffer.from(pdfWith('% dokumen kosong')),
  ];
  for (const pdf of cases) {
    assert.strictEqual(await pdfHiddenEicar(asBytes(pdf)), false);
    assert.strictEqual((await analyzeBytes(asBytes(pdf), {})).flags.eicar_test, undefined);
  }
});

test('EICAR yang hampir lengkap (kurang satu karakter) di dalam stream tidak ditandai', async () => {
  const hampir = content(EICAR.slice(0, -1));
  assert.strictEqual(await pdfHiddenEicar(asBytes(pdfWithStream(['FlateDecode'], zlib.deflateSync(hampir)))), false);
});

test('PDF dengan stream rusak, terpotong, atau filter tak dikenal tidak error dan tidak ditandai', async () => {
  const flate = zlib.deflateSync(content(EICAR));
  const rusak = [
    pdfWithStream(['FlateDecode'], flate.subarray(0, flate.length - 6)),                // terpotong
    pdfWithStream(['FlateDecode'], Buffer.from('bukan data zlib sama sekali')),          // bukan zlib
    pdfWithStream(['ASCII85Decode'], Buffer.from('~~~ ÿ rusak')),                    // ASCII85 tidak sah
    pdfWithStream(['ASCIIHexDecode'], Buffer.from('ZZZZ>')),                              // heks tidak sah
    pdfWithStream(['LZWDecode'], flate),                                                  // filter yang tidak didukung
    pdfWithStream(['DCTDecode'], flate),                                                  // gambar JPEG
    Buffer.from('%PDF-1.3\n9 0 obj\n<< /Filter /FlateDecode >>\nstream\n' + 'x'.repeat(100)), // tanpa endstream
    Buffer.from('%PDF-1.3\nstream\n\nendstream\nstream\r\n'),                              // kosong dan tanpa dict
  ];
  for (const pdf of rusak) assert.strictEqual(await pdfHiddenEicar(asBytes(pdf)), false);
});

test('stream berukuran raksasa setelah dekompresi (zip bomb) dihentikan tanpa menandai apa pun', async () => {
  const bomb = zlib.deflateSync(Buffer.alloc(40 * 1024 * 1024)); // ±40 KB terkompresi, 40 MB setelah dibuka
  const t0 = Date.now();
  assert.strictEqual(await pdfHiddenEicar(asBytes(pdfWithStream(['FlateDecode'], bomb))), false);
  assert.ok(Date.now() - t0 < 5000, 'harus cepat berhenti');
});

test('data acak di dalam stream ber-filter tidak pernah menghasilkan EICAR palsu', async () => {
  let x = 0x1badf00d;
  const next = () => { x ^= x << 13; x >>>= 0; x ^= x >>> 17; x ^= x << 5; x >>>= 0; return x & 0xff; };
  for (let i = 0; i < 20; i++) {
    const rnd = Buffer.alloc(64 * 1024); for (let j = 0; j < rnd.length; j++) rnd[j] = next();
    for (const [f, p] of [[['FlateDecode'], rnd], [['ASCII85Decode'], Buffer.from(a85(rnd))], [['ASCIIHexDecode'], Buffer.from(rnd.toString('hex') + '>')], [['FlateDecode'], zlib.deflateSync(rnd)]]) {
      assert.strictEqual(await pdfHiddenEicar(asBytes(pdfWithStream(f, p))), false);
    }
  }
});

test('EICAR tersembunyi di PDF besar juga terdeteksi lewat jalur file besar (dibaca bertahap)', async () => {
  const eicarPdf = pdfWithStream(['ASCII85Decode', 'FlateDecode'], Buffer.from(a85(zlib.deflateSync(content(EICAR)))));
  const besar = Buffer.concat([eicarPdf, Buffer.alloc(2 * 1024 * 1024, 0x20)]); // di atas CFG.maxBytes
  const r = await inspect(spyFile(asBytes(besar), 'tagihan.pdf'), CFG);
  assert.strictEqual(r.detected_type, 'pdf');
  assert.strictEqual(r.flags.eicar_test, true);
  const bersih = Buffer.concat([pdfWithStream(['FlateDecode'], zlib.deflateSync(content('biasa'))), Buffer.alloc(2 * 1024 * 1024, 0x20)]);
  assert.strictEqual((await inspect(spyFile(asBytes(bersih), 'tagihan.pdf'), CFG)).flags.eicar_test, undefined);
});

test('pdfHiddenEicar tidak mengganggu deteksi lain: JavaScript di PDF berkompresi tetap ditandai oleh pemindai lama', async () => {
  const r = await analyzeBytes(asBytes(pdfWith('<< /S /JavaScript /JS (app.alert\\(1\\)) >>')), {});
  assert.strictEqual(r.flags.pdf_javascript, true);
  assert.strictEqual(r.flags.eicar_test, undefined);
});
