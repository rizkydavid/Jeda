<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Jeda membantu mengenali tanda tekanan dan penipuan digital sebelum mengambil keputusan.">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Jeda · @yield('title', 'Ruang Aman Digital')</title>
    <script>
        (() => {
            try {
                const savedTheme = localStorage.getItem('jeda-theme');
                if (savedTheme === 'dark') document.documentElement.dataset.theme = 'dark';
            } catch (error) {}
        })();
    </script>
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
</head>
<body>
    <div class="intro-loader" id="introLoader" aria-label="Memuat Jeda" role="status">
        <div class="intro-loader__content">
            <div class="intro-loader__brand-wrap" aria-hidden="true">
                <span class="intro-loader__orbit intro-loader__orbit--one"></span>
                <span class="intro-loader__orbit intro-loader__orbit--two"></span>
                <span class="intro-loader__spark intro-loader__spark--one"></span>
                <span class="intro-loader__spark intro-loader__spark--two"></span>
                <span class="intro-loader__spark intro-loader__spark--three"></span>
                <span class="intro-loader__halo intro-loader__halo--outer"></span>
                <span class="intro-loader__halo intro-loader__halo--inner"></span>
                <span class="jeda-mark jeda-mark--intro"><i></i><i></i></span>
                <span class="intro-loader__wordmark">Jeda</span>
            </div>
            <p class="intro-loader__caption">Ambil jeda. Pikirkan dengan tenang.</p>
            <div class="intro-loader__track" aria-hidden="true"><span></span></div>
            <span class="intro-loader__status">Menyiapkan ruang amanmu</span>
        </div>
        <div class="intro-loader__orb intro-loader__orb--one" aria-hidden="true"></div>
        <div class="intro-loader__orb intro-loader__orb--two" aria-hidden="true"></div>
    </div>

    <div class="site-shell" id="siteShell">
    <header class="site-header">
        <div class="wrap header-inner">
            <a class="logo" href="{{ route('home') }}" aria-label="Jeda, halaman utama">
                <span class="jeda-mark" aria-hidden="true"><i></i><i></i></span>
                <span class="logo-word">Jeda</span>
            </a>

            <nav class="site-nav" aria-label="Navigasi utama">
                <a href="{{ route('home') }}" @class(['on' => request()->routeIs('home')])>Bedah</a>
                <a href="{{ route('cek') }}" @class(['on' => request()->routeIs('cek')])>Cek</a>
                <a href="{{ route('simulasi') }}" @class(['on' => request()->routeIs('simulasi')])>Latihan</a>
                <a href="{{ route('jam-emas') }}" @class(['on' => request()->routeIs('jam-emas')])>Jam Emas</a>
                <a href="{{ route('keluarga') }}" @class(['on' => request()->routeIs('keluarga')])>Keluarga</a>
                <a class="pill" href="{{ route('batasan') }}" @class(['on' => request()->routeIs('batasan')])>Tanpa akun</a>
            </nav>
            <button class="theme-toggle" id="themeToggle" type="button" aria-label="Aktifkan mode gelap" aria-pressed="false" title="Ganti mode terang/gelap">
                <span class="theme-toggle__icon" aria-hidden="true">☾</span>
                <span class="theme-toggle__label">Mode gelap</span>
            </button>
        </div>
    </header>

    <main class="page-content">
        @yield('content')
    </main>
    </div>
    <script>
        (() => {
            const themeToggle = document.getElementById('themeToggle');
            const themeIcon = themeToggle?.querySelector('.theme-toggle__icon');
            const themeLabel = themeToggle?.querySelector('.theme-toggle__label');

            const applyTheme = (theme) => {
                const isDark = theme === 'dark';
                document.documentElement.dataset.theme = isDark ? 'dark' : 'light';
                themeToggle?.setAttribute('aria-pressed', String(isDark));
                themeToggle?.setAttribute('aria-label', isDark ? 'Aktifkan mode terang' : 'Aktifkan mode gelap');
                if (themeIcon) themeIcon.textContent = isDark ? '☀' : '☾';
                if (themeLabel) themeLabel.textContent = isDark ? 'Mode terang' : 'Mode gelap';
            };

            let initialTheme = 'light';
            try { initialTheme = localStorage.getItem('jeda-theme') === 'dark' ? 'dark' : 'light'; } catch (error) {}
            applyTheme(initialTheme);

            themeToggle?.addEventListener('click', () => {
                const nextTheme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
                applyTheme(nextTheme);
                try { localStorage.setItem('jeda-theme', nextTheme); } catch (error) {}
            });

            const loader = document.getElementById('introLoader');
            const shell = document.getElementById('siteShell');
            const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            let seen = false;
            try { seen = sessionStorage.getItem('jeda-intro-seen') === '1'; } catch (error) {}

            if (seen || reducedMotion) {
                loader?.remove();
                shell?.classList.add('is-ready');
            } else {
                document.body.classList.add('is-loading');
                const status = loader?.querySelector('.intro-loader__status');
                const messages = [
                    'Menyiapkan ruang amanmu',
                    'Menyusun tanda menjadi petunjuk',
                    'Memberi ruang sebelum memutuskan',
                    'Hampir siap. Tarik napas dulu'
                ];
                let messageIndex = 0;
                const messageTimer = window.setInterval(() => {
                    if (!status) return;
                    status.classList.add('is-changing');
                    window.setTimeout(() => {
                        messageIndex = (messageIndex + 1) % messages.length;
                        status.textContent = messages[messageIndex];
                        status.classList.remove('is-changing');
                    }, 220);
                }, 1350);
                window.setTimeout(() => {
                    window.clearInterval(messageTimer);
                    loader?.classList.add('is-leaving');
                    shell?.classList.add('is-ready');
                    document.body.classList.remove('is-loading');
                    try { sessionStorage.setItem('jeda-intro-seen', '1'); } catch (error) {}
                    window.setTimeout(() => loader?.remove(), 900);
                }, 6500);
            }

            // Reveal elemen secara halus ketika masuk viewport.
            const revealTargets = document.querySelectorAll('.hero, .card, .flag, .check, .q, .notice, .bubble, .opt, .chip, table, .sw');
            revealTargets.forEach((element, index) => {
                element.classList.add('motion-reveal');
                element.style.setProperty('--reveal-delay', `${Math.min(index % 6, 5) * 65}ms`);
            });

            if ('IntersectionObserver' in window && !reducedMotion) {
                const observer = new IntersectionObserver((entries) => {
                    entries.forEach((entry) => {
                        if (entry.isIntersecting) {
                            entry.target.classList.add('is-visible');
                            observer.unobserve(entry.target);
                        }
                    });
                }, { threshold: 0.08, rootMargin: '0px 0px -24px 0px' });
                revealTargets.forEach((element) => observer.observe(element));
            } else {
                revealTargets.forEach((element) => element.classList.add('is-visible'));
            }
        })();
    </script>

    <div class="jeda-toast" id="jedaToast" role="status" aria-live="polite"></div>
    <script>
    (() => {
      const $ = (s, root=document) => root.querySelector(s);
      const $$ = (s, root=document) => [...root.querySelectorAll(s)];
      const toast = (message) => {
        const el = $('#jedaToast'); if (!el) return;
        el.textContent = message; el.classList.add('is-shown');
        clearTimeout(window.__jedaToastTimer);
        window.__jedaToastTimer = setTimeout(() => el.classList.remove('is-shown'), 2800);
      };
      const copyText = async (text, success='Berhasil disalin.') => {
        try {
          if (navigator.clipboard && window.isSecureContext) await navigator.clipboard.writeText(text);
          else {
            const area = document.createElement('textarea'); area.value=text; area.style.position='fixed'; area.style.opacity='0';
            document.body.appendChild(area); area.select(); const ok=document.execCommand('copy'); area.remove();
            if (!ok) throw new Error('copy failed');
          }
          toast(success);
        } catch (_) { toast('Salin manual: pilih teks lalu tekan Ctrl+C.'); }
      };
      const textOf = (el) => (el?.innerText || el?.textContent || '').trim();

      // Make chips behave like real single-choice controls without changing their geometry.
      $$('.row .chip').forEach(chip => {
        chip.tabIndex = 0; chip.setAttribute('role','button');
        const activate = () => {
          const row=chip.closest('.row'); if (!row) return;
          $$('.chip', row).forEach(c => { c.classList.remove('on'); c.setAttribute('aria-pressed','false'); });
          chip.classList.add('on'); chip.setAttribute('aria-pressed','true');
          const value=textOf(chip);
          const pageTitle=textOf($('main h1'));
          if (pageTitle.includes('Rencana Jam Emas')) updateEmergency(value);
          if (pageTitle.includes('Lindungi keluarga')) updateFamily(value);
          toast('Pilihan: ' + value);
        };
        chip.addEventListener('click', activate);
        chip.addEventListener('keydown', e => { if(e.key==='Enter'||e.key===' '){e.preventDefault();activate();} });
      });

      // Checklist rows are keyboard and pointer accessible.
      $$('.check').forEach(row => {
        if (row.querySelector('i')) {
          row.tabIndex=0; row.setAttribute('role','checkbox'); row.setAttribute('aria-checked','false');
          const toggle=()=>{ row.classList.toggle('is-checked'); row.setAttribute('aria-checked',String(row.classList.contains('is-checked'))); };
          row.addEventListener('click', e=>{ if(!e.target.closest('a,button,input')) toggle(); });
          row.addEventListener('keydown', e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();}});
        }
      });
      $$('.sw').forEach(row => {
        row.tabIndex=0; row.setAttribute('role','checkbox'); row.setAttribute('aria-checked',String(row.classList.contains('on')));
        const toggle=()=>{row.classList.toggle('on');row.setAttribute('aria-checked',String(row.classList.contains('on')));};
        row.addEventListener('click',toggle); row.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();toggle();}});
      });

      // Bedah pesan: logika ada di public/js/bedah.js (hanya dimuat di halaman Bedah).

      // Link checker: parse URL locally and show its actual hostname.
      const linkInput=$('input[aria-label="URL"]');
      const linkButton=$$('.btn').find(b=>/periksa tautan/i.test(textOf(b)));
      linkButton?.addEventListener('click',()=>{
        const raw=linkInput?.value.trim()||''; let parsed;
        try{parsed=new URL(/^https?:\/\//i.test(raw)?raw:'https://'+raw);}catch(_){toast('Format tautan belum valid. Periksa kembali alamatnya.');linkInput?.focus();return;}
        const host=parsed.hostname.toLowerCase(); const parts=host.split('.');
        const suspicious=/(login|verify|verifikasi|secure| hadiah|hadiah|claim|gift|bonus|bank)/i.test(host);
        const message=suspicious?'Alamat memuat kata yang sering dipakai untuk memancing perhatian. Verifikasi lewat aplikasi/situs resmi.':'Alamat berhasil dibaca. Ini bukan jaminan situs aman; periksa ejaan domain dan sumber pengirim.';
        let box=$('#linkCheckFeedback'); if(!box){box=document.createElement('div');box.id='linkCheckFeedback';box.className='notice';linkButton.closest('.hero')?.appendChild(box);}
        box.innerHTML=`<strong>Domain yang dibaca: ${host}</strong><br>${message}`;
        toast('Tautan diperiksa di perangkat ini.');
      });
      // Add filename checker to the file card, never upload/open the file.
      const fileCard=$$('.grid2 .card').find(c=>/penguji file/i.test(textOf(c)));
      if(fileCard && !$('#fileNameCheck')){
        const panel=fileCard.querySelector('[style*="border:2px dashed"]');
        if(panel){
          const input=document.createElement('input');input.id='fileNameCheck';input.type='text';input.placeholder='Contoh: Undangan.pdf.apk';input.setAttribute('aria-label','Nama file');input.className='jeda-field';input.style.marginTop='14px';
          const btn=document.createElement('button');btn.type='button';btn.className='btn block';btn.textContent='Periksa nama file';btn.style.marginTop='8px';
          const out=document.createElement('div');out.id='fileNameFeedback';out.className='notice';out.style.marginTop='10px';out.hidden=true;
          panel.append(input,btn,out);
          btn.addEventListener('click',()=>{const name=input.value.trim();if(!name){toast('Masukkan nama file terlebih dahulu.');input.focus();return;}const ext=name.toLowerCase().split('.');const double=ext.length>2 && /^(pdf|jpg|jpeg|png|doc|docx)$/i.test(ext[ext.length-2]) && /^(apk|exe|bat|cmd|msi|scr)$/i.test(ext[ext.length-1]);const risky=/\.(apk|exe|bat|cmd|msi|scr|js|vbs)$/i.test(name);out.hidden=false;out.innerHTML=`<strong>${double?'Ekstensi ganda terdeteksi':risky?'Jenis file perlu kehati-hatian':'Nama file terbaca'}</strong><br>${double?'Nama terlihat seperti dokumen, tetapi ekstensi terakhir menunjukkan file yang dapat dijalankan.':risky?'Ekstensi ini dapat menjalankan program. Jangan buka jika sumbernya tidak jelas.':'Nama saja tidak cukup untuk memastikan file aman. Periksa pengirim dan sumbernya.'}<br><small>File tidak dibuka atau diunggah.</small>`;});
        }
      }

      // Jam Emas: adapt the action plan to the selected incident and enable checklist state.
      function updateEmergency(choice){
        const title=$('.card.dark h2'); const copy=$('.card.dark .mut');
        const plans={
          'Sudah transfer uang':['Ini bukan salahmu. Bertindak cepat membantu.','Hubungi bank/e-wallet sekarang dan minta transaksi ditinjau atau diblokir. Simpan bukti transfer dan percakapan.'],
          'Sudah beri kode OTP':['Amankan akun sekarang.','Hubungi bank/layanan terkait, ubah kata sandi dari perangkat aman, keluar dari sesi lain, dan jangan bagikan kode berikutnya.'],
          'Sudah klik tautan':['Berhenti dan periksa perangkat.','Jangan isi data lagi. Jika sudah memasukkan kata sandi, ubah melalui situs resmi. Jika mengunduh aplikasi, jangan jalankan dan minta bantuan tepercaya.'],
          'Akun dibobol':['Ambil kembali kendali akun.','Gunakan proses pemulihan resmi dari perangkat yang aman, ganti kata sandi email utama, aktifkan 2FA, lalu beri tahu kontak jika akun mengirim pesan mencurigakan.']
        };
        const plan=plans[choice];if(plan){if(title)title.textContent=plan[0];if(copy)copy.textContent=plan[1];}
      }
      function updateFamily(choice){const h=$$('h2').find(el=>/penipuan yang sering menyasar/i.test(textOf(el)));if(h)h.textContent=`Penipuan yang perlu diwaspadai: ${choice.toLowerCase()}`;}
      // Make Jam Emas checklist items visibly checkable.
      $$('.check').forEach(row=>{if(/Hubungi bank|Simpan bukti|Hentikan kontak|Ganti kata sandi/i.test(textOf(row))){row.setAttribute('aria-label','Tandai langkah selesai');}});

      // Functional actions for all placeholder links/buttons throughout the site.
      document.addEventListener('click', async (event)=>{
        const target=event.target.closest('a,button');if(!target)return;
        const label=textOf(target);if(!label)return;
        if(target.matches('a[href="#"]')) event.preventDefault();
        if(/salin kalimat/i.test(label)){const q=$$('.q').find(el=>textOf(el).includes('Ma, aku mau cerita'));await copyText(q?textOf(q).replace(/^.*?"/,'').replace(/"$/,''):'Ma, aku mau cerita. Aku butuh bantuan untuk memeriksa pesan mencurigakan.','Kalimat pembuka disalin.');}
        else if(/salin pesan peringatan/i.test(label)){await copyText('Hati-hati terhadap pesan yang mengaku dari keluarga dan meminta transfer mendesak. Pastikan dengan menelepon nomor yang sudah kamu kenal, jangan bagikan OTP, dan jangan klik tautan mencurigakan.','Pesan peringatan disalin.');}
        else if(/kirim lewat whatsapp/i.test(label)){const message='Ma, ada penipuan yang lagi ramai: ada yang mengaku jadi aku dan minta transfer. Kalau ada pesan begitu, telepon aku dulu ya, jangan langsung transfer.';window.open('https://wa.me/?text='+encodeURIComponent(message),'_blank','noopener');}
        else if(/salin pesan|salin tantangan|kirim tantangan/i.test(label)){await copyText('Yuk latihan bareng Jeda untuk mengenali pesan mendesak, permintaan uang, OTP, dan tautan mencurigakan. Jangan buru-buru, verifikasi dulu.','Teks tantangan disalin.');}
        else if(/buka panduan pelaporan/i.test(label)){showInfo('Panduan pelaporan','Hubungi bank atau e-wallet melalui aplikasi/nomor resmi. Untuk dugaan penipuan transaksi di Indonesia, cek kanal resmi lembaga terkait dan jangan gunakan nomor yang diberikan oleh pengirim mencurigakan.');}
        else if(/lihat tips lengkap/i.test(label)){showInfo('Tips ngobrol tanpa menggurui','Mulai dengan bertanya, dengarkan tanpa menyalahkan, jelaskan contoh yang kamu temui, lalu sepakati langkah sederhana: verifikasi lewat nomor resmi, jangan bagikan OTP, dan telepon keluarga untuk memastikan identitas.');}
        else if(/kirim tantangan ke teman/i.test(label)){await copyText('Aku lagi latihan mengenali pola penipuan di Jeda. Yuk cek pesan dengan tenang, jangan terburu-buru transfer atau bagikan OTP.','Tantangan disalin, siap dibagikan.');}
      });
      function showInfo(title, body){let modal=$('#jedaInfoModal');if(!modal){modal=document.createElement('div');modal.id='jedaInfoModal';modal.className='jeda-modal';modal.innerHTML='<div class="jeda-modal__panel" role="dialog" aria-modal="true" aria-labelledby="jedaModalTitle"><button class="jeda-modal__close" type="button" aria-label="Tutup">×</button><h2 id="jedaModalTitle"></h2><p></p><button class="btn" type="button">Mengerti</button></div>';document.body.appendChild(modal);modal.addEventListener('click',e=>{if(e.target===modal||e.target.closest('.jeda-modal__close')||e.target.closest('.btn'))modal.classList.remove('is-open');});}$('#jedaModalTitle',modal).textContent=title;$('.jeda-modal__panel p',modal).textContent=body;modal.classList.add('is-open');}

      // Simulation answers select and update feedback in place.
      const options=$$('.opt'); options.forEach((opt,index)=>{opt.tabIndex=0;opt.setAttribute('role','button');const choose=()=>{options.forEach(o=>{o.classList.remove('sel');const note=o.querySelector('.small');if(note)note.remove();});opt.classList.add('sel');const feedback=$$('.card').find(c=>/umpan balik/i.test(textOf(c)));if(feedback){const p=$('p.mut',feedback);if(p){p.textContent=index===1?'Pilihan kuat: verifikasi legalitas dan bicarakan dengan orang tepercaya sebelum bertindak.':index===2?'Memblokir dapat membantu jika pesan jelas mencurigakan, tetapi simpan bukti dan verifikasi jika ada akun yang mungkin disalahgunakan.':'Jangan transfer karena tekanan waktu. Berhenti dulu, cek legalitas, dan minta pendapat pihak tepercaya.';}}};opt.addEventListener('click',choose);opt.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();choose();}});});
    })();
    </script>

    <script src="{{ asset('js/jeda-backend.js') }}" defer></script>
</body>
</html>