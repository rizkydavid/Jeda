# Jeda — Edukasi Keamanan Digital

> **Berhenti sejenak, periksa, lalu bertindak dengan lebih aman.**

**Jeda** adalah aplikasi web edukasi keamanan digital untuk membantu orang mengenali penipuan online (phishing, rekayasa sosial, file berbahaya) sebelum terlanjur klik atau transfer. Hasil pemeriksaan selalu disampaikan sebagai **indikator risiko, bukan vonis**.

Aplikasi ini *guest-first*: tidak perlu daftar atau login. Data progres dipisahkan berdasarkan sesi browser.

**Demo langsung:** [jeda.up.railway.app](https://jeda.up.railway.app)

### Ringkasan singkat

- **Masalah:** penipuan digital (phishing, rekayasa sosial, file berbahaya) sering berhasil karena korban terburu-buru dan tidak tahu cara memeriksa.
- **Solusi:** Jeda mengajak pengguna *berhenti sejenak*, menunjukkan tanda-tanda tekanan atau risiko, lalu memberi langkah aman berikutnya. Hasilnya indikator risiko, bukan vonis.
- **Pembeda:** pemeriksaan pesan berjalan di browser, isi file tidak pernah diunggah, tidak perlu akun, dan pemeriksaan yang gagal tidak pernah dianggap aman.

<!-- Tambahkan tangkapan layar di sini, contoh:
![Beranda](docs/screenshots/beranda.png)
![Bedah Pesan](docs/screenshots/bedah.png)
![Latihan](docs/screenshots/latihan.png)
-->

---

## Daftar Isi

- [Fitur](#fitur)
- [Teknologi](#teknologi)
- [Struktur Proyek](#struktur-proyek)
- [Menjalankan Secara Lokal](#menjalankan-secara-lokal)
- [Konfigurasi `.env`](#konfigurasi-env)
- [Deploy ke Railway](#deploy-ke-railway)
- [Endpoint API Internal](#endpoint-api-internal)
- [Database](#database)
- [Testing](#testing)
- [Privasi dan Keamanan](#privasi-dan-keamanan)
- [Batasan](#batasan)

---

## Fitur

| Halaman | Route | Deskripsi |
|---|---|---|
| Beranda | `/` | Pintu masuk ke semua fitur |
| Cek | `/cek` | Bedah Pesan, Cek Tautan, dan Cek File |
| Latihan | `/latihan` | Kuis berlevel dengan sistem EXP |
| Jam Emas | `/jam-emas` | Rencana tindakan cepat kalau sudah terkena penipuan |
| Keluarga | `/keluarga` | Perlindungan untuk keluarga, terutama orang tua |
| Batasan | `/batasan` | Penjelasan jujur tentang apa yang bisa dan tidak bisa dilakukan Jeda |

### 1. Bedah Pesan

Menganalisis pesan mencurigakan (WhatsApp, SMS, DM) dalam dua lapis.

**Lapis 1: pemeriksaan lokal (selalu aktif).** Berjalan di browser (`public/js/bedah.js`), teks pesan tidak dikirim ke server. Berbasis aturan dengan skor berbobot 0–100, bukan machine learning. Yang diperiksa:

- Tekanan waktu, permintaan merahasiakan, permintaan uang, permintaan OTP/PIN/data pribadi.
- Iming-iming hadiah atau pekerjaan, mengaku pihak resmi, ancaman, mengaku kenalan dengan nomor baru.
- Tautan di dalam pesan: nama merek di subdomain, kata pancingan, IP langsung, pemendek tautan, link file `.apk`.
- Teks dinormalisasi dulu, jadi variasi seperti `0TP`, `tr4nsfer`, atau `o t p` tetap terdeteksi.
- Kombinasi pola berbahaya (misalnya uang + tekanan) menambah skor.

Server hanya mencatat skor, daftar id indikator, dan panjang teks.

**Lapis 2: analisis AI (opsional).** Tombol "Kirim ke AI dan analisis" memanggil `POST /jeda/api/bedah-ai`. Nomor HP, rekening, dan email disamarkan di server sebelum dikirim ke penyedia AI, teks tidak disimpan, dan hasilnya diberi label bahwa AI bisa salah. Penyedia yang didukung: **Gemini** dan **Anthropic (Claude)**. Tanpa API key, tombol ini menampilkan pesan bahwa AI belum aktif dan pemeriksaan lokal tetap berfungsi.

### 2. Cek Tautan

Pemeriksaan berlapis yang dijalankan server (`app/Services/Cek/LinkAnalysisService.php`):

1. **Pola alamat** (lokal): TLD mencurigakan, pemendek tautan, tiruan merek (BCA, BRI, Shopee, DANA, dll.), subdomain pengalih.
2. **Pelacakan pengalihan** (redirect): mengikuti rantai pengalihan dengan batas hop dan waktu.
3. **Isi halaman tujuan**: mencari tanda form login palsu dan sejenisnya.
4. **Google Safe Browsing** (opsional).
5. **VirusTotal** (opsional).
6. **Umur domain** lewat RDAP.
7. **Skor akhir** oleh `RiskScorer`, dengan batas bobot per kelompok sinyal supaya satu jenis tanda tidak mendominasi.

Prinsip pentingnya: pemeriksaan eksternal yang gagal dicatat sebagai **"belum lengkap"** dan **tidak pernah dianggap aman**. Ada juga `NetworkGuard` yang mencegah server dipakai membuka alamat jaringan internal (SSRF), dan alamat internal tidak dikirim ke layanan pihak ketiga.

Penjelasan dalam bahasa awam dibuat terpisah oleh AI (`POST /jeda/api/cek-tautan/penjelasan`). Kalau AI gagal, hasil pemeriksaan utama tidak terpengaruh.

### 3. Cek File

**Isi file tidak diunggah.** Browser membaca file sendiri (`public/js/file-inspect.js`), lalu hanya mengirim ringkasan dan hash SHA-256 ke server. Server menilai berdasarkan:

- Ekstensi berbahaya (`.exe`, `.apk`, `.scr`, `.js`, dll.) dan ekstensi ganda seperti `laporan.pdf.exe`.
- Jenis file asli dari byte awal, dibandingkan dengan ekstensinya.
- Dokumen yang bisa membawa makro.
- Nama file bernada penipuan (undangan, resi, tilang, dll.), khusus untuk APK/program.
- Reputasi hash lewat VirusTotal (opsional).

### 4. Latihan

Kuis 10 soal per ronde dengan **10 level**, dari Pemula sampai Puncak Kedamaian (120 soal, 12 per level; tiap ronde mengambil 10 soal acak dari level tersebut).

- Level **tidak naik otomatis** setelah satu ronde. Setiap jawaban benar memberi **EXP**, EXP dikumpulkan lintas ronde, dan level naik saat total EXP mencapai batas level berikutnya.
- EXP per jawaban benar = `6 + 2 x (level - 1)`; ronde 10/10 mendapat bonus +10.
- Level 2 sampai 10 terbuka pada 100, 250, 450, 700, 1000, 1350, 1750, 2200, dan 2700 EXP. Level yang sudah terbuka bisa dimainkan ulang kapan saja.
- EXP dihitung di server (`App\Support\QuizProgression`), bukan di browser, dan disimpan di `jeda_quiz_attempts.exp_earned`.
- Semua angka bisa diubah di `config/jeda_quiz.php`.

### 5. Jam Emas

Panduan langkah berurutan setelah terkena penipuan: apa yang sudah terjadi, kapan kejadiannya, langkah yang harus dilakukan, ke mana melapor, draf laporan siap salin, dan peringatan soal penipuan kedua.

### 6. Keluarga

Pilih siapa yang ingin dilindungi, lalu dapat:

- **Checklist perlindungan** per kelompok (tersimpan ke database).
- **Kartu peringatan siap kirim.**
- **Kata sandi keluarga**: pilih dari ide acak atau tulis sendiri.
- **Kalau sudah terlanjur**: langkah yang perlu dilakukan.

---

## Teknologi

| Bagian | Teknologi |
|---|---|
| Backend | PHP ^8.2, Laravel ^12 |
| Frontend | Blade, CSS biasa, JavaScript biasa (tanpa framework) |
| Database | SQLite (default), mendukung MySQL/MariaDB dan PostgreSQL |
| Sesi dan cache | Driver `database` (default) |
| Layanan eksternal (opsional) | Google Safe Browsing, VirusTotal, Gemini, Anthropic |
| Testing | PHPUnit 11 |

> **Catatan aset:** CSS dan JS ada di `public/css/` dan `public/js/`, dimuat lewat `asset()` di `resources/views/layouts/app.blade.php`. Vite/Tailwind (`resources/css/app.css`, `vite.config.js`) bawaan Laravel dan **tidak dipakai** oleh layout, jadi `npm run build` tidak wajib.

---

## Struktur Proyek

```
Jeda/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── BedahController.php          # Bedah Pesan (lokal + AI)
│   │   │   ├── LinkCheckController.php      # Cek Tautan
│   │   │   ├── LinkExplanationController.php# Penjelasan AI untuk Cek Tautan
│   │   │   ├── FileCheckController.php      # Cek File
│   │   │   ├── LatihanController.php        # Kuis berlevel dan EXP
│   │   │   ├── ProgresController.php        # Checklist, aksi bantuan, ringkasan progres
│   │   │   ├── JamEmasController.php
│   │   │   ├── KeluargaController.php
│   │   │   └── Concerns/HasSessionKey.php   # Kunci sesi (hash SHA-256 dari session id)
│   │   └── Requests/                        # Validasi input Cek
│   ├── Services/Cek/                        # Mesin pemeriksaan tautan dan file
│   └── Support/QuizProgression.php          # Logika EXP dan level
├── config/
│   ├── jeda_cek.php                         # Daftar merek, TLD, ekstensi, batas bobot
│   ├── jeda_quiz.php                        # Level dan EXP kuis
│   └── services.php                         # Key API eksternal
├── database/migrations/
├── public/
│   ├── css/  (style.css, nav-mobile.css, latihan.css)
│   ├── js/   (bedah, cek, file-inspect, latihan, latihan-data, nav, jeda-backend)
│   ├── fonts/  (Plus Jakarta Sans)
│   └── images/
├── resources/views/
│   ├── layouts/app.blade.php
│   └── pages/ (home, cek, simulasi, jam-emas, keluarga, batasan)
├── routes/web.php
└── tests/ (Feature/Cek, LatihanQuizTest, Unit/QuizProgressionTest)
```

---

## Menjalankan Secara Lokal

**Kebutuhan:** PHP 8.2+, Composer. Node.js hanya jika ingin memakai Vite.

```bash
# 1. Clone dan masuk folder
git clone <url-repo> jeda
cd jeda

# 2. Install dependensi
composer install

# 3. Siapkan .env lalu generate key
cp .env.example .env
php artisan key:generate

# 4. Siapkan database (default SQLite)
touch database/database.sqlite
php artisan migrate

# 5. Jalankan
php artisan serve
```

Buka `http://127.0.0.1:8000`.

Contoh `.env` minimal untuk lokal:

```env
APP_NAME=Jeda
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=sqlite
SESSION_DRIVER=database
CACHE_STORE=database
```

---

## Konfigurasi `.env`

Semua key eksternal **opsional**. Tanpa key, fitur terkait menampilkan status "belum lengkap" atau "AI belum aktif", bukan error.

| Variabel | Fungsi |
|---|---|
| `APP_KEY` | Wajib. Generate dengan `php artisan key:generate --show` |
| `APP_URL` | URL publik aplikasi |
| `DB_CONNECTION` | `sqlite` (default), `mysql`, atau `pgsql` |
| `DB_URL` / `DB_HOST` / `DB_PORT` / `DB_DATABASE` / `DB_USERNAME` / `DB_PASSWORD` | Koneksi database non-SQLite |
| `GOOGLE_SAFE_BROWSING_KEY` | Cek reputasi tautan lewat Google Safe Browsing |
| `VIRUSTOTAL_API_KEY` | Cek tautan dan hash file lewat VirusTotal (gratis: 4 permintaan/menit, 500/hari) |
| `GEMINI_API_KEY` | Analisis AI dan penjelasan tautan lewat Gemini |
| `GEMINI_MODEL` | Model Gemini yang dipakai |
| `GEMINI_THINKING_LEVEL` | Opsional |
| `ANTHROPIC_API_KEY` | Analisis AI lewat Claude |
| `JEDA_AI_MODEL` | Model Anthropic (default `claude-haiku-5-5`) |
| `JEDA_AI_PROVIDER` | `gemini` atau `anthropic` untuk memaksa penyedia. Kosong = pakai yang key-nya terisi (Gemini diprioritaskan) |

Jangan pernah commit `.env` atau key API ke repo.

---

## Deploy ke Railway

1. Push repo ke GitHub, lalu buat project di Railway dari repo tersebut.
2. Tambahkan **Variables**:
   ```env
   APP_ENV=production
   APP_DEBUG=false
   APP_KEY=base64:...            # dari: php artisan key:generate --show
   APP_URL=https://domain-kamu.up.railway.app
   ASSET_URL=https://domain-kamu.up.railway.app
   ```
   Tambahkan juga key API opsional dari tabel di atas.
3. Railway menghentikan HTTPS di proxy. Supaya Laravel membuat URL `https://` (CSS/JS tidak diblokir sebagai *mixed content*), trust proxy **sudah diaktifkan** di `bootstrap/app.php` (`$middleware->trustProxies(at: '*')`), jadi tidak perlu diubah lagi.
4. Jalankan migrasi saat deploy: `php artisan migrate --force`.
5. Pastikan document root web server adalah folder `public/`.

**Soal database di Railway:** SQLite disimpan di filesystem container yang **hilang saat redeploy** (kecuali memakai Volume). Karena sesi, cache, dan progres pengguna semuanya tersimpan di database, untuk produksi sebaiknya tambahkan **PostgreSQL/MySQL** dari Railway dan set `DB_CONNECTION` beserta variabel koneksinya, atau pasang Volume untuk file SQLite.

---

## Endpoint API Internal

Semua di bawah prefix `/jeda/api`, hanya `POST`/`GET` dari halaman Jeda sendiri.

| Method | Path | Fungsi | Throttle |
|---|---|---|---|
| POST | `/analisis-pesan` | Catat hasil Bedah Pesan lokal (skor + indikator) | - |
| POST | `/bedah-ai` | Analisis AI untuk Bedah Pesan | 10/menit |
| POST | `/cek-tautan` | Pemeriksaan tautan lengkap | 10/menit per IP |
| POST | `/cek-tautan/penjelasan` | Penjelasan AI hasil Cek Tautan | 5/menit per IP |
| POST | `/cek-file` | Pemeriksaan file (ringkasan + SHA-256) | 10/menit per IP |
| POST | `/simulasi` | Simpan hasil simulasi (tersedia di API, belum dipakai tampilan saat ini) | - |
| GET | `/quiz-progress` | Progres kuis dan level | - |
| POST | `/quiz-attempt` | Simpan hasil satu ronde kuis | 30/menit |
| POST | `/checklist` | Simpan checklist Jam Emas / Keluarga | - |
| POST | `/aksi-bantuan` | Catat aksi bantuan | - |
| GET | `/progres` | Ringkasan progres semua modul | - |

---

## Database

Tabel buatan Jeda (semua dikaitkan lewat `session_key`, bukan akun):

| Tabel | Isi |
|---|---|
| `jeda_message_analyses` | Skor, level risiko, id indikator, panjang teks (bukan isi pesan) |
| `jeda_link_checks` | Hostname, skor, sinyal, hasil per pemeriksaan, penjelasan AI, umur domain |
| `jeda_file_checks` | Ekstensi, jenis terdeteksi, ukuran, skor, sinyal |
| `jeda_simulation_attempts` | Skenario, opsi dipilih, skor |
| `jeda_quiz_attempts` | Level, tanggal, skor, jawaban, EXP yang didapat |
| `jeda_checklist_items` | Checklist per fitur dan situasi |
| `jeda_support_actions` | Aksi bantuan yang diklik pengguna |

Ditambah tabel bawaan Laravel (`users`, `sessions`, `cache`, `jobs`).

---

## Testing

```bash
php artisan test
# atau
composer test
```

Cakupan utama: `RiskScorer`, `UrlHeuristics`, `RedirectTracer`, `NetworkGuard`, cek tautan, cek file, penjelasan Gemini, kuis Latihan, dan `QuizProgression`.

---

## Privasi dan Keamanan

- **Tanpa akun.** Data dipisah per sesi browser; kunci sesi di-hash sebelum disimpan.
- **Isi pesan tidak disimpan.** Server hanya mencatat skor, id indikator, dan panjang teks.
- **Isi file tidak diunggah.** Hanya ringkasan dan SHA-256.
- **Data pribadi disamarkan** (nomor HP, rekening, email) sebelum teks dikirim ke API AI.
- **Perlindungan SSRF.** `NetworkGuard` memblokir akses ke jaringan internal saat server membuka tautan.
- **Pembatasan laju** pada endpoint yang memakai API berkuota.
- **Prompt AI** memperlakukan isi pesan sebagai data, bukan perintah.
- **Kegagalan tidak berarti aman.** Pemeriksaan yang tidak lengkap ditandai "belum lengkap".

---

## Batasan

- Skor Bedah Pesan berbasis aturan, bukan machine learning, sehingga penipuan baru bisa lolos.
- Daftar merek dan domain resmi di `config/jeda_cek.php` adalah contoh awal; **verifikasi ulang sebelum dipakai di demo atau produksi**.
- Hasil AI bisa salah dan selalu diberi label.
- **Progres kuis terikat sesi browser.** Level dan EXP bisa hilang jika cookie dihapus, memakai browser atau perangkat lain, atau sesi kedaluwarsa (bawaan 120 menit tanpa aktivitas; atur lewat `SESSION_LIFETIME`).
- **Soal kuis disusun dari pengetahuan umum tentang modus penipuan digital** dan belum diverifikasi dengan sumber resmi (misalnya OJK, BSSN, atau Komdigi). Perlakukan sebagai bahan latihan, bukan rujukan hukum.
- Jeda tidak menjamin sebuah tautan, file, atau pesan 100% aman. Selalu verifikasi lewat kanal resmi.

---

**Jeda — Berhenti sejenak, periksa, lalu bertindak dengan lebih aman.**
