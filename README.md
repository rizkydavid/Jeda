# Jeda — Edukasi Keamanan Digital

> **Ragu? Jeda dulu.** Berhenti sejenak, periksa, lalu bertindak dengan lebih aman.

**Jeda** adalah aplikasi web edukasi keamanan digital yang membantu orang mengenali penipuan online (phishing, rekayasa sosial, file berbahaya) sebelum terlanjur klik atau transfer. Hasil pemeriksaan selalu disampaikan sebagai **indikator risiko, bukan vonis**.

Aplikasi ini *guest-first*: tidak perlu daftar atau login. Data progres dipisahkan berdasarkan sesi browser.

**Demo langsung:** [jeda.up.railway.app](https://jeda.up.railway.app)

<!-- Tambahkan tangkapan layar di sini, contoh:
![Beranda](docs/screenshots/beranda.png)
![Cek](docs/screenshots/cek.png)
![Latihan](docs/screenshots/latihan.png)
-->

---

## Daftar Isi

- [Latar Belakang](#latar-belakang)
- [Fitur Utama](#fitur-utama)
- [Petunjuk Penggunaan](#petunjuk-penggunaan)
- [Teknologi](#teknologi)
- [Struktur Proyek](#struktur-proyek)
- [Cara Menjalankan Aplikasi](#cara-menjalankan-aplikasi)
- [Konfigurasi `.env`](#konfigurasi-env)
- [Testing](#testing)
- [Deploy ke Railway](#deploy-ke-railway)
- [Privasi dan Keamanan](#privasi-dan-keamanan)
- [Batasan](#batasan)
- [Dokumentasi Teknis](#dokumentasi-teknis)

---

## Latar Belakang

Penipuan digital sering berhasil karena korban terburu-buru dan tidak tahu cara memeriksa. Jeda mengajak pengguna *berhenti sejenak*, menunjukkan tanda-tanda tekanan atau risiko, lalu memberi langkah aman berikutnya.

**Pembeda Jeda:**

- Pemeriksaan pesan berjalan di browser, teksnya tidak dikirim ke server.
- Isi file tidak diunggah secara bawaan; hanya ringkasan dan hash SHA-256 yang dikirim.
- Tidak perlu akun.
- Pemeriksaan yang gagal tidak pernah dianggap aman, melainkan ditandai **"belum lengkap"**.

---

## Fitur Utama

| Halaman | Route | Deskripsi |
|---|---|---|
| Beranda | `/` | Pintu masuk ke semua fitur |
| Cek | `/cek` | Bedah Pesan, Cek Tautan, dan Cek File |
| Latihan | `/latihan` | Kuis berlevel dengan sistem EXP dan kode pemulihan |
| Jam Emas | `/jam-emas` | Rencana tindakan cepat kalau sudah terkena penipuan |
| Keluarga | `/keluarga` | Perlindungan untuk keluarga, terutama orang tua |
| Bagikan | `/bagikan` | Mode orang tua: terima pesan dari menu "Bagikan" HP (PWA) |

### 1. Bedah Pesan

Menganalisis pesan mencurigakan (WhatsApp, SMS, DM) dalam dua lapis.

- **Lapis 1, pemeriksaan lokal (selalu aktif).** Berjalan di browser (`public/js/bedah.js`). Berbasis aturan dengan skor berbobot 0–100, bukan machine learning. Mendeteksi tekanan waktu, permintaan merahasiakan, permintaan uang/OTP/PIN/data pribadi, iming-iming hadiah atau pekerjaan, mengaku pihak resmi, ancaman, dan tautan mencurigakan. Teks dinormalisasi dulu, jadi variasi seperti `0TP` atau `tr4nsfer` tetap terdeteksi.
- **Lapis 2, analisis AI (opsional).** Nomor HP, rekening, dan email disamarkan di server sebelum dikirim ke penyedia AI (**Gemini** atau **Anthropic Claude**). Teks tidak disimpan. Tanpa API key, tombol ini menampilkan "AI belum aktif" dan pemeriksaan lokal tetap berfungsi.

### 2. Cek Tautan

Pemeriksaan berlapis di server (`app/Services/Cek/LinkAnalysisService.php`):

1. Pola alamat: TLD mencurigakan, pemendek tautan, tiruan merek (BCA, BRI, Shopee, DANA, dll.), subdomain pengalih.
2. Pelacakan pengalihan (redirect) dengan batas hop dan waktu.
3. Pembacaan isi halaman tujuan untuk tanda form login palsu.
4. Google Safe Browsing (opsional).
5. VirusTotal (opsional).
6. Umur domain lewat RDAP.
7. Skor akhir oleh `RiskScorer`, dengan batas bobot per kelompok sinyal.

`NetworkGuard` mencegah server dipakai membuka alamat jaringan internal (SSRF). Penjelasan bahasa awam dibuat terpisah oleh Gemini; jika gagal, hasil utama tidak terpengaruh.

### 3. Cek File

Browser membaca file sendiri (`public/js/file-inspect.js`) lalu hanya mengirim ringkasan dan SHA-256. Server menilai:

- Ekstensi berbahaya (`.exe`, `.apk`, `.scr`, `.js`, dll.) dan ekstensi ganda seperti `laporan.pdf.exe`.
- Jenis file asli dari byte awal, dibandingkan dengan ekstensinya.
- Dokumen yang bisa membawa makro, serta izin berbahaya di dalam APK.
- Nama file bernada penipuan (undangan, resi, tilang, dll.), khusus APK/program.
- Reputasi hash lewat VirusTotal (opsional).

**Pemindaian antivirus opsional:** satu-satunya jalur yang mengirim isi file ke server, hanya setelah pengguna mencentang izin, lalu diteruskan ke VirusTotal dan tidak disimpan.

### 4. Latihan

Kuis **10 soal per ronde**, **10 level** (Pemula sampai Puncak Kedamaian), 12 soal per level (total 120 soal).

- Level tidak naik otomatis. Jawaban benar memberi **EXP** yang dikumpulkan lintas ronde.
- EXP per jawaban benar = `6 + 2 × (level − 1)`; ronde 10/10 mendapat bonus +10.
- Level 2–10 terbuka pada 100, 250, 450, 700, 1000, 1350, 1750, 2200, dan 2700 EXP.
- EXP dihitung di server (`App\Support\QuizProgression`).
- **Kode pemulihan** format `JEDA-XXXX-XXXX-XXXX` sebagai pengganti akun, untuk memindahkan progres ke browser atau perangkat lain. Server hanya menyimpan hash SHA-256 dari kode.
- Semua angka bisa diubah di `config/jeda_quiz.php`.

### 5. Jam Emas

Panduan berurutan setelah terkena penipuan: apa yang sudah terjadi, kapan kejadiannya, langkah yang harus dilakukan, ke mana melapor, draf laporan siap salin, dan peringatan soal penipuan kedua. Checklist tersimpan ke database.

### 6. Keluarga

Pilih siapa yang ingin dilindungi, lalu dapatkan checklist perlindungan, kartu peringatan siap kirim, ide kata sandi keluarga, dan langkah jika sudah terlanjur.

### 7. Bagikan (PWA)

Jeda bisa dipasang di layar utama HP. Lewat menu "Bagikan" Android, pesan mencurigakan dikirim ke Jeda tanpa disalin manual. Isi kiriman disimpan sementara di cache lokal browser (service worker `public/sw.js`), **tidak dikirim ke server**, dan langsung dihapus setelah dibaca.

---

## Petunjuk Penggunaan

1. **Dapat pesan atau tautan mencurigakan?** Buka `/cek`, pilih tab **Bedah Pesan**, **Cek Tautan**, atau **Cek File**, lalu tempel pesan/tautan atau pilih file. Baca indikator risiko dan langkah aman yang disarankan.
2. **Mau berlatih?** Buka `/latihan`, pilih level, jawab 10 soal, kumpulkan EXP. Setelah memainkan minimal satu ronde, buat **kode pemulihan** dan simpan di tempat aman.
3. **Pindah perangkat?** Di `/latihan`, masukkan kode pemulihan untuk memindahkan progres ke sesi baru.
4. **Sudah terlanjur kena tipu?** Buka `/jam-emas`, ikuti langkah dari atas ke bawah secepat mungkin.
5. **Melindungi orang tua atau keluarga?** Buka `/keluarga`, centang checklist, kirim kartu peringatan, dan sepakati kata sandi keluarga.
6. **Orang tua ragu dengan pesan masuk?** Pasang Jeda di layar utama HP, lalu bagikan pesan langsung lewat menu "Bagikan" ke Jeda.

> Hasil Jeda adalah **indikator risiko**, bukan jaminan. Jika ragu, verifikasi lewat kanal resmi (aplikasi atau nomor resmi instansi).

---

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP ^8.2, Laravel ^12 |
| Frontend | Blade, CSS dan JavaScript biasa (tanpa framework), font Plus Jakarta Sans |
| Database | MySQL / MariaDB (juga mendukung SQLite dan PostgreSQL) |
| Sesi, cache, antrean | Driver `database` |
| PWA | `manifest.json` dan service worker `sw.js` |
| Layanan eksternal (opsional) | Google Safe Browsing, VirusTotal, Gemini, Anthropic |
| Testing | PHPUnit 11, `node --test` untuk deteksi tipe file di browser |

> **Catatan aset:** CSS dan JS dimuat dari `public/css/` dan `public/js/` lewat `asset()` di `resources/views/layouts/app.blade.php`. Vite/Tailwind bawaan Laravel tidak dipakai layout, jadi `npm run build` **tidak wajib**.

---

## Struktur Proyek

```
Jeda/
├── app/
│   ├── Http/
│   │   ├── Controllers/        # Bedah, LinkCheck, LinkExplanation, FileCheck, FileScan,
│   │   │                       # Latihan, Recovery, Progres (+ Jam Emas & Keluarga kosong)
│   │   └── Requests/           # Validasi input Cek
│   ├── Services/Cek/           # Mesin pemeriksaan tautan, file, dan AI
│   ├── Support/                # QuizProgression, QuizProgressStore, RecoveryCode
│   └── Providers/AppServiceProvider.php   # Definisi rate limiter
├── config/
│   ├── jeda_cek.php            # Merek, TLD, ekstensi, izin APK, batas bobot, batas laju
│   ├── jeda_quiz.php           # Level dan EXP kuis
│   └── services.php            # Key API eksternal
├── database/migrations/
├── docs/                       # Dokumentasi teknis (arsitektur, API, database)
├── public/
│   ├── css/  js/  fonts/  images/
│   ├── manifest.json           # PWA
│   └── sw.js                   # Service worker (menu Bagikan)
├── resources/views/            # layouts/app + pages (home, cek, simulasi, jam-emas, keluarga, bagikan)
├── routes/web.php
└── tests/                      # Feature/Cek, Latihan, Recovery; Unit; js
```

---

## Cara Menjalankan Aplikasi

### Kebutuhan

- PHP 8.2+ dengan ekstensi `mbstring`, `openssl`, `pdo_mysql`, `curl`, `fileinfo`, `xml`, `ctype`, `tokenizer`
- Composer
- MySQL atau MariaDB (misalnya lewat XAMPP atau Laragon)
- Node.js (opsional, hanya untuk Vite atau menjalankan tes JS)

### Langkah

```bash
# 1. Clone dan masuk folder
git clone <url-repo> jeda
cd jeda

# 2. Install dependensi PHP
composer install

# 3. Siapkan .env lalu generate key
#    (repo tidak menyertakan .env.example, buat .env dari contoh di bagian Konfigurasi)
php artisan key:generate

# 4. Buat database kosong bernama "jeda" di MySQL, lalu migrasi
php artisan migrate

# 5. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000`.

Alternatif satu perintah untuk pengembangan (server, antrean, log, dan Vite sekaligus): `composer dev`.

---

## Konfigurasi `.env`

Buat file `.env` di akar proyek. Contoh untuk lokal dengan MySQL:

```env
APP_NAME=Jeda
APP_ENV=local
APP_KEY=                      # isi lewat: php artisan key:generate
APP_DEBUG=true
APP_URL=http://localhost
APP_LOCALE=en
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=stack
LOG_LEVEL=debug

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=jeda
DB_USERNAME=root
DB_PASSWORD=

SESSION_DRIVER=database
SESSION_LIFETIME=120
QUEUE_CONNECTION=database
CACHE_STORE=database
FILESYSTEM_DISK=local

MAIL_MAILER=log
```

**Variabel opsional khusus Jeda.** Tanpa key ini, fitur terkait menampilkan status "belum lengkap" atau "AI belum aktif", bukan error.

| Variabel | Fungsi |
|---|---|
| `SAFE_BROWSING_API_KEY` | Reputasi tautan lewat Google Safe Browsing |
| `VIRUSTOTAL_API_KEY` | Cek tautan, hash file, dan pemindaian file (gratis: 4 permintaan/menit, 500/hari) |
| `GEMINI_API_KEY` | Analisis AI Bedah Pesan dan penjelasan Cek Tautan |
| `GEMINI_MODEL` | Model Gemini (bawaan `gemini-3.8-flash`) |
| `GEMINI_THINKING_LEVEL` | Opsional |
| `ANTHROPIC_API_KEY` | Analisis AI lewat Claude |
| `JEDA_AI_MODEL` | Model Anthropic (bawaan `claude-haiku-5-5`) |
| `JEDA_AI_PROVIDER` | `gemini` atau `anthropic`. Kosong = pakai yang key-nya terisi (Gemini diprioritaskan) |
| `JEDA_CEK_PER_MINUTE` / `JEDA_CEK_PER_HOUR` | Batas Cek Tautan/File per IP (bawaan 10 dan 120) |
| `JEDA_UPLOAD_MAX_BYTES` | Batas ukuran pemindaian file (bawaan 10 MB, maksimum 32 MB) |
| `JEDA_UPLOAD_PER_MINUTE` / `JEDA_UPLOAD_PER_HOUR` | Batas pemindaian file per IP (bawaan 3 dan 15) |

> **Keamanan:** jangan commit `.env` atau key API ke repo (sudah ada di `.gitignore`). `APP_KEY` bersifat rahasia; buat ulang dengan `php artisan key:generate` jika pernah terbagikan.

---

## Testing

```bash
php artisan test        # atau: composer test
node --test             # tes deteksi tipe file di browser (file-inspect.js)
```

`phpunit.xml` memakai SQLite in-memory, jadi ekstensi `pdo_sqlite` harus aktif. Cakupan utama: `RiskScorer`, `UrlHeuristics`, `RedirectTracer`, `NetworkGuard`, Cek Tautan, Cek File, pemindaian file, penjelasan Gemini, kuis Latihan, `QuizProgression`, dan kode pemulihan.

---

## Deploy ke Railway

1. Push repo ke GitHub, lalu buat project di Railway dari repo tersebut.
2. Tambahkan **Variables**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...          # dari: php artisan key:generate --show
   APP_URL=https://domain-kamu.up.railway.app
   ASSET_URL=https://domain-kamu.up.railway.app
   ```
   Tambahkan variabel koneksi database dan key API opsional.
3. Proxy HTTPS sudah dipercaya (`trustProxies` di `bootstrap/app.php`) dan skema `https` dipaksa saat `APP_ENV=production`.
4. Jalankan migrasi saat deploy: `php artisan migrate --force`.
5. Pastikan document root web server adalah folder `public/`.
6. Gunakan database terkelola (MySQL/PostgreSQL) karena sesi, cache, dan progres pengguna tersimpan di database. SQLite di filesystem container hilang saat redeploy.

---

## Privasi dan Keamanan

- **Tanpa akun.** Data dipisah per sesi browser; kunci sesi di-hash (SHA-256) sebelum disimpan.
- **Isi pesan tidak disimpan.** Server hanya mencatat skor, id indikator, dan panjang teks.
- **Isi file tidak diunggah** secara bawaan; pemindaian antivirus butuh persetujuan eksplisit dan tidak menyimpan apa pun.
- **Data pribadi disamarkan** (nomor HP, rekening, email) sebelum teks dikirim ke API AI.
- **Perlindungan SSRF.** `NetworkGuard` memblokir jaringan internal dan hanya mengizinkan port 80 dan 443.
- **Pembatasan laju** pada endpoint yang memakai API berkuota atau rawan ditebak.
- **Kode pemulihan** hanya disimpan sebagai hash dan dibatasi 5 percobaan per menit per IP.
- **Prompt AI** memperlakukan isi pesan sebagai data, bukan perintah.
- **Kegagalan tidak berarti aman.** Pemeriksaan yang tidak lengkap ditandai "belum lengkap".

---

## Batasan

- Skor Bedah Pesan berbasis aturan, bukan machine learning, sehingga penipuan baru bisa lolos.
- Daftar merek dan domain resmi di `config/jeda_cek.php` adalah contoh awal; **verifikasi ulang sebelum dipakai di demo atau produksi**.
- Hasil AI bisa salah dan selalu diberi label.
- Progres kuis terikat sesi browser. Tanpa kode pemulihan, level dan EXP bisa hilang jika cookie dihapus, berpindah perangkat, atau sesi kedaluwarsa (bawaan 120 menit).
- Soal kuis disusun dari pengetahuan umum tentang modus penipuan dan belum diverifikasi dengan sumber resmi (misalnya OJK, BSSN, Komdigi). Perlakukan sebagai bahan latihan.
- Jeda tidak menjamin sebuah tautan, file, atau pesan 100% aman.

---

## Dokumentasi Teknis

| Dokumen | Isi |
|---|---|
| [docs/ARSITEKTUR.md](docs/ARSITEKTUR.md) | Arsitektur, alur data tiap fitur, sistem skor, pengamanan |
| [docs/API.md](docs/API.md) | Daftar endpoint internal, batas laju, contoh permintaan |
| [docs/DATABASE.md](docs/DATABASE.md) | Skema tabel dan relasi lewat `session_key` |

---

**Jeda — Berhenti sejenak, periksa, lalu bertindak dengan lebih aman.**
