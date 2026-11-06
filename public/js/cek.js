/*
 * Halaman Cek: pemeriksaan tautan dan nama file.
 * Tautan tidak dibuka dan file tidak diunggah/dibaca; server hanya menganalisis teksnya.
 */
(() => {
  'use strict';

  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (s, r = document) => r.querySelector(s);

  const SEVERITY_CLASS = { high: 't-rose', medium: 't-amber', none: 't-none' };

  function el(tag, props = {}, children = []) {
    const node = document.createElement(tag);
    Object.entries(props).forEach(([k, v]) => {
      if (k === 'class') node.className = v;
      else if (k === 'text') node.textContent = v;
      else node.setAttribute(k, v);
    });
    children.forEach(c => c && node.appendChild(c));
    return node;
  }

  async function postJson(url, body) {
    let response;
    try {
      response = await fetch(url, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify(body),
      });
    } catch (_) {
      throw new Error('Tidak bisa terhubung ke server. Periksa koneksi, lalu coba lagi.');
    }
    const data = await response.json().catch(() => null);
    if (response.ok && data?.ok) return data;
    if (response.status === 419) throw new Error('Sesi halaman sudah kedaluwarsa. Muat ulang halaman, lalu coba lagi.');
    if (response.status === 429) throw new Error('Terlalu banyak permintaan. Tunggu sebentar, lalu coba lagi.');
    if (response.status === 422) {
      const firstError = data?.errors ? Object.values(data.errors)[0]?.[0] : null;
      throw new Error(data?.message || firstError || 'Masukan belum valid.');
    }
    throw new Error('Pemeriksaan belum bisa dijalankan di server. Coba lagi sebentar.');
  }

  function renderResult(box, emptyEl, data, title) {
    const level = data.level || 'Belum terdeteksi';
    const head = el('div', { class: 'cek-head' }, [
      el('span', { class: 'tag ' + (SEVERITY_CLASS[data.severity] || 't-none'), text: level }),
      title ? el('span', { class: 'cek-host', text: title }) : null,
    ]);

    const parts = [head];

    if (data.indicators?.length) {
      parts.push(el('div', {}, [
        el('b', { text: 'Tanda yang ditemukan' }),
        el('ul', { class: 'cek-list' }, data.indicators.map(t => el('li', { text: t }))),
      ]));
    } else {
      parts.push(el('p', { class: 'mut', style: 'margin:0', text: 'Belum ada tanda dari pola alamat. Ini bukan jaminan aman.' }));
    }

    (data.notes || []).forEach(n => parts.push(el('p', { class: 'small', style: 'margin:0', text: n })));

    if (data.advice?.length) {
      parts.push(el('div', { class: 'cek-advice' }, [
        el('b', { text: 'Langkah yang disarankan' }),
        el('ul', {}, data.advice.map(t => el('li', { text: t }))),
      ]));
    }
    if (data.reputation === 'bersih') parts.push(el('p', { class: 'small', style: 'margin:0', text: 'Reputasi: tidak ada di daftar Google Safe Browsing.' }));
    else if (data.reputation === 'gagal') parts.push(el('p', { class: 'small', style: 'margin:0', text: 'Pengecekan reputasi sedang tidak tersedia; hasil di atas hanya dari pola alamat.' }));
    else if (data.reputation === 'tidak-aktif') parts.push(el('p', { class: 'small', style: 'margin:0', text: 'Pengecekan reputasi belum diaktifkan; hasil di atas hanya dari pola alamat.' }));
    if (data.message) parts.push(el('p', { class: 'small', style: 'margin:0', text: data.message }));

    box.replaceChildren(...parts);
    box.hidden = false;
    if (emptyEl) emptyEl.hidden = true;
  }

  function renderError(box, emptyEl, message) {
    box.replaceChildren(el('div', { class: 'cek-error', role: 'alert', text: message }));
    box.hidden = false;
    if (emptyEl) emptyEl.hidden = true;
  }

  function wire({ formId, inputId, buttonId, boxId, emptyId, url, field, idleLabel, busyLabel, title, extra }) {
    const form = document.getElementById(formId);
    const input = document.getElementById(inputId);
    const button = document.getElementById(buttonId);
    const box = document.getElementById(boxId);
    const emptyEl = document.getElementById(emptyId);
    if (!form || !input || !box) return;

    let busy = false;
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (busy) return;
      const value = input.value.trim();
      if (!value) {
        renderError(box, emptyEl, field === 'url' ? 'Tempel tautan yang ingin diperiksa dulu.' : 'Tulis nama file yang ingin diperiksa dulu.');
        input.focus();
        return;
      }
      busy = true;
      if (button) { button.disabled = true; button.textContent = busyLabel; }
      try {
        const data = await postJson(url, { [field]: value, ...(extra ? extra(value) : {}) });
        renderResult(box, emptyEl, data, title(data));
      } catch (error) {
        renderError(box, emptyEl, error.message);
      } finally {
        busy = false;
        if (button) { button.disabled = false; button.textContent = idleLabel; }
      }
    });
  }

  wire({
    formId: 'linkForm', inputId: 'linkInput', buttonId: 'linkButton', boxId: 'linkResult', emptyId: 'linkEmpty',
    url: '/jeda/api/cek-tautan', field: 'url', idleLabel: 'Cek tautan', busyLabel: 'Memeriksa...',
    title: d => d.hostname,
  });

  /* ---- Pilih file dari komputer: hanya nama dan 16 byte awal yang dibaca, tidak ada yang diunggah ---- */
  function sniff(b) {
    const s = String.fromCharCode(...b.slice(0, 8));
    if (b[0] === 0x4d && b[1] === 0x5a) return 'exe';
    if (b[0] === 0x7f && s.slice(1, 4) === 'ELF') return 'elf';
    if (s.startsWith('%PDF')) return 'pdf';
    if (b[0] === 0x89 && s.slice(1, 4) === 'PNG') return 'png';
    if (b[0] === 0xff && b[1] === 0xd8 && b[2] === 0xff) return 'jpg';
    if (s.startsWith('GIF8')) return 'gif';
    if (b[0] === 0x50 && b[1] === 0x4b && (b[2] === 3 || b[2] === 5)) return 'zip';
    if (s.startsWith('Rar!')) return 'rar';
    if (b[0] === 0x37 && b[1] === 0x7a && b[2] === 0xbc && b[3] === 0xaf) return '7z';
    if (b[0] === 0x1f && b[1] === 0x8b) return 'gz';
    if (s.startsWith('#!')) return 'script';
    return 'unknown';
  }
  const readHead = file => new Promise(resolve => {
    const reader = new FileReader();
    reader.onload = () => resolve(new Uint8Array(reader.result));
    reader.onerror = () => resolve(new Uint8Array());
    reader.readAsArrayBuffer(file.slice(0, 16));
  });
  const humanSize = n => n < 1024 ? n + ' B' : n < 1048576 ? (n / 1024).toFixed(1) + ' KB' : (n / 1048576).toFixed(1) + ' MB';

  let picked = null; // { name, detected }
  const picker = document.getElementById('filePicker');
  const zone = document.getElementById('dropZone');
  const nameInput = document.getElementById('fileNameCheck');
  const info = document.getElementById('pickedInfo');
  const fileForm = document.getElementById('fileForm');

  async function takeFile(file) {
    if (!file) return;
    const detected = sniff(await readHead(file));
    picked = { name: file.name, detected };
    nameInput.value = file.name;
    info.textContent = 'Terpilih: ' + file.name + ' (' + humanSize(file.size) + '). File tidak diunggah.';
    info.hidden = false;
    fileForm.requestSubmit ? fileForm.requestSubmit() : fileForm.dispatchEvent(new Event('submit', { cancelable: true, bubbles: true }));
  }

  picker?.addEventListener('change', () => { takeFile(picker.files?.[0]); picker.value = ''; });
  nameInput?.addEventListener('input', () => { if (picked && nameInput.value.trim() !== picked.name) { picked = null; info.hidden = true; } });
  ['dragenter', 'dragover'].forEach(t => zone?.addEventListener(t, e => { e.preventDefault(); zone.classList.add('is-over'); }));
  ['dragleave', 'drop'].forEach(t => zone?.addEventListener(t, e => { e.preventDefault(); zone.classList.remove('is-over'); }));
  zone?.addEventListener('drop', e => takeFile(e.dataTransfer?.files?.[0]));

  wire({
    extra: () => (picked ? { detected: picked.detected } : {}),
    formId: 'fileForm', inputId: 'fileNameCheck', buttonId: 'fileButton', boxId: 'fileResult', emptyId: 'fileEmpty',
    url: '/jeda/api/cek-file', field: 'filename', idleLabel: 'Cek nama file', busyLabel: 'Memeriksa...',
    title: d => (d.extension ? '.' + d.extension : 'Tanpa ekstensi'),
  });

  // Chip "Penguji Tautan" / "Penguji File" menggeser fokus ke bagian yang dipilih.
  const chips = [...document.querySelectorAll('.hero .chip')];
  const targets = [document.getElementById('linkInput'), document.getElementById('fileNameCheck')];
  chips.forEach((chip, i) => {
    chip.setAttribute('role', 'button');
    chip.tabIndex = 0;
    const go = () => {
      chips.forEach(c => c.classList.toggle('on', c === chip));
      targets[i]?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      targets[i]?.focus({ preventScroll: true });
    };
    chip.addEventListener('click', go);
    chip.addEventListener('keydown', e => { if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); go(); } });
  });
})();