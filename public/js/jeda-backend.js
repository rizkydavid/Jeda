(() => {
  'use strict';
  const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const text = el => (el?.innerText || el?.textContent || '').trim();
  const status = (() => {
    const el = document.createElement('div');
    el.id = 'jedaDbStatus';
    el.setAttribute('role', 'status');
    Object.assign(el.style, {position:'fixed',right:'20px',bottom:'20px',zIndex:'99999',maxWidth:'340px',padding:'11px 15px',borderRadius:'12px',background:'#263452',color:'#fff',boxShadow:'0 8px 28px #0002',fontSize:'13px',opacity:'0',transform:'translateY(8px)',transition:'opacity .2s, transform .2s',pointerEvents:'none'});
    document.body.appendChild(el);
    let timer;
    return message => { el.textContent = message; el.style.opacity = '1'; el.style.transform = 'translateY(0)'; clearTimeout(timer); timer = setTimeout(() => { el.style.opacity = '0'; el.style.transform = 'translateY(8px)'; }, 2600); };
  })();

  async function post(url, data, quiet = false) {
    try {
      const response = await fetch(url, {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':csrf,'X-Requested-With':'XMLHttpRequest'},
        body: JSON.stringify(data)
      });
      const result = await response.json().catch(() => ({}));
      if (!response.ok) throw new Error(result.message || 'Data belum berhasil disimpan. Pastikan migrasi database sudah dijalankan.');
      if (!quiet) status('Tersimpan ke database Jeda.');
      return result;
    } catch (error) {
      console.error('[Jeda backend]', error);
      if (!quiet) status(error.message || 'Database belum terhubung. Periksa konfigurasi .env.');
      return null;
    }
  }

  // Bedah pesan: logika dan pencatatan progres ada di public/js/bedah.js (hanya dimuat di halaman Bedah).

  // Pemeriksaan tautan dipindah ke public/js/cek.js (hanya dimuat di halaman /cek).

  // Pemeriksaan file dipindah ke public/js/file-inspect.js dan cek.js (hanya dimuat di halaman /cek).

  // Pilihan kondisi Jam Emas disimpan di session browser.
  const situationChips = $$('.chip');
  const emergencyOptions = ['Sudah transfer uang','Sudah beri kode OTP','Sudah klik tautan','Akun dibobol'];
  situationChips.filter(chip => emergencyOptions.includes(text(chip))).forEach(chip => {
    chip.style.cursor = 'pointer';
    chip.addEventListener('click', async () => {
      situationChips.filter(c => emergencyOptions.includes(text(c))).forEach(c => c.classList.toggle('on', c === chip));
      const key = text(chip).toLowerCase().replace(/[^a-z0-9]+/g, '-');
      await post('/jeda/api/aksi-bantuan', {feature:'jam-emas', action:'pilih-kondisi-' + key}, true);
    });
  });

  // Checklist langkah darurat dan cek mandiri tersimpan per sesi.
  $$('.check').forEach((row, index) => {
    if (!/Hubungi bank|Simpan bukti|Hentikan kontak|Ganti kata sandi/i.test(text(row))) return;
    row.style.cursor = 'pointer';
    row.setAttribute('role', 'checkbox');
    row.setAttribute('aria-checked', 'false');
    row.addEventListener('click', async () => {
      const done = row.dataset.jedaDone !== 'true';
      row.dataset.jedaDone = String(done);
      row.setAttribute('aria-checked', String(done));
      row.classList.toggle('is-complete', done);
      row.style.opacity = done ? '.72' : '1';
      const item = text(row).toLowerCase().split('\n')[0].replace(/[^a-z0-9]+/g, '-').replace(/^-|-$/g, '').slice(0, 95) || `langkah-${index}`;
      await post('/jeda/api/checklist', {feature:'jam-emas', situation:'umum', item, done}, true);
      status(done ? 'Langkah ditandai selesai.' : 'Tanda selesai dibatalkan.');
    });
  });

  // Pilihan simulasi disimpan. Indeks opsi B (1) adalah pilihan verifikasi yang kuat pada skenario awal.
  $$('.opt').forEach((option, index) => {
    option.addEventListener('click', async () => {
      await post('/jeda/api/simulasi', {scenario:'rekruter-1', option:index}, true);
      status(index === 1 ? 'Pilihan latihan tersimpan.' : 'Pilihan latihan tersimpan. Coba pertimbangkan langkah verifikasi.');
    });
  });

  // Simpan pemakaian tindakan bantuan yang bisa dipakai offline (salin/berbagi tidak mengirim isi pesan ke server).
  document.addEventListener('click', async event => {
    const target = event.target.closest('a,button');
    if (!target) return;
    const label = text(target).toLowerCase();
    const actions = [
      ['salin kalimat','salin-kalimat'], ['salin pesan peringatan','salin-pesan-peringatan'],
      ['kirim lewat whatsapp','buka-whatsapp'], ['lihat tips lengkap','buka-tips'],
      ['buka panduan pelaporan','buka-panduan-pelaporan'], ['kirim tantangan ke teman','kirim-tantangan']
    ];
    const match = actions.find(([needle]) => label.includes(needle));
    if (match) await post('/jeda/api/aksi-bantuan', {feature:location.pathname.replace(/^\//,'') || 'dashboard', action:match[1]}, true);
  });

  // Helper debug progres: buka /jeda/api/progres di tab browser untuk melihat ringkasan sesi ini.
  window.JedaBackend = {
    async progress() {
      const response = await fetch('/jeda/api/progres', {headers:{'Accept':'application/json'}, credentials:'same-origin'});
      return response.json();
    }
  };
})();
