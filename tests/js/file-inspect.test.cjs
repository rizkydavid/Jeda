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
const sandbox = { module: { exports: {} }, TextDecoder, TextEncoder, console };
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
